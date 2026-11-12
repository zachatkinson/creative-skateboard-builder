<?php
/**
 * What a save changed of the index's sources.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Service\SavedSources;
use PHPUnit\Framework\TestCase;

/**
 * Issue #21: a save that changes no source of the index (the product's attributes, its two spec documents, its
 * parent) queues no job, and a save nothing is known of still does.
 */
final class SavedSourcesTest extends TestCase {

	private SavedSources $saved;

	protected function setUp(): void {
		$this->saved = new SavedSources( array( '_cbuild_specs', '_cbuild_spec_table' ) );
	}

	public function test_a_save_nothing_is_known_of_is_taken_to_have_moved_a_source(): void {
		self::assertTrue( $this->saved->moved( 7 ) );
	}

	public function test_a_save_of_stock_alone_moved_none_and_is_forgotten_once_asked(): void {
		$this->saved->saving( 7, array() );
		self::assertFalse( $this->saved->moved( 7 ), 'an order writes the count itself, and the save changes nothing' );
		self::assertTrue( $this->saved->moved( 7 ), 'the next save is not this one' );

		$this->saved->saving( 7, array( 'stock_quantity', 'stock_status', 'total_sales', 'date_modified' ) );
		$this->saved->saving( 8, array( 'stock_status' ) );
		self::assertFalse( $this->saved->moved( 7 ) );
		self::assertFalse( $this->saved->moved( 8 ), 'each product by itself' );
	}

	public function test_any_other_change_moved_one(): void {
		foreach ( array( 'attributes', 'parent_id', 'name', 'regular_price' ) as $prop ) {
			$this->saved->saving( 7, array( 'stock_quantity', $prop ) );
			self::assertTrue( $this->saved->moved( 7 ), $prop );
		}
		$this->saved->saving( 7, array( 'name' ) );
		$this->saved->moved( 7 );
		$this->saved->saving( 7, array( 'stock_status' ) );
		self::assertFalse( $this->saved->moved( 7 ), 'what the last save changed is not carried into this one' );
	}

	public function test_a_product_not_yet_made_is_not_noted(): void {
		$this->saved->saving( 0, array() );
		self::assertTrue( $this->saved->moved( 0 ) );
	}

	public function test_a_spec_document_written_moved_one_and_another_plugins_meta_did_not(): void {
		$this->saved->saving( 7, array() );
		$this->saved->meta( 7, '_stock' );
		$this->saved->meta( 8, '_cbuild_specs' );
		self::assertFalse( $this->saved->moved( 7 ) );

		foreach ( array( '_cbuild_specs', '_cbuild_spec_table' ) as $key ) {
			$this->saved->saving( 7, array() );
			$this->saved->meta( 7, $key );
			self::assertTrue( $this->saved->moved( 7 ), $key );
			$this->saved->saving( 7, array() );
			self::assertFalse( $this->saved->moved( 7 ), 'and is forgotten with the save' );
		}
	}

	public function test_an_attributes_terms_set_directly_moved_one_when_they_changed(): void {
		$this->saved->saving( 7, array() );
		$this->saved->terms( 7, 'pa_width', array( 3, 4 ), array( '4', '3' ) );
		self::assertFalse( $this->saved->moved( 7 ), 'the same terms, in another order and as the database gives them' );

		$this->saved->saving( 7, array() );
		$this->saved->terms( 7, 'product_visibility', array( 9 ), array() );
		self::assertFalse( $this->saved->moved( 7 ), 'WooCommerce marks a sold-out product with a term, which is no attribute' );

		$this->saved->saving( 7, array() );
		$this->saved->terms( 7, 'pa_width', array( 3 ), array( 3, 4 ) );
		self::assertTrue( $this->saved->moved( 7 ) );

		$this->saved->saving( 7, array() );
		$this->saved->terms( 7, 'pa_width', array( 5 ), array() );
		self::assertTrue( $this->saved->moved( 7 ) );

		$this->saved->saving( 7, array() );
		$this->saved->terms( 7, 'pa_width', array(), array( 5 ) );
		self::assertTrue( $this->saved->moved( 7 ) );
	}
}
