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
use CreativeBranding\SkateboardBuilder\Scope;
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
		self::assertSame( array( 'schema_1', 'noop_1', 'spec_tables_1' ), $keys );
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

	public function test_a_dependency_after_a_scalar_with_a_default_is_still_resolved(): void {
		$service = ( new Container() )->get( DefaultThenService::class );
		self::assertSame( 7, $service->limit );
		self::assertInstanceOf( SystemClock::class, $service->clock );
	}

	public function test_the_plugins_own_hookables_are_built_for_any_request(): void {
		$container = new Container();
		$container->set( MigrationStore::class, new InMemoryMigrationStore() );
		$hookables = $container->hookablesFor( Scope::Everywhere );
		self::assertCount( 3, $hookables, 'the install\'s, the index\'s and the spec tables\'' );
		self::assertContainsOnlyInstancesOf( Hookable::class, $hookables );
	}

	public function test_a_hookable_is_built_only_in_a_scope_it_fires_in_and_in_registration_order(): void {
		$container = new Container(
			array(
				SecondHooks::class => array( Scope::Admin, Scope::Rest ),
				FirstHooks::class  => array( Scope::Everywhere ),
				ThirdHooks::class  => array( Scope::Cli ),
			)
		);
		$hookables = $container->hookablesFor( Scope::Everywhere, Scope::Admin );
		self::assertCount( 2, $hookables, 'every one in scope, not the first alone' );
		self::assertInstanceOf( SecondHooks::class, $hookables[0] );
		self::assertInstanceOf( FirstHooks::class, $hookables[1] );
		self::assertFalse( $container->has( ThirdHooks::class ), 'out of scope: never built' );
	}

	public function test_a_hookable_is_handed_out_once_whatever_scopes_follow(): void {
		$container = new Container(
			array(
				FirstHooks::class  => array( Scope::Storefront, Scope::Rest ),
				SecondHooks::class => array( Scope::Rest ),
			)
		);
		self::assertSame( array(), $container->hookablesFor( Scope::Admin, Scope::Cron ) );
		self::assertFalse( $container->has( FirstHooks::class ) );
		$boot = $container->hookablesFor( Scope::Everywhere, Scope::Storefront );
		self::assertCount( 1, $boot );
		self::assertInstanceOf( FirstHooks::class, $boot[0] );
		$rest = $container->hookablesFor( Scope::Rest );
		self::assertCount( 1, $rest, 'the one registered at boot is not registered again' );
		self::assertInstanceOf( SecondHooks::class, $rest[0] );
		self::assertSame( array(), $container->hookablesFor( Scope::Rest, Scope::Storefront ) );
	}

	public function test_the_scopes_at_boot(): void {
		self::assertSame( array( Scope::Everywhere, Scope::Storefront ), Scope::atBoot( false, false, false ) );
		self::assertSame( array( Scope::Everywhere, Scope::Admin ), Scope::atBoot( true, false, false ) );
		self::assertSame( array( Scope::Everywhere, Scope::Cli ), Scope::atBoot( false, true, false ) );
		self::assertSame( array( Scope::Everywhere, Scope::Cron ), Scope::atBoot( false, false, true ) );
		self::assertSame( array( Scope::Everywhere, Scope::Admin, Scope::Cli, Scope::Cron ), Scope::atBoot( true, true, true ), 'wp-admin code run from WP-CLI' );
		foreach ( array( array( true, false, false ), array( false, true, false ), array( false, false, true ), array( false, false, false ) ) as $request ) {
			self::assertNotContains( Scope::Rest, Scope::atBoot( ...$request ), 'known only at rest_api_init' );
		}
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

/**
 * A scalar default first, then a service.
 */
final class DefaultThenService {
	/**
	 * Constructor.
	 *
	 * @param int        $limit Default 7.
	 * @param Clock|null $clock The clock.
	 */
	public function __construct( public int $limit = 7, public ?Clock $clock = null ) {}
}

/**
 * A Hookable with nothing to attach.
 */
final class FirstHooks implements Hookable {
	public function register(): void {}
}

/**
 * Another.
 */
final class SecondHooks implements Hookable {
	public function register(): void {}
}

/**
 * A third.
 */
final class ThirdHooks implements Hookable {
	public function register(): void {}
}
