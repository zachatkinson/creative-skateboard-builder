<?php
/**
 * Runs the specs import's job.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A confirmed import is written wherever Action Scheduler runs, so this fires on any request.
 *
 * @since 0.3.0
 */
final class ImportHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): SpecImport $import Builds the import, when its job runs.
	 */
	public function __construct( private Closure $import ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		add_action( Options::JOB_IMPORT, array( $this, 'run' ), 10, 0 );
	}

	/**
	 * The job that writes a batch of the import.
	 *
	 * @since 0.4.0
	 */
	public function run(): void {
		( $this->import )()->run();
	}
}
