<?php
/**
 * A spec table as a person sent it, checked.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What became of a table that was added or saved: its ID when it was stored, and everything wrong with it when it
 * was not. Each problem names the row, counted from 1 as the screen shows them (0 is the table itself), the field
 * and a code.
 *
 * @since 0.3.0
 */
final readonly class TableDraft {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param int                                                $id       The table's ID, 0 when a new one was
	 *                                                                     refused.
	 * @param list<array{row: int, field: string, code: string}> $problems What is wrong.
	 */
	public function __construct( public int $id, public array $problems ) {}

	/**
	 * Whether it was stored: nothing was wrong with it.
	 *
	 * @since 0.3.0
	 */
	public function saved(): bool {
		return array() === $this->problems;
	}
}
