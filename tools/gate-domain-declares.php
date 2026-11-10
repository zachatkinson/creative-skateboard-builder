<?php
/**
 * Gate 13's scanner: prints every statement at the top of a folder's PHP files that is not a declaration, as
 * file:line: token. A domain file carries no direct-access guard, because the domain knows nothing of WordPress
 * (gate 6); what makes that safe is that requesting one runs nothing, and this holds it so (D-063). A file may open
 * with declare, namespace and use, and then declare classes, interfaces, enums and traits. A namespace or a declare
 * with a block of its own is refused whole: what is inside the braces runs as the top of the file does.
 *
 * Run: php tools/gate-domain-declares.php src/Domain
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
$cbuild_skipped = array( T_OPEN_TAG, T_WHITESPACE, T_COMMENT, T_DOC_COMMENT );
$cbuild_opens   = array( T_DECLARE, T_NAMESPACE, T_USE, T_FINAL, T_ABSTRACT, T_READONLY, T_CLASS, T_INTERFACE, T_ENUM, T_TRAIT, T_ATTRIBUTE );
foreach ( $cbuild_paths as $cbuild_path ) {
	$cbuild_depth      = 0;
	$cbuild_first      = true;
	$cbuild_opens_with = null;
	$cbuild_line       = 1;
	foreach ( token_get_all( (string) file_get_contents( $cbuild_path ) ) as $cbuild_token ) {
		$cbuild_id   = is_array( $cbuild_token ) ? $cbuild_token[0] : null;
		$cbuild_text = is_array( $cbuild_token ) ? $cbuild_token[1] : $cbuild_token;
		$cbuild_line = is_array( $cbuild_token ) ? $cbuild_token[2] : $cbuild_line;
		if ( in_array( $cbuild_id, $cbuild_skipped, true ) ) {
			continue;
		}
		if ( 0 === $cbuild_depth && $cbuild_first ) {
			$cbuild_first      = false;
			$cbuild_opens_with = $cbuild_id;
			if ( ! in_array( $cbuild_id, $cbuild_opens, true ) ) {
				printf( "%s:%d: %s\n", $cbuild_path, $cbuild_line, trim( $cbuild_text ) );
			}
		}
		if ( '{' === $cbuild_text || T_CURLY_OPEN === $cbuild_id || T_DOLLAR_OPEN_CURLY_BRACES === $cbuild_id ) {
			if ( 0 === $cbuild_depth && in_array( $cbuild_opens_with, array( T_NAMESPACE, T_DECLARE ), true ) ) {
				printf( "%s:%d: a %s with a block\n", $cbuild_path, $cbuild_line, T_NAMESPACE === $cbuild_opens_with ? 'namespace' : 'declare' );
			}
			++$cbuild_depth;
		} elseif ( '}' === $cbuild_text ) {
			--$cbuild_depth;
			$cbuild_first = $cbuild_first || 0 === $cbuild_depth;
		} elseif ( ';' === $cbuild_text && 0 === $cbuild_depth ) {
			$cbuild_first = true;
		}
	}
}
