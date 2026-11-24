<?php
/**
 * "Build a complete with this deck" on a deck's own page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The way in from a deck's own product page (spec: Ways in that skip the hunt, D-253): a button after its add-to-cart
 * form, `woocommerce_after_add_to_cart_form` (WooCommerce 11.3.0, templates/single-product/add-to-cart/simple.php:54,
 * variable.php:125, external.php:32, grouped.php:145), that opens a kit's builder with the deck picked. The deck brings
 * its width, so the builder skips the question.
 *
 * - **Which kit.** One whose first slot offers the product, or one of its variations, in stock, and whose Build tab
 *   leaves Show on its parts' pages on (1.2.0, #115); where more than one does, the first by menu order, then by ID.
 * - **Which deck.** The product, or its first variation the kit offers in stock; on a variable product's page, the
 *   variation the shopper chooses in its form, where the kit offers it in stock (the spec reviewer's M10 minor M3).
 * - **What a shop changes.** `cbuild_start_button`.
 *
 * @since 1.0.0
 */
final class StartButton implements Hookable {

	/**
	 * Where the button goes unless a shop moves it.
	 */
	private const string HOOK = 'woocommerce_after_add_to_cart_form';

	/**
	 * Follows the variation chosen in WooCommerce's form: its `found_variation` and `reset_data` events
	 * (assets/js/frontend/add-to-cart-variation.js:318, :320 in 11.3.0) carry no DOM event, so jQuery's are read. The
	 * links are the server's, one a variation, in the button's `data-cbuild-starts`; a variation not among them keeps
	 * the button's own.
	 */
	private const string FOLLOW = 'jQuery(function($){$(".variations_form").on("found_variation reset_data",function(e,v){$(".cbuild-start__link[data-cbuild-starts]").each(function(){var a=$(this),s=a.data("cbuildStarts")||{},d=a.data("cbuildStart");a.attr("href",v&&s[v.variation_id]?s[v.variation_id]:d);});});});';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Closure(): Kits $kits The kits and their parts, built on a product's page alone.
	 */
	public function __construct( private Closure $kits ) {}

	/**
	 * Where each kit's builder is, as its Build tab chose (1.2.0, #113): none in a copy an earlier release's container
	 * built, which asks `BuilderUrl::of()` as 1.1.0 did (D-208).
	 *
	 * @var BuilderAddress|null
	 */
	private ?BuilderAddress $address = null;

	/**
	 * The same, sending shoppers to the Builder page each kit's Build tab chose (#113, D-348). Only this release's
	 * container calls it (D-208).
	 *
	 * @since 1.2.0
	 *
	 * @param BuilderAddress $address Where each kit's builder is.
	 */
	public function withAddress( BuilderAddress $address ): self {
		$copy          = clone $this;
		$copy->address = $address;
		return $copy;
	}

	/**
	 * Attach hooks: the button is placed once the request knows its product, so a shop's filter can move it.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'wp', array( $this, 'place' ) );
	}

	/**
	 * On a product's page, the button where the shop wants it, if any kit offers the product first.
	 *
	 * @since 1.0.0
	 */
	public function place(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$product = get_queried_object_id();
		$start   = $this->start( $product );
		$button  = null === $start ? false : $start + array(
			'text'     => __( 'Build a complete with this deck', 'creative-skateboard-builder' ),
			'hook'     => self::HOOK,
			'priority' => 10,
		);
		/**
		 * Filters "Build a complete with this deck" on a product's page. The button is an array with `kit` (the kit
		 * product's ID), `part` (the product or variation it opens picked), `slot` (the kit's slot it is picked in: the first, or another of the kit's slots), `text`, `hook` (the action it prints on,
		 * `woocommerce_after_add_to_cart_form` by default) and `priority`; or false where no kit offers the product in
		 * its first slot. Return false to leave the button out, or an array to choose the kit, move it or word it. A
		 * kit a visitor may not see shows no button, and the builder checks the part as it checks any link.
		 *
		 * @since 1.0.0
		 *
		 * @param array{kit: int, part: int, slot: string, text: string, hook: string, priority: int}|false $button  The button, or false.
		 * @param int                                                                                       $product The product's ID.
		 */
		$said = apply_filters( 'cbuild_start_button', $button, $product );
		if ( ! is_array( $said ) ) {
			return;
		}
		$kit  = Cast::int( $said['kit'] ?? null );
		$part = Cast::int( $said['part'] ?? null );
		$text = trim( wp_strip_all_tags( Cast::str( $said['text'] ?? null ) ) );
		$hook = Cast::str( $said['hook'] ?? null );
		$kits = ( $this->kits )();
		$read = $kit > 0 && $kits->isPublic( $kit ) ? $kits->kit( $kit ) : null;
		$slot = null === $read ? null : ( $read->slots->get( Cast::str( $said['slot'] ?? null ) ) ?? $read->slots->all()[0] ?? null );
		if ( null === $read || null === $slot || $part < 1 || '' === $text || '' === $hook ) {
			return;
		}
		$base   = $this->address?->of( $kit, $read ) ?? BuilderUrl::of( $kit );
		$url    = ( new BuildLink() )->pick( $slot->key, $part )->address( $base, $read->slots );
		$starts = $this->starts( $product, $kit, $slot, $read, $base );
		add_action(
			$hook,
			static function () use ( $url, $text, $starts ): void {
				if ( array() === $starts ) {
					printf( '<p class="cbuild-start"><a class="button cbuild-start__link" href="%1$s" rel="nofollow">%2$s</a></p>', esc_url( $url ), esc_html( $text ) );
					return;
				}
				printf(
					'<p class="cbuild-start"><a class="button cbuild-start__link" href="%1$s" rel="nofollow" data-cbuild-start="%1$s" data-cbuild-starts="%2$s">%3$s</a></p>',
					esc_url( $url ),
					esc_attr( (string) wp_json_encode( array_map( 'esc_url_raw', $starts ) ) ),
					esc_html( $text )
				);
			},
			Cast::int( $said['priority'] ?? 10 )
		);
		if ( array() !== $starts ) {
			// After WooCommerce registers its variation script (class-wc-frontend-scripts.php:56, :238 in 11.3.0).
			add_action(
				'wp_enqueue_scripts',
				static function (): void {
					wp_add_inline_script( 'wc-add-to-cart-variation', self::FOLLOW );
				},
				20
			);
		}
	}

