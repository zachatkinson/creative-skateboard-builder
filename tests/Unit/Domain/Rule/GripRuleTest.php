<?php
/**
 * R6.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\GripRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * R6, grip (spec: Compatibility rules).
 */
final class GripRuleTest extends TestCase {

	/**
	 * The rule's findings for parts by slot; a null leaves the slot empty.
	 *
	 * @param array<string, Part|null> $parts    The parts.
	 * @param RuleSettings|null        $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	private static function check( array $parts, ?RuleSettings $settings = null ): array {
		return ( new GripRule() )->check( Parts::picks( array_filter( $parts ) ), $settings ?? RuleSettings::defaults() );
	}

	private static function deck( ?string $width, ?string $length = null ): Part {
		return Parts::of( ( null === $width ? array() : array( 'deck_width' => $width ) ) + ( null === $length ? array() : array( 'deck_length' => $length ) ) );
	}

	private static function grip( ?string $width, ?string $length = null ): Part {
		return Parts::of( ( null === $width ? array() : array( 'grip_width' => $width ) ) + ( null === $length ? array() : array( 'grip_length' => $length ) ) );
	}

	private static function unchecked(): Finding {
		return new Finding( RuleId::R6, Severity::Fits, MessageCode::LengthUnchecked, Source::Standard, array( SlotKey::DECK ), array() );
	}

	private static function lengthOff(): RuleSettings {
		$document                       = RuleSettings::DEFAULTS;
		$document['r6']['check_length'] = false;
		return RuleSettings::fromDocument( $document );
	}

	public function test_it_is_r6(): void {
		self::assertSame( RuleId::R6, ( new GripRule() )->id() );
	}

	public function test_the_specs_case_a_9_25_inch_deck_with_a_9_inch_sheet_is_blocked(): void {
		self::assertEquals(
			array(
				new Finding(
					RuleId::R6,
					Severity::Block,
					MessageCode::GripTooNarrow,
					Source::Maker,
					array( SlotKey::DECK, SlotKey::GRIP ),
					array(
						'grip_width' => Parts::inches( '9' ),
						'deck_width' => Parts::inches( '9.25' ),
					)
				),
			),
			self::check( array( SlotKey::DECK => self::deck( '9.25', '32' ), SlotKey::GRIP => self::grip( '9', '33' ) ) )
		);
	}

	public function test_the_specs_case_a_34_inch_deck_with_a_33_inch_sheet_is_blocked(): void {
		self::assertEquals(
			array(
				new Finding(
					RuleId::R6,
					Severity::Block,
					MessageCode::GripTooShort,
					Source::Maker,
					array( SlotKey::DECK, SlotKey::GRIP ),
					array(
						'grip_length' => Parts::inches( '33' ),
						'deck_length' => Parts::inches( '34' ),
					)
				),
			),
			self::check( array( SlotKey::DECK => self::deck( '8.5', '34' ), SlotKey::GRIP => self::grip( '9', '33' ) ) )
		);
	}

	public function test_a_sheet_at_least_as_wide_and_as_long_as_the_deck_fits(): void {
		self::assertSame( array(), self::check( array( SlotKey::DECK => self::deck( '9', '33' ), SlotKey::GRIP => self::grip( '9', '33' ) ) ), 'the same size' );
		self::assertSame( array(), self::check( array( SlotKey::DECK => self::deck( '8.25', '31.875' ), SlotKey::GRIP => self::grip( '9', '33' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::DECK => self::deck( '10', '33' ), SlotKey::GRIP => self::grip( '10', '33' ) ) ), '10 in sheets cover decks up to 10 in' );
		self::assertSame( MessageCode::GripTooNarrow, self::check( array( SlotKey::DECK => self::deck( '9.0001', '33' ), SlotKey::GRIP => self::grip( '9', '33' ) ) )[0]->code );
		self::assertSame( MessageCode::GripTooShort, self::check( array( SlotKey::DECK => self::deck( '9', '33.0001' ), SlotKey::GRIP => self::grip( '9', '33' ) ) )[0]->code );
	}

	public function test_a_sheet_too_narrow_and_too_short_is_told_both(): void {
		$findings = self::check( array( SlotKey::DECK => self::deck( '9.5', '36' ), SlotKey::GRIP => self::grip( '9', '33' ) ) );
		self::assertSame( array( MessageCode::GripTooNarrow, MessageCode::GripTooShort ), array_column( $findings, 'code' ) );
	}

	public function test_a_deck_with_no_length_skips_the_length_check_and_is_flagged(): void {
		self::assertEquals( array( self::unchecked() ), self::check( array( SlotKey::DECK => self::deck( '8.5' ) ) ), 'on its own' );
		self::assertEquals( array( self::unchecked() ), self::check( array( SlotKey::DECK => self::deck( '8.5' ), SlotKey::GRIP => self::grip( '9', '33' ) ) ) );
		self::assertEquals( array( self::unchecked() ), self::check( array( SlotKey::DECK => self::deck( '8.5' ), SlotKey::GRIP => self::grip( '9' ) ) ), 'a sheet needs no length for it' );
		$narrow = self::check( array( SlotKey::DECK => self::deck( '9.5' ), SlotKey::GRIP => self::grip( '9' ) ) );
		self::assertSame( array( MessageCode::LengthUnchecked, MessageCode::GripTooNarrow ), array_column( $narrow, 'code' ) );
	}

	public function test_a_kit_can_switch_the_length_check_off(): void {
		$parts = array( SlotKey::DECK => self::deck( '8.5', '34' ), SlotKey::GRIP => self::grip( '9', '33' ) );
		self::assertSame( array(), self::check( $parts, self::lengthOff() ) );
		self::assertSame( array(), self::check( array( SlotKey::DECK => self::deck( '8.5' ) ), self::lengthOff() ), 'and nothing is flagged' );
		self::assertSame( array(), self::check( array( SlotKey::DECK => self::deck( '8.5', '34' ), SlotKey::GRIP => self::grip( '9' ) ), self::lengthOff() ), 'and a sheet needs no length' );
		self::assertSame( MessageCode::GripTooNarrow, self::check( array( SlotKey::DECK => self::deck( '9.5', '34' ), SlotKey::GRIP => self::grip( '9' ) ), self::lengthOff() )[0]->code );
	}

	public function test_missing_specs(): void {
		$deckWidth  = Finding::missing( RuleId::R6, SlotKey::DECK, SpecKey::DeckWidth );
		$gripWidth  = Finding::missing( RuleId::R6, SlotKey::GRIP, SpecKey::GripWidth );
		$gripLength = Finding::missing( RuleId::R6, SlotKey::GRIP, SpecKey::GripLength );
		self::assertEquals( array( $gripWidth ), self::check( array( SlotKey::GRIP => self::grip( null, '33' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::GRIP => self::grip( '9' ) ) ), 'before a deck is picked a sheet needs no length' );
		self::assertEquals( array( $deckWidth ), self::check( array( SlotKey::DECK => self::deck( null, '32' ) ) ) );
		self::assertEquals( array( $deckWidth, self::unchecked() ), self::check( array( SlotKey::DECK => self::deck( null ) ) ) );
		self::assertEquals( array( $gripLength ), self::check( array( SlotKey::DECK => self::deck( '8.5', '32' ), SlotKey::GRIP => self::grip( '9' ) ) ), 'against a deck with a length it does' );
		self::assertEquals( array( $gripWidth, $gripLength ), self::check( array( SlotKey::DECK => self::deck( '8.5', '32' ), SlotKey::GRIP => self::grip( null ) ) ) );
		self::assertEquals( array( $deckWidth ), self::check( array( SlotKey::DECK => self::deck( null, '34' ), SlotKey::GRIP => self::grip( '9', '36' ) ) ) );
		$short = self::check( array( SlotKey::DECK => self::deck( null, '34' ), SlotKey::GRIP => self::grip( '9', '33' ) ) );
		self::assertSame( array( MessageCode::SpecMissing, MessageCode::GripTooShort ), array_column( $short, 'code' ), 'what can be compared still is' );
	}

	public function test_parts_on_their_own_with_their_specs_say_nothing(): void {
		self::assertSame( array(), self::check( array() ) );
		self::assertSame( array(), self::check( array( SlotKey::DECK => self::deck( '8.5', '32' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::GRIP => self::grip( '9', '33' ) ) ) );
	}
}
