<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e scene
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: with the cookie cbuild_e2e_scene, a builder view with no scene gets the scene contract's fixture (tests/e2e/fixtures/scene.json), with the deck's graphic and a printed grip from the layer images, so the 3D view's browser tests draw the same board on every run. The cookie's value `pinned` answers `cbuild_scene` with the fixture, for the visual tests. The cookie's value `wheels` gives the fixture other wheels, 4 mm larger, for the test that a part's change swaps that part alone. Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

if ( isset( $_COOKIE['cbuild_e2e_scene'] ) ) {
	$cbuild_e2e_scene = static function (): array {
		$scene = json_decode( (string) file_get_contents( WP_CONTENT_DIR . '/plugins/creative-skateboard-builder/tests/e2e/fixtures/scene.json' ), true );
		// The deck's graphic and the grip's sheet from the layer images beside it, on the shop's own origin.
		$layers                           = content_url( 'plugins/creative-skateboard-builder/tests/e2e/fixtures/layers/' );
		$scene['deck']['texture']['bottom'] = $layers . 'deck-8.0-31.5.png';
		$scene['grip']                      = array(
			'part'    => 9001,
			'texture' => $layers . 'grip-sheet-printed.png',
		);
		if ( 'wheels' === $_COOKIE['cbuild_e2e_scene'] ) {
			$scene['wheels']['part']     += 1;
			$scene['wheels']['diameter'] += 4000;
		}
		return $scene;
	};
	if ( 'pinned' === $_COOKIE['cbuild_e2e_scene'] ) {
		// The visual tests' board: the fixture in place of the server's scene, answered as a plugin would answer
		// cbuild_scene, so their pictures stay the same whatever the kit's parts measure.
		add_filter( 'cbuild_scene', $cbuild_e2e_scene, 99 );
	}
	add_filter(
		'rest_post_dispatch',
		static function ( $response ) use ( $cbuild_e2e_scene ) {
			$data = $response instanceof WP_REST_Response ? $response->get_data() : null;
			if ( is_array( $data ) && isset( $data['view'] ) && is_array( $data['view'] ) && ! isset( $data['view']['scene'] ) ) {
				$data['view']['scene'] = $cbuild_e2e_scene();
				$response->set_data( $data );
			}
			return $response;
		}
	);
	add_action(
		'template_redirect',
		static function () use ( $cbuild_e2e_scene ) {
			wp_interactivity_state( 'creative-skateboard-builder', array( 'view' => array( 'scene' => $cbuild_e2e_scene() ) ) );
		}
	);
}
