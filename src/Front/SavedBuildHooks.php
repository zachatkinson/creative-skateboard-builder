<?php
/**
 * Saved builds in My account, and the forms that keep, rename and delete them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedPrices;
use DateTimeImmutable;
use DateTimeZone;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * My builds (spec: Saved builds; roadmap, milestone 8). The endpoint, its menu item and its title are WooCommerce's
 * own (checked in WooCommerce 11.3.0): `woocommerce_get_query_vars` (includes/class-wc-query.php:238), which WooCommerce
 * makes a rewrite endpoint of on `init`; `woocommerce_account_menu_items` (includes/wc-account-functions.php:138);
 * `woocommerce_endpoint_{endpoint}_title` (includes/class-wc-query.php:183); `woocommerce_account_{endpoint}_endpoint`
 * (includes/wc-template-functions.php:3829).
 *
 * Save build, Rename and Delete are POST forms with a nonce each, sent to My builds and answered on
 * `template_redirect`, where WooCommerce answers its own account forms (includes/class-wc-form-handler.php:50), so
 * they work without JavaScript and their notices go through the customer's session. Each asks the service for the
 * signed-in customer's builds alone (D-217).
 *
 * @since 0.8.0
 */
final class SavedBuildHooks implements Hookable {

	/**
	 * The endpoint, under My account.
	 *
	 * @since 0.8.0
	 */
	public const string ENDPOINT = 'my-builds';

	/**
	 * My builds' rules: the picture on its own line, its name under it, shown on a phone too, and the compare.
	 *
	 * The compare (#136, D-411): a column a build at least 14rem wide, so a phone shows about one build at a time and
	 * the region scrolls sideways, its focus ring drawn as the theme's text. A row that differs is tinted, and the
	 * lowest total, beside the words that say each; the tint is mixed with transparency, so the theme's text keeps its
	 * contrast on a light or a dark page. Under forced colours the tint is gone and the words and the mark's border stay.
	 */
	private const string STYLES = '.cbuild-my-builds__build th img{display:block;margin:0 0 8px}'
		// A phone's responsive table hides its row headers (WooCommerce's woocommerce-smallscreen.css), and the build's
		// name is its row's header: shown, so each row says which build its controls are for (D-412).
		. 'table.cbuild-my-builds.shop_table_responsive tbody tr th[scope=row]{display:block;text-align:start}'
		. '.cbuild-my-builds__pick{display:flex;align-items:center;gap:.5em;margin-block-end:.5em}'
		. '.cbuild-compare{overflow-x:auto;margin-block:1em}'
		. '.cbuild-compare:focus-visible{outline:2px solid currentColor;outline-offset:2px}'
		. '.cbuild-compare__table{inline-size:auto;min-inline-size:100%;margin:0}'
		. '.cbuild-compare__table caption{text-align:start;font-weight:600;padding-block:.5em}'
		. '.cbuild-compare__table th,.cbuild-compare__table td{vertical-align:top;text-align:start}'
		. '.cbuild-compare__build{min-width:14rem}'
		. '.cbuild-compare__part,.cbuild-compare__price,.cbuild-compare__note,.cbuild-compare__open,.cbuild-compare__name{display:block}'
		. '.cbuild-compare__note{font-size:.875em;margin-block-start:.25em}'
		. '.cbuild-compare__open{font-weight:400}'
		. '.cbuild-compare__mark{display:inline-block;margin-inline-start:.5em;padding:0 .375em;border:1px solid currentColor;border-radius:.25em;font-size:.875em;font-weight:600}'
		. '.cbuild-compare__differs{background:color-mix(in srgb,#e0a800 20%,transparent)}'
		. '.cbuild-compare__lowest{background:color-mix(in srgb,#2e8540 20%,transparent);font-weight:600}';

	/**
	 * The field that says which form was sent.
	 *
	 * @since 0.8.0
	 */
	public const string FIELD = 'cbuild_build_action';

	/**
	 * Save build, from the review. Its nonce action.
	 *
	 * @since 0.8.0
	 */
	public const string SAVE = 'cbuild_save_build';

	/**
	 * Rename, from My builds. Its nonce action is this and the build's UUID.
	 *
	 * @since 0.8.0
	 */
	public const string RENAME = 'cbuild_rename_build';

	/**
	 * Delete, from My builds. Its nonce action is this and the build's UUID.
	 *
	 * @since 0.8.0
	 */
	public const string DELETE = 'cbuild_delete_build';

