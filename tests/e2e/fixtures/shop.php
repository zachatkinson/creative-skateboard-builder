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
// The 3D kit with its Trick buttons off (1.4.0): the same parts, View in 3D on, and no trick offered.
$cbuild_post      = get_page_by_path( 'three-quiet-kit', OBJECT, 'product' );
$cbuild_quiet_kit = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_quiet_kit->set_name( '3D test kit without tricks' );
$cbuild_quiet_kit->set_slug( 'three-quiet-kit' );
$cbuild_quiet_kit->set_status( 'publish' );
foreach ( $cbuild_kit->documents() as $cbuild_key => $cbuild_document ) {
	if ( is_array( $cbuild_document ) ) {
		$cbuild_quiet_kit->setDocument( $cbuild_key, $cbuild_document );
	}
}
$cbuild_quiet_kit->setDocument(
	'_cbuild_settings',
	array(
		'schema_version' => 1,
		'view_3d'        => true,
		'tricks'         => false,
	)
);
$cbuild_quiet_kit->save();
$cbuild_quiet_page = get_page_by_path( 'three-quiet-page' );
$cbuild_quiet_page = (int) wp_insert_post(
	array(
		'ID'           => $cbuild_quiet_page instanceof WP_Post ? $cbuild_quiet_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'three-quiet-page',
		'post_title'   => 'Build a complete without tricks',
		'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $cbuild_quiet_kit->get_id() . '} /-->',
	)
);

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
// Reviews (#137): the 139 trucks, Street's, rated 5 and 4, and the 54 mm wheels 3, so a step shows a ready build's pick
// and a rating, and a part with neither. The shop shows ratings; each part's reviews are made afresh.
update_option( 'woocommerce_enable_reviews', 'yes' );
update_option( 'woocommerce_enable_review_rating', 'yes' );
foreach (
	array(
		'builder-test-trucks-139' => array( 5, 4 ),
		'builder-test-wheels-54'  => array( 3 ),
	) as $cbuild_sku => $cbuild_ratings
) {
	$cbuild_id = $cbuild_parts[ $cbuild_sku ];
	foreach ( get_comments( array( 'post_id' => $cbuild_id, 'fields' => 'ids', 'type' => 'review', 'status' => 'all' ) ) as $cbuild_had ) {
		wp_delete_comment( (int) $cbuild_had, true );
	}
	foreach ( $cbuild_ratings as $cbuild_at => $cbuild_rating ) {
		wp_insert_comment(
			array(
				'comment_post_ID'      => $cbuild_id,
				'comment_type'         => 'review',
				'comment_approved'     => 1,
				'comment_author'       => 'Shopper ' . ( $cbuild_at + 1 ),
				'comment_author_email' => 'shopper+' . ( $cbuild_at + 1 ) . '@example.com',
				'comment_content'      => 'Rides well.',
				'comment_meta'         => array( 'rating' => $cbuild_rating ),
			)
		);
	}
	// The rating's meta is added after the comment is counted (wp-includes/comment.php:2206, :2220).
	WC_Comments::clear_transients( $cbuild_id );
}
// Layer images for the picture (milestone 7): clean test art drawn by tools/layer-art.php to the shooting template,
// at two pixels to the millimetre, in the Media Library. The 8.25 in deck and the trucks 144 have none, so the
// labelled photo is tested beside the board. An image is made again when its file is gone (wp-env started on another
// WordPress keeps the database but not the uploads) or the art has changed; its renditions are made as the part's
// index job makes them.
require_once ABSPATH . 'wp-admin/includes/image.php';
$cbuild_layers = array(
	'builder-test-deck-8-0'   => 'deck-8.0-31.5.png',
	'builder-test-deck-8-5'   => 'deck-8.5-32.png',
	'builder-test-trucks-139' => 'trucks-8.0.png',
	'builder-test-trucks-149' => 'trucks-8.5.png',
	'builder-test-wheels-52'  => 'wheels-52.png',
	'builder-test-wheels-54'  => 'wheels-54.png',
	'builder-test-wheels-56'  => 'wheels-56.png',
	'builder-test-wheels-58'  => 'wheels-58.png',
	// The board's top (#46): the grip's swatch, and the longer bolts' heads. The 1 in bolts have none, so the hardware's
	// labelled photo is tested as well; tools/layer-art.php draws a blue grip and black heads too, for a demo's parts.
	'builder-test-grip'       => 'grip-black.png',
	'builder-test-bolts-1-25' => 'bolts-1.25.png',
);
foreach ( $cbuild_layers as $cbuild_sku => $cbuild_art ) {
	$cbuild_item = wc_get_product( $cbuild_parts[ $cbuild_sku ] );
	$cbuild_had  = (int) $cbuild_item->get_meta( '_cbuild_layer', true );
	$cbuild_from = __DIR__ . '/layers/' . $cbuild_art;
	$cbuild_hash = (string) md5_file( $cbuild_from );
	if ( $cbuild_had < 1 || ! wp_attachment_is_image( $cbuild_had ) || ! file_exists( (string) get_attached_file( $cbuild_had ) ) || get_post_meta( $cbuild_had, '_cbuild_e2e_art', true ) !== $cbuild_hash ) {
		if ( $cbuild_had > 0 ) {
			// Its renditions go with it (D-201).
			wp_delete_attachment( $cbuild_had, true );
		}
		$cbuild_upload = wp_upload_bits( $cbuild_sku . '-layer.png', null, (string) file_get_contents( $cbuild_from ) );
		$cbuild_had    = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/png',
				'post_title'     => $cbuild_sku . ' layer',
				'post_status'    => 'inherit',
			),
			$cbuild_upload['file']
		);
		wp_update_attachment_metadata( $cbuild_had, wp_generate_attachment_metadata( $cbuild_had, $cbuild_upload['file'] ) );
		update_post_meta( $cbuild_had, '_cbuild_e2e_art', $cbuild_hash );
		$cbuild_item->update_meta_data( '_cbuild_layer', (string) $cbuild_had );
		$cbuild_item->save();
	}
	CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\PartRenditions::class )->make( $cbuild_item->get_id() );
}

