<?php
/**
 * A sample shop for looking at the builder as a shopper would (milestone 10's walkthrough): a kit, "Sample complete",
 * with fourteen decks under five brands, each deck a variable product whose widths are its variations and which shares
 * one photo, so each is one card with "Also in" (D-240); trucks, wheels, bearings, hardware and grip with photos. The
 * photos are drawn here, plain shapes in each brand's colours. No test reads it, so the suite's own shop stays as its
 * tests expect: run it by hand, `npx wp-env run cli wp eval-file
 * wp-content/plugins/creative-skateboard-builder/tests/e2e/fixtures/sample-shop.php`. A second run makes nothing new.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare(strict_types=1);

use CreativeBranding\SkateboardBuilder\Adapter\KitProduct;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;

require_once ABSPATH . 'wp-admin/includes/image.php';

/**
 * A photo of a part, drawn and stored as an attachment once by its name.
 *
 * @param string $name   The file's name, without its extension.
 * @param string $shape  deck, trucks, wheels, bearings, hardware or grip.
 * @param string $ground The part's colour, as a hex code.
 * @param string $mark   The graphic's colour, as a hex code.
 * @return int The attachment's ID.
 */
function sample_shop_photo( string $name, string $shape, string $ground, string $mark ): int {
	$found = get_posts(
		array(
			'post_type'   => 'attachment',
			'name'        => 'sample-' . $name,
			'numberposts' => 1,
			'fields'      => 'ids',
		)
	);
	// An attachment is reused only while its file is there: a site whose uploads were reset under its database kept
	// the row and lost the photo, and the shop's pages asked for it with a 404 (M12's combined /verify, lane 2).
	$kept = array() === $found ? '' : (string) get_attached_file( (int) $found[0] );
	if ( '' !== $kept && file_exists( $kept ) ) {
		return (int) $found[0];
	}
	$size  = 600;
	$image = imagecreatetruecolor( $size, $size );
	$hex   = static function ( string $code ) use ( $image ): int {
		$rgb = sscanf( ltrim( $code, '#' ), '%02x%02x%02x' );
		return (int) imagecolorallocate( $image, (int) $rgb[0], (int) $rgb[1], (int) $rgb[2] );
	};
	imagefill( $image, 0, 0, $hex( '#ececec' ) );
	$fill  = $hex( $ground );
	$ink   = $hex( $mark );
	$metal = $hex( '#9aa0a6' );
	switch ( $shape ) {
		case 'deck':
			// A deck seen from underneath, upright, with a band and a ring, as the prototype's thumbnails are.
			imagefilledrectangle( $image, 235, 90, 365, 510, $fill );
			imagefilledellipse( $image, 300, 90, 130, 130, $fill );
			imagefilledellipse( $image, 300, 510, 130, 130, $fill );
			imagefilledpolygon( $image, array( 235, 300, 365, 255, 365, 300, 235, 345 ), $ink );
			imagesetthickness( $image, 14 );
			imageellipse( $image, 300, 410, 60, 60, $ink );
			break;
		case 'trucks':
			imagefilledrectangle( $image, 130, 280, 470, 320, $metal );
			imagefilledrectangle( $image, 250, 230, 350, 370, $fill );
			imagefilledellipse( $image, 300, 300, 50, 50, $ink );
			break;
		case 'wheels':
			foreach ( array( array( 210, 210 ), array( 390, 210 ), array( 210, 390 ), array( 390, 390 ) ) as $at ) {
				imagefilledellipse( $image, $at[0], $at[1], 160, 160, $fill );
				imagefilledellipse( $image, $at[0], $at[1], 50, 50, $ink );
			}
			break;
		case 'bearings':
			imagefilledellipse( $image, 300, 300, 300, 300, $metal );
			imagefilledellipse( $image, 300, 300, 200, 200, $fill );
			imagefilledellipse( $image, 300, 300, 90, 90, $hex( '#ececec' ) );
			break;
		case 'hardware':
			for ( $i = 0; $i < 4; $i++ ) {
				imagefilledrectangle( $image, 170 + $i * 80, 220, 190 + $i * 80, 420, $metal );
				imagefilledellipse( $image, 180 + $i * 80, 215, 60, 30, $fill );
			}
			break;
		default:
			imagefilledrectangle( $image, 200, 80, 400, 520, $fill );
			imagefilledrectangle( $image, 200, 280, 400, 320, $ink );
	}
	if ( '' !== $kept ) {
		// The row stays, so every product that shows it keeps its photo: only the file is drawn again, where it was.
		wp_mkdir_p( dirname( $kept ) );
		imagepng( $image, $kept );
		wp_update_attachment_metadata( (int) $found[0], wp_generate_attachment_metadata( (int) $found[0], $kept ) );
		return (int) $found[0];
	}
	$uploads = wp_upload_dir();
	$file    = trailingslashit( $uploads['path'] ) . 'sample-' . $name . '.png';
	imagepng( $image, $file );
	$id = wp_insert_attachment(
		array(
			'post_title'     => 'Sample ' . $name,
			'post_name'      => 'sample-' . $name,
			'post_mime_type' => 'image/png',
			'post_status'    => 'inherit',
		),
		$file
	);
	wp_update_attachment_metadata( $id, wp_generate_attachment_metadata( $id, $file ) );
	return (int) $id;
}

