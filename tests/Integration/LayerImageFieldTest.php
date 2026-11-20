<?php
/**
 * The Layer image field on the Part specs panel, on real WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpLayerFile;
use CreativeBranding\SkateboardBuilder\Adapter\WpLayerImages;
use CreativeBranding\SkateboardBuilder\Admin\PartSpecsHooks;
use CreativeBranding\SkateboardBuilder\Admin\PartSpecsPanel;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerImage;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Spec, Admin, Milestone 7: "A Layer image field on each part's Part specs panel (simple products and variations)
 * takes a transparent PNG or WebP from the Media Library, previews it on the template at the part's size, and checks
 * its transparency and size." Spec, Data model: the layer image is an attachment ID in `_cbuild_layer`. D-190.
 */
final class LayerImageFieldTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	public function tear_down(): void {
		unset( $_POST['cbuild_layer'], $_POST['cbuild_variation_layer'], $GLOBALS['product_object'] );
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		parent::tear_down();
	}

	/**
	 * The hooks with a panel of this test's own.
	 */
	private function hooks(): PartSpecsHooks {
		$panel = ( new PartSpecsPanel( $this->service( SpecWriter::class ), $this->service( SpecIndexer::class ), $this->service( SpecTables::class ), $this->service( Kits::class ) ) )->withImages( $this->service( LayerImages::class ) );
		return new PartSpecsHooks( static fn(): PartSpecsPanel => $panel );
	}

	/**
	 * A simple product with these specs, named in a slot of a kit.
	 *
	 * @param string                $slot  The slot.
	 * @param array<string, string> $specs Its specs, as stored.
	 */
	private static function part( string $slot, array $specs = array() ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Part' );
		$product->set_regular_price( '10' );
		if ( array() !== $specs ) {
			$product->update_meta_data( Options::META_SPECS, $specs );
		}
		$product->save();
		$documents = KitDocuments::slots();
		foreach ( $documents['slots'] as $index => $row ) {
			if ( $slot === $row['key'] ) {
				$documents['slots'][ $index ]['sources'] = array( 'products' => array( $product->get_id() ) );
			}
		}
		KitFactory::kit( 'Complete ' . $slot, array( Options::META_KIT_SLOTS => $documents ) );
		return $product;
	}

	private function panel( \WC_Product $product ): string {
		$GLOBALS['product_object'] = wc_get_product( $product->get_id() );
		ob_start();
		$this->hooks()->product();
		return (string) ob_get_clean();
	}

	/**
	 * Send the Layer image field for this product, as WooCommerce's save does.
	 *
	 * @param \WC_Product $product The product.
	 * @param string|null $layer   The field; null when it is not sent.
	 */
	private function send( \WC_Product $product, ?string $layer ): \WC_Product {
		unset( $_POST['cbuild_layer'] );
		if ( null !== $layer ) {
			$_POST['cbuild_layer'] = wp_slash( $layer );
		}
		$product = wc_get_product( $product->get_id() );
		$this->hooks()->saveProduct( $product );
		$product->save();
		return wc_get_product( $product->get_id() );
	}

	/**
	 * The markup's Picture group, from its details to its end.
	 */
	private static function group( string $panel ): string {
		self::assertSame( 1, preg_match( '#<details class="cbuild-layer".*?</details>#s', $panel, $group ), 'the panel has a Picture group' );
		return $group[0];
	}

	public function test_a_deck_has_its_layer_image_field_open_with_a_preview_box_at_its_size(): void {
		$deck  = self::part( 'deck', array( 'deck_width' => '8.2500', 'deck_length' => '32.0000' ) );
		$group = self::group( $this->panel( $deck ) );

		self::assertStringContainsString( '<details class="cbuild-layer" open>', $group );
		self::assertStringContainsString( '<summary>Picture</summary>', $group );
		self::assertMatchesRegularExpression( '#<label for="cbuild-spec-layer">Layer image</label>\s*<input\s+type="text"\s+inputmode="numeric"\s+class="short cbuild-layer__id"\s+id="cbuild-spec-layer"\s+name="cbuild_layer"\s+value=""#', $group );
		self::assertStringNotContainsString( 'disabled', $group, 'an open group\'s field is sent' );
		self::assertStringContainsString( 'aria-describedby="cbuild-spec-layer-note cbuild-spec-layer-caption"', $group, 'the note, then the size the caption gives (Cowork\'s M7 review, M5)' );
		self::assertStringContainsString( 'Left empty, the picture shows the part&#039;s labelled photo.', $group );
		self::assertMatchesRegularExpression( '#<button type="button" class="button cbuild-layer__choose" data-cbuild-layer="cbuild-spec-layer" data-cbuild-title="Layer image" data-cbuild-types="image/png,image/webp,image/avif" hidden>Choose an image for Part</button>#', $group, 'without the script, the ID is typed; the button names the part, as a page of variations has one each (the auditor\'s 1.0.0 P5)' );
		// 209.55 by 812.8 mm, at half a pixel to the millimetre, rounded up.
		self::assertStringContainsString( '<div class="cbuild-layer__box" style="inline-size:105px;block-size:407px">', $group );
		self::assertMatchesRegularExpression( '#<img alt="" src="" hidden>#', $group, 'nothing to show yet' );
		self::assertStringContainsString( '<figcaption class="description" id="cbuild-spec-layer-caption">Drawn 209.6 by 812.8 mm, nose up. The image needs at least 420 pixels across or 1,626 along.</figcaption>', $group );
	}

	public function test_the_preview_shows_the_parts_image_on_the_chequer_and_a_wheel_is_drawn_at_its_diameter(): void {
		$wheels = self::part( 'wheels', array( 'wheel_diameter' => '54.0000' ) );
		$image  = LayerFiles::attachment( 80, 120 );
		$wheels->update_meta_data( Options::META_LAYER, (string) $image );
		$wheels->save();
		$group = self::group( $this->panel( $wheels ) );

		self::assertStringContainsString( 'value="' . $image . '"', $group );
		self::assertStringContainsString( '<img alt="" src="' . esc_url( (string) wp_get_attachment_image_url( $image, 'full' ) ) . '">', $group );
		self::assertStringContainsString( 'style="inline-size:17px;block-size:27px"', $group );
		self::assertStringContainsString( 'Drawn 32.4 by 54 mm, nose up. The image needs at least 65 pixels across or 108 along.', $group );
	}

	public function test_a_part_the_picture_does_not_draw_has_the_field_closed_with_no_preview(): void {
		$risers = self::part( 'risers', array( 'riser_thickness' => '3.0000' ) );
		$group  = self::group( $this->panel( $risers ) );

		self::assertStringContainsString( '<details class="cbuild-layer">', $group );
		self::assertMatchesRegularExpression( '#name="cbuild_layer"[^>]*disabled#s', $group, 'a closed group sends nothing (issue #23)' );
		self::assertStringNotContainsString( 'cbuild-layer__preview', $group );

		$trucks = self::part( 'trucks' );
		$group  = self::group( $this->panel( $trucks ) );
		self::assertStringContainsString( '<details class="cbuild-layer" open>', $group, 'trucks are drawn' );
		self::assertStringNotContainsString( 'cbuild-layer__preview', $group, 'but with no axle width there is no size to draw' );
	}

	public function test_a_transparent_png_or_webp_large_enough_is_saved_and_an_empty_field_clears_it(): void {
		$deck = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		$png  = LayerFiles::attachment( 420, 900 );
		self::assertSame( (string) $png, $this->send( $deck, ' ' . $png . ' ' )->get_meta( Options::META_LAYER, true ) );
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$meta_box_errors );

		$webp = LayerFiles::attachment( 300, 1626, true, 'webp' );
		self::assertSame( (string) $webp, $this->send( $deck, (string) $webp )->get_meta( Options::META_LAYER, true ) );

		self::assertSame( (string) $webp, $this->send( $deck, null )->get_meta( Options::META_LAYER, true ), 'a field not sent changes nothing' );
		self::assertSame( '', $this->send( $deck, '' )->get_meta( Options::META_LAYER, true ), 'empty clears it' );
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$meta_box_errors );
	}

	public function test_an_image_that_is_not_a_layer_is_not_saved_what_was_there_is_kept_and_the_notice_says_why(): void {
		$deck = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		$kept = LayerFiles::attachment( 420, 1626 );
		$this->send( $deck, (string) $kept );
		$page = self::factory()->post->create();

		foreach ( array(
			'abc'                                                       => 'Layer image was not saved: &quot;abc&quot; is not the ID of an image in the Media Library.',
			(string) $page                                              => 'Layer image was not saved: &quot;' . $page . '&quot; is not the ID of an image in the Media Library.',
			(string) LayerFiles::attachment( 420, 1626, true, 'jpeg' )  => 'Layer image was not saved: it is image/jpeg, and a layer must be a PNG, WebP or AVIF with a transparent background.',
			(string) LayerFiles::attachment( 420, 1626, false )         => 'Layer image was not saved: its corners are not transparent. Export the cut-out with a transparent background.',
			(string) LayerFiles::attachment( 419, 1625 )                => 'Layer image was not saved: it is too small for the size the picture draws this part. It needs at least 420 pixels across or 1,626 along.',
		) as $sent => $notice ) {
			\WC_Admin_Meta_Boxes::$meta_box_errors = array();
			self::assertSame( (string) $kept, $this->send( $deck, (string) $sent )->get_meta( Options::META_LAYER, true ), (string) $sent );
			self::assertSame( array( '<a href="#cbuild-spec-layer">' . $notice . '</a>' ), \WC_Admin_Meta_Boxes::$meta_box_errors, (string) $sent );
		}
	}

	public function test_an_avif_the_host_reads_is_saved_and_one_it_cannot_read_is_refused_with_its_own_reason(): void {
		// Cowork's M7 review, F1 (D-210).
		$deck = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		$avif = LayerFiles::attachment( 420, 1626, true, 'avif' );
		self::assertSame( array( 'image/avif', true, true ), array( $this->service( LayerImages::class )->facts( $avif )?->type, $this->service( LayerImages::class )->facts( $avif )?->clear, $this->service( LayerImages::class )->facts( $avif )?->readable ), 'GD reads AVIF in wp-env, its corners among it' );
		self::assertSame( (string) $avif, $this->send( $deck, (string) $avif )->get_meta( Options::META_LAYER, true ) );
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$meta_box_errors );

		// A host whose one library has no AVIF coder, as wp-env's Imagick.
		$kept = LayerFiles::attachment( 420, 1626 );
		$this->send( $deck, (string) $kept );
		$panel = $this->service( PartSpecsPanel::class )->withImages( new WpLayerImages( array( 'imagick' ) ) );
		if ( WpLayerFile::imagickReads( 'image/avif' ) ) {
			self::markTestIncomplete( 'This Imagick reads AVIF, so it stands in for no host without it.' );
		}
		$_POST['cbuild_layer'] = (string) $avif;
		$product               = wc_get_product( $deck->get_id() );
		( new PartSpecsHooks( static fn(): PartSpecsPanel => $panel ) )->saveProduct( $product );
		$product->save();
		self::assertSame( (string) $kept, wc_get_product( $deck->get_id() )->get_meta( Options::META_LAYER, true ) );
		self::assertSame( array( '<a href="#cbuild-spec-layer">Layer image was not saved: it is image/avif, and this server&#039;s image library cannot read that type. Upload the cut-out as a PNG or WebP, or ask your host to turn on AVIF in GD or Imagick.</a>' ), \WC_Admin_Meta_Boxes::$meta_box_errors );
	}

	public function test_imagick_has_a_coder_for_each_layer_type_the_server_accepts_and_no_other(): void {
		// One list (Cowork's M7 review, F1; the audit of the fixes): a type added to LayerImage::TYPES needs its coder.
		$coders = ( new \ReflectionClassConstant( WpLayerFile::class, 'CODERS' ) )->getValue();
		self::assertIsArray( $coders );
		self::assertSame( LayerImage::TYPES, array_keys( $coders ) );
	}

	public function test_a_png_on_a_host_with_no_library_to_read_it_is_saved_on_its_type_and_size_alone(): void {
		// PICTURE.md: where neither library reads the file, transparency is not known, and it is checked for its type
		// and size alone. Only an AVIF is refused for want of a reader (the spec review of the fixes, D-210).
		$deck  = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		$png   = LayerFiles::attachment( 420, 1626 );
		$none  = new WpLayerImages( array() );
		$facts = $none->facts( $png );
		self::assertSame( array( 'image/png', null, true ), array( $facts?->type, $facts?->clear, $facts?->readable ) );
		$_POST['cbuild_layer'] = (string) $png;
		$product               = wc_get_product( $deck->get_id() );
		$panel                 = $this->service( PartSpecsPanel::class )->withImages( $none );
		( new PartSpecsHooks( static fn(): PartSpecsPanel => $panel ) )->saveProduct( $product );
		$product->save();
		self::assertSame( (string) $png, wc_get_product( $deck->get_id() )->get_meta( Options::META_LAYER, true ) );
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$meta_box_errors );
	}

	public function test_a_png_an_output_format_mapping_made_avif_is_saved_and_read_as_the_file_it_is(): void {
		// Cowork's M7 review, F1: the demo's host maps every upload to AVIF; the attachment keeps image/png.
		add_filter( 'image_editor_output_format', static fn( array $formats ): array => array( 'image/png' => 'image/avif' ) + $formats );
		try {
			$mapped = LayerFiles::attachment( 420, 1626 );
		} finally {
			remove_all_filters( 'image_editor_output_format' );
		}
		self::assertSame( array( 'image/png', 'image/avif' ), array( get_post_mime_type( $mapped ), wp_get_image_mime( (string) get_attached_file( $mapped ) ) ) );
		$facts = $this->service( LayerImages::class )->facts( $mapped );
		self::assertSame( array( 'image/avif', 420, 1626, true, true ), array( $facts?->type, $facts?->width, $facts?->height, $facts?->clear, $facts?->readable ), 'the AVIF file, read as AVIF' );
		$deck = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		self::assertSame( (string) $mapped, $this->send( $deck, (string) $mapped )->get_meta( Options::META_LAYER, true ) );
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$meta_box_errors );

		// A host that reads PNG and not AVIF reads the PNG WordPress kept beside it.
		$imagick = ( new WpLayerImages( array( 'imagick' ) ) )->facts( $mapped );
		self::assertSame( WpLayerFile::imagickReads( 'image/avif' ) ? 'image/avif' : 'image/png', $imagick?->type );
		self::assertSame( array( true, true ), array( $imagick?->clear, $imagick?->readable ) );
	}

	public function test_the_chooser_lists_the_layer_types_the_server_accepts_and_the_caption_describes_the_field(): void {
		// Cowork's M7 review, F1 (one list for PHP and the script) and M5 (the caption in aria-describedby).
		$deck = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		$html = $this->panel( $deck );
		self::assertStringContainsString( 'data-cbuild-types="' . implode( ',', LayerImage::TYPES ) . '"', $html );
		self::assertSame( array( 'image/png', 'image/webp', 'image/avif' ), LayerImage::TYPES );
		self::assertMatchesRegularExpression( '/aria-describedby="cbuild-spec-layer-note cbuild-spec-layer-caption"/', $html );
		self::assertMatchesRegularExpression( '/<figcaption class="description" id="cbuild-spec-layer-caption">Drawn /', $html );
	}

	public function test_a_variation_saves_its_own_row_and_says_when_it_takes_its_parents_image(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Wheels' );
		$image = LayerFiles::attachment( 80, 120 );
		$parent->update_meta_data( Options::META_LAYER, (string) $image );
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '30' );
		$variation->save();

		ob_start();
		$this->hooks()->variation( 3, array(), get_post( $variation->get_id() ) );
		$fields = (string) ob_get_clean();
		$group  = self::group( $fields );
		self::assertStringContainsString( 'name="cbuild_variation_layer[3]"', $group );
		self::assertStringContainsString( 'id="cbuild-spec-3-layer"', $group );
		self::assertStringContainsString( 'value=""', $group );
		self::assertStringContainsString( 'In use: image #' . $image . ', from the parent product.', $group );
		self::assertStringContainsString( '<img alt="" src="' . esc_url( (string) wp_get_attachment_image_url( $image, 'full' ) ) . '">', $group, 'the parent\'s, previewed' );

		$own                              = LayerFiles::attachment( 80, 120, true, 'webp' );
		$_POST['cbuild_variation_layer']  = array(
			0 => '1',
			3 => (string) $own,
		);
		$loaded                           = wc_get_product( $variation->get_id() );
		$this->hooks()->saveVariation( $loaded, 3 );
		$loaded->save();
		self::assertSame( (string) $own, wc_get_product( $variation->get_id() )->get_meta( Options::META_LAYER, true ) );

		$_POST['cbuild_variation_layer'] = array( 3 => 'none' );
		$loaded                          = wc_get_product( $variation->get_id() );
		$this->hooks()->saveVariation( $loaded, 3 );
		$loaded->save();
		self::assertSame( (string) $own, wc_get_product( $variation->get_id() )->get_meta( Options::META_LAYER, true ) );
		self::assertSame(
			array( '<a href="#cbuild_variation_' . $variation->get_id() . '">Variation #' . $variation->get_id() . ': Layer image was not saved: &quot;none&quot; is not the ID of an image in the Media Library.</a>' ),
			\WC_Admin_Meta_Boxes::$meta_box_errors
		);
	}

	public function test_who_cannot_edit_products_saves_no_layer_image(): void {
		$deck = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		self::assertSame( '', $this->send( $deck, (string) LayerFiles::attachment( 420, 1626 ) )->get_meta( Options::META_LAYER, true ) );
	}

	// D-208: a panel that 0.6.0's container builds, in the request that puts 0.7.0's files in its place, has no images
	// to check the field against, and leaves the part's layer image as it was.
	public function test_a_panel_built_without_images_leaves_the_layer_image_as_it_was(): void {
		$deck = self::part( 'deck', array( 'deck_width' => '8.2500' ) );
		$kept = LayerFiles::attachment( 420, 1626 );
		$this->send( $deck, (string) $kept );
		$plain                 = new PartSpecsPanel( $this->service( SpecWriter::class ), $this->service( SpecIndexer::class ), $this->service( SpecTables::class ), $this->service( Kits::class ) );
		$_POST['cbuild_layer'] = '';
		$product               = wc_get_product( $deck->get_id() );
		( new PartSpecsHooks( static fn(): PartSpecsPanel => $plain ) )->saveProduct( $product );
		$product->save();
		self::assertSame( (string) $kept, wc_get_product( $deck->get_id() )->get_meta( Options::META_LAYER, true ) );
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$meta_box_errors );
	}

	public function test_gd_and_imagick_read_the_same_corners_and_neither_is_a_corner_not_known(): void {
		$clear  = LayerFiles::attachment( 40, 60 );
		$opaque = LayerFiles::attachment( 40, 60, false );
		$webp   = LayerFiles::attachment( 40, 60, true, 'webp' );
		foreach ( array( array( 'gd' ), array( 'imagick' ) ) as $libraries ) {
			$images = new WpLayerImages( $libraries );
			self::assertTrue( $images->facts( $clear )?->clear, $libraries[0] );
			self::assertFalse( $images->facts( $opaque )?->clear, $libraries[0] );
			self::assertTrue( $images->facts( $webp )?->clear, $libraries[0] . ', a WebP' );
			self::assertSame( array( 'image/webp', 40, 60 ), array( $images->facts( $webp )?->type, $images->facts( $webp )?->width, $images->facts( $webp )?->height ) );
		}
		self::assertNull( ( new WpLayerImages( array() ) )->facts( $clear )?->clear, 'with no library, the type and size are still read' );
		self::assertSame( 40, ( new WpLayerImages( array() ) )->facts( $clear )?->width );
	}

	public function test_a_file_that_is_gone_or_cut_short_is_answered_without_a_warning(): void {
		$images = new WpLayerImages();
		$gone   = LayerFiles::attachment( 40, 60 );
		unlink( (string) get_attached_file( $gone ) );
		self::assertNull( $images->facts( $gone ) );
		self::assertNull( $images->facts( 0 ) );

		// The header says a PNG of 40 by 60; the pixels are not there.
		$short = LayerFiles::attachment( 40, 60 );
		$file  = (string) get_attached_file( $short );
		file_put_contents( $file, substr( (string) file_get_contents( $file ), 0, 60 ) ); // phpcs:ignore WordPress.WP.AlternativeFunctions -- A test's own fixture.
		foreach ( array( array( 'gd' ), array( 'imagick' ) ) as $libraries ) {
			$facts = ( new WpLayerImages( $libraries ) )->facts( $short );
			self::assertSame( 40, $facts?->width, $libraries[0] );
			self::assertNull( $facts?->clear, $libraries[0] . ': not known' );
		}
	}
}