	/**
	 * Undo, in the notice after a Delete: saves the deleted build back. Its nonce action is this and the deleted build's
	 * UUID (Cowork's 0.8.0 review, F3; D-231).
	 *
	 * @since 0.9.0
	 */
	public const string RESTORE = 'cbuild_restore_build';

	/**
	 * The customer's session key that holds a build just deleted, whole, for Undo. The form carries its UUID alone. It
	 * goes when My builds is next shown after its Undo, when Undo brings it back, at sign-out, when the session ends,
	 * or UNDO_MINUTES after the Delete, whichever comes first (Cowork's review of 0.9.0, F2; D-275).
	 */
	private const string DELETED = 'cbuild_deleted';

	/**
	 * How long Undo can bring a deleted build back, in minutes from the Delete (D-275).
	 *
	 * @since 1.0.0
	 */
	public const int UNDO_MINUTES = 15;

	/**
	 * The nonce's field.
	 *
	 * @since 0.8.0
	 */
	public const string NONCE = 'cbuild_nonce';

	/**
	 * The build's UUID's field.
	 *
	 * @since 0.8.0
	 */
	public const string UUID = MyBuildsView::PARAM;

	/**
	 * The name's field.
	 *
	 * @since 0.8.0
	 */
	public const string NAME = 'cbuild_name';

	/**
	 * Save build's field for the kit. Not `add-to-cart`, which WooCommerce answers on any request by adding to the cart.
	 *
	 * @since 0.8.0
	 */
	public const string KIT = 'cbuild_kit';

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param Closure(): SavedBuilds  $saved The customer's builds, built when a form is sent.
	 * @param Closure(): MyBuildsView $view  My builds' rows, built when the page is shown.
	 * @param Clock                   $clock The time, for how long Undo is offered.
	 */
	public function __construct( private Closure $saved, private Closure $view, private Clock $clock ) {}

	/**
	 * Builds My builds' compare when it is asked; none in a copy an earlier release's container built (D-208).
	 *
	 * @var (Closure(): CompareView)|null
	 */
	private ?Closure $compare = null;

	/**
	 * The same, comparing up to three of the customer's builds when My builds is asked with `cbuild_compare[]` (#136).
	 * Only this release's container calls it (D-208).
	 *
	 * @since 1.3.0
	 *
	 * @param Closure(): CompareView $compare The compare, built when it is asked.
	 */
	public function withCompare( Closure $compare ): self {
		$copy          = clone $this;
		$copy->compare = $compare;
		return $copy;
	}

	/**
	 * Attach hooks.
	 *
	 * @since 0.8.0
	 */
	public function register(): void {
		add_filter( 'woocommerce_get_query_vars', array( $this, 'vars' ) );
		add_filter( 'woocommerce_account_menu_items', array( $this, 'menu' ) );
		add_filter( 'woocommerce_endpoint_' . self::ENDPOINT . '_title', array( $this, 'title' ) );
		add_action( 'woocommerce_account_' . self::ENDPOINT . '_endpoint', array( $this, 'page' ) );
		add_action( 'template_redirect', array( $this, 'handle' ) );
		// After WooCommerce has added its endpoints, on `init` (includes/class-wc-query.php:30).
		add_action( 'init', array( $this, 'rewrite' ), 99 );
		add_action( 'wp_enqueue_scripts', array( $this, 'styles' ), 20 );
	}

	/**
	 * WooCommerce's query vars, with My builds.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $vars Endpoint to query var.
	 * @return mixed
	 */
	public function vars( mixed $vars ): mixed {
		if ( is_array( $vars ) ) {
			$vars[ self::ENDPOINT ] = self::ENDPOINT;
		}
		return $vars;
	}

	/**
	 * My account's menu, with My builds before Log out.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $items Endpoint to label.
	 * @return mixed
	 */
	public function menu( mixed $items ): mixed {
		if ( ! is_array( $items ) ) {
			return $items;
		}
		$out = array();
		foreach ( $items as $key => $label ) {
			if ( 'customer-logout' === $key ) {
				$out[ self::ENDPOINT ] = __( 'My builds', 'creative-skateboard-builder' );
			}
			$out[ $key ] = $label;
		}
		$out[ self::ENDPOINT ] ??= __( 'My builds', 'creative-skateboard-builder' );
		return $out;
	}

	/**
	 * The endpoint's title.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $title WooCommerce's.
	 */
	public function title( mixed $title ): string {
		unset( $title );
		return __( 'My builds', 'creative-skateboard-builder' );
	}

