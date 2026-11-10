<?php
/**
 * The kits before there are any.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Adapter;

use CreativeBranding\SkateboardBuilder\Adapter\NoKits;
use PHPUnit\Framework\TestCase;

/**
 * Until milestone 4 stores kits, the Catalogue check is told there are none (issue #17).
 */
final class NoKitsTest extends TestCase {

	public function test_there_are_no_kits_and_no_slot_has_a_candidate(): void {
		$kits = new NoKits();
		self::assertSame( array(), $kits->all() );
		self::assertSame( array(), $kits->candidates( 12, 'trucks' ) );
	}
}
