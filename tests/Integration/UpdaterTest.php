<?php
/**
 * Updates from the agency's releases.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeManifestSource;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Updates\HttpManifestSource;
use CreativeBranding\SkateboardBuilder\Updates\Manifest;
use CreativeBranding\SkateboardBuilder\Updates\Updater;
use WP_Error;

/**
 * The update offer, the details popup, the SHA-256 check and the manifest cache, with no network (D-003).
 */
final class UpdaterTest extends IntegrationTestCase {

	private const BASENAME = 'creative-skateboard-builder/creative-skateboard-builder.php';
	private const PACKAGE  = Updater::RELEASES . '9.0.0/creative-skateboard-builder-9.0.0.zip';

	/**
	 * A manifest for version 9.0.0.
	 *
	 * @param string|null $sha     SHA-256; a fixed one when null.
	 * @param string      $package Package address.
	 */
	private static function manifest( ?string $sha = null, string $package = self::PACKAGE ): Manifest {
		$m = Manifest::fromArray(
			array(
				'version'      => '9.0.0',
				'package'      => $package,
				'sha256'       => $sha ?? str_repeat( 'a', 64 ),
				'requires'     => '7.0',
				'requires_php' => '8.5',
				'tested'       => '7.1',
				'url'          => 'https://github.com/zachatkinson/creative-releases/releases/tag/creative-skateboard-builder-9.0.0',
				'changelog'    => array( 'New <b>things</b>.' ),
			)
		);
		self::assertNotNull( $m );
		return $m;
	}

	/**
	 * A downloader that writes the bytes given to a temporary file and counts its calls.
	 *
	 * @param string $bytes File content.
	 * @param int    $calls Counter, by reference.
	 * @return callable(string): string
	 */
	private static function downloader( string $bytes, int &$calls ): callable {
		return static function ( string $url ) use ( $bytes, &$calls ): string {
			++$calls;
			$file = wp_tempnam( 'cbuild' );
			file_put_contents( $file, $bytes ); // phpcs:ignore
			return $file;
		};
	}

	public function test_check_offers_the_release_for_this_plugin_only(): void {
		$updater = new Updater( new FakeManifestSource( self::manifest() ), self::BASENAME );
		$update  = $updater->check( false, array(), self::BASENAME );
		self::assertIsArray( $update );
		self::assertSame( '9.0.0', $update['new_version'] );
		self::assertSame( self::PACKAGE, $update['package'] );
		self::assertSame( 'creative-skateboard-builder', $update['slug'] );
		self::assertSame( self::BASENAME, $update['plugin'] );
		self::assertSame( Updater::MANIFEST_URL, $update['id'] );
		self::assertSame( '7.0', $update['requires'] );
		self::assertSame( '8.5', $update['requires_php'] );
		self::assertSame( Updater::tested( '7.1', get_bloginfo( 'version' ) ), $update['tested'] );
		self::assertFalse( $updater->check( false, array(), 'woocommerce/woocommerce.php' ), 'another plugin is left alone' );
		self::assertFalse( ( new Updater( new FakeManifestSource( null ), self::BASENAME ) )->check( false, array(), self::BASENAME ), 'no manifest, no offer' );
	}

	public function test_details_popup_escapes_the_changelog(): void {
		$updater = new Updater( new FakeManifestSource( self::manifest() ), self::BASENAME );
		$info    = $updater->details( false, 'plugin_information', (object) array( 'slug' => 'creative-skateboard-builder' ) );
		self::assertIsObject( $info );
		self::assertSame( '9.0.0', $info->version );
		self::assertStringContainsString( 'New &lt;b&gt;things&lt;/b&gt;.', $info->sections['changelog'] );
		self::assertSame( self::PACKAGE, $info->download_link );
		self::assertFalse( $updater->details( false, 'plugin_information', (object) array( 'slug' => 'woocommerce' ) ) );
		self::assertFalse( $updater->details( false, 'query_plugins', (object) array( 'slug' => 'creative-skateboard-builder' ) ) );
	}

