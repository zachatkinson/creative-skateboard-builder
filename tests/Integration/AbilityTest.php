<?php
/**
 * The plugin's ability, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Abilities\CatalogueCheckAbility;
use CreativeBranding\SkateboardBuilder\Admin\CheckScreen;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use WP_Error;
use WP_REST_Request;

/**
 * Roadmap, milestone 3: the ability `creative-skateboard-builder/catalogue-check`, for those who can manage
 * WooCommerce. Spec, Abilities: the shop's own tools get the answers the builder gives, under the same permissions.
 */
final class AbilityTest extends IntegrationTestCase {

	private function as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	private static function lost( string $name ): int {
		$part = new \WC_Product_Simple();
		$part->set_name( $name );
		$part->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => 999999,
				'size'  => '1',
			)
		);
		$part->save();
		return $part->get_id();
	}

	public function test_the_ability_is_registered_in_the_plugins_category_as_read_only(): void {
		$ability = wp_get_ability( CatalogueCheckAbility::NAME );
		self::assertNotNull( $ability );
		self::assertSame( 'creative-skateboard-builder/catalogue-check', $ability->get_name() );
		self::assertSame( 'Run the Catalogue check', $ability->get_label() );
		self::assertSame( CatalogueCheckAbility::CATEGORY, $ability->get_category() );
		self::assertSame( 'Creative Skateboard Builder', wp_get_ability_category( CatalogueCheckAbility::CATEGORY )?->get_label() );
		self::assertSame( array(), $ability->get_input_schema() );
		self::assertSame( array( 'count', 'findings' ), $ability->get_output_schema()['required'] );
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

	public function test_who_can_manage_the_shop_gets_the_checks_findings_and_no_one_else_does(): void {
		$part    = self::lost( 'Lost' );
		$ability = wp_get_ability( CatalogueCheckAbility::NAME );
		self::assertNotNull( $ability );

		foreach ( array( 'editor', 'customer' ) as $role ) {
			$this->as( $role );
			self::assertFalse( $ability->check_permissions(), $role );
			self::assertInstanceOf( WP_Error::class, $ability->execute(), $role );
		}
		wp_set_current_user( 0 );
		self::assertInstanceOf( WP_Error::class, $ability->execute(), 'signed out' );

		$this->as( 'shop_manager' );
		self::assertTrue( $ability->check_permissions() );
		$answer = $ability->execute();
		self::assertIsArray( $answer, 'the answer passes its own schema' );
		self::assertSame( 1, $answer['count'] );
		self::assertSame( array( 'code', 'part', 'kit', 'slot', 'spec', 'values', 'message' ), array_keys( $answer['findings'][0] ) );
		self::assertSame( CatalogueCheck::UNREAD, $answer['findings'][0]['code'] );
		self::assertSame( $part, $answer['findings'][0]['part'] );
		self::assertSame( 0, $answer['findings'][0]['kit'] );
		self::assertSame( 'table_missing', $answer['findings'][0]['values']->reason );
		self::assertSame( 'It names a maker\'s chart that is no longer there. Choose another on its Part specs.', $answer['findings'][0]['message'] );
		self::assertSame( '', get_post_meta( $part, Options::META_SPECS, true ), 'nothing is written' );
	}

	public function test_a_shop_with_nothing_wrong_answers_with_none_and_values_are_an_object_as_json(): void {
		$this->as( 'administrator' );
		$ability = wp_get_ability( CatalogueCheckAbility::NAME );
		self::assertSame(
			array(
				'count'    => 0,
				'findings' => array(),
			),
			$ability?->execute()
		);
		$this->service( \CreativeBranding\SkateboardBuilder\Port\ReindexStore::class )->save( ( new \CreativeBranding\SkateboardBuilder\Service\ReindexState( \CreativeBranding\SkateboardBuilder\Service\ReindexState::FAILED, 0, 0, 1 ) )->toArray() );
		$answer = $ability?->execute();
		delete_option( Options::REINDEX );
		self::assertIsArray( $answer );
		self::assertStringContainsString( '"values":{}', (string) wp_json_encode( $answer ) );
	}

	public function test_the_answer_lists_five_hundred_findings_and_counts_them_all(): void {
		for ( $at = 0; $at < CatalogueCheckAbility::MOST + 1; $at++ ) {
			$post = self::factory()->post->create( array( 'post_type' => 'product' ) );
			update_post_meta(
				$post,
				Options::META_SPEC_TABLE,
				array(
					'table' => 999999,
					'size'  => '1',
				)
			);
		}
		$this->as( 'shop_manager' );
		$answer = wp_get_ability( CatalogueCheckAbility::NAME )?->execute();
		self::assertIsArray( $answer );
		self::assertSame( CatalogueCheckAbility::MOST + 1, $answer['count'] );
		self::assertCount( CatalogueCheckAbility::MOST, $answer['findings'] );
		self::assertSame( CheckScreen::SHOWN, CatalogueCheckAbility::MOST, 'as many as the screen lists' );
	}

	public function test_over_rest_the_ability_runs_for_a_manager_and_is_refused_to_anyone_else(): void {
		self::lost( 'Lost' );
		$route = '/wp-abilities/v1/abilities/' . CatalogueCheckAbility::NAME . '/run';
		$this->as( 'subscriber' );
		$refused = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) );
		self::assertContains( $refused->get_status(), array( 401, 403 ) );
		self::assertArrayNotHasKey( 'findings', (array) $refused->get_data() );

		$this->as( 'shop_manager' );
		$answer = rest_get_server()->dispatch( new WP_REST_Request( 'GET', $route ) );
		self::assertSame( 200, $answer->get_status() );
		self::assertSame( 1, $answer->get_data()['count'] );
		// Read-only, so WordPress serves it by GET alone (class-wp-rest-abilities-v1-run-controller.php:110).
		self::assertSame( 405, rest_get_server()->dispatch( new WP_REST_Request( 'POST', $route ) )->get_status() );
	}
}
