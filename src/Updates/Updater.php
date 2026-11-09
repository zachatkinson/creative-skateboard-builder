<?php
/**
 * Updates from the agency's releases.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Updates;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Offers new releases on the Plugins and Updates screens through WordPress core's `update_plugins_{hostname}` filter
 * for the `Update URI` header (WordPress 7.1.4, wp-includes/update.php:555), so updates and auto-updates work as for
 * any plugin. The zip is checked against the manifest's SHA-256 in `upgrader_pre_download`
 * (wp-admin/includes/class-wp-upgrader.php:322) before WordPress unpacks it. Registered before the WooCommerce check,
 * so a fix still arrives while WooCommerce is missing or too old (D-003). Turned off by
 * `define( 'CBUILD_DISABLE_UPDATES', true )` or the `cbuild_updates_enabled` filter.
 */
final class Updater {

	/**
	 * The manifest's address; its hostname must be the `Update URI` header's.
	 */
	public const MANIFEST_URL = 'https://raw.githubusercontent.com/zachatkinson/creative-releases/main/creative-skateboard-builder/info.json'; // phpcs:ignore PluginCheck.CodeAnalysis.Offloading.OffloadedContent -- The update manifest, read server side; no asset is loaded from it.

	public const SLUG = 'creative-skateboard-builder';

	/**
	 * Where this plugin's release zips are published: a package under it is this plugin's to check, and the only
	 * kind the manifest may name. A sibling's package is left to its own updater.
	 */
	public const RELEASES = Manifest::RELEASES;

	/**
	 * How a package is downloaded to a temporary file.
	 *
	 * @var callable(string): (string|WP_Error)
	 */
	private $download;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param ManifestSource                             $source   The latest release.
	 * @param string                                     $basename The plugin's basename (creative-skateboard-builder/creative-skateboard-builder.php).
	 * @param (callable(string): (string|WP_Error))|null $download Downloader; WordPress's own when null.
	 */
	public function __construct( private ManifestSource $source, private string $basename, ?callable $download = null ) {
		$this->download = $download ?? static function ( string $url ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
			// download_url() sends WordPress's default user agent, which carries the site's address
			// (wp-includes/class-wp-http.php:211). For this one request it names only the plugin, as the manifest's does.
			$agent = static function ( $args, $requested ) use ( $url ) {
				if ( is_array( $args ) && $requested === $url ) {
					$args['user-agent'] = 'Creative Skateboard Builder/' . CBUILD_VERSION;
				}
				return $args;
			};
			add_filter( 'http_request_args', $agent, 10, 2 ); // phpcs:ignore WordPressVIPMinimum.Hooks.RestrictedHooks.http_request_args -- Sets the user agent of one request; the timeout is untouched.
			try {
				return download_url( $url );
			} finally {
				remove_filter( 'http_request_args', $agent, 10 );
			}
		};
	}

	/**
	 * Attach hooks. Whether updates are on is asked each time one runs, so the filter and the constant work
	 * wherever they are set, a theme's functions.php included.
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_filter( 'update_plugins_' . (string) wp_parse_url( self::MANIFEST_URL, PHP_URL_HOST ), array( $this, 'check' ), 10, 3 );
		add_filter( 'plugins_api', array( $this, 'details' ), 10, 3 );
		add_filter( 'upgrader_pre_download', array( $this, 'verify' ), 10, 2 );
		add_action( 'load-update-core.php', array( self::class, 'forceCheck' ) );
	}

	/**
	 * Whether update checks are on.
	 *
	 * @since 0.1.0
	 */
	public static function enabled(): bool {
		$on = ! ( defined( 'CBUILD_DISABLE_UPDATES' ) && CBUILD_DISABLE_UPDATES );
		/**
		 * Filter whether Creative Skateboard Builder checks the agency's releases for updates.
		 *
		 * @since 0.1.0
		 *
		 * @param bool $on Whether update checks are on. Default true, unless CBUILD_DISABLE_UPDATES is defined true.
		 */
		return (bool) apply_filters( 'cbuild_updates_enabled', $on );
	}

	/**
	 * The update core asks about, for this plugin only.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $update      What another filter returned, or false.
	 * @param mixed $plugin_data The plugin's header data.
	 * @param mixed $plugin_file The plugin's basename.
	 * @return mixed
	 */
	public function check( mixed $update, mixed $plugin_data, mixed $plugin_file ): mixed {
		unset( $plugin_data );
		if ( ! self::enabled() ) {
			return $update;
		}
		if ( $this->basename !== $plugin_file ) {
			return $update;
		}
		$manifest = $this->source->latest();
		if ( null === $manifest ) {
			return $update;
		}
		return array(
			'id'           => self::MANIFEST_URL,
			'slug'         => self::SLUG,
			'plugin'       => $this->basename,
			'version'      => $manifest->version,
			'new_version'  => $manifest->version,
			'url'          => $manifest->url,
			'package'      => $manifest->package,
			'tested'       => self::tested( $manifest->tested, get_bloginfo( 'version' ) ),
			'requires'     => $manifest->requires,
			'requires_php' => $manifest->requires_php,
		);
	}