	public function test_a_package_is_checked_against_its_sha256_before_install(): void {
		$calls = 0;
		$good  = new Updater( new FakeManifestSource( self::manifest( hash( 'sha256', 'zip bytes' ) ) ), self::BASENAME, self::downloader( 'zip bytes', $calls ) );
		$path  = $good->verify( false, self::PACKAGE );
		self::assertIsString( $path );
		self::assertFileExists( $path );
		wp_delete_file( $path );

		$bad    = new Updater( new FakeManifestSource( self::manifest( hash( 'sha256', 'other bytes' ) ) ), self::BASENAME, self::downloader( 'zip bytes', $calls ) );
		$result = $bad->verify( false, self::PACKAGE );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cbuild_package_mismatch', $result->get_error_code() );
		self::assertSame( 2, $calls );

		self::assertFalse( $good->verify( false, 'https://downloads.wordpress.org/plugin/woocommerce.zip' ), 'other packages are left to core' );
		self::assertSame( 'kept', $good->verify( 'kept', self::PACKAGE ), 'another filter\'s answer is kept' );
	}

	public function test_a_package_the_manifest_no_longer_names_is_checked_against_it_read_again(): void {
		$newer = Updater::RELEASES . '9.0.1/creative-skateboard-builder-9.0.1.zip';
		$calls = 0;
		$stale = new FakeManifestSource( self::manifest( hash( 'sha256', 'zip bytes' ) ), self::manifest( hash( 'sha256', 'zip bytes' ), $newer ) );
		$path  = ( new Updater( $stale, self::BASENAME, self::downloader( 'zip bytes', $calls ) ) )->verify( false, $newer );
		self::assertIsString( $path, 'published since the last check: the manifest read again names it' );
		self::assertSame( 1, $stale->fresh );
		wp_delete_file( $path );

		$calls   = 0;
		$unknown = new FakeManifestSource( self::manifest() );
		$result  = ( new Updater( $unknown, self::BASENAME, self::downloader( 'zip bytes', $calls ) ) )->verify( false, $newer );
		self::assertInstanceOf( WP_Error::class, $result, 'a package the manifest does not name is refused' );
		self::assertSame( 'cbuild_package_unknown', $result->get_error_code() );
		self::assertSame( 1, $unknown->fresh, 'read again once' );
		self::assertSame( 0, $calls, 'nothing downloaded' );
	}

	public function test_a_sibling_plugins_release_is_left_to_its_own_updater(): void {
		$calls  = 0;
		$source = new FakeManifestSource( null );
		$sister = 'https://github.com/zachatkinson/creative-releases/releases/download/creative-search-1.0.0/creative-search-1.0.0.zip';
		self::assertFalse( ( new Updater( $source, self::BASENAME, self::downloader( 'zip bytes', $calls ) ) )->verify( false, $sister ) );
		self::assertSame( 0, $source->fresh );
		self::assertSame( 0, $calls );
	}

	public function test_turned_off_by_the_filter_whenever_it_is_added(): void {
		self::assertTrue( Updater::enabled() );
		$calls   = 0;
		$source  = new FakeManifestSource( self::manifest( hash( 'sha256', 'zip bytes' ) ) );
		$updater = new Updater( $source, self::BASENAME, self::downloader( 'zip bytes', $calls ) );
		$updater->register();
		self::assertIsArray( $updater->check( false, array(), self::BASENAME ), 'on: the release is offered' );

		// Added after the plugin booted, as a theme's functions.php or another plugin's plugins_loaded callback would.
		add_filter( 'cbuild_updates_enabled', '__return_false' );
		self::assertFalse( Updater::enabled() );
		self::assertFalse( $updater->check( false, array(), self::BASENAME ), 'no offer' );
		self::assertFalse( $updater->details( false, 'plugin_information', (object) array( 'slug' => 'creative-skateboard-builder' ) ), 'no details' );
		// A package of this plugin's can still arrive: WordPress keeps the offer it was made before updates were turned
		// off. Answering false would have WordPress download and install it unchecked, so it is refused.
		$refused = $updater->verify( false, self::PACKAGE );
		self::assertInstanceOf( \WP_Error::class, $refused, 'this plugin\'s package is not left to install unchecked' );
		self::assertSame( 'cbuild_updates_off', $refused->get_error_code() );
		self::assertSame( 0, $calls, 'nothing downloaded' );
		self::assertSame( 0, $source->fresh, 'and no request made' );
		self::assertFalse( $updater->verify( false, 'https://downloads.wordpress.org/plugin/akismet.5.3.zip' ), 'another plugin\'s package is not this one\'s business' );
	}

