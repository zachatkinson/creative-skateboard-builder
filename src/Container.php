<?php
/**
 * Service container.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder;

use CreativeBranding\SkateboardBuilder\Support\PluginException;
use ReflectionClass;
use ReflectionNamedType;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Builds the plugin's services once and hands them out. Hand-written, no magic: constructor arguments are resolved by
 * class name, scalars take their default, ports are bound to their adapters in `bindings()`, and the few services
 * that need more than that are built in `factories()`. The container is the only place the adapters are named, so the
 * presentation layers depend on ports alone (docs/STANDARDS.md: Layers; D-020).
 */
final class Container {

	/**
	 * Port to adapter.
	 *
	 * @return array<class-string, class-string>
	 */
	private static function bindings(): array {
		return array(
			Port\Clock::class           => Adapter\SystemClock::class,
			Port\Logger::class          => Adapter\WooCommerceLogger::class,
			Port\Queue::class           => Adapter\ActionSchedulerQueue::class,
			Port\MigrationStore::class  => Repository\MigrationRepository::class,
			Port\SpecIndex::class       => Repository\SpecIndexRepository::class,
			Port\ShopSettings::class    => Adapter\OptionShopSettings::class,
			Port\PartCatalogue::class   => Adapter\WooPartCatalogue::class,
			Port\ReindexStore::class    => Adapter\OptionReindexStore::class,
			Port\SpecTables::class      => Adapter\PostSpecTables::class,
			Port\PartSpecs::class       => Adapter\WooPartSpecs::class,
			Port\ImportStore::class     => Adapter\OptionImportStore::class,
			Port\Uploads::class         => Adapter\RequestUploads::class,
			Port\Kits::class            => Adapter\WooKits::class,
			Port\PartCards::class       => Adapter\WooPartCards::class,
			Port\PartFacts::class       => Repository\PartFactsRepository::class,
			Port\Tally::class           => Adapter\CacheTally::class,
			Port\Money::class           => Adapter\ShopMoney::class,
			Port\DocumentSchemas::class => Adapter\KitSchemas::class,
			Port\Files::class           => Adapter\DiskFiles::class,
			Port\Reindex::class         => Service\IndexJobs::class,
			Port\Cart::class            => Adapter\WooCart::class,
			Port\Uuids::class           => Adapter\WpUuids::class,
		);
	}

