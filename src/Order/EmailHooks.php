<?php
/**
 * A build on the customer's order: its picture and words on the order and in the emails, its parts under its kit.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Order;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orders, for the customer (roadmap, milestone 8). Everything is read from the kit item's snapshot, written at
 * checkout in the language the order was placed in, and never from the kit as it is now (D-133).
 *
 * - **The picture,** under the kit's line on the order's page and in an HTML email, with the build in words as its alt text; in a plain-text
 *   email, the words alone. `woocommerce_order_item_meta_end` (templates/emails/email-order-items.php:148 and :210, the
 *   two layouts; templates/emails/plain/email-order-items.php:112). The picture is the one made for emails; where it
 *   is not made yet, it is asked for and the line goes without.
 * - **Lead time and returns notice,** after the order table, once each for the order, as the shopper read them at the
 *   review. `woocommerce_email_after_order_table` (templates/emails/email-order-details.php).
 * - **Parts under their kit:** a class on each part's row, `woocommerce_order_item_class`
 *   (templates/order/order-details-item.php:26, templates/emails/email-order-items.php:59), and the emails' styles
 *   indent it.
 *
 * @since 0.8.0
 */
final class EmailHooks implements Hookable {

	/**
	 * A part's row, under its kit.
	 */
	private const string INDENT = '.cbuild-order-part td:first-child, .cbuild-order-part th:first-child { padding-inline-start: 24px; }';

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param Closure(): ComposedPictures $pictures The builds' pictures, built when an email has a build in it.
	 */
	public function __construct( private Closure $pictures ) {}


	/**
	 * Attach hooks.
	 *
	 * @since 0.8.0
	 */
	public function register(): void {
		add_action( 'woocommerce_order_item_meta_end', array( $this, 'picture' ), 10, 4 );
		add_action( 'woocommerce_email_after_order_table', array( $this, 'notes' ), 10, 3 );
		add_filter( 'woocommerce_order_item_class', array( $this, 'rowClass' ), 10, 2 );
		add_filter( 'woocommerce_email_styles', array( $this, 'styles' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'pageStyles' ), 20 );
	}

	/**
	 * On an order's own page, in My account and after checkout: a part's row indented under its kit, beside
	 * WooCommerce's own styles.
	 *
	 * @since 0.8.0
	 */
	public function pageStyles(): void {
		if ( function_exists( 'is_account_page' ) && ( is_account_page() || is_order_received_page() ) ) {
			wp_add_inline_style( 'woocommerce-general', self::INDENT );
		}
	}

	/**
	 * Under a kit's line, in an email or on the order's own page (spec: Orders): the build's picture, with its words as
	 * the alt text, or the words alone in a plain-text email.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $id    The item's ID.
	 * @param mixed $item  The order item.
	 * @param mixed $order The order.
	 * @param mixed $plain Whether the email is plain text.
	 */
	public function picture( mixed $id, mixed $item, mixed $order = null, mixed $plain = false ): void {
		unset( $id, $order );
		$build = self::build( $item );
		if ( null === $build ) {
			return;
		}
		$words = self::words( $build['picks'] );
		if ( true === $plain ) {
			echo "\n" . esc_html( $words ) . "\n";
			return;
		}
		$url = ( $this->pictures )()->url( $build['kit'], $build['ids'], ComposedPictures::EMAIL );
		if ( null === $url ) {
			( $this->pictures )()->ask( $build['kit'], $build['ids'] );
			return;
		}
		// Made at 600 pixels square, shown at half that, so it is sharp on a dense screen and fits a phone's mail app.
		printf(
			'<div class="cbuild-email-picture" style="margin-top:8px"><img src="%1$s" alt="%2$s" width="300" height="300" style="display:block;max-width:100%%;height:auto;border:0" /></div>',
			esc_url( $url ),
			esc_attr( $words )
		);
	}