/**
 * A brand, found by its slug or made.
 *
 * @param string $name The brand's name.
 */
function sample_shop_brand( string $name ): int {
	$slug = sanitize_title( $name );
	$term = get_term_by( 'slug', $slug, 'product_brand' );
	return $term instanceof WP_Term ? $term->term_id : (int) wp_insert_term( $name, 'product_brand', array( 'slug' => $slug ) )['term_id'];
}

/**
 * A category, found by its slug or made.
 *
 * @param string $slot The slot it feeds.
 */
function sample_shop_category( string $slot ): int {
	$slug = 'sample-' . $slot;
	$term = get_term_by( 'slug', $slug, 'product_cat' );
	return $term instanceof WP_Term ? $term->term_id : (int) wp_insert_term( 'Sample ' . $slot, 'product_cat', array( 'slug' => $slug ) )['term_id'];
}

/**
 * A part sold in sizes: a variable product, one photo, a variation a size with its own specs.
 *
 * @param string                                       $slot     The slot.
 * @param string                                       $brand    Its brand.
 * @param string                                       $name     Its name.
 * @param string                                       $label    The size attribute's name, such as Width.
 * @param array<string, array{0: string, 1: array<string, string>, 2: bool}> $sizes  Each size's label: its price, its specs and whether it is in stock.
 * @param int                                          $photo    Its photo.
 */
function sample_shop_variable( string $slot, string $brand, string $name, string $label, array $sizes, int $photo ): void {
	$sku     = 'sample-' . sanitize_title( $brand . '-' . $name );
	$product = new WC_Product_Variable( wc_get_product_id_by_sku( $sku ) );
	$size    = new WC_Product_Attribute();
	$size->set_name( $label );
	$size->set_options( array_map( 'strval', array_keys( $sizes ) ) );
	$size->set_visible( true );
	$size->set_variation( true );
	$product->set_name( $name );
	$product->set_slug( $sku );
	$product->set_sku( $sku );
	$product->set_attributes( array( $size ) );
	$product->set_status( 'publish' );
	$product->set_category_ids( array( sample_shop_category( $slot ) ) );
	$product->set_image_id( $photo );
	$product->save();
	wp_set_object_terms( $product->get_id(), array( sample_shop_brand( $brand ) ), 'product_brand' );
	$indexer = Plugin::container()->get( SpecIndexer::class );
	$kept    = array();
	foreach ( $sizes as $value => $about ) {
		$variation_sku = $sku . '-' . sanitize_title( str_replace( '″', 'in', (string) $value ) );
		$variation     = new WC_Product_Variation( wc_get_product_id_by_sku( $variation_sku ) );
		$variation->set_parent_id( $product->get_id() );
		$variation->set_attributes( array( sanitize_title( $label ) => (string) $value ) );
		$variation->set_sku( $variation_sku );
		$variation->set_regular_price( $about[0] );
		$variation->set_stock_status( $about[2] ? 'instock' : 'outofstock' );
		$variation->update_meta_data( '_cbuild_specs', $about[1] );
		$variation->save();
		$indexer->index( $variation->get_id() );
		$kept[] = $variation->get_id();
	}
	// A size an earlier run sold and this one doesn't is gone.
	foreach ( array_diff( $product->get_children(), $kept ) as $gone ) {
		wp_delete_post( $gone, true );
	}
	WC_Product_Variable::sync( $product->get_id() );
	$indexer->index( $product->get_id() );
}

