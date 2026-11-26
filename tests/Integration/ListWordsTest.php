<?php
/**
 * A list as the page's language joins one, in the plugin's own words.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use WP_Locale_Switcher;

/**
 * Milestone 13's walkthrough (D-460): the swap notice said "Échangé : wheels et .hardware." in French. The builder
 * joined its lists with `wp_sprintf( '%l' )`, whose separators are WordPress's own strings, and core's French for
 * Canada translates "%1$s and %2$s" as "%1$s et %2$s.", with a full stop, and "%1$s, and %2$s" with a comma before
 * "et". The plugin joins a list with its own patterns, which its French translates, whatever core's translation says.
 */
final class ListWordsTest extends IntegrationTestCase {

	/**
	 * Core's French for Canada's list separators, as the translation wp-env installs has them (fr_CA.po, lines 10753,
	 * 10844 and 20322, for wp-includes/formatting.php:5433 to 5437). The integration site installs no core language,
	 * so the test gives these.
	 */
	private const CORE_FRENCH = array(
		'%1$s, %2$s'      => '%1$s, %2$s',
		'%1$s, and %2$s'  => '%1$s, et %2$s',
		'%1$s and %2$s'   => '%1$s et %2$s.',
	);

	public function test_two_and_three_items_are_joined_in_english_with_no_comma_before_and(): void {
		self::assertSame( 'Swapped: wheels and hardware.', Words::swapped( array( 'Wheels', 'Hardware' ) ) );
		self::assertSame( 'Swapped: wheels, bearings and hardware.', Words::swapped( array( 'Wheels', 'Bearings', 'Hardware' ) ), 'no comma before "and"' );
		self::assertSame( 'wheels', Words::joined( array( 'wheels' ) ) );
		self::assertSame( '', Words::joined( array() ) );
	}

	public function test_two_and_three_items_are_joined_in_the_plugins_french_not_cores(): void {
		add_filter( 'get_available_languages', static fn( array $languages ): array => array_merge( $languages, array( 'fr_CA' ) ) );
		$core = static fn( string $translation, string $text, string $domain ): string => 'default' === $domain && 'fr_CA' === determine_locale() ? ( self::CORE_FRENCH[ $text ] ?? $translation ) : $translation;
		add_filter( 'gettext', $core, 10, 3 );
		$GLOBALS['wp_locale_switcher'] = new WP_Locale_Switcher();
		$GLOBALS['wp_locale_switcher']->init();
		self::assertTrue( switch_to_locale( 'fr_CA' ) );
		self::assertTrue( load_textdomain( 'creative-skateboard-builder', CBUILD_PATH . 'languages/creative-skateboard-builder-fr_CA.mo', 'fr_CA' ) );
		try {
			$two   = Words::swapped( array( 'Roues', 'Quincaillerie' ) );
			$three = Words::swapped( array( 'Roues', 'Roulements', 'Quincaillerie' ) );
		} finally {
			unload_textdomain( 'creative-skateboard-builder' );
			restore_previous_locale();
			remove_filter( 'gettext', $core );
		}
		// French puts a no-break space before its colon.
		self::assertSame( "Remplacement\u{00A0}: roues et quincaillerie.", $two, 'no full stop inside the list' );
		self::assertSame( "Remplacement\u{00A0}: roues, roulements et quincaillerie.", $three, 'no comma before "et"' );
	}
}
