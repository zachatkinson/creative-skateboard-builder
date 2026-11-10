<?php
/**
 * A part's specs from the maker's chart it names.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\StoredSpecs;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Unread;
use CreativeBranding\SkateboardBuilder\Port\SpecSource;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Product;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A part names a spec table and a size in `_cbuild_spec_table`, and takes that row's values (spec: Data model, Part
 * specs). A variation left blank for the table or the size takes its parent's, so a variable truck names the chart
 * once and each variation its size. A table or a size that is not there is reported as unread, for the Catalogue
 * check. A reading's reference is the table's ID, a colon and the size.
 *
 * @since 0.3.0
 */
final class TableSpecSource implements SpecSource {

	/**
	 * Why a part's name could not be read: the table is gone, in the bin or a draft.
	 *
	 * @since 0.3.0
	 */
	public const TABLE_MISSING = StoredSpecs::TABLE_MISSING;

	/**
	 * Why a part's name could not be read: the table has no row for the size, or the part names none.
	 *
	 * @since 0.3.0
	 */
	public const SIZE_MISSING = StoredSpecs::SIZE_MISSING;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Products   $products Loads the batch.
	 * @param SpecTables $tables   The shop's tables.
	 */
	public function __construct( private Products $products, private SpecTables $tables ) {}

	/**
	 * What each part's chart says.
	 *
	 * @since 0.3.0
	 *
	 * @param int ...$partIds Products' and variations' IDs.
	 * @return array<int, list<Reading|Unread>>
	 */
	public function read( int ...$partIds ): array {
		$products = $this->products->load( ...$partIds );
		$parents  = $this->products->parentsOf( $products );
		$named    = array();
		foreach ( $products as $id => $product ) {
			$own    = self::named( $product );
			$parent = $parents[ $product->get_parent_id() ] ?? null;
			$theirs = null === $parent ? array( 0, '' ) : self::named( $parent );
			$name   = array( 0 === $own[0] ? $theirs[0] : $own[0], '' === $own[1] ? $theirs[1] : $own[1] );
			if ( array( 0, '' ) !== $name ) {
				$named[ $id ] = $name;
			}
		}
		$tables = $this->tables->find( ...array_column( $named, 0 ) );
		$found  = array_fill_keys( $partIds, array() );
		foreach ( $named as $id => [ $table, $size ] ) {
			$ref = $table . ':' . $size;
			if ( ! isset( $tables[ $table ] ) ) {
				$found[ $id ][] = new Unread( '', (string) $table, Origin::Table, $ref, self::TABLE_MISSING );
				continue;
			}
			$row = $tables[ $table ]->row( $size );
			if ( null === $row ) {
				$found[ $id ][] = new Unread( '', $size, Origin::Table, $ref, self::SIZE_MISSING );
				continue;
			}
			foreach ( SpecKey::cases() as $key ) {
				if ( isset( $row->values[ $key->value ] ) ) {
					$found[ $id ][] = Reading::from( $key, $row->values[ $key->value ], Origin::Table, $ref );
				}
			}
		}
		return $found;
	}

	/**
	 * The table and size a product names itself: 0 and '' where it names none.
	 *
	 * @param WC_Product $product The product.
	 * @return array{0: int, 1: string}
	 */
	private static function named( WC_Product $product ): array {
		$named = StoredSpecs::named( $product->get_meta( Options::META_SPEC_TABLE, true ) );
		return array( $named['table'], $named['size'] );
	}
}
