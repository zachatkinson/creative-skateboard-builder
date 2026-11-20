<?php
/**
 * The parts' own layer images through WooCommerce's products, and the Media Library's images by file name.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\PartLayers;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A part's own layer image is its `_cbuild_layer` meta, read and written through the product's CRUD, as the Part specs
 * panel's field writes it; the save queues the part's index job (IndexHooks). An image is found by its file's name
 * among the attachments whose stored file holds that name, then held to it exactly: the file uploaded, or the copy
 * WordPress scaled from a large one (`-scaled`, whose original's name `wp_get_original_image_path()` gives).
 *
 * @since 1.0.0
 */
final class WooPartLayers implements PartLayers {

	/**
	 * The most images with one name read: two are already ambiguous, and are refused so.
	 */
	private const LOOKED = 20;

	/**
	 * Each part's own layer image, and whether it is a variation.
	 *
	 * @since 1.0.0
	 *
	 * @param int ...$parts Product or variation IDs.
	 * @return array<int, array{own: int, variation: bool}>
	 */
	public function owned( int ...$parts ): array {
		$owned = array();
		foreach ( $parts as $part ) {
			$product = wc_get_product( $part );
			if ( $product instanceof WC_Product ) {
				$owned[ $part ] = array(
					'own'       => max( 0, Cast::int( $product->get_meta( Options::META_LAYER, true ) ) ),
					'variation' => $product->is_type( 'variation' ),
				);
			}
		}
		return $owned;
	}

	/**
	 * The images whose file has this name.
	 *
	 * @since 1.0.0
	 *
	 * @param string $file A file's name.
	 * @return list<int>
	 */
	public function named( string $file ): array {
		$file = wp_basename( $file );
		$stem = pathinfo( $file, PATHINFO_FILENAME );
		if ( '' === $stem ) {
			return array();
		}
		$type  = pathinfo( $file, PATHINFO_EXTENSION );
		$names = array( $file, $stem . '-scaled' . ( '' === $type ? '' : '.' . $type ) );
		$query = new WP_Query(
			array(
				'post_type'      => 'attachment',
				'post_status'    => 'inherit',
				'post_mime_type' => 'image',
				'fields'         => 'ids',
				'posts_per_page' => self::LOOKED,
				'orderby'        => 'ID',
				'order'          => 'ASC',
				'no_found_rows'  => true,
				// The file's whole name in any folder, or the copy WordPress scales a large upload to, so a library of
				// files whose names merely hold it fills no page before it (the auditor's 1.0.0 minor 3). LIKE wraps
				// its value in wildcards, so the folder's slash marks the name's start; the match is made exact below.
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- Once a row of an import an admin confirmed, by name, as the Media Library's own search does.
				'meta_query'     => array(
					'relation' => 'OR',
					...array_map(
						static fn( string $name ): array => array(
							'key'     => '_wp_attached_file',
							'value'   => '/' . $name,
							'compare' => 'LIKE',
						),
						$names
					),
					...array_map(
						static fn( string $name ): array => array(
							'key'   => '_wp_attached_file',
							'value' => $name,
						),
						$names
					),
				),
			)
		);
		$named = array();
		foreach ( (array) $query->posts as $post ) {
			$id = Cast::int( $post instanceof \WP_Post ? $post->ID : $post );
			if ( $this->fileName( $id ) === $file || wp_basename( (string) get_attached_file( $id ) ) === $file ) {
				$named[] = $id;
			}
		}
		return $named;
	}

	/**
	 * An image's file name as it was uploaded.
	 *
	 * @since 1.0.0
	 *
	 * @param int $attachment The attachment's ID.
	 */
	public function fileName( int $attachment ): string {
		if ( $attachment <= 0 || ! wp_attachment_is_image( $attachment ) ) {
			return '';
		}
		$original = wp_get_original_image_path( $attachment );
		return is_string( $original ) ? wp_basename( $original ) : '';
	}

	/**
	 * Set a part's own layer image, or remove it.
	 *
	 * @since 1.0.0
	 *
	 * @param int $part       A product's or variation's ID.
	 * @param int $attachment The image's attachment ID, or 0.
	 */
	public function assign( int $part, int $attachment ): void {
		$product = wc_get_product( $part );
		if ( ! $product instanceof WC_Product ) {
			return;
		}
		if ( $attachment > 0 ) {
			$product->update_meta_data( Options::META_LAYER, (string) $attachment );
		} else {
			$product->delete_meta_data( Options::META_LAYER );
		}
		$product->save();
	}
}
