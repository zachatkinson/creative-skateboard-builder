<?php
/**
 * A kit's documents as a person sent them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the Build tab sent, sorted: the documents to save, the ones refused with what was typed so the tab can show
 * it again, everything wrong, and what the owner should know of a kit that was saved.
 *
 * @since 0.4.0
 */
final readonly class KitDraft {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param array<string, array<string, mixed>>                                                      $documents The documents to save, by name.
	 * @param array<string, array<string, mixed>>                                                      $refused   The documents not saved, as built from what was typed, by name.
	 * @param list<array{document: string, code: string, path: string, values: array<string, string>}> $problems  What is wrong: the document's name, a code, the field's path in the document, and what the words need.
	 * @param list<array{code: string, slot: string, count: int}>                                      $notes     What to know of the kit as it now stands.
	 * @param list<array{path: string, values: array<string, string>}>                                 $unoffered Parts that presets sent unchanged hold and their slots don't offer now (issue #62): the field's path, and the preset, slot and part.
	 */
	public function __construct( public array $documents, public array $refused, public array $problems, public array $notes, public array $unoffered = array() ) {}

	/**
	 * Whether a document was sent and not saved.
	 *
	 * @since 0.4.0
	 *
	 * @param string $key The document's name.
	 */
	public function refuses( string $key ): bool {
		return isset( $this->refused[ $key ] );
	}
}
