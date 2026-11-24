<?php
/**
 * The migration that queues the renditions 1.1.0 never made of a part that gives no size.
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
 * Until 1.2.0 a part with no spec in the index got no resized copy of its layer image, and nothing counted it missing:
 * on the demo, every grip that gives no size (issue #129, D-371). 1.2.0 counts them, so this step, the 0.8.0 step run
 * again under a key of its own, queues each such part's index job, which makes its copies in the background (D-373).
 * The update's request reads each image's stored pixels and opens no file; on a shop with nothing missing it queues
 * nothing and says nothing.
 *
 * @since 1.2.0
 */
final class UnsizedRenditionsMigration implements Migration {

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Closure(): Reindex $index  Queues the jobs of the parts whose layer images have no copies, built when the
	 *                                   migration runs.
	 * @param Logger             $logger Where how many were queued is written.
	 */
	public function __construct( private Closure $index, private Logger $logger ) {}

	/**
	 * The key, recorded in `cbuild_migrations` once done.
	 *
	 * @since 1.2.0
	 */
	public function key(): string {
		return 'layer_renditions_2';
	}

	/**
	 * Queue the jobs, in one run, as 0.8.0's step does.
	 *
	 * @since 1.2.0
	 *
	 * @param Cursor $cursor Unused: one run does it.
	 */
	public function run( Cursor $cursor ): Cursor {
		return ( new RenditionsMigration( $this->index, $this->logger ) )->run( $cursor );
	}
}
