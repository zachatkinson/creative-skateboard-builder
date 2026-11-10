<?php
/**
 * WooCommerce > Builder, Catalogue check, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\BuilderHooks;
use CreativeBranding\SkateboardBuilder\Admin\CheckScreen;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\Reindex;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\ReindexState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Builds;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Spec, Admin, Catalogue check: parts whose sources disagree, and for each kit the parts that lack a spec, presets
 * that no longer fit and slots with nothing in stock; each row links to the field that fixes it. Resilience: a
 * failure shows in the Catalogue check. Audit, Security: the capability and the nonce.
 */
final class CheckScreenTest extends IntegrationTestCase {

	public function tear_down(): void {
		unset( $_POST['cbuild_action'], $_REQUEST['_wpnonce'], $_GET['tab'] );
		delete_option( Options::REINDEX );
		delete_option( Options::IMPORT );
		parent::tear_down();
	}

	private function as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * The screen, over the shop's own kits or these.
	 *
	 * @param Kits|null $kits Kits made from documents.
	 */
	private function screen( ?Kits $kits = null ): CheckScreen {
		$kits ??= $this->service( Kits::class );
		$check  = new CatalogueCheck( $kits, $this->service( PartCatalogue::class ), $this->service( SpecIndexer::class ), $this->service( ReindexStore::class ), $this->service( ImportStore::class ), $this->service( Queue::class ) );
		return new CheckScreen( $check, $kits, $this->service( Reindex::class ) );
	}

	/**
	 * Run the check from its form and draw the page.
	 *
	 * @param CheckScreen $screen The screen.
	 * @param bool        $nonce  Whether the form's nonce is sent.
	 */
	private static function checked( CheckScreen $screen, bool $nonce = true ): string {
		$_POST['cbuild_action'] = 'check';
		unset( $_REQUEST['_wpnonce'] );
		if ( $nonce ) {
			$_REQUEST['_wpnonce'] = wp_create_nonce( CheckScreen::NONCE );
		}
		$screen->handle();
		return self::drawn( $screen );
	}

	private static function drawn( CheckScreen $screen ): string {
		ob_start();
		$screen->render();
		return (string) ob_get_clean();
	}

	public function test_the_check_is_the_pages_third_tab_and_runs_only_when_asked(): void {
		$this->as( 'shop_manager' );
		$_GET['tab'] = 'check';
		ob_start();
		$this->service( BuilderHooks::class )->render();
		$page = (string) ob_get_clean();
		self::assertMatchesRegularExpression( '#<a\s+class="nav-tab nav-tab-active"\s+href="[^"]*tab=check"\s+aria-current="page"\s*>Catalogue check</a>#', $page );
		self::assertSame( 3, substr_count( $page, 'class="nav-tab' ) - 1 );
		self::assertStringContainsString( '<h2>Catalogue check</h2>', $page );
		self::assertStringContainsString( 'value="Run the check"', $page );
		self::assertStringContainsString( 'The shop has no kit yet.', $page );
		self::assertStringNotContainsString( 'role="status"', $page );
		self::assertSame( add_query_arg( array( 'page' => 'cbuild', 'tab' => 'check' ), admin_url( 'admin.php' ) ), CheckScreen::url() );

		$page = self::checked( $this->screen() );
		self::assertStringContainsString( '<div class="notice notice-success inline" role="status"><p>The check found nothing to fix.</p></div>', $page );
		self::assertStringContainsString( 'value="Run the check again"', $page );
		self::assertStringNotContainsString( '<table', $page );
	}

