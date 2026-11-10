<?php
/**
 * The spec tables, on real posts and products.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\TableSpecSource;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;
use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\SeedSpecTables;
use CreativeBranding\SkateboardBuilder\Install\Migrations\SpecTablesMigration;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Roadmap, milestone 3: the seeded spec tables, a part's specs from the chart it names, and a table's change
 * re-indexing the parts. Audit, SEO: the post type is nobody's page.
 */
final class SpecTablesTest extends IntegrationTestCase {

	public function tear_down(): void {
		delete_option( Options::REINDEX );
		parent::tear_down();
	}

	/**
	 * A table of two sizes.
	 *
	 * @param string $status The post's status once made.
	 */
	private function table( string $status = 'publish' ): int {
		$id = $this->service( SpecTables::class )->create(
			'Test trucks',
			SpecTable::read(
				array(
					array( 'size' => '149', 'specs' => array( 'axle_width' => '8.5000', 'deck_min' => '8.3750', 'deck_max' => '8.6000' ), 'source' => 'A chart', 'read_on' => '2026-11-10' ),
					array( 'size' => '159', 'specs' => array( 'axle_width' => '8.7500', 'baseplate' => 'both', 'max_wheel' => 'big' ) ),
				)
			)
		);
		if ( 'publish' !== $status ) {
			wp_update_post( array( 'ID' => $id, 'post_status' => $status ) );
		}
		return $id;
	}

	/**
	 * A truck that names this table and size.
	 *
	 * @param mixed $named The meta's value, or null for none.
	 */
	private static function truck( mixed $named = null ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Truck' );
		$product->set_regular_price( '30' );
		if ( null !== $named ) {
			$product->update_meta_data( Options::META_SPEC_TABLE, $named );
		}
		$product->save();
		return $product;
	}

	/**
	 * A reading as key=text@ref, an unread value as !reason:text@ref.
	 *
	 * @param list<Reading|Unread> $read What a source read for one part.
	 * @return list<string>
	 */
	private static function said( array $read ): array {
		return array_map(
			static fn( Reading|Unread $one ): string => $one instanceof Reading
				? $one->key->value . '=' . $one->text . '@' . $one->ref
				: '!' . $one->reason . ':' . $one->text . '@' . $one->ref,
			$read
		);
	}

	public function test_the_post_type_is_the_shops_record_and_nobodys_page(): void {
		$type = get_post_type_object( Options::POST_TYPE_SPEC_TABLE );
		self::assertNotNull( $type );
		self::assertFalse( $type->public );
		self::assertFalse( $type->publicly_queryable );
		self::assertTrue( $type->exclude_from_search );
		self::assertFalse( $type->show_in_rest );
		self::assertFalse( $type->show_in_nav_menus );
		self::assertFalse( $type->has_archive );
		self::assertFalse( $type->rewrite );
		self::assertFalse( $type->query_var );
		self::assertNotContains( Options::POST_TYPE_SPEC_TABLE, get_post_types( array( 'public' => true ) ) );
		self::assertNotContains( Options::POST_TYPE_SPEC_TABLE, get_post_types( array( 'exclude_from_search' => false ) ) );
		$provider = wp_sitemaps_get_server()->registry->get_provider( 'posts' );
		self::assertNotNull( $provider );
		self::assertArrayNotHasKey( Options::POST_TYPE_SPEC_TABLE, $provider->get_object_subtypes() );
		self::assertArrayHasKey( 'page', $provider->get_object_subtypes(), 'the check reads the sitemap\'s own list' );

		$id = $this->table();
		$this->go_to( '/?p=' . $id );
		self::assertTrue( is_404(), 'a table has no page' );
		// go_to() hands its query to WordPress as trusted, so the visitor's request is parsed as WordPress parses it
		// (wp-includes/class-wp.php:376 drops a post type that is not publicly queryable).
		$_GET['post_type'] = Options::POST_TYPE_SPEC_TABLE;
		try {
			$GLOBALS['wp']->parse_request();
		} finally {
			unset( $_GET['post_type'] );
		}
		self::assertArrayNotHasKey( 'post_type', $GLOBALS['wp']->query_vars, 'and no archive' );
		$this->go_to( '/?s=Test+trucks' );
		self::assertNotContains( $id, wp_list_pluck( $GLOBALS['wp_query']->posts, 'ID' ), 'and search does not find it' );
	}