	/**
	 * After the order table: each assembled build's lead time and returns notice, as the shopper read them, each
	 * said once.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $order The order.
	 * @param mixed $admin Whether the email goes to the shop.
	 * @param mixed $plain Whether the email is plain text.
	 */
	public function notes( mixed $order, mixed $admin = false, mixed $plain = false ): void {
		unset( $admin );
		if ( ! $order instanceof WC_Order ) {
			return;
		}
		$said = array();
		foreach ( $order->get_items() as $item ) {
			$snapshot = $item instanceof WC_Order_Item_Product && Options::ROLE_KIT === $item->get_meta( Options::ORDER_ROLE ) ? Cast::map( $item->get_meta( Options::ORDER_SNAPSHOT ) ) : array();
			foreach ( array( 'lead_time', 'returns_notice' ) as $key ) {
				$text = trim( Cast::str( $snapshot[ $key ] ?? null ) );
				if ( '' !== $text ) {
					$said[ $text ] = $text;
				}
			}
		}
		if ( array() === $said ) {
			return;
		}
		if ( true === $plain ) {
			echo "\n" . esc_html( implode( "\n\n", $said ) ) . "\n\n";
			return;
		}
		echo '<div class="cbuild-email-notes" style="margin-bottom:40px">';
		foreach ( $said as $text ) {
			echo '<p>' . esc_html( $text ) . '</p>';
		}
		echo '</div>';
	}

	/**
	 * A part's row, marked as under its kit.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $classes The row's classes.
	 * @param mixed $item    The order item.
	 * @return mixed
	 */
	public function rowClass( mixed $classes, mixed $item = null ): mixed {
		if ( is_string( $classes ) && $item instanceof WC_Order_Item_Product && Options::ROLE_PART === $item->get_meta( Options::ORDER_ROLE ) ) {
			return trim( $classes . ' cbuild-order-part' );
		}
		return $classes;
	}

	/**
	 * The emails' styles, with a part's row indented under its kit.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $css WooCommerce's styles.
	 * @return mixed
	 */
	public function styles( mixed $css ): mixed {
		return is_string( $css ) ? $css . "\n" . self::INDENT . "\n" : $css;
	}

	/**
	 * A kit item's build, from its snapshot: the kit, the parts by slot, and each pick's label and name.
	 *
	 * @param mixed $item The order item.
	 * @return array{kit: int, ids: array<string, int>, picks: list<array{label: string, name: string}>}|null
	 */
	private static function build( mixed $item ): ?array {
		if ( ! $item instanceof WC_Order_Item_Product || Options::ROLE_KIT !== $item->get_meta( Options::ORDER_ROLE ) ) {
			return null;
		}
		$snapshot = Cast::map( $item->get_meta( Options::ORDER_SNAPSHOT ) );
		$ids      = array();
		$picks    = array();
		foreach ( Cast::map( $snapshot['picks'] ?? null ) as $slot => $pick ) {
			$pick = Cast::map( $pick );
			$part = Cast::int( $pick['part'] ?? null );
			if ( $part > 0 ) {
				$ids[ $slot ] = $part;
			}
			$picks[] = array(
				'label' => Cast::str( $pick['label'] ?? null ),
				'name'  => Cast::str( $pick['name'] ?? null ),
			);
		}
		$kit = Cast::int( $snapshot['kit'] ?? null );
		return $kit > 0 && array() !== $picks ? array(
			'kit'   => $kit,
			'ids'   => $ids,
			'picks' => $picks,
		) : null;
	}

	/**
	 * The build in words, part by part.
	 *
	 * @param list<array{label: string, name: string}> $picks The picks.
	 */
	private static function words( array $picks ): string {
		$parts = array();
		foreach ( $picks as $pick ) {
			/* translators: 1: a slot's label, such as Deck; 2: the part's name. */
			$parts[] = '' === $pick['label'] ? $pick['name'] : sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $pick['label'], $pick['name'] );
		}
		/* translators: %l: the build's parts, each with its slot, as a list in the site's language. */
		return wp_sprintf( __( 'Your build: %l', 'creative-skateboard-builder' ), $parts );
	}
}
