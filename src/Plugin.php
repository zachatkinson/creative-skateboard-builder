<?php
/**
 * Plugin bootstrap.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder;

use CreativeBranding\SkateboardBuilder\Support\Requirements;
use CreativeBranding\SkateboardBuilder\Updates\HttpManifestSource;
use CreativeBranding\SkateboardBuilder\Updates\Updater;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Wires services to hooks on `plugins_loaded`. Hook callbacks live in one Hookable per concern; the work is in
 * services. Without WooCommerce 11.2 the plugin hooks nothing and says so in the admin (spec: Resilience).
 */
final class Plugin {

	/**
	 * Whether boot() ran.
	 *
	 * @var bool
	 */
	private static bool $booted = false;

	/**
	 * The service container, built once.
	 *
	 * @var Container|null
	 */
	private static ?Container $container = null;

	/**
	 * Boot on plugins_loaded.
	 *
	 * @since 0.1.0
	 */
	public static function boot(): void {
		if ( self::$booted ) {
			return;
		}
		self::$booted = true;

		// Updates come from the agency's releases, also while WooCommerce is missing, so a fix still arrives (D-003).
		( new Updater( new HttpManifestSource( Updater::MANIFEST_URL ), plugin_basename( CBUILD_FILE ) ) )->register();

		if ( null !== Requirements::woocommerceShortfall( self::woocommerceVersion() ) ) {
			add_action( 'admin_notices', array( self::class, 'floorsNotice' ) );
			return;
		}

		// Only the Hookables whose hooks can fire on this kind of request are built (D-067).
		self::register( ...Scope::atBoot( is_admin(), defined( 'WP_CLI' ) && WP_CLI, wp_doing_cron() ) );
		// Before any route is registered at the default priority, so a Hookable built here can add its own.
		add_action( 'rest_api_init', array( self::class, 'registerRest' ), 0 );
	}

	/**
	 * On a REST request: the Hookables that fire there.
	 *
	 * @since 0.3.0
	 */
	public static function registerRest(): void {
		self::register( Scope::Rest );
	}

	/**
	 * Build and register the Hookables of the scopes given that are not registered yet.
	 *
	 * @param Scope ...$scopes The scopes the request is in.
	 */
	private static function register( Scope ...$scopes ): void {
		foreach ( self::container()->hookablesFor( ...$scopes ) as $hookable ) {
			$hookable->register();
		}
	}

	/**
	 * The container (lazily built).
	 *
	 * @since 0.1.0
	 */
	public static function container(): Container {
		if ( null === self::$container ) {
			self::$container = new Container();
		}
		return self::$container;
	}

	/**
	 * WooCommerce's version, or null when it is not active.
	 *
	 * @since 0.1.0
	 */
	public static function woocommerceVersion(): ?string {
		if ( ! class_exists( 'WooCommerce' ) || ! defined( 'WC_VERSION' ) ) {
			return null;
		}
		return WC_VERSION;
	}

	/**
	 * The notice when WooCommerce is missing or too old: the same one the main file shows below the PHP or WordPress
	 * floor (D-023). Shown to the people who can do something about it.
	 *
	 * @since 0.1.0
	 */
	public static function floorsNotice(): void {
		if ( ! current_user_can( 'activate_plugins' ) ) {
			return;
		}
		wp_admin_notice( Requirements::notice(), array( 'type' => 'error' ) );
	}
}
