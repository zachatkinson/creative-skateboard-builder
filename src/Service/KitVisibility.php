<?php
/**
 * Keeps kits out of what lists products.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit is the builder, not a product to list (spec: SEO and sharing). WooCommerce's own lists read the kit's
 * visibility; these are the two lists that do not: WordPress's sitemap, and the product markup on its page.
 *
 * @since 0.4.0
 */
final class KitVisibility {

	/**
	 * The products' post type and the taxonomy WooCommerce keeps a product's type in.
	 */
	private const POST_TYPE = 'product';
	private const TAXONOMY  = 'product_type';

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param string $type The kit's product type.
	 */
	public function __construct( private string $type ) {}

	/**
	 * The query WordPress builds a post type's sitemap from, with kits left out of the products'.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $args     The query's arguments.
	 * @param mixed $postType The post type the sitemap is of.
	 * @return mixed
	 */
	public function sitemapQuery( mixed $args, mixed $postType ): mixed {
		if ( self::POST_TYPE !== $postType || ! is_array( $args ) ) {
			return $args;
		}
		$asked   = is_array( $args['tax_query'] ?? null ) ? $args['tax_query'] : array();
		$asked[] = array(
			'taxonomy' => self::TAXONOMY,
			'field'    => 'slug',
			'terms'    => array( $this->type ),
			'operator' => 'NOT IN',
		);
		// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query -- One term of a small taxonomy, on a query WordPress caches as a sitemap page.
		$args['tax_query'] = $asked;
		return $args;
	}

	/**
	 * A product's structured data, or none for a kit: its own price is only the assembly fee, and an offer at that
	 * price would be a wrong one.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed  $markup The markup WooCommerce built.
	 * @param string $type   The product's type.
	 * @return mixed
	 */
	public function markup( mixed $markup, string $type ): mixed {
		return $this->type === $type ? array() : $markup;
	}
}