	/**
	 * Services built by hand: those whose constructor takes a list.
	 *
	 * @return array<class-string, callable(Container): object>
	 */
	private static function factories(): array {
		return array(
			Install\Migrations\MigrationRegistry::class => static function ( Container $c ): object {
				$migrations = array();
				foreach ( Install\Migrations\MigrationRegistry::CLASSES as $class ) {
					$migrations[] = $c->get( $class );
				}
				return new Install\Migrations\MigrationRegistry( ...$migrations );
			},
			// The index's services are built when a migration finishes, not on every request.
			Hooks\InstallHooks::class                   => static function ( Container $c ): object {
				return new Hooks\InstallHooks(
					$c->get( Install\Migrations\MigrationRunner::class ),
					$c->get( Install\Migrations\InlineRule::class ),
					static function () use ( $c ): Port\Reindex {
						return $c->get( Port\Reindex::class );
					}
				);
			},
			// The settings tab extends a class WooCommerce loads on its settings screen alone, so it is built when asked for.
			Admin\SettingsHooks::class                  => static function ( Container $c ): object {
				return new Admin\SettingsHooks(
					static function () use ( $c ): object {
						return $c->get( Admin\SettingsPage::class );
					}
				);
			},
			// The Builder page's screen is built when the page is asked for.
			Admin\BuilderHooks::class                   => static function ( Container $c ): object {
				return new Admin\BuilderHooks(
					static function () use ( $c ): Admin\SpecTablesScreen {
						return $c->get( Admin\SpecTablesScreen::class );
					},
					static function () use ( $c ): Admin\ImportScreen {
						return $c->get( Admin\ImportScreen::class );
					},
					static function () use ( $c ): Admin\CheckScreen {
						return $c->get( Admin\CheckScreen::class );
					}
				);
			},
			// Nothing is built until a test runs or the Info tab is drawn.
			Health\SiteHealth::class                    => static function ( Container $c ): object {
				return new Health\SiteHealth(
					static function () use ( $c ): Service\Health {
						return $c->get( Service\Health::class );
					},
					static function () use ( $c ): Port\ShopSettings {
						return $c->get( Port\ShopSettings::class );
					},
					static function () use ( $c ): Port\MigrationStore {
						return $c->get( Port\MigrationStore::class );
					},
					static function () use ( $c ): Service\CatalogueCheck {
						return $c->get( Service\CatalogueCheck::class );
					}
				);
			},
			// The check is built when the ability is run.
			Abilities\CatalogueCheckAbility::class      => static function ( Container $c ): object {
				return new Abilities\CatalogueCheckAbility(
					static function () use ( $c ): Service\CatalogueCheck {
						return $c->get( Service\CatalogueCheck::class );
					}
				);
			},
			// A build's services are built when an ability is run.
			Abilities\CheckBuildAbility::class          => static function ( Container $c ): object {
				return new Abilities\CheckBuildAbility(
					static function () use ( $c ): Port\Kits {
						return $c->get( Port\Kits::class );
					},
					static function () use ( $c ): Service\BuildState {
						return $c->get( Service\BuildState::class );
					},
					static function () use ( $c ): Front\StateAnswer {
						return $c->get( Front\StateAnswer::class );
					},
					static function () use ( $c ): Port\Cart {
						return $c->get( Port\Cart::class );
					}
				);
			},
			Abilities\PriceBuildAbility::class          => static function ( Container $c ): object {
				return new Abilities\PriceBuildAbility(
					static function () use ( $c ): Port\Kits {
						return $c->get( Port\Kits::class );
					},
					static function () use ( $c ): Service\BuildState {
						return $c->get( Service\BuildState::class );
					},
					static function () use ( $c ): Front\StateAnswer {
						return $c->get( Front\StateAnswer::class );
					},
					static function () use ( $c ): Port\Cart {
						return $c->get( Port\Cart::class );
					}
				);
			},
			// The kit's class is an adapter's, which a Hookable may not name.
			Hooks\KitHooks::class                       => static function ( Container $c ): object {
				return new Hooks\KitHooks(
					Adapter\KitProduct::class,
					new Service\KitVisibility(
						Support\Options::PRODUCT_TYPE_KIT,
						static function () use ( $c ): array {
							return $c->get( Port\Kits::class )->ids();
						}
					)
				);
			},
			// The jobs and the import are built when a part is saved or a job runs, not on every request.
			Hooks\IndexHooks::class                     => static function ( Container $c ): object {
				return new Hooks\IndexHooks(
					static function () use ( $c ): Service\IndexJobs {
						return $c->get( Service\IndexJobs::class );
					},
					$c->get( Service\SavedSources::class )
				);
			},
			// The cart's service is built when a kit is on its way to the cart or in it.
			Cart\CartHooks::class                       => static function ( Container $c ): object {
				return new Cart\CartHooks(
					static function () use ( $c ): Service\BuildCart {
						return $c->get( Service\BuildCart::class );
					},
					$c->get( Cart\Refusals::class )
				);
			},
			Cart\LineHooks::class                       => static function ( Container $c ): object {
				return new Cart\LineHooks(
					static function () use ( $c ): Service\BuildLines {
						return $c->get( Service\BuildLines::class );
					},
					$c->get( Cart\Refusals::class )
				);
			},
			Cart\AgainHooks::class                      => static function ( Container $c ): object {
				return new Cart\AgainHooks(
					static function () use ( $c ): Service\BuildCart {
						return $c->get( Service\BuildCart::class );
					}
				);
			},
			Cart\PriceHooks::class                      => static function ( Container $c ): object {
				return new Cart\PriceHooks(
					static function () use ( $c ): Service\BuildCart {
						return $c->get( Service\BuildCart::class );
					}
				);
			},
			Order\OrderHooks::class                     => static function ( Container $c ): object {
				return new Order\OrderHooks(
					static function () use ( $c ): Service\OrderBuilds {
						return $c->get( Service\OrderBuilds::class );
					},
					static function () use ( $c ): Port\Kits {
						return $c->get( Port\Kits::class );
					}
				);
			},
			Front\Delivery::class                       => static function ( Container $c ): object {
				return new Front\Delivery( $c->get( Port\Kits::class ) );
			},
			Cart\ShowHooks::class                       => static function ( Container $c ): object {
				return new Cart\ShowHooks(
					static function () use ( $c ): Port\Kits {
						return $c->get( Port\Kits::class );
					}
				);
			},
			Cart\Refusals::class                        => static function ( Container $c ): object {
				return new Cart\Refusals(
					static function () use ( $c ): Port\Kits {
						return $c->get( Port\Kits::class );
					}
				);
			},
			Service\SavedSources::class                 => static function (): object {
				return new Service\SavedSources( array( Support\Options::META_SPECS, Support\Options::META_SPEC_TABLE ) );
			},
			// The builder's services are built when a page shows a builder, not on every request.
			Front\PageHooks::class                      => static function ( Container $c ): object {
				return new Front\PageHooks(
					static function () use ( $c ): Front\BuilderPage {
						return $c->get( Front\BuilderPage::class );
					}
				);
			},
			// The state route's services are built when the route is asked, not whenever the REST API starts.
			Rest\StateRoute::class                      => static function ( Container $c ): object {
				return new Rest\StateRoute(
					$c->get( Port\Kits::class ),
					static function () use ( $c ): Service\BuildState {
						return $c->get( Service\BuildState::class );
					},
					static function () use ( $c ): Front\StateAnswer {
						return $c->get( Front\StateAnswer::class );
					},
					static function () use ( $c ): Service\RateLimit {
						return $c->get( Service\RateLimit::class );
					},
					static function () use ( $c ): Port\Cart {
						return $c->get( Port\Cart::class );
					}
				);
			},
			// The cards are built when something a card shows moves.
			Hooks\CardHooks::class                      => static function ( Container $c ): object {
				return new Hooks\CardHooks(
					static function () use ( $c ): Port\PartCards {
						return $c->get( Port\PartCards::class );
					},
					static function () use ( $c ): Port\Kits {
						return $c->get( Port\Kits::class );
					}
				);
			},
			Hooks\SpecTableHooks::class                 => static function ( Container $c ): object {
				return new Hooks\SpecTableHooks(
					static function () use ( $c ): Service\IndexJobs {
						return $c->get( Service\IndexJobs::class );
					}
				);
			},
			Hooks\ImportHooks::class                    => static function ( Container $c ): object {
				return new Hooks\ImportHooks(
					static function () use ( $c ): Service\SpecImport {
						return $c->get( Service\SpecImport::class );
					}
				);
			},
			// The panel reads the sources, so it is built when one of its hooks fires.
			Admin\PartSpecsHooks::class                 => static function ( Container $c ): object {
				return new Admin\PartSpecsHooks(
					static function () use ( $c ): Admin\PartSpecsPanel {
						return $c->get( Admin\PartSpecsPanel::class );
					}
				);
			},
			// The tab reads the kit and its parts, so it is built when one of its hooks fires.
			Admin\BuildTabHooks::class                  => static function ( Container $c ): object {
				return new Admin\BuildTabHooks(
					static function () use ( $c ): Admin\BuildTab {
						return $c->get( Admin\BuildTab::class );
					}
				);
			},
			// The sources, each asked once per batch.
			Service\SpecIndexer::class                  => static fn( Container $c ): object => new Service\SpecIndexer(
				$c->get( Port\SpecIndex::class ),
				$c->get( Port\ShopSettings::class ),
				$c->get( Port\Clock::class ),
				$c->get( Adapter\TableSpecSource::class ),
				$c->get( Adapter\FieldSpecSource::class ),
				$c->get( Adapter\AttributeSpecSource::class )
			),
		);
	}

