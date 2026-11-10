<?php
/**
 * The specs sheet, read: shown first, written once confirmed.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\SpecExport;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryImportStore;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartSpecs;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecTables;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Spec, Admin, Import and export: a row per part, keyed on the product or variation ID, or on the SKU. An import
 * first shows what it would change and any rows it can't read, and writes nothing until confirmed.
 */
final class SpecImportTest extends TestCase {

	private InMemoryPartSpecs $parts;

	private InMemorySpecTables $tables;

	private InMemoryImportStore $store;

	private FakeQueue $queue;

	private FakeClock $clock;

	private FakeLogger $logger;

	private SpecImport $import;

	private int $chart;

	protected function setUp(): void {
		$this->parts  = new InMemoryPartSpecs();
		$this->tables = new InMemorySpecTables();
		$this->store  = new InMemoryImportStore();
		$this->queue  = new FakeQueue();
		$this->clock  = new FakeClock();
		$this->logger = new FakeLogger();
		$this->import = new SpecImport( $this->parts, $this->tables, new SpecWriter( $this->tables ), $this->store, $this->queue, $this->clock, $this->logger );
		$this->chart  = $this->tables->create(
			'Independent',
			SpecTable::read(
				array(
					array(
						'size'  => '149',
						'specs' => array( 'axle_width' => '8.5' ),
					),
				)
			)
		);
		$this->parts->add( 7, 'DK-7750', array( 'deck_width' => '7.7500' ), 0, '', 'Katie 7.75' );
		$this->parts->add( 9, 'TR-149', array(), 0, '', 'Stage 11' );
	}

	/**
	 * The waiting import's plan, whole.
	 *
	 * @return list<array<string, mixed>>
	 */
	private function plan(): array {
		$state = $this->import->state();
		self::assertNotNull( $state );
		return $this->import->plan( $state, 0, $state->total );
	}

	/**
	 * Problems as column:code.
	 *
	 * @param array<mixed> $row A row of the plan.
	 * @return list<string>
	 */
	private static function codes( array $row ): array {
		return array_map( static fn( array $problem ): string => $problem['key'] . ':' . $problem['code'], $row['problems'] );
	}

	/**
	 * Confirm the waiting import and run its job until none is queued.
	 */
	private function confirmAndRun(): ImportState {
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertTrue( $this->import->confirm( $state->token ) );
		do {
			$this->queue->plain = array();
			$this->import->run();
		} while ( array() !== $this->queue->plain );
		$after = $this->import->state();
		self::assertNotNull( $after );
		return $after;
	}

	public function test_the_job_has_the_name_the_plugin_lists(): void {
		self::assertSame( Options::JOB_IMPORT, SpecImport::JOB );
		self::assertContains( SpecImport::JOB, Options::jobs() );
		self::assertContains( Options::IMPORT, Options::names() );
	}

	public function test_reading_a_file_writes_nothing_and_shows_what_it_would_change(): void {
		self::assertSame( array(), $this->import->read( "id,deck_width,wheelbase\n7,8.25,14 1/4\n9,,\n" ) );

		self::assertSame( array(), $this->parts->written );
		self::assertSame( array(), $this->queue->calls );
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertSame( ImportState::WAITING, $state->status );
		self::assertSame( 2, $state->total );
		self::assertSame( $this->clock->timestamp(), $state->at );
		self::assertSame( array( 0, 0, 0, 0, array() ), array( $state->cursor, $state->changed, $state->same, $state->failures, $state->refused ) );
		self::assertSame( array( 0, 0, 0, 0, 0 ), array( $this->store->state['cursor'] ?? null, $this->store->state['changed'] ?? null, $this->store->state['same'] ?? null, $this->store->state['failures'] ?? null, $this->store->state['by'] ?? null ) );
		self::assertSame( hash( 'sha256', $this->clock->timestamp() . "|id,deck_width,wheelbase\n7,8.25,14 1/4\n9,,\n" ), $state->token );

		$plan = $this->plan();
		self::assertSame( array( array( 7, 9 ) ), $this->parts->reads );
		self::assertSame( array( 2, 3 ), array_column( $plan, 'row' ) );
		self::assertSame( array( 7, 9 ), array_column( $plan, 'part' ) );
		self::assertSame( 'Katie 7.75', $plan[0]['name'] );
		self::assertSame( 'DK-7750', $plan[0]['sku'] );
		self::assertSame(
			array(
				array(
					'key'  => 'deck_width',
					'from' => '7.7500',
					'to'   => '8.2500',
				),
				array(
					'key'  => 'wheelbase',
					'from' => '',
					'to'   => '14.2500',
				),
			),
			$plan[0]['changes']
		);
		self::assertSame( array(), $plan[0]['problems'] );
		self::assertSame( array(), $plan[1]['changes'] );
		self::assertSame( array(), $plan[1]['problems'] );
	}

