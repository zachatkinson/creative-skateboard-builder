<?php
/**
 * The Part specs panel, on real WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\PartSpecsHooks;
use CreativeBranding\SkateboardBuilder\Admin\PartSpecsPanel;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Scope;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Spec, Admin, Part specs: a panel on simple products and inside each variation; each field with its unit, taking
 * the shop's decimal separator or a fraction; a value from somewhere else shown with where it comes from; saved with
 * the product's own save, by who may edit products. Audit, Security and Accessibility.
 */
final class PartSpecsPanelTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
	}

	public function tear_down(): void {
		unset( $_POST['cbuild_specs'], $_POST['cbuild_spec_table'], $_POST['cbuild_variation_specs'], $_POST['cbuild_variation_spec_table'], $GLOBALS['product_object'] );
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		delete_option( 'woocommerce_price_decimal_sep' );
		delete_option( Options::SETTINGS );
		parent::tear_down();
	}

	private ?PartSpecsHooks $hooks = null;

	/**
	 * The hooks with a panel of this test's own: a panel keeps the charts it read for the length of a request.
	 */
	private function hooks(): PartSpecsHooks {
		if ( null === $this->hooks ) {
			$panel       = new PartSpecsPanel( $this->service( SpecWriter::class ), $this->service( SpecIndexer::class ), $this->service( SpecTables::class ) );
			$this->hooks = new PartSpecsHooks( static fn(): PartSpecsPanel => $panel );
		}
		return $this->hooks;
	}

	private function as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * A simple product with these fields of its own.
	 *
	 * @param array<string, string> $fields The fields.
	 */
	private static function simple( array $fields = array() ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Part' );
		$product->set_regular_price( '10' );
		if ( array() !== $fields ) {
			$product->update_meta_data( Options::META_SPECS, $fields );
		}
		$product->save();
		return $product;
	}

	/**
	 * A variable product and one variation of it.
	 *
	 * @param array<string, string> $fields The parent's own fields.
	 * @return array{0: \WC_Product_Variable, 1: \WC_Product_Variation}
	 */
	private static function variable( array $fields = array() ): array {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Trucks' );
		if ( array() !== $fields ) {
			$parent->update_meta_data( Options::META_SPECS, $fields );
		}
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '30' );
		$variation->save();
		return array( $parent, $variation );
	}

	/**
	 * A maker's chart with two sizes.
	 *
	 * @param string $name The maker.
	 */
	private function chart( string $name = 'Independent' ): int {
		return $this->service( SpecTables::class )->create(
			$name,
			SpecTable::read(
				array(
					array(
						'size'  => '149',
						'specs' => array(
							'axle_width'   => '8.5',
							'truck_height' => '55',
						),
					),
					array(
						'size'  => '159',
						'specs' => array( 'axle_width' => '8.75' ),
					),
				)
			)
		);
	}

	/**
	 * The tab's panel as drawn for this product.
	 *
	 * @param \WC_Product $product The product on the screen.
	 */
	private function panel( \WC_Product $product ): string {
		$GLOBALS['product_object'] = wc_get_product( $product->get_id() );
		ob_start();
		$this->hooks()->product();
		return (string) ob_get_clean();
	}

	/**
	 * A variation's fields as drawn.
	 *
	 * @param \WC_Product $variation The variation.
	 * @param int         $loop      Its row number.
	 */
	private function fields( \WC_Product $variation, int $loop = 0 ): string {
		ob_start();
		$this->hooks()->variation( $loop, array(), get_post( $variation->get_id() ) );
		return (string) ob_get_clean();
	}

	/**
	 * Send the tab's panel for this product, as WooCommerce's save does: the hook's callback, then the product's own save.
	 *
	 * @param \WC_Product   $product The product.
	 * @param array<mixed>  $specs   The fields.
	 * @param string|null   $chart   The chart field, null when it is not sent.
	 */
	private function send( \WC_Product $product, array $specs, ?string $chart = null ): \WC_Product {
		$_POST['cbuild_specs'] = wp_slash( $specs );
		if ( null !== $chart ) {
			$_POST['cbuild_spec_table'] = wp_slash( $chart );
		}
		$product = wc_get_product( $product->get_id() );
		$this->hooks()->saveProduct( $product );
		$product->save();
		return wc_get_product( $product->get_id() );
	}

	public function test_the_panel_hangs_on_woocommerces_hooks_in_the_admin_alone(): void {
		$built = static fn( array $scopes ): array => array_map( 'get_class', ( new \CreativeBranding\SkateboardBuilder\Container() )->hookablesFor( ...$scopes ) );
		self::assertContains( PartSpecsHooks::class, $built( Scope::atBoot( true, false, false ) ) );
		self::assertNotContains( PartSpecsHooks::class, $built( Scope::atBoot( false, false, false ) ), 'a storefront request builds nothing of the panel' );

		$hooks = $this->service( PartSpecsHooks::class );
		$hooks->register();
		self::assertSame( 20, has_action( 'admin_enqueue_scripts', array( $hooks, 'styles' ) ), 'after WooCommerce registers its style sheet' );
		remove_action( 'admin_enqueue_scripts', array( $hooks, 'styles' ), 20 );
		foreach ( array( 'woocommerce_product_data_tabs' => 'tab', 'woocommerce_product_data_panels' => 'product', 'woocommerce_product_after_variable_attributes' => 'variation', 'woocommerce_admin_process_product_object' => 'saveProduct', 'woocommerce_admin_process_variation_object' => 'saveVariation' ) as $hook => $method ) {
			self::assertSame( 10, has_filter( $hook, array( $hooks, $method ) ), $hook );
			remove_filter( $hook, array( $hooks, $method ), 10 );
		}

		$tabs = $hooks->tab( array( 'general' => array() ) );
		self::assertSame( array( 'general', 'cbuild_specs' ), array_keys( $tabs ) );
		self::assertSame( 'Part specs', $tabs['cbuild_specs']['label'] );
		self::assertSame( 'cbuild_part_specs', $tabs['cbuild_specs']['target'] );
		self::assertSame( array( 'show_if_simple', 'show_if_variable' ), $tabs['cbuild_specs']['class'] );
		self::assertSame( 'not tabs', $hooks->tab( 'not tabs' ) );
	}

	public function test_the_groups_headings_are_styled_on_the_product_screen_and_nowhere_else(): void {
		wp_register_style( 'woocommerce_admin_styles', 'https://example.com/admin.css', array(), '1' );
		wp_register_script( 'wc-admin-variation-meta-boxes', 'https://example.com/meta-boxes.js', array(), '1', true );
		$added  = static fn(): string => implode( '', (array) wp_styles()->get_data( 'woocommerce_admin_styles', 'after' ) );
		$inline = static fn(): string => implode( '', (array) wp_scripts()->get_data( 'wc-admin-variation-meta-boxes', 'after' ) );

		set_current_screen( 'dashboard' );
		$this->hooks()->styles();
		self::assertSame( '', $added() );
		self::assertFalse( wp_script_is( 'cbuild-part-specs', 'enqueued' ) );

		set_current_screen( 'product' );
		$this->hooks()->styles();
		self::assertStringContainsString( '.cbuild-part-specs summary{padding:8px 12px;', $added(), 'a target 34 pixels high' );
		// The Catalogue check's links open the tab, or the variation, they name: the built script, in the footer,
		// after the last of WooCommerce's product scripts, with the build's own version (D-086).
		self::assertTrue( wp_script_is( 'cbuild-part-specs', 'enqueued' ) );
		$script = wp_scripts()->registered['cbuild-part-specs'];
		$built  = require CBUILD_PATH . 'build/admin/part-specs.asset.php';
		self::assertSame( CBUILD_URL . 'build/admin/part-specs.js', $script->src );
		self::assertSame( array( 'jquery', 'wc-admin-variation-meta-boxes' ), $script->deps );
		self::assertSame( $built['version'], $script->ver );
		self::assertMatchesRegularExpression( '/^[0-9a-f]{20}$/', $script->ver );
		self::assertSame( 1, $script->extra['group'], 'in the footer' );
		self::assertSame( '', $inline(), 'no inline script (docs/STANDARDS.md: TypeScript)' );
		self::assertFileExists( CBUILD_PATH . 'build/admin/part-specs.js' );

		wp_dequeue_script( 'cbuild-part-specs' );
		wp_deregister_script( 'cbuild-part-specs' );

		wp_deregister_style( 'woocommerce_admin_styles' );
		wp_deregister_script( 'wc-admin-variation-meta-boxes' );
		set_current_screen( 'front' );
	}

	public function test_the_panel_draws_every_spec_once_each_with_a_label_its_unit_and_the_parts_own_value(): void {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$this->chart();
		$product = self::simple(
			array(
				'deck_width'       => '8.2500',
				'deck_length'      => '32.0000',
				'hole_pattern'     => 'new_school',
				'spacers_built_in' => '0',
				'set_size'         => '8',
			)
		);
		$panel = $this->panel( $product );

		self::assertStringContainsString( '<div id="cbuild_part_specs" class="panel woocommerce_options_panel hidden">', $panel, 'the tab\'s target' );
		foreach ( SpecKey::cases() as $key ) {
			$id = 'cbuild-spec-' . str_replace( '_', '-', $key->value );
			self::assertSame( 1, substr_count( $panel, '<label for="' . $id . '">' ), $key->value . ': a visible label' );
			self::assertSame( 1, substr_count( $panel, 'id="' . $id . '"' ), $key->value . ': for one control' );
			self::assertSame( 1, substr_count( $panel, 'name="cbuild_specs[' . $key->value . ']"' ), $key->value );
		}
		self::assertSame( 1, substr_count( $panel, '<label for="cbuild-spec-chart">Maker&#039;s chart and size</label>' ) );
		self::assertSame( 1, substr_count( $panel, 'name="cbuild_spec_table"' ) );
		self::assertStringContainsString( '<label for="cbuild-spec-deck-width">Deck width (in)</label>', $panel );
		self::assertStringContainsString( '<label for="cbuild-spec-truck-height">Truck height (mm)</label>', $panel );
		self::assertStringContainsString( '<label for="cbuild-spec-set-size">Number in a set</label>', $panel );

		self::assertMatchesRegularExpression( '/name="cbuild_specs\[deck_width\]"\s+value="8,25"/', $panel, 'no trailing zeros, the shop\'s separator' );
		self::assertMatchesRegularExpression( '/name="cbuild_specs\[deck_length\]"\s+value="32"/', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_specs\[wheelbase\]"\s+value=""/', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_specs\[set_size\]"\s+value="8"/', $panel );
		self::assertStringContainsString( '<option value="new_school"  selected=\'selected\'>New school</option>', $panel );
		self::assertStringContainsString( '<option value="0"  selected=\'selected\'>No</option>', $panel );
		self::assertSame( 4, substr_count( $panel, '<option value="">Not set</option>' ), 'three choices and one flag' );

		self::assertSame( 8, substr_count( $panel, '<details' ), 'a group per kind of part' );
		self::assertSame( 3, substr_count( $panel, '<details open>' ), 'open where the part has a value: deck, bearings and sets' );
		self::assertStringNotContainsString( '<fieldset', $panel, 'the tab names its panel; WooCommerce lays out a fieldset\'s labels differently' );
		self::assertStringContainsString( '<optgroup label="Independent">', $panel );
		self::assertStringContainsString( '<option value=""  selected=\'selected\'>None</option>', $panel );
		self::assertStringNotContainsString( 'Leave as it is', $panel );
		self::assertStringNotContainsString( 'In use:', $panel, 'every value in use is the part\'s own' );
	}

	public function test_a_value_from_somewhere_else_is_shown_beneath_its_field_with_where_it_comes_from(): void {
		update_option(
			Options::SETTINGS,
			array(
				'attribute_mapping' => array(
					array(
						'attribute' => 'Width <b>',
						'spec'      => 'deck_width',
						'unit'      => 'in',
					),
				),
			)
		);
		$chart   = $this->chart( 'Indy & "Co"' );
		$product = self::simple();
		$local   = new \WC_Product_Attribute();
		$local->set_name( 'Width <b>' );
		$local->set_options( array( '8.25' ) );
		$product->set_attributes( array( $local ) );
		$product->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => $chart,
				'size'  => '149',
			)
		);
		$product->save();
		$panel = $this->panel( $product );

		self::assertStringContainsString( '<span class="description" id="cbuild-spec-deck-width-note">In use: 8.25 in, from the Width &lt;b&gt; attribute.</span>', $panel );
		self::assertMatchesRegularExpression( '/id="cbuild-spec-deck-width"[^>]*aria-describedby="cbuild-spec-deck-width-note"/s', $panel, 'tied to its field' );
		self::assertStringContainsString( 'id="cbuild-spec-axle-width-note">In use: 8.5 in, from Indy &amp; &quot;Co&quot;&#039;s chart, size 149.</span>', $panel );
		self::assertStringContainsString( 'id="cbuild-spec-truck-height-note">In use: 55 mm, from Indy', $panel );
		self::assertStringContainsString( '<optgroup label="Indy &amp; &quot;Co&quot;">', $panel );
		self::assertStringNotContainsString( '<b>', $panel );
		self::assertStringContainsString( '<option value="' . $chart . ':149"  selected=\'selected\'>149</option>', $panel );
		self::assertStringContainsString( '<option value="' . $chart . ':159" >159</option>', $panel );
		self::assertSame( 2, substr_count( $panel, '<details open>' ), 'the deck and the trucks, which have a value in use' );
	}

	public function test_a_variation_has_the_same_fields_of_its_own_and_says_what_it_takes_from_its_parent(): void {
		[ $parent, $variation ] = self::variable(
			array(
				'axle_width'  => '8.5000',
				'baseplate'   => 'both',
				'bolt_length' => '1.0000',
			)
		);
		$variation->update_meta_data( Options::META_SPECS, array( 'bolt_length' => '0.8750' ) );
		$variation->save();
		$fields = $this->fields( $variation, 3 );

		self::assertStringNotContainsString( 'cbuild_part_specs', $fields, 'no tab panel around a variation\'s fields' );
		self::assertStringContainsString( '<legend><strong>Part specs</strong></legend>', $fields );
		foreach ( SpecKey::cases() as $key ) {
			$id = 'cbuild-spec-3-' . str_replace( '_', '-', $key->value );
			self::assertSame( 1, substr_count( $fields, '<label for="' . $id . '">' ), $key->value );
			self::assertSame( 1, substr_count( $fields, 'id="' . $id . '"' ), $key->value );
			self::assertSame( 1, substr_count( $fields, 'name="cbuild_variation_specs[3][' . $key->value . ']"' ), $key->value );
		}
		self::assertSame( 1, substr_count( $fields, 'name="cbuild_variation_spec_table[3]"' ) );
		self::assertMatchesRegularExpression( '/name="cbuild_variation_specs\[3\]\[axle_width\]"\s+value=""/', $fields, 'its own field is blank' );
		self::assertStringContainsString( 'id="cbuild-spec-3-axle-width-note">In use: 8.5 in, from the parent product.</span>', $fields );
		self::assertStringContainsString( 'id="cbuild-spec-3-baseplate-note">In use: Both, from the parent product.</span>', $fields );
		self::assertMatchesRegularExpression( '/name="cbuild_variation_specs\[3\]\[bolt_length\]"\s+value="0.875"/', $fields );
		self::assertStringNotContainsString( 'cbuild-spec-3-bolt-length-note', $fields, 'its own value is in use' );

		unset( $parent );
		ob_start();
		$this->hooks()->variation( 0, array(), 'not a post' );
		$this->hooks()->variation( 'x', array(), get_post( $variation->get_id() ) );
		self::assertSame( '', ob_get_clean() );
	}

	public function test_the_charts_are_asked_for_once_however_many_variations_are_drawn(): void {
		$this->chart();
		[ , $variation ] = self::variable();
		$this->fields( $variation, 0 );
		$log = array();
		$spy = static function ( string $query ) use ( &$log ): string {
			if ( str_contains( $query, Options::POST_TYPE_SPEC_TABLE ) ) {
				$log[] = $query;
			}
			return $query;
		};
		add_filter( 'query', $spy );
		$this->fields( $variation, 1 );
		$this->fields( $variation, 2 );
		remove_filter( 'query', $spy );
		self::assertSame( array(), $log, 'the first variation asked; the rest reuse it' );
	}

	public function test_who_can_edit_products_saves_the_fields_as_the_index_writes_them_and_the_part_is_queued(): void {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$this->as( 'shop_manager' );
		$chart   = $this->chart();
		$product = self::simple( array( 'wheelbase' => '14.0000' ) );
		self::work();
		$saved = $this->send(
			$product,
			array(
				'deck_width'       => ' 8,25 ',
				'deck_length'      => '31 7/8',
				'wheelbase'        => '',
				'hole_pattern'     => 'new_school',
				'spacers_built_in' => '1',
				'set_size'         => '8',
				'truck_height'     => '',
				'kingpin'          => 'inverted',
			),
			$chart . ':149'
		);
		self::assertSame(
			array(
				'deck_width'       => '8.2500',
				'deck_length'      => '31.8750',
				'hole_pattern'     => 'new_school',
				'spacers_built_in' => '1',
				'set_size'         => '8',
			),
			$saved->get_meta( Options::META_SPECS, true )
		);
		self::assertSame(
			array(
				'table' => $chart,
				'size'  => '149',
			),
			$saved->get_meta( Options::META_SPEC_TABLE, true )
		);
		self::assertSame( array(), \WC_Admin_Meta_Boxes::$meta_box_errors );
		self::assertCount( 1, self::waiting( Options::JOB_INDEX_PART ), 'the save queues the part, and indexes nothing itself' );

		$cleared = $this->send( $saved, array_fill_keys( array_column( SpecKey::cases(), 'value' ), '' ), '' );
		self::assertSame( '', $cleared->get_meta( Options::META_SPECS, true ), 'nothing left to store, so the meta goes' );
		self::assertSame( '', $cleared->get_meta( Options::META_SPEC_TABLE, true ) );
	}

	public function test_a_value_that_is_wrong_is_not_saved_what_was_there_is_kept_and_the_notice_says_which(): void {
		$this->as( 'administrator' );
		$chart   = $this->chart();
		$product = self::simple( array( 'deck_width' => '8.2500' ) );
		$saved   = $this->send(
			$product,
			array(
				'deck_width'  => '<b>wide</b>',
				'deck_length' => '32',
				'set_size'    => '0',
			),
			$chart . ':169'
		);
		self::assertSame(
			array(
				'deck_width'  => '8.2500',
				'deck_length' => '32.0000',
			),
			$saved->get_meta( Options::META_SPECS, true ),
			'the rest of the product saves as usual'
		);
		self::assertSame( '', $saved->get_meta( Options::META_SPEC_TABLE, true ) );
		self::assertSame(
			array(
				'Deck width was not saved: &quot;wide&quot; is not a measurement. Type a number, or a fraction such as 8 1/4.',
				'Number in a set was not saved: &quot;0&quot; must be more than zero.',
				'The maker&#039;s chart was not saved: the chart has no size 169 now. Choose again.',
			),
			\WC_Admin_Meta_Boxes::$meta_box_errors,
			'each escaped, since WooCommerce prints its notices as HTML'
		);
	}

	public function test_the_chart_field_leaves_what_it_cannot_show_and_a_chart_that_is_gone_is_refused(): void {
		$this->as( 'shop_manager' );
		$chart   = $this->chart();
		$product = self::simple();
		$product->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => $chart,
				'size'  => '',
			)
		);
		$product->save();
		$panel = $this->panel( $product );
		self::assertStringContainsString( '<option value="keep"  selected=\'selected\'>Leave as it is</option>', $panel, 'a chart with no size, as an import may store' );

		$kept = $this->send( $product, array(), PartSpecsPanel::KEEP );
		self::assertSame(
			array(
				'table' => $chart,
				'size'  => '',
			),
			$kept->get_meta( Options::META_SPEC_TABLE, true )
		);
		$gone = $this->send( $product, array(), ( $chart + 999 ) . ':149' );
		self::assertSame( $chart, $gone->get_meta( Options::META_SPEC_TABLE, true )['table'] );
		self::assertSame( array( 'The maker&#039;s chart was not saved: that chart is no longer there. Choose again.' ), \WC_Admin_Meta_Boxes::$meta_box_errors );
	}

	public function test_who_cannot_edit_products_saves_nothing_and_a_save_without_the_fields_changes_nothing(): void {
		$product = self::simple( array( 'deck_width' => '8.2500' ) );
		$this->as( 'subscriber' );
		$same = $this->send( $product, array( 'deck_width' => '9' ), '' );
		self::assertSame( array( 'deck_width' => '8.2500' ), $same->get_meta( Options::META_SPECS, true ) );

		// A quick edit, or another plugin's save: the hook fires and the panel's fields are not in the request.
		$this->as( 'shop_manager' );
		unset( $_POST['cbuild_specs'], $_POST['cbuild_spec_table'] );
		$quick = wc_get_product( $product->get_id() );
		$this->hooks()->saveProduct( $quick );
		$quick->save();
		self::assertSame( array( 'deck_width' => '8.2500' ), wc_get_product( $product->get_id() )->get_meta( Options::META_SPECS, true ) );

		$_POST['cbuild_specs'] = 'not fields';
		$this->hooks()->saveProduct( $quick );
		$this->hooks()->saveProduct( 'not a product' );
		$quick->save();
		self::assertSame( array( 'deck_width' => '8.2500' ), wc_get_product( $product->get_id() )->get_meta( Options::META_SPECS, true ) );
	}

	public function test_a_variation_saves_its_own_row_of_fields_and_names_itself_in_a_notice(): void {
		[ , $variation ] = self::variable();
		$chart           = $this->chart();
		$_POST['cbuild_variation_specs']      = wp_slash(
			array(
				0 => array( 'axle_width' => '9' ),
				2 => array(
					'axle_width'   => '8 1/2',
					'truck_height' => 'tall',
				),
			)
		);
		$_POST['cbuild_variation_spec_table'] = array( 2 => $chart . ':159' );

		$this->as( 'subscriber' );
		$loaded = wc_get_product( $variation->get_id() );
		$this->hooks()->saveVariation( $loaded, 2 );
		$loaded->save();
		self::assertSame( '', wc_get_product( $variation->get_id() )->get_meta( Options::META_SPECS, true ), 'WooCommerce checks no nonce here, so the plugin checks the capability' );

		$this->as( 'shop_manager' );
		$loaded = wc_get_product( $variation->get_id() );
		$this->hooks()->saveVariation( $loaded, 2 );
		$this->hooks()->saveVariation( $loaded, 7 );
		$this->hooks()->saveVariation( $loaded, '2' );
		$this->hooks()->saveVariation( 'not a variation', 2 );
		$loaded->save();
		$saved = wc_get_product( $variation->get_id() );
		self::assertSame( array( 'axle_width' => '8.5000' ), $saved->get_meta( Options::META_SPECS, true ), 'row 2, not row 0' );
		self::assertSame(
			array(
				'table' => $chart,
				'size'  => '159',
			),
			$saved->get_meta( Options::META_SPEC_TABLE, true )
		);
		self::assertSame(
			array( 'Variation #' . $variation->get_id() . ': Truck height was not saved: &quot;tall&quot; is not a measurement. Type a number, or a fraction such as 8 1/4.' ),
			\WC_Admin_Meta_Boxes::$meta_box_errors
		);
	}
}
