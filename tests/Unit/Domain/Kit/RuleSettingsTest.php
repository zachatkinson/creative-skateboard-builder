<?php
/**
 * RuleSettings.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * A kit's rule switches and tolerances, from the rules document.
 */
final class RuleSettingsTest extends TestCase {

	public function test_the_rules_are_r1_to_r6(): void {
		self::assertSame( array( 'r1', 'r2', 'r3', 'r4', 'r5', 'r6' ), array_map( static fn( RuleId $rule ): string => $rule->value, RuleId::fit() ) );
		self::assertSame( array( 'r1', 'r2', 'r3', 'r4', 'r5', 'r6', 'r7' ), array_column( RuleId::cases(), 'value' ), 'R7, the quantities, is the engine\'s own' );
		self::assertTrue( RuleSettings::defaults()->enabled( RuleId::R7 ), 'and no kit switches it off' );
	}

	public function test_a_new_kit_starts_from_the_specs_seeds(): void {
		$rules = RuleSettings::defaults();
		foreach ( RuleId::fit() as $rule ) {
			self::assertTrue( $rules->enabled( $rule ), $rule->value );
		}
		self::assertSame( 0, $rules->rangeTolerance->tenThousandths );
		self::assertSame( Unit::Inch, $rules->rangeTolerance->unit );
		self::assertSame( 2500, $rules->axleFit->tenThousandths );
		self::assertSame( Unit::Inch, $rules->axleFit->unit );
		self::assertSame( 5000, $rules->axleWarn->tenThousandths );
		self::assertSame( Unit::Inch, $rules->axleWarn->unit );
		self::assertSame( 530000, $rules->classLimit( 'low' )->tenThousandths );
		self::assertSame( 560000, $rules->classLimit( 'mid' )->tenThousandths );
		self::assertSame( 560000, $rules->classLimit( 'high' )->tenThousandths );
		self::assertSame( Unit::Millimetre, $rules->classLimit( 'low' )->unit );
		self::assertCount( 2, $rules->riserSteps );
		self::assertSame( 20000, $rules->riserSteps[0]->over->tenThousandths );
		self::assertSame( Unit::Millimetre, $rules->riserSteps[0]->over->unit );
		self::assertSame( 2500, $rules->riserSteps[0]->riser->tenThousandths );
		self::assertSame( Unit::Inch, $rules->riserSteps[0]->riser->unit );
		self::assertSame( 60000, $rules->riserSteps[1]->over->tenThousandths );
		self::assertSame( 5000, $rules->riserSteps[1]->riser->tenThousandths );
		self::assertTrue( $rules->checkLength );
	}

	public function test_a_truck_with_no_class_or_one_nobody_knows_counts_as_high(): void {
		$document                     = RuleSettings::DEFAULTS;
		$document['r3']['limits']     = array( 'low' => '52', 'mid' => '54', 'high' => '57' );
		$rules                        = RuleSettings::fromDocument( $document );
		self::assertSame( 520000, $rules->classLimit( 'low' )->tenThousandths );
		self::assertSame( 540000, $rules->classLimit( 'mid' )->tenThousandths );
		self::assertSame( 570000, $rules->classLimit( 'high' )->tenThousandths );
		self::assertSame( 570000, $rules->classLimit( null )->tenThousandths );
		self::assertSame( 570000, $rules->classLimit( 'forged' )->tenThousandths );
	}

	public function test_each_rule_is_switched_on_its_own(): void {
		foreach ( RuleId::fit() as $off ) {
			$document                          = RuleSettings::DEFAULTS;
			$document[ $off->value ]['enabled'] = false;
			$rules                             = RuleSettings::fromDocument( $document );
			foreach ( RuleId::fit() as $rule ) {
				self::assertSame( $rule !== $off, $rules->enabled( $rule ), $off->value . ' off, ' . $rule->value );
			}
		}
	}