	/**
	 * Hookable classes in registration order, each with the scopes its hooks can fire in (D-067).
	 *
	 * @return array<class-string<Hookable>, list<Scope>>
	 */
	private static function hookableClasses(): array {
		return array(
			// `init`, `admin_init`, the migration job and a network's deleted site: any request.
			Hooks\InstallHooks::class              => array( Scope::Everywhere ),
			// A product is saved, and a job runs, on any kind of request.
			Hooks\IndexHooks::class                => array( Scope::Everywhere ),
			// A product is saved, sold or restocked on any kind of request.
			Hooks\CardHooks::class                 => array( Scope::Everywhere ),
			// A post type is registered on every request, and a table is saved from any.
			Hooks\SpecTableHooks::class            => array( Scope::Everywhere ),
			// The import's job runs wherever the queue does.
			Hooks\ImportHooks::class               => array( Scope::Everywhere ),
			// WooCommerce builds a product from its type's class wherever one is read.
			Hooks\KitHooks::class                  => array( Scope::Everywhere ),
			// A build is added from a page, the Store API or admin-ajax, and the cart is read on all three.
			Cart\CartHooks::class                  => array( Scope::Everywhere ),
			Cart\LineHooks::class                  => array( Scope::Everywhere ),
			Cart\PriceHooks::class                 => array( Scope::Everywhere ),
			Cart\ShowHooks::class                  => array( Scope::Everywhere ),
			Cart\ShipHooks::class                  => array( Scope::Everywhere ),
			Cart\ExpressHooks::class               => array( Scope::Everywhere ),
			Cart\AgainHooks::class                 => array( Scope::Everywhere ),
			// Both checkouts make the order, and its items are named on pages, in emails and in wp-admin.
			Order\OrderHooks::class                => array( Scope::Everywhere ),
			// WooCommerce's settings screen.
			Admin\SettingsHooks::class             => array( Scope::Admin ),
			Admin\PartSpecsHooks::class            => array( Scope::Admin ),
			Admin\BuildTabHooks::class             => array( Scope::Admin ),
			Admin\BuilderHooks::class              => array( Scope::Admin ),
			Hooks\CliHooks::class                  => array( Scope::Cli ),
			// Site Health's page and Info tab are wp-admin's; its weekly check runs in cron, and WP-CLI can ask too. The
			// page asks for the Catalogue check's test over REST.
			Health\SiteHealth::class               => array( Scope::Admin, Scope::Cron, Scope::Cli, Scope::Rest ),
			// The state route, which the builder's script asks.
			Rest\StateRoute::class                 => array( Scope::Rest ),
			// The block is registered on every request, the editor's and the REST renderer's among them.
			Front\PageHooks::class                 => array( Scope::Everywhere ),
			// Robots and the canonical are printed in the head of a page the site shows a visitor.
			Front\SeoHooks::class                  => array( Scope::Storefront ),
			// WordPress builds its abilities when something first asks for one, on any kind of request.
			Abilities\CatalogueCheckAbility::class => array( Scope::Everywhere ),
			Abilities\CheckBuildAbility::class     => array( Scope::Everywhere ),
			Abilities\PriceBuildAbility::class     => array( Scope::Everywhere ),
		);
	}

