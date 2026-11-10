<?php
/**
 * `wp cbuild reindex`.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cli;

use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads every part's specs into the index again: the job that saving the Builder settings queues, run here and now
 * or left to the queue.
 *
 * @since 0.3.0
 */
final class ReindexCommand {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param IndexJobs $jobs The index's jobs.
	 */
	public function __construct( private IndexJobs $jobs ) {}

	/**
	 * Read every part's specs into the index again.
	 *
	 * ## OPTIONS
	 *
	 * [--background]
	 * : Queue it and return at once. The queue then runs it 100 parts at a time.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cbuild reindex
	 *     wp cbuild reindex --background
	 *
	 * ## EXIT STATUS
	 *
	 * 0 when every part was read, or the reindex was queued. 1 when a batch failed; the queue tries it again.
	 *
	 * @since 0.3.0
	 *
	 * @param array<int, string>   $args  Positional arguments.
	 * @param array<string, mixed> $assoc Flags.
	 */
	public function __invoke( array $args, array $assoc = array() ): void {
		unset( $args );
		$this->jobs->requestReindex();
		if ( isset( $assoc['background'] ) && false !== $assoc['background'] ) {
			WP_CLI::success( __( 'The reindex is queued. It runs in the background, 100 parts at a time.', 'creative-skateboard-builder' ) );
			return;
		}
		do {
			$this->jobs->runReindex();
			$state = $this->jobs->state();
		} while ( null !== $state && $state->isRunning() && 0 === $state->failures );
		if ( null === $state || ReindexState::DONE !== $state->phase ) {
			WP_CLI::error( __( 'A batch of the reindex failed. It is tried again in the background. WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' ) );
		}
		/* translators: %s: a number of parts. */
		WP_CLI::success( sprintf( _n( '%s part read into the index.', '%s parts read into the index.', $state->parts, 'creative-skateboard-builder' ), number_format_i18n( $state->parts ) ) );
	}
}
