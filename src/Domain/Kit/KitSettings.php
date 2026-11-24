<?php
/**
 * A kit's settings from 1.2.0.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * The kit's settings that came after its layout (D-354): from 1.2.0 the page its builder is on (#113, D-348), whether
 * its parts' pages show "Build a complete with this deck" (#115, D-349), the width guide (#114, D-350) and the
 * assembly time in business days (#142, D-375). From the
 * `_cbuild_settings` document (`schema/kit-settings.json`), which the release before never reads, so a rollback leaves
 * the kit readable; a kit with none has the defaults. A setting this release does not know is passed over, so a
 * later release can add one here.
 *
 * @since 1.2.0
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
	 */
	private function __construct( public bool $startButton, public ?int $builderPage, public SizeGuide $guide, public ?LeadDays $leadDays ) {}

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
			return new self( true, null, SizeGuide::plugins(), null );
		}
		$root = Fields::document( $document );
		// A kit with no start_button shows the button on its parts' pages, as one saved before 1.2.0 did.
		$starts = ! $root->has( 'start_button' ) || $root->flag( 'start_button' );
		// One with no width_guide shows the plugin's rows.
		$guide = $root->has( 'width_guide' ) ? SizeGuide::fromFields( $root->object( 'width_guide' ) ) : SizeGuide::plugins();
		// One with no lead_days has no assembly time (#142).
		$days = $root->has( 'lead_days' ) ? LeadDays::kept( $root->text( 'lead_days', self::TYPED ), $root->path( 'lead_days' ) ) : null;
		// One with no builder_page has its builder on the kit's own page.
		return new self( $starts, $root->idOrNull( 'builder_page' ), $guide, $days );
	}
}