	public function test_only_who_manages_woocommerce_may_touch_a_table(): void {
		$id = $this->table();
		foreach ( array( 'edit_posts', 'create_posts', 'publish_posts', 'delete_posts', 'edit_others_posts', 'edit_published_posts', 'delete_published_posts', 'read_private_posts' ) as $cap ) {
			self::assertSame( 'manage_woocommerce', get_post_type_object( Options::POST_TYPE_SPEC_TABLE )->cap->$cap, $cap );
		}
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		self::assertTrue( current_user_can( 'edit_post', $id ) );
		self::assertTrue( current_user_can( 'delete_post', $id ) );
		// An editor edits others' posts and pages, and not these.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'editor' ) ) );
		self::assertTrue( current_user_can( 'edit_others_posts' ) );
		self::assertFalse( current_user_can( 'edit_post', $id ) );
		self::assertFalse( current_user_can( 'delete_post', $id ) );
		self::assertFalse( current_user_can( 'read_post', $id ) );
	}

	public function test_the_migration_makes_the_three_charts_once_and_leaves_what_the_shop_changed(): void {
		$migration = $this->service( SpecTablesMigration::class );
		self::assertTrue( $migration->run( Cursor::start() )->done );
		$made = static fn(): array => get_posts(
			array(
				'post_type'      => Options::POST_TYPE_SPEC_TABLE,
				'post_status'    => array( 'publish', 'draft', 'trash' ),
				'posts_per_page' => -1,
				'orderby'        => 'ID',
				'order'          => 'ASC',
			)
		);
		self::assertSame( array( 'Independent', 'Thunder', 'Venture' ), wp_list_pluck( $made(), 'post_title' ) );
		self::assertSame( array( 'publish', 'publish', 'publish' ), wp_list_pluck( $made(), 'post_status' ) );
		[ $indy, $thunder, $venture ] = wp_list_pluck( $made(), 'ID' );
		self::assertSame( 'thunder', get_post_meta( $thunder, Options::META_TABLE_SEED, true ) );
		self::assertSame( SeedSpecTables::all()['venture']['table']->toArray(), get_post_meta( $venture, Options::META_TABLE_ROWS, true ), 'stored as the document HOOKS.md gives' );
		self::assertEquals( SeedSpecTables::all()['independent']['table'], $this->service( SpecTables::class )->find( $indy )[ $indy ] );

		// The shop corrects one chart, bins another and deletes the third for good.
		$corrected = array( array( 'size' => '149', 'specs' => array( 'axle_width' => '8.4900' ), 'source' => 'Measured', 'read_on' => '2026-11-11' ) );
		update_post_meta( $indy, Options::META_TABLE_ROWS, $corrected );
		wp_update_post( array( 'ID' => $indy, 'post_title' => 'Indy, measured' ) );
		wp_trash_post( $thunder );
		wp_delete_post( $venture, true );

		self::assertTrue( $migration->run( Cursor::start() )->done );
		self::assertSame( array( 'Indy, measured', 'Thunder', 'Venture' ), wp_list_pluck( $made(), 'post_title' ), 'the one deleted for good is made again; this migration runs once on a shop, and again only if cut off' );
		self::assertSame( $corrected, get_post_meta( $indy, Options::META_TABLE_ROWS, true ) );
		self::assertSame( 'trash', get_post_status( $thunder ), 'a binned chart is not made a second time' );
		self::assertTrue( $migration->run( Cursor::start() )->done );
		self::assertCount( 3, $made() );
	}

	public function test_only_a_published_table_is_in_use(): void {
		$tables = $this->service( SpecTables::class );
		$live   = $this->table();
		$draft  = $this->table( 'draft' );
		$binned = $this->table();
		wp_trash_post( $binned );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		update_post_meta( $page, Options::META_TABLE_ROWS, array( array( 'size' => '1' ) ) );

		self::assertSame( array( $live ), array_keys( $tables->find( $live, $draft, $binned, $page, 999999, 0, -3, $live ) ) );
		self::assertSame( array(), $tables->find() );
		self::assertFalse( $tables->hasSeed( 'independent' ) );
		$seeded = $tables->create( 'Seeded', SpecTable::read( array() ), 'independent' );
		self::assertTrue( $tables->hasSeed( 'independent' ) );
		self::assertFalse( $tables->hasSeed( 'thunder' ) );
		wp_trash_post( $seeded );
		self::assertTrue( $tables->hasSeed( 'independent' ), 'in the bin, it is still the shop\'s' );
		self::assertSame( '', get_post_meta( $live, Options::META_TABLE_SEED, true ), 'a table the shop made carries no seed' );
	}

