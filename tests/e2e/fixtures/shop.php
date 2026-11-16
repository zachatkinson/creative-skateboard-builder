<?php
/**
 * The browser site's shop, for the smoke test: the shop open to visitors and one product to visit. Run by
 * tests/e2e/support/global-setup.ts with `wp eval-file`; safe to run again. Milestone 5 adds a kit a visitor can buy, its parts and the pages the carts are tested on; milestone 6 the builder's shop and a page with the Builder block.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// A new WooCommerce shop shows visitors a "coming soon" page in place of every shop page.
update_option( 'woocommerce_coming_soon', 'no' );

if ( 0 === wc_get_product_id_by_sku( 'smoke-test-deck' ) ) {
	$cbuild_product = new WC_Product_Simple();
	$cbuild_product->set_name( 'Smoke test deck' );
	$cbuild_product->set_slug( 'smoke-test-deck' );
	$cbuild_product->set_sku( 'smoke-test-deck' );
	$cbuild_product->set_regular_price( '60' );
	$cbuild_product->set_status( 'publish' );
	$cbuild_product->save();
}

// A variable product with two variations, for the Part specs panel inside a variation (milestone 3).
if ( 0 === wc_get_product_id_by_sku( 'panel-test-trucks' ) ) {
	$cbuild_size = new WC_Product_Attribute();
	$cbuild_size->set_name( 'Size' );
	$cbuild_size->set_options( array( '139', '149' ) );
	$cbuild_size->set_visible( true );
	$cbuild_size->set_variation( true );
	$cbuild_trucks = new WC_Product_Variable();
	$cbuild_trucks->set_name( 'Panel test trucks' );
	$cbuild_trucks->set_slug( 'panel-test-trucks' );
	$cbuild_trucks->set_sku( 'panel-test-trucks' );
	$cbuild_trucks->set_attributes( array( $cbuild_size ) );
	$cbuild_trucks->set_status( 'publish' );
	$cbuild_trucks->save();
	foreach ( array( '139', '149' ) as $cbuild_option ) {
		$cbuild_variation = new WC_Product_Variation();
		$cbuild_variation->set_parent_id( $cbuild_trucks->get_id() );
		$cbuild_variation->set_attributes( array( 'size' => $cbuild_option ) );
		$cbuild_variation->set_sku( 'panel-test-trucks-' . $cbuild_option );
		$cbuild_variation->set_regular_price( '30' );
		$cbuild_variation->save();
	}
}

// A kit, for the Build tab (milestone 4). A kit has no SKU field to be found by, so its slug finds it. Its slots are
// written afresh each run, so every run starts from the same kit. The last slot names the two products above: where
// a shop has kits, a product no kit takes has no Part specs fields (D-105), and those products' specs are tested. It
// is not a slot the rules read and takes one unit, so it opens no group on them and asks no set size of them.
$cbuild_post = get_page_by_path( 'build-tab-test-kit', OBJECT, 'product' );
$cbuild_kit  = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_kit->set_name( 'Build tab test kit' );
$cbuild_kit->set_slug( 'build-tab-test-kit' );
$cbuild_kit->set_status( 'publish' );
$cbuild_slots = array();
foreach ( array(
	'deck'     => array( 'Deck', true, 1 ),
	'trucks'   => array( 'Trucks', true, 2 ),
	'wheels'   => array( 'Wheels', true, 4 ),
	'risers'   => array( 'Risers', false, 2 ),
	'bearings' => array( 'Bearings', true, 8 ),
	'hardware' => array( 'Hardware', true, 8 ),
	'grip'     => array( 'Grip tape', true, 1 ),
	'extras'   => array( 'Extras', false, 1 ),
) as $cbuild_key => $cbuild_slot ) {
	$cbuild_slots[] = array(
		'key'      => $cbuild_key,
		'label'    => $cbuild_slot[0],
		'required' => $cbuild_slot[1],
		'units'    => $cbuild_slot[2],
	) + ( 'extras' === $cbuild_key ? array( 'sources' => array( 'products' => array( wc_get_product_id_by_sku( 'smoke-test-deck' ), wc_get_product_id_by_sku( 'panel-test-trucks' ) ) ) ) : array() );
}
$cbuild_kit->setDocument(
	'_cbuild_slots',
	array(
		'schema_version' => 1,
		'slots'          => $cbuild_slots,
	)
);
$cbuild_kit->save();

// A kit a visitor can buy, with one part that fits in each slot, for the carts (milestone 5). Each part is found by its
// SKU and its slot's category by its slug, so a second run makes nothing new. The kit's and the parts' IDs are written
// where the browser tests read them, since the builder that would send them arrives in milestone 6.
$cbuild_parts = array(
	'deck'     => array( 'Deck', '70', 1, true, array( 'deck_width' => '8.5000', 'deck_length' => '32.0000', 'hole_pattern' => 'new_school' ) ),
	'trucks'   => array( 'Trucks', '27', 2, true, array( 'axle_width' => '8.5000', 'deck_min' => '8.3750', 'deck_max' => '8.6000', 'baseplate' => 'new_school', 'max_wheel' => '56.0000', 'axle_diameter' => '8.0000', 'set_size' => '1' ) ),
	'wheels'   => array( 'Wheels', '32', 4, true, array( 'wheel_diameter' => '54.0000', 'set_size' => '4' ) ),
	'bearings' => array( 'Bearings', '15', 8, true, array( 'bearing_bore' => '8.0000', 'set_size' => '8' ) ),
	'hardware' => array( 'Hardware', '4', 8, true, array( 'bolt_length' => '1.0000', 'set_size' => '8' ) ),
	'grip'     => array( 'Grip tape', '8', 1, true, array( 'grip_width' => '9.0000', 'grip_length' => '33.0000' ) ),
);
$cbuild_picks = array();
$cbuild_slots = array();
foreach ( $cbuild_parts as $cbuild_key => $cbuild_part ) {
	$cbuild_term = get_term_by( 'slug', 'cart-test-' . $cbuild_key, 'product_cat' );
	$cbuild_term = $cbuild_term instanceof WP_Term ? $cbuild_term->term_id : (int) wp_insert_term( 'Cart test ' . $cbuild_key, 'product_cat', array( 'slug' => 'cart-test-' . $cbuild_key ) )['term_id'];
	$cbuild_id   = wc_get_product_id_by_sku( 'cart-test-' . $cbuild_key );
	$cbuild_item = new WC_Product_Simple( $cbuild_id );
	$cbuild_item->set_name( 'Cart test ' . $cbuild_key );
	$cbuild_item->set_slug( 'cart-test-' . $cbuild_key );
	$cbuild_item->set_sku( 'cart-test-' . $cbuild_key );
	$cbuild_item->set_regular_price( $cbuild_part[1] );
	$cbuild_item->set_status( 'publish' );
	$cbuild_item->set_category_ids( array( $cbuild_term ) );
	$cbuild_item->update_meta_data( '_cbuild_specs', $cbuild_part[4] );
	$cbuild_item->save();
	CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\SpecIndexer::class )->index( $cbuild_item->get_id() );
	$cbuild_picks[ $cbuild_key ] = $cbuild_item->get_id();
	$cbuild_slots[]              = array(
		'key'      => $cbuild_key,
		'label'    => $cbuild_part[0],
		'required' => $cbuild_part[3],
		'units'    => $cbuild_part[2],
		'sources'  => array( 'categories' => array( $cbuild_term ) ),
	);
}
$cbuild_post = get_page_by_path( 'cart-test-kit', OBJECT, 'product' );
$cbuild_kit  = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_kit->set_name( 'Cart test kit' );
$cbuild_kit->set_slug( 'cart-test-kit' );
$cbuild_kit->set_status( 'publish' );
$cbuild_kit->setDocument(
	'_cbuild_slots',
	array(
		'schema_version' => 1,
		'slots'          => $cbuild_slots,
	)
);
$cbuild_kit->setDocument( '_cbuild_rules', CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings::DEFAULTS );
$cbuild_kit->setDocument(
	'_cbuild_presets',
	array(
		'schema_version' => 1,
		'presets'        => array(),
	)
);
$cbuild_kit->setDocument(
	'_cbuild_assembly',
	array(
		'schema_version' => 1,
		'default_on'     => false,
		'fee'            => '15',
		'lead_time'      => '',
		'returns_notice' => '',
	)
);
$cbuild_kit->save();

// The pages the carts are tested on: the Mini-Cart on a page of its own, and a classic cart and checkout, which the
// must-use plugin beside this makes the shop's cart and checkout pages for a browser that sends its cookie.
$cbuild_pages = array();
foreach ( array(
	'cart-test-mini-cart'        => '<!-- wp:woocommerce/mini-cart /-->',
	'cart-test-classic-cart'     => '<!-- wp:shortcode -->[woocommerce_cart]<!-- /wp:shortcode -->',
	'cart-test-classic-checkout' => '<!-- wp:shortcode -->[woocommerce_checkout]<!-- /wp:shortcode -->',
) as $cbuild_slug => $cbuild_content ) {
	$cbuild_page              = get_page_by_path( $cbuild_slug );
	$cbuild_pages[ $cbuild_slug ] = $cbuild_page instanceof WP_Post ? $cbuild_page->ID : (int) wp_insert_post(
		array(
			'post_type'    => 'page',
			'post_status'  => 'publish',
			'post_name'    => $cbuild_slug,
			'post_title'   => ucfirst( str_replace( '-', ' ', $cbuild_slug ) ),
			'post_content' => $cbuild_content,
		)
	);
}
// Not under the plugin's prefix: the zip's lifecycle counts every `cbuild_` option as the plugin's own, to be gone on uninstall.
delete_option( 'cbuild_e2e_classic_pages' );
update_option( 'e2e_classic_pages_for_cbuild', array( 'cart' => $cbuild_pages['cart-test-classic-cart'], 'checkout' => $cbuild_pages['cart-test-classic-checkout'] ) );

// An order can be placed: an offline payment method, and a shipping method wherever the shopper is.
update_option( 'woocommerce_cheque_settings', array( 'enabled' => 'yes' ) + (array) get_option( 'woocommerce_cheque_settings', array() ) );
$cbuild_zone = new WC_Shipping_Zone( 0 );
if ( array() === $cbuild_zone->get_shipping_methods() ) {
	$cbuild_zone->add_shipping_method( 'free_shipping' );
}

wp_mkdir_p( CBUILD_PATH . 'tmp' );
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a test fixture, writing where the tests read.
file_put_contents(
	CBUILD_PATH . 'tmp/e2e-cart.json',
	(string) wp_json_encode(
		array(
			'kit'   => $cbuild_kit->get_id(),
			'picks' => $cbuild_picks,
		)
	)
);

// The builder's shop (milestone 6): three widths of deck and the trucks for each, wheels that fit and one that wants a
// riser, two risers, two lengths of bolt, bearings and grip, under two brands, with one part sold out. A wider deck
// breaks the trucks picked for a narrower one, which is what the change panel is tested on. A page holds the Builder
// block. Each part is found by its SKU and each category by its slug, so a second run makes nothing new.
$cbuild_truck  = array( 'baseplate' => 'new_school', 'max_wheel' => '56.0000', 'axle_diameter' => '8.0000', 'set_size' => '2' );
$cbuild_shop   = array(
	'deck'     => array(
		array( 'Builder test deck 8.0', '60', array( 'deck_width' => '8.0000', 'deck_length' => '31.5000', 'hole_pattern' => 'new_school' ), 'northside', true ),
		array( 'Builder test deck 8.25', '65', array( 'deck_width' => '8.2500', 'deck_length' => '32.0000', 'hole_pattern' => 'new_school' ), 'southside', true ),
		array( 'Builder test deck 8.5', '70', array( 'deck_width' => '8.5000', 'deck_length' => '32.0000', 'hole_pattern' => 'new_school' ), 'northside', true ),
	),
	'trucks'   => array(
		array( 'Builder test trucks 139', '50', array( 'axle_width' => '8.0000', 'deck_min' => '7.8000', 'deck_max' => '8.2000' ) + $cbuild_truck, 'northside', true ),
		array( 'Builder test trucks 144', '52', array( 'axle_width' => '8.2500', 'deck_min' => '8.1250', 'deck_max' => '8.3750' ) + $cbuild_truck, 'southside', true ),
		array( 'Builder test trucks 149', '54', array( 'axle_width' => '8.5000', 'deck_min' => '8.3750', 'deck_max' => '8.6000' ) + $cbuild_truck, 'northside', true ),
	),
	'wheels'   => array(
		array( 'Builder test wheels 52', '30', array( 'wheel_diameter' => '52.0000', 'set_size' => '4' ), 'southside', true ),
		array( 'Builder test wheels 54', '32', array( 'wheel_diameter' => '54.0000', 'set_size' => '4' ), 'northside', true ),
		array( 'Builder test wheels 56', '33', array( 'wheel_diameter' => '56.0000', 'set_size' => '4' ), 'northside', false ),
		array( 'Builder test wheels 58', '34', array( 'wheel_diameter' => '58.0000', 'set_size' => '4' ), 'southside', true ),
	),
	'risers'   => array(
		array( 'Builder test risers 1/8', '5', array( 'riser_thickness' => '0.1250', 'set_size' => '2' ), '', true ),
		array( 'Builder test risers 1/4', '6', array( 'riser_thickness' => '0.2500', 'set_size' => '2' ), '', true ),
	),
	'bearings' => array(
		array( 'Builder test bearings', '15', array( 'bearing_bore' => '8.0000', 'set_size' => '8' ), '', true ),
	),
	'hardware' => array(
		array( 'Builder test bolts 1', '4', array( 'bolt_length' => '1.0000', 'set_size' => '8' ), '', true ),
		array( 'Builder test bolts 1.25', '5', array( 'bolt_length' => '1.2500', 'set_size' => '8' ), '', true ),
	),
	'grip'     => array(
		array( 'Builder test grip', '8', array( 'grip_width' => '9.0000', 'grip_length' => '33.0000' ), '', true ),
	),
);
$cbuild_labels = array(
	'deck'     => array( 'Deck', true, 1 ),
	'trucks'   => array( 'Trucks', true, 2 ),
	'wheels'   => array( 'Wheels', true, 4 ),
	'risers'   => array( 'Risers', false, 2 ),
	'bearings' => array( 'Bearings', true, 8 ),
	'hardware' => array( 'Hardware', true, 8 ),
	'grip'     => array( 'Grip tape', true, 1 ),
);
$cbuild_parts = array();
$cbuild_slots = array();
foreach ( $cbuild_shop as $cbuild_key => $cbuild_items ) {
	$cbuild_term = get_term_by( 'slug', 'builder-test-' . $cbuild_key, 'product_cat' );
	$cbuild_term = $cbuild_term instanceof WP_Term ? $cbuild_term->term_id : (int) wp_insert_term( 'Builder test ' . $cbuild_key, 'product_cat', array( 'slug' => 'builder-test-' . $cbuild_key ) )['term_id'];
	foreach ( $cbuild_items as $cbuild_part ) {
		$cbuild_sku  = sanitize_title( $cbuild_part[0] );
		$cbuild_item = new WC_Product_Simple( wc_get_product_id_by_sku( $cbuild_sku ) );
		$cbuild_item->set_name( $cbuild_part[0] );
		$cbuild_item->set_slug( $cbuild_sku );
		$cbuild_item->set_sku( $cbuild_sku );
		$cbuild_item->set_regular_price( $cbuild_part[1] );
		$cbuild_item->set_status( 'publish' );
		$cbuild_item->set_stock_status( $cbuild_part[4] ? 'instock' : 'outofstock' );
		$cbuild_item->set_category_ids( array( $cbuild_term ) );
		$cbuild_item->update_meta_data( '_cbuild_specs', $cbuild_part[2] );
		$cbuild_item->save();
		if ( '' !== $cbuild_part[3] && taxonomy_exists( 'product_brand' ) ) {
			$cbuild_brand = get_term_by( 'slug', $cbuild_part[3], 'product_brand' );
			$cbuild_brand = $cbuild_brand instanceof WP_Term ? $cbuild_brand->term_id : (int) wp_insert_term( ucfirst( $cbuild_part[3] ), 'product_brand', array( 'slug' => $cbuild_part[3] ) )['term_id'];
			wp_set_object_terms( $cbuild_item->get_id(), array( $cbuild_brand ), 'product_brand' );
		}
		CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\SpecIndexer::class )->index( $cbuild_item->get_id() );
		$cbuild_parts[ $cbuild_sku ] = $cbuild_item->get_id();
	}
	$cbuild_slots[] = array(
		'key'      => $cbuild_key,
		'label'    => $cbuild_labels[ $cbuild_key ][0],
		'required' => $cbuild_labels[ $cbuild_key ][1],
		'units'    => $cbuild_labels[ $cbuild_key ][2],
		'sources'  => array( 'categories' => array( $cbuild_term ) ),
	);
}
$cbuild_post = get_page_by_path( 'builder-test-kit', OBJECT, 'product' );
$cbuild_kit  = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_kit->set_name( 'Builder test kit' );
$cbuild_kit->set_slug( 'builder-test-kit' );
$cbuild_kit->set_status( 'publish' );
$cbuild_kit->setDocument(
	'_cbuild_slots',
	array(
		'schema_version' => 1,
		'slots'          => $cbuild_slots,
	)
);
$cbuild_kit->setDocument( '_cbuild_rules', CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings::DEFAULTS );
// Two presets: Street, all in stock, and Cruiser, whose wheels are the part sold out.
$cbuild_kit->setDocument(
	'_cbuild_presets',
	array(
		'schema_version' => 1,
		'presets'        => array(
			array(
				'name'        => 'Street',
				'description' => 'For ledges and stairs.',
				'picks'       => array(
					'deck'     => $cbuild_parts['builder-test-deck-8-0'],
					'trucks'   => $cbuild_parts['builder-test-trucks-139'],
					'wheels'   => $cbuild_parts['builder-test-wheels-52'],
					'bearings' => $cbuild_parts['builder-test-bearings'],
					'hardware' => $cbuild_parts['builder-test-bolts-1'],
					'grip'     => $cbuild_parts['builder-test-grip'],
				),
			),
			array(
				'name'        => 'Cruiser',
				'description' => 'For getting about town.',
				'picks'       => array(
					'deck'     => $cbuild_parts['builder-test-deck-8-5'],
					'trucks'   => $cbuild_parts['builder-test-trucks-149'],
					'wheels'   => $cbuild_parts['builder-test-wheels-56'],
					'bearings' => $cbuild_parts['builder-test-bearings'],
					'hardware' => $cbuild_parts['builder-test-bolts-1'],
					'grip'     => $cbuild_parts['builder-test-grip'],
				),
			),
		),
	)
);
$cbuild_kit->setDocument(
	'_cbuild_assembly',
	array(
		'schema_version' => 1,
		'default_on'     => true,
		'fee'            => '15',
		'lead_time'      => 'Two business days',
		'returns_notice' => 'An assembled board comes back as parts.',
	)
);
$cbuild_kit->save();
// Layer images for the picture (milestone 7): a block of one colour on a transparent ground, at two pixels to the
// millimetre of the size the picture draws each part, in the Media Library. The 8.25 in deck and the trucks 144 have
// none, so the labelled photo is tested beside the board. Each is made once; its renditions are made as the part's
// index job makes them.
require_once ABSPATH . 'wp-admin/includes/image.php';
$cbuild_layers = array(
	'builder-test-deck-8-0'      => array( 407, 1601, 0x1f6feb ),
	'builder-test-deck-8-5'      => array( 432, 1626, 0x8250df ),
	'builder-test-trucks-139'    => array( 407, 153, 0x57606a ),
	'builder-test-trucks-149'    => array( 432, 153, 0x24292f ),
	'builder-test-wheels-54'     => array( 65, 108, 0xd1242f ),
);
foreach ( $cbuild_layers as $cbuild_sku => $cbuild_layer ) {
	$cbuild_item = wc_get_product( $cbuild_parts[ $cbuild_sku ] );
	$cbuild_had  = (int) $cbuild_item->get_meta( '_cbuild_layer', true );
	// The file too: wp-env started on another WordPress keeps the database but not the uploads, so an attachment can
	// outlive its file.
	if ( $cbuild_had < 1 || ! wp_attachment_is_image( $cbuild_had ) || ! file_exists( (string) get_attached_file( $cbuild_had ) ) ) {
		$cbuild_image = imagecreatetruecolor( $cbuild_layer[0], $cbuild_layer[1] );
		imagealphablending( $cbuild_image, false );
		imagesavealpha( $cbuild_image, true );
		imagefill( $cbuild_image, 0, 0, imagecolorallocatealpha( $cbuild_image, 0, 0, 0, 127 ) );
		$cbuild_inset = max( 1, intdiv( min( $cbuild_layer[0], $cbuild_layer[1] ), 10 ) );
		imagefilledrectangle( $cbuild_image, $cbuild_inset, $cbuild_inset, $cbuild_layer[0] - 1 - $cbuild_inset, $cbuild_layer[1] - 1 - $cbuild_inset, imagecolorallocate( $cbuild_image, ( $cbuild_layer[2] >> 16 ) & 0xff, ( $cbuild_layer[2] >> 8 ) & 0xff, $cbuild_layer[2] & 0xff ) );
		$cbuild_upload = wp_upload_bits( $cbuild_sku . '-layer.png', null, '' );
		imagepng( $cbuild_image, $cbuild_upload['file'] );
		$cbuild_had = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $cbuild_sku . ' layer',
				'post_status'    => 'inherit',
			),
			$cbuild_upload['file']
		);
		wp_update_attachment_metadata( $cbuild_had, wp_generate_attachment_metadata( $cbuild_had, $cbuild_upload['file'] ) );
		$cbuild_item->update_meta_data( '_cbuild_layer', (string) $cbuild_had );
		$cbuild_item->save();
	}
	CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\PartRenditions::class )->make( $cbuild_item->get_id() );
}

$cbuild_page = get_page_by_path( 'builder-test-page' );
$cbuild_page = (int) wp_insert_post(
	array(
		'ID'           => $cbuild_page instanceof WP_Post ? $cbuild_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'builder-test-page',
		'post_title'   => 'Build a complete',
		'post_content' => '<!-- wp:paragraph --><p>Pick a deck, and every later step offers only what fits it.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:creative-skateboard-builder/builder {"kit":' . $cbuild_kit->get_id() . '} /-->',
	)
);
// The builder with a Mini-Cart that opens its drawer on an add, as the demo's header has it (Cowork's recheck, 10).
$cbuild_drawer = get_page_by_path( 'builder-drawer-test-page' );
$cbuild_drawer = (int) wp_insert_post(
	array(
		'ID'           => $cbuild_drawer instanceof WP_Post ? $cbuild_drawer->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'builder-drawer-test-page',
		'post_title'   => 'Build a complete, with a drawer',
		'post_content' => '<!-- wp:woocommerce/mini-cart {"addToCartBehaviour":"open_drawer"} /-->' . "\n" . '<!-- wp:creative-skateboard-builder/builder {"kit":' . $cbuild_kit->get_id() . '} /-->',
	)
);
// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- a test fixture, writing where the tests read.
file_put_contents(
	CBUILD_PATH . 'tmp/e2e-builder.json',
	(string) wp_json_encode(
		array(
			'kit'    => $cbuild_kit->get_id(),
			'page'   => (string) get_permalink( $cbuild_page ),
			'drawer' => (string) get_permalink( $cbuild_drawer ),
			'parts'  => $cbuild_parts,
		)
	)
);

// wp-env's uploads folder does not hold the placeholder image WooCommerce's installer recorded, so every product
// without a photo asked for a file that is not there. With no attachment set, WooCommerce serves the one it ships.
$cbuild_placeholder = (int) get_option( 'woocommerce_placeholder_image', 0 );
$cbuild_file        = $cbuild_placeholder > 0 ? get_attached_file( $cbuild_placeholder ) : false;
if ( $cbuild_placeholder > 0 && ( ! is_string( $cbuild_file ) || ! file_exists( $cbuild_file ) ) ) {
	update_option( 'woocommerce_placeholder_image', 0 );
}