	/**
	 * My builds' content.
	 *
	 * @since 0.8.0
	 */
	public function page(): void {
		$asked = null === $this->compare ? null : CompareView::asked();
		$said  = null;
		if ( null !== $asked && null !== $this->compare ) {
			$table = ( $this->compare )()->table( get_current_user_id(), $asked );
			$said  = CompareView::refused( $table );
			if ( null === $said ) {
				$back = self::url();
				require CBUILD_PATH . 'templates/account/compare.php';
				return;
			}
		}
		$rows    = ( $this->view )()->rows( get_current_user_id() );
		$action  = self::url();
		$compare = null === $this->compare ? null : self::fields( $action );
		$gone    = $this->offered();
		$undo    = null === $gone ? null : array(
			'uuid' => $gone->uuid,
			'name' => ( $this->view )()->name( $gone ),
		);
		require CBUILD_PATH . 'templates/account/my-builds.php';
	}

	/**
	 * On My builds alone, beside WooCommerce's own styles: each build's picture on its own line above its name, so a
	 * name of more than one word does not wrap around it.
	 *
	 * @since 0.8.0
	 */
	public function styles(): void {
		if ( function_exists( 'is_wc_endpoint_url' ) && is_wc_endpoint_url( self::ENDPOINT ) ) {
			wp_add_inline_style( 'woocommerce-general', self::STYLES );
		}
	}

	/**
	 * Make the rewrite rules again once, the first time the plugin's endpoints are not those they were made with, so
	 * My builds' address answers after an update with no step for the shop.
	 *
	 * @since 0.8.0
	 */
	public function rewrite(): void {
		if ( self::ENDPOINT !== get_option( Options::ENDPOINTS ) ) {
			// phpcs:ignore WordPressVIPMinimum.Functions.RestrictedFunctions.flush_rewrite_rules_flush_rewrite_rules -- Once per change of the plugin's endpoints, as WooCommerce does after its own (includes/class-wc-install.php), never on every request.
			flush_rewrite_rules( false );
			update_option( Options::ENDPOINTS, self::ENDPOINT, true );
		}
	}

	/**
	 * Answer a form of the plugin's sent to My builds, and go back to it.
	 *
	 * @since 0.8.0
	 */
	public function handle(): void {
		$to = $this->answer();
		if ( null !== $to && wp_safe_redirect( $to ) ) {
			exit;
		}
	}

