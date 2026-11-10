<?php
/**
 * Adds the Builder tab to WooCommerce's settings.
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
 * WooCommerce asks for its settings pages on the settings screen alone, through `woocommerce_get_settings_pages`
 * (includes/admin/class-wc-admin-settings.php:64 in 11.2.2), and only then is the class the tab extends loaded. So
 * the tab is built when asked for, not with this.
 *
 * @since 0.3.0
 */
final class SettingsHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): object $page Builds the tab.
	 */
	public function __construct( private Closure $page ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_settings_pages', array( $this, 'add' ), 10, 1 );
	}

	/**
	 * Add the tab after WooCommerce's own.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $pages WooCommerce's settings pages.
	 * @return mixed
	 */
	public function add( mixed $pages ): mixed {
		if ( is_array( $pages ) ) {
			$pages[] = ( $this->page )();
		}
		return $pages;
	}
}
