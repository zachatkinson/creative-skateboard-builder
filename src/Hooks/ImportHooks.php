<?php
/**
 * Runs the specs import's job.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Hooks;

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
	 * @param SpecImport $import The import.
	 */
	public function __construct( private SpecImport $import ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		add_action( Options::JOB_IMPORT, array( $this->import, 'run' ), 10, 0 );
	}
}
