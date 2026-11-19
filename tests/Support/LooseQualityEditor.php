<?php
/**
 * An image editor that takes its quality as given, as a media plugin's own editor may.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

use WP_Image_Editor_GD;

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';

/**
 * GD's editor without WordPress's range check on the quality `wp_editor_set_quality` answers
 * (wp-includes/class-wp-image-editor.php:291): what reached `imageavif()` on the demo under a media plugin set to AVIF
 * (Cowork's 1.0.0 report). A quality set on the editor itself is still checked. Put first with `wp_image_editors`.
 * With `$throws`, a save of that type throws as GD did.
 */
final class LooseQualityEditor extends WP_Image_Editor_GD {

	/**
	 * A type whose save throws, or null for none.
	 *
	 * @var string|null
	 */
	public static ?string $throws = null;

	/**
	 * Sets the quality: the filter's answer as it is, else as WordPress sets it.
	 *
	 * @param int|null             $quality Quality, or null for the filter's.
	 * @param array<string, mixed> $dims    The image's size.
	 * @return true|\WP_Error
	 */
	public function set_quality( $quality = null, $dims = array() ) {
		if ( null === $quality ) {
			$this->quality = apply_filters( 'wp_editor_set_quality', 82, $this->output_mime_type ?? $this->mime_type, $dims ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own filter.
			return true;
		}
		return parent::set_quality( $quality, $dims );
	}

	/**
	 * Saves, or throws for `$throws`'s type.
	 *
	 * @param string|null $destfilename Where it goes.
	 * @param string|null $mime_type    The type.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		if ( null !== self::$throws && self::$throws === $mime_type ) {
			throw new \ValueError( 'imageavif(): Argument #3 ($quality) must be between -1 and 100' );
		}
		return parent::save( $destfilename, $mime_type );
	}
}
