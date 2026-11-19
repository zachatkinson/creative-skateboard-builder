<?php
/**
 * The parts' own layer images, held in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\PartLayers;

/**
 * Parts with their own layer image, the variations among them, and the Media Library's images by file name.
 */
final class InMemoryPartLayers implements PartLayers {

	/**
	 * Each part's own layer image, 0 for none.
	 *
	 * @var array<int, int>
	 */
	public array $own = array();

	/**
	 * The parts that are variations.
	 *
	 * @var list<int>
	 */
	public array $variations = array();

	/**
	 * Each image's file name.
	 *
	 * @var array<int, string>
	 */
	public array $files = array();

	/**
	 * Each assignment, in order: the part and the image.
	 *
	 * @var list<array{int, int}>
	 */
	public array $assigned = array();

	/**
	 * The parts each owned() was asked for.
	 *
	 * @var list<list<int>>
	 */
	public array $asked = array();

	public function owned( int ...$parts ): array {
		$this->asked[] = $parts;
		$owned         = array();
		foreach ( $parts as $part ) {
			if ( isset( $this->own[ $part ] ) ) {
				$owned[ $part ] = array(
					'own'       => $this->own[ $part ],
					'variation' => in_array( $part, $this->variations, true ),
				);
			}
		}
		return $owned;
	}

	public function named( string $file ): array {
		$found = array_keys( $this->files, $file, true );
		sort( $found );
		return $found;
	}

	public function fileName( int $attachment ): string {
		return $this->files[ $attachment ] ?? '';
	}

	public function assign( int $part, int $attachment ): void {
		$this->assigned[]   = array( $part, $attachment );
		$this->own[ $part ] = $attachment;
	}
}
