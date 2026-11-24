<?php
/**
 * What the Build tab sends, checked and made into the kit's documents.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Assembly;
use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Fields;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitLayout;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Preset;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureParser;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Picture\PictureSettings;
use CreativeBranding\SkateboardBuilder\Domain\Spec\HeightClass;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Port\DocumentSchemas;
use CreativeBranding\SkateboardBuilder\Port\Kits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Build tab's sections become the kit's documents here (spec: Admin, The kit's Build tab). Each is
 * built from what was typed, read by the domain, which says what is wrong and where, and then held to its schema.
 * One that fails is not saved and the rest are. Presets are held to the slots and rules that will be in force once
 * the save is done, and are saved only when the rules accept every one. The tab, and anything else that edits a
 * kit, calls this one service; it stores nothing itself.
 *
 * @since 0.4.0
 */
final class KitEditor {

	/**
	 * The kit's four documents, by the names this service and the schemas know them by. Where each is stored is
	 * the caller's to know.
	 *
	 * @since 0.4.0
	 */
	public const SLOTS    = 'slots';
	public const RULES    = 'rules';
	public const PRESETS  = 'presets';
	public const ASSEMBLY = 'assembly';
	public const PICTURE  = 'picture';
	public const LAYOUT   = 'layout';

	/**
	 * The kit's settings from 1.2.0 on: the Builder page, the start button and the width guide (D-354). A new kit
	 * setting goes in this document, which the release before never reads, never as a new key in an older one.
	 *
	 * @since 1.2.0
	 */
	public const SETTINGS = 'settings';

	/**
	 * The picture's wheelbase anchor is outside what a deck has: 10 to 20 in.
	 *
	 * @since 0.7.0
	 */
	public const WHEELBASE_RANGE = 'picture_wheelbase_range';

	/**
	 * A width guide's row names a deck width outside 5 to 12 in (#114).
	 *
	 * @since 1.2.0
	 */
	public const GUIDE_WIDTH = 'width_guide_width_range';

	/**
	 * A width guide names a shoe size twice (#114).
	 *
	 * @since 1.2.0
	 */
	public const GUIDE_SHOE_TWICE = 'width_guide_shoe_twice';

	/**
	 * A document the domain read and its schema still refused. The schemas and the domain agree
	 * (`KitSchemasTest`), so this is a guard and not a path a person should meet.
	 *
	 * @since 0.4.0
	 */
	public const SCHEMA = 'document_schema';

	/**
	 * Presets were sent for a kit that has no slots that read, stored or sent.
	 *
	 * @since 0.4.0
	 */
	public const NO_SLOTS = 'presets_without_slots';

	/**
	 * The slots sent would be saved beside presets that are not being saved with them, and a stored preset names
	 * a slot they leave out, or leaves empty one a build must now have.
	 *
	 * @since 0.4.0
	 */
	public const LEAVE_PRESETS = 'slots_leave_presets';

	/**
	 * A preset names a part its slot does not take, or one the rules block beside the preset's other parts.
	 *
	 * @since 0.4.0
	 */
	public const NOT_IN_SLOT = 'preset_part_not_in_slot';
	public const BLOCKED     = 'preset_part_blocked';

	/**
	 * A note, not a refusal: parts in a slot that takes more than one unit have no set size, so R7 cannot say how
	 * many a build takes and they are not offered (D-062).
	 *
	 * @since 0.4.0
	 */
	public const SET_SIZE = 'set_size_missing';

	/**
	 * The settings name a Builder page the tab does not offer: not a published page that holds the kit's Builder
	 * block, nor the one the kit has (1.2.0, #113).
	 *
	 * @since 1.2.0
	 */
	public const BUILDER_PAGE = 'layout_builder_page_not_offered';

