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
		$tab = ( new BuildTab( $this->service( KitEditor::class ), $this->service( ShopSettings::class ) ) )->withPlace( $this->service( \CreativeBranding\SkateboardBuilder\Front\BuilderPlace::class ) );
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
			'picture'  => array(
				'layers'    => array(
					'deck'   => '1',
					'grip'   => '2',
					'trucks' => '3',
					'wheels' => '4',
				),
				'wheelbase' => '',
			),
			'layout'   => array(
				'stage_side'   => 'left',
				'board_size'   => 'large',
				'search'       => 'creative_search',
				// Ticked, as the tab shows it on a kit saved before 1.1.0 (#104).
				'show_presets' => '1',
			),
			// The settings from 1.2.0, in a document of their own (D-354).
			'settings' => array(
				// Ticked, as the tab shows it on a kit saved before 1.2.0 (#115).
				'start_button' => '1',
				// The kit's own page, as the tab shows it on a kit saved before 1.2.0 (#113).
				'builder_page' => '',
			),
		);
	}

	/**
	 * Send the tab as the browser does and let WooCommerce's hook save it, then write the product.
	 *
	 * @param \WC_Product          $product The product being saved.
	 * @param array<string, mixed> $sent    The tab's fields.
	 * @param bool                 $whole   Whether the panel's last field arrived.
	 */
	private function send( \WC_Product $product, array $sent, bool $whole = true ): void {
		$_POST[ BuildTab::FIELD ] = wp_slash( $whole ? $sent + array( BuildTab::END => '1' ) : $sent );
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
		$this->as( 'shop_manager' );
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
		Kit::fromDocuments( $stored[ Options::META_KIT_SLOTS ], $stored[ Options::META_KIT_RULES ], $stored[ Options::META_KIT_PRESETS ], $stored[ Options::META_KIT_ASSEMBLY ], $stored[ Options::META_KIT_PICTURE ], $stored[ Options::META_KIT_LAYOUT ], $stored[ Options::META_KIT_SETTINGS ] );
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

	/**
	 * Issue #62 (Cowork, on the demo): the test kit's presets name trucks the shop hides, so every save said the
	 * presets were not saved, though nothing in them had changed and nothing was lost. Presets sent as they are
	 * stored are kept without a failure, and the tab says which preset holds which part the slot doesn't offer now.
	 * A preset that was edited is still held to the slots.
	 */
	// D-252: a preset's Photo, by its media library ID, with the modal's button where the page has it.
	public function test_a_presets_photo_is_a_field_with_a_choose_button_and_its_preview(): void {
		$this->as( 'shop_manager' );
		$photo = self::factory()->attachment->create_upload_object( DIR_TESTDATA . '/images/canola.jpg' );
		$kit   = KitFactory::kit(
			'Complete',
			array(
				Options::META_KIT_PRESETS => array(
					'schema_version' => 1,
					'presets'        => array(
						array(
							'name'  => 'Street',
							'picks' => array( 'deck' => 1 ),
							'image' => $photo,
						),
					),
				),
			)
		);
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/<input\s[^>]*id="cbuild-kit-presets-0-image"[^>]*name="cbuild_kit\[presets\]\[0\]\[image\]"[^>]*value="' . $photo . '"/s', $panel );
		self::assertMatchesRegularExpression( '/<button type="button" class="button cbuild-layer__choose" hidden data-cbuild-layer="cbuild-kit-presets-0-image"/', $panel, 'the button the script shows' );
		self::assertMatchesRegularExpression( '/id="cbuild-kit-presets-0-image-preview"><img alt="" width="60" height="60" src="[^"]+canola[^"]*"/', $panel, 'and what it is' );
		self::assertMatchesRegularExpression( '/id="cbuild-kit-presets-1-image-preview"><img alt="" width="60" height="60" src="" hidden/', $panel, 'an empty row has no preview' );
		// Each named for its preset, so six buttons are not six of one name (the auditor's M10 review, 14).
		self::assertStringContainsString( 'aria-label="Choose a photo for Preset 2"', $panel );
	}

	public function test_unchanged_presets_naming_a_part_the_shop_hides_are_kept_and_named_not_refused(): void {
		$this->as( 'shop_manager' );
		$deck = new \WC_Product_Simple();
		$deck->set_name( 'Plain deck' );
		$deck->set_regular_price( '60' );
		$deck->set_status( 'publish' );
		$deck->update_meta_data(
			Options::META_SPECS,
			array(
				'deck_width'  => '8.25',
				'deck_length' => '32',
			)
		);
		$deck->save();
		$this->service( SpecIndexer::class )->index( $deck->get_id() );
		$kit  = KitFactory::kit();
		$sent = self::form(
			array(
				'slots'   => array(
					array(
						'key'      => 'deck',
						'label'    => 'Deck',
						'required' => '1',
						'units'    => '1',
						'products' => array( (string) $deck->get_id() ),
					),
				),
				'presets' => array(
					array(
						'name'  => 'Street',
						'picks' => array( 'deck' => (string) $deck->get_id() ),
					),
				),
			)
		);
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices(), 'saved as a preset that fits' );
		$saved = self::stored( $kit->get_id() )[ Options::META_KIT_PRESETS ];

		// The shop hides the deck, and saves the kit again with nothing changed.
		$deck->set_catalog_visibility( 'hidden' );
		$deck->save();
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		$this->send( $kit, $sent );
		self::assertSame(
			array( '<a href="#cbuild-kit-presets-0-picks-deck">Street holds Plain deck (#' . $deck->get_id() . '), which the Deck slot doesn&#039;t offer now. The preset is kept as it is, and its tile says so until the part is offered again.</a>' ),
			self::notices(),
			'that alone: no "not saved"'
		);
		self::assertSame( $saved, self::stored( $kit->get_id() )[ Options::META_KIT_PRESETS ], 'the presets are as they were' );

		// Edited, it is held to the slots as before.
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		$sent['presets'][0]['name']            = 'Street, edited';
		$this->send( $kit, $sent );
		self::assertStringContainsString( 'The kit&#039;s presets were not saved.', implode( ' ', self::notices() ) );
		self::assertSame( $saved, self::stored( $kit->get_id() )[ Options::META_KIT_PRESETS ] );
	}

	// The auditor's should-fix 9, milestone 4: every row's buttons were "Move up" and "Move down", and every fold
	// "Settings", so a list of the tab's controls named none of them apart.
	// Claude in Cowork's review of milestone 4, should-fix 1: PHP drops the fields past max_input_vars with no error,
	// and a field that is not sent read as a choice.
	public function test_a_post_cut_short_saves_nothing_says_why_and_keeps_what_arrived(): void {
		$this->as( 'shop_manager' );
		$kit    = KitFactory::kit();
		$before = self::stored( $kit->get_id() );
		self::assertSame( 1, preg_match_all( '/<input type="hidden" name="cbuild_kit\[end\]" value="1" \/>\s*<\/div>\s*$/', $this->panel( $kit ) ), 'the last field of the panel' );

		// Cut after the second slot: five slots, every rule, the presets and assembly never arrived.
		$sent          = self::form();
		$sent['slots'] = array_slice( $sent['slots'], 0, 2 );
		unset( $sent['rules'], $sent['presets'], $sent['assembly'] );
		$this->send( $kit, $sent, false );

		self::assertSame( $before, self::stored( $kit->get_id() ), 'nothing is saved' );
		$notices = implode( ' ', \WC_Admin_Meta_Boxes::$meta_box_errors );
		self::assertStringContainsString( 'max_input_vars', $notices );
		self::assertStringContainsString( 'href="#cbuild_build"', $notices );
		$panel = $this->panel( $kit );
		self::assertStringContainsString( 'Not saved. This is what you typed', $panel );
		self::assertStringContainsString( 'max_input_vars', $panel );

		// Whole, the same form saves.
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		$whole                    = self::form();
		$whole['assembly']['fee'] = '31';
		$this->send( $kit, $whole );
		self::assertSame( '31', self::stored( $kit->get_id() )[ Options::META_KIT_ASSEMBLY ]['fee'] );
	}

	// Minor 2 of the same review: the tab was drawn for anyone who may edit the product and saved for a manager alone.
	public function test_the_tab_is_drawn_only_for_who_can_save_it(): void {
		$kit  = KitFactory::kit();
		$user = new \WP_User( $this->as( 'author' ) );
		$user->add_cap( 'edit_products' );
		$user->add_cap( 'edit_others_products' );
		self::assertArrayNotHasKey( BuildTab::PANEL, $this->hooks()->tab( array() ) );
		self::assertSame( '', $this->panel( $kit ) );
		$this->as( 'shop_manager' );
		self::assertArrayHasKey( BuildTab::PANEL, $this->hooks()->tab( array() ) );
		self::assertStringContainsString( 'id="cbuild_build"', $this->panel( $kit ) );
	}

	public function test_each_rows_buttons_and_fold_are_named_with_the_row(): void {
		$this->as( 'shop_manager' );
		// A slot has its attribute fold once the shop has an attribute. The row is rolled back after the test; the
		// taxonomy registered in this process is not, and WooCommerce refuses a slug whose taxonomy is registered.
		if ( 0 === wc_attribute_taxonomy_id_by_name( 'pa_cbuildfold' ) ) {
			unregister_taxonomy( 'pa_cbuildfold' );
			self::assertIsInt( wc_create_attribute( array( 'name' => 'Fold', 'slug' => 'cbuildfold' ) ) );
		}
		$panel = $this->panel( KitFactory::kit() );
		// The buttons carry the row's name as hidden text, not by aria-labelledby: Firefox's page crashed under axe when a
		// button named from the legend had been moved (D-120).
		foreach ( array( 'up' => 'Move up', 'down' => 'Move down' ) as $control => $name ) {
			self::assertSame( 1, preg_match_all( '/id="cbuild-kit-slots-1-' . $control . '">' . $name . ' <span class="screen-reader-text">Slot: Trucks<\/span><\/button>/', $panel ), $control );
		}
		self::assertSame( 1, preg_match_all( '/id="cbuild-kit-slots-1-fold"\s+aria-labelledby="cbuild-kit-slots-1-fold cbuild-kit-slots-1-legend"/', $panel ), 'fold' );
		// Minor 3 of Claude in Cowork's review: the fold of a slot's attribute values had the same name in every row.
		self::assertSame( 1, preg_match_all( '/id="cbuild-kit-slots-1-more"\s+aria-labelledby="cbuild-kit-slots-1-more cbuild-kit-slots-1-legend"/', $panel ), 'more' );
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
			array( '<a href="#cbuild-kit-slots-2-units">1 part the Wheels slot takes has no set size, so the builder cannot tell how many a build needs and does not offer it. Fill in Number in a set on its Part specs.</a>' ),
			self::notices()
		);
		self::assertSame( array( $wheels->get_id() ), self::stored( $kit->get_id() )[ Options::META_KIT_SLOTS ]['slots'][2]['sources']['products'], 'a note, not a refusal' );
	}

	/**
	 * The layer a slot's select has chosen in the tab, or null where the tab has no such select.
	 *
	 * @param string $panel The tab.
	 * @param string $slot  The slot's key.
	 */
	private static function layer( string $panel, string $slot ): ?string {
		if ( 1 !== preg_match( '#<select[^>]*name="cbuild_kit\[picture\]\[layers\]\[' . $slot . '\]".*?</select>#s', $panel, $select ) ) {
			return null;
		}
		return 1 === preg_match( '#<option value="(\d+)"\s+selected=\'selected\'>#', $select[0], $chosen ) ? $chosen[1] : '';
	}

	/**
	 * Spec, Admin, Milestone 7: "On the kit's Build tab, a Picture section sets the slots' layer order and any anchor
	 * the kit needs to move." D-192.
	 */
	public function test_the_picture_section_sets_the_layer_order_and_where_the_trucks_sit_on_a_deck_with_no_wheelbase(): void {
		$this->as( 'shop_manager' );
		$kit   = KitFactory::kit();
		$panel = $this->panel( $kit );
		self::assertStringContainsString( 'id="cbuild-kit-picture"', $panel );
		// Cowork's M7 review, M5: no group is announced without a name. One with no legend is a plain box.
		self::assertMatchesRegularExpression( '#<div id="cbuild-kit-picture-fields" class="cbuild-kit-group">#', $panel );
		preg_match_all( '#<fieldset[^>]*>\s*(<\w+)#', $panel, $opened );
		self::assertNotSame( array(), $opened[1] );
		self::assertSame( array( '<legend' ), array_values( array_unique( $opened[1] ) ), 'each fieldset opens with its legend' );
		self::assertSame(
			array( '1', '2', '3', '4', null, null, null ),
			array_map( static fn( string $slot ): ?string => self::layer( $panel, $slot ), array( 'deck', 'grip', 'trucks', 'wheels', 'risers', 'bearings', 'hardware' ) ),
			'a kit saved before 0.7.0: the usual order, and a layer only for the slots the picture draws'
		);
		self::assertStringContainsString( '<option value="1"  selected=\'selected\'>1, the bottom</option>', $panel );
		self::assertMatchesRegularExpression( '#<label for="cbuild-kit-picture-wheelbase">Wheelbase where a deck gives none \(in\)</label>\s*<input[^>]*name="cbuild_kit\[picture\]\[wheelbase\]"\s+value=""#s', $panel );

		$sent                    = self::form();
		$sent['picture']['layers'] = array(
			'deck'   => '2',
			'grip'   => '1',
			'trucks' => '3',
			'wheels' => '3',
		);
		$sent['picture']['wheelbase'] = '14 1/4';
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertSame(
			array(
				'schema_version' => 1,
				'order'          => array( 'grip', 'deck', 'trucks', 'wheels' ),
				'wheelbase'      => '14.25',
			),
			self::stored( $kit->get_id() )[ Options::META_KIT_PICTURE ],
			'equal layers keep the order sent; a fraction is written as a decimal'
		);
		$panel = $this->panel( $kit );
		self::assertSame( array( '2', '1' ), array( self::layer( $panel, 'deck' ), self::layer( $panel, 'grip' ) ) );
		self::assertLessThan( strpos( $panel, '[layers][deck]' ), strpos( $panel, '[layers][grip]' ), 'drawn in the order in force' );
		self::assertMatchesRegularExpression( '#name="cbuild_kit\[picture\]\[wheelbase\]"\s+value="14.25"#', $panel );
		$read = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->kit( $kit->get_id() );
		self::assertSame( array( 'grip', 'deck', 'trucks', 'wheels' ), $read?->picture->order, 'the kit the builder reads' );
		self::assertSame( 142500, $read?->picture->wheelbase?->tenThousandths );
	}

	// Spec (configurator): Settings (D-255): the Build tab's Layout section, kept in `_cbuild_layout`.
	public function test_the_layout_section_sets_the_stage_side_the_board_size_and_the_search(): void {
		$this->as( 'shop_manager' );
		$kit   = KitFactory::kit();
		$panel = $this->panel( $kit );
		self::assertStringContainsString( 'id="cbuild-kit-layout"', $panel );
		foreach ( array( 'stage_side' => 'left', 'board_size' => 'large', 'search' => 'creative_search', 'motion' => 'playful' ) as $key => $default ) {
			self::assertMatchesRegularExpression( '#name="cbuild_kit\[layout\]\[' . $key . '\]".*?<option value="' . $default . '"\s+selected#s', $panel, $key . ': a kit saved before 1.0.0 shows the default' );
		}

		$sent           = self::form();
		$sent['layout'] = array(
			'stage_side'   => 'right',
			'board_size'   => 'balanced',
			'search'       => 'own',
			'motion'       => 'subtle',
			'show_presets' => '1',
		);
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertSame( array( 'schema_version' => 1, 'stage_side' => 'right', 'board_size' => 'balanced', 'search' => 'own', 'motion' => 'subtle', 'show_presets' => true ), self::stored( $kit->get_id() )[ Options::META_KIT_LAYOUT ] );
		$read = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->kit( $kit->get_id() );
		self::assertSame( array( 'right', 'balanced', 'own', 'subtle' ), array( $read?->layout->stageSide, $read?->layout->boardSize, $read?->layout->search, $read?->layout->motion ), 'the kit the builder reads' );

		$sent['layout']['stage_side'] = 'top';
		$this->send( $kit, $sent );
		self::assertStringContainsString( 'The kit&#039;s layout was not saved.', implode( '', self::notices() ) );
		self::assertSame( 'right', self::stored( $kit->get_id() )[ Options::META_KIT_LAYOUT ]['stage_side'], 'the layout in force stays' );
	}

	// #104 (R1): Show ready builds, in the Presets section and kept in `_cbuild_layout`. Ticked on a kit saved before
	// 1.1.0; unticked, the kit's ready builds are hidden and kept as they are. All from wp-admin, with no WP-CLI.
	public function test_show_ready_builds_is_ticked_for_a_kit_saved_before_and_unticking_keeps_the_presets(): void {
		$this->as( 'shop_manager' );
		$kit   = KitFactory::kit();
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[layout\]\[show_presets\]"[^>]*checked/', $panel, 'a kit saved before 1.1.0 shows its ready builds' );
		self::assertStringContainsString( 'Show ready builds', $panel );
		self::assertMatchesRegularExpression( '#id="cbuild-kit-presets".*?name="cbuild_kit\[layout\]\[show_presets\]"#s', $panel, 'in the Presets section' );

		$presets = self::stored( $kit->get_id() )[ Options::META_KIT_PRESETS ];
		$sent    = self::form();
		unset( $sent['layout']['show_presets'], $sent['presets'] );
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertFalse( self::stored( $kit->get_id() )[ Options::META_KIT_LAYOUT ]['show_presets'] );
		self::assertSame( $presets, self::stored( $kit->get_id() )[ Options::META_KIT_PRESETS ], 'the presets are kept as they are' );
		$read = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->kit( $kit->get_id() );
		self::assertFalse( $read?->layout->showPresets, 'the kit the builder reads' );
		self::assertDoesNotMatchRegularExpression( '/name="cbuild_kit\[layout\]\[show_presets\]"[^>]*checked/', $this->panel( $kit ) );

		$sent['layout']['show_presets'] = '1';
		$this->send( $kit, $sent );
		self::assertTrue( self::stored( $kit->get_id() )[ Options::META_KIT_LAYOUT ]['show_presets'] );
	}

	// #115: Show on its parts' pages, in the Layout section and kept in `_cbuild_settings` (D-354). Ticked on a kit
	// saved before 1.2.0; unticked, the kit places no "Build a complete with this deck". Saved by who may manage the
	// shop alone, through WooCommerce's product save, from wp-admin with no WP-CLI.
	public function test_show_on_its_parts_pages_is_ticked_for_a_kit_saved_before_and_saved_by_who_may_manage_the_shop(): void {
		$this->as( 'shop_manager' );
		$kit   = KitFactory::kit();
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[settings\]\[start_button\]"[^>]*checked/', $panel, 'a kit saved before 1.2.0 places the button' );
		self::assertMatchesRegularExpression( '#id="cbuild-kit-layout".*?<input\s+type="checkbox"[^>]*id="cbuild-kit-settings-start_button"[^>]*name="cbuild_kit\[settings\]\[start_button\]"#s', $panel, 'in the Layout section' );
		self::assertMatchesRegularExpression( '#<label for="cbuild-kit-settings-start_button">Show &quot;Build a complete with this deck&quot; on its parts&\#039; pages</label>#', $panel, 'labelled with what it shows (M12 audit)' );
		self::assertMatchesRegularExpression( '#id="cbuild-kit-settings-start_button"[^>]*aria-describedby="cbuild-kit-settings-start_button-note"#', $panel, 'and described' );
		self::assertStringContainsString( 'lowest menu order', $panel, 'the note says which kit wins where several offer a part' );
		// One switch, in the Layout section: a second, in Picture, sent `cbuild_kit[picture][start_button]` and did nothing (M12's walkthrough).
		self::assertSame( 1, preg_match_all( '/\[start_button\]"/', $panel ), 'one Show on its parts\' pages' );
		self::assertSame( 1, substr_count( $panel, 'Where shoppers find it' ), 'one Where shoppers find it' );

		$sent = self::form();
		unset( $sent['settings']['start_button'] );
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertFalse( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['start_button'] );
		$read = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->kit( $kit->get_id() );
		self::assertFalse( $read?->settings->startButton, 'the kit the button reads' );
		self::assertTrue( $read?->layout->showPresets, 'and nothing else' );
		self::assertDoesNotMatchRegularExpression( '/name="cbuild_kit\[settings\]\[start_button\]"[^>]*checked/', $this->panel( $kit ) );

		// A customer's post is not saved, whatever it says.
		$this->as( 'customer' );
		$sent['settings']['start_button'] = '1';
		$this->send( $kit, $sent );
		self::assertFalse( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['start_button'], 'not saved by a customer' );

		$this->as( 'shop_manager' );
		$sent['settings']['start_button'] = '<b>yes</b>';
		$this->send( $kit, $sent );
		self::assertTrue( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['start_button'], 'any value ticked is on, stored as true and never as what was sent' );
	}

	/**
	 * A page holding a kit's Builder block.
	 *
	 * @param int                  $kit  The kit.
	 * @param array<string, mixed> $more The page's other fields.
	 */
	private function builderPage( int $kit, array $more = array() ): int {
		return self::factory()->post->create(
			$more + array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Build a board ' . $kit,
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $kit . '} /-->',
			)
		);
	}

	// #113: the Builder page, a select in the Layout section that lists the published pages holding this kit's Builder
	// block, the kit's own page first and chosen on a kit saved before 1.2.0. Only a page it lists is saved; one that
	// has since been taken down is shown as not in use, and kept while the owner leaves it.
	public function test_the_builder_page_lists_the_pages_that_hold_the_kits_block_and_saves_only_one_of_them(): void {
		$this->as( 'shop_manager' );
		$kit     = KitFactory::kit();
		$other   = KitFactory::kit( 'Cruiser complete' );
		$listed  = $this->builderPage( $kit->get_id() );
		$draft   = $this->builderPage( $kit->get_id(), array( 'post_status' => 'draft', 'post_title' => 'Draft board' ) );
		$theirs  = $this->builderPage( $other->get_id(), array( 'post_title' => 'Cruiser board' ) );
		$without = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'About us',
			)
		);
		$select  = static function ( string $panel ): string {
			self::assertSame( 1, preg_match( '#<select[^>]*id="cbuild-kit-settings-builder_page"[^>]*>.*?</select>#s', $panel, $found ), 'the select' );
			return $found[0];
		};
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '#id="cbuild-kit-layout".*?<label for="cbuild-kit-settings-builder_page">Builder page</label>#s', $panel, 'labelled, in the Layout section' );
		self::assertMatchesRegularExpression( '#id="cbuild-kit-settings-builder_page"[^>]*aria-describedby="cbuild-kit-settings-builder_page-note"#s', $panel, 'and described' );
		$shown = $select( $panel );
		self::assertMatchesRegularExpression( '#<option value=""\s+selected=\'selected\'>The kit&\#039;s own page</option>#', $shown, 'a kit saved before 1.2.0: its own page' );
		self::assertStringContainsString( '<option value="' . $listed . '" >Build a board ' . $kit->get_id() . '</option>', $shown );
		foreach ( array( $draft, $theirs, $without ) as $page ) {
			self::assertStringNotContainsString( 'value="' . $page . '"', $shown, 'only published pages holding this kit\'s block' );
		}

		$sent                           = self::form();
		$sent['settings']['builder_page'] = (string) $listed;
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertSame( $listed, self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['builder_page'] );
		$read = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->kit( $kit->get_id() );
		self::assertSame( $listed, $read?->settings->builderPage, 'the kit the builder reads' );
		self::assertMatchesRegularExpression( '#<option value="' . $listed . '"\s+selected=\'selected\'>#', $select( $this->panel( $kit ) ) );

		foreach ( array( $draft, $theirs, $without, $other->get_id() ) as $page ) {
			$sent['settings']['builder_page'] = (string) $page;
			$this->send( $kit, $sent );
			self::assertStringContainsString( 'Builder page: choose one of the pages listed, or the kit&#039;s own page.', implode( '', self::notices() ), 'refused: ' . $page );
			self::assertSame( $listed, self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['builder_page'], 'the page in force stays' );
			\WC_Admin_Meta_Boxes::$meta_box_errors = array();
			$this->panel( $kit );
		}
		$sent['settings']['builder_page'] = '<b>about-us</b>';
		$this->send( $kit, $sent );
		self::assertStringContainsString( 'The kit&#039;s Builder page, the button on its parts&#039; pages and its width guide were not saved.', implode( '', self::notices() ), 'what is no page is refused' );
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		$this->panel( $kit );

		// Taken down after it was chosen: the tab says so, and saving the tab as it is keeps it.
		wp_update_post(
			array(
				'ID'          => $listed,
				'post_status' => 'draft',
			)
		);
		self::assertMatchesRegularExpression( '#<option value="' . $listed . '"\s+selected=\'selected\'>Build a board ' . $kit->get_id() . ' \(not in use now\)</option>#', $select( $this->panel( $kit ) ) );
		$sent['settings']['builder_page'] = (string) $listed;
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertSame( $listed, self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['builder_page'], 'kept, and used again once it is published' );

		$sent['settings']['builder_page'] = '';
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertArrayNotHasKey( 'builder_page', self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ], 'the kit\'s own page' );
		self::assertNull( $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->kit( $kit->get_id() )?->settings->builderPage );
	}

	// #115 and #113: the kit's settings go through WooCommerce's own product save, which checks its nonce and who may
	// edit the product before the tab's hook fires (includes/admin/class-wc-admin-meta-boxes.php:228 and :238).
	public function test_the_layout_is_saved_through_woocommerces_product_save_and_only_with_its_nonce(): void {
		$this->as( 'shop_manager' );
		$kit = KitFactory::kit();
		$this->hooks()->register();
		$page = $this->builderPage( $kit->get_id() );
		$sent = self::form();
		unset( $sent['settings']['start_button'] );
		$sent['settings']['builder_page'] = (string) $page;
		$posted = $_POST;
		$save   = static function ( string $nonce ) use ( $kit, $sent ): void {
			( new \ReflectionProperty( \WC_Admin_Meta_Boxes::class, 'saved_meta_boxes' ) )->setValue( null, false );
			$_POST = array(
				'woocommerce_meta_nonce' => $nonce,
				'post_ID'                => (string) $kit->get_id(),
				BuildTab::FIELD          => wp_slash( $sent + array( BuildTab::END => '1' ) ),
			);
			( new \WC_Admin_Meta_Boxes() )->save_meta_boxes( $kit->get_id(), get_post( $kit->get_id() ) );
		};
		try {
			$save( 'not-the-nonce' );
			self::assertNull( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ], 'nothing saved without the nonce' );
			$save( wp_create_nonce( 'woocommerce_save_data' ) );
			self::assertSame( array(), self::notices() );
			self::assertFalse( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['start_button'] ?? null, 'saved with it' );
			self::assertSame( $page, self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['builder_page'] ?? null, 'the Builder page too' );
			self::assertSame( Options::PRODUCT_TYPE_KIT, \WC_Product_Factory::get_product_type( $kit->get_id() ), 'and still a kit' );
		} finally {
			$_POST = $posted;
			( new \ReflectionProperty( \WC_Admin_Meta_Boxes::class, 'saved_meta_boxes' ) )->setValue( null, false );
		}
	}

	/**
	 * A width guide's row, as the tab draws its field.
	 *
	 * @param string $panel The panel.
	 * @param int    $row   The row's place, from 0.
	 * @param string $field shoe, from or to.
	 */
	private static function guideField( string $panel, int $row, string $field ): ?string {
		$found = array();
		return 1 === preg_match( '#name="cbuild_kit\[settings\]\[width_guide\]\[rows\]\[' . $row . '\]\[' . $field . '\]"\s+value="([^"]*)"#', $panel, $found ) ? $found[1] : null;
	}

	// #114: the Build tab's Width guide section. A kit saved before 1.2.0 shows the guide with the plugin's rows, which
	// the editor starts from; the shop's own rows are kept as plain text in `_cbuild_settings`, "Use the builder's rows"
	// stores none again, and unticking Show takes the guide away. All from wp-admin, with no WP-CLI.
	public function test_the_width_guide_section_keeps_the_shops_rows_restores_the_plugins_and_hides_the_guide(): void {
		$this->as( 'shop_manager' );
		$kit   = KitFactory::kit();
		$panel = $this->panel( $kit );
		self::assertMatchesRegularExpression( '#<div class="options_group" id="cbuild-kit-settings-width_guide"[^>]*>\s*<h3>Width guide</h3>#', $panel );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[settings\]\[width_guide\]\[shown\]"[^>]*checked/', $panel, 'shown, on a kit saved before 1.2.0' );
		self::assertMatchesRegularExpression( '/name="cbuild_kit\[settings\]\[width_guide\]\[plugins\]"[^>]*checked/', $panel, 'with the plugin\'s rows' );
		self::assertSame( array( '6.5 or smaller', '7', '7.75' ), array( self::guideField( $panel, 0, 'shoe' ), self::guideField( $panel, 0, 'from' ), self::guideField( $panel, 0, 'to' ) ), 'the editor starts from them' );
		self::assertSame( array( '9.5 and larger', '8.25', '' ), array( self::guideField( $panel, 3, 'shoe' ), self::guideField( $panel, 3, 'from' ), self::guideField( $panel, 3, 'to' ) ) );
		self::assertSame( '', self::guideField( $panel, 4, 'shoe' ), 'a row to add one, shown' );
		self::assertMatchesRegularExpression( '#<fieldset id="cbuild-kit-settings-width_guide-rows-4" class="cbuild-kit-group cbuild-kit-adds">#', $panel );
		self::assertMatchesRegularExpression( '#<fieldset id="cbuild-kit-settings-width_guide-rows-5" class="cbuild-kit-group cbuild-kit-adds" hidden>#', $panel, 'the rest wait for Add a row' );
		self::assertSame( 12, preg_match_all( '#id="cbuild-kit-settings-width_guide-rows-\d+-shoe"#', $panel ), 'twelve rows at most' );
		self::assertSame( 1, preg_match_all( '#<button type="button" class="button" data-cbuild-add data-cbuild-removed="%s removed. The rows after it moved up." data-cbuild-unticked="&quot;Use the builder&\#039;s rows&quot; is now unticked, so the rows you type are saved." hidden>Add a row</button>#', $panel ), 'shown by the script, with what it says' );
		self::assertSame( 1, preg_match_all( '#<button type="button" class="button" data-cbuild-remove id="cbuild-kit-settings-width_guide-rows-1-remove" hidden>Remove <span class="screen-reader-text">Row 2</span></button>#', $panel ), 'each Remove named with its row' );
		self::assertMatchesRegularExpression( '#<input\s+type="text"\s+class="short"\s+id="cbuild-kit-settings-width_guide-rows-0-shoe"\s+name="[^"]+"\s+value="[^"]*"\s+autocomplete="off"\s+maxlength="40"#', $panel );

		// The shop's own rows, typed with its separator and with markup, which is kept as plain text.
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$sent                          = self::form();
		$sent['settings']['width_guide'] = array(
			'shown' => '1',
			'rows'  => array(
				array( 'shoe' => '<b>EU 38</b> to 40', 'from' => '7,75', 'to' => '8' ),
				array( 'shoe' => '', 'from' => '', 'to' => '' ),
				array( 'shoe' => 'EU 41 and up', 'from' => '8 1/4', 'to' => '' ),
			),
		);
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices() );
		self::assertSame(
			array(
				'shown' => true,
				'rows'  => array(
					array( 'shoe' => 'EU 38 to 40', 'from' => '7.75', 'to' => '8' ),
					array( 'shoe' => 'EU 41 and up', 'from' => '8.25' ),
				),
			),
			self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['width_guide']
		);
		$read = $this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->kit( $kit->get_id() );
		self::assertSame( 'EU 41 and up', $read?->settings->guide->rows[1]->shoe ?? null, 'the kit the builder reads' );
		$panel = $this->panel( $kit );
		self::assertDoesNotMatchRegularExpression( '/name="cbuild_kit\[settings\]\[width_guide\]\[plugins\]"[^>]*checked/', $panel );
		self::assertSame( array( 'EU 41 and up', '8,25', '' ), array( self::guideField( $panel, 1, 'shoe' ), self::guideField( $panel, 1, 'from' ), self::guideField( $panel, 1, 'to' ) ), 'shown with the shop\'s separator' );
		self::assertMatchesRegularExpression( '#<fieldset id="cbuild-kit-settings-width_guide-rows-2" class="cbuild-kit-group cbuild-kit-adds">#', $panel );
		self::assertMatchesRegularExpression( '#<fieldset id="cbuild-kit-settings-width_guide-rows-3" class="cbuild-kit-group cbuild-kit-adds" hidden>#', $panel );

		// Hidden: its rows are kept for when it is shown again.
		unset( $sent['settings']['width_guide']['shown'] );
		$this->send( $kit, $sent );
		self::assertFalse( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['width_guide']['shown'] );
		self::assertCount( 2, self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['width_guide']['rows'] );
		self::assertDoesNotMatchRegularExpression( '/name="cbuild_kit\[settings\]\[width_guide\]\[shown\]"[^>]*checked/', $this->panel( $kit ) );

		// Restored: the plugin's rows, stored as none, whatever the rows hold.
		$sent['settings']['width_guide'] += array(
			'shown'   => '1',
			'plugins' => '1',
		);
		$this->send( $kit, $sent );
		self::assertSame( array( 'shown' => true ), self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['width_guide'] );
	}

	// M12's audit: without the script, rows typed while "Use the builder's rows" is ticked are not saved, and the tab
	// says so with a link to the box. Rows sent as the tab drew them, the builder's own, say nothing.
	public function test_rows_typed_while_the_builders_rows_are_ticked_are_not_saved_and_the_tab_says_so(): void {
		$this->as( 'shop_manager' );
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$kit   = KitFactory::kit();
		$panel = $this->panel( $kit );
		$rows  = array();
		for ( $at = 0; $at < 12; $at++ ) {
			$rows[] = array(
				'shoe' => (string) self::guideField( $panel, $at, 'shoe' ),
				'from' => (string) self::guideField( $panel, $at, 'from' ),
				'to'   => (string) self::guideField( $panel, $at, 'to' ),
			);
		}
		$sent                            = self::form();
		$sent['settings']['width_guide'] = array(
			'shown'   => '1',
			'plugins' => '1',
			'rows'    => $rows,
		);
		$this->send( $kit, $sent );
		self::assertSame( array(), self::notices(), 'the builder\'s rows sent back as drawn say nothing' );

		$sent['settings']['width_guide']['rows'][4] = array( 'shoe' => 'EU 46', 'from' => '9', 'to' => '' );
		$this->send( $kit, $sent );
		self::assertSame(
			array( '<a href="#cbuild-kit-settings-width_guide-plugins">Width guide: the rows you typed were not saved, since &quot;Use the builder&#039;s rows&quot; is ticked. Untick it and save again to keep them.</a>' ),
			self::notices()
		);
		self::assertSame( array( 'shown' => true ), self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['width_guide'], 'the builder\'s rows kept, as the box says' );
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();

		$sent['settings']['width_guide']['rows'][0]['shoe'] = 'EU 38';
		unset( $sent['settings']['width_guide']['rows'][4] );
		$this->send( $kit, $sent );
		self::assertCount( 1, self::notices(), 'a row of the builder\'s changed is said too' );
	}

	// #114: a row that is wrong is named at its field, in the Width guide section, and the settings in force stay.
	public function test_a_width_guide_row_that_is_wrong_is_not_saved_and_the_notice_names_its_field(): void {
		$this->as( 'shop_manager' );
		$kit                           = KitFactory::kit();
		$sent                          = self::form();
		$sent['settings']['width_guide'] = array(
			'shown' => '1',
			'rows'  => array(
				array( 'shoe' => 'EU 38', 'from' => '7.75', 'to' => '' ),
				array( 'shoe' => 'EU 50', 'from' => '8', 'to' => '21' ),
			),
		);
		$sent['assembly']['fee']       = '9';
		$this->send( $kit, $sent );
		self::assertSame(
			array(
				'<a href="#cbuild-kit-settings-width_guide">The kit&#039;s Builder page, the button on its parts&#039; pages and its width guide were not saved. The Build tab shows them as you chose them, with what to put right.</a>',
				'<a href="#cbuild-kit-settings-width_guide-rows-1-to">Width guide, row 2, Deck width to: type a width from 5 to 12 in.</a>',
			),
			self::notices()
		);
		$stored = self::stored( $kit->get_id() );
		self::assertNull( $stored[ Options::META_KIT_SETTINGS ], 'nothing saved in its place' );
		self::assertSame( 'left', $stored[ Options::META_KIT_LAYOUT ]['stage_side'] ?? null, 'the layout saved: it is a document of its own (D-354)' );
		self::assertSame( '9', $stored[ Options::META_KIT_ASSEMBLY ]['fee'], 'the rest of the kit saved' );

		$panel = $this->panel( $kit );
		self::assertSame( '21', self::guideField( $panel, 1, 'to' ), 'shown as typed' );
		self::assertMatchesRegularExpression( '/id="cbuild-kit-settings-width_guide-rows-1-to"[^>]*aria-describedby="cbuild-kit-settings-width_guide-rows-1-to-note cbuild-kit-error-0"\s+aria-invalid="true"/', $panel );
		self::assertMatchesRegularExpression( '#id="cbuild-kit-settings-width_guide".*?Not saved\. This is what you typed.*?<li id="cbuild-kit-error-0"><a href="\#cbuild-kit-settings-width_guide-rows-1-to">#s', $panel, 'listed in the Width guide section' );
		self::assertDoesNotMatchRegularExpression( '#id="cbuild-kit-layout"(?:(?!id="cbuild-kit-settings-width_guide").)*cbuild-kit-error-0#s', $panel, 'and not in the Layout section' );
	}

	// #114, Security: the guide is saved only through WooCommerce's product save, which checks its nonce first, and
	// only for who may manage the shop.
	public function test_the_width_guide_is_saved_through_woocommerces_product_save_with_its_nonce_by_who_may_manage_the_shop(): void {
		$kit  = KitFactory::kit();
		$sent = self::form();
		$sent['settings']['width_guide'] = array(
			'shown' => '1',
			'rows'  => array( array( 'shoe' => 'EU 40', 'from' => '8', 'to' => '' ) ),
		);
		$hooks = $this->hooks();
		$hooks->register();
		$save = function ( ?string $nonce ) use ( $kit, $sent ): void {
			$property = new \ReflectionProperty( \WC_Admin_Meta_Boxes::class, 'saved_meta_boxes' );
			$property->setValue( null, false );
			$_POST = array(
				'post_ID'      => (string) $kit->get_id(),
				'product-type' => Options::PRODUCT_TYPE_KIT,
				BuildTab::FIELD => wp_slash( $sent + array( BuildTab::END => '1' ) ),
			) + ( null === $nonce ? array() : array( 'woocommerce_meta_nonce' => $nonce ) );
			( new \WC_Admin_Meta_Boxes() )->save_meta_boxes( $kit->get_id(), get_post( $kit->get_id() ) );
		};
		try {
			$this->as( 'shop_manager' );
			$save( null );
			self::assertNull( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ], 'no nonce, nothing saved' );
			$save( 'not-a-nonce' );
			self::assertNull( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ], 'a wrong nonce, nothing saved' );

			$user = new \WP_User( $this->as( 'author' ) );
			$user->add_cap( 'edit_products' );
			$user->add_cap( 'edit_others_products' );
			$user->add_cap( 'edit_published_products' );
			$save( wp_create_nonce( 'woocommerce_save_data' ) );
			self::assertNull( self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ], 'who may edit products and not manage the shop saves nothing' );

			$this->as( 'shop_manager' );
			$save( wp_create_nonce( 'woocommerce_save_data' ) );
			self::assertSame( 'EU 40', self::stored( $kit->get_id() )[ Options::META_KIT_SETTINGS ]['width_guide']['rows'][0]['shoe'] ?? null );
		} finally {
			$_POST = array();
		}
	}

	public function test_a_wheelbase_outside_what_a_deck_has_is_not_saved_and_the_notice_says_the_range(): void {
		$this->as( 'shop_manager' );
		$kit                          = KitFactory::kit();
		$sent                         = self::form();
		$sent['picture']['wheelbase'] = '25';
		$sent['assembly']['fee']      = '9';
		$this->send( $kit, $sent );
		self::assertSame(
			array(
				'<a href="#cbuild-kit-picture">The kit&#039;s picture settings were not saved. The Build tab shows them as you typed them, with what to put right.</a>',
				'<a href="#cbuild-kit-picture-wheelbase">Wheelbase where a deck gives none: type a wheelbase from 10 to 20 in, or leave it empty.</a>',
			),
			self::notices()
		);
		$stored = self::stored( $kit->get_id() );
		self::assertNull( $stored[ Options::META_KIT_PICTURE ], 'nothing saved in its place' );
		self::assertSame( '9', $stored[ Options::META_KIT_ASSEMBLY ]['fee'], 'the rest of the kit saved' );
		self::assertMatchesRegularExpression( '#name="cbuild_kit\[picture\]\[wheelbase\]"\s+value="25"#', $this->panel( $kit ), 'shown as typed' );

		$sent['picture']['wheelbase'] = 'long';
		\WC_Admin_Meta_Boxes::$meta_box_errors = array();
		$this->send( $kit, $sent );
		self::assertContains( '<a href="#cbuild-kit-picture-wheelbase">Wheelbase where a deck gives none: type a number, such as 0.25.</a>', self::notices() );
	}


	// The M9 audit's minor 11: a slot's list of products, or of terms, is read together, so it asks as many queries
	// for twelve as for two.
	public function test_a_list_of_products_or_terms_is_named_in_queries_that_do_not_grow_with_it(): void {
		$products = array();
		$terms    = array();
		for ( $n = 0; $n < 12; $n++ ) {
			$product = new \WC_Product_Simple();
			$product->set_name( 'Deck ' . $n );
			$products[] = $product->save();
			$terms[]    = (int) wp_insert_term( 'Category ' . $n, 'product_cat' )['term_id'];
		}
		foreach ( array( 'products' => $products, 'terms' => $terms ) as $method => $ids ) {
			$named   = new \ReflectionMethod( \CreativeBranding\SkateboardBuilder\Admin\BuildTab::class, $method );
			$queries = static function ( array $some ) use ( $named ): int {
				global $wpdb;
				wp_cache_flush();
				$before = $wpdb->num_queries;
				self::assertCount( count( $some ), $named->invoke( null, $some ) );
				return $wpdb->num_queries - $before;
			};
			self::assertSame( $queries( array_slice( $ids, 0, 2 ) ), $queries( $ids ), $method );
		}
	}
}
