<?php
/**
 * ClosestPart.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Fit;

use CreativeBranding\SkateboardBuilder\Domain\Fit\ClosestPart;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * The closest part that fits, which the change list and a saved build's sold-out parts both ask for (D-254). Its
 * order (nearest spec, same brand, nearest price, in stock) is tested through the change list in ChangePlannerTest.
 */
final class ClosestPartTest extends TestCase {

	public function test_a_saved_builds_sold_out_wheel_gets_the_nearest_wheel_that_fits_the_rest_of_the_build(): void {
		$build  = Builds::complete( array( SlotKey::WHEELS => Builds::wheel( '54' ) ) );
		$wheel  = static fn( int $id, string $diameter, string $brand, int $price ): Part => Parts::of( array( 'wheel_diameter' => $diameter, 'set_size' => 4 ), $id, $price, $brand );
		$option = ( new ClosestPart( FitEngine::standard() ) )->find( KitDocuments::kit(), SlotKey::WHEELS, Builds::wheel( '54' ), $build, array( $wheel( 1, '60', 'spitfire', 4000 ), $wheel( 2, '53', 'bones', 9000 ), $wheel( 3, '56', 'spitfire', 4000 ) ) );
		self::assertSame( 2, $option?->part->id, '53 mm is the nearest to 54 mm' );
	}

	public function test_with_nothing_that_fits_there_is_no_closest_part(): void {
		$finder = new ClosestPart( FitEngine::standard() );
		self::assertNull( $finder->find( KitDocuments::kit(), SlotKey::WHEELS, Builds::wheel( '54' ), Builds::complete(), array() ) );
	}
}
