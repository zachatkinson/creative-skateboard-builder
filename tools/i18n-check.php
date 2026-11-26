<?php
/**
 * The i18n check (spec: Quality bar; docs/STANDARDS.md: Internationalisation). Fails when the committed POT is missing a string the code
 * has, or keeps one it lost, and when a shipped translation has an empty, fuzzy or misplaced entry: every msgstr
 * filled, every plural form present, the placeholders of each form the same set as its source's, and the spacing
 * of its locale's punctuation (Creative Search's D-158: in fr_CA a no-break space before a colon and inside « »).
 * From milestone 9 fr_CA is required: the check fails when it is missing.
 *
 * Run: php tools/i18n-check.php tmp/i18n-check.pot languages/creative-skateboard-builder.pot languages/*.po
 * (`composer i18n:check` makes the first file from the code with WP-CLI in wp-env.)
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

require_once __DIR__ . '/i18n-lib.php';

/**
 * A readable name for an entry key: the msgid, with its context when it has one.
 *
 * @param string $key Context, EOT, msgid.
 */
function cbuild_po_name( string $key ): string {
	[ $ctx, $id ] = explode( "\4", $key, 2 ) + array( '', '' );
	return '"' . $id . '"' . ( '' === $ctx ? '' : ' (context "' . $ctx . '")' );
}

/**
 * The placeholders of a string, as a sorted list (`%s`, `%1$s`, `%d`; `%%` is not one).
 *
 * @param string $text The text.
 * @return string[]
 */
function cbuild_placeholders( string $text ): array {
	preg_match_all( '/%(?:\d+\$)?[sdfF]/', str_replace( '%%', '', $text ), $m );
	$found = $m[0];
	sort( $found );
	return $found;
}

[ , $cbuild_fresh, $cbuild_pot ] = $argv + array( '', '', '' );
// Composer runs the script without a shell's globbing rules, so a pattern that matches no file (no translation yet)
// arrives as written and is dropped.
$cbuild_pos = array_values( array_filter( array_slice( $argv, 3 ), static fn( string $file ): bool => ! str_contains( $file, '*' ) ) );
foreach ( array_merge( array( $cbuild_fresh, $cbuild_pot ), $cbuild_pos ) as $cbuild_file ) {
	if ( '' === $cbuild_file || ! is_readable( $cbuild_file ) ) {
		fwrite( STDERR, "Usage: php tools/i18n-check.php <fresh.pot> <committed.pot> [translation.po ...]; cannot read '{$cbuild_file}'.\n" );
		exit( 1 );
	}
}

