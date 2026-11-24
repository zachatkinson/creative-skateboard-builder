<?php
/**
 * What the Build tab sends, checked and made into the kit's documents.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Port\DocumentSchemas;
use CreativeBranding\SkateboardBuilder\Service\KitDraft;
use CreativeBranding\SkateboardBuilder\Service\KitEditor;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, The kit's Build tab: each setting is checked against its schema; one that fails isn't saved, the rest
 * saves, and each problem is named with its field. Presets are saved only when the rules accept them. Roadmap,
 * milestone 4: "The Build tab: each validation error", "Presets: a preset that breaks a rule is refused".
 */
final class KitEditorTest extends TestCase {

	private const SLOTS    = KitEditor::SLOTS;
	private const RULES    = KitEditor::RULES;
	private const PRESETS  = KitEditor::PRESETS;
	private const ASSEMBLY = KitEditor::ASSEMBLY;

	private InMemoryKits $kits;

	/**
	 * What the schemas refuse, by the document's name: none unless a test says so.
	 *
	 * @var array<string, string>
	 */
	private array $schemaRefuses = array();

	private KitEditor $editor;

	protected function setUp(): void {
		$this->kits = new InMemoryKits();
		// Each slot's sources are one category, numbered from 1 in the kit's order, and these are its parts.
		$this->kits->found = array(
			'1' => array( Builds::deck(), Builds::deck( '8.0', 101 ) ),
			'2' => array( Builds::truck( '149' ), Builds::truck( '129' ) ),
			'3' => array( Builds::wheel( '54' ) ),
			'4' => array( Builds::riser( '0.125' ) ),
			'5' => array( Builds::bearings() ),
			'6' => array( Builds::bolts() ),
			'7' => array( Builds::grip() ),
		);
		$refuses           = &$this->schemaRefuses;
		$this->editor      = new KitEditor(
			$this->kits,
			new class( $refuses ) implements DocumentSchemas {
				/**
				 * @param array<string, string> $refuses What to refuse.
				 */
				public function __construct( private array &$refuses ) {}
				public function refusal( string $key, mixed $document ): ?string {
					return $this->refuses[ $key ] ?? null;
				}
			}
		);
	}

	/**
	 * The stored documents of the spec's kit, each slot with its category.
	 *
	 * @return array<string, array<string, mixed>|null>
	 */
	private static function stored(): array {
		$slots = KitDocuments::slots();
		foreach ( array_keys( $slots['slots'] ) as $at ) {
			$slots['slots'][ $at ]['sources'] = array( 'categories' => array( $at + 1 ) );
		}
		return array(
			self::SLOTS    => $slots,
			self::RULES    => KitDocuments::rules(),
			self::PRESETS  => KitDocuments::presets(),
			self::ASSEMBLY => KitDocuments::assembly(),
		);
	}

	/**
	 * The tab as it would send the stored kit back unchanged: every value text, as a form sends it.
	 *
	 * @return array<string, mixed>
	 */
	private static function sent(): array {
		$rows = array();
		foreach ( self::stored()[ self::SLOTS ]['slots'] as $at => $slot ) {
			$row = array(
				'key'        => $slot['key'],
				'label'      => $slot['label'],
				'help'       => '',
				'units'      => (string) $slot['units'],
				'categories' => array( (string) ( $at + 1 ) ),
			);
			if ( $slot['required'] ) {
				$row['required'] = '1';
			}
			$rows[] = $row;
		}
		return array(
			'slots'    => $rows,
			'rules'    => array(
				'r1' => array(
					'enabled'         => '1',
					'range_tolerance' => '0',
					'axle_fit'        => '0.25',
					'axle_warn'       => '0.5',
				),
				'r2' => array( 'enabled' => '1' ),
				'r3' => array(
					'enabled' => '1',
					'limits'  => array(
						'low'  => '53',
						'mid'  => '56',
						'high' => '56',
					),
					'risers'  => array(
						array(
							'over'  => '2',
							'riser' => '0.25',
						),
						array(
							'over'  => '6',
							'riser' => '0.5',
						),
					),
				),
				'r4' => array( 'enabled' => '1' ),
				'r5' => array( 'enabled' => '1' ),
				'r6' => array(
					'enabled'      => '1',
					'check_length' => '1',
				),
			),
			'presets'  => array(),
			'assembly' => array(
				'default_on'     => '1',
				'fee'            => '0',
				'lead_time'      => '',
				'returns_notice' => '',
			),
		);
	}

	/**
	 * A preset of the parts that fit, as the tab sends one.
	 *
	 * @param array<string, string> $picks Picks in place of these.
	 * @return array<string, mixed>
	 */
	private static function preset( array $picks = array(), string $name = 'Street' ): array {
		return array(
			'name'        => $name,
			'description' => 'For ledges and stairs.',
			'picks'       => $picks + array(
				'deck'     => '100',
				'trucks'   => '149',
				'wheels'   => '300',
				'risers'   => '',
				'bearings' => '500',
				'hardware' => '600',
				'grip'     => '700',
			),
		);
	}

	/**
	 * Each problem as `document code path`.
	 *
	 * @return list<string>
	 */
	private static function problems( KitDraft $draft ): array {
		return array_map( static fn( array $problem ): string => $problem['document'] . ' ' . $problem['code'] . ' ' . $problem['path'], $draft->problems );
	}

	public function test_the_kit_sent_back_unchanged_is_the_four_documents_it_was(): void {
		$draft = $this->editor->draft( self::sent(), self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array(), $draft->refused );
		self::assertSame( array(), $draft->notes );
		self::assertEquals( self::stored(), $draft->documents );
		self::assertSame( array( self::SLOTS, self::RULES, self::ASSEMBLY, self::PRESETS ), array_keys( $draft->documents ) );
	}

