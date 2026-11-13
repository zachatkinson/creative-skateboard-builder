<?php
/**
 * A build in the cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Fields;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Domain\Pricing\Pricer;
use CreativeBranding\SkateboardBuilder\Port\Cart;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\Uuids;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Adds a build to the cart and keeps it whole (spec: Cart, checkout and orders). Every hook of the cart calls
 * this, whichever route a request came by, so there is one answer to whether a build may be bought.
 *
 * Nothing the request says is taken on trust. A pick is an ID; the part is read from WooCommerce as it is at
 * that moment, never from what the builder was shown (issue #26), and is taken only if it is one of its slot's
 * parts that a shopper could buy. The whole build is then checked by the domain's validator, and each part is
 * counted across every line the cart already holds.
 *
 * @since 0.5.0
 */
final class BuildCart {

	/**
	 * The builds taken in this request and not yet in the cart with their parts, by kit and by the build's ID. A
	 * kit may be bought only while one of its builds is here.
	 *
	 * @var array<int, array<string, Build>>
	 */
	private array $taken = array();

	/**
	 * The build last offered, and what was said of it, so that the hooks that ask twice are answered once.
	 *
	 * @var array{array{int, array<string, int>, bool, int, string}, Verdict}|null
	 */
	private ?array $said = null;

	/**
	 * The build each build taken in this request replaces, by the new build's ID: Edit build's add (D-157).
	 *
	 * @var array<string, string>
	 */
	private array $replacing = array();

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Kits         $kits     The shop's kits, and a part as the shop has it now.
	 * @param Cart         $cart     The cart.
	 * @param ShopSettings $settings The shop's settings, for the build discount.
	 * @param Uuids        $uuids    Gives each build its identifier.
	 */
	public function __construct( private Kits $kits, private Cart $cart, private ShopSettings $settings, private Uuids $uuids ) {}

	/**
	 * Whether a build of a kit may go to the cart.
	 *
	 * @since 0.5.0
	 *
	 * @param int   $kit      The kit product's ID.
	 * @param mixed $request  What was sent with it: `picks`, slot key to part ID; `assembly`, which may be left
	 *                        out for the kit's own default; and `replace`, from Edit build, the ID of the build in
	 *                        the cart this one takes the place of once all of it is added (D-157).
	 * @param int   $quantity How many of the build.
	 */
	public function consider( int $kit, mixed $request, int $quantity ): Verdict {
		$picks = is_array( $request ) ? ( $request['picks'] ?? null ) : null;
		if ( ! is_array( $request ) || ! is_array( $picks ) ) {
			return Verdict::refused( CartRefusal::NoBuild );
		}
		// A request's body is not bounded as a form's fields are: more picks than a kit can have slots, or a key no
		// slot could have, is refused before the kit or a part is read, and nothing of it is said back.
		if ( count( $picks ) > Slots::MAX ) {
			return Verdict::refused( CartRefusal::BuildRefused );
		}
		$asked = array();
		foreach ( $picks as $slot => $id ) {
			if ( ! is_string( $slot ) || ! Fields::isKey( $slot ) ) {
				return Verdict::refused( CartRefusal::BuildRefused );
			}
			$asked[ $slot ] = self::id( $id );
		}
		$read = $this->kits->isPublic( $kit ) ? $this->kits->kit( $kit ) : null;
		if ( null === $read ) {
			return Verdict::refused( CartRefusal::KitNotFound );
		}
		$assembly = array_key_exists( 'assembly', $request ) ? self::flag( $request['assembly'] ) : $read->assembly->defaultOn;
		$replace  = $request['replace'] ?? null;
		$replace  = is_string( $replace ) && 1 === preg_match( '/\A[a-z0-9-]{1,64}\z/', $replace ) ? $replace : '';
		$asking   = array( $kit, $asked, $assembly, $quantity, $replace );
		if ( null === $this->said || $asking !== $this->said[0] ) {
			// Edit build's add keeps the quantity the cart holds of the build it replaces, and is counted against the
			// shop's stock without that build's lines, which go once it is in (D-157).
			$held       = $this->held( $replace );
			$this->said = array( $asking, $this->judge( $kit, $read, $asked, $assembly, max( 1, $quantity, $held[1] ), $held[0] ) );
		}
		if ( null !== $this->said[1]->build && '' !== $replace ) {
			$this->replacing[ $this->said[1]->build->id ] = $replace;
		}
		return $this->said[1];
	}

