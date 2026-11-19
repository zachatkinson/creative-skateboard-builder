<?php
/**
 * "Build a complete with this deck" on a deck's own page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
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
 * - **Which kit.** One whose first slot offers the product, or one of its variations, in stock; where more than one
 *   does, the first by menu order, then by ID.
 * - **Which deck.** The product, or its first variation the kit offers in stock.
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
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Closure(): Kits $kits The kits and their parts, built on a product's page alone.
	 */
	public function __construct( private Closure $kits ) {}

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
		$url = ( new BuildLink() )->pick( $slot->key, $part )->address( BuilderUrl::of( $kit ), $read->slots );
		add_action(
			$hook,
			static function () use ( $url, $text ): void {
				printf( '<p class="cbuild-start"><a class="button cbuild-start__link" href="%1$s" rel="nofollow">%2$s</a></p>', esc_url( $url ), esc_html( $text ) );
			},
			Cast::int( $said['priority'] ?? 10 )
		);
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
			if ( null === $read || null === $slot ) {
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
