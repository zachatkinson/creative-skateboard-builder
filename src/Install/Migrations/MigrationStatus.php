<?php
/**
 * A migration's status.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where one migration stands. The values are what `cbuild_migrations` stores.
 *
 * @since 0.3.0
 */
enum MigrationStatus: string {

	case Pending = 'pending';
	case Running = 'running';
	case Done    = 'done';
	case Failed  = 'failed';

	/**
	 * From the store; anything that is not a status reads as pending.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $stored What was stored.
	 */
	public static function read( mixed $stored ): self {
		return is_string( $stored ) ? self::tryFrom( $stored ) ?? self::Pending : self::Pending;
	}
}
