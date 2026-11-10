<?php
/**
 * The object-cache leg's drop-in (tools/object-cache.sh copies it to wp-content/object-cache.php; D-066).
 *
 * It names the leg's Redis server and loads Redis Object Cache's own drop-in, unchanged. The setting is here, not in
 * wp-config.php, because every PHP process in the container must find the server: the suite's (wp-tests-config.php)
 * and the WP-CLI processes its tests start on the integration site (wp-config.php). Test tooling only: it is not in
 * the plugin's zip.
 *
 * @package CreativeBranding\SkateboardBuilder\Tools
 */

declare( strict_types=1 );

if ( ! defined( 'WP_REDIS_HOST' ) ) {
	define( 'WP_REDIS_HOST', 'cbuild-redis' );
}

require WP_CONTENT_DIR . '/plugins/redis-cache/includes/object-cache.php';
