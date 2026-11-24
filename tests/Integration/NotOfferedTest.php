<?php
/**
 * A part "Not offered in the builder".
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\PartSpecsHooks;
use CreativeBranding\SkateboardBuilder\Admin\PartSpecsPanel;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\PartsKeptOut;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * #116: "Not offered in the builder", a checkbox on a part's Part specs and on each variation's. Ticked, the part is
 * left out exactly as `cbuild_part_available` returning false leaves it out, on the same path: the stored box is the
 * filter's first value, so the filter answers last. A change tells what is kept of the part, and the Catalogue check
 * lists the parts kept out (D-351).
 */
final class NotOfferedTest extends IntegrationTestCase {

	/**
	 * The deck slot's category.
	 */
	private int $decks = 0;

	/**
	 * The kit whose deck slot takes it.
	 */
	private int $kit = 0;

	public function set_up(): void {
		parent::set_up();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		// What keeps each slot's parts current (D-116).
		$this->service( \CreativeBranding\SkateboardBuilder\Hooks\CardHooks::class )->register();
		$this->decks                  = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array( 'categories' => array( $this->decks ) );
		$this->kit                    = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
	}

	public function tear_down(): void {
		unset( $_POST['cbuild_not_offered'], $_POST['cbuild_variation_not_offered'], $_POST['product-type'], $GLOBALS['product_object'] );
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		parent::tear_down();
	}

	private function panel(): PartSpecsPanel {
		return ( new PartSpecsPanel( $this->service( SpecWriter::class ), $this->service( SpecIndexer::class ), $this->service( SpecTables::class ), $this->service( Kits::class ) ) )->withImages( $this->service( LayerImages::class ) );
	}

	private function hooks(): PartSpecsHooks {
		$panel = $this->panel();
		return new PartSpecsHooks( static fn(): PartSpecsPanel => $panel );
	}

	private function as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * A published deck in the kit's deck slot, indexed.
	 *
	 * @param bool $kept Whether its box is ticked.
	 */
	private function deck( bool $kept = false ): int {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Deck' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '60' );
		$product->set_category_ids( array( $this->decks ) );
		$product->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.2500' ) );
		if ( $kept ) {
			$product->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		}
		$product->save();
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		return $product->get_id();
	}

	/**
	 * A published variable deck in the deck slot, with two variations.
	 *
	 * @return array{0: int, 1: int, 2: int} The product's ID and its variations'.
	 */
	private function variable(): array {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Deck, in two colours' );
		$parent->set_status( 'publish' );
		$parent->set_category_ids( array( $this->decks ) );
		$parent->update_meta_data( Options::META_SPECS, array( 'deck_width' => '8.5000' ) );
		$parent->save();
		$ids = array( $parent->get_id() );
		foreach ( array( 1, 2 ) as $each ) {
			$variation = new \WC_Product_Variation();
			$variation->set_parent_id( $parent->get_id() );
			$variation->set_regular_price( (string) ( 60 + $each ) );
			$variation->set_status( 'publish' );
			$variation->save();
			$ids[] = $variation->get_id();
		}
		foreach ( $ids as $id ) {
			$this->service( SpecIndexer::class )->index( $id );
		}
		return array( $ids[0], $ids[1], $ids[2] );
	}

	/**
	 * The deck slot's parts, as a later request reads them.
	 *
	 * @return list<int>
	 */
	private function offered(): array {
		$ids = array_map( static fn( Part $part ): int => $part->id, $this->service( Kits::class )->candidates( $this->kit, 'deck' ) );
		sort( $ids );
		return $ids;
	}

	/**
	 * Tick or untick a product's box, as the product's screen sends it, through the panel's save and the product's own.
	 *
	 * @param int         $id   The product.
	 * @param string|null $sent What the box sends: '1' ticked, or null for nothing, as a box not ticked sends.
	 * @param bool        $form Whether the product data box was sent, as the product's screen sends it.
	 */
	private function send( int $id, ?string $sent, bool $form = true ): void {
		unset( $_POST['cbuild_not_offered'], $_POST['product-type'] );
		if ( null !== $sent ) {
			$_POST['cbuild_not_offered'] = $sent;
		}
		if ( $form ) {
			$_POST['product-type'] = 'simple';
		}
		$product = wc_get_product( $id );
		$this->hooks()->saveProduct( $product );
		$product->save();
	}

	private static function stored( int $id ): string {
		clean_post_cache( $id );
		return (string) wc_get_product( $id )->get_meta( Options::META_NOT_OFFERED, true );
	}

	public function test_a_part_with_no_box_is_offered_as_in_1_1_0_and_one_ticked_is_not(): void {
		$deck = $this->deck();
		self::assertSame( array( $deck ), $this->offered() );
		$kept = $this->deck( true );
		self::assertSame( array( $deck ), $this->offered() );
	}

