<?php
/**
 * A customer's saved builds.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedPrices;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\SavedBuildStore;
use CreativeBranding\SkateboardBuilder\Port\Uuids;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Save, rename, delete and list a customer's builds under My builds (spec: Saved builds; roadmap, milestone 8).
 * Each takes the customer's user ID and touches only their builds: another customer's UUID, or a guessed one, is a
 * build they do not have. A build is kept only as the builder would send it to the cart, with the picks it counts, in
 * step order (D-217).
 *
 * @since 0.8.0
 */
final class SavedBuilds {

	/**
	 * A new build kept.
	 */
	public const string SAVED = 'saved';

	/**
	 * A build the customer had, saved again.
	 */
	public const string UPDATED = 'updated';

	/**
	 * Not a build the cart would take: short of a part, refused by a rule, or of no kit the shop has.
	 */
	public const string NOT_READY = 'not_ready';

	/**
	 * The customer has the most builds they may keep.
	 */
	public const string FULL = 'full';

	/**
	 * No one is signed in.
	 */
	public const string SIGNED_OUT = 'signed_out';

	/**
	 * The table refused the write; it is in WooCommerce's log.
	 *
	 * @since 0.8.0
	 */
	public const string FAILED = 'failed';

	/**
	 * The most builds a customer keeps (D-217), as the store holds it.
	 */
	public const int MOST = SavedBuildStore::MOST;

	/**
	 * The longest name, in characters, as the table's column holds it.
	 */
	public const int NAME = 100;

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param SavedBuildStore $store  The table.
	 * @param BuildState      $builds Checks a build as the builder does.
	 * @param Uuids           $uuids  Names a new build.
	 * @param Clock           $clock  When.
	 * @param Logger          $logger Where a write the table refuses is said.
	 * @param Money|null      $money  The currency prices are shown in, for the prices a build keeps (from 1.0.0).
	 */
	public function __construct( private SavedBuildStore $store, private BuildState $builds, private Uuids $uuids, private Clock $clock, private Logger $logger, private ?Money $money = null ) {}

	/**
	 * Keep a build for a customer. With the UUID of a build of theirs, that build is saved again, its name kept where
	 * none is given; any other UUID is passed over, and the build is a new one.
	 *
	 * @since 0.8.0
	 *
	 * @param int          $user     The customer's user ID, 0 when no one is signed in.
	 * @param int          $kit      The kit product's ID.
	 * @param array<mixed> $picks    The picks as sent: slot key to part ID.
	 * @param bool         $assembly Whether the shop assembles it.
	 * @param string       $name     The name given, which may be empty.
	 * @param string       $uuid     The UUID of the saved build it was opened from, or empty.
	 * @return array{code: string, uuid: string}
	 */
	public function save( int $user, int $kit, array $picks, bool $assembly, string $name, string $uuid = '' ): array {
		if ( $user < 1 ) {
			return self::said( self::SIGNED_OUT );
		}
		$state = $this->counted( $kit, $picks, $assembly );
		if ( null === $state ) {
			return self::said( self::NOT_READY );
		}
		$kept   = array_map( static fn( $part ): int => $part->id, $state->counted() );
		$prices = $this->prices( $state );
		$name   = self::name( $name );
		$now    = $this->clock->now();
		$had    = '' === $uuid ? null : $this->store->find( $user, $uuid );
		if ( null !== $had ) {
			return $this->put( $had->with( $kit, $kept, $assembly, '' === $name ? $had->name : $name, $now, $prices ) ) ? self::said( self::UPDATED, $had->uuid ) : self::said( self::FAILED );
		}
		if ( $this->store->count( $user ) >= self::MOST ) {
			return self::said( self::FULL );
		}
		$build = new SavedBuild( $this->uuids->next(), $user, $kit, $kept, $assembly, $name, $now, $now, $prices );
		return $this->put( $build ) ? self::said( self::SAVED, $build->uuid ) : self::said( self::FAILED );
	}

	/**
	 * Undo: put back a build its customer just deleted, as it was, its UUID, picks and dates the same (Cowork's 0.8.0
	 * review, F3; D-231). It is not checked again: a part gone since stays among its picks, and Open names it, as for any
	 * saved build. Sent twice, it is there already, and nothing more is made. The most a customer keeps still holds.
	 *
	 * @since 0.9.0
	 *
	 * @param SavedBuild $build The build as it was deleted.
	 * @return string SAVED, FULL, FAILED or SIGNED_OUT.
	 */
	public function restore( SavedBuild $build ): string {
		if ( $build->user < 1 ) {
			return self::SIGNED_OUT;
		}
		if ( null !== $this->store->find( $build->user, $build->uuid ) ) {
			return self::SAVED;
		}
		if ( $this->store->count( $build->user ) >= self::MOST ) {
			return self::FULL;
		}
		return $this->put( $build ) ? self::SAVED : self::FAILED;
	}

	/**
	 * One of a customer's builds as it reopens, where anything changed since it was saved: a part gone, sold out or no
	 * longer fitting, or a price (spec: A saved build that has changed, D-254). Null where nothing did, or the build is
	 * not theirs, or its kit does not read.
	 *
	 * @since 1.0.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 */
	public function reopened( int $user, string $uuid ): ?Reopened {
		$build = $this->find( $user, $uuid );
		return null === $build ? null : $this->changed( $build );
	}

