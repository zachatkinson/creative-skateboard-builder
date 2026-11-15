<?php
/**
 * The layer images' renditions, made with WordPress's image editors.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Port\LayerRenditions;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WP_Image_Editor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each rendition is the image in the Media Library, resized by the editor WordPress chooses for its type and the
 * rendition's, and saved to `uploads/creative-skateboard-builder/layers/`. Its name is a hash of the attachment, its
 * file's name and its size in bytes, then the rendition's pixels: an image edited or replaced in the Media Library has
 * a new file, so new names, and the page never shows a stale one (D-194). Nothing here filters WordPress's output
 * format, which would change every upload in the shop. A type the host's editors cannot write, or one a filter turns
 * into another, is not kept.
 *
 * @since 0.7.0
 */
final class WpLayerRenditions implements LayerRenditions {

	/**
	 * The folder in the uploads folder.
	 */
	public const FOLDER = 'creative-skateboard-builder/layers';

	/**
	 * Make what is not made yet.
	 *
	 * @since 0.7.0
	 *
	 * @param int                   $attachment The image's attachment ID.
	 * @param list<array{int, int}> $sizes      Pixels across and along.
	 */
	public function make( int $attachment, array $sizes ): void {
		$file  = get_attached_file( $attachment );
		$type  = get_post_mime_type( $attachment );
		$stem  = $this->stem( $attachment );
		$where = self::where();
		if ( ! is_string( $file ) || ! is_string( $type ) || null === $stem || null === $where || array() === $sizes || ! is_readable( $file ) || ! wp_mkdir_p( $where['dir'] ) ) {
			return;
		}
		$original = wp_getimagesize( $file );
		foreach ( Renditions::TYPES as $mime => $extension ) {
			foreach ( $sizes as $size ) {
				$path = $where['dir'] . '/' . self::name( $stem, $size, $extension );
				if ( file_exists( $path ) ) {
					continue;
				}
				$this->save(
					$file,
					$type,
					$mime,
					$path,
					is_array( $original ) && array( $original[0], $original[1] ) === $size ? null : $size
				);
			}
		}
	}

	/**
	 * What is made.
	 *
	 * @since 0.7.0
	 *
	 * @param int                   $attachment The image's attachment ID.
	 * @param list<array{int, int}> $sizes      Pixels across and along.
	 * @return array<string, list<array{url: string, width: int, height: int}>>
	 */
	public function sources( int $attachment, array $sizes ): array {
		$stem  = $this->stem( $attachment );
		$where = self::where();
		if ( null === $stem || null === $where ) {
			return array();
		}
		$sources = array();
		foreach ( Renditions::TYPES as $mime => $extension ) {
			foreach ( $sizes as $size ) {
				$name = self::name( $stem, $size, $extension );
				if ( file_exists( $where['dir'] . '/' . $name ) ) {
					$sources[ $mime ][] = array(
						'url'    => $where['url'] . '/' . $name,
						'width'  => $size[0],
						'height' => $size[1],
					);
				}
			}
		}
		return $sources;
	}

	/**
	 * One rendition: resized unless it is the image's own size, then saved in its type. A file of another type, which
	 * an `image_editor_output_format` filter may make, is removed.
	 *
	 * @param string               $file The image's file.
	 * @param string               $type Its MIME type.
	 * @param string               $mime The rendition's.
	 * @param string               $path Where it goes.
	 * @param array{int, int}|null $size The pixels to resize to, or null to keep the image's.
	 */
	private function save( string $file, string $type, string $mime, string $path, ?array $size ): void {
		$editor = wp_get_image_editor(
			$file,
			array(
				'mime_type'        => $type,
				'output_mime_type' => $mime,
			)
		);
		if ( ! $editor instanceof WP_Image_Editor || ! $editor::supports_mime_type( $mime ) ) {
			return;
		}
		if ( null !== $size && true !== $editor->resize( $size[0], $size[1], false ) ) {
			return;
		}
		$saved = $editor->save( $path, $mime );
		if ( ! is_array( $saved ) ) {
			return;
		}
		$written = Cast::str( $saved['path'] ?? null );
		if ( $written !== $path || Cast::str( $saved['mime-type'] ?? null ) !== $mime ) {
			wp_delete_file( $written );
		}
	}

	/**
	 * The hash every rendition of this image starts with, or null when it is no image in the Media Library.
	 *
	 * @param int $attachment The attachment's ID.
	 */
	private function stem( int $attachment ): ?string {
		$file = get_attached_file( $attachment );
		if ( ! is_string( $file ) || ! wp_attachment_is_image( $attachment ) ) {
			return null;
		}
		$bytes = is_readable( $file ) ? (int) filesize( $file ) : 0;
		return substr( hash( 'sha256', $attachment . '|' . wp_basename( $file ) . '|' . $bytes ), 0, 20 );
	}

	/**
	 * A rendition's file name.
	 *
	 * @param string          $stem      The image's hash.
	 * @param array{int, int} $size      Its pixels.
	 * @param string          $extension Its type's extension.
	 */
	private static function name( string $stem, array $size, string $extension ): string {
		return $stem . '-' . $size[0] . 'x' . $size[1] . '.' . $extension;
	}

	/**
	 * The folder, on disk and as a URL, or null when the uploads folder is not known.
	 *
	 * @return array{dir: string, url: string}|null
	 */
	private static function where(): ?array {
		$uploads = wp_get_upload_dir();
		if ( ! is_string( $uploads['basedir'] ?? null ) || '' === $uploads['basedir'] || ! is_string( $uploads['baseurl'] ?? null ) ) {
			return null;
		}
		return array(
			'dir' => $uploads['basedir'] . '/' . self::FOLDER,
			'url' => $uploads['baseurl'] . '/' . self::FOLDER,
		);
	}
}
