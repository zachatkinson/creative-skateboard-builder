<?php
/**
 * Whether a request may run migrations itself.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Install\Migrations;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The rule of D-022: migrations run inline for a signed-in user with `manage_woocommerce`, on an admin page that is
 * not an AJAX, admin-post or cron request. Every other request queues the job.
 *
 * @since 0.3.0
 */
final class InlineRule {

	/**
	 * Whether this request may run a migration inline.
	 *
	 * @since 0.3.0
	 */
	public function allows(): bool {
		if ( ! is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return false;
		}
		if ( isset( $GLOBALS['pagenow'] ) && 'admin-post.php' === $GLOBALS['pagenow'] ) {
			return false;
		}
		return current_user_can( 'manage_woocommerce' );
	}
}