	public function test_confirmed_it_writes_each_part_that_changes_in_the_background(): void {
		$this->import->read( "id,deck_width\n7,8.25\n9,\n" );
		$after = $this->confirmAndRun();

		self::assertSame( array( 7 ), $this->parts->written );
		self::assertSame( array( 'deck_width' => '8.2500' ), $this->parts->held[7]['fields'] );
		self::assertSame( ImportState::DONE, $after->status );
		self::assertSame( 1, $after->changed );
		self::assertSame( 1, $after->same );
		self::assertSame( array(), $after->refused );
		self::assertSame( array(), $after->rows );
		self::assertSame( 2, $after->total );
		self::assertSame( array( SpecImport::JOB => 1 ), $this->queue->calls );
	}

	public function test_a_column_left_out_is_left_and_a_blank_cell_is_removed(): void {
		$this->parts->add( 7, 'DK-7750', array( 'deck_width' => '7.7500', 'wheelbase' => '14.0000', 'later_key' => 'x' ), $this->chart, '149' );
		$this->import->read( "id,deck_width\n7,\n" );
		$this->confirmAndRun();
		self::assertSame( array( 'wheelbase' => '14.0000', 'later_key' => 'x' ), $this->parts->held[7]['fields'] );
		self::assertSame( $this->chart, $this->parts->held[7]['table'] );
		self::assertSame( '149', $this->parts->held[7]['size'] );
	}

	public function test_a_short_row_reads_its_missing_cells_as_blank(): void {
		$this->import->read( "id,wheelbase,deck_width\n7\n" );
		$plan = $this->plan();
		self::assertSame( array( 'deck_width' ), array_column( $plan[0]['changes'], 'key' ) );
		self::assertSame( '', $plan[0]['changes'][0]['to'] );
	}

	public function test_rows_are_matched_by_sku_when_asked(): void {
		$this->import->read( "sku;id;deck_width\nDK-7750;9;8,25\nNOPE;7;8\n", 'sku', ',' );
		$plan = $this->plan();
		self::assertSame( array( array( 7 ) ), $this->parts->reads );
		self::assertSame( 7, $plan[0]['part'] );
		self::assertSame( array(), $plan[0]['problems'] );
		self::assertSame( '8.2500', $plan[0]['changes'][0]['to'] );
		self::assertSame( array( 'sku:part_missing' ), self::codes( $plan[1] ) );
		self::assertSame( 'NOPE', $plan[1]['problems'][0]['text'] );
		self::assertSame( 0, $plan[1]['part'] );
	}

