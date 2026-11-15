<?php
/**
 * A kit's documents as its product holds them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Support;

use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each of a kit's documents is JSON text in one of the product's meta keys. The kit's own class and the Build
 * tab both read and write them here, through the product's CRUD, so there is one way a document is stored.
 *
 * @since 0.4.0
 */
final class StoredKit {

	/**
	 * One document, decoded, or null where there is none, the key is not a kit's, or what is stored is not a JSON
	 * object.
	 *
	 * @since 0.4.0
	 *
	 * @param WC_Product $product The kit.
	 * @param string     $key     The document's meta key.
	 * @return array<string, mixed>|null
	 */
	public static function read( WC_Product $product, string $key ): ?array {
		if ( ! in_array( $key, Options::kitDocuments(), true ) ) {
			return null;
		}
		$stored  = $product->get_meta( $key, true );
		$decoded = is_string( $stored ) ? json_decode( $stored, true ) : null;
		if ( ! is_array( $decoded ) || array_is_list( $decoded ) ) {
			return null;
		}
		/** @var array<string, mixed> $decoded */
		return $decoded;
	}

	/**
	 * The documents by the name the kit's services know each by.
	 *
	 * @since 0.4.0
	 *
	 * @param WC_Product $product The kit.
	 * @return array<string, array<string, mixed>|null>
	 */
	public static function named( WC_Product $product ): array {
		$documents = array();
		foreach ( Options::kitDocumentNames() as $name => $key ) {
			$documents[ $name ] = self::read( $product, $key );
		}
		return $documents;
	}

	/**
	 * Put a document on the product, to be stored when the product is saved. A key that is not a kit's is left.
	 *
	 * @since 0.4.0
	 *
	 * @param WC_Product           $product  The kit.
	 * @param string               $key      The document's meta key.
	 * @param array<string, mixed> $document The document.
	 */
	public static function write( WC_Product $product, string $key, array $document ): void {
		if ( ! in_array( $key, Options::kitDocuments(), true ) ) {
			return;
		}
		$text = wp_json_encode( $document, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
		if ( false !== $text ) {
			$product->update_meta_data( $key, $text );
		}
	}
}
