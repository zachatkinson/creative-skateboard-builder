<?php
/**
 * The container.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit;

use CreativeBranding\SkateboardBuilder\Adapter\SystemClock;
use CreativeBranding\SkateboardBuilder\Container;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRegistry;
use CreativeBranding\SkateboardBuilder\Install\Migrations\MigrationRunner;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Support\PluginException;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeQueue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryMigrationStore;
use PHPUnit\Framework\TestCase;

/**
 * Resolution by class, the bindings, the factories and the failures.
 */
final class ContainerTest extends TestCase {

	public function test_a_port_resolves_to_its_adapter(): void {
		$container = new Container();
		$clock     = $container->get( Clock::class );
		self::assertInstanceOf( SystemClock::class, $clock );
		self::assertSame( $clock, $container->get( Clock::class ), 'built once' );
	}

	public function test_set_replaces_a_service_and_has_reports_it(): void {
		$container = new Container();
		self::assertFalse( $container->has( Clock::class ) );
		$fake = new FakeClock();
		$container->set( Clock::class, $fake );
		self::assertTrue( $container->has( Clock::class ) );
		self::assertSame( $fake, $container->get( Clock::class ) );
	}

	public function test_constructor_dependencies_come_from_the_container(): void {
		$container = new Container();
		$store     = new InMemoryMigrationStore();
		$container->set( MigrationStore::class, $store );
		$container->set( Logger::class, new FakeLogger() );
		$container->set( Queue::class, new FakeQueue() );
		$container->set( Clock::class, new FakeClock() );
		$runner = $container->get( MigrationRunner::class );
		self::assertTrue( $runner->needed(), 'the runner reads the store it was given' );
	}

	public function test_the_registry_is_built_from_its_classes(): void {
		$registry = ( new Container() )->get( MigrationRegistry::class );
		$keys     = $registry->keys();
		self::assertSame( array( 'schema_1', 'noop_1' ), $keys );
	}

	public function test_an_unknown_service_throws(): void {
		$this->expectException( PluginException::class );
		$this->expectExceptionMessage( 'Unknown service Nope\\Missing.' );
		( new Container() )->resolve( 'Nope\\Missing' );
	}

	public function test_an_unbound_interface_throws(): void {
		$this->expectException( PluginException::class );
		$this->expectExceptionMessage( 'not instantiable' );
		( new Container() )->resolve( Hookable::class );
	}

	public function test_a_scalar_without_a_default_cannot_be_resolved(): void {
		$this->expectException( PluginException::class );
		$this->expectExceptionMessage( 'Cannot resolve parameter $needed' );
		( new Container() )->resolve( NeedsScalar::class );
	}

	public function test_a_scalar_with_a_default_takes_it(): void {
		$service = ( new Container() )->get( HasDefault::class );
		self::assertSame( 7, $service->limit );
	}

	public function test_hookables_are_every_hookable_class_once(): void {
		$container = new Container();
		$container->set( MigrationStore::class, new InMemoryMigrationStore() );
		$hookables = $container->hookables();
		self::assertCount( 1, $hookables );
		self::assertContainsOnlyInstancesOf( Hookable::class, $hookables );
	}
}

/**
 * A constructor the container cannot satisfy.
 */
final class NeedsScalar {
	/**
	 * Constructor.
	 *
	 * @param int $needed No default.
	 */
	public function __construct( public int $needed ) {}
}

/**
 * A constructor with a scalar default.
 */
final class HasDefault {
	/**
	 * Constructor.
	 *
	 * @param int $limit Default 7.
	 */
	public function __construct( public int $limit = 7 ) {}
}
