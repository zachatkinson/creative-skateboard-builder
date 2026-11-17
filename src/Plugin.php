<?php
/**
 * Plugin bootstrap.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder;

use Closure;
use CreativeBranding\SkateboardBuilder\Support\Requirements;
use CreativeBranding\SkateboardBuilder\Updates\HttpManifestSource;
use CreativeBranding\SkateboardBuilder\Updates\Updater;
use ReflectionFunction;
use WP_Hook;

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
	 * WordPress has just emptied a plugin's folder for the files it is about to copy in: when it is this plugin's,
	 * nothing of this release runs again in this request (D-208). Passes the result on unchanged.
	 *
	 * WordPress 7.1.4 applies the filter between deleting the old files and copying the new ones
	 * (wp-admin/includes/class-wp-upgrader.php:694), and does the same for an update, an upload's "Replace current with
	 * uploaded" and WP-CLI.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $removed The result of emptying it.
	 * @param mixed $local   The folder the plugin's folder is in.
	 * @param mixed $remote  The plugin's folder, as the filesystem names it.
	 * @return mixed
	 */
	public static function cleared( mixed $removed, mixed $local, mixed $remote ): mixed {
		if ( is_string( $local ) && is_string( $remote ) && self::isHere( trailingslashit( $local ) . basename( untrailingslashit( $remote ) ) ) ) {
			self::stop();
		}
		return $removed;
	}

	/**
	 * A plugin's files were copied in: when they are this plugin's, nothing of this release runs again in this request.
	 * Where the filter above ran, this callback is gone already. Passes the response on unchanged.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $response The installation's response.
	 * @param mixed $extra    What the upgrader was asked to do.
	 * @param mixed $result   Where the files went: `destination`, among others.
	 * @return mixed
	 */
	public static function installed( mixed $response, mixed $extra, mixed $result ): mixed {
		if ( is_array( $result ) && is_string( $result['destination'] ?? null ) && self::isHere( $result['destination'] ) ) {
			self::stop();
		}
		return $response;
	}

	/**
	 * An upgrader finished: when it put files in this plugin's folder, nothing of this release runs again in this
	 * request.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $upgrader The upgrader.
	 */
	public static function completed( mixed $upgrader ): void {
		$result = is_object( $upgrader ) && property_exists( $upgrader, 'result' ) ? $upgrader->result : null;
		self::installed( true, null, $result );
	}

	/**
	 * Whether a folder is this plugin's.
	 *
	 * @param string $folder The folder.
	 */
	private static function isHere( string $folder ): bool {
		return untrailingslashit( wp_normalize_path( $folder ) ) === untrailingslashit( wp_normalize_path( CBUILD_PATH ) );
	}

	/**
	 * Take every callback of this plugin off every hook. The files on disk are, or are about to be, another
	 * release's: a callback that builds a service from them, through this release's container, would build that
	 * release's classes from this release's bindings (Cowork's M7 review, F3).
	 *
	 * @since 0.7.0
	 */
	public static function stop(): void {
		global $wp_filter;
		foreach ( (array) $wp_filter as $name => $hook ) {
			if ( ! $hook instanceof WP_Hook ) {
				continue;
			}
			foreach ( $hook->callbacks as $priority => $callbacks ) {
				foreach ( $callbacks as $callback ) {
					if ( self::isOurs( $callback['function'] ) ) {
						remove_filter( (string) $name, $callback['function'], $priority );
					}
				}
			}
		}
		// An automatic update runs in wp-cron.php, which runs Action Scheduler's queue after it: a job of this plugin's,
		// its callback gone, would be marked failed. The queue claims nothing more in this request, and the jobs wait
		// for the next, with the new release's callbacks (the audit of the fixes).
		add_filter( 'action_scheduler_queue_runner_concurrent_batches', '__return_zero', PHP_INT_MAX );
	}

	/**
	 * Whether a callback is this plugin's: a method of one of its classes, or a closure in one of its files.
	 *
	 * @param mixed $callback The callback.
	 */
	private static function isOurs( mixed $callback ): bool {
		if ( is_array( $callback ) && isset( $callback[0] ) ) {
			$callback = is_object( $callback[0] ) ? $callback[0]::class : $callback[0];
		}
		if ( $callback instanceof Closure ) {
			$file = ( new ReflectionFunction( $callback ) )->getFileName();
			return is_string( $file ) && str_starts_with( wp_normalize_path( $file ), trailingslashit( wp_normalize_path( CBUILD_PATH ) ) );
		}
		return is_string( $callback ) && str_starts_with( ltrim( $callback, '\\' ), __NAMESPACE__ . '\\' );
	}

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

		// Before anything else, so a request that replaces these files stops using them, however the plugin booted.
		add_filter( 'upgrader_clear_destination', array( self::class, 'cleared' ), 10, 3 );
		add_filter( 'upgrader_post_install', array( self::class, 'installed' ), 10, 3 );
		add_action( 'upgrader_process_complete', array( self::class, 'completed' ), 10, 1 );

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
