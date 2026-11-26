<?php
/**
 * A kit's settings from 1.2.0.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Guide\WheelGuide;

/**
 * The kit's settings that came after its layout (D-354): from 1.2.0 the page its builder is on (#113, D-348), whether
 * its parts' pages show "Build a complete with this deck" (#115, D-349), the width guide (#114, D-350) and the
 * assembly time in business days (#142, D-375); from 1.3.0 the wheel guide the guided start reads (#134), and whether
 * the builder shows "Help me choose", the cards' ready-build picks and their ratings (D-409). From the
 * `_cbuild_settings` document (`schema/kit-settings.json`), which the release before never reads, so a rollback leaves
 * the kit readable; a kit with none has the defaults. A setting this release does not know is passed over, so a
 * later release can add one here.
 *
 * @since 1.2.0
 * @since 1.3.0 The wheel guide, and the switches for Help me choose and the cards' picks and ratings.
 */
final readonly class KitSettings {

	/**
	 * The most characters the assembly time is read from: as many as the lead time takes, so anything typed is read and named.
	 */
	private const int TYPED = Assembly::MAX_LEAD_TIME;

	/**
	 * Constructor.
	 *
	 * @param bool          $startButton Whether the kit may place "Build a complete with this deck" on its parts'
	 *                                   pages. Off, another kit that offers the part may still place it.
	 * @param int|null      $builderPage The page the kit's builder is on, by its ID, or null for the kit's own page.
	 *                                   Whether the page may be used is asked each time it is.
	 * @param SizeGuide     $guide       The width guide under the deck step's width question.
	 * @param LeadDays|null $leadDays    How many business days putting a build together takes, or null where the kit
	 *                                   says none (#142).
	 * @param WheelGuide    $wheels      The wheel sizes the guided start recommends for each riding style (1.3.0, #134).
	 * @param bool          $swaps       Whether the builder offers smart swaps (from 1.3.0, #135, D-397). Off, the
	 *                                   state route offers none and the builder shows no swap control.
	 * @param bool          $guided      Whether the builder offers "Help me choose" at its start (1.3.0, `guide`, D-409).
	 * @param bool          $cardPicks   Whether a card says the ready builds that pick its part (1.3.0, `picks`, D-409).
	 * @param bool          $cardRatings Whether a card shows its part's rating (1.3.0, `ratings`, D-409).
	 * @param bool          $view3d      Whether the builder offers its 3D view (1.4.0, `view_3d`, #139). Off by default.
	 * @param bool          $tricks      Whether the 3D view offers its trick buttons (1.4.0, `tricks`, #139). On by default.
	 */
	private function __construct( public bool $startButton, public ?int $builderPage, public SizeGuide $guide, public ?LeadDays $leadDays, public WheelGuide $wheels, public bool $swaps, public bool $guided, public bool $cardPicks, public bool $cardRatings, public bool $view3d = false, public bool $tricks = true ) {}

	/**
	 * From the decoded document, or the defaults where there is none.
	 *
	 * @since 1.2.0
	 *
	 * @param mixed $document The decoded `_cbuild_settings` document, or null.
	 *
	 * @throws InvalidDocument When it breaks the document's shape.
	 */
	public static function fromDocument( mixed $document ): self {
		if ( null === $document ) {
			return new self( true, null, SizeGuide::plugins(), null, WheelGuide::plugins(), true, true, true, true );
		}
		$root = Fields::document( $document );
		// A kit with no start_button shows the button on its parts' pages, as one saved before 1.2.0 did.
		$starts = ! $root->has( 'start_button' ) || $root->flag( 'start_button' );
		// One with no width_guide shows the plugin's rows.
		$guide = $root->has( 'width_guide' ) ? SizeGuide::fromFields( $root->object( 'width_guide' ) ) : SizeGuide::plugins();
		// One with no lead_days has no assembly time (#142).
		$days = $root->has( 'lead_days' ) ? LeadDays::kept( $root->text( 'lead_days', self::TYPED ), $root->path( 'lead_days' ) ) : null;
		// One with no wheel_guide, as every kit saved before 1.3.0, has the plugin's wheel sizes.
		$wheels = $root->has( 'wheel_guide' ) ? WheelGuide::fromFields( $root->object( 'wheel_guide' ) ) : WheelGuide::plugins();
		// One with no swaps offers them, as one saved before 1.3.0 is read.
		$swaps = ! $root->has( 'swaps' ) || $root->flag( 'swaps' );
		// One with no guide, picks or ratings shows each, as one saved before 1.3.0 is read (D-409).
		$shows = static fn( string $key ): bool => ! $root->has( $key ) || $root->flag( $key );
		// One with no builder_page has its builder on the kit's own page.
		// One with no view_3d has no 3D view, so the update to 1.4.0 changes nothing; one with no tricks offers them (#139).
		$view3d = $root->has( 'view_3d' ) && $root->flag( 'view_3d' );
		return new self( $starts, $root->idOrNull( 'builder_page' ), $guide, $days, $wheels, $swaps, $shows( 'guide' ), $shows( 'picks' ), $shows( 'ratings' ), $view3d, $shows( 'tricks' ) );
	}
}
