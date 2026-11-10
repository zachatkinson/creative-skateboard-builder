<?php
/**
 * Where a Hookable's hooks can fire.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The kinds of request a Hookable is built for (issue #3, D-067). The container names each Hookable's scopes, and the
 * plugin builds a Hookable only on a request in one of them.
 *
 * @since 0.3.0
 */
enum Scope {

	/**
	 * Every request: a job's handler, or a hook that can fire anywhere.
	 */
	case Everywhere;

	/**
	 * In wp-admin, admin-ajax.php and admin-post.php.
	 */
	case Admin;

	/**
	 * In WP-CLI.
	 */
	case Cli;

	/**
	 * In wp-cron.php.
	 */
	case Cron;

	/**
	 * A REST request. WordPress knows only at `rest_api_init`, so these are built there.
	 */
	case Rest;

	/**
	 * Anything that is not wp-admin, WP-CLI or cron: a shopper's page, and a REST request before it is known to be one.
	 */
	case Storefront;

	/**
	 * The scopes a request is in when the plugin boots, from what WordPress says of it. Rest is never among them.
	 *
	 * @since 0.3.0
	 *
	 * @param bool $admin Whether is_admin().
	 * @param bool $cli   Whether WP-CLI is running.
	 * @param bool $cron  Whether wp_doing_cron().
	 * @return list<self>
	 */
	public static function atBoot( bool $admin, bool $cli, bool $cron ): array {
		$scopes = array( self::Everywhere );
		if ( $admin ) {
			$scopes[] = self::Admin;
		}
		if ( $cli ) {
			$scopes[] = self::Cli;
		}
		if ( $cron ) {
			$scopes[] = self::Cron;
		}
		if ( ! $admin && ! $cli && ! $cron ) {
			$scopes[] = self::Storefront;
		}
		return $scopes;
	}
}
