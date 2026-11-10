<?php
/**
 * The kit's JSON Schemas against the domain that reads the same documents.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Assembly;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * The edge validates a kit's documents with WordPress's own `rest_validate_value_from_schema()` and the files in
 * `schema/` (spec: Data model), and the domain's value objects then build from them. The two must not disagree: what
 * the domain's fixtures hold, the schemas accept, and what a schema can say is wrong, both refuse.
 */
final class KitSchemasTest extends IntegrationTestCase {

	/**
	 * A schema from the plugin's schema/ folder.
	 *
	 * @param string $name The file's name without its extension.
	 * @return array<string, mixed>
	 */
	private static function schema( string $name ): array {
		$schema = json_decode( (string) file_get_contents( CBUILD_PATH . 'schema/' . $name . '.json' ), true );
		self::assertIsArray( $schema, $name );
		return $schema;
	}

	/**
	 * Whether WordPress accepts a document for a schema.
	 *
	 * @param mixed  $document The decoded document.
	 * @param string $name     The schema.
	 */
	private static function accepts( $document, string $name ): bool {
		return true === rest_validate_value_from_schema( $document, self::schema( $name ), $name );
	}

	/**
	 * A preset that fills the complete kit.
	 *
	 * @return array<string, mixed>
	 */
	private static function presets(): array {
		return array(
			'schema_version' => 1,
			'presets'        => array(
				array(
					'name'        => 'Street',
					'description' => 'A first board',
					'picks'       => array(
						'deck'     => 11,
						'trucks'   => 22,
						'wheels'   => 33,
						'bearings' => 55,
						'hardware' => 66,
						'grip'     => 77,
					),
				),
			),
		);
	}

	public function test_the_schemas_are_draft_4_objects_that_allow_nothing_extra(): void {
		foreach ( array( 'kit-slots', 'kit-rules', 'kit-presets', 'kit-assembly' ) as $name ) {
			$schema = self::schema( $name );
			$this->assertSame( 'http://json-schema.org/draft-04/schema#', $schema['$schema'], $name );
			$this->assertSame( 'object', $schema['type'], $name );
			$this->assertFalse( $schema['additionalProperties'], $name );
			$this->assertContains( 'schema_version', $schema['required'], $name );
			$this->assertSame( array( 1 ), $schema['properties']['schema_version']['enum'], $name );
		}
	}

	public function test_what_the_domain_builds_from_the_schemas_accept(): void {
		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['help']    = 'Start with a width';
		$slots['slots'][0]['default'] = null;
		$slots['slots'][1]['default'] = 812;
		$slots['slots'][1]['sources'] = array(
			'categories' => array( 14 ),
			'brands'     => array( 3 ),
			'products'   => array( 812, 813 ),
			'attributes' => array(
				array(
					'taxonomy' => 'pa_sheet-size',
					'terms'    => array( 21 ),
				),
			),
		);
		$assembly                     = array(
			'fee'            => '15.50',
			'lead_time'      => 'Two working days',
			'returns_notice' => 'Assembled boards are final sale',
		) + KitDocuments::assembly();

		$this->assertTrue( self::accepts( $slots, 'kit-slots' ) );
		$this->assertTrue( self::accepts( RuleSettings::DEFAULTS, 'kit-rules' ) );
		$this->assertTrue( self::accepts( self::presets(), 'kit-presets' ) );
		$this->assertTrue( self::accepts( KitDocuments::presets(), 'kit-presets' ) );
		$this->assertTrue( self::accepts( $assembly, 'kit-assembly' ) );
		$this->assertTrue( self::accepts( KitDocuments::assembly(), 'kit-assembly' ) );

		$kit = Kit::fromDocuments( $slots, RuleSettings::DEFAULTS, self::presets(), $assembly );
		$this->assertSame( 812, $kit->slots->get( 'trucks' )->defaultPart );
		$this->assertSame( 1550, $kit->assembly->fee( 2 ) );
	}

