<?php
/**
 * The migration framework against the database.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RunResult;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Repository\MigrationRepository;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\BatchMigration;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\ThrowingMigration;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * The repository's lock and versions as rows (D-021), and the runner over the real store, clock, queue and log: a
 * run stopped halfway resumes through Action Scheduler and matches an uninterrupted run; a failure is in
 * WooCommerce's log under the plugin's source (docs/ROADMAP.md, milestone 1: Migrations).
 */
final class MigrationTest extends IntegrationTestCase {

	private MigrationRepository $store;

	public function set_up(): void {
		parent::set_up();
		$this->store = $this->service( MigrationRepository::class );
	}

	/**
	 * A runner over the real adapters and the migrations given.
	 */
	private function runner( \CreativeBranding\SkateboardBuilder\Install\Migrations\Migration ...$migrations ): MigrationRunner {
		return new MigrationRunner( new MigrationRegistry( ...$migrations ), $this->store, $this->service( Clock::class ), $this->service( Logger::class ), $this->service( Queue::class ) );
	}

	public function test_the_lock_is_one_row_with_autoload_off_read_from_the_table(): void {
		global $wpdb;
		self::assertNull( $this->store->lockValue() );
		self::assertTrue( $this->store->acquireLock( 1_800_000_000, 300 ) );
		$row = $wpdb->get_row( $wpdb->prepare( 'SELECT option_value, autoload FROM %i WHERE option_name = %s', $wpdb->options, Options::MIGRATION_LOCK ), ARRAY_A ); // phpcs:ignore
		self::assertSame( array( 'option_value' => '1800000000', 'autoload' => 'off' ), $row );
		self::assertFalse( $this->store->acquireLock( 1_800_000_100, 300 ), 'held' );
		self::assertFalse( $this->store->acquireLock( 1_800_000_300, 300 ), 'held until the TTL has passed' );

		$this->store->refreshLock( 1_800_000_200 );
		self::assertSame( '1800000200', $this->store->lockValue() );
		self::assertSame( '1800000200', get_option( Options::MIGRATION_LOCK ), 'the options cache is not stale' );

		self::assertTrue( $this->store->acquireLock( 1_800_000_501, 300 ), 'taken over once expired' );
		self::assertSame( '1800000501', $this->store->lockValue() );

		$this->store->releaseLock();
		self::assertNull( $this->store->lockValue() );
		self::assertFalse( get_option( Options::MIGRATION_LOCK ) );
	}

	public function test_a_lock_row_holding_junk_is_not_taken_over(): void {
		global $wpdb;
		$wpdb->insert( $wpdb->options, array( 'option_name' => Options::MIGRATION_LOCK, 'option_value' => 'not a time', 'autoload' => 'off' ) );
		self::assertFalse( $this->store->acquireLock( 1_900_000_000, 300 ), 'a value that is not a time is left to a person' );
	}

	public function test_two_real_processes_racing_for_the_lock_one_wins(): void {
		if ( ! self::hasWpCli() ) {
			self::markTestSkipped( 'Needs wp on the path (wp-env).' );
		}
		// Two calls in one PHPUnit process pass even with a broken lock, and the test transaction hides one
		// connection's writes from another (D-021), so two `wp` processes race on wp-env's tests site.
		self::wp( 'option', 'delete', Options::MIGRATION_LOCK );
		$this->raceForTheLock( 'fresh' );

		// An expired lock: both see it expired, one compare-and-swap wins.
		$old = self::wpEval( 'global $wpdb; $wpdb->insert( $wpdb->options, array( "option_name" => "' . Options::MIGRATION_LOCK . '", "option_value" => (string) ( time() - 301 ), "autoload" => "off" ) ); echo "seeded";' );
		self::assertSame( 'seeded', $old['out'], $old['err'] );
		$this->raceForTheLock( 'expired' );
	}

	/**
	 * Start two processes that take the lock at the same second, and assert exactly one did.
	 *
	 * @param string $case For the message.
	 */
	private function raceForTheLock( string $case ): void {
		$at    = time() + 4;
		$code  = 'use CreativeBranding\SkateboardBuilder\Plugin; use CreativeBranding\SkateboardBuilder\Port\MigrationStore;';
		$code .= 'while ( microtime( true ) < ' . $at . ' ) { usleep( 2000 ); }';
		$code .= 'echo Plugin::container()->get( MigrationStore::class )->acquireLock( ' . $at . ', 300 ) ? "won" : "lost";';
		$file  = tempnam( sys_get_temp_dir(), 'cbuild-race-' );
		file_put_contents( $file, "<?php\n" . $code ); // phpcs:ignore
		try {
			$a = self::startWp( array( 'eval-file', $file, '--skip-themes' ) );
			$b = self::startWp( array( 'eval-file', $file, '--skip-themes' ) );
			$x = self::finishWp( $a );
			$y = self::finishWp( $b );
		} finally {
			unlink( $file ); // phpcs:ignore
		}
		self::assertSame( 0, $x['code'], $x['err'] );
		self::assertSame( 0, $y['code'], $y['err'] );
		$outcomes = array( $x['out'], $y['out'] );
		sort( $outcomes );
		self::assertSame( array( 'lost', 'won' ), $outcomes, $case . ' lock: ' . implode( ', ', $outcomes ) );
		$value = self::wpEval( 'global $wpdb; echo (string) $wpdb->get_var( "SELECT option_value FROM {$wpdb->options} WHERE option_name = \'' . Options::MIGRATION_LOCK . '\'" );' );
		self::assertSame( (string) $at, $value['out'], 'the winner\'s timestamp is in the row' );
		self::wp( 'option', 'delete', Options::MIGRATION_LOCK );
	}

