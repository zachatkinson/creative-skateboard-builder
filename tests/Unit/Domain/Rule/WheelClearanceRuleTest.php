<?php
/**
 * R3.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\WheelClearanceRule;
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
 * R3, wheels, truck height and risers (spec: Compatibility rules).
 */
final class WheelClearanceRuleTest extends TestCase {

	/**
	 * The rule's findings for parts by slot; a null leaves the slot empty.
	 *
	 * @param array<string, Part|null> $parts    The parts.
	 * @param RuleSettings|null        $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	private static function check( array $parts, ?RuleSettings $settings = null ): array {
		return ( new WheelClearanceRule() )->check( Parts::picks( array_filter( $parts ) ), $settings ?? RuleSettings::defaults() );
	}

	private static function standard(): Part {
		// Indy Standard, 55 mm tall, takes wheels of 56 mm and under.
		return Parts::indy( '149', array( 'truck_height' => '55', 'max_wheel' => '56' ) );
	}

	private static function mid(): Part {
		// Indy Mid, 52 mm tall, takes 53 mm and under.
		return Parts::indy( '149', array( 'truck_height' => '52', 'max_wheel' => '53' ) );
	}

	private static function wheel( string $diameter ): Part {
		return Parts::of( array( 'wheel_diameter' => $diameter ) );
	}

	private static function riser( string $thickness ): Part {
		return Parts::of( array( 'riser_thickness' => $thickness ) );
	}

	/**
	 * The warning for a wheel over a limit.
	 *
	 * @param string      $diameter The wheel.
	 * @param string      $limit    The truck's limit.
	 * @param string      $over     How far over.
	 * @param string|null $riser    The riser suggested, or null when none covers it.
	 * @param Source      $source   Where the limit came from.
	 */
	private static function warning( string $diameter, string $limit, string $over, ?string $riser, Source $source = Source::Maker ): Finding {
		$values = array(
			'wheel_diameter' => Parts::mm( $diameter ),
			'limit'          => Parts::mm( $limit ),
			'over'           => Parts::mm( $over ),
		);
		if ( null === $riser ) {
			return new Finding( RuleId::R3, Severity::Warn, MessageCode::WheelNoRiser, $source, array( SlotKey::TRUCKS, SlotKey::WHEELS ), $values );
		}
		return new Finding( RuleId::R3, Severity::Warn, MessageCode::WheelNeedsRiser, $source, array( SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS ), $values + array( 'riser' => Parts::inches( $riser ) ) );
	}

	public function test_it_is_r3(): void {
		self::assertSame( RuleId::R3, ( new WheelClearanceRule() )->id() );
	}

