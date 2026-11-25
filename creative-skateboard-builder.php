<?php
/**
 * Plugin Name:       Creative Skateboard Builder
 * Plugin URI:        https://creativebranding.ca
 * Description:       Sells complete skateboards built from parts the shop stocks, with fit checked from specs and each part on its own cart and order line.
 * Version:           1.2.0
 * Requires at least: 7.0
 * Requires PHP:      8.5
 * Requires Plugins:  woocommerce
 * Author:            Creative Branding
 * Author URI:        https://zachatkinson.com
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       creative-skateboard-builder
 * Domain Path:       /languages
 * Update URI:        https://raw.githubusercontent.com/zachatkinson/creative-releases/main/creative-skateboard-builder/info.json
 * WC requires at least: 11.2
 * WC tested up to:   11.3
 *
 * Bootstrap only: headers, constants, the floors check, the autoloader and the hooks into src/. This file must parse
 * on PHP 7.4, the oldest PHP WordPress 7.0 runs on, so a host below the floor sees the notice and no parse error
 * (D-023); CI lints it on 7.4.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'CBUILD_VERSION', '1.2.0' );
define( 'CBUILD_FILE', __FILE__ );
define( 'CBUILD_PATH', plugin_dir_path( __FILE__ ) );
define( 'CBUILD_URL', plugin_dir_url( __FILE__ ) );

require_once __DIR__ . '/src/Support/Requirements.php';

if ( null !== \CreativeBranding\SkateboardBuilder\Support\Requirements::platformShortfall( PHP_VERSION, get_bloginfo( 'version' ) ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			// For the people who can do something about it.
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			// WordPress below 6.4 has no wp_admin_notice(), and this is the notice for a WordPress below the floor:
			// printNotice() writes the markup itself there.
			\CreativeBranding\SkateboardBuilder\Support\Requirements::printNotice( \CreativeBranding\SkateboardBuilder\Support\Requirements::notice() );
		}
	);
	return;
}

if ( ! is_readable( __DIR__ . '/vendor/autoload.php' ) ) {
	add_action(
		'admin_notices',
		static function (): void {
			if ( ! current_user_can( 'activate_plugins' ) ) {
				return;
			}
			wp_admin_notice(
				esc_html__( 'Creative Skateboard Builder: run "composer install" before activating the plugin.', 'creative-skateboard-builder' ),
				array( 'type' => 'error' )
			);
		}
	);
	return;
}
require_once __DIR__ . '/vendor/autoload.php';

register_activation_hook( __FILE__, array( \CreativeBranding\SkateboardBuilder\Install\Activator::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \CreativeBranding\SkateboardBuilder\Install\Deactivator::class, 'deactivate' ) );

// WooCommerce fires this while it is included, before plugins_loaded (11.2.2, includes/class-woocommerce.php:998), so
// the declarations are registered here. The product_block_editor feature is gone from 11.2, so nothing is declared
// about it (D-027).
add_action(
	'before_woocommerce_init',
	static function (): void {
		if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
			\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'cart_checkout_blocks', __FILE__, true );
		}
	}
);

add_action( 'plugins_loaded', array( \CreativeBranding\SkateboardBuilder\Plugin::class, 'boot' ), 5 );
