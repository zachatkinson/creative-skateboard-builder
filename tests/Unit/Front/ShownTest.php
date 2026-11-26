<?php
/**
 * What the builder shows, as the kit's switches and a plugin's answer say.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\KitSettings;
use CreativeBranding\SkateboardBuilder\Front\Shown;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * D-409: Help me choose, the cards' ready-build picks and their ratings, each the kit's switch, on for a kit with
 * none, and the filter's answer last, `true` alone being on.
 */
final class ShownTest extends TestCase {

	private const int KIT = 77;

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'] );
	}

	/**
	 * Each switch: its key in `_cbuild_settings`, its filter and what reads it.
	 *
	 * @return iterable<string, array{string, string, callable(int, KitSettings): bool}>
	 */
	public static function switches(): iterable {
		yield 'Help me choose' => array( 'guide', 'cbuild_help_me_choose', Shown::guide( ... ) );
		yield 'the cards\' picks' => array( 'picks', 'cbuild_card_picks', Shown::picks( ... ) );
		yield 'the cards\' ratings' => array( 'ratings', 'cbuild_card_ratings', Shown::ratings( ... ) );
	}

	/**
	 * @param callable(int, KitSettings): bool $shown What reads the switch.
	 */
	#[DataProvider( 'switches' )]
	public function test_each_is_the_kits_switch_on_where_it_has_none_and_the_filter_answers_last( string $key, string $hook, callable $shown ): void {
		self::assertTrue( $shown( self::KIT, KitSettings::fromDocument( null ) ), 'a kit with no settings' );
		self::assertTrue( $shown( self::KIT, KitSettings::fromDocument( array( 'schema_version' => 1, 'start_button' => true ) ) ), 'settings saved before 1.3.0' );
		$off = KitSettings::fromDocument( array( 'schema_version' => 1, $key => false ) );
		self::assertFalse( $shown( self::KIT, $off ), 'off' );

		$asked                                   = array();
		$GLOBALS['cbuild_test_filters'][ $hook ] = static function ( mixed $value, int $kit ) use ( &$asked ): bool {
			$asked[] = array( $value, $kit );
			return true;
		};
		self::assertTrue( $shown( self::KIT, $off ), 'a plugin turns it on' );
		self::assertSame( array( array( false, self::KIT ) ), $asked, 'asked with the kit\'s setting and its ID' );

		foreach ( array( false, 1, 'yes', null ) as $answer ) {
			$GLOBALS['cbuild_test_filters'][ $hook ] = static fn(): mixed => $answer;
			self::assertFalse( $shown( self::KIT, KitSettings::fromDocument( null ) ), var_export( $answer, true ) . ' is off' );
		}
	}
}
