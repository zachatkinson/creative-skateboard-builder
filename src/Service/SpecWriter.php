<?php
/**
 * Checks a part's specs before they are stored.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\StoredSpecs;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Domain\Spec\TableRow;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place a part's own specs are checked, whoever sends them: the Part specs panel, an import or the command
 * line. A value is read as the index's sources read it, and stored as the index writes it, so what is stored reads
 * the same in every locale: 8.25, 8,25 and 8 1/4 are all stored as 8.2500.
 *
 * @since 0.3.0
 */
final class SpecWriter {

	/**
	 * A problem's key when it is with the table or the size, not a spec.
	 */
	public const TABLE = 'spec_table';

	/**
	 * Problems with the table and the size. A value's problem carries the domain's own code.
	 */
	public const TABLE_MISSING = StoredSpecs::TABLE_MISSING;
	public const SIZE_MISSING  = StoredSpecs::SIZE_MISSING;
	public const SIZE_LONG     = 'size_long';

	/**
	 * A narrowest deck wider than the widest. Each figure reads alone, and the two together fit no deck: R1 would
	 * hide the truck from every one. The problem's text is the two figures as the index writes them, with a tab
	 * between.
	 */
	public const RANGE = 'deck_range_reversed';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecTables $tables The makers' charts.
	 */
	public function __construct( private SpecTables $tables ) {}

	/**
	 * Check what was sent for one part.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, string>                $sent      What was typed, by spec key. A key left out is left as
	 *                                                        it is; a blank one is removed; a key this version does
	 *                                                        not know is passed over.
	 * @param array<string, string>                $stored    The part's own values now.
	 * @param string                               $separator The shop's decimal separator.
	 * @param array{table: int, size: string}|null $named    The table and the size sent, null to leave them as they
	 *                                                       are.
	 * @param array{table: int, size: string}      $naming    The table and the size the part names now.
	 */
	public function draft( array $sent, array $stored, string $separator = '.', ?array $named = null, array $naming = array(
		'table' => 0,
		'size'  => '',
	) ): SpecDraft {
		$fields   = array();
		$problems = array();
		foreach ( SpecKey::cases() as $key ) {
			$kept = trim( $stored[ $key->value ] ?? '' );
			$text = trim( $sent[ $key->value ] ?? $kept );
			if ( isset( $sent[ $key->value ] ) && '' !== $text ) {
				$reading = Reading::typed( $key, $text, Origin::Field, '', $separator );
				if ( $reading instanceof Reading ) {
					$text = $reading->text;
				} else {
					$problems[] = array(
						'key'  => $key->value,
						'code' => $reading->reason,
						'text' => $text,
					);
					$text       = $kept;
				}
			}
			if ( '' !== $text ) {
				$fields[ $key->value ] = $text;
			}
		}

		foreach ( $stored as $name => $text ) {
			// What a newer version wrote under a key this one does not know is left where it is.
			if ( null === SpecKey::tryFrom( $name ) && '' !== trim( $text ) ) {
				$fields[ $name ] = trim( $text );
			}
		}

		if ( self::reversed( $fields[ SpecKey::DeckMin->value ] ?? '', $fields[ SpecKey::DeckMax->value ] ?? '' ) ) {
			$problems[] = array(
				'key'  => SpecKey::DeckMin->value,
				'code' => self::RANGE,
				'text' => $fields[ SpecKey::DeckMin->value ] . "\t" . $fields[ SpecKey::DeckMax->value ],
			);
			// Both go back to what was stored: there is no telling which of the two was mistyped.
			foreach ( array( SpecKey::DeckMin->value, SpecKey::DeckMax->value ) as $end ) {
				if ( '' === trim( $stored[ $end ] ?? '' ) ) {
					unset( $fields[ $end ] );
				} else {
					$fields[ $end ] = trim( $stored[ $end ] );
				}
			}
		}
		$table = $naming['table'];
		$size  = $naming['size'];
		if ( null !== $named ) {
			$asked   = trim( $named['size'] );
			$found   = $this->tables->find( $named['table'] )[ $named['table'] ] ?? null;
			$problem = $this->refused( $named['table'], $asked, $found );
			if ( null === $problem ) {
				$table = $named['table'];
				// Under the chart's own name for the size: a spreadsheet writes Venture's 5.0 as 5 (D-091).
				$size = $found?->named( $asked )->size ?? $asked;
			} else {
				$problems[] = array(
					'key'  => self::TABLE,
					'code' => $problem,
					'text' => $asked,
				);
			}
		}
		return new SpecDraft( $fields, $table, $size, $problems );
	}

	/**
	 * Whether a narrowest deck is wider than a widest, each as the index writes it. One that is blank is no range.
	 *
	 * @since 0.3.0
	 *
	 * @param string $narrowest The narrowest deck.
	 * @param string $widest    The widest deck.
	 */
	public static function reversed( string $narrowest, string $widest ): bool {
		return '' !== $narrowest && '' !== $widest && (float) $narrowest > (float) $widest;
	}

	/**
	 * Why a table and a size cannot be named, or null when they can. A size with no table is a variation taking its
	 * parent's table, and a table with no size is a parent naming the chart for its variations; neither can be
	 * checked here, and the Catalogue check lists a name that leads nowhere.
	 *
	 * @param int            $table The table's ID, 0 for none.
	 * @param string         $size  The size, '' for none.
	 * @param SpecTable|null $found The table of that ID, null when there is none.
	 */
	private function refused( int $table, string $size, ?SpecTable $found ): ?string {
		if ( strlen( $size ) > TableRow::MAX_SIZE ) {
			return self::SIZE_LONG;
		}
		if ( $table <= 0 ) {
			return null;
		}
		if ( null === $found ) {
			return self::TABLE_MISSING;
		}
		return '' === $size || null !== $found->named( $size ) ? null : self::SIZE_MISSING;
	}
}
