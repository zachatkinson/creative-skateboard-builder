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
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WP_Image_Editor;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each rendition is the image in the Media Library, resized by the editor WordPress chooses for its type and the
 * rendition's, and saved to `uploads/creative-skateboard-builder/layers/`. Its name is a hash of the attachment, its
 * file's name and its size in bytes, then the rendition's pixels: an image edited or replaced in the Media Library has
 * a new file, so new names, and the page never shows a stale one (D-194). WordPress's output format is filtered only
 * while a rendition is saved, never for the shop's own uploads (D-209). A type the host's editors cannot write is not
 * made.
 *
 * @since 0.7.0
 */
final class WpLayerRenditions implements LayerRenditions {

	/**
	 * The folder in the uploads folder.
	 */
	public const FOLDER = Options::UPLOADS_FOLDER . '/layers';

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param Logger $logger Where a rendition that was not made is noted.
	 */
	public function __construct( private Logger $logger ) {}

	/**
	 * Make what is not made yet.
	 *
	 * @since 0.7.0
	 *
	 * @param int                   $attachment The image's attachment ID.
	 * @param list<array{int, int}> $sizes      Pixels across and along.
	 */
	public function make( int $attachment, array $sizes ): void {
		// The file and type WpLayerFile reads: an AVIF an output format mapping put under a PNG attachment is an AVIF.
		$layer = WpLayerFile::of( $attachment );
		$stem  = $this->stem( $attachment );
		$where = self::where();
		if ( null === $layer || null === $stem || null === $where || array() === $sizes || ! wp_mkdir_p( $where['dir'] ) ) {
			return;
		}
		[ 'path' => $file, 'type' => $type ] = $layer;
		$this->clear( $where['dir'], $attachment, $stem );
		$original = wp_getimagesize( $file );
		foreach ( Renditions::TYPES as $mime => $extension ) {
			foreach ( $sizes as $size ) {
				$path = $where['dir'] . '/' . self::name( $attachment, $stem, $size, $extension );
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
				$name = self::name( $attachment, $stem, $size, $extension );
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
	 * Remove every rendition of an image, as when it is deleted from the Media Library. Its file may be gone, so
	 * they are found by the attachment's ID that starts their names.
	 *
	 * @since 0.7.0
	 *
	 * @param int $attachment The image's attachment ID.
	 */
	public function forget( int $attachment ): void {
		$where = self::where();
		if ( null !== $where ) {
			$this->clear( $where['dir'], $attachment, null );
		}
	}

	/**
	 * Remove an image's renditions of any file but this one: those of the file it had before an edit or a
	 * replacement, or, with no stem, all of them (D-194).
	 *
	 * @param string      $dir        The renditions' folder.
	 * @param int         $attachment The image's attachment ID.
	 * @param string|null $stem       The hash of the file it has now, whose renditions are kept.
	 */
	private function clear( string $dir, int $attachment, ?string $stem ): void {
		foreach ( (array) glob( $dir . '/' . $attachment . '-*' ) as $path ) {
			$path = (string) $path;
			if ( null === $stem || ! str_starts_with( wp_basename( $path ), $attachment . '-' . $stem . '-' ) ) {
				wp_delete_file( $path );
			}
		}
	}

	/**
	 * One rendition: resized unless it is the image's own size, then saved in its type. While it is saved, an
	 * `image_editor_output_format` mapping of its type is set aside, so a shop that maps WebP to AVIF for its uploads
	 * still gets a WebP rendition, and the AVIF one already made is never written over (Cowork's M7 review, F2; D-209).
	 * The shop's other mappings, and every save that is not one of ours, are left as they are. A file that still comes
	 * out as another type, from an editor of the host's own, is removed unless it is a rendition's name, and the
	 * failure is logged once an hour.
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
			$this->failed( $path, 'resize' );
			return;
		}
		$aside = static function ( mixed $formats ) use ( $mime ): mixed {
			if ( is_array( $formats ) ) {
				unset( $formats[ $mime ] );
			}
			return $formats;
		};
		add_filter( 'image_editor_output_format', $aside, PHP_INT_MAX );
		try {
			$saved = $editor->save( $path, $mime );
		} finally {
			remove_filter( 'image_editor_output_format', $aside, PHP_INT_MAX );
		}
		if ( ! is_array( $saved ) ) {
			$this->failed( $path, 'save' );
			return;
		}
		$written = Cast::str( $saved['path'] ?? null );
		if ( $written === $path && Cast::str( $saved['mime-type'] ?? null ) === $mime ) {
			return;
		}
		$this->failed( $path, 'type' );
		if ( '' !== $written && $written !== $path && ! self::isRendition( $written ) ) {
			wp_delete_file( $written );
		}
	}

	/**
	 * Whether a file is one of the renditions' own names, any image's, in any of their types.
	 *
	 * @param string $path The file.
	 */
	private static function isRendition( string $path ): bool {
		$where = self::where();
		return null !== $where
			&& wp_normalize_path( dirname( $path ) ) === wp_normalize_path( $where['dir'] )
			&& 1 === preg_match( '/^\d+-[0-9a-f]{20}-\d+x\d+\.(' . implode( '|', array_map( 'preg_quote', array_values( Renditions::TYPES ) ) ) . ')$/', wp_basename( $path ) );
	}

	/**
	 * A rendition that was not made, logged at most once an hour for its type and step: the page draws the part from
	 * its full-size image until it is, and Site Health counts the parts with none.
	 *
	 * @param string $path Where it was to go.
	 * @param string $step resize, save or type.
	 */
	private function failed( string $path, string $step ): void {
		$this->logger->hourly(
			'rendition_' . $step . '_' . pathinfo( $path, PATHINFO_EXTENSION ),
			Logger::WARNING,
			'A layer image rendition was not made.',
			array(
				'file' => wp_basename( $path ),
				'step' => $step,
			)
		);
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
	 * @param int             $attachment The image's attachment ID, which starts the name, so its renditions are found
	 *                                    after its file is gone.
	 * @param string          $stem       The image's hash.
	 * @param array{int, int} $size       Its pixels.
	 * @param string          $extension  Its type's extension.
	 */
	private static function name( int $attachment, string $stem, array $size, string $extension ): string {
		return $attachment . '-' . $stem . '-' . $size[0] . 'x' . $size[1] . '.' . $extension;
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