// A photo for the 8.25 in deck, which has no layer image: the deck a demo's shop has most, a photo and no layer, so its
// photo takes the board's place on the stage (#82). The 8 in deck's art serves as the photo; made again as a layer is.
$cbuild_item  = wc_get_product( $cbuild_parts['builder-test-deck-8-25'] );
$cbuild_had   = $cbuild_item->get_image_id();
$cbuild_from  = __DIR__ . '/layers/deck-8.0-31.5.png';
$cbuild_hash  = (string) md5_file( $cbuild_from );
if ( $cbuild_had < 1 || ! file_exists( (string) get_attached_file( $cbuild_had ) ) || get_post_meta( $cbuild_had, '_cbuild_e2e_art', true ) !== $cbuild_hash ) {
	if ( $cbuild_had > 0 ) {
		wp_delete_attachment( $cbuild_had, true );
	}
	$cbuild_upload = wp_upload_bits( 'builder-test-deck-8-25-photo.png', null, (string) file_get_contents( $cbuild_from ) );
	$cbuild_had    = (int) wp_insert_attachment(
		array(
			'post_mime_type' => 'image/png',
			'post_title'     => 'builder-test-deck-8-25 photo',
			'post_status'    => 'inherit',
		),
		$cbuild_upload['file']
	);
	wp_update_attachment_metadata( $cbuild_had, wp_generate_attachment_metadata( $cbuild_had, $cbuild_upload['file'] ) );
	update_post_meta( $cbuild_had, '_cbuild_e2e_art', $cbuild_hash );
	$cbuild_item->set_image_id( $cbuild_had );
	$cbuild_item->save();
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
// A kit whose deck step is as long as a real shop's (milestone 10; spec: One card per look, Search): 46 decks at 8.25,
// over four brands, with a search past 40, chips past 12, pages of 30 and three sold out; and six at 8. One deck is
// named Rascals for the search, and one 8″ deck Ponies, for a search that finds nothing at 8.25″. Its other slots are the cart test kit's.
$cbuild_shelf_cat = get_term_by( 'slug', 'shelf-test-deck', 'product_cat' );
$cbuild_shelf_cat = $cbuild_shelf_cat instanceof WP_Term ? $cbuild_shelf_cat->term_id : (int) wp_insert_term( 'Shelf test deck', 'product_cat', array( 'slug' => 'shelf-test-deck' ) )['term_id'];
$cbuild_brands    = array();
foreach ( array( 'Alder', 'Birch', 'Cedar', 'Dogwood' ) as $cbuild_brand ) {
	$cbuild_found                   = get_term_by( 'slug', 'shelf-' . strtolower( $cbuild_brand ), 'product_brand' );
	$cbuild_brands[ $cbuild_brand ] = $cbuild_found instanceof WP_Term ? $cbuild_found->term_id : (int) wp_insert_term( $cbuild_brand, 'product_brand', array( 'slug' => 'shelf-' . strtolower( $cbuild_brand ) ) )['term_id'];
}
$cbuild_shelf = array();
for ( $cbuild_at = 1; $cbuild_at <= 55; ++$cbuild_at ) {
	$cbuild_width = $cbuild_at > 49 ? '8.0000' : '8.2500';
	$cbuild_name  = array( 7 => 'Rascals', 52 => 'Ponies' )[ $cbuild_at ] ?? sprintf( 'Shelf deck %02d', $cbuild_at );
	$cbuild_brand = array_keys( $cbuild_brands )[ $cbuild_at % 4 ];
	$cbuild_item  = new WC_Product_Simple( wc_get_product_id_by_sku( 'shelf-test-deck-' . $cbuild_at ) );
	$cbuild_item->set_name( $cbuild_name );
	$cbuild_item->set_slug( 'shelf-test-deck-' . $cbuild_at );
	$cbuild_item->set_sku( 'shelf-test-deck-' . $cbuild_at );
	$cbuild_item->set_regular_price( (string) ( 50 + $cbuild_at ) );
	$cbuild_item->set_status( 'publish' );
	$cbuild_item->set_stock_status( in_array( $cbuild_at, array( 47, 48, 49 ), true ) ? 'outofstock' : 'instock' );
	$cbuild_item->set_category_ids( array( $cbuild_shelf_cat ) );
	$cbuild_item->update_meta_data(
		'_cbuild_specs',
		array(
			'deck_width'   => $cbuild_width,
			'deck_length'  => '32.0000',
			'hole_pattern' => 'new_school',
		)
	);
	$cbuild_item->save();
	wp_set_object_terms( $cbuild_item->get_id(), array( $cbuild_brands[ $cbuild_brand ] ), 'product_brand' );
	CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\SpecIndexer::class )->index( $cbuild_item->get_id() );
	$cbuild_shelf[] = $cbuild_item->get_id();
}
$cbuild_cart_kit                     = get_page_by_path( 'cart-test-kit', OBJECT, 'product' );
$cbuild_slots                        = $cbuild_cart_kit instanceof WP_Post ? ( new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_cart_kit->ID ) )->document( '_cbuild_slots' ) : null;
$cbuild_slots                        = is_array( $cbuild_slots ) ? $cbuild_slots : array(
	'schema_version' => 1,
	'slots'          => array(),
);
$cbuild_slots['slots'][0]['sources'] = array( 'categories' => array( $cbuild_shelf_cat ) );
$cbuild_post                         = get_page_by_path( 'shelf-test-kit', OBJECT, 'product' );
$cbuild_shelf_kit                    = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_shelf_kit->set_name( 'Shelf test kit' );
$cbuild_shelf_kit->set_slug( 'shelf-test-kit' );
$cbuild_shelf_kit->set_status( 'publish' );
$cbuild_shelf_kit->setDocument( '_cbuild_slots', $cbuild_slots );
$cbuild_shelf_kit->setDocument( '_cbuild_rules', CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings::DEFAULTS );
$cbuild_shelf_kit->setDocument(
	'_cbuild_presets',
	array(
		'schema_version' => 1,
		'presets'        => array(),
	)
);
// A kit reads only with every document, its assembly too.
$cbuild_shelf_kit->setDocument(
	'_cbuild_assembly',
	array(
		'schema_version' => 1,
		'default_on'     => false,
		'fee'            => '15',
		'lead_time'      => '',
		'returns_notice' => '',
	)
);
$cbuild_shelf_kit->save();
$cbuild_shelf_page = get_page_by_path( 'shelf-test-page' );
$cbuild_shelf_page = (int) wp_insert_post(
	array(
		'ID'           => $cbuild_shelf_page instanceof WP_Post ? $cbuild_shelf_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'shelf-test-page',
		'post_title'   => 'A long step',
		'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $cbuild_shelf_kit->get_id() . '} /-->',
	)
);

