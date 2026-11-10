<?php
/**
 * CSV files on the server's disk.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\Files;
use CreativeBranding\SkateboardBuilder\Port\UploadRefused;
use WP_Filesystem_Direct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads and writes through `WP_Filesystem_Direct`, after the path is known to be a plain one: a path with a scheme
 * (`https://`, `phar://`, `php://`) is refused before anything opens it, since PHP's file functions read those too.
 *
 * @since 0.3.0
 */
final class DiskFiles implements Files {

	/**
	 * The text of a CSV file.
	 *
	 * @since 0.3.0
	 *
	 * @param string $path The file's path.
	 * @param int    $most The most bytes it may hold.
	 *
	 * @throws UploadRefused When it cannot be read.
	 */
	public function csv( string $path, int $most ): string {
		$why = self::refused( $path );
		if ( '' === $why && ! is_file( $path ) ) {
			$why = UploadRefused::MISSING;
		} elseif ( '' === $why && ! is_readable( $path ) ) {
			$why = UploadRefused::FAILED;
		} elseif ( '' === $why && (int) filesize( $path ) > $most ) {
			$why = UploadRefused::BIG;
		}
		$text = '' === $why ? self::disk()->get_contents( $path ) : null;
		if ( ! is_string( $text ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- One of the class's own codes, which the command turns into a sentence.
			throw new UploadRefused( '' === $why ? UploadRefused::FAILED : $why );
		}
		return $text;
	}

	/**
	 * Write a CSV file, in place of one that is there. The mode is given: without one WordPress reads a constant
	 * that only its own filesystem set-up defines.
	 *
	 * @since 0.3.0
	 *
	 * @param string $path The file's path.
	 * @param string $text Its text.
	 *
	 * @throws UploadRefused When it cannot be written.
	 */
	public function write( string $path, string $text ): void {
		$why = self::refused( $path );
		if ( '' === $why && ( is_dir( $path ) || ! is_dir( dirname( $path ) ) || ! self::disk()->put_contents( $path, $text, 0644 ) ) ) {
			$why = UploadRefused::FAILED;
		}
		if ( '' !== $why ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- One of the class's own codes, which the command turns into a sentence.
			throw new UploadRefused( $why );
		}
	}

	/**
	 * Why a path is not one this reads or writes, or '' when it is.
	 *
	 * @param string $path The path.
	 */
	private static function refused( string $path ): string {
		if ( '' === $path || str_contains( $path, '://' ) || str_contains( $path, "\0" ) ) {
			return UploadRefused::MISSING;
		}
		return 'csv' === strtolower( pathinfo( $path, PATHINFO_EXTENSION ) ) ? '' : UploadRefused::NOT_CSV;
	}

	/**
	 * WordPress's reader and writer of the local disk.
	 */
	private static function disk(): WP_Filesystem_Direct {
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
		return new WP_Filesystem_Direct( null );
	}
}
