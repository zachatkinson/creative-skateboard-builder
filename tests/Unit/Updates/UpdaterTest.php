<?php
/**
 * The updater's pure parts.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Updates;

use CreativeBranding\SkateboardBuilder\Updates\Updater;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * "Tested up to" as WordPress compares it, and the constants the Update URI header depends on. The class is
 * WordPress-bound, so the hooks are proven in tests/Integration/UpdaterTest.php.
 */
final class UpdaterTest extends TestCase {

	/**
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function branches(): iterable {
		yield 'branch covers its maintenance release' => array( '7.1', '7.1.4', '7.1.4' );
		yield 'branch of another version is left alone' => array( '7.0', '7.1.4', '7.0' );
		yield 'full version is passed on' => array( '7.1.2', '7.1.4', '7.1.2' );
		yield 'empty is passed on' => array( '', '7.1.4', '' );
		yield 'alpha site version is not a release' => array( '7.2', '7.2-alpha-61000', '7.2' );
	}

	#[DataProvider( 'branches' )]
	public function test_tested( string $manifest, string $site, string $expected ): void {
		self::assertSame( $expected, Updater::tested( $manifest, $site ) );
	}

	public function test_the_manifest_host_is_the_update_uri_header_host(): void {
		$header = (string) file_get_contents( dirname( __DIR__, 3 ) . '/creative-skateboard-builder.php' );
		self::assertSame( 1, preg_match( '/^ \* Update URI:\s+(\S+)$/m', $header, $m ) );
		self::assertSame( $m[1], Updater::MANIFEST_URL );
		self::assertSame( parse_url( Updater::MANIFEST_URL, PHP_URL_HOST ), parse_url( $m[1], PHP_URL_HOST ) );
	}

	public function test_releases_prefix_names_this_plugin(): void {
		self::assertStringEndsWith( '/creative-skateboard-builder-', Updater::RELEASES );
		self::assertSame( 'https://github.com/zachatkinson/creative-releases/releases/download/creative-skateboard-builder-', Updater::RELEASES );
		self::assertSame( \CreativeBranding\SkateboardBuilder\Updates\Manifest::RELEASES, Updater::RELEASES, 'one prefix: what the manifest may name is what the updater checks' );
	}
}