// A kit whose decks each come in seven widths, as a real shop's do (Cowork's 1.2.0 review, F31, #148): six looks, each
// a variable product whose variations are its widths and share one photo, so each is one card with six other widths to
// say once a width is chosen. Their names wrap as the demo's do. Its other slots are the cart test kit's.
$cbuild_widths_cat = get_term_by( 'slug', 'widths-test-deck', 'product_cat' );
$cbuild_widths_cat = $cbuild_widths_cat instanceof WP_Term ? $cbuild_widths_cat->term_id : (int) wp_insert_term( 'Widths test deck', 'product_cat', array( 'slug' => 'widths-test-deck' ) )['term_id'];
$cbuild_found      = get_term_by( 'slug', 'widths-elmwood', 'product_brand' );
$cbuild_elm        = $cbuild_found instanceof WP_Term ? $cbuild_found->term_id : (int) wp_insert_term( 'Elmwood', 'product_brand', array( 'slug' => 'widths-elmwood' ) )['term_id'];
$cbuild_photo      = wc_get_product( $cbuild_parts['builder-test-deck-8-25'] )->get_image_id();
// The 8.5 in deck's layer image, so each card draws its deck upright, cut out, as the demo's decks are drawn.
$cbuild_layer      = (string) wc_get_product( $cbuild_parts['builder-test-deck-8-5'] )->get_meta( '_cbuild_layer', true );
$cbuild_sizes      = array( '7.75', '8', '8.125', '8.25', '8.38', '8.5', '8.75' );
$cbuild_widths     = array();
foreach ( array( 'Beaver Wizard', 'Dragon Wizard', 'Owl Wizard', 'Toad Wizard', 'Heron Wizard', 'Lynx Wizard' ) as $cbuild_at => $cbuild_graphic ) {
	$cbuild_sku  = 'widths-test-deck-' . ( $cbuild_at + 1 );
	$cbuild_deck = new WC_Product_Variable( wc_get_product_id_by_sku( $cbuild_sku ) );
	$cbuild_size = new WC_Product_Attribute();
	$cbuild_size->set_name( 'Deck width' );
	$cbuild_size->set_options( $cbuild_sizes );
	$cbuild_size->set_visible( true );
	$cbuild_size->set_variation( true );
	$cbuild_deck->set_name( 'Elmwood Skate Deck, ' . $cbuild_graphic );
	$cbuild_deck->set_slug( $cbuild_sku );
	$cbuild_deck->set_sku( $cbuild_sku );
	$cbuild_deck->set_attributes( array( $cbuild_size ) );
	$cbuild_deck->set_status( 'publish' );
	$cbuild_deck->set_category_ids( array( $cbuild_widths_cat ) );
	$cbuild_deck->set_image_id( $cbuild_photo );
	$cbuild_deck->update_meta_data( '_cbuild_layer', $cbuild_layer );
	$cbuild_deck->save();
	wp_set_object_terms( $cbuild_deck->get_id(), array( $cbuild_elm ), 'product_brand' );
	$cbuild_indexer = CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\SpecIndexer::class );
	foreach ( $cbuild_sizes as $cbuild_width ) {
		$cbuild_variation_sku = $cbuild_sku . '-' . str_replace( '.', '-', $cbuild_width );
		$cbuild_variation     = new WC_Product_Variation( wc_get_product_id_by_sku( $cbuild_variation_sku ) );
		$cbuild_variation->set_parent_id( $cbuild_deck->get_id() );
		$cbuild_variation->set_attributes( array( 'deck-width' => $cbuild_width ) );
		$cbuild_variation->set_sku( $cbuild_variation_sku );
		$cbuild_variation->set_regular_price( '99.99' );
		// Counted, as a shop that keeps stock does, so the card says how many are left on a line of its own.
		$cbuild_variation->set_manage_stock( true );
		$cbuild_variation->set_stock_quantity( 12 );
		$cbuild_variation->update_meta_data(
			'_cbuild_specs',
			array(
				'deck_width'   => number_format( (float) $cbuild_width, 4, '.', '' ),
				'deck_length'  => '32.0000',
				'hole_pattern' => 'new_school',
			)
		);
		$cbuild_variation->save();
		$cbuild_indexer->index( $cbuild_variation->get_id() );
		CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\PartRenditions::class )->make( $cbuild_variation->get_id() );
	}
	WC_Product_Variable::sync( $cbuild_deck->get_id() );
	$cbuild_indexer->index( $cbuild_deck->get_id() );
	CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Service\PartRenditions::class )->make( $cbuild_deck->get_id() );
	$cbuild_widths[] = $cbuild_deck->get_id();
}
$cbuild_slots['slots'][0]['sources'] = array( 'categories' => array( $cbuild_widths_cat ) );
$cbuild_post                         = get_page_by_path( 'widths-test-kit', OBJECT, 'product' );
$cbuild_widths_kit                   = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_widths_kit->set_name( 'Widths test kit' );
$cbuild_widths_kit->set_slug( 'widths-test-kit' );
$cbuild_widths_kit->set_status( 'publish' );
$cbuild_widths_kit->setDocument( '_cbuild_slots', $cbuild_slots );
$cbuild_widths_kit->setDocument( '_cbuild_rules', CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings::DEFAULTS );
$cbuild_widths_kit->setDocument(
	'_cbuild_presets',
	array(
		'schema_version' => 1,
		'presets'        => array(),
	)
);
$cbuild_widths_kit->setDocument(
	'_cbuild_assembly',
	array(
		'schema_version' => 1,
		'default_on'     => false,
		'fee'            => '15',
		'lead_time'      => '',
		'returns_notice' => '',
	)
);
$cbuild_widths_kit->save();
$cbuild_widths_page = get_page_by_path( 'widths-test-page' );
$cbuild_widths_page = (int) wp_insert_post(
	array(
		'ID'           => $cbuild_widths_page instanceof WP_Post ? $cbuild_widths_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'widths-test-page',
		'post_title'   => 'Decks in seven widths',
		'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $cbuild_widths_kit->get_id() . '} /-->',
	)
);

