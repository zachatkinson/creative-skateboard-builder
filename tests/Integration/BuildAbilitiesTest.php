<?php
/**
 * The abilities that check and price a build.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Abilities\CatalogueCheckAbility;
use CreativeBranding\SkateboardBuilder\Abilities\CheckBuildAbility;
use CreativeBranding\SkateboardBuilder\Abilities\PriceBuildAbility;
use CreativeBranding\SkateboardBuilder\Rest\StateRoute;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Product_Simple;
use WP_Ability;
use WP_Error;
use WP_REST_Request;

/**
 * `creative-skateboard-builder/check-build` and `creative-skateboard-builder/price-build` (roadmap, milestone 4:
 * read-only, and they give the route's answers). Milestone 4's plan, the agreement test: for the same kit and picks,
 * what an ability says is what the state route says.
 */
final class BuildAbilitiesTest extends IntegrationTestCase {

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

	public function set_up(): void {
		parent::set_up();
		// An ability is run by someone signed in; a subscriber sees of a kit what a visitor does.
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		// What keeps each slot's kept parts current (D-116).
		$this->service( \CreativeBranding\SkateboardBuilder\Hooks\CardHooks::class )->register();
		$_SERVER['REMOTE_ADDR'] = '203.0.113.7';
		rest_get_server();
		$this->service( StateRoute::class )->register();
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );

		$decks  = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$trucks = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$this->parts['deck 8.0']  = $this->part( 'Deck 8.0', '60', array( 'deck_width' => '8.0000' ), $decks );
		$this->parts['deck 8.5']  = $this->part( 'Deck 8.5', '70', array( 'deck_width' => '8.5000' ), $decks );
		$this->parts['truck 139'] = $this->part(
			'Truck 139',
			'25',
			array(
				'axle_width' => '8.0000',
				'deck_min'   => '7.8000',
				'deck_max'   => '8.2000',
				'baseplate'  => 'new_school',
				'set_size'   => '2',
			),
			$trucks
		);

		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array( 'categories' => array( $decks ) );
		$slots['slots'][1]['sources'] = array( 'categories' => array( $trucks ) );
		$this->kit                    = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
	}

	public function tear_down(): void {
		unset( $_SERVER['REMOTE_ADDR'] );
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

	private static function ability( string $name ): WP_Ability {
		$ability = wp_get_ability( $name );
		self::assertNotNull( $ability );
		return $ability;
	}

	/**
	 * What the state route says of the same build.
	 *
	 * @param array<string, int> $picks The picks.
	 * @return array<string, mixed>
	 */
	private function route( array $picks, ?bool $assembly = null ): array {
		$request = new WP_REST_Request( 'GET', '/cbuild/v1/kits/' . $this->kit . '/state' );
		$request->set_query_params( array( 'picks' => $picks ) + ( null === $assembly ? array() : array( 'assembly' => $assembly ) ) );
		$response = rest_get_server()->dispatch( $request );
		self::assertSame( 200, $response->get_status() );
		return $response->get_data();
	}

	public function test_both_are_registered_read_only_in_the_plugins_category_and_take_a_kit_and_its_picks(): void {
		foreach ( array(
			CheckBuildAbility::NAME => array( 'Check a build', array( 'kit', 'ready', 'refused', 'problems' ) ),
			PriceBuildAbility::NAME => array( 'Price a build', array( 'kit', 'total' ) ),
		) as $name => [ $label, $required ] ) {
			$ability = self::ability( $name );
			self::assertSame( $label, $ability->get_label() );
			self::assertSame( CatalogueCheckAbility::CATEGORY, $ability->get_category() );
			self::assertSame( array( 'kit' ), $ability->get_input_schema()['required'] );
			self::assertSame( array( 'kit', 'picks', 'assembly' ), array_keys( $ability->get_input_schema()['properties'] ) );
			self::assertFalse( $ability->get_input_schema()['additionalProperties'] );
			self::assertSame( $required, $ability->get_output_schema()['required'] );
			$meta = $ability->get_meta();
			self::assertTrue( $meta['show_in_rest'] );
			self::assertSame(
				array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				array_intersect_key( $meta['annotations'], array_flip( array( 'readonly', 'destructive', 'idempotent' ) ) )
			);
		}
	}

	public function test_what_an_ability_says_of_a_build_is_what_the_state_route_says_of_it(): void {
		$builds = array(
			'nothing picked'         => array(),
			'a deck'                 => array( 'deck' => $this->parts['deck 8.0'] ),
			'a pair that fits'       => array(
				'deck'   => $this->parts['deck 8.0'],
				'trucks' => $this->parts['truck 139'],
			),
			'a pair that does not'   => array(
				'deck'   => $this->parts['deck 8.5'],
				'trucks' => $this->parts['truck 139'],
			),
			'a part of another slot' => array( 'deck' => $this->parts['truck 139'] ),
		);
		foreach ( $builds as $what => $picks ) {
			foreach ( array( null, true, false ) as $assembly ) {
				$input = array(
					'kit'   => $this->kit,
					'picks' => $picks,
				) + ( null === $assembly ? array() : array( 'assembly' => $assembly ) );
				$route = $this->route( $picks, $assembly );

				$checked = self::ability( CheckBuildAbility::NAME )->execute( $input );
				self::assertIsArray( $checked, $what );
				self::assertSame( array_intersect_key( $route, array_flip( array( 'kit', 'ready', 'refused', 'problems' ) ) ), $checked, $what );

				$priced = self::ability( PriceBuildAbility::NAME )->execute( $input );
				self::assertIsArray( $priced, $what );
				self::assertSame( array_intersect_key( $route, array_flip( array( 'kit', 'total' ) ) ), $priced, $what );
			}
		}

		// And the answers are not empty agreement: the pair that does not fit is blocked, and a deck has a price.
		$blocked = self::ability( CheckBuildAbility::NAME )->execute(
			array(
				'kit'   => $this->kit,
				'picks' => $builds['a pair that does not'],
			)
		);
		self::assertFalse( $blocked['ready'] );
		self::assertContains( 'part_blocked', array_column( $blocked['problems'], 'code' ) );
		$priced = self::ability( PriceBuildAbility::NAME )->execute(
			array(
				'kit'      => $this->kit,
				'picks'    => $builds['a pair that fits'],
				'assembly' => true,
			)
		);
		self::assertSame( array( 6000, 2500 ), array_column( array_column( $priced['total']['lines'], 'total' ), 'amount' ) );
		self::assertTrue( $priced['total']['assembly'] );
		self::assertSame( '$85.00', $priced['total']['parts']['text'] );
	}

	public function test_each_answer_is_its_own_output_schemas(): void {
		// WordPress holds an ability's answer against its output schema, and gives an error for one that fails.
		foreach ( array( CheckBuildAbility::NAME, PriceBuildAbility::NAME ) as $name ) {
			$answer = self::ability( $name )->execute(
				array(
					'kit'   => $this->kit,
					'picks' => array(
						'deck'   => $this->parts['deck 8.5'],
						'trucks' => $this->parts['truck 139'],
						'sail'   => 5,
					),
				)
			);
			self::assertIsArray( $answer, $answer instanceof WP_Error ? $answer->get_error_message() : '' );
			self::assertTrue( rest_validate_value_from_schema( json_decode( (string) wp_json_encode( $answer ), true ), self::ability( $name )->get_output_schema(), 'answer' ) );
		}
	}

	public function test_input_that_is_not_a_kit_and_its_picks_is_refused_before_anything_runs(): void {
		foreach ( array(
			'no kit'             => array( 'picks' => array() ),
			'a kit of nothing'   => array( 'kit' => 0 ),
			'an ID that is text' => array(
				'kit'   => $this->kit,
				'picks' => array( 'deck' => 'the wide one' ),
			),
			'something more'     => array(
				'kit'   => $this->kit,
				'price' => 1,
			),
			'more than 20 picks' => array(
				'kit'   => $this->kit,
				'picks' => array_fill_keys( array_map( static fn( int $n ): string => "slot_{$n}", range( 1, 21 ) ), 5 ),
			),
		) as $what => $input ) {
			foreach ( array( CheckBuildAbility::NAME, PriceBuildAbility::NAME ) as $name ) {
				$answer = self::ability( $name )->execute( $input );
				self::assertWPError( $answer, $what );
				self::assertSame( 'ability_invalid_input', $answer->get_error_code(), $what );
			}
		}
	}

	public function test_a_kit_a_visitor_may_not_see_is_not_there_for_an_ability_either(): void {
		$draft = KitFactory::kit( 'Draft' );
		$draft->set_status( 'draft' );
		$draft->save();
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'administrator' ) ) );
		foreach ( array( $draft->get_id(), 999999, $this->parts['deck 8.0'] ) as $id ) {
			foreach ( array( CheckBuildAbility::NAME, PriceBuildAbility::NAME ) as $name ) {
				$answer = self::ability( $name )->execute( array( 'kit' => $id ) );
				self::assertWPError( $answer );
				self::assertSame( 'cbuild_kit_not_found', $answer->get_error_code() );
			}
		}
	}

	// The auditor's blocker 2, milestone 4: WordPress's run route asks only the ability's own callback, so an open
	// one was the state route again with no limit, for anyone.
	public function test_a_visitor_who_is_not_signed_in_runs_neither(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );
		wp_set_current_user( 0 );
		foreach ( array( CheckBuildAbility::NAME, PriceBuildAbility::NAME ) as $name ) {
			$request = new WP_REST_Request( 'GET', '/wp-abilities/v1/abilities/' . $name . '/run' );
			$request->set_query_params( array( 'input' => array( 'kit' => $this->kit ) ) );
			$answer = rest_get_server()->dispatch( $request );
			self::assertSame( 401, $answer->get_status(), (string) wp_json_encode( $answer->get_data() ) );
			self::assertArrayNotHasKey( 'kit', (array) $answer->get_data() );
			self::assertWPError( self::ability( $name )->execute( array( 'kit' => $this->kit ) ) );
		}
	}

	public function test_over_rest_each_is_a_get_that_changes_nothing(): void {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WordPress's own, to build the routes.
		do_action( 'rest_api_init', rest_get_server() );
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'subscriber' ) ) );
		foreach ( array( CheckBuildAbility::NAME, PriceBuildAbility::NAME ) as $name ) {
			$route   = '/wp-abilities/v1/abilities/' . $name . '/run';
			$request = new WP_REST_Request( 'GET', $route );
			$request->set_query_params( array( 'input' => array( 'kit' => $this->kit ) ) );
			$answer = rest_get_server()->dispatch( $request );
			self::assertSame( 200, $answer->get_status(), (string) wp_json_encode( $answer->get_data() ) );
			self::assertSame( $this->kit, $answer->get_data()['kit'] );
			self::assertSame( 405, rest_get_server()->dispatch( new WP_REST_Request( 'POST', $route ) )->get_status() );
		}
	}
}