	/**
	 * Whether a kit may be bought now: only while a build of it has been taken in this request and its parts are
	 * not yet in the cart beside it.
	 *
	 * @since 0.5.0
	 *
	 * @param int $kit The kit product's ID.
	 */
	public function admits( int $kit ): bool {
		return array() !== ( $this->taken[ $kit ] ?? array() );
	}

	/**
	 * A kit's line was added: add a line for each of its build's parts and tie them to it. A kit's line whose
	 * build was not taken in this request is not one of ours, and is removed.
	 *
	 * @since 0.5.0
	 *
	 * @param string $key The new line's cart key.
	 * @return CartRefusal|null Why nothing of the build stays in the cart, or null when all of it does or the
	 *                          line is no kit's.
	 */
	public function attach( string $key ): ?CartRefusal {
		$line = $this->cart->lines()[ $key ] ?? null;
		if ( null === $line || null === $line->build || array() !== $line->children ) {
			return null;
		}
		$build = Build::read( $line->build );
		if ( null === $build || ! isset( $this->taken[ $build->kit ][ $build->id ] ) ) {
			$this->cart->remove( $key );
			return CartRefusal::NoBuild;
		}
		unset( $this->taken[ $build->kit ][ $build->id ] );
		$this->said = null;

		// Edit build's add takes the build it replaces out first, so that WooCommerce's own stock check does not
		// count it beside the new one, and puts it back as it was when the new one cannot all go in: the shopper
		// never ends up with neither. The new one keeps the quantity the cart held of it (D-157).
		[ $old, $held ] = $this->held( $this->replacing[ $build->id ] ?? '' );
		unset( $this->replacing[ $build->id ] );
		foreach ( $old as $gone ) {
			$this->cart->remove( $gone );
		}
		$children = array();
		foreach ( $build->picks as $slot => $part ) {
			$child = $this->cart->add( $part, $build->quantities[ $slot ] * $line->quantity, $key, $slot );
			if ( null === $child ) {
				foreach ( array( ...array_values( $children ), $key ) as $added ) {
					$this->cart->remove( $added );
				}
				foreach ( $old as $back ) {
					$this->cart->restore( $back );
				}
				return CartRefusal::NotAdded;
			}
			$children[ $slot ] = $child;
		}
		$this->cart->setChildren( $key, array_values( $children ) );
		if ( $held > $line->quantity ) {
			$this->cart->setQuantity( $key, $held );
			foreach ( $children as $slot => $child ) {
				$this->cart->setQuantity( $child, $build->quantities[ $slot ] * $held );
			}
		}
		return null;
	}

	/**
	 * The lines of the build in the cart with this ID, and how many of it the cart holds: none where no build has it.
	 *
	 * @param string $id A build's ID, or empty.
	 * @return array{list<string>, int}
	 */
	private function held( string $id ): array {
		if ( '' !== $id ) {
			foreach ( $this->cart->lines() as $key => $line ) {
				if ( Build::read( $line->build )?->id === $id ) {
					return array( array( ...$line->children, $key ), $line->quantity );
				}
			}
		}
		return array( array(), 0 );
	}

	/**
	 * What a part of a build is charged at: the shop's price for it, less the build discount of the shop's
	 * settings, rounded half up to the minor unit as the builder's total rounds it.
	 *
	 * @since 0.5.0
	 *
	 * @param int $price The shop's price, in minor units.
	 */
	public function charged( int $price ): int {
		return Pricer::discounted( $price, $this->settings->terms()->discount );
	}

