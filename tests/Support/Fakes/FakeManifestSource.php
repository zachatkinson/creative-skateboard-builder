<?php
/**
 * A manifest source the tests set.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Updates\Manifest;
use CreativeBranding\SkateboardBuilder\Updates\ManifestSource;

/**
 * Answers latest() with one manifest and fresh() with another, and counts the fresh reads.
 */
final class FakeManifestSource implements ManifestSource {

	public int $fresh = 0;

	/**
	 * Constructor.
	 *
	 * @param Manifest|null $latest What latest() answers.
	 * @param Manifest|null $after  What fresh() answers; $latest when null.
	 */
	public function __construct( private ?Manifest $latest, private ?Manifest $after = null ) {}

	/**
	 * The cached answer.
	 */
	public function latest(): ?Manifest {
		return $this->latest;
	}

	/**
	 * The answer read again.
	 */
	public function fresh(): ?Manifest {
		++$this->fresh;
		$this->latest = $this->after ?? $this->latest;
		return $this->latest;
	}
}
