<?php
/**
 * The shop's data for tools/upgrade.sh. With WooCommerce alone, the same on every release: a few products with SKUs,
 * prices, stock and an attribute, and a paid order holding them. `saved` makes a customer's saved build with the old
 * release's own service, from 0.8.0 (D-232), and `layer` a grip with a layer image and no spec, from 0.7.0 (D-373).
 * The old release's own kits come from its own browser fixture, which
 * upgrade.sh runs. `read <order> [<user>] [<layer image>]` reads all of it back with this release after the update. Run with wp eval-file; never shipped (milestone 9: upgrades from every 0.x release).
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test tool outside the plugin, run by WP-CLI.

$cbuild_args = $args ?? array();
if ( 'read' === ( $cbuild_args[0] ?? '' ) ) {
	$order = wc_get_order( (int) ( $cbuild_args[1] ?? 0 ) );
	if ( ! $order instanceof WC_Order ) {
		echo "no order\n";
		return;
	}
	$lines = count( $order->get_items() );
	$found = 0;
	foreach ( $order->get_items() as $item ) {
		$found += $item instanceof WC_Order_Item_Product && $item->get_product() instanceof WC_Product ? 1 : 0;
	}
	ob_start();
	do_action( 'woocommerce_admin_order_data_after_order_details', $order );
	ob_end_clean();
	// The old release's kits, as its own fixture stored them: each read by this release, and each preset priced.
	$container = CreativeBranding\SkateboardBuilder\Plugin::container();
	$kits      = $container->get( CreativeBranding\SkateboardBuilder\Port\Kits::class );
	$states    = $container->get( CreativeBranding\SkateboardBuilder\Service\BuildState::class );
	$stored    = wc_get_products( array( 'type' => 'cbuild_kit', 'limit' => 50, 'return' => 'ids', 'status' => array( 'publish', 'private', 'draft' ) ) );
	$read      = 0;
	$presets   = 0;
	$priced    = 0;
	foreach ( $stored as $id ) {
		$kit = $kits->kit( (int) $id );
		if ( null === $kit ) {
			continue;
		}
		++$read;
		foreach ( $kit->presets as $preset ) {
			++$presets;
			$priced += null !== $states->of( (int) $id, $preset->picks ) ? 1 : 0;
		}
	}
	// The build 0.8.0 saved, listed by this release.
	$user  = (int) ( $cbuild_args[2] ?? 0 );
	$builds = $container->get( CreativeBranding\SkateboardBuilder\Service\SavedBuilds::class );
	$list   = $user > 0 ? $builds->of( $user ) : array();
	$saved  = count( $list );
	// Each reopened as My builds opens it (the spec reviewer's M10 S6): a build saved before 1.0.0 kept no prices, so
	// it shows no price changes and no total then, whatever else changed. One saved by 1.0.0 or later kept them
	// (D-254): it shows the total it kept, and no price change where the shop changed none.
	$clean = 0;
	$kept  = 0;
	foreach ( $list as $build ) {
		// A build where nothing changed does not reopen at all (SavedBuilds::reopened()), so one of its parts is sold
		// out first: then it must reopen, saying so, and with no prices it never kept.
		$picks = $build->picks;
		$part  = wc_get_product( (int) end( $picks ) );
		if ( $part instanceof WC_Product ) {
			$part->set_stock_quantity( 0 );
			$part->set_stock_status( 'outofstock' );
			$part->save();
		}
		$again  = $builds->reopened( $user, $build->uuid );
		// One that doesn't reopen at all is not clean (the spec reviewer's M10 m2).
		$clean += null !== $again && null === $again->then && array() === $again->prices ? 1 : 0;
		$kept  += null !== $again && null !== $again->then && array() === $again->prices ? 1 : 0;
	}
	// The grip the layer seed made, if it made one: its resized copies on disk, and the parts still missing copies (#129).
	$layer   = (int) ( $cbuild_args[3] ?? 0 );
	$copies  = $layer > 0 ? count( (array) glob( wp_get_upload_dir()['basedir'] . '/creative-skateboard-builder/layers/' . $layer . '-*' ) ) : 0;
	$missing = count( $container->get( CreativeBranding\SkateboardBuilder\Service\PartRenditions::class )->unrenderedParts() );
	echo "read: order {$order->get_id()}, {$lines} lines, {$found} products, total {$order->get_total()}; kits " . $read . ' of ' . count( $stored ) . ", presets {$priced} of {$presets} priced; saved builds {$saved}, {$clean} reopened with no prices then, {$kept} with the prices kept; layer copies {$copies}, parts missing copies {$missing}\n";
	return;
}

// How many kits the active release reads, of those stored: run on the old release and again on this one.
if ( 'kits' === ( $cbuild_args[0] ?? '' ) ) {
	$port = 'CreativeBranding\\SkateboardBuilder\\Port\\Kits';
	if ( ! interface_exists( $port ) ) {
		echo "kits:0\n";
		return;
	}
	$kits = CreativeBranding\SkateboardBuilder\Plugin::container()->get( $port );
	// 0.3.x's Kits was a stub with no kit(): no kit could be read before 0.4.0.
	if ( ! method_exists( $kits, 'kit' ) ) {
		echo "kits:0\n";
		return;
	}
	$read = 0;
	foreach ( wc_get_products( array( 'type' => 'cbuild_kit', 'limit' => 50, 'return' => 'ids', 'status' => array( 'publish', 'private', 'draft' ) ) ) as $id ) {
		$read += null === $kits->kit( (int) $id ) ? 0 : 1;
	}
	echo "kits:{$read}\n";
	return;
}

// After the run: the seed's products, orders and customers deleted. The kits the old fixture made are the browser
// suite's own, which its setup writes afresh.
if ( 'clean' === ( $cbuild_args[0] ?? '' ) ) {
	foreach ( wc_get_products( array( 'sku' => 'UPG-', 'limit' => -1, 'return' => 'ids' ) ) as $id ) {
		wp_delete_post( (int) $id, true );
	}
	foreach ( wc_get_orders( array( 'billing_email' => 'shopper+1@example.com', 'limit' => -1 ) ) as $order ) {
		$order->delete( true );
	}
	foreach ( get_posts( array( 'post_type' => 'attachment', 'title' => 'cbuild-upgrade-grip', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ) ) as $id ) {
		wp_delete_attachment( (int) $id, true );
	}
	require_once ABSPATH . 'wp-admin/includes/user.php';
	foreach ( get_users( array( 'search' => 'upgrade-shopper-*', 'search_columns' => array( 'user_login' ), 'fields' => 'ID' ) ) as $id ) {
		wp_delete_user( (int) $id );
	}
	echo "clean\n";
	return;
}

// 0.7.0 on (#129, D-373): a grip with a layer image and no spec, in the category of a grip a kit offers, as the demo's
// grips were. Up to 1.1.0 such a grip got no resized copy, and nothing counted it missing; the update to 1.2.0 queues
// its job. Releases before 0.7.0 had no layer images, and make none.
if ( 'layer' === ( $cbuild_args[0] ?? '' ) ) {
	if ( ! class_exists( 'CreativeBranding\\SkateboardBuilder\\Service\\PartRenditions' ) ) {
		echo "layer:none\n";
		return;
	}
	$kits       = CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Port\Kits::class );
	$categories = array();
	foreach ( array_keys( $kits->all() ) as $kit ) {
		foreach ( $kits->candidates( (int) $kit, 'grip' ) as $part ) {
			$product    = wc_get_product( $part->id );
			$owner      = $product instanceof WC_Product && $product->get_parent_id() > 0 ? $product->get_parent_id() : $part->id;
			$categories = wp_get_post_terms( $owner, 'product_cat', array( 'fields' => 'ids' ) );
			break 2;
		}
	}
	if ( ! is_array( $categories ) || array() === $categories ) {
		echo "no layer: no kit offers a grip\n";
		return;
	}
	// A sheet of grip, 60 by 220 pixels, drawn with GD and put in the Media Library.
	$image = imagecreatetruecolor( 60, 220 );
	imagefill( $image, 0, 0, (int) imagecolorallocate( $image, 31, 31, 34 ) );
	ob_start();
	imagepng( $image );
	$upload = wp_upload_bits( 'cbuild-upgrade-grip.png', null, (string) ob_get_clean() );
	if ( ! empty( $upload['error'] ) ) {
		echo "no layer: {$upload['error']}\n";
		return;
	}
	require_once ABSPATH . 'wp-admin/includes/image.php';
	$attachment = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => 'cbuild-upgrade-grip', 'post_status' => 'inherit' ), $upload['file'] );
	wp_update_attachment_metadata( $attachment, wp_generate_attachment_metadata( $attachment, $upload['file'] ) );
	$grip = new WC_Product_Simple();
	$grip->set_name( 'Upgrade grip sheet' );
	$grip->set_sku( 'UPG-grip-' . wp_generate_password( 6, false ) );
	$grip->set_regular_price( '9' );
	$grip->set_status( 'publish' );
	$grip->set_category_ids( array_map( 'intval', $categories ) );
	$grip->update_meta_data( '_cbuild_layer', (string) $attachment );
	$grip->save();
	echo "layer:{$attachment}\n";
	return;
}

// 0.8.0 on: a customer's saved build, made by the old release's own service, from its first kit's first preset.
if ( 'saved' === ( $cbuild_args[0] ?? '' ) ) {
	$service = 'CreativeBranding\\SkateboardBuilder\\Service\\SavedBuilds';
	if ( ! class_exists( $service ) ) {
		echo "saved:none\n";
		return;
	}
	$user = wp_insert_user( array( 'user_login' => 'upgrade-shopper-' . wp_generate_password( 6, false ), 'user_pass' => wp_generate_password(), 'user_email' => 'shopper+' . wp_rand( 2, 99999 ) . '@example.com', 'role' => 'customer' ) );
	$kits = CreativeBranding\SkateboardBuilder\Plugin::container()->get( CreativeBranding\SkateboardBuilder\Port\Kits::class );
	foreach ( wc_get_products( array( 'type' => 'cbuild_kit', 'limit' => 50, 'return' => 'ids' ) ) as $id ) {
		$kit = $kits->kit( (int) $id );
		foreach ( null === $kit ? array() : $kit->presets as $preset ) {
			$said = CreativeBranding\SkateboardBuilder\Plugin::container()->get( $service )->save( (int) $user, (int) $id, $preset->picks, true, 'Upgrade build' );
			if ( 'saved' === $said['code'] ) {
				echo "saved:{$user}\n";
				return;
			}
		}
	}
	echo "saved:none\n";
	return;
}

$ids = array();
foreach ( array( 'Deck 8.25', 'Trucks 149', 'Wheels 54', 'Bearings', 'Hardware 1 in', 'Grip' ) as $n => $name ) {
	$product = new WC_Product_Simple();
	$product->set_name( 'Upgrade ' . $name );
	$product->set_sku( 'UPG-' . $n . '-' . wp_generate_password( 6, false ) );
	$product->set_regular_price( (string) ( 10 + 5 * $n ) );
	$product->set_manage_stock( true );
	$product->set_stock_quantity( 5 );
	$attribute = new WC_Product_Attribute();
	$attribute->set_name( 'Size' );
	$attribute->set_options( array( $name ) );
	$attribute->set_visible( true );
	$product->set_attributes( array( $attribute ) );
	$ids[] = $product->save();
}
$order = wc_create_order( array( 'customer_id' => 0 ) );
foreach ( $ids as $id ) {
	$order->add_product( wc_get_product( $id ), 1 );
}
$order->set_billing_email( 'shopper+1@example.com' );
$order->calculate_totals();
$order->set_status( 'processing' );
$order->save();
echo 'order:' . $order->get_id() . "\n";
