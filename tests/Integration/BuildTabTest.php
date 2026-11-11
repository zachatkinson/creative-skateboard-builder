<?php
/**
 * The kit's Build tab, on real WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\KitProduct;
use CreativeBranding\SkateboardBuilder\Admin\BuildTab;
use CreativeBranding\SkateboardBuilder\Admin\BuildTabHooks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Port\DocumentSchemas;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Service\KitEditor;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;

/**
 * Spec, Admin, The kit's Build tab: a product data tab on kit products, saved through the product's own CRUD by who
 * may manage the shop; each setting checked, one that fails not saved while the rest are, and a notice for each
 * problem with a link to its field. Roadmap, milestone 4: "The Build tab with schema validation and up to six
 * presets". Audit, Security and Accessibility.
 */
final class BuildTabTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-meta-boxes.php';
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
	}

	public function tear_down(): void {
		unset( $_POST[ BuildTab::FIELD ], $GLOBALS['product_object'] );
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		delete_option( 'woocommerce_price_decimal_sep' );
		parent::tear_down();
	}

	private function hooks(): BuildTabHooks {
		$tab = new BuildTab( $this->service( KitEditor::class ), $this->service( ShopSettings::class ) );
		return new BuildTabHooks( static fn(): BuildTab => $tab );
	}

	private function as( string $role ): int {
		$user = self::factory()->user->create( array( 'role' => $role ) );
		wp_set_current_user( $user );
		return $user;
	}

	/**
	 * The tab's panel as drawn for this product.
	 *
	 * @param \WC_Product $product The product on the screen.
	 */
	private function panel( \WC_Product $product ): string {
		// As a later request: nothing of the product in memory. Not a flush of the whole cache, which on a site with a
		// persistent one would take what the person typed with it.
		clean_post_cache( $product->get_id() );
		$GLOBALS['product_object'] = wc_get_product( $product->get_id() );
		ob_start();
		$this->hooks()->panel();
		return (string) ob_get_clean();
	}

	/**
	 * The tab's fields as a browser sends the fixtures' kit, with these in place of its sections.
	 *
	 * @param array<string, mixed> $over Sections to send in place of the fixtures'.
	 * @return array<string, mixed>
	 */
	private static function form( array $over = array() ): array {
		$slots = array();
		foreach ( KitDocuments::slots()['slots'] as $slot ) {
			$slots[] = array(
				'key'   => $slot['key'],
				'label' => $slot['label'],
				'units' => (string) $slot['units'],
			) + ( $slot['required'] ? array( 'required' => '1' ) : array() );
		}
		$on = array( 'enabled' => '1' );
		return $over + array(
			'slots'    => $slots,
			'rules'    => array(
				'r1' => $on + array(
					'range_tolerance' => '0',
					'axle_fit'        => '0.25',
					'axle_warn'       => '0.5',
				),
				'r2' => $on,
				'r3' => $on + array(
					'limits' => array(
						'low'  => '53',
						'mid'  => '56',
						'high' => '56',
					),
					'risers' => array(
						array(
							'over'  => '2',
							'riser' => '1/4',
						),
						array(
							'over'  => '6',
							'riser' => '0.5',
						),
						array(
							'over'  => '',
							'riser' => '',
						),
					),
				),
				'r4' => $on,
				'r5' => $on,
				'r6' => $on + array( 'check_length' => '1' ),
			),
			'presets'  => array(
				array(
					'name'        => '',
					'description' => '',
				),
			),
			'assembly' => array(
				'default_on'     => '1',
				'fee'            => '0',
				'lead_time'      => '',
				'returns_notice' => '',
			),
		);
	}

	/**
	 * Send the tab as the browser does and let WooCommerce's hook save it, then write the product.
	 *
	 * @param \WC_Product          $product The product being saved.
	 * @param array<string, mixed> $sent    The tab's fields.
	 */
	private function send( \WC_Product $product, array $sent ): void {
		$_POST[ BuildTab::FIELD ] = wp_slash( $sent );
		$this->hooks()->save( $product );
		$product->save();
	}

	/**
	 * The kit's documents as a later request reads them, by meta key.
	 *
	 * @param int $id The kit's ID.
	 * @return array<string, array<string, mixed>|null>
	 */
	private static function stored( int $id ): array {
		clean_post_cache( $id );
		$kit = wc_get_product( $id );
		self::assertInstanceOf( KitProduct::class, $kit );
		return $kit->documents();
	}

	/**
	 * The notices WooCommerce will print after the save.
	 *
	 * @return list<string>
	 */
	private static function notices(): array {
		return array_values( \WC_Admin_Meta_Boxes::$meta_box_errors );
	}

	public function test_the_tab_is_on_the_product_data_box_and_shows_on_kits_alone(): void {
		$this->hooks()->register();
		$tabs = apply_filters( 'woocommerce_product_data_tabs', array() );
		self::assertSame( 'Build', $tabs[ BuildTab::PANEL ]['label'] );
		self::assertSame( 'cbuild_build', $tabs[ BuildTab::PANEL ]['target'] );
		self::assertSame( array( 'show_if_cbuild_kit' ), $tabs[ BuildTab::PANEL ]['class'] );
		self::assertSame( 'not tabs', apply_filters( 'woocommerce_product_data_tabs', 'not tabs' ), 'what is not a list of tabs is left alone' );
		self::assertNotFalse( has_action( 'woocommerce_product_data_panels' ) );
		self::assertNotFalse( has_action( 'woocommerce_admin_process_product_object' ) );
	}

	public function test_a_product_that_is_not_a_kit_gets_no_fields_so_its_form_grows_by_nothing(): void {
		$this->as( 'shop_manager' );
		$simple = new \WC_Product_Simple();
		$simple->set_name( 'Deck' );
		$simple->save();

		$panel = $this->panel( $simple );
		self::assertStringContainsString( 'id="cbuild_build"', $panel );
		self::assertStringContainsString( 'Save the product as a Builder kit', $panel );
		self::assertStringNotContainsString( 'name="', $panel, 'issue #23: no field of the tab is in another product\'s form' );

		// And fields sent for one are not read.
		$this->send( $simple, self::form() );
		self::assertSame( '', get_post_meta( $simple->get_id(), Options::META_KIT_SLOTS, true ) );
		self::assertSame( array(), self::notices() );
	}

	public function test_a_new_kit_starts_from_the_seven_parts_of_a_complete_and_the_seeded_rules(): void {
		$this->as( 'shop_manager' );
		$kit = new KitProduct();
		$kit->set_name( 'New kit' );
		$kit->save();

		$panel = $this->panel( $kit );
		foreach ( array( 'Deck', 'Trucks', 'Wheels', 'Risers', 'Bearings', 'Hardware', 'Grip tape' ) as $at => $label ) {
			self::assertStringContainsString( 'id="cbuild-kit-slots-' . $at . '-label"', $panel );
			self::assertMatchesRegularExpression( '/name="cbuild_kit\[slots\]\[' . $at . '\]\[label\]"\s+value="' . $label . '"/', $panel );
		}
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[slots\]\[2\]\[units\]"\s+value="4"/', $panel, 'four wheels' );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[rules\]\[r1\]\[axle_fit\]"\s+value="0\.25"/', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[rules\]\[r3\]\[risers\]\[1\]\[riser\]"\s+value="0\.5"/', $panel );
		self::assertSame( 6, preg_match_all( '/<legend id="[^"]+">Preset /', $panel ), 'up to six presets' );
		self::assertMatchesRegularExpression( '/<legend id="[^"]+">New slot<\/legend>/', $panel );
		self::assertStringContainsString( 'R7. Quantities', $panel );
		self::assertStringContainsString( 'R8. Wheelbase and deck length', $panel );
		self::assertStringNotContainsString( 'aria-invalid', $panel );

		// Sent back as drawn, the kit is one the domain and the schemas take.
		$sent = self::form();
		foreach ( $sent['slots'] as $at => $slot ) {
			// A slot the kit does not hold yet is made by its kind.
			$sent['slots'][ $at ]['kind'] = $slot['key'];
			$sent['slots'][ $at ]['key']  = '';
		}
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		$stored = self::stored( $kit->get_id() );
		self::assertSame( KitDocuments::slots(), $stored[ Options::META_KIT_SLOTS ] );
		self::assertSame( RuleSettings::DEFAULTS, $stored[ Options::META_KIT_RULES ] );
		foreach ( Options::kitDocumentNames() as $name => $key ) {
			self::assertNull( $this->service( DocumentSchemas::class )->refusal( $name, $stored[ $key ] ), $name );
		}
		Kit::fromDocuments( $stored[ Options::META_KIT_SLOTS ], $stored[ Options::META_KIT_RULES ], $stored[ Options::META_KIT_PRESETS ], $stored[ Options::META_KIT_ASSEMBLY ] );
	}

	public function test_a_new_kits_assembly_starts_from_the_shops_settings_and_a_kit_that_has_its_own_keeps_it(): void {
		$this->as( 'shop_manager' );
		$kit = new KitProduct();
		$kit->set_name( 'New kit' );
		$kit->save();
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[assembly\]\[fee\]"\s+value="0"/', $panel, 'a shop that has set nothing: no fee' );
		self::assertDoesNotMatchRegularExpression( '/name="cbuild_kit\[assembly\]\[default_on\]"[^>]*checked/', $panel );

		update_option( 'woocommerce_price_decimal_sep', ',' );
		update_option(
			Options::SETTINGS,
			array(
				'kit_defaults' => array(
					'assembly' => array(
						'schema_version' => 1,
						'default_on'     => true,
						'fee'            => '15.5',
						'lead_time'      => 'Two working days',
						'returns_notice' => 'An assembled board cannot be returned.',
					),
				),
			)
		);
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[assembly\]\[fee\]"\s+value="15,5"/', $panel, 'with the shop\'s separator' );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[assembly\]\[default_on\]"[^>]*checked/', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[assembly\]\[lead_time\]"\s+value="Two working days"/', $panel );
		self::assertStringContainsString( '>An assembled board cannot be returned.</textarea>', $panel );

		// A kit with assembly of its own shows its own, whatever the shop's settings say.
		$own = KitFactory::kit();
		self::assertNotSame( '15.5', KitDocuments::assembly()['fee'] );
		$panel = $this->panel( $own );
		self::assertDoesNotMatchRegularExpression( '/name="cbuild_kit\[assembly\]\[fee\]"\s+value="15,5"/', $panel );
		self::assertStringNotContainsString( 'Two working days', $panel );
		delete_option( Options::SETTINGS );
	}

	public function test_a_shop_manager_saves_each_section_through_the_kits_own_crud(): void {
		$this->as( 'shop_manager' );
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$kit  = KitFactory::kit();
		$sent = self::form(
			array(
				'assembly' => array(
					'fee'            => '15,50',
					'lead_time'      => 'Ready in 2 days',
					'returns_notice' => "Built boards\ncannot be returned.",
				),
			)
		);
		$sent['slots'][1]['label']             = 'Trucks \\ "pair" <b>bold</b>';
		$sent['slots'][1]['help']              = "Two lines\nof help";
		$sent['rules']['r1']['axle_fit']       = '3/8';
		$sent['rules']['r1']['axle_warn']      = '0,75';
		$sent['rules']['r6']['check_length']   = '';
		$sent['rules']['r2']                   = array();
		$this->send( $kit, $sent );

		self::assertSame( array(), self::notices() );
		$stored = self::stored( $kit->get_id() );
		self::assertSame( 'Trucks \\ "pair" bold', $stored[ Options::META_KIT_SLOTS ]['slots'][1]['label'], 'plain text: a quote and a backslash are kept, markup is not' );
		self::assertSame( "Two lines\nof help", $stored[ Options::META_KIT_SLOTS ]['slots'][1]['help'] );
		self::assertSame( '0.375', $stored[ Options::META_KIT_RULES ]['r1']['axle_fit'], 'a fraction is a measure' );
		self::assertSame( '0.75', $stored[ Options::META_KIT_RULES ]['r1']['axle_warn'], 'and so is the shop\'s decimal comma' );
		self::assertFalse( $stored[ Options::META_KIT_RULES ]['r2']['enabled'], 'a box left empty is off' );
		self::assertFalse( $stored[ Options::META_KIT_RULES ]['r6']['check_length'] );
		self::assertSame(
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '15.50',
				'lead_time'      => 'Ready in 2 days',
				'returns_notice' => "Built boards\ncannot be returned.",
			),
			$stored[ Options::META_KIT_ASSEMBLY ]
		);
		self::assertSame( '15.50', wc_get_product( $kit->get_id() )->get_price(), 'the fee is the kit\'s price' );

		// Drawn again, each field shows what is stored, escaped, with the shop's separator.
		$panel = $this->panel( $kit );
		self::assertStringContainsString( 'value="Trucks \\ &quot;pair&quot; bold"', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[rules\]\[r1\]\[axle_fit\]"\s+value="0,375"/', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[assembly\]\[fee\]"\s+value="15,5"/', $panel );
		self::assertStringContainsString( "Built boards\ncannot be returned.</textarea>", $panel );
		self::assertStringNotContainsString( 'name="cbuild_kit[slots][1][kind]"', $panel, 'a saved slot keeps its key' );
		self::assertStringContainsString( 'name="cbuild_kit[slots][7][kind]"', $panel, 'the new slot\'s row asks its kind' );
	}

	public function test_only_who_may_manage_the_shop_saves_a_kit(): void {
		$kit    = KitFactory::kit();
		$before = self::stored( $kit->get_id() );
		$sent   = self::form( array( 'assembly' => array( 'fee' => '99' ) ) );

		// May edit products, and may not manage the shop.
		$user = new \WP_User( $this->as( 'author' ) );
		$user->add_cap( 'edit_products' );
		$user->add_cap( 'edit_others_products' );
		$this->send( $kit, $sent );
		self::assertSame( $before, self::stored( $kit->get_id() ) );

		wp_set_current_user( 0 );
		$this->send( $kit, $sent );
		self::assertSame( $before, self::stored( $kit->get_id() ) );

		$this->as( 'shop_manager' );
		// What is not a product, and a tab that was not sent, change nothing.
		$this->hooks()->save( 'not a product' );
		unset( $_POST[ BuildTab::FIELD ] );
		$this->hooks()->save( $kit );
		$kit->save();
		self::assertSame( $before, self::stored( $kit->get_id() ) );

		$this->send( $kit, $sent );
		self::assertSame( '99', self::stored( $kit->get_id() )[ Options::META_KIT_ASSEMBLY ]['fee'] );
	}

	public function test_a_section_that_fails_is_not_saved_the_rest_is_and_each_problem_links_to_its_field(): void {
		$this->as( 'shop_manager' );
		$kit    = KitFactory::kit();
		$before = self::stored( $kit->get_id() );
		$sent   = self::form( array( 'assembly' => array( 'fee' => '12' ) ) );
		$sent['slots'][2]['units']           = 'four';
		$sent['slots'][2]['label']           = 'Wheels <i>typed</i>';
		$sent['rules']['r3']['limits']['low'] = 'tall';
		$this->send( $kit, $sent );

		$stored = self::stored( $kit->get_id() );
		self::assertSame( $before[ Options::META_KIT_SLOTS ], $stored[ Options::META_KIT_SLOTS ], 'the slots are as they were' );
		self::assertSame( $before[ Options::META_KIT_RULES ], $stored[ Options::META_KIT_RULES ], 'and the rules' );
		self::assertSame( '12', $stored[ Options::META_KIT_ASSEMBLY ]['fee'], 'the rest of the kit saved' );

		self::assertSame(
			array(
				'<a href="#cbuild-kit-slots">The kit&#039;s slots were not saved. The Build tab shows them as you typed them, with what to put right.</a>',
				'<a href="#cbuild-kit-rules">The kit&#039;s rules were not saved. The Build tab shows them as you typed them, with what to put right.</a>',
				'<a href="#cbuild-kit-slots-2-units">The Wheels typed slot, Units per build: this is not a value the field takes.</a>',
				'<a href="#cbuild-kit-rules-r3-limits-low">R3, Largest wheel on a low truck: type a number, such as 0.25.</a>',
			),
			self::notices()
		);

		// The tab shows the refused sections as typed, each wrong field marked and tied to its sentence.
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[slots\]\[2\]\[units\]"\s+value="four"/', $panel );
		self::assertMatchesRegularExpression( '/id="cbuild-kit-slots-2-units"[^>]*aria-describedby="cbuild-kit-slots-2-units-note cbuild-kit-error-0"\s+aria-invalid="true"/', $panel );
		self::assertStringContainsString( '<li id="cbuild-kit-error-0"><a href="#cbuild-kit-slots-2-units">The Wheels typed slot, Units per build: this is not a value the field takes.</a></li>', $panel );
		self::assertMatchesRegularExpression( '/id="cbuild-kit-rules-r3-limits-low"[^>]*aria-describedby="cbuild-kit-rules-r3-limits-low-note cbuild-kit-error-1"\s+aria-invalid="true"/', $panel );
		self::assertSame( 2, substr_count( $panel, 'aria-invalid="true"' ), 'only the fields that were wrong' );
		self::assertSame( 2, substr_count( $panel, 'Not saved. This is what you typed' ) );

		// Once shown it is let go: the tab is the stored kit again.
		$panel = $this->panel( $kit );
		self::assertStringNotContainsString( 'aria-invalid', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[slots\]\[2\]\[units\]"\s+value="4"/', $panel );
	}

	public function test_what_one_person_typed_is_shown_to_that_person_alone(): void {
		$this->as( 'shop_manager' );
		$kit                       = KitFactory::kit();
		$sent                      = self::form();
		$sent['slots'][0]['units'] = '0';
		$this->send( $kit, $sent );
		self::assertStringContainsString( 'The Deck slot, Units per build: this is more than the kit takes, or less.', self::notices()[1] );

		$typist = get_current_user_id();
		$this->as( 'shop_manager' );
		self::assertStringNotContainsString( 'aria-invalid', $this->panel( $kit ) );
		wp_set_current_user( $typist );
		self::assertStringContainsString( 'aria-invalid', $this->panel( $kit ) );

		// A save that passes lets go of what was kept.
		$sent['slots'][0]['units'] = 'x';
		$this->send( $kit, $sent );
		$this->send( $kit, self::form() );
		self::assertStringNotContainsString( 'aria-invalid', $this->panel( $kit ) );
	}

	public function test_a_preset_is_saved_only_when_its_slots_take_its_parts(): void {
		$this->as( 'shop_manager' );
		$deck = new \WC_Product_Simple();
		$deck->set_name( 'Plain deck' );
		$deck->set_regular_price( '60' );
		$deck->set_status( 'publish' );
		$deck->save();
		$kit = KitFactory::kit();

		// One slot, which names no product.
		$sent = self::form(
			array(
				'slots'   => array(
					array(
						'key'      => 'deck',
						'label'    => 'Deck',
						'required' => '1',
						'units'    => '1',
					),
				),
				'presets' => array(
					array(
						'name'  => 'Street',
						'picks' => array( 'deck' => (string) $deck->get_id() ),
					),
					array( 'name' => 'Empty' ),
				),
			)
		);
		$this->send( $kit, $sent );
		self::assertSame( array(), self::stored( $kit->get_id() )[ Options::META_KIT_PRESETS ]['presets'], 'no preset is saved' );
		self::assertCount( 1, self::stored( $kit->get_id() )[ Options::META_KIT_SLOTS ]['slots'], 'the slots are' );
		self::assertSame(
			array(
				'<a href="#cbuild-kit-presets">The kit&#039;s presets were not saved. The Build tab shows them as you typed them, with what to put right.</a>',
				'<a href="#cbuild-kit-presets-1-picks-deck">Preset 2, Deck: choose a part. A build must have one.</a>',
			),
			self::notices()
		);
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/id="cbuild-kit-presets-1-picks-deck"[^>]*aria-invalid="true"/', $panel );
		self::assertStringContainsString( '<option value="' . $deck->get_id() . '" selected="selected">Plain deck (#' . $deck->get_id() . ')</option>', $panel, 'the part typed is still chosen' );

		// The second preset dropped, the first names a part its slot does not take.
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		unset( $sent['presets'][1] );
		$this->send( $kit, $sent );
		self::assertSame( '<a href="#cbuild-kit-presets-0-picks-deck">Preset 1, Deck: Plain deck (#' . $deck->get_id() . ') is not a part the Deck slot takes, or it cannot be bought now.</a>', self::notices()[1] );

		// Named in the slot it is one, but the rules cannot check a deck that gives no width.
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		$sent['slots'][0]['products']          = array( (string) $deck->get_id() );
		$this->send( $kit, $sent );
		self::assertSame( '<a href="#cbuild-kit-presets-0-picks-deck">Preset 1, Deck: Plain deck (#' . $deck->get_id() . ') lacks a spec that rule R6 reads, so the builder does not offer it. Fill it in on its Part specs.</a>', self::notices()[1] );
		self::assertSame( array(), self::stored( $kit->get_id() )[ Options::META_KIT_PRESETS ]['presets'] );

		// With its specs, the preset saves.
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		$deck->update_meta_data(
			Options::META_SPECS,
			array(
				'deck_width'  => '8.25',
				'deck_length' => '32',
			)
		);
		$deck->save();
		$this->service( SpecIndexer::class )->index( $deck->get_id() );
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		$stored = self::stored( $kit->get_id() );
		self::assertSame(
			array(
				array(
					'name'  => 'Street',
					'picks' => array( 'deck' => $deck->get_id() ),
				),
			),
			$stored[ Options::META_KIT_PRESETS ]['presets']
		);
		self::assertSame( array( 'products' => array( $deck->get_id() ) ), $stored[ Options::META_KIT_SLOTS ]['slots'][0]['sources'] );
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/id="cbuild-kit-slots-0-sources-products"[^>]*multiple="multiple"[^>]*>\s*<option value="' . $deck->get_id() . '" selected="selected">/', $panel );
	}

	// The auditor's should-fix 9, milestone 4: every row's buttons were "Move up" and "Move down", and every fold
	// "Settings", so a list of the tab's controls named none of them apart.
	public function test_each_rows_buttons_and_fold_are_named_with_the_row(): void {
		$this->as( 'shop_manager' );
		$panel = $this->panel( KitFactory::kit() );
		// The buttons carry the row's name as hidden text, not by aria-labelledby: Firefox's page crashed under axe when a
		// button named from the legend had been moved (D-120).
		foreach ( array( 'up' => 'Move up', 'down' => 'Move down' ) as $control => $name ) {
			self::assertSame( 1, preg_match_all( '/id="cbuild-kit-slots-1-' . $control . '">' . $name . ' <span class="screen-reader-text">Slot: Trucks<\/span><\/button>/', $panel ), $control );
		}
		self::assertSame( 1, preg_match_all( '/id="cbuild-kit-slots-1-fold"\s+aria-labelledby="cbuild-kit-slots-1-fold cbuild-kit-slots-1-legend"/', $panel ), 'fold' );
		self::assertSame( 1, preg_match_all( '/<legend id="cbuild-kit-slots-1-legend">Slot: Trucks<\/legend>/', $panel ) );
		self::assertSame( 0, preg_match_all( '/<button[^>]*data-cbuild-move[^>]*>[^<]*<\/button>/', $panel ), 'no button is left with the bare name' );
		self::assertSame( 0, preg_match_all( '/<button[^>]*data-cbuild-move[^>]*aria-labelledby/', $panel ), 'and none is named from the legend' );
		self::assertSame( 0, preg_match_all( '/<summary(?![^>]*aria-labelledby)[^>]*id="cbuild-kit-(?:slots|presets)-\d+-fold"/', $panel ), 'no fold is left with the bare name' );
	}

	public function test_a_slot_is_removed_when_ticked_and_the_rows_keep_the_order_they_are_sent_in(): void {
		$this->as( 'shop_manager' );
		$kit  = KitFactory::kit();
		$sent = self::form();
		// Grip moved up to second, as the browser sends a moved row: its own index, in its new place.
		$sent['slots']              = array(
			0 => $sent['slots'][0],
			6 => $sent['slots'][6],
			1 => $sent['slots'][1],
			2 => $sent['slots'][2],
			3 => $sent['slots'][3] + array( 'remove' => '1' ),
			4 => $sent['slots'][4],
			5 => $sent['slots'][5],
			7 => array(
				'key'   => '',
				'kind'  => '',
				'label' => 'Shop sticker',
				'units' => '1',
			),
			8 => array(
				'key'   => '',
				'kind'  => '',
				'label' => '',
				'units' => '1',
			),
		);
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertSame(
			array( 'deck', 'grip', 'trucks', 'wheels', 'bearings', 'hardware', 'shop_sticker' ),
			array_column( self::stored( $kit->get_id() )[ Options::META_KIT_SLOTS ]['slots'], 'key' ),
			'risers removed, a new slot keyed from its label, and the empty row no slot'
		);
	}

	public function test_saving_says_which_parts_of_a_slot_have_no_set_size(): void {
		$this->as( 'shop_manager' );
		$wheels = new \WC_Product_Simple();
		$wheels->set_name( 'Wheels' );
		$wheels->set_regular_price( '40' );
		$wheels->set_status( 'publish' );
		$wheels->save();
		$kit                          = KitFactory::kit();
		$sent                         = self::form();
		$sent['slots'][2]['products'] = array( (string) $wheels->get_id() );
		$this->send( $kit, $sent );

		self::assertSame(
			array( '<a href="#cbuild-kit-slots-2-units">1 part the Wheels slot takes has no set size, so the builder cannot tell how many a build needs and does not offer it. Fill in Units in a set on its Part specs.</a>' ),
			self::notices()
		);
		self::assertSame( array( $wheels->get_id() ), self::stored( $kit->get_id() )[ Options::META_KIT_SLOTS ]['slots'][2]['sources']['products'], 'a note, not a refusal' );
	}
}
