<?php
/**
 * WP-CLI registration.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use CreativeBranding\SkateboardBuilder\Cli\CheckCommand;
use CreativeBranding\SkateboardBuilder\Cli\ReindexCommand;
use CreativeBranding\SkateboardBuilder\Cli\SpecsCommand;
use CreativeBranding\SkateboardBuilder\Hookable;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers `wp cbuild` when WP-CLI runs, and is built only then (D-067). WP-CLI reads each command's synopsis from
 * its class's docblocks, so the commands are given as they are.
 *
 * @since 0.3.0
 */
final class CliHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecsCommand   $specs   `wp cbuild specs`.
	 * @param CheckCommand   $check   `wp cbuild check`.
	 * @param ReindexCommand $reindex `wp cbuild reindex`.
	 */
	public function __construct( private SpecsCommand $specs, private CheckCommand $check, private ReindexCommand $reindex ) {}

	/**
	 * Add the commands.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		if ( ! class_exists( WP_CLI::class ) ) {
			return;
		}
		WP_CLI::add_command( 'cbuild specs', $this->specs );
		WP_CLI::add_command( 'cbuild check', $this->check );
		WP_CLI::add_command( 'cbuild reindex', $this->reindex );
	}
}