	public function test_matched_by_id_a_sku_that_is_another_parts_refuses_the_row(): void {
		$this->import->read( "id,sku,deck_width\n7,TR-149,8\n9,TR-149,8\n7,,8\n" );
		$this->parts->add( 11, '' );
		$state = $this->import->state();
		self::assertNotNull( $state );
		$plan = $this->import->plan( $state, 0, 2 );
		self::assertCount( 2, $plan );
		// Row 2 is also in the file twice, and both are said.
		self::assertSame( array( 'id:key_twice', 'sku:sku_mismatch' ), self::codes( $plan[0] ) );
		self::assertSame( array(), self::codes( $plan[1] ) );

		$this->parts->add( 7, 'DK-7750', array( 'deck_width' => '7.7500' ), $this->chart, '149', 'Katie 7.75' );
		$this->import->read( "id,sku,deck_width\n7,TR-149,8\n" );
		$plan = $this->plan();
		self::assertSame( array( 'sku:sku_mismatch' ), self::codes( $plan[0] ) );
		self::assertSame( 'TR-149', $plan[0]['problems'][0]['text'] );
		self::assertSame( array(), $plan[0]['changes'] );
		// A row that is refused still says which part it was for, and what the part holds.
		self::assertSame( array( 'Katie 7.75', 'DK-7750', array( 'deck_width' => '7.7500' ), $this->chart, '149' ), array( $plan[0]['name'], $plan[0]['sku'], $plan[0]['fields'], $plan[0]['table'], $plan[0]['size'] ) );

		// A blank SKU cell is no claim, and nor is the SKU of a part that is not there.
		$this->import->read( "id,sku,deck_width\n7,,8\n404,X,8\n9,TR-149,8\n" );
		$this->parts->reads = array();
		$state              = $this->import->state();
		self::assertNotNull( $state );
		$plan = $this->import->plan( $state, 1, 2 );
		self::assertSame( array( array( 404, 9 ) ), $this->parts->reads );
		self::assertSame( array( 3, 4 ), array_column( $plan, 'row' ) );
		self::assertSame( array( 'id:part_missing' ), self::codes( $plan[0] ) );
		self::assertSame( array( 404, '', '', array(), 0, '' ), array( $plan[0]['part'], $plan[0]['name'], $plan[0]['sku'], $plan[0]['fields'], $plan[0]['table'], $plan[0]['size'] ) );
		self::assertSame( array(), self::codes( $plan[1] ) );
		self::assertSame( array(), self::codes( $this->import->plan( $state, 0, 1 )[0] ) );
	}

	public function test_a_row_with_no_key_a_key_twice_or_no_such_part_is_refused(): void {
		$this->import->read( "id,deck_width\n,8\nseven,8\n-7,8\n0,8\n7,8\n07,8\n404,8\n9,8\n" );
		$plan = $this->plan();
		self::assertSame(
			array(
				array( 'id:key_missing' ),
				array( 'id:key_missing' ),
				array( 'id:key_missing' ),
				array( 'id:key_missing' ),
				array( 'id:key_twice' ),
				array( 'id:key_twice' ),
				array( 'id:part_missing' ),
				array(),
			),
			array_map( self::codes( ... ), $plan )
		);
		self::assertSame( array( array( 7, 404, 9 ) ), $this->parts->reads );
		self::assertSame( 'seven', $plan[1]['problems'][0]['text'] );
		self::assertSame( '7', $plan[5]['problems'][0]['text'] );
		self::assertSame( '404', $plan[6]['problems'][0]['text'] );
		// Rows with no key are not each other's duplicates.
		$after = $this->confirmAndRun();
		self::assertSame( array( 9 ), $this->parts->written );
		self::assertSame( 1, $after->changed );
		self::assertSame( 0, $after->same );
		self::assertCount( 7, $after->refused );
		self::assertSame(
			array(
				'row'  => 2,
				'key'  => 'id',
				'code' => 'key_missing',
				'text' => '',
			),
			$after->refused[0]
		);
	}

	public function test_a_row_with_a_cell_that_is_wrong_is_not_written_at_all(): void {
		$this->import->read( "id,deck_width,wheelbase\n7,wide,14\n" );
		$plan = $this->plan();
		self::assertSame( array( 'deck_width:measure_malformed' ), self::codes( $plan[0] ) );
		self::assertSame( 'wide', $plan[0]['problems'][0]['text'] );
		$after = $this->confirmAndRun();
		self::assertSame( array(), $this->parts->written );
		self::assertSame( array( 'deck_width' => '7.7500' ), $this->parts->held[7]['fields'] );
		self::assertSame( 0, $after->changed );
		self::assertSame( 'deck_width', $after->refused[0]['key'] );
	}

	public function test_a_chart_is_named_by_its_name_and_a_size_must_be_in_it(): void {
		$this->import->read( "id,spec_table,spec_size\n9,Independent,149\n7,Independent,999\n" );
		$plan = $this->plan();
		self::assertSame(
			array(
				array(
					'key'  => 'spec_table',
					'from' => '',
					'to'   => 'Independent',
				),
				array(
					'key'  => 'spec_size',
					'from' => '',
					'to'   => '149',
				),
			),
			$plan[0]['changes']
		);
		self::assertSame( array( 'spec_table:size_missing' ), self::codes( $plan[1] ) );
		$this->confirmAndRun();
		self::assertSame( $this->chart, $this->parts->held[9]['table'] );
		self::assertSame( '149', $this->parts->held[9]['size'] );
		self::assertSame( 0, $this->parts->held[7]['table'] );
	}

