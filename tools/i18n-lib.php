<?php
/**
 * The functions the i18n check uses (tools/i18n-check.php), apart so the unit tests can load them without running
 * the check: reading a PO or POT file, and the punctuation spacing of the locales the plugin ships (Creative Search's D-158).
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

/**
 * Entries of a PO or POT file, keyed by context and msgid.
 *
 * @param string $file The file.
 * @return array<string, array{id: string, plural: ?string, strs: array<int, string>, fuzzy: bool}>
 */
function cbuild_po_entries( string $file ): array {
	$lines   = file( $file, FILE_IGNORE_NEW_LINES );
	$entries = array();
	$entry   = null;
	$field   = null;
	$flush   = static function () use ( &$entry, &$entries ): void {
		if ( null !== $entry && '' !== $entry['id'] ) {
			$entries[ $entry['ctx'] . "\4" . $entry['id'] ] = $entry;
		}
		$entry = null;
	};
	$fresh   = static fn(): array => array(
		'ctx'    => '',
		'id'     => '',
		'plural' => null,
		'strs'   => array(),
		'fuzzy'  => false,
	);
	foreach ( false === $lines ? array() : $lines as $line ) {
		if ( '' === trim( $line ) ) {
			$flush();
			$field = null;
			continue;
		}
		if ( str_starts_with( $line, '#,' ) ) {
			$entry          = $entry ?? $fresh();
			$entry['fuzzy'] = str_contains( $line, 'fuzzy' );
			continue;
		}
		if ( str_starts_with( $line, '#' ) ) {
			continue;
		}
		if ( 1 === preg_match( '/^(msgctxt|msgid_plural|msgid|msgstr(?:\[(\d+)\])?) "(.*)"$/', $line, $m ) ) {
			$entry = $entry ?? $fresh();
			if ( 'msgctxt' === $m[1] && '' !== $entry['id'] ) {
				$flush();
				$entry = $fresh();
			}
			$field = 'msgstr' === substr( $m[1], 0, 6 ) ? 'str' . ( '' === $m[2] ? '0' : $m[2] ) : $m[1];
			cbuild_po_append( $entry, $field, stripcslashes( $m[3] ) );
			continue;
		}
		if ( null !== $entry && null !== $field && 1 === preg_match( '/^"(.*)"$/', $line, $m ) ) {
			cbuild_po_append( $entry, $field, stripcslashes( $m[1] ) );
		}
	}
	$flush();
	return $entries;
}

/**
 * Add text to one field of an entry.
 *
 * @param array<string, mixed> $entry The entry.
 * @param string               $field The field.
 * @param string               $text  The text.
 */
function cbuild_po_append( array &$entry, string $field, string $text ): void {
	if ( 'msgctxt' === $field ) {
		$entry['ctx'] .= $text;
	} elseif ( 'msgid' === $field ) {
		$entry['id'] .= $text;
	} elseif ( 'msgid_plural' === $field ) {
		$entry['plural'] = ( $entry['plural'] ?? '' ) . $text;
	} else {
		$n                   = (int) substr( $field, 3 );
		$entry['strs'][ $n ] = ( $entry['strs'][ $n ] ?? '' ) . $text;
	}
}

/**
 * The locale of a PO file: its `Language` header, else the end of its file name (`creative-skateboard-builder-fr_CA.po`).
 *
 * @param string $file The file.
 */
function cbuild_po_locale( string $file ): string {
	$text = (string) file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- a build tool reading a local file.
	if ( 1 === preg_match( '/^"Language: ([A-Za-z_]+)\\\\n"$/m', $text, $m ) ) {
		return $m[1];
	}
	return 1 === preg_match( '/-([a-z]{2}(?:_[A-Z]{2})?)\\.po$/', $file, $m ) ? $m[1] : '';
}

/**
 * The punctuation spacing a translated string gets wrong in its locale, each as a sentence.
 *
 * Canadian French (Office québécois de la langue française, as the plugin's French strings follow it): a no-break
 * space (U+00A0) before a colon and inside guillemets, so the colon or the guillemet never starts or ends a line on
 * its own; no space before ; ! ? (fr_FR would want a narrow no-break space there: a locale with other rules needs
 * its own). Other locales: none checked.
 *
 * @param string $locale The locale (`fr_CA`).
 * @param string $text   A translated string.
 * @return string[]
 */
function cbuild_typography( string $locale, string $text ): array {
	if ( 'fr_CA' !== $locale ) {
		return array();
	}
	$problems = array();
	if ( 1 === preg_match( '/[ \\x{202F}]:/u', $text ) ) {
		$problems[] = 'has a plain or narrow space before a colon (fr_CA wants a no-break space, U+00A0)';
	}
	// A colon straight after a word, a placeholder or a guillemet; not in an address (https://) or a time (10:30).
	if ( 1 === preg_match( '/(?<=[\\p{L}\\p{N}»)$])(?<!\\d(?=:\\d)):(?!\\/\\/)/u', $text ) ) {
		$problems[] = 'has no space before a colon (fr_CA wants a no-break space, U+00A0)';
	}
	if ( 1 === preg_match( '/«(?!\\x{00A0})|(?<!\\x{00A0})»/u', $text ) ) {
		$problems[] = 'has « » without a no-break space inside (fr_CA wants U+00A0 after « and before »)';
	}
	return $problems;
}
