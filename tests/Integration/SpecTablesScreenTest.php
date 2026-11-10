<?php
/**
 * WooCommerce > Builder, Spec tables, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\BuilderHooks;
use CreativeBranding\SkateboardBuilder\Admin\SpecTablesScreen;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Scope;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\SpecTableEditor;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Spec, Admin, WooCommerce > Builder: the makers' charts, a row per size. Audit, Security: a state change needs the
 * capability and the nonce, and nothing a person typed is echoed unescaped. Audit, Accessibility: every control has
 * a label, and a problem is tied to the field it is about.
 */
final class SpecTablesScreenTest extends IntegrationTestCase {

	private const FIELDS = array( 'cbuild_action', 'cbuild_table', 'cbuild_name', 'cbuild_rows', '_wpnonce' );

	public function tear_down(): void {
		foreach ( self::FIELDS as $field ) {
			unset( $_POST[ $field ], $_REQUEST[ $field ] );
		}
		unset( $_GET['table'], $_GET['delete'], $_GET['notice'] );
		delete_option( 'woocommerce_price_decimal_sep' );
		delete_option( Options::REINDEX );
		parent::tear_down();
	}

	private function screen(): SpecTablesScreen {
		return new SpecTablesScreen( $this->service( SpecTables::class ), $this->service( SpecTableEditor::class ) );
	}

	private function as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * A chart with one size.
	 *
	 * @param string $name The maker.
	 */
	private function chart( string $name = 'Independent' ): int {
		return $this->service( SpecTables::class )->create(
			$name,
			SpecTable::read(
				array(
					array(
						'size'    => '149',
						'specs'   => array(
							'axle_width' => '8.5000',
							'deck_min'   => '8.3750',
							'baseplate'  => 'both',
							'set_size'   => '2',
						),
						'source'  => 'Independent, https://example.com/chart',
						'read_on' => '2026-11-10',
					),
				)
			)
		);
	}

	/**
	 * Send a form, slashed as PHP's superglobals are in WordPress.
	 *
	 * @param array<string, mixed> $fields The form's fields.
	 * @param bool                 $nonce  Whether the form's nonce is sent.
	 * @return array{0: SpecTablesScreen, 1: string|null} The screen, and where it leads.
	 */
	private function send( array $fields, bool $nonce = true ): array {
		foreach ( self::FIELDS as $field ) {
			unset( $_POST[ $field ], $_REQUEST[ $field ] );
		}
		foreach ( wp_slash( $fields ) as $name => $value ) {
			$_POST[ $name ] = $value;
		}
		if ( $nonce ) {
			$_REQUEST['_wpnonce'] = wp_create_nonce( SpecTablesScreen::NONCE );
		}
		$screen = $this->screen();
		return array( $screen, $screen->handle() );
	}

	/**
	 * What the page draws.
	 *
	 * @param SpecTablesScreen          $screen The screen.
	 * @param array<string, int|string> $query  The address's arguments.
	 */
	private static function drawn( SpecTablesScreen $screen, array $query = array() ): string {
		unset( $_GET['table'], $_GET['delete'], $_GET['notice'] );
		foreach ( $query as $name => $value ) {
			$_GET[ $name ] = (string) $value;
		}
		ob_start();
		$screen->render();
		return (string) ob_get_clean();
	}

	/**
	 * The rows a chart stores.
	 *
	 * @param int $id The chart's ID.
	 * @return list<array<string, mixed>>
	 */
	private function rows( int $id ): array {
		return $this->service( SpecTables::class )->all()[ $id ]['table']->toArray();
	}