	/**
	 * Do what a form asked, add its notice, and say where to go: My builds. Null where no form of the plugin's was sent
	 * or no one is signed in.
	 *
	 * @since 0.8.0
	 */
	public function answer(): ?string {
		$sent = isset( $_SERVER['REQUEST_METHOD'] ) && 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST[ self::FIELD ] ) && is_string( $_POST[ self::FIELD ] ) ? sanitize_key( wp_unslash( $_POST[ self::FIELD ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- Each form's nonce is checked below, by its own action.
		$user = get_current_user_id();
		if ( ! in_array( $sent, array( self::SAVE, self::RENAME, self::DELETE, self::RESTORE ), true ) || $user < 1 ) {
			return null;
		}
		$uuid  = self::uuid();
		$nonce = isset( $_POST[ self::NONCE ] ) && is_string( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( false === wp_verify_nonce( $nonce, self::SAVE === $sent ? self::SAVE : $sent . '_' . $uuid ) ) {
			wc_add_notice( __( 'That form has expired. Please try again.', 'creative-skateboard-builder' ), 'error' );
			return self::url();
		}
		if ( self::RESTORE === $sent ) {
			return $this->restore( $user, $uuid );
		}
		$name = isset( $_POST[ self::NAME ] ) && is_string( $_POST[ self::NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NAME ] ) ) : '';
		if ( self::SAVE === $sent ) {
			$this->save( $user, $name, $uuid );
			return self::url();
		}
		$gone = self::DELETE === $sent ? ( $this->saved )()->find( $user, $uuid ) : null;
		$done = self::RENAME === $sent ? ( $this->saved )()->rename( $user, $uuid, $name ) : ( $this->saved )()->delete( $user, $uuid );
		if ( ! $done && null !== ( $this->saved )()->find( $user, $uuid ) ) {
			// Theirs, and the table refused the change: it is in the log.
			wc_add_notice( __( 'The build could not be saved just now. Please try again.', 'creative-skateboard-builder' ), 'error' );
		} elseif ( ! $done ) {
			wc_add_notice( __( 'That build is not among your saved builds.', 'creative-skateboard-builder' ), 'error' );
		} elseif ( self::RENAME === $sent ) {
			wc_add_notice( __( 'The build is renamed.', 'creative-skateboard-builder' ) );
		} else {
			wc_add_notice( __( 'The build is deleted.', 'creative-skateboard-builder' ) );
			$this->keep( $gone );
		}
		return self::url();
	}

	/**
	 * Undo: the build the session kept, put back as it was, when it is the one the form names and the customer's own. A
	 * form sent again finds it back already; one sent after the offer passed finds nothing to bring back.
	 *
	 * @param int    $user The customer.
	 * @param string $uuid The UUID the form names.
	 */
	private function restore( int $user, string $uuid ): string {
		$kept = $this->kept();
		if ( null === $kept || $uuid !== $kept['build']->uuid || $user !== $kept['build']->user ) {
			if ( null !== ( $this->saved )()->find( $user, $uuid ) ) {
				wc_add_notice( __( 'The build is back in My builds.', 'creative-skateboard-builder' ) );
				return self::url();
			}
			wc_add_notice( __( 'That build can no longer be brought back.', 'creative-skateboard-builder' ), 'error' );
			return self::url();
		}
		$code = ( $this->saved )()->restore( $kept['build'] );
		if ( SavedBuilds::SAVED === $code ) {
			self::drop();
			wc_add_notice( __( 'The build is back in My builds.', 'creative-skateboard-builder' ) );
			return self::url();
		}
		if ( SavedBuilds::FULL === $code ) {
			/* translators: %s: how many builds a customer may keep, such as 100. */
			wc_add_notice( sprintf( _n( 'You have %s saved build, the most kept. Delete it to save another.', 'You have %s saved builds, the most kept. Delete one to save another.', SavedBuilds::MOST, 'creative-skateboard-builder' ), number_format_i18n( SavedBuilds::MOST ) ), 'error' );
			return self::url();
		}
		wc_add_notice( __( 'The build could not be saved just now. Please try again.', 'creative-skateboard-builder' ), 'error' );
		return self::url();
	}

	/**
	 * Keep a build just deleted in the customer's session, whole, so My builds can offer to undo it on the next view.
	 * The browser is sent its UUID alone (D-231).
	 *
	 * @param SavedBuild|null $build The build, as it was.
	 */
	private function keep( ?SavedBuild $build ): void {
		$session = function_exists( 'WC' ) ? WC()->session : null;
		if ( null === $build || null === $session ) {
			return;
		}
		$session->set(
			self::DELETED,
			array(
				'uuid'     => $build->uuid,
				'user'     => $build->user,
				'kit'      => $build->kit,
				'picks'    => $build->picks,
				'assembly' => $build->assembly,
				'name'     => $build->name,
				'created'  => $build->created->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				'updated'  => $build->updated->setTimezone( new DateTimeZone( 'UTC' ) )->format( 'Y-m-d H:i:s' ),
				'prices'   => $build->prices?->toArray(),
				'shown'    => false,
				'deleted'  => $this->clock->now()->getTimestamp(),
			)
		);
	}

	/**
	 * The build the session keeps for Undo, and whether My builds has offered it yet. Null when there is none.
	 *
	 * @return array{build: SavedBuild, shown: bool}|null
	 */
	private function kept(): ?array {
		$session = function_exists( 'WC' ) ? WC()->session : null;
		if ( null === $session ) {
			return null;
		}
		$kept = Cast::map( $session->get( self::DELETED ) );
		$uuid = Cast::str( $kept['uuid'] ?? null );
		// A copy from before 1.0.0 has no time, and is past its Undo as any other kept too long. One past it is taken
		// out of the session as it is read (the auditor's 1.0.0 should-fix P2).
		if ( '' === $uuid || $this->clock->now()->getTimestamp() - Cast::int( $kept['deleted'] ?? null ) > self::UNDO_MINUTES * 60 ) {
			if ( null !== $session->get( self::DELETED ) ) {
				self::drop();
			}
			return null;
		}
		$picks = array();
		foreach ( Cast::map( $kept['picks'] ?? null ) as $slot => $part ) {
			$picks[ (string) $slot ] = Cast::int( $part );
		}
		$utc     = new DateTimeZone( 'UTC' );
		$created = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', Cast::str( $kept['created'] ?? null ), $utc );
		$updated = DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', Cast::str( $kept['updated'] ?? null ), $utc );
		if ( false === $created || false === $updated ) {
			return null;
		}
		return array(
			'build' => new SavedBuild( $uuid, Cast::int( $kept['user'] ?? null ), Cast::int( $kept['kit'] ?? null ), $picks, Cast::bool( $kept['assembly'] ?? null ), Cast::str( $kept['name'] ?? null ), $created, $updated, SavedPrices::fromArray( $kept['prices'] ?? null ) ),
			'shown' => Cast::bool( $kept['shown'] ?? null ),
		);
	}

	/**
	 * Forget the build kept for Undo.
	 */
	private static function drop(): void {
		$session = function_exists( 'WC' ) ? WC()->session : null;
		$session?->set( self::DELETED, null );
	}

	/**
	 * The build to offer Undo for on this view of My builds: the one just deleted, on the first view after the delete
	 * alone. A later view forgets it.
	 */
	private function offered(): ?SavedBuild {
		$kept = $this->kept();
		$user = get_current_user_id();
		if ( null === $kept || $user !== $kept['build']->user || $kept['shown'] ) {
			self::drop();
			return null;
		}
		$session = WC()->session;
		$stored  = Cast::map( $session?->get( self::DELETED ) );
		$session?->set( self::DELETED, array( 'shown' => true ) + $stored );
		return $kept['build'];
	}

	/**
	 * Where the compare's form is sent, and the fields that keep the address's own query: a form sent by GET drops the
	 * query its action has, as a site without pretty permalinks gives My builds one.
	 *
	 * @param string $url My builds' address.
	 * @return array{action: string, fields: array<string, string>}
	 */
	private static function fields( string $url ): array {
		$query = (string) wp_parse_url( $url, PHP_URL_QUERY );
		$args  = array();
		wp_parse_str( $query, $args );
		$fields = array();
		foreach ( $args as $key => $value ) {
			$fields[ (string) $key ] = is_string( $value ) ? $value : '';
		}
		return array(
			'action' => '' === $query ? $url : (string) strtok( $url, '?' ),
			'fields' => $fields,
		);
	}

	/**
	 * My builds' address.
	 *
	 * @since 0.8.0
	 */
	public static function url(): string {
		return wc_get_account_endpoint_url( self::ENDPOINT );
	}

	/**
	 * Save build: the kit as its own field, and the picks and assembly under `cbuild` as the review sends them.
	 *
	 * @param int    $user   The customer.
	 * @param string $name   The name given.
	 * @param string $uuid   The saved build it was opened from, or empty.
	 */
	private function save( int $user, string $name, string $uuid ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in answer(), before this is called.
		$kit  = isset( $_POST[ self::KIT ] ) && is_string( $_POST[ self::KIT ] ) ? absint( wp_unslash( $_POST[ self::KIT ] ) ) : 0;
		$sent = isset( $_POST[ BuildLink::PARAM ] ) && is_array( $_POST[ BuildLink::PARAM ] ) ? map_deep( wp_unslash( $_POST[ BuildLink::PARAM ] ), 'sanitize_text_field' ) : array(); // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitised by map_deep().
		// phpcs:enable WordPress.Security.NonceVerification.Missing
		$sent = Cast::map( $sent );
		// The picks as IDs, read as a build link reads them: a slot's key to a positive whole number.
		$picks = BuildLink::read( $sent['picks'] ?? null )->picks;
		$on    = $sent['assembly'] ?? '1';
		$saved = ( $this->saved )()->save( $user, $kit, $picks, '1' === $on, $name, $uuid );
		match ( $saved['code'] ) {
			SavedBuilds::SAVED   => wc_add_notice( __( 'The build is saved. It is listed below.', 'creative-skateboard-builder' ) ),
			SavedBuilds::UPDATED => wc_add_notice( __( 'Your saved build is updated.', 'creative-skateboard-builder' ) ),
			SavedBuilds::FAILED  => wc_add_notice( __( 'The build could not be saved just now. Please try again.', 'creative-skateboard-builder' ), 'error' ),
			/* translators: %s: how many builds a customer may keep, such as 100. */
			SavedBuilds::FULL    => wc_add_notice( sprintf( _n( 'You have %s saved build, the most kept. Delete it to save another.', 'You have %s saved builds, the most kept. Delete one to save another.', SavedBuilds::MOST, 'creative-skateboard-builder' ), number_format_i18n( SavedBuilds::MOST ) ), 'error' ),
			default              => wc_add_notice( __( 'This build could not be saved: a part is missing or no longer fits. Open it in the builder to check it.', 'creative-skateboard-builder' ), 'error' ),
		};
	}

	/**
	 * The UUID sent, or empty.
	 */
	private static function uuid(): string {
		$uuid = isset( $_POST[ self::UUID ] ) && is_string( $_POST[ self::UUID ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::UUID ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Missing -- The nonce names the UUID and is checked with it.
		return 1 === preg_match( '/\A[0-9a-f-]{36}\z/', $uuid ) ? $uuid : '';
	}
}
