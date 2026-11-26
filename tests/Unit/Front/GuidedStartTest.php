<?php
/**
 * The guided start's recommendations from a shopper's answers.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Front\GuideAnswers;
use CreativeBranding\SkateboardBuilder\Front\GuidedStart;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeMoney;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartCards;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * Issue #134, Help me choose: the shoe size answered is the width guide's row, which comes to a deck width among the
 * decks the shop holds; where they skate is the wheel guide's sizes; a plugin's answer to `cbuild_guided_start` comes
 * after both. The kit is the usual one, with the plugin's guides: an 8, an 8.25 and an 8.5 deck.
 */
final class GuidedStartTest extends TestCase {

	private const int KIT = 7;

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'] );
	}

	/**
	 * What the answers recommend, with what `cbuild_guided_start` was asked, and the recommendation short: the shoe
	 * size, the width and the wheels as the build link writes them, and whether no deck is in the row.
	 *
	 * @param array<string, string> $sent   The answers, as the form sends them.
	 * @param callable|null         $answer A plugin's answer to the filter, or none.
	 * @return array{asked: list<array<int, mixed>>, shoe: string, width: string, wheels: string, noDeck: bool, builds: int}
	 */
	private static function recommended( array $sent, ?callable $answer = null ): array {
		$kits = new InMemoryKits();
		$kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$state = ( new BuildState( $kits, new InMemoryShopSettings(), new FakeLogger() ) )->of( self::KIT, array(), null, SlotKey::DECK );
		self::assertNotNull( $state );
		$asked = array();
		$GLOBALS['cbuild_test_filters']['cbuild_guided_start'] = static function ( array $recommended, array $answers, int $kit ) use ( &$asked, $answer ): mixed {
			$asked[] = array( $recommended, $answers, $kit );
			return null === $answer ? $recommended : $answer( $recommended );
		};
		$made = ( new GuidedStart( $kits, new InMemoryPartCards(), new FakeMoney() ) )->of( $state, GuideAnswers::read( $sent ) );
		return array(
			'asked'  => $asked,
			'shoe'   => $made['shoe'],
			'width'  => null === $made['width'] ? '' : $made['width']->decimal(),
			'wheels' => null === $made['wheels'] ? '' : $made['wheels']->from->decimal() . ' ' . ( $made['wheels']->to?->decimal() ?? 'and larger' ),
			'noDeck' => $made['noDeck'],
			'builds' => count( $made['builds'] ),
		);
	}

	// #134: a shoe size and a style come to a deck width the shop holds and the style's wheel sizes, asked of a plugin
	// as the build link writes them, with the answers.
	public function test_the_answers_come_to_a_deck_width_and_wheel_sizes_a_plugin_is_asked_about(): void {
		$made = self::recommended(
			array(
				'shoe'   => '1',
				'style'  => 'park',
				'budget' => '200',
			)
		);
		self::assertSame(
			array(
				array(
					array(
						'width'  => '8in',
						'wheels' => array(
							'from' => '53mm',
							'to'   => '56mm',
						),
					),
					array(
						'shoe'   => 1,
						'style'  => 'park',
						'budget' => 200,
					),
					self::KIT,
				),
			),
			$made['asked']
		);
		self::assertSame( array( '7 to 8', '8.0000', '53.0000 56.0000', false ), array( $made['shoe'], $made['width'], $made['wheels'], $made['noDeck'] ) );
		self::assertSame( 1, $made['builds'], 'one 8 inch deck, so one build' );
	}

	// #134: no answer is any width and any wheel, and no shoe size.
	public function test_no_answer_is_any_width_and_any_wheel(): void {
		$made = self::recommended( array() );
		self::assertSame(
			array(
				array(
					array(
						'width'  => '',
						'wheels' => array(
							'from' => '',
							'to'   => '',
						),
					),
					array(
						'shoe'   => null,
						'style'  => null,
						'budget' => null,
					),
					self::KIT,
				),
			),
			$made['asked']
		);
		self::assertSame( array( '', '', '', false ), array( $made['shoe'], $made['width'], $made['wheels'], $made['noDeck'] ) );
		self::assertSame( 3, $made['builds'] );
	}

	// #134: a shoe size whose widths the shop holds no deck in takes any width, and says so.
	public function test_a_shoe_size_with_no_deck_in_its_widths_takes_any_width(): void {
		$made = self::recommended( array( 'shoe' => '0' ) );
		self::assertSame( array( '6.5 or smaller', '', true ), array( $made['shoe'], $made['width'], $made['noDeck'] ) );
		self::assertSame( '', $made['asked'][0][0]['width'] );
	}

	// #134: a plugin's answer is the recommendation: its sizes as the build link writes them, a size that does not read
	// left out, so the builds take any, and wheels from a size with no largest.
	public function test_a_plugins_answer_is_the_recommendation_and_a_size_that_does_not_read_is_any(): void {
		$made = self::recommended(
			array( 'style' => 'street' ),
			static fn(): array => array(
				'width'  => '8.5in',
				'wheels' => array(
					'from' => '54mm',
					'to'   => '',
				),
			)
		);
		self::assertSame( array( '8.5000', '54.0000 and larger' ), array( $made['width'], $made['wheels'] ) );

		foreach ( array( 'wide', '8.5', '8.5 in', '8.5cm', '1234in', '8.12345in', array( '8.5in' ) ) as $width ) {
			self::assertSame( '', self::recommended( array(), static fn(): array => array( 'width' => $width ) )['width'], var_export( $width, true ) );
		}
		self::assertSame( '', self::recommended( array( 'style' => 'street' ), static fn(): string => 'none' )['wheels'], 'an answer that is no recommendation' );
	}
}
