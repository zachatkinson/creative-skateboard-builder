<?php
/**
 * One field of an admin form, described once for the server's template and the script's DataForm.
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
 * A field's id, the input name its value is posted under, its label, its DataForm type and control, its help, its
 * choices and the limits the server holds it to (M15's principle 2, docs/ADMIN-KIT.md). The strings arrive translated:
 * the kit has no words of its own. Each method returns a new field, so a description shared between screens is never
 * changed by one of them. A description that would draw a broken form is refused where it is written.
 *
 * The limits are hints for the script; the server's own check decides, and its answer comes back through Errors.
 *
 * @since 1.5.0
 */
final class Field {

	/**
	 * The field types DataForm 12.0.1 knows (`FieldTypeName`, D-553).
	 */
	public const TYPES = array( 'text', 'integer', 'number', 'datetime', 'date', 'media', 'boolean', 'email', 'password', 'telephone', 'color', 'url', 'array' );

	/**
	 * The controls DataForm 12.0.1 registers (`dataform-controls/index.tsx`, D-553).
	 */
	public const CONTROLS = array( 'adaptiveSelect', 'array', 'checkbox', 'color', 'combobox', 'datetime', 'date', 'email', 'telephone', 'url', 'integer', 'number', 'password', 'radio', 'select', 'text', 'toggle', 'textarea', 'toggleGroup' );

	/**
	 * The longest help a field may carry (M15's principle 4).
	 */
	public const HELP_LIMIT = 140;

	/**
	 * The most rows a list may hold: the most of any of M15's lists, the mapping's 50.
	 */
	public const ROWS_LIMIT = 50;

	/**
	 * A list's type: rows of columns, drawn as a table with a modal (docs/ADMIN-KIT.md), not one of DataForm's.
	 */
	private const ROWS = 'rows';

	/**
	 * What the field says of itself beyond its id, name, label and type, keyed as the script reads it.
	 *
	 * @var array<string, mixed>
	 */
	private array $extra = array();

	/**
	 * The field's value, as the input holds it.
	 *
	 * @var string
	 */
	private string $value = '';

	/**
	 * A list's column ids, in order; none for any other field.
	 *
	 * @var list<string>
	 */
	private array $columns = array();

	/**
	 * A field.
	 *
	 * @param string $id    Its id: a slug.
	 * @param string $name  The input name its value is posted under.
	 * @param string $label Its label.
	 * @param string $type  Its DataForm type.
	 */
	private function __construct(
		public readonly string $id,
		public readonly string $name,
		public readonly string $label,
		public readonly string $type,
	) {}

