<?php
/**
 * WP-CLI symbols the plugin uses, for PHPStan. The php-stubs WP-CLI package has no build for WordPress 7
 * stubs (see CLAUDE.md), so the few calls the plugin makes are declared by hand.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

// phpcs:ignoreFile -- declarations only.

namespace {
class WP_CLI {
	/**
	 * @param string               $name     Command name.
	 * @param callable|object|string $callable Handler.
	 * @param array<string, mixed> $args     Registration args.
	 */
	public static function add_command( string $name, $callable, array $args = array() ): bool {
		return true;
	}

	public static function log( string $message ): void {}

	public static function line( string $message = '' ): void {}

	public static function success( string $message ): void {}

	public static function warning( string $message ): void {}

	/**
	 * @param string               $question Question.
	 * @param array<string, mixed> $assoc    Flags; `yes` skips the question.
	 */
	public static function confirm( string $question, array $assoc = array() ): void {}

	/**
	 * @param string|\WP_Error $message Message.
	 * @param bool|int         $exit    Exit.
	 * @return never
	 */
	public static function error( $message, $exit = true ): void {
		exit( 1 );
	}
}
}

namespace WP_CLI\Utils {

/**
 * @param string                           $format Format.
 * @param array<int, array<string, mixed>> $items  Rows.
 * @param string[]|string                  $fields Fields.
 */
function format_items( string $format, array $items, $fields ): void {}
}
