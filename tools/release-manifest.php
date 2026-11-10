<?php
/**
 * Writes the update manifest (info.json) for a built zip: version, package address, SHA-256, requirements and the
 * release's changelog lines from readme.txt, the ones written for shop owners. Updates\Manifest reads it on client sites.
 *
 * Usage: php tools/release-manifest.php tmp/creative-skateboard-builder-1.2.0.zip > tmp/info.json
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions, WordPress.Security.EscapeOutput -- A build script, run outside WordPress.

const RELEASES = 'https://github.com/zachatkinson/creative-releases';

$root = dirname( __DIR__ );
$zip  = $argv[1] ?? '';
if ( '' === $zip || ! is_file( $zip ) ) {
	fwrite( STDERR, "Usage: php tools/release-manifest.php <zip>\n" );
	exit( 1 );
}

$header  = (string) file_get_contents( $root . '/creative-skateboard-builder.php' );
$readme  = (string) file_get_contents( $root . '/readme.txt' );
$field   = static function ( string $text, string $name ): string {
	return preg_match( '/^[\s*]*' . preg_quote( $name, '/' ) . ':\s*(\S+)/mi', $text, $m ) ? $m[1] : '';
};
$version = $field( $header, 'Version' );
if ( 1 !== preg_match( '/^\d+\.\d+\.\d+$/', $version ) || basename( $zip ) !== "creative-skateboard-builder-{$version}.zip" ) {
	fwrite( STDERR, "The zip's name must match the plugin header's version ({$version}).\n" );
	exit( 1 );
}

// The lines a shop owner reads in "View details": readme.txt's == Changelog == entry for this version, which is
// written for them. CHANGELOG.md is the developers' (decision numbers, class names) and stays out of the popup.
$changelog = array();
if ( preg_match( '/^== Changelog ==\s*$(.*?)(?=^== |\z)/ms', $readme, $section ) && preg_match( '/^= ' . preg_quote( $version, '/' ) . ' =\s*$(.*?)(?=^= |\z)/ms', $section[1], $m ) ) {
	foreach ( explode( "\n", $m[1] ) as $line ) {
		if ( str_starts_with( $line, '* ' ) ) {
			$changelog[] = trim( substr( $line, 2 ) );
		}
	}
}
if ( array() === $changelog ) {
	fwrite( STDERR, "readme.txt's == Changelog == has no lines under = {$version} =.\n" );
	exit( 1 );
}
foreach ( $changelog as $line ) {
	if ( 1 === preg_match( '/\bD-\d{3}\b|`|\\\\|\bsrc\//', $line ) ) {
		fwrite( STDERR, "readme.txt's changelog is the shop owner's: no decision numbers, code or paths. Found: {$line}\n" );
		exit( 1 );
	}
}

echo json_encode(
	array(
		'version'      => $version,
		'package'      => RELEASES . "/releases/download/creative-skateboard-builder-{$version}/creative-skateboard-builder-{$version}.zip",
		'sha256'       => hash_file( 'sha256', $zip ),
		'requires'     => $field( $readme, 'Requires at least' ),
		'requires_php' => $field( $readme, 'Requires PHP' ),
		'tested'       => $field( $readme, 'Tested up to' ),
		'url'          => RELEASES . "/releases/tag/creative-skateboard-builder-{$version}",
		'changelog'    => $changelog,
	),
	JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
) . "\n";