/**
 * A part sold in one size.
 *
 * @param string                $slot  The slot.
 * @param string                $brand Its brand.
 * @param string                $name  Its name.
 * @param string                $price Its price.
 * @param array<string, string> $specs Its specs.
 * @param int                   $photo Its photo.
 */
function sample_shop_simple( string $slot, string $brand, string $name, string $price, array $specs, int $photo ): void {
	$sku     = 'sample-' . sanitize_title( $brand . '-' . $name );
	$product = new WC_Product_Simple( wc_get_product_id_by_sku( $sku ) );
	$product->set_name( $name );
	$product->set_slug( $sku );
	$product->set_sku( $sku );
	$product->set_regular_price( $price );
	$product->set_status( 'publish' );
	$product->set_category_ids( array( sample_shop_category( $slot ) ) );
	$product->set_image_id( $photo );
	$product->update_meta_data( '_cbuild_specs', $specs );
	$product->save();
	wp_set_object_terms( $product->get_id(), array( sample_shop_brand( $brand ) ), 'product_brand' );
	Plugin::container()->get( SpecIndexer::class )->index( $product->get_id() );
}

// Decks: brand, graphic, price, the widths it comes in (a width with false is sold out), and its colours.
$sample_decks = array(
	array( 'Pacer', 'Blind Bag Series 3', '124.99', array( '8.25' => true ), '#cfe0ea', '#4b6170' ),
	array( 'Pacer', 'Blind Bag Series 2', '124.99', array( '8.25' => true ), '#b8242c', '#f4f1ea' ),
	array( 'Harbour', 'Katie', '94.99', array( '8.0' => true, '8.25' => true, '8.5' => true, '8.75' => true ), '#1d1d1d', '#e8e8e8' ),
	array( 'Harbour', 'Kelly', '94.99', array( '8.0' => true, '8.25' => true, '8.5' => true, '8.75' => true ), '#2f7a5c', '#f2e600' ),
	array( 'Kestrel', 'White Dress', '89.99', array( '8.0' => true, '8.25' => true, '8.5' => true ), '#a8232b', '#f4f1ea' ),
	array( 'Kestrel', 'Backyard', '89.99', array( '8.25' => true, '8.5' => true ), '#8fd9c4', '#f4f1ea' ),
	array( 'Hoser', 'Night Swim', '79.99', array( '8.0' => true, '8.25' => true, '8.5' => false ), '#1e2a4a', '#e8c35a' ),
	array( 'Hoser', 'Static', '79.99', array( '8.25' => true, '8.5' => true ), '#d8d0c0', '#3a3a3a' ),
	array( 'Gnarmy', 'Field Day', '84.99', array( '8.0' => true, '8.25' => true ), '#6b7f3a', '#f1e7c8' ),
	array( 'Gnarmy', 'Signal', '84.99', array( '8.25' => true, '8.5' => true, '8.75' => true ), '#ff7a1a', '#1d1d1d' ),
	array( 'Pacer', 'Logo', '69.99', array( '8.0' => true, '8.25' => true, '8.5' => true ), '#f4f1ea', '#1d1d1d' ),
	array( 'Harbour', 'Tidal', '99.99', array( '8.25' => true, '8.5' => true ), '#3a7bd5', '#f4f1ea' ),
	array( 'Kestrel', 'Dusk', '89.99', array( '8.25' => false, '8.5' => true ), '#5b3a6b', '#f2b8c6' ),
	array( 'Hoser', 'Paper Plane', '74.99', array( '8.25' => true ), '#e9e4d8', '#c0392b' ),
);
foreach ( $sample_decks as $sample_deck ) {
	$sample_sizes = array();
	foreach ( $sample_deck[3] as $sample_width => $sample_stock ) {
		$sample_sizes[ $sample_width . '″' ] = array(
			$sample_deck[2],
			array(
				'deck_width'   => number_format( (float) $sample_width, 4, '.', '' ),
				'deck_length'  => (float) $sample_width >= 8.5 ? '32.2500' : '31.7500',
				'hole_pattern' => 'new_school',
			),
			$sample_stock,
		);
	}
	sample_shop_variable( 'deck', $sample_deck[0], $sample_deck[1], 'Deck width', $sample_sizes, sample_shop_photo( 'deck-' . sanitize_title( $sample_deck[0] . '-' . $sample_deck[1] ), 'deck', $sample_deck[4], $sample_deck[5] ) );
}

