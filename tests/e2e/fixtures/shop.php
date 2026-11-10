<?php
/**
 * The browser site's shop, for the smoke test: the shop open to visitors and one product to visit. Run by
 * tests/e2e/support/global-setup.ts with `wp eval-file`; safe to run again. Milestone 6 adds a kit and its parts.
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

// wp-env's uploads folder does not hold the placeholder image WooCommerce's installer recorded, so every product
// without a photo asked for a file that is not there. With no attachment set, WooCommerce serves the one it ships.
$cbuild_placeholder = (int) get_option( 'woocommerce_placeholder_image', 0 );
$cbuild_file        = $cbuild_placeholder > 0 ? get_attached_file( $cbuild_placeholder ) : false;
if ( $cbuild_placeholder > 0 && ( ! is_string( $cbuild_file ) || ! file_exists( $cbuild_file ) ) ) {
	update_option( 'woocommerce_placeholder_image', 0 );
}
