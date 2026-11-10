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
 * The page is the shop's own tools for the builder, a tab each: the makers' charts, the import and export, and
 * the Catalogue check. Its screen is built when the page is asked for, not on every admin request.
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
	 * The Import and export tab: `admin.php?page=cbuild&tab=import`.
	 *
	 * @since 0.3.0
	 */
	public const TAB_IMPORT = 'import';

	/**
	 * The Catalogue check's tab: `admin.php?page=cbuild&tab=check`.
	 *
	 * @since 0.3.0
	 */
	public const TAB_CHECK = 'check';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): SpecTablesScreen $tables Builds the Spec tables screen.
	 * @param Closure(): ImportScreen     $import Builds the Import and export screen.
	 * @param Closure(): CheckScreen      $check  Builds the Catalogue check's screen.
	 */
	public function __construct( private Closure $tables, private Closure $import, private Closure $check ) {}

	/**
	 * The page's tabs, for its templates to draw.
	 *
	 * @since 0.3.0
	 *
	 * @param string $current The tab being drawn: '' for Spec tables.
	 * @return list<array{label: string, url: string, current: bool}>
	 */
	public static function tabs( string $current ): array {
		$tabs = array(
			''               => __( 'Spec tables', 'creative-skateboard-builder' ),
			self::TAB_IMPORT => __( 'Import and export', 'creative-skateboard-builder' ),
			self::TAB_CHECK  => __( 'Catalogue check', 'creative-skateboard-builder' ),
		);
		$list = array();
		foreach ( $tabs as $tab => $label ) {
			$list[] = array(
				'label'   => $label,
				'url'     => add_query_arg(
					array_filter(
						array(
							'page' => self::PAGE,
							'tab'  => $tab,
						) 
					),
					admin_url( 'admin.php' ) 
				),
				'current' => $tab === $current,
			);
		}
		return $list;
	}

	/**
	 * The screen the address asks for.
	 */
	private function screen(): SpecTablesScreen|ImportScreen|CheckScreen {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses what is shown and changes nothing.
		$tab = isset( $_GET['tab'] ) && is_string( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : '';
		if ( self::TAB_CHECK === $tab ) {
			return ( $this->check )();
		}
		return self::TAB_IMPORT === $tab ? ( $this->import )() : ( $this->tables )();
	}

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
		$screen = $this->screen();
		$screen->styles();
		$file = $screen instanceof ImportScreen ? $screen->download() : null;
		if ( null !== $file ) {
			nocache_headers();
			header( 'Content-Type: text/csv; charset=utf-8' );
			header( 'Content-Disposition: attachment; filename="part-specs.csv"' );
			header( 'X-Content-Type-Options: nosniff' );
			// phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- A CSV file sent as an attachment, not HTML; each cell went through Csv::line().
			echo $file;
			exit;
		}
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
		$this->screen()->render();
	}
}
