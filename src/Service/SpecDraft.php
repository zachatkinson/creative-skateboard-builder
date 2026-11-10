<?php
/**
 * A part's specs as a person sent them, checked.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What to store for one part, and everything that was wrong with what was sent. A value that is wrong is not
 * stored; what the part had for it is kept, and the rest is stored as usual (spec: Admin, Saving).
 *
 * @since 0.3.0
 */
final readonly class SpecDraft {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, string>                                $fields   The part's own values to store, by spec key,
	 *                                                                       each as the index writes it.
	 * @param int                                                  $table    The spec table the part names, 0 for none.
	 * @param string                                               $size     The size it names in it, '' for none.
	 * @param list<array{key: string, code: string, text: string}> $problems What is wrong: the spec key (or
	 *                                                                       `SpecWriter::TABLE`), a code and what
	 *                                                                       was sent.
	 */
	public function __construct( public array $fields, public int $table, public string $size, public array $problems ) {}
}
