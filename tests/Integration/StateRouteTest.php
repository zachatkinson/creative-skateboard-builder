<?php
/**
 * The state route.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Rest\StateRoute;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use CreativeBranding\SkateboardBuilder\Service\RateLimit;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuilderUrl;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Plugin;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;
use WC_Product_Simple;
use WP_Locale_Switcher;
use WP_REST_Request;
use WP_REST_Response;

/**
 * `GET cbuild/v1/kits/<id>/state` (spec: How it fits; Security; French and other languages). Milestone 4's plan, the
 * route: the argument schema's refusals, `no-store`, the locale, each answer against `schema/state.json`, then the
 * rate limit with its 429 and `Retry-After`.
 */
final class StateRouteTest extends IntegrationTestCase {

	/**
	 * The kit.
	 *
	 * @var int
	 */
	private int $kit;

	/**
	 * The parts, by a name of the test's own.
	 *
	 * @var array<string, int>
	 */
	private array $parts = array();

	/**
	 * The deck and trucks slots' categories, by slot key.
	 *
	 * @var array<string, int>
	 */
	private array $categories = array();

	/**
	 * The schema, with each reference written out.
	 *
	 * @var array<string, mixed>|null
	 */
	private static ?array $schema = null;

	public function set_up(): void {
		parent::set_up();
		// What keeps each slot's kept parts current (D-116).
		$this->service( \CreativeBranding\SkateboardBuilder\Hooks\CardHooks::class )->register();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		// WordPress's test case puts the hooks back as they were after each test, and the plugin adds its own once.
		rest_get_server();
		$this->service( StateRoute::class )->register();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );

		$decks  = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$trucks = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$truck  = array(
			'baseplate'     => 'new_school',
			'max_wheel'     => '56.0000',
			'axle_diameter' => '8.0000',
			'set_size'      => '2',
		);
		$this->parts['deck 8.0']  = $this->part( 'Deck 8.0 & co', '60', array( 'deck_width' => '8.0000' ), $decks );
		$this->parts['deck 8.5']  = $this->part( 'Deck 8.5', '70', array( 'deck_width' => '8.5000' ), $decks );
		$this->parts['truck 139'] = $this->part(
			'Truck 139',
			'25',
			array(
				'axle_width' => '8.0000',
				'deck_min'   => '7.8000',
				'deck_max'   => '8.2000',
			) + $truck,
			$trucks
		);
		$this->parts['truck 149'] = $this->part(
			'Truck 149',
			'27',
			array(
				'axle_width' => '8.5000',
				'deck_min'   => '8.3750',
				'deck_max'   => '8.6000',
			) + $truck,
			$trucks
		);

