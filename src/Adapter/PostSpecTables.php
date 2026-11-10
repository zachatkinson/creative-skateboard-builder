<?php
/**
 * The spec tables, as posts.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\PluginException;
use WP_Post;
use WP_Query;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A table is a post of the type `cbuild_spec_table`: its title the maker's name, its rows in the meta `_cbuild_rows`.
 * Only a published one is in use.
 *
 * @since 0.3.0
 */
final class PostSpecTables implements SpecTables {

	/**
	 * The tables in use among these IDs.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$ids Tables' IDs.
	 * @return array<int, SpecTable>
	 */
	public function find( int ...$ids ): array {
		$ids = array_values( array_unique( array_filter( $ids, static fn( int $id ): bool => $id > 0 ) ) );
		// One query for the posts and one for their meta, however many tables a batch of parts names.
		_prime_post_caches( $ids, false, true );
		$tables = array();
		foreach ( $ids as $id ) {
			$post = get_post( $id );
			if ( $post instanceof WP_Post && Options::POST_TYPE_SPEC_TABLE === $post->post_type && 'publish' === $post->post_status ) {
				$tables[ $id ] = SpecTable::read( get_post_meta( $id, Options::META_TABLE_ROWS, true ) );
			}
		}
		return $tables;
	}

	/**
	 * Make a table.
	 *
	 * @since 0.3.0
	 *
	 * @param string    $name  The maker's name.
	 * @param SpecTable $table Its rows.
	 * @param string    $seed  The seed it was made from.
	 * @throws PluginException When WordPress cannot save it.
	 */
	public function create( string $name, SpecTable $table, string $seed = '' ): int {
		$meta = array( Options::META_TABLE_ROWS => $table->toArray() );
		if ( '' !== $seed ) {
			$meta[ Options::META_TABLE_SEED ] = $seed;
		}
		// wp_insert_post() expects slashed data and unslashes it.
		$id = wp_insert_post(
			wp_slash(
				array(
					'post_type'   => Options::POST_TYPE_SPEC_TABLE,
					'post_status' => 'publish',
					'post_title'  => $name,
					'meta_input'  => $meta,
				)
			),
			true
		);
		if ( is_wp_error( $id ) ) {
			throw new PluginException( esc_html( sprintf( 'The spec table %1$s could not be saved: %2$s', $name, $id->get_error_message() ) ) );
		}
		return $id;
	}

	/**
	 * Whether a table made from this seed exists, in use, a draft or in the bin.
	 *
	 * @since 0.3.0
	 *
	 * @param string $seed The seed's name.
	 */
	public function hasSeed( string $seed ): bool {
		$found = new WP_Query(
			array(
				'post_type'      => Options::POST_TYPE_SPEC_TABLE,
				'post_status'    => array_keys( get_post_stati() ),
				'posts_per_page' => 1,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'orderby'        => 'none',
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key -- Three times, in the migration that makes the seed tables; a shop keeps a handful of tables.
				'meta_key'       => Options::META_TABLE_SEED,
				// phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value -- As above.
				'meta_value'     => $seed,
			)
		);
		return array() !== $found->posts;
	}
}
