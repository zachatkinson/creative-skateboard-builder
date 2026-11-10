<?php
/**
 * R1.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\DeckWidthRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * R1, deck width and truck size (spec: Compatibility rules).
 */
final class DeckWidthRuleTest extends TestCase {

	/**
	 * The rule's findings for a deck and a truck.
	 *
	 * @param Part|null         $deck     The deck.
	 * @param Part|null         $truck    The truck.
	 * @param RuleSettings|null $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	private static function check( ?Part $deck, ?Part $truck, ?RuleSettings $settings = null ): array {
		$parts = array();
		if ( null !== $deck ) {
			$parts[ SlotKey::DECK ] = $deck;
		}
		if ( null !== $truck ) {
			$parts[ SlotKey::TRUCKS ] = $truck;
		}
		return ( new DeckWidthRule() )->check( Parts::picks( $parts ), $settings ?? RuleSettings::defaults() );
	}

	private static function deck( string $width ): Part {
		return Parts::of( array( 'deck_width' => $width ) );
	}

	private static function axle( string $width ): Part {
		return Parts::of( array( 'axle_width' => $width ) );
	}

	/**
	 * Settings with R1's figures replaced.
	 *
	 * @param array<string, string> $r1 The figures.
	 */
	private static function settings( array $r1 ): RuleSettings {
		$document       = RuleSettings::DEFAULTS;
		$document['r1'] = $r1 + $document['r1'];
		return RuleSettings::fromDocument( $document );
	}

	public function test_it_is_r1(): void {
		self::assertSame( RuleId::R1, ( new DeckWidthRule() )->id() );
	}

	public function test_the_specs_case_an_8_inch_deck_with_indy_139_fits(): void {
		self::assertSame( array(), self::check( self::deck( '8.0' ), Parts::indy( '139' ) ) );
	}