	public function test_every_setting_is_read_from_its_own_field(): void {
		$document                          = RuleSettings::DEFAULTS;
		$document['r1']['range_tolerance'] = '0.125';
		$document['r1']['axle_fit']        = '0.3';
		$document['r1']['axle_warn']       = '0.3';
		$document['r3']['risers']          = array(
			array( 'over' => '1.5', 'riser' => '0.125' ),
			array( 'over' => '3', 'riser' => '0.25' ),
			array( 'over' => '8', 'riser' => '0.5' ),
		);
		$document['r6']['check_length']    = false;
		$rules                             = RuleSettings::fromDocument( $document );
		self::assertSame( 1250, $rules->rangeTolerance->tenThousandths );
		self::assertSame( 3000, $rules->axleFit->tenThousandths );
		self::assertSame( 3000, $rules->axleWarn->tenThousandths, 'the two tolerances may be equal' );
		self::assertCount( 3, $rules->riserSteps );
		self::assertSame( 15000, $rules->riserSteps[0]->over->tenThousandths );
		self::assertSame( 1250, $rules->riserSteps[0]->riser->tenThousandths );
		self::assertSame( 80000, $rules->riserSteps[2]->over->tenThousandths );
		self::assertSame( 5000, $rules->riserSteps[2]->riser->tenThousandths );
		self::assertFalse( $rules->checkLength );
	}

	public function test_a_riser_table_holds_one_to_six_rows(): void {
		self::assertSame( 6, RuleSettings::MAX_RISER_STEPS );
		$document                 = RuleSettings::DEFAULTS;
		$document['r3']['risers'] = array( array( 'over' => '2', 'riser' => '0.25' ) );
		self::assertCount( 1, RuleSettings::fromDocument( $document )->riserSteps );
		$document['r3']['risers'] = array();
		foreach ( range( 1, 6 ) as $n ) {
			$document['r3']['risers'][] = array( 'over' => (string) $n, 'riser' => '0.25' );
		}
		self::assertCount( 6, RuleSettings::fromDocument( $document )->riserSteps );
	}

