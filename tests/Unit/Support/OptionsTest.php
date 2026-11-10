<?php
/**
 * Options.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Support;

use CreativeBranding\SkateboardBuilder\Support\Options;
use PHPUnit\Framework\TestCase;

/**
 * The lists uninstall, deactivation and the API snapshot read: every name the plugin stores under, each prefixed.
 */
final class OptionsTest extends TestCase {

	public function test_every_option_is_listed_once_with_the_prefix(): void {
		$names = Options::names();
		self::assertSame( array( 'cbuild_db_version', 'cbuild_migrations', 'cbuild_migration_lock', 'cbuild_settings', 'cbuild_reindex_state', 'cbuild_import_state', 'cbuild_import_rows' ), $names );
		self::assertSame( $names, array_unique( $names ) );
		foreach ( $names as $name ) {
			self::assertStringStartsWith( 'cbuild_', $name );
		}
	}

	public function test_the_site_transients_carry_the_transient_prefix(): void {
		self::assertSame( array( 'cbuild_update_manifest' ), Options::siteTransients() );
		foreach ( Options::siteTransients() as $transient ) {
			self::assertStringStartsWith( Options::TRANSIENT_PREFIX, $transient );
		}
	}

	public function test_the_jobs_are_the_ones_deactivation_cancels(): void {
		self::assertSame( array( 'cbuild_migrate', 'cbuild_index_part', 'cbuild_reindex', 'cbuild_import' ), Options::jobs() );
		self::assertSame( 'cbuild_migrate', Options::JOB_MIGRATE );
	}

	public function test_the_meta_and_settings_keys_are_the_ones_uninstall_and_the_snapshot_read(): void {
		self::assertSame( array( '_cbuild_specs', '_cbuild_spec_table', '_cbuild_rows', '_cbuild_seed' ), Options::metaKeys() );
		self::assertSame( array( 'cbuild_spec_table' ), Options::postTypes() );
		self::assertStringStartsWith( 'cbuild_', Options::POST_TYPE_SPEC_TABLE );
		self::assertLessThanOrEqual( 20, strlen( Options::POST_TYPE_SPEC_TABLE ), 'a post type\'s name is at most 20 characters' );
		self::assertSame( array( 'attribute_mapping', 'source_order' ), Options::settingsKeys() );
		foreach ( Options::metaKeys() as $key ) {
			self::assertStringStartsWith( '_cbuild_', $key, 'hidden from the custom fields box, and the plugin\'s own' );
		}
	}

	public function test_the_group_and_the_log_source_are_the_slug(): void {
		self::assertSame( 'creative-skateboard-builder', Options::QUEUE_GROUP );
		self::assertSame( 'creative-skateboard-builder', Options::LOG_SOURCE );
		self::assertSame( 'cbuild', Options::CACHE_GROUP );
	}
}