	/**
	 * A part's line's price, each time the cart's totals are worked out. The price the shop asks is what the
	 * line's product says, unless that is the price this gave it the last time: then it is the price asked then, so
	 * the discount comes off once however often the totals are worked out.
	 *
	 * @since 0.5.0
	 *
	 * @param string                     $now      The price the line's product says now.
	 * @param array{string, string}|null $given    The price asked and the price given the last time, or null.
	 * @param int                        $decimals The currency's decimals.
	 * @return array{string, string|null} The price asked, and the price to give the line: null where the two are
	 *                                    the same.
	 */
	public function repriced( string $now, ?array $given, int $decimals ): array {
		$asked   = null !== $given && $given[1] === $now ? $given[0] : $now;
		$minor   = (int) round( (float) $asked * 10 ** $decimals );
		$charged = $this->charged( $minor );
		return array( $asked, $charged === $minor ? null : number_format( $charged / 10 ** $decimals, $decimals, '.', '' ) );
	}

	/**
	 * Whether a coupon takes a part of a build. The part answers with its kit: a coupon that leaves the kit out
	 * leaves its parts out; a fixed product coupon stays on the kit's line unless the shop's setting sends it to
	 * the parts; any other takes a part it would take alone, or whose kit it takes. A part the coupon itself
	 * leaves out, by its ID, its category or its being on sale, stays left out: being in a build earns it nothing
	 * the shop's coupon denies it.
	 *
	 * @since 0.5.0
	 *
	 * @param bool $fixed  Whether it is a fixed product coupon, whose amount comes off each item.
	 * @param bool $part   Whether it takes the part as a product of its own.
	 * @param bool $kit    Whether it takes the kit.
	 * @param bool $left   Whether it names the kit, or a category of the kit's, as left out.
	 * @param bool $barred Whether it leaves the part itself out.
	 */
	public function couponTakes( bool $fixed, bool $part, bool $kit, bool $left, bool $barred ): bool {
		if ( $left || $barred || ( $fixed && BuildTerms::ON_PARTS !== $this->settings->terms()->fixedCoupons ) ) {
			return false;
		}
		return $part || $kit;
	}

	/**
	 * Judge again a build whose lines are in the cart, as the shop is now: its kit, each part and the stock, with
	 * the cart's lines counted as they stand. Nothing is taken and nothing changes.
	 *
	 * @since 0.5.0
	 *
	 * @param Build $build The build.
	 * @return Verdict|null Why it can no longer be bought, or null when it can.
	 */
	public function recheck( Build $build ): ?Verdict {
		$kit = $this->kits->isPublic( $build->kit ) ? $this->kits->kit( $build->kit ) : null;
		if ( null === $kit ) {
			return Verdict::refused( CartRefusal::KitNotFound );
		}
		$problems = $this->examine( $kit, $build->picks, 0 )[0];
		return array() === $problems ? null : Verdict::refused( CartRefusal::BuildRefused, $problems );
	}

	/**
	 * What the kit's line of a build costs, in minor units: the kit's assembly fee where the shop assembles the
	 * build, and nothing where it does not, or where the kit no longer reads or its fee has no minor unit.
	 *
	 * @since 0.5.0
	 *
	 * @param Build $build The build.
	 */
	public function fee( Build $build ): int {
		$kit = $build->assembly ? $this->kits->kit( $build->kit ) : null;
		try {
			return $kit?->assembly->fee( $this->kits->decimals() ) ?? 0;
		} catch ( InvalidMoney ) {
			return 0;
		}
	}

	/**
	 * Check a build whose kit reads.
	 *
	 * @param int                $id       The kit product's ID.
	 * @param Kit                $kit      The kit.
	 * @param array<string, int> $asked The picks: slot key to part ID, 0 where what was sent is no ID.
	 * @param bool               $assembly Whether the shop assembles it.
	 * @param int                $quantity How many of the build, one or more.
	 * @param list<string>       $leaving  The cart's lines that go once it is in: the build it replaces.
	 */
	private function judge( int $id, Kit $kit, array $asked, bool $assembly, int $quantity, array $leaving ): Verdict {
		[ $problems, $ids, $quantities, $options ] = $this->examine( $kit, $asked, $quantity, $leaving );
		if ( array() !== $problems ) {
			return Verdict::refused( CartRefusal::BuildRefused, $problems );
		}
		try {
			( new Pricer() )->quote( $kit, $options, $assembly, $this->settings->terms()->discount, $this->kits->decimals() );
		} catch ( InvalidMoney ) {
			return Verdict::refused( CartRefusal::NoPrice );
		}
		$build                            = new Build( $this->uuids->next(), $id, $ids, $quantities, $assembly );
		$this->taken[ $id ][ $build->id ] = $build;
		return Verdict::taken( $build );
	}

