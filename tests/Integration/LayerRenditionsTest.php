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
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Plan, milestone 7: "The plugin makes its AVIF and WebP renditions at the sizes the picture shows, without changing
 * how WordPress handles the shop's other uploads." Audit, Anti-patterns: "a test uploading an ordinary image checks
 * WordPress's sizes are untouched" (D-194).
 */
final class LayerRenditionsTest extends IntegrationTestCase {

	private const SIZES = array( array( 105, 407 ), array( 210, 813 ), array( 420, 1626 ) );

	public function tear_down(): void {
		remove_all_filters( 'image_editor_output_format' );
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

	public function test_a_type_a_filter_turns_into_another_is_not_kept_and_the_shops_uploads_are_as_wordpress_makes_them(): void {
		$image = LayerFiles::attachment( 420, 1626 );
		add_filter( 'image_editor_output_format', static fn( array $formats ): array => array( 'image/avif' => 'image/jpeg' ) + $formats );
		$this->renditions()->make( $image, self::SIZES );
		$sources = $this->renditions()->sources( $image, self::SIZES );
		self::assertSame( array( 'image/webp' ), array_keys( $sources ) );
		self::assertSame( array(), (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpLayerRenditions::FOLDER . '/*.jpg' ), 'the file the filter made is removed' );
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