	public function test_a_section_the_tab_did_not_send_is_left_as_it_is(): void {
		$sent = self::sent();
		unset( $sent['rules'], $sent['presets'] );
		$sent['slots'] = 'not rows';
		$draft         = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array( self::ASSEMBLY ), array_keys( $draft->documents ) );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array(), $this->editor->draft( array(), self::stored() )->documents );
	}

	public function test_a_slot_keeps_its_key_whatever_its_label_becomes_and_a_new_one_gets_its_key_once(): void {
		$sent                       = self::sent();
		$sent['slots'][0]['label']  = 'Planche';
		$sent['slots'][0]['help']   = '  Start here.  ';
		$sent['slots'][0]['brands'] = array( '12', '12', 'x', '0', '-4', '15' );
		$sent['slots'][0]['default'] = '100';
		$sent['slots'][0]['attributes'] = array(
			'pa_shape' => array( '31', '32' ),
			'pa_empty' => array(),
		);
		// A new slot the rules know, by its kind; one they do not, by its label; and, before them, a row left blank
		// and something that is no row.
		$sent['slots'][] = array(
			'key'   => '',
			'label' => '',
			'units' => '1',
		);
		$sent['slots'][] = 'not a row';
		$sent['slots'][] = array(
			'key'   => '',
			'kind'  => 'spacers',
			'label' => 'Entretoises',
			'units' => '4',
		);
		$sent['slots'][] = array(
			'key'   => 'forged',
			'kind'  => 'other',
			'label' => '  Stickers & Wax!  ',
			'units' => '1',
			'products' => array( '900' ),
		);
		$sent['slots'][] = array(
			'key'   => '',
			'kind'  => 'other',
			'label' => 'Stickers / wax',
			'units' => '1',
		);
		$long            = str_repeat( 'L', 32 );
		$sent['slots'][] = array(
			'key'   => '',
			'label' => $long,
			'units' => '1',
		);
		$sent['slots'][] = array(
			'key'        => '',
			'label'      => $long,
			'units'      => '1',
			'attributes' => array( '12' => array( '3' ) ),
		);

		$draft = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array( 'slots document_bad_key slots[11].sources.attributes[0].taxonomy' ), self::problems( $draft ), 'an attribute that is no taxonomy' );
		$slots = $draft->refused[ self::SLOTS ]['slots'];
		self::assertSame( '12', $slots[11]['sources']['attributes'][0]['taxonomy'] );
		self::assertSame(
			array(
				'key'      => 'deck',
				'label'    => 'Planche',
				'required' => true,
				'units'    => 1,
				'help'     => 'Start here.',
				'sources'  => array(
					'categories' => array( 1 ),
					'brands'     => array( 12, 15 ),
					'attributes' => array(
						array(
							'taxonomy' => 'pa_shape',
							'terms'    => array( 31, 32 ),
						),
					),
				),
				'default'  => 100,
			),
			$slots[0]
		);
		self::assertSame( array( 'deck', 'trucks', 'wheels', 'risers', 'bearings', 'hardware', 'grip', 'spacers', 'stickers_wax', 'stickers_wax_2', str_repeat( 'l', 32 ), str_repeat( 'l', 30 ) . '_2' ), array_column( $slots, 'key' ) );
		self::assertSame(
			array(
				'key'      => 'stickers_wax',
				'label'    => 'Stickers & Wax!',
				'required' => false,
				'units'    => 1,
				'sources'  => array( 'products' => array( 900 ) ),
			),
			$slots[8],
			'a key the kit does not have is not taken from the form'
		);
	}

	public function test_a_key_made_from_a_label_is_a_key_whatever_the_label(): void {
		self::assertSame( 'slot', KitEditor::keyFrom( '…' ) );
		self::assertSame( 'slot', KitEditor::keyFrom( '' ) );
		self::assertSame( 's_4_wheels', KitEditor::keyFrom( '4 wheels' ) );
		self::assertSame( 'crous', KitEditor::keyFrom( 'Écrous' ) );
		self::assertSame( 'a_b', KitEditor::keyFrom( '--A   b--' ) );
		self::assertSame( str_repeat( 'a', 32 ), KitEditor::keyFrom( str_repeat( 'A', 40 ) ) );
		self::assertSame( str_repeat( 'a', 31 ), KitEditor::keyFrom( str_repeat( 'a', 31 ) . ' b' ), 'cut at 32 with no underscore left at its end' );
	}

	public function test_each_thing_wrong_with_a_slot_is_named_with_its_field_and_no_slot_is_saved(): void {
		$sent                      = self::sent();
		$sent['slots'][1]['label'] = '';
		$sent['slots'][2]['label'] = str_repeat( 'w', 81 );
		$sent['slots'][3]['units'] = 'two';
		$sent['slots'][4]['units'] = '0';
		$sent['slots'][5]['units'] = '100';
		$sent['slots'][6]['help']  = str_repeat( 'h', 301 );
		$draft                     = $this->editor->draft( $sent, self::stored() );
		self::assertSame(
			array(
				'slots document_out_of_range slots[1].label',
				'slots document_out_of_range slots[2].label',
				'slots document_wrong_type slots[3].units',
				'slots document_out_of_range slots[4].units',
				'slots document_out_of_range slots[5].units',
				'slots document_out_of_range slots[6].help',
			),
			self::problems( $draft )
		);
		self::assertArrayNotHasKey( self::SLOTS, $draft->documents );
		self::assertSame( array( self::RULES, self::ASSEMBLY, self::PRESETS ), array_keys( $draft->documents ), 'the rest saves' );
		self::assertSame( array( self::SLOTS ), array_keys( $draft->refused ) );
		self::assertSame( 'two', $draft->refused[ self::SLOTS ]['slots'][3]['units'], 'what was typed is kept to show again' );
	}

	public function test_what_is_wrong_with_the_slots_together_is_named_once_the_rows_read(): void {
		$stored = self::stored();

		$sent = self::sent();
		array_unshift( $sent['slots'], array_pop( $sent['slots'] ) );
		self::assertSame( array( 'slots slots_deck_not_first slots[0].key' ), self::problems( $this->editor->draft( $sent, $stored ) ) );

		$sent                      = self::sent();
		list( $risers, $bearings, $hardware ) = array( $sent['slots'][3], $sent['slots'][4], $sent['slots'][5] );
		$sent['slots'][3]          = $hardware;
		$sent['slots'][4]          = $bearings;
		$sent['slots'][5]          = $risers;
		self::assertSame( array( 'slots slots_risers_after_hardware slots[5].key' ), self::problems( $this->editor->draft( $sent, $stored ) ) );

		$sent            = self::sent();
		$sent['slots'][] = array(
			'key'   => '',
			'kind'  => 'wheels',
			'label' => 'More wheels',
			'units' => '4',
		);
		self::assertSame( array( 'slots document_duplicate slots[7].key' ), self::problems( $this->editor->draft( $sent, $stored ) ), 'a kind the kit already has is not given another key' );

		$sent = self::sent();
		for ( $n = 0; $n < 14; $n++ ) {
			$sent['slots'][] = array(
				'key'   => '',
				'kind'  => 'other',
				'label' => 'Extra ' . $n,
				'units' => '1',
			);
		}
		self::assertSame( array( 'slots document_out_of_range slots' ), self::problems( $this->editor->draft( $sent, $stored ) ), 'twenty at most' );

		$sent          = self::sent();
		$sent['slots'] = array();
		self::assertSame( array( 'slots document_out_of_range slots' ), self::problems( $this->editor->draft( $sent, $stored ) ), 'and one at least' );
	}

	public function test_the_rules_take_the_shops_decimal_separator_and_a_fraction_and_a_box_left_empty_is_off(): void {
		$sent                                 = self::sent();
		$sent['rules']['r1']['axle_fit']      = '1/4';
		$sent['rules']['r1']['axle_warn']     = ' 0,625 ';
		$sent['rules']['r3']['limits']['low'] = '52,5';
		$sent['rules']['r3']['risers']        = array(
			array(
				'over'  => '',
				'riser' => '',
			),
			'not a row',
			array(
				'over'  => '3',
				'riser' => '⅛',
			),
		);
		unset( $sent['rules']['r2']['enabled'], $sent['rules']['r5'], $sent['rules']['r6']['check_length'] );

		$draft = $this->editor->draft( $sent, self::stored(), ',' );
		self::assertSame( array(), $draft->problems );
		$rules = $draft->documents[ self::RULES ];
		self::assertSame(
			array(
				'enabled'         => true,
				'range_tolerance' => '0',
				'axle_fit'        => '0.25',
				'axle_warn'       => '0.625',
			),
			$rules['r1']
		);
		self::assertSame( '52.5', $rules['r3']['limits']['low'] );
		self::assertSame(
			array(
				array(
					'over'  => '3',
					'riser' => '0.125',
				),
			),
			$rules['r3']['risers']
		);
		self::assertSame( array( false, true, false, false ), array( $rules['r2']['enabled'], $rules['r4']['enabled'], $rules['r5']['enabled'], $rules['r6']['check_length'] ) );
		self::assertSame( 1, $rules['schema_version'] );
	}

	public function test_each_thing_wrong_with_the_rules_is_named_with_its_field(): void {
		$stored = self::stored();
		$cases  = array(
			'rules document_bad_measure r1.axle_fit'        => static function ( array &$rules ): void {
				$rules['r1']['axle_fit'] = 'snug';
			},
			'rules document_bad_measure r1.range_tolerance' => static function ( array &$rules ): void {
				$rules['r1']['range_tolerance'] = '';
			},
			'rules rules_out_of_order r1.axle_warn'         => static function ( array &$rules ): void {
				$rules['r1']['axle_warn'] = '0.1';
			},
			'rules document_bad_measure r3.limits.high'     => static function ( array &$rules ): void {
				$rules['r3']['limits']['high'] = '-56';
			},
			'rules rules_out_of_order r3.risers[1].over'    => static function ( array &$rules ): void {
				$rules['r3']['risers'][1]['over'] = '2';
			},
			'rules document_bad_measure r3.risers[0].riser' => static function ( array &$rules ): void {
				$rules['r3']['risers'][0]['riser'] = 'thin';
			},
			'rules document_bad_measure r3.risers[2].riser' => static function ( array &$rules ): void {
				$rules['r3']['risers'][] = array(
					'over'  => '9',
					'riser' => '',
				);
			},
			'rules document_bad_measure r3.risers[2].over'  => static function ( array &$rules ): void {
				$rules['r3']['risers'][] = array(
					'over'  => '',
					'riser' => '0.75',
				);
			},
			'rules document_out_of_range r3.risers'         => static function ( array &$rules ): void {
				$rules['r3']['risers'] = array();
			},
		);
		foreach ( $cases as $expected => $bend ) {
			$sent = self::sent();
			$bend( $sent['rules'] );
			$draft = $this->editor->draft( $sent, $stored );
			self::assertSame( array( $expected ), self::problems( $draft ) );
			self::assertArrayNotHasKey( self::RULES, $draft->documents, $expected );
			self::assertArrayHasKey( self::SLOTS, $draft->documents, 'the rest saves' );
		}
		$sent                            = self::sent();
		$sent['rules']['r1']['axle_fit'] = 'snug';
		self::assertSame( 'snug', $this->editor->draft( $sent, $stored )->refused[ self::RULES ]['r1']['axle_fit'] );
	}

	// D-192: the picture's wheelbase anchor is 10 to 20 in, and says so in its own words; anything else wrong with
	// it is named as the documents name it.
	public function test_the_pictures_wheelbase_is_read_and_one_out_of_range_is_named_in_its_own_words(): void {
		$sent            = self::sent();
		$sent['picture'] = array(
			'layers'    => array( 'deck' => '10', 'grip' => ' 9 ', 'trucks' => 'top', 'wheels' => array( '1' ), 0 => '1' ),
			'wheelbase' => '14,25',
		);
		$draft           = $this->editor->draft( $sent, self::stored(), ',' );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( 'schema_version' => 1, 'order' => array( 'grip', 'deck' ), 'wheelbase' => '14.25' ), $draft->documents[ KitEditor::PICTURE ], 'by number, 9 before 10, spaces trimmed; a word, a list and a field with no slot\'s name left out' );

		foreach ( array(
			'picture ' . KitEditor::WHEELBASE_RANGE . ' wheelbase' => '25',
			'picture document_bad_measure wheelbase'              => 'long',
		) as $expected => $wheelbase ) {
			$sent['picture'] = array( 'wheelbase' => $wheelbase );
			$draft           = $this->editor->draft( $sent, self::stored() );
			self::assertSame( array( $expected ), self::problems( $draft ), $wheelbase );
			self::assertArrayNotHasKey( KitEditor::PICTURE, $draft->documents );
		}
	}

	// Spec (configurator): Settings (D-255): the Layout section's three choices, in `_cbuild_layout`.
	public function test_the_layout_keeps_each_choice_and_refuses_one_it_does_not_have(): void {
		$sent           = self::sent();
		$sent['layout'] = array(
			'stage_side' => 'right',
			'board_size' => 'balanced',
			'motion'     => 'subtle',
			'search'     => 'own',
			'motion'     => 'subtle',
		);
		$draft          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( 'schema_version' => 1, 'stage_side' => 'right', 'board_size' => 'balanced', 'search' => 'own', 'motion' => 'subtle', 'show_presets' => false, 'start_button' => false ), $draft->documents[ KitEditor::LAYOUT ] );

		$sent['layout'] = array( 'stage_side' => 'top' );
		$draft          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array( 'layout document_out_of_range stage_side' ), self::problems( $draft ) );
		self::assertArrayNotHasKey( KitEditor::LAYOUT, $draft->documents );
		self::assertSame( 'top', $draft->refused[ KitEditor::LAYOUT ]['stage_side'] ?? null, 'shown as typed' );

		$sent['layout'] = array(
			'stage_side' => ' left ',
			'board_size' => "large\n",
			'search'     => ' own',
		);
		$draft          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems, 'a choice is read without its outer spaces' );
		self::assertSame( array( 'schema_version' => 1, 'stage_side' => 'left', 'board_size' => 'large', 'search' => 'own', 'show_presets' => false, 'start_button' => false ), $draft->documents[ KitEditor::LAYOUT ] );
	}

	// #104 (R1): Show ready builds is a checkbox, so one not ticked is not sent, and the kit's ready builds are hidden.
	public function test_show_ready_builds_is_kept_on_when_ticked_and_off_when_not(): void {
		$sent           = self::sent();
		$sent['layout'] = array( 'show_presets' => '1' );
		$draft          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( 'schema_version' => 1, 'show_presets' => true, 'start_button' => false ), $draft->documents[ KitEditor::LAYOUT ] );

		$sent['layout'] = array( 'stage_side' => 'left' );
		$draft          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( 'schema_version' => 1, 'stage_side' => 'left', 'show_presets' => false, 'start_button' => false ), $draft->documents[ KitEditor::LAYOUT ] );
	}

	// #115: Show on its parts' pages is a checkbox, so one not ticked is not sent, and the kit places no start button.
	public function test_the_start_button_is_kept_on_when_ticked_and_off_when_not(): void {
		$sent           = self::sent();
		$sent['layout'] = array(
			'show_presets' => '1',
			'start_button' => '1',
		);
		$draft          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( 'schema_version' => 1, 'show_presets' => true, 'start_button' => true ), $draft->documents[ KitEditor::LAYOUT ] );

		$sent['layout'] = array( 'show_presets' => '1' );
		$draft          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( 'schema_version' => 1, 'show_presets' => true, 'start_button' => false ), $draft->documents[ KitEditor::LAYOUT ] );
	}

	// #113: the Builder page is a page's ID, or nothing for the kit's own page. Where the tab says which pages may be
	// chosen, any other is refused; a select not sent at all keeps the page the kit has.
	public function test_the_builder_page_is_kept_as_an_id_and_one_not_offered_is_refused(): void {
		$sent           = self::sent();
		$sent['layout'] = array(
			'builder_page' => '42',
			'start_button' => '1',
		);
		$draft          = $this->editor->draft( $sent, self::stored(), '.', array( 42, 43 ) );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( 'schema_version' => 1, 'show_presets' => false, 'start_button' => true, 'builder_page' => 42 ), $draft->documents[ KitEditor::LAYOUT ] );

		foreach ( array( '', '0', ' ' ) as $own ) {
			$sent['layout']['builder_page'] = $own;
			$draft                          = $this->editor->draft( $sent, self::stored(), '.', array( 42 ) );
			self::assertSame( array(), $draft->problems, var_export( $own, true ) );
			self::assertArrayNotHasKey( 'builder_page', $draft->documents[ KitEditor::LAYOUT ], 'the kit\'s own page: ' . var_export( $own, true ) );
		}

		$sent['layout']['builder_page'] = '44';
		$draft                          = $this->editor->draft( $sent, self::stored(), '.', array( 42, 43 ) );
		self::assertSame( array( 'layout ' . KitEditor::BUILDER_PAGE . ' builder_page' ), self::problems( $draft ), 'a page the tab does not offer' );
		self::assertArrayNotHasKey( KitEditor::LAYOUT, $draft->documents );
		self::assertSame( 44, $draft->refused[ KitEditor::LAYOUT ]['builder_page'] ?? null, 'shown as chosen' );

		// A layout that is already refused for another field is named at that field alone, and the page is checked
		// once the rest of the section reads.
		$sent['layout']['width_guide'] = array( 'rows' => array( array( 'shoe' => 'EU 40', 'from' => '21' ) ) );
		$draft                         = $this->editor->draft( $sent, self::stored(), '.', array( 42, 43 ) );
		self::assertSame( array( 'layout ' . KitEditor::GUIDE_WIDTH . ' width_guide.rows[0].from' ), self::problems( $draft ) );
		unset( $sent['layout']['width_guide'] );

		$sent['layout']['builder_page'] = 'https://elsewhere.example/';
		$draft                          = $this->editor->draft( $sent, self::stored(), '.', array( 42 ) );
		self::assertSame( array( 'layout document_wrong_type builder_page' ), self::problems( $draft ), 'an address is no page' );

		$sent['layout']['builder_page'] = '44';
		$draft                          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems, 'where the tab names no pages, none is checked' );

		unset( $sent['layout']['builder_page'] );
		$stored                     = self::stored();
		$stored[ KitEditor::LAYOUT ] = array(
			'schema_version' => 1,
			'builder_page'   => 43,
		);
		$draft                      = $this->editor->draft( $sent, $stored, '.', array() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( 43, $draft->documents[ KitEditor::LAYOUT ]['builder_page'] ?? null, 'a form with no Builder page keeps the kit\'s' );
	}

	// #114: the width guide's section. A box to show the guide, a box for the plugin's rows, and the shop's own rows,
	// each width as the shop writes a measure. A row left empty is no row, and the plugin's rows store none.
	public function test_the_width_guide_keeps_the_shops_rows_or_the_plugins_and_whether_it_is_shown(): void {
		$sent           = self::sent();
		$sent['layout'] = array(
			'width_guide' => array(
				'shown' => '1',
				'rows'  => array(
					array( 'shoe' => ' EU 38 to 40 ', 'from' => '7,75', 'to' => '8 in' ),
					array( 'shoe' => '', 'from' => '', 'to' => '' ),
					array( 'shoe' => 'EU 41 and up', 'from' => '8 1/4', 'to' => '' ),
				),
			),
		);
		$draft = $this->editor->draft( $sent, self::stored(), ',' );
		self::assertSame( array(), $draft->problems );
		self::assertSame(
			array(
				'shown' => true,
				'rows'  => array(
					array( 'shoe' => 'EU 38 to 40', 'from' => '7.75', 'to' => '8' ),
					array( 'shoe' => 'EU 41 and up', 'from' => '8.25' ),
				),
			),
			$draft->documents[ KitEditor::LAYOUT ]['width_guide'] ?? null
		);

		// The plugin's rows ticked: whatever the rows hold, none is stored, and the guide shows the plugin's.
		$sent['layout']['width_guide']['plugins'] = '1';
		unset( $sent['layout']['width_guide']['shown'] );
		$draft = $this->editor->draft( $sent, self::stored(), ',' );
		self::assertSame( array( 'shown' => false ), $draft->documents[ KitEditor::LAYOUT ]['width_guide'] ?? null, 'hidden, with the plugin\'s rows' );

		// No rows typed is the plugin's rows too.
		$sent['layout']['width_guide'] = array(
			'shown' => '1',
			'rows'  => array( array( 'shoe' => ' ', 'from' => '', 'to' => '' ) ),
		);
		$draft = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array( 'shown' => true ), $draft->documents[ KitEditor::LAYOUT ]['width_guide'] ?? null );

		// A section that sends no guide, as a 1.1.0 screen's, stores none, and the kit shows the plugin's rows.
		$sent['layout'] = array( 'stage_side' => 'left' );
		self::assertArrayNotHasKey( 'width_guide', $this->editor->draft( $sent, self::stored() )->documents[ KitEditor::LAYOUT ] );
	}

	// #114: what is wrong with a row is named at its field, and the layout is not saved.
	public function test_a_width_guide_row_that_is_wrong_is_named_at_its_field(): void {
		foreach ( array(
			'a width with no shoe size'      => array( array( 'shoe' => '', 'from' => '8', 'to' => '' ), 'layout document_field_missing width_guide.rows[1].shoe' ),
			'a shoe size with no width'      => array( array( 'shoe' => 'EU 40', 'from' => '', 'to' => '8.5' ), 'layout document_field_missing width_guide.rows[1].from' ),
			'a width that is no number'      => array( array( 'shoe' => 'EU 40', 'from' => 'eight', 'to' => '' ), 'layout document_bad_measure width_guide.rows[1].from' ),
			'a width no deck has'            => array( array( 'shoe' => 'EU 40', 'from' => '8', 'to' => '21' ), 'layout ' . KitEditor::GUIDE_WIDTH . ' width_guide.rows[1].to' ),
			'a shoe size the guide has'      => array( array( 'shoe' => 'EU 38', 'from' => '8', 'to' => '' ), 'layout ' . KitEditor::GUIDE_SHOE_TWICE . ' width_guide.rows[1].shoe' ),
			'a widest under the narrowest'   => array( array( 'shoe' => 'EU 40', 'from' => '8.5', 'to' => '8' ), 'layout rules_out_of_order width_guide.rows[1].to' ),
		) as $case => [ $row, $expected ] ) {
			$sent           = self::sent();
			$sent['layout'] = array(
				'width_guide' => array(
					'shown' => '1',
					'rows'  => array( array( 'shoe' => 'EU 38', 'from' => '7.75' ), $row ),
				),
			);
			$draft          = $this->editor->draft( $sent, self::stored() );
			self::assertSame( array( $expected ), self::problems( $draft ), $case );
			self::assertArrayNotHasKey( KitEditor::LAYOUT, $draft->documents, $case );
		}
	}

	public function test_assembly_takes_a_fee_as_the_shop_writes_money_and_each_thing_wrong_is_named(): void {
		$stored                             = self::stored();
		$sent                               = self::sent();
		$sent['assembly']                   = array(
			'fee'            => ' 15,5 ',
			'lead_time'      => ' Two working days. ',
			'returns_notice' => "Assembled boards\ncannot be returned.",
		);
		$draft                              = $this->editor->draft( $sent, $stored, ',' );
		self::assertSame( array(), $draft->problems );
		self::assertSame(
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '15.5',
				'lead_time'      => 'Two working days.',
				'returns_notice' => "Assembled boards\ncannot be returned.",
			),
			$draft->documents[ self::ASSEMBLY ]
		);

		$sent['assembly']['fee'] = '';
		self::assertSame( '0', $this->editor->draft( $sent, $stored )->documents[ self::ASSEMBLY ]['fee'], 'no fee is a fee of nothing' );

		foreach ( array(
			'assembly document_bad_measure fee'            => array( 'fee' => 'free' ),
			'assembly document_bad_measure fee '           => array( 'fee' => '-1' ),
			'assembly document_bad_measure fee  '          => array( 'fee' => '1 500' ),
			'assembly document_out_of_range lead_time'     => array( 'lead_time' => str_repeat( 'd', 201 ) ),
			'assembly document_out_of_range returns_notice' => array( 'returns_notice' => str_repeat( 'r', 1001 ) ),
		) as $expected => $fields ) {
			$sent['assembly'] = $fields;
			$draft = $this->editor->draft( $sent, $stored, ',' );
			self::assertSame( array( trim( $expected ) ), self::problems( $draft ) );
			self::assertArrayNotHasKey( self::ASSEMBLY, $draft->documents );
		}
	}

	public function test_a_preset_the_rules_accept_is_saved(): void {
		$sent              = self::sent();
		$sent['presets'][] = array(
			'name'  => '',
			'picks' => array( 'deck' => '' ),
		);
		$sent['presets'][] = 'not a row';
		$sent['presets'][] = self::preset();
		$draft             = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array( '1', '2', '3', '4', '5', '6', '7' ), $this->kits->asked, 'each slot\'s parts are found once, for the presets and the count together' );
		self::assertSame( array(), $draft->problems );
		self::assertSame(
			array(
				'schema_version' => 1,
				'presets'        => array(
					array(
						'name'        => 'Street',
						'picks'       => array(
							'deck'     => 100,
							'trucks'   => 149,
							'wheels'   => 300,
							'bearings' => 500,
							'hardware' => 600,
							'grip'     => 700,
						),
						'description' => 'For ledges and stairs.',
					),
				),
			),
			$draft->documents[ self::PRESETS ]
		);
	}

	// D-252: a preset's rider photo, an attachment's ID, from the tab's Photo field.
	public function test_a_presets_photo_is_kept_by_its_id_and_an_empty_one_is_none(): void {
		$sent                = self::sent();
		$sent['presets'][]   = array( 'image' => ' 81 ' ) + self::preset();
		$sent['presets'][]   = array(
			'image' => '',
			'name'  => 'Park',
		) + self::preset();
		$saved               = $this->editor->draft( $sent, self::stored() )->documents[ self::PRESETS ]['presets'] ?? array();
		self::assertSame( array( 81, null ), array( $saved[0]['image'] ?? null, $saved[1]['image'] ?? null ) );
		$sent['presets'][0]['image'] = 'a photo';
		$wrong                       = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array( 'presets[0].image' ), array_column( $wrong->problems, 'path' ), 'kept as sent, for the schema to refuse' );
	}

	public function test_a_preset_the_rules_refuse_is_not_saved_and_says_which_part_and_which_rule(): void {
		// A 7.75 in deck is narrower than the 149's range: R1 blocks the trucks.
		$this->kits->found['1'][] = Builds::deck( '7.75', 102 );
		$sent                     = self::sent();
		$sent['presets'][]        = self::preset();
		$sent['presets'][]        = self::preset( array( 'deck' => '102' ), 'Narrow' );
		$draft                    = $this->editor->draft( $sent, self::stored() );

		self::assertSame( array( 'presets preset_part_blocked presets[1].picks.trucks' ), self::problems( $draft ) );
		self::assertSame(
			array(
				'preset'  => 'Narrow',
				'slot'    => 'trucks',
				'part'    => '149',
				'rule'    => 'r1',
				'message' => 'deck_below_range',
			),
			$draft->problems[0]['values']
		);
		self::assertArrayNotHasKey( self::PRESETS, $draft->documents, 'one preset refused, and none is saved' );
		self::assertArrayHasKey( self::SLOTS, $draft->documents );
		self::assertSame( 'Narrow', $draft->refused[ self::PRESETS ]['presets'][1]['name'] );
	}

	/**
	 * Issue #62: presets sent as they are stored, whose only fault is a part their slot doesn't offer now, are kept
	 * and named, not refused. Any other fault, or any edit, and they are held to the slots as before.
	 */
	public function test_unchanged_presets_whose_parts_the_slots_no_longer_offer_are_kept_and_named(): void {
		$this->kits->found['1'][] = Builds::deck( '7.75', 102 );
		$sent                     = self::sent();
		$sent['presets'][]        = self::preset();
		$sent['presets'][]        = self::preset( array( 'deck' => '102' ), 'Narrow' );
		unset( $sent['rules']['r1']['enabled'] );
		$stored                  = self::stored();
		$stored[ self::PRESETS ] = $this->editor->draft( $sent, $stored )->documents[ self::PRESETS ];

		// The shop hides deck 100: Street names it.
		$this->kits->found['1'] = array( Builds::deck( '8.0', 101 ), Builds::deck( '7.75', 102 ) );
		$draft                  = $this->editor->draft( $sent, $stored );
		self::assertSame( array(), $draft->problems );
		self::assertArrayNotHasKey( self::PRESETS, $draft->documents, 'nothing to write' );
		self::assertArrayNotHasKey( self::PRESETS, $draft->refused );
		self::assertArrayHasKey( self::SLOTS, $draft->documents );
		self::assertSame(
			array(
				array(
					'path'   => 'presets[0].picks.deck',
					'values' => array(
						'preset' => 'Street',
						'slot'   => 'deck',
						'part'   => '100',
					),
				),
			),
			$draft->unoffered
		);

		// And its wheels: each part is named, in both presets.
		$this->kits->found['3'] = array();
		self::assertSame(
			array( 'presets[0].picks.deck', 'presets[0].picks.wheels', 'presets[1].picks.wheels' ),
			array_column( $this->editor->draft( $sent, $stored )->unoffered, 'path' )
		);

		// A fault of another kind beside it: R1 on again blocks Narrow's trucks, and the presets are refused.
		$this->kits->found['3'] = array( Builds::wheel( '54' ) );
		$rules                  = $sent;
		$rules['rules']['r1']['enabled'] = '1';
		$draft                  = $this->editor->draft( $rules, $stored );
		self::assertSame( array( 'presets preset_part_not_in_slot presets[0].picks.deck', 'presets preset_part_blocked presets[1].picks.trucks' ), self::problems( $draft ) );
		self::assertSame( array(), $draft->unoffered );

		// Edited, they are refused as before.
		$edited                       = $sent;
		$edited['presets'][0]['name'] = 'Street, edited';
		$draft                        = $this->editor->draft( $edited, $stored );
		self::assertSame( array( 'presets preset_part_not_in_slot presets[0].picks.deck' ), self::problems( $draft ) );
		self::assertArrayHasKey( self::PRESETS, $draft->refused );
		self::assertSame( array(), $draft->unoffered );
	}

	public function test_a_preset_names_only_its_slots_parts_and_fills_each_slot_a_build_must(): void {
		$stored = self::stored();
		foreach ( array(
			'presets preset_part_not_in_slot presets[0].picks.wheels' => array( 'wheels' => '500' ),
			'presets preset_part_not_in_slot presets[0].picks.deck'   => array( 'deck' => '999' ),
			'presets presets_slot_not_filled presets[0].picks.grip'   => array( 'grip' => '' ),
			'presets presets_unknown_slot presets[0].picks.sails'     => array( 'sails' => '9' ),
			'presets document_bad_key presets[0].picks.Sails'         => array( 'Sails' => '9' ),
		) as $expected => $picks ) {
			$sent              = self::sent();
			$sent['presets'][] = self::preset( $picks );
			$draft             = $this->editor->draft( $sent, $stored );
			self::assertSame( array( $expected ), self::problems( $draft ) );
			self::assertArrayNotHasKey( self::PRESETS, $draft->documents, $expected );
		}

		$sent              = self::sent();
		$sent['presets'][] = self::preset( array(), '' );
		self::assertSame( array( 'presets document_out_of_range presets[0].name' ), self::problems( $this->editor->draft( $sent, $stored ) ) );

		$sent = self::sent();
		for ( $n = 0; $n < 7; $n++ ) {
			$sent['presets'][] = self::preset( array(), 'Preset ' . $n );
		}
		self::assertSame( array( 'presets document_out_of_range presets' ), self::problems( $this->editor->draft( $sent, $stored ) ), 'six at most' );
	}

	public function test_a_preset_with_a_part_that_is_sold_out_is_saved_since_stock_comes_and_goes(): void {
		$this->kits->found['3'] = array( Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4 ), 300, 1000, '', false ) );
		$sent                   = self::sent();
		$sent['presets'][]      = self::preset();
		$draft                  = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertArrayHasKey( self::PRESETS, $draft->documents );
	}

	public function test_presets_are_held_to_the_slots_and_rules_being_saved_with_them(): void {
		// The deck's slot now takes category 8, where deck 100 is not.
		$this->kits->found['8']            = array( Builds::deck( '8.5', 105 ) );
		$sent                              = self::sent();
		$sent['slots'][0]['categories']    = array( '8' );
		$sent['presets'][]                 = self::preset();
		self::assertSame( array( 'presets preset_part_not_in_slot presets[0].picks.deck' ), self::problems( $this->editor->draft( $sent, self::stored() ) ) );

		// With R1 switched off in the same save, the narrow deck's preset is accepted.
		$this->kits->found['1'][] = Builds::deck( '7.75', 102 );
		$sent                     = self::sent();
		$sent['presets'][]        = self::preset( array( 'deck' => '102' ) );
		unset( $sent['rules']['r1']['enabled'] );
		self::assertSame( array(), $this->editor->draft( $sent, self::stored() )->problems );
	}

	public function test_presets_are_held_to_what_is_stored_when_what_was_sent_is_refused_and_wait_when_there_is_neither(): void {
		$sent                           = self::sent();
		$sent['slots'][0]['categories'] = array( '8' );
		$sent['slots'][2]['units']      = 'four';
		$sent['presets'][]              = self::preset();
		$draft                          = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array( 'slots document_wrong_type slots[2].units' ), self::problems( $draft ), 'the stored slots still take deck 100' );
		self::assertArrayHasKey( self::PRESETS, $draft->documents );

		// A new kit whose slots were refused has nothing to hold a preset to.
		$new   = array_fill_keys( array( self::SLOTS, self::RULES, self::PRESETS, self::ASSEMBLY ), null );
		$draft = $this->editor->draft( $sent, $new );
		self::assertSame( array( 'slots document_wrong_type slots[2].units', 'presets presets_without_slots presets' ), self::problems( $draft ) );
		self::assertSame( array( self::RULES, self::ASSEMBLY ), array_keys( $draft->documents ) );

		// With no preset sent there is nothing to wait for: an empty list is saved.
		$sent['presets'] = array();
		self::assertSame( array( self::RULES, self::ASSEMBLY, self::PRESETS ), array_keys( $this->editor->draft( $sent, $new )->documents ) );
	}

	// The auditor's should-fix 3, milestone 4: the slots were saved on their own while the presets sent with them were
	// refused, and the stored presets and the new slots then made a kit that does not read, which no shopper can open.
	public function test_slots_that_the_presets_left_in_force_cannot_live_with_are_not_saved(): void {
		$with            = self::sent();
		$with['presets'] = array( self::preset() );
		$first           = $this->editor->draft( $with, self::stored() );
		self::assertSame( array(), $first->problems );
		$stored = array_merge( self::stored(), $first->documents );

		// The risers' slot, which the stored preset leaves empty, is now one a build must have.
		$sent = $with;
		foreach ( $sent['slots'] as $at => $slot ) {
			if ( 'risers' === $slot['key'] ) {
				$sent['slots'][ $at ]['required'] = '1';
			}
		}
		$draft = $this->editor->draft( $sent, $stored );
		self::assertSame( array( 'presets presets_slot_not_filled presets[0].picks.risers', 'slots slots_leave_presets slots' ), self::problems( $draft ) );
		self::assertArrayNotHasKey( self::SLOTS, $draft->documents );
		self::assertArrayHasKey( self::SLOTS, $draft->refused );

		// The same slots with the presets not sent at all.
		unset( $sent['presets'] );
		$draft = $this->editor->draft( $sent, $stored );
		self::assertSame( array( 'slots slots_leave_presets slots' ), self::problems( $draft ) );
		self::assertArrayNotHasKey( self::SLOTS, $draft->documents );

		// Slots refused for themselves are said once, as what is wrong with them, and not again for the presets.
		$bad                    = $sent;
		$bad['slots'][0]['key'] = 'Not A Key';
		$told                   = self::problems( $this->editor->draft( $bad, $stored ) );
		self::assertNotSame( array(), $told );
		self::assertNotContains( 'slots slots_leave_presets slots', $told );

		// And with the preset put right in the same save, both are saved.
		$sent['presets'] = array( self::preset( array( 'risers' => '400' ) ) );
		$this->kits->found['4'] ??= array();
		$draft                   = $this->editor->draft( $sent, $stored );
		self::assertNotContains( 'slots slots_leave_presets slots', self::problems( $draft ) );

		// Slots the stored presets can live with are saved as before.
		$sent = self::sent();
		unset( $sent['presets'] );
		$sent['slots'][0]['label'] = 'Board';
		self::assertSame( array(), $this->editor->draft( $sent, $stored )->problems );
	}

	public function test_a_new_kit_with_refused_rules_holds_its_presets_to_the_usual_rules(): void {
		$new                             = array_fill_keys( array( self::SLOTS, self::RULES, self::PRESETS, self::ASSEMBLY ), null );
		$this->kits->found['1'][]        = Builds::deck( '7.75', 102 );
		$sent                            = self::sent();
		$sent['rules']['r1']['axle_fit'] = 'snug';
		$sent['assembly']['fee']         = 'free';
		$sent['presets'][]               = self::preset( array( 'deck' => '102' ) );
		self::assertSame(
			array(
				'rules document_bad_measure r1.axle_fit',
				'assembly document_bad_measure fee',
				'presets preset_part_blocked presets[0].picks.trucks',
			),
			self::problems( $this->editor->draft( $sent, $new ) )
		);
	}

	public function test_parts_with_no_set_size_in_a_slot_that_takes_more_than_one_are_counted_r7(): void {
		$bare                   = static fn( int $id ): Part => Parts::of( array( 'wheel_diameter' => '54' ), $id );
		$this->kits->found['3'] = array( Builds::wheel( '54' ), $bare( 301 ), $bare( 302 ) );
		// A deck needs none: its slot takes one (D-064).
		$this->kits->found['1'] = array( Parts::of( array( 'deck_width' => '8.5' ), 100 ) );
		$draft                  = $this->editor->draft( self::sent(), self::stored() );
		self::assertSame( array(), $draft->problems, 'the kit is saved: its parts are filled in afterwards' );
		self::assertSame(
			array(
				array(
					'code'  => KitEditor::SET_SIZE,
					'slot'  => 'wheels',
					'count' => 2,
				),
			),
			$draft->notes
		);
		self::assertSame( 'set_size_missing', KitEditor::SET_SIZE );
	}

	public function test_no_part_is_counted_while_the_slots_are_refused_and_a_new_kit_has_none_stored(): void {
		$this->kits->found['3'][]  = Parts::of( array( 'wheel_diameter' => '54' ), 301 );
		$sent                      = self::sent();
		$sent['slots'][2]['units'] = 'four';
		$new                       = array_fill_keys( array( self::SLOTS, self::RULES, self::PRESETS, self::ASSEMBLY ), null );
		self::assertSame( array(), $this->editor->draft( $sent, $new )->notes );
		self::assertCount( 1, $this->editor->draft( $sent, self::stored() )->notes, 'the stored slots are still the kit\'s' );
	}

	public function test_a_document_its_schema_refuses_is_not_saved_whatever_the_domain_made_of_it(): void {
		$this->schemaRefuses = array( self::ASSEMBLY => 'rest_additional_properties_forbidden' );
		$draft               = $this->editor->draft( self::sent(), self::stored() );
		self::assertSame( array( 'assembly document_schema ' ), self::problems( $draft ) );
		self::assertSame( array( 'schema' => 'rest_additional_properties_forbidden' ), $draft->problems[0]['values'] );
		self::assertSame( array( self::SLOTS, self::RULES, self::PRESETS ), array_keys( $draft->documents ) );
		self::assertSame( array( self::ASSEMBLY ), array_keys( $draft->refused ) );

		$this->schemaRefuses = array( self::PRESETS => 'rest_too_many_items' );
		self::assertSame( array( 'presets document_schema ' ), self::problems( $this->editor->draft( self::sent(), self::stored() ) ) );
	}

	public function test_the_draft_says_whether_a_document_was_refused(): void {
		$sent                    = self::sent();
		$sent['assembly']['fee'] = 'free';
		$draft                   = $this->editor->draft( $sent, self::stored() );
		self::assertTrue( $draft->refuses( self::ASSEMBLY ) );
		self::assertFalse( $draft->refuses( self::SLOTS ) );
	}

	public function test_every_part_a_preset_names_wrongly_is_said_and_a_sold_out_one_hides_no_misfit(): void {
		// The deck is sold out, which is let through, and too narrow for the trucks, which is not.
		$this->kits->found['1'] = array( Parts::of( array( 'deck_width' => '7.75', 'deck_length' => '32', 'hole_pattern' => 'new_school' ), 102, 1000, '', false ) );
		$sent                   = self::sent();
		$sent['presets'][]      = self::preset( array( 'deck' => '102' ) );
		$sent['presets'][]      = self::preset(
			array(
				'deck'   => '102',
				'wheels' => '999',
				'grip'   => '998',
			),
			'Two wrong'
		);
		self::assertSame(
			array(
				'presets preset_part_blocked presets[0].picks.trucks',
				'presets preset_part_not_in_slot presets[1].picks.wheels',
				'presets preset_part_not_in_slot presets[1].picks.grip',
				'presets preset_part_blocked presets[1].picks.trucks',
			),
			self::problems( $this->editor->draft( $sent, self::stored() ) )
		);
		self::assertCount( 7, $this->kits->asked, 'two presets, and each slot asked once' );
	}

	public function test_presets_are_held_to_the_rules_sent_when_the_slots_were_not(): void {
		$this->kits->found['1'][] = Builds::deck( '7.75', 102 );
		$sent                     = self::sent();
		$sent['presets'][]        = self::preset( array( 'deck' => '102' ) );
		unset( $sent['slots'], $sent['rules']['r1']['enabled'] );
		$draft = $this->editor->draft( $sent, self::stored() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( array( self::RULES, self::ASSEMBLY, self::PRESETS ), array_keys( $draft->documents ) );
	}

	public function test_only_the_slots_that_take_more_than_one_are_asked_for_their_parts_and_each_short_slot_is_noted(): void {
		$this->kits->found['3'] = array( Parts::of( array( 'wheel_diameter' => '54' ), 301 ) );
		$this->kits->found['5'] = array( Parts::of( array( 'bearing_bore' => '8' ), 501 ) );
		$draft                  = $this->editor->draft( self::sent(), self::stored() );
		self::assertSame( array( 'wheels', 'bearings' ), array_column( $draft->notes, 'slot' ) );
		self::assertSame( array( 1, 1 ), array_column( $draft->notes, 'count' ) );
		self::assertSame( array( '2', '3', '4', '5', '6' ), $this->kits->asked, 'the deck and the grip take one of any part' );
	}
}
