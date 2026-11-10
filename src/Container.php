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
		);
	}

	/**
	 * Hookable classes, in registration order.
	 *
	 * @return class-string<Hookable>[]
	 */
	private static function hookableClasses(): array {
		return array(
			Hooks\InstallHooks::class,
		);
	}

	/**
	 * Built instances by class name.
	 *
	 * @var array<string, object>
	 */
	private array $instances = array();

	/**
	 * The Hookable classes this container builds, in registration order.
	 *
	 * @var class-string<Hookable>[]
	 */
	private array $hookableClasses;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param class-string<Hookable>[]|null $hookableClasses The Hookable classes, for tests; the plugin's own when null.
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
	 * Every class that attaches hooks, built and ready.
	 *
	 * @since 0.1.0
	 *
	 * @return Hookable[]
	 */
	public function hookables(): array {
		$out = array();
		foreach ( $this->hookableClasses as $id ) {
			$out[] = $this->get( $id );
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
