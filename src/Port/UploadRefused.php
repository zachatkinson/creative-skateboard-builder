<?php
/**
 * An upload that was not read.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use RuntimeException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Its message is one of the codes below, for the screen to put into words.
 *
 * @since 0.3.0
 */
final class UploadRefused extends RuntimeException {

	/**
	 * Why.
	 */
	public const MISSING = 'upload_missing';
	public const FAILED  = 'upload_failed';
	public const BIG     = 'file_too_big';
	public const NOT_CSV = 'upload_not_csv';
}
