<?php
/**
 * The assembly ticket: an admin page that prints.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use Automattic\WooCommerce\Utilities\OrderUtil;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The assembly ticket of one order or many, one board to a page (spec: Pricing, stock, assembly and returns,
 * Assembly; Admin, On orders): the order's number, the customer, each slot's part and SKU with the specs that matter,
 * the customer's note, and a tick box per step. A page of its own at `admin-post.php`, with its own print stylesheet
 * and no PDF, behind `edit_shop_orders` and a nonce. It reads orders and changes nothing (D-YYY).
 *
 * @since 0.8.0
 *
 * @phpstan-import-type View from WorkshopView
 * @phpstan-import-type Shown from WorkshopView
 */
final class TicketPage {

	/**
	 * The page's action, `admin-post.php?action=cbuild_print_tickets`, and the orders list's bulk action of the same
	 * name; also the nonce's action.
	 *
	 * @since 0.8.0
	 */
	public const string ACTION = 'cbuild_print_tickets';

	/**
	 * The most orders one page prints, so a bulk print of a long list stays one page's work.
	 *
	 * @since 0.8.0
	 */
	public const int MOST = 100;

	/**
	 * The style and script handles of the page.
	 *
	 * @since 0.8.0
	 */
	public const string HANDLE = 'cbuild-tickets';

	/**
	 * The page's stylesheet: plain on screen, and on paper one board to a page with the tools hidden.
	 */
	private const string STYLES = 'body.cbuild-tickets{margin:24px;background:#fff;color:#000;font:14px/1.45 -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif}.cbuild-tickets__tools{margin:0 0 24px;padding:0 0 16px;border-block-end:1px solid #888}.cbuild-ticket{max-inline-size:48rem;margin:0 0 48px;break-after:page;page-break-after:always}.cbuild-ticket:last-child{break-after:auto;page-break-after:auto}.cbuild-ticket h2{margin:0 0 4px;font-size:1.5em}.cbuild-ticket h3{margin:16px 0 8px;font-size:1.1em}.cbuild-ticket__facts{display:grid;grid-template-columns:max-content 1fr;gap:4px 16px;margin:8px 0}.cbuild-ticket__facts dt{font-weight:600}.cbuild-ticket__facts dd{margin:0}.cbuild-ticket table{inline-size:100%;border-collapse:collapse}.cbuild-ticket th,.cbuild-ticket td{border:1px solid #000;padding:4px 8px;text-align:start;vertical-align:top}.cbuild-ticket__specs{margin:0;padding:0;list-style:none}.cbuild-ticket__warning{border:2px solid #000;padding:4px 12px;margin:8px 0}.cbuild-ticket__steps{margin:0;padding:0;list-style:none}.cbuild-ticket__steps li{display:flex;gap:8px;align-items:flex-start;margin:0 0 8px}.cbuild-ticket__steps input{inline-size:1.25em;block-size:1.25em;margin:0;flex:none}.cbuild-ticket .cbuild-order__board{block-size:12rem}.cbuild-ticket .cbuild-order__picture{margin:8px 0}@media print{body.cbuild-tickets{margin:0}.cbuild-tickets__tools{display:none}.cbuild-ticket{margin:0}}';

	/**
	 * Shows the Print button, which needs a script, and prints when it is pressed. It carries no data.
	 */
	private const string SCRIPT = 'document.querySelectorAll(".cbuild-tickets__print").forEach(function(b){b.hidden=false;b.addEventListener("click",function(){window.print();});});';

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param WorkshopView $view What a ticket shows of an order.
	 */
	public function __construct( private WorkshopView $view ) {}

	/**
	 * The page's address for some orders, with its nonce: the first MOST of them, and how many more were picked.
	 *
	 * @since 0.8.0
	 *
	 * @param list<int> $orders The orders' IDs.
	 */
	public static function url( array $orders ): string {
		$more = max( 0, count( $orders ) - self::MOST );
		$args = array(
			'action' => self::ACTION,
			'orders' => implode( ',', array_slice( $orders, 0, self::MOST ) ),
		);
		if ( $more > 0 ) {
			$args['more'] = (string) $more;
		}
		$args['_wpnonce'] = wp_create_nonce( self::ACTION );
		return add_query_arg( $args, admin_url( 'admin-post.php' ) );
	}

