<?php
/**
 * The saved builds' table, read and written for each row's owner alone.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Port\SavedBuildStore;
use CreativeBranding\SkateboardBuilder\Repository\SavedBuildRepository;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use DateTimeImmutable;
use DateTimeZone;

/**
 * Spec, Security: "Saved builds are read and changed only by the customer who owns them, checked on every request."
 * Roadmap, milestone 8: IDOR tests, with another customer's ID and a guessed UUID (D-217).
 */
final class SavedBuildRepositoryTest extends IntegrationTestCase {

	private SavedBuildStore $store;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::builds() ) );
		$this->store = $this->service( SavedBuildStore::class );
	}

	private static function utc( string $when ): DateTimeImmutable {
		return new DateTimeImmutable( $when, new DateTimeZone( 'UTC' ) );
	}

	private static function build( string $uuid, int $user, string $name, string $when = '2026-11-17 10:00:00' ): SavedBuild {
		return new SavedBuild( $uuid, $user, 12, array( 'deck' => 21, 'trucks' => 22 ), true, $name, self::utc( $when ), self::utc( $when ) );
	}

	public function test_the_container_gives_the_repository(): void {
		self::assertInstanceOf( SavedBuildRepository::class, $this->store );
	}

	public function test_a_build_is_kept_and_read_back_as_it_was_written(): void {
		$this->store->put( self::build( 'a0000000-0000-4000-8000-000000000001', 7, 'Mine & yours "quoted"' ) );
		self::assertEquals( self::build( 'a0000000-0000-4000-8000-000000000001', 7, 'Mine & yours "quoted"' ), $this->store->find( 7, 'a0000000-0000-4000-8000-000000000001' ) );
		self::assertSame( 1, $this->store->count( 7 ) );
	}

	public function test_another_customer_reads_changes_and_deletes_none_of_a_customers_builds(): void {
		$hers = self::build( 'a0000000-0000-4000-8000-000000000002', 7, 'Hers' );
		$this->store->put( $hers );

		self::assertNull( $this->store->find( 8, $hers->uuid ), 'another customer\'s ID' );
		self::assertNull( $this->store->find( 7, 'a0000000-0000-4000-8000-00000000ffff' ), 'a guessed UUID' );
		self::assertSame( array(), $this->store->of( 8 ) );
		self::assertFalse( $this->store->delete( 8, $hers->uuid ) );
		// Written by another customer under her UUID: neither an update of hers nor a row of his.
		$this->store->put( self::build( $hers->uuid, 8, 'Taken' ) );
		self::assertEquals( $hers, $this->store->find( 7, $hers->uuid ) );
		self::assertSame( 0, $this->store->count( 8 ) );
		self::assertSame( 0, $this->store->forget( 8 ) );
		self::assertSame( 1, $this->store->count( 7 ) );
	}

	public function test_a_customers_builds_come_the_last_saved_first_and_go_together(): void {
		$this->store->put( self::build( 'a0000000-0000-4000-8000-000000000003', 7, 'Old', '2026-11-01 08:00:00' ) );
		$this->store->put( self::build( 'a0000000-0000-4000-8000-000000000004', 7, 'New', '2026-11-16 08:00:00' ) );
		$this->store->put( self::build( 'a0000000-0000-4000-8000-000000000005', 8, 'His' ) );
		self::assertSame( array( 'New', 'Old' ), array_map( static fn( SavedBuild $build ): string => $build->name, $this->store->of( 7 ) ) );

		$renamed = self::build( 'a0000000-0000-4000-8000-000000000003', 7, 'Renamed', '2026-11-01 08:00:00' )->with( 12, array( 'deck' => 23 ), false, 'Renamed', self::utc( '2026-11-17 09:00:00' ) );
		$this->store->put( $renamed );
		self::assertEquals( $renamed, $this->store->find( 7, $renamed->uuid ) );
		self::assertSame( array( 'Renamed', 'New' ), array_map( static fn( SavedBuild $build ): string => $build->name, $this->store->of( 7 ) ) );
		$this->store->put( $renamed );
		self::assertSame( 2, $this->store->count( 7 ), 'written again unchanged: no second row' );

		self::assertTrue( $this->store->delete( 7, $renamed->uuid ) );
		self::assertSame( 1, $this->store->forget( 7 ) );
		self::assertSame( 0, $this->store->count( 7 ) );
		self::assertSame( 1, $this->store->count( 8 ) );
	}
}
