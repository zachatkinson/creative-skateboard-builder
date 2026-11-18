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

	public function test_named_migrations_are_kept_as_a_list(): void {
		$a        = new CountingMigration( 'a' );
		$b        = new CountingMigration( 'b' );
		$registry = new MigrationRegistry( first: $a, second: $b );
		self::assertSame( array( $a, $b ), $registry->all() );
	}

	public function test_refuses_two_migrations_with_one_key(): void {
		$this->expectException( PluginException::class );
		$this->expectExceptionMessage( 'share the key dup' );
		new MigrationRegistry( new CountingMigration( 'dup' ), new CountingMigration( 'dup' ) );
	}

	public function test_the_shipped_list_is_in_the_order_it_shipped(): void {
		self::assertSame( array( SchemaMigration::class, NoopMigration::class, \CreativeBranding\SkateboardBuilder\Install\Migrations\SpecTablesMigration::class, \CreativeBranding\SkateboardBuilder\Install\Migrations\PartsTableMigration::class, \CreativeBranding\SkateboardBuilder\Install\Migrations\ReservedSlotKeysMigration::class, \CreativeBranding\SkateboardBuilder\Install\Migrations\SeedBaseplatesMigration::class, \CreativeBranding\SkateboardBuilder\Install\Migrations\RenditionsMigration::class ), MigrationRegistry::CLASSES );
		self::assertSame( '6', MigrationRegistry::VERSION );
		self::assertSame( 'schema_2', ( new \CreativeBranding\SkateboardBuilder\Install\Migrations\PartsTableMigration() )->key() );
	}

	public function test_the_noop_migration_does_nothing_and_is_done(): void {
		$noop = new NoopMigration();
		self::assertSame( 'noop_1', $noop->key() );
		self::assertTrue( $noop->run( \CreativeBranding\SkateboardBuilder\Install\Migrations\Cursor::start() )->done );
	}

	public function test_the_version_is_raised_with_every_migration_added(): void {
		// VERSION and CLASSES are two constants: a migration appended without raising VERSION would never run on a
		// site already at that version. Each release that adds one adds its pair here.
		$versionAfter = array(
			2 => '1',
			3 => '2',
			4 => '3',
			5 => '4',
			6 => '5',
			7 => '6',
		);
		self::assertArrayHasKey( count( MigrationRegistry::CLASSES ), $versionAfter, 'a migration was added: raise MigrationRegistry::VERSION and add the pair here' );
		self::assertSame( $versionAfter[ count( MigrationRegistry::CLASSES ) ], MigrationRegistry::VERSION );
		self::assertSame( MigrationRegistry::CLASSES, array_values( array_unique( MigrationRegistry::CLASSES ) ) );
	}
}
