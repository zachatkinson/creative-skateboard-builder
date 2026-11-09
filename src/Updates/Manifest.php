<?php
/**
 * What a release manifest says.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Updates;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The JSON file published with each release (tools/release-manifest.php writes it): the version, the zip's https
 * address on an allowed host and its SHA-256, and what WordPress shows about it. Anything malformed is no manifest.
 */
final class Manifest {

	/**
	 * Hosts a package may be downloaded from (GitHub release assets).
	 */
	public const PACKAGE_HOSTS = array( 'github.com' );

	/**
	 * Constructor.
	 *
	 * @param string   $version      Version, such as 0.1.0.
	 * @param string   $package      The zip's https address.
	 * @param string   $sha256       The zip's SHA-256, 64 lowercase hex characters.
	 * @param string   $requires     Minimum WordPress version, or ''.
	 * @param string   $requires_php Minimum PHP version, or ''.
	 * @param string   $tested       WordPress version tested up to, or ''.
	 * @param string   $url          Page about the release, or ''.
	 * @param string[] $changelog    This release's changelog lines, plain text.
	 */
	public function __construct(
		public readonly string $version,
		public readonly string $package,
		public readonly string $sha256,
		public readonly string $requires = '',
		public readonly string $requires_php = '',
		public readonly string $tested = '',
		public readonly string $url = '',
		public readonly array $changelog = array()
	) {}

	/**
	 * Read a decoded manifest; null when anything required is missing or unsafe.
	 *
	 * @param mixed $data Decoded JSON.
	 */
	public static function fromArray( mixed $data ): ?self {
		if ( ! is_array( $data ) ) {
			return null;
		}
		$text    = static fn( string $key ): string => isset( $data[ $key ] ) && is_string( $data[ $key ] ) ? trim( $data[ $key ] ) : '';
		$version = $text( 'version' );
		$package = $text( 'package' );
		$sha256  = strtolower( $text( 'sha256' ) );
		if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $version ) || 1 !== preg_match( '/^[0-9a-f]{64}$/', $sha256 ) || ! self::allowedPackage( $package ) ) {
			return null;
		}
		$versions = array();
		foreach ( array( 'requires', 'requires_php', 'tested' ) as $key ) {
			$versions[ $key ] = 1 === preg_match( '/^\d+(\.\d+){0,2}$/', $text( $key ) ) ? $text( $key ) : '';
		}
		$url       = $text( 'url' );
		$changelog = isset( $data['changelog'] ) && is_array( $data['changelog'] ) ? array_values( array_filter( array_map( static fn( mixed $line ): string => is_string( $line ) ? trim( $line ) : '', $data['changelog'] ), static fn( string $line ): bool => '' !== $line ) ) : array();
		return new self(
			$version,
			$package,
			$sha256,
			$versions['requires'],
			$versions['requires_php'],
			$versions['tested'],
			self::isHttps( $url ) ? $url : '',
			$changelog
		);
	}

	/**
	 * Whether a package address is https on an allowed host, ending in .zip.
	 *
	 * @param string $package Address.
	 */
	public static function allowedPackage( string $package ): bool {
		// Host, then a plain path ending in .zip: no user info, port, query or fragment. Read by pattern, since this
		// class runs without WordPress and wp_parse_url() isn't there.
		if ( 1 !== preg_match( '~^https://([a-z0-9.-]+)(/[^?#\s]*\.zip)$~i', $package, $parts ) ) {
			return false;
		}
		return in_array( strtolower( $parts[1] ), self::PACKAGE_HOSTS, true );
	}

	/**
	 * Whether an address is https with a host.
	 *
	 * @param string $url Address.
	 */
	private static function isHttps( string $url ): bool {
		return 1 === preg_match( '~^https://[^/\s?#]+(?:[/?#]\S*)?$~i', $url );
	}
}