	public function test_the_specs_case_an_8_5_inch_deck_with_indy_144_is_blocked_by_the_makers_range(): void {
		$findings = self::check( self::deck( '8.5' ), Parts::indy( '144' ) );
		self::assertCount( 1, $findings );
		self::assertSame( RuleId::R1, $findings[0]->rule );
		self::assertSame( Severity::Block, $findings[0]->severity );
		self::assertSame( MessageCode::DeckAboveRange, $findings[0]->code );
		self::assertSame( Source::Maker, $findings[0]->source );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS ), $findings[0]->slots );
		self::assertEquals(
			array(
				'deck_width' => Parts::inches( '8.5' ),
				'deck_min'   => Parts::inches( '8.2' ),
				'deck_max'   => Parts::inches( '8.375' ),
			),
			$findings[0]->values
		);
	}

	public function test_sizes_are_compared_by_range_never_by_number(): void {
		// Indy 149 and Thunder 149 are both 8.5 in axles; Thunder 151 matches Indy 159.
		$thunder149 = Parts::of( array( 'axle_width' => '8.5', 'deck_min' => '8.25', 'deck_max' => '8.75' ) );
		$thunder151 = Parts::of( array( 'axle_width' => '8.75', 'deck_min' => '8.5', 'deck_max' => '9.0' ) );
		self::assertSame( array(), self::check( self::deck( '8.5' ), Parts::indy( '149' ) ) );
		self::assertSame( array(), self::check( self::deck( '8.5' ), $thunder149 ) );
		self::assertSame( array(), self::check( self::deck( '8.75' ), $thunder151 ) );
		self::assertSame( array(), self::check( self::deck( '8.75' ), Parts::indy( '159' ) ) );
		self::assertSame( MessageCode::DeckAboveRange, self::check( self::deck( '8.75' ), Parts::indy( '149' ) )[0]->code );
	}

	/**
	 * @return array<string, array{string, string, string|null}>
	 */
	public static function ranges(): array {
		return array(
			'at the bottom of the range'    => array( '8.2', '0', null ),
			'at the top of the range'       => array( '8.375', '0', null ),
			'a hair under the bottom'       => array( '8.1999', '0', 'deck_below_range' ),
			'a hair over the top'           => array( '8.3751', '0', 'deck_above_range' ),
			'under, inside the tolerance'   => array( '8.1', '0.1', null ),
			'over, inside the tolerance'    => array( '8.475', '0.1', null ),
			'under, a hair past tolerance'  => array( '8.0999', '0.1', 'deck_below_range' ),
			'over, a hair past tolerance'   => array( '8.4751', '0.1', 'deck_above_range' ),
			'the retailers range, loosened' => array( '8.5', '0.125', null ),
		);
	}

	#[DataProvider( 'ranges' )]
	public function test_the_makers_range_and_the_kits_tolerance( string $width, string $tolerance, ?string $code ): void {
		$findings = self::check( self::deck( $width ), Parts::indy( '144' ), self::settings( array( 'range_tolerance' => $tolerance ) ) );
		self::assertSame( $code, $findings[0]->code->value ?? null );
		self::assertSame( null === $code ? Severity::Fits : Severity::Block, Finding::worst( $findings ) );
	}

	/**
	 * @return array<string, array{string, string, string|null, string|null, string}>
	 */
	public static function axles(): array {
		return array(
			'the same width'                 => array( '8.5', '8.5', null, null, '0' ),
			'a quarter inch narrower fits'   => array( '8.5', '8.25', null, null, '0.25' ),
			'a quarter inch wider fits'      => array( '8.25', '8.5', null, null, '0.25' ),
			'a hair more, narrower, warns'   => array( '8.5', '8.2499', 'warn', 'axle_narrower', '0.2501' ),
			'a hair more, wider, warns'      => array( '8.25', '8.5001', 'warn', 'axle_wider', '0.2501' ),
			'half an inch narrower warns'    => array( '8.5', '8.0', 'warn', 'axle_narrower', '0.5' ),
			'half an inch wider warns'       => array( '8.0', '8.5', 'warn', 'axle_wider', '0.5' ),
			'past half an inch, narrower'    => array( '8.5', '7.9999', 'block', 'axle_narrower', '0.5001' ),
			'past half an inch, wider'       => array( '8.0', '8.5001', 'block', 'axle_wider', '0.5001' ),
		);
	}

	#[DataProvider( 'axles' )]
	public function test_a_truck_with_no_range_goes_by_its_axle( string $deck, string $axle, ?string $severity, ?string $code, string $gap ): void {
		$findings = self::check( self::deck( $deck ), self::axle( $axle ) );
		if ( null === $severity ) {
			self::assertSame( array(), $findings );
			return;
		}
		self::assertCount( 1, $findings );
		self::assertSame( $severity, $findings[0]->severity->value );
		self::assertSame( $code, $findings[0]->code->value );
		self::assertSame( Source::Kit, $findings[0]->source );
		self::assertSame( RuleId::R1, $findings[0]->rule );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS ), $findings[0]->slots );
		self::assertEquals(
			array(
				'deck_width' => Parts::inches( $deck ),
				'axle_width' => Parts::inches( $axle ),
				'gap'        => Parts::inches( $gap ),
			),
			$findings[0]->values
		);
	}

	public function test_the_axles_tolerances_are_the_kits(): void {
		$loose = self::settings( array( 'axle_fit' => '0.4', 'axle_warn' => '0.75' ) );
		self::assertSame( array(), self::check( self::deck( '8.5' ), self::axle( '8.1' ), $loose ) );
		self::assertSame( Severity::Warn, Finding::worst( self::check( self::deck( '8.5' ), self::axle( '7.75' ), $loose ) ) );
		self::assertSame( Severity::Block, Finding::worst( self::check( self::deck( '8.5' ), self::axle( '7.7499' ), $loose ) ) );
	}

	public function test_the_specs_table_venture_6_1_fits_8_62_and_up(): void {
		// One end published: that end holds, and the other is open. The axle is not asked.
		$truck = Parts::of( array( 'axle_width' => '8.75', 'deck_min' => '8.62' ) );
		self::assertSame( array(), self::check( self::deck( '8.62' ), $truck ) );
		self::assertSame( array(), self::check( self::deck( '9.5' ), $truck ), 'no upper end, though the axle is 0.75 in narrower' );

		$findings = self::check( self::deck( '8.5' ), $truck );
		self::assertCount( 1, $findings );
		self::assertSame( Severity::Block, $findings[0]->severity );
		self::assertSame( MessageCode::DeckBelowRange, $findings[0]->code );
		self::assertSame( Source::Maker, $findings[0]->source );
		self::assertEquals(
			array(
				'deck_width' => Parts::inches( '8.5' ),
				'deck_min'   => Parts::inches( '8.62' ),
			),
			$findings[0]->values
		);
	}

	public function test_the_specs_table_venture_5_0_fits_up_to_7_9(): void {
		$truck = Parts::of( array( 'axle_width' => '7.6', 'deck_max' => '7.9' ) );
		self::assertSame( array(), self::check( self::deck( '7.9' ), $truck ) );
		self::assertSame( array(), self::check( self::deck( '7.0' ), $truck ), 'no lower end, though the axle is 0.6 in wider' );

		$findings = self::check( self::deck( '8.0' ), $truck );
		self::assertCount( 1, $findings );
		self::assertSame( Severity::Block, $findings[0]->severity );
		self::assertSame( MessageCode::DeckAboveRange, $findings[0]->code );
		self::assertSame( Source::Maker, $findings[0]->source );
		self::assertEquals(
			array(
				'deck_width' => Parts::inches( '8.0' ),
				'deck_max'   => Parts::inches( '7.9' ),
			),
			$findings[0]->values
		);
	}

	public function test_the_kits_tolerance_widens_a_single_end_too(): void {
		$wider = self::settings( array( 'range_tolerance' => '0.125' ) );
		$up    = Parts::of( array( 'deck_min' => '8.62' ) );
		$down  = Parts::of( array( 'deck_max' => '7.9' ) );
		self::assertSame( array(), self::check( self::deck( '8.495' ), $up, $wider ) );
		self::assertSame( MessageCode::DeckBelowRange, self::check( self::deck( '8.4949' ), $up, $wider )[0]->code );
		self::assertSame( array(), self::check( self::deck( '8.025' ), $down, $wider ) );
		self::assertSame( MessageCode::DeckAboveRange, self::check( self::deck( '8.0251' ), $down, $wider )[0]->code );
	}

	public function test_a_truck_with_one_end_and_no_axle_needs_no_axle(): void {
		self::assertSame( array(), self::check( self::deck( '9.0' ), Parts::of( array( 'deck_min' => '8.62' ) ) ) );
		self::assertSame( array(), self::check( null, Parts::of( array( 'deck_min' => '8.62' ) ) ), 'before a deck is picked too' );
		self::assertSame( array(), self::check( null, Parts::of( array( 'deck_max' => '7.9' ) ) ) );
	}

	public function test_a_range_wins_over_the_axle(): void {
		$odd = Parts::of( array( 'axle_width' => '7.0', 'deck_min' => '8.0', 'deck_max' => '9.0' ) );
		self::assertSame( array(), self::check( self::deck( '8.5' ), $odd ) );
	}

	public function test_a_truck_with_neither_a_range_nor_an_axle_is_missing_its_axle(): void {
		$expected = array( Finding::missing( RuleId::R1, SlotKey::TRUCKS, SpecKey::AxleWidth ) );
		self::assertEquals( $expected, self::check( self::deck( '8.5' ), Parts::of() ) );
		self::assertEquals( $expected, self::check( null, Parts::of() ), 'before a deck is picked too' );
	}

	public function test_a_deck_with_no_width_is_missing_it(): void {
		$expected = array( Finding::missing( RuleId::R1, SlotKey::DECK, SpecKey::DeckWidth ) );
		self::assertEquals( $expected, self::check( Parts::of(), null ), 'on its own' );
		self::assertEquals( $expected, self::check( Parts::of(), Parts::indy( '144' ) ), 'against a range' );
		self::assertEquals( $expected, self::check( Parts::of(), self::axle( '8.5' ) ), 'against an axle' );
		self::assertEquals(
			array( $expected[0], Finding::missing( RuleId::R1, SlotKey::TRUCKS, SpecKey::AxleWidth ) ),
			self::check( Parts::of(), Parts::of() )
		);
	}

	public function test_one_part_on_its_own_with_its_specs_says_nothing(): void {
		self::assertSame( array(), self::check( null, null ) );
		self::assertSame( array(), self::check( self::deck( '8.5' ), null ) );
		self::assertSame( array(), self::check( null, Parts::indy( '144' ) ) );
		self::assertSame( array(), self::check( null, self::axle( '8.5' ) ) );
	}
}
