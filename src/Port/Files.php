<?php
/**
 * Files on the server's own disk.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A CSV file read from, or written to, a path a person at the command line gave. Only a plain path on the server's
 * disk that ends in .csv is read or written: no address, and no stream.
 *
 * @since 0.3.0
 */
interface Files {

	/**
	 * The text of a CSV file.
	 *
	 * @since 0.3.0
	 *
	 * @param string $path The file's path.
	 * @param int    $most The most bytes it may hold.
	 *
	 * @throws UploadRefused When there is no file there, it is too big, it is not a .csv or it cannot be read.
	 */
	public function csv( string $path, int $most ): string;

	/**
	 * Write a CSV file, in place of one that is there.
	 *
	 * @since 0.3.0
	 *
	 * @param string $path The file's path.
	 * @param string $text Its text.
	 *
	 * @throws UploadRefused When the path is not a .csv on the disk, or it cannot be written.
	 */
	public function write( string $path, string $text ): void;
}
