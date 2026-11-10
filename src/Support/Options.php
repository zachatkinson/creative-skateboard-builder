<?php
/**
 * The names of everything the plugin stores outside its tables.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Option, job, meta, transient, cache-group and queue-group names in one place, so the uninstaller, the API
 * snapshot (tools/api-surface.php) and the tests read the same list the code writes to.
 */
final class Options {

	/**
	 * The installed schema version, autoloaded: the migration runner compares it on every request.
	 */
	public const DB_VERSION = 'cbuild_db_version';

	/**
	 * Each migration's state and cursor (Install\Migrations\MigrationState), so an interrupted one resumes.
	 */
	public const MIGRATIONS = 'cbuild_migrations';

	/**
	 * The migration runner's lock: a timestamp from the Clock, taken in one statement (D-021).
	 */
	public const MIGRATION_LOCK = 'cbuild_migration_lock';

	/**
	 * The Action Scheduler action that continues a migration run (D-022).
	 */
	public const JOB_MIGRATE = 'cbuild_migrate';

	/**
	 * Action Scheduler group for every job.
	 */
	public const QUEUE_GROUP = 'creative-skateboard-builder';

	/**
	 * The source every log line carries (wc_get_logger()).
	 */
	public const LOG_SOURCE = 'creative-skateboard-builder';

	/**
	 * Object-cache group for the plugin's caches.
	 */
	public const CACHE_GROUP = 'cbuild';

	/**
	 * Prefix of every transient the plugin sets.
	 */
	public const TRANSIENT_PREFIX = 'cbuild_';

	/**
	 * Site transient: the cached release manifest, for the network (Updates\HttpManifestSource).
	 */
	public const UPDATE_MANIFEST = 'cbuild_update_manifest';

	/**
	 * The shop's settings, one option (docs/SETTINGS.md).
	 *
	 * @since 0.3.0
	 */
	public const SETTINGS = 'cbuild_settings';

	/**
	 * Settings keys: which attribute feeds which spec, and which source wins where two disagree (D-026).
	 *
	 * @since 0.3.0
	 */
	public const SETTING_ATTRIBUTE_MAPPING = 'attribute_mapping';
	public const SETTING_SOURCE_ORDER      = 'source_order';

	/**
	 * Product meta: a part's own specs, a value per spec key.
	 *
	 * @since 0.3.0
	 */
	public const META_SPECS = '_cbuild_specs';

	/**
	 * Product meta: the spec table and the size a part names, as `table` (the table's ID) and `size`.
	 *
	 * @since 0.3.0
	 */
	public const META_SPEC_TABLE = '_cbuild_spec_table';

	/**
	 * The spec tables' post type.
	 *
	 * @since 0.3.0
	 */
	public const POST_TYPE_SPEC_TABLE = 'cbuild_spec_table';

	/**
	 * A spec table's meta: its rows, and the seed it was made from.
	 *
	 * @since 0.3.0
	 */
	public const META_TABLE_ROWS = '_cbuild_rows';
	public const META_TABLE_SEED = '_cbuild_seed';

	/**
	 * The jobs that keep the specs index: one part, and everything, 100 parts at a time (spec: Options and jobs).
	 *
	 * @since 0.3.0
	 */
	public const JOB_INDEX_PART = 'cbuild_index_part';
	public const JOB_REINDEX    = 'cbuild_reindex';

	/**
	 * Where the reindex has got to, so one stopped halfway resumes.
	 *
	 * @since 0.3.0
	 */
	public const REINDEX = 'cbuild_reindex_state';

	/**
	 * Set while a reindex is owed: the plugin was activated on a site with no queue to put it in. Autoloaded, and
	 * removed when the reindex is asked for.
	 *
	 * @since 0.3.0
	 */
	public const REINDEX_OWED = 'cbuild_reindex_owed';

	/**
	 * The job that writes a confirmed specs import, 100 rows at a time.
	 *
	 * @since 0.3.0
	 */
	public const JOB_IMPORT = 'cbuild_import';

	/**
	 * The specs import in hand: where it is, its place and its counts, and what it did after. Not autoloaded.
	 *
	 * @since 0.3.0
	 */
	public const IMPORT = 'cbuild_import_state';

	/**
	 * The rows of the specs import in hand, written once when the file is read and removed when the import ends.
	 * Not autoloaded.
	 *
	 * @since 0.3.0
	 */
	public const IMPORT_ROWS = 'cbuild_import_rows';

	/**
	 * Every fixed-name option, per site.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public static function names(): array {
		return array( self::DB_VERSION, self::MIGRATIONS, self::MIGRATION_LOCK, self::SETTINGS, self::REINDEX, self::REINDEX_OWED, self::IMPORT, self::IMPORT_ROWS );
	}

	/**
	 * Every fixed-name site transient, for the network.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public static function siteTransients(): array {
		return array( self::UPDATE_MANIFEST );
	}

	/**
	 * Every Action Scheduler action the plugin schedules.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public static function jobs(): array {
		return array( self::JOB_MIGRATE, self::JOB_INDEX_PART, self::JOB_REINDEX, self::JOB_IMPORT );
	}

	/**
	 * Every product meta key.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public static function metaKeys(): array {
		return array( self::META_SPECS, self::META_SPEC_TABLE, self::META_TABLE_ROWS, self::META_TABLE_SEED );
	}

	/**
	 * Every post type the plugin registers. Uninstall deletes their posts.
	 *
	 * @since 0.3.0
	 *
	 * @return list<string>
	 */
	public static function postTypes(): array {
		return array( self::POST_TYPE_SPEC_TABLE );
	}

	/**
	 * Every settings key (docs/SETTINGS.md).
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public static function settingsKeys(): array {
		return array( self::SETTING_ATTRIBUTE_MAPPING, self::SETTING_SOURCE_ORDER );
	}
}
