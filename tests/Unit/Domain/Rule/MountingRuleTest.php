<?php
/**
 * R2.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Rule;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MountingRule;
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
 * R2, mounting holes (spec: Compatibility rules).
 */
final class MountingRuleTest extends TestCase {

	/**
	 * The rule's findings for parts by slot; a null leaves the slot empty.
	 *
	 * @param array<string, Part|null> $parts    The parts.
	 * @param RuleSettings|null        $settings The kit's rule settings.
	 * @return list<Finding>
	 */
	private static function check( array $parts, ?RuleSettings $settings = null ): array {
		return ( new MountingRule() )->check( Parts::picks( array_filter( $parts ) ), $settings ?? RuleSettings::defaults() );
	}

	private static function deck( ?string $pattern ): Part {
		return Parts::of( null === $pattern ? array() : array( 'hole_pattern' => $pattern ) );
	}

	private static function truck( ?string $baseplate ): Part {
		return Parts::of( null === $baseplate ? array() : array( 'baseplate' => $baseplate ) );
	}

	public function test_it_is_r2(): void {
		self::assertSame( RuleId::R2, ( new MountingRule() )->id() );
	}

	public function test_the_specs_case_an_old_school_deck_with_indy_144_is_blocked(): void {
		$findings = self::check(
			array(
				SlotKey::DECK   => self::deck( 'old_school' ),
				SlotKey::TRUCKS => Parts::indy( '144', array( 'baseplate' => 'new_school' ) ),
			)
		);
		self::assertEquals(
			array(
				new Finding(
					RuleId::R2,
					Severity::Block,
					MessageCode::PatternMismatch,
					Source::Maker,
					array( SlotKey::DECK, SlotKey::TRUCKS ),
					array(
						'hole_pattern' => 'old_school',
						'baseplate'    => 'new_school',
					)
				),
			),
			$findings
		);
	}

	public function test_the_specs_case_an_old_school_deck_with_the_six_hole_indy_159_fits(): void {
		self::assertSame(
			array(),
			self::check(
				array(
					SlotKey::DECK   => self::deck( 'old_school' ),
					SlotKey::TRUCKS => Parts::indy( '159', array( 'baseplate' => 'both' ) ),
				)
			)
		);
	}

	/**
	 * @return array<string, array{string, string, bool}>
	 */
	public static function pairs(): array {
		return array(
			'new on new'   => array( 'new_school', 'new_school', true ),
			'old on old'   => array( 'old_school', 'old_school', true ),
			'new on old'   => array( 'new_school', 'old_school', false ),
			'old on new'   => array( 'old_school', 'new_school', false ),
			'new on both'  => array( 'new_school', 'both', true ),
			'old on both'  => array( 'old_school', 'both', true ),
			'both on new'  => array( 'both', 'new_school', true ),
			'both on old'  => array( 'both', 'old_school', true ),
			'both on both' => array( 'both', 'both', true ),
		);
	}

	#[DataProvider( 'pairs' )]
	public function test_the_baseplate_takes_the_decks_pattern_or_it_is_blocked( string $pattern, string $baseplate, bool $fits ): void {
		$findings = self::check(
			array(
				SlotKey::DECK   => self::deck( $pattern ),
				SlotKey::TRUCKS => self::truck( $baseplate ),
			)
		);
		self::assertSame( $fits ? Severity::Fits : Severity::Block, Finding::worst( $findings ) );
		self::assertCount( $fits ? 0 : 1, $findings );
	}

	public function test_a_deck_with_no_pattern_counts_as_new_school_and_is_flagged(): void {
		$note = new Finding( RuleId::R2, Severity::Fits, MessageCode::PatternAssumed, Source::Standard, array( SlotKey::DECK ), array( 'hole_pattern' => 'new_school' ) );
		self::assertEquals( array( $note ), self::check( array( SlotKey::DECK => self::deck( null ) ) ), 'on its own' );
		self::assertEquals(
			array( $note ),
			self::check(
				array(
					SlotKey::DECK   => self::deck( null ),
					SlotKey::TRUCKS => self::truck( 'new_school' ),
				)
			)
		);
		self::assertEquals(
			array( $note ),
			self::check(
				array(
					SlotKey::DECK   => self::deck( null ),
					SlotKey::TRUCKS => self::truck( 'both' ),
				)
			)
		);
		$blocked = self::check(
			array(
				SlotKey::DECK   => self::deck( null ),
				SlotKey::TRUCKS => self::truck( 'old_school' ),
			)
		);
		self::assertCount( 2, $blocked );
		self::assertEquals( $note, $blocked[0] );
		self::assertSame( MessageCode::PatternMismatch, $blocked[1]->code );
		self::assertSame(
			array(
				'hole_pattern' => 'new_school',
				'baseplate'    => 'old_school',
			),
			$blocked[1]->values
		);
	}

	public function test_a_truck_with_no_baseplate_is_missing_it(): void {
		$missing = Finding::missing( RuleId::R2, SlotKey::TRUCKS, SpecKey::Baseplate );
		self::assertEquals( array( $missing ), self::check( array( SlotKey::TRUCKS => self::truck( null ) ) ), 'on its own' );
		self::assertEquals(
			array( $missing ),
			self::check(
				array(
					SlotKey::DECK   => self::deck( 'both' ),
					SlotKey::TRUCKS => self::truck( null ),
				)
			)
		);
		$both = self::check(
			array(
				SlotKey::DECK   => self::deck( null ),
				SlotKey::TRUCKS => self::truck( null ),
			)
		);
		self::assertCount( 2, $both );
		self::assertEquals( $missing, $both[0] );
		self::assertSame( MessageCode::PatternAssumed, $both[1]->code );
	}

	public function test_one_part_on_its_own_with_its_specs_says_nothing(): void {
		self::assertSame( array(), self::check( array() ) );
		self::assertSame( array(), self::check( array( SlotKey::DECK => self::deck( 'old_school' ) ) ) );
		self::assertSame( array(), self::check( array( SlotKey::TRUCKS => self::truck( 'new_school' ) ) ) );
	}
}
