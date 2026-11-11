<?php
/**
 * A build's lines, kept together.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Port\Cart;
use CreativeBranding\SkateboardBuilder\Port\CartLine;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use Closure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Keeps a build in the cart whole (spec: Cart, checkout and orders, Keeping it together): its parts' quantities
 * follow the kit's line, removing any of its lines removes them all, an undo brings them all back, and what a
 * cart holds is checked again before it is paid for. The server does this, whichever cart the change came from.
 *
 * @since 0.5.0
 */
final class BuildLines {

	/**
	 * Whether a change of this class's own is under way, so the cart's hooks for it are not answered again.
	 *
	 * @var bool
	 */
	private bool $busy = false;

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Cart      $cart   The cart.
	 * @param Kits      $kits   The shop's kits, and a part as the shop has it now.
	 * @param BuildCart $builds Judges a build.
	 */
	public function __construct( private Cart $cart, private Kits $kits, private BuildCart $builds ) {}

	/**
	 * A line's quantity was set: each part of its build is put at what the kit's line now asks for. A part's line
	 * changed alone goes back to that.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function resized( string $key ): void {
		$this->alone(
			function () use ( $key ): void {
				$lines = $this->cart->lines();
				$found = self::kitLine( self::parentKey( $key, $lines ), $lines );
				if ( null === $found ) {
					return;
				}
				[ $kit, $build ] = $found;
				foreach ( $kit->children as $child ) {
					$part = $lines[ $child ] ?? null;
					$each = null === $part ? null : ( $build->quantities[ $part->slot ] ?? null );
					if ( null !== $part && null !== $each && $each * $kit->quantity !== $part->quantity ) {
						$this->cart->setQuantity( $child, $each * $kit->quantity );
					}
				}
			}
		);
	}

	/**
	 * A line is about to be removed: every other line of its build goes with it.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function removing( string $key ): void {
		$this->alone(
			function () use ( $key ): void {
				$lines = $this->cart->lines();
				$this->drop( self::parentKey( $key, $lines ), $lines, $key );
			}
		);
	}

	/**
	 * A removed line was put back, as the cart's Undo does: the rest of its build comes back with it. Where any
	 * of it cannot, none of it stays.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function restored( string $key ): void {
		$this->alone(
			function () use ( $key ): void {
				$kit = self::parentKey( $key, $this->cart->lines() );
				$this->cart->restore( $kit );
				foreach ( $this->cart->lines()[ $kit ]->children ?? array() as $child ) {
					$this->cart->restore( $child );
				}
				$lines = $this->cart->lines();
				if ( ! self::whole( $kit, $lines ) ) {
					$this->drop( $kit, $lines, '' );
				}
			}
		);
	}

	/**
	 * Whether a shopper may set a line's quantity themselves: not a part's, which follows its kit's line.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function editable( string $key ): bool {
		return '' === ( $this->cart->lines()[ $key ]->kit ?? '' );
	}

	/**
	 * The most a kit's line may hold: what the scarcest of its parts allows, beside what the cart's other lines
	 * take of it. Null where nothing limits it, or the line is no kit's.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The line's cart key.
	 */
	public function limit( string $key ): ?int {
		$room = array_column( $this->room( $key, $this->cart->lines() ), 1 );
		return array() === $room ? null : min( $room );
	}

	/**
	 * Whether a line's quantity may be set to a number: a part's may not be changed alone, and a kit's may not be
	 * raised past what the shop has of a part.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key      The line's cart key.
	 * @param int    $quantity The quantity asked for.
	 * @return Verdict|null Why not, or null when it may, or the line is no build's.
	 */
	public function mayResize( string $key, int $quantity ): ?Verdict {
		$lines = $this->cart->lines();
		$line  = $lines[ $key ] ?? null;
		if ( null !== $line && '' !== $line->kit ) {
			$found = self::kitLine( $line->kit, $lines );
			if ( null === $found || ! isset( $found[1]->quantities[ $line->slot ] ) ) {
				return Verdict::refused( CartRefusal::PartLocked );
			}
			return $found[1]->quantities[ $line->slot ] * $found[0]->quantity === $quantity ? null : Verdict::refused( CartRefusal::PartLocked );
		}
		// Say which parts stop it: each the shop has too few of for that many builds.
		$problems = array();
		foreach ( $this->room( $key, $lines ) as $slot => [ $part, $most ] ) {
			if ( $quantity > $most ) {
				$problems[] = new BuildProblem( Problem::PartShort, $slot, $part );
			}
		}
		return array() === $problems ? null : Verdict::refused( CartRefusal::BuildRefused, $problems );
	}

	/**
	 * Look the cart over before it is paid for. A build that has lost its kit's line or one of its parts, or whose
	 * lines no longer name one another, is dropped with what is left of it. Each whole build has its quantities
	 * put right and is judged again as the shop is now, since stock, specs or the kit may have changed since it
	 * was added.
	 *
	 * @since 0.5.0
	 */
	public function tidy(): Tidied {
		$dropped = 0;
		$this->alone(
			function () use ( &$dropped ): void {
				$lines = $this->cart->lines();
				$kits  = array();
				foreach ( array_keys( $lines ) as $key ) {
					$kits[] = self::parentKey( $key, $lines );
				}
				foreach ( array_unique( $kits ) as $kit ) {
					if ( '' !== $kit && ! self::whole( $kit, $lines ) ) {
						$this->drop( $kit, $lines, '' );
						++$dropped;
					}
				}
			}
		);
		$refused = array();
		foreach ( $this->cart->lines() as $key => $line ) {
			$build = Build::read( $line->build );
			if ( null === $build ) {
				continue;
			}
			$this->resized( $key );
			$verdict = $this->builds->recheck( $build );
			if ( null !== $verdict ) {
				$refused[ $key ] = $verdict;
			}
		}
		return new Tidied( $dropped, $refused );
	}