	public function test_a_chart_no_shop_has_or_two_share_is_refused_and_a_blank_one_is_removed(): void {
		$this->tables->create( 'Thunder', SpecTable::read( array() ) );
		$this->tables->create( 'Thunder', SpecTable::read( array() ) );
		$this->parts->add( 9, 'TR-149', array(), $this->chart, '149' );
		$this->import->read( "id,spec_table\n7,Venture\n9,Thunder\n" );
		$plan = $this->plan();
		self::assertSame( array( 'spec_table:table_unknown' ), self::codes( $plan[0] ) );
		self::assertSame( 'Venture', $plan[0]['problems'][0]['text'] );
		self::assertSame( array( 'spec_table:table_ambiguous' ), self::codes( $plan[1] ) );

		// The chart removed, the size kept: the size column is not in the file.
		$this->import->read( "id,spec_table\n9,\n" );
		$plan = $this->plan();
		self::assertSame(
			array(
				array(
					'key'  => 'spec_table',
					'from' => 'Independent',
					'to'   => '',
				),
			),
			$plan[0]['changes']
		);
		self::assertSame( 0, $plan[0]['table'] );
		self::assertSame( '149', $plan[0]['size'] );

		// The size alone, against the chart the part names now.
		$this->import->read( "id,spec_size\n9,\n" );
		$plan = $this->plan();
		self::assertSame( array( 'spec_size' ), array_column( $plan[0]['changes'], 'key' ) );
		self::assertSame( $this->chart, $plan[0]['table'] );
		$this->import->read( "id,spec_size\n9,151\n" );
		self::assertSame( array( 'spec_table:size_missing' ), self::codes( $this->plan()[0] ) );
	}

	public function test_an_export_read_back_changes_nothing(): void {
		$this->parts->add( 9, 'TR-149', array( 'height_class' => 'mid' ), $this->chart, '149', '=Stage' );
		$this->import->read( "\xEF\xBB\xBF" . ( new SpecExport( $this->parts, $this->tables ) )->text() );
		foreach ( $this->plan() as $row ) {
			self::assertSame( array(), $row['problems'] );
			self::assertSame( array(), $row['changes'] );
		}
		$after = $this->confirmAndRun();
		self::assertSame( 2, $after->same );
		self::assertSame( array(), $this->parts->written );
	}

	/**
	 * @return iterable<string, array{string, list<string>}>
	 */
	public static function refusedFiles(): iterable {
		yield 'nothing' => array( '', array( SpecImport::FILE_EMPTY ) );
		yield 'a heading alone' => array( "id,deck_width\n", array( SpecImport::FILE_EMPTY ) );
		yield 'not UTF-8' => array( "id,deck_width\n7,\xC3\x28\n", array( SpecImport::FILE_NOT_UTF ) );
		yield 'no key column' => array( "sku,deck_width\nA,8\n", array( SpecImport::KEY_COLUMN ) );
		yield 'a column twice' => array( "id,deck_width,deck_width\n7,8,9\n", array( SpecImport::COLUMN_TWICE ) );
		yield 'nothing to import' => array( "id,sku,name,notes\n7,A,B,C\n", array( SpecImport::NO_SPECS ) );
		yield 'all three' => array( "sku,sku,name\nA,A,B\n", array( SpecImport::KEY_COLUMN, SpecImport::COLUMN_TWICE, SpecImport::NO_SPECS ) );
		yield 'too many rows' => array( "id,deck_width\n" . str_repeat( "7,8\n", SpecImport::MAX_ROWS + 1 ), array( SpecImport::ROWS_MANY ) );
		yield 'too big' => array( "id,deck_width\n7,8\n" . str_repeat( ' ', SpecImport::MAX_BYTES ), array( SpecImport::FILE_BIG ) );
	}