	/**
	 * A field.
	 *
	 * @since 1.5.0
	 *
	 * @param string $id    Its id: lowercase letters, digits and underscores, starting with a letter.
	 * @param string $name  The input name its value is posted under, as the server form names it.
	 * @param string $label Its label, translated.
	 * @param string $type  One of TYPES.
	 *
	 * @throws InvalidArgumentException A description that cannot be drawn.
	 */
	public static function of( string $id, string $name, string $label, string $type ): self {
		if ( 1 !== preg_match( '/^[a-z][a-z0-9_]*$/', $id ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'A field id is a slug; "%s" is not.', $id ) ) );
		}
		if ( '' === $name ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" needs an input name.', $id ) ) );
		}
		if ( '' === trim( $label ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" needs a label.', $id ) ) );
		}
		if ( ! in_array( $type, self::TYPES, true ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" has the type "%s", which DataForm has not.', $id, $type ) ) );
		}
		return new self( $id, $name, $label, $type );
	}

	/**
	 * A list of rows, each a value for each column: a table the shop owner adds to, edits in a modal, removes from with
	 * an Undo, and, once ordered(), reorders. Each row posts as the list's name with its place and the column's name
	 * after it: `cbuild[guide][0][size]`.
	 *
	 * @since 1.5.0
	 *
	 * @param string      $id      Its id, as of() takes it.
	 * @param string      $name    The input name its rows are posted under.
	 * @param string      $label   Its label, translated.
	 * @param list<Field> $columns Its columns: fields whose names are what follows a row's place, such as `[size]`.
	 * @param int         $max     The most rows it holds, from 1 to ROWS_LIMIT.
	 *
	 * @throws InvalidArgumentException A list that cannot be drawn.
	 */
	public static function rows( string $id, string $name, string $label, array $columns, int $max ): self {
		$list = self::of( $id, $name, $label, 'text' );
		if ( array() === $columns ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The list "%s" needs a column.', $id ) ) );
		}
		if ( $max < 1 || $max > self::ROWS_LIMIT ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The list "%s" holds at least one row and at most %d rows.', $id, self::ROWS_LIMIT ) ) );
		}
		$described = array();
		foreach ( $columns as $column ) {
			if ( isset( $described[ $column->id ] ) ) {
				throw new InvalidArgumentException( esc_html( sprintf( 'The list "%s" has the column "%s" twice.', $id, $column->id ) ) );
			}
			if ( self::ROWS === $column->type ) {
				throw new InvalidArgumentException( esc_html( sprintf( 'The list "%s" has a list inside a list.', $id ) ) );
			}
			if ( ! str_starts_with( $column->name, '[' ) ) {
				throw new InvalidArgumentException( esc_html( sprintf( 'The column "%s" of the list "%s" is posted inside its row: its name starts with "[".', $column->id, $id ) ) );
			}
			if ( null !== $column->condition() ) {
				throw new InvalidArgumentException( esc_html( sprintf( 'The column "%s" of the list "%s" has a condition, which a table cannot show.', $column->id, $id ) ) );
			}
			$described[ $column->id ] = $column->toArray();
		}
		$made          = new self( $id, $name, $label, self::ROWS );
		$made->extra   = $list->extra;
		$made->columns = array_keys( $described );
		return $made->with( 'columns', array_values( $described ) )
			->with( 'rows', array() )
			->with( 'max', $max )
			->with( 'ordered', false );
	}

	/**
	 * The list with its rows, as the server holds them.
	 *
	 * @since 1.5.0
	 *
	 * @param list<array<string, string>> $rows Each row's values, by column id.
	 *
	 * @throws InvalidArgumentException A field that is not a list, or a row with a column the list has not.
	 */
	public function listed( array $rows ): self {
		$this->listOnly( 'rows' );
		$listed = array();
		foreach ( $rows as $row ) {
			foreach ( array_keys( $row ) as $key ) {
				if ( ! in_array( $key, $this->columns, true ) ) {
					throw new InvalidArgumentException( esc_html( sprintf( 'The list "%s" has no column "%s".', $this->id, $key ) ) );
				}
			}
			$full = array();
			foreach ( $this->columns as $column ) {
				$full[ $column ] = $row[ $column ] ?? '';
			}
			$listed[] = $full;
		}
		return $this->with( 'rows', $listed );
	}

	/**
	 * The list's words, translated.
	 *
	 * @since 1.5.0
	 *
	 * @param string $add   Its Add button, such as "Add a mapping".
	 * @param string $edit  Its modal's title for a row, such as "Edit the mapping".
	 * @param string $none  What it says with no rows: what a row is for, and the first step.
	 * @param string $full  What it says once it holds its most rows, such as "The mapping holds 50 lines at most.".
	 *
	 * @throws InvalidArgumentException A field that is not a list.
	 */
	public function words( string $add, string $edit, string $none, string $full ): self {
		$this->listOnly( 'words' );
		return $this->with(
			'words',
			array(
				'add'   => $add,
				'edit'  => $edit,
				'empty' => $none,
				'full'  => $full,
			)
		);
	}

	/**
	 * The list in an order that matters: each row moves up and down.
	 *
	 * @since 1.5.0
	 *
	 * @throws InvalidArgumentException A field that is not a list.
	 */
	public function ordered(): self {
		$this->listOnly( 'an order' );
		return $this->with( 'ordered', true );
	}

	/**
	 * Refuses a list's method on a field that is not one.
	 *
	 * @param string $what What was asked of it.
	 *
	 * @throws InvalidArgumentException A field that is not a list.
	 */
	private function listOnly( string $what ): void {
		if ( self::ROWS !== $this->type ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" is not a list, so it takes no %s.', $this->id, $what ) ) );
		}
	}

	/**
	 * The field with its value.
	 *
	 * @since 1.5.0
	 *
	 * @param string $value The value, as the input holds it.
	 */
	public function value( string $value ): self {
		$copy        = clone $this;
		$copy->value = $value;
		return $copy;
	}

	/**
	 * The field with its help.
	 *
	 * @since 1.5.0
	 *
	 * @param string $help The help, translated: HELP_LIMIT characters at most.
	 *
	 * @throws InvalidArgumentException Help over the limit.
	 */
	public function help( string $help ): self {
		if ( self::length( $help ) > self::HELP_LIMIT ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" has help over %d characters.', $this->id, self::HELP_LIMIT ) ) );
		}
		return $this->with( 'description', $help );
	}

	/**
	 * The field drawn with a control of DataForm's.
	 *
	 * @since 1.5.0
	 *
	 * @param string $control One of CONTROLS.
	 *
	 * @throws InvalidArgumentException A control DataForm has not.
	 */
	public function control( string $control ): self {
		if ( ! in_array( $control, self::CONTROLS, true ) ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" asks for the control "%s", which DataForm has not.', $this->id, $control ) ) );
		}
		return $this->with( 'control', $control );
	}

	/**
	 * The field's choices, in order.
	 *
	 * @since 1.5.0
	 *
	 * @param array<string, string> $choices Each value and its label, translated.
	 */
	public function choices( array $choices ): self {
		$elements = array();
		foreach ( $choices as $value => $label ) {
			$elements[] = array(
				'value' => (string) $value,
				'label' => $label,
			);
		}
		return $this->with( 'elements', $elements );
	}

	/**
	 * The limits the server holds the field to, given to the script as hints.
	 *
	 * @since 1.5.0
	 *
	 * @param bool        $required  Whether it needs a value.
	 * @param int|null    $min       Its least value.
	 * @param int|null    $max       Its greatest value.
	 * @param int|null    $maxLength Its longest value, in characters.
	 * @param string|null $pattern   A pattern its value matches, in JavaScript's syntax.
	 *
	 * @throws InvalidArgumentException A least value over the greatest.
	 */
	public function rules( bool $required = false, ?int $min = null, ?int $max = null, ?int $maxLength = null, ?string $pattern = null ): self {
		if ( null !== $min && null !== $max && $min > $max ) {
			throw new InvalidArgumentException( esc_html( sprintf( 'The field "%s" has a min over its max.', $this->id ) ) );
		}
		$rules = array_filter(
			array(
				'required'  => $required ? true : null,
				'min'       => $min,
				'max'       => $max,
				'maxLength' => $maxLength,
				'pattern'   => $pattern,
			),
			static fn( mixed $rule ): bool => null !== $rule
		);
		return $this->with( 'rules', $rules );
	}

	/**
	 * The field with a unit or a word after its control.
	 *
	 * @since 1.5.0
	 *
	 * @param string $suffix The suffix, translated.
	 */
	public function suffix( string $suffix ): self {
		return $this->with( 'suffix', $suffix );
	}

	/**
	 * The field's placeholder, such as the value a variation takes from its parent.
	 *
	 * @since 1.5.0
	 *
	 * @param string $placeholder The placeholder, translated.
	 */
	public function placeholder( string $placeholder ): self {
		return $this->with( 'placeholder', $placeholder );
	}

	/**
	 * The field shown but not changed here, since its value comes from elsewhere.
	 *
	 * @since 1.5.0
	 *
	 * @param string $source Where its value comes from, translated.
	 */
	public function readOnly( string $source ): self {
		return $this->with( 'readOnly', true )->with( 'source', $source );
	}

	/**
	 * The field shown only while another field of the form holds one of some values (DataForm's `isVisible`, which
	 * takes a function the server cannot send).
	 *
	 * @since 1.5.0
	 *
	 * @param string       $field  The other field's id.
	 * @param list<string> $values The values it shows for.
	 */
	public function visibleWhen( string $field, array $values ): self {
		return $this->with( 'visibleWhen', array( $field => $values ) );
	}

	/**
	 * The field it is shown with, or null.
	 *
	 * @since 1.5.0
	 */
	public function condition(): ?string {
		$when = $this->extra['visibleWhen'] ?? null;
		return is_array( $when ) ? (string) array_key_first( $when ) : null;
	}

	/**
	 * The field as the script reads it.
	 *
	 * @since 1.5.0
	 *
	 * @return array<string, mixed>
	 */
	public function toArray(): array {
		return array(
			'id'    => $this->id,
			'name'  => $this->name,
			'label' => $this->label,
			'type'  => $this->type,
			'value' => $this->value,
		) + $this->extra;
	}

	/**
	 * A copy with one more thing said.
	 *
	 * @param string $key   What, as the script reads it.
	 * @param mixed  $value Its value.
	 */
	private function with( string $key, mixed $value ): self {
		$copy                = clone $this;
		$copy->extra[ $key ] = $value;
		return $copy;
	}

	/**
	 * A string's length in characters, with no mbstring needed (#72).
	 *
	 * @param string $text The string.
	 */
	private static function length( string $text ): int {
		return function_exists( 'mb_strlen' ) ? mb_strlen( $text, 'UTF-8' ) : (int) preg_match_all( '/./us', $text );
	}
}
