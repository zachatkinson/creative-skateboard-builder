<?php
/**
 * RiserClearance.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\RiserClearance;
use CreativeBranding\SkateboardBuilder\Domain\Fit\SlotOptions;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Rule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleSet;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Source;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * Wheels over the trucks' limit are offered only when the kit can supply a riser that clears them with bolts R4
 * allows for it, and a riser only when the kit has such bolts (SPEC-CONFIGURATOR, Only what fits; Risers; D-245).
 */
final class RiserClearanceTest extends TestCase {

	/**
	 * Deck and trucks that take wheels up to 56 mm.
	 */
	private static function build(): Picks {
		return Parts::picks(
			array(
				SlotKey::DECK   => Builds::deck( '8.5' ),
				SlotKey::TRUCKS => Builds::truck( '149' ),
			)
		);
	}

	/**
	 * A part with a stock count.
	 *
	 * @param Part $part  The part.
	 * @param int  $stock How many the shop holds.
	 */
	private static function stocked( Part $part, int $stock ): Part {
		return new Part( $part->id, $part->brand, $part->specs, $part->price, $stock > 0, $stock );
	}

	/**
	 * The test kit without some of its slots.
	 *
	 * @param list<string> $without The slots to leave out.
	 */
	private static function kit( array $without = array() ): Kit {
		$slots          = KitDocuments::slots();
		$slots['slots'] = array_values( array_filter( $slots['slots'], static fn( array $slot ): bool => ! in_array( $slot['key'], $without, true ) ) );
		return KitDocuments::kit( $slots );
	}

	/**
	 * 54 mm wheels, which clear; 57 mm, which ask for ¼ in risers; 60 mm, which ask for ½ in.
	 *
	 * @param Kit $kit The kit.
	 */
	private static function wheels( Kit $kit ): SlotOptions {
		return FitEngine::standard()->options( $kit, SlotKey::WHEELS, self::build(), array( Builds::wheel( '54', 354 ), Builds::wheel( '57', 357 ), Builds::wheel( '60', 360 ) ) );
	}

	/**
	 * The wheels offered, and the hidden ones with their reason's code, after clearance.
	 *
	 * @param list<Part> $risers The kit's risers.
	 * @param list<Part> $bolts  The kit's bolts.
	 * @param Kit|null   $kit    The kit, the test kit by default.
	 * @return array{offered: list<int>, hidden: array<int, string>}
	 */
	private static function cleared( array $risers, array $bolts, ?Kit $kit = null ): array {
		$kit     = $kit ?? KitDocuments::kit();
		$options = ( new RiserClearance( FitEngine::standard() ) )->wheels( $kit, self::build(), self::wheels( $kit ), $risers, $bolts );
		$hidden  = array();
		foreach ( $options->hidden as $option ) {
			$hidden[ $option->part->id ] = $option->reason()?->code->value ?? '';
		}
		return array(
			'offered' => array_map( static fn( Option $option ): int => $option->part->id, $options->offered ),
			'hidden'  => $hidden,
		);
	}

	// D-289: the wheels are worked out once for each thing R3 says of them, which holds while R3 is the only rule that
	// reads the wheels with the risers. A rule that also did would make two wheels R3 says the same of differ.
	public function test_r3_is_the_only_standard_rule_that_reads_the_wheels_with_the_risers(): void {
		$rules = ( new \ReflectionProperty( RuleSet::class, 'rules' ) )->getValue( RuleSet::standard() );
		self::assertIsArray( $rules );
		$both = array();
		foreach ( $rules as $rule ) {
			self::assertInstanceOf( Rule::class, $rule );
			if ( in_array( SlotKey::WHEELS, $rule->slots(), true ) && in_array( SlotKey::RISERS, $rule->slots(), true ) ) {
				$both[] = $rule->id();
			}
		}
		self::assertSame( array( RuleId::R3 ), $both );
	}

	// D-289: the bolts are worked out once for each riser thickness, which holds while R4 is the only rule that reads
	// the risers with the bolts, and reads their thickness alone.
	public function test_r4_is_the_only_standard_rule_that_reads_the_risers_with_the_bolts(): void {
		$rules = ( new \ReflectionProperty( RuleSet::class, 'rules' ) )->getValue( RuleSet::standard() );
		self::assertIsArray( $rules );
		$both = array();
		foreach ( $rules as $rule ) {
			self::assertInstanceOf( Rule::class, $rule );
			if ( in_array( SlotKey::RISERS, $rule->slots(), true ) && in_array( SlotKey::HARDWARE, $rule->slots(), true ) ) {
				$both[] = $rule->id();
			}
		}
		self::assertSame( array( RuleId::R4 ), $both );
	}