// The 3D view's kit (milestone 14): the builder kit's parts and settings, with View in 3D on, so the shared kit and
// every other test's page stay as they were. Remade from the builder kit's documents on each run.
$cbuild_post      = get_page_by_path( 'three-test-kit', OBJECT, 'product' );
$cbuild_three_kit = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_three_kit->set_name( '3D test kit' );
$cbuild_three_kit->set_slug( 'three-test-kit' );
$cbuild_three_kit->set_status( 'publish' );
foreach ( $cbuild_kit->documents() as $cbuild_key => $cbuild_document ) {
	if ( is_array( $cbuild_document ) ) {
		$cbuild_three_kit->setDocument( $cbuild_key, $cbuild_document );
	}
}
$cbuild_three_kit->setDocument(
	'_cbuild_settings',
	array(
		'schema_version' => 1,
		'view_3d'        => true,
	)
);
$cbuild_three_kit->save();
$cbuild_three_page = get_page_by_path( 'three-test-page' );
$cbuild_three_page = (int) wp_insert_post(
	array(
		'ID'           => $cbuild_three_page instanceof WP_Post ? $cbuild_three_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'three-test-page',
		// The builder page's own words, so the two pages differ by the kit's switch alone.
		'post_title'   => 'Build a complete',
		'post_content' => '<!-- wp:paragraph --><p>Pick a deck, and every later step offers only what fits it.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:creative-skateboard-builder/builder {"kit":' . $cbuild_three_kit->get_id() . '} /-->',
	)
);

