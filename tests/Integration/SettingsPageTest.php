<?php
/**
 * WooCommerce > Settings > Builder, on real WooCommerce.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\SettingsHooks;
use CreativeBranding\SkateboardBuilder\Admin\SettingsPage;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Scope;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\SettingsEditor;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\PluginException;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Spec, Admin: the settings tab, who may save it, and what it stores. Audit, Security: a state change needs the
 * capability and the nonce; nothing a person typed is echoed unescaped.
 */
final class SettingsPageTest extends IntegrationTestCase {

	public function set_up(): void {
		parent::set_up();
		// WooCommerce loads its settings classes on its settings screen alone.
		require_once WC_ABSPATH . 'includes/admin/class-wc-admin-settings.php';
		require_once WC_ABSPATH . 'includes/admin/settings/class-wc-settings-page.php';
	}

	public function tear_down(): void {
		unset( $_POST['cbuild_mapping'], $_POST['cbuild_order'], $_POST['cbuild_terms'], $_POST['_wpnonce'], $_REQUEST['_wpnonce'] );
		delete_option( 'woocommerce_price_decimal_sep' );
		delete_option( Options::SETTINGS );
		delete_option( Options::REINDEX );
		parent::tear_down();
	}

	/**
	 * A tab of its own, as WooCommerce would be handed.
	 */
	private function page(): SettingsPage {
		return new SettingsPage( $this->service( ShopSettings::class ), $this->service( SettingsEditor::class ) );
	}

	/**
	 * Send the form as this role, slashed as PHP's superglobals are in WordPress.
	 *
	 * @param string       $role  The user's role.
	 * @param array<mixed> $lines The mapping's lines.
	 * @param array<mixed> $order The order.
	 * @param bool         $nonce Whether the form's nonce is sent.
	 */
	private function send( string $role, array $lines, array $order = array( 'table', 'field', 'attribute' ), bool $nonce = true ): SettingsPage {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
		$_POST['cbuild_mapping'] = wp_slash( $lines );
		$_POST['cbuild_order']   = wp_slash( $order );
		if ( $nonce ) {
			$_REQUEST['_wpnonce'] = wp_create_nonce( 'woocommerce-settings' );
		}
		$page = $this->page();
		$page->save();
		return $page;
	}

	/**
	 * What a field draws.
	 *
	 * @param SettingsPage $page  The tab.
	 * @param string       $field `mappingField` or `orderField`.
	 */
	private static function drawn( SettingsPage $page, string $field ): string {
		ob_start();
		$page->$field();
		return (string) ob_get_clean();
	}

	public function test_the_tab_is_added_to_woocommerces_settings_in_the_admin_alone(): void {
		$admin = Scope::atBoot( true, false, false );
		$front = Scope::atBoot( false, false, false );
		$built = static fn( array $scopes ): array => array_map( 'get_class', ( new \CreativeBranding\SkateboardBuilder\Container() )->hookablesFor( ...$scopes ) );
		self::assertContains( SettingsHooks::class, $built( $admin ) );
		self::assertNotContains( SettingsHooks::class, $built( $front ), 'a storefront request builds nothing of the settings screen' );

		$pages = $this->service( SettingsHooks::class )->add( array( 'theirs' ) );
		self::assertIsArray( $pages );
		self::assertSame( 'theirs', $pages[0] );
		self::assertInstanceOf( SettingsPage::class, $pages[1] );
		self::assertSame( 'cbuild', $pages[1]->get_id() );
		self::assertSame( 'Builder', $pages[1]->get_label() );
		self::assertSame( array( 'theirs' => 'x', 'cbuild' => 'Builder' ), $pages[1]->add_settings_page( array( 'theirs' => 'x' ) ) );
		self::assertSame( 'not a list', $this->service( SettingsHooks::class )->add( 'not a list' ) );
		self::assertSame(
			array( 'title', 'cbuild_mapping', 'cbuild_order', 'sectionend', 'title', 'cbuild_terms', 'sectionend', 'title', 'cbuild_terms', 'sectionend', 'title', 'cbuild_terms', 'sectionend' ),
			array_column( $pages[1]->get_settings_for_section( '' ), 'type' )
		);
	}

