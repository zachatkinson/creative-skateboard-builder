<?php
/**
 * The install and upgrade hooks.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Uninstaller;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where migrations run when `cbuild_db_version` is behind (D-022): inline on `admin_init` for a user who can manage
 * WooCommerce on an admin page that is not admin-ajax.php, admin-post.php or cron; everywhere else one
 * `cbuild_migrate` job is queued on `init`, and runs the migrations off the request. Also the `cbuild_migrate` action
 * itself, and `wpmu_drop_tables` for a deleted network site (D-025).
 */
final class InstallHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param MigrationRunner $runner The migration runner.
	 */
	public function __construct( private MigrationRunner $runner ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.1.0
	 */
	public function register(): void {
		add_action( 'init', array( $this, 'queueWhenBehind' ) );
		add_action( 'admin_init', array( $this, 'runWhenBehind' ) );
		add_action( Options::JOB_MIGRATE, array( $this, 'runJob' ) );
		add_filter( 'wpmu_drop_tables', array( Uninstaller::class, 'siteTables' ) );
	}

	/**
	 * On every request that cannot run the migrations itself: queue the job, once. After a failure nothing is queued
	 * until the wait has passed; the runner has already scheduled that attempt.
	 *
	 * @since 0.1.0
	 */
	public function queueWhenBehind(): void {
		if ( ! self::inlineAllowed() && $this->runner->due() ) {
			$this->runner->queue();
		}
	}

	/**
	 * On an admin page, for a user who may: run the migrations now.
	 *
	 * @since 0.1.0
	 */
	public function runWhenBehind(): void {
		if ( self::inlineAllowed() && $this->runner->needed() ) {
			// A short budget: a long migration must not hold a manager's every admin page. The job takes the rest.
			$this->runner->run( MigrationRunner::INLINE_BUDGET );
		}
	}

	/**
	 * The queued job: run within the budget, and queue again if there is more.
	 *
	 * @since 0.1.0
	 */
	public function runJob(): void {
		$this->runner->run( MigrationRunner::BUDGET, true );
	}

	/**
	 * Whether this request may run a migration inline: a signed-in user with `manage_woocommerce`, on an admin page
	 * that is not an AJAX, admin-post or cron request (D-022).
	 *
	 * @since 0.1.0
	 */
	public static function inlineAllowed(): bool {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'] ) {
			return false;
		}
		return current_user_can( 'manage_woocommerce' );
	}
}
