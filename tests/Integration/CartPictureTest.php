<?php
/**
 * The kit's line in the cart shows the build's composed picture, and the deck's photo until it is made.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Cart\ThumbnailHooks;
use CreativeBranding\SkateboardBuilder\Hooks\PictureHooks;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;
use WP_REST_Request;

/**
 * Spec, The kit's thumbnail: "In every cart, the kit's line shows the build's composed picture once it's made,
 * through the Store API's cart item images and `woocommerce_cart_item_thumbnail`, and the deck's photo until then.
 * Every part sits under it as its own line with its own photo." (D-199.)
 */
final class CartPictureTest extends CartTestCase {

	/**
	 * The deck's photo, an ordinary product image.
	 */
	private int $photo;

	/**
	 * The trucks' photo.
	 */
	private int $trucks;

	public function set_up(): void {
		parent::set_up();
		$this->service( PictureHooks::class )->register();
		$this->service( ThumbnailHooks::class )->register();
		$this->photo  = LayerFiles::attachment( 400, 400, false, 'jpeg' );
		$this->trucks = LayerFiles::attachment( 300, 300, false, 'jpeg' );
		$deck         = wc_get_product( $this->parts['deck'] );
		$deck->set_image_id( $this->photo );
		$deck->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 420, 1626 ) );
		$deck->save();
		$trucks = wc_get_product( $this->parts['trucks'] );
		$trucks->set_image_id( $this->trucks );
		$trucks->save();
	}

	public function tear_down(): void {
		as_unschedule_all_actions( Options::JOB_COMPOSE_PICTURE, array(), Options::QUEUE_GROUP );
		as_unschedule_all_actions( Options::JOB_PRUNE_PICTURES, array(), Options::QUEUE_GROUP );
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		parent::tear_down();
	}

	/**
	 * Each line's first image as the Store API gives it, by product ID: its ID and its addresses.
	 *
	 * @return array<int, array{int, string, string, string}>
	 */
	private static function storeImages(): array {
		$cart   = rest_get_server()->dispatch( new WP_REST_Request( 'GET', '/wc/store/v1/cart' ) )->get_data();
		$images = array();
		foreach ( $cart['items'] as $item ) {
			$image                 = $item['images'][0] ?? null;
			$images[ $item['id'] ] = null === $image ? array( 0, '', '', '' ) : array( (int) $image->id, (string) $image->src, (string) $image->thumbnail, (string) $image->alt );
		}
		return $images;
	}

	/**
	 * Each line's classic thumbnail, by product ID, as the cart's template asks for it.
	 *
	 * @return array<int, string>
	 */
	private static function thumbnails(): array {
		$thumbnails = array();
		foreach ( WC()->cart->get_cart() as $key => $line ) {
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own, as templates/cart/cart.php:102 applies it.
			$thumbnails[ $line['product_id'] ] = (string) apply_filters( 'woocommerce_cart_item_thumbnail', $line['data']->get_image(), $line, $key );
		}
		return $thumbnails;
	}

	public function test_the_kits_line_shows_the_decks_photo_until_the_picture_is_made_and_then_the_picture(): void {
		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		$deck = (string) wp_get_attachment_image_src( $this->photo, 'full' )[0];

		$images = self::storeImages();
		self::assertSame( array( $this->photo, $deck, 'Complete' ), array( $images[ $this->kit ][0], $images[ $this->kit ][1], $images[ $this->kit ][3] ), 'the deck\'s photo, named for the kit' );
		self::assertSame( $this->photo, $images[ $this->parts['deck'] ][0], 'the deck\'s own line, its photo' );
		self::assertSame( $this->trucks, $images[ $this->parts['trucks'] ][0], 'every part keeps its own photo' );
		$thumbnails = self::thumbnails();
		self::assertStringContainsString( (string) wp_get_attachment_image_src( $this->photo, 'woocommerce_thumbnail' )[0], $thumbnails[ $this->kit ] );
		self::assertStringContainsString( 'alt="Complete"', $thumbnails[ $this->kit ] );

		self::work();
		$images  = self::storeImages();
		$picture = $images[ $this->kit ][1];
		self::assertMatchesRegularExpression( '#/creative-skateboard-builder/pictures/[0-9a-f]{32}\.webp$#', $picture, 'the composed picture' );
		self::assertSame( array( 0, $picture, 'Complete' ), array( $images[ $this->kit ][0], $images[ $this->kit ][2], $images[ $this->kit ][3] ) );
		self::assertFileExists( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/' . basename( $picture ) );
		self::assertSame( $this->trucks, $images[ $this->parts['trucks'] ][0], 'the parts are untouched' );
		$thumbnails = self::thumbnails();
		self::assertStringContainsString( 'src="' . esc_url( $picture ) . '" width="480" height="480" alt="Complete"', $thumbnails[ $this->kit ] );
		self::assertSame( wc_get_product( $this->parts['trucks'] )->get_image(), $thumbnails[ $this->parts['trucks'] ] );
	}

	public function test_a_line_that_is_no_kits_keeps_what_woocommerce_gave_it(): void {
		WC()->cart->add_to_cart( $this->parts['trucks'] );
		$key  = (string) array_key_first( WC()->cart->get_cart() );
		$line = WC()->cart->get_cart()[ $key ];
		$hooks = $this->service( ThumbnailHooks::class );
		self::assertSame( 'as it was', $hooks->thumbnail( 'as it was', $line ) );
		self::assertSame( array( 'as it was' ), $hooks->images( array( 'as it was' ), $line ) );
		self::assertSame( 'as it was', $hooks->thumbnail( 'as it was', 'not a line' ) );
	}
}