	/**
	 * How many of a kit's line's build each of its parts allows, for the parts the shop counts: what it has of
	 * the part, less what the cart holds of it outside this build, over what one build takes of it.
	 *
	 * @param string                  $key   The kit's line's cart key.
	 * @param array<string, CartLine> $lines The cart's lines.
	 * @return array<string, array{int, int}> Slot key to the part and how many builds. Empty for a line that is no
	 *                                        kit's, or whose kit no longer reads.
	 */
	private function room( string $key, array $lines ): array {
		$build = self::kitLine( $key, $lines )[1] ?? null;
		$slots = null === $build ? null : $this->kits->kit( $build->kit )?->slots;
		if ( null === $build || null === $slots ) {
			return array();
		}
		$each = array();
		$room = array();
		foreach ( $build->picks as $slot => $part ) {
			$each[ $part ] = ( $each[ $part ] ?? 0 ) + $build->quantities[ $slot ];
		}
		$asked = array();
		foreach ( $build->picks as $slot => $part ) {
			$found = $slots->get( $slot );
			if ( null !== $found ) {
				$asked[ $slot ] = array( $found, $part );
			}
		}
		$read = $this->kits->picked( $asked );
		foreach ( $build->picks as $slot => $part ) {
			$stock = ( $read[ $slot ] ?? null )?->stock;
			if ( null === $stock ) {
				continue;
			}
			foreach ( $lines as $line ) {
				if ( $line->part === $part && $line->kit !== $key ) {
					$stock -= $line->quantity;
				}
			}
			$room[ $slot ] = array( $part, intdiv( max( 0, $stock ), $each[ $part ] ) );
		}
		return $room;
	}

	/**
	 * Make a change to the cart that its own hooks will tell this class of, and answer none of them meanwhile.
	 * A change asked for while another is under way is not made.
	 *
	 * @param Closure(): void $change The change.
	 */
	private function alone( Closure $change ): void {
		if ( $this->busy ) {
			return;
		}
		$this->busy = true;
		try {
			$change();
		} finally {
			$this->busy = false;
		}
	}

	/**
	 * Whether a kit's line and its parts are all there and tied to one another: the kit's line reads, names a line
	 * for each of its picks and no other, each names it back with its slot and its part, and no other line names
	 * it.
	 *
	 * @param string                  $kit   The kit's line's cart key.
	 * @param array<string, CartLine> $lines The cart's lines.
	 */
	private static function whole( string $kit, array $lines ): bool {
		$line  = $lines[ $kit ] ?? null;
		$named = array();
		foreach ( $line->children ?? array() as $child ) {
			if ( ( $lines[ $child ]->kit ?? null ) === $kit ) {
				$named[ $lines[ $child ]->slot ] = $lines[ $child ]->part;
			}
		}
		$tied = 0;
		foreach ( $lines as $other ) {
			$tied += $other->kit === $kit ? 1 : 0;
		}
		$picks = self::kitLine( $kit, $lines )[1]->picks ?? array();
		ksort( $picks );
		ksort( $named );
		return array() !== $picks && $picks === $named && count( $picks ) === $tied && count( $picks ) === count( $line->children ?? array() );
	}

	/**
	 * Remove a build's lines: its kit's and each that names it, but one.
	 *
	 * @param string                  $kit   The kit's line's cart key, or '' for no build.
	 * @param array<string, CartLine> $lines The cart's lines.
	 * @param string                  $but   A line to leave, which the cart is removing itself.
	 */
	private function drop( string $kit, array $lines, string $but ): void {
		foreach ( $lines as $key => $line ) {
			if ( '' !== $kit && $key !== $but && ( $key === $kit || $line->kit === $kit ) ) {
				$this->cart->remove( $key );
			}
		}
	}

	/**
	 * The cart key of the kit's line a line belongs to: its own for a kit's line, '' for a line of no build.
	 *
	 * @param string                  $key   The line's cart key.
	 * @param array<string, CartLine> $lines The cart's lines.
	 */
	private static function parentKey( string $key, array $lines ): string {
		$line = $lines[ $key ] ?? null;
		if ( null === $line || '' !== $line->kit ) {
			return $line->kit ?? '';
		}
		return null === $line->build ? '' : $key;
	}

	/**
	 * A kit's line and the build it carries, where the cart holds the line and its build reads.
	 *
	 * @param string                  $key   The line's cart key.
	 * @param array<string, CartLine> $lines The cart's lines.
	 * @return array{CartLine, Build}|null
	 */
	private static function kitLine( string $key, array $lines ): ?array {
		$line = $lines[ $key ] ?? null;
		if ( null === $line ) {
			return null;
		}
		$build = Build::read( $line->build );
		return null === $build ? null : array( $line, $build );
	}
}