	public function test_wheels_r3_says_the_same_of_get_the_same_answer_whatever_else_they_differ_in(): void {
		$kit    = KitDocuments::kit();
		$soft   = Parts::of( array( 'wheel_diameter' => '60', 'set_size' => 4 ), 361 );
		$hard   = Parts::of( array( 'wheel_diameter' => '60', 'bearings_included' => true, 'set_size' => 4 ), 362 );
		$wheels = FitEngine::standard()->options( $kit, SlotKey::WHEELS, self::build(), array( $soft, $hard ) );
		$codes  = static fn( SlotOptions $options ): array => array_map( static fn( Option $option ): string => $option->reason()?->code->value ?? '', $options->hidden );
		$clear  = new RiserClearance( FitEngine::standard() );

		$short = $clear->wheels( $kit, self::build(), $wheels, array( Builds::riser( '0.5' ) ), array( Builds::bolts( '1.25' ) ) );
		self::assertSame( array( MessageCode::RiserNoBolts->value, MessageCode::RiserNoBolts->value ), $codes( $short ), 'both, for the one reason' );
		$long = $clear->wheels( $kit, self::build(), $wheels, array( Builds::riser( '0.5' ) ), array( Builds::bolts( '1.5' ) ) );
		self::assertSame( array( 361, 362 ), array_map( static fn( Option $option ): int => $option->part->id, $long->offered ) );
	}

	public function test_wheels_stay_when_a_riser_in_stock_clears_them_with_bolts_long_enough(): void {
		$cleared = self::cleared( array( Builds::riser( '0.25', 401 ), Builds::riser( '0.5', 402 ) ), array( Builds::bolts( '1.5', 601 ) ) );
		self::assertSame( array( 354, 357, 360 ), $cleared['offered'] );
		self::assertSame( array(), $cleared['hidden'] );
	}

	public function test_wheels_go_when_no_riser_that_clears_them_is_in_stock(): void {
		$cleared = self::cleared( array( Builds::riser( '0.25', 401 ), self::stocked( Builds::riser( '0.5', 402 ), 0 ) ), array( Builds::bolts( '1.5', 601 ) ) );
		self::assertSame( array( 354, 357 ), $cleared['offered'] );
		self::assertSame( array( 360 => 'riser_not_in_stock' ), $cleared['hidden'] );
	}

	public function test_a_thicker_riser_clears_wheels_that_ask_for_a_thinner_one(): void {
		$cleared = self::cleared( array( Builds::riser( '0.5', 402 ) ), array( Builds::bolts( '1.5', 601 ) ) );
		self::assertSame( array( 354, 357, 360 ), $cleared['offered'] );
	}

	public function test_wheels_go_when_no_bolts_in_stock_reach_through_the_riser_that_clears_them(): void {
		$risers = array( Builds::riser( '0.25', 401 ), Builds::riser( '0.5', 402 ) );
		self::assertSame( array( 357 => 'riser_no_bolts', 360 => 'riser_no_bolts' ), self::cleared( $risers, array( Builds::bolts( '1', 601 ) ) )['hidden'], '1 in is shorter than 1⅛ in, ⅞ in plus ¼ in' );
		self::assertSame( array( 360 => 'riser_no_bolts' ), self::cleared( $risers, array( Builds::bolts( '1.25', 601 ) ) )['hidden'], '1¼ in reaches through ¼ in, and not ½ in' );
		self::assertSame( array( 357 => 'riser_no_bolts', 360 => 'riser_no_bolts' ), self::cleared( $risers, array( self::stocked( Builds::bolts( '1.5', 601 ), 0 ) ) )['hidden'], 'long enough, and sold out' );
	}

	public function test_bolts_for_any_riser_that_clears_them_are_enough(): void {
		$cleared = self::cleared( array( Builds::riser( '0.75', 403 ), Builds::riser( '0.5', 402 ) ), array( Builds::bolts( '1.5', 601 ) ) );
		self::assertSame( array( 354, 357, 360 ), $cleared['offered'], 'nothing reaches through ¾ in, and 1½ in reaches through ½ in' );
	}

	public function test_wheels_go_when_the_kit_has_no_risers_slot(): void {
		$kit = self::kit( array( SlotKey::RISERS ) );
		self::assertSame( array( 357 => 'riser_no_slot', 360 => 'riser_no_slot' ), self::cleared( array( Builds::riser( '0.5', 402 ) ), array( Builds::bolts( '1.5', 601 ) ), $kit )['hidden'] );
	}

