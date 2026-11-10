<?php
/**
 * A test double of WP-CLI's static API: records what a command prints, and throws where WP-CLI would exit 1. As
 * Creative Search's.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

// phpcs:ignoreFile -- a test double named after the class it stands in for.

namespace {
	if ( ! class_exists( 'WP_CLI' ) ) {
		class WP_CLI {
			/** @var string[] */
			public static array $lines = array();
			/** @var array<int, array<string, mixed>> */
			public static array $items = array();
			public static string $format = '';
			/** @var array<string, object> */
			public static array $commands = array();

			public static function reset(): void {
				self::$lines  = array();
				self::$items  = array();
				self::$format = '';
			}
			public static function add_command( string $name, $callable, array $args = array() ): bool {
				self::$commands[ $name ] = $callable;
				return true;
			}
			public static function log( string $m ): void {
				self::$lines[] = 'log: ' . $m;
			}
			public static function line( string $m = '' ): void {
				self::$lines[] = 'line: ' . $m;
			}
			public static function success( string $m ): void {
				self::$lines[] = 'success: ' . $m;
			}
			public static function warning( string $m ): void {
				self::$lines[] = 'warning: ' . $m;
			}
			public static function error( $m, $exit = true ): never {
				throw new \RuntimeException( (string) $m );
			}
		}
	}
}

namespace WP_CLI\Utils {
	if ( ! function_exists( 'WP_CLI\Utils\format_items' ) ) {
		function format_items( string $format, array $items, $fields ): void {
			\WP_CLI::$format = $format;
			\WP_CLI::$items  = $items;
		}
	}
}
