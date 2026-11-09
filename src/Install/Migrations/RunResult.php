<?php
/**
 * What a migration run did.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The runner's answer, for its callers and the log.
 */
enum RunResult: string {

	/**
	 * The schema version was already current; nothing ran.
	 */
	case NotNeeded = 'not_needed';

	/**
	 * Another request holds the lock; nothing ran here.
	 */
	case Locked = 'locked';

	/**
	 * Every migration is done and the schema version is recorded.
	 */
	case Finished = 'finished';

	/**
	 * The time budget ran out with work left; a job was queued to continue from the saved cursor.
	 */
	case Continued = 'continued';

	/**
	 * A migration threw; its state and the error are saved, the lock released and the version left behind.
	 */
	case Failed = 'failed';
}
