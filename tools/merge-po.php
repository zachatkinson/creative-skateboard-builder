<?php
/**
 * Git's merge driver for the translations' .po files (D-463), registered by `composer hooks`:
 *
 *     merge.po.driver = php tools/merge-po.php %O %A %B
 *
 * Two lanes that each add strings both change the .po's header dates and add entries beside one another, which git's
 * line merge calls conflicts. This merges by entry instead, an entry being its msgctxt and msgid:
 *
 * - the header is ours (the landing remakes the dates with `composer i18n:po`);
 * - an entry only one side changed or added takes that side's;
 * - an entry one side removed and the other left as the base had it goes;
 * - an entry both sides changed differently stays ours, and the driver fails so a person looks.
 *
 * Ours keep their order, with their new entries after; `composer i18n:po` sorts the file at landing.
 *
 * Usage: php tools/merge-po.php <base> <ours> <theirs>. The result is written to <ours>.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions -- A git driver run by git, outside WordPress.

/**
 * A .po file's entries by key, in their order. The header is the entry whose msgid is empty, keyed ''.
 *
 * @param string $po The file.
 * @return array<string, string>
 */
function cbuild_po_entries( string $po ): array {
	$entries = array();
	foreach ( preg_split( "/\n\s*\n/", trim( $po ) ) ?: array() as $block ) {
		$block = trim( $block );
		if ( '' === $block ) {
			continue;
		}
		$entries[ cbuild_po_key( $block ) ] = $block;
	}
	return $entries;
}

/**
 * An entry's key: its msgctxt and msgid, with each continued line joined, so a comment or a translation changing
 * leaves it the same entry.
 *
 * @param string $block The entry.
 */
function cbuild_po_key( string $block ): string {
	$key   = '';
	$field = '';
	foreach ( explode( "\n", $block ) as $line ) {
		if ( 1 === preg_match( '/^(msgctxt|msgid|msgid_plural|msgstr(?:\[\d+\])?)\s+(".*")$/', $line, $found ) ) {
			$field = $found[1];
			if ( 'msgctxt' === $field || 'msgid' === $field ) {
				$key .= $field . ':' . $found[2];
			}
		} elseif ( str_starts_with( $line, '"' ) && ( 'msgctxt' === $field || 'msgid' === $field ) ) {
			$key .= $line;
		}
	}
	return 'msgid:""' === $key ? '' : $key;
}

/**
 * The three sides merged by entry.
 *
 * @param string $base   The base.
 * @param string $ours   Ours.
 * @param string $theirs Theirs.
 * @return array{0: string, 1: list<string>} The file, and the keys both sides changed differently.
 */
function cbuild_po_merge( string $base, string $ours, string $theirs ): array {
	$was     = cbuild_po_entries( $base );
	$mine    = cbuild_po_entries( $ours );
	$other   = cbuild_po_entries( $theirs );
	$out     = array();
	$clashes = array();
	foreach ( $mine as $key => $block ) {
		if ( '' === $key ) {
			$out[ $key ] = $block;
			continue;
		}
		$before = $was[ $key ] ?? null;
		$after  = $other[ $key ] ?? null;
		if ( null === $after ) {
			// Theirs removed it: it goes if ours left it as it was.
			if ( null !== $before && $before === $block ) {
				continue;
			}
			$out[ $key ] = $block;
		} elseif ( $after === $block || $after === $before ) {
			$out[ $key ] = $block;
		} elseif ( $block === $before ) {
			$out[ $key ] = $after;
		} else {
			$out[ $key ] = $block;
			$clashes[]   = $key;
		}
	}
	foreach ( $other as $key => $block ) {
		if ( isset( $mine[ $key ] ) ) {
			continue;
		}
		// Ours removed it: it goes if theirs left it as it was.
		if ( isset( $was[ $key ] ) && $was[ $key ] === $block ) {
			continue;
		}
		$out[ $key ] = $block;
	}
	return array( implode( "\n\n", $out ) . "\n", $clashes );
}

// Run only as git's driver, not when the selftest includes the functions.
if ( 'merge-po.php' === basename( (string) ( $argv[0] ?? '' ) ) ) {
	if ( 4 !== $argc ) {
		fwrite( STDERR, "Usage: php tools/merge-po.php <base> <ours> <theirs>\n" );
		exit( 2 );
	}
	[ $cbuild_text, $cbuild_clashes ] = cbuild_po_merge(
		(string) file_get_contents( $argv[1] ),
		(string) file_get_contents( $argv[2] ),
		(string) file_get_contents( $argv[3] )
	);
	file_put_contents( $argv[2], $cbuild_text );
	foreach ( $cbuild_clashes as $cbuild_key ) {
		fwrite( STDERR, "Both sides changed the entry {$cbuild_key}; ours is kept.\n" );
	}
	exit( array() === $cbuild_clashes ? 0 : 1 );
}