	/**
	 * Built instances by class name.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * The Hookable classes this container builds, in registration order, each with its scopes.
	 *
	 * @var array<class-string<Hookable>, list<Scope>>
	 */
	private array $hookableClasses;

	/**
	 * The Hookable classes already handed out, each under its own name.
	 *
	 * @var array<class-string<Hookable>, class-string<Hookable>>
	 */
	private array $handedOut = array();

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param array<class-string<Hookable>, list<Scope>>|null $hookableClasses The Hookable classes and their scopes, for tests; the plugin's own when null.
	 */
	public function __construct( ?array $hookableClasses = null ) {
		$this->hookableClasses = $hookableClasses ?? self::hookableClasses();
	}

	/**
	 * Get (building on first use) a service.
	 *
	 * @since 0.1.0
	 *
	 * @template T of object
	 * @param class-string<T> $id Service class or interface.
	 * @return T
	 */
	public function get( string $id ) {
		/** @var T $instance */
		$instance = $this->resolve( $id );
		return $instance;
	}

	/**
	 * Get (building on first use) a service, untyped.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Service class or interface.
	 */
	public function resolve( string $id ): object {
		if ( ! isset( $this->instances[ $id ] ) ) {
			$this->instances[ $id ] = $this->build( $id );
		}
		return $this->instances[ $id ];
	}

