<?php
/**
 * The release manifest.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Updates;

use CreativeBranding\SkateboardBuilder\Updates\Manifest;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * What is a manifest and what is not (spec: Supply chain and updates).
 */
final class ManifestTest extends TestCase {

	private const SHA = 'a3f1c2d4e5f60718293a4b5c6d7e8f90a1b2c3d4e5f60718293a4b5c6d7e8f90';

	private const PACKAGE = 'https://github.com/zachatkinson/creative-releases/releases/download/creative-skateboard-builder-0.1.0/creative-skateboard-builder-0.1.0.zip';

	/**
	 * @return array<string, mixed>
	 */
	private static function valid(): array {
		return array(
			'version'      => '0.1.0',
			'package'      => self::PACKAGE,
			'sha256'       => strtoupper( self::SHA ),
			'requires'     => '7.0',
			'requires_php' => '8.5',
			'tested'       => '7.1',
			'url'          => 'https://github.com/zachatkinson/creative-releases/releases/tag/creative-skateboard-builder-0.1.0',
			'changelog'    => array( ' The migration framework. ', '', 42, 'The updater.' ),
		);
	}

	public function test_a_valid_manifest_is_read_whole(): void {
		$m = Manifest::fromArray( self::valid() );
		self::assertNotNull( $m );
		self::assertSame( '0.1.0', $m->version );
		self::assertSame( self::PACKAGE, $m->package );
		self::assertSame( self::SHA, $m->sha256, 'lower-cased' );
		self::assertSame( '7.0', $m->requires );
		self::assertSame( '8.5', $m->requires_php );
		self::assertSame( '7.1', $m->tested );
		self::assertSame( 'https://github.com/zachatkinson/creative-releases/releases/tag/creative-skateboard-builder-0.1.0', $m->url );
		self::assertSame( array( 'The migration framework.', 'The updater.' ), $m->changelog, 'trimmed, blanks and non-strings dropped' );
	}

	public function test_the_optional_fields_default(): void {
		$m = Manifest::fromArray(
			array(
				'version' => '1.0.0',
				'package' => self::PACKAGE,
				'sha256'  => self::SHA,
			)
		);
		self::assertNotNull( $m );
		self::assertSame( '', $m->requires );
		self::assertSame( '', $m->requires_php );
		self::assertSame( '', $m->tested );
		self::assertSame( '', $m->url );
		self::assertSame( array(), $m->changelog );
	}

	public function test_malformed_optional_fields_are_dropped_not_fatal(): void {
		$data                 = self::valid();
		$data['requires']     = 'seven';
		$data['tested']       = '7.1.4.1';
		$data['url']          = 'http://insecure.example';
		$data['changelog']    = 'not a list';
		$data['requires_php'] = 8.5;
		$m                    = Manifest::fromArray( $data );
		self::assertNotNull( $m );
		self::assertSame( '', $m->requires );
		self::assertSame( '', $m->tested );
		self::assertSame( '', $m->url );
		self::assertSame( array(), $m->changelog );
		self::assertSame( '', $m->requires_php );
	}

	/**
	 * @return iterable<string, array{mixed}>
	 */
	public static function invalid(): iterable {
		yield 'not an array' => array( 'nope' );
		yield 'null' => array( null );
		yield 'no version' => array( array_diff_key( self::valid(), array( 'version' => 1 ) ) );
		yield 'two-part version' => array( array_merge( self::valid(), array( 'version' => '1.0' ) ) );
		yield 'short sha' => array( array_merge( self::valid(), array( 'sha256' => 'abc' ) ) );
		yield 'sha with a non-hex character' => array( array_merge( self::valid(), array( 'sha256' => str_repeat( 'g', 64 ) ) ) );
		yield 'sha not a string' => array( array_merge( self::valid(), array( 'sha256' => 123 ) ) );
		yield 'http package' => array( array_merge( self::valid(), array( 'package' => str_replace( 'https://', 'http://', self::PACKAGE ) ) ) );
		yield 'another host' => array( array_merge( self::valid(), array( 'package' => 'https://evil.example/creative-skateboard-builder-0.1.0.zip' ) ) );
		yield 'host that only looks right' => array( array_merge( self::valid(), array( 'package' => 'https://github.com.evil.example/x.zip' ) ) );
		yield 'not a zip' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/zachatkinson/creative-releases/releases/download/x/x.php' ) ) );
		yield 'zip only in the query' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/zachatkinson/x?f=.zip' ) ) );
		yield 'another owner on GitHub' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/attacker/evil/releases/download/v1/creative-skateboard-builder-9.9.9.zip' ) ) );
		yield 'another repository of the agency' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/zachatkinson/other/releases/download/creative-skateboard-builder-0.1.0/creative-skateboard-builder-0.1.0.zip' ) ) );
		yield 'a branch archive, not a release' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/zachatkinson/creative-releases/archive/refs/heads/main.zip' ) ) );
		yield 'a sibling plugin of the agency' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/zachatkinson/creative-releases/releases/download/creative-search-1.0.0/creative-search-1.0.0.zip' ) ) );
		yield 'a path that climbs out of the releases' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/zachatkinson/creative-releases/releases/download/creative-skateboard-builder-0.1.0/../../../../attacker/evil/x.zip' ) ) );
		yield 'an encoded path' => array( array_merge( self::valid(), array( 'package' => 'https://github.com/zachatkinson/creative-releases/releases/download/creative-skateboard-builder-0.1.0/%2e%2e/x.zip' ) ) );
		yield 'empty package' => array( array_merge( self::valid(), array( 'package' => '' ) ) );
	}

	#[DataProvider( 'invalid' )]
	public function test_anything_malformed_is_no_manifest( mixed $data ): void {
		self::assertNull( Manifest::fromArray( $data ) );
	}

	public function test_allowed_package_is_a_zip_among_this_plugins_releases_and_nothing_else(): void {
		self::assertTrue( Manifest::allowedPackage( self::PACKAGE ) );
		self::assertStringStartsWith( Manifest::RELEASES, self::PACKAGE );
		self::assertFalse( Manifest::allowedPackage( 'HTTPS://GITHUB.COM/a/b/c.ZIP' ), 'github.com is everyone\'s host' );
		self::assertFalse( Manifest::allowedPackage( strtoupper( self::PACKAGE ) ), 'a path is matched as written' );
		self::assertFalse( Manifest::allowedPackage( 'https://github.com' ) );
		self::assertFalse( Manifest::allowedPackage( 'https://user@github.com/a.zip' ), 'user info is not a host' );
		self::assertFalse( Manifest::allowedPackage( self::PACKAGE . '#https://github.com/' ) );
		self::assertFalse( Manifest::allowedPackage( self::PACKAGE . '?x=1' ) );
		self::assertFalse( Manifest::allowedPackage( Manifest::RELEASES . '0.1.0/a b.zip' ) );
	}
}
