<?php
/**
 * R4.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\BoltLengthRule;
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
 * R4, bolts and risers (spec: Compatibility rules).
 */
final class BoltLengthRuleTest extends TestCase {

	/**
	 * The rule's findings for parts by slot; a null leaves the slot empty.
	 *
	 * @param array<string, Part|null> $parts    The parts.
	 * @param RuleSettings|null        $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	private static function check( array $parts, ?RuleSettings $settings = null ): array {
		return ( new BoltLengthRule() )->check( Parts::picks( array_filter( $parts ) ), $settings ?? RuleSettings::defaults() );
	}

	private static function bolts( string $length ): Part {
		return Parts::of( array( 'bolt_length' => $length ) );
	}

	private static function riser( string $thickness ): Part {
		return Parts::of( array( 'riser_thickness' => $thickness ) );
	}

	public function test_it_is_r4(): void {
		self::assertSame( RuleId::R4, ( new BoltLengthRule() )->id() );
	}

	public function test_the_specs_case_a_quarter_inch_riser_with_1_inch_bolts_is_blocked(): void {
		self::assertEquals(
			array(
				new Finding(
					RuleId::R4,
					Severity::Block,
					MessageCode::BoltsTooShort,
					Source::Standard,
					array( SlotKey::RISERS, SlotKey::HARDWARE ),
					array(
						'bolt_length'     => Parts::inches( '1' ),
						'shortest'        => Parts::inches( '1 1/8' ),
						'usual'           => Parts::inches( '1 1/4' ),
						'riser_thickness' => Parts::inches( '1/4' ),
					)
				),
			),
			self::check( array( SlotKey::RISERS => self::riser( '1/4' ), SlotKey::HARDWARE => self::bolts( '1' ) ) )
		);
	}

	public function test_the_specs_case_a_quarter_inch_riser_with_1_and_a_quarter_inch_bolts_fits(): void {
		self::assertSame( array(), self::check( array( SlotKey::RISERS => self::riser( '1/4' ), SlotKey::HARDWARE => self::bolts( '1 1/4' ) ) ) );
	}

	public function test_the_specs_case_no_riser_with_1_and_a_half_inch_bolts_warns(): void {
		self::assertEquals(
			array(
				new Finding(
					RuleId::R4,
					Severity::Warn,
					MessageCode::BoltsTooLong,
					Source::Standard,
					array( SlotKey::HARDWARE ),
					array(
						'bolt_length'     => Parts::inches( '1 1/2' ),
						'shortest'        => Parts::inches( '7/8' ),
						'usual'           => Parts::inches( '1' ),
						'riser_thickness' => Parts::inches( '0' ),
					)
				),
			),
			self::check( array( SlotKey::HARDWARE => self::bolts( '1 1/2' ) ) )
		);
	}

	/**
	 * The published table (no riser, 7/8 to 1 in; 1/8 in, 1 to 1 1/8 in; 1/4 in, 1 1/4 in; 1/2 in, 1 1/2 in) and
	 * both ends of what the rule allows around it.
	 *
	 * @return array<string, array{string|null, string, string}>
	 */
	public static function lengths(): array {
		return array(
			'no riser, 7/8'                    => array( null, '7/8', 'fits' ),
			'no riser, 1'                      => array( null, '1', 'fits' ),
			'no riser, a hair under 7/8'       => array( null, '0.8749', 'block' ),
			'no riser, 1 1/4, the most'        => array( null, '1 1/4', 'fits' ),
			'no riser, a hair over 1 1/4'      => array( null, '1.2501', 'warn' ),
			'an eighth, 1'                     => array( '1/8', '1', 'fits' ),
			'an eighth, 1 1/8'                 => array( '1/8', '1 1/8', 'fits' ),
			'an eighth, 7/8'                   => array( '1/8', '7/8', 'block' ),
			'an eighth, a hair under 1'        => array( '1/8', '0.9999', 'block' ),
			'a quarter, 1 1/8, the least'      => array( '1/4', '1 1/8', 'fits' ),
			'a quarter, a hair under'          => array( '1/4', '1.1249', 'block' ),
			'a quarter, 1 1/2, the most'       => array( '1/4', '1 1/2', 'fits' ),
			'a quarter, a hair over 1 1/2'     => array( '1/4', '1.5001', 'warn' ),
			'a half, 1 1/2'                    => array( '1/2', '1 1/2', 'fits' ),
			'a half, 1 1/4'                    => array( '1/2', '1 1/4', 'block' ),
			'a half, 2'                        => array( '1/2', '2', 'warn' ),
		);
	}

	#[DataProvider( 'lengths' )]
	public function test_the_shortest_bolt_is_seven_eighths_plus_the_riser_and_the_longest_a_quarter_over_the_usual( ?string $riser, string $bolt, string $severity ): void {
		$findings = self::check(
			array(
				SlotKey::RISERS   => null === $riser ? null : self::riser( $riser ),
				SlotKey::HARDWARE => self::bolts( $bolt ),
			)
		);
		self::assertSame( $severity, Finding::worst( $findings )->value );
		self::assertCount( 'fits' === $severity ? 0 : 1, $findings );
		if ( 'fits' !== $severity ) {
			self::assertSame( 'block' === $severity ? MessageCode::BoltsTooShort : MessageCode::BoltsTooLong, $findings[0]->code );
			self::assertSame( null === $riser ? array( SlotKey::HARDWARE ) : array( SlotKey::RISERS, SlotKey::HARDWARE ), $findings[0]->slots );
		}
	}

	public function test_bolts_with_no_length_and_a_riser_with_no_thickness_are_missing_them(): void {
		$bolts = Finding::missing( RuleId::R4, SlotKey::HARDWARE, SpecKey::BoltLength );
		$riser = Finding::missing( RuleId::R4, SlotKey::RISERS, SpecKey::RiserThickness );
		self::assertEquals( array( $bolts ), self::check( array( SlotKey::HARDWARE => Parts::of() ) ) );
		self::assertEquals( array( $riser ), self::check( array( SlotKey::RISERS => Parts::of() ) ) );
		self::assertEquals( array( $riser ), self::check( array( SlotKey::RISERS => Parts::of(), SlotKey::HARDWARE => self::bolts( '3/4' ) ) ), 'and nothing is compared' );
		self::assertEquals( array( $bolts ), self::check( array( SlotKey::RISERS => self::riser( '1/4' ), SlotKey::HARDWARE => Parts::of() ) ) );
		self::assertEquals( array( $bolts, $riser ), self::check( array( SlotKey::RISERS => Parts::of(), SlotKey::HARDWARE => Parts::of() ) ) );
	}

	public function test_a_riser_on_its_own_says_nothing(): void {
		self::assertSame( array(), self::check( array() ) );
		self::assertSame( array(), self::check( array( SlotKey::RISERS => self::riser( '1/2' ) ) ) );
	}
}
