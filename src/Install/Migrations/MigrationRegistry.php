<?php
/**
 * The migrations, in order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

use CreativeBranding\SkateboardBuilder\Support\PluginException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every migration the plugin has shipped, oldest first, and the schema version they add up to. A release that adds
 * a migration appends it here and raises VERSION; nothing is ever removed or reordered, so a site upgrading from any
 * earlier release runs exactly the steps it lacks (docs/AUDIT.md: Upgrades). The container builds it from CLASSES.
 */
final class MigrationRegistry {

	/**
	 * The schema version `cbuild_db_version` holds once every migration below is done.
	 */
	public const VERSION = '1';

	/**
	 * The migrations, oldest first.
	 *
	 * @var list<class-string<Migration>>
	 */
	public const CLASSES = array(
		SchemaMigration::class,
		NoopMigration::class,
	);

	/**
	 * The migrations, oldest first.
	 *
	 * @var list<Migration>
	 */
	private array $migrations;

	/**
	 * Constructor.
	 *
	 * @since 0.1.0
	 *
	 * @param Migration ...$migrations The migrations, oldest first.
	 * @throws PluginException When two migrations share a key.
	 */
	public function __construct( Migration ...$migrations ) {
		$seen = array();
		foreach ( $migrations as $migration ) {
			if ( isset( $seen[ $migration->key() ] ) ) {
				throw new PluginException( esc_html( sprintf( 'Two migrations share the key %s.', $migration->key() ) ) );
			}
			$seen[ $migration->key() ] = $migration;
		}
		$this->migrations = array_values( $migrations );
	}

	/**
	 * The migrations, oldest first.
	 *
	 * @since 0.1.0
	 *
	 * @return list<Migration>
	 */
	public function all(): array {
		return $this->migrations;
	}

	/**
	 * The keys, oldest first.
	 *
	 * @since 0.1.0
	 *
	 * @return list<string>
	 */
	public function keys(): array {
		return array_map( static fn( Migration $m ): string => $m->key(), $this->migrations );
	}
}
