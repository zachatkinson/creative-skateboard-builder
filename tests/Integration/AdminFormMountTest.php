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
use WP_Locale_Switcher;

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

	// The research's recommendation 13 and finding 4.2: one notice names every refusal and links to each field. Printed
	// inside the form, it carries `inline`, so core's common.js leaves it where it is.
	public function test_the_refusals_are_summed_up_inside_the_mount_with_a_link_to_each_field(): void {
		$form = Form::of(
			'cbuild-settings',
			array(
				Field::of( 'unit', 'cbuild[unit]', 'Unit <i>', 'text' ),
				Field::of( 'lead_days', 'cbuild[lead_days]', 'Assembly time', 'integer' ),
				Field::of( 'note', 'cbuild[note]', 'Note', 'text' ),
			),
			array()
		)->failed( Errors::none()->with( 'note', 'Too long & "odd".' )->with( 'unit', 'Choose one.' ) );
		$xpath = self::read( Mount::open( $form ) . Mount::close() );

		$summary = $xpath->query( '//div[@class="cbuild-form"]/div[contains(@class, "notice-error")][contains(@class, "inline")][contains(@class, "cbuild-form__summary")][@tabindex="-1"]' );
		self::assertNotFalse( $summary );
		self::assertSame( 1, $summary->length, 'one inline error notice, first in the mount, which the script can focus' );
		$first = $xpath->query( '//div[@class="cbuild-form"]/*[1]' );
		self::assertNotFalse( $first );
		self::assertSame( $summary->item( 0 ), $first->item( 0 ), 'before the description and the controls' );
		self::assertStringContainsString( '2 fields need a change before the form can be saved.', (string) $summary->item( 0 )?->textContent );

		$links = $xpath->query( '//div[contains(@class, "cbuild-form__summary")]//li/a' );
		self::assertNotFalse( $links );
		$found = array();
		foreach ( $links as $link ) {
			self::assertInstanceOf( \DOMElement::class, $link );
			$found[] = array( $link->getAttribute( 'href' ), $link->getAttribute( 'data-cbuild-field' ), trim( $link->textContent ) );
		}
		self::assertSame(
			array(
				array( '#' . Mount::controlId( $form, 'unit' ), 'unit', 'Unit <i>: Choose one.' ),
				array( '#' . Mount::controlId( $form, 'note' ), 'note', 'Note: Too long & "odd".' ),
			),
			$found,
			'in the fields\' order, each the field\'s label and its message, as text'
		);
		self::assertSame( 'cbuild-form-cbuild-settings-note', Mount::controlId( $form, 'note' ), 'the server control\'s id, for a page with no script' );
	}

	public function test_one_refusal_is_said_in_the_singular_and_none_prints_no_summary(): void {
		$fields = array( Field::of( 'note', 'cbuild[note]', 'Note', 'text' ) );
		$one    = self::read( Mount::open( Form::of( 'f', $fields, array() )->failed( Errors::none()->with( 'note', 'No.' ) ) ) . Mount::close() );
		self::assertStringContainsString( '1 field needs a change before the form can be saved.', (string) $one->document->textContent );
		$none = self::read( Mount::open( Form::of( 'f', $fields, array() ) ) . Mount::close() );
		$summary = $none->query( '//*[contains(@class, "cbuild-form__summary")]' );
		self::assertNotFalse( $summary );
		self::assertSame( 0, $summary->length );
	}

	/**
	 * The markup, read.
	 *
	 * @param string $html Some markup.
	 */
	private static function read( string $html ): DOMXPath {
		$dom = new DOMDocument();
		self::assertTrue( $dom->loadHTML( '<!doctype html><html><head><meta charset="utf-8"></head><body>' . $html . '</body></html>', LIBXML_NOERROR ) );
		return new DOMXPath( $dom );
	}

	public function test_a_form_with_a_list_carries_the_kits_own_words_for_it_translated(): void {
		$columns = array( Field::of( 'size', '[size]', 'Shoe size', 'text' ) );
		$listed  = Form::of( 'f', array( Field::rows( 'guide', 'cbuild[guide]', 'Width guide', $columns, 3 ) ), array() );
		$json    = json_decode( (string) self::read( Mount::open( $listed ) . Mount::close() )->query( '//script' )?->item( 0 )?->textContent, true );
		self::assertIsArray( $json );
		self::assertSame(
			array( 'edit', 'remove', 'moveUp', 'moveDown', 'removed', 'undo', 'cancel', 'done' ),
			array_keys( $json['kit'] ),
			'the words of a list\'s actions, its snackbar and its modal'
		);
		self::assertSame( 'Remove', $json['kit']['remove'] );
		self::assertSame( 'Row removed.', $json['kit']['removed'] );
		self::assertSame( $listed->toArray(), array_diff_key( $json, array( 'kit' => true ) ), 'the description as it was, beside them' );

		$plain = json_decode( (string) self::read( Mount::open( Form::of( 'f', $columns, array() ) ) . Mount::close() )->query( '//script' )?->item( 0 )?->textContent, true );
		self::assertIsArray( $plain );
		self::assertArrayNotHasKey( 'kit', $plain, 'none for a form with no list' );

		// As ListWordsTest switches: the test site has no fr_CA of its own, so the plugin's file is loaded by hand.
		add_filter( 'get_available_languages', static fn( array $languages ): array => array_merge( $languages, array( 'fr_CA' ) ) );
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		self::assertTrue( switch_to_locale( 'fr_CA' ) );
		self::assertTrue( load_textdomain( 'creative-skateboard-builder', CBUILD_PATH . 'languages/creative-skateboard-builder-fr_CA.mo', 'fr_CA' ) );
		try {
			$french = json_decode( (string) self::read( Mount::open( $listed ) . Mount::close() )->query( '//script' )?->item( 0 )?->textContent, true );
		} finally {
			unload_textdomain( 'creative-skateboard-builder' );
			restore_previous_locale();
		}
		self::assertIsArray( $french );
		self::assertSame( 'Retirer', $french['kit']['remove'] );
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
