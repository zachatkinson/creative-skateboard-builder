<?php
/**
 * The kit documents' schemas.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Support\Options;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks a kit's document against its JSON Schema in `schema/`, with WordPress's own validator. The schema says
 * what a document may hold; the domain then says whether the four agree with each other.
 *
 * @since 0.4.0
 */
final class KitSchemas {

	/**
	 * The schema files, by meta key.
	 */
	private const array FILES = array(
		Options::META_KIT_SLOTS    => 'kit-slots.json',
		Options::META_KIT_RULES    => 'kit-rules.json',
		Options::META_KIT_PRESETS  => 'kit-presets.json',
		Options::META_KIT_ASSEMBLY => 'kit-assembly.json',
	);

	/**
	 * The schemas read so far, by meta key.
	 *
	 * @var array<string, array<mixed>>
	 */
	private array $read = array();

	/**
	 * Why a document is refused, or null when its schema accepts it. A key that is not a kit document's is refused.
	 *
	 * @since 0.4.0
	 *
	 * @param string $key      The document's meta key.
	 * @param mixed  $document The decoded document.
	 */
	public function refusal( string $key, mixed $document ): ?WP_Error {
		$schema = $this->schema( $key );
		if ( null === $schema ) {
			return new WP_Error( 'cbuild_no_schema', $key );
		}
		$valid = rest_validate_value_from_schema( $document, $schema, $key );
		return is_wp_error( $valid ) ? $valid : null;
	}

	/**
	 * A document's schema.
	 *
	 * @param string $key The document's meta key.
	 * @return array<mixed>|null
	 */
	private function schema( string $key ): ?array {
		if ( ! isset( self::FILES[ $key ] ) ) {
			return null;
		}
		if ( ! isset( $this->read[ $key ] ) ) {
			$schema = wp_json_file_decode( CBUILD_PATH . 'schema/' . self::FILES[ $key ], array( 'associative' => true ) );
			if ( ! is_array( $schema ) ) {
				return null;
			}
			$this->read[ $key ] = $schema;
		}
		return $this->read[ $key ];
	}
}