// Trucks: each sold in the axle widths that suit 8.0 to 8.75 decks.
$sample_truck = array(
	'baseplate'     => 'new_school',
	'max_wheel'     => '56.0000',
	'axle_diameter' => '8.0000',
	'set_size'      => '2',
);
$sample_axles = array(
	'8″'    => array( '8.0000', '7.8000', '8.2000' ),
	'8.25″' => array( '8.2500', '8.1250', '8.3750' ),
	'8.5″'  => array( '8.5000', '8.3750', '8.6000' ),
	'8.75″' => array( '8.7500', '8.6000', '9.0000' ),
);
foreach ( array( array( 'Harbour', 'Stage 11', '64.00', '#9aa0a6', '#1d1d1d' ), array( 'Pacer', 'Hollow Light', '72.00', '#c9a227', '#1d1d1d' ), array( 'Gnarmy', 'Standard', '52.00', '#3a3a3a', '#f2e600' ) ) as $sample_trucks ) {
	$sample_sizes = array();
	foreach ( $sample_axles as $sample_label => $sample_axle ) {
		$sample_sizes[ $sample_label ] = array(
			$sample_trucks[2],
			array(
				'axle_width' => $sample_axle[0],
				'deck_min'   => $sample_axle[1],
				'deck_max'   => $sample_axle[2],
			) + $sample_truck,
			true,
		);
	}
	sample_shop_variable( 'trucks', $sample_trucks[0], $sample_trucks[1], 'Truck axle', $sample_sizes, sample_shop_photo( 'trucks-' . sanitize_title( $sample_trucks[0] . '-' . $sample_trucks[1] ), 'trucks', $sample_trucks[3], $sample_trucks[4] ) );
}

// Wheels, bearings, hardware and grip.
foreach ( array(
	array( 'Retna', 'Conical 52', '44.99', '52.0000', '#f4f1ea', '#c0392b' ),
	array( 'Retna', 'Conical 54', '44.99', '54.0000', '#8e44ad', '#f4f1ea' ),
	array( 'Blotto', 'Classic 53', '39.99', '53.0000', '#f4f1ea', '#1d1d1d' ),
	array( 'Blotto', 'Cruiser 56', '42.99', '56.0000', '#8fd9c4', '#1d1d1d' ),
) as $sample_wheels ) {
	sample_shop_simple(
		'wheels',
		$sample_wheels[0],
		$sample_wheels[1],
		$sample_wheels[2],
		array(
			'wheel_diameter' => $sample_wheels[3],
			'set_size'       => '4',
		),
		sample_shop_photo( 'wheels-' . sanitize_title( $sample_wheels[0] . '-' . $sample_wheels[1] ), 'wheels', $sample_wheels[4], $sample_wheels[5] )
	);
}
sample_shop_simple(
	'bearings',
	'Harbour',
	'Ceramic bearings',
	'69.99',
	array(
		'bearing_bore' => '8.0000',
		'set_size'     => '8',
	),
	sample_shop_photo( 'bearings-ceramic', 'bearings', '#1d1d1d', '#f2e600' )
);
sample_shop_simple(
	'bearings',
	'Gnarmy',
	'Reds',
	'24.99',
	array(
		'bearing_bore' => '8.0000',
		'set_size'     => '8',
	),
	sample_shop_photo( 'bearings-reds', 'bearings', '#c0392b', '#f4f1ea' )
);
sample_shop_simple(
	'hardware',
	'Pacer',
	'Hardware, Jade',
	'6.99',
	array(
		'bolt_length' => '1.0000',
		'set_size'    => '8',
	),
	sample_shop_photo( 'hardware-jade', 'hardware', '#2f7a5c', '#f4f1ea' )
);
sample_shop_simple(
	'grip',
	'Kestrel',
	'Mumber-Fur grip',
	'12.50',
	array(
		'grip_width'  => '9.0000',
		'grip_length' => '33.0000',
	),
	sample_shop_photo( 'grip-mumber', 'grip', '#1d1d1d', '#f2e600' )
);

