<?php
/**
 * The kit documents' schemas.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\DocumentSchemas;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checks a kit's document against its JSON Schema in `schema/`, with WordPress's own validator. The schema says
 * what a document may hold; the domain then says whether they agree with each other.
 *
 * @since 0.4.0
 */
final class KitSchemas implements DocumentSchemas {

	/**
	 * The schema files, by the document's name.
	 */
	private const array FILES = array(
		'slots'    => 'kit-slots.json',
		'rules'    => 'kit-rules.json',
		'presets'  => 'kit-presets.json',
		'assembly' => 'kit-assembly.json',
		'picture'  => 'kit-picture.json',
		'layout'   => 'kit-layout.json',
	);

	/**
	 * The schemas read so far, by name.
	 *
	 * @var array<string, array<mixed>>
	 */
	private array $read = array();

	/**
	 * Why a document is refused, as WordPress's code for it, or null when its schema accepts it. A name that is
	 * not a kit document's is refused.
	 *
	 * @since 0.4.0
	 *
	 * @param string $name     The document's name.
	 * @param mixed  $document The decoded document.
	 */
	public function refusal( string $name, mixed $document ): ?string {
		$schema = $this->schema( $name );
		if ( null === $schema ) {
			return 'cbuild_no_schema';
		}
		$valid = rest_validate_value_from_schema( $document, $schema, $name );
		return is_wp_error( $valid ) ? (string) $valid->get_error_code() : null;
	}

	/**
	 * A document's schema.
	 *
	 * @param string $key The document's name.
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
