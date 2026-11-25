<?php
declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Service\Phases;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * #125: the phases of a state request, timed for a shop manager's Server-Timing header (D-365).
 */
#[CoversClass( Phases::class )]
final class PhasesTest extends TestCase {

	/**
	 * A clock that reads each of the moments given, in turn.
	 *
	 * @param string ...$moments Each moment, as `U.u`.
	 */
	private static function clock( string ...$moments ): Clock {
		return new class( $moments ) implements Clock {
			/**
			 * @param list<string> $moments The moments.
			 */
			public function __construct( private array $moments ) {}

			public function now(): DateTimeImmutable {
				$moment = array_shift( $this->moments );
				return DateTimeImmutable::createFromFormat( 'U.u', $moment ?? '0.0' ) ?: new DateTimeImmutable( '@0' );
			}
		};
	}

	public function test_each_phase_takes_its_works_time_and_a_phase_run_again_adds_up(): void {
		$phases = new Phases( self::clock( '100.250000', '100.262500', '101.999000', '102.000500', '102.000500', '102.003500' ) );
		self::assertSame( 'shelf', $phases->run( 'shelf', static fn(): string => 'shelf' ) );
		self::assertSame( 7, $phases->run( 'cards', static fn(): int => 7 ) );
		$phases->run( 'shelf', static fn(): null => null );
		self::assertSame(
			array(
				'shelf' => 15.5,
				'cards' => 1.5,
			),
			$phases->took(),
			'in milliseconds, across a second, in the order each first ran'
		);
		$phases->forget();
		self::assertSame( array(), $phases->took() );
	}

	public function test_a_phase_that_throws_is_timed_all_the_same(): void {
		$phases = new Phases( self::clock( '5.000000', '5.002000' ) );
		try {
			$phases->run(
				'boards',
				static function (): never {
					throw new RuntimeException( 'no board' );
				}
			);
			self::fail( 'The work threw.' );
		} catch ( RuntimeException ) {
			self::assertSame( array( 'boards' => 2.0 ), $phases->took() );
		}
	}

	public function test_the_milliseconds_between_two_moments(): void {
		self::assertSame(
			1001.5,
			Phases::between( new DateTimeImmutable( '@10.999000' ), new DateTimeImmutable( '@12.000500' ) )
		);
	}
}