	/**
	 * "Tested up to" as WordPress compares it: a branch such as `7.1`, as the readme states it, covers the branch's
	 * maintenance releases, so a site on 7.1.4 reads it as 7.1.4, as WordPress.org's own API reports it. Core compares
	 * the whole version (`version_compare( '7.1.4', '7.1', '<=' )` is false) and would warn that the plugin "has not
	 * been tested" with it. Any other value is passed on as it is.
	 *
	 * @since 0.1.0
	 *
	 * @param string $tested The manifest's "Tested up to".
	 * @param string $site   The site's WordPress version.
	 */
	public static function tested( string $tested, string $site ): string {
		if ( 1 !== preg_match( '/^\d+\.\d+$/', $tested ) || 1 !== preg_match( '/^(\d+\.\d+)\.\d+$/', $site, $m ) ) {
			return $tested;
		}
		return $m[1] === $tested ? $site : $tested;
	}

	/**
	 * The "View details" popup.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $result What another filter returned, or false.
	 * @param mixed $action The plugins_api action.
	 * @param mixed $args   Its arguments.
	 * @return mixed
	 */
	public function details( mixed $result, mixed $action, mixed $args ): mixed {
		if ( 'plugin_information' !== $action || ! is_object( $args ) || ! isset( $args->slug ) || self::SLUG !== $args->slug || ! self::enabled() ) {
			return $result;
		}
		$manifest = $this->source->latest();
		if ( null === $manifest ) {
			return $result;
		}
		$changelog = array() !== $manifest->changelog
			? '<h4>' . esc_html( $manifest->version ) . '</h4><ul><li>' . implode( '</li><li>', array_map( 'esc_html', $manifest->changelog ) ) . '</li></ul>'
			: '';
		return (object) array(
			'name'          => 'Creative Skateboard Builder',
			'slug'          => self::SLUG,
			'version'       => $manifest->version,
			'author'        => '<a href="https://creativebranding.ca">Creative Branding</a>',
			'homepage'      => $manifest->url,
			'requires'      => $manifest->requires,
			'requires_php'  => $manifest->requires_php,
			'tested'        => self::tested( $manifest->tested, get_bloginfo( 'version' ) ),
			'download_link' => $manifest->package,
			'sections'      => array(
				'description' => '<p>' . esc_html__( 'Complete skateboards built from the parts the shop stocks: fit checked from specs, a picture of the build, the total in view, each part its own cart and order line.', 'creative-skateboard-builder' ) . '</p>',
				'changelog'   => $changelog,
			),
		);
	}

	/**
	 * Download the package and check its SHA-256 against the manifest before WordPress unpacks it.
	 *
	 * @since 0.1.0
	 *
	 * @param mixed $reply   False, or what another filter returned.
	 * @param mixed $package The package address.
	 * @return mixed The downloaded file's path, a WP_Error, or $reply for other packages.
	 */
	public function verify( mixed $reply, mixed $package ): mixed {
		if ( false !== $reply || ! is_string( $package ) || ! self::enabled() || ! $this->ours( $package ) ) {
			return $reply;
		}
		$manifest = $this->source->latest();
		if ( null === $manifest || $manifest->package !== $package ) {
			// Published since WordPress last checked, or unreadable a moment ago: read it again, once.
			$manifest = $this->source->fresh();
		}
		if ( null === $manifest || $manifest->package !== $package ) {
			return new WP_Error( 'cbuild_package_unknown', __( 'This Creative Skateboard Builder update could not be checked against its published release, so it was not installed. Check for updates again, then update.', 'creative-skateboard-builder' ) );
		}
		$file = ( $this->download )( $package );
		if ( ! is_string( $file ) ) {
			return $file;
		}
		$hash = hash_file( 'sha256', $file );
		if ( ! is_string( $hash ) || ! hash_equals( $manifest->sha256, $hash ) ) {
			wp_delete_file( $file );
			return new WP_Error( 'cbuild_package_mismatch', __( 'The Creative Skateboard Builder update did not match its published checksum, so it was not installed.', 'creative-skateboard-builder' ) );
		}
		return $file;
	}

	/**
	 * Whether a package is this plugin's: the one WordPress's update data offers for it, or one of its own releases.
	 *
	 * @param string $package The package's address.
	 */
	private function ours( string $package ): bool {
		if ( str_starts_with( $package, self::RELEASES ) ) {
			return true;
		}
		$updates = get_site_transient( 'update_plugins' );
		$offer   = is_object( $updates ) && isset( $updates->response ) && is_array( $updates->response ) ? ( $updates->response[ $this->basename ] ?? null ) : null;
		return is_object( $offer ) && isset( $offer->package ) && $package === $offer->package;
	}

	/**
	 * Dashboard > Updates > Check again: read the manifest afresh.
	 *
	 * @since 0.1.0
	 */
	public static function forceCheck(): void {
		if ( isset( $_GET['force-check'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Only clears a cache, as core's own check does.
			HttpManifestSource::flush();
		}
	}
}