	#[\PHPUnit\Framework\Attributes\DataProvider( 'refusedFiles' )]
	public function test_a_file_that_cannot_be_read_is_refused_whole( string $text, array $problems ): void {
		self::assertSame( $problems, $this->import->read( $text ) );
		self::assertNull( $this->store->state );
	}

	public function test_the_most_rows_and_the_most_bytes_are_read(): void {
		self::assertSame( array(), $this->import->read( "id,deck_width\n" . str_repeat( "7,8\n", SpecImport::MAX_ROWS ) ) );
		$text = "id,deck_width\n7,8\n";
		self::assertSame( array(), $this->import->read( $text . str_repeat( ' ', SpecImport::MAX_BYTES - strlen( $text ) ) ) );
		self::assertSame( 1, $this->import->state()?->total );
	}

	public function test_a_key_other_than_sku_is_the_id_and_headings_match_in_any_case(): void {
		self::assertSame( array(), $this->import->read( "ID,Deck_Width\n7,8\n", 'name' ) );
		self::assertSame( 'id', $this->import->state()?->key );
		self::assertSame( array( 'id' => 0, 'deck_width' => 1 ), $this->import->state()?->columns );
	}

	public function test_a_new_file_takes_the_place_of_one_that_waits_and_each_has_its_own_token(): void {
		$this->import->read( "id,deck_width\n7,8\n" );
		$first = $this->import->state();
		$this->clock->advance( 1 );
		$this->import->read( "id,deck_width\n7,8\n" );
		$second = $this->import->state();
		self::assertNotNull( $first );
		self::assertNotNull( $second );
		self::assertNotSame( $first->token, $second->token );
		self::assertSame( 64, strlen( $second->token ) );
		$this->import->read( "id,deck_width\n7,9\n" );
		self::assertNotSame( $second->token, $this->import->state()?->token );

		self::assertFalse( $this->import->confirm( $first->token ) );
		self::assertSame( ImportState::WAITING, $this->import->state()?->status );
		self::assertSame( array(), $this->queue->calls );
	}

	public function test_only_who_sent_the_file_confirms_it_and_who_that_was_is_not_kept_after(): void {
		$this->import->read( "id,wheelbase\n7,14\n", 'id', '.', 5 );
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertSame( 5, $state->by );
		self::assertSame( 5, $this->store->state['by'] ?? null );
		self::assertFalse( $this->import->confirm( $state->token, 6 ) );
		self::assertFalse( $this->import->confirm( $state->token ) );
		self::assertSame( array(), $this->queue->calls );
		self::assertTrue( $this->import->confirm( $state->token, 5 ) );
		self::assertSame( 0, $this->import->state()?->by );
		self::assertSame( 0, $this->store->state['by'] ?? null );
		self::assertSame( 0, ImportState::fromArray( array( 'status' => 'done', 'key' => 'id', 'by' => -1 ) )?->by );
		self::assertSame( 8, ImportState::fromArray( array( 'status' => 'waiting', 'key' => 'id', 'by' => 8 ) )?->by );
	}

	public function test_one_that_waits_is_forgotten_after_an_hour_or_when_discarded(): void {
		$this->import->read( "id,deck_width\n7,8\n" );
		$token = (string) $this->import->state()?->token;
		$this->clock->advance( SpecImport::KEEP );
		self::assertNotNull( $this->import->state() );
		$this->clock->advance( 1 );
		self::assertNull( $this->import->state() );
		self::assertNull( $this->store->state );
		self::assertFalse( $this->import->confirm( $token ) );

		$this->import->read( "id,deck_width\n7,8\n" );
		$this->import->discard();
		self::assertNull( $this->store->state );
		$this->import->discard();
		self::assertNull( $this->store->state );
	}

	public function test_while_one_runs_no_file_is_read_and_it_is_not_discarded_or_confirmed_again(): void {
		$this->import->read( "id,deck_width\n7,8\n" );
		$token = (string) $this->import->state()?->token;
		self::assertTrue( $this->import->confirm( $token ) );
		$this->clock->advance( SpecImport::KEEP + 1 );

		self::assertSame( array( SpecImport::BUSY ), $this->import->read( "id,deck_width\n9,8\n" ) );
		$this->import->discard();
		self::assertSame( ImportState::RUNNING, $this->import->state()?->status );
		self::assertFalse( $this->import->confirm( $token ) );
		self::assertSame( array( SpecImport::JOB => 1 ), $this->queue->calls );

		$this->import->run();
		self::assertSame( ImportState::DONE, $this->import->state()?->status );
		self::assertSame( $this->clock->timestamp(), $this->import->state()?->at );
		$this->import->run();
		self::assertSame( array( 7 ), $this->parts->written );
		// One that has finished gives way to a new file, and can be put away.
		$this->import->discard();
		self::assertNull( $this->store->state );
	}

