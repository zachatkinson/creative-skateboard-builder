<?php
/**
 * Reads typed fields out of a decoded document.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;

use function array_is_list;
use function array_key_exists;
use function array_keys;
use function count;
use function is_array;
use function is_bool;
use function is_int;
use function is_string;
use function preg_match;
use function substr;

/**
 * One object of a kit's JSON document, with the path that leads to it. Every read names its field, checks its type
 * and, where the domain relies on one, its range, and refuses with the field's path, so each value object builds
 * itself from fields it can trust and holds only the checks that are its own.
 */
final readonly class Fields {

	/**
	 * The only version of each document so far.
	 */
	public const VERSION = 1;

	/**
	 * Constructor.
	 *
	 * @param array<mixed> $data The object's fields.
	 * @param string       $path The path to the object: empty at the root.
	 */
	private function __construct( private array $data, private string $path ) {}

	/**
	 * The root of a document, once its `schema_version` is one this code reads.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $data The decoded document.
	 *
	 * @throws InvalidDocument When it is not an object, or its version is not known.
	 */
	public static function document( mixed $data ): self {
		$root = self::of( $data, '' );
		if ( self::VERSION !== ( $root->data['schema_version'] ?? null ) ) {
			$refused = InvalidDocument::because( DocumentError::UnknownVersion, 'schema_version' );
			throw $refused;
		}
		return $root;
	}

	/**
	 * An object at a path.
	 *
	 * @param mixed  $data What the document holds there.
	 * @param string $path The path.
	 *
	 * @throws InvalidDocument When it is not an object.
	 */
	private static function of( mixed $data, string $path ): self {
		if ( ! is_array( $data ) || ( array() !== $data && array_is_list( $data ) ) ) {
			$refused = InvalidDocument::because( DocumentError::NotAnObject, $path );
			throw $refused;
		}
		return new self( $data, $path );
	}

	/**
	 * The path of one of this object's fields.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 */
	public function path( string $key ): string {
		return '' === $this->path ? $key : $this->path . '.' . $key;
	}

	/**
	 * Whether the object has the field.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 */
	public function has( string $key ): bool {
		return array_key_exists( $key, $this->data );
	}

	/**
	 * A field that must be there.
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is missing.
	 */
	private function need( string $key ): mixed {
		if ( ! $this->has( $key ) ) {
			$refused = InvalidDocument::because( DocumentError::Missing, $this->path( $key ) );
			throw $refused;
		}
		return $this->data[ $key ];
	}

	/**
	 * Refuse a field for its type.
	 *
	 * @param string $key The field.
	 */
	private function wrongType( string $key ): InvalidDocument {
		return InvalidDocument::because( DocumentError::WrongType, $this->path( $key ) );
	}

	/**
	 * A required string.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is missing or not a string.
	 */
	public function text( string $key ): string {
		$value = $this->need( $key );
		if ( ! is_string( $value ) ) {
			$refused = $this->wrongType( $key );
			throw $refused;
		}
		return $value;
	}

	/**
	 * A string that may be left out, read as empty.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is there and not a string.
	 */
	public function textOrEmpty( string $key ): string {
		return $this->has( $key ) ? $this->text( $key ) : '';
	}

	/**
	 * A required key: a lower-case letter, then up to 31 letters, digits or underscores.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is missing, not a string or not a key.
	 */
	public function key( string $key ): string {
		$value = $this->text( $key );
		if ( ! self::isKey( $value ) ) {
			$refused = InvalidDocument::because( DocumentError::BadKey, $this->path( $key ) );
			throw $refused;
		}
		return $value;
	}

	/**
	 * Whether a string is a key.
	 *
	 * @since 0.2.0
	 *
	 * @param string $value The string.
	 */
	public static function isKey( string $value ): bool {
		return 1 === preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $value );
	}

	/**
	 * A required true or false.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is missing or not a boolean.
	 */
	public function flag( string $key ): bool {
		$value = $this->need( $key );
		if ( ! is_bool( $value ) ) {
			$refused = $this->wrongType( $key );
			throw $refused;
		}
		return $value;
	}

	/**
	 * A required whole number within a range.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 * @param int    $min The smallest allowed.
	 * @param int    $max The largest allowed.
	 *
	 * @throws InvalidDocument When it is missing, not an integer or out of range.
	 */
	public function whole( string $key, int $min, int $max ): int {
		$value = $this->need( $key );
		if ( ! is_int( $value ) ) {
			$refused = $this->wrongType( $key );
			throw $refused;
		}
		if ( $value < $min || $value > $max ) {
			$refused = InvalidDocument::because( DocumentError::OutOfRange, $this->path( $key ) );
			throw $refused;
		}
		return $value;
	}

	/**
	 * An id of one or more, or null when the field is left out or null.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is there and not a positive integer.
	 */
	public function idOrNull( string $key ): ?int {
		return null === ( $this->data[ $key ] ?? null ) ? null : $this->whole( $key, 1, PHP_INT_MAX );
	}

	/**
	 * A list of ids of one or more, empty when the field is left out.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @return list<int>
	 *
	 * @throws InvalidDocument When it is not a list, or holds something that is not a positive integer.
	 */
	public function ids( string $key ): array {
		$ids = array();
		foreach ( $this->items( $key ) as $at => $value ) {
			if ( ! is_int( $value ) ) {
				$refused = $this->wrongType( $key . '[' . $at . ']' );
				throw $refused;
			}
			if ( $value < 1 ) {
				$refused = InvalidDocument::because( DocumentError::OutOfRange, $this->path( $key . '[' . $at . ']' ) );
				throw $refused;
			}
			$ids[] = $value;
		}
		return $ids;
	}

	/**
	 * A list's items, none when the field is left out.
	 *
	 * @param string $key The field.
	 *
	 * @return list<mixed>
	 *
	 * @throws InvalidDocument When it is there and not a list.
	 */
	private function items( string $key ): array {
		$value = $this->data[ $key ] ?? array();
		if ( ! is_array( $value ) || ! array_is_list( $value ) ) {
			$refused = $this->wrongType( $key );
			throw $refused;
		}
		return $value;
	}

	/**
	 * A list of objects, at most so many, and none when the field is left out.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 * @param int    $max The most allowed.
	 *
	 * @return list<self>
	 *
	 * @throws InvalidDocument When it is not a list, holds too many, or holds something that is not an object.
	 */
	public function rows( string $key, int $max ): array {
		$items = $this->items( $key );
		if ( count( $items ) > $max ) {
			$refused = InvalidDocument::because( DocumentError::OutOfRange, $this->path( $key ) );
			throw $refused;
		}
		$rows = array();
		foreach ( $items as $at => $item ) {
			$rows[] = self::of( $item, $this->path( $key . '[' . $at . ']' ) );
		}
		return $rows;
	}

	/**
	 * A list of objects that must hold at least one.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 * @param int    $max The most allowed.
	 *
	 * @return non-empty-list<self>
	 *
	 * @throws InvalidDocument When it is not a list, is empty or left out, holds too many, or holds something that is not an object.
	 */
	public function rowsFilled( string $key, int $max ): array {
		$rows = $this->rows( $key, $max );
		if ( array() === $rows ) {
			$refused = InvalidDocument::because( DocumentError::OutOfRange, $this->path( $key ) );
			throw $refused;
		}
		return $rows;
	}

	/**
	 * A required object.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is missing or not an object.
	 */
	public function object( string $key ): self {
		return self::of( $this->need( $key ), $this->path( $key ) );
	}

	/**
	 * An object that may be left out, read as an empty one.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is there and not an object.
	 */
	public function objectOrEmpty( string $key ): self {
		return self::of( $this->data[ $key ] ?? array(), $this->path( $key ) );
	}

	/**
	 * The object's own field names, each of which must be a string.
	 *
	 * @since 0.2.0
	 *
	 * @return list<string>
	 */
	public function names(): array {
		$names = array();
		foreach ( array_keys( $this->data ) as $name ) {
			$names[] = (string) $name;
		}
		return $names;
	}

	/**
	 * A required amount, written as a decimal with a point and up to four places (`0.25`, `56`), in ten-thousandths.
	 * A document holds its lengths and its fee this way, so JSON's floats never carry one.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key The field.
	 *
	 * @throws InvalidDocument When it is missing, not a string or not such a decimal.
	 */
	public function amount( string $key ): int {
		$text = $this->text( $key );
		if ( 1 !== preg_match( '/\A(?<whole>\d{1,9})(?:\.(?<places>\d{1,4}))?\z/', $text, $found ) ) {
			$refused = InvalidDocument::because( DocumentError::BadMeasure, $this->path( $key ) );
			throw $refused;
		}
		return 10000 * (int) $found['whole'] + (int) substr( ( $found['places'] ?? '' ) . '0000', 0, 4 );
	}

	/**
	 * A required measure, written as an amount.
	 *
	 * @since 0.2.0
	 *
	 * @param string $key  The field.
	 * @param Unit   $unit The unit it is in.
	 *
	 * @throws InvalidDocument When it is missing, not a string or not such a decimal.
	 */
	public function measure( string $key, Unit $unit ): Measure {
		return Measure::of( $this->amount( $key ), $unit );
	}
}