	public function test_a_part_whose_sources_disagree_or_cannot_be_read_is_listed_with_a_link_to_its_part_specs(): void {
		$chart = $this->service( SpecTables::class )->create(
			'Independent',
			SpecTable::read(
				array(
					array(
						'size'  => '149',
						'specs' => array( 'axle_width' => '8.5000' ),
					),
				)
			)
		);
		$truck = new \WC_Product_Simple();
		$truck->set_name( 'Stage 11 <b>149</b>' );
		$truck->set_sku( 'TR-149' );
		$truck->update_meta_data( Options::META_SPECS, array( 'axle_width' => '8.0000' ) );
		$truck->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => $chart,
				'size'  => '149',
			)
		);
		$truck->save();
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Hollows' );
		$parent->save();
		$size = new \WC_Product_Variation();
		$size->set_parent_id( $parent->get_id() );
		$size->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => $chart,
				'size'  => '151',
			)
		);
		$size->save();
		$lost = new \WC_Product_Simple();
		$lost->set_name( 'Lost' );
		$lost->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => 999999,
				'size'  => '1',
			)
		);
		$lost->save();

		$this->as( 'shop_manager' );
		$page = self::checked( $this->screen() );
		self::assertStringContainsString( 'The check found 3 things to fix.', $page );
		self::assertStringContainsString( 'role="region" aria-labelledby="cbuild-found" tabindex="0"', $page );
		self::assertStringContainsString( 'Stage 11 &lt;b&gt;149&lt;/b&gt; (TR-149)</a>', $page );
		self::assertStringContainsString( 'post=' . $truck->get_id() . '&#038;action=edit#cbuild_part_specs"', $page );
		self::assertStringContainsString( 'Axle width: two sources disagree. The maker&#039;s chart says 8.5 in, which is used. The part&#039;s own field says 8 in.', $page );
		// A variation's fields are on its parent's screen, in the variation.
		self::assertStringContainsString( 'post=' . $parent->get_id() . '&#038;action=edit#cbuild_variation_' . $size->get_id() . '"', $page );
		self::assertStringContainsString( 'It names the size &quot;151&quot;, which its maker&#039;s chart does not have.', $page );
		self::assertStringContainsString( 'It names a maker&#039;s chart that is no longer there.', $page );
		self::assertStringNotContainsString( '<b>149', $page );
	}

	public function test_background_work_that_failed_is_listed_with_a_link_to_the_queue(): void {
		$this->service( ReindexStore::class )->save( ( new ReindexState( ReindexState::FAILED, 0, 0, 1 ) )->toArray() );
		$this->service( ImportStore::class )->save( ( new ImportState( ImportState::FAILED, 't', 'id', '.', 1, array(), array(), 1 ) )->toArray() );
		$queue = $this->service( Queue::class );
		self::assertSame( 0, $queue->failed() );
		foreach ( array( Options::QUEUE_GROUP, Options::QUEUE_GROUP, 'another-plugin' ) as $at => $group ) {
			\ActionScheduler::store()->mark_failure( (string) as_enqueue_async_action( Options::JOB_INDEX_PART, array( 'part' => $at + 1 ), $group ) );
		}
		as_enqueue_async_action( Options::JOB_INDEX_PART, array( 'part' => 9 ), Options::QUEUE_GROUP );
		self::assertSame( 2, $queue->failed(), 'the plugin\'s own failed actions, and no other\'s' );

		$this->as( 'administrator' );
		$page = self::checked( $this->screen() );
		self::assertStringContainsString( 'The check found 3 things to fix.', $page );
		self::assertStringContainsString( 'Reading every part again stopped after five failures.', $page );
		self::assertStringContainsString( 'The last specs import stopped before its end.', $page );
		self::assertStringContainsString( '2 background jobs of the builder have failed.', $page );
		self::assertSame( 3, substr_count( $page, 'page=wc-status&#038;tab=action-scheduler&#038;status=failed">Background work</a>' ) );
	}

	public function test_a_kits_findings_are_sentences_with_the_kits_name(): void {
		$kits            = new InMemoryKits();
		$parts           = array(
			'deck'     => array( Parts::of( array( 'deck_length' => '32', 'set_size' => 1 ), 100 ) ),
			'trucks'   => array( Builds::truck( '149' ) ),
			'wheels'   => array( Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4 ), 300, 1000, '', false ) ),
			'bearings' => array( Builds::bearings() ),
			'hardware' => array( Builds::bolts() ),
			'grip'     => array( Builds::grip( '9', 701 ) ),
		);
		$presets         = array(
			'schema_version' => 1,
			'presets'        => array(
				array(
					'name'  => 'Street <i>',
					'picks' => array(
						'deck'     => 100,
						'trucks'   => 149,
						'wheels'   => 300,
						'bearings' => 500,
						'hardware' => 600,
						'grip'     => 700,
					),
				),
			),
		);
		$kits->add( 5, KitDocuments::kit( presets: $presets ), $parts, 'Complete & co' );

		$this->as( 'shop_manager' );
		$page = self::checked( $this->screen( $kits ) );
		self::assertStringNotContainsString( 'The shop has no kit yet.', $page );
		self::assertStringContainsString( '<td>Complete &amp; co</td>', $page );
		self::assertStringContainsString( 'Deck width is missing, so the part is not offered in the slot &quot;deck&quot;.', $page );
		self::assertStringContainsString( 'Deck hole pattern is not filled in, so the builder assumes the usual one.', $page );
		self::assertStringContainsString( 'The slot &quot;wheels&quot; has nothing in stock.', $page );
		self::assertStringContainsString( 'The slot &quot;risers&quot; has no parts.', $page );
		self::assertStringContainsString( 'The preset &quot;Street &lt;i&gt;&quot; names it for the slot &quot;grip&quot;, and it is not one of that slot&#039;s parts now.', $page );
		self::assertStringContainsString( 'The preset &quot;Street &lt;i&gt;&quot; names it for the slot &quot;wheels&quot;, and it is sold out.', $page );
		self::assertStringContainsString( 'The preset &quot;Street &lt;i&gt;&quot; names it for the slot &quot;deck&quot;, and it no longer fits the preset&#039;s other parts.', $page );
		// A part that is not a product of this shop is named by its ID, with no link.
		self::assertMatchesRegularExpression( '#<td>\s*Product 100\s*</td>#', $page );
	}

	public function test_a_long_list_is_cut_and_says_how_many_more_there_are(): void {
		for ( $at = 0; $at < CheckScreen::SHOWN + 2; $at++ ) {
			$post = self::factory()->post->create( array( 'post_type' => 'product' ) );
			update_post_meta(
				$post,
				Options::META_SPEC_TABLE,
				array(
					'table' => 999999,
					'size'  => '1',
				)
			);
		}
		$this->as( 'shop_manager' );
		$page = self::checked( $this->screen() );
		self::assertStringContainsString( 'The check found ' . number_format_i18n( CheckScreen::SHOWN + 2 ) . ' things to fix.', $page );
		self::assertSame( CheckScreen::SHOWN + 1, substr_count( $page, '<tr>' ) );
		self::assertStringContainsString( 'And 2 more.', $page );
	}

	public function test_a_manager_starts_the_reindex_again_from_the_page_and_nobody_else_does(): void {
		$sent = static function ( CheckScreen $screen, bool $nonce = true ): string {
			$_POST['cbuild_action'] = 'reindex';
			unset( $_REQUEST['_wpnonce'] );
			if ( $nonce ) {
				$_REQUEST['_wpnonce'] = wp_create_nonce( CheckScreen::NONCE );
			}
			$screen->handle();
			return self::drawn( $screen );
		};
		// A reindex that stopped for good: the check says where to start it again, and the page has the button.
		$this->service( ReindexStore::class )->save( ( new ReindexState( ReindexState::FAILED, 0, 0, 1, null, false, ReindexState::MAX_FAILURES, 'gone' ) )->toArray() );
		$this->as( 'shop_manager' );
		$page = self::checked( $this->screen() );
		self::assertStringContainsString( 'Start it again with &quot;Read every part again&quot; on this page', $page );
		self::assertStringContainsString( '<input type="hidden" name="cbuild_action" value="reindex" />', $page );
		self::assertStringContainsString( 'id="cbuild-reindex" class="button" value="Read every part again"', $page );
		self::assertStringNotContainsString( 'Every part is being read again in the background', $page );

		$this->as( 'editor' );
		self::assertSame( '', $sent( $this->screen() ) );
		self::assertSame( ReindexState::FAILED, get_option( Options::REINDEX )['phase'], 'not for who cannot manage the shop' );

		$this->as( 'shop_manager' );
		try {
			$sent( $this->screen(), false );
			self::fail( 'The reindex was asked for without its nonce.' );
		} catch ( \WPDieException $stopped ) {
			self::assertSame( ReindexState::FAILED, get_option( Options::REINDEX )['phase'] );
		}

		$page = $sent( $this->screen() );
		self::assertStringContainsString( '<div class="notice notice-success inline" role="status"><p>Every part is being read again in the background', $page );
		self::assertStringNotContainsString( 'What it found', $page, 'the check did not run' );
		self::assertSame( 2, get_option( Options::REINDEX )['run'] );
		self::assertSame( ReindexState::ROWS, get_option( Options::REINDEX )['phase'] );
		self::assertCount( 1, self::waiting( Options::JOB_REINDEX ) );
		self::work();
		self::assertSame( ReindexState::DONE, get_option( Options::REINDEX )['phase'] );
	}

	public function test_who_cannot_manage_the_shop_runs_nothing_and_sees_nothing_and_the_form_needs_its_nonce(): void {
		$this->as( 'editor' );
		$screen = $this->screen();
		self::assertSame( '', self::checked( $screen ) );

		$this->as( 'shop_manager' );
		try {
			self::checked( $this->screen(), false );
			self::fail( 'The check ran without its nonce.' );
		} catch ( \WPDieException $stopped ) {
			self::assertStringContainsString( 'expired', $stopped->getMessage() );
		}
		// Another action sent to the tab runs nothing.
		$_POST['cbuild_action'] = 'export';
		$_REQUEST['_wpnonce']   = wp_create_nonce( CheckScreen::NONCE );
		$screen                 = $this->screen();
		self::assertNull( $screen->handle() );
		self::assertStringNotContainsString( 'role="status"', self::drawn( $screen ) );
		self::assertSame( array(), $this->service( Kits::class )->all() );
		self::assertSame( array(), $this->service( Kits::class )->candidates( 1, 'deck' ) );
	}
}
