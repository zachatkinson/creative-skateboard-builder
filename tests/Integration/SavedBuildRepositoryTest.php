<?php
/**
 * The saved builds' table, read and written for each row's owner alone.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedPrices;
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
		self::assertTrue( $this->store->put( self::build( 'a0000000-0000-4000-8000-000000000001', 7, 'Mine & yours "quoted"' ) ) );
		self::assertEquals( self::build( 'a0000000-0000-4000-8000-000000000001', 7, 'Mine & yours "quoted"' ), $this->store->find( 7, 'a0000000-0000-4000-8000-000000000001' ) );
		self::assertSame( 1, $this->store->count( 7 ) );
	}

	// Spec (configurator): A saved build that has changed (D-254), "in the saved build's picks document (no new column)".
	public function test_a_builds_prices_are_kept_beside_its_picks_and_a_build_saved_before_has_none(): void {
		$uuid   = 'a0000000-0000-4000-8000-000000000009';
		$prices = new SavedPrices( 'CAD', array( 'deck' => 6000, 'trucks' => 5000 ), 11000 );
		$build  = new SavedBuild( $uuid, 7, 12, array( 'deck' => 21, 'trucks' => 22 ), true, '', self::utc( '2026-11-17 10:00:00' ), self::utc( '2026-11-17 10:00:00' ), $prices );
		self::assertTrue( $this->store->put( $build ) );
		self::assertEquals( $build, $this->store->find( 7, $uuid ) );
		global $wpdb;
		$stored = json_decode( (string) $wpdb->get_var( $wpdb->prepare( 'SELECT picks FROM %i WHERE uuid = %s', Tables::builds(), $uuid ) ), true );
		self::assertSame( array( 'deck' => 21, 'trucks' => 22, '_prices' => array( 'currency' => 'CAD', 'lines' => array( 'deck' => 6000, 'trucks' => 5000 ), 'total' => 11000 ) ), $stored, 'one document, which 0.9.0 reads as its picks alone' );

		$wpdb->query( $wpdb->prepare( 'UPDATE %i SET picks = %s WHERE uuid = %s', Tables::builds(), '{"deck":21,"_prices":{"currency":"CAD","lines":{"deck":"60"},"total":6000}}', $uuid ) );
		self::assertNull( $this->store->find( 7, $uuid )?->prices, 'prices that do not read are none' );
		self::assertNull( $this->store->find( 7, 'a0000000-0000-4000-8000-000000000001' ), 'and a build saved before 1.0.0 has none' );
	}

	public function test_another_customer_reads_changes_and_deletes_none_of_a_customers_builds(): void {
		$hers = self::build( 'a0000000-0000-4000-8000-000000000002', 7, 'Hers' );
		$this->store->put( $hers );

		self::assertNull( $this->store->find( 8, $hers->uuid ), 'another customer\'s ID' );
		self::assertNull( $this->store->find( 7, 'a0000000-0000-4000-8000-00000000ffff' ), 'a guessed UUID' );
		self::assertSame( array(), $this->store->of( 8 ) );
		self::assertFalse( $this->store->delete( 8, $hers->uuid ) );
		// Written by another customer under her UUID: neither an update of hers nor a row of his.
		self::assertFalse( $this->store->put( self::build( $hers->uuid, 8, 'Taken' ) ), 'nothing written is said' );
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
		self::assertTrue( $this->store->put( $renamed ), 'written again unchanged: it holds it' );
		self::assertSame( 2, $this->store->count( 7 ), 'written again unchanged: no second row' );

		self::assertTrue( $this->store->delete( 7, $renamed->uuid ) );
		self::assertSame( 1, $this->store->forget( 7 ) );
		self::assertSame( 0, $this->store->count( 7 ) );
		self::assertSame( 1, $this->store->count( 8 ) );
	}

	// The M9 audit's minor 10: a customer's builds are read to the most kept, and a new one past it is refused by the
	// insert itself, so two saves at once can't both pass the count. One already kept is still written again.
	public function test_a_customer_keeps_the_most_and_no_more_whatever_the_count_said(): void {
		for ( $n = 1; $n <= SavedBuildStore::MOST; $n++ ) {
			self::assertTrue( $this->store->put( self::build( sprintf( '00000000-0000-4000-8000-%012d', $n ), 7, 'Build ' . $n ) ) );
		}
		self::assertFalse( $this->store->put( self::build( '00000000-0000-4000-8000-999999999999', 7, 'One more' ) ) );
		self::assertSame( SavedBuildStore::MOST, $this->store->count( 7 ) );
		self::assertCount( SavedBuildStore::MOST, $this->store->of( 7 ) );
		self::assertTrue( $this->store->put( self::build( sprintf( '00000000-0000-4000-8000-%012d', 1 ), 7, 'Renamed' ) ), 'one already kept' );
		self::assertTrue( $this->store->put( self::build( '00000000-0000-4000-8000-999999999999', 8, 'Another customer' ) ) );

		global $wpdb;
		$this->store->of( 7 );
		self::assertStringContainsString( 'LIMIT ' . SavedBuildStore::MOST, (string) $wpdb->last_query );
	}

	public function test_a_write_the_database_refuses_is_said(): void {
		global $wpdb;
		$build = self::build( 'a0000000-0000-4000-8000-000000000009', 7, 'Lost' );
		$fail  = static fn( string $query ): string => str_contains( $query, Tables::builds() ) && 1 === preg_match( '/^\s*(UPDATE|INSERT)/', $query ) ? 'SELECT * FROM cbuild_no_such_table' : $query;
		add_filter( 'query', $fail );
		$shown = $wpdb->show_errors( false );
		try {
			self::assertFalse( $this->store->put( $build ) );
		} finally {
			$wpdb->show_errors( $shown );
			remove_filter( 'query', $fail );
		}
		self::assertNull( $this->store->find( 7, $build->uuid ) );
	}
}
