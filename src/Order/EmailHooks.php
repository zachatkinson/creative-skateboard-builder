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
use CreativeBranding\SkateboardBuilder\Service\OrderedBuild;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Order;
use WC_Order_Item_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Orders, for the customer (roadmap, milestone 8). Each build is read through `OrderLines`, as the Build box and the
 * ticket read it, from the kit item's snapshot (D-133): the parts as sold, never the kit as it is now.
 *
 * - **The picture,** under the kit's line on the order's page and in an HTML email, with the build in words as its alt
 *   text; in a plain-text email, the words alone. `woocommerce_order_item_meta_end`
 *   (templates/emails/email-order-items.php:148 and :210, the two layouts; templates/emails/plain/email-order-items.php:112;
 *   templates/order/order-details-item.php). The picture is the one made for emails; where it is not made yet, it is
 *   asked for as a page asks (D-205) and the line goes without.
 * - **Lead time and returns notice,** after the order table, once each for the order, as the shopper read them at the
 *   review. `woocommerce_email_after_order_table` (templates/emails/email-order-details.php).
 * - **Parts under their kit:** a class on each part's row, `woocommerce_order_item_class`
 *   (templates/order/order-details-item.php:26, templates/emails/email-order-items.php:59), and the emails' styles
 *   indent it.
 * - **Language.** The words are those of the email as WooCommerce writes it: the site's language for a customer's
 *   email (includes/emails/class-wc-email.php:443), or the one a translation plugin chooses there. Slot labels follow
 *   it; the part names are as sold, as WooCommerce's own lines' are (D-222).
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
	 * @param Closure(): ComposedPictures $pictures The builds' pictures, built when an order has a build in it.
	 * @param Closure(): WorkshopView     $view     How a build is named, as the Build box names it.
	 */
	public function __construct( private Closure $pictures, private Closure $view ) {}


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
	 * Under a kit's line, in an email or on the order's own page (spec: Orders): the build's picture, with a short alt
	 * since its parts are listed below it, or the build in words in a plain-text email.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $id    The item's ID.
	 * @param mixed $item  The order item.
	 * @param mixed $order The order.
	 * @param mixed $plain Whether the email is plain text.
	 */
	public function picture( mixed $id, mixed $item, mixed $order = null, mixed $plain = false ): void {
		unset( $id );
		$build = self::build( $item, $order );
		if ( null === $build ) {
			return;
		}
		if ( true === $plain ) {
			$words = WorkshopWords::described( ( $this->view )()->named( $build ) );
			// Plain text is not escaped for HTML: WooCommerce's clean-up drops an entity it does not know, such as
			// &#039;, and French would lose its apostrophes (class-wc-email.php:216, :244).
			echo "\n" . wp_kses( $words, array() ) . "\n";
			return;
		}
		$parts = array();
		foreach ( $build->picks as $slot => $pick ) {
			$parts[ $slot ] = $pick->part;
		}
		$url = ( $this->pictures )()->url( $build->kit, $parts, ComposedPictures::EMAIL );
		if ( null === $url ) {
			( $this->pictures )()->ask( $build->kit, $parts, ComposedPictures::FROM_PAGE );
			return;
		}
		// Made at 600 pixels square, shown at half that, so it is sharp on a dense screen and fits a phone's mail app.
		// The parts are listed under the kit's line, so the alt says what the picture is and does not read them all
		// again; not empty, so a mail app that blocks images still shows something (Cowork's 0.8.0 review, F4).
		printf(
			'<div class="cbuild-email-picture" style="margin-top:8px"><img src="%1$s" alt="%2$s" width="300" height="300" style="display:block;max-width:100%%;height:auto;border:0" /></div>',
			esc_url( $url ),
			esc_attr__( 'The build, as drawn', 'creative-skateboard-builder' )
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
		foreach ( OrderLines::read( $order )->builds as $build ) {
			foreach ( $build->notes as $note ) {
				$said[ $note ] = $note;
			}
		}
		if ( array() === $said ) {
			return;
		}
		if ( true === $plain ) {
			echo "\n" . wp_kses( implode( "\n\n", $said ), array() ) . "\n\n";
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
		// Physical padding: Outlook reads no logical property.
		$side = is_rtl() ? 'right' : 'left';
		return is_string( $css ) ? $css . "\n.cbuild-order-part td:first-child, .cbuild-order-part th:first-child { padding-" . $side . ": 24px; }\n" : $css;
	}

	/**
	 * The build whose kit is this line, read from its snapshot; null for any other line, or a build whose snapshot does
	 * not read, since its picture cannot be drawn and the Build box already says what is wrong.
	 *
	 * @param mixed $item  The order item.
	 * @param mixed $order The order.
	 */
	private static function build( mixed $item, mixed $order ): ?OrderedBuild {
		if ( ! $item instanceof WC_Order_Item_Product || Options::ROLE_KIT !== $item->get_meta( Options::ORDER_ROLE ) ) {
			return null;
		}
		$order = $order instanceof WC_Order ? $order : $item->get_order();
		if ( ! $order instanceof WC_Order ) {
			return null;
		}
		foreach ( OrderLines::read( $order )->builds as $build ) {
			if ( $build->item === $item->get_id() ) {
				return $build->read && $build->kit > 0 ? $build : null;
			}
		}
		return null;
	}
}
