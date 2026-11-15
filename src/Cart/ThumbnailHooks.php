<?php
/**
 * The kit's line in the cart shows its build's composed picture.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\Build;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec, The kit's thumbnail: "In every cart, the kit's line shows the build's composed picture once it's made,
 * through the Store API's cart item images and `woocommerce_cart_item_thumbnail`, and the deck's photo until then.
 * Every part sits under it as its own line with its own photo." (D-199.) Checked in WooCommerce 11.3.0:
 * `woocommerce_cart_item_thumbnail` with the product's image, the line and its key (templates/cart/cart.php:102,
 * templates/cart/mini-cart.php:52); `woocommerce_store_api_cart_item_images` with the line's images, as
 * ImageAttachmentSchema's objects, the line and its key, where an image with no `id`, or a `src` or `thumbnail`
 * with no host, is dropped (src/StoreApi/Schemas/V1/CartItemSchema.php:168). The part lines are left as they are.
 *
 * @since 0.7.0
 */
final class ThumbnailHooks implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param Closure(): ComposedPictures $pictures Builds the pictures' service, when a kit's line is shown.
	 */
	public function __construct( private Closure $pictures ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.7.0
	 */
	public function register(): void {
		add_filter( 'woocommerce_cart_item_thumbnail', array( $this, 'thumbnail' ), 10, 2 );
		add_filter( 'woocommerce_store_api_cart_item_images', array( $this, 'images' ), 10, 2 );
	}

	/**
	 * The classic cart's and the mini cart's thumbnail of a kit's line.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $html The product's image, as WooCommerce made it.
	 * @param mixed $line The cart line.
	 * @return mixed
	 */
	public function thumbnail( mixed $html, mixed $line ): mixed {
		$picture = $this->picture( $line );
		if ( null === $picture ) {
			return $html;
		}
		if ( null !== $picture['src'] ) {
			return sprintf(
				'<img src="%1$s" width="%2$d" height="%3$d" alt="%4$s" class="attachment-woocommerce_thumbnail cbuild-cart-picture" loading="lazy" decoding="async" />',
				esc_url( $picture['src'] ),
				ComposedPictures::CANVASES[ ComposedPictures::CART ][0],
				ComposedPictures::CANVASES[ ComposedPictures::CART ][1],
				esc_attr( $picture['name'] )
			);
		}
		$deck = wc_get_product( $picture['deck'] );
		return $deck instanceof WC_Product ? $deck->get_image( 'woocommerce_thumbnail', array( 'alt' => $picture['name'] ) ) : $html;
	}

	/**
	 * The Store API's images of a kit's line: the composed picture, or the deck's own images.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $images The kit product's images, as ImageAttachmentSchema's objects.
	 * @param mixed $line   The cart line.
	 * @return mixed
	 */
	public function images( mixed $images, mixed $line ): mixed {
		$picture = $this->picture( $line );
		if ( null === $picture ) {
			return $images;
		}
		if ( null !== $picture['src'] ) {
			return array(
				(object) array(
					'id'        => 0,
					'src'       => $picture['src'],
					'thumbnail' => $picture['src'],
					'srcset'    => '',
					'sizes'     => '',
					'name'      => $picture['name'],
					'alt'       => $picture['name'],
				),
			);
		}
		$deck  = wc_get_product( $picture['deck'] );
		$image = $deck instanceof WC_Product ? (int) $deck->get_image_id() : 0;
		$full  = $image > 0 ? wp_get_attachment_image_src( $image, 'full' ) : false;
		$small = $image > 0 ? wp_get_attachment_image_src( $image, 'woocommerce_thumbnail' ) : false;
		if ( false === $full || false === $small ) {
			return $images;
		}
		return array(
			(object) array(
				'id'        => $image,
				'src'       => $full[0],
				'thumbnail' => $small[0],
				'srcset'    => (string) wp_get_attachment_image_srcset( $image, 'full' ),
				'sizes'     => (string) wp_get_attachment_image_sizes( $image, 'full' ),
				'name'      => get_the_title( $image ),
				'alt'       => $picture['name'],
			),
		);
	}

	/**
	 * A kit's line's picture: the composed picture's address, or null while it is not made, with the deck to show
	 * until then and the kit's name for the alt text. Null for a line that is no kit's, or a build with no deck.
	 *
	 * @param mixed $line The cart line.
	 * @return array{src: string|null, deck: int, name: string}|null
	 */
	private function picture( mixed $line ): ?array {
		$build = Build::read( is_array( $line ) ? ( $line[ Options::CART_BUILD ] ?? null ) : null );
		$deck  = $build?->picks[ SlotKey::DECK ] ?? null;
		if ( null === $build || null === $deck ) {
			return null;
		}
		$kit = is_array( $line ) && ( $line['data'] ?? null ) instanceof WC_Product ? $line['data'] : null;
		return array(
			'src'  => ( $this->pictures )()->url( $build->kit, $build->picks, ComposedPictures::CART ),
			'deck' => $deck,
			'name' => null === $kit ? '' : wp_strip_all_tags( $kit->get_name() ),
		);
	}
}
