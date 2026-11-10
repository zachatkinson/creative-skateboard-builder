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
			Port\Clock::class          => Adapter\SystemClock::class,
			Port\Logger::class         => Adapter\WooCommerceLogger::class,
			Port\Queue::class          => Adapter\ActionSchedulerQueue::class,
			Port\MigrationStore::class => Repository\MigrationRepository::class,
			Port\SpecIndex::class      => Repository\SpecIndexRepository::class,
			Port\ShopSettings::class   => Adapter\OptionShopSettings::class,
			Port\PartCatalogue::class  => Adapter\WooPartCatalogue::class,
			Port\ReindexStore::class   => Adapter\OptionReindexStore::class,
			Port\SpecTables::class     => Adapter\PostSpecTables::class,
			Port\PartSpecs::class      => Adapter\WooPartSpecs::class,
			Port\ImportStore::class    => Adapter\OptionImportStore::class,
			Port\Uploads::class        => Adapter\RequestUploads::class,
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
			Hooks\InstallHooks::class   => array( Scope::Everywhere ),
			// A product is saved, and a job runs, on any kind of request.
			Hooks\IndexHooks::class     => array( Scope::Everywhere ),
			// A post type is registered on every request, and a table is saved from any.
			Hooks\SpecTableHooks::class => array( Scope::Everywhere ),
			// The import's job runs wherever the queue does.
			Hooks\ImportHooks::class    => array( Scope::Everywhere ),
			// WooCommerce's settings screen.
			Admin\SettingsHooks::class  => array( Scope::Admin ),
			Admin\PartSpecsHooks::class => array( Scope::Admin ),
			Admin\BuilderHooks::class   => array( Scope::Admin ),
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
	 * The Hookable classes already handed out.
	 *
	 * @var array<class-string<Hookable>, true>
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
			$this->handedOut[ $id ] = true;
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
