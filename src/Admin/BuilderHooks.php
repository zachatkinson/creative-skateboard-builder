<?php
/**
 * Adds WooCommerce > Builder to the admin menu.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The page is the shop's own tools for the builder: the makers' charts now, the import and the Catalogue check
 * after. Its screen is built when the page is asked for, not on every admin request.
 *
 * @since 0.3.0
 */
final class BuilderHooks implements Hookable {

	/**
	 * The page's slug: `admin.php?page=cbuild`.
	 *
	 * @since 0.3.0
	 */
	public const PAGE = 'cbuild';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): SpecTablesScreen $screen Builds the screen.
	 */
	public function __construct( private Closure $screen ) {}

	/**
	 * Attach.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		// After WooCommerce adds its own menu and its settings, at 9 and 50.
		add_action( 'admin_menu', array( $this, 'menu' ), 60, 0 );
	}

	/**
	 * Add the page under WooCommerce, for who can manage the shop.
	 *
	 * @since 0.3.0
	 */
	public function menu(): void {
		$hook = add_submenu_page(
			'woocommerce',
			__( 'Builder', 'creative-skateboard-builder' ),
			__( 'Builder', 'creative-skateboard-builder' ),
			'manage_woocommerce',
			self::PAGE,
			array( $this, 'render' )
		);
		if ( is_string( $hook ) ) {
			add_action( 'load-' . $hook, array( $this, 'load' ), 10, 0 );
		}
	}

	/**
	 * Before the page draws: act on a form that was sent, and leave for the page it leads to.
	 *
	 * @since 0.3.0
	 */
	public function load(): void {
		$screen = ( $this->screen )();
		$screen->styles();
		$next = $screen->handle();
		if ( null !== $next ) {
			wp_safe_redirect( $next );
			exit;
		}
	}

	/**
	 * Draw the page.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		( $this->screen )()->render();
	}
}