	public function test_the_fresh_version_is_read_past_the_object_cache(): void {
		global $wpdb;
		update_option( Options::DB_VERSION, '1', true );
		self::assertSame( '1', $this->store->schemaVersion() );
		$wpdb->update( $wpdb->options, array( 'option_value' => '0' ), array( 'option_name' => Options::DB_VERSION ) );
		self::assertSame( '1', $this->store->schemaVersion(), 'the cached answer' );
		self::assertSame( '0', $this->store->freshSchemaVersion(), 'the table' );
		delete_option( Options::DB_VERSION );
		self::assertSame( '0', $this->store->freshSchemaVersion(), 'none is 0' );
		self::assertSame( '0', $this->store->schemaVersion() );
	}

	public function test_a_run_stopped_halfway_resumes_through_the_queue_and_matches_an_uninterrupted_run(): void {
		delete_option( Options::DB_VERSION );
		$whole = new BatchMigration( 10, 3 );
		self::assertSame( RunResult::Finished, $this->runner( $whole )->run() );
		self::assertSame( array(), self::pendingJobs( Options::JOB_MIGRATE ), 'nothing queued when the budget holds' );

		delete_option( Options::DB_VERSION );
		delete_option( Options::MIGRATIONS );
		$stopped = new BatchMigration( 10, 3 );
		$runner  = $this->runner( $stopped );
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'one job queued' );
		self::assertSame( 3, get_option( Options::MIGRATIONS )['batch_1']['cursor']['position'], 'the cursor is in the row' );
		self::assertNull( $this->store->lockValue(), 'the lock is released between batches' );
		self::assertFalse( get_option( Options::DB_VERSION ), 'not current yet' );

		// The job, as Action Scheduler would run it, until done.
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'still one: the unique flag drops the second' );
		self::assertSame( RunResult::Continued, $runner->run( 0 ) );
		self::assertSame( RunResult::Finished, $runner->run( 0 ) );
		self::assertSame( $whole->rows, $stopped->rows, 'the same rows, no duplicate' );
		self::assertSame( MigrationRegistry::VERSION, get_option( Options::DB_VERSION ) );
		self::assertSame( 'done', get_option( Options::MIGRATIONS )['batch_1']['status'] );
		self::assertSame( RunResult::NotNeeded, $runner->run() );
	}

	public function test_a_failure_is_logged_under_the_plugin_source_and_leaves_the_version_behind(): void {
		delete_option( Options::DB_VERSION );
		$entries = array();
		$logger  = new class( $entries ) implements \WC_Logger_Interface {
			/**
			 * @param array<int, array<string, mixed>> $entries By reference.
			 */
			public function __construct( public array &$entries ) {}
			public function add( $handle, $message, $level = \WC_Log_Levels::NOTICE ) {}
			public function log( $level, $message, $context = array() ) {
				$this->entries[] = array( 'level' => $level, 'message' => $message, 'context' => $context );
			}
			public function emergency( $message, $context = array() ) {}
			public function alert( $message, $context = array() ) {}
			public function critical( $message, $context = array() ) {}
			public function error( $message, $context = array() ) {}
			public function warning( $message, $context = array() ) {}
			public function notice( $message, $context = array() ) {}
			public function info( $message, $context = array() ) {}
			public function debug( $message, $context = array() ) {}
		};
		add_filter( 'woocommerce_logging_class', static fn() => $logger );

		self::assertSame( RunResult::Failed, $this->runner( new ThrowingMigration() )->run() );

		self::assertCount( 1, $logger->entries );
		self::assertSame( 'error', $logger->entries[0]['level'] );
		self::assertSame( Options::LOG_SOURCE, $logger->entries[0]['context']['source'], 'wc_get_logger() source' );
		self::assertSame( 'throwing_1', $logger->entries[0]['context']['migration'] );
		self::assertStringContainsString( 'the disk is full', $logger->entries[0]['message'] );
		self::assertFalse( get_option( Options::DB_VERSION ), 'left behind' );
		self::assertSame( 'failed', get_option( Options::MIGRATIONS )['throwing_1']['status'] );
		self::assertNull( $this->store->lockValue(), 'released' );
	}

	public function test_the_real_log_takes_the_entry(): void {
		// WooCommerce's own logger with the file or database handler: the call must be valid (level, source).
		$this->service( Logger::class )->log( Logger::INFO, 'Creative Skateboard Builder test entry', array( 'migration' => 'noop_1' ) );
		self::assertTrue( true, 'no notice, no doing_it_wrong' );
	}

	public function test_the_queue_is_action_scheduler_with_the_plugin_group_and_the_unique_flag(): void {
		$queue = $this->service( Queue::class );
		self::assertFalse( $queue->isPending( Options::JOB_MIGRATE ) );
		$queue->enqueueUnique( Options::JOB_MIGRATE );
		$queue->enqueueUnique( Options::JOB_MIGRATE );
		self::assertTrue( $queue->isPending( Options::JOB_MIGRATE ) );
		self::assertCount( 1, self::pendingJobs( Options::JOB_MIGRATE ), 'queued once' );
		$action = \ActionScheduler::store()->fetch_action( (string) self::pendingJobs( Options::JOB_MIGRATE )[0] );
		self::assertSame( Options::QUEUE_GROUP, $action->get_group() );
		$queue->cancelAll( Options::JOB_MIGRATE );
		self::assertFalse( $queue->isPending( Options::JOB_MIGRATE ) );
	}

	public function test_the_clock_is_utc(): void {
		$now = $this->service( Clock::class )->now();
		self::assertSame( 'UTC', $now->getTimezone()->getName() );
		self::assertEqualsWithDelta( time(), $now->getTimestamp(), 2 );
	}
}
