<?php
/**
 * The layer images' renditions, made by WordPress's image editors.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpLayerRenditions;
use CreativeBranding\SkateboardBuilder\Port\LayerRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;
use CreativeBranding\SkateboardBuilder\Tests\Support\StrayEditor;

/**
 * Plan, milestone 7: "The plugin makes its AVIF and WebP renditions at the sizes the picture shows, without changing
 * how WordPress handles the shop's other uploads." Audit, Anti-patterns: "a test uploading an ordinary image checks
 * WordPress's sizes are untouched" (D-194).
 */
final class LayerRenditionsTest extends IntegrationTestCase {

	private const SIZES = array( array( 105, 407 ), array( 210, 813 ), array( 420, 1626 ) );

	public function tear_down(): void {
		remove_all_filters( 'image_editor_output_format' );
		StrayEditor::$to = null;
		$folder = wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER;
		foreach ( (array) glob( $folder . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		parent::tear_down();
	}

	private function renditions(): LayerRenditions {
		return $this->service( LayerRenditions::class );
	}

	/**
	 * Whether a file's top left pixel is transparent, read with GD.
	 *
	 * @param string $file The file.
	 */
	private static function clearCorner( string $file ): bool {
		$image = str_ends_with( $file, '.avif' ) ? imagecreatefromavif( $file ) : imagecreatefromwebp( $file );
		self::assertNotFalse( $image, $file );
		return 127 === ( imagecolorat( $image, 0, 0 ) >> 24 & 0x7f );
	}

	public function test_each_size_is_made_in_avif_and_webp_transparent_and_listed_smallest_first(): void {
		$image = LayerFiles::attachment( 420, 1626 );
		$this->renditions()->make( $image, self::SIZES );
		$sources = $this->renditions()->sources( $image, self::SIZES );

		self::assertSame( array( 'image/avif', 'image/webp' ), array_keys( $sources ), 'AVIF first, so a browser that reads it takes it' );
		$base = wp_get_upload_dir();
		foreach ( $sources as $mime => $files ) {
			self::assertSame( self::SIZES, array_map( static fn( array $file ): array => array( $file['width'], $file['height'] ), $files ), $mime );
			foreach ( $files as $file ) {
				self::assertStringStartsWith( $base['baseurl'] . '/creative-skateboard-builder/layers/', $file['url'] );
				$path = $base['basedir'] . substr( $file['url'], strlen( $base['baseurl'] ) );
				$size = wp_getimagesize( $path );
				self::assertIsArray( $size, $path );
				self::assertSame( array( $file['width'], $file['height'], $mime ), array( $size[0], $size[1], $size['mime'] ), $path );
				self::assertTrue( self::clearCorner( $path ), 'the cut-out keeps its transparent background: ' . $path );
			}
		}
	}

	public function test_what_is_made_is_not_made_again_and_a_new_file_has_new_names(): void {
		$image = LayerFiles::attachment( 420, 1626 );
		$this->renditions()->make( $image, self::SIZES );
		$first = $this->renditions()->sources( $image, self::SIZES );
		$base  = wp_get_upload_dir();
		$path  = $base['basedir'] . substr( $first['image/webp'][0]['url'], strlen( $base['baseurl'] ) );
		file_put_contents( $path, 'kept' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A marker the second make would overwrite.
		$this->renditions()->make( $image, self::SIZES );
		self::assertSame( 'kept', file_get_contents( $path ), 'a rendition already made is left as it is' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- A local file.

		// The image replaced in the Media Library, as an edit saves it: a new file, so new names.
		update_attached_file( $image, LayerFiles::draw( 420, 1626, true, 'png', 0xd1242f ) );
		self::assertSame( array(), $this->renditions()->sources( $image, self::SIZES ), 'nothing made of the new file yet' );
		self::assertNotSame( $first, ( function () use ( $image ) {
			$this->renditions()->make( $image, self::SIZES );
			return $this->renditions()->sources( $image, self::SIZES );
		} )() );
	}

	public function test_a_new_file_takes_the_old_files_renditions_away_and_a_deleted_image_takes_all_of_its(): void {
		$image  = LayerFiles::attachment( 420, 1626 );
		$other  = LayerFiles::attachment( 420, 1626 );
		$folder = wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER;
		$this->renditions()->make( $image, self::SIZES );
		$this->renditions()->make( $other, self::SIZES );
		$old = (array) glob( $folder . '/' . $image . '-*' );
		self::assertCount( 6, $old, 'three sizes in two types, named for their image' );

		// D-194: "An image edited or replaced in the Media Library has a new file, so new names; the old files go."
		update_attached_file( $image, LayerFiles::draw( 420, 1626, true, 'png', 0xd1242f ) );
		$this->renditions()->make( $image, self::SIZES );
		$now = (array) glob( $folder . '/' . $image . '-*' );
		self::assertCount( 6, $now, 'the new file\'s renditions, and the old file\'s gone' );
		self::assertSame( array(), array_intersect( $old, $now ) );

		wp_delete_attachment( $image, true );
		self::assertSame( array(), (array) glob( $folder . '/' . $image . '-*' ), 'an image deleted from the Media Library takes its renditions' );
		self::assertCount( 6, (array) glob( $folder . '/' . $other . '-*' ), 'and leaves another image\'s' );
	}

	public function test_a_mapping_of_the_renditions_types_is_set_aside_while_they_are_saved_and_kept_for_the_shops_uploads(): void {
		// Cowork's M7 review, F2: a shop that maps uploads to AVIF, as the demo's host does, and one that maps AVIF away.
		$image = LayerFiles::attachment( 420, 1626 );
		add_filter( 'image_editor_output_format', static fn( array $formats ): array => array( 'image/webp' => 'image/avif', 'image/avif' => 'image/jpeg' ) + $formats );
		$this->renditions()->make( $image, self::SIZES );
		$sources = $this->renditions()->sources( $image, self::SIZES );
		self::assertSame( array( 'image/avif', 'image/webp' ), array_keys( $sources ), 'both types made, neither taken away by the other' );
		$base = wp_get_upload_dir();
		foreach ( $sources as $mime => $files ) {
			self::assertCount( 3, $files, $mime );
			foreach ( $files as $file ) {
				$size = wp_getimagesize( $base['basedir'] . substr( $file['url'], strlen( $base['baseurl'] ) ) );
				self::assertSame( $mime, is_array( $size ) ? $size['mime'] : null, $file['url'] );
			}
		}
		self::assertSame( array(), (array) glob( $base['basedir'] . '/' . WpLayerRenditions::FOLDER . '/*.jpg' ), 'no file of a third type' );
		self::assertSame( array( 'image/webp' => 'image/avif', 'image/avif' => 'image/jpeg' ), array_intersect_key( wp_get_image_editor_output_format( 'x.webp', 'image/webp' ), array( 'image/webp' => 1, 'image/avif' => 1 ) ), 'the shop\'s mapping is as it was after' );
		remove_all_filters( 'image_editor_output_format' );

		$ordinary = LayerFiles::attachment( 1200, 900, false, 'jpeg' );
		$meta     = wp_get_attachment_metadata( $ordinary );
		self::assertIsArray( $meta );
		self::assertSame( 'image/jpeg', get_post_mime_type( $ordinary ) );
		foreach ( (array) ( $meta['sizes'] ?? array() ) as $name => $size ) {
			self::assertSame( 'image/jpeg', is_array( $size ) ? ( $size['mime-type'] ?? null ) : null, 'WordPress\'s ' . $name . ' size of an ordinary upload is as it makes it' );
		}
		self::assertNotSame( array(), (array) ( $meta['sizes'] ?? array() ) );
	}

	public function test_an_avif_layer_and_a_png_an_output_format_made_avif_each_get_both_renditions(): void {
		// Cowork's M7 review, F1 (D-210): the type is the file's own, so the editor is asked to read what is there.
		$avif = LayerFiles::attachment( 420, 1626, true, 'avif' );
		add_filter( 'image_editor_output_format', static fn( array $formats ): array => array( 'image/png' => 'image/avif' ) + $formats );
		try {
			$mapped = LayerFiles::attachment( 420, 1626 );
		} finally {
			remove_all_filters( 'image_editor_output_format' );
		}
		foreach ( array( $avif, $mapped ) as $image ) {
			$this->renditions()->make( $image, self::SIZES );
			$sources = $this->renditions()->sources( $image, self::SIZES );
			self::assertSame( array( 'image/avif', 'image/webp' ), array_keys( $sources ), (string) $image );
			$base = wp_get_upload_dir();
			$file = $base['basedir'] . substr( $sources['image/webp'][0]['url'], strlen( $base['baseurl'] ) );
			self::assertTrue( self::clearCorner( $file ), 'transparent: ' . $file );
		}
	}

	public function test_a_save_that_lands_elsewhere_is_logged_and_takes_no_rendition_away_and_a_stray_file_is_removed(): void {
		// The audit of the fixes (F2's failure path, D-209): a host's editor that writes another file than asked.
		$image  = LayerFiles::attachment( 420, 1626 );
		$logger = new FakeLogger();
		$made   = new WpLayerRenditions( $logger );
		$made->make( $image, self::SIZES );
		$folder = wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER;
		$webps  = (array) glob( $folder . '/*.webp' );
		self::assertCount( 3, $webps );
		foreach ( $webps as $webp ) {
			wp_delete_file( (string) $webp );
		}
		add_filter( 'wp_image_editors', static fn( array $editors ): array => array_merge( array( StrayEditor::class ), $editors ) );

		// Onto another rendition's name, the AVIF of the same size: logged, and the AVIF is not deleted.
		StrayEditor::$to = static fn( string $path ): array => array( preg_replace( '/\.webp$/', '.avif', $path ) ?? $path, 'image/avif' );
		$made->make( $image, self::SIZES );
		self::assertCount( 3, (array) glob( $folder . '/*.avif' ), 'every AVIF rendition kept' );
		self::assertSame( array( 'rendition_type_webp' => true ), $logger->keys );
		self::assertSame( array( 'warning', 'A layer image rendition was not made.' ), array( $logger->lines[0]['level'] ?? null, $logger->lines[0]['message'] ?? null ) );
		self::assertSame( 'type', $logger->lines[0]['context']['step'] ?? null );

		// Onto a name of no rendition's: removed.
		StrayEditor::$to = static fn( string $path ): array => array( dirname( $path ) . '/stray-' . wp_basename( $path, '.webp' ) . '.png', 'image/png' );
		$made->make( $image, self::SIZES );
		self::assertSame( array(), (array) glob( $folder . '/stray-*' ), 'no stray file left' );
		self::assertCount( 3, (array) glob( $folder . '/*.avif' ) );
	}

	public function test_an_image_that_is_gone_or_no_image_makes_nothing(): void {
		$image = LayerFiles::attachment( 420, 1626 );
		wp_delete_file( (string) get_attached_file( $image ) );
		$this->renditions()->make( $image, self::SIZES );
		self::assertSame( array(), $this->renditions()->sources( $image, self::SIZES ) );
		$post = self::factory()->post->create();
		$this->renditions()->make( $post, self::SIZES );
		self::assertSame( array(), $this->renditions()->sources( $post, self::SIZES ) );
		$this->renditions()->make( 0, array() );
		self::assertSame( array(), (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER . '/*' ) );
	}
}
