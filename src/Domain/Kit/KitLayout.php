<?php
/**
 * A kit's layout settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * How a kit's builder is laid out, from 1.0.0 (spec: Settings, D-255): the side the stage takes on a desktop, how much
 * of the width the board takes there, which search the steps use, and from 1.1.0 how the board moves. From the `_cbuild_layout` document
 * (`schema/kit-layout.json`); a kit with none has the defaults. There is one layout and no mode switch.
 *
 * @since 1.0.0
 */
final readonly class KitLayout {

	/**
	 * The stage's sides on a desktop, the default first.
	 */
	public const array SIDES = array( 'left', 'right' );

	/**
	 * The board's sizes on a desktop, the default first: large takes 60 % of the width, balanced 50 %.
	 */
	public const array SIZES = array( 'large', 'balanced' );

	/**
	 * The searches, the default first: Creative Search where it is active and the builder's own otherwise, or the
	 * builder's own always.
	 */
	public const array SEARCHES = array( 'creative_search', 'own' );

	/**
	 * The motion, the default first (milestone 11, D-256): playful moves the board's parts, and subtle fades them for
	 * every shopper, as reduced motion does.
	 */
	public const array MOTIONS = array( 'playful', 'subtle' );

	/**
	 * Constructor.
	 *
	 * @param string $stageSide One of SIDES.
	 * @param string $boardSize One of SIZES.
	 * @param string $search    One of SEARCHES.
	 * @param string $motion    One of MOTIONS (1.1.0).
	 */
	private function __construct( public string $stageSide, public string $boardSize, public string $search, public string $motion ) {}

	/**
	 * From the decoded document, or the defaults where there is none.
	 *
	 * @since 1.0.0
	 *
	 * @param mixed $document The decoded `_cbuild_layout` document, or null.
	 *
	 * @throws InvalidDocument When it breaks the document's shape.
	 */
	public static function fromDocument( mixed $document ): self {
		if ( null === $document ) {
			return new self( self::SIDES[0], self::SIZES[0], self::SEARCHES[0], self::MOTIONS[0] );
		}
		$root = Fields::document( $document );
		return new self( $root->choice( 'stage_side', self::SIDES ), $root->choice( 'board_size', self::SIZES ), $root->choice( 'search', self::SEARCHES ), $root->choice( 'motion', self::MOTIONS ) );
	}
}
