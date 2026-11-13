<?php
/**
 * A kit's documents, from its product's meta.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\KitMeta;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\StoredKit;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit product's documents, as JSON in its meta through StoredKit and WooCommerce's product CRUD, so that its
 * caches are cleared as a save clears them.
 *
 * @since 0.6.0
 */
final class WooKitMeta implements KitMeta {

	/**
	 * Every kit product's ID, those in the bin too, but not those WordPress made and nobody saved: a kit restored
	 * from the bin after a migration would otherwise keep what the migration changed (Cowork's review, minor 21).
	 *
	 * @since 0.6.0
	 *
	 * @return list<int>
	 */
	public function ids(): array {
		return self::query( array( 'auto-draft' ) );
	}

	/**
	 * Every kit product's ID but those in the bin and those WordPress made and nobody saved: the kits a shop has.
	 * Kits answers with these.
	 *
	 * @since 0.6.0
	 *
	 * @return list<int>
	 */
	public static function live(): array {
		return self::query( array( 'trash', 'auto-draft' ) );
	}

	/**
	 * Every kit product's ID in any status but those left out, by ID.
	 *
	 * @param list<string> $without The statuses left out.
	 * @return list<int>
	 */
	private static function query( array $without ): array {
		$found = wc_get_products(
			array(
				'type'    => Options::PRODUCT_TYPE_KIT,
				'status'  => array_values( array_diff( array_keys( get_post_stati() ), $without ) ),
				'limit'   => WooKits::MAX_KITS,
				'orderby' => 'ID',
				'order'   => 'ASC',
				'return'  => 'ids',
			)
		);
		return array_values( Cast::ints( $found ) );
	}


	/**
	 * A kit's document as stored, decoded, or null where it has none or it does not decode to an object.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $kit The kit product's ID.
	 * @param string $key The document's meta key.
	 * @return array<string, mixed>|null
	 */
	public function read( int $kit, string $key ): ?array {
		$product = wc_get_product( $kit );
		return $product instanceof WC_Product ? StoredKit::read( $product, $key ) : null;
	}

	/**
	 * Store a kit's document.
	 *
	 * @since 0.6.0
	 *
	 * @param int                  $kit      The kit product's ID.
	 * @param string               $key      The document's meta key.
	 * @param array<string, mixed> $document The document.
	 */
	public function write( int $kit, string $key, array $document ): void {
		$product = wc_get_product( $kit );
		if ( $product instanceof WC_Product ) {
			StoredKit::write( $product, $key, $document );
			$product->save();
		}
	}
}
