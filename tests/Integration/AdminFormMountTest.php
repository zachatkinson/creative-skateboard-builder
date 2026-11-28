<?php
/**
 * The admin kit's mount: the markup its script finds, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\Form\Assets;
use CreativeBranding\SkateboardBuilder\Admin\Form\Card;
use CreativeBranding\SkateboardBuilder\Admin\Form\Errors;
use CreativeBranding\SkateboardBuilder\Admin\Form\Field;
use CreativeBranding\SkateboardBuilder\Admin\Form\Form;
use CreativeBranding\SkateboardBuilder\Admin\Form\Mount;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use DOMDocument;
use DOMXPath;

/**
 * M15's principle 1: the server's form stays the page, inside the mount, and the description rides beside it as JSON
 * the script reads (assets/src/admin/form). The JSON holds what any field's words hold, a closing script tag
 * included, without ending its script early.
 */
final class AdminFormMountTest extends IntegrationTestCase {

	public function test_the_mount_holds_the_description_and_the_server_form_as_it_was(): void {
		$form = Form::of(
			'cbuild-settings',
			array(
				Field::of( 'lead_days', 'cbuild[lead_days]', 'Assembly time', 'integer' )->value( '3' ),
				Field::of( 'note', 'cbuild[note]', 'Note', 'text' )->help( 'Say </script><b>it</b> & "more"' ),
			),
			array( Card::of( 'main', 'Builds', array( 'lead_days', 'note' ) ) )
		)->failed( Errors::none()->with( 'lead_days', 'Use a whole number.' ) );
		$server = '<input type="text" name="cbuild[lead_days]" value="3"><input type="text" name="cbuild[note]" value="">';
		$html   = Mount::open( $form ) . $server . Mount::close();

		$dom = new DOMDocument();
		self::assertTrue( $dom->loadHTML( '<!doctype html><html><body>' . $html . '</body></html>', LIBXML_NOERROR ) );
		$xpath = new DOMXPath( $dom );
		$mount = $xpath->query( '//div[@class="cbuild-form"][@data-cbuild-form="cbuild-settings"]' );
		self::assertNotFalse( $mount );
		self::assertSame( 1, $mount->length, 'one mount, named by its form' );
		$scripts = $xpath->query( '//div[@class="cbuild-form"]/script[@type="application/json"][@class="cbuild-form__description"]' );
		self::assertNotFalse( $scripts );
		self::assertSame( 1, $scripts->length, 'the description, as JSON, a child of the mount' );
		$json = json_decode( (string) $scripts->item( 0 )?->textContent, true );
		self::assertSame( $form->toArray(), $json, 'the whole description, the help\'s closing script tag kept as text' );
		$inputs = $xpath->query( '//div[@class="cbuild-form"]/div[@class="cbuild-form__server"]/input' );
		self::assertNotFalse( $inputs );
		self::assertSame( 2, $inputs->length, 'the server\'s controls inside the mount, as they were' );
		self::assertStringNotContainsString( '</script><b>', $html, 'the help cannot end the script early' );
	}

	public function test_a_screen_loads_its_script_as_the_build_wrote_it_and_the_kits_styles(): void {
		self::assertTrue( Assets::enqueue( 'settings', CBUILD_PATH, CBUILD_URL ) );
		$script = wp_scripts()->registered['cbuild-admin-settings'] ?? null;
		self::assertNotNull( $script );
		self::assertSame( CBUILD_URL . 'build/admin/settings.js', $script->src );
		self::assertContains( 'wp-components', $script->deps, 'the dependencies the build wrote' );
		$built = require CBUILD_PATH . 'build/admin/settings.asset.php';
		self::assertSame( $built['version'], $script->ver );
		$style = wp_styles()->registered[ Assets::STYLE ] ?? null;
		self::assertNotNull( $style );
		self::assertSame( CBUILD_URL . 'build/admin/kit.css', $style->src );
		self::assertSame( array( 'wp-components' ), $style->deps, 'after the components\' own styles' );
		self::assertSame( 'replace', $style->extra['rtl'] ?? null, 'with its right-to-left copy' );
	}

	public function test_a_screen_with_no_build_or_no_slug_loads_nothing(): void {
		// WordPress's registries outlive a test: start from none of the kit's.
		wp_deregister_style( Assets::STYLE );
		wp_deregister_script( 'cbuild-admin-settings' );
		self::assertFalse( Assets::enqueue( 'no-such-screen', CBUILD_PATH, CBUILD_URL ) );
		self::assertFalse( Assets::enqueue( '../settings', CBUILD_PATH, CBUILD_URL ) );
		self::assertArrayNotHasKey( 'cbuild-admin-no-such-screen', wp_scripts()->registered );
		self::assertArrayNotHasKey( Assets::STYLE, wp_styles()->registered );
	}
}