	public function test_the_specs_case_indy_standard_with_56_mm_wheels_gives_no_warning(): void {
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => self::wheel( '56' ) ) ) );
	}

	public function test_the_specs_case_58_mm_wheels_and_no_riser_warn_and_suggest_a_quarter_inch(): void {
		self::assertEquals(
			array( self::warning( '58', '56', '2', '0.25' ) ),
			self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => self::wheel( '58' ) ) )
		);
	}

	public function test_the_specs_case_with_a_quarter_inch_riser_58_mm_wheels_fit(): void {
		self::assertSame(
			array(),
			self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => self::wheel( '58' ), SlotKey::RISERS => self::riser( '1/4' ) ) )
		);
	}

	public function test_the_specs_case_indy_mid_with_54_mm_wheels_warns_and_suggests_a_quarter_inch(): void {
		self::assertEquals(
			array( self::warning( '54', '53', '1', '0.25' ) ),
			self::check( array( SlotKey::TRUCKS => self::mid(), SlotKey::WHEELS => self::wheel( '54' ) ) )
		);
	}

	public function test_the_specs_case_indy_standard_with_63_mm_wheels_warns_that_no_riser_covers_it(): void {
		$expected = array( self::warning( '63', '56', '7', null ) );
		self::assertEquals( $expected, self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => self::wheel( '63' ) ) ) );
		self::assertEquals(
			$expected,
			self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => self::wheel( '63' ), SlotKey::RISERS => self::riser( '1/2' ) ) ),
			'and a riser does not clear it'
		);
	}

	/**
	 * Hammer's table for a 56 mm limit: none up to 56 mm, a quarter inch for 57 to 58 mm, half an inch for 59 to 62 mm.
	 *
	 * @return array<string, array{string, string|null, string|null, bool}>
	 */
	public static function steps(): array {
		return array(
			'55 mm, under'                  => array( '55', null, null, false ),
			'56.0001 mm, a hair over'       => array( '56.0001', null, '0.25', true ),
			'57 mm'                         => array( '57', null, '0.25', true ),
			'58 mm with an eighth'          => array( '58', '1/8', '0.25', true ),
			'58 mm with a hair under'       => array( '58', '0.2499', '0.25', true ),
			'58 mm with a quarter'          => array( '58', '1/4', null, false ),
			'58 mm with a half'             => array( '58', '1/2', null, false ),
			'58.0001 mm, onto the next row' => array( '58.0001', '1/4', '0.5', true ),
			'59 mm with a quarter'          => array( '59', '1/4', '0.5', true ),
			'62 mm with a half'             => array( '62', '1/2', null, false ),
			'62 mm with none'               => array( '62', null, '0.5', true ),
			'62.0001 mm, past the table'    => array( '62.0001', '1/2', null, true ),
		);
	}

	#[DataProvider( 'steps' )]
	public function test_the_riser_table_measured_from_the_trucks_limit( string $diameter, ?string $riser, ?string $suggested, bool $warns ): void {
		$findings = self::check(
			array(
				SlotKey::TRUCKS => self::standard(),
				SlotKey::WHEELS => self::wheel( $diameter ),
				SlotKey::RISERS => null === $riser ? null : self::riser( $riser ),
			)
		);
		if ( ! $warns ) {
			self::assertSame( array(), $findings );
			return;
		}
		self::assertCount( 1, $findings );
		self::assertSame( Severity::Warn, $findings[0]->severity );
		self::assertSame( null === $suggested ? MessageCode::WheelNoRiser : MessageCode::WheelNeedsRiser, $findings[0]->code );
		self::assertEquals( null === $suggested ? null : Parts::inches( $suggested ), $findings[0]->values['riser'] ?? null );
	}

	public function test_the_table_is_the_kits(): void {
		$document                 = RuleSettings::DEFAULTS;
		$document['r3']['risers'] = array(
			array(
				'over'  => '3',
				'riser' => '0.125',
			),
		);
		$settings                 = RuleSettings::fromDocument( $document );
		$parts                    = array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => self::wheel( '59' ) );
		self::assertEquals( array( self::warning( '59', '56', '3', '0.125' ) ), self::check( $parts, $settings ) );
		self::assertSame( array(), self::check( $parts + array( SlotKey::RISERS => self::riser( '1/8' ) ), $settings ) );
		self::assertSame( MessageCode::WheelNoRiser, self::check( array( SlotKey::WHEELS => self::wheel( '59.5' ) ) + $parts, $settings )[0]->code );
	}

	/**
	 * @return array<string, array{string|null, string, string}>
	 */
	public static function classes(): array {
		return array(
			'low, seeded at 53 mm'       => array( 'low', '53', 'low' ),
			'mid, seeded at 56 mm'       => array( 'mid', '56', 'mid' ),
			'high, seeded at 56 mm'      => array( 'high', '56', 'high' ),
			'not labelled counts as high' => array( null, '56', 'high' ),
		);
	}

	#[DataProvider( 'classes' )]
	public function test_a_truck_with_no_makers_figure_goes_by_its_height_class_and_is_flagged( ?string $class, string $limit, string $counted ): void {
		$truck = Parts::of( null === $class ? array() : array( 'height_class' => $class ) );
		$note  = new Finding(
			RuleId::R3,
			Severity::Fits,
			MessageCode::LimitFromClass,
			Source::Kit,
			array( SlotKey::TRUCKS ),
			array(
				'height_class' => $counted,
				'limit'        => Parts::mm( $limit ),
			)
		);
		self::assertEquals( array( $note ), self::check( array( SlotKey::TRUCKS => $truck ) ), 'on its own' );
		self::assertEquals( array( $note ), self::check( array( SlotKey::TRUCKS => $truck, SlotKey::WHEELS => self::wheel( $limit ) ) ), 'at the limit' );
		$over = self::check( array( SlotKey::TRUCKS => $truck, SlotKey::WHEELS => self::wheel( (string) ( 1 + (int) $limit ) ) ) );
		self::assertEquals( array( $note, self::warning( (string) ( 1 + (int) $limit ), $limit, '1', '0.25', Source::Kit ) ), $over );
	}

	public function test_the_class_limits_are_the_kits(): void {
		$document                        = RuleSettings::DEFAULTS;
		$document['r3']['limits']['low'] = '54';
		$settings                        = RuleSettings::fromDocument( $document );
		$parts                           = array( SlotKey::TRUCKS => Parts::of( array( 'height_class' => 'low' ) ), SlotKey::WHEELS => self::wheel( '54' ) );
		self::assertSame( Severity::Fits, Finding::worst( self::check( $parts, $settings ) ) );
		self::assertSame( Severity::Warn, Finding::worst( self::check( $parts ) ) );
	}

	public function test_the_makers_figure_wins_over_the_class(): void {
		$truck = Parts::of( array( 'height_class' => 'low', 'max_wheel' => '58' ) );
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => $truck, SlotKey::WHEELS => self::wheel( '58' ) ) ) );
	}

	public function test_a_wheel_with_no_diameter_and_a_riser_with_no_thickness_are_missing_them(): void {
		$wheel = Finding::missing( RuleId::R3, SlotKey::WHEELS, SpecKey::WheelDiameter );
		$riser = Finding::missing( RuleId::R3, SlotKey::RISERS, SpecKey::RiserThickness );
		self::assertEquals( array( $wheel ), self::check( array( SlotKey::WHEELS => Parts::of() ) ) );
		self::assertEquals( array( $riser ), self::check( array( SlotKey::RISERS => Parts::of() ) ) );
		self::assertEquals( array( $wheel ), self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => Parts::of() ) ) );
		self::assertEquals(
			array( $riser, self::warning( '58', '56', '2', '0.25' ) ),
			self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::WHEELS => self::wheel( '58' ), SlotKey::RISERS => Parts::of() ) ),
			'a riser of unknown thickness clears nothing'
		);
		self::assertEquals( array( $wheel, $riser ), self::check( array( SlotKey::WHEELS => Parts::of(), SlotKey::RISERS => Parts::of() ) ) );
	}

	public function test_a_flagged_truck_keeps_its_flag_beside_whatever_else_is_found(): void {
		$truck    = Parts::of( array( 'height_class' => 'low' ) );
		$unknown  = self::check( array( SlotKey::TRUCKS => $truck, SlotKey::WHEELS => Parts::of() ) );
		$tooLarge = self::check( array( SlotKey::TRUCKS => $truck, SlotKey::WHEELS => self::wheel( '60' ) ) );
		self::assertSame( array( MessageCode::SpecMissing, MessageCode::LimitFromClass ), array_column( $unknown, 'code' ) );
		self::assertSame( array( MessageCode::LimitFromClass, MessageCode::WheelNoRiser ), array_column( $tooLarge, 'code' ) );
		self::assertEquals( self::warning( '60', '53', '7', null, Source::Kit ), $tooLarge[1] );
	}

	public function test_parts_on_their_own_with_their_specs_say_nothing(): void {
		self::assertSame( array(), self::check( array() ) );
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => self::standard() ) ) );
		self::assertSame( array(), self::check( array( SlotKey::WHEELS => self::wheel( '70' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::WHEELS => self::wheel( '70' ), SlotKey::RISERS => self::riser( '1/2' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => self::standard(), SlotKey::RISERS => self::riser( '1/2' ) ) ) );
	}
}
