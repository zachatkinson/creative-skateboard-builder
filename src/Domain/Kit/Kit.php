<?php
/**
 * A kit's settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Picture\PictureSettings;

/**
 * What the owner set up on a kit's Build tab, as the domain reads it: the slots, the rules, the presets and assembly
 * (spec: Data model, the kit). Built from the four JSON documents once the edge has validated their shape.
 */
final readonly class Kit {

	/**
	 * The most presets a kit has.
	 */
	public const int MAX_PRESETS = 6;

	/**
	 * Constructor.
	 *
	 * @param Slots           $slots    The slots, in step order.
	 * @param RuleSettings    $rules    Which rules run, and their settings.
	 * @param list<Preset>    $presets  The ready builds.
	 * @param Assembly        $assembly Assembly's settings.
	 * @param PictureSettings $picture  The picture's settings (from 0.7.0).
	 * @param KitLayout       $layout   The builder's layout (from 1.0.0).
	 * @param KitSettings     $settings The settings that came after the layout (from 1.2.0, D-354).
	 */
	private function __construct( public Slots $slots, public RuleSettings $rules, public array $presets, public Assembly $assembly, public PictureSettings $picture, public KitLayout $layout, public KitSettings $settings ) {}

	/**
	 * From the kit's documents.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $slots    The decoded `_cbuild_slots` document.
	 * @param mixed $rules    The decoded `_cbuild_rules` document.
	 * @param mixed $presets  The decoded `_cbuild_presets` document.
	 * @param mixed $assembly The decoded `_cbuild_assembly` document.
	 * @param mixed $picture  The decoded `_cbuild_picture` document, from 0.7.0; null for a kit that has none.
	 * @param mixed $layout   The decoded `_cbuild_layout` document, from 1.0.0; null for a kit that has none.
	 * @param mixed $settings The decoded `_cbuild_settings` document, from 1.2.0; null for a kit that has none.
	 *
	 * @throws InvalidDocument When a document is not valid, a preset names a slot the kit lacks or leaves a required one empty.
	 */
	public static function fromDocuments( mixed $slots, mixed $rules, mixed $presets, mixed $assembly, mixed $picture = null, mixed $layout = null, mixed $settings = null ): self {
		$kitSlots = Slots::fromDocument( $slots );
		$ready    = array();
		foreach ( Fields::document( $presets )->rows( 'presets', self::MAX_PRESETS ) as $row ) {
			$preset = Preset::fromFields( $row );
			foreach ( $preset->picks as $slot => $part ) {
				if ( null === $kitSlots->get( $slot ) ) {
					$refused = InvalidDocument::because( DocumentError::UnknownSlot, $row->path( 'picks.' . $slot ) );
					throw $refused;
				}
			}
			foreach ( $kitSlots->all() as $slot ) {
				if ( $slot->required && ! isset( $preset->picks[ $slot->key ] ) ) {
					$refused = InvalidDocument::because( DocumentError::SlotNotFilled, $row->path( 'picks.' . $slot->key ) );
					throw $refused;
				}
			}
			$ready[] = $preset;
		}
		// The picture's order passes over a slot the kit no longer has: removing a slot must not leave a kit that
		// no shopper can open for want of a picture setting (D-192).
		$drawn = PictureSettings::fromDocument( $picture )->only( ...array_map( static fn( Slot $slot ): string => $slot->key, $kitSlots->all() ) );
		return new self( $kitSlots, RuleSettings::fromDocument( $rules ), $ready, Assembly::fromDocument( $assembly ), $drawn, KitLayout::fromDocument( $layout ), KitSettings::fromDocument( $settings ) );
	}
}