	public function test_a_part_takes_the_values_of_the_table_and_size_it_names(): void {
		$table  = $this->table();
		$truck  = self::truck( array( 'table' => $table, 'size' => '149' ) );
		$other  = self::truck( array( 'table' => $table, 'size' => ' 159 ' ) );
		$none   = self::truck();
		$junk   = self::truck( 'Independent 149' );
		$source = $this->service( TableSpecSource::class );
		$read   = $source->read( $truck->get_id(), $other->get_id(), $none->get_id(), $junk->get_id(), 999999 );

		self::assertSame(
			array( 'axle_width=8.5000@' . $table . ':149', 'deck_min=8.3750@' . $table . ':149', 'deck_max=8.6000@' . $table . ':149' ),
			self::said( $read[ $truck->get_id() ] )
		);
		self::assertSame( Origin::Table, $read[ $truck->get_id() ][0]->origin );
		self::assertSame(
			array( 'axle_width=8.7500@' . $table . ':159', '!choice_unknown:big@' . $table . ':159', 'baseplate=both@' . $table . ':159' ),
			array_map( static fn( string $said ): string => (string) preg_replace( '/^!\w+:/', '!choice_unknown:', $said ), self::said( $read[ $other->get_id() ] ) ),
			'a value the chart holds and the spec cannot read is reported, and the rest read'
		);
		self::assertSame( array(), $read[ $none->get_id() ] );
		self::assertSame( array(), $read[ $junk->get_id() ] );
		self::assertSame( array(), $read[999999] );
	}

	public function test_a_table_or_a_size_that_is_not_there_is_reported(): void {
		$table   = $this->table();
		$draft   = $this->table( 'draft' );
		$source  = $this->service( TableSpecSource::class );
		$gone    = self::truck( array( 'table' => 999999, 'size' => '149' ) );
		$unused  = self::truck( array( 'table' => $draft, 'size' => '149' ) );
		$size    = self::truck( array( 'table' => $table, 'size' => '169' ) );
		$nosize  = self::truck( array( 'table' => $table ) );
		$notable = self::truck( array( 'size' => '149' ) );
		$text    = self::truck( array( 'table' => (string) $table, 'size' => 149 ) );
		$read    = $source->read( $gone->get_id(), $unused->get_id(), $size->get_id(), $nosize->get_id(), $notable->get_id(), $text->get_id() );

		self::assertSame( array( '!table_missing:999999@999999:149' ), self::said( $read[ $gone->get_id() ] ) );
		self::assertSame( array( '!table_missing:' . $draft . '@' . $draft . ':149' ), self::said( $read[ $unused->get_id() ] ) );
		self::assertSame( array( '!size_missing:169@' . $table . ':169' ), self::said( $read[ $size->get_id() ] ) );
		self::assertSame( array( '!size_missing:@' . $table . ':' ), self::said( $read[ $nosize->get_id() ] ) );
		self::assertSame( array( '!table_missing:0@0:149' ), self::said( $read[ $notable->get_id() ] ) );
		self::assertSame( array(), self::said( $read[ $text->get_id() ] ), 'the ID is a number and the size text, as the panel will save them' );
		self::assertSame( Origin::Table, $read[ $gone->get_id() ][0]->origin );
		self::assertSame( '', $read[ $gone->get_id() ][0]->key );
	}

	public function test_a_variation_left_blank_takes_its_parents_table_or_size(): void {
		$table  = $this->table();
		$second = $this->service( SpecTables::class )->create( 'Other trucks', SpecTable::read( array( array( 'size' => '149', 'specs' => array( 'axle_width' => '8.4000' ) ) ) ) );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Trucks' );
		$parent->update_meta_data( Options::META_SPEC_TABLE, array( 'table' => $table, 'size' => '159' ) );
		$parent->save();
		$ids = array();
		foreach ( array( array( 'size' => '149' ), null, array( 'table' => $second, 'size' => '149' ), array( 'table' => $second ) ) as $named ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_regular_price( '30' );
			if ( null !== $named ) {
				$variation->update_meta_data( Options::META_SPEC_TABLE, $named );
			}
			$variation->save();
			$ids[] = $variation->get_id();
		}
		$read = $this->service( TableSpecSource::class )->read( ...$ids );
		self::assertSame( 'axle_width=8.5000@' . $table . ':149', self::said( $read[ $ids[0] ] )[0], 'its own size of the parent\'s chart' );
		self::assertSame( 'axle_width=8.7500@' . $table . ':159', self::said( $read[ $ids[1] ] )[0], 'both from the parent' );
		self::assertSame( array( 'axle_width=8.4000@' . $second . ':149' ), self::said( $read[ $ids[2] ] ), 'both its own' );
		self::assertSame( array( '!size_missing:159@' . $second . ':159' ), self::said( $read[ $ids[3] ] ), 'its own chart has no row for the parent\'s size' );
	}