	/**
	 * Replace a service, for tests. Not part of the public API: another plugin changes behaviour through the hooks in
	 * docs/HOOKS.md.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id       Service class or interface.
	 * @param object $instance Replacement.
	 */
	public function set( string $id, object $instance ): void {
		$this->instances[ $id ] = $instance;
	}

	/**
	 * Whether a service has been built or set.
	 *
	 * @since 0.1.0
	 *
	 * @param string $id Service class or interface.
	 */
	public function has( string $id ): bool {
		return isset( $this->instances[ $id ] );
	}

	/**
	 * The classes that attach hooks in any of the scopes given, built and ready, in registration order. Each is handed
	 * out once, so a Hookable with two scopes is not registered twice when a request turns out to be in both. One
	 * with no scope the request is in is never built.
	 *
	 * @since 0.3.0
	 *
	 * @param Scope ...$scopes The scopes the request is in.
	 * @return list<Hookable>
	 */
	public function hookablesFor( Scope ...$scopes ): array {
		$out = array();
		foreach ( $this->hookableClasses as $id => $fires ) {
			if ( isset( $this->handedOut[ $id ] ) || array() === array_intersect( array_column( $fires, 'name' ), array_column( $scopes, 'name' ) ) ) {
				continue;
			}
			$this->handedOut[ $id ] = $id;
			$out[]                  = $this->get( $id );
		}
		return $out;
	}

	/**
	 * Build one service with its constructor dependencies resolved from this container.
	 *
	 * @param string $id Service class or interface.
	 * @throws PluginException When the class or a constructor argument can't be resolved.
	 */
	private function build( string $id ): object {
		$factory = self::factories()[ $id ] ?? null;
		if ( null !== $factory ) {
			return $factory( $this );
		}
		$class = self::bindings()[ $id ] ?? $id;
		if ( ! class_exists( $class ) && ! interface_exists( $class ) ) {
			throw new PluginException( esc_html( sprintf( 'Unknown service %s.', $id ) ) );
		}
		$reflection = new ReflectionClass( $class );
		if ( ! $reflection->isInstantiable() ) {
			throw new PluginException( esc_html( sprintf( 'Service %s is not instantiable and has no binding.', $id ) ) );
		}
		$constructor = $reflection->getConstructor();
		if ( null === $constructor ) {
			return new $class();
		}
		$args = array();
		foreach ( $constructor->getParameters() as $parameter ) {
			$type = $parameter->getType();
			if ( $type instanceof ReflectionNamedType && ! $type->isBuiltin() ) {
				/** @var class-string $dependency */
				$dependency = $type->getName();
				$args[]     = $this->resolve( $dependency );
				continue;
			}
			if ( $parameter->isDefaultValueAvailable() ) {
				$args[] = $parameter->getDefaultValue();
				continue;
			}
			throw new PluginException( esc_html( sprintf( 'Cannot resolve parameter $%s of %s.', $parameter->getName(), $class ) ) );
		}
		return $reflection->newInstanceArgs( $args );
	}
}
