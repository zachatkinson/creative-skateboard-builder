<?php
/**
 * A slot keyed with a word the build link keeps, renamed in each kit.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor;
use CreativeBranding\SkateboardBuilder\Install\Migrations\ReservedSlotKeysMigration;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKitMeta;
use PHPUnit\Framework\TestCase;

/**
 * Audit 19, milestone 6 (D-169): a kit saved by 0.4.0 or 0.5.0 with a slot keyed step, assembly, preset or review.
 */
final class ReservedSlotKeysMigrationTest extends TestCase {

	/**
	 * A slots document with these keys.
	 *
	 * @return array<string, mixed>
	 */
	private static function slots( string ...$keys ): array {
		return array(
			'schema_version' => 1,
			'slots'          => array_map( static fn( string $key ): array => array( 'key' => $key, 'label' => ucfirst( $key ) ), $keys ),
		);
	}

	public function test_each_kit_with_a_reserved_key_is_renamed_and_logged_and_the_others_are_not_written(): void {
		$presets = array( 'presets' => array( array( 'picks' => array( 'deck' => 1, 'review' => 2 ) ) ) );
		$meta    = new InMemoryKitMeta(
			array(
				7 => array( Options::META_KIT_SLOTS => self::slots( 'deck', 'trucks' ) ),
				8 => array(
					Options::META_KIT_SLOTS   => self::slots( 'deck', 'review' ),
					Options::META_KIT_PRESETS => $presets,
				),
				9 => array( Options::META_KIT_SLOTS => self::slots( 'step' ) ),
			)
		);
		$logger    = new FakeLogger();
		$migration = new ReservedSlotKeysMigration( $meta, $logger );

		self::assertSame( 'reserved_slot_keys_1', $migration->key() );
		self::assertTrue( $migration->run( Cursor::start() )->done );

		self::assertSame( array( array( 8, Options::META_KIT_SLOTS ), array( 8, Options::META_KIT_PRESETS ), array( 9, Options::META_KIT_SLOTS ) ), $meta->writes, 'kit 7 is not written; kit 9, with no presets, gets none' );
		self::assertSame( array( 'deck', 'review_slot' ), array_column( $meta->read( 8, Options::META_KIT_SLOTS )['slots'] ?? array(), 'key' ) );
		self::assertSame( array( 'deck' => 1, 'review_slot' => 2 ), $meta->read( 8, Options::META_KIT_PRESETS )['presets'][0]['picks'] ?? null );
		self::assertNull( $meta->read( 9, Options::META_KIT_PRESETS ) );
		self::assertSame(
			array(
				array(
					'level'   => Logger::WARNING,
					'message' => 'A kit\'s slot was keyed with a word the build link keeps for itself, and was renamed.',
					'context' => array( 'kit' => 8, 'renamed' => array( 'review' => 'review_slot' ) ),
				),
				array(
					'level'   => Logger::WARNING,
					'message' => 'A kit\'s slot was keyed with a word the build link keeps for itself, and was renamed.',
					'context' => array( 'kit' => 9, 'renamed' => array( 'step' => 'step_slot' ) ),
				),
			),
			$logger->lines
		);
	}
}