	public function test_the_page_is_under_woocommerce_for_who_manages_the_shop_in_the_admin_alone(): void {
		$built = static fn( array $scopes ): array => array_map( 'get_class', ( new \CreativeBranding\SkateboardBuilder\Container() )->hookablesFor( ...$scopes ) );
		self::assertContains( BuilderHooks::class, $built( Scope::atBoot( true, false, false ) ) );
		self::assertNotContains( BuilderHooks::class, $built( Scope::atBoot( false, false, false ) ) );

		$hooks = $this->service( BuilderHooks::class );
		$hooks->register();
		self::assertSame( 60, has_action( 'admin_menu', array( $hooks, 'menu' ) ), 'after WooCommerce\'s own menu' );
		remove_action( 'admin_menu', array( $hooks, 'menu' ), 60 );

		global $submenu, $_registered_pages;
		$this->as( 'administrator' );
		$hooks->menu();
		$entry = end( $submenu['woocommerce'] );
		self::assertSame( array( 'Builder', 'manage_woocommerce', 'cbuild', 'Builder' ), $entry );
		$hook = get_plugin_page_hookname( 'cbuild', 'woocommerce' );
		self::assertTrue( $_registered_pages[ $hook ] );
		self::assertSame( 10, has_action( $hook, array( $hooks, 'render' ) ) );
		self::assertSame( 10, has_action( 'load-' . $hook, array( $hooks, 'load' ) ) );
		remove_action( $hook, array( $hooks, 'render' ) );
		remove_action( 'load-' . $hook, array( $hooks, 'load' ) );
		unset( $submenu['woocommerce'], $_registered_pages[ $hook ] );

		self::assertSame( admin_url( 'admin.php?page=cbuild&table=7' ), SpecTablesScreen::url( array( 'table' => 7 ) ) );
	}

	public function test_the_list_names_each_chart_with_its_sizes_and_offers_to_add_one(): void {
		$this->as( 'shop_manager' );
		$empty = self::drawn( $this->screen() );
		self::assertStringContainsString( 'There are no charts yet.', $empty );
		self::assertStringNotContainsString( '<table', $empty );

		$id   = $this->chart( 'Thunder & "Co"' );
		$list = self::drawn( $this->screen(), array( 'notice' => 'deleted' ) );
		self::assertStringContainsString( '<h1>Builder</h1>', $list );
		self::assertStringContainsString( '<a href="' . esc_url( admin_url( 'admin.php?page=cbuild&table=' . $id ) ) . '">Thunder &amp; &quot;Co&quot;</a>', $list );
		self::assertStringContainsString( '<td>1</td>', $list );
		self::assertStringContainsString( '<div class="notice notice-success"><p>The chart was deleted.</p></div>', $list );
		self::assertStringContainsString( '<input type="hidden" name="cbuild_action" value="add" />', $list );
		self::assertSame( 1, substr_count( $list, '<label for="cbuild-name">' ) );
		self::assertSame( 1, substr_count( $list, 'id="cbuild-name"' ) );
		self::assertSame( 1, substr_count( $list, 'name="_wpnonce"' ) );
		self::assertStringNotContainsString( 'aria-invalid', $list );

		self::assertStringNotContainsString( 'notice-', self::drawn( $this->screen(), array( 'notice' => 'anything' ) ), 'a notice the page does not know says nothing' );
		$gone = self::drawn( $this->screen(), array( 'table' => $id + 999 ) );
		self::assertStringContainsString( '<div class="notice notice-error"><p>That chart is no longer there.</p></div>', $gone );
		self::assertStringContainsString( '<h1>Builder</h1>', $gone, 'the list, not a chart' );

		$this->as( 'subscriber' );
		self::assertSame( '', self::drawn( $this->screen() ) );
	}

	public function test_a_chart_is_added_by_name_and_a_blank_name_is_refused_beside_its_field(): void {
		$this->as( 'shop_manager' );
		[ , $next ] = $this->send(
			array(
				'cbuild_action' => 'add',
				'cbuild_name'   => ' Ace\'s "AF1" ',
			)
		);
		$tables     = $this->service( SpecTables::class )->all();
		$id         = (int) array_key_first( $tables );
		self::assertSame( 'Ace\'s "AF1"', $tables[ $id ]['name'], 'as typed, with no slash added' );
		self::assertSame( admin_url( 'admin.php?page=cbuild&table=' . $id . '&notice=added' ), $next );
		self::assertSame( '', get_post_meta( $id, Options::META_TABLE_SEED, true ) );

		[ $screen, $stay ] = $this->send(
			array(
				'cbuild_action' => 'add',
				'cbuild_name'   => '  ',
			)
		);
		self::assertNull( $stay );
		self::assertCount( 1, $this->service( SpecTables::class )->all() );
		$list = self::drawn( $screen );
		self::assertStringContainsString( '<div class="notice notice-error"><p>Nothing was saved. Each problem is shown with the field it is about.</p></div>', $list );
		self::assertStringContainsString( '<div class="notice notice-error inline" id="cbuild-name-problem"><p>Type the maker&#039;s name.</p></div>', $list );
		self::assertMatchesRegularExpression( '/id="cbuild-name"[^>]*aria-invalid="true" aria-describedby="cbuild-name-problem"/s', $list );
	}