	public function test_a_kit_with_no_hardware_slot_needs_no_bolts(): void {
		$kit = self::kit( array( SlotKey::HARDWARE ) );
		self::assertSame( array( 354, 357, 360 ), self::cleared( array( Builds::riser( '0.5', 402 ) ), array(), $kit )['offered'] );
	}

	public function test_a_hidden_wheel_says_which_riser_it_needs_and_why(): void {
		$options = ( new RiserClearance( FitEngine::standard() ) )->wheels( KitDocuments::kit(), self::build(), self::wheels( KitDocuments::kit() ), array(), array() );
		$reason  = $options->hidden[1]->reason();
		self::assertSame( 360, $options->hidden[1]->part->id );
		self::assertSame( RuleId::R3, $reason?->rule );
		self::assertSame( Severity::Block, $reason->severity );
		self::assertSame( array( SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS ), $reason->slots );
		self::assertSame( array( 'wheel_diameter', 'limit', 'over', 'riser' ), array_keys( $reason->values ) );
		self::assertSame( MessageCode::RiserNotInStock->values(), array_keys( $reason->values ) );
		$riser = $reason->values['riser'];
		self::assertInstanceOf( Measure::class, $riser );
		self::assertTrue( $riser->equals( Measure::inches( 5000 ) ) );
		self::assertSame( array( MessageCode::WheelNeedsRiser, MessageCode::RiserNotInStock ), array_column( $options->hidden[1]->findings, 'code' ), 'R3\'s warning is kept before it' );
		$warned = $options->hidden[1]->findings[0];
		$note   = new Finding( RuleId::R3, Severity::Fits, MessageCode::LimitFromClass, Source::Kit, array( SlotKey::WHEELS ), array() );
		$given  = new SlotOptions( SlotKey::WHEELS, array( new Option( Builds::wheel( '60', 360 ), array( $note, $warned ), 1 ) ), array(), null );
		$hidden = ( new RiserClearance( FitEngine::standard() ) )->wheels( KitDocuments::kit(), self::build(), $given, array(), array() )->hidden;
		self::assertSame( array( MessageCode::LimitFromClass, MessageCode::WheelNeedsRiser, MessageCode::RiserNotInStock ), array_column( $hidden[0]->findings, 'code' ), 'every finding is kept' );
	}

	public function test_wheels_further_over_than_any_riser_covers_are_left_as_they_are(): void {
		$kit     = KitDocuments::kit();
		$options = FitEngine::standard()->options( $kit, SlotKey::WHEELS, self::build(), array( Builds::wheel( '66', 366 ) ) );
		$cleared = ( new RiserClearance( FitEngine::standard() ) )->wheels( $kit, self::build(), $options, array(), array() );
		self::assertSame( array( 366 ), array_map( static fn( Option $option ): int => $option->part->id, $cleared->offered ), 'R3 warns of them, as before' );
	}

	public function test_hidden_wheels_stay_hidden_and_the_slots_read_are_kept(): void {
		$kit     = KitDocuments::kit();
		$options = FitEngine::standard()->options( $kit, SlotKey::WHEELS, self::build(), array( Builds::wheel( '60', 360 ), Parts::of( array( 'set_size' => 4 ), 399 ) ) );
		$cleared = ( new RiserClearance( FitEngine::standard() ) )->wheels( $kit, self::build(), $options, array(), array() );
		self::assertSame( array( 399, 360 ), array_map( static fn( Option $option ): int => $option->part->id, $cleared->hidden ), 'a wheel with no diameter was hidden already' );
		self::assertSame( $options->against, $cleared->against );
		self::assertSame( SlotKey::WHEELS, $cleared->slot );
	}

	/**
	 * The risers offered, and the hidden ones with their reason's code, after clearance.
	 *
	 * @param list<Part> $bolts The kit's bolts.
	 * @param Kit|null   $kit   The kit, the test kit by default.
	 * @return array{offered: list<int>, hidden: array<int, string>}
	 */
	private static function risers( array $bolts, ?Kit $kit = null ): array {
		$kit     = $kit ?? KitDocuments::kit();
		$build   = self::build()->with( SlotKey::WHEELS, Builds::wheel( '57' ) );
		$options = FitEngine::standard()->options( $kit, SlotKey::RISERS, $build, array( Builds::riser( '0.25', 401 ), Builds::riser( '0.5', 402 ) ) );
		$options = ( new RiserClearance( FitEngine::standard() ) )->risers( $kit, $build, $options, $bolts );
		$hidden  = array();
		foreach ( $options->hidden as $option ) {
			$hidden[ $option->part->id ] = $option->reason()?->code->value ?? '';
		}
		return array(
			'offered' => array_map( static fn( Option $option ): int => $option->part->id, $options->offered ),
			'hidden'  => $hidden,
		);
	}