// The 3D kit with its Trick buttons off (1.4.0): the same parts, View in 3D on, and no trick offered.
$cbuild_post      = get_page_by_path( 'three-quiet-kit', OBJECT, 'product' );
$cbuild_quiet_kit = new CreativeBranding\SkateboardBuilder\Adapter\KitProduct( $cbuild_post instanceof WP_Post ? $cbuild_post->ID : 0 );
$cbuild_quiet_kit->set_name( '3D test kit without tricks' );
$cbuild_quiet_kit->set_slug( 'three-quiet-kit' );
$cbuild_quiet_kit->set_status( 'publish' );
foreach ( $cbuild_kit->documents() as $cbuild_key => $cbuild_document ) {
	if ( is_array( $cbuild_document ) ) {
		$cbuild_quiet_kit->setDocument( $cbuild_key, $cbuild_document );
	}
}
$cbuild_quiet_kit->setDocument(
	'_cbuild_settings',
	array(
		'schema_version' => 1,
		'view_3d'        => true,
		'tricks'         => false,
	)
);
$cbuild_quiet_kit->save();
$cbuild_quiet_page = get_page_by_path( 'three-quiet-page' );
$cbuild_quiet_page = (int) wp_insert_post(
	array(
		'ID'           => $cbuild_quiet_page instanceof WP_Post ? $cbuild_quiet_page->ID : 0,
		'post_type'    => 'page',
		'post_status'  => 'publish',
		'post_name'    => 'three-quiet-page',
		'post_title'   => 'Build a complete without tricks',
		'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $cbuild_quiet_kit->get_id() . '} /-->',
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
			'shelf'  => array(
				'kit'   => $cbuild_shelf_kit->get_id(),
				'page'  => (string) get_permalink( $cbuild_shelf_page ),
				'decks' => $cbuild_shelf,
			),
			'widths' => array(
				'kit'   => $cbuild_widths_kit->get_id(),
				'page'  => (string) get_permalink( $cbuild_widths_page ),
				'decks' => $cbuild_widths,
			),
			'three'  => array(
				'kit'   => $cbuild_three_kit->get_id(),
				'page'  => (string) get_permalink( $cbuild_three_page ),
				'quiet' => (string) get_permalink( $cbuild_quiet_page ),
			),
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
