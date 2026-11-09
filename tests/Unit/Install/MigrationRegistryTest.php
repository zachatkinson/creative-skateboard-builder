<?php
/**
 * MigrationRegistry.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Install;

use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Migrations\NoopMigration;
use CreativeBranding\SkateboardBuilder\Install\Migrations\SchemaMigration;
use CreativeBranding\SkateboardBuilder\Support\PluginException;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\CountingMigration;
use PHPUnit\Framework\TestCase;

/**
 * Ordered, unique, versioned.
 */
final class MigrationRegistryTest extends TestCase {

	public function test_keeps_the_order_given(): void {
		$a        = new CountingMigration( 'a' );
		$b        = new CountingMigration( 'b' );
		$registry = new MigrationRegistry( $b, $a );
		self::assertSame( array( $b, $a ), $registry->all() );
		self::assertSame( array( 'b', 'a' ), $registry->keys() );
	}

	public function test_refuses_two_migrations_with_one_key(): void {
		$this->expectException( PluginException::class );
		$this->expectExceptionMessage( 'share the key dup' );
		new MigrationRegistry( new CountingMigration( 'dup' ), new CountingMigration( 'dup' ) );
	}

	public function test_the_shipped_list_is_the_schema_then_the_noop(): void {
		self::assertSame( array( SchemaMigration::class, NoopMigration::class ), MigrationRegistry::CLASSES );
		self::assertSame( '1', MigrationRegistry::VERSION );
	}

	public function test_the_noop_migration_does_nothing_and_is_done(): void {
		$noop = new NoopMigration();
		self::assertSame( 'noop_1', $noop->key() );
		self::assertTrue( $noop->run( \CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor::start() )->done );
	}
}
