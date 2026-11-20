<?php
/**
 * The specs CSV's `layer_image` column, in WordPress and WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\Sentences;
use CreativeBranding\SkateboardBuilder\Port\PartLayers;
use CreativeBranding\SkateboardBuilder\Service\LayerCheck;
use CreativeBranding\SkateboardBuilder\Service\SpecExport;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Cowork's 1.0.0 request (D-287).
 */
final class LayerColumnTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
	}

	public function tear_down(): void {
		delete_option( Options::IMPORT );
		parent::tear_down();
	}

	/**
	 * A deck 8.25 wide, named in a kit's Deck slot, which the picture draws at 420 by 1,626 pixels at least.
	 */
	private static function deck(): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Katie 8.25' );
		$product->set_regular_price( '60' );
		$product->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.2500' ) );
		$product->save();
		$documents = KitDocuments::slots();
		foreach ( $documents['slots'] as $index => $row ) {
			if ( 'deck' === $row['key'] ) {
				$documents['slots'][ $index ]['sources'] = array( 'products' => array( $product->get_id() ) );
			}
		}
		KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $documents ) );
		return $product;
	}

	/**
	 * Read a file, confirm it and run the import to its end.
	 *
	 * @param string $text The file.
	 * @return list<array<string, mixed>> The rows as planned before it ran.
	 */
	private function import( string $text ): array {
		$import = $this->service( SpecImport::class );
		self::assertSame( array(), $import->read( $text ) );
		$state = $import->state();
		self::assertNotNull( $state );
		$plan = $import->plan( $state, 0, $state->total );
		self::assertTrue( $import->confirm( $state->token ) );
		for ( $batch = 0; $batch < 5 && 'done' !== $import->state()?->status; ++$batch ) {
			$import->run();
		}
		return $plan;
	}

	/**
	 * A part's own Layer image, as stored.
	 *
	 * @param int $part The part.
	 */
	private static function layer( int $part ): string {
		return (string) wc_get_product( $part )->get_meta( Options::META_LAYER, true );
	}

	public function test_the_export_writes_each_parts_own_layer_image_by_the_name_it_was_uploaded_with(): void {
		$deck  = self::deck();
		$image = LayerFiles::attachment( 420, 1626 );
		$deck->update_meta_data( Options::META_LAYER, (string) $image );
		$deck->save();
		$name = wp_basename( (string) wp_get_original_image_path( $image ) );
		self::assertNotSame( '', $name );

		$rows = array();
		foreach ( array_slice( explode( "\n", trim( $this->service( SpecExport::class )->text() ) ), 1 ) as $line ) {
			$cells              = str_getcsv( $line, ',', '"', '' );
			$rows[ $cells[0] ] = $cells;
		}
		self::assertStringStartsWith( 'id,sku,name,spec_table,spec_size,layer_image,deck_width,', $this->service( SpecExport::class )->header() );
		self::assertSame( $name, $rows[ (string) $deck->get_id() ][5] );
	}

	public function test_a_file_name_sets_the_image_through_the_product_which_queues_its_index_job_and_leaves_the_product_image(): void {
		$deck  = self::deck();
		$photo = self::factory()->attachment->create_upload_object( LayerFiles::draw( 600, 600, false, 'jpeg' ) );
		$deck->set_image_id( (int) $photo );
		$deck->save();
		$image = LayerFiles::attachment( 420, 1626, true, 'webp' );
		$name  = $this->service( PartLayers::class )->fileName( $image );
		as_unschedule_all_actions( Options::JOB_INDEX_PART, array(), Options::QUEUE_GROUP );

		$plan = $this->import( "id,layer_image\n{$deck->get_id()},{$name}\n" );

		self::assertSame( array( array() ), array_column( $plan, 'problems' ) );
		self::assertSame( (string) $image, self::layer( $deck->get_id() ) );
		self::assertSame( (string) $photo, (string) wc_get_product( $deck->get_id() )->get_image_id(), 'the product image is left as it is' );
		self::assertSame( array(), wc_get_product( $deck->get_id() )->get_gallery_image_ids() );
		self::assertContains( array( 'part' => $deck->get_id() ), self::waiting( Options::JOB_INDEX_PART ), 'the part is indexed again, and the image copied' );

		$this->import( "id,layer_image\n{$deck->get_id()},\n" );
		self::assertSame( (string) $image, self::layer( $deck->get_id() ), 'an empty cell leaves a product\'s image' );
	}

	public function test_a_name_two_images_have_and_an_image_the_field_would_refuse_are_refused_in_the_fields_words(): void {
		$deck  = self::deck();
		$kept  = LayerFiles::attachment( 420, 1626 );
		$deck->update_meta_data( Options::META_LAYER, (string) $kept );
		$deck->save();
		$first = LayerFiles::attachment( 420, 1626 );
		$name  = $this->service( PartLayers::class )->fileName( $first );
		// The same name uploaded in another month: WordPress renames a clash in one folder only.
		$twin  = LayerFiles::attachment( 420, 1626 );
		$older = wp_upload_dir()['basedir'] . '/2025/01/' . $name;
		wp_mkdir_p( dirname( $older ) );
		rename( (string) get_attached_file( $twin ), $older );
		update_attached_file( $twin, $older );
		self::assertSame( array( $first, $twin ), $this->service( PartLayers::class )->named( $name ), 'two images uploaded with one name' );
		$small = LayerFiles::attachment( 419, 1625 );

		foreach ( array(
			$name                                       => 'Layer image was not saved: more than one image in the Media Library has the file name "' . $name . '" (IDs ' . $first . ' and ' . $twin . '). Use the ID of the one you mean.',
			'not-uploaded.png'                         => 'Layer image was not saved: no image in the Media Library has the file name "not-uploaded.png".',
			$this->service( PartLayers::class )->fileName( $small ) => 'Layer image was not saved: it is too small for the size the picture draws this part. It needs at least 420 pixels across or 1,626 along.',
		) as $cell => $sentence ) {
			$plan    = $this->import( "id,deck_width,layer_image\n{$deck->get_id()},8.5,{$cell}\n" );
			self::assertNotSame( array(), $plan[0]['problems'], (string) $cell );
			$problem = $plan[0]['problems'][0];
			self::assertSame( 'layer_image', $problem['key'], (string) $cell );
			self::assertSame( $sentence, Sentences::rowProblem( $problem['code'], $problem['text'] ), (string) $cell );
			self::assertSame( (string) $kept, self::layer( $deck->get_id() ), (string) $cell );
			self::assertSame( array( 'deck_width' => '8.2500' ), wc_get_product( $deck->get_id() )->get_meta( Options::META_SPECS, true ), 'the row is refused whole' );
		}
		self::assertSame( LayerCheck::AMBIGUOUS, $this->import( "id,layer_image\n{$deck->get_id()},{$name}\n" )[0]['problems'][0]['code'] );
		$this->import( "id,layer_image\n{$deck->get_id()},{$twin}\n" );
		self::assertSame( (string) $twin, self::layer( $deck->get_id() ), 'its ID names it' );
	}

	// The auditor's 1.0.0 minor 3: a name is looked for by the file's whole name, so a library with many files whose
	// names hold it still finds the one, wherever its ID falls.
	public function test_a_file_name_is_found_among_more_than_twenty_files_whose_names_hold_it(): void {
		for ( $n = 1; $n <= 25; $n++ ) {
			$decoy = wp_insert_attachment(
				array(
					'post_mime_type' => 'image/png',
					'post_status'    => 'inherit',
					'post_title'     => 'crowded copy ' . $n,
				)
			);
			update_attached_file( $decoy, wp_upload_dir()['basedir'] . "/2025/03/cbuild-crowded-copy-{$n}.png" );
		}
		$image = LayerFiles::attachment( 420, 1626 );
		$named = wp_upload_dir()['basedir'] . '/2025/04/cbuild-crowded.png';
		wp_mkdir_p( dirname( $named ) );
		rename( (string) get_attached_file( $image ), $named );
		update_attached_file( $image, $named );
		self::assertSame( array( $image ), $this->service( PartLayers::class )->named( 'cbuild-crowded.png' ) );
		self::assertSame( array(), $this->service( PartLayers::class )->named( 'crowded.png' ), 'a name is the whole name' );
	}

	// The auditor's 1.0.0 should-fix P1: a sheet exported and imported again names each part's own image, by its file
	// name. That is no change, so it is never looked up or checked again: another image with the same name, or a
	// kit that now draws the part larger, refused the row whole, its specs too.
	public function test_a_cell_naming_the_parts_own_image_keeps_it_whatever_else_has_that_name(): void {
		$deck = self::deck();
		$own  = LayerFiles::attachment( 420, 1626 );
		$deck->update_meta_data( Options::META_LAYER, (string) $own );
		$deck->save();
		$name = $this->service( PartLayers::class )->fileName( $own );
		// The same name uploaded in another month.
		$twin  = LayerFiles::attachment( 420, 1626 );
		$older = wp_upload_dir()['basedir'] . '/2025/02/' . $name;
		wp_mkdir_p( dirname( $older ) );
		rename( (string) get_attached_file( $twin ), $older );
		update_attached_file( $twin, $older );
		self::assertCount( 2, $this->service( PartLayers::class )->named( $name ) );

		foreach ( array( $name, (string) $own ) as $cell ) {
			$plan = $this->import( "id,deck_width,layer_image\n{$deck->get_id()},8.5,{$cell}\n" );
			self::assertSame( array(), $plan[0]['problems'], $cell );
			self::assertSame( SpecImport::KEEP_LAYER, $plan[0]['layer'], $cell );
			self::assertSame( (string) $own, self::layer( $deck->get_id() ), $cell );
			self::assertSame( '8.5000', wc_get_product( $deck->get_id() )->get_meta( Options::META_SPECS, true )['deck_width'] ?? null, 'the row\'s specs are saved' );
			$deck->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.2500' ) );
			$deck->save();
		}
	}

	public function test_an_empty_cell_gives_a_variation_its_parents_image(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Wheels' );
		$parent->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 80, 120 ) );
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '30' );
		$variation->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 80, 120 ) );
		$variation->save();

		$plan = $this->import( "id,layer_image\n{$parent->get_id()},\n{$variation->get_id()},\n" );

		self::assertSame( array( SpecImport::KEEP_LAYER, 0 ), array_column( $plan, 'layer' ) );
		self::assertNotSame( '', self::layer( $parent->get_id() ) );
		self::assertSame( '', self::layer( $variation->get_id() ) );
	}
}
