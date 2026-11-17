<?php
/**
 * Saved builds in My account, and the forms that keep, rename and delete them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
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
	 * My builds' one rule: the picture on its own line, its name under it.
	 */
	private const string STYLES = '.cbuild-my-builds__build th img{display:block;margin:0 0 8px}';

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
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param Closure(): SavedBuilds  $saved The customer's builds, built when a form is sent.
	 * @param Closure(): MyBuildsView $view  My builds' rows, built when the page is shown.
	 */
	public function __construct( private Closure $saved, private Closure $view ) {}

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
		$rows   = ( $this->view )()->rows( get_current_user_id() );
		$action = self::url();
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
		if ( ! in_array( $sent, array( self::SAVE, self::RENAME, self::DELETE ), true ) || $user < 1 ) {
			return null;
		}
		$uuid  = self::uuid();
		$nonce = isset( $_POST[ self::NONCE ] ) && is_string( $_POST[ self::NONCE ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE ] ) ) : '';
		if ( false === wp_verify_nonce( $nonce, self::SAVE === $sent ? self::SAVE : $sent . '_' . $uuid ) ) {
			wc_add_notice( __( 'That form has expired. Please try again.', 'creative-skateboard-builder' ), 'error' );
			return self::url();
		}
		$name = isset( $_POST[ self::NAME ] ) && is_string( $_POST[ self::NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NAME ] ) ) : '';
		if ( self::SAVE === $sent ) {
			$this->save( $user, $name, $uuid );
			return self::url();
		}
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
		}
		return self::url();
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
	 * Save build: the review's own fields, the kit as `add-to-cart` and the picks and assembly under `cbuild`.
	 *
	 * @param int    $user The customer.
	 * @param string $name The name given.
	 * @param string $uuid The saved build it was opened from, or empty.
	 */
	private function save( int $user, string $name, string $uuid ): void {
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Checked in answer(), before this is called.
		$kit  = isset( $_POST['add-to-cart'] ) && is_string( $_POST['add-to-cart'] ) ? absint( wp_unslash( $_POST['add-to-cart'] ) ) : 0;
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
			SavedBuilds::FULL    => wc_add_notice( sprintf( __( 'You have %s saved builds, the most kept. Delete one to save another.', 'creative-skateboard-builder' ), number_format_i18n( SavedBuilds::MOST ) ), 'error' ),
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
