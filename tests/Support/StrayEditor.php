<?php
/**
 * An image editor that writes somewhere other than it is asked.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

use Closure;
use WP_Image_Editor_GD;

require_once ABSPATH . WPINC . '/class-wp-image-editor.php';
require_once ABSPATH . WPINC . '/class-wp-image-editor-gd.php';

/**
 * GD's editor, whose save goes where `$to` says: a host's editor that turns a file into another, as the audit of the fixes
 * asked a test of. Put first with `wp_image_editors`.
 */
final class StrayEditor extends WP_Image_Editor_GD {

	/**
	 * Where a save asked for a path goes instead, and as what type; null to go where asked. WordPress gives a file the
	 * extension of the type it is saved as, so both are named.
	 *
	 * @var (Closure(string): array{string, string})|null
	 */
	public static ?Closure $to = null;

	/**
	 * Saves where `$to` says.
	 *
	 * @param string|null $destfilename Where it was asked to go.
	 * @param string|null $mime_type    The type asked for.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function save( $destfilename = null, $mime_type = null ) {
		if ( null === self::$to || null === $destfilename ) {
			return parent::save( $destfilename, $mime_type );
		}
		[ $path, $type ] = ( self::$to )( $destfilename );
		return parent::save( $path, $type );
	}
}