// As a shop does, the attributes the sizes are sold by are mapped to the slots' size specs, so a size is never a
// choice of its own; the variations' own specs still give the values. The settings test clears the mapping first.
$sample_settings = get_option( 'cbuild_settings', array() );
$sample_settings = is_array( $sample_settings ) ? $sample_settings : array();
$sample_mapping  = array_values(
	array_filter(
		(array) ( $sample_settings['attribute_mapping'] ?? array() ),
		static fn( mixed $line ): bool => ! is_array( $line ) || ! in_array( $line['attribute'] ?? '', array( 'Deck width', 'Truck axle' ), true )
	)
);
$sample_settings['attribute_mapping'] = array_merge(
	$sample_mapping,
	array(
		array(
			'attribute' => 'Deck width',
			'spec'      => 'deck_width',
			'unit'      => 'in',
		),
		array(
			'attribute' => 'Truck axle',
			'spec'      => 'axle_width',
			'unit'      => 'in',
		),
	)
);
update_option( 'cbuild_settings', $sample_settings );

// The kit.
$sample_slots = array();
foreach ( array(
	'deck'     => array( 'Deck', true, 1 ),
	'trucks'   => array( 'Trucks', true, 2 ),
	'wheels'   => array( 'Wheels', true, 4 ),
	'bearings' => array( 'Bearings', true, 8 ),
	'hardware' => array( 'Hardware', true, 8 ),
	'grip'     => array( 'Grip tape', true, 1 ),
) as $sample_key => $sample_slot ) {
	$sample_slots[] = array(
		'key'      => $sample_key,
		'label'    => $sample_slot[0],
		'required' => $sample_slot[1],
		'units'    => $sample_slot[2],
		'sources'  => array( 'categories' => array( sample_shop_category( $sample_key ) ) ),
	);
}
$sample_post = get_page_by_path( 'sample-complete', OBJECT, 'product' );
$sample_kit  = new KitProduct( $sample_post instanceof WP_Post ? $sample_post->ID : 0 );
$sample_kit->set_name( 'Sample complete' );
$sample_kit->set_slug( 'sample-complete' );
$sample_kit->set_status( 'publish' );
$sample_kit->setDocument(
	'_cbuild_slots',
	array(
		'schema_version' => 1,
		'slots'          => $sample_slots,
	)
);
$sample_kit->setDocument( '_cbuild_rules', RuleSettings::DEFAULTS );
$sample_kit->setDocument(
	'_cbuild_presets',
	array(
		'schema_version' => 1,
		'presets'        => array(),
	)
);
$sample_kit->setDocument(
	'_cbuild_assembly',
	array(
		'schema_version' => 1,
		'default_on'     => true,
		'fee'            => '0',
		'lead_time'      => '2 business days',
		'returns_notice' => '',
	)
);
$sample_kit->save();
WP_CLI::success( 'The sample shop is at ' . get_permalink( $sample_kit->get_id() ) );
