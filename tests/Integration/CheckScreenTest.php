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
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\LookFact;
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
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryPartLooks;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

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
		$check  = ( new CatalogueCheck( $kits, $this->service( PartCatalogue::class ), $this->service( SpecIndexer::class ), $this->service( ReindexStore::class ), $this->service( ImportStore::class ), $this->service( Queue::class ) ) )->withLayers( $this->service( LayerImages::class ) );
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

	/**
	 * A simple product, with this layer image or none.
	 *
	 * @param int $layer An attachment's ID, or 0.
	 */
	private static function part( int $layer ): int {
		$product = new \WC_Product_Simple();
		$product->set_regular_price( '60' );
		if ( $layer > 0 ) {
			$product->update_meta_data( Options::META_LAYER, (string) $layer );
		}
		return $product->save();
	}

	public function test_a_drawn_slots_part_with_no_layer_image_is_listed_with_a_link_to_its_layer_field(): void {
		$drawn   = self::part( LayerFiles::attachment( 420, 1626 ) );
		$bare    = self::part( 0 );
		$gone    = LayerFiles::attachment( 420, 1626 );
		$deleted = self::part( $gone );
		wp_delete_attachment( $gone, true );

		$parent = new \WC_Product_Variable();
		$parent->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 70, 110 ) );
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '30' );
		$variation->save();

		$kits = new InMemoryKits();
		$kits->add(
			5,
			KitDocuments::kit(),
			array(
				'deck'     => array( Parts::of( array( 'deck_width' => '8.25' ), $drawn ), Parts::of( array( 'deck_width' => '8.5' ), $bare ) ),
				'trucks'   => array( Parts::of( array( 'axle_width' => '8.25' ), $deleted ) ),
				'wheels'   => array( Parts::of( array( 'wheel_diameter' => '54', 'set_size' => 4 ), $variation->get_id() ) ),
				'bearings' => array( Builds::bearings() ),
				'hardware' => array( Builds::bolts() ),
			),
			'Complete'
		);

		$this->as( 'shop_manager' );
		$page = self::checked( $this->screen( $kits ) );
		self::assertStringContainsString( 'It has no layer image, so the picture shows its labelled photo in the slot &quot;deck&quot;. Add one on its Part specs, under Picture.', $page );
		self::assertStringContainsString( 'It has no layer image, so the picture shows its labelled photo in the slot &quot;trucks&quot;.', $page, 'an image no longer in the Media Library is none' );
		self::assertStringContainsString( esc_url( (string) get_edit_post_link( $bare, 'raw' ) . '#cbuild-spec-layer' ), $page );
		self::assertSame( 2, substr_count( $page, 'It has no layer image' ), 'a part with its own image, and a variation with its parent\'s, are not listed; bearings and hardware are not drawn' );
		self::assertStringNotContainsString( 'slot &quot;wheels&quot;. Add one', $page );
		self::assertGreaterThan( strrpos( $page, 'The slot &quot;risers&quot; has no parts.' ), strpos( $page, 'It has no layer image' ), 'listed after what keeps a part from being offered' );
		// Cowork's M7 review, F4 (D-211): in a section of their own, counted by part, and not among the things to fix.
		self::assertGreaterThan( strpos( $page, '<h3 id="cbuild-found">' ), strpos( $page, '<h3 id="cbuild-unlaid">Parts with no layer image</h3>' ) );
		self::assertStringContainsString( '2 parts the picture draws have no layer image. Each is still offered', $page );
		self::assertStringContainsString( 'aria-labelledby="cbuild-unlaid"', $page );
		self::assertSame( 0, substr_count( substr( $page, 0, (int) strpos( $page, 'id="cbuild-unlaid"' ) ), 'It has no layer image' ), 'none of them among what it found to fix' );
	}

	// Spec (configurator): "The Catalogue check says which variations share a photo, and so share a card" (D-281).
	public function test_variations_that_share_a_card_are_said_in_a_list_of_their_own_and_not_as_a_fix(): void {
		$kits  = new InMemoryKits();
		$looks = new InMemoryPartLooks();
		$kits->add( 5, KitDocuments::kit(), array( 'deck' => array( Builds::deck( '8.0', 11 ), Builds::deck( '8.25', 12 ) ) ) );
		$looks->facts = array(
			11 => new LookFact( 10, 1, 0, 'Board', array(), array(), false, 0, 0 ),
			12 => new LookFact( 10, 1, 0, 'Board', array(), array(), false, 0, 0 ),
		);
		$check = ( new CatalogueCheck( $kits, $this->service( PartCatalogue::class ), $this->service( SpecIndexer::class ), $this->service( ReindexStore::class ), $this->service( ImportStore::class ), $this->service( Queue::class ) ) )->withLooks( $looks );

		$this->as( 'shop_manager' );
		$page = self::checked( new CheckScreen( $check, $kits, $this->service( Reindex::class ) ) );
		self::assertStringContainsString( '<h3 id="cbuild-shared">Variations that share a card</h3>', $page );
		self::assertStringContainsString( 'Its variations 11 and 12 share a photo, so the builder shows them as one card in the slot &quot;deck&quot; and asks for the rest after the pick.', $page );
		self::assertStringNotContainsString( 'thing to fix', $page, 'not counted among the things to fix' );
		self::assertSame( 0, substr_count( substr( $page, 0, (int) strpos( $page, 'id="cbuild-shared"' ) ), 'share a photo' ) );
	}

	// #116: parts the shop keeps out are listed apart, not as a fix, each with a link to the box that keeps it out.
	public function test_parts_kept_out_are_said_in_a_list_of_their_own_with_a_link_to_their_box(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Kept deck' );
		$product->set_regular_price( '50' );
		$product->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$product->save();
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Kept trucks' );
		$parent->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$parent->save();
		$variation = new \WC_Product_Variation();
		$variation->set_parent_id( $parent->get_id() );
		$variation->set_regular_price( '30' );
		$variation->save();
		$kits  = new InMemoryKits();
		$check = ( new CatalogueCheck( $kits, $this->service( PartCatalogue::class ), $this->service( SpecIndexer::class ), $this->service( ReindexStore::class ), $this->service( ImportStore::class ), $this->service( Queue::class ) ) )->withKeptOut( $this->service( \CreativeBranding\SkateboardBuilder\Port\PartsKeptOut::class ) );

		$this->as( 'shop_manager' );
		$page = self::checked( new CheckScreen( $check, $kits, $this->service( Reindex::class ) ) );
		self::assertStringContainsString( '<h3 id="cbuild-kept">Parts kept out of the builder</h3>', $page );
		self::assertStringContainsString( 'The check found nothing to fix.', $page );
		self::assertDoesNotMatchRegularExpression( '/found \d+ things? to fix/', $page, 'not counted among the things to fix' );
		self::assertStringContainsString( 'post=' . $product->get_id() . '&#038;action=edit#cbuild-spec-not-offered">Kept deck</a>', $page );
		self::assertStringContainsString( 'post=' . $parent->get_id() . '&#038;action=edit#cbuild-spec-not-offered">', $page, 'the variation\'s link is to its product\'s box' );
		self::assertStringContainsString( 'so no kit offers it or the product&#039;s other variations.', $page );
		self::assertSame( 0, substr_count( substr( $page, 0, (int) strpos( $page, 'id="cbuild-kept"' ) ), 'Not offered in the builder is ticked' ) );
		// A part kept out is in no kit, so its list has no Kit column of empty cells.
		self::assertStringNotContainsString( '<th scope="col">Kit</th>', substr( $page, (int) strpos( $page, 'id="cbuild-kept"' ) ), 'no Kit column' );
	}

	// The auditor's M12 minor 6: with parts kept out and other lists below, the notice does not say there is nothing to
	// fix above a list of things to look at, and says once what it says.
	public function test_parts_kept_out_beside_another_list_leave_the_notice_that_list_reads_with(): void {
		$product = new \WC_Product_Simple();
		$product->set_name( 'Kept deck' );
		$product->set_regular_price( '50' );
		$product->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		$product->save();
		$kits  = new InMemoryKits();
		$looks = new InMemoryPartLooks();
		// A kit of the deck alone, so the shared card is all the check finds.
		$deck = array(
			'schema_version' => 1,
			'slots'          => array( array_values( KitDocuments::slots()['slots'] )[0] ),
		);
		$kits->add( 5, KitDocuments::kit( $deck ), array( 'deck' => array( Builds::deck( '8.0', 11 ), Builds::deck( '8.25', 12 ) ) ) );
		$looks->facts = array(
			11 => new LookFact( 10, 1, 0, 'Board', array(), array(), false, 0, 0 ),
			12 => new LookFact( 10, 1, 0, 'Board', array(), array(), false, 0, 0 ),
		);
		$check = ( new CatalogueCheck( $kits, $this->service( PartCatalogue::class ), $this->service( SpecIndexer::class ), $this->service( ReindexStore::class ), $this->service( ImportStore::class ), $this->service( Queue::class ) ) )->withLooks( $looks )->withKeptOut( $this->service( \CreativeBranding\SkateboardBuilder\Port\PartsKeptOut::class ) );

		$this->as( 'shop_manager' );
		$page = self::checked( new CheckScreen( $check, $kits, $this->service( Reindex::class ) ) );
		self::assertStringContainsString( '<h3 id="cbuild-shared">Variations that share a card</h3>', $page );
		self::assertStringContainsString( '<h3 id="cbuild-kept">Parts kept out of the builder</h3>', $page );
		self::assertStringNotContainsString( 'The check found nothing to fix.', $page );
		self::assertSame( 1, substr_count( $page, 'Nothing the check found keeps a part out of the builder.' ), (string) preg_replace( '/\s+/', ' ', substr( $page, (int) strpos( $page, 'notice' ) - 20, 600 ) ) );
	}

	public function test_parts_with_no_layer_image_have_their_own_list_cut_at_its_own_length(): void {
		// Cowork's M7 review, F4: on a large catalogue, the layer lines are reached however many other findings there are.
		$decks = array();
		for ( $id = 900001; $id <= 900000 + CheckScreen::SHOWN + 2; $id++ ) {
			$decks[] = Parts::of( array( 'deck_width' => '8.25' ), $id );
		}
		$kits = new InMemoryKits();
		$kits->add(
			5,
			KitDocuments::kit(),
			array(
				'deck'     => $decks,
				'trucks'   => array( Builds::truck( '149' ) ),
				'wheels'   => array( Builds::wheel( '54' ) ),
				'bearings' => array( Builds::bearings() ),
				'hardware' => array( Builds::bolts() ),
				'grip'     => array( Builds::grip() ),
				'risers'   => array( Builds::riser( '0.125' ) ),
			),
			'Complete'
		);
		$this->as( 'shop_manager' );
		$page    = self::checked( $this->screen( $kits ) );
		$section = substr( $page, (int) strpos( $page, 'id="cbuild-unlaid"' ) );
		self::assertStringContainsString( number_format_i18n( CheckScreen::SHOWN + 2 + 3 ) . ' parts the picture draws have no layer image.', $section, 'the decks, then the trucks, wheels and grip' );
		self::assertStringContainsString( 'And 5 more.', $section );
		self::assertSame( CheckScreen::SHOWN, substr_count( $section, 'It has no layer image' ) );
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

	// The M9 audit's minor 11: the findings' parts and their parents are read together, so the table asks as many
	// queries for twelve rows as for two.
	public function test_the_findings_table_reads_its_parts_together(): void {
		$parent = new \WC_Product_Variable();
		$parent->set_name( 'Katie' );
		$parent->save();
		$parts = array();
		for ( $n = 0; $n < 12; $n++ ) {
			$part = 0 === $n % 2 ? new \WC_Product_Simple() : new \WC_Product_Variation();
			if ( $part instanceof \WC_Product_Variation ) {
				$part->set_parent_id( $parent->get_id() );
			} else {
				$part->set_name( 'Deck ' . $n );
			}
			$part->set_sku( 'CHK-' . $n );
			$parts[] = $part->save();
		}
		$rows    = new \ReflectionMethod( CheckScreen::class, 'rows' );
		$screen  = $this->screen();
		$queries = static function ( array $ids ) use ( $rows, $screen ): int {
			global $wpdb;
			wp_cache_flush();
			$before = $wpdb->num_queries;
			$found  = $rows->invoke( $screen, array_map( static fn( int $id ): \CreativeBranding\SkateboardBuilder\Service\CheckFinding => new \CreativeBranding\SkateboardBuilder\Service\CheckFinding( CatalogueCheck::SPEC_MISSING, $id, 0, 'deck', 'deck_width' ), $ids ), array() );
			self::assertCount( count( $ids ), $found );
			return $wpdb->num_queries - $before;
		};
		self::assertSame( $queries( array_slice( $parts, 0, 2 ) ), $queries( $parts ) );
	}
}
