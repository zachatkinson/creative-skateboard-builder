<?php
/**
 * A kit's settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

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
	 * @param Slots        $slots    The slots, in step order.
	 * @param RuleSettings $rules    Which rules run, and their settings.
	 * @param list<Preset> $presets  The ready builds.
	 * @param Assembly     $assembly Assembly's settings.
	 */
	private function __construct( public Slots $slots, public RuleSettings $rules, public array $presets, public Assembly $assembly ) {}

	/**
	 * From the kit's four documents.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $slots    The decoded `_cbuild_slots` document.
	 * @param mixed $rules    The decoded `_cbuild_rules` document.
	 * @param mixed $presets  The decoded `_cbuild_presets` document.
	 * @param mixed $assembly The decoded `_cbuild_assembly` document.
	 *
	 * @throws InvalidDocument When a document is not valid, or a preset names a slot the kit lacks or leaves a required one empty.
	 */
	public static function fromDocuments( mixed $slots, mixed $rules, mixed $presets, mixed $assembly ): self {
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
		return new self( $kitSlots, RuleSettings::fromDocument( $rules ), $ready, Assembly::fromDocument( $assembly ) );
	}
}
