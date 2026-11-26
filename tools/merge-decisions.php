<?php
/**
 * Git's merge driver for docs/DECISIONS.md (D-462), registered by `composer hooks`:
 *
 *     merge.decisions.driver = php tools/merge-decisions.php %O %A %B
 *
 * Lanes add decisions at the end of the file and index lines at the end of the index, each from its own range of
 * numbers, so two lanes' additions always meet at the same place and git calls it a conflict. This runs git's own
 * three-way merge, then settles each conflict whose two sides only add: entries (`### D-…`) or index lines (`[D-…`)
 * are kept from both sides and put in number order. A conflict that changes a line the base had, or two sides adding
 * the same number, is left marked for a person, and the driver fails as git's own merge would.
 *
 * Usage: php tools/merge-decisions.php <base> <ours> <theirs>. The result is written to <ours>.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

// phpcs:disable WordPress.WP.AlternativeFunctions -- A git driver run by git, outside WordPress.

/**
 * The number a block starts with: an entry's `### D-462` or an index line's `[D-462`, else null.
 *
 * @param string $block The block's first line.
 */
function cbuild_decision_number( string $block ): ?int {
	return 1 === preg_match( '/^(?:### D-|.*?\[D-)(\d+)/', $block, $found ) ? (int) $found[1] : null;
}

/**
 * The side's text cut into blocks: entries for `### D-` text, else one block per non-blank line.
 *
 * @param string $text A conflict's side.
 * @return list<string>
 */
function cbuild_decision_blocks( string $text ): array {
	if ( str_contains( $text, '### D-' ) ) {
		$parts = preg_split( '/^(?=### D-)/m', $text ) ?: array();
		$head  = trim( (string) array_shift( $parts ) );
		if ( '' !== $head ) {
			return array();
		}
		return array_map( static fn( string $part ): string => rtrim( $part ) . "\n", $parts );
	}
	return array_values(
		array_map(
			static fn( string $line ): string => $line . "\n",
			array_filter( explode( "\n", $text ), static fn( string $line ): bool => '' !== trim( $line ) )
		)
	);
}

/**
 * One conflict settled, or null where it isn't additions alone.
 *
 * @param string $ours   Our side.
 * @param string $base   The base's side (diff3).
 * @param string $theirs Their side.
 */
function cbuild_decision_settle( string $ours, string $base, string $theirs ): ?string {
	if ( '' !== trim( $base ) ) {
		return null;
	}
	$blocks  = array_merge( cbuild_decision_blocks( $ours ), cbuild_decision_blocks( $theirs ) );
	$entries = str_contains( $ours . $theirs, '### D-' );
	$numbers = array();
	foreach ( $blocks as $block ) {
		$number = cbuild_decision_number( $block );
		if ( null === $number || isset( $numbers[ $number ] ) ) {
			return null;
		}
		$numbers[ $number ] = $block;
	}
	if ( array() === $numbers ) {
		return null;
	}
	ksort( $numbers );
	if ( ! $entries ) {
		return implode( '', $numbers );
	}
	// Each side's additions start with the blank line that ends the entry above them, which cutting them into entries
	// drops: put it back, or the first settled entry runs on from the last one before it.
	$lead = str_starts_with( $ours, "\n" ) || str_starts_with( $theirs, "\n" ) ? "\n" : '';
	return $lead . implode( "\n", $numbers );
}

/**
 * The merged text with each additions-only conflict settled.
 *
 * @param string $merged git merge-file's output, in diff3 style.
 * @return array{0: string, 1: int} The text, and how many conflicts are left.
 */
function cbuild_decision_merge( string $merged ): array {
	$left    = 0;
	$pattern = '/^<<<<<<< [^\n]*\n(.*?)^\|\|\|\|\|\|\| [^\n]*\n(.*?)^=======\n(.*?)^>>>>>>> [^\n]*\n/sm';
	$text    = preg_replace_callback(
		$pattern,
		static function ( array $found ) use ( &$left ): string {
			$settled = cbuild_decision_settle( $found[1], $found[2], $found[3] );
			if ( null === $settled ) {
				++$left;
				return $found[0];
			}
			return $settled;
		},
		$merged
	);
	return array( (string) $text, $left );
}

// Run only as git's driver, not when the selftest includes the functions.
if ( 'merge-decisions.php' === basename( (string) ( $argv[0] ?? '' ) ) ) {
	if ( 4 !== $argc ) {
		fwrite( STDERR, "Usage: php tools/merge-decisions.php <base> <ours> <theirs>\n" );
		exit( 2 );
	}
	[ , $cbuild_base, $cbuild_ours, $cbuild_theirs ] = $argv;
	$cbuild_command                                   = sprintf(
		'git merge-file -p --diff3 -L ours -L base -L theirs %s %s %s',
		escapeshellarg( $cbuild_ours ),
		escapeshellarg( $cbuild_base ),
		escapeshellarg( $cbuild_theirs )
	);
	$cbuild_merged = shell_exec( $cbuild_command ); // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.system_calls_shell_exec -- A git driver.
	if ( ! is_string( $cbuild_merged ) ) {
		exit( 2 );
	}
	[ $cbuild_text, $cbuild_left ] = cbuild_decision_merge( $cbuild_merged );
	file_put_contents( $cbuild_ours, $cbuild_text );
	exit( $cbuild_left > 0 ? 1 : 0 );
}