	/**
	 * What changed in a build since it was saved, as `reopened()` says it, for a build the caller has already read as
	 * its customer's: My builds' compare reads them together (#136). Null where nothing changed or its kit does not read.
	 *
	 * @since 1.3.0
	 *
	 * @param SavedBuild $build The build, the customer's own.
	 */
	public function changed( SavedBuild $build ): ?Reopened {
		$check = $this->builds->check( $build->kit, $build->picks );
		if ( null === $check ) {
			return null;
		}
		try {
			$state = $this->builds->of( $build->kit, $check->picks, null, State::REVIEW, $build->assembly );
		} catch ( InvalidMoney ) {
			return null;
		}
		if ( null === $state ) {
			return null;
		}
		$currency = null === $this->money ? '' : $this->money->currency();
		$changed  = array_map( static fn( $change ): string => $change->slot, $check->changes );
		$prices   = array();
		foreach ( $state->quote->lines as $line ) {
			$then = in_array( $line->slot, $changed, true ) ? null : $build->prices?->of( $line->slot, $currency );
			if ( null !== $then && $then !== $line->total ) {
				$prices[] = array(
					'slot' => $line->slot,
					'part' => $line->part,
					'then' => $then,
					'now'  => $line->total,
				);
			}
		}
		if ( ! $check->changed() && array() === $prices ) {
			return null;
		}
		return new Reopened( $build, $check, $state, $prices, $build->prices?->total( $currency ) );
	}

	/**
	 * Give one of a customer's builds another name. False when they have none with that UUID.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 * @param string $name The new name; empty leaves it unnamed.
	 */
	public function rename( int $user, string $uuid, string $name ): bool {
		$had = $this->find( $user, $uuid );
		if ( null === $had ) {
			return false;
		}
		return $this->put( $had->with( $had->kit, $had->picks, $had->assembly, self::name( $name ), $this->clock->now() ) );
	}

	/**
	 * Write a build, and say in WooCommerce's log where the table refuses it: by its UUID alone, nothing about the
	 * customer.
	 *
	 * @param SavedBuild $build The build.
	 */
	private function put( SavedBuild $build ): bool {
		if ( $this->store->put( $build ) ) {
			return true;
		}
		$this->logger->log( Logger::ERROR, 'A saved build could not be written.', array( 'uuid' => $build->uuid ) );
		return false;
	}

	/**
	 * Delete one of a customer's builds. False when they have none with that UUID.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 */
	public function delete( int $user, string $uuid ): bool {
		return $user > 0 && $this->store->delete( $user, $uuid );
	}

	/**
	 * One of a customer's builds, or null.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $user The customer's user ID.
	 * @param string $uuid The build's UUID.
	 */
	public function find( int $user, string $uuid ): ?SavedBuild {
		return $user > 0 && '' !== $uuid ? $this->store->find( $user, $uuid ) : null;
	}

	/**
	 * A customer's builds, the last saved first.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 * @return list<SavedBuild>
	 */
	public function of( int $user ): array {
		return $user > 0 ? $this->store->of( $user ) : array();
	}

	/**
	 * Delete every build of a customer's: their account is deleted, or their data erased. How many there were.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 */
	public function forget( int $user ): int {
		return $user > 0 ? $this->store->forget( $user ) : 0;
	}

	/**
	 * The build's state where the builder would send it to the cart, or null when it would send none.
	 *
	 * @param int          $kit      The kit.
	 * @param array<mixed> $picks    The picks as sent.
	 * @param bool         $assembly Assembly.
	 */
	private function counted( int $kit, array $picks, bool $assembly ): ?State {
		try {
			$state = $this->builds->of( $kit, $picks, null, State::REVIEW, $assembly );
		} catch ( InvalidMoney ) {
			// Logged by the state.
			return null;
		}
		if ( null === $state || ! $state->ready() ) {
			return null;
		}
		return $state;
	}

	/**
	 * What the build costs as it is saved, in the currency shown now (D-254). None where no currency is known: an
	 * earlier release's container builds this class without one.
	 *
	 * @param State $state The build's state.
	 */
	private function prices( State $state ): ?SavedPrices {
		if ( null === $this->money ) {
			return null;
		}
		$lines = array();
		foreach ( $state->quote->lines as $line ) {
			$lines[ $line->slot ] = $line->total;
		}
		return new SavedPrices( $this->money->currency(), $lines, $state->quote->total );
	}

	/**
	 * A name, its outer spaces taken off and cut to the column's length.
	 *
	 * @param string $name The name given.
	 */
	private static function name( string $name ): string {
		return mb_substr( trim( $name ), 0, self::NAME );
	}

	/**
	 * What a save did.
	 *
	 * @param string $code What happened.
	 * @param string $uuid The build's UUID, where one was kept.
	 * @return array{code: string, uuid: string}
	 */
	private static function said( string $code, string $uuid = '' ): array {
		return array(
			'code' => $code,
			'uuid' => $uuid,
		);
	}
}
