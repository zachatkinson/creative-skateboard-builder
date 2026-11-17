<?php
/**
 * The migration that queues the layer images' missing renditions.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use Closure;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\Reindex;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Queues the index job of every part the picture draws whose layer image has not one resized copy, so an update makes
 * them with nobody saving a part (Cowork's recheck of 0.7.0, N1; D-214). A site that ran 0.7.0-rc.1 lost some; a site
 * from before 0.7.0 has no layer images and queues nothing. Each job makes its part's copies in the background, as a
 * save's does. Run again, it queues only what is still missing, and a job already waiting is not queued twice.
 *
 * @since 0.8.0
 */
final class RenditionsMigration implements Migration {

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param Closure(): Reindex $index  Queues the jobs of the parts whose layer images have no copies, built when the
	 *                                   migration runs: the registry is built on any request that checks the version.
	 * @param Logger             $logger Where how many were queued is written.
	 */
	public function __construct( private Closure $index, private Logger $logger ) {}

	/**
	 * The key, recorded in `cbuild_migrations` once done.
	 *
	 * @since 0.8.0
	 */
	public function key(): string {
		return 'layer_renditions_1';
	}

	/**
	 * Queue the jobs, in one run: it reads each image's stored pixels and opens no file.
	 *
	 * @since 0.8.0
	 *
	 * @param Cursor $cursor Unused: one run does it.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		$parts = ( $this->index )()->requestRenditions();
		if ( $parts > 0 ) {
			$this->logger->log( Logger::INFO, 'Queued the index job of each part whose layer image has no resized copies.', array( 'parts' => $parts ) );
		}
		return Cursor::done();
	}
}
