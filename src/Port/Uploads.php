<?php
/**
 * Files sent with a form.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A file's text, read where PHP put the upload. The file is never moved into the site, run or kept.
 *
 * @since 0.3.0
 */
interface Uploads {

	/**
	 * The text of the CSV file a form sent.
	 *
	 * @since 0.3.0
	 *
	 * @param string $field The file field's name.
	 * @param int    $most  The most bytes it may hold.
	 *
	 * @throws UploadRefused When there is no file, it is too big, it is not a .csv or it did not arrive whole.
	 */
	public function csv( string $field, int $most ): string;
}
