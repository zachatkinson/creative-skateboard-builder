<?php
/**
 * Activation.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Install\Migrations\RunResult;
use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Port\Reindex;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Runs the migrations, which create the two tables and seed nothing (docs/ROADMAP.md, milestone 1). On a network
 * activation every site gets the same (D-025). Activation is an admin action, so the run is inline, under the lock.
 * Then every part is asked to be read again: a new install has an empty index, and while the plugin was switched off
 * its jobs were cancelled and no save was heard (D-082).
 */
final class Activator {

	/**
	 * Activation hook.
	 *
	 * @since 0.1.0
	 *
	 * @param bool|mixed $networkWide Whether the plugin is being network-activated.
	 */
	public static function activate( $networkWide = false ): void {
		// On a network, the must-use file that keeps a site's or an account's deletion whole (#8, D-220). Where it
		// cannot be written, Site Health says so.
		NetworkFile::ensure();
		if ( is_multisite() && true === $networkWide ) {
			Sites::each( array( self::class, 'site' ) );
			return;
		}
		self::site();
	}

	/**
	 * Activate the current site.
	 *
	 * @since 0.1.0
	 */
	public static function site(): void {
		$result = Plugin::container()->get( MigrationRunner::class )->run();
		// A run that stopped short asks when it finishes (Hooks\InstallHooks). A site with no queue yet has nowhere to
		// put the job: WooCommerce is missing. It is owed, and asked for on the first request with a queue (D-095).
		if ( in_array( $result, array( RunResult::Finished, RunResult::NotNeeded ), true ) ) {
			self::settle( Sites::hasQueueTables(), Plugin::container()->get( Reindex::class ) );
		}
	}

	/**
	 * Ask for the reindex where there is a queue, and leave a mark where there is none. Activated while WooCommerce
	 * is missing, a site has no queue to put the job in; the first request that finds the queue asks and removes
	 * the mark (Hooks\InstallHooks, D-095).
	 *
	 * @since 0.3.0
	 *
	 * @param bool    $queue   Whether the site has its queue.
	 * @param Reindex $reindex What asks for every part to be read again.
	 */
	public static function settle( bool $queue, Reindex $reindex ): void {
		if ( ! $queue ) {
			update_option( Options::REINDEX_OWED, '1', true );
			return;
		}
		// Asked first, then the mark removed: a request that dies between the two asks again, and asking twice starts once.
		$reindex->requestReindex();
		delete_option( Options::REINDEX_OWED );
	}

	/**
	 * Whether a reindex is owed. The mark is an autoloaded option that exists only while one is, looked for among
	 * the options WordPress has already loaded, so a site that owes nothing spends no query finding that out.
	 *
	 * @since 0.3.0
	 */
	public static function owesReindex(): bool {
		return isset( wp_load_alloptions()[ Options::REINDEX_OWED ] );
	}
}
