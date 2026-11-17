<?php
/**
 * Layer images in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;

/**
 * Images by attachment ID, and the parts that have a layer. Until a test says which parts have one, every part has.
 */
final class InMemoryLayerImages implements LayerImages {

	/**
	 * The images, by attachment ID.
	 *
	 * @var array<int, ImageFacts>
	 */
	public array $images = array();

	/**
	 * The parts with a layer image; null for every part.
	 *
	 * @var list<int>|null
	 */
	public ?array $layered = null;

	/**
	 * Each part's layer image, by part.
	 *
	 * @var array<int, int>
	 */
	public array $attachments = array();

	/**
	 * How many times an image's file was read for its facts.
	 */
	public int $factsRead = 0;

	public function facts( int $attachment ): ?ImageFacts {
		++$this->factsRead;
		return $this->images[ $attachment ] ?? null;
	}

	public function layered( int ...$parts ): array {
		return null === $this->layered ? $parts : array_values( array_intersect( $parts, $this->layered ) );
	}

	public function attachment( int $part ): ?int {
		return $this->attachments[ $part ] ?? null;
	}

	/**
	 * Each call to attachments(), with its parts.
	 *
	 * @var list<list<int>>
	 */
	public array $together = array();

	public function attachments( int ...$parts ): array {
		$this->together[] = $parts;
		return array_intersect_key( $this->attachments, array_flip( $parts ) );
	}

	public function image( int $attachment ): ?array {
		$facts = $this->images[ $attachment ] ?? null;
		return null === $facts ? null : array(
			'url'    => 'https://example.com/layer-' . $attachment . '.png',
			'width'  => $facts->width,
			'height' => $facts->height,
		);
	}
}