	public function test_the_chart_wins_over_the_field_by_default_and_the_two_are_shown_to_disagree(): void {
		$table = $this->table();
		$truck = self::truck( array( 'table' => $table, 'size' => '149' ) );
		$truck->update_meta_data( Options::META_SPECS, array( 'axle_width' => '8.4000', 'truck_height' => '53.5000' ) );
		$truck->save();
		$indexer = $this->service( SpecIndexer::class );
		$indexer->index( $truck->get_id() );

		$rows = array();
		foreach ( $this->service( SpecIndex::class )->rowsOf( $truck->get_id() ) as $row ) {
			$rows[ $row->key->value ] = $row->text . ' ' . $row->origin->value . ' ' . $row->ref;
		}
		ksort( $rows );
		self::assertSame(
			array(
				'axle_width'   => '8.5000 table ' . $table . ':149',
				'deck_max'     => '8.6000 table ' . $table . ':149',
				'deck_min'     => '8.3750 table ' . $table . ':149',
				'truck_height' => '53.5000 field ',
			),
			$rows
		);
		$sourced = $indexer->resolve( $truck->get_id() )[ $truck->get_id() ];
		self::assertCount( 1, $sourced->resolution->disagreements );
		self::assertSame( 'table', $sourced->resolution->disagreements[0]->kept->origin->value );
		self::assertSame( '8.4000', $sourced->resolution->disagreements[0]->others[0]->text );
	}

	public function test_a_change_to_a_table_reindexes_the_parts_that_name_it(): void {
		$jobs  = $this->service( IndexJobs::class );
		$table = $this->table();
		self::assertSame( 1, $jobs->state()?->run, 'making a table asked for a reindex' );
		$truck = self::truck( array( 'table' => $table, 'size' => '149' ) );
		self::work();
		$index = $this->service( SpecIndex::class );
		$width = static fn(): string => ( $index->specsOf( $truck->get_id() )[ $truck->get_id() ] ?? null )?->toIndex()['axle_width'] ?? 'none';
		self::assertSame( '8.5000', $width() );
		self::assertSame( ReindexState::DONE, $jobs->state()?->phase );

		// The shop corrects the row, as the table's screen will: the meta, then the post.
		update_post_meta( $table, Options::META_TABLE_ROWS, array( array( 'size' => '149', 'specs' => array( 'axle_width' => '8.4900' ) ) ) );
		wp_update_post( array( 'ID' => $table ) );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase );
		self::assertSame( '8.5000', $width(), 'nothing is indexed in the request that saves' );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		self::work();
		self::assertSame( '8.4900', $width() );

		wp_trash_post( $table );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase, 'binned' );
		self::work();
		self::assertSame( 'none', $width(), 'a binned chart feeds nothing' );
		wp_untrash_post( $table );
		wp_publish_post( $table );
		self::work();
		self::assertSame( '8.4900', $width(), 'restored and published' );

		wp_delete_post( $table, true );
		self::assertSame( ReindexState::ROWS, $jobs->state()?->phase, 'deleted for good' );
		self::work();
		self::assertSame( 'none', $width() );
	}

	public function test_another_posts_save_or_an_editor_opening_asks_for_nothing(): void {
		$jobs = $this->service( IndexJobs::class );
		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		wp_update_post( array( 'ID' => $page, 'post_title' => 'About' ) );
		wp_delete_post( $page, true );
		self::truck();
		$this->service( Queue::class )->cancelAll( Options::JOB_INDEX_PART );
		self::assertNull( $jobs->state() );

		$draft = wp_insert_post( array( 'post_type' => Options::POST_TYPE_SPEC_TABLE, 'post_status' => 'auto-draft', 'post_title' => 'Auto Draft' ) );
		self::assertIsInt( $draft );
		self::assertNull( $jobs->state(), 'an auto-draft is the editor opening' );
		wp_update_post( array( 'ID' => $draft, 'post_status' => 'draft', 'post_title' => 'Ace' ) );
		self::assertNotNull( $jobs->state(), 'saved by a person, it may be one a part names' );
	}
}
