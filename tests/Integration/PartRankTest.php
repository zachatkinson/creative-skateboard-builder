<?php
/**
 * A part's rank in its slot.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\PartSpecsHooks;
use CreativeBranding\SkateboardBuilder\Admin\PartSpecsPanel;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\PartRanks;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * #135: a part's rank in its slot, which smart swaps' "better" follows. A whole number on the part's Part specs, and
 * on each variation's, where a variation with none takes its product's; another plugin's answer to
 * `cbuild_part_rank` is last (D-395).
 */
final class PartRankTest extends IntegrationTestCase {

	public function tear_down(): void {
		unset( $_POST['cbuild_rank'], $_POST['cbuild_variation_rank'], $GLOBALS['product_object'] );
		remove_all_filters( 'cbuild_part_rank' );
		parent::tear_down();
	}

	private function hooks(): PartSpecsHooks {
		$panel = ( new PartSpecsPanel( $this->service( SpecWriter::class ), $this->service( SpecIndexer::class ), $this->service( SpecTables::class ), $this->service( Kits::class ) ) )->withImages( $this->service( LayerImages::class ) );
		return new PartSpecsHooks( static fn(): PartSpecsPanel => $panel );
	}

	/**
	 * A simple part with specs, so its panel has fields.
	 *
	 * @param string|null $rank Its rank as stored, or null for none.
	 */
	private static function part( ?string $rank = null ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Wheels' );
		$product->set_regular_price( '30' );
		$product->update_meta_data( Options::META_SPECS, array( 'wheel_diameter' => '52.0000' ) );
		if ( null !== $rank ) {
			$product->update_meta_data( Options::META_RANK, $rank );
		}
		$product->save();
		return $product;
	}

	/**
	 * The product's panel as drawn.
	 *
	 * @param \WC_Product $product The product.
	 */
	private function panel( \WC_Product $product ): string {
		$GLOBALS['product_object'] = wc_get_product( $product->get_id() );
		ob_start();
		$this->hooks()->product();
		return (string) ob_get_clean();
	}

	/**
	 * Send a rank as the product's screen does, through the panel's save and the product's own.
	 *
	 * @param \WC_Product $product The product.
	 * @param string|null $sent    The field, or null where it is not sent.
	 */
	private function send( \WC_Product $product, ?string $sent ): string {
		unset( $_POST['cbuild_rank'] );
		if ( null !== $sent ) {
			$_POST['cbuild_rank'] = wp_slash( $sent );
		}
		$loaded = wc_get_product( $product->get_id() );
		$this->hooks()->saveProduct( $loaded );
		$loaded->save();
		clean_post_cache( $product->get_id() );
		return (string) wc_get_product( $product->get_id() )->get_meta( Options::META_RANK, true );
	}

	/**
	 * The ranks the builder reads, as a later request reads them.
	 *
	 * @param int ...$parts The parts.
	 * @return array<int, int>
	 */
	private function ranks( int ...$parts ): array {
		foreach ( $parts as $part ) {
			clean_post_cache( $part );
		}
		return $this->service( PartRanks::class )->ranks( ...$parts );
	}

	public function test_the_panel_has_a_labelled_whole_number_field_with_the_parts_own_rank(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$panel = $this->panel( self::part( '4' ) );
		self::assertStringContainsString( '<details class="cbuild-rank" open>', $panel, 'open where the part has a rank' );
		self::assertMatchesRegularExpression( '#<label for="cbuild-spec-rank">Rank in its slot</label>#', $panel );
		self::assertMatchesRegularExpression( '#<input\s+type="number"[^>]*min="0"[^>]*step="1"[^>]*id="cbuild-spec-rank"\s+name="cbuild_rank"\s+value="4"[^>]*aria-describedby="cbuild-spec-rank-note"\s*/>#', $panel, 'and enabled' );
		self::assertStringContainsString( 'id="cbuild-spec-rank-note"', $panel );

		// Closed where it has none, its field disabled, so a page of variations sends nothing for it (issue #23).
		$none = $this->panel( self::part() );
		self::assertStringContainsString( '<details class="cbuild-rank">', $none );
		self::assertMatchesRegularExpression( '#name="cbuild_rank"\s+value=""[^>]*disabled#', $none );
	}

	public function test_a_whole_number_is_saved_an_empty_field_takes_it_off_and_anything_else_keeps_what_was_there(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$part = self::part();
		self::assertSame( '7', $this->send( $part, ' 7 ' ) );
		foreach ( array( '-1', '2.5', 'top', '07', '1000000000', '1e3' ) as $wrong ) {
			self::assertSame( '7', $this->send( $part, $wrong ), $wrong . ' is not a rank' );
		}
		self::assertSame( '7', $this->send( $part, null ), 'not sent: kept' );
		self::assertSame( '0', $this->send( $part, '0' ) );
		self::assertSame( '999999999', $this->send( $part, '999999999' ) );
		self::assertSame( '', $this->send( $part, '' ), 'emptied: no rank' );
		self::assertSame( array(), $this->ranks( $part->get_id() ) );
	}

	public function test_a_variation_has_its_own_rank_or_its_products_and_a_plugin_answers_last(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Wheels, in two colours' );
		$parent->update_meta_data( Options::META_SPECS, array( 'wheel_diameter' => '54.0000' ) );
		$parent->update_meta_data( Options::META_RANK, '3' );
		$parent->save();
		$ids = array();
		foreach ( array( 1, 2 ) as $each ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_regular_price( (string) ( 30 + $each ) );
			$variation->save();
			$ids[] = $variation->get_id();
		}
		[ $first, $second ] = $ids;

		$_POST['cbuild_variation_rank'] = array( 4 => '9' );
		$variation                      = wc_get_product( $first );
		$this->hooks()->saveVariation( $variation, 4 );
		$variation->save();
		$plain = self::part();
		self::assertSame(
			array(
				$first  => 9,
				$second => 3,
			),
			$this->ranks( $first, $second, $plain->get_id() ),
			'its own, its product\'s, and none for a part with none'
		);

		ob_start();
		$this->hooks()->variation( 4, array(), get_post( $second ) );
		$fields = (string) ob_get_clean();
		self::assertMatchesRegularExpression( '#name="cbuild_variation_rank\[4\]"\s+value=""#', $fields, 'its own field is empty: the product\'s rank is not written onto it' );

		add_filter( 'cbuild_part_rank', static fn( ?int $rank, int $id ): ?int => $id === $second ? null : ( $rank ?? 1 ), 10, 2 );
		self::assertSame(
			array(
				$first           => 9,
				$plain->get_id() => 1,
			),
			$this->ranks( $first, $second, $plain->get_id() )
		);
	}

	public function test_who_cannot_edit_products_saves_no_rank(): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'customer' ) ) );
		self::assertSame( '', $this->send( self::part(), '5' ) );
	}
}
