<?php
/**
 * A rollback to the release before keeps every kit readable.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\BuildTab;
use CreativeBranding\SkateboardBuilder\Admin\BuildTabHooks;
use CreativeBranding\SkateboardBuilder\Front\BuilderPlace;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Service\KitEditor;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * Spec, Upgrades: "Rolling back one minor release leaves a working shop." The release before reads a kit only when
 * each of its documents passes that release's schema, and its schemas refuse a key they do not know, so a kit saved
 * here must store nothing in those documents that the release before does not know (D-354).
 *
 * The schemas of the release before are copies in `tests/Fixtures/previous-schemas/`, taken with
 * `git show v<previous>:schema/<file>`. Refresh them at each release, from the tag of the release just made, so the
 * next release is held to it.
 */
final class RollbackTest extends IntegrationTestCase {

	/**
	 * The kit documents of the release before, 1.1.0: each meta key and its schema's file in the fixtures.
	 */
	private const array PREVIOUS = array(
		'_cbuild_slots'    => 'kit-slots.json',
		'_cbuild_rules'    => 'kit-rules.json',
		'_cbuild_presets'  => 'kit-presets.json',
		'_cbuild_assembly' => 'kit-assembly.json',
		'_cbuild_picture'  => 'kit-picture.json',
		'_cbuild_layout'   => 'kit-layout.json',
	);

	public function set_up(): void {
		parent::set_up();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
	}

	public function tear_down(): void {
		unset( $_POST[ BuildTab::FIELD ], $GLOBALS['product_object'] );
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		parent::tear_down();
	}

	private function hooks(): BuildTabHooks {
		$tab = ( new BuildTab( $this->service( KitEditor::class ), $this->service( ShopSettings::class ) ) )->withPlace( $this->service( BuilderPlace::class ) );
		return new BuildTabHooks( static fn(): BuildTab => $tab );
	}

	/**
	 * The name a control is sent under, found by its label as the tab draws it, so the test sends what a browser
	 * would whatever the fields are called.
	 *
	 * @param string $panel The panel.
	 * @param string $label The control's label, as HTML.
	 */
	private static function named( string $panel, string $label ): string {
		self::assertSame( 1, preg_match( '#<label for="([^"]+)">' . preg_quote( $label, '#' ) . '</label>#', $panel, $found ), 'a control labelled ' . $label );
		self::assertSame( 1, preg_match( '#<(?:input|select|textarea)\b[^>]*\bid="' . preg_quote( $found[1], '#' ) . '"[^>]*>#', $panel, $control ), 'the control of ' . $label );
		self::assertSame( 1, preg_match( '#\bname="([^"]+)"#', $control[0], $name ), 'sent under a name: ' . $label );
		return html_entity_decode( $name[1], ENT_QUOTES );
	}

	// The release blocker of milestone 12's audit: 1.2.0 wrote its new settings into `_cbuild_layout`, whose schema in
	// 1.1.0 has additionalProperties false, so 1.1.0 read no kit saved on 1.2.0. Every 1.2.0 setting is set here, as
	// the Build tab sends them, and every kit document the release before reads must still pass its schema.
	public function test_a_kit_saved_with_every_setting_of_this_release_passes_the_schemas_of_the_release_before(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$kit  = KitFactory::kit();
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Build a board',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $kit->get_id() . '} /-->',
			)
		);
		clean_post_cache( $kit->get_id() );
		$GLOBALS['product_object'] = wc_get_product( $kit->get_id() );
		ob_start();
		$this->hooks()->panel();
		$panel = (string) ob_get_clean();

		// Own rows, the guide hidden and the start button off: a box left unticked is not sent.
		$guide  = substr( self::named( $panel, 'Use the builder&#039;s rows' ), 0, -strlen( '[plugins]' ) );
		$fields = array(
			self::named( $panel, 'Stage side on a desktop' ) => 'right',
			self::named( $panel, 'Show ready builds' )       => '1',
			self::named( $panel, 'Builder page' )            => (string) $page,
			$guide . '[rows][0][shoe]'                        => 'EU 38 to 40',
			$guide . '[rows][0][from]'                        => '7.75',
			$guide . '[rows][0][to]'                          => '8',
			$guide . '[rows][1][shoe]'                        => 'EU 41 and up',
			$guide . '[rows][1][from]'                        => '8.25',
			$guide . '[rows][1][to]'                          => '',
			BuildTab::FIELD . '[' . BuildTab::END . ']'       => '1',
		);
		parse_str( http_build_query( $fields ), $sent );
		$_POST[ BuildTab::FIELD ] = wp_slash( $sent[ BuildTab::FIELD ] );
		$this->hooks()->save( $kit );
		$kit->save();
		self::assertSame( array(), array_values( \WC_Admin_Meta_Boxes::$meta_box_errors ), 'saved with no notice' );

		clean_post_cache( $kit->get_id() );
		foreach ( self::PREVIOUS as $key => $file ) {
			$stored = get_post_meta( $kit->get_id(), $key, true );
			if ( in_array( $key, array( '_cbuild_picture', '_cbuild_layout' ), true ) && '' === $stored ) {
				// The release before reads a kit with no picture or layout, with the defaults.
				continue;
			}
			$schema = wp_json_file_decode( dirname( __DIR__ ) . '/Fixtures/previous-schemas/' . $file, array( 'associative' => true ) );
			self::assertIsArray( $schema, $file );
			self::assertIsString( $stored, $key );
			$valid = rest_validate_value_from_schema( json_decode( $stored, true ), $schema, $key );
			self::assertTrue( $valid, $key . ' fails the release before\'s schema: ' . ( is_wp_error( $valid ) ? $valid->get_error_message() : '' ) );
		}

		// And this release keeps each setting.
		$read = $this->service( Kits::class )->kit( $kit->get_id() );
		self::assertSame( 'right', $read?->layout->stageSide );
		self::assertSame( $page, $read?->settings->builderPage );
		self::assertFalse( $read?->settings->startButton );
		self::assertFalse( $read?->settings->guide->shown );
		self::assertSame( array( 'EU 38 to 40', 'EU 41 and up' ), array_map( static fn( $row ): string => $row->shoe, $read?->settings->guide->rows ?? array() ) );
	}
}
