<?php
/**
 * Renditions in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\LayerRenditions;

/**
 * Records what it was asked to make, and answers that it made it.
 */
final class InMemoryLayerRenditions implements LayerRenditions {

	/**
	 * What was asked to be made, in order: an attachment and its sizes.
	 *
	 * @var list<array{int, list<array{int, int}>}>
	 */
	public array $made = array();

	public function make( int $attachment, array $sizes ): void {
		$this->made[] = array( $attachment, $sizes );
	}

	public function sources( int $attachment, array $sizes ): array {
		$sources = array();
		foreach ( $this->made as $made ) {
			if ( $made[0] === $attachment ) {
				foreach ( $sizes as $size ) {
					if ( in_array( $size, $made[1], true ) ) {
						$sources['image/webp'][] = array(
							'url'    => 'https://example.com/' . $attachment . '-' . $size[0] . 'x' . $size[1] . '.webp',
							'width'  => $size[0],
							'height' => $size[1],
						);
					}
				}
			}
		}
		return $sources;
	}

	/**
	 * What was forgotten, in order.
	 *
	 * @var list<int>
	 */
	public array $forgotten = array();

	public function forget( int $attachment ): void {
		$this->forgotten[] = $attachment;
		$this->made        = array_values( array_filter( $this->made, static fn( array $made ): bool => $made[0] !== $attachment ) );
	}
}
