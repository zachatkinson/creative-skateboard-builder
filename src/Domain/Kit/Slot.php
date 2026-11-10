<?php
/**
 * One place in a kit where a part goes.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * A slot (spec: Vocabulary): its key, the owner's label and help text, whether a build needs it, how many units of
 * a part one build takes, which products may fill it, and the part it starts on.
 */
final readonly class Slot {

	/**
	 * The most units of a part one build takes in one slot.
	 */
	public const int MAX_UNITS = 99;

	/**
	 * Constructor.
	 *
	 * @param string      $key         The slot's key.
	 * @param string      $label       The owner's name for it.
	 * @param string      $help        The owner's help text, or empty.
	 * @param bool        $required    Whether a build must fill it.
	 * @param int         $units       Units of a part one build takes: 2 trucks, 4 wheels.
	 * @param SlotSources $sources     Which products may fill it.
	 * @param int|null    $defaultPart The part it starts on, if the owner chose one.
	 */
	private function __construct(
		public string $key,
		public string $label,
		public string $help,
		public bool $required,
		public int $units,
		public SlotSources $sources,
		public ?int $defaultPart
	) {}

	/**
	 * From one row of the slots document.
	 *
	 * @since 0.2.0
	 *
	 * @param Fields $fields The row.
	 *
	 * @throws InvalidDocument When a field is missing, of the wrong type or out of range.
	 */
	public static function fromFields( Fields $fields ): self {
		return new self(
			$fields->key( 'key' ),
			$fields->text( 'label' ),
			$fields->textOrEmpty( 'help' ),
			$fields->flag( 'required' ),
			$fields->whole( 'units', 1, self::MAX_UNITS ),
			SlotSources::fromFields( $fields->objectOrEmpty( 'sources' ) ),
			$fields->idOrNull( 'default' )
		);
	}
}