	public function test_the_checksum_is_checked_on_the_hook_wordpress_downloads_through(): void {
		$calls   = 0;
		$updater = new Updater( new FakeManifestSource( self::manifest( hash( 'sha256', 'other bytes' ) ) ), self::BASENAME, self::downloader( 'zip bytes', $calls ) );
		// The plugin booted an updater of its own on these hooks; this one stands in for it.
		remove_all_filters( 'upgrader_pre_download' );
		$updater->register();
		self::assertSame( 10, has_filter( 'upgrader_pre_download', array( $updater, 'verify' ) ) );
		self::assertSame( 10, has_filter( 'plugins_api', array( $updater, 'details' ) ) );
		self::assertSame( 10, has_action( 'load-update-core.php', array( Updater::class, 'forceCheck' ) ) );

		// As WP_Upgrader::download_package() asks (wp-admin/includes/class-wp-upgrader.php): a wrong checksum comes
		// back as the error the upgrader shows, and the file is gone.
		$result = apply_filters( 'upgrader_pre_download', false, self::PACKAGE, new \stdClass(), array() );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( 'cbuild_package_mismatch', $result->get_error_code() );
		self::assertSame( 1, $calls );
		self::assertFalse( apply_filters( 'upgrader_pre_download', false, 'https://downloads.wordpress.org/plugin/akismet.zip', new \stdClass(), array() ), 'core\'s own packages are left to core' );
		self::assertSame( 1, $calls );
	}

	public function test_the_package_wordpress_was_offered_for_this_plugin_is_checked_wherever_it_is(): void {
		$calls   = 0;
		$mirror  = 'https://downloads.example.org/creative-skateboard-builder.zip';
		$updater = new Updater( new FakeManifestSource( self::manifest() ), self::BASENAME, self::downloader( 'zip bytes', $calls ) );
		self::assertFalse( $updater->verify( false, $mirror ), 'not offered: not ours' );

		$updates           = new \stdClass();
		$updates->response = array( self::BASENAME => (object) array( 'package' => $mirror ) );
		set_site_transient( 'update_plugins', $updates );
		$result = $updater->verify( false, $mirror );
		self::assertInstanceOf( WP_Error::class, $result, 'offered for this plugin, and not what the manifest names: refused' );
		self::assertSame( 'cbuild_package_unknown', $result->get_error_code() );
		self::assertSame( 0, $calls, 'before any download' );
		delete_site_transient( 'update_plugins' );
	}

