<?php
/**
 * Renames slot keys a build link keeps for itself.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Port\KitMeta;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit saved by 0.4.0 or 0.5.0 with a slot keyed `step`, `assembly`, `preset` or `review` would not read in 0.6.0,
 * which keeps those words for the build link (D-142). Each is renamed `<key>_slot` in the kit's slots and presets,
 * and the log says what became what, with the kit's ID (D-169). Run again, it finds nothing to rename. 0.5.0 reads
 * the new key as any other, so rolling back leaves the kit working.
 *
 * @since 0.6.0
 */
final class ReservedSlotKeysMigration implements Migration {

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param KitMeta $meta   The kits' IDs and their documents as stored: not Kits, which every request would build
	 *                        with the index's services, as the runner is built on every request (issue #19).
	 * @param Logger  $logger Says what was renamed.
	 */
	public function __construct( private KitMeta $meta, private Logger $logger ) {}

	/**
	 * The key.
	 *
	 * @since 0.6.0
	 */
	public function key(): string {
		return 'reserved_slot_keys_1';
	}

	/**
	 * Rename the reserved keys of every kit: a shop has a few kits, one batch.
	 *
	 * @since 0.6.0
	 *
	 * @param Cursor $cursor Ignored: one batch.
	 */
	public function run( Cursor $cursor ): Cursor {
		unset( $cursor );
		foreach ( $this->meta->ids() as $kit ) {
			$renamed = ReservedSlotKeys::renamed( $this->meta->read( $kit, Options::META_KIT_SLOTS ), $this->meta->read( $kit, Options::META_KIT_PRESETS ) );
			if ( null === $renamed ) {
				continue;
			}
			$this->meta->write( $kit, Options::META_KIT_SLOTS, $renamed['slots'] );
			if ( null !== $renamed['presets'] ) {
				$this->meta->write( $kit, Options::META_KIT_PRESETS, $renamed['presets'] );
			}
			$this->logger->log(
				Logger::WARNING,
				'A kit\'s slot was keyed with a word the build link keeps for itself, and was renamed.',
				array(
					'kit'     => $kit,
					'renamed' => $renamed['renamed'],
				)
			);
		}
		return Cursor::done();
	}
}