	public function test_the_box_is_the_filters_first_value_so_the_filter_answers_last(): void {
		$deck   = $this->deck();
		$kept   = $this->deck( true );
		$asked  = array();
		$answer = static function ( bool $available, int $id ) use ( &$asked, $kept ): bool {
			$asked[ $id ] = $available;
			return $kept === $id ? true : $available;
		};
		add_filter( 'cbuild_part_available', $answer, 10, 2 );
		do_action( 'cbuild_parts_changed' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own prefix.
		self::assertSame( array( $deck, $kept ), $this->offered(), 'a plugin offers it all the same' );
		self::assertSame( array( true, false ), array( $asked[ $deck ], $asked[ $kept ] ), 'asked with the box as its first value' );

		remove_filter( 'cbuild_part_available', $answer, 10 );
		add_filter( 'cbuild_part_available', static fn( bool $available, int $id ): bool => $deck === $id ? false : $available, 10, 2 );
		do_action( 'cbuild_parts_changed' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own prefix.
		self::assertSame( array(), $this->offered(), 'and keeps out one the shop offers' );
	}

	public function test_a_products_box_keeps_out_its_variations_and_a_variations_own_keeps_out_it_alone(): void {
		[ $parent, $first, $second ] = $this->variable();
		self::assertSame( array( $first, $second ), $this->offered() );

		$this->as( 'shop_manager' );
		$_POST['cbuild_variation_not_offered'] = array( 3 => '1' );
		$variation                             = wc_get_product( $first );
		$this->hooks()->saveVariation( $variation, 3 );
		$variation->save();
		self::assertSame( 'yes', self::stored( $first ) );
		self::assertSame( array( $second ), $this->offered(), 'the variation\'s save told what is kept of it' );

		$this->send( $parent, '1' );
		self::assertSame( array(), $this->offered(), 'the product\'s box keeps out every variation' );

		$this->send( $parent, null );
		$_POST['cbuild_variation_not_offered'] = array();
		$variation                             = wc_get_product( $first );
		$this->hooks()->saveVariation( $variation, 3 );
		$variation->save();
		self::assertSame( '', self::stored( $first ), 'unticked is no meta at all' );
		self::assertSame( array( $first, $second ), $this->offered() );
	}

	// The save path: who may edit products, WooCommerce's nonce before the hook, and only '1' ticks the box.
	public function test_the_box_is_saved_by_who_may_edit_products_through_woocommerces_save_and_tells_what_is_kept(): void {
		$deck    = $this->deck();
		$changed = did_action( 'cbuild_parts_changed' );

		$this->as( 'subscriber' );
		$this->send( $deck, '1' );
		self::assertSame( '', self::stored( $deck ), 'who may not edit products changes nothing' );

		$this->as( 'shop_manager' );
		foreach ( array( 'yes', 'on', '2', '' ) as $other ) {
			$this->send( $deck, $other );
			self::assertSame( '', self::stored( $deck ), $other . ' is not a tick' );
		}
		self::assertSame( $changed, did_action( 'cbuild_parts_changed' ), 'nothing changed, nothing said' );

		$this->send( $deck, '1' );
		self::assertSame( 'yes', self::stored( $deck ) );
		self::assertSame( $changed + 1, did_action( 'cbuild_parts_changed' ), 'a change says so, as a plugin does' );
		self::assertSame( array(), $this->offered() );
		$this->send( $deck, '1' );
		self::assertSame( $changed + 1, did_action( 'cbuild_parts_changed' ), 'and only a change' );

		$this->send( $deck, null, false );
		self::assertSame( 'yes', self::stored( $deck ), 'a save without the product data box, as another plugin\'s, changes nothing' );
		$this->send( $deck, null );
		self::assertSame( '', self::stored( $deck ), 'the form sent with the box not ticked unticks it' );
		self::assertSame( array( $deck ), $this->offered() );

		// Through WooCommerce's own save: nothing without its nonce.
		$this->hooks()->register();
		$save = static function ( ?string $nonce ) use ( $deck ): void {
			$property = new \ReflectionProperty( \WC_Admin_Meta_Boxes::class, 'saved_meta_boxes' );
			$property->setValue( null, false );
			$_POST = array(
				'post_ID'            => (string) $deck,
				'product-type'       => 'simple',
				'cbuild_not_offered' => '1',
			) + ( null === $nonce ? array() : array( 'woocommerce_meta_nonce' => $nonce ) );
			( new \WC_Admin_Meta_Boxes() )->save_meta_boxes( $deck, get_post( $deck ) );
		};
		try {
			$save( null );
			self::assertSame( '', self::stored( $deck ), 'no nonce, nothing saved' );
			$save( 'not-a-nonce' );
			self::assertSame( '', self::stored( $deck ), 'a wrong nonce, nothing saved' );
			$save( wp_create_nonce( 'woocommerce_save_data' ) );
			self::assertSame( 'yes', self::stored( $deck ) );
		} finally {
			$_POST = array();
		}
	}

	public function test_the_panel_draws_the_box_on_a_product_and_on_each_variation(): void {
		$this->as( 'shop_manager' );
		$deck = $this->deck( true );
		$GLOBALS['product_object'] = wc_get_product( $deck );
		ob_start();
		$this->hooks()->product();
		$panel = (string) ob_get_clean();
		self::assertMatchesRegularExpression( '#<p class="form-field cbuild-not-offered">\s*<input\s+type="checkbox"\s+class="checkbox"\s+id="cbuild-spec-not-offered"\s+name="cbuild_not_offered"\s+value="1"\s+checked=\'checked\'\s+aria-describedby="cbuild-spec-not-offered-note"#', $panel );
		self::assertStringContainsString( '<label for="cbuild-spec-not-offered">Not offered in the builder</label>', $panel );

		[ , $variation ] = $this->variable();
		ob_start();
		$this->hooks()->variation( 4, array(), get_post( $variation ) );
		$fields = (string) ob_get_clean();
		self::assertMatchesRegularExpression( '#<p class="form-row form-row-full cbuild-not-offered">\s*<input\s+type="checkbox"\s+class="checkbox"\s+id="cbuild-spec-4-not-offered"\s+name="cbuild_variation_not_offered\[4\]"\s+value="1"\s+aria-describedby="cbuild-spec-4-not-offered-note"#', $fields );

		// A part no kit takes has no fields, unless its box is ticked, which must be seen to be unticked.
		$alone = new \WC_Product_Simple();
		$alone->set_name( 'No kit takes it' );
		$alone->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$alone->save();
		$GLOBALS['product_object'] = wc_get_product( $alone->get_id() );
		ob_start();
		$this->hooks()->product();
		self::assertStringContainsString( 'id="cbuild-spec-not-offered"', (string) ob_get_clean() );
		$alone->delete_meta_data( Options::META_NOT_OFFERED );
		$alone->save();
		$GLOBALS['product_object'] = wc_get_product( $alone->get_id() );
		ob_start();
		$this->hooks()->product();
		self::assertStringNotContainsString( 'id="cbuild-spec-not-offered"', (string) ob_get_clean() );
	}

	public function test_the_catalogue_check_lists_the_parts_kept_out_and_by_whose_box(): void {
		$deck                        = $this->deck( true );
		[ $parent, $first, $second ] = $this->variable();
		$product                     = wc_get_product( $parent );
		$product->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$product->save();
		$variation = wc_get_product( $second );
		$variation->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$variation->save();
		$offered = $this->deck();

		self::assertSame(
			array(
				$deck   => PartsKeptOut::OWN,
				$first  => PartsKeptOut::PRODUCT,
				$second => PartsKeptOut::OWN,
			),
			$this->service( PartsKeptOut::class )->keptOut( $deck, $first, $second, $offered, 999999 )
		);
		$found = array();
		foreach ( $this->service( CatalogueCheck::class )->run() as $finding ) {
			self::assertInstanceOf( CheckFinding::class, $finding );
			if ( CatalogueCheck::NOT_OFFERED === $finding->code ) {
				$found[ $finding->part ] = $finding->values['from'] ?? '';
			}
		}
		// A variable product is no part, and is not listed: its variations are, each saying it is the product's box.
		self::assertSame(
			array(
				$deck   => 'part',
				$first  => 'product',
				$second => 'part',
			),
			$found
		);
	}

	// Performance: the box is read from what WooCommerce already loaded of each part, so six parts kept out by their
	// box cost the slot what six kept out by a plugin's answer do, and no query more.
	public function test_reading_the_box_asks_no_query_a_part(): void {
		$count = function (): int {
			do_action( 'cbuild_parts_changed' ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own prefix.
			wp_cache_flush_runtime();
			$asked = 0;
			$watch = static function ( string $query ) use ( &$asked ): string {
				++$asked;
				return $query;
			};
			add_filter( 'query', $watch );
			$this->offered();
			remove_filter( 'query', $watch );
			return $asked;
		};
		$decks  = array_map( fn(): int => $this->deck(), range( 1, 8 ) );
		$out    = array_slice( $decks, 2 );
		$plugin = static fn( bool $available, int $id ): bool => in_array( $id, $out, true ) ? false : $available;
		add_filter( 'cbuild_part_available', $plugin, 10, 2 );
		self::assertSame( array_slice( $decks, 0, 2 ), $this->offered() );
		$filtered = $count();
		remove_filter( 'cbuild_part_available', $plugin, 10 );

		foreach ( $out as $deck ) {
			$product = wc_get_product( $deck );
			$product->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
			$product->save();
		}
		self::assertSame( array_slice( $decks, 0, 2 ), $this->offered() );
		self::assertSame( $filtered, $count(), 'six kept out by their box ask what six kept out by a plugin did' );
	}
}
