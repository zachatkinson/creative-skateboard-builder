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

	public function test_turned_off_by_the_filter(): void {
		self::assertTrue( Updater::enabled() );
		add_filter( 'cbuild_updates_enabled', '__return_false' );
		self::assertFalse( Updater::enabled() );
		$before = $GLOBALS['wp_filter']['plugins_api'] ?? null;
		( new Updater( new FakeManifestSource( null ), self::BASENAME ) )->register();
		self::assertSame( $before, $GLOBALS['wp_filter']['plugins_api'] ?? null, 'nothing attached' );
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
