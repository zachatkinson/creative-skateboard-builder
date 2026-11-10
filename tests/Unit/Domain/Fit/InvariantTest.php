<?php
/**
 * The invariant: what the engine offers, the validator accepts.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\ChangePlanner;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Recommender;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Severity;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Catalogue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;

/**
 * Seeded random builds on a generated catalogue (spec: Testing; docs/ROADMAP.md, milestone 2). The step, the cart,
 * the change list and the recommender all ask one engine; if any of them came to decide fit for itself, these runs
 * are where it would show. A failure names its seed, and the seed makes the same run again.
 *
 * In the group `invariant`: the unit suite runs it, and the two runs that measure the domain's floors (Xdebug's path
 * coverage and Infection) leave it out. Path coverage makes these walks take minutes, and the floors are the case
 * tests' to meet: a branch or a mutant only a random walk reaches is not one a test has pinned down.
 */
#[Group( 'invariant' )]
final class InvariantTest extends TestCase {

	/**
	 * How many seeds each invariant runs.
	 */
	private const int RUNS = 200;

	/**
	 * How many parts each slot of a generated catalogue holds.
	 */
	private const int PARTS = 30;

	/**
	 * A build made the way a shopper makes one: each step picks one of the parts the step offers that is in stock,
	 * and a slot the kit does not require is left empty half the time. Null when a required step offers nothing.
	 *
	 * @param array<string, list<Part>> $catalogue The catalogue.
	 * @param Randomizer                $random    The run's randomness.
	 */
	private static function walk( array $catalogue, Randomizer $random ): ?Picks {
		$kit    = KitDocuments::kit();
		$engine = FitEngine::standard();
		$picks  = Picks::none();
		foreach ( $kit->slots->all() as $slot ) {
			if ( ! $slot->required && Catalogue::chance( $random, 50 ) ) {
				continue;
			}
			$pickable = array_values( array_filter( $engine->options( $kit, $slot->key, $picks, $catalogue[ $slot->key ] )->offered, static fn( Option $option ): bool => $option->part->inStock ) );
			if ( array() === $pickable ) {
				if ( $slot->required ) {
					return null;
				}
				continue;
			}
			$picks = $picks->with( $slot->key, Catalogue::pick( $random, $pickable )->part );
		}
		return $picks;
	}

	public function test_the_generated_catalogue_has_a_shops_faults_in_it(): void {
		$kit       = KitDocuments::kit();
		$engine    = FitEngine::standard();
		$catalogue = Catalogue::generate( 1 );
		self::assertEquals( $catalogue, Catalogue::generate( 1 ), 'a seed makes the same catalogue again' );
		self::assertNotEquals( $catalogue, Catalogue::generate( 2 ) );
		self::assertSame( array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS, SlotKey::RISERS, SlotKey::BEARINGS, SlotKey::HARDWARE, SlotKey::GRIP ), array_keys( $catalogue ) );

