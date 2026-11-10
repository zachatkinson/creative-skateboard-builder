<?php
/**
 * An option read from the table.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads an option another process may have written since this one last read it. Without a persistent object cache
 * `get_option()` answers from the process's own memory (wp-includes/option.php: `wp_cache_get( $option,
 * 'options' )`), so a job that reads its state again "in case it changed" would be told what it already knew, and
 * save over the change. The cached copy, and the note that the option did not exist, are dropped first.
 *
 * @since 0.3.0
 */
final class FreshOption {

	/**
	 * The option as the table has it now.
	 *
	 * @since 0.3.0
	 *
	 * @param string $option The option's name.
	 * @return array<mixed>|null The stored array, or null when there is none or it is not one.
	 */
	public static function read( string $option ): ?array {
		wp_cache_delete( $option, 'options' );
		$missing = wp_cache_get( 'notoptions', 'options' );
		if ( is_array( $missing ) && isset( $missing[ $option ] ) ) {
			unset( $missing[ $option ] );
			wp_cache_set( 'notoptions', $missing, 'options' );
		}
		$stored = get_option( $option, null );
		return is_array( $stored ) ? $stored : null;
	}
}
