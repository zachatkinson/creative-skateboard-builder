<?php
/**
 * Gate 11's scanner: prints every string literal in a folder's PHP files that holds words, as file:line: text. The
 * domain answers in codes and values, and the edge turns them into the shop's language, so a sentence in src/Domain is
 * English a translator never sees (docs/STANDARDS.md: Strings; D-062). Words are two runs of letters with a space
 * between them, which no code, key, pattern or exception code has.
 *
 * Run: php tools/gate-domain-strings.php src/Domain
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

$cbuild_dir = $argv[1] ?? '';
if ( ! is_dir( $cbuild_dir ) ) {
	exit( 0 );
}
$cbuild_files = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $cbuild_dir, FilesystemIterator::SKIP_DOTS ) );
$cbuild_paths = array();
foreach ( $cbuild_files as $cbuild_file ) {
	if ( $cbuild_file instanceof SplFileInfo && 'php' === $cbuild_file->getExtension() ) {
		$cbuild_paths[] = $cbuild_file->getPathname();
	}
}
sort( $cbuild_paths );
foreach ( $cbuild_paths as $cbuild_path ) {
	foreach ( token_get_all( (string) file_get_contents( $cbuild_path ) ) as $cbuild_token ) {
		if ( ! is_array( $cbuild_token ) || ! in_array( $cbuild_token[0], array( T_CONSTANT_ENCAPSED_STRING, T_ENCAPSED_AND_WHITESPACE, T_INLINE_HTML ), true ) ) {
			continue;
		}
		if ( 1 === preg_match( '/[A-Za-z]{2,}\\s+[A-Za-z]{2,}/', $cbuild_token[1] ) ) {
			printf( "%s:%d: %s\n", $cbuild_path, $cbuild_token[2], trim( $cbuild_token[1] ) );
		}
	}
}
