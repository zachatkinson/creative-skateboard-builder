<?php
/**
 * The plugin's exception.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Support;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thrown for a state the plugin cannot continue from (an unknown service, a migration that cannot run). The
 * message is plain text for logs and WP-CLI; callers that print it escape it at the sink.
 */
final class PluginException extends RuntimeException {
}
