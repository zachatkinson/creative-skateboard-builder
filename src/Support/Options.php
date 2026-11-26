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
	 * The transient set when a layer image's rendition is not made, kept 30 days, for Site Health to say the logs have
	 * the reason (D-214). Uninstall's sweep of the transient prefix takes it.
	 *
	 * @since 0.8.0
	 */
	public const RENDITIONS_FAILED = 'cbuild_renditions_failed';

	/**
	 * The account endpoints the rewrite rules were last made with, so they are made again once when the plugin's
	 * change (D-217). Removed on uninstall.
	 *
	 * @since 0.8.0
	 */
	public const ENDPOINTS = 'cbuild_endpoints';

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
	public const SETTING_BUILD_DISCOUNT    = 'build_discount';
	public const SETTING_FIXED_COUPONS     = 'fixed_coupons';
	public const SETTING_RATE_LIMIT        = 'rate_limit';
	public const SETTING_PROXY_HEADER      = 'trusted_proxy_header';
	public const SETTING_KIT_DEFAULTS      = 'kit_defaults';

	/**
	 * The keys a cart line carries (spec: Data model, Cart items): a kit's line holds the build and its parts'
	 * cart keys, and a part's line its kit's cart key and the slot it fills.
	 */
	public const CART_BUILD    = 'cbuild';
	public const CART_CHILDREN = 'cbuild_children';
	public const CART_PARENT   = 'cbuild_parent';
	public const CART_SLOT     = 'cbuild_slot';

	/**
	 * The classes the plugin puts in a shop's carts, which a theme or a cart drawer may style or group by
	 * (docs/HOOKS.md, Cart): a kit's row, a part's row and its item data, a part's quantity in a classic cart, and a
	 * kit's Edit build link. The script for the Cart and Checkout blocks writes the first two as `cbuild-<role>`.
	 */
	public const CLASS_KIT      = 'cbuild-kit';
	public const CLASS_PART     = 'cbuild-part';
	public const CLASS_QUANTITY = 'cbuild-quantity';
	public const CLASS_EDIT     = 'cbuild-edit';

	/**
	 * The other classes docs/HOOKS.md promises a theme: in the cart, what a kit's line charges for assembly, the
	 * slot's label in a part's Part of words, and a kit's composed picture; in the builder, the places
	 * `cbuild_part_classes` reaches: a part's place in the picture, its layer on the board, the bar's photo, its
	 * line in the review and, from 1.1.0, a ready build's tile picture, with its parts' classes merged.
	 */
	public const CLASS_ASSEMBLY     = 'cbuild-assembly';
	public const CLASS_PART_SLOT    = 'cbuild-part__slot';
	public const CLASS_CART_PICTURE = 'cbuild-cart-picture';
	public const CLASS_PICTURE_ITEM = 'cbuild-picture__item';
	public const CLASS_LAYER        = 'cbuild-board__layer';
	public const CLASS_BAR_PHOTO    = 'cbuild-bar__photo';
	public const CLASS_REVIEW_ITEM  = 'cbuild-review__item';
	public const CLASS_PRESET_PHOTO = 'cbuild-preset__photo';

	/**
	 * The brand chips' "N more" button (#98, D-314).
	 *
	 * @since 1.1.0
	 */
	public const CLASS_CHIPS_MORE = 'cbuild-chips__more';

	/**
	 * "N more" with nothing to open (#98).
	 *
	 * @since 1.1.0
	 */
	public const CLASS_CHIPS_MORE_IDLE = 'cbuild-chips__more--idle';

	/**
	 * A brand chip past the row (#98).
	 *
	 * @since 1.1.0
	 */
	public const CLASS_CHIP_PAST = 'cbuild-chips__chip--past';

	/**
	 * The brand chips' row, where its group has "N more" (#98, D-314).
	 *
	 * @since 1.1.0
	 */
	public const CLASS_CHIPS_ROW = 'cbuild-chips__options--row';

	/**
	 * The deck step's compact card lists (#99, D-322).
	 *
	 * @since 1.1.0
	 */
	public const CLASS_CARDS_COMPACT = 'cbuild-cards--compact';

	/**
	 * A card's picture drawn from the deck's layer image (#99).
	 *
	 * @since 1.1.0
	 */
	public const CLASS_CARD_DRAWN = 'cbuild-card__photo--drawn';

	/**
	 * A part's card in a step, where `cbuild_part_classes` lands (#143, D-376).
	 *
	 * @since 1.2.0
	 */
	public const CLASS_CARD = 'cbuild-card';

	/**
	 * A card's own radio (#143).
	 *
	 * @since 1.2.0
	 */
	public const CLASS_CARD_CONTROL = 'cbuild-card__control';

	/**
	 * The picture's root, on the stage (#143).
	 *
	 * @since 1.2.0
	 */
	public const CLASS_PICTURE = 'cbuild-picture';

	/**
	 * The stage, around the picture (#143, D-376).
	 *
	 * @since 1.2.0
	 */
	public const CLASS_STAGE = 'cbuild__stage';

	/**
	 * A step's form (#143, D-376).
	 *
	 * @since 1.2.0
	 */
	public const CLASS_STEP = 'cbuild-step';

	/**
	 * A part's thumbnail on its line in the review, where `cbuild_part_classes` lands too (#132, D-403).
	 *
	 * @since 1.3.0
	 */
	public const CLASS_REVIEW_PHOTO = 'cbuild-review__photo';

	/**
	 * A review thumbnail drawn from the deck's layer image, as its card's is (D-398).
	 *
	 * @since 1.3.0
	 */
	public const CLASS_REVIEW_DRAWN = 'cbuild-review__photo--drawn';

	/**
	 * The place just above a step's card list, which the builder renders empty for another plugin's own element
	 * (#151, D-407).
	 *
	 * @since 1.3.0
	 */
	public const CLASS_ABOVE_CARDS = 'cbuild-step__above-cards';

	/**
	 * The place just above the guide's builds, which the builder renders empty for another plugin's own element
	 * (F41, #164).
	 *
	 * @since 1.4.0
	 */
	public const CLASS_ABOVE_BUILDS = 'cbuild-guide__above-builds';

	/**
	 * A ready build's tile picture when it is the deck's layer image rather than its photo, as a build Help me choose
	 * recommends shows its deck's card picture (D-408).
	 *
	 * @since 1.3.0
	 */
	public const CLASS_PRESET_DRAWN = 'cbuild-preset__photo--drawn';

	/**
	 * The 3D view's classes, which docs/HOOKS.md offers a theme (#139, D-495): View in 3D, the open view, the canvas's
	 * box, the canvas, the camera's buttons, the trick buttons and the message where it can't be shown.
	 *
	 * @since 1.4.0
	 */
	public const CLASS_THREE_OPEN   = 'cbuild-3d__open';
	public const CLASS_THREE_STAGE  = 'cbuild-3d__stage';
	public const CLASS_THREE_FRAME  = 'cbuild-3d__frame';
	public const CLASS_THREE_CANVAS = 'cbuild-3d__canvas';
	public const CLASS_THREE_PLACES = 'cbuild-3d__places';
	public const CLASS_THREE_TRICKS = 'cbuild-3d__tricks';
	public const CLASS_THREE_FAILED = 'cbuild-3d__failed';

	/**
	 * Order item meta (spec: Data model, Order items). Hidden from customers by their underscore, and kept with the
	 * shop's order records for good: a key is never renamed.
	 */
	public const ORDER_BUILD    = '_cbuild_id';
	public const ORDER_ROLE     = '_cbuild_role';
	public const ORDER_PARENT   = '_cbuild_parent';
	public const ORDER_CHILDREN = '_cbuild_children';
	public const ORDER_SLOT     = '_cbuild_slot';
	public const ORDER_SNAPSHOT = '_cbuild_snapshot';

	/**
	 * What `_cbuild_role` holds: the kit's item, or a part's.
	 */
	public const ROLE_KIT  = 'kit';
	public const ROLE_PART = 'part';

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
	 * Product meta: a part's layer image for the picture, an attachment's ID (spec: Data model; D-190).
	 *
	 * @since 0.7.0
	 */
	public const META_LAYER = '_cbuild_layer';

	/**
	 * Product meta: "Not offered in the builder", ticked on a part's Part specs (#116, D-351). `yes` when ticked; a
	 * part with none is offered.
	 *
	 * @since 1.2.0
	 */
	public const META_NOT_OFFERED = '_cbuild_not_offered';

	/**
	 * Product meta: the shop's rank for a part in its slot, a whole number, higher being better, from its Part specs
	 * (#135, D-395). A part with none is unranked.
	 *
	 * @since 1.3.0
	 */
	public const META_RANK = '_cbuild_rank';

	/**
	 * The kit's product type.
	 *
	 * @since 0.4.0
	 */
	public const PRODUCT_TYPE_KIT = 'cbuild_kit';

	/**
	 * The Builder block's name, which a page's content holds as `<!-- wp:creative-skateboard-builder/builder`.
	 *
	 * @since 1.2.0
	 */
	public const BLOCK = 'creative-skateboard-builder/builder';

	/**
	 * A kit's meta: its four documents, each as JSON text (docs/HOOKS.md, Document schemas).
	 *
	 * @since 0.4.0
	 */
	public const META_KIT_SLOTS    = '_cbuild_slots';
	public const META_KIT_RULES    = '_cbuild_rules';
	public const META_KIT_PRESETS  = '_cbuild_presets';
	public const META_KIT_ASSEMBLY = '_cbuild_assembly';

	/**
	 * A kit's meta: its picture settings, as JSON text (schema/kit-picture.json). A kit may have none.
	 *
	 * @since 0.7.0
	 */
	public const META_KIT_PICTURE = '_cbuild_picture';

	/**
	 * A kit's meta: its layout settings, as JSON text (schema/kit-layout.json). A kit may have none (D-255).
	 *
	 * @since 1.0.0
	 */
	public const META_KIT_LAYOUT = '_cbuild_layout';

	/**
	 * A kit's meta: its settings from 1.2.0 on, as JSON text (schema/kit-settings.json). A kit may have none. A new
	 * kit setting goes here, never as a new key in an older document, so a rollback still reads the kit (D-354).
	 *
	 * @since 1.2.0
	 */
	public const META_KIT_SETTINGS = '_cbuild_settings';

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
	 * The job that draws one build's composed pictures (spec: Options and jobs).
	 *
	 * @since 0.7.0
	 */
	public const JOB_COMPOSE_PICTURE = 'cbuild_compose_picture';

	/**
	 * The daily job that deletes composed pictures unused for 30 days and keeps their folder under its cap.
	 *
	 * @since 0.7.0
	 */
	public const JOB_PRUNE_PICTURES = 'cbuild_prune_pictures';

	/**
	 * The plugin's folder in the uploads folder: the layers' renditions and the composed pictures. Uninstall removes it.
	 *
	 * @since 0.7.0
	 */
	public const UPLOADS_FOLDER = 'creative-skateboard-builder';

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
		return array( self::DB_VERSION, self::MIGRATIONS, self::MIGRATION_LOCK, self::SETTINGS, self::REINDEX, self::REINDEX_OWED, self::IMPORT, self::IMPORT_ROWS, self::ENDPOINTS );
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
		return array( self::JOB_MIGRATE, self::JOB_INDEX_PART, self::JOB_REINDEX, self::JOB_IMPORT, self::JOB_COMPOSE_PICTURE, self::JOB_PRUNE_PICTURES );
	}

	/**
	 * Every product meta key.
	 *
	 * @since 0.1.0
	 *
	 * @return string[]
	 */
	public static function metaKeys(): array {
		return array( self::META_SPECS, self::META_SPEC_TABLE, self::META_LAYER, self::META_NOT_OFFERED, self::META_RANK, self::META_TABLE_ROWS, self::META_TABLE_SEED, ...self::kitDocuments() );
	}

	/**
	 * The meta keys of a kit's documents: the four, the picture's from 0.7.0, the layout's from 1.0.0 and the settings'
	 * from 1.2.0.
	 *
	 * @since 0.4.0
	 *
	 * @return list<string>
	 */
	public static function kitDocuments(): array {
		return array( self::META_KIT_SLOTS, self::META_KIT_RULES, self::META_KIT_PRESETS, self::META_KIT_ASSEMBLY, self::META_KIT_PICTURE, self::META_KIT_LAYOUT, self::META_KIT_SETTINGS );
	}

	/**
	 * A kit's documents' meta keys, by the name the kit's services and schemas know each by.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, string>
	 */
	public static function kitDocumentNames(): array {
		return array(
			'slots'    => self::META_KIT_SLOTS,
			'rules'    => self::META_KIT_RULES,
			'presets'  => self::META_KIT_PRESETS,
			'assembly' => self::META_KIT_ASSEMBLY,
			'picture'  => self::META_KIT_PICTURE,
			'layout'   => self::META_KIT_LAYOUT,
			'settings' => self::META_KIT_SETTINGS,
		);
	}

	/**
	 * Every class docs/HOOKS.md promises a theme, in the cart and in the builder.
	 *
	 * @since 0.6.0
	 *
	 * @return list<string>
	 */
	public static function cssClasses(): array {
		return array(
			self::CLASS_KIT,
			self::CLASS_PART,
			self::CLASS_QUANTITY,
			self::CLASS_EDIT,
			self::CLASS_ASSEMBLY,
			self::CLASS_PART_SLOT,
			self::CLASS_CART_PICTURE,
			self::CLASS_PICTURE_ITEM,
			self::CLASS_LAYER,
			self::CLASS_BAR_PHOTO,
			self::CLASS_REVIEW_ITEM,
			self::CLASS_PRESET_PHOTO,
			self::CLASS_CHIPS_MORE,
			self::CLASS_CHIPS_MORE_IDLE,
			self::CLASS_CHIP_PAST,
			self::CLASS_CHIPS_ROW,
			self::CLASS_CARDS_COMPACT,
			self::CLASS_CARD_DRAWN,
			self::CLASS_CARD,
			self::CLASS_CARD_CONTROL,
			self::CLASS_PICTURE,
			self::CLASS_STAGE,
			self::CLASS_STEP,
			self::CLASS_REVIEW_PHOTO,
			self::CLASS_REVIEW_DRAWN,
			self::CLASS_ABOVE_CARDS,
			self::CLASS_ABOVE_BUILDS,
			self::CLASS_PRESET_DRAWN,
			self::CLASS_THREE_OPEN,
			self::CLASS_THREE_STAGE,
			self::CLASS_THREE_FRAME,
			self::CLASS_THREE_CANVAS,
			self::CLASS_THREE_PLACES,
			self::CLASS_THREE_TRICKS,
			self::CLASS_THREE_FAILED,
		);
	}

	/**
	 * Every key the plugin puts on a cart line.
	 *
	 * @since 0.5.0
	 *
	 * @return list<string>
	 */
	public static function cartKeys(): array {
		return array( self::CART_BUILD, self::CART_CHILDREN, self::CART_PARENT, self::CART_SLOT );
	}

	/**
	 * Every field the Store API gives of a cart item under the plugin's namespace in `extensions`.
	 *
	 * @since 0.5.0
	 *
	 * @return list<string>
	 */
	public static function cartItemFields(): array {
		return array( 'role', 'build', 'parent', 'slot', 'name', 'removed' );
	}

	/**
	 * Every key the plugin puts on an order item.
	 *
	 * @since 0.5.0
	 *
	 * @return list<string>
	 */
	public static function orderItemKeys(): array {
		return array( self::ORDER_BUILD, self::ORDER_ROLE, self::ORDER_PARENT, self::ORDER_CHILDREN, self::ORDER_SLOT, self::ORDER_SNAPSHOT );
	}

	/**
	 * Every product type the plugin adds.
	 *
	 * @since 0.4.0
	 *
	 * @return list<string>
	 */
	public static function productTypes(): array {
		return array( self::PRODUCT_TYPE_KIT );
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
		return array( self::SETTING_ATTRIBUTE_MAPPING, self::SETTING_SOURCE_ORDER, self::SETTING_BUILD_DISCOUNT, self::SETTING_FIXED_COUPONS, self::SETTING_RATE_LIMIT, self::SETTING_PROXY_HEADER, self::SETTING_KIT_DEFAULTS );
	}
}