	public function test_the_job_does_nothing_with_no_import_or_one_not_confirmed(): void {
		$this->import->run();
		$this->import->read( "id,deck_width\n7,8\n" );
		$this->import->run();
		self::assertSame( array(), $this->parts->written );
		self::assertSame( ImportState::WAITING, $this->import->state()?->status );
	}

	public function test_a_long_file_runs_a_batch_at_a_time_from_where_it_got_to(): void {
		$text = "id,wheelbase\n";
		for ( $id = 1; $id <= SpecImport::BATCH + 2; $id++ ) {
			$this->parts->add( 100 + $id, 'S' . $id );
			$text .= ( 100 + $id ) . ",14\n";
		}
		$this->import->read( $text );
		$started = $this->clock->timestamp();
		self::assertTrue( $this->import->confirm( (string) $this->import->state()?->token ) );
		$this->clock->advance( 5 );

		$this->import->run();
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertSame( ImportState::RUNNING, $state->status );
		self::assertSame( SpecImport::BATCH, $state->cursor );
		self::assertSame( SpecImport::BATCH, $state->changed );
		self::assertSame( $started, $state->at );
		self::assertCount( SpecImport::BATCH + 2, $state->rows );
		self::assertSame( array( SpecImport::JOB => 1 ), $this->queue->plain );
		self::assertSame( 100 + SpecImport::BATCH, end( $this->parts->written ) );

		$this->import->run();
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertSame( ImportState::DONE, $state->status );
		self::assertSame( SpecImport::BATCH + 2, $state->changed );
		self::assertSame( $started + 5, $state->at );
		self::assertSame( array( SpecImport::JOB => 1 ), $this->queue->plain );
		self::assertCount( SpecImport::BATCH + 2, $this->parts->written );
	}

	public function test_a_file_of_exactly_one_batch_is_done_in_one_and_refusals_add_up_across_batches(): void {
		$text = "id,wheelbase\n";
		for ( $id = 1; $id <= SpecImport::BATCH; $id++ ) {
			$this->parts->add( 100 + $id );
			$text .= ( 100 + $id ) . ",14\n";
		}
		$this->import->read( $text );
		$this->import->confirm( (string) $this->import->state()?->token );
		$this->import->run();
		self::assertSame( ImportState::DONE, $this->import->state()?->status );
		self::assertSame( array(), $this->queue->plain );

		$this->import->read( "id,wheelbase\n404,1\n" . str_repeat( "7,14\n", SpecImport::BATCH ) . "405,1\n" );
		$after = $this->confirmAndRun();
		self::assertSame( array( 2, SpecImport::BATCH + 3 ), array_values( array_unique( array_column( array_filter( $after->refused, static fn( array $problem ): bool => 'part_missing' === $problem['code'] ), 'row' ) ) ) );
		self::assertCount( SpecImport::BATCH + 2, $after->refused );
	}