	/**
	 * The slots the rules read, which a new slot may be made as. Any other new slot gets its key from its label.
	 *
	 * @since 0.4.0
	 */
	public const KINDS = array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP, SlotKey::SPACERS );

	/**
	 * The longest a key may be, as the schema says.
	 */
	private const KEY_LENGTH = 32;

	/**
	 * An assembly document that reads, for building a kit to hold presets to when the kit's own was refused: the
	 * rules do not read it.
	 */
	private const NO_ASSEMBLY = array(
		'schema_version' => 1,
		'default_on'     => false,
		'fee'            => '0',
	);

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Kits            $kits    Finds the parts a slot's sources take.
	 * @param DocumentSchemas $schemas The documents' schemas.
	 */
	public function __construct( private Kits $kits, private DocumentSchemas $schemas ) {}

	/**
	 * Sort what was sent. A section that was not sent, or is not a list of fields, is left as it is stored.
	 *
	 * @since 0.4.0
	 *
	 * @param array<mixed>                             $sent      The tab's fields: `slots`, `rules`, `presets` and `assembly`.
	 * @param array<string, array<string, mixed>|null> $stored    The kit's documents as stored, by name; null where there is none.
	 * @param string                                   $separator The shop's decimal separator.
	 * @param list<int>|null                           $pages     The pages the settings' Builder page may be, from
	 *                                                            1.2.0: those that hold the kit's Builder block. The
	 *                                                            one it has is kept too. Null checks none.
	 */
	public function draft( array $sent, array $stored, string $separator = '.', ?array $pages = null ): KitDraft {
		$built = array();
		if ( is_array( $sent['slots'] ?? null ) ) {
			$built[ self::SLOTS ] = self::slots( $sent['slots'], self::keysOf( $stored[ self::SLOTS ] ?? null ) );
		}
		if ( is_array( $sent['rules'] ?? null ) ) {
			$built[ self::RULES ] = self::rules( $sent['rules'], $separator );
		}
		if ( is_array( $sent['assembly'] ?? null ) ) {
			$built[ self::ASSEMBLY ] = self::assembly( $sent['assembly'], $separator );
		}
		if ( is_array( $sent['picture'] ?? null ) ) {
			$built[ self::PICTURE ] = self::picture( $sent['picture'], $separator );
		}
		if ( is_array( $sent['layout'] ?? null ) ) {
			$built[ self::LAYOUT ] = self::layout( $sent['layout'] );
		}
		if ( is_array( $sent['settings'] ?? null ) ) {
			$built[ self::SETTINGS ] = self::settings( $sent['settings'], $stored[ self::SETTINGS ] ?? null, $separator );
		}

		$problems = array();
		$inForce  = array();
		foreach ( array( self::SLOTS, self::RULES, self::ASSEMBLY, self::PICTURE, self::LAYOUT, self::SETTINGS ) as $key ) {
			$inForce[ $key ] = $stored[ $key ] ?? null;
			if ( ! isset( $built[ $key ] ) ) {
				continue;
			}
			$found = $this->held( $key, $built[ $key ], self::read( $key, $built[ $key ] ) );
			if ( array() === $found ) {
				$inForce[ $key ] = $built[ $key ];
			}
			$problems = array( ...$problems, ...$found );
		}
		$page = $built[ self::SETTINGS ]['builder_page'] ?? null;
		// The page the kit has is kept while the owner leaves it, though it may be one the tab offers no longer: it is
		// checked again each time it is used.
		$has = $stored[ self::SETTINGS ]['builder_page'] ?? null;
		if ( null !== $pages && is_int( $page ) && $has !== $page && ! in_array( $page, $pages, true ) && ! in_array( self::SETTINGS, array_column( $problems, 'document' ), true ) ) {
			$problems[] = self::problem( self::SETTINGS, self::BUILDER_PAGE, 'builder_page' );
		}

		$parts     = array();
		$unoffered = array();
		if ( is_array( $sent['presets'] ?? null ) ) {
			$presets                = self::presets( $sent['presets'] );
			$built[ self::PRESETS ] = $presets;
			$found                  = self::rows( self::PRESETS, $presets, 'presets', static fn( Fields $row ): Preset => Preset::fromFields( $row ) );
			if ( array() === $found && array() !== $presets['presets'] ) {
				$found = $this->accepted( $presets, $inForce, $parts );
			}
			// Presets sent as they are stored, whose only fault is a part their slot doesn't offer now (the shop hid
			// it, or it can't be bought), are not a failed save: nothing in them changed, and they stay as they are.
			// The tab names each such part instead (issue #62). An edited preset is held to the slots as before.
			if ( array() !== $found && ( $stored[ self::PRESETS ] ?? null ) === $presets && array( self::NOT_IN_SLOT ) === array_unique( array_column( $found, 'code' ) ) ) {
				foreach ( $found as $problem ) {
					$unoffered[] = array(
						'path'   => $problem['path'],
						'values' => $problem['values'],
					);
				}
				unset( $built[ self::PRESETS ] );
				$found = array();
			}
			$problems = array( ...$problems, ...$this->held( self::PRESETS, $presets, $found ) );
		}

		// Slots that are to be saved beside presets that are not: the presets that stay are the stored ones, and
		// the pair must still read, or the kit is one no shopper can open.
		$slotsRefused   = in_array( self::SLOTS, array_column( $problems, 'document' ), true );
		$presetsRefused = in_array( self::PRESETS, array_column( $problems, 'document' ), true );
		$staying        = $stored[ self::PRESETS ] ?? null;
		if ( isset( $built[ self::SLOTS ] ) && ! $slotsRefused && null !== $staying && ( $presetsRefused || ! isset( $built[ self::PRESETS ] ) ) ) {
			try {
				// The slots and the presets alone are in question, so the rules are the defaults, which always read.
				Kit::fromDocuments( $built[ self::SLOTS ], RuleSettings::DEFAULTS, $staying, self::NO_ASSEMBLY );
			} catch ( InvalidDocument ) {
				$problems[] = self::problem( self::SLOTS, self::LEAVE_PRESETS, 'slots' );
			}
		}

		$documents = array();
		$refused   = array();
		foreach ( $built as $key => $document ) {
			if ( in_array( $key, array_column( $problems, 'document' ), true ) ) {
				$refused[ $key ] = $document;
			} else {
				$documents[ $key ] = $document;
			}
		}
		return new KitDraft( $documents, $refused, $problems, $this->notes( $inForce, $parts ), $unoffered );
	}

	/**
	 * A key for a new slot the rules do not read, from its label: lower-case letters, digits and underscores,
	 * starting with a letter, 32 at most. A label with nothing a key can hold gives `slot`.
	 *
	 * @since 0.4.0
	 *
	 * @param string $label The slot's label.
	 */
	public static function keyFrom( string $label ): string {
		$key = trim( (string) preg_replace( '/[^a-z0-9]+/', '_', strtolower( $label ) ), '_' );
		if ( '' === $key ) {
			return 'slot';
		}
		if ( 1 !== preg_match( '/\A[a-z]/', $key ) ) {
			$key = 's_' . $key;
		}
		return rtrim( substr( $key, 0, self::KEY_LENGTH ), '_' );
	}

	/**
	 * A document's problems, with its schema's refusal when the domain found nothing.
	 *
	 * @param string                                                                                   $key      The document's name.
	 * @param array<string, mixed>                                                                     $document The document.
	 * @param list<array{document: string, code: string, path: string, values: array<string, string>}> $found    What the domain found.
	 * @return list<array{document: string, code: string, path: string, values: array<string, string>}>
	 */
	private function held( string $key, array $document, array $found ): array {
		if ( array() !== $found ) {
			return $found;
		}
		$refusal = $this->schemas->refusal( $key, $document );
		return null === $refusal ? array() : array( self::problem( $key, self::SCHEMA, '', array( 'schema' => $refusal ) ) );
	}

	/**
	 * What the domain finds wrong with the slots, the rules or assembly. The slots' rows are read one by one, so
	 * each wrong row is named; what is wrong with them together is named once every row reads.
	 *
	 * @param string               $key      The document's name.
	 * @param array<string, mixed> $document The document.
	 * @return list<array{document: string, code: string, path: string, values: array<string, string>}>
	 */
	private static function read( string $key, array $document ): array {
		if ( self::SLOTS === $key ) {
			$found = self::rows( $key, $document, 'slots', static fn( Fields $row ): Slot => Slot::fromFields( $row ) );
			if ( array() !== $found ) {
				return $found;
			}
		}
		try {
			match ( $key ) {
				self::SLOTS => Slots::fromDocument( $document ),
				self::RULES => RuleSettings::fromDocument( $document ),
				self::PICTURE => PictureSettings::fromDocument( $document ),
				self::LAYOUT => KitLayout::fromDocument( $document ),
				self::SETTINGS => KitSettings::fromDocument( $document ),
				default                 => Assembly::fromDocument( $document ),
			};
		} catch ( InvalidDocument $refused ) {
			$code = match ( true ) {
				self::SETTINGS === $key && DocumentError::Duplicate === $refused->reason => self::GUIDE_SHOE_TWICE,
				DocumentError::OutOfRange !== $refused->reason => $refused->reason->value,
				self::PICTURE === $key => self::WHEELBASE_RANGE,
				self::SETTINGS === $key && 1 === preg_match( '/\Awidth_guide\.rows\[\d+\]\.(from|to)\z/', $refused->path ) => self::GUIDE_WIDTH,
				default => $refused->reason->value,
			};
			return array( self::problem( $key, $code, $refused->path ) );
		}
		return array();
	}

	/**
	 * What is wrong with each row of a document's list, a problem per row at most.
	 *
	 * @param string                  $key      The document's name.
	 * @param array<string, mixed>    $document The document.
	 * @param string                  $rows     The list's name.
	 * @param callable(Fields): mixed $read     Reads a row, or throws.
	 * @return list<array{document: string, code: string, path: string, values: array<string, string>}>
	 */
	private static function rows( string $key, array $document, string $rows, callable $read ): array {
		$found = array();
		foreach ( Fields::document( $document )->rows( $rows, PHP_INT_MAX ) as $row ) {
			try {
				$read( $row );
			} catch ( InvalidDocument $refused ) {
				$found[] = self::problem( $key, $refused->reason->value, $refused->path );
			}
		}
		return $found;
	}

	/**
	 * Whether the kit that will be in force accepts every preset: each names its slots' parts, fills what a build
	 * must, and holds no part the rules block. A part that is sold out is let through: stock comes and goes, and
	 * the Catalogue check and the builder say so when it is.
	 *
	 * @param array<string, mixed>                     $presets The presets document.
	 * @param array<string, array<string, mixed>|null> $inForce The slots, rules and assembly that will be stored.
	 * @param array<string, list<Part>>                $parts   Each slot's parts, filled as they are asked for.
	 * @return list<array{document: string, code: string, path: string, values: array<string, string>}>
	 */
	private function accepted( array $presets, array $inForce, array &$parts ): array {
		$key = self::PRESETS;
		if ( null === $inForce[ self::SLOTS ] ) {
			return array( self::problem( $key, self::NO_SLOTS, 'presets' ) );
		}
		try {
			$kit = Kit::fromDocuments( $inForce[ self::SLOTS ], $inForce[ self::RULES ] ?? RuleSettings::DEFAULTS, $presets, self::NO_ASSEMBLY );
		} catch ( InvalidDocument $refused ) {
			return array( self::problem( $key, $refused->reason->value, $refused->path ) );
		}

		$found     = array();
		$validator = new BuildValidator( FitEngine::standard() );
		foreach ( $kit->presets as $at => $preset ) {
			$picks = Picks::none();
			$ids   = array();
			foreach ( $kit->slots->all() as $slot ) {
				$parts[ $slot->key ] ??= $this->kits->parts( $slot->sources );
				$ids[ $slot->key ]     = array_map( static fn( Part $part ): int => $part->id, $parts[ $slot->key ] );
			}
			foreach ( $preset->picks as $slot => $id ) {
				$at_  = array_search( $id, $ids[ $slot ], true );
				$path = 'presets[' . $at . '].picks.' . $slot;
				if ( false === $at_ ) {
					$found[] = self::problem( $key, self::NOT_IN_SLOT, $path, self::named( $preset->name, $slot, $id ) );
					continue;
				}
				$picks = $picks->with( $slot, $parts[ $slot ][ $at_ ] );
			}
			// The validator names a misfit from both sides, the deck and the trucks that do not fit it. It is said
			// once, of the part picked later, as the builder's steps would meet it.
			$misfits = array();
			foreach ( $validator->validate( $kit, $picks, $ids )->problems as $problem ) {
				// A blocked part alone has findings, and it always names its part.
				foreach ( $problem->findings as $finding ) {
					// A message belongs to one rule, so it names the misfit.
					$misfits[ $finding->code->value ] = self::problem(
						$key,
						self::BLOCKED,
						'presets[' . $at . '].picks.' . $problem->slot,
						self::named( $preset->name, $problem->slot, $problem->part ) + array(
							'rule'    => $finding->rule->value,
							'message' => $finding->code->value,
						)
					);
				}
			}
			$found = array( ...$found, ...array_values( $misfits ) );
		}
		return $found;
	}

	/**
	 * What to know of the kit as it will stand: in each slot that takes more than one unit, how many of its parts
	 * give no set size (R7). None while the kit has no slots that read.
	 *
	 * @param array<string, array<string, mixed>|null> $inForce The slots, rules and assembly that will be stored.
	 * @param array<string, list<Part>>                $parts   Each slot's parts, where the presets asked for them.
	 * @return list<array{code: string, slot: string, count: int}>
	 */
	private function notes( array $inForce, array $parts ): array {
		try {
			// A kit with no slots stored or sent does not read, and has nothing to count.
			$kit = Kit::fromDocuments(
				$inForce[ self::SLOTS ],
				RuleSettings::DEFAULTS,
				array(
					'schema_version' => 1,
					'presets'        => array(),
				),
				self::NO_ASSEMBLY
			);
		} catch ( InvalidDocument ) {
			return array();
		}
		$engine = FitEngine::standard();
		$notes  = array();
		foreach ( $kit->slots->all() as $slot ) {
			if ( 1 === $slot->units ) {
				continue;
			}
			$count = 0;
			foreach ( $parts[ $slot->key ] ?? $this->kits->parts( $slot->sources ) as $part ) {
				$count += null === $engine->quantity( $kit, $slot->key, $part ) ? 1 : 0;
			}
			if ( $count > 0 ) {
				$notes[] = array(
					'code'  => self::SET_SIZE,
					'slot'  => $slot->key,
					'count' => $count,
				);
			}
		}
		return $notes;
	}

	/**
	 * The slots document from the tab's rows, in the order sent. A row with neither a key nor a label is no row.
	 *
	 * @param array<mixed> $rows  The rows.
	 * @param list<string> $known The keys the stored kit's slots have.
	 * @return array<string, mixed>
	 */
	private static function slots( array $rows, array $known ): array {
		$slots = array();
		$taken = $known;
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$label = self::text( $row, 'label' );
			$key   = self::text( $row, 'key' );
			if ( ! in_array( $key, $known, true ) ) {
				// A key is the kit's own or it is made here, once: builds and orders store it.
				if ( '' === $label ) {
					continue;
				}
				$kind = self::text( $row, 'kind' );
				if ( in_array( $kind, self::KINDS, true ) ) {
					$key = $kind;
				} else {
					$base = self::keyFrom( $label );
					$key  = $base;
					$n    = 1;
					while ( in_array( $key, $taken, true ) ) {
						++$n;
						$suffix = '_' . $n;
						$key    = substr( $base, 0, self::KEY_LENGTH - strlen( $suffix ) ) . $suffix;
					}
				}
			}
			$taken[] = $key;
			$units   = self::text( $row, 'units' );
			$slot    = array(
				'key'      => $key,
				'label'    => $label,
				'required' => self::on( $row, 'required' ),
				'units'    => 1 === preg_match( '/\A\d{1,9}\z/', $units ) ? (int) $units : $units,
			);
			$help    = self::text( $row, 'help' );
			if ( '' !== $help ) {
				$slot['help'] = $help;
			}
			$sources = array();
			foreach ( array( 'categories', 'brands', 'products' ) as $kind ) {
				$ids = self::ids( $row[ $kind ] ?? null );
				if ( array() !== $ids ) {
					$sources[ $kind ] = $ids;
				}
			}
			foreach ( self::map( $row['attributes'] ?? null ) as $taxonomy => $terms ) {
				$ids = self::ids( $terms );
				if ( array() !== $ids ) {
					$sources['attributes'][] = array(
						'taxonomy' => (string) $taxonomy,
						'terms'    => $ids,
					);
				}
			}
			if ( array() !== $sources ) {
				$slot['sources'] = $sources;
			}
			$default = self::ids( array( $row['default'] ?? null ) );
			if ( array() !== $default ) {
				$slot['default'] = $default[0];
			}
			$slots[] = $slot;
		}
		return array(
			'schema_version' => 1,
			'slots'          => $slots,
		);
	}

	/**
	 * The rules document from the tab's fields. A switch that was not sent is off, as a box left empty is; a
	 * measure that does not read is kept as typed, for the domain to refuse by name.
	 *
	 * @param array<mixed> $sent      The fields, by rule.
	 * @param string       $separator The shop's decimal separator.
	 * @return array<string, mixed>
	 */
	private static function rules( array $sent, string $separator ): array {
		$rules = array( 'schema_version' => 1 );
		foreach ( RuleId::fit() as $rule ) {
			$rules[ $rule->value ] = array( 'enabled' => self::on( self::map( $sent[ $rule->value ] ?? null ), 'enabled' ) );
		}
		$r1 = self::map( $sent[ RuleId::R1->value ] ?? null );
		foreach ( array( 'range_tolerance', 'axle_fit', 'axle_warn' ) as $field ) {
			$rules[ RuleId::R1->value ][ $field ] = self::measure( self::text( $r1, $field ), Unit::Inch, $separator );
		}
		$r3     = self::map( $sent[ RuleId::R3->value ] ?? null );
		$limits = self::map( $r3['limits'] ?? null );
		foreach ( HeightClass::cases() as $class ) {
			$rules[ RuleId::R3->value ]['limits'][ $class->value ] = self::measure( self::text( $limits, $class->value ), Unit::Millimetre, $separator );
		}
		$rules[ RuleId::R3->value ]['risers'] = array();
		foreach ( self::map( $r3['risers'] ?? null ) as $row ) {
			$row   = self::map( $row );
			$over  = self::text( $row, 'over' );
			$riser = self::text( $row, 'riser' );
			if ( '' !== $over || '' !== $riser ) {
				$rules[ RuleId::R3->value ]['risers'][] = array(
					'over'  => self::measure( $over, Unit::Millimetre, $separator ),
					'riser' => self::measure( $riser, Unit::Inch, $separator ),
				);
			}
		}
		$rules[ RuleId::R6->value ]['check_length'] = self::on( self::map( $sent[ RuleId::R6->value ] ?? null ), 'check_length' );
		return $rules;
	}

	/**
	 * The presets document from the tab's rows. A row with no name and no pick is no row.
	 *
	 * @param array<mixed> $rows The rows.
	 * @return array{schema_version: int, presets: list<array<string, mixed>>}
	 */
	private static function presets( array $rows ): array {
		$presets = array();
		foreach ( $rows as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}
			$picks = array();
			foreach ( self::map( $row['picks'] ?? null ) as $slot => $part ) {
				$ids = self::ids( array( $part ) );
				if ( array() !== $ids ) {
					$picks[ $slot ] = $ids[0];
				}
			}
			$name        = self::text( $row, 'name' );
			$description = self::text( $row, 'description' );
			if ( '' === $name && array() === $picks ) {
				continue;
			}
			$preset = compact( 'name', 'picks' );
			if ( '' !== $description ) {
				$preset['description'] = $description;
			}
			// A rider photo, by its attachment's ID (D-252); anything else is kept as sent, for the schema to refuse.
			$image = self::text( $row, 'image' );
			if ( '' !== $image ) {
				$preset['image'] = ctype_digit( $image ) ? (int) $image : $image;
			}
			$presets[] = $preset;
		}
		return array(
			'schema_version' => 1,
			'presets'        => $presets,
		);
	}

	/**
	 * The assembly document from the tab's fields. No fee is a fee of nothing.
	 *
	 * @param array<mixed> $sent      The fields.
	 * @param string       $separator The shop's decimal separator.
	 * @return array<string, mixed>
	 */
	private static function assembly( array $sent, string $separator ): array {
		$fee = self::text( $sent, 'fee' );
		return array(
			'schema_version' => 1,
			'default_on'     => self::on( $sent, 'default_on' ),
			'fee'            => '' === $fee ? '0' : ( '' === $separator ? $fee : str_replace( $separator, '.', $fee ) ),
			'lead_time'      => self::text( $sent, 'lead_time' ),
			'returns_notice' => self::text( $sent, 'returns_notice' ),
		);
	}

	/**
	 * The picture document from the tab's fields: a layer number for each slot the picture draws, and the wheelbase
	 * anchor. The order is the slots by their numbers, equal numbers in the order sent; a slot with none is left to
	 * the usual order (D-192).
	 *
	 * @param array<mixed> $sent      The fields.
	 * @param string       $separator The shop's decimal separator.
	 * @return array<string, mixed>
	 */
	private static function picture( array $sent, string $separator ): array {
		$layers = array();
		foreach ( self::map( $sent['layers'] ?? null ) as $slot => $number ) {
			if ( is_string( $slot ) && is_string( $number ) && ctype_digit( trim( $number ) ) ) {
				// Digits: asort() compares them as numbers, so 9 comes before 10.
				$layers[ $slot ] = $number;
			}
		}
		// asort() keeps equal numbers in the order they came in (PHP 8.0).
		asort( $layers );
		$wheelbase = self::text( $sent, 'wheelbase' );
		return array(
			'schema_version' => 1,
			'order'          => array_keys( $layers ),
			'wheelbase'      => '' === $wheelbase ? '' : self::measure( $wheelbase, Unit::Inch, $separator ),
		);
	}

	/**
	 * The layout document from the tab's fields (D-255): each choice sent as text, which the domain then holds to its
	 * choices. Show ready builds (1.1.0, #104) is a checkbox: one not ticked is not sent, and is off. It holds 1.1.0's
	 * keys alone: a later setting goes in the settings document (D-354).
	 *
	 * @param array<mixed> $sent The fields.
	 * @return array<string, mixed>
	 */
	private static function layout( array $sent ): array {
		$document = array( 'schema_version' => 1 );
		foreach ( array( 'stage_side', 'board_size', 'search', 'motion' ) as $key ) {
			if ( is_string( $sent[ $key ] ?? null ) ) {
				$document[ $key ] = trim( $sent[ $key ] );
			}
		}
		$document['show_presets'] = self::on( $sent, 'show_presets' );
		return $document;
	}

	/**
	 * The settings document from the tab's fields (D-354). Show "Build a complete with this deck" on its parts' pages
	 * (#115) is a checkbox: one not ticked is not sent, and is off. The Builder page (#113) is a page's ID, or empty for
	 * the kit's own page; a form that does not send it at all keeps the one the kit has. The width guide (#114) is
	 * stored only where its section was sent.
	 *
	 * @param array<mixed>              $sent      The fields.
	 * @param array<string, mixed>|null $stored    The settings as stored.
	 * @param string                    $separator The shop's decimal separator.
	 * @return array<string, mixed>
	 */
	private static function settings( array $sent, ?array $stored, string $separator ): array {
		$document = array(
			'schema_version' => 1,
			'start_button'   => self::on( $sent, 'start_button' ),
		);
		if ( is_array( $sent['width_guide'] ?? null ) ) {
			$document['width_guide'] = self::guide( $sent['width_guide'], $separator );
		}
		if ( ! array_key_exists( 'builder_page', $sent ) ) {
			if ( isset( $stored['builder_page'] ) ) {
				$document['builder_page'] = $stored['builder_page'];
			}
			return $document;
		}
		$page = self::text( $sent, 'builder_page' );
		if ( '' !== $page && '0' !== $page ) {
			// Anything but an ID is kept as typed, for the domain to refuse.
			$document['builder_page'] = 1 === preg_match( '/\A[1-9]\d{0,17}\z/', $page ) ? (int) $page : $page;
		}
		return $document;
	}

	/**
	 * The width guide from its section's fields (#114, D-350): whether it is shown, and the shop's own rows unless the
	 * plugin's are ticked. A row left empty is no row, and no rows at all is the plugin's, which are stored as none so
	 * they keep their translatable words. A field left empty in a row that has others is left out, so the domain names
	 * it as missing.
	 *
	 * @param array<mixed> $sent      The section's fields.
	 * @param string       $separator The shop's decimal separator.
	 * @return array<string, mixed>
	 */
	private static function guide( array $sent, string $separator ): array {
		$guide = array( 'shown' => self::on( $sent, 'shown' ) );
		if ( self::on( $sent, 'plugins' ) ) {
			return $guide;
		}
		$rows = array();
		foreach ( self::map( $sent['rows'] ?? null ) as $row ) {
			$row    = self::map( $row );
			$fields = array_filter(
				array(
					'shoe' => self::text( $row, 'shoe' ),
					'from' => self::text( $row, 'from' ),
					'to'   => self::text( $row, 'to' ),
				),
				static fn( string $text ): bool => '' !== $text
			);
			if ( array() === $fields ) {
				continue;
			}
			foreach ( array( 'from', 'to' ) as $width ) {
				if ( isset( $fields[ $width ] ) ) {
					$fields[ $width ] = self::measure( $fields[ $width ], Unit::Inch, $separator );
				}
			}
			$rows[] = $fields;
		}
		if ( array() !== $rows ) {
			$guide['rows'] = $rows;
		}
		return $guide;
	}

	/**
	 * A measure as the documents write one: a decimal with a point and no zeros it does not need. Text that is no
	 * measure is returned as typed.
	 *
	 * @param string $text      What was typed.
	 * @param Unit   $unit      The unit an unmarked number is in.
	 * @param string $separator The shop's decimal separator.
	 */
	private static function measure( string $text, Unit $unit, string $separator ): string {
		$read = MeasureParser::read( $text, $unit, $separator );
		if ( null === $read ) {
			return $text;
		}
		$decimal = rtrim( $read->decimal(), '0' );
		return rtrim( $decimal, '.' );
	}

	/**
	 * The keys of a stored slots document's slots.
	 *
	 * @param array<string, mixed>|null $stored The document.
	 * @return list<string>
	 */
	private static function keysOf( ?array $stored ): array {
		$keys = array();
		foreach ( self::map( $stored['slots'] ?? null ) as $slot ) {
			$key = self::text( self::map( $slot ), 'key' );
			if ( '' !== $key ) {
				$keys[] = $key;
			}
		}
		return $keys;
	}

	/**
	 * A problem.
	 *
	 * @param string                $document The document's name.
	 * @param string                $code     Its code.
	 * @param string                $path     The field's path in the document.
	 * @param array<string, string> $values   What the words need.
	 * @return array{document: string, code: string, path: string, values: array<string, string>}
	 */
	private static function problem( string $document, string $code, string $path, array $values = array() ): array {
		return array(
			'document' => $document,
			'code'     => $code,
			'path'     => $path,
			'values'   => $values,
		);
	}

	/**
	 * The values a refused preset's words need.
	 *
	 * @param string $preset The preset's name.
	 * @param string $slot   The slot's key.
	 * @param ?int   $part   The part's ID; none where the problem names no part.
	 * @return array{preset: string, slot: string, part: string}
	 */
	private static function named( string $preset, string $slot, ?int $part ): array {
		return array(
			'preset' => $preset,
			'slot'   => $slot,
			'part'   => (string) $part,
		);
	}

	/**
	 * A field's text, trimmed; empty when it was not sent or is not text.
	 *
	 * @param array<mixed> $fields The fields.
	 * @param string       $name   The field.
	 */
	private static function text( array $fields, string $name ): string {
		$value = $fields[ $name ] ?? '';
		return is_string( $value ) ? trim( $value ) : '';
	}

	/**
	 * Whether a box was ticked: its field was sent with anything in it.
	 *
	 * @param array<mixed> $fields The fields.
	 * @param string       $name   The field.
	 */
	private static function on( array $fields, string $name ): bool {
		return '' !== self::text( $fields, $name );
	}

	/**
	 * A list of fields, or none when it is not one.
	 *
	 * @param mixed $value What was sent.
	 * @return array<mixed>
	 */
	private static function map( mixed $value ): array {
		return is_array( $value ) ? $value : array();
	}

	/**
	 * The IDs in what was sent: whole numbers of one or more, each once, in the order sent.
	 *
	 * @param mixed $value What was sent.
	 * @return list<int>
	 */
	private static function ids( mixed $value ): array {
		$ids = array();
		foreach ( self::map( $value ) as $id ) {
			if ( is_string( $id ) && 1 === preg_match( '/\A[1-9]\d{0,17}\z/', $id ) ) {
				$id         = (int) $id;
				$ids[ $id ] = $id;
			}
		}
		return array_values( $ids );
	}
}
