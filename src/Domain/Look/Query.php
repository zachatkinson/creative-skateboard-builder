<?php
/**
 * A step's search query.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Look;

use function array_keys;
use function implode;
use function preg_split;
use function str_starts_with;

/**
 * What a step's search box matches (SPEC-CONFIGURATOR, Search; D-248). Text comes folded, in lower case with its
 * accents taken off, as the adapter folds both the query and the parts' words; a word is a run of letters and digits.
 *
 * - Without Creative Search, a look matches when every word of the query starts a word of its brand, name, look or
 *   size. It allows no typos.
 * - With it, Creative Search ranks parent products; of each parent's looks, those whose own words a word of the
 *   query, or of its corrected query, starts are kept, and all of them when none is, since then the name or the
 *   brand matched.
 */
final class Query {

	/**
	 * The words of a folded text.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text The text.
	 * @return list<string>
	 */
	public static function words( string $text ): array {
		$words = preg_split( '/[^\p{L}\p{N}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		// Text that is not UTF-8 has no words.
		return false === $words ? array() : $words;
	}

	/**
	 * Whether every word of the query starts a word of the texts. A query with no words matches nothing.
	 *
	 * @since 1.0.0
	 *
	 * @param string       $query The folded query.
	 * @param list<string> $texts The look's folded brand, name, look and size.
	 */
	public static function matches( string $query, array $texts ): bool {
		$asked = self::words( $query );
		if ( array() === $asked ) {
			return false;
		}
		$words = self::words( implode( ' ', $texts ) );
		foreach ( $asked as $word ) {
			if ( ! self::starts( $word, $words ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * The keys of the looks to keep from Creative Search's ranked parents, parent by parent in its order.
	 *
	 * @since 1.0.0
	 *
	 * @param list<int>                               $parents   The parents Creative Search ranked, best first.
	 * @param array<int, array<string, list<string>>> $looks     Each parent's looks: key to the look's own folded words.
	 * @param string                                  $query     The folded query.
	 * @param string|null                             $corrected Creative Search's folded corrected query, if any.
	 * @return list<string>
	 */
	public static function narrow( array $parents, array $looks, string $query, ?string $corrected ): array {
		$asked = array( ...self::words( $query ), ...self::words( $corrected ?? '' ) );
		$kept  = array();
		foreach ( $parents as $parent ) {
			$own     = $looks[ $parent ] ?? array();
			$matched = array();
			foreach ( $own as $key => $texts ) {
				$words = self::words( implode( ' ', $texts ) );
				foreach ( $asked as $word ) {
					if ( self::starts( $word, $words ) ) {
						$matched[] = $key;
						break;
					}
				}
			}
			$kept = array( ...$kept, ...( array() === $matched ? array_keys( $own ) : $matched ) );
		}
		return $kept;
	}

	/**
	 * Whether a word starts one of the words.
	 *
	 * @param string       $word  The query's word.
	 * @param list<string> $words The words.
	 */
	private static function starts( string $word, array $words ): bool {
		foreach ( $words as $held ) {
			if ( str_starts_with( $held, $word ) ) {
				return true;
			}
		}
		return false;
	}
}
