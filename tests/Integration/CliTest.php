<?php
/**
 * `wp cbuild`, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Cli\CheckCommand;
use CreativeBranding\SkateboardBuilder\Cli\ReindexCommand;
use CreativeBranding\SkateboardBuilder\Cli\SpecsCommand;
use CreativeBranding\SkateboardBuilder\Hooks\CliHooks;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use RuntimeException;
use WP_CLI;

require_once dirname( __DIR__ ) . '/Support/wp-cli-double.php';

/**
 * Spec, Admin: the import and the export run as `wp cbuild specs import <file> --dry-run` and `wp cbuild specs
 * export`; roadmap, milestone 3: `wp cbuild check` exits non-zero, and each command has its exit codes. The commands
 * run against a double of WP_CLI that records what is printed and throws where WP-CLI exits 1, and then once each as
 * a real `wp` process.
 */
final class CliTest extends IntegrationTestCase {

	/**
	 * Files this test wrote.
	 *
	 * @var list<string>
	 */
	private array $files = array();

	public function set_up(): void {
		parent::set_up();
		WP_CLI::reset();
	}

	public function tear_down(): void {
		foreach ( $this->files as $file ) {
			if ( is_file( $file ) ) {
				unlink( $file ); // phpcs:ignore
			}
		}
		delete_option( Options::IMPORT );
		delete_option( Options::REINDEX );
		delete_option( 'woocommerce_price_decimal_sep' );
		parent::tear_down();
	}

	/**
	 * A path in the temporary folder, removed after the test, holding this text when one is given.
	 *
	 * @param string      $ending The file's ending.
	 * @param string|null $text   Its text.
	 */
	private function file( string $ending = '.csv', ?string $text = null ): string {
		$path          = sys_get_temp_dir() . '/cbuild-' . bin2hex( random_bytes( 6 ) ) . $ending;
		$this->files[] = $path;
		if ( null !== $text ) {
			file_put_contents( $path, $text ); // phpcs:ignore
		}
		return $path;
	}

	private static function deck( string $sku, string $width = '' ): \WC_Product_Simple {
		$deck = new \WC_Product_Simple();
		$deck->set_name( 'Deck ' . $sku );
		$deck->set_sku( $sku );
		if ( '' !== $width ) {
			$deck->update_meta_data( Options::META_SPECS, array( 'deck_width' => $width ) );
		}
		$deck->save();
		return $deck;
	}

	/**
	 * Run a command that must exit 1, and give back what it said.
	 *
	 * @param callable(): void $command The command.
	 */
	private static function exits( callable $command ): string {
		try {
			$command();
		} catch ( RuntimeException $stopped ) {
			return $stopped->getMessage();
		}
		self::fail( 'The command did not exit 1.' );
	}

	public function test_wp_cli_is_given_the_three_commands(): void {
		$this->service( CliHooks::class )->register();
		self::assertSame( array( 'cbuild specs', 'cbuild check', 'cbuild reindex' ), array_keys( WP_CLI::$commands ) );
		self::assertInstanceOf( SpecsCommand::class, WP_CLI::$commands['cbuild specs'] );
		self::assertInstanceOf( CheckCommand::class, WP_CLI::$commands['cbuild check'] );
		self::assertInstanceOf( ReindexCommand::class, WP_CLI::$commands['cbuild reindex'] );
	}

	public function test_export_prints_the_sheet_or_writes_it_to_a_csv_file_on_the_disk(): void {
		$deck  = self::deck( 'DK-1', '8.2500' );
		$specs = $this->service( SpecsCommand::class );

		$specs->export( array() );
		self::assertCount( 1, WP_CLI::$lines );
		self::assertStringStartsWith( 'line: id,sku,name,spec_table,spec_size,', WP_CLI::$lines[0] );
		self::assertStringContainsString( "\n" . $deck->get_id() . ',DK-1,"Deck DK-1",,,', str_replace( 'Deck DK-1,', '"Deck DK-1",', WP_CLI::$lines[0] ) );
		self::assertStringEndsNotWith( "\n", WP_CLI::$lines[0] );

		WP_CLI::reset();
		$path = $this->file();
		file_put_contents( $path, 'what was there' ); // phpcs:ignore
		$specs->export( array( $path ) );
		$text = (string) file_get_contents( $path ); // phpcs:ignore
		self::assertStringStartsWith( "\xEF\xBB\xBFid,sku,name,spec_table,spec_size,", $text );
		self::assertStringContainsString( "\n" . $deck->get_id() . ',DK-1,', $text );
		self::assertSame( array( 'success: 1 row written to ' . $path . '.' ), WP_CLI::$lines );

		// Not a .csv, an address, a stream and a folder that is not there: nothing is written.
		$other = $this->file( '.php' );
		self::assertSame( $other . ' is not a .csv file.', self::exits( static fn() => $specs->export( array( $other ) ) ) );
		self::assertFileDoesNotExist( $other );
		self::assertSame( 'There is no file at ftp://example.com/specs.csv.', self::exits( static fn() => $specs->export( array( 'ftp://example.com/specs.csv' ) ) ) );
		self::assertSame( 'There is no file at php://output/x.csv.', self::exits( static fn() => $specs->export( array( 'php://output/x.csv' ) ) ) );
		$nowhere = sys_get_temp_dir() . '/cbuild-no-folder/specs.csv';
		self::assertSame( $nowhere . ' cannot be read or written. Check the folder is there and its permissions.', self::exits( static fn() => $specs->export( array( $nowhere ) ) ) );
		self::assertSame( sys_get_temp_dir() . '/x.CSV/ cannot be read or written. Check the folder is there and its permissions.', self::exits( static fn() => $specs->export( array( sys_get_temp_dir() . '/x.CSV/' ) ) ) );
	}

