<?php
/**
 * Checks a layer image for a part, as the Layer image field and the specs CSV both do.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerImage;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\PartLayers;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One set of rules for a layer image, wherever it is set (D-190, D-287): an image in the Media Library, of a type and
 * size the picture can draw at the size it draws this part, with transparent corners. A problem is a code and the
 * text its sentence needs (`Admin\Sentences::layer()`), so the field and an import's results say the same.
 *
 * @since 1.0.0
 */
final class LayerCheck {

	/**
	 * Why a layer image is refused, beside the domain's `LayerError`s.
	 */
	public const NOT_IMAGE = 'layer_not_image';
	public const NOT_FOUND = 'layer_not_found';
	public const AMBIGUOUS = 'layer_ambiguous';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param LayerImages $images  The Media Library's images.
	 * @param SpecIndexer $indexer The specs in use, for the size the picture draws a part at.
	 * @param Kits        $kits    The kits, for the slot a part fills.
	 */
	public function __construct( private LayerImages $images, private SpecIndexer $indexer, private Kits $kits ) {}

	/**
	 * The image a cell names: digits are an attachment's ID, anything else an image's file name, which must name one
	 * image alone.
	 *
	 * @since 1.0.0
	 *
	 * @param string     $cell   What the cell holds, trimmed.
	 * @param PartLayers $layers The Media Library's images by name.
	 * @return array{0: int, 1: list<array{code: string, text: string}>} The attachment's ID, 0 when it names none, and why.
	 */
	public static function named( string $cell, PartLayers $layers ): array {
		if ( ctype_digit( $cell ) ) {
			return array( (int) $cell, array() );
		}
		$found = $layers->named( $cell );
		if ( 1 === count( $found ) ) {
			return array( $found[0], array() );
		}
		return array(
			0,
			array(
				array() === $found
					? array(
						'code' => self::NOT_FOUND,
						'text' => $cell,
					)
					: array(
						'code' => self::AMBIGUOUS,
						'text' => $cell . "\t" . implode( ', ', $found ),
					),
			),
		);
	}

	/**
	 * What is wrong with an image as this part's layer image.
	 *
	 * @since 1.0.0
	 *
	 * @param int                  $attachment The image's attachment ID.
	 * @param array{int, int}|null $size       The size the picture draws the part at, as size() gives it; the Part
	 *                                         specs panel gives its own, from the page's parts resolved together.
	 * @param bool                 $cutOut     Whether its corners must be clear, as cutOut() gives it: not a grip's sheet
	 *                                         (1.1.0, F18).
	 * @return list<array{code: string, text: string}> None when it may be the part's layer image.
	 */
	public function problems( int $attachment, ?array $size, bool $cutOut = true ): array {
		$facts = $attachment > 0 ? $this->images->facts( $attachment ) : null;
		if ( null === $facts ) {
			return array(
				array(
					'code' => self::NOT_IMAGE,
					'text' => (string) $attachment,
				),
			);
		}
		$needs = null === $size ? array( 0, 0 ) : LayerImage::needs( $size );
		$found = array();
		foreach ( LayerImage::problems( $facts, $size, $cutOut ) as $problem ) {
			$found[] = array(
				'code' => $problem->value,
				'text' => $facts->type . "\t" . $needs[0] . "\t" . $needs[1],
			);
		}
		return $found;
	}

	/**
	 * Whether a part's layer image is a cut-out with clear corners: every drawn part's but a grip's, whose image is its
	 * whole sheet (1.1.0, F18, #103). A part in no drawn slot is held to the cut-out's rule.
	 *
	 * @since 1.1.0
	 *
	 * @param int $part The product's or variation's ID.
	 */
	public function cutOut( int $part ): bool {
		$slots = array_values( array_intersect( Layout::DRAWN, $this->kits->slotsOf( $part, ...array_column( $this->kits->all(), 'kit' ) ) ) );
		return array() === $slots || Layout::cutOut( $slots[0] );
	}

	/**
	 * The size the picture draws a part at, from the first slot it fills that the picture draws and the specs in use.
	 *
	 * @since 1.0.0
	 *
	 * @param int $part The product's or variation's ID.
	 * @return array{int, int}|null Null when it fills no such slot, or lacks the spec the size comes from.
	 */
	public function size( int $part ): ?array {
		// Read on each check, not kept: one process runs many queued jobs, and a kit may change between them.
		$slots = array_values( array_intersect( Layout::DRAWN, $this->kits->slotsOf( $part, ...array_column( $this->kits->all(), 'kit' ) ) ) );
		if ( array() === $slots || $part <= 0 ) {
			return null;
		}
		$texts = array();
		foreach ( $this->indexer->resolve( $part )[ $part ]->resolution->rows() as $row ) {
			$texts[ $row->key->value ] = $row->text;
		}
		return Layout::drawn( $slots[0], Specs::fromIndex( $texts ) );
	}
}
