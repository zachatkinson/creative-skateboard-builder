<?php
/**
 * KitSettings' 3D switches.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitSettings;
use PHPUnit\Framework\TestCase;

/**
 * #139: the 3D view's two switches in `_cbuild_settings`. "3D view" is off unless the kit turns it on, so a kit saved
 * before 1.4.0 shows the builder as it did; "Trick buttons" is on unless the kit turns it off.
 */
final class KitSettingsThreeTest extends TestCase {

	public function test_the_3d_view_is_off_and_the_tricks_on_unless_the_kit_says_otherwise(): void {
		foreach ( array(
			'a kit with no settings'      => null,
			'settings saved before 1.4.0' => array( 'schema_version' => 1, 'guide' => false ),
		) as $case => $document ) {
			$settings = KitSettings::fromDocument( $document );
			self::assertFalse( $settings->view3d, $case . ': the 3D view' );
			self::assertTrue( $settings->tricks, $case . ': the tricks' );
		}
		$on = KitSettings::fromDocument( array( 'schema_version' => 1, 'view_3d' => true ) );
		self::assertTrue( $on->view3d );
		self::assertTrue( $on->tricks );
		self::assertTrue( $on->guided, 'and nothing else changes' );
		$off = KitSettings::fromDocument( array( 'schema_version' => 1, 'view_3d' => false, 'tricks' => false ) );
		self::assertFalse( $off->view3d );
		self::assertFalse( $off->tricks );
	}

	public function test_a_switch_that_is_not_true_or_false_is_refused(): void {
		$this->expectException( InvalidDocument::class );
		KitSettings::fromDocument( array( 'schema_version' => 1, 'view_3d' => 'on' ) );
	}
}