	/**
	 * Documents a schema can tell are wrong: [ schema, the bent document, a reader in the domain ].
	 *
	 * @return array<string, array{string, array<string, mixed>}>
	 */
	public function wrong_documents(): array {
		$slots = static function ( int $at, string $field, $value ): array {
			$document                            = KitDocuments::slots();
			$document['slots'][ $at ][ $field ] = $value;
			return $document;
		};
		$rules = static function ( string $rule, string $field, $value ): array {
			$document                    = RuleSettings::DEFAULTS;
			$document[ $rule ][ $field ] = $value;
			return $document;
		};
		$without = static function ( array $document, string $field ): array {
			unset( $document[ $field ] );
			return $document;
		};
		return array(
			'slots of another version'       => array( 'kit-slots', array( 'schema_version' => 2 ) + KitDocuments::slots() ),
			'no slots'                       => array( 'kit-slots', array( 'slots' => array() ) + KitDocuments::slots() ),
			'a key in capitals'              => array( 'kit-slots', $slots( 2, 'key', 'Wheels' ) ),
			'no units'                       => array( 'kit-slots', $slots( 1, 'units', 0 ) ),
			'a hundred units'                => array( 'kit-slots', $slots( 1, 'units', 100 ) ),
			'a label that is a list'         => array( 'kit-slots', $slots( 1, 'label', array( 'Trucks' ) ) ),
			'a default of none'              => array( 'kit-slots', $slots( 1, 'default', 0 ) ),
			'a product id of none'           => array( 'kit-slots', $slots( 1, 'sources', array( 'products' => array( 0 ) ) ) ),
			'rules without r4'               => array( 'kit-rules', $without( RuleSettings::DEFAULTS, 'r4' ) ),
			'a tolerance as a fraction'      => array( 'kit-rules', $rules( 'r1', 'axle_fit', '1/4' ) ),
			'a limit with its unit'          => array( 'kit-rules', $rules( 'r3', 'limits', array( 'low' => '53 mm', 'mid' => '56', 'high' => '56' ) ) ),
			'an empty riser table'           => array( 'kit-rules', $rules( 'r3', 'risers', array() ) ),
			'a rule switched by a list'      => array( 'kit-rules', $rules( 'r2', 'enabled', array() ) ),
			'seven presets'                  => array( 'kit-presets', array( 'presets' => array_fill( 0, 7, self::presets()['presets'][0] ) ) + self::presets() ),
			'a preset with a bad slot key'   => array( 'kit-presets', array( 'presets' => array( array( 'picks' => array( 'Grip Tape' => 4 ) ) + self::presets()['presets'][0] ) ) + self::presets() ),
			'a preset with a part of none'   => array( 'kit-presets', array( 'presets' => array( array( 'picks' => array( 'deck' => 0 ) + self::presets()['presets'][0]['picks'] ) + self::presets()['presets'][0] ) ) + self::presets() ),
			'assembly with a signed fee'     => array( 'kit-assembly', array( 'fee' => '-15' ) + KitDocuments::assembly() ),
			'assembly with no default'       => array( 'kit-assembly', $without( KitDocuments::assembly(), 'default_on' ) ),
			'assembly with a list for words' => array( 'kit-assembly', array( 'lead_time' => array() ) + KitDocuments::assembly() ),
		);
	}

	/**
	 * @dataProvider wrong_documents
	 *
	 * @param string               $name     The schema.
	 * @param array<string, mixed> $document The bent document.
	 */
	public function test_what_a_schema_refuses_the_domain_refuses_too( string $name, array $document ): void {
		$this->assertFalse( self::accepts( $document, $name ), 'the schema accepted it' );
		$refused = false;
		try {
			switch ( $name ) {
				case 'kit-slots':
					Slots::fromDocument( $document );
					break;
				case 'kit-rules':
					RuleSettings::fromDocument( $document );
					break;
				case 'kit-presets':
					KitDocuments::kit( null, null, $document );
					break;
				default:
					Assembly::fromDocument( $document );
			}
		} catch ( InvalidDocument $e ) {
			$refused = true;
		}
		$this->assertTrue( $refused, 'the domain accepted it' );
	}

	public function test_what_only_the_domain_can_tell_the_schemas_let_through(): void {
		// A schema cannot say "unique by key", "the deck first" or "each row reaches further": the domain's own invariants.
		$repeated                    = KitDocuments::slots();
		$repeated['slots'][3]['key'] = 'trucks';
		$this->assertTrue( self::accepts( $repeated, 'kit-slots' ) );
		$reversed          = KitDocuments::slots();
		$reversed['slots'] = array_reverse( $reversed['slots'] );
		$this->assertTrue( self::accepts( $reversed, 'kit-slots' ) );
		$disordered                 = RuleSettings::DEFAULTS;
		$disordered['r3']['risers'] = array_reverse( $disordered['r3']['risers'] );
		$this->assertTrue( self::accepts( $disordered, 'kit-rules' ) );
		foreach ( array( array( Slots::class, $repeated ), array( Slots::class, $reversed ), array( RuleSettings::class, $disordered ) ) as [ $reader, $document ] ) {
			try {
				$reader::fromDocument( $document );
				$this->fail( 'the domain accepted it' );
			} catch ( InvalidDocument $e ) {
				$this->assertNotSame( '', $e->path );
			}
		}
	}
}
