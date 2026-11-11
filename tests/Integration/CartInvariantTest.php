<?php
/**
 * The invariant, through the state route and the real cart.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Cart\CartHooks;
use CreativeBranding\SkateboardBuilder\Cart\LineHooks;
use CreativeBranding\SkateboardBuilder\Cart\PriceHooks;
use CreativeBranding\SkateboardBuilder\Hooks\CardHooks;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Catalogue;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use Random\Engine\Mt19937;
use Random\Randomizer;
use WC_Product_Simple;
use WP_REST_Request;

/**
 * Spec, Testing, The invariant: across random builds over a generated catalogue, whatever the state route offers,
 * the cart's validation accepts, and whatever it blocks, validation refuses. Milestone 2 held it of the domain
 * (`InvariantTest`); here the catalogue is a shop's products, each step asks the route as the builder does, and the
 * build goes to WooCommerce's cart through the Store API.
 *
 * A failure names its seed, and the seed makes the same walk again.
 */
final class CartInvariantTest extends IntegrationTestCase {

	/**
	 * How many walks each way, and how many parts each slot of the catalogue holds.
	 */
	private const RUNS  = 30;
	private const PARTS = 10;

	private int $kit;

	/**
	 * The kit's slots: key to whether it is required.
	 *
	 * @var array<string, bool>
	 */
	private array $slots = array();

	public function set_up(): void {
		parent::set_up();
		$this->service( CardHooks::class )->register();
		$this->service( CartHooks::class )->register();
		$this->service( LineHooks::class )->register();
		$this->service( PriceHooks::class )->register();
		add_filter( 'woocommerce_store_api_disable_nonce_check', '__return_true' );
		rest_get_server();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );
		// The walks ask the route some hundreds of times from one address.
		update_option( Options::SETTINGS, array( 'rate_limit' => 6000 ) );

		$catalogue = Catalogue::generate( 5, self::PARTS );
		$slots     = KitDocuments::slots();
		foreach ( $slots['slots'] as $at => $slot ) {
			$category                         = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
			$slots['slots'][ $at ]['sources'] = array( 'categories' => array( $category ) );
			$this->slots[ $slot['key'] ]      = $slot['required'];
			foreach ( $catalogue[ $slot['key'] ] as $n => $part ) {
				$product = new WC_Product_Simple();
				$product->set_name( $slot['key'] . ' ' . $n );
				$product->set_status( 'publish' );
				$product->set_regular_price( number_format( $part->price / 100, 2, '.', '' ) );
				$product->set_stock_status( $part->inStock ? 'instock' : 'outofstock' );
				$product->update_meta_data( Options::META_SPECS, $part->specs->toIndex() );
				$product->save();
				wp_set_object_terms( $product->get_id(), array( $category ), 'product_cat' );
				$this->service( SpecIndexer::class )->index( $product->get_id() );
			}
		}
		$this->kit = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
		wc_load_cart();
		WC()->cart->empty_cart();
		wc_clear_notices();
	}

	public function tear_down(): void {
		WC()->cart->empty_cart();
		wc_clear_notices();
		delete_option( Options::SETTINGS );
		parent::tear_down();
	}

	/**
	 * Ask the state route for a step, as the builder does.
	 *
	 * @param array<string, int> $picks The picks so far.
	 * @param string             $step  The step.
	 * @return array<string, mixed>
	 */
	private function state( array $picks, string $step ): array {
		$request = new WP_REST_Request( 'GET', "/cbuild/v1/kits/{$this->kit}/state" );
		$request->set_query_params(
			array(
				'picks' => $picks,
				'step'  => $step,
			)
		);
		$response = rest_get_server()->dispatch( $request );
		self::assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		return $response->get_data();
	}

	/**
	 * Send a build to the cart through the Store API, and give the answer's status and code.
	 *
	 * @param array<string, int> $picks The picks.
	 * @return array{int, string}
	 */
	private function add( array $picks ): array {
		$request = new WP_REST_Request( 'POST', '/wc/store/v1/cart/add-item' );
		$request->set_header( 'Content-Type', 'application/json' );
		$request->set_body(
			(string) wp_json_encode(
				array(
					'id'       => $this->kit,
					'quantity' => 1,
					'cbuild'   => array( 'picks' => $picks ),
				)
			)
		);
		$response = rest_get_server()->dispatch( $request );
		$data     = $response->get_data();
		WC()->cart->empty_cart();
		wc_clear_notices();
		return array( $response->get_status(), is_array( $data ) && isset( $data['code'] ) ? (string) $data['code'] : '' );
	}

	/**
	 * The IDs a step's list holds of one kind: those a shopper can pick, or those the route rules out, which are
	 * the hidden and the ones the shop cannot supply.
	 *
	 * @param array<string, mixed> $state The route's answer.
	 * @param bool                 $good  Whether the pickable are wanted.
	 * @return list<int>
	 */
	private static function ids( array $state, bool $good ): array {
		$ids = array();
		foreach ( $state['options'] as $option ) {
			if ( $good === (bool) $option['available'] ) {
				$ids[] = (int) $option['id'];
			}
		}
		foreach ( $good ? array() : $state['hidden'] as $option ) {
			$ids[] = (int) $option['id'];
		}
		return $ids;
	}

	/**
	 * Walk the steps as a shopper does, picking at each one of the parts the route offers and the shop can supply.
	 * With `$wrong` a slot's key, that step picks one the route rules out instead.
	 *
	 * @param Randomizer  $random The walk's randomness.
	 * @param string|null $wrong  The step that picks what it should not, or null.
	 * @return array<string, int>|null The picks, or null where the walk could not be made as asked.
	 */
	private function walk( Randomizer $random, ?string $wrong ): ?array {
		$picks = array();
		foreach ( $this->slots as $slot => $required ) {
			if ( $slot !== $wrong && ! $required && Catalogue::chance( $random, 50 ) ) {
				continue;
			}
			$state = $this->state( $picks, $slot );
			$from  = self::ids( $state, $slot !== $wrong );
			if ( array() === $from ) {
				if ( $required || $slot === $wrong ) {
					return null;
				}
				continue;
			}
			$picks[ $slot ] = Catalogue::pick( $random, $from );
		}
		return $picks;
	}

	public function test_what_the_route_offers_the_cart_accepts(): void {
		$made = 0;
		for ( $seed = 1; $seed <= self::RUNS; $seed++ ) {
			$picks = $this->walk( new Randomizer( new Mt19937( $seed ) ), null );
			if ( null === $picks ) {
				continue;
			}
			++$made;
			self::assertSame( array( 201, '' ), $this->add( $picks ), "seed {$seed}: " . wp_json_encode( $picks ) );
		}
		self::assertGreaterThan( self::RUNS / 2, $made, 'most walks reach a whole build' );
	}

	public function test_what_the_route_rules_out_the_cart_refuses(): void {
		$made  = 0;
		$slots = array_keys( $this->slots );
		for ( $seed = 1; $seed <= self::RUNS; $seed++ ) {
			$random = new Randomizer( new Mt19937( 1000 + $seed ) );
			$wrong  = Catalogue::pick( $random, $slots );
			$picks  = $this->walk( $random, $wrong );
			if ( null === $picks ) {
				continue;
			}
			++$made;
			self::assertSame( array( 400, 'cbuild_build_refused' ), $this->add( $picks ), "seed {$seed}, wrong at {$wrong}: " . wp_json_encode( $picks ) );
		}
		self::assertGreaterThan( self::RUNS / 3, $made, 'enough walks found a part the route rules out' );
	}
}