		$this->categories             = array(
			'deck'   => $decks,
			'trucks' => $trucks,
		);
		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array( 'categories' => array( $decks ) );
		$slots['slots'][1]['sources'] = array( 'categories' => array( $trucks ) );
		$slots['slots'][1]['help']    = 'Sold in pairs.';
		$street                       = array_fill_keys( array( 'wheels', 'bearings', 'hardware', 'grip' ), $this->parts['deck 8.0'] );
		$this->kit                    = KitFactory::kit(
			'Complete',
			array(
				Options::META_KIT_SLOTS    => $slots,
				Options::META_KIT_PRESETS  => array(
					'schema_version' => 1,
					'presets'        => array(
						array(
							'name'        => 'Street',
							'description' => 'For ledges.',
							'picks'       => array(
								'deck'   => $this->parts['deck 8.0'],
								'trucks' => $this->parts['truck 139'],
							) + $street,
						),
					),
				),
				Options::META_KIT_ASSEMBLY => array(
					'schema_version' => 1,
					'default_on'     => false,
					'fee'            => '15',
					'lead_time'      => 'Two working days.',
					'returns_notice' => 'An assembled board goes back whole.',
				),
			)
		)->get_id();
	}

	public function tear_down(): void {
		unset( $_SERVER['REMOTE_ADDR'] );
		remove_all_filters( 'get_available_languages' );
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		parent::tear_down();
	}

	/**
	 * A published part in a category, indexed.
	 *
	 * @param array<string, string> $specs Its specs.
	 */
	private function part( string $name, string $price, array $specs, int $category ): int {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->update_meta_data( Options::META_SPECS, $specs );
		$product->save();
		wp_set_object_terms( $product->get_id(), array( $category ), 'product_cat' );
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		return $product->get_id();
	}

	/**
	 * A kit like set_up's whose Street is whole (#104): each of its picks one of its slot's parts, in stock and
	 * fitting, so its tile is shown. Set up's Street names the 8.0 deck in every other slot, which no other slot
	 * offers, so it is left out for shoppers.
	 *
	 * @param array<string, mixed>|null                                        $layout  The kit's layout document, or null for one saved before 1.0.0.
	 * @param (callable(array<string, int>): list<array<string, mixed>>)|null $presets  The kit's presets from the whole Street's picks, or null for that Street alone.
	 * @param array<string, mixed>|null                                        $settings The kit's settings document, or null for one saved before 1.2.0.
	 * @return array{kit: int, picks: array<string, int>}
	 */
	private function whole( ?array $layout = null, ?callable $presets = null, ?array $settings = null ): array {
		$slots = KitDocuments::slots();
		$picks = array(
			'deck'   => $this->parts['deck 8.0'],
			'trucks' => $this->parts['truck 139'],
		);
		foreach ( array(
			'wheels'   => array(
				'wheel_diameter' => '52.0000',
				'set_size'       => '4',
			),
			'bearings' => array(
				'bearing_bore' => '8.0000',
				'set_size'     => '8',
			),
			'hardware' => array(
				'bolt_length' => '1.0000',
				'set_size'    => '8',
			),
			'grip'     => array(
				'grip_width'  => '9.0000',
				'grip_length' => '33.0000',
			),
		) as $key => $specs ) {
			$this->categories[ $key ] = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
			$picks[ $key ]            = $this->part( ucfirst( $key ) . ' for Street', '10', $specs, $this->categories[ $key ] );
		}
		foreach ( $slots['slots'] as $at => $slot ) {
			if ( isset( $this->categories[ $slot['key'] ] ) ) {
				$slots['slots'][ $at ]['sources'] = array( 'categories' => array( $this->categories[ $slot['key'] ] ) );
			}
		}
		$slots['slots'][1]['help'] = 'Sold in pairs.';
		$kit                       = KitFactory::kit(
			'Complete, whole',
			array(
				Options::META_KIT_SLOTS    => $slots,
				Options::META_KIT_PRESETS  => array(
					'schema_version' => 1,
					'presets'        => null === $presets ? array(
						array(
							'name'        => 'Street',
							'description' => 'For ledges.',
							'picks'       => $picks,
						),
					) : $presets( $picks ),
				),
				Options::META_KIT_ASSEMBLY => array(
					'schema_version' => 1,
					'default_on'     => false,
					'fee'            => '15',
					'lead_time'      => 'Two working days.',
					'returns_notice' => 'An assembled board goes back whole.',
				),
				Options::META_KIT_LAYOUT   => $layout,
				Options::META_KIT_SETTINGS => $settings,
			)
		)->get_id();
		return array(
			'kit'   => $kit,
			'picks' => $picks,
		);
	}

	/**
	 * Ask the route, as WordPress serves a request: its last filter run on the answer.
	 *
	 * @param array<string, mixed>  $query   The query's arguments.
	 * @param array<string, string> $headers The request's headers.
	 */
	private function ask( array $query = array(), ?int $kit = null, array $headers = array() ): WP_REST_Response {
		$request = new WP_REST_Request( 'GET', '/cbuild/v1/kits/' . ( $kit ?? $this->kit ) . '/state' );
		$request->set_query_params( $query );
		foreach ( $headers as $name => $value ) {
			$request->set_header( $name, $value );
		}
		$server   = rest_get_server();
		$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( $server->dispatch( $request ) ), $server, $request );
		self::assertInstanceOf( WP_REST_Response::class, $response );
		return $response;
	}

	/**
	 * An answer's body, as the script reads it: through JSON.
	 *
	 * @return array<string, mixed>
	 */
	private static function body( WP_REST_Response $response ): array {
		$body = json_decode( (string) wp_json_encode( rest_get_server()->response_to_data( $response, false ) ), true );
		self::assertIsArray( $body );
		return $body;
	}

	/**
	 * `schema/state.json` with each `$ref` written out, which WordPress's validator needs, and made strict: the
	 * published schema lets a field it does not describe through, since the answer gains fields, and here every
	 * object takes its own properties alone, so a field the schema does not describe fails the test.
	 *
	 * @return array<string, mixed>
	 */
	private static function schema(): array {
		if ( null === self::$schema ) {
			$schema = json_decode( (string) file_get_contents( CBUILD_PATH . 'schema/state.json' ), true );
			self::assertIsArray( $schema );
			$definitions = $schema['definitions'];
			$resolve     = static function ( mixed $node ) use ( &$resolve, $definitions ): mixed {
				if ( ! is_array( $node ) ) {
					return $node;
				}
				if ( isset( $node['$ref'] ) ) {
					$name = substr( $node['$ref'], strlen( '#/definitions/' ) );
					self::assertArrayHasKey( $name, $definitions, 'A reference to a definition the schema lacks.' );
					$rest = $node;
					unset( $rest['$ref'] );
					return $resolve( $rest + $definitions[ $name ] );
				}
				$node = array_map( $resolve, $node );
				if ( isset( $node['properties'] ) && is_array( $node['properties'] ) && isset( $node['type'] ) ) {
					self::assertNotFalse( $node['additionalProperties'] ?? null, 'The published schema closes no object.' );
					// Closed here, so an answer with a field the schema does not describe fails; but for the view, which
					// says it is open: only the fields the store reads are described, the rest are the templates' (S9).
					if ( true !== ( $node['additionalProperties'] ?? null ) ) {
						$node['additionalProperties'] = false;
					}
				}
				return $node;
			};
			unset( $schema['definitions'] );
			self::$schema = $resolve( $schema );
		}
		return self::$schema;
	}

	/**
	 * A 200 whose body the published schema accepts.
	 *
	 * @param array<string, mixed> $query The query's arguments.
	 * @return array<string, mixed>
	 */
	private function answer( array $query = array() ): array {
		$response = $this->ask( $query );
		self::assertSame( 200, $response->get_status(), (string) wp_json_encode( $response->get_data() ) );
		$body  = self::body( $response );
		$valid = rest_validate_value_from_schema( $body, self::schema(), 'state' );
		self::assertTrue( $valid, is_wp_error( $valid ) ? $valid->get_error_message() : '' );
		return $body;
	}

	public function test_the_route_is_public_and_takes_only_a_get(): void {
		$routes = rest_get_server()->get_routes( 'cbuild/v1' );
		$route  = '/cbuild/v1/kits/(?P<id>[1-9][0-9]{0,17})/state';
		self::assertArrayHasKey( $route, $routes );
		self::assertSame( array( 'GET' => true ), $routes[ $route ][0]['methods'] );
		self::assertSame( '__return_true', $routes[ $route ][0]['permission_callback'], 'Said, not left out: WordPress warns of a route with none.' );
		self::assertSame( array( 'id', 'picks', 'previous', 'step', 'assembly', 'locale', 'cbuild', 'page', 'width', 'chips', 'q', 'sort', 'from' ), array_keys( $routes[ $route ][0]['args'] ) );

		wp_set_current_user( 0 );
		self::assertSame( 200, $this->ask()->get_status() );
		$post = new WP_REST_Request( 'POST', '/cbuild/v1/kits/' . $this->kit . '/state' );
		self::assertSame( 404, rest_get_server()->dispatch( $post )->get_status() );

		// An ID is a whole number above nothing that PHP can hold: anything else is no route at all.
		foreach ( array( '0', '-5', '1.5', 'abc', '1234567890123456789' ) as $id ) {
			$answer = rest_get_server()->dispatch( new WP_REST_Request( 'GET', "/cbuild/v1/kits/{$id}/state" ) );
			self::assertSame( 'rest_no_route', $answer->get_data()['code'] ?? null, $id );
		}
	}

	public function test_every_answer_is_the_published_schemas(): void {
		$deck   = $this->parts['deck 8.5'];
		$narrow = $this->parts['truck 139'];

		$empty = $this->answer();
		self::assertSame( 'deck', $empty['step'] );
		self::assertFalse( $empty['ready'] );
		self::assertSame( array( $this->parts['deck 8.0'], $deck ), array_column( $empty['options'], 'id' ) );
		$card = $empty['options'][0]['card'];
		self::assertSame( 'Deck 8.0 & co', $card['name'], 'plain text, for the page to escape' );
		self::assertSame( '$60.00', $card['price'] );
		self::assertSame( get_permalink( $this->parts['deck 8.0'] ), $card['url'] );

		$picked = $this->answer(
			array(
				'picks' => array( 'deck' => (string) $deck ),
				'step'  => 'trucks',
			)
		);
		self::assertSame( 'trucks', $picked['step'] );
		self::assertSame( array( $this->parts['truck 149'] ), array_column( $picked['options'], 'id' ) );
		self::assertSame( array( $narrow ), array_column( $picked['hidden'], 'id' ) );
		self::assertSame( 'The trucks are made for decks from 7.8″ to 8.2″ wide. The deck is 8.5″.', $picked['hidden'][0]['messages'][0]['text'] );
		self::assertSame( '1 part hidden: the trucks are made for another width of deck.', $picked['steps'][1]['groups'][0]['message'] );
		self::assertSame( array( 'amount' => 7000, 'text' => '$70.00' ), $picked['total']['lines'][0]['price'] );
		self::assertSame( 'Deck 8.5', $picked['total']['lines'][0]['name'] );
		self::assertSame( '$70.00', $picked['total']['total']['text'], 'the deck alone: nothing is picked for the shopper (D-213)' );
		self::assertArrayHasKey( 'estimate', $picked['total'] );
		self::assertNull( $picked['total']['estimate'], 'this shop has no wheels, so no complete board to estimate' );

		$changed = $this->answer(
			array(
				'picks'    => array(
					'deck'   => $deck,
					'trucks' => $narrow,
				),
				'previous' => array(
					'deck'   => $this->parts['deck 8.0'],
					'trucks' => $narrow,
				),
			)
		);
		self::assertSame( $narrow, $changed['changes']['changes'][0]['was'] );
		self::assertSame( 'Truck 139', $changed['changes']['changes'][0]['name'] );
		self::assertSame( $this->parts['truck 149'], $changed['changes']['changes'][0]['closest']['id'] );
		self::assertSame( 'part_blocked', $changed['problems'][0]['code'] );

		$refused = $this->answer( array( 'picks' => array( 'deck' => $narrow ) ) );
		self::assertSame( 'part_not_in_slot', $refused['refused'][0]['code'] );
		self::assertSame( $narrow, $refused['refused'][0]['part'] );

		$assembled = $this->answer( array( 'assembly' => 'true' ) );
		self::assertTrue( $assembled['total']['assembly'] );
		self::assertFalse( $this->answer( array( 'assembly' => 'false' ) )['total']['assembly'] );
	}

	public function test_a_build_link_is_answered_with_the_view_the_page_would_show(): void {
		// Plain permalinks, where a form must carry the page's own ID: a multisite's site may have others.
		$this->set_permalink_structure( '' );
		$deck = $this->parts['deck 8.5'];
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $this->kit . '} /-->',
			)
		);
		$build = array(
			'deck' => (string) $deck,
			'step' => 'trucks',
		);

		$answer = $this->answer(
			array(
				'cbuild' => $build,
				'page'   => $page,
			)
		);
		$view = function ( string $base ) use ( $build ): mixed {
			$shown = Plugin::container()->get( Builder::class )->show( $this->kit, BuildLink::read( $build ), $base );
			self::assertNotNull( $shown );
			return json_decode( (string) wp_json_encode( $shown['view'] ), true );
		};
		self::assertSame( 'trucks', $answer['step'] );
		self::assertSame( $view( (string) get_permalink( $page ) ), $answer['view'], 'the page\'s own view, for the page it is on' );
		self::assertContains( array( 'name' => 'page_id', 'value' => (string) $page ), $answer['view']['carry'], 'its forms go back to that page' );

		// A page that holds no builder is not where the builder is: its links go to the kit's builder.
		$plain = self::factory()->post->create( array( 'post_status' => 'publish' ) );
		$there = $this->answer(
			array(
				'cbuild' => $build,
				'page'   => $plain,
			)
		);
		self::assertSame( $view( BuilderUrl::of( $this->kit ) ), $there['view'] );
		self::assertSame( $view( BuilderUrl::of( $this->kit ) ), $this->answer( array( 'cbuild' => $build ) )['view'] );
		// Nor is a page whose block sells another kit.
		$other = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:group --><div><!-- wp:creative-skateboard-builder/builder {"kit":' . ( $this->kit + 1000 ) . '} /--></div><!-- /wp:group -->',
			)
		);
		self::assertSame(
			$view( BuilderUrl::of( $this->kit ) ),
			$this->answer(
				array(
					'cbuild' => $build,
					'page'   => $other,
				)
			)['view']
		);
		// A block inside another is found.
		$nested = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => '<!-- wp:group --><div><!-- wp:creative-skateboard-builder/builder {"kit":' . $this->kit . '} /--></div><!-- /wp:group -->',
			)
		);
		self::assertSame(
			$view( (string) get_permalink( $nested ) ),
			$this->answer(
				array(
					'cbuild' => $build,
					'page'   => $nested,
				)
			)['view']
		);

		self::assertArrayNotHasKey( 'view', $this->answer( array( 'picks' => array( 'deck' => $deck ) ) ), 'asked by its five arguments, the answer is 0.4.0\'s' );
	}

	public function test_a_build_link_takes_the_place_of_the_five_arguments_and_is_refused_beside_them(): void {
		foreach ( array( 'picks', 'previous', 'step', 'assembly' ) as $argument ) {
			$query = array(
				'cbuild'  => array( 'deck' => (string) $this->parts['deck 8.5'] ),
				$argument => 'step' === $argument ? 'deck' : ( 'assembly' === $argument ? 'true' : array( 'deck' => $this->parts['deck 8.0'] ) ),
			);
			$response = $this->ask( $query );
			self::assertSame( 400, $response->get_status(), $argument );
			self::assertSame( 'rest_invalid_param', $response->get_data()['code'] ?? null, $argument );
		}
	}

	public function test_an_empty_build_link_is_the_clean_page_with_its_view(): void {
		$answer = $this->answer( array( 'cbuild' => '' ) );
		self::assertSame( 'deck', $answer['step'] );
		self::assertArrayHasKey( 'view', $answer );
	}

	/**
	 * #89: the store asks for `_fields=view`, and WordPress trims the answer to the view it draws; the raw shelf and
	 * options, which repeated the step's cards, stay in the answer for anyone who asks without it.
	 */
	public function test_an_answer_asked_for_its_view_alone_is_that_view_and_no_more(): void {
		$query   = array(
			'cbuild' => array( 'step' => 'deck' ),
			'page'   => $this->kit,
		);
		$whole   = self::body( $this->ask( $query ) );
		$trimmed = self::body( $this->ask( $query + array( '_fields' => 'view' ) ) );
		self::assertSame( array( 'view' ), array_keys( $trimmed ) );
		self::assertSame( $whole['view'], $trimmed['view'] );
		self::assertArrayHasKey( 'shelf', $whole );
		self::assertArrayHasKey( 'options', $whole );
	}

	public function test_a_build_link_the_kit_cannot_read_is_still_answered(): void {
		$answer = $this->answer( array( 'cbuild' => array( 'nope' => 'x', 'deck' => '999999' ) ) );
		self::assertSame( 'deck', $answer['step'], 'a link is read as the page reads it: what is no part of it is dropped' );
		self::assertArrayHasKey( 'view', $answer );
	}

	public function test_the_shelf_lists_the_step_as_its_arguments_ask(): void {
		$asking = $this->answer();
		self::assertSame( array(), $asking['shelf']['looks'], 'the deck step asks its width first' );
		self::assertSame( array( '8in', '8.5in' ), array_column( $asking['shelf']['widths'], 'value' ) );
		self::assertSame( array( '8″', '8.5″' ), array_column( $asking['shelf']['widths'], 'text' ) );

		$wide = $this->answer( array( 'width' => '8.5in' ) );
		self::assertSame( array( $this->parts['deck 8.5'] ), array_column( array_column( $wide['shelf']['looks'], 'part' ), 'id' ) );
		self::assertSame( 'Deck 8.5', $wide['shelf']['looks'][0]['part']['card']['name'], 'each card\'s part with its card' );
		self::assertSame( array( false, true ), array_column( $wide['shelf']['widths'], 'selected' ) );

		$trucks = array(
			'picks' => array( 'deck' => $this->parts['deck 8.0'] ),
			'step'  => 'trucks',
		);
		self::assertSame( array( $this->parts['truck 139'] ), array_column( array_column( $this->answer( $trucks )['shelf']['looks'], 'part' ), 'id' ) );
		$none = $this->answer( $trucks + array( 'q' => '  nothing like it ', 'sort' => 'newest' ) );
		self::assertSame( array( array(), 0, 'nothing like it', 'newest' ), array( $none['shelf']['looks'], $none['shelf']['matching'], $none['shelf']['query'], $none['shelf']['sort'] ) );
		self::assertSame( array(), $this->answer( $trucks + array( 'from' => '1' ) )['shelf']['looks'], 'a page past the last lists none' );
		self::assertSame( 1, $this->answer( $trucks + array( 'chips' => array( 'brand' => array( 'nobody' ) ) ) )['shelf']['offered'], 'a chip no look has narrows nothing it does not know' );
	}

	public function test_a_build_link_carries_the_shelf_and_the_routes_arguments_change_it(): void {
		$linked = $this->answer( array( 'cbuild' => array( '_width' => '8.5in' ) ) );
		self::assertSame( array( $this->parts['deck 8.5'] ), array_column( array_column( $linked['shelf']['looks'], 'part' ), 'id' ) );
		$changed = $this->answer(
			array(
				'cbuild' => array( '_width' => '8.5in' ),
				'width'  => '8in',
			)
		);
		self::assertSame( array( $this->parts['deck 8.0'] ), array_column( array_column( $changed['shelf']['looks'], 'part' ), 'id' ) );
		self::assertStringContainsString( rawurlencode( 'cbuild[_width]' ) . '=8in', $changed['view']['url'], 'the view\'s address is the build as now listed' );
	}

	// #114, on the route: a kit saved before 1.2.0 answers the plugin's rows; the shop's own rows are answered with
	// their shoe sizes through `cbuild_kit_text`, and a hidden guide carries no rows and no words.
	public function test_the_width_guide_is_the_kits_own_through_the_text_filter_and_a_hidden_one_is_not_in_the_answer(): void {
		$guide = static fn( array $answer ): mixed => $answer['view']['step']['shelf']['width']['guide'] ?? null;
		$deck  = array( 'cbuild' => array( 'step' => 'deck' ) );
		$plain = $guide( $this->answer( $deck ) );
		self::assertIsArray( $plain );
		self::assertTrue( $plain['shown'] );
		self::assertSame( 'Shoe size (US)', $plain['shoe'] );
		self::assertSame( array( '6.5 or smaller', '7 to 8', '8.5 to 9', '9.5 and larger' ), array_column( $plain['rows'], 'shoe' ) );

		$rows      = array(
			array(
				'shoe' => 'EU 38 to 40',
				'from' => '7.75',
				'to'   => '8',
			),
			array(
				'shoe' => 'EU 41 and up',
				'from' => '8.25',
			),
		);
		$this->kit = $this->whole( null, null, array( 'schema_version' => 1, 'width_guide' => array( 'rows' => $rows ) ) )['kit'];
		$kit       = $this->kit;
		add_filter( 'cbuild_kit_text', static fn( string $text, string $field, int $id, string $of ): string => 'width_guide_shoe' === $field && $kit === $id && '1' === $of ? 'EU 41 et plus' : $text, 10, 4 );
		$own = $guide( $this->answer( $deck ) );
		self::assertIsArray( $own );
		self::assertSame( 'Shoe size', $own['shoe'], 'not the plugin\'s US sizes' );
		self::assertSame(
			array(
				array( 'shoe' => 'EU 38 to 40', 'width' => '7.75″ to 8″' ),
				array( 'shoe' => 'EU 41 et plus', 'width' => '8.25″ and wider' ),
			),
			$own['rows']
		);

		$this->kit = $this->whole( null, null, array( 'schema_version' => 1, 'width_guide' => array( 'shown' => false, 'rows' => $rows ) ) )['kit'];
		$body      = (string) wp_json_encode( $this->answer( $deck ) );
		self::assertSame( array( 'shown' => false, 'summary' => '', 'caption' => '', 'shoe' => '', 'width' => '', 'rows' => array() ), $guide( json_decode( $body, true ) ) );
		self::assertStringNotContainsString( 'EU 38', $body, 'nothing of the rows in the answer' );
		self::assertStringNotContainsString( 'Not sure of your width?', $body );
	}

	public function test_the_schema_refuses_an_answer_that_is_not_its_own(): void {
		$body = $this->answer();
		unset( $body['total'] );
		self::assertWPError( rest_validate_value_from_schema( $body, self::schema(), 'state' ) );
		$body                = $this->answer();
		$body['options'][0] += array( 'cost' => 1 );
		self::assertWPError( rest_validate_value_from_schema( $body, self::schema(), 'state' ) );
		$body                           = $this->answer();
		$body['total']['total']['text'] = 12;
		self::assertWPError( rest_validate_value_from_schema( $body, self::schema(), 'state' ) );
	}

	/**
	 * @return iterable<string, array{array<string, mixed>, string}>
	 */
	public static function refusals(): iterable {
		yield 'picks that are no map' => array( array( 'picks' => '5' ), 'picks' );
		yield 'a slot key that is no key' => array( array( 'picks' => array( 'Deck' => '5' ) ), 'picks' );
		yield 'a key with a line break' => array( array( 'picks' => array( "deck\n" => '5' ) ), 'picks' );
		yield 'an ID that is no number' => array( array( 'picks' => array( 'deck' => 'five' ) ), 'picks' );
		yield 'an ID of nothing' => array( array( 'picks' => array( 'deck' => '0' ) ), 'picks' );
		yield 'an ID below nothing' => array( array( 'picks' => array( 'deck' => '-4' ) ), 'picks' );
		yield 'an ID that is a list' => array( array( 'picks' => array( 'deck' => array( '5', '6' ) ) ), 'picks' );
		yield 'more picks than a kit can have slots' => array( array( 'picks' => array_fill_keys( array_map( static fn( int $n ): string => "slot_{$n}", range( 1, 21 ) ), '5' ) ), 'picks' );
		yield 'previous picks that are no map' => array( array( 'previous' => 'deck' ), 'previous' );
		yield 'a previous ID that is no number' => array( array( 'previous' => array( 'deck' => '1.5' ) ), 'previous' );
		yield 'a step that is no key' => array( array( 'step' => 'Trucks!' ), 'step' );
		yield 'assembly that is neither' => array( array( 'assembly' => 'perhaps' ), 'assembly' );
		yield 'a locale that is no locale' => array( array( 'locale' => '../fr' ), 'locale' );
		yield 'a locale with a line break' => array( array( 'locale' => "fr_CA\n" ), 'locale' );
		yield 'a search of 101 characters' => array( array( 'q' => str_repeat( 'é', 101 ) ), 'q' );
		yield 'a search that is a list' => array( array( 'q' => array( 'x' ) ), 'q' );
		yield 'a sort of its own' => array( array( 'sort' => 'cheapest' ), 'sort' );
		yield 'a page before the first' => array( array( 'from' => '-1' ), 'from' );
		// The auditor's 1.0.0 minor 8: the shelf lists 500 looks at most, so no page starts past them.
		yield 'a page past the most a step lists' => array( array( 'from' => '501' ), 'from' );
		yield 'a width with no unit' => array( array( 'width' => '8.25' ), 'width' );
		yield 'a width with a line break' => array( array( 'width' => "8in\n" ), 'width' );
		yield 'chips that are no map' => array( array( 'chips' => 'brand' ), 'chips' );
		yield 'a chip group that is no key' => array( array( 'chips' => array( 'Brand' => array( 'x' ) ) ), 'chips' );
		yield 'a chip that is no slug' => array( array( 'chips' => array( 'brand' => array( '<b>' ) ) ), 'chips' );
		yield 'more chips than a group shows' => array( array( 'chips' => array( 'brand' => array_map( static fn( int $n ): string => "b{$n}", range( 1, 21 ) ) ) ), 'chips' );
		yield 'more chip groups than a step shows' => array( array( 'chips' => array_fill_keys( array( 'brand', 'pa_a', 'pa_b', 'pa_c', 'pa_d' ), array( 'x' ) ) ), 'chips' );
	}

	/**
	 * @dataProvider refusals
	 *
	 * @param array<string, mixed> $query The query's arguments.
	 */
	public function test_the_argument_schema_refuses( array $query, string $argument ): void {
		$response = $this->ask( $query );
		self::assertSame( 400, $response->get_status() );
		$body = self::body( $response );
		self::assertSame( 'rest_invalid_param', $body['code'] );
		self::assertSame( array( $argument ), array_keys( $body['data']['params'] ) );
	}

	public function test_a_slot_the_kit_does_not_have_is_refused_wherever_it_is_named(): void {
		foreach ( array(
			array( 'picks' => array( 'sail' => '5' ) ),
			array( 'previous' => array( 'sail' => '5' ) ),
			array( 'step' => 'sail' ),
		) as $query ) {
			$response = $this->ask( $query );
			self::assertSame( 400, $response->get_status(), (string) wp_json_encode( $query ) );
			$body = self::body( $response );
			self::assertSame( 'rest_invalid_param', $body['code'] );
			self::assertSame( 'This kit has no slot called "sail".', $body['message'] );
		}
		// Twenty picks pass the schema, which knows no kit; the kit has seven slots.
		$many = $this->ask( array( 'picks' => array_fill_keys( array_map( static fn( int $n ): string => "slot_{$n}", range( 1, 20 ) ), '5' ) ) );
		self::assertSame( 400, $many->get_status() );
		self::assertSame( 'This kit has no slot called "slot_1".', self::body( $many )['message'] );
	}

	// Claude in Cowork's review of milestone 4, minor 1: the whole state was worked out before the slot was refused.
	public function test_a_slot_the_kit_does_not_have_is_refused_before_the_state_is_worked_out(): void {
		$route = new StateRoute(
			$this->service( Kits::class ),
			static function (): BuildState {
				throw new \LogicException( 'The state was worked out for a slot the kit does not have.' );
			},
			fn (): StateAnswer => $this->service( StateAnswer::class ),
			fn (): RateLimit => $this->service( RateLimit::class ),
			fn (): \CreativeBranding\SkateboardBuilder\Port\Cart => $this->service( \CreativeBranding\SkateboardBuilder\Port\Cart::class ),
			fn (): Builder => $this->service( Builder::class ),
			$this->service( \CreativeBranding\SkateboardBuilder\Front\BuilderPlace::class )
		);
		foreach ( array(
			array( 'picks' => array( 'sail' => '5' ) ),
			array( 'previous' => array( 'sail' => '5' ) ),
			array( 'step' => 'sail' ),
		) as $query ) {
			$request = new WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
			$request->set_url_params( array( 'id' => (string) $this->kit ) );
			$request->set_query_params( $query );
			$answer = $route->state( $request );
			self::assertInstanceOf( \WP_Error::class, $answer, (string) wp_json_encode( $query ) );
			self::assertSame( 'rest_invalid_param', $answer->get_error_code() );
		}
	}

	public function test_a_kit_a_visitor_may_not_see_is_answered_as_one_that_is_not_there(): void {
		$none = self::body( $this->ask( array(), 999999 ) );
		self::assertSame( 'cbuild_kit_not_found', $none['code'] );
		self::assertSame( 404, $none['data']['status'] );

		$draft = KitFactory::kit( 'Draft' );
		$draft->set_status( 'draft' );
		$draft->save();
		$private = KitFactory::kit( 'Private' );
		$private->set_status( 'private' );
		$private->save();
		$locked = KitFactory::kit( 'Behind a password' );
		$locked->set_post_password( 'kickflip' );
		$locked->save();
		$broken = KitFactory::kit( 'No slots', array( Options::META_KIT_SLOTS => null ) );

		// Even to someone who could read the draft in wp-admin: the route has one answer for everyone.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		foreach ( array( $draft->get_id(), $private->get_id(), $locked->get_id(), $broken->get_id(), $this->parts['deck 8.5'] ) as $id ) {
			$response = $this->ask( array(), $id );
			self::assertSame( 404, $response->get_status(), "Product {$id}." );
			self::assertSame( $none, self::body( $response ), 'The same words as for no product at all.' );
		}
	}

	public function test_every_answer_of_the_route_is_kept_out_of_caches_and_out_of_search(): void {
		foreach ( array(
			200 => $this->ask(),
			400 => $this->ask( array( 'step' => 'Trucks!' ) ),
			404 => $this->ask( array(), 999999 ),
		) as $status => $response ) {
			self::assertSame( $status, $response->get_status() );
			self::assertSame( 'no-store', $response->get_headers()['Cache-Control'] ?? null, "A {$status}." );
			self::assertSame( 'noindex', $response->get_headers()['X-Robots-Tag'] ?? null, "A {$status}." );
		}

		// Another plugin's route, and one of this plugin's own that is not the state route, are left as they are.
		// The other route is registered here, not left to whichever test ran before, and on a network Site Health
		// is the super admin's.
		$this->service( \CreativeBranding\SkateboardBuilder\Health\SiteHealth::class )->register();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );
		$admin = self::factory()->user->create( array( 'role' => 'administrator' ) );
		wp_set_current_user( $admin );
		if ( is_multisite() ) {
			grant_super_admin( $admin );
		}
		foreach ( array( '/wp/v2/types', '/cbuild/v1/site-health/catalogue-check' ) as $route ) {
			$request  = new WP_REST_Request( 'GET', $route );
			$response = apply_filters( 'rest_post_dispatch', rest_ensure_response( rest_get_server()->dispatch( $request ) ), rest_get_server(), $request );
			self::assertSame( 200, $response->get_status() );
			self::assertArrayNotHasKey( 'Cache-Control', $response->get_headers(), $route );
		}
	}

	// Spec review of milestone 7, should-fix: the picture's written description had no test in French. Spec, The
	// picture: "a written description of the build"; Internationalisation: no sentence is built by joining words.
	/**
	 * #90: the route's own time and queries, in a Server-Timing header, to a signed-in user who can manage WooCommerce
	 * alone, so the priming work can be measured on a real host (Zach, 22 November: shop managers only, no filter).
	 */
	public function test_a_shop_manager_is_told_the_routes_time_and_queries_and_no_one_else_is(): void {
		$query = array(
			'cbuild' => array( 'step' => 'deck' ),
			'page'   => $this->kit,
		);
		self::assertArrayNotHasKey( 'Server-Timing', $this->ask( $query )->get_headers(), 'a visitor' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		self::assertArrayNotHasKey( 'Server-Timing', $this->ask( $query )->get_headers(), 'a customer' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$response = $this->ask( $query );
		$timing   = $response->get_headers()['Server-Timing'] ?? '';
		self::assertMatchesRegularExpression( '#^cbuild-route;dur=\d+\.\d, cbuild-queries;desc="\d+"(, cbuild-[a-z]+;dur=\d+\.\d)*$#', $timing );
		preg_match( '#dur=([\d.]+).*desc="(\d+)"#', $timing, $read );
		self::assertGreaterThan( 0.0, (float) $read[1], 'the route takes some time' );
		self::assertGreaterThan( 0, (int) $read[2], 'and asks the database something' );
		self::assertSame( 'no-store', $response->get_headers()['Cache-Control'] ?? null, 'the other headers as they were' );
		self::assertArrayNotHasKey( 'Server-Timing', $this->ask( array( 'picks' => array( 'deck' => 1 ) ), 999999999 )->get_headers(), 'an error is not timed' );
		wp_set_current_user( 0 );
	}

	/**
	 * #125: the deck step's phases in the same header, the shelf, the cards and the decks' boards (D-365), so a shop
	 * manager sees where the route's time goes; still no one else is sent them.
	 */
	public function test_a_shop_manager_is_told_the_deck_steps_phases_and_no_one_else_is(): void {
		$query = array(
			'cbuild' => array( 'step' => 'deck' ),
			'page'   => $this->kit,
			'width'  => '8in',
		);
		self::assertArrayNotHasKey( 'Server-Timing', $this->ask( $query )->get_headers(), 'a visitor' );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		self::assertArrayNotHasKey( 'Server-Timing', $this->ask( $query )->get_headers(), 'a customer' );

		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$timing = $this->ask( $query )->get_headers()['Server-Timing'] ?? '';
		self::assertMatchesRegularExpression( '#^cbuild-route;dur=\d+\.\d, cbuild-queries;desc="\d+", cbuild-shelf;dur=\d+\.\d, cbuild-cards;dur=\d+\.\d, cbuild-boards;dur=\d+\.\d$#', $timing );
		preg_match_all( '#cbuild-([a-z]+);dur=([\d.]+)#', $timing, $read );
		$took = array_combine( $read[1], array_map( 'floatval', $read[2] ) );
		// Each phase is this request's alone, inside the route's time: none is carried over from the requests before.
		self::assertLessThanOrEqual( $took['route'] + 0.2, $took['shelf'] + $took['cards'] + $took['boards'], 'the phases are within the route' );
		wp_set_current_user( 0 );
	}

	public function test_the_pictures_description_and_the_status_after_it_are_in_the_language_asked_for(): void {
		add_filter( 'get_available_languages', static fn( array $languages ): array => array_merge( $languages, array( 'fr_CA' ) ) );
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		$french = array(
			'The build so far: %s.' => 'Votre planche jusqu’ici : %s.',
			'%1$s: %2$s'            => '%1$s : %2$s',
			'%1$s Total: %2$s.'     => '%1$s Total : %2$s.',
			'Deck'                  => 'Planche',
			'Trucks'                => 'Trucks',
		);
		add_filter(
			'gettext_creative-skateboard-builder',
			static fn( string $translation, string $text ): string => 'fr_CA' === determine_locale() ? ( $french[ $text ] ?? $translation ) : $translation,
			10,
			2
		);
		add_filter(
			'gettext_with_context_creative-skateboard-builder',
			static fn( string $translation, string $text, string $context ): string => 'fr_CA' === determine_locale() && 'between the parts of a build' === $context ? ' ; ' : $translation,
			10,
			3
		);
		$body = $this->answer(
			array(
				'locale' => 'fr_CA',
				'cbuild' => array(
					'deck'   => (string) $this->parts['deck 8.5'],
					'trucks' => (string) $this->parts['truck 139'],
					'step'   => 'review',
				),
			)
		);
		$description = (string) $body['view']['picture']['description'];
		self::assertMatchesRegularExpression( '/^Votre planche jusqu’ici : \S.* : .+ ; .+ : .+\.$/u', $description, 'the pattern, each part by its slot, and the separator, all in French' );
		self::assertStringStartsWith( $description . ' Total : ', (string) $body['view']['status'], 'the status says the description, then the total, in French' );
	}

	public function test_the_answer_is_in_the_language_asked_for_when_the_site_has_it_and_the_sites_own_otherwise(): void {
		add_filter( 'get_available_languages', static fn( array $languages ): array => array_merge( $languages, array( 'fr_CA' ) ) );
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();

		$during = array();
		$spy    = static function ( string $translation ) use ( &$during ): string {
			$during[ determine_locale() ] = true;
			return $translation;
		};
		add_filter( 'gettext_creative-skateboard-builder', $spy );
		$query = array(
			'picks' => array( 'deck' => $this->parts['deck 8.5'] ),
			'step'  => 'trucks',
		);

		$this->answer( array( 'locale' => 'fr_CA' ) + $query );
		self::assertSame( array( 'fr_CA' ), array_keys( $during ), 'Every sentence of the answer was made in French.' );
		self::assertFalse( is_locale_switched(), 'And the request is handed back in the language it came in.' );
		self::assertSame( 'en_US', determine_locale() );

		// A language the site does not have, and no language at all: the site's own.
		foreach ( array( array( 'locale' => 'tlh' ), array() ) as $asked ) {
			$during = array();
			$this->answer( $asked + $query );
			self::assertSame( array( 'en_US' ), array_keys( $during ) );
			self::assertFalse( is_locale_switched() );
		}

		// A refusal is in the language asked for too, and hands the request back as it came.
		foreach ( array( 400 => array( 'step' => 'sail' ), 404 => array() ) as $status => $asked ) {
			$during = array();
			self::assertSame( $status, $this->ask( array( 'locale' => 'fr_CA' ) + $asked, 404 === $status ? 999999 : null )->get_status() );
			self::assertSame( array( 'fr_CA' ), array_keys( $during ) );
			self::assertFalse( is_locale_switched() );
		}
		remove_filter( 'gettext_creative-skateboard-builder', $spy );
	}

	/**
	 * Set the limit and the header.
	 */
	private function terms( int $rate, string $header = '' ): void {
		$settings = $this->service( ShopSettings::class );
		$settings->save(
			$settings->attributeMapping(),
			$settings->sourceOrder(),
			BuildTerms::read(
				array(
					BuildTerms::RATE  => $rate,
					BuildTerms::PROXY => $header,
				)
			)
		);
	}

	/**
	 * The statuses of so many requests in a row.
	 *
	 * @param array<string, string> $headers The requests' headers.
	 * @return list<int>
	 */
	private function statuses( int $requests, array $headers = array() ): array {
		$statuses = array();
		for ( $request = 1; $request <= $requests; $request++ ) {
			$statuses[] = $this->ask( array(), null, $headers )->get_status();
		}
		return $statuses;
	}

	public function test_an_address_over_the_limit_is_told_to_wait_and_another_address_is_not(): void {
		$this->terms( 10 );

		// The clock is the real one, so a minute may turn during the run: 25 requests cross at most one such turn,
		// and a limit of 10 is passed on one side of it or the other.
		$statuses = $this->statuses( 25 );
		self::assertSame( array_fill( 0, 10, 200 ), array_slice( $statuses, 0, 10 ), 'The first ten are answered.' );
		self::assertContains( 429, array_slice( $statuses, 10, 11 ), 'By the twenty-first at the latest, one is refused.' );

		$refused = $this->ask();
		if ( 429 !== $refused->get_status() ) {
			// The minute turned just now: this one has ten more.
			$this->statuses( 10 );
			$refused = $this->ask();
		}
		self::assertSame( 429, $refused->get_status() );
		$wait = $refused->get_headers()['Retry-After'] ?? null;
		self::assertIsString( $wait );
		self::assertMatchesRegularExpression( '/\A[1-9][0-9]?\z/', $wait );
		self::assertLessThanOrEqual( 60, (int) $wait );
		self::assertSame( 'no-store', $refused->get_headers()['Cache-Control'] ?? null );
		$body = self::body( $refused );
		self::assertSame( array( 'code', 'message', 'data' ), array_keys( $body ), 'The shape of any REST error.' );
		self::assertSame( 'cbuild_rate_limited', $body['code'] );
		self::assertSame( 429, $body['data']['status'] );
		// Spec (configurator): Search, "After a 429 the step keeps its last results and says when to try again."
		self::assertSame( sprintf( 1 === (int) $wait ? 'Too many requests. Try again in %s second.' : 'Too many requests. Try again in %s seconds.', $wait ), $body['message'] );

		$_SERVER['REMOTE_ADDR'] = '203.0.113.8';
		self::assertSame( 200, $this->ask()->get_status() );
	}

	public function test_a_request_over_the_limit_reads_no_kit(): void {
		$this->terms( 10 );
		$this->statuses( 21 );
		$refused = $this->ask( array(), 999999 );
		if ( 429 !== $refused->get_status() ) {
			$this->statuses( 10 );
			$refused = $this->ask( array(), 999999 );
		}
		self::assertSame( 429, $refused->get_status(), 'Refused before the kit is looked for: a 404 would have cost a product read.' );
	}

	public function test_the_address_is_the_connections_own_unless_the_shop_names_a_header(): void {
		$this->terms( 10 );
		// No header is trusted: a visitor who sends one is still counted by the connection.
		$statuses = array();
		for ( $request = 1; $request <= 25; $request++ ) {
			$statuses[] = $this->ask( array(), null, array( 'X-Forwarded-For' => "198.51.100.{$request}" ) )->get_status();
		}
		self::assertContains( 429, $statuses, 'A header nobody trusts gets nobody past the limit.' );

		// The shop names its proxy's header: every request comes from the proxy, and each visitor has a count.
		$this->terms( 10, 'X-Forwarded-For' );
		$_SERVER['REMOTE_ADDR'] = '10.0.0.1';
		$statuses               = array();
		for ( $request = 1; $request <= 25; $request++ ) {
			$statuses[] = $this->ask( array(), null, array( 'X-Forwarded-For' => "192.0.2.1, 198.51.100.{$request}" ) )->get_status();
		}
		self::assertSame( array_fill( 0, 25, 200 ), $statuses );
		self::assertContains( 429, $this->statuses( 25, array( 'X-Forwarded-For' => '192.0.2.77, 198.51.100.200' ) ), 'One visitor behind the proxy, whatever they put first in the list.' );
	}

	// The auditor's should-fix 8, milestone 4: the plan promised a query count on the route, and only the benchmark's
	// time would have noticed a query for each option.
	public function test_the_route_asks_the_same_number_of_queries_however_many_parts_a_step_has(): void {
		$this->terms( 100000 );
		$decks = (int) wp_get_object_terms( $this->parts['deck 8.0'], 'product_cat', array( 'fields' => 'ids' ) )[0];
		$count = function ( bool $cold, array $query = array() ): int {
			// Once for whatever is read the first time a part is met, then the request that is counted.
			$this->ask( $query );
			if ( $cold ) {
				// As on a site with no persistent object cache: nothing of the last request is in memory.
				wp_cache_flush();
			}
			// All but the limit's own count, which is two writes or four by whether its row's end moved a second on.
			$asked = 0;
			$watch = static function ( string $query ) use ( &$asked ): string {
				if ( ! str_contains( $query, 'cbuild_rate_' ) ) {
					++$asked;
				}
				return $query;
			};
			add_filter( 'query', $watch );
			self::assertSame( 200, $this->ask( $query )->get_status() );
			remove_filter( 'query', $watch );
			return $asked;
		};
		// What the store sends on every move: the build's own fields and the page, which add the view, its brand
		// lookup, the presets and the page's blocks (audit 13, milestone 6).
		$moved = fn( bool $cold ): int => $count(
			$cold,
			array(
				'cbuild' => array( 'step' => 'deck' ),
				'page'   => $this->kit,
			)
		);
		// Each deck with a layer image of its own, so the deck step draws a board for each card it lists (D-308): the
		// interim audit of milestone 11 found the decks here had none, so that path never ran.
		$layered = static function ( int $deck ): void {
			$product = wc_get_product( $deck );
			$product->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 54, 204 ) );
			$product->save();
		};
		// The saves' own request ends, and forgets the cards they moved, before a shopper's comes: in one process, the
		// first read of a card would forget them, and the looks read beside it, part way through the request counted.
		$settled = function (): void {
			$cards = $this->service( \CreativeBranding\SkateboardBuilder\Port\PartCards::class );
			self::assertInstanceOf( \CreativeBranding\SkateboardBuilder\Adapter\WooPartCards::class, $cards );
			$cards->settle();
		};
		$more = function ( int $upTo ) use ( $decks, $layered, $settled ): void {
			for ( $at = count( $this->parts ); $at < $upTo; $at++ ) {
				$this->parts[ 'deck ' . $at ] = $this->part( 'Deck ' . $at, '65', array( 'deck_width' => '8.2500' ), $decks );
				$layered( $this->parts[ 'deck ' . $at ] );
				// Each with a review, so each card has a rating, read with the products the cards already load (#137).
				self::review( $this->parts[ 'deck ' . $at ], 4, $at );
			}
			$settled();
		};
		$layered( $this->parts['deck 8.0'] );
		$layered( $this->parts['deck 8.5'] );
		// A deck made in two widths, picked in 8.0: asking for 8.25 moves it to its 8.25 (#81), which builds a second
		// shelf and a second state (the auditor's 1.1.0 minor 6).
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Two widths' );
		$parent->set_status( 'publish' );
		$parent->save();
		// One board for both widths, on the parent: the two are one look, as variations sharing a picture are.
		$layered( $parent->get_id() );
		wp_set_object_terms( $parent->get_id(), array( $decks ), 'product_cat' );
		$widths = array();
		foreach ( array( '8.0000', '8.2500' ) as $width ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_status( 'publish' );
			$variation->set_regular_price( '65' );
			$variation->set_attributes( array( 'width' => $width ) );
			$variation->update_meta_data( Options::META_SPECS, array( 'deck_width' => $width ) );
			$variation->save();
			$this->service( SpecIndexer::class )->index( $variation->get_id() );
			$widths[ $width ] = $variation->get_id();
		}
		$widen   = array(
			'cbuild' => array(
				'deck'   => (string) $widths['8.0000'],
				'step'   => 'deck',
				'_width' => '8.25in',
			),
			'page'   => $this->kit,
		);
		$widened = fn( bool $cold ): int => $count( $cold, $widen );

		// Fewer decks than a step lists, so each one added is another card and another board.
		$more( 10 );
		self::assertSame( array( $widths['8.2500'] ), array_column( array_filter( $this->answer( $widen )['steps'], static fn( array $step ): bool => 'deck' === $step['slot'] ), 'picked' ), 'the width moves the deck' );
		$few = array( $count( false ), $count( true ), $moved( false ), $moved( true ), $widened( false ), $widened( true ) );
		self::assertNotNull( $this->answer()['options'][0]['board'] ?? null, 'a deck\'s card draws its board' );
		$rated = array_filter( array_column( array_column( array_column( $this->answer( array( 'width' => '8.25in' ) )['shelf']['looks'], 'part' ), 'card' ), 'rating' ) );
		self::assertNotSame( array(), $rated, 'the cards counted have their ratings' );

		// More parts than a step lists, and then twice as many.
		$more( 30 );
		$warm      = $count( false );
		$cold      = $count( true );
		$movedWarm = $moved( false );
		$movedCold = $moved( true );
		$widenWarm = $widened( false );
		$widenCold = $widened( true );
		// A step lists 30 looks at most, so past that the boards drawn stop growing: it is eight decks against thirty
		// that shows a query for each board.
		self::assertSame( $few, array( $warm, $cold, $movedWarm, $movedCold, $widenWarm, $widenCold ), 'eight decks or thirty, each with its board' );
		$more( 60 );
		self::assertSame( $warm, $count( false ), 'warm' );
		self::assertSame( $cold, $count( true ), 'cold' );
		self::assertSame( $movedWarm, $moved( false ), 'a move, warm' );
		self::assertSame( $movedCold, $moved( true ), 'a move, cold' );
		self::assertSame( $widenWarm, $widened( false ), 'a deck moved to a new width, warm' );
		self::assertSame( $widenCold, $widened( true ), 'a deck moved to a new width, cold' );
		self::assertLessThanOrEqual( 15, $warm, 'warm' );
		self::assertLessThanOrEqual( 60, $cold, 'cold' );
		self::assertLessThanOrEqual( 15, $movedWarm, 'a move, warm' );
		self::assertLessThanOrEqual( 60, $movedCold, 'a move, cold' );

		// The review with every slot picked, where smart swaps are worked out (the auditor's 1.3.0 should-fix 2 and 7):
		// the wheels' parts to try grow, some ranked, and the review asks as many queries at each size, warm and cold.
		$whole     = $this->whole();
		$this->kit = $whole['kit'];
		$review    = array( 'cbuild' => array_map( 'strval', $whole['picks'] ) + array( 'step' => 'review' ) );
		// Each size asked once before it is counted: the parts just saved move WooCommerce's term counts, whose transient
		// the first cold request after writes with its timeout, two queries that are WooCommerce's and not the review's.
		$reviewed  = function ( bool $cold ) use ( $count, $review ): int {
			$count( $cold, $review );
			return $count( $cold, $review );
		};
		$added     = 0;
		$wheels    = function ( int $upTo ) use ( &$added, $settled ): void {
			for ( ; $added < $upTo; $added++ ) {
				// For less and dearer than the 10 picked, every third ranked.
				$wheel = $this->wheels( 'Wheels ' . $added, 0 === $added % 2 ? '8' : '12' );
				if ( 0 === $added % 3 ) {
					$product = wc_get_product( $wheel );
					$product->update_meta_data( Options::META_RANK, (string) ( $added + 1 ) );
					$product->save();
				}
			}
			$settled();
		};
		$wheels( 3 );
		self::assertNotSame( array(), $this->answer( $review )['swaps'], 'the review offers swaps' );
		$sizes = array( $reviewed( false ), $reviewed( true ) );
		$wheels( 15 );
		self::assertSame( $sizes, array( $reviewed( false ), $reviewed( true ) ), 'the review, 4 wheels or 16' );
		$wheels( 45 );
		self::assertSame( $sizes, array( $reviewed( false ), $reviewed( true ) ), 'the review, 4 wheels or 46' );
	}

	/**
	 * A guest on a shop that geolocates its customers, with prices shown by address: what the route costs beside
	 * WooCommerce's own lookup (D-139, D-164). WooCommerce geolocates a shopper from MaxMind's database, through
	 * `woocommerce_geolocate_ip`, or a CDN's country header, never from a country service
	 * (`wc_get_customer_geolocation()` asks with no API fallback). Without either, it asks services for the server's
	 * own public address, one after another with 2 seconds each, and keeps the answer a day for that guest's address.
	 * Those services are answered here, with no network.
	 */
	public function test_a_guests_geolocation_is_woocommerces_own_lookup_once_and_kept_after(): void {
		$options = array( 'woocommerce_calc_taxes', 'woocommerce_tax_display_shop', 'woocommerce_default_customer_address' );
		$was     = array_map( 'get_option', array_combine( $options, $options ) );
		$wc      = array( WC()->customer, WC()->session );
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_default_customer_address', 'geolocation' );
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		$asked                  = array();
		$answer                 = static function ( $preempt, array $args, string $url ) use ( &$asked ): array {
			$asked[] = (string) wp_parse_url( $url, PHP_URL_HOST );
			return array(
				'headers'  => array(),
				'body'     => '198.51.100.20',
				'response' => array(
					'code'    => 200,
					'message' => 'OK',
				),
				'cookies'  => array(),
			);
		};
		add_filter( 'pre_http_request', $answer, 10, 3 );
		// Each ask a request of its own: no customer or session carried from the last.
		$fresh = static function (): void {
			WC()->customer = null;
			WC()->session  = null;
		};
		$timed = function () use ( $fresh ): float {
			$fresh();
			$started = hrtime( true );
			self::assertSame( 200, $this->ask()->get_status() );
			return ( hrtime( true ) - $started ) / 1e6;
		};
		$services = array( 'api64.ipify.org', 'ipecho.net', 'ident.me', 'tnedi.me' );
		try {
			// Prices shown without tax go by no address: nobody is looked up.
			update_option( 'woocommerce_tax_display_shop', 'excl' );
			$timed();
			self::assertSame( array(), $asked, 'no address needed, no lookup' );

			// Shown with tax, and no MaxMind database: the first request asks one service, as any page would.
			update_option( 'woocommerce_tax_display_shop', 'incl' );
			delete_transient( 'external_ip_address_203.0.113.7' );
			$first = $timed();
			self::assertCount( 1, $asked );
			self::assertContains( $asked[0], $services );

			$asked = array();
			$next  = $timed();
			self::assertSame( array(), $asked, "the next: the address WooCommerce kept for this guest's" );

			// With MaxMind's database, which answers WooCommerce's own filter: nothing goes out.
			delete_transient( 'external_ip_address_203.0.113.7' );
			$maxmind = static fn (): string => 'CA';
			add_filter( 'woocommerce_geolocate_ip', $maxmind );
			$local = $timed();
			remove_filter( 'woocommerce_geolocate_ip', $maxmind );
			self::assertSame( array(), $asked, 'MaxMind: no request' );
			fwrite( STDERR, sprintf( "\nGeolocation: a guest's first request %.1f ms with the service answering at once, the next %.1f ms, with MaxMind %.1f ms.\n", $first, $next, $local ) );
		} finally {
			remove_filter( 'pre_http_request', $answer, 10 );
			delete_transient( 'external_ip_address_203.0.113.7' );
			foreach ( $was as $option => $value ) {
				update_option( $option, $value );
			}
			// The tests after this one find WooCommerce's customer and session as they were.
			[ WC()->customer, WC()->session ] = $wc;
		}
	}

	public function test_nothing_kept_for_the_limit_names_an_address(): void {
		global $wpdb;
		$this->terms( 10 );
		$this->statuses( 3 );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- the test reads what the route left behind.
		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s OR option_value LIKE %s", '%203.0.113.7%', '%203.0.113.7%' ) );
		self::assertSame( array(), $rows, 'No option, transient or setting holds the address.' );

		if ( ! wp_using_ext_object_cache() ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- as above.
			$kept = $wpdb->get_col( $wpdb->prepare( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s", $wpdb->esc_like( '_transient_cbuild_rate_' ) . '%' ) );
			self::assertNotSame( array(), $kept );
			$counts = array();
			foreach ( $kept as $name ) {
				// One of a fixed number of rows (D-119). What it holds is a count and its end for each key, and a key
				// is 40 characters of a keyed hash.
				self::assertMatchesRegularExpression( '/\A_transient_cbuild_rate_[0-9]{1,4}\z/', $name );
				$held = get_transient( substr( $name, strlen( '_transient_' ) ) );
				self::assertIsArray( $held );
				foreach ( $held as $key => $entry ) {
					self::assertMatchesRegularExpression( '/\A[0-9a-f]{40}\z/', (string) $key );
					self::assertTrue( is_int( $entry[0] ) && is_int( $entry[1] ) );
					$counts[] = $entry[0];
				}
				$expires = (int) get_option( '_transient_timeout_' . substr( $name, strlen( '_transient_' ) ) );
				self::assertLessThanOrEqual( time() + 120, $expires, 'Gone within two minutes.' );
				self::assertGreaterThan( time(), $expires );
			}
			// The three requests are counted under the minute each was made in (Service\RateLimit), and the clock is the
			// real one: a minute that turns between them splits them over two keys, 2 and 1 (CI run 44174950072,
			// hpos-off). Together the counts are the three requests, every one.
			self::assertSame( 3, array_sum( $counts ) );
			self::assertLessThanOrEqual( 2, count( $counts ), 'One minute, or two when one turns between the requests.' );
		}
	}

	public function test_a_plugin_that_answers_for_the_owners_words_changes_what_the_route_says(): void {
		// A Street with every part its slot's, so the answer offers it (#104).
		$this->kit = $this->whole()['kit'];
		$plain     = $this->answer();
		self::assertSame( 'Trucks', $plain['steps'][1]['label'] );
		self::assertSame( 'Sold in pairs.', $plain['steps'][1]['help'] );
		self::assertSame( 'Street', $plain['presets'][0]['name'] );
		self::assertSame( 'For ledges.', $plain['presets'][0]['description'] );
		self::assertSame( 'Two working days.', $plain['texts']['lead_time'] );
		self::assertSame( 'An assembled board goes back whole.', $plain['texts']['returns_notice'] );

		$kit = $this->kit;
		add_filter( 'cbuild_slot_label', static fn( string $label, string $slot, int $id ): string => 'trucks' === $slot && $kit === $id ? 'Trucks (paire)' : $label, 10, 3 );
		add_filter( 'cbuild_preset_label', static fn( string $name, int $position, int $id ): string => 0 === $position && $kit === $id ? 'Rue' : $name, 10, 3 );
		add_filter(
			'cbuild_kit_text',
			static fn( string $text, string $field, int $id, string $of ): string => array(
				'slot_help trucks'     => 'Vendus en paires.',
				'preset_description 0' => 'Pour les rebords.',
				'lead_time '           => 'Deux jours ouvrables.',
				'returns_notice '      => 'Une planche assemblée revient entière.',
			)[ "{$field} {$of}" ] ?? $text,
			10,
			4
		);

		$said = $this->answer();
		self::assertSame( 'Deck', $said['steps'][0]['label'], 'A label the plugin did not answer for is the owner\'s.' );
		self::assertSame( 'Trucks (paire)', $said['steps'][1]['label'] );
		self::assertSame( 'Vendus en paires.', $said['steps'][1]['help'] );
		self::assertSame( 'Rue', $said['presets'][0]['name'] );
		self::assertSame( 'Pour les rebords.', $said['presets'][0]['description'] );
		self::assertSame( 'Deux jours ouvrables.', $said['texts']['lead_time'] );
		self::assertSame( 'Une planche assemblée revient entière.', $said['texts']['returns_notice'] );
		self::assertContains( 'Trucks (paire): nothing is chosen yet.', array_column( $said['problems'], 'message' ) );
	}

	/**
	 * #104 (R1), on the route: a ready build with a part that's gone, sold out or no longer fits is listed in the
	 * answer's presets as not shown, and left out of the view's section; a kit with Show ready builds off shows none; a
	 * kit saved before 1.1.0 shows its whole ones (D-336). A link with `cbuild[preset]` still opens each, and says what is missing (F6).
	 */
	public function test_the_route_offers_whole_ready_builds_alone_and_none_with_show_ready_builds_off(): void {
		$start = array( 'step' => 'deck' );

		// Set up's Street names the 8.0 deck in every other slot, which none of them offers.
		$gone = $this->answer( array( 'cbuild' => $start ) );
		self::assertSame( array( false ), array_column( $gone['presets'], 'shown' ) );
		self::assertFalse( $gone['view']['presets']['shown'] );
		$street = array(
			'deck'   => $this->parts['deck 8.0'],
			'trucks' => $this->parts['truck 139'],
		) + array_fill_keys( array( 'wheels', 'bearings', 'hardware', 'grip' ), $this->parts['deck 8.0'] );
		$opened = $this->answer( array( 'cbuild' => array_map( 'strval', $street ) + array( 'step' => 'review', 'preset' => '0' ) ) );
		self::assertSame( 'part_not_in_slot', $opened['refused'][0]['code'] ?? null, 'the link still opens it' );
		self::assertMatchesRegularExpression( '/\AWheels: .+ is no longer offered here\.\z/', (string) ( $opened['refused'][0]['message'] ?? '' ), 'named as its tile named it' );

		// A whole Street on a kit saved before 1.1.0, with no show_presets: its tile, at its place among the kit's.
		$whole     = $this->whole( array( 'schema_version' => 1, 'stage_side' => 'right' ) );
		$this->kit = $whole['kit'];
		$shown     = $this->answer( array( 'cbuild' => $start ) );
		self::assertSame( array( 'Street' ), array_column( $shown['presets'], 'name' ) );
		self::assertSame( array( true ), array_column( $shown['presets'], 'shown' ) );
		self::assertTrue( $shown['presets'][0]['ready'] );
		self::assertTrue( $shown['view']['presets']['shown'] );

		// Its grip sold out: left out, and the Catalogue check lists it.
		$grip = wc_get_product( $whole['picks']['grip'] );
		$grip->set_stock_status( 'outofstock' );
		$grip->save();
		$sold = $this->answer( array( 'cbuild' => $start ) );
		self::assertSame( array( false ), array_column( $sold['presets'], 'shown' ) );
		self::assertFalse( $sold['presets'][0]['ready'] );
		self::assertFalse( $sold['view']['presets']['shown'] );
		$lines = array_map( static fn( CheckFinding $finding ): string => $finding->code . ' ' . $finding->part . ' ' . ( $finding->values['problem'] ?? '' ), $this->service( CatalogueCheck::class )->run() );
		self::assertContains( CatalogueCheck::PRESET . ' ' . $whole['picks']['grip'] . ' part_sold_out', $lines );

		// Show ready builds off: none, and the ready build kept, which its link opens with nothing missing.
		$off       = $this->whole( array( 'schema_version' => 1, 'show_presets' => false ) );
		$this->kit = $off['kit'];
		$hidden    = $this->answer( array( 'cbuild' => $start ) );
		self::assertSame( array( false ), array_column( $hidden['presets'], 'shown' ) );
		self::assertTrue( $hidden['presets'][0]['ready'] );
		self::assertFalse( $hidden['view']['presets']['shown'] );
		$review = $this->answer( array( 'cbuild' => array_map( 'strval', $off['picks'] ) + array( 'step' => 'review', 'preset' => '0' ) ) );
		self::assertSame( 'review', $review['step'] );
		self::assertSame( array(), $review['refused'] );
		self::assertTrue( $review['ready'] );
	}

	/**
	 * The interim audit of milestone 11, should-fix 1, and the final audit's should-fix 2: a minor release only gains
	 * fields, so the answer lists every ready build, `presets[i]` is the preset `cbuild[preset]=i` opens and says so in
	 * `at`, and a broken one says it is not `shown` (#104, D-336). The view has a tile for the shown ones alone.
	 */
	public function test_each_ready_build_offered_says_its_place_among_the_kits(): void {
		$whole     = $this->whole(
			array(
				'schema_version' => 1,
				'stage_side'     => 'right',
			),
			fn( array $picks ): array => array(
				array(
					'name'        => 'Street',
					'description' => 'For ledges.',
					'picks'       => $picks,
				),
				// The 8.0 deck in every slot but the deck's and the trucks': none of them offers it.
				array(
					'name'        => 'Broken',
					'description' => '',
					'picks'       => array(
						'deck'   => $this->parts['deck 8.0'],
						'trucks' => $this->parts['truck 139'],
					) + array_fill_keys( array( 'wheels', 'bearings', 'hardware', 'grip' ), $this->parts['deck 8.0'] ),
				),
				array(
					'name'        => 'Park',
					'description' => '',
					'picks'       => $picks,
				),
			)
		);
		$this->kit = $whole['kit'];
		$shown     = $this->answer( array( 'cbuild' => array( 'step' => 'deck' ) ) );
		self::assertCount( 3, $shown['presets'], 'each of the kit\'s ready builds' );
		self::assertSame( array( 'Street', 'Broken', 'Park' ), array_column( $shown['presets'], 'name' ) );
		self::assertSame( array( 0, 1, 2 ), array_column( $shown['presets'], 'at' ), 'presets[i] is preset i' );
		self::assertSame( array( true, false, true ), array_column( $shown['presets'], 'shown' ) );
		self::assertSame( array( 'Start from Street', 'Start from Park' ), array_column( $shown['view']['presets']['tiles'], 'button' ), 'no tile for the broken one' );
		$fields = array_map( static fn( array $tile ): array => array_column( $tile['fields'], 'value', 'name' ), $shown['view']['presets']['tiles'] );
		self::assertSame( array( '0', '2' ), array_column( $fields, BuildLink::PARAM . '[preset]' ), 'each tile opens its own' );
	}

	public function test_a_plugin_that_rules_a_part_out_takes_it_from_the_route(): void {
		$query = array(
			'picks' => array( 'deck' => $this->parts['deck 8.5'] ),
			'step'  => 'trucks',
		);
		$with  = $this->answer( $query );
		self::assertSame( array( $this->parts['truck 149'] ), array_column( $with['options'], 'id' ) );
		self::assertSame( $this->parts['truck 149'], $with['steps'][1]['recommended'] );

		$out   = $this->parts['truck 149'];
		$asked = array();
		add_filter(
			'cbuild_part_available',
			static function ( bool $available, int $id, $product ) use ( $out, &$asked ): bool {
				$asked[ $id ] = $product instanceof \WC_Product && $id === $product->get_id();
				return $out === $id ? false : $available;
			},
			10,
			3
		);

		// A slot's parts are kept for an hour (D-116), so a plugin whose answer changes says so.
		self::assertSame( $with, $this->answer( $query ) );
		self::assertSame( array(), $asked );
		do_action( 'cbuild_parts_changed' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own prefix.

		$without = $this->answer( $query );
		self::assertSame( array(), $without['options'] );
		self::assertNull( $without['steps'][1]['recommended'] );
		self::assertSame( 0, $without['steps'][1]['offered'] );
		self::assertSame( array( $this->parts['truck 139'] ), array_column( $without['hidden'], 'id' ), 'The other trucks are still the slot\'s.' );
		self::assertSame( array( $this->parts['deck 8.0'], $this->parts['deck 8.5'] ), array_column( $this->answer()['options'], 'id' ) );
		self::assertTrue( $asked[ $out ], 'Asked with the part\'s ID and its product.' );

		// Picked all the same, it is refused as any product that is not the slot's.
		$picked = $this->answer( array( 'picks' => array( 'trucks' => $out ) ) );
		self::assertSame( 'part_not_in_slot', $picked['refused'][0]['code'] );
	}

	// Issue #116: a part ticked Not offered in the builder leaves the route as a plugin's false does, and a link or a
	// saved build that names it is refused as any part that is not the slot's.
	public function test_a_part_the_shop_keeps_out_is_taken_from_the_route_and_refused_when_picked(): void {
		$query = array(
			'picks' => array( 'deck' => $this->parts['deck 8.5'] ),
			'step'  => 'trucks',
		);
		$out   = $this->parts['truck 149'];
		self::assertSame( array( $out ), array_column( $this->answer( $query )['options'], 'id' ) );

		$truck = wc_get_product( $out );
		$truck->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$truck->save();
		do_action( 'cbuild_parts_changed', array( $out ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own prefix.

		self::assertSame( array(), $this->answer( $query )['options'], 'kept out of the slot' );
		$picked = $this->answer( array( 'picks' => array( 'deck' => $this->parts['deck 8.5'], 'trucks' => $out ) ) );
		self::assertSame( 'part_not_in_slot', $picked['refused'][0]['code'], 'and refused when a link names it' );
	}

	public function test_a_plugin_that_prices_some_shoppers_apart_says_so_and_each_gets_their_own_card(): void {
		$deck  = $this->parts['deck 8.0'];
		$price = fn(): string => $this->answer()['options'][0]['card']['price'];
		self::assertSame( '$60.00', $price() );

		// A dealer's price, from a plugin that says nothing of it to the builder: the card kept is the one shown.
		$dealer  = false;
		$cheaper = static function ( $amount, $product ) use ( &$dealer, $deck ) {
			return $dealer && $deck === $product->get_id() ? '45' : $amount;
		};
		add_filter( 'woocommerce_product_get_price', $cheaper, 10, 2 );
		$dealer = true;
		self::assertSame( '$60.00', $price(), 'Kept: nothing told the builder this shopper is priced apart.' );

		// The plugin adds what tells its shoppers apart, and each kind has a card of its own.
		add_filter(
			'cbuild_price_context',
			static function ( array $context ) use ( &$dealer ): array {
				$context[] = $dealer ? 'dealer' : 'retail';
				return $context;
			}
		);
		self::assertSame( '$45.00', $price() );
		$dealer = false;
		self::assertSame( '$60.00', $price() );
		$dealer = true;
		self::assertSame( '$45.00', $price() );
	}

	/**
	 * A review of a product, as a shopper leaves one, and WooCommerce's numbers worked out again from its reviews: the
	 * rating's meta is added after `wp_insert_comment()` counts the post's comments (wp-includes/comment.php:2206,
	 * :2220), so the count is asked for again, as WooCommerce's own review form has it done.
	 */
	private static function review( int $product, int $rating, int $shopper ): void {
		$comment = wp_insert_comment(
			array(
				'comment_post_ID'      => $product,
				'comment_type'         => 'review',
				'comment_approved'     => 1,
				'comment_author'       => 'Shopper ' . $shopper,
				'comment_author_email' => 'shopper+' . $shopper . '@example.com',
				'comment_content'      => 'Rides well.',
				'comment_meta'         => array( 'rating' => $rating ),
			)
		);
		self::assertIsInt( $comment );
		\WC_Comments::clear_transients( $product );
	}

	/**
	 * #137 (D-392): a part's card has its star rating and review count from WooCommerce's product API, which Creative
	 * Reviews keeps right for its reviews, so it shows with that plugin or without it. None where the shop shows no
	 * ratings (`wc_review_ratings_enabled()`, includes/wc-conditional-functions.php:531) or the part has no reviews. A
	 * new review moves it, kept card and all, and a variation has its product's.
	 */
	public function test_a_card_has_the_rating_the_shop_shows_and_none_where_it_shows_none(): void {
		$options = array( 'woocommerce_enable_reviews', 'woocommerce_enable_review_rating' );
		$was     = array_map( 'get_option', array_combine( $options, $options ) );
		update_option( 'woocommerce_enable_reviews', 'yes' );
		update_option( 'woocommerce_enable_review_rating', 'yes' );
		$whole     = $this->whole();
		$this->kit = $whole['kit'];
		self::review( $this->parts['truck 139'], 5, 1 );
		self::review( $this->parts['truck 139'], 4, 2 );
		$trucks = function (): array {
			$cards  = array();
			$answer = $this->answer(
				array(
					'picks' => array( 'deck' => $this->parts['deck 8.0'] ),
					'step'  => 'trucks',
				)
			);
			// The 149s do not fit the 8.0 deck, and are listed as hidden, with their cards.
			foreach ( array_merge( $answer['options'], $answer['hidden'] ) as $option ) {
				$cards[ $option['id'] ] = $option['card']['rating'];
			}
			return $cards;
		};
		self::assertSame(
			array(
				'average' => 4.5,
				'count'   => 2,
				'text'    => '4.5 out of 5, 2 reviews',
			),
			$trucks()[ $this->parts['truck 139'] ]
		);
		self::assertNull( $trucks()[ $this->parts['truck 149'] ], 'a part with no reviews' );

		// The shop's ratings off, then its reviews: none, though the card is kept.
		update_option( 'woocommerce_enable_review_rating', 'no' );
		self::assertNull( $trucks()[ $this->parts['truck 139'] ], 'ratings off' );
		update_option( 'woocommerce_enable_review_rating', 'yes' );
		update_option( 'woocommerce_enable_reviews', 'no' );
		self::assertNull( $trucks()[ $this->parts['truck 139'] ], 'reviews off' );
		update_option( 'woocommerce_enable_reviews', 'yes' );

		// A third review: the kept card is read again with the product it was saved with.
		self::review( $this->parts['truck 139'], 3, 3 );
		self::assertSame( '4 out of 5, 3 reviews', $trucks()[ $this->parts['truck 139'] ]['text'] ?? null );

		// A deck sold in widths: reviewed as one product, each width has its rating.
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Reviewed widths' );
		$parent->set_status( 'publish' );
		$parent->save();
		wp_set_object_terms( $parent->get_id(), array( $this->categories['deck'] ), 'product_cat' );
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_status( 'publish' );
		$variation->set_regular_price( '65' );
		$variation->set_attributes( array( 'width' => '8.0000' ) );
		$variation->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.0000' ) );
		$variation->save();
		$this->service( SpecIndexer::class )->index( $variation->get_id() );
		self::review( $parent->get_id(), 4, 4 );
		$decks = array();
		foreach ( $this->answer( array( 'width' => '8in' ) )['shelf']['looks'] as $look ) {
			$decks[ $look['part']['id'] ] = $look['part']['card']['rating'];
		}
		self::assertSame( '4 out of 5, 1 review', $decks[ $variation->get_id() ]['text'] ?? null, 'its product\'s' );

		foreach ( $was as $option => $value ) {
			update_option( $option, $value );
		}
	}

	/**
	 * #137 (D-392): each option names the ready builds shown that pick it, by their places among the kit's, and the
	 * view's card says so in words; none where the kit's ready builds are not shown (#104).
	 */
	public function test_an_option_names_the_ready_builds_shown_that_pick_it(): void {
		$whole     = $this->whole(
			null,
			static fn( array $picks ): array => array(
				array(
					'name'  => 'Street',
					'picks' => $picks,
				),
				array(
					'name'  => 'Ledge',
					'picks' => array( 'trucks' => $picks['trucks'] ) + $picks,
				),
			)
		);
		$this->kit = $whole['kit'];
		$build     = array(
			'deck' => (string) $whole['picks']['deck'],
			'step' => 'trucks',
		);
		$answer    = $this->answer( array( 'cbuild' => $build ) );
		$named     = array_column( array_merge( $answer['options'], $answer['hidden'] ), 'presets', 'id' );
		self::assertSame( array( 0, 1 ), $named[ $this->parts['truck 139'] ] );
		self::assertSame( array(), $named[ $this->parts['truck 149'] ], 'hidden, and no ready build picks it' );
		$card = array_column( $answer['view']['step']['options'], null, 'id' )[ $this->parts['truck 139'] ];
		self::assertSame( array( 'Picked for Street and Ledge', true ), array( $card['pickedFor'], $card['hasPickedFor'] ) );

		$off       = $this->whole( array( 'schema_version' => 1, 'show_presets' => false ) );
		$this->kit = $off['kit'];
		$hidden    = $this->answer(
			array(
				'cbuild' => array(
					'deck' => (string) $off['picks']['deck'],
					'step' => 'trucks',
				),
			)
		);
		self::assertSame( array( array() ), array_values( array_unique( array_column( array_merge( $hidden['options'], $hidden['hidden'] ), 'presets' ), SORT_REGULAR ) ) );
		self::assertFalse( array_column( $hidden['view']['step']['options'], null, 'id' )[ $this->parts['truck 139'] ]['hasPickedFor'] );
	}

	/**
	 * D-409, wired to #137's cards: the kit's Ready-build picks on cards and Ratings on cards. Picks off, no card says
	 * "Picked for"; ratings off, or WooCommerce's star ratings off, no card has a star or a score. Each is its own: one
	 * off leaves the other. A kit saved before 1.2.0, or with 1.2.0's settings and none of the keys, shows both. The
	 * view says so for the page and the state route alike, so the card's line is hidden, not drawn empty.
	 */
	public function test_the_kits_switches_take_the_cards_picks_and_ratings_away_and_a_kit_without_them_shows_both(): void {
		$options = array( 'woocommerce_enable_reviews', 'woocommerce_enable_review_rating' );
		$was     = array_map( 'get_option', array_combine( $options, $options ) );
		update_option( 'woocommerce_enable_reviews', 'yes' );
		update_option( 'woocommerce_enable_review_rating', 'yes' );
		self::review( $this->parts['truck 139'], 5, 1 );
		self::review( $this->parts['truck 139'], 4, 2 );
		$card = function ( ?array $settings ): array {
			$whole     = $this->whole(
				null,
				static fn( array $picks ): array => array(
					array(
						'name'  => 'Street',
						'picks' => $picks,
					),
				),
				$settings
			);
			$this->kit = $whole['kit'];
			$answer    = $this->answer(
				array(
					'cbuild' => array(
						'deck' => (string) $whole['picks']['deck'],
						'step' => 'trucks',
					),
				)
			);
			$card = array_column( $answer['view']['step']['options'], null, 'id' )[ $this->parts['truck 139'] ];
			return array(
				'picks'  => array( $card['pickedFor'], $card['hasPickedFor'] ),
				'rating' => array( $card['score'], $card['rated'], $card['hasRating'] ),
			);
		};
		$picks  = array( 'Picked for Street', true );
		$rating = array( '4.5 (2)', '4.5 out of 5, 2 reviews', true );
		$none   = array( '', '', false );
		foreach ( array(
			'a kit saved before 1.2.0'               => null,
			'1.2.0\'s settings, none of the keys'    => array(
				'schema_version' => 1,
				'start_button'   => true,
			),
			'each said on'                           => array(
				'schema_version' => 1,
				'picks'          => true,
				'ratings'        => true,
			),
		) as $case => $settings ) {
			self::assertSame(
				array(
					'picks'  => $picks,
					'rating' => $rating,
				),
				$card( $settings ),
				$case
			);
		}
		self::assertSame(
			array(
				'picks'  => array( '', false ),
				'rating' => $rating,
			),
			$card(
				array(
					'schema_version' => 1,
					'picks'          => false,
				)
			),
			'picks off: no line, and the rating stays'
		);
		self::assertSame(
			array(
				'picks'  => $picks,
				'rating' => $none,
			),
			$card(
				array(
					'schema_version' => 1,
					'ratings'        => false,
				)
			),
			'ratings off: no star or score, and the picks stay'
		);
		update_option( 'woocommerce_enable_review_rating', 'no' );
		self::assertSame(
			array(
				'picks'  => $picks,
				'rating' => $none,
			),
			$card( null ),
			'the kit\'s on, WooCommerce\'s star ratings off'
		);

		foreach ( $was as $option => $value ) {
			update_option( $option, $value );
		}
	}

	// #139, D-470: the view carries the 3D view's scene only where the kit's 3D view is on, measured by the domain from
	// a deck whose specs no deck has, and held to the schema's limits; a plugin's answer that is no scene is passed over.
	public function test_the_view_carries_the_scene_only_where_the_kits_3d_view_is_on_and_a_hostile_deck_is_held_to_its_limits(): void {
		$scene = function ( ?array $settings, ?int $deck = null ): array {
			$whole     = $this->whole( null, null, $settings );
			$this->kit = $whole['kit'];
			$picks     = array( 'step' => 'review' ) + array_map( 'strval', ( null === $deck ? array() : array( 'deck' => $deck ) ) + $whole['picks'] );
			return $this->answer( array( 'cbuild' => $picks ) )['view'];
		};
		foreach ( array(
			'a kit saved before 1.4.0' => null,
			'1.3.0\'s settings'        => array( 'schema_version' => 1, 'ratings' => true ),
			'the 3D view said off'     => array( 'schema_version' => 1, 'view_3d' => false ),
		) as $case => $settings ) {
			self::assertArrayNotHasKey( 'scene', $scene( $settings ), $case );
		}

		$hostile = $this->part(
			'Deck with specs no deck has',
			'60',
			array(
				'deck_width'    => '8.0000',
				'deck_length'   => '20.0000',
				'wheelbase'     => '14.0000',
				'nose_length'   => '40.0000',
				'concave_depth' => '9.0000',
				'kick_angle'    => '900',
			),
			$this->categories['deck']
		);
		$on   = array( 'schema_version' => 1, 'view_3d' => true );
		$deck = $scene( $on, $hostile )['scene']['deck'];
		$span = $deck['holes']['points'][0][0] - $deck['holes']['points'][7][0];
		self::assertSame( array( $hostile, 508000 ), array( $deck['part'], $deck['length'] ) );
		self::assertSame( $deck['length'], $deck['nose'] + $deck['tail'] + $span, 'no nose or tail past what the length leaves' );
		self::assertSame( array( 0, 450 ), array( $deck['tail'], $deck['kick'] ), 'the nose takes what is left; the kick held at 45°' );
		self::assertSame( intdiv( $deck['width'], 4 ), $deck['concave'], 'the concave held at a quarter of the width' );

		$view = $scene( $on );
		self::assertSame( $this->parts['deck 8.0'], $view['scene']['deck']['part'] );
		self::assertSame( -( 11000 + $view['scene']['trucks']['height'] ), $view['scene']['wheels']['places'][0][2], 'no risers: the axle under the deck' );
		self::assertSame( $view['scene']['wheels']['places'][0][2], $view['scene']['trucks']['centres'][0][2], 'the trucks\' axle is the wheels\' (F9)' );
		self::assertContains( 'nose_length', $view['scene']['assumed'] );
		add_filter( 'cbuild_scene', static fn(): string => 'no scene' );
		$kept = $scene( $on )['scene'];
		self::assertSame( array( $view['scene']['deck'], $view['scene']['trucks'] ), array( $kept['deck'], $kept['trucks'] ), 'the domain\'s kept' );
	}

	/**
	 * A set of wheels in the whole kit's wheels category, indexed.
	 *
	 * @param string $name     Its name.
	 * @param string $price    Its price, as the shop enters it.
	 * @param string $diameter Its diameter in millimetres.
	 */
	private function wheels( string $name, string $price, string $diameter = '52.0000' ): int {
		return $this->part(
			$name,
			$price,
			array(
				'wheel_diameter' => $diameter,
				'set_size'       => '4',
			),
			$this->categories['wheels']
		);
	}

	/**
	 * The swaps an answer offers, each as its kind and the part each slot takes.
	 *
	 * @param array<string, mixed> $answer The answer.
	 * @return list<array{string, array<string, int>}>
	 */
	private static function swaps( array $answer ): array {
		$swaps = array();
		foreach ( $answer['swaps'] as $swap ) {
			$swaps[] = array( $swap['kind'], array_column( $swap['parts'], 'part', 'slot' ) );
		}
		return $swaps;
	}

	public function test_the_review_offers_each_swap_at_the_change_it_makes_to_the_total_with_tax_and_the_shops_currency(): void {
		update_option( 'woocommerce_calc_taxes', 'yes' );
		update_option( 'woocommerce_prices_include_tax', 'no' );
		update_option( 'woocommerce_tax_display_shop', 'incl' );
		update_option( 'woocommerce_currency', 'EUR' );
		update_option( 'woocommerce_currency_pos', 'right_space' );
		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option( 'woocommerce_price_thousand_sep', '.' );
		$rate = \WC_Tax::_insert_tax_rate(
			array(
				'tax_rate_country'  => '',
				'tax_rate'          => '13.0000',
				'tax_rate_name'     => 'Tax',
				'tax_rate_priority' => 1,
				'tax_rate_order'    => 1,
				'tax_rate_class'    => '',
			)
		);
		try {
			$whole     = $this->whole();
			$this->kit = $whole['kit'];
			$cheaper   = $this->wheels( 'Wheels for less', '7.35' );
			$better    = $this->wheels( 'Wheels, a step up', '13.10' );
			$this->wheels( 'Wheels, a step further', '19.95' );
			// The cheapest, but too big for the trucks without risers; and sold out.
			$this->wheels( 'Wheels, too big', '5', '60.0000' );
			$sold = wc_get_product( $this->wheels( 'Wheels, sold out', '6' ) );
			$sold->set_stock_status( 'outofstock' );
			$sold->save();
			$bearings = $this->part(
				'Bearings for less',
				'8.20',
				array(
					'bearing_bore' => '8.0000',
					'set_size'     => '8',
				),
				$this->categories['bearings']
			);
			$picks    = array_map( 'strval', $whole['picks'] );
			$review   = $this->answer( array( 'cbuild' => $picks + array( 'step' => 'review' ) ) );
			self::assertSame(
				array(
					array(
						'board',
						array(
							'wheels'   => $cheaper,
							'bearings' => $bearings,
						),
					),
					array( 'less', array( 'wheels' => $cheaper ) ),
					array( 'better', array( 'wheels' => $better ) ),
					array( 'less', array( 'bearings' => $bearings ) ),
				),
				self::swaps( $review ),
				'the same board for less first, then each slot in step order; the deck is never swapped, and the hardware and grip have nothing for less'
			);
			self::assertSame( array( 'Wheels for less', 'Bearings for less' ), array_column( $review['swaps'][0]['parts'], 'name' ) );
			self::assertSame( array( $whole['picks']['wheels'], $whole['picks']['bearings'] ), array_column( $review['swaps'][0]['parts'], 'was' ) );
			foreach ( $review['swaps'] as $swap ) {
				$after = $picks;
				foreach ( $swap['parts'] as $part ) {
					$after[ $part['slot'] ] = (string) $part['part'];
				}
				$swapped = $this->answer( array( 'cbuild' => $after + array( 'step' => 'review' ) ) );
				self::assertTrue( $swapped['ready'], $swap['kind'] . ': the build after it fits and is in stock' );
				self::assertSame( array(), $swapped['problems'] );
				self::assertSame( $swapped['total']['total']['amount'] - $review['total']['total']['amount'], $swap['change']['amount'], $swap['kind'] . ': the difference shown is the total\'s change' );
				self::assertMatchesRegularExpression( '/\A[0-9]+,[0-9]{2}\x{00A0}€\z/u', $swap['change']['text'], 'written as the shop writes prices, with no sign' );
			}
			// With tax: 10.00 and 7.35 are 11.30 and 8.31 a set, so the wheels for less take 2.99 off.
			self::assertSame( array( -299, '2,99' . "\u{00A0}" . '€' ), array( $review['swaps'][1]['change']['amount'], $review['swaps'][1]['change']['text'] ) );
			self::assertSame( 350, $review['swaps'][2]['change']['amount'], '13.10 with tax is 14.80, 3.50 more' );
		} finally {
			\WC_Tax::_delete_tax_rate( $rate );
		}
	}

	public function test_better_follows_the_shops_ranks_or_a_plugins_and_a_step_offers_its_own_picks_swaps_alone(): void {
		$whole     = $this->whole();
		$this->kit = $whole['kit'];
		$picks     = array_map( 'strval', $whole['picks'] );
		$dearer    = $this->wheels( 'Wheels, dearer', '12' );
		$ranked    = $this->wheels( 'Wheels, ranked next', '25' );
		$top       = $this->wheels( 'Wheels, ranked top', '15' );
		$rank      = static function ( int $part, string $rank ): void {
			$product = wc_get_product( $part );
			$product->update_meta_data( Options::META_RANK, $rank );
			$product->save();
		};
		$rank( $whole['picks']['wheels'], '1' );
		$rank( $ranked, '2' );
		$rank( $top, '7' );

		$at = fn( string $step, array $build = array() ): array => $this->answer( array( 'cbuild' => ( array() === $build ? $picks : $build ) + array( 'step' => $step ) ) );
		self::assertSame( array( array( 'better', array( 'wheels' => $ranked ) ) ), self::swaps( $at( 'wheels' ) ), 'the next rank up, not the top or the next price; on the wheels step, the wheels\' alone' );

		add_filter( 'cbuild_part_rank', static fn( ?int $rank, int $part ): ?int => $part === $top ? 2 : $rank, 10, 2 );
		self::assertSame( array( array( 'better', array( 'wheels' => $top ) ) ), self::swaps( $at( 'wheels' ) ), 'another plugin\'s rank, the cheaper of two at 2' );
		remove_all_filters( 'cbuild_part_rank' );

		add_filter( 'cbuild_part_rank', static fn(): ?int => null );
		self::assertSame( array( array( 'better', array( 'wheels' => $dearer ) ) ), self::swaps( $at( 'wheels' ) ), 'no ranks: the next dearer' );
		remove_all_filters( 'cbuild_part_rank' );

		self::assertSame( array(), $at( 'trucks' )['swaps'], 'the trucks have nothing else that fits' );
		$open = $picks;
		unset( $open['wheels'] );
		self::assertSame( array(), $at( 'wheels', $open )['swaps'], 'a step with no pick offers none' );
		self::assertSame( array(), $at( 'deck' )['swaps'], 'the deck is never swapped' );
	}

	// The auditor's 1.3.0 minor: a swap followed after its part sold out, between the offer and the press. The link is
	// no promise: the build is checked again as it arrives, keeps the picks it asks for, names the part, and is not
	// ready, as any build link with a part sold out is.
	public function test_a_swap_whose_part_sold_out_since_it_was_offered_keeps_its_picks_names_the_part_and_is_not_ready(): void {
		$whole     = $this->whole();
		$this->kit = $whole['kit'];
		$cheaper   = $this->wheels( 'Wheels for less', '7' );
		$picks     = array_map( 'strval', $whole['picks'] );
		$review    = $this->answer( array( 'cbuild' => $picks + array( 'step' => 'review' ) ) );
		self::assertContains( array( 'less', array( 'wheels' => $cheaper ) ), self::swaps( $review ), 'offered while in stock' );

		$product = wc_get_product( $cheaper );
		$product->set_stock_status( 'outofstock' );
		$product->save();
		$swapped = array( 'wheels' => (string) $cheaper ) + $picks;
		$after   = $this->answer(
			array(
				'cbuild' => $swapped + array(
					'_undo' => array( 'wheels' => $picks['wheels'] ),
					'step'  => 'review',
				),
			)
		);
		$picked  = array_column( $after['steps'], 'picked', 'slot' );
		foreach ( $swapped as $slot => $part ) {
			self::assertSame( (int) $part, $picked[ $slot ] ?? null, $slot . ': the picks the link asks for, kept' );
		}
		self::assertFalse( $after['ready'], 'not ready with a part sold out' );
		self::assertSame(
			array(
				array(
					'code' => 'part_sold_out',
					'slot' => 'wheels',
					'part' => $cheaper,
				),
			),
			array_map( static fn( array $problem ): array => array_intersect_key( $problem, array_flip( array( 'code', 'slot', 'part' ) ) ), $after['problems'] ),
			'the part sold out named, in its slot'
		);
	}

	public function test_a_kit_with_smart_swaps_off_offers_none_and_one_saved_without_the_setting_offers_them(): void {
		$offered = function ( ?array $settings ): array {
			$whole     = $this->whole( null, null, $settings );
			$this->kit = $whole['kit'];
			$this->wheels( 'Wheels for less', '7' );
			return $this->answer( array( 'cbuild' => array_map( 'strval', $whole['picks'] ) + array( 'step' => 'review' ) ) )['swaps'];
		};
		self::assertNotSame( array(), $offered( null ), 'a kit with no settings' );
		self::assertNotSame( array(), $offered( array( 'schema_version' => 1, 'start_button' => true ) ), 'a kit saved before 1.3.0, without the setting' );
		self::assertSame( array(), $offered( array( 'schema_version' => 1, 'swaps' => false ) ), 'switched off' );

		// The filter answers after the setting, with the kit's ID.
		$asked = array();
		add_filter(
			'cbuild_swaps',
			static function ( bool $offers, int $kit ) use ( &$asked ): bool {
				$asked[] = array( $offers, $kit );
				return ! $offers;
			},
			10,
			2
		);
		self::assertNotSame( array(), $offered( array( 'schema_version' => 1, 'swaps' => false ) ), 'a plugin turns them on' );
		self::assertSame( array( array( false, $this->kit ) ), $asked );
		self::assertSame( array(), $offered( null ), 'and off' );
		remove_all_filters( 'cbuild_swaps' );
	}
}