$cbuild_problems = array();
// From milestone 9 French ships, complete (roadmap; docs/AUDIT.md, dimension 6), so its file must be there to be read.
foreach ( array( 'fr_CA' ) as $cbuild_required ) {
	$cbuild_named = array_filter( $cbuild_pos, static fn( string $file ): bool => str_ends_with( $file, '-' . $cbuild_required . '.po' ) );
	if ( array() === $cbuild_named ) {
		$cbuild_problems[] = "languages/: no {$cbuild_required} translation, which ships complete (run composer i18n:po and translate it)";
	}
}
$cbuild_code     = array_keys( cbuild_po_entries( $cbuild_fresh ) );
$cbuild_kept     = array_keys( cbuild_po_entries( $cbuild_pot ) );
foreach ( array_diff( $cbuild_code, $cbuild_kept ) as $cbuild_key ) {
	$cbuild_problems[] = "{$cbuild_pot}: missing " . cbuild_po_name( $cbuild_key ) . ' (run composer i18n:pot)';
}
// make-pot copies a double-quoted string's escapes as written, so a source string with "\u{00A0}" in it is a msgid
// that never matches the string PHP makes, and no translation of it is ever used (the French draft, 18 November).
foreach ( $cbuild_code as $cbuild_key ) {
	if ( 1 === preg_match( '/\\\\(u\{[0-9A-Fa-f]+\}|x[0-9A-Fa-f]{1,2}|[0-7]{1,3})/', $cbuild_key ) ) {
		$cbuild_problems[] = "{$cbuild_fresh}: " . cbuild_po_name( $cbuild_key ) . ' holds an escape PHP turns into another character; write the character itself in a single-quoted string';
	}
}
foreach ( array_diff( $cbuild_kept, $cbuild_code ) as $cbuild_key ) {
	$cbuild_problems[] = "{$cbuild_pot}: keeps " . cbuild_po_name( $cbuild_key ) . ', which the code no longer has (run composer i18n:pot)';
}
foreach ( $cbuild_pos as $cbuild_po ) {
	$cbuild_entries = cbuild_po_entries( $cbuild_po );
	$cbuild_locale  = cbuild_po_locale( $cbuild_po );
	foreach ( array_diff( $cbuild_kept, array_keys( $cbuild_entries ) ) as $cbuild_key ) {
		$cbuild_problems[] = "{$cbuild_po}: has no entry for " . cbuild_po_name( $cbuild_key ) . ' (run composer i18n:po)';
	}
	// WordPress reads the compiled .l10n.php, not the .po: a translation compiled before its strings were translated
	// shows them in English (the compare's "Compare %s" in fr_CA, 25 November; D-423).
	$cbuild_compiled_file = substr( $cbuild_po, 0, -3 ) . '.l10n.php';
	$cbuild_compiled      = is_readable( $cbuild_compiled_file ) ? include $cbuild_compiled_file : null;
	$cbuild_messages      = is_array( $cbuild_compiled ) && is_array( $cbuild_compiled['messages'] ?? null ) ? $cbuild_compiled['messages'] : null;
	if ( null === $cbuild_messages ) {
		$cbuild_problems[] = "{$cbuild_po}: no compiled {$cbuild_compiled_file} (run composer i18n:compile)";
	}
	foreach ( $cbuild_entries as $cbuild_entry ) {
		$cbuild_name = '"' . $cbuild_entry['id'] . '"';
		if ( null !== $cbuild_messages && ! $cbuild_entry['fuzzy'] ) {
			$cbuild_compiled_key = ( '' === $cbuild_entry['ctx'] ? '' : $cbuild_entry['ctx'] . "\4" ) . $cbuild_entry['id'];
			$cbuild_strs         = $cbuild_entry['strs'];
			ksort( $cbuild_strs );
			if ( ( $cbuild_messages[ $cbuild_compiled_key ] ?? null ) !== implode( "\0", $cbuild_strs ) ) {
				$cbuild_problems[] = "{$cbuild_compiled_file}: {$cbuild_name} is not compiled as {$cbuild_po} translates it (run composer i18n:compile)";
			}
		}
		if ( $cbuild_entry['fuzzy'] ) {
			$cbuild_problems[] = "{$cbuild_po}: {$cbuild_name} is fuzzy";
		}
		$cbuild_forms = null === $cbuild_entry['plural'] ? array( 0 ) : array( 0, 1 );
		foreach ( $cbuild_forms as $cbuild_n ) {
			$cbuild_str = $cbuild_entry['strs'][ $cbuild_n ] ?? '';
			if ( '' === $cbuild_str ) {
				$cbuild_problems[] = "{$cbuild_po}: {$cbuild_name} form {$cbuild_n} is not translated";
				continue;
			}
			$cbuild_source = 1 === $cbuild_n ? (string) $cbuild_entry['plural'] : $cbuild_entry['id'];
			$cbuild_want   = cbuild_placeholders( $cbuild_source );
			$cbuild_have   = cbuild_placeholders( $cbuild_str );
			if ( $cbuild_want !== $cbuild_have ) {
				$cbuild_problems[] = "{$cbuild_po}: {$cbuild_name} form {$cbuild_n} has placeholders " . implode( ' ', $cbuild_have ) . ', the source ' . implode( ' ', $cbuild_want );
			}
			foreach ( cbuild_typography( $cbuild_locale, $cbuild_str ) as $cbuild_typo ) {
				$cbuild_problems[] = "{$cbuild_po}: {$cbuild_name} form {$cbuild_n} {$cbuild_typo}";
			}
		}
	}
}

if ( array() !== $cbuild_problems ) {
	fwrite( STDERR, implode( "\n", $cbuild_problems ) . "\n" . count( $cbuild_problems ) . " i18n problem(s).\n" );
	exit( 1 );
}
echo 'i18n: ' . count( $cbuild_kept ) . ' strings, the POT current, ' . count( $cbuild_pos ) . " translation(s) complete.\n";
