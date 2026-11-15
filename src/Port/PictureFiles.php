<?php
/**
 * The composed pictures' files.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Composed pictures are files in the uploads folder, named by their composition's hash, never in the Media Library
 * (spec: Data model; D-196). They are drawn with GD, or Imagick where the host has no GD that writes the type; a host
 * with neither draws none.
 *
 * @since 0.7.0
 */
interface PictureFiles {

	/**
	 * Draw a composition and keep it under its name. Nothing is drawn for an empty one, or where no library can.
	 *
	 * @since 0.7.0
	 *
	 * @param Composition $composition The drawing.
	 * @return bool Whether the file is there.
	 */
	public function draw( Composition $composition ): bool;

	/**
	 * The file's address, or null when it is not made.
	 *
	 * @since 0.7.0
	 *
	 * @param string $name A composition's name.
	 */
	public function url( string $name ): ?string;

	/**
	 * The library a picture is drawn with on this host: `gd`, `imagick`, or null for neither.
	 *
	 * @since 0.7.0
	 *
	 * @param string $type The composed picture's MIME type.
	 */
	public function library( string $type ): ?string;
}
