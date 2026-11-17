<?php
/**
 * A kit's picture settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Picture;

use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Fields;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;

use function array_intersect;
use function array_search;
use function array_values;
use function count;

/**
 * The kit's `_cbuild_picture` document (spec: Data model): the slots' layer order, from the bottom of the stack, and
 * the trucks' anchor on a deck that gives no wheelbase. A slot the order leaves out is drawn after those it names,
 * in the layout's own order, and with no anchor the layout guesses the wheelbase from the deck's shape. A kit saved
 * before 0.7.0 has no document, which reads as no order and no anchor: drawn as it always was (D-192).
 */
final readonly class PictureSettings {

	/**
	 * The shortest and the longest wheelbase the anchor takes: 10 in and 20 in, past any deck sold.
	 */
	public const int MIN_WHEELBASE = 100000;

	public const int MAX_WHEELBASE = 200000;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param list<string> $order     Slot keys, from the bottom of the stack.
	 * @param Measure|null $wheelbase The trucks' anchor on a deck that gives no wheelbase, in inches.
	 */
	private function __construct( public array $order, public ?Measure $wheelbase ) {}

	/**
	 * The settings from the decoded document; none for a kit that has no document.
	 *
	 * @since 0.7.0
	 *
	 * @param mixed $document The decoded document, or null.
	 *
	 * @throws InvalidDocument When it breaks the document's shape.
	 */
	public static function fromDocument( mixed $document ): self {
		if ( null === $document ) {
			return new self( array(), null );
		}
		$root      = Fields::document( $document );
		$order     = $root->keys( 'order', Slots::MAX );
		$wheelbase = $root->amountOrNull( 'wheelbase' );
		if ( null !== $wheelbase && ( $wheelbase < self::MIN_WHEELBASE || $wheelbase > self::MAX_WHEELBASE ) ) {
			$refused = InvalidDocument::because( DocumentError::OutOfRange, $root->path( 'wheelbase' ) );
			throw $refused;
		}
		return new self( $order, null === $wheelbase ? null : Measure::inches( $wheelbase ) );
	}

	/**
	 * These settings with the order cut to the slots a kit has.
	 *
	 * @since 0.7.0
	 *
	 * @param string ...$slots The kit's slot keys.
	 */
	public function only( string ...$slots ): self {
		return new self( array_values( array_intersect( $this->order, $slots ) ), $this->wheelbase );
	}

	/**
	 * The place a slot's layers take in the stack: its place in the order, or after every slot the order names.
	 *
	 * @since 0.7.0
	 *
	 * @param string $slot The slot's key.
	 */
	public function rank( string $slot ): int {
		$at = array_search( $slot, $this->order, true );
		return false === $at ? count( $this->order ) : $at;
	}
}
