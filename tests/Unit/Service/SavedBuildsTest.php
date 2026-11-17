<?php
/**
 * Saved builds: kept, renamed, deleted and listed for their owner alone.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\CountingUuids;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySavedBuilds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Spec: Saved builds under My builds (roadmap, milestone 8): "save, rename, delete and open, with nonces; owner-only;
 * re-checked on opening". The service checks the build and the owner; the hooks check the nonce.
 */
final class SavedBuildsTest extends TestCase {

	private const int KIT = 900;

	private const int ANA = 7;

	private const int BEN = 8;

	private const array COMPLETE = array(
		SlotKey::DECK     => 103,
		SlotKey::TRUCKS   => 149,
		SlotKey::WHEELS   => 302,
		SlotKey::BEARINGS => 501,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

	private InMemorySavedBuilds $store;

	private FakeClock $clock;

	private SavedBuilds $builds;

	protected function setUp(): void {
		$kits = new InMemoryKits();
		$kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$this->store  = new InMemorySavedBuilds();
		$this->clock  = new FakeClock();
		$this->builds = new SavedBuilds( $this->store, new BuildState( $kits, new InMemoryShopSettings(), new FakeLogger() ), new CountingUuids(), $this->clock );
	}

	public function test_a_complete_build_is_kept_with_its_picks_in_step_order_and_its_name(): void {
		$picks = array_reverse( self::COMPLETE, true ) + array( 'nothing' => 5 );
		$saved = $this->builds->save( self::ANA, self::KIT, $picks, true, '  My street board  ' );
		self::assertSame( SavedBuilds::SAVED, $saved['code'] );
		$build = $this->builds->find( self::ANA, $saved['uuid'] );
		self::assertNotNull( $build );
		self::assertSame( self::COMPLETE, $build->picks, 'the picks the build counts, in step order, and nothing else sent' );
		self::assertSame( array( self::KIT, true, 'My street board', self::ANA ), array( $build->kit, $build->assembly, $build->name, $build->user ) );
		self::assertEquals( $this->clock->now(), $build->created );
		self::assertEquals( $this->clock->now(), $build->updated );
	}

	public function test_a_build_short_of_a_part_or_of_no_kit_or_from_no_one_is_not_kept(): void {
		$short = self::COMPLETE;
		unset( $short[ SlotKey::GRIP ] );
		self::assertSame( SavedBuilds::NOT_READY, $this->builds->save( self::ANA, self::KIT, $short, true, '' )['code'] );
		self::assertSame( SavedBuilds::NOT_READY, $this->builds->save( self::ANA, 404, self::COMPLETE, true, '' )['code'] );
		self::assertSame( SavedBuilds::SIGNED_OUT, $this->builds->save( 0, self::KIT, self::COMPLETE, true, '' )['code'] );
		self::assertSame( array(), $this->store->rows );
	}

	public function test_a_name_is_cut_to_a_hundred_characters(): void {
		$saved = $this->builds->save( self::ANA, self::KIT, self::COMPLETE, false, str_repeat( 'é', 120 ) );
		self::assertSame( str_repeat( 'é', 100 ), $this->builds->find( self::ANA, $saved['uuid'] )?->name );
	}

	public function test_saving_a_build_opened_from_my_builds_updates_it_and_keeps_when_it_was_first_saved(): void {
		$first = $this->builds->save( self::ANA, self::KIT, self::COMPLETE, true, 'First' );
		$this->clock->advance( 60 );
		$other = array( SlotKey::WHEELS => 303 ) + self::COMPLETE;
		$again = $this->builds->save( self::ANA, self::KIT, $other, false, '', $first['uuid'] );
		self::assertSame( array( SavedBuilds::UPDATED, $first['uuid'] ), array( $again['code'], $again['uuid'] ) );
		$build = $this->builds->find( self::ANA, $first['uuid'] );
		self::assertSame( 303, $build?->picks[ SlotKey::WHEELS ] );
		self::assertSame( 'First', $build?->name, 'an empty name keeps the one it had' );
		self::assertFalse( $build?->assembly );
		self::assertSame( 60, ( $build?->updated->getTimestamp() ?? 0 ) - ( $build?->created->getTimestamp() ?? 0 ) );
		self::assertCount( 1, $this->store->rows );
	}

	public function test_another_customers_uuid_is_never_updated_renamed_or_deleted_and_a_save_under_it_is_a_new_build(): void {
		// IDOR (roadmap, milestone 8): another customer's UUID, and a guessed one.
		$hers = $this->builds->save( self::ANA, self::KIT, self::COMPLETE, true, 'Ana\'s' );
		$his  = $this->builds->save( self::BEN, self::KIT, self::COMPLETE, false, 'Ben\'s', $hers['uuid'] );
		self::assertSame( SavedBuilds::SAVED, $his['code'] );
		self::assertNotSame( $hers['uuid'], $his['uuid'] );
		self::assertFalse( $this->builds->rename( self::BEN, $hers['uuid'], 'Taken' ) );
		self::assertFalse( $this->builds->delete( self::BEN, $hers['uuid'] ) );
		self::assertNull( $this->builds->find( self::BEN, $hers['uuid'] ) );
		self::assertFalse( $this->builds->rename( self::BEN, '00000000-0000-4000-8000-000000000000', 'Guessed' ) );
		self::assertSame( 'Ana\'s', $this->builds->find( self::ANA, $hers['uuid'] )?->name );
		self::assertSame( array( $his['uuid'] ), array_map( static fn( $build ): string => $build->uuid, $this->builds->of( self::BEN ) ) );
	}

	public function test_a_build_is_renamed_and_deleted_by_its_owner(): void {
		$saved = $this->builds->save( self::ANA, self::KIT, self::COMPLETE, true, 'Old' );
		$this->clock->advance( 5 );
		self::assertTrue( $this->builds->rename( self::ANA, $saved['uuid'], ' New ' ) );
		self::assertSame( 'New', $this->builds->find( self::ANA, $saved['uuid'] )?->name );
		self::assertSame( 5, ( $this->builds->find( self::ANA, $saved['uuid'] )?->updated->getTimestamp() ?? 0 ) - ( $this->builds->find( self::ANA, $saved['uuid'] )?->created->getTimestamp() ?? 0 ) );
		self::assertTrue( $this->builds->delete( self::ANA, $saved['uuid'] ) );
		self::assertFalse( $this->builds->delete( self::ANA, $saved['uuid'] ), 'gone already' );
		self::assertSame( array(), $this->builds->of( self::ANA ) );
	}

	public function test_a_customer_keeps_a_hundred_builds_at_most_and_may_still_update_one(): void {
		for ( $i = 0; $i < SavedBuilds::MOST; $i++ ) {
			$last = $this->builds->save( self::ANA, self::KIT, self::COMPLETE, true, (string) $i );
		}
		self::assertSame( SavedBuilds::FULL, $this->builds->save( self::ANA, self::KIT, self::COMPLETE, true, 'one more' )['code'] );
		self::assertSame( SavedBuilds::UPDATED, $this->builds->save( self::ANA, self::KIT, self::COMPLETE, true, 'changed', $last['uuid'] ?? '' )['code'] );
		self::assertSame( SavedBuilds::SAVED, $this->builds->save( self::BEN, self::KIT, self::COMPLETE, true, '' )['code'], 'each customer counts their own' );
		self::assertSame( 100, $this->builds->forget( self::ANA ) );
		self::assertSame( array(), $this->builds->of( self::ANA ) );
		self::assertCount( 1, $this->builds->of( self::BEN ) );
	}
}
