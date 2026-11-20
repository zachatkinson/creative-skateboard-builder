<?php
/**
 * R5.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\BearingRule;
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
 * R5, bearings (spec: Compatibility rules).
 */
final class BearingRuleTest extends TestCase {

	/**
	 * The rule's findings for parts by slot; a null leaves the slot empty.
	 *
	 * @param array<string, Part|null> $parts    The parts.
	 * @param RuleSettings|null        $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	private static function check( array $parts, ?RuleSettings $settings = null ): array {
		return ( new BearingRule() )->check( Parts::picks( array_filter( $parts ) ), $settings ?? RuleSettings::defaults() );
	}

	private static function bearings( ?string $bore, bool $spacers = false ): Part {
		return Parts::of( ( null === $bore ? array() : array( 'bearing_bore' => $bore ) ) + ( $spacers ? array( 'spacers_built_in' => true ) : array() ) );
	}

	private static function truck( ?string $axle ): Part {
		return Parts::of( null === $axle ? array() : array( 'axle_diameter' => $axle ) );
	}

	private static function assumed(): Finding {
		return new Finding( RuleId::R5, Severity::Fits, MessageCode::AxleAssumed, Source::Standard, array( SlotKey::TRUCKS ), array( 'axle_diameter' => Parts::mm( '8' ) ) );
	}

	private static function mismatch( string $bore, string $axle, Source $source ): Finding {
		return new Finding(
			RuleId::R5,
			Severity::Block,
			MessageCode::BoreMismatch,
			$source,
			array( SlotKey::TRUCKS, SlotKey::BEARINGS ),
			array(
				'bearing_bore'  => Parts::mm( $bore ),
				'axle_diameter' => Parts::mm( $axle ),
			)
		);
	}

	public function test_it_is_r5(): void {
		self::assertSame( RuleId::R5, ( new BearingRule() )->id() );
	}

	public function test_a_608_bearing_fits_an_8_mm_axle(): void {
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => self::truck( '8' ), SlotKey::BEARINGS => self::bearings( '8' ) ) ) );
	}

	public function test_the_specs_case_a_7_mm_bearing_is_blocked_on_an_8_mm_axle(): void {
		self::assertEquals(
			array( self::mismatch( '7', '8', Source::Maker ) ),
			self::check( array( SlotKey::TRUCKS => self::truck( '8' ), SlotKey::BEARINGS => self::bearings( '7' ) ) )
		);
	}

	public function test_the_bore_and_the_axle_are_equal_or_it_is_blocked(): void {
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => self::truck( '7' ), SlotKey::BEARINGS => self::bearings( '7' ) ) ) );
		self::assertEquals( array( self::mismatch( '8', '7', Source::Maker ) ), self::check( array( SlotKey::TRUCKS => self::truck( '7' ), SlotKey::BEARINGS => self::bearings( '8' ) ) ) );
		self::assertEquals( array( self::mismatch( '8.0001', '8', Source::Maker ) ), self::check( array( SlotKey::TRUCKS => self::truck( '8' ), SlotKey::BEARINGS => self::bearings( '8.0001' ) ) ) );
		self::assertEquals( array( self::mismatch( '7.9999', '8', Source::Maker ) ), self::check( array( SlotKey::TRUCKS => self::truck( '8' ), SlotKey::BEARINGS => self::bearings( '7.9999' ) ) ) );
	}

	public function test_an_axle_with_no_diameter_counts_as_8_mm_and_is_flagged(): void {
		self::assertEquals( array( self::assumed() ), self::check( array( SlotKey::TRUCKS => self::truck( null ) ) ), 'on its own' );
		self::assertEquals( array( self::assumed() ), self::check( array( SlotKey::TRUCKS => self::truck( null ), SlotKey::BEARINGS => self::bearings( '8' ) ) ) );
		self::assertEquals(
			array( self::assumed(), self::mismatch( '7', '8', Source::Standard ) ),
			self::check( array( SlotKey::TRUCKS => self::truck( null ), SlotKey::BEARINGS => self::bearings( '7' ) ) )
		);
	}

	public function test_bearings_with_no_bore_are_missing_it(): void {
		$missing = Finding::missing( RuleId::R5, SlotKey::BEARINGS, SpecKey::BearingBore );
		self::assertEquals( array( $missing ), self::check( array( SlotKey::BEARINGS => self::bearings( null ) ) ) );
		self::assertEquals( array( $missing ), self::check( array( SlotKey::TRUCKS => self::truck( '8' ), SlotKey::BEARINGS => self::bearings( null ) ) ) );
		self::assertEquals( array( $missing, self::assumed() ), self::check( array( SlotKey::TRUCKS => self::truck( null ), SlotKey::BEARINGS => self::bearings( null ) ) ) );
	}

	public function test_bearings_with_spacers_built_in_block_a_spacers_slot(): void {
		$blocked = new Finding( RuleId::R5, Severity::Block, MessageCode::SpacersBuiltIn, Source::Maker, array( SlotKey::BEARINGS, SlotKey::SPACERS ), array() );
		self::assertEquals( array( $blocked ), self::check( array( SlotKey::BEARINGS => self::bearings( '8', true ), SlotKey::SPACERS => Parts::of() ) ) );
		self::assertEquals(
			array( self::mismatch( '7', '8', Source::Maker ), $blocked ),
			self::check( array( SlotKey::TRUCKS => self::truck( '8' ), SlotKey::BEARINGS => self::bearings( '7', true ), SlotKey::SPACERS => Parts::of() ) )
		);
		self::assertSame( array(), self::check( array( SlotKey::BEARINGS => self::bearings( '8', true ) ) ), 'no spacers picked' );
		self::assertSame( array(), self::check( array( SlotKey::BEARINGS => self::bearings( '8' ), SlotKey::SPACERS => Parts::of() ) ), 'plain bearings take spacers' );
		self::assertSame( array(), self::check( array( SlotKey::SPACERS => Parts::of() ) ), 'spacers on their own' );
	}

	public function test_wheels_that_come_with_their_bearings_block_a_bearing(): void {
		$included = Parts::of( array( 'wheel_diameter' => '54', 'bearings_included' => true ) );
		$blocked  = new Finding( RuleId::R5, Severity::Block, MessageCode::BearingsIncluded, Source::Maker, array( SlotKey::BEARINGS ), array() );
		self::assertEquals( array( $blocked ), self::check( array( SlotKey::WHEELS => $included, SlotKey::BEARINGS => self::bearings( '8' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::WHEELS => $included ) ), 'no bearing picked' );
		self::assertSame( array(), self::check( array( SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54' ) ), SlotKey::BEARINGS => self::bearings( '8' ) ) ), 'plain wheels take bearings' );
		self::assertContains( SlotKey::WHEELS, ( new BearingRule() )->slots(), 'R5 reads the wheels' );
	}

	public function test_parts_on_their_own_with_their_specs_say_nothing(): void {
		self::assertSame( array(), self::check( array() ) );
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => self::truck( '8' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::BEARINGS => self::bearings( '7' ) ) ) );
	}
}
