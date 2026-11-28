<?php
/**
 * One card of an admin form: one idea, its fields, open or closed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin\Form;

use InvalidArgumentException;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * M15's principle 4: one idea per card, most used first, rare settings in a closed card that shows in its summary what
 * it holds. The summary is a field of the form, often a read-only one written for it, as DataForm's card layout names
 * fields, not text (docs/research/admin-handbooks-2026.md). A closed card with a field the server refused opens
 * (Form::failed()).
 *
 * @since 1.5.0
 */
final class Card {

	/**
	 * Whether the card starts open.
	 *
	 * @var bool
	 */
	private bool $open = true;

	/**
	 * The id of the field a closed card shows as its summary (DataForm's card `summary`), or '' for none.
	 *
	 * @var string
	 */
	public string $summary = '';

	/**
	 * A card.
	 *
	 * @param string       $id     Its id.
	 * @param string       $label  Its heading.
	 * @param list<string> $fields Its fields' ids, in order.
	 */
	private function __construct(
		public readonly string $id,
		private readonly string $label,
		public readonly array $fields,
	) {}

	/**
	 * A card, open.
	 *
	 * @since 1.5.0
	 *
	 * @param string       $id     Its id.
	 * @param string       $label  Its heading, translated.
	 * @param list<string> $fields Its fields' ids, in order: one at least.
	 *
	 * @throws InvalidArgumentException A card with no fields.
	 */
	public static function of( string $id, string $label, array $fields ): self {
		if ( array() === $fields ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The card "%s" has no field.', $id ) ) );
		}
		return new self( $id, $label, $fields );
	}

	/**
	 * The card, closed until opened.
	 *
	 * @since 1.5.0
	 *
	 * @param string $summary The id of the field shown as its summary while it is closed: a field of the form.
	 */
	public function closed( string $summary ): self {
		$copy          = clone $this;
		$copy->open    = false;
		$copy->summary = $summary;
		return $copy;
	}

	/**
	 * The card, open.
	 *
	 * @since 1.5.0
	 */
	public function opened(): self {
		$copy       = clone $this;
		$copy->open = true;
		return $copy;
	}

	/**
	 * The card as the script reads it.
	 *
	 * @since 1.5.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		$card = array(
			'id'     => $this->id,
			'label'  => $this->label,
			'fields' => $this->fields,
			'open'   => $this->open,
		);
		if ( ! $this->open ) {
			$card['summary'] = $this->summary;
		}
		return $card;
	}
}