	public function test_a_batch_that_fails_is_logged_and_tried_again_then_the_import_stops(): void {
		$this->import->read( "id,wheelbase\n7,14\n9,14\n" );
		$this->import->confirm( (string) $this->import->state()?->token );
		$this->parts->fails   = new RuntimeException( 'The database went away.' );
		$this->parts->failsOn = 9;
		$now                  = $this->clock->timestamp();

		$this->import->run();
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertSame( ImportState::RUNNING, $state->status );
		self::assertSame( 1, $state->failures );
		self::assertSame( 0, $state->cursor );
		self::assertSame( 0, $state->changed );
		self::assertCount( 2, $state->rows );
		self::assertSame( array( SpecImport::JOB => $now + SpecImport::RETRY_AFTER ), $this->queue->scheduled );
		self::assertSame( array( SpecImport::JOB => array() ), $this->queue->scheduledWith );
		self::assertSame(
			array(
				'level'   => 'error',
				'message' => 'A batch of the specs import failed.',
				'context' => array(
					'row'      => 2,
					'failures' => 1,
					'error'    => 'The database went away.',
				),
			),
			$this->logger->lines[0]
		);

		$this->import->run();
		self::assertSame( $now + SpecImport::RETRY_AFTER * 2, $this->queue->scheduled[ SpecImport::JOB ] );
		$this->import->run();
		self::assertSame( $now + SpecImport::RETRY_AFTER * 4, $this->queue->scheduled[ SpecImport::JOB ] );
		$this->clock->advance( 9 );
		$this->import->run();
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertSame( ImportState::FAILED, $state->status );
		self::assertSame( ImportState::MAX_FAILURES, $state->failures );
		self::assertSame( array(), $state->rows );
		self::assertSame( $now + 9, $state->at );
		self::assertSame( array( SpecImport::JOB => 3 ), $this->queue->plainScheduled );
		self::assertCount( 4, $this->logger->lines );

		// The part written before the failure was written once, and counted as the same after.
		self::assertSame( array( 7 ), $this->parts->written );
	}

	public function test_a_batch_that_works_after_one_that_failed_goes_on_and_clears_the_count(): void {
		$text = "id,wheelbase\n";
		for ( $id = 1; $id <= SpecImport::BATCH + 1; $id++ ) {
			$this->parts->add( 100 + $id );
			$text .= ( 100 + $id ) . ",14\n";
		}
		$this->import->read( $text );
		$this->import->confirm( (string) $this->import->state()?->token );
		$this->parts->fails   = new RuntimeException( 'Once.' );
		$this->parts->failsOn = 150;
		$this->import->run();
		$this->parts->fails = null;
		$this->import->run();
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertSame( 0, $state->failures );
		self::assertSame( SpecImport::BATCH, $state->cursor );
		self::assertSame( SpecImport::BATCH - 49, $state->changed );
		self::assertSame( 49, $state->same );
	}

	public function test_what_is_stored_reads_back_and_anything_else_is_no_import(): void {
		$this->import->read( "id,wheelbase\n7,x\n" );
		$this->confirmAndRun();
		$state = $this->import->state();
		self::assertNotNull( $state );
		self::assertEquals( $state, ImportState::fromArray( $state->toArray() ) );
		self::assertSame( '.', $state->separator );

		self::assertNull( ImportState::fromArray( null ) );
		self::assertNull( ImportState::fromArray( array( 'status' => 'paused', 'key' => 'id' ) ) );
		self::assertNull( ImportState::fromArray( array( 'status' => 'waiting', 'key' => 'name' ) ) );
		$loose = ImportState::fromArray(
			array(
				'status'   => 'failed',
				'key'      => 'sku',
				'at'       => -5,
				'total'    => '9',
				'columns'  => array( 'sku' => 0, 3 => 4, 'id' => 'x' ),
				'rows'     => array( array( 'a' => 'A', 'b' => 2 ), 'x' ),
				'refused'  => array( 'x', array( 'row' => 3 ) ),
				'failures' => 2,
				'cursor'   => 4,
				'changed'  => 5,
				'same'     => 6,
				'token'    => 7,
			)
		);
		self::assertNotNull( $loose );
		self::assertSame( 0, $loose->at );
		self::assertSame( 0, $loose->total );
		self::assertSame( array( 'sku' => 0 ), $loose->columns );
		self::assertSame( array( array( 'A', '' ), array() ), $loose->rows );
		self::assertSame(
			array(
				array(
					'row'  => 3,
					'key'  => '',
					'code' => '',
					'text' => '',
				),
			),
			$loose->refused
		);
		self::assertSame( array( 2, 4, 5, 6, '', '' ), array( $loose->failures, $loose->cursor, $loose->changed, $loose->same, $loose->token, $loose->separator ) );
		foreach ( array( 'waiting', 'running', 'done' ) as $status ) {
			self::assertSame( $status, ImportState::fromArray( array( 'status' => $status, 'key' => 'id' ) )?->status );
		}
	}
}