	/**
	 * On a variable product's page, the builder's link for each of its variations the slot offers in stock, by ID.
	 *
	 * @param int    $product The product's ID.
	 * @param int    $kit     The kit product's ID.
	 * @param Slot   $slot    The slot the deck is picked in.
	 * @param Kit    $read    The kit.
	 * @param string $base    The builder's address for the kit.
	 * @return array<int, string>
	 */
	private function starts( int $product, int $kit, Slot $slot, Kit $read, string $base ): array {
		$found = wc_get_product( $product );
		if ( ! $found instanceof \WC_Product_Variable ) {
			return array();
		}
		$children = array_map( Cast::int( ... ), $found->get_children() );
		$starts   = array();
		foreach ( ( $this->kits )()->offered( $kit, $slot ) as $part ) {
			if ( $part->inStock && in_array( $part->id, $children, true ) ) {
				$starts[ $part->id ] = ( new BuildLink() )->pick( $slot->key, $part->id )->address( $base, $read->slots );
			}
		}
		return $starts;
	}

	/**
	 * The kit and the part the button opens, or null where no kit offers the product first.
	 *
	 * @param int $product The product's ID.
	 * @return array{kit: int, part: int, slot: string}|null
	 */
	private function start( int $product ): ?array {
		$found = wc_get_product( $product );
		if ( ! $found instanceof \WC_Product ) {
			return null;
		}
		$ids   = array( $product, ...array_map( Cast::int( ... ), $found->get_children() ) );
		$every = ( $this->kits )();
		// Asked once, of the product: its variations are in its categories, brands and attributes. A kit that takes it
		// in its first slot then finds its parts among what is kept of the slot's, with no product read (the auditor's
		// M10 review, 4).
		$kits = array_keys( $every->all() );
		$rank = array();
		foreach ( $kits as $kit ) {
			$rank[ $kit ] = (int) get_post_field( 'menu_order', $kit );
		}
		usort( $kits, static fn( int $a, int $b ): int => array( $rank[ $a ], $a ) <=> array( $rank[ $b ], $b ) );
		foreach ( $kits as $kit ) {
			$read = $every->isPublic( $kit ) ? $every->kit( $kit ) : null;
			$slot = null === $read ? null : ( $read->slots->all()[0] ?? null );
			// A kit whose Show on its parts' pages is off is passed over, and the next that offers the part places it
			// (#115, D-349).
			if ( null === $read || null === $slot || ! $read->settings->startButton ) {
				continue;
			}
			if ( ! in_array( $slot->key, $every->slotsOf( $product, $read ), true ) ) {
				continue;
			}
			foreach ( $every->offered( $kit, $slot ) as $part ) {
				if ( $part->inStock && in_array( $part->id, $ids, true ) ) {
					return array(
						'kit'  => $kit,
						'part' => $part->id,
						'slot' => $slot->key,
					);
				}
			}
		}
		return null;
	}
}