	public function test_a_chart_draws_a_group_of_labelled_fields_per_size_and_blank_groups_after(): void {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$this->as( 'shop_manager' );
		$id   = $this->chart();
		$page = self::drawn( $this->screen(), array( 'table' => $id, 'notice' => 'saved' ) );

		self::assertMatchesRegularExpression( '/<h1>\s*Spec table: Independent\s*<\/h1>/', $page );
		self::assertStringContainsString( 'The chart was saved. Every part is being read again, in the background.', $page );
		self::assertStringContainsString( '<form method="post" action="' . esc_url( admin_url( 'admin.php?page=cbuild&table=' . $id ) ) . '">', $page );
		self::assertStringContainsString( '<input type="hidden" name="cbuild_table" value="' . $id . '" />', $page );
		self::assertSame( 4, substr_count( $page, '<fieldset class="cbuild-chart-row">' ), 'one size and three blank groups' );
		self::assertSame( 4, substr_count( $page, '<legend>' ) );
		self::assertMatchesRegularExpression( '/<legend>\s*Row 1: size 149\s*<\/legend>/', $page );
		self::assertMatchesRegularExpression( '/<legend>\s*Row 4\s*<\/legend>/', $page );
		foreach ( range( 1, 4 ) as $row ) {
			foreach ( array( 'size', 'axle_width', 'deck_min', 'deck_max', 'truck_height', 'max_wheel', 'baseplate', 'source', 'read' ) as $field ) {
				$control = 'cbuild-row-' . $row . '-' . $field;
				self::assertSame( 1, substr_count( $page, '<label for="' . $control . '">' ), $control . ': a visible label' );
				self::assertSame( 1, substr_count( $page, 'id="' . $control . '"' ), $control . ': for one control' );
			}
		}
		self::assertStringContainsString( '<label for="cbuild-row-1-axle_width">Axle width (in)</label>', $page );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[0\]\[specs\]\[axle_width\]"\s+value="8,5"/', $page, 'no trailing zeros, the shop\'s separator' );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[0\]\[specs\]\[deck_min\]"\s+value="8,375"/', $page );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[0\]\[specs\]\[deck_max\]"\s+value=""/', $page );
		self::assertStringContainsString( '<option value="both"  selected=\'selected\'>Both</option>', $page );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[0\]\[read_on\]"\s+value="2026-11-10"/', $page );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[0\]\[source\]"\s+value="Independent, https:\/\/example.com\/chart"/', $page );
		self::assertStringContainsString( '<input type="hidden" name="cbuild_rows[0][specs][set_size]" value="2" />', $page, 'a figure the groups do not show rides along' );
		self::assertStringNotContainsString( 'aria-invalid', $page );
		self::assertStringContainsString( 'href="' . esc_url( admin_url( 'admin.php?page=cbuild&table=' . $id . '&delete=1' ) ) . '"', $page );
	}

	public function test_a_shop_manager_saves_a_chart_as_the_index_writes_it_and_every_part_is_read_again(): void {
		update_option( 'woocommerce_price_decimal_sep', ',' );
		$this->as( 'shop_manager' );
		$id = $this->chart();
		delete_option( Options::REINDEX );
		[ , $next ] = $this->send(
			array(
				'cbuild_action' => 'save',
				'cbuild_table'  => (string) $id,
				'cbuild_name'   => 'Indy\'s',
				'cbuild_rows'   => array(
					array(
						'size'    => '149',
						'specs'   => array(
							'axle_width'   => '8,5',
							'deck_min'     => '8 3/8',
							'truck_height' => '55',
							'baseplate'    => 'new_school',
							'set_size'     => '2',
							'kingpin'      => 'inverted',
						),
						'source'  => 'Indy\'s "chart"',
						'read_on' => '2026-11-10',
					),
					array(
						'size'    => '',
						'specs'   => array( 'axle_width' => '' ),
						'source'  => '',
						'read_on' => '',
					),
					array(
						'size'  => '159',
						'specs' => array( 'axle_width' => '8.75' ),
					),
				),
			)
		);
		self::assertSame( admin_url( 'admin.php?page=cbuild&table=' . $id . '&notice=saved' ), $next );
		self::assertSame( 'Indy\'s', get_post( $id )->post_title, 'as typed, with no slash added' );
		self::assertSame(
			array(
				array(
					'size'    => '149',
					'specs'   => array(
						'axle_width'   => '8.5000',
						'deck_min'     => '8.3750',
						'truck_height' => '55.0000',
						'baseplate'    => 'new_school',
						'set_size'     => '2',
					),
					'source'  => 'Indy\'s "chart"',
					'read_on' => '2026-11-10',
				),
				array(
					'size'    => '159',
					'specs'   => array( 'axle_width' => '8.7500' ),
					'source'  => '',
					'read_on' => '',
				),
			),
			$this->rows( $id )
		);
		self::assertSame( 1, $this->service( IndexJobs::class )->state()?->run, 'the save asked for a reindex' );
	}

	public function test_a_chart_with_a_mistake_saves_nothing_and_comes_back_as_typed_with_each_problem_in_its_row(): void {
		$this->as( 'administrator' );
		$id     = $this->chart();
		$before = $this->rows( $id );
		[ $screen, $next ] = $this->send(
			array(
				'cbuild_action' => 'save',
				'cbuild_table'  => (string) $id,
				'cbuild_name'   => '',
				'cbuild_rows'   => array(
					array(
						'size'    => '149',
						'specs'   => array( 'axle_width' => '"><script>alert(1)</script>' ),
						'source'  => 'kept as typed',
						'read_on' => '2026-11-10',
					),
					array(
						'size'  => '',
						'specs' => array( 'deck_min' => '8' ),
					),
				),
			)
		);
		self::assertNull( $next );
		self::assertSame( $before, $this->rows( $id ) );
		self::assertSame( 'Independent', get_post( $id )->post_title );

		$page = self::drawn( $screen, array( 'table' => $id ) );
		self::assertStringContainsString( '<div class="notice notice-error"><p>Nothing was saved.', $page );
		self::assertStringNotContainsString( '<script>', $page );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[0\]\[specs\]\[axle_width\]"\s+value="&quot;&gt;"/', $page, 'what was typed, escaped, in its field' );
		self::assertStringContainsString( '<div class="notice notice-error inline" id="cbuild-chart-problem">', $page );
		self::assertStringContainsString( '<p>Type the maker&#039;s name.</p>', $page );
		self::assertMatchesRegularExpression( '/id="cbuild-name"[^>]*aria-invalid="true" aria-describedby="cbuild-chart-problem"/s', $page );
		self::assertStringContainsString( 'id="cbuild-row-1-problem"', $page );
		self::assertStringContainsString( '<p>Axle width: &quot;&quot;&gt;&quot; is not a measurement. Type a number, or a fraction such as 8 1/4.</p>', $page );
		self::assertMatchesRegularExpression( '/id="cbuild-row-1-axle_width"[^>]*aria-invalid="true" aria-describedby="cbuild-row-1-problem"/s', $page );
		self::assertDoesNotMatchRegularExpression( '/id="cbuild-row-1-size"[^>]*aria-invalid/s', $page, 'the size of that row is right' );
		self::assertStringContainsString( '<p>Type the size this row is for, or clear the row.</p>', $page );
		self::assertMatchesRegularExpression( '/id="cbuild-row-2-size"[^>]*aria-invalid="true" aria-describedby="cbuild-row-2-problem"/s', $page );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[0\]\[source\]"\s+value="kept as typed"/', $page );
		self::assertMatchesRegularExpression( '/name="cbuild_rows\[1\]\[specs\]\[deck_min\]"\s+value="8"/', $page );
		self::assertSame( 2, substr_count( $page, '<fieldset class="cbuild-chart-row">' ), 'the rows that were sent, as they were sent' );
	}

	public function test_who_cannot_manage_woocommerce_changes_nothing_and_a_form_needs_its_nonce(): void {
		$id     = $this->chart();
		$fields = array(
			'cbuild_action' => 'delete',
			'cbuild_table'  => (string) $id,
		);
		foreach ( array( 'editor', 'customer', 'subscriber' ) as $role ) {
			$this->as( $role );
			self::assertNull( $this->send( $fields )[1], $role );
			self::assertCount( 1, $this->service( SpecTables::class )->all(), $role );
		}
		wp_set_current_user( 0 );
		self::assertNull( $this->send( $fields )[1], 'signed out' );

		$this->as( 'administrator' );
		try {
			$this->send( $fields, false );
			self::fail( 'WordPress stops the request' );
		} catch ( \WPDieException $stopped ) {
			self::assertCount( 1, $this->service( SpecTables::class )->all() );
		}
		$_REQUEST['_wpnonce'] = wp_create_nonce( 'another-form' );
		try {
			$this->screen()->handle();
			self::fail( 'another form\'s nonce is not this one\'s' );
		} catch ( \WPDieException $stopped ) {
			self::assertCount( 1, $this->service( SpecTables::class )->all() );
		}

		foreach ( self::FIELDS as $field ) {
			unset( $_POST[ $field ], $_REQUEST[ $field ] );
		}
		self::assertNull( $this->screen()->handle(), 'a page that was only opened does nothing' );
		self::assertNull( $this->send( array( 'cbuild_action' => 'rename' ) )[1], 'an action the page does not know does nothing' );
		self::assertNull( $this->send( array( 'cbuild_action' => array( 'save' ) ) )[1] );
	}

	public function test_a_chart_is_deleted_for_good_after_the_page_asks(): void {
		$this->as( 'shop_manager' );
		$id   = $this->chart( 'Thunder <b>' );
		$post = self::factory()->post->create();
		$ask  = self::drawn( $this->screen(), array( 'table' => $id, 'delete' => 1 ) );
		self::assertStringContainsString( 'Delete the chart Thunder', $ask );
		self::assertStringNotContainsString( '<b>', $ask );
		self::assertStringContainsString( '<input type="hidden" name="cbuild_action" value="delete" />', $ask );
		self::assertStringContainsString( '<input type="hidden" name="cbuild_table" value="' . $id . '" />', $ask );
		self::assertStringContainsString( 'A deleted chart cannot be brought back.', $ask );
		self::assertSame( 1, substr_count( $ask, 'name="_wpnonce"' ) );
		self::assertCount( 1, $this->service( SpecTables::class )->all(), 'asking deletes nothing' );

		[ , $other ] = $this->send(
			array(
				'cbuild_action' => 'delete',
				'cbuild_table'  => (string) $post,
			)
		);
		self::assertSame( admin_url( 'admin.php?page=cbuild&notice=missing' ), $other );
		self::assertNotNull( get_post( $post ), 'a post that is not a chart is left alone' );

		[ , $next ] = $this->send(
			array(
				'cbuild_action' => 'delete',
				'cbuild_table'  => (string) $id,
			)
		);
		self::assertSame( admin_url( 'admin.php?page=cbuild&notice=deleted' ), $next );
		self::assertNull( get_post( $id ), 'not in the bin: gone' );
	}

	public function test_the_adapter_refuses_to_save_what_is_not_a_chart(): void {
		$post = self::factory()->post->create( array( 'post_title' => 'A post' ) );
		try {
			$this->service( SpecTables::class )->update( $post, 'Indy', SpecTable::read( array() ) );
			self::fail( 'a post is not a spec table' );
		} catch ( \CreativeBranding\SkateboardBuilder\Support\PluginException $refused ) {
			self::assertStringContainsString( 'could not be saved', $refused->getMessage() );
		}
		self::assertSame( 'A post', get_post( $post )->post_title );
		$this->service( SpecTables::class )->delete( $post );
		self::assertNotNull( get_post( $post ) );
	}

	public function test_the_rows_fields_are_styled_on_the_page(): void {
		$this->screen()->styles();
		self::assertStringContainsString( '.cbuild-field{display:inline-block;', implode( '', (array) wp_styles()->get_data( 'common', 'after' ) ) );
	}
}