	public function test_a_shop_manager_saves_the_mapping_and_the_order_and_a_reindex_is_asked_for(): void {
		$this->send(
			'shop_manager',
			array(
				array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'in' ),
				array( 'attribute' => '', 'spec' => '', 'unit' => '' ),
				array( 'attribute' => 'Board\'s "length"', 'spec' => 'deck_length', 'unit' => '' ),
			),
			array( 'field', 'table', 'attribute' )
		);
		self::assertSame(
			array(
				'attribute_mapping' => array(
					array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'in' ),
					array( 'attribute' => 'Board\'s "length"', 'spec' => 'deck_length', 'unit' => '' ),
				),
				'source_order'      => array( 'field', 'table', 'attribute' ),
			),
			get_option( Options::SETTINGS ),
			'as typed, with no slash added'
		);
		$settings = $this->service( ShopSettings::class );
		self::assertSame( 'Board\'s "length"', $settings->attributeMapping()[1]->attribute );
		self::assertSame( Origin::Field, $settings->sourceOrder()->origins[0] );
		self::assertSame( 1, $this->service( IndexJobs::class )->state()?->run, 'every part is read again' );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );

		// Saved again unchanged, nothing is indexed again.
		$this->send( 'shop_manager', get_option( Options::SETTINGS )['attribute_mapping'], array( 'field', 'table', 'attribute' ) );
		self::assertSame( 1, $this->service( IndexJobs::class )->state()?->run );
	}

	public function test_who_cannot_manage_woocommerce_saves_nothing(): void {
		foreach ( array( 'editor', 'customer', 'subscriber' ) as $role ) {
			$this->send( $role, array( array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => '' ) ) );
			self::assertFalse( get_option( Options::SETTINGS ), $role );
		}
		wp_set_current_user( 0 );
		$this->page()->save();
		self::assertFalse( get_option( Options::SETTINGS ), 'signed out' );
	}

	public function test_without_the_forms_nonce_nothing_is_saved(): void {
		try {
			$this->send( 'administrator', array( array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => '' ) ), array( 'table', 'field', 'attribute' ), false );
			self::fail( 'WordPress stops the request' );
		} catch ( \WPDieException $stopped ) {
			self::assertFalse( get_option( Options::SETTINGS ) );
		}
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'another-form' );
		try {
			$this->page()->save();
			self::fail( 'another form\'s nonce is not this one\'s' );
		} catch ( \WPDieException $stopped ) {
			self::assertFalse( get_option( Options::SETTINGS ) );
		}
	}

	// Claude in Cowork's review of milestone 3, minor 8: a unit problem was not tied to the unit's own control.
	public function test_a_units_problem_is_tied_to_the_unit_control_of_its_line(): void {
		$page    = $this->send(
			'shop_manager',
			array(
				array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'in' ),
				array( 'attribute' => 'pa_pattern', 'spec' => 'hole_pattern', 'unit' => 'mm' ),
			),
			array( 'table', 'field', 'attribute' )
		);
		$mapping = self::drawn( $page, 'mappingField' );
		self::assertStringContainsString( 'This spec is not a length, so it has no unit.', $mapping );
		self::assertMatchesRegularExpression( '/id="cbuild-mapping-2-unit"\s+name="cbuild_mapping\[1\]\[unit\]"\s+aria-invalid="true" aria-describedby="cbuild-mapping-problem-2"/', $mapping );
		self::assertDoesNotMatchRegularExpression( '/id="cbuild-mapping-1-unit"[^>]*aria-invalid/', $mapping );
		self::assertSame( 3, substr_count( $mapping, 'aria-invalid="true"' ), 'the line\'s attribute, spec and unit' );
	}

	public function test_a_line_that_is_wrong_saves_nothing_and_is_shown_again_with_what_is_wrong(): void {
		update_option( Options::SETTINGS, array( 'attribute_mapping' => array( array( 'attribute' => 'pa_old', 'spec' => 'deck_width', 'unit' => '' ) ) ) );
		$before = get_option( Options::SETTINGS );
		$page   = $this->send(
			'shop_manager',
			array(
				array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'in' ),
				array( 'attribute' => 'pa_length', 'spec' => '', 'unit' => '' ),
			),
			array( 'field', 'field', 'attribute' )
		);
		self::assertSame( $before, get_option( Options::SETTINGS ), 'none of it is saved' );

		ob_start();
		\WC_Admin_Settings::show_messages();
		$messages = (string) ob_get_clean();
		self::assertStringContainsString( 'The settings were not saved.', $messages );

		$mapping = self::drawn( $page, 'mappingField' );
		self::assertStringContainsString( 'value="pa_width"', $mapping, 'what was typed is kept' );
		self::assertStringContainsString( 'value="pa_length"', $mapping );
		self::assertStringNotContainsString( 'pa_old', $mapping );
		self::assertStringContainsString( 'Choose the spec this attribute feeds, or clear the line.', $mapping );
		self::assertSame( 2, substr_count( $mapping, 'aria-invalid="true"' ), 'the attribute and the spec of the line that is wrong' );
		self::assertSame( 2, substr_count( $mapping, 'aria-describedby="cbuild-mapping-problem-2"' ) );
		self::assertSame( 1, substr_count( $mapping, 'id="cbuild-mapping-problem-2"' ) );
		self::assertSame( 5, substr_count( $mapping, '[attribute]"' ), 'the two sent and three blank' );
		// Issue #19: the order's problem is the order's to show, and no id is on the page twice.
		self::assertStringNotContainsString( 'Put each source in one place', $mapping );
		self::assertStringNotContainsString( 'id="cbuild-mapping-problem"', $mapping );

		$order = self::drawn( $page, 'orderField' );
		self::assertStringContainsString( 'Put each source in one place: first, second and third.', $order );
		self::assertSame( 3, substr_count( $order, 'aria-invalid="true"' ) );
		self::assertSame( 2, substr_count( $order, '<option value="field"  selected=\'selected\'>' ), 'as sent' );
	}

	public function test_the_fields_draw_what_is_saved_every_control_named_and_nothing_unescaped(): void {
		wc_create_attribute( array( 'name' => 'Width <b>', 'slug' => 'cbuildwidth' ) );
		$evil = '"><script>alert(1)</script>';
		update_option(
			Options::SETTINGS,
			array(
				'attribute_mapping' => array(
					array( 'attribute' => 'pa_cbuildwidth', 'spec' => 'deck_width', 'unit' => 'mm' ),
					array( 'attribute' => $evil, 'spec' => 'deck_length', 'unit' => '' ),
				),
				'source_order'      => array( 'attribute', 'field', 'table' ),
			)
		);
		$page    = $this->page();
		$mapping = self::drawn( $page, 'mappingField' );
		self::assertStringNotContainsString( '<script>', $mapping );
		self::assertStringNotContainsString( 'Width <b>', $mapping );
		self::assertStringContainsString( 'value="&quot;&gt;&lt;script&gt;alert(1)&lt;/script&gt;"', $mapping );
		self::assertStringContainsString( '<option value="pa_cbuildwidth" label="Width &lt;b&gt;">', $mapping, 'the shop\'s attributes are offered' );
		self::assertStringContainsString( '<option value="deck_width"  selected=\'selected\'>Deck width</option>', $mapping );
		self::assertStringContainsString( '<option value="mm"  selected=\'selected\'>Millimetres (mm)</option>', $mapping );
		self::assertStringNotContainsString( 'aria-invalid', $mapping );
		self::assertSame( 5, substr_count( $mapping, '[attribute]"' ), 'two saved and three blank' );
		foreach ( range( 1, 5 ) as $line ) {
			self::assertStringContainsString( 'Line ' . $line, $mapping );
			foreach ( array( 'attribute', 'spec', 'unit' ) as $control ) {
				self::assertSame( 1, substr_count( $mapping, '<label for="cbuild-mapping-' . $line . '-' . $control . '">' ), 'a visible label' );
				self::assertSame( 1, substr_count( $mapping, 'id="cbuild-mapping-' . $line . '-' . $control . '"' ), 'for one control' );
			}
		}
		self::assertSame( 5, substr_count( $mapping, '<fieldset' ) );
		self::assertSame( 5, substr_count( $mapping, '<legend>' ), 'each line a named group' );
		self::assertSame( 5, substr_count( $mapping, '<option value="">No spec</option>' ), 'each line offers no spec, and every spec' );
		self::assertSame( 5 * ( count( SpecKey::cases() ) + 1 + count( Unit::cases() ) + 1 ) + 1, substr_count( $mapping, '<option' ), 'and the one attribute the shop has' );
		self::assertSame( 5, substr_count( $mapping, '>Number in a set</option>' ) );

		$order = self::drawn( $page, 'orderField' );
		self::assertSame( 3, substr_count( $order, '<label for="cbuild-order-' ) );
		self::assertMatchesRegularExpression( '/id="cbuild-order-0".*?<option value="attribute"  selected=\'selected\'>A mapped attribute/s', $order );
		self::assertMatchesRegularExpression( '/id="cbuild-order-2".*?<option value="table"  selected=\'selected\'>The maker&#039;s chart/s', $order );
		self::assertStringContainsString( '<legend', $order );
	}

	public function test_saving_leaves_the_shops_other_settings_as_they_are(): void {
		update_option( Options::SETTINGS, array( 'build_discount' => '5', 'source_order' => array( 'attribute', 'field', 'table' ) ) );
		$this->service( ShopSettings::class )->save( array(), SourceOrder::standard() );
		self::assertSame(
			array( 'build_discount' => '5', 'source_order' => array( 'table', 'field', 'attribute' ), 'attribute_mapping' => array() ),
			get_option( Options::SETTINGS )
		);
	}

	public function test_the_schema_and_the_domain_agree_on_what_a_setting_is(): void {
		$schema = wp_json_file_decode( CBUILD_PATH . 'schema/settings.json', array( 'associative' => true ) );
		self::assertIsArray( $schema );
		$line = $schema['properties']['attribute_mapping']['items']['properties'];
		self::assertSame( array_column( SpecKey::cases(), 'value' ), $line['spec']['enum'] );
		self::assertSame( array_merge( array( '' ), array_column( Unit::cases(), 'value' ) ), $line['unit']['enum'] );
		self::assertSame( AttributeMap::MAX_NAME, $line['attribute']['maxLength'] );
		self::assertSame( SettingsEditor::MAX_LINES, $schema['properties']['attribute_mapping']['maxItems'] );
		self::assertSame( array_column( Origin::cases(), 'value' ), array_values( array_intersect( array( 'field', 'attribute', 'table' ), $schema['properties']['source_order']['items']['enum'] ) ) );
		self::assertCount( count( Origin::cases() ), $schema['properties']['source_order']['items']['enum'] );

		$accepts = static fn( array $document ): bool => true === rest_validate_value_from_schema( $document, $schema, 'cbuild_settings' );
		self::assertTrue( $accepts( array() ), 'a shop that has saved nothing' );
		self::assertTrue( $accepts( array( 'attribute_mapping' => array(), 'source_order' => array( 'attribute', 'table', 'field' ), 'a_later_setting' => 1 ) ) );

		// What the schema refuses, the reader does not take as a setting either.
		$settings = $this->service( ShopSettings::class );
		$refused  = array(
			'two sources'    => array( 'source_order' => array( 'table', 'field' ) ),
			'one twice'      => array( 'source_order' => array( 'table', 'table', 'field' ) ),
			'a fourth'       => array( 'source_order' => array( 'table', 'field', 'attribute', 'feed' ) ),
			'an unknown one' => array( 'source_order' => array( 'table', 'field', 'feed' ) ),
			'no attribute'   => array( 'attribute_mapping' => array( array( 'attribute' => '', 'spec' => 'deck_width', 'unit' => '' ) ) ),
			'a long name'    => array( 'attribute_mapping' => array( array( 'attribute' => str_repeat( 'a', 191 ), 'spec' => 'deck_width', 'unit' => '' ) ) ),
			'an unknown spec' => array( 'attribute_mapping' => array( array( 'attribute' => 'pa_width', 'spec' => 'deck_girth', 'unit' => '' ) ) ),
		);
		foreach ( $refused as $what => $document ) {
			self::assertFalse( $accepts( $document ), $what );
			update_option( Options::SETTINGS, $document );
			self::assertEquals( SourceOrder::standard(), $settings->sourceOrder(), $what );
			self::assertSame( array(), $settings->attributeMapping(), $what );
		}
		self::assertFalse( $accepts( array( 'attribute_mapping' => array( array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'cm' ) ) ) ), 'an unknown unit' );
		self::assertFalse( $accepts( array( 'attribute_mapping' => array( array( 'attribute' => 'pa_width', 'spec' => 'deck_width' ) ) ) ), 'a line with no unit key' );
	}

	public function test_a_document_the_schema_refuses_is_not_stored(): void {
		update_option( Options::SETTINGS, array( 'attribute_mapping' => 'broken by another plugin', 'source_order' => array( 'table' ) ) );
		$this->service( ShopSettings::class )->save( array(), SourceOrder::standard() );
		self::assertSame( array( 'attribute_mapping' => array(), 'source_order' => array( 'table', 'field', 'attribute' ) ), get_option( Options::SETTINGS ), 'the two keys are replaced whole' );

		$lines = array();
		foreach ( range( 1, 51 ) as $number ) {
			$lines[] = AttributeMap::read( array( 'attribute' => 'a' . $number, 'spec' => 'deck_width' ) );
		}
		$this->expectException( PluginException::class );
		$this->expectExceptionMessage( 'schema/settings.json' );
		$this->service( ShopSettings::class )->save( array_values( array_filter( $lines ) ), SourceOrder::standard() );
	}

	/**
	 * The terms as the form sends them, with nothing wrong, in a shop that writes decimals with a comma.
	 */
	private const array TERMS = array(
		'discount'       => '7,5',
		'coupons'        => 'parts',
		'rate'           => '60',
		'header'         => 'X-Forwarded-For',
		'default_on'     => '1',
		'fee'            => '15,50',
		'lead_time'      => 'Two working days',
		'returns_notice' => "An assembled board\ncan't be \"returned\".",
	);

	/**
	 * Send the form with its terms as this role.
	 *
	 * @param string       $role  The user's role.
	 * @param array<mixed> $terms The fields that differ from the ones with nothing wrong.
	 * @param array<mixed> $lines The mapping's lines.
	 */
	private function terms( string $role, array $terms = array(), array $lines = array() ): SettingsPage {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$_POST['cbuild_terms'] = wp_slash( $terms + self::TERMS );
		return $this->send( $role, $lines );
	}

	/**
	 * The three groups of terms as the tab draws them.
	 *
	 * @param SettingsPage $page The tab.
	 */
	private static function groups( SettingsPage $page ): string {
		$drawn = '';
		foreach ( array( 'pricing', 'requests', 'kits' ) as $group ) {
			ob_start();
			$page->termsField( array( 'group' => $group ) );
			$drawn .= (string) ob_get_clean();
		}
		return $drawn;
	}

	public function test_a_shop_manager_saves_the_terms_and_no_part_is_read_again(): void {
		$this->terms( 'shop_manager' );
		$stored = get_option( Options::SETTINGS );
		self::assertSame(
			array(
				'build_discount'       => '7.5',
				'fixed_coupons'        => 'parts',
				'rate_limit'           => 60,
				'trusted_proxy_header' => 'X-Forwarded-For',
				'kit_defaults'         => array(
					'assembly' => array(
						'schema_version' => 1,
						'default_on'     => true,
						'fee'            => '15.5',
						'lead_time'      => 'Two working days',
						'returns_notice' => "An assembled board\ncan't be \"returned\".",
					),
				),
				'attribute_mapping'    => array(),
				'source_order'         => array( 'table', 'field', 'attribute' ),
			),
			$stored,
			'as typed, the notice with its line break and no slash added'
		);
		self::assertSame( array_keys( $stored ), array_values( array_intersect( array_keys( $stored ), Options::settingsKeys() ) ), 'every key is one the plugin names' );
		$terms = $this->service( ShopSettings::class )->terms();
		self::assertSame( 750, $terms->discount );
		self::assertSame( 60, $terms->rateLimit );
		self::assertNull( $this->service( IndexJobs::class )->state(), 'the terms feed no spec' );
		self::assertSame( array(), self::waiting( Options::JOB_REINDEX ) );
	}

	public function test_the_terms_keys_are_the_domains(): void {
		self::assertSame(
			array( BuildTerms::DISCOUNT, BuildTerms::COUPONS, BuildTerms::RATE, BuildTerms::PROXY, BuildTerms::KIT_DEFAULTS ),
			array( Options::SETTING_BUILD_DISCOUNT, Options::SETTING_FIXED_COUPONS, Options::SETTING_RATE_LIMIT, Options::SETTING_PROXY_HEADER, Options::SETTING_KIT_DEFAULTS )
		);
		self::assertSame( array_keys( BuildTerms::read( null )->toArray() ), array_slice( Options::settingsKeys(), 2 ) );
	}

	public function test_who_cannot_manage_woocommerce_saves_no_terms(): void {
		$this->terms( 'editor' );
		self::assertFalse( get_option( Options::SETTINGS ) );
	}

	public function test_a_form_without_the_terms_leaves_them_as_they_are(): void {
		$this->terms( 'shop_manager' );
		unset( $_POST['cbuild_terms'] );
		$this->send( 'shop_manager', array( array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => '' ) ) );
		self::assertSame( 750, $this->service( ShopSettings::class )->terms()->discount );
		self::assertCount( 1, $this->service( ShopSettings::class )->attributeMapping() );
	}

	public function test_a_term_that_is_wrong_saves_nothing_and_is_shown_again_beside_what_is_wrong(): void {
		$this->terms( 'shop_manager' );
		$before = get_option( Options::SETTINGS );
		$evil   = '"><script>alert(1)</script>';
		$page   = $this->terms(
			'shop_manager',
			array(
				'discount' => '100',
				'rate'     => '5',
				'header'   => $evil,
				'fee'      => 'free',
				'coupons'  => 'kit',
			),
			array( array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => '' ) )
		);
		self::assertSame( $before, get_option( Options::SETTINGS ), 'not the mapping either' );
		ob_start();
		\WC_Admin_Settings::show_messages();
		self::assertStringContainsString( 'The settings were not saved. Each problem is shown beside its field.', (string) ob_get_clean() );

		$mapping = self::drawn( $page, 'mappingField' );
		self::assertStringNotContainsString( 'aria-invalid', $mapping, 'the mapping shows none of the terms\' problems' );
		self::assertStringNotContainsString( 'notice-error', $mapping );
		self::assertStringContainsString( 'value="pa_width"', $mapping, 'and is shown as sent' );

		$drawn = self::groups( $page );
		self::assertStringNotContainsString( '<script>', $drawn );
		self::assertSame( 4, substr_count( $drawn, 'aria-invalid="true"' ) );
		$wrong = array(
			'discount' => array( '100', 'Enter a percentage below 100 with up to two decimals, or 0 for none.' ),
			'rate'     => array( '5', 'Enter a whole number from 10 to 6000.' ),
			'header'   => array( '&quot;&gt;', 'Enter the header&#039;s name alone, such as X-Forwarded-For: letters, digits and hyphens, starting with a letter, 64 characters at most.' ),
			'fee'      => array( 'free', 'Enter an amount with up to four decimals, or 0 for none.' ),
		);
		foreach ( $wrong as $field => $shown ) {
			self::assertMatchesRegularExpression( '/<input[^>]*id="cbuild-terms-' . $field . '"[^>]*value="' . preg_quote( $shown[0], '/' ) . '"[^>]*aria-describedby="cbuild-terms-' . $field . '-note cbuild-terms-' . $field . '-problem"[^>]*aria-invalid="true"/', $drawn, $field );
			self::assertStringContainsString( '<div class="notice notice-error inline" id="cbuild-terms-' . $field . '-problem"><p>' . $shown[1] . '</p></div>', $drawn, $field );
		}
		// What was right is shown as sent, not as saved.
		self::assertStringContainsString( '<option value="kit"  selected=\'selected\'>', $drawn );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-lead_time"[^>]*value="Two working days"/', $drawn );
	}

	public function test_a_long_lead_time_and_a_long_notice_are_each_named(): void {
		$page  = $this->terms( 'shop_manager', array( 'lead_time' => str_repeat( 'a', 201 ) ) );
		$drawn = self::groups( $page );
		self::assertStringContainsString( 'id="cbuild-terms-lead_time-problem"><p>The lead time is too long. Use 200 characters or fewer.</p>', $drawn );
		$page  = $this->terms( 'shop_manager', array( 'returns_notice' => str_repeat( 'a', 1001 ) ) );
		$drawn = self::groups( $page );
		self::assertStringContainsString( 'id="cbuild-terms-returns_notice-problem"><p>The returns notice is too long. Use 1,000 characters or fewer.</p>', $drawn );
		$page  = $this->terms( 'shop_manager', array( 'coupons' => 'cart' ) );
		$drawn = self::groups( $page );
		self::assertStringContainsString( 'id="cbuild-terms-coupons-problem"><p>Choose where the coupon applies from the list.</p>', $drawn );
		self::assertFalse( get_option( Options::SETTINGS ) );
	}

	public function test_the_terms_draw_what_is_saved_with_the_shops_separator_and_every_control_named(): void {
		$drawn = self::groups( $this->page() );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-discount"[^>]*value="0"/', $drawn, 'a shop that has saved nothing' );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-rate"[^>]*value="120"/', $drawn );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-header"[^>]*value=""/', $drawn );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-fee"[^>]*value="0"/', $drawn );
		self::assertStringContainsString( '<option value="kit"  selected=\'selected\'>', $drawn );
		self::assertDoesNotMatchRegularExpression( '/id="cbuild-terms-default_on"[^>]*checked/', $drawn );

		$this->terms( 'shop_manager' );
		$drawn = self::groups( $this->page() );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-discount"[^>]*value="7,5"/', $drawn );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-fee"[^>]*value="15,5"/', $drawn );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-rate"[^>]*value="60"/', $drawn );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-header"[^>]*value="X-Forwarded-For"/', $drawn );
		self::assertStringContainsString( '<option value="parts"  selected=\'selected\'>', $drawn );
		self::assertMatchesRegularExpression( '/id="cbuild-terms-default_on"[^>]*checked/', $drawn );
		self::assertStringContainsString( ">An assembled board\ncan&#039;t be &quot;returned&quot;.</textarea>", $drawn );
		self::assertStringNotContainsString( 'aria-invalid', $drawn );
		self::assertStringNotContainsString( 'notice-error', $drawn );
		foreach ( array( 'discount', 'coupons', 'rate', 'header', 'default_on', 'fee', 'lead_time', 'returns_notice' ) as $field ) {
			self::assertSame( 1, substr_count( $drawn, '<label for="cbuild-terms-' . $field . '">' ), $field . ': a visible label' );
			self::assertSame( 1, preg_match_all( '/\sid="cbuild-terms-' . $field . '"/', $drawn ), $field . ': for one control' );
			self::assertSame( 1, preg_match_all( '/\sname="cbuild_terms\[' . $field . '\]"/', $drawn ), $field );
		}
		self::assertSame( 8, substr_count( $drawn, '<th scope="row" class="titledesc">' ) );
		self::assertSame( '', self::groups_of( $this->page(), 'nothing' ), 'a group the tab does not have draws nothing' );
	}

	/**
	 * One group as the tab draws it.
	 *
	 * @param SettingsPage $page  The tab.
	 * @param string       $group The group.
	 */
	private static function groups_of( SettingsPage $page, string $group ): string {
		ob_start();
		$page->termsField( array( 'group' => $group ) );
		return (string) ob_get_clean();
	}

	public function test_the_schema_and_the_domain_agree_on_what_a_term_is(): void {
		$schema = wp_json_file_decode( CBUILD_PATH . 'schema/settings.json', array( 'associative' => true ) );
		self::assertIsArray( $schema );
		$kit = wp_json_file_decode( CBUILD_PATH . 'schema/kit-assembly.json', array( 'associative' => true ) );
		self::assertIsArray( $kit );
		$assembly = $schema['properties']['kit_defaults']['properties']['assembly'];
		foreach ( array( 'type', 'additionalProperties', 'required', 'properties' ) as $part ) {
			self::assertSame( $kit[ $part ], $assembly[ $part ], 'a new kit\'s assembly is a kit\'s: ' . $part );
		}
		self::assertSame( BuildTerms::RATE_MIN, $schema['properties']['rate_limit']['minimum'] );
		self::assertSame( BuildTerms::RATE_MAX, $schema['properties']['rate_limit']['maximum'] );
		self::assertSame( array( BuildTerms::ON_KIT, BuildTerms::ON_PARTS ), $schema['properties']['fixed_coupons']['enum'] );

		$accepts = static fn( array $document ): bool => true === rest_validate_value_from_schema( $document, $schema, 'cbuild_settings' );
		self::assertTrue( $accepts( BuildTerms::read( null )->toArray() ), 'the defaults' );
		$most = array(
			'build_discount'       => '99.99',
			'fixed_coupons'        => 'parts',
			'rate_limit'           => 6000,
			'trusted_proxy_header' => 'X' . str_repeat( '1', 63 ),
			'kit_defaults'         => array(
				'assembly' => array(
					'schema_version' => 1,
					'default_on'     => true,
					'fee'            => '999999999.9999',
					'lead_time'      => str_repeat( 'é', 200 ),
					'returns_notice' => str_repeat( 'é', 1000 ),
				),
			),
		);
		self::assertTrue( $accepts( $most ), 'the most each takes' );
		self::assertSame( $most, BuildTerms::read( $most )->toArray() );

		// What the schema refuses, the reader does not take as a setting either.
		$none    = BuildTerms::read( null )->toArray();
		$refused = array(
			'all of it'          => array( 'build_discount' => '100' ),
			'three places'       => array( 'build_discount' => '5.125' ),
			'a number'           => array( 'build_discount' => 5 ),
			'a line after'       => array( 'build_discount' => "5\n" ),
			'the cart'           => array( 'fixed_coupons' => 'cart' ),
			'too few'            => array( 'rate_limit' => 9 ),
			'too many'           => array( 'rate_limit' => 6001 ),
			'a rate in text'     => array( 'rate_limit' => 'often' ),
			'a header with more' => array( 'trusted_proxy_header' => 'X-Real-IP: 1' ),
			'a long header'      => array( 'trusted_proxy_header' => 'X' . str_repeat( '1', 64 ) ),
			'defaults in text'   => array( 'kit_defaults' => 'none' ),
			'a fee in words'     => array( 'kit_defaults' => array( 'assembly' => array( 'fee' => 'free' ) + $none['kit_defaults']['assembly'] ) ),
			'a long lead time'   => array( 'kit_defaults' => array( 'assembly' => array( 'lead_time' => str_repeat( 'a', 201 ) ) + $none['kit_defaults']['assembly'] ) ),
		);
		$settings = $this->service( ShopSettings::class );
		foreach ( $refused as $what => $document ) {
			self::assertFalse( $accepts( $document ), $what );
			update_option( Options::SETTINGS, $document );
			self::assertSame( $none, $settings->terms()->toArray(), $what );
		}
	}
}