	/**
	 * What is wrong with a set of picks, as the shop is now, and what the build takes.
	 *
	 * @param Kit                $kit   The kit.
	 * @param array<string, int> $asked The picks: slot key to part ID, 0 where what was sent is no ID. Each key
	 *                                  is a string: a request's are checked as slot keys before this, and a
	 *                                  stored build's are read as strings (D-139).
	 * @param int                $more  How many of the build are to be added to what the cart holds: none for a
	 *                                      build whose lines are in the cart already.
	 * @param list<string>       $leaving The cart's lines not counted: those that go once the build is in.
	 * @return array{list<BuildProblem>, array<string, int>, array<string, int>, array<string, Option>}
	 *         The problems; each slot's part; how many of it one build takes; the build's options.
	 */
	private function examine( Kit $kit, array $asked, int $more, array $leaving = array() ): array {
		$picks    = Picks::none();
		$found    = array();
		$problems = array();
		$named    = array();
		foreach ( $asked as $key => $part ) {
			$slot = $kit->slots->get( $key );
			if ( null !== $slot && 0 !== $part ) {
				$named[ $key ] = array( $slot, $part );
			}
		}
		$parts = $this->kits->picked( $named );
		foreach ( $asked as $key => $part ) {
			$read = $parts[ $key ] ?? null;
			if ( null === $read ) {
				$problems[] = new BuildProblem( null === $kit->slots->get( $key ) ? Problem::SlotUnknown : Problem::PartNotInSlot, $key, 0 === $part ? null : $part );
				continue;
			}
			$picks         = $picks->with( $key, $read );
			$found[ $key ] = array( $part );
		}
		$validation = ( new BuildValidator( FitEngine::standard() ) )->validate( $kit, $picks, $found );
		$problems   = array( ...$problems, ...$validation->problems );

		// Each part is counted across the cart: what this build takes of it, in every slot and for every copy of
		// the build to be added, beside what the cart's lines already hold.
		$needed = array();
		foreach ( array_diff_key( $this->cart->lines(), array_flip( $leaving ) ) as $line ) {
			$needed[ $line->part ] = ( $needed[ $line->part ] ?? 0 ) + $line->quantity;
		}
		$ids        = array();
		$quantities = array();
		foreach ( $validation->build as $slot => $option ) {
			if ( null !== $option->quantity ) {
				$ids[ $slot ]                = $option->part->id;
				$quantities[ $slot ]         = $option->quantity;
				$needed[ $option->part->id ] = ( $needed[ $option->part->id ] ?? 0 ) + $option->quantity * $more;
			}
		}
		foreach ( $ids as $slot => $part ) {
			$option = $validation->build[ $slot ];
			// One the validator has already found sold out or short for a single build is not said twice.
			if ( $option->part->inStock && $option->available() && ! $option->part->covers( $needed[ $part ] ) ) {
				$problems[] = new BuildProblem( Problem::PartShort, $slot, $part );
			}
		}
		return array( $problems, $ids, $quantities, $validation->build );
	}

	/**
	 * A part's ID as a request gives it: a whole number of one or more, or the same as digits, as a form sends
	 * it. Anything else is 0, which is no part.
	 *
	 * @param mixed $sent What was sent.
	 */
	private static function id( mixed $sent ): int {
		$text = is_int( $sent ) ? (string) $sent : $sent;
		return is_string( $text ) && 1 === preg_match( '/^[1-9][0-9]{0,17}$/', $text ) ? (int) $text : 0;
	}

	/**
	 * Whether a request asks for assembly: true, 1, or the words a form or a query sends for yes.
	 *
	 * @param mixed $sent What was sent.
	 */
	private static function flag( mixed $sent ): bool {
		return in_array( $sent, array( true, 1, '1', 'true', 'yes', 'on' ), true );
	}
}