	/**
	 * @return iterable<string, array{callable(array<string, mixed>): array<string, mixed>, DocumentError, string}>
	 */
	public static function refused(): iterable {
		$set = static fn( string $rule, string $field, mixed $value ): callable => static function ( array $d ) use ( $rule, $field, $value ): array {
			$d[ $rule ][ $field ] = $value;
			return $d;
		};
		$drop = static fn( string $rule, ?string $field = null ): callable => static function ( array $d ) use ( $rule, $field ): array {
			if ( null === $field ) {
				unset( $d[ $rule ] );
			} else {
				unset( $d[ $rule ][ $field ] );
			}
			return $d;
		};
		yield 'another version' => array( static fn( array $d ): array => array( 'schema_version' => 3 ) + $d, DocumentError::UnknownVersion, 'schema_version' );
		foreach ( array( 'r1', 'r2', 'r3', 'r4', 'r5', 'r6' ) as $rule ) {
			yield $rule . ' left out' => array( $drop( $rule ), DocumentError::Missing, $rule );
			yield $rule . ' with no switch' => array( $drop( $rule, 'enabled' ), DocumentError::Missing, $rule . '.enabled' );
			yield $rule . ' switched by a word' => array( $set( $rule, 'enabled', 'on' ), DocumentError::WrongType, $rule . '.enabled' );
		}
		yield 'no range tolerance' => array( $drop( 'r1', 'range_tolerance' ), DocumentError::Missing, 'r1.range_tolerance' );
		yield 'a range tolerance that is a number' => array( $set( 'r1', 'range_tolerance', 0.125 ), DocumentError::WrongType, 'r1.range_tolerance' );
		yield 'no axle fit' => array( $drop( 'r1', 'axle_fit' ), DocumentError::Missing, 'r1.axle_fit' );
		yield 'an axle fit that is a fraction' => array( $set( 'r1', 'axle_fit', '1/4' ), DocumentError::BadMeasure, 'r1.axle_fit' );
		yield 'no axle warn' => array( $drop( 'r1', 'axle_warn' ), DocumentError::Missing, 'r1.axle_warn' );
		yield 'a warning tighter than the fit' => array( $set( 'r1', 'axle_warn', '0.2499' ), DocumentError::OutOfOrder, 'r1.axle_warn' );
		yield 'no limits' => array( $drop( 'r3', 'limits' ), DocumentError::Missing, 'r3.limits' );
		yield 'no low limit' => array( $set( 'r3', 'limits', array( 'mid' => '56', 'high' => '56' ) ), DocumentError::Missing, 'r3.limits.low' );
		yield 'no mid limit' => array( $set( 'r3', 'limits', array( 'low' => '53', 'high' => '56' ) ), DocumentError::Missing, 'r3.limits.mid' );
		yield 'no high limit' => array( $set( 'r3', 'limits', array( 'low' => '53', 'mid' => '56' ) ), DocumentError::Missing, 'r3.limits.high' );
		yield 'a limit in words' => array( $set( 'r3', 'limits', array( 'low' => '53 mm', 'mid' => '56', 'high' => '56' ) ), DocumentError::BadMeasure, 'r3.limits.low' );
		yield 'no riser table' => array( $drop( 'r3', 'risers' ), DocumentError::OutOfRange, 'r3.risers' );
		yield 'an empty riser table' => array( $set( 'r3', 'risers', array() ), DocumentError::OutOfRange, 'r3.risers' );
		yield 'seven rows' => array( $set( 'r3', 'risers', array_map( static fn( int $n ): array => array( 'over' => (string) $n, 'riser' => '0.25' ), range( 1, 7 ) ) ), DocumentError::OutOfRange, 'r3.risers' );
		yield 'a row with no reach' => array( $set( 'r3', 'risers', array( array( 'riser' => '0.25' ) ) ), DocumentError::Missing, 'r3.risers[0].over' );
		yield 'a row with no riser' => array( $set( 'r3', 'risers', array( array( 'over' => '2' ) ) ), DocumentError::Missing, 'r3.risers[0].riser' );
		yield 'a row that reaches no further than the last' => array( $set( 'r3', 'risers', array( array( 'over' => '2', 'riser' => '0.25' ), array( 'over' => '2', 'riser' => '0.5' ) ) ), DocumentError::OutOfOrder, 'r3.risers[1].over' );
		yield 'rows out of order' => array( $set( 'r3', 'risers', array( array( 'over' => '6', 'riser' => '0.5' ), array( 'over' => '2', 'riser' => '0.25' ) ) ), DocumentError::OutOfOrder, 'r3.risers[1].over' );
		yield 'no length switch' => array( $drop( 'r6', 'check_length' ), DocumentError::Missing, 'r6.check_length' );
		yield 'a length switch that is a number' => array( $set( 'r6', 'check_length', 1 ), DocumentError::WrongType, 'r6.check_length' );
	}

	/**
	 * @param callable(array<string, mixed>): array<string, mixed> $bend
	 */
	#[DataProvider( 'refused' )]
	public function test_a_rules_document_that_breaks_an_invariant_is_refused( callable $bend, DocumentError $reason, string $path ): void {
		try {
			RuleSettings::fromDocument( $bend( RuleSettings::DEFAULTS ) );
			self::fail( 'accepted' );
		} catch ( InvalidDocument $e ) {
			self::assertSame( $reason, $e->reason );
			self::assertSame( $path, $e->path );
		}
	}

	public function test_a_first_row_reaching_nothing_is_allowed_and_the_next_must_reach_further(): void {
		$document                 = RuleSettings::DEFAULTS;
		$document['r3']['risers'] = array( array( 'over' => '0', 'riser' => '0.125' ), array( 'over' => '0.0001', 'riser' => '0.25' ) );
		self::assertCount( 2, RuleSettings::fromDocument( $document )->riserSteps );
	}
}
