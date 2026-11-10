<?php
/**
 * Keeps kits out of what lists products.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use Closure;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit is the builder, not a product to list (spec: SEO and sharing). WooCommerce's own lists read the kit's
 * visibility; these are the lists that do not: WordPress's sitemap, Yoast SEO's, and the product markup on its page.
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
	 * @param string               $type The kit's product type.
	 * @param Closure(): list<int> $kits Every kit product's ID, read when a list asks for them.
	 */
	public function __construct( private string $type, private Closure $kits ) {}

	/**
	 * The posts Yoast SEO leaves out of its sitemaps, with every kit among them (Yoast SEO 28.8,
	 * inc/sitemaps/class-post-type-sitemap-provider.php:296). Yoast builds its own sitemap and never asks
	 * WordPress's, so the query above does not reach it.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $ids The IDs left out so far.
	 * @return list<int>
	 */
	public function excluded( mixed $ids ): array {
		$out = array();
		foreach ( array_merge( is_array( $ids ) ? array_values( $ids ) : array(), ( $this->kits )() ) as $id ) {
			if ( is_numeric( $id ) && (int) $id > 0 ) {
				$out[ (int) $id ] = (int) $id;
			}
		}
		return array_values( $out );
	}

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
