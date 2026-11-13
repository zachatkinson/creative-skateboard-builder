<?php
/**
 * A slot keyed with a word the build link keeps for itself, renamed.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\ReservedSlotKeys;
use PHPUnit\Framework\TestCase;

/**
 * Audit 19, milestone 6 (D-169): 0.6.0 refuses the slot keys `step`, `assembly`, `preset` and `review` (D-142), which a
 * kit saved by 0.4.0 or 0.5.0 may hold. Each is renamed `<key>_slot`, in the slots and in each preset's picks.
 */
final class ReservedSlotKeysTest extends TestCase {

	private static function slots( string ...$keys ): array {
		return array(
			'schema_version' => 1,
			'slots'          => array_map(
				static fn( string $key ): array => array(
					'key'   => $key,
					'label' => ucfirst( $key ),
				),
				$keys
			),
		);
	}

	public function test_a_reserved_key_is_renamed_in_the_slots_and_every_presets_picks(): void {
		$presets = array(
			'schema_version' => 1,
			'presets'        => array(
				array(
					'name'  => 'Street',
					'picks' => array(
						'deck'   => 11,
						'review' => 12,
					),
				),
				array(
					'name'  => 'Park',
					'picks' => array( 'deck' => 13 ),
				),
			),
		);
		$renamed = ReservedSlotKeys::renamed( self::slots( 'deck', 'review', 'step' ), $presets );
		self::assertNotNull( $renamed );
		self::assertSame( array( 'deck', 'review_slot', 'step_slot' ), array_column( $renamed['slots']['slots'], 'key' ) );
		self::assertSame( 'Review', $renamed['slots']['slots'][1]['label'], 'the label the shopper reads is kept' );
		self::assertSame( 1, $renamed['slots']['schema_version'] );
		self::assertSame(
			array(
				'deck'        => 11,
				'review_slot' => 12,
			),
			$renamed['presets']['presets'][0]['picks']
		);
		self::assertSame( array( 'deck' => 13 ), $renamed['presets']['presets'][1]['picks'] );
		self::assertSame( 'Street', $renamed['presets']['presets'][0]['name'] );
		self::assertSame(
			array(
				'review' => 'review_slot',
				'step'   => 'step_slot',
			),
			$renamed['renamed']
		);
	}

	public function test_a_new_key_already_taken_counts_on_until_it_is_free(): void {
		$renamed = ReservedSlotKeys::renamed( self::slots( 'preset', 'preset_slot', 'preset_slot_2' ), array() );
		self::assertNotNull( $renamed );
		self::assertSame( array( 'preset_slot_3', 'preset_slot', 'preset_slot_2' ), array_column( $renamed['slots']['slots'], 'key' ) );
		self::assertSame( array(), $renamed['presets'], 'no presets document stays none' );
	}

	public function test_a_kit_with_no_reserved_key_or_no_slots_is_left_as_it_is(): void {
		self::assertNull( ReservedSlotKeys::renamed( self::slots( 'deck', 'trucks', 'assembly_kit' ), array() ) );
		self::assertNull( ReservedSlotKeys::renamed( array(), array() ) );
		self::assertNull( ReservedSlotKeys::renamed( null, null ) );
		self::assertNull( ReservedSlotKeys::renamed( array( 'slots' => array( 'not a slot', array( 'label' => 'No key' ) ) ), array() ) );
	}

	public function test_presets_that_are_none_or_not_a_document_are_kept_as_they_were(): void {
		$renamed = ReservedSlotKeys::renamed( self::slots( 'assembly' ), null );
		self::assertNotNull( $renamed );
		self::assertNull( $renamed['presets'], 'no presets stored, none written' );
		$odd = ReservedSlotKeys::renamed( self::slots( 'assembly' ), array( 'presets' => array( 'not a preset', array( 'picks' => 'none' ), array( 'picks' => array( 'assembly' => 9 ) ) ) ) );
		self::assertNotNull( $odd );
		self::assertSame( array( 'presets' => array( 'not a preset', array( 'picks' => 'none' ), array( 'picks' => array( 'assembly_slot' => 9 ) ) ) ), $odd['presets'], 'a preset after an odd one is still renamed' );
	}

	public function test_the_count_starts_at_two(): void {
		$renamed = ReservedSlotKeys::renamed( self::slots( 'step', 'step_slot' ), null );
		self::assertNotNull( $renamed );
		self::assertSame( array( 'step_slot_2', 'step_slot' ), array_column( $renamed['slots']['slots'], 'key' ) );
	}
}