	public function test_a_dry_run_says_what_a_file_would_do_and_writes_nothing(): void {
		$one   = self::deck( 'DK-1', '8.2500' );
		$two   = self::deck( 'DK-2' );
		$specs = $this->service( SpecsCommand::class );
		$path  = $this->file( '.csv', "id,deck_width\n{$one->get_id()},8.25\n{$two->get_id()},8.5\n" );

		$specs->import( array( $path ), array( 'dry-run' => true ) );
		self::assertSame( array( 'success: Dry run: 1 would change, 1 would stay as they are, 0 cannot be read. Nothing was written.' ), WP_CLI::$lines );
		self::assertSame( '', WP_CLI::$format, 'no list when every row reads' );
		self::assertSame( '', get_post_meta( $two->get_id(), Options::META_SPECS, true ) );
		self::assertNull( $this->service( ImportStore::class )->load(), 'a dry run leaves nothing waiting' );
		self::assertSame( array(), self::waiting( Options::JOB_IMPORT ) );

		// A row that cannot be read is listed with its sentence, and the command exits 1.
		$path = $this->file( '.csv', "id,deck_width\n{$two->get_id()},wide\n999999,8\n{$one->get_id()},8.5\n" );
		self::assertSame(
			'Dry run: 1 would change, 0 would stay as they are, 2 cannot be read. Nothing was written.',
			self::exits( static fn() => $specs->import( array( $path ), array( 'dry-run' => true, 'format' => 'json' ) ) )
		);
		self::assertSame( 'json', WP_CLI::$format );
		self::assertSame(
			array(
				array( 'row' => 2, 'column' => 'deck_width', 'code' => 'measure_malformed', 'message' => '"wide" is not a measurement. Use a number, or a fraction such as 8 1/4.' ),
				array( 'row' => 3, 'column' => 'id', 'code' => 'part_missing', 'message' => 'No product is "999999".' ),
			),
			WP_CLI::$items
		);
		self::assertSame( array( 'deck_width' => '8.2500' ), get_post_meta( $one->get_id(), Options::META_SPECS, true ) );
	}

	public function test_an_import_writes_the_rows_that_read_lists_the_rest_and_exits_1_for_them(): void {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$one   = self::deck( 'DK-1', '8.2500' );
		$two   = self::deck( 'DK-2' );
		$specs = $this->service( SpecsCommand::class );

		// By SKU, with the shop's comma, and more rows than one batch holds.
		$rows = '';
		for ( $at = 0; $at < 120; $at++ ) {
			$rows .= self::deck( 'MANY-' . $at )->get_sku() . ";\"8,0\"\n";
		}
		$path = $this->file( '.csv', "sku;deck_width\nDK-1;\"8,25\"\nDK-2;\"8,5\"\n" . $rows );
		$specs->import( array( $path ), array( 'key' => 'sku' ) );
		self::assertSame( array( 'success: Imported: 121 changed, 1 already as the file has them, 0 not written.' ), WP_CLI::$lines );
		self::assertSame( array( 'deck_width' => '8.5000' ), get_post_meta( $two->get_id(), Options::META_SPECS, true ) );
		self::assertSame( array( 'deck_width' => '8.0000' ), get_post_meta( (int) wc_get_product_id_by_sku( 'MANY-119' ), Options::META_SPECS, true ) );
		self::assertSame( ImportState::DONE, ImportState::fromArray( $this->service( ImportStore::class )->load() )?->status );

		WP_CLI::reset();
		$path = $this->file( '.csv', "id,deck_width\n{$one->get_id()},9\n{$two->get_id()},-1\n" );
		self::assertSame( 'Imported: 1 changed, 0 already as the file has them, 1 not written.', self::exits( static fn() => $specs->import( array( $path ) ) ) );
		self::assertSame( 'table', WP_CLI::$format );
		self::assertSame( array( array( 'row' => 3, 'column' => 'deck_width', 'code' => 'measure_malformed', 'message' => '"-1" is not a measurement. Use a number, or a fraction such as 8 1/4.' ) ), WP_CLI::$items );
		self::assertSame( array( 'deck_width' => '9.0000' ), get_post_meta( $one->get_id(), Options::META_SPECS, true ) );
		self::assertSame( array( 'deck_width' => '8.5000' ), get_post_meta( $two->get_id(), Options::META_SPECS, true ), 'the refused row is not written' );
	}