	public function test_a_riser_goes_when_no_bolts_in_stock_reach_through_it(): void {
		self::assertSame( array( 'offered' => array( 401 ), 'hidden' => array( 402 => 'riser_bolts_short' ) ), self::risers( array( Builds::bolts( '1.25', 601 ) ) ) );
		self::assertSame( array( 'offered' => array( 401, 402 ), 'hidden' => array() ), self::risers( array( Builds::bolts( '1.25', 601 ), Builds::bolts( '1.5', 602 ) ) ) );
		self::assertSame( array( 'offered' => array(), 'hidden' => array( 401 => 'riser_bolts_short', 402 => 'riser_bolts_short' ) ), self::risers( array( self::stocked( Builds::bolts( '1.5', 601 ), 0 ) ) ) );
	}

	// D-289: the bolts are worked out once for each riser thickness, and the first in stock that R4 allows ends the
	// search, past any sold out before it.
	public function test_risers_as_thick_get_the_same_answer_and_a_sold_out_bolt_is_passed_over(): void {
		$kit    = KitDocuments::kit();
		$build  = self::build()->with( SlotKey::WHEELS, Builds::wheel( '57' ) );
		$risers = FitEngine::standard()->options( $kit, SlotKey::RISERS, $build, array( Builds::riser( '0.5', 402 ), Parts::of( array( 'riser_thickness' => '0.5', 'set_size' => 4 ), 403 ) ) );
		$clear  = new RiserClearance( FitEngine::standard() );
		$ids    = static fn( array $options ): array => array_map( static fn( Option $option ): int => $option->part->id, $options );

		$short = $clear->risers( $kit, $build, $risers, array( Builds::bolts( '1.25', 601 ) ) );
		self::assertSame( array( 402, 403 ), $ids( $short->hidden ), 'both, for the one thickness' );
		$long = $clear->risers( $kit, $build, $risers, array( self::stocked( Builds::bolts( '1.5', 601 ), 0 ), Builds::bolts( '1.5', 602 ) ) );
		self::assertSame( array( 402, 403 ), $ids( $long->offered ) );
		$bolt = Builds::bolts( '1.5', 603 );
		$few  = $clear->risers( $kit, $build, $risers, array( new Part( $bolt->id, $bolt->brand, $bolt->specs, $bolt->price, true, 0 ) ) );
		self::assertSame( array( 402, 403 ), $ids( $few->hidden ), 'a bolt said to be in stock with none to sell is not enough' );
	}

	public function test_a_kit_with_no_hardware_slot_offers_every_riser(): void {
		self::assertSame( array( 'offered' => array( 401, 402 ), 'hidden' => array() ), self::risers( array(), self::kit( array( SlotKey::HARDWARE ) ) ) );
	}

	public function test_a_hidden_riser_says_how_long_the_bolts_must_be(): void {
		$kit     = KitDocuments::kit();
		$build   = self::build()->with( SlotKey::WHEELS, Builds::wheel( '57' ) );
		$options = FitEngine::standard()->options( $kit, SlotKey::RISERS, $build, array( Builds::riser( '0.5', 402 ) ) );
		$reason  = ( new RiserClearance( FitEngine::standard() ) )->risers( $kit, $build, $options, array() )->hidden[0]->reason();
		self::assertSame( RuleId::R4, $reason?->rule );
		$thin = FitEngine::standard()->options( $kit, SlotKey::RISERS, $build, array( Builds::riser( '0.125', 401 ) ) );
		self::assertSame( array( MessageCode::WheelNeedsRiser, MessageCode::RiserBoltsShort ), array_column( ( new RiserClearance( FitEngine::standard() ) )->risers( $kit, $build, $thin, array() )->hidden[0]->findings, 'code' ), 'R3\'s warning is kept before it' );
		self::assertSame( array( SlotKey::RISERS, SlotKey::HARDWARE ), $reason->slots );
		self::assertSame( MessageCode::RiserBoltsShort->values(), array_keys( $reason->values ) );
		$shortest = $reason->values['shortest'];
		self::assertInstanceOf( Measure::class, $shortest );
		self::assertTrue( $shortest->equals( Measure::inches( 13750 ) ), '⅞ in plus ½ in' );
	}
}
