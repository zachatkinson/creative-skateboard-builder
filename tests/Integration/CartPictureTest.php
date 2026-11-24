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
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\ComposedLayers;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\PictureFiles;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
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

	/**
	 * The auditor's 1.1.0 minor 7: a page with a Mini-Cart asks for each kit line's thumbnail and its Store API images,
	 * on every view until the picture is drawn. A picture not made is asked for once a request, and its composition is
	 * read once a call, so a call after the first asks the queue nothing and costs no more than the one before it.
	 */
	public function test_a_mini_cart_asks_the_queue_once_a_picture_and_each_later_call_costs_the_same(): void {
		foreach ( array( 1, 3 ) as $lines ) {
			WC()->cart->empty_cart();
			for ( $line = 0; $line < $lines; $line++ ) {
				self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
			}
			// The add's own answer asked for the picture: a page is a request of its own, with a service of its own.
			as_unschedule_all_actions( Options::JOB_COMPOSE_PICTURE, array(), Options::QUEUE_GROUP );
			$pictures = new ComposedPictures( $this->service( Kits::class ), $this->service( BuildState::class ), $this->service( LayerArtwork::class ), $this->service( PictureFiles::class ), $this->service( Queue::class ), $this->service( Clock::class ), $this->service( Logger::class ), $this->service( ComposedLayers::class ) );
			$hooks    = new ThumbnailHooks( static fn (): ComposedPictures => $pictures );
			$passes   = array();
			for ( $pass = 0; $pass < 3; $pass++ ) {
				$passes[] = self::queries(
					static function () use ( $hooks ): void {
						foreach ( WC()->cart->get_cart() as $line ) {
							$hooks->thumbnail( '', $line );
							$hooks->images( array(), $line );
						}
					}
				);
			}
			self::assertCount( 1, self::waiting( Options::JOB_COMPOSE_PICTURE ), $lines . ': the picture is asked for' );
			self::assertGreaterThan( 0, $passes[0]['queue'], $lines . ': the first call asks the queue' );
			self::assertSame( 0, $passes[1]['queue'], $lines . ': a later call asks it nothing' );
			self::assertSame( $passes[1], $passes[2], $lines . ': and each costs the same' );
		}
	}

	/**
	 * The queries a call makes: all of them, and those of the queue's tables.
	 *
	 * @param callable(): void $call The call.
	 * @return array{all: int, queue: int}
	 */
	private static function queries( callable $call ): array {
		$counted = array(
			'all'   => 0,
			'queue' => 0,
		);
		$count   = static function ( string $query ) use ( &$counted ): string {
			++$counted['all'];
			if ( str_contains( $query, 'actionscheduler_' ) ) {
				++$counted['queue'];
			}
			return $query;
		};
		add_filter( 'query', $count );
		try {
			$call();
		} finally {
			remove_filter( 'query', $count );
		}
		return $counted;
	}

	// Cowork's review of 1.0.0, F19 (#105; D-309): a flag set after the cart's picture was drawn draws it again, the deck
	// blurred, and meanwhile the kit's line shows neither the sharp picture nor the deck's photo.
	public function test_a_flag_set_after_the_carts_picture_was_drawn_draws_it_again_and_shows_no_deck_meanwhile(): void {
		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		self::work();
		$sharp = self::storeImages()[ $this->kit ][1];
		self::assertMatchesRegularExpression( '#/pictures/[0-9a-f]{32}\.webp$#', $sharp );

		$flag = fn ( string $answer, int $part ): string => $part === $this->parts['deck'] ? 'blurred' : $answer;
		add_filter( 'cbuild_composed_layer', $flag, 10, 2 );
		try {
			$images     = self::storeImages();
			$thumbnails = self::thumbnails();
			self::assertNotSame( $sharp, $images[ $this->kit ][1], 'never the sharp picture once the deck is flagged' );
			self::assertNotSame( $this->photo, $images[ $this->kit ][0], 'nor the deck\'s photo in its place' );
			self::assertStringNotContainsString( basename( $sharp ), $thumbnails[ $this->kit ] );
			self::assertStringNotContainsString( (string) wp_get_attachment_image_src( $this->photo, 'woocommerce_thumbnail' )[0], $thumbnails[ $this->kit ] );
			self::assertCount( 1, self::waiting( Options::JOB_COMPOSE_PICTURE ), 'the new picture is asked for' );

			self::work();
			$blurred = self::storeImages()[ $this->kit ][1];
			self::assertMatchesRegularExpression( '#/pictures/[0-9a-f]{32}\.webp$#', $blurred );
			self::assertNotSame( $sharp, $blurred );
			self::assertStringContainsString( 'src="' . esc_url( $blurred ) . '"', self::thumbnails()[ $this->kit ] );
		} finally {
			remove_filter( 'cbuild_composed_layer', $flag, 10 );
		}
	}
}
