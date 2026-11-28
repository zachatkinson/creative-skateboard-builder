<?php
/**
 * The server's refusals of a posted form, each to its field.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin\Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * M15's principle 3: the server decides, and what it refused comes back to the field, through DataForm's `validity`,
 * beside one notice at the top that links to each. A field keeps the first message given for it.
 *
 * @since 1.5.0
 */
final class Errors {

	/**
	 * Each field's message, by field id.
	 *
	 * @param array<string, string> $messages The messages.
	 */
	private function __construct( private readonly array $messages ) {}

	/**
	 * No errors.
	 *
	 * @since 1.5.0
	 */
	public static function none(): self {
		return new self( array() );
	}

	/**
	 * These errors and one more, unless the field has one already.
	 *
	 * @since 1.5.0
	 *
	 * @param string $field   The field's id.
	 * @param string $message What to put right, translated.
	 */
	public function with( string $field, string $message ): self {
		return new self( $this->messages + array( $field => $message ) );
	}

	/**
	 * The errors of a form's own fields.
	 *
	 * @since 1.5.0
	 *
	 * @param Form $form The form.
	 */
	public function for( Form $form ): self {
		return new self( array_intersect_key( $this->messages, array_flip( $form->ids() ) ) );
	}

	/**
	 * Whether there are none.
	 *
	 * @since 1.5.0
	 */
	public function isEmpty(): bool {
		return array() === $this->messages;
	}

	/**
	 * The errors as the script reads them.
	 *
	 * @since 1.5.0
	 *
	 * @return array<string, string>
	 */
	public function toArray(): array {
		return $this->messages;
	}
}
