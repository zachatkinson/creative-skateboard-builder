<?php
/**
 * An admin form: its fields, its cards and what the server refused.
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
 * The one description a screen's template and its script share (M15's principles 1 to 3). The script mounts DataForm
 * over the server's form, from this description, and writes each value back into its field's input, so the form posts
 * what it always did, with the same nonce and the same checks.
 *
 * @since 1.5.0
 */
final class Form {

	/**
	 * A form.
	 *
	 * @param string      $id     Its id.
	 * @param list<Field> $fields Its fields.
	 * @param list<Card>  $cards  Its cards.
	 * @param Errors      $errors What the server refused.
	 * @param bool        $leave  Whether the kit warns before the page is left with a change.
	 */
	private function __construct(
		public readonly string $id,
		private readonly array $fields,
		private readonly array $cards,
		private readonly Errors $errors,
		private readonly bool $leave,
	) {}

	/**
	 * A form.
	 *
	 * @since 1.5.0
	 *
	 * @param string      $id     Its id.
	 * @param list<Field> $fields Its fields, each id once.
	 * @param list<Card>  $cards  Its cards, naming only its fields; none for a form with no cards.
	 *
	 * @throws InvalidArgumentException A form that cannot be drawn.
	 */
	public static function of( string $id, array $fields, array $cards ): self {
		$ids = array();
		foreach ( $fields as $field ) {
			if ( isset( $ids[ $field->id ] ) ) {
				throw new InvalidArgumentException( esc_html( sprintf( 'The form "%s" has two fields "%s".', $id, $field->id ) ) );
			}
			$ids[ $field->id ] = true;
		}
		foreach ( $fields as $field ) {
			$condition = $field->condition();
			if ( null !== $condition && ! isset( $ids[ $condition ] ) ) {
				throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" is shown with "%s", which the form has not.', $field->id, $condition ) ) );
			}
		}
		foreach ( $cards as $card ) {
			if ( '' !== $card->summary && ! isset( $ids[ $card->summary ] ) ) {
				throw new InvalidArgumentException( esc_html( sprintf( 'The card "%s" is summed up by "%s", which the form has not.', $card->id, $card->summary ) ) );
			}
			foreach ( $card->fields as $named ) {
				if ( ! isset( $ids[ $named ] ) ) {
					throw new InvalidArgumentException( esc_html( sprintf( 'The card "%s" names "%s", which the form has not.', $card->id, $named ) ) );
				}
			}
		}
		return new self( $id, $fields, $cards, Errors::none(), true );
	}

	/**
	 * The form with what the server refused, each closed card holding a refused field opened.
	 *
	 * @since 1.5.0
	 *
	 * @param Errors $errors The refusals.
	 */
	public function failed( Errors $errors ): self {
		$errors   = $errors->for( $this );
		$messages = $errors->toArray();
		$cards    = array_map(
			static fn( Card $card ): Card => array() === array_intersect( $card->fields, array_keys( $messages ) ) ? $card : $card->opened(),
			$this->cards
		);
		return new self( $this->id, $this->fields, $cards, $errors, $this->leave );
	}

	/**
	 * The form on a page that warns before it is left by itself, as WooCommerce's Settings do once a control's `change`
	 * bubbles: the kit adds no second warning (docs/ADMIN-KIT.md).
	 *
	 * @since 1.5.0
	 */
	public function leftToHost(): self {
		return new self( $this->id, $this->fields, $this->cards, $this->errors, false );
	}

	/**
	 * What the server refused, in the fields' order: each field's id, its label and the message.
	 *
	 * @since 1.5.0
	 *
	 * @return list<array{id: string, label: string, message: string}>
	 */
	public function refusals(): array {
		$messages = $this->errors->toArray();
		$refused  = array();
		foreach ( $this->fields as $field ) {
			if ( isset( $messages[ $field->id ] ) ) {
				$refused[] = array(
					'id'      => $field->id,
					'label'   => $field->label,
					'message' => $messages[ $field->id ],
				);
			}
		}
		return $refused;
	}

	/**
	 * Its fields' ids.
	 *
	 * @since 1.5.0
	 *
	 * @return list<string>
	 */
	public function ids(): array {
		return array_map( static fn( Field $field ): string => $field->id, $this->fields );
	}

	/**
	 * The form as the script reads it.
	 *
	 * @since 1.5.0
	 *
	 * @return array{id: string, fields: list<array<string, mixed>>, cards: list<array<string, mixed>>, errors: array<string, string>, leave: bool}
	 */
	public function toArray(): array {
		return array(
			'id'     => $this->id,
			'fields' => array_map( static fn( Field $field ): array => $field->toArray(), $this->fields ),
			'cards'  => array_map( static fn( Card $card ): array => $card->toArray(), $this->cards ),
			'errors' => $this->errors->toArray(),
			'leave'  => $this->leave,
		);
	}
}
