<?php
/**
 * The kit product type.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Assembly;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\StoredKit;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit: the product a shopper builds from parts (spec: Data model). It is virtual, since the parts ship, and is
 * bought through the builder alone. Its price is the assembly fee, read from its assembly settings and never kept
 * in WooCommerce's price fields: with the plugin switched off, WooCommerce reads a kit as a simple product with
 * no price, which it will not sell (spec: Security, performance and compatibility).
 *
 * Its four documents are product meta, each as JSON text, read and written through the product's own CRUD.
 * Nothing here checks a document: the edge checks each against its schema, and the domain builds from it.
 *
 * WooCommerce finds the product's data store by the type's name and falls back to the product store
 * (WooCommerce 11.2.2, includes/class-wc-data-store.php:84), so the type needs none of its own.
 *
 * @since 0.4.0
 */
final class KitProduct extends WC_Product {

	/**
	 * The product's type.
	 *
	 * @since 0.4.0
	 */
	public function get_type(): string {
		return Options::PRODUCT_TYPE_KIT;
	}

	/**
	 * Virtual: the kit is no parcel, and its parts ship as themselves.
	 *
	 * @since 0.4.0
	 *
	 * @param string $context What the value is for: `view` or `edit`.
	 */
	public function get_virtual( $context = 'view' ): bool {
		unset( $context );
		return true;
	}

	/**
	 * Hidden from the catalogue and from search, whatever it is set to: the kit is the builder, not a product to
	 * list (spec: SEO and sharing). WooCommerce's data store writes the two visibility terms from this
	 * (WooCommerce 11.2.2, includes/data-stores/class-wc-product-data-store-cpt.php:1061), and its queries, its
	 * search, its feed and the Store API all read those terms.
	 *
	 * @since 0.4.0
	 *
	 * @param string $context What the value is for: `view` or `edit`.
	 */
	public function get_catalog_visibility( $context = 'view' ): string {
		unset( $context );
		return 'hidden';
	}

	/**
	 * Hidden, whatever is asked for.
	 *
	 * @since 0.4.0
	 *
	 * @param string $visibility The visibility asked for, which a kit does not take.
	 */
	public function set_catalog_visibility( $visibility ): void {
		unset( $visibility );
		parent::set_catalog_visibility( 'hidden' );
	}

	/**
	 * Save, with the visibility always among what changed. WooCommerce writes the two
	 * visibility terms on an update only when visibility is one of the product's changes (WooCommerce 11.2.2,
	 * includes/data-stores/class-wc-product-data-store-cpt.php:385 and :1039), and a product made a kit on the
	 * product screen, by an import or by the REST API is an update of a post whose terms say visible.
	 *
	 * @since 0.4.0
	 */
	public function save(): int {
		// Set as a change outright: the setter records one only where the value read differs, and what is read of a
		// kit is already hidden, whatever its terms say.
		$this->changes['catalog_visibility'] = 'hidden';
		return (int) parent::save();
	}

	/**
	 * Not purchasable as a product: the builder puts a kit in the cart with its parts, and nothing else may.
	 * Milestone 5 answers this filter for the build it is adding.
	 *
	 * @since 0.4.0
	 */
	public function is_purchasable(): bool {
		/** This filter is documented in woocommerce/includes/abstracts/abstract-wc-product.php */
		return (bool) apply_filters( 'woocommerce_is_purchasable', false, $this ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WooCommerce's own filter, run as its products run it.
	}

	/**
	 * The price: the assembly fee to whoever shows it, and nothing to whoever stores it.
	 * WooCommerce's data store writes what `edit` gives (WooCommerce 11.2.2,
	 * includes/data-stores/class-wc-product-data-store-cpt.php:790), so a price set on a kit by an
	 * importer or the REST API never reaches the price fields.
	 *
	 * @since 0.4.0
	 *
	 * @param string $context What the value is for: `view` or `edit`.
	 */
	public function get_price( $context = 'view' ): string {
		return 'edit' === $context ? '' : $this->fee();
	}

	/**
	 * The regular price, as the price.
	 *
	 * @since 0.4.0
	 *
	 * @param string $context What the value is for: `view` or `edit`.
	 */
	public function get_regular_price( $context = 'view' ): string {
		return 'edit' === $context ? '' : $this->fee();
	}

	/**
	 * A kit has no sale price.
	 *
	 * @since 0.4.0
	 *
	 * @param string $context What the value is for: `view` or `edit`.
	 */
	public function get_sale_price( $context = 'view' ): string {
		unset( $context );
		return '';
	}

	/**
	 * One of the kit's documents, decoded, or null where there is none or what is stored is not a JSON object.
	 *
	 * @since 0.4.0
	 *
	 * @param string $key The document's meta key.
	 * @return array<string, mixed>|null
	 */
	public function document( string $key ): ?array {
		return StoredKit::read( $this, $key );
	}

	/**
	 * The kit's four documents, by meta key.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, array<string, mixed>|null>
	 */
	public function documents(): array {
		$documents = array();
		foreach ( Options::kitDocuments() as $key ) {
			$documents[ $key ] = $this->document( $key );
		}
		return $documents;
	}

	/**
	 * Put a document on the kit, to be stored when the kit is saved. A key that is not one of the four is left.
	 *
	 * @since 0.4.0
	 *
	 * @param string               $key      The document's meta key.
	 * @param array<string, mixed> $document The document.
	 */
	public function setDocument( string $key, array $document ): void {
		StoredKit::write( $this, $key, $document );
	}

	/**
	 * The fee as the assembly settings give it, a decimal in text, or '' where they do not read.
	 */
	private function fee(): string {
		$document = $this->document( Options::META_KIT_ASSEMBLY );
		try {
			Assembly::fromDocument( $document );
		} catch ( InvalidDocument ) {
			return '';
		}
		return is_string( $document['fee'] ?? null ) ? $document['fee'] : '';
	}
}