	public function test_the_package_is_downloaded_without_naming_the_site(): void {
		$agent = '';
		$seen  = '';
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$agent, &$seen ) {
				$agent = (string) $args['user-agent'];
				$seen  = (string) $url;
				return new WP_Error( 'cbuild_test', 'stopped by the test' );
			},
			10,
			3
		);
		// WordPress's own downloader, as in production.
		$updater = new Updater( new FakeManifestSource( self::manifest() ), self::BASENAME );
		$result  = $updater->verify( false, self::PACKAGE );
		self::assertInstanceOf( WP_Error::class, $result );
		self::assertSame( self::PACKAGE, $seen );
		self::assertSame( 'Creative Skateboard Builder/' . CBUILD_VERSION, $agent, 'WordPress\'s default user agent carries the site\'s address' );
		self::assertStringNotContainsString( (string) wp_parse_url( home_url(), PHP_URL_HOST ), $agent );

		// Only for that one request: the next one of anybody's has WordPress's own again.
		wp_remote_get( 'https://example.org/' );
		self::assertStringContainsString( 'WordPress/', $agent );
	}

	public function test_check_again_on_the_updates_screen_forgets_the_cached_manifest(): void {
		set_site_transient( Options::UPDATE_MANIFEST, array( 'data' => null ), HOUR_IN_SECONDS );
		Updater::forceCheck();
		self::assertIsArray( get_site_transient( Options::UPDATE_MANIFEST ), 'an ordinary load of the screen keeps it' );
		$_GET['force-check'] = '1';
		Updater::forceCheck();
		unset( $_GET['force-check'] );
		self::assertFalse( get_site_transient( Options::UPDATE_MANIFEST ) );
	}

	public function test_the_constant_turns_updates_off(): void {
		if ( ! self::hasWpCli() ) {
			self::markTestSkipped( 'Needs wp on the path (wp-env).' );
		}
		// A constant cannot be undefined again, so it is defined in a process of its own, before WordPress loads.
		$code   = 'echo \CreativeBranding\SkateboardBuilder\Updates\Updater::enabled() ? "on" : "off", "|", false === apply_filters( "update_plugins_raw.githubusercontent.com", false, array(), "' . self::BASENAME . '", array() ) ? "no offer" : "offer";';
		$result = self::wpEval( $code, array( '--exec=define( "CBUILD_DISABLE_UPDATES", true );' ) );
		self::assertSame( 0, $result['code'], $result['err'] );
		self::assertSame( 'off|no offer', $result['out'] );
	}

	public function test_the_manifest_request_follows_no_redirect_and_reads_a_bounded_body(): void {
		HttpManifestSource::flush();
		$args = array();
		add_filter(
			'pre_http_request',
			static function ( $pre, $request ) use ( &$args ) {
				$args = $request;
				return new WP_Error( 'cbuild_test', 'stopped by the test' );
			},
			10,
			2
		);
		( new HttpManifestSource( Updater::MANIFEST_URL ) )->latest();
		self::assertSame( 0, $args['redirection'], 'a redirect could leave https' );
		self::assertSame( 65536, $args['limit_response_size'] );
		HttpManifestSource::flush();
	}

	public function test_a_manifest_address_that_is_not_https_is_never_requested(): void {
		HttpManifestSource::flush();
		$calls = 0;
		add_filter(
			'pre_http_request',
			static function () use ( &$calls ) {
				++$calls;
				return array(
					'headers'  => array(),
					'body'     => (string) wp_json_encode( array( 'version' => '9.0.0', 'package' => self::PACKAGE, 'sha256' => str_repeat( 'b', 64 ) ) ),
					'response' => array( 'code' => 200, 'message' => '' ),
					'cookies'  => array(),
				);
			}
		);
		$source = new HttpManifestSource( str_replace( 'https://', 'http://', Updater::MANIFEST_URL ) );
		self::assertNull( $source->latest(), 'no manifest from an address anyone on the path could rewrite' );
		self::assertSame( 0, $calls, 'and no request made' );
		HttpManifestSource::flush();
	}

	public function test_http_source_caches_and_retries_after_failure_and_names_only_the_plugin(): void {
		HttpManifestSource::flush();
		$calls = 0;
		$body  = '';
		$agent = '';
		$seen  = '';
		add_filter(
			'pre_http_request',
			static function ( $pre, $args, $url ) use ( &$calls, &$body, &$agent, &$seen ) {
				++$calls;
				$agent = $args['user-agent'];
				$seen  = $url;
				return array(
					'headers'  => array(),
					'body'     => $body,
					'response' => array( 'code' => '' === $body ? 500 : 200, 'message' => '' ),
					'cookies'  => array(),
				);
			},
			10,
			3
		);
		$source = new HttpManifestSource( Updater::MANIFEST_URL );
		self::assertNull( $source->latest() );
		self::assertNull( $source->latest() );
		self::assertSame( 1, $calls, 'a failure is cached too' );
		self::assertSame( 'Creative Skateboard Builder/' . CBUILD_VERSION, $agent, 'the request does not name the site' );
		self::assertSame( Updater::MANIFEST_URL, $seen );
		self::assertIsArray( get_site_transient( Options::UPDATE_MANIFEST ) );

		HttpManifestSource::flush();
		$body = (string) wp_json_encode( array( 'version' => '9.0.0', 'package' => self::PACKAGE, 'sha256' => str_repeat( 'b', 64 ) ) );
		self::assertSame( '9.0.0', $source->latest()?->version );
		self::assertSame( '9.0.0', $source->latest()?->version );
		self::assertSame( 2, $calls );
		self::assertSame( '9.0.0', $source->fresh()?->version );
		self::assertSame( 3, $calls, 'fresh() reads again' );
		HttpManifestSource::flush();
	}
}
