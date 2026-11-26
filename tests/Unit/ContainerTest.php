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

	// D-127: a save that writes either of a part's two documents may have moved a source, and no other meta does.
	public function test_the_saves_the_index_follows_are_those_of_a_parts_two_documents(): void {
		$saved = ( new Container() )->get( \CreativeBranding\SkateboardBuilder\Service\SavedSources::class );
		foreach ( array( '_cbuild_specs' => true, '_cbuild_spec_table' => true, '_stock' => false ) as $key => $moves ) {
			$saved->saving( 7, array( 'stock_quantity' ) );
			$saved->meta( 7, $key );
			self::assertSame( $moves, $saved->moved( 7 ), $key );
		}
	}

	// D-208: the classes an earlier release's container built keep that release's constructors, and this container
	// gives each what 0.7.0 adds to it; a service asked for by its port gets it too.
	public function test_each_class_an_earlier_release_built_gets_what_this_release_adds(): void {
		$container = new Container();
		foreach (
			array(
				array( \CreativeBranding\SkateboardBuilder\Service\IndexJobs::class, 'renditions', \CreativeBranding\SkateboardBuilder\Service\PartRenditions::class ),
				array( \CreativeBranding\SkateboardBuilder\Port\Reindex::class, 'renditions', \CreativeBranding\SkateboardBuilder\Service\PartRenditions::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\CatalogueCheck::class, 'layers', \CreativeBranding\SkateboardBuilder\Port\LayerImages::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\CatalogueCheck::class, 'looks', \CreativeBranding\SkateboardBuilder\Port\PartLooks::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\StateAnswer::class, 'artwork', \CreativeBranding\SkateboardBuilder\Service\LayerArtwork::class ),
				array( \CreativeBranding\SkateboardBuilder\Admin\PartSpecsPanel::class, 'images', \CreativeBranding\SkateboardBuilder\Port\LayerImages::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\BuilderPage::class, 'saved', \CreativeBranding\SkateboardBuilder\Service\SavedBuilds::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\StateAnswer::class, 'pictures', \Closure::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\Builder::class, 'saved', \CreativeBranding\SkateboardBuilder\Service\SavedBuilds::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\Builder::class, 'remembered', \CreativeBranding\SkateboardBuilder\Front\RememberedWidth::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\SavedBuilds::class, 'money', \CreativeBranding\SkateboardBuilder\Port\Money::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\SpecImport::class, 'check', \CreativeBranding\SkateboardBuilder\Service\LayerCheck::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\SpecImport::class, 'layers', \CreativeBranding\SkateboardBuilder\Port\PartLayers::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\SpecExport::class, 'layers', \CreativeBranding\SkateboardBuilder\Port\PartLayers::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\PageHooks::class, 'address', \CreativeBranding\SkateboardBuilder\Front\BuilderAddress::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\StartButton::class, 'address', \CreativeBranding\SkateboardBuilder\Front\BuilderAddress::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\MyBuildsView::class, 'address', \CreativeBranding\SkateboardBuilder\Front\BuilderAddress::class ),
				array( \CreativeBranding\SkateboardBuilder\Cart\ShowHooks::class, 'address', \CreativeBranding\SkateboardBuilder\Front\BuilderAddress::class ),
				array( \CreativeBranding\SkateboardBuilder\Rest\StateRoute::class, 'address', \CreativeBranding\SkateboardBuilder\Front\BuilderAddress::class ),
				array( \CreativeBranding\SkateboardBuilder\Admin\BuildTab::class, 'place', \CreativeBranding\SkateboardBuilder\Front\BuilderPlace::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\CatalogueCheck::class, 'kept', \CreativeBranding\SkateboardBuilder\Port\PartsKeptOut::class ),
				array( \CreativeBranding\SkateboardBuilder\Port\Kits::class, 'meta', \CreativeBranding\SkateboardBuilder\Port\ProductMeta::class ),
				array( \CreativeBranding\SkateboardBuilder\Front\MyBuildsView::class, 'meta', \CreativeBranding\SkateboardBuilder\Port\ProductMeta::class ),
				array( \CreativeBranding\SkateboardBuilder\Service\ComposedPictures::class, 'layers', \CreativeBranding\SkateboardBuilder\Port\ComposedLayers::class ),
			) as [ $id, $property, $type ]
		) {
			$service = $container->get( $id );
			self::assertInstanceOf( $type, ( new \ReflectionProperty( $service, $property ) )->getValue( $service ), $id );
		}
		self::assertNull( ( new \ReflectionProperty( \CreativeBranding\SkateboardBuilder\Front\StateAnswer::class, 'artwork' ) )->getValue( ( new \ReflectionClass( \CreativeBranding\SkateboardBuilder\Front\StateAnswer::class ) )->newInstanceWithoutConstructor() ), 'none without the container' );
	}

	// D-425: an earlier release's container builds each class of src/ it can, the build's link among them, and resolves
	// a parameter typed with a class by building that class, its default aside. So the link's guide answers must be
	// buildable that way too: no enum in their constructor.
	public function test_the_build_link_and_its_guide_answers_build_as_an_earlier_release_built_them(): void {
		$container = new Container();
		$link      = $container->get( \CreativeBranding\SkateboardBuilder\Front\BuildLink::class );
		self::assertTrue( $link->guide->isEmpty(), 'the link built with no guide answers' );
		self::assertTrue( $container->get( \CreativeBranding\SkateboardBuilder\Front\GuideAnswers::class )->isEmpty(), 'the answers built empty' );
	}

	// D-208: what 1.2.0 adds is given to a copy, so the object it was called on is still what an earlier release's
	// container built: My builds' Open and the start button go to the kit's own page, and My builds reads its parts'
	// meta a part at a time, as 1.1.0 did.
	public function test_each_with_this_release_adds_gives_a_copy_and_leaves_the_one_it_was_called_on_as_it_was(): void {
		$container = new Container();
		$address   = $container->get( \CreativeBranding\SkateboardBuilder\Front\BuilderAddress::class );
		$meta      = $container->get( \CreativeBranding\SkateboardBuilder\Port\ProductMeta::class );
		foreach (
			array(
				array( \CreativeBranding\SkateboardBuilder\Front\StartButton::class, 'address', static fn( object $earlier ): object => $earlier->withAddress( $address ) ),
				array( \CreativeBranding\SkateboardBuilder\Front\MyBuildsView::class, 'address', static fn( object $earlier ): object => $earlier->withAddress( $address ) ),
				array( \CreativeBranding\SkateboardBuilder\Front\MyBuildsView::class, 'meta', static fn( object $earlier ): object => $earlier->withMeta( $meta ) ),
			) as [ $class, $property, $with ]
		) {
			$earlier = ( new \ReflectionClass( $class ) )->newInstanceWithoutConstructor();
			$copy    = $with( $earlier );
			$read    = new \ReflectionProperty( $class, $property );
			self::assertNotNull( $read->getValue( $copy ), $class . ' ' . $property );
			self::assertNull( $read->getValue( $earlier ), $class . ' ' . $property . ': the one it was called on' );
		}
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
		self::assertSame( array( 'schema_1', 'noop_1', 'spec_tables_1', 'schema_2', 'reserved_slot_keys_1', 'spec_tables_2', 'layer_renditions_1', 'schema_3', 'layer_renditions_2' ), $keys );
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
		self::assertCount( 23, $hookables, 'the install\'s, the index\'s, the cards\', the spec tables\', the import\'s, the picture\'s, the kit\'s, the cart\'s eight, the order\'s two, the privacy tools\', the builder\'s, the deck page\'s start button, the saved builds\' and the three abilities\'' );
		self::assertContainsOnlyInstancesOf( Hookable::class, $hookables );
	}

	// Issue #19: a request that saves no part and runs no job built the index's and the import's services all the same.
	public function test_a_request_builds_the_jobs_services_only_when_one_of_their_hooks_fires(): void {
		$container = new Container();
		$container->set( MigrationStore::class, new InMemoryMigrationStore() );
		$container->hookablesFor( Scope::Everywhere );
		foreach ( array( \CreativeBranding\SkateboardBuilder\Service\IndexJobs::class, \CreativeBranding\SkateboardBuilder\Service\SpecImport::class, \CreativeBranding\SkateboardBuilder\Service\SpecIndexer::class, \CreativeBranding\SkateboardBuilder\Port\SpecIndex::class, \CreativeBranding\SkateboardBuilder\Port\PartSpecs::class, \CreativeBranding\SkateboardBuilder\Front\BuilderPage::class, \CreativeBranding\SkateboardBuilder\Front\Builder::class ) as $service ) {
			self::assertFalse( $container->has( $service ), $service );
		}
	}

	// Claude in Cowork's review of milestone 3, minor 9: mutation testing left the plugin's own scopes unpinned.
	public function test_each_scope_builds_its_own_hookables_and_no_others(): void {
		$scopes = array(
			'Everywhere' => array( 'Hooks\\InstallHooks', 'Hooks\\IndexHooks', 'Hooks\\CardHooks', 'Hooks\\SpecTableHooks', 'Hooks\\ImportHooks', 'Hooks\\PictureHooks', 'Hooks\\KitHooks', 'Cart\\CartHooks', 'Cart\\LineHooks', 'Cart\\PriceHooks', 'Cart\\ShowHooks', 'Cart\\ShipHooks', 'Cart\\ExpressHooks', 'Cart\\AgainHooks', 'Cart\\ThumbnailHooks', 'Order\\OrderHooks', 'Order\\EmailHooks', 'Privacy\\PrivacyHooks', 'Front\\PageHooks', 'Front\\SavedBuildHooks', 'Abilities\\CatalogueCheckAbility', 'Abilities\\CheckBuildAbility', 'Abilities\\PriceBuildAbility' ),
			'Admin'      => array( 'Order\\WorkshopHooks', 'Admin\\SettingsHooks', 'Admin\\PartSpecsHooks', 'Admin\\BuildTabHooks', 'Admin\\BuilderHooks', 'Health\\SiteHealth' ),
			'Cli'        => array( 'Hooks\\CliHooks', 'Health\\SiteHealth' ),
			'Cron'       => array( 'Health\\SiteHealth' ),
			'Rest'       => array( 'Health\\SiteHealth', 'Rest\\StateRoute' ),
			'Storefront' => array( 'Front\\StartButton', 'Front\\SeoHooks', 'Front\\ShareHooks' ),
		);
		self::assertSame( array_column( Scope::cases(), 'name' ), array_keys( $scopes ), 'every scope' );
		foreach ( Scope::cases() as $scope ) {
			$built = array_map(
				static fn( Hookable $hookable ): string => substr( $hookable::class, strlen( 'CreativeBranding\\SkateboardBuilder\\' ) ),
				( new Container() )->hookablesFor( $scope )
			);
			self::assertSame( $scopes[ $scope->name ], $built, $scope->name );
		}
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
