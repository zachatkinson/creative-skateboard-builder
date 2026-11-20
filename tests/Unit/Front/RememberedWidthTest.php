<?php
/**
 * A signed-in customer's deck width, from their saved builds.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Front\RememberedWidth;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\CountingUuids;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySavedBuilds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\TestCase;

/**
 * Spec (configurator): The deck's width first, "Remembered": the width of the deck in the build a customer saved last,
 * where this kit offers that deck, and nothing otherwise (D-242).
 */
final class RememberedWidthTest extends TestCase {

	private const int KIT = 77;

	private const int USER = 7;

	private InMemorySavedBuilds $store;

	private RememberedWidth $remembered;

	protected function setUp(): void {
		$kits = new InMemoryKits();
		$kits->add( self::KIT, Shop::kit(), Shop::parts() );
		$this->store      = new InMemorySavedBuilds();
		$saved            = new SavedBuilds( $this->store, new BuildState( $kits, new InMemoryShopSettings(), new FakeLogger() ), new CountingUuids(), new FakeClock(), new FakeLogger() );
		$this->remembered = new RememberedWidth( $saved, $kits );
		$GLOBALS['cbuild_test_user'] = self::USER;
	}

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_user'] );
	}

	private function saved( int $deck ): void {
		$at = new DateTimeImmutable( '2026-11-02', new DateTimeZone( 'UTC' ) );
		$this->store->put( new SavedBuild( 'a', self::USER, self::KIT, array( SlotKey::DECK => $deck ), true, 'Mine', $at, $at ) );
	}

	public function test_the_width_is_the_deck_of_the_build_saved_last(): void {
		$this->saved( 102 );
		self::assertSame( '8.25in', $this->remembered->of( self::KIT ) );
	}

	public function test_a_customer_with_no_saved_build_has_no_width(): void {
		self::assertSame( '', $this->remembered->of( self::KIT ) );
	}

	public function test_a_kit_the_shop_does_not_have_brings_no_width(): void {
		$this->saved( 102 );
		self::assertSame( '', $this->remembered->of( 999 ) );
	}

	public function test_a_deck_this_kit_does_not_offer_brings_no_width(): void {
		$this->saved( 999 );
		self::assertSame( '', $this->remembered->of( self::KIT ) );
	}
}