	public function test_a_file_that_cannot_be_read_exits_1_with_the_reason_and_changes_nothing(): void {
		$deck  = self::deck( 'DK-1' );
		$specs = $this->service( SpecsCommand::class );
		$gone  = sys_get_temp_dir() . '/cbuild-not-there.csv';
		self::assertSame( 'There is no file at ' . $gone . '.', self::exits( static fn() => $specs->import( array( $gone ) ) ) );
		self::assertSame( 'There is no file at .', self::exits( static fn() => $specs->import( array() ) ) );
		$text = $this->file( '.txt', "id,deck_width\n{$deck->get_id()},8\n" );
		self::assertSame( $text . ' is not a .csv file.', self::exits( static fn() => $specs->import( array( $text ) ) ) );
		self::assertSame( 'There is no file at https://example.com/specs.csv.', self::exits( static fn() => $specs->import( array( 'https://example.com/specs.csv' ) ) ) );
		$big = $this->file( '.csv', "id,deck_width\n" . str_repeat( "1,8\n", 600000 ) );
		self::assertSame( 'The file is over 2 MB. Split it into smaller files.', self::exits( static fn() => $specs->import( array( $big ) ) ) );
		$keyless = $this->file( '.csv', "id,deck_width\n{$deck->get_id()},8\n" );
		self::assertSame( 'The file has no sku column. Add one, or match parts by the other with --key.', self::exits( static fn() => $specs->import( array( $keyless ), array( 'key' => 'sku' ) ) ) );
		$bare = $this->file( '.csv', "id,name,id\n{$deck->get_id()},x,1\n" );
		self::assertSame( 'A column is in the file twice. Keep one of each. The file has no spec columns, so there is nothing to import.', self::exits( static fn() => $specs->import( array( $bare ) ) ) );
		self::assertSame( '', get_post_meta( $deck->get_id(), Options::META_SPECS, true ) );
		self::assertNull( $this->service( ImportStore::class )->load() );

		// An import that runs is left to finish.
		$this->service( ImportStore::class )->save( ( new ImportState( ImportState::RUNNING, 't', 'id', '.', time(), array(), array(), 1 ) )->toArray() );
		self::assertSame( 'An import is running. Wait for it to finish.', self::exits( static fn() => $specs->import( array( $keyless ) ) ) );
	}

	public function test_check_lists_what_it_finds_and_exits_1_or_says_it_found_nothing(): void {
		$check = $this->service( CheckCommand::class );
		$check( array() );
		self::assertSame( array( 'success: The check found nothing to fix.' ), WP_CLI::$lines );
		self::assertSame( '', WP_CLI::$format );

		// Asked for as data, nothing is an empty list and no sentence.
		WP_CLI::reset();
		$check( array(), array( 'format' => 'json' ) );
		self::assertSame( array(), WP_CLI::$lines );
		self::assertSame( 'json', WP_CLI::$format );
		self::assertSame( array(), WP_CLI::$items );

		$lost = self::deck( 'DK-9' );
		$lost->update_meta_data( Options::META_SPEC_TABLE, array( 'table' => 999999, 'size' => '1' ) );
		$lost->save();
		WP_CLI::reset();
		self::assertSame( 'The check found 1 thing to fix.', self::exits( static fn() => $check( array() ) ) );
		self::assertSame( 'table', WP_CLI::$format );
		self::assertCount( 1, WP_CLI::$items );
		self::assertSame( 'value_unread', WP_CLI::$items[0]['code'] );
		self::assertSame( $lost->get_id(), WP_CLI::$items[0]['part'] );
		self::assertSame( 0, WP_CLI::$items[0]['kit'] );
		self::assertSame( 'It names a maker\'s chart that is no longer there. Choose another on its Part specs.', WP_CLI::$items[0]['message'] );
		self::assertSame( array( 'code', 'part', 'kit', 'slot', 'spec', 'message' ), array_keys( WP_CLI::$items[0] ) );
	}