	/**
	 * The orders the address names: positive IDs, each once, in its order, at most MOST of them; and how many more
	 * were picked, those it names past MOST and those its `more` says the bulk action left out.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $asked The `orders` parameter: IDs separated by commas.
	 * @param mixed $more  The `more` parameter.
	 * @return array{list<int>, int} The IDs, and how many more were picked.
	 */
	public static function ids( mixed $asked, mixed $more = '' ): array {
		$ids = array();
		foreach ( explode( ',', is_string( $asked ) ? $asked : '' ) as $id ) {
			$id = trim( $id );
			if ( ctype_digit( $id ) && (int) $id > 0 ) {
				$ids[ (int) $id ] = (int) $id;
			}
		}
		$ids  = array_values( $ids );
		$left = is_string( $more ) && ctype_digit( $more ) ? (int) $more : 0;
		return array( array_slice( $ids, 0, self::MOST ), max( 0, count( $ids ) - self::MOST ) + $left );
	}

	/**
	 * The page's tickets, one to a board: each build of each order, as many times as the order has boards of it.
	 *
	 * @since 0.8.0
	 *
	 * @param list<View> $orders The orders, as the workshop reads them.
	 * @return list<array{id: string, order: View, build: Shown, which: string, problems: list<string>}>
	 */
	public static function pages( array $orders ): array {
		$pages = array();
		foreach ( $orders as $order ) {
			foreach ( $order['builds'] as $build ) {
				for ( $at = 1; $at <= $build['boards']; $at++ ) {
					$pages[] = array(
						'id'       => 'cbuild-ticket-' . ( count( $pages ) + 1 ),
						'order'    => $order,
						'build'    => $build,
						'which'    => WorkshopWords::board( $at, $build['boards'] ),
						'problems' => array( ...$order['problems'], ...$build['problems'] ),
					);
				}
			}
		}
		return $pages;
	}

	/**
	 * Draw the page, for a user with `edit_shop_orders` and the page's nonce; refuse anyone else with WordPress's
	 * own error page and a 403.
	 *
	 * @since 0.8.0
	 */
	public function print(): void {
		if ( ! current_user_can( 'edit_shop_orders' ) ) {
			wp_die( esc_html__( 'You are not allowed to print assembly tickets.', 'creative-skateboard-builder' ), '', array( 'response' => 403 ) );
		}
		$nonce = isset( $_GET['_wpnonce'] ) && is_string( $_GET['_wpnonce'] ) ? sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ) : '';
		if ( false === wp_verify_nonce( $nonce, self::ACTION ) ) {
			wp_die( esc_html__( 'This link to the assembly tickets has expired. Go back to the order and print it again.', 'creative-skateboard-builder' ), '', array( 'response' => 403 ) );
		}
		[ $ids, $more ] = self::ids(
			isset( $_GET['orders'] ) && is_string( $_GET['orders'] ) ? sanitize_text_field( wp_unslash( $_GET['orders'] ) ) : '',
			isset( $_GET['more'] ) && is_string( $_GET['more'] ) ? sanitize_text_field( wp_unslash( $_GET['more'] ) ) : ''
		);
		$orders         = array();
		$skipped        = array();
		foreach ( $ids as $id ) {
			$order = wc_get_order( $id );
			$view  = $order instanceof WC_Order ? $this->view->of( $order ) : null;
			if ( null === $view || array() === $view['builds'] ) {
				$skipped[] = null === $view ? (string) $id : $view['number'];
				continue;
			}
			$orders[] = $view;
		}
		$pages = self::pages( $orders );
		$most  = self::MOST;
		$back  = wp_get_referer();
		$back  = false === $back ? admin_url( OrderUtil::custom_orders_table_usage_is_enabled() ? 'admin.php?page=wc-orders' : 'edit.php?post_type=shop_order' ) : $back;
		if ( ! headers_sent() ) {
			nocache_headers();
			header( 'Content-Type: text/html; charset=' . Cast::str( get_option( 'blog_charset' ), 'UTF-8' ) );
		}
		wp_register_style( self::HANDLE, false, array(), CBUILD_VERSION );
		wp_add_inline_style( self::HANDLE, OrderBoard::STYLES . self::STYLES );
		wp_register_script( self::HANDLE, false, array(), CBUILD_VERSION, true );
		wp_add_inline_script( self::HANDLE, self::SCRIPT );
		$handle = self::HANDLE;
		include CBUILD_PATH . 'templates/admin/tickets.php';
	}
}
