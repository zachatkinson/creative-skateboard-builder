<?php
/**
 * Identifiers that can be told apart.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Uuids;

/**
 * Gives build-1, build-2 and so on.
 */
final class CountingUuids implements Uuids {

	public int $given = 0;

	public function next(): string {
		return 'build-' . ( ++$this->given );
	}
}
