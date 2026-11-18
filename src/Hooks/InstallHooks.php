<?php
/**
 * The install and upgrade hooks.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Install\Migrations\InlineRule;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RunResult;
use CreativeBranding\SkateboardBuilder\Install\NetworkFile;
use CreativeBranding\SkateboardBuilder\Install\Uninstaller;
use CreativeBranding\SkateboardBuilder\Install\Sites;
use CreativeBranding\SkateboardBuilder\Install\Activator;
use CreativeBranding\SkateboardBuilder\Port\Reindex;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where migrations run when `cbuild_db_version` is behind (D-022): inline on `admin_init` where
 * the InlineRule allows it; everywhere else one
 * `cbuild_migrate` job is queued on `init`, and runs the migrations off the request. Also the `cbuild_migrate` action
 * itself, and `wpmu_drop_tables` for a deleted network site (D-025).
 */
final class InstallHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param MigrationRunner    $runner  The migration runner.
	 * @param InlineRule         $inline  Whether this request may run migrations itself.
	 * @param Closure(): Reindex $reindex Builds what asks for every part to be read again.
	 */
	public function __construct( private MigrationRunner $runner, private InlineRule $inline, private Closure $reindex ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'queueWhenBehind' ) );
		add_action( 'init', array( $this, 'askWhenOwed' ) );
		add_action( 'admin_init', array( $this, 'runWhenBehind' ) );
		add_action( Options::JOB_MIGRATE, array( $this, 'runJob' ) );
		add_filter( 'wpmu_drop_tables', array( Uninstaller::class, 'siteTables' ) );
		add_action( 'admin_init', array( $this, 'networkFile' ) );
	}

	/**
	 * On a network, in wp-admin: write the must-use file where it is missing or out of date, as after an update (#8,
	 * D-220). One read of a small file a request; nothing on a single site.
	 *
	 * @since 0.8.0
	 */
	public function networkFile(): void {
		NetworkFile::ensure();
	}

	/**
	 * On every request that cannot run the migrations itself: queue the job, once. After a failure nothing is queued
	 * until the wait has passed; the runner has already scheduled that attempt.
	 *
	 * @since 0.1.0
	 */
	public function queueWhenBehind(): void {
		if ( ! $this->inline->allows() && $this->runner->due() ) {
			$this->runner->queue();
		}
	}

	/**
	 * On every request: ask for the reindex an activation without a queue could not (D-095). A site that owes none
	 * spends no query here; one that does looks for the queue's tables until it finds them.
	 *
	 * @since 0.3.0
	 */
	public function askWhenOwed(): void {
		if ( Activator::owesReindex() && Sites::hasQueueTables() ) {
			Activator::settle( true, ( $this->reindex )() );
		}
	}

	/**
	 * On an admin page, for a user who may: run the migrations now.
	 *
	 * @since 0.1.0
	 */
	public function runWhenBehind(): void {
		if ( $this->inline->allows() && $this->runner->needed() ) {
			// A short budget: a long migration must not hold a manager's every admin page. The job takes the rest.
			$this->finished( $this->runner->run( MigrationRunner::INLINE_BUDGET ) );
		}
	}

	/**
	 * The queued job: run within the budget, and queue again if there is more.
	 *
	 * @since 0.1.0
	 */
	public function runJob(): void {
		$this->finished( $this->runner->run( MigrationRunner::BUDGET, true ) );
	}

	/**
	 * After a run that brought the schema up to date, every part is read again: an upgrade arrives with no
	 * activation, and the version before may have had no index, or have read a spec differently.
	 *
	 * @param RunResult $result How the run ended.
	 */
	private function finished( RunResult $result ): void {
		if ( RunResult::Finished === $result ) {
			( $this->reindex )()->requestReindex();
		}
	}
}
