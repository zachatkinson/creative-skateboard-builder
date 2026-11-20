<?php
/**
 * A ready build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

/**
 * A build the owner sets up for shoppers to start from (spec: Vocabulary, Preset): its name, a line on who it is
 * for, and a part's id for each slot it fills.
 */
final readonly class Preset {

	/**
	 * The most characters a name and a description may have, as the schema says.
	 */
	public const int MAX_NAME = 60;

	public const int MAX_DESCRIPTION = 160;

	/**
	 * Constructor.
	 *
	 * @param string             $name        The owner's name for it.
	 * @param string             $description A line of description, or empty.
	 * @param array<string, int> $picks       A part's id by slot key.
	 * @param int|null           $image       From 1.0.0, a rider photo for its tile, an attachment's id, or null for the
	 *                                        build's own picture (D-252).
	 */
	private function __construct( public string $name, public string $description, public array $picks, public ?int $image = null ) {}

	/**
	 * From one row of the presets document.
	 *
	 * @since 0.2.0
	 *
	 * @param Fields $fields The row.
	 *
	 * @throws InvalidDocument When a field is missing or of the wrong type, or a pick's slot is not a key or its part not an id.
	 */
	public static function fromFields( Fields $fields ): self {
		$chosen = $fields->object( 'picks' );
		$picks  = array();
		foreach ( $chosen->names() as $slot ) {
			if ( ! Fields::isKey( $slot ) ) {
				$refused = InvalidDocument::because( DocumentError::BadKey, $chosen->path( $slot ) );
				throw $refused;
			}
			$picks[ $slot ] = $chosen->whole( $slot, 1, PHP_INT_MAX );
		}
		return new self( $fields->text( 'name', self::MAX_NAME ), $fields->textOrEmpty( 'description', self::MAX_DESCRIPTION ), $picks, $fields->idOrNull( 'image' ) );
	}
}
