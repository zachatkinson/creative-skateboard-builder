<?php
/**
 * Files sent with this request.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\UploadRefused;
use CreativeBranding\SkateboardBuilder\Port\Uploads;
use WP_Filesystem_Direct;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Reads an upload from PHP's temporary file, which PHP deletes when the request ends. The file is not passed to
 * `wp_handle_upload()`: that would move it into the uploads folder, where it could be fetched by its address.
 * The caller has checked the capability and the nonce before asking.
 *
 * @since 0.3.0
 */
final class RequestUploads implements Uploads {

	/**
	 * The text of the CSV file a form sent.
	 *
	 * @since 0.3.0
	 *
	 * @param string $field The file field's name.
	 * @param int    $most  The most bytes it may hold.
	 *
	 * @throws UploadRefused When it cannot be read.
	 */
	public function csv( string $field, int $most ): string {
		// phpcs:disable WordPress.Security.NonceVerification.Missing, WordPress.Security.ValidatedSanitizedInput -- The screen checks the nonce before it asks; the name is only checked for its ending and the path only tested, below.
		$file  = isset( $_FILES[ $field ] ) && is_array( $_FILES[ $field ] ) ? $_FILES[ $field ] : array();
		$error = $file['error'] ?? UPLOAD_ERR_NO_FILE;
		$name  = is_string( $file['name'] ?? null ) ? $file['name'] : '';
		$path  = is_string( $file['tmp_name'] ?? null ) ? $file['tmp_name'] : '';
		$size  = is_int( $file['size'] ?? null ) ? $file['size'] : 0;
		// phpcs:enable
		$why = '';
		if ( UPLOAD_ERR_NO_FILE === $error || array() === $file ) {
			$why = UploadRefused::MISSING;
		} elseif ( UPLOAD_ERR_INI_SIZE === $error || UPLOAD_ERR_FORM_SIZE === $error || $size > $most ) {
			$why = UploadRefused::BIG;
		} elseif ( UPLOAD_ERR_OK !== $error ) {
			$why = UploadRefused::FAILED;
		} elseif ( 'csv' !== wp_check_filetype( $name, array( 'csv' => 'text/csv' ) )['ext'] ) {
			$why = UploadRefused::NOT_CSV;
		} elseif ( ! is_uploaded_file( $path ) ) {
			$why = UploadRefused::FAILED;
		}
		$text = null;
		if ( '' === $why ) {
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-base.php';
			require_once ABSPATH . 'wp-admin/includes/class-wp-filesystem-direct.php';
			$text = ( new WP_Filesystem_Direct( null ) )->get_contents( $path );
		}
		if ( ! is_string( $text ) ) {
			// phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped -- One of the class's own codes, which the screen turns into a sentence and escapes.
			throw new UploadRefused( '' === $why ? UploadRefused::FAILED : $why );
		}
		return $text;
	}
}
