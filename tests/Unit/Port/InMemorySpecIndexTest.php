<?php
/**
 * The in-memory specs index.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Port;

use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemorySpecIndex;
use CreativeBranding\SkateboardBuilder\Tests\Support\SpecIndexCases;
use PHPUnit\Framework\TestCase;

/**
 * The fake passes the port's cases, as the repository does in the integration suite.
 */
final class InMemorySpecIndexTest extends TestCase {

	use SpecIndexCases;

	protected function index(): SpecIndex {
		return new InMemorySpecIndex();
	}
}
