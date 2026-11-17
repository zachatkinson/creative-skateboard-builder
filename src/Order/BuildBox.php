<?php
/**
 * The Build box on the order screen.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The order's builds by slot, as sold, with the picture's layers, the mismatch said before anyone prints, the refund
 * help and a link to the assembly ticket (spec: Admin, On orders). Registered for `wc_get_page_screen_id( 'shop-order' )`
 * so it shows with HPOS on or off, for users with `edit_shop_orders`, and only on an order with a build or a line that
 * should have been one. It reads the order and changes nothing.
 *
 * @since 0.8.0
 *
 * @phpstan-import-type View from WorkshopView
 */
final class BuildBox {

	/**
	 * The box's ID.
	 *
	 * @since 0.8.0
	 */
	public const string ID = 'cbuild-build';

	/**
	 * The capability that sees a build on an order and prints its tickets (spec: Admin, the capability table).
	 *
	 * @since 0.8.0
	 */
	public const string CAPABILITY = 'edit_shop_orders';

	/**
	 * The style handle of the box's few rules.
	 *
	 * @since 0.8.0
	 */
	public const string STYLE = 'cbuild-order';

	/**
	 * The box's rules beside the board's.
	 */
	private const string STYLES = '.cbuild-order__build{margin:0 0 16px}.cbuild-order__parts{display:flex;flex-wrap:wrap;gap:16px;align-items:flex-start}.cbuild-order__parts .cbuild-order__scroll{flex:1 1 32rem;overflow-x:auto}.cbuild-order__specs{margin:0;padding:0;list-style:none}.cbuild-order__picture{margin:0;flex:0 0 auto}.cbuild-order__picture figcaption{max-inline-size:16rem}';

	/**
	 * What the box shows of the order it was added for, by the order's ID.
	 *
	 * @var array<int, View>
	 */
	private array $shown = array();

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param WorkshopView $view What the box shows of an order.
	 */
	public function __construct( private WorkshopView $view ) {}

	/**
	 * Add the box to the order's screen. WooCommerce's HPOS screen hands over its screen ID and the order
	 * (src/Internal/Admin/Orders/Edit.php:170); WordPress's post screen the post type, which is the screen's ID there,
	 * and the post (wp-admin/includes/meta-boxes.php:1692).
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $screen The screen's ID, or the post type.
	 * @param mixed $subject The order, or its post.
	 */
	public function add( mixed $screen, mixed $subject = null ): void {
		if ( wc_get_page_screen_id( 'shop-order' ) !== $screen || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$order = self::order( $subject );
		if ( null === $order ) {
			return;
		}
		$view = $this->view->of( $order );
		if ( array() === $view['builds'] && array() === $view['problems'] ) {
			return;
		}
		$this->shown[ $order->get_id() ] = $view;
		add_meta_box( self::ID, __( 'Build', 'creative-skateboard-builder' ), array( $this, 'render' ), $screen, 'normal', 'default' );
		wp_register_style( self::STYLE, false, array(), CBUILD_VERSION );
		wp_add_inline_style( self::STYLE, OrderBoard::STYLES . self::STYLES );
		wp_enqueue_style( self::STYLE );
	}

	/**
	 * Draw the box.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $subject The order, or its post.
	 */
	public function render( mixed $subject = null ): void {
		$order = self::order( $subject );
		if ( null === $order || ! current_user_can( 'edit_shop_orders' ) ) {
			return;
		}
		$view   = $this->shown[ $order->get_id() ] ?? $this->view->of( $order );
		$print  = array() === $view['builds'] ? '' : TicketPage::url( array( $order->get_id() ) );
		$refund = $view['assembles'] ? WorkshopWords::refund() : '';
		include CBUILD_PATH . 'templates/admin/order-build.php';
	}

	/**
	 * The order a screen hands over: the order itself, or the post that holds it.
	 *
	 * @param mixed $subject The order, or its post.
	 */
	private static function order( mixed $subject ): ?WC_Order {
		$order = $subject instanceof WC_Order ? $subject : ( is_object( $subject ) && isset( $subject->ID ) ? wc_get_order( $subject->ID ) : false );
		return $order instanceof WC_Order ? $order : null;
	}
}
