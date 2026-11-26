<?php
/**
 * The changelog's fragments (D-464). Lanes write their entry as `changelog.d/<lane>-<topic>.md` instead of editing
 * CHANGELOG.md, so no two lanes edit one file. Each fragment holds `### Added`, `### Changed`, `### Fixed` (and the
 * other Keep a Changelog headings) with bullets under them.
 *
 * - `php tools/changelog.php --check`: every fragment is headings and bullets, under headings Keep a Changelog names.
 * - `php tools/changelog.php --assemble [<version> <date>]`: puts every fragment's bullets into CHANGELOG.md's
 *   [Unreleased] entry, under its headings in the standard order, removes the fragments, and with a version and date
 *   renames [Unreleased] to that release (`changelog-version-before-merge`). Run it before the release's pull request.
 *
 * tools/api-surface.php reads the fragments as part of [Unreleased], so a lane's new item is named before assembly.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions -- A command-line tool, outside WordPress.

const CBUILD_CHANGELOG_HEADINGS = array( 'Added', 'Changed', 'Deprecated', 'Removed', 'Fixed', 'Security' );

/**
 * A fragment's bullets by heading.
 *
 * @param string $text The fragment.
 * @return array<string, string>|string The bullets, or what's wrong.
 */
function cbuild_changelog_fragment( string $text ) {
	$sections = array();
	$heading  = null;
	foreach ( explode( "\n", $text ) as $at => $line ) {
		if ( 1 === preg_match( '/^### (.+?)\s*$/', $line, $found ) ) {
			if ( ! in_array( $found[1], CBUILD_CHANGELOG_HEADINGS, true ) ) {
				return 'line ' . ( $at + 1 ) . ': "' . $found[1] . '" is no Keep a Changelog heading';
			}
			$heading              = $found[1];
			$sections[ $heading ] = $sections[ $heading ] ?? '';
			continue;
		}
		if ( null === $heading ) {
			if ( '' !== trim( $line ) ) {
				return 'line ' . ( $at + 1 ) . ': text before the first heading';
			}
			continue;
		}
		$sections[ $heading ] .= $line . "\n";
	}
	if ( array() === $sections ) {
		return 'no heading';
	}
	return array_map( static fn( string $body ): string => trim( $body, "\n" ), $sections );
}

/**
 * The fragments' text, in name order, for tools/api-surface.php.
 *
 * @param string $root The repository.
 */
function cbuild_changelog_fragments_text( string $root ): string {
	$files = glob( $root . '/changelog.d/*.md' ) ?: array();
	sort( $files );
	return implode( "\n", array_map( static fn( string $file ): string => (string) file_get_contents( $file ), $files ) );
}

/**
 * CHANGELOG.md with the fragments' bullets in its [Unreleased] entry.
 *
 * @param string                              $changelog The changelog.
 * @param list<array<string, string>>         $fragments Each fragment's bullets by heading.
 * @param string|null                         $version   The release to name the entry, or null to keep [Unreleased].
 * @param string|null                         $date      Its date.
 */
function cbuild_changelog_assemble( string $changelog, array $fragments, ?string $version, ?string $date ): string {
	$entry = '/^## \[Unreleased\][^\n]*\n(.*?)(?=^## \[|^\[[^\]]+\]: |\z)/sm';
	if ( 1 !== preg_match( $entry, $changelog, $found, PREG_OFFSET_CAPTURE ) ) {
		$first     = preg_match( '/^## \[/m', $changelog, $at, PREG_OFFSET_CAPTURE ) ? (int) $at[0][1] : strlen( $changelog );
		$changelog = substr( $changelog, 0, $first ) . "## [Unreleased]\n\n" . substr( $changelog, $first );
		preg_match( $entry, $changelog, $found, PREG_OFFSET_CAPTURE );
	}
	$start    = (int) $found[0][1];
	$length   = strlen( $found[0][0] );
	$existing = cbuild_changelog_fragment( "\n" . $found[1][0] );
	$sections = is_array( $existing ) ? $existing : array();
	$preface  = is_array( $existing ) ? '' : trim( $found[1][0] );
	foreach ( $fragments as $fragment ) {
		foreach ( $fragment as $heading => $body ) {
			$sections[ $heading ] = trim( ( $sections[ $heading ] ?? '' ) . "\n" . $body, "\n" );
		}
	}
	$title = null === $version ? '## [Unreleased]' : '## [' . $version . '] - ' . $date;
	$text  = $title . "\n\n" . ( '' === $preface ? '' : $preface . "\n\n" );
	foreach ( CBUILD_CHANGELOG_HEADINGS as $heading ) {
		if ( isset( $sections[ $heading ] ) && '' !== $sections[ $heading ] ) {
			$text .= '### ' . $heading . "\n\n" . $sections[ $heading ] . "\n\n";
		}
	}
	$changelog = substr( $changelog, 0, $start ) . $text . substr( $changelog, $start + $length );
	// The release's compare link, from the newest one already listed, as each release's entry has had.
	if ( null !== $version && ! str_contains( $changelog, "\n[" . $version . ']: ' ) ) {
		if ( 1 === preg_match( '/^\[(\d+\.\d+\.\d+)\]: (\S+)\/compare\/[^\n]*$/m', $changelog, $link, PREG_OFFSET_CAPTURE ) ) {
			$line      = '[' . $version . ']: ' . $link[2][0] . '/compare/v' . $link[1][0] . '...v' . $version . "\n";
			$at        = (int) $link[0][1];
			$changelog = substr( $changelog, 0, $at ) . $line . substr( $changelog, $at );
		}
	}
	return $changelog;
}

// Run only from the command line, not when another tool includes the functions.
if ( 'changelog.php' === basename( (string) ( $argv[0] ?? '' ) ) ) {
	$cbuild_root  = dirname( __DIR__ );
	$cbuild_mode  = $argv[1] ?? '';
	$cbuild_files = glob( $cbuild_root . '/changelog.d/*.md' ) ?: array();
	sort( $cbuild_files );
	$cbuild_fragments = array();
	$cbuild_status    = 0;
	foreach ( $cbuild_files as $cbuild_file ) {
		$cbuild_read = cbuild_changelog_fragment( (string) file_get_contents( $cbuild_file ) );
		if ( is_string( $cbuild_read ) ) {
			fwrite( STDERR, basename( $cbuild_file ) . ': ' . $cbuild_read . "\n" );
			$cbuild_status = 1;
			continue;
		}
		$cbuild_fragments[] = $cbuild_read;
	}
	if ( '--check' === $cbuild_mode ) {
		echo 'Changelog fragments: ' . count( $cbuild_files ) . ( 0 === $cbuild_status ? " read.\n" : " read, with errors.\n" );
		exit( $cbuild_status );
	}
	if ( '--assemble' !== $cbuild_mode || 0 !== $cbuild_status || ( isset( $argv[2] ) && ! isset( $argv[3] ) ) ) {
		fwrite( STDERR, "Usage: php tools/changelog.php --check | --assemble [<version> <YYYY-MM-DD>]\n" );
		exit( 0 !== $cbuild_status ? 1 : 2 );
	}
	$cbuild_path = $cbuild_root . '/CHANGELOG.md';
	file_put_contents(
		$cbuild_path,
		cbuild_changelog_assemble( (string) file_get_contents( $cbuild_path ), $cbuild_fragments, $argv[2] ?? null, $argv[3] ?? null )
	);
	array_map( 'unlink', $cbuild_files );
	echo 'Changelog: ' . count( $cbuild_files ) . " fragment(s) put into CHANGELOG.md.\n";
}