		$decks = $engine->options( $kit, SlotKey::DECK, Picks::none(), $catalogue[ SlotKey::DECK ] );
		$seen  = array();
		foreach ( $decks->offered as $deck ) {
			$trucks = $engine->options( $kit, SlotKey::TRUCKS, Picks::none()->with( SlotKey::DECK, $deck->part ), $catalogue[ SlotKey::TRUCKS ] );
			foreach ( array( ...$trucks->offered, ...$trucks->hidden ) as $option ) {
				$seen[ $option->severity->value ] = true;
				foreach ( $option->findings as $finding ) {
					$seen[ $finding->code->value ] = true;
				}
				$seen[ $option->part->inStock ? 'in stock' : 'sold out' ]          = true;
				$seen[ null === $option->quantity ? 'no set size' : 'a set size' ] = true;
			}
		}
		foreach ( array( 'fits', 'warn', 'block', 'spec_missing', 'deck_above_range', 'deck_below_range', 'pattern_mismatch', 'limit_from_class', 'axle_assumed', 'in stock', 'sold out', 'no set size', 'a set size' ) as $expected ) {
			self::assertArrayHasKey( $expected, $seen, $expected );
		}
		self::assertNotSame( array(), $decks->hidden, 'decks with no width' );
	}

	public function test_what_the_engine_offers_the_validator_accepts(): void {
		$kit       = KitDocuments::kit();
		$validator = new BuildValidator( FitEngine::standard() );
		$built     = 0;
		$warned    = 0;
		$raised    = 0;
		for ( $seed = 1; $seed <= self::RUNS; $seed++ ) {
			$catalogue = Catalogue::generate( $seed, self::PARTS );
			$picks     = self::walk( $catalogue, new Randomizer( new Mt19937( $seed ) ) );
			if ( null === $picks ) {
				continue;
			}
			++$built;
			$validation = $validator->validate( $kit, $picks, Catalogue::ids( $catalogue ) );
			self::assertSame( array(), array_map( static fn( $problem ): string => $problem->code->value . ' ' . $problem->slot, $validation->problems ), 'seed ' . $seed );
			foreach ( $validation->build as $option ) {
				self::assertNotNull( $option->quantity, 'seed ' . $seed );
				$warned += Severity::Warn === $option->severity ? 1 : 0;
			}
			$raised += null === $picks->part( SlotKey::RISERS ) ? 0 : 1;
		}
		self::assertGreaterThan( intdiv( self::RUNS, 2 ), $built, 'most runs reach a whole build' );
		self::assertGreaterThan( 0, $warned, 'some of them with a warning' );
		self::assertGreaterThan( 0, $raised, 'and some with risers' );
	}

	public function test_what_the_engine_hides_the_validator_refuses(): void {
		$kit       = KitDocuments::kit();
		$engine    = FitEngine::standard();
		$validator = new BuildValidator( $engine );
		$refused   = 0;
		for ( $seed = 1; $seed <= self::RUNS; $seed++ ) {
			$catalogue = Catalogue::generate( $seed, self::PARTS );
			$random    = new Randomizer( new Mt19937( $seed ) );
			$picks     = self::walk( $catalogue, $random );
			if ( null === $picks ) {
				continue;
			}
			$slot   = Catalogue::pick( $random, $picks->slots() );
			$hidden = $engine->options( $kit, $slot, $picks, $catalogue[ $slot ] )->hidden;
			if ( array() === $hidden ) {
				continue;
			}
			++$refused;
			$forced     = Catalogue::pick( $random, $hidden )->part;
			$validation = $validator->validate( $kit, $picks->with( $slot, $forced ), Catalogue::ids( $catalogue ) );
			self::assertFalse( $validation->isValid(), 'seed ' . $seed );
			self::assertContains( array( Problem::PartBlocked, $slot, $forced->id ), array_map( static fn( $problem ): array => array( $problem->code, $problem->slot, $problem->part ), $validation->problems ), 'seed ' . $seed );
		}
		self::assertGreaterThan( intdiv( self::RUNS, 3 ), $refused );
	}

	public function test_what_the_recommender_recommends_the_step_offers_and_the_validator_accepts(): void {
		$kit         = KitDocuments::kit();
		$engine      = FitEngine::standard();
		$recommender = new Recommender( $engine );
		$validator   = new BuildValidator( $engine );
		$built       = 0;
		for ( $seed = 1; $seed <= self::RUNS; $seed++ ) {
			$catalogue = Catalogue::generate( $seed, self::PARTS );
			$random    = new Randomizer( new Mt19937( $seed ) );
			$decks     = array_values( array_filter( $engine->options( $kit, SlotKey::DECK, Picks::none(), $catalogue[ SlotKey::DECK ] )->offered, static fn( Option $option ): bool => $option->part->inStock ) );
			$picks     = Picks::none()->with( SlotKey::DECK, Catalogue::pick( $random, $decks )->part );
			foreach ( $kit->slots->after( SlotKey::DECK ) as $slot ) {
				$recommended = $recommender->recommend( $kit, $slot, $picks, $catalogue[ $slot ] );
				if ( null === $recommended ) {
					continue;
				}
				self::assertSame( Severity::Fits, $recommended->severity, 'seed ' . $seed . ' ' . $slot );
				self::assertTrue( $recommended->part->inStock, 'seed ' . $seed . ' ' . $slot );
				self::assertNotNull( $engine->options( $kit, $slot, $picks, $catalogue[ $slot ] )->offer( $recommended->part->id ), 'seed ' . $seed . ' ' . $slot );
				$picks = $picks->with( $slot, $recommended->part );
			}
			$validation = $validator->validate( $kit, $picks, Catalogue::ids( $catalogue ) );
			foreach ( $validation->problems as $problem ) {
				self::assertSame( Problem::SlotEmpty, $problem->code, 'seed ' . $seed . ': a slot with nothing to recommend is the only thing left to do' );
			}
			$built += $validation->isValid() ? 1 : 0;
		}
		self::assertGreaterThan( intdiv( self::RUNS, 2 ), $built, 'a deck picked and the recommendations taken is most often a whole build' );
	}

	public function test_what_the_change_list_proposes_the_validator_accepts(): void {
		$kit       = KitDocuments::kit();
		$engine    = FitEngine::standard();
		$planner   = new ChangePlanner( $engine );
		$validator = new BuildValidator( $engine );
		$changed   = 0;
		$swapped   = 0;
		for ( $seed = 1; $seed <= self::RUNS; $seed++ ) {
			$catalogue = Catalogue::generate( $seed, self::PARTS );
			$random    = new Randomizer( new Mt19937( $seed ) );
			$before    = self::walk( $catalogue, $random );
			if ( null === $before ) {
				continue;
			}
			$decks = array_values( array_filter( $engine->options( $kit, SlotKey::DECK, Picks::none(), $catalogue[ SlotKey::DECK ] )->offered, static fn( Option $option ): bool => $option->part->inStock ) );
			$list  = $planner->plan( $kit, $before, SlotKey::DECK, Catalogue::pick( $random, $decks )->part, $catalogue );
			self::assertSame( $before, $list->before, 'seed ' . $seed );
			$changed += $list->isClean() ? 0 : 1;
			foreach ( $list->changes as $change ) {
				self::assertNotSame( array(), $change->findings, 'seed ' . $seed );
				self::assertNotContains( MessageCode::LimitFromClass, array_column( $change->findings, 'code' ), 'only what blocks' );
				$swapped += null === $change->closest ? 0 : 1;
			}
			$validation = $validator->validate( $kit, $list->proposed, Catalogue::ids( $catalogue ) );
			foreach ( $validation->problems as $problem ) {
				self::assertSame( Problem::SlotEmpty, $problem->code, 'seed ' . $seed . ': a slot with no closest fit is left to pick again, and nothing else is wrong' );
			}
		}
		self::assertGreaterThan( intdiv( self::RUNS, 10 ), $changed, 'a different deck often breaks a later pick' );
		self::assertGreaterThan( 0, $swapped );
	}
}