	public function test_reindex_reads_every_part_now_or_queues_it(): void {
		$this->withRealTables(
			function (): void {
				$decks = array();
				for ( $at = 0; $at < 3; $at++ ) {
					$decks[] = self::deck( 'RX-' . $at, '8.0000' )->get_id();
				}
				$index = $this->service( SpecIndex::class );
				$index->forget( ...$decks );
				$reindex = $this->service( ReindexCommand::class );

				$reindex( array(), array( 'background' => true ) );
				self::assertSame( array( 'success: The reindex is queued. It runs in the background, 100 parts at a time.' ), WP_CLI::$lines );
				self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
				self::assertSame( array(), $index->specsOf( ...$decks ) );

				WP_CLI::reset();
				$reindex( array() );
				self::assertCount( 1, WP_CLI::$lines );
				self::assertMatchesRegularExpression( '/^success: [\d,]+ parts read into the index\.$/', WP_CLI::$lines[0] );
				self::assertCount( 3, $index->specsOf( ...$decks ) );
				self::assertFalse( $this->service( IndexJobs::class )->state()?->isRunning() );
			}
		);
	}

	public function test_a_batch_that_fails_exits_1_and_is_left_to_the_queue(): void {
		$deck  = self::deck( 'DK-1' );
		$specs = $this->service( SpecsCommand::class );
		$path  = $this->file( '.csv', "id,deck_width\n{$deck->get_id()},8\n" );
		$break = static function (): void {
			throw new RuntimeException( 'The database went away.' );
		};
		add_action( 'woocommerce_before_product_object_save', $break );
		try {
			$said = self::exits( static fn() => $specs->import( array( $path ) ) );
		} finally {
			remove_action( 'woocommerce_before_product_object_save', $break );
		}
		self::assertSame( 'A batch of the import failed. The rows before it are written, and the rest are tried again in the background. WooCommerce > Status > Logs has the reason.', $said );
		$state = ImportState::fromArray( $this->service( ImportStore::class )->load() );
		self::assertSame( ImportState::RUNNING, $state?->status );
		self::assertSame( 1, $state?->failures );
		self::assertNotSame( array(), self::waiting( Options::JOB_IMPORT ), 'the queue tries the batch again' );

		$catalogue = $this->createMock( PartCatalogue::class );
		$catalogue->method( 'page' )->willThrowException( new RuntimeException( 'The database went away.' ) );
		$jobs    = new IndexJobs( $this->service( SpecIndexer::class ), $this->service( SpecIndex::class ), $catalogue, $this->service( Queue::class ), $this->service( ReindexStore::class ), $this->service( ShopSettings::class ), $this->service( Clock::class ), $this->service( Logger::class ), $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class ), $this->service( \CreativeBranding\SkateboardBuilder\Service\SavedSources::class ), $this->service( \CreativeBranding\SkateboardBuilder\Service\PartRenditions::class ) );
		$reindex = new ReindexCommand( $jobs );
		self::assertSame( 'A batch of the reindex failed. It is tried again in the background. WooCommerce > Status > Logs has the reason.', self::exits( static fn() => $reindex( array() ) ) );
		self::assertTrue( $jobs->state()?->isRunning() );
		self::assertSame( 1, $jobs->state()?->failures );
	}

	public function test_a_real_wp_process_has_the_commands_and_their_exit_codes(): void {
		if ( ! self::hasWpCli() ) {
			self::markTestSkipped( 'Needs wp on the path (wp-env).' );
		}
		$help = self::wp( 'help', 'cbuild', 'specs', 'import' );
		self::assertSame( 0, $help['code'], $help['err'] );
		self::assertStringContainsString( 'wp cbuild specs import <file> [--dry-run] [--key=<key>] [--format=<format>]', $help['out'] );
		$export = self::wp( 'cbuild', 'specs', 'export' );
		self::assertSame( 0, $export['code'], $export['err'] );
		self::assertStringStartsWith( 'id,sku,name,spec_table,spec_size,', $export['out'] );
		$import = self::wp( 'cbuild', 'specs', 'import', '/tmp/cbuild-not-there.csv', '--dry-run' );
		self::assertSame( 1, $import['code'] );
		self::assertStringContainsString( 'Error: There is no file at /tmp/cbuild-not-there.csv.', $import['err'] );
		$check = self::wp( 'cbuild', 'check', '--format=count' );
		self::assertContains( $check['code'], array( 0, 1 ), $check['err'] );
		self::assertSame( 0 === (int) $check['out'] ? 0 : 1, $check['code'], 'non-zero exactly when it finds anything' );
	}
}
