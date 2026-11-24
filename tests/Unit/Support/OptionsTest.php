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
		self::assertSame( array( 'cbuild_db_version', 'cbuild_migrations', 'cbuild_migration_lock', 'cbuild_settings', 'cbuild_reindex_state', 'cbuild_reindex_owed', 'cbuild_import_state', 'cbuild_import_rows', 'cbuild_endpoints' ), $names );
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
		self::assertSame( array( 'cbuild_migrate', 'cbuild_index_part', 'cbuild_reindex', 'cbuild_import', 'cbuild_compose_picture', 'cbuild_prune_pictures' ), Options::jobs() );
		self::assertSame( 'cbuild_migrate', Options::JOB_MIGRATE );
	}

	public function test_the_meta_and_settings_keys_are_the_ones_uninstall_and_the_snapshot_read(): void {
		self::assertSame( array( '_cbuild_specs', '_cbuild_spec_table', '_cbuild_layer', '_cbuild_not_offered', '_cbuild_rows', '_cbuild_seed', '_cbuild_slots', '_cbuild_rules', '_cbuild_presets', '_cbuild_assembly', '_cbuild_picture', '_cbuild_layout', '_cbuild_settings' ), Options::metaKeys() );
		self::assertSame( array( '_cbuild_slots', '_cbuild_rules', '_cbuild_presets', '_cbuild_assembly', '_cbuild_picture', '_cbuild_layout', '_cbuild_settings' ), Options::kitDocuments() );
		self::assertSame( array( 'cbuild_kit' ), Options::productTypes() );
		self::assertSame( array( 'cbuild', 'cbuild_children', 'cbuild_parent', 'cbuild_slot' ), Options::cartKeys() );
		self::assertSame( array( 'role', 'build', 'parent', 'slot', 'name', 'removed' ), Options::cartItemFields() );
		self::assertSame( array( '_cbuild_id', '_cbuild_role', '_cbuild_parent', '_cbuild_children', '_cbuild_slot', '_cbuild_snapshot' ), Options::orderItemKeys() );
		self::assertLessThanOrEqual( 32, strlen( Options::PRODUCT_TYPE_KIT ), 'a term\'s slug, well inside its 200 characters' );
		self::assertSame( array( 'cbuild_spec_table' ), Options::postTypes() );
		self::assertStringStartsWith( 'cbuild_', Options::POST_TYPE_SPEC_TABLE );
		self::assertLessThanOrEqual( 20, strlen( Options::POST_TYPE_SPEC_TABLE ), 'a post type\'s name is at most 20 characters' );
		self::assertSame( array( 'attribute_mapping', 'source_order', 'build_discount', 'fixed_coupons', 'rate_limit', 'trusted_proxy_header', 'kit_defaults' ), Options::settingsKeys() );
		foreach ( Options::metaKeys() as $key ) {
			self::assertStringStartsWith( '_cbuild_', $key, 'hidden from the custom fields box, and the plugin\'s own' );
		}
	}

	public function test_the_group_and_the_log_source_are_the_slug(): void {
		self::assertSame( 'creative-skateboard-builder', Options::QUEUE_GROUP );
		self::assertSame( 'creative-skateboard-builder', Options::LOG_SOURCE );
		self::assertSame( 'cbuild', Options::CACHE_GROUP );
	}

	// Spec review of milestone 7, should-fix: classes HOOKS.md promises a theme were not in the API's snapshot, so
	// `composer api:check` would not notice one renamed. Every class in a table of HOOKS.md's is in the list the
	// snapshot is made from; api:check holds the other way.
	public function test_every_class_hooks_md_lists_in_a_table_is_in_the_api_surface(): void {
		$hooks = (string) file_get_contents( dirname( __DIR__, 3 ) . '/docs/HOOKS.md' );
		preg_match_all( '/^\| `(cbuild-[a-z0-9_-]+)` \| \d+\.\d+\.\d+ \|/m', $hooks, $rows );
		self::assertNotSame( array(), $rows[1] );
		self::assertSame( array(), array_values( array_diff( $rows[1], Options::cssClasses() ) ) );
		self::assertContains( 'cbuild-board__layer', Options::cssClasses(), 'the board\'s layers, which cbuild_part_classes reaches' );
		self::assertContains( 'cbuild-cart-picture', Options::cssClasses() );
	}
}
