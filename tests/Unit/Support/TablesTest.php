<?php
/**
 * Tables.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Support;

use CreativeBranding\SkateboardBuilder\Support\Tables;
use PHPUnit\Framework\TestCase;

/**
 * The two tables, named with the site's prefix, so each site of a network has its own.
 */
final class TablesTest extends TestCase {

	/**
	 * What $wpdb held before the test.
	 *
	 * @var mixed
	 */
	private mixed $before = null;

	protected function setUp(): void {
		$this->before    = $GLOBALS['wpdb'] ?? null;
		$GLOBALS['wpdb'] = (object) array( 'prefix' => 'wp_3_' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	protected function tearDown(): void {
		$GLOBALS['wpdb'] = $this->before; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
	}

	public function test_the_tables_in_creation_order(): void {
		self::assertSame( array( 'specs', 'builds' ), Tables::all() );
	}

	public function test_a_name_carries_the_site_prefix_then_the_plugin_prefix(): void {
		self::assertSame( 'wp_3_cbuild_specs', Tables::name( Tables::SPECS ) );
		self::assertSame( 'wp_3_cbuild_specs', Tables::specs() );
		self::assertSame( 'wp_3_cbuild_builds', Tables::builds() );
	}

	public function test_the_prefix_follows_the_site(): void {
		$GLOBALS['wpdb'] = (object) array( 'prefix' => 'wp_' ); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		self::assertSame( 'wp_cbuild_builds', Tables::builds() );
	}
}
