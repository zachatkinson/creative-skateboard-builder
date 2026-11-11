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
use CreativeBranding\SkateboardBuilder\Service\RateLimit;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
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
	 * `schema/state.json` with each `$ref` written out, which WordPress's validator needs.
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
				return array_map( $resolve, $node );
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
		self::assertSame( array( 'id', 'picks', 'previous', 'step', 'assembly', 'locale' ), array_keys( $routes[ $route ][0]['args'] ) );

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
		self::assertSame( 'The trucks are made for decks from 7.8 in to 8.2 in wide. The deck is 8.5 in.', $picked['hidden'][0]['messages'][0]['text'] );
		self::assertSame( '1 part hidden: the trucks are made for another width of deck.', $picked['steps'][1]['groups'][0]['message'] );
		self::assertSame( array( 'amount' => 7000, 'text' => '$70.00' ), $picked['total']['lines'][0]['price'] );
		self::assertSame( 'Deck 8.5', $picked['total']['lines'][0]['name'] );
		self::assertSame( '$97.00', $picked['total']['total']['text'], 'the deck and the set of trucks the step starts on' );

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
			fn (): RateLimit => $this->service( RateLimit::class )
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
		$count = function ( bool $cold ): int {
			// Once for whatever is read the first time a part is met, then the request that is counted.
			$this->ask();
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
			self::assertSame( 200, $this->ask()->get_status() );
			remove_filter( 'query', $watch );
			return $asked;
		};
		$more = function ( int $upTo ) use ( $decks ): void {
			for ( $at = count( $this->parts ); $at < $upTo; $at++ ) {
				$this->parts[ 'deck ' . $at ] = $this->part( 'Deck ' . $at, '65', array( 'deck_width' => '8.2500' ), $decks );
			}
		};

		// More parts than a step lists, and then twice as many.
		$more( 30 );
		$warm = $count( false );
		$cold = $count( true );
		$more( 60 );
		self::assertSame( $warm, $count( false ), 'warm' );
		self::assertSame( $cold, $count( true ), 'cold' );
		self::assertLessThanOrEqual( 15, $warm, 'warm' );
		self::assertLessThanOrEqual( 60, $cold, 'cold' );
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
			foreach ( $kept as $name ) {
				// One of a fixed number of rows (D-119). What it holds is a count and its end for each key, and a key
				// is 40 characters of a keyed hash.
				self::assertMatchesRegularExpression( '/\A_transient_cbuild_rate_[0-9]{1,4}\z/', $name );
				$held = get_transient( substr( $name, strlen( '_transient_' ) ) );
				self::assertIsArray( $held );
				foreach ( $held as $key => $entry ) {
					self::assertMatchesRegularExpression( '/\A[0-9a-f]{40}\z/', (string) $key );
					self::assertSame( array( 3, true ), array( $entry[0], is_int( $entry[1] ) ) );
				}
				$expires = (int) get_option( '_transient_timeout_' . substr( $name, strlen( '_transient_' ) ) );
				self::assertLessThanOrEqual( time() + 120, $expires, 'Gone within two minutes.' );
				self::assertGreaterThan( time(), $expires );
			}
		}
	}

	public function test_a_plugin_that_answers_for_the_owners_words_changes_what_the_route_says(): void {
		$plain = $this->answer();
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
}
