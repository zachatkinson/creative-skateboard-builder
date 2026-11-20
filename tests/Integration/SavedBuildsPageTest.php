<?php
/**
 * My builds and Save build: the forms, their nonces and their owner.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuilderPage;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\MyBuildsView;
use CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeClock;

/**
 * Roadmap, milestone 8: "saved builds under My builds: save, rename, delete and open, with nonces; owner-only;
 * re-checked on opening", and "IDOR tests, with another customer's ID and a guessed UUID" (D-217).
 */
final class SavedBuildsPageTest extends CartTestCase {

	private int $ana;

	private int $ben;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::builds() ) );
		$this->ana = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => 'shopper+1@example.com' ) );
		$this->ben = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => 'shopper+2@example.com' ) );
		$this->service( SavedBuildHooks::class )->register();
	}

	public function tear_down(): void {
		$_POST = array();
		$_GET  = array();
		unset( $_SERVER['REQUEST_METHOD'] );
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Post a form of the plugin's, as the signed-in customer, and answer it.
	 *
	 * @param array<string, mixed> $fields The form's fields.
	 */
	private function post( array $fields ): ?string {
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = $fields;
		wc_clear_notices();
		return $this->service( SavedBuildHooks::class )->answer();
	}

	/**
	 * The review's Save build fields for the usual build.
	 *
	 * @return array<string, mixed>
	 */
	private function saveFields( string $nonce, string $name = '', string $uuid = '' ): array {
		return array(
			SavedBuildHooks::FIELD => SavedBuildHooks::SAVE,
			SavedBuildHooks::NONCE => $nonce,
			SavedBuildHooks::NAME  => $name,
			SavedBuildHooks::UUID  => $uuid,
			SavedBuildHooks::KIT   => (string) $this->kit,
			BuildLink::PARAM       => array(
				'picks'    => array_map( 'strval', $this->picks() ),
				'assembly' => '1',
			),
		);
	}

	/**
	 * The notices of a type, as text.
	 *
	 * @return list<string>
	 */
	private static function notices( string $type = 'success' ): array {
		return array_map( static fn( array $notice ): string => wp_strip_all_tags( (string) $notice['notice'] ), wc_get_notices( $type ) );
	}

	public function test_my_builds_is_an_account_endpoint_with_its_menu_item_before_log_out(): void {
		self::assertSame( SavedBuildHooks::ENDPOINT, WC()->query->get_query_vars()['my-builds'] ?? null );
		$items = wc_get_account_menu_items();
		$keys  = array_keys( $items );
		self::assertSame( 'My builds', $items['my-builds'] ?? null );
		self::assertSame( array_search( 'customer-logout', $keys, true ) - 1, array_search( 'my-builds', $keys, true ) );
		self::assertStringContainsString( 'my-builds', SavedBuildHooks::url() );
	}

	public function test_a_signed_in_customer_saves_the_review_with_its_nonce_and_lands_on_my_builds(): void {
		wp_set_current_user( $this->ana );
		$to = $this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Street <b>board</b>' ) );
		self::assertSame( SavedBuildHooks::url(), $to );
		self::assertSame( array( 'The build is saved. It is listed below.' ), self::notices(), implode( ' | ', self::notices( 'error' ) ) );
		$saved = $this->service( SavedBuilds::class )->of( $this->ana );
		self::assertCount( 1, $saved );
		self::assertSame( 'Street board', $saved[0]->name, 'the name sanitised as text' );
		self::assertSame( $this->picks(), $saved[0]->picks );
		self::assertTrue( $saved[0]->assembly );
	}

	/**
	 * CI run 44119225891: each Save build in the browser test put the build in the cart as well, seven lines a save. The
	 * review's Save build fields carried the kit as `add-to-cart`, which WooCommerce's form handler adds to the cart on
	 * any request (includes/class-wc-form-handler.php, add_to_cart_action on `wp_loaded`).
	 */
	/**
	 * A renderer of its own, as a request has: the container's draws once.
	 */
	private function builder(): BuilderPage {
		return new BuilderPage( $this->service( Builder::class ), $this->service( Kits::class ), $this->service( Logger::class ) );
	}

	// Spec (configurator): A saved build that has changed (D-254): "Any of these opens a 'Your build has changed'
	// panel before the review, with focus on its heading", and "Without JavaScript, the panel is a step of its own".
	public function test_a_saved_build_whose_price_changed_opens_on_your_build_has_changed_with_focus_on_it(): void {
		wp_set_current_user( $this->ana );
		$saved = $this->service( SavedBuilds::class )->save( $this->ana, $this->kit, $this->picks(), true, '' );
		$uuid  = $saved['uuid'];
		self::assertNotSame( '', $uuid );
		$link = BuildLink::read( array_map( 'strval', $this->picks() ) + array( 'step' => 'review', '_saved' => $uuid ) );
		$base = (string) get_permalink( $this->kit );
		self::assertStringNotContainsString( 'Your build has changed', $this->builder()->render( $this->kit, $link, $base ), 'nothing has changed yet' );

		$deck = wc_get_product( $this->picks()['deck'] );
		self::assertInstanceOf( \WC_Product::class, $deck );
		$deck->set_regular_price( '99' );
		$deck->save();
		$html = $this->builder()->render( $this->kit, $link, $base );
		self::assertMatchesRegularExpression( '/<h3 autofocus class="cbuild-changes__heading"[^>]*>Your build has changed<\/h3>/', $html );
		self::assertStringContainsString( ' when you saved, ', $html, 'the new price, then and now' );
		self::assertStringContainsString( '>Choose myself</button>', $html );

		wp_set_current_user( $this->ben );
		self::assertStringNotContainsString( 'Your build has changed', $this->builder()->render( $this->kit, $link, $base ), 'checked only for its own customer' );
	}

	public function test_save_build_sends_the_review_as_the_page_draws_it_and_leaves_the_cart_alone(): void {
		wp_set_current_user( $this->ana );
		$_GET[ BuildLink::PARAM ] = array_map( 'strval', $this->picks() ) + array( 'step' => 'review' );
		$this->service( BuilderPage::class )->render( $this->kit, BuildLink::read( $_GET[ BuildLink::PARAM ] ), (string) get_permalink( $this->kit ) );
		$fields = array();
		foreach ( wp_interactivity_state( BuilderPage::STORE )['view']['review']['saveFields'] as $field ) {
			$fields[] = array( $field['name'], $field['value'] );
		}
		self::assertNotContains( 'add-to-cart', array_column( $fields, 0 ), 'Save build carries nothing of the cart\'s (D-217).' );
		parse_str( implode( '&', array_map( static fn( array $field ): string => rawurlencode( $field[0] ) . '=' . rawurlencode( $field[1] ), $fields ) ), $sent );
		$sent += array(
			SavedBuildHooks::FIELD => SavedBuildHooks::SAVE,
			SavedBuildHooks::NONCE => wp_create_nonce( SavedBuildHooks::SAVE ),
			SavedBuildHooks::NAME  => 'Kept, not bought',
		);
		WC()->cart->empty_cart();
		$_SERVER['REQUEST_METHOD'] = 'POST';
		$_POST                     = $sent;
		$_REQUEST                  = $sent;
		\WC_Form_Handler::add_to_cart_action();
		self::assertSame( 0, WC()->cart->get_cart_contents_count(), 'WooCommerce added nothing to the cart' );
		self::assertSame( SavedBuildHooks::url(), $this->post( $sent ) );
		self::assertSame( array( 'The build is saved. It is listed below.' ), self::notices(), implode( ' | ', self::notices( 'error' ) ) );
		$saved = $this->service( SavedBuilds::class )->of( $this->ana );
		self::assertCount( 1, $saved );
		self::assertSame( $this->kit, $saved[0]->kit );
		self::assertSame( $this->picks(), $saved[0]->picks );
		$_REQUEST = array();
	}

	public function test_a_form_without_its_nonce_or_from_no_one_changes_nothing(): void {
		wp_set_current_user( $this->ana );
		self::assertSame( SavedBuildHooks::url(), $this->post( $this->saveFields( 'stale' ) ) );
		self::assertSame( array( 'That form has expired. Please try again.' ), self::notices( 'error' ) );
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ) );

		wp_set_current_user( 0 );
		self::assertNull( $this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ) ) ), 'signed out: no answer, so WooCommerce\'s page shows its sign-in form' );
		$_SERVER['REQUEST_METHOD'] = 'GET';
		self::assertNull( $this->service( SavedBuildHooks::class )->answer(), 'a GET is no form' );
	}

	public function test_another_customer_cannot_rename_or_delete_a_build_by_its_uuid_or_a_guessed_one(): void {
		wp_set_current_user( $this->ana );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Ana\'s' ) );
		$hers = $this->service( SavedBuilds::class )->of( $this->ana )[0]->uuid;

		wp_set_current_user( $this->ben );
		foreach ( array( SavedBuildHooks::RENAME, SavedBuildHooks::DELETE ) as $action ) {
			foreach ( array( $hers, '00000000-0000-4000-8000-000000000000' ) as $uuid ) {
				// His own valid nonce for her UUID: the nonce passes, the owner check does not.
				$this->post(
					array(
						SavedBuildHooks::FIELD => $action,
						SavedBuildHooks::UUID  => $uuid,
						SavedBuildHooks::NAME  => 'Taken',
						SavedBuildHooks::NONCE => wp_create_nonce( $action . '_' . $uuid ),
					)
				);
				self::assertSame( array( 'That build is not among your saved builds.' ), self::notices( 'error' ), $action . ' ' . $uuid );
			}
		}
		// Saved from a page opened with her UUID: a new build of his.
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Ben\'s', $hers ) );
		self::assertSame( array( 'The build is saved. It is listed below.' ), self::notices() );
		self::assertSame( 'Ana\'s', $this->service( SavedBuilds::class )->find( $this->ana, $hers )?->name );
		self::assertCount( 1, $this->service( SavedBuilds::class )->of( $this->ben ) );
		self::assertNotSame( $hers, $this->service( SavedBuilds::class )->of( $this->ben )[0]->uuid );
	}

	public function test_the_owner_renames_and_deletes_with_each_builds_own_nonce(): void {
		wp_set_current_user( $this->ana );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ) ) );
		$uuid = $this->service( SavedBuilds::class )->of( $this->ana )[0]->uuid;
		$this->post(
			array(
				SavedBuildHooks::FIELD => SavedBuildHooks::RENAME,
				SavedBuildHooks::UUID  => $uuid,
				SavedBuildHooks::NAME  => 'Cruiser',
				SavedBuildHooks::NONCE => wp_create_nonce( SavedBuildHooks::DELETE . '_' . $uuid ),
			)
		);
		self::assertSame( array( 'That form has expired. Please try again.' ), self::notices( 'error' ), 'Delete\'s nonce does not rename' );
		$this->post(
			array(
				SavedBuildHooks::FIELD => SavedBuildHooks::RENAME,
				SavedBuildHooks::UUID  => $uuid,
				SavedBuildHooks::NAME  => 'Cruiser',
				SavedBuildHooks::NONCE => wp_create_nonce( SavedBuildHooks::RENAME . '_' . $uuid ),
			)
		);
		self::assertSame( array( 'The build is renamed.' ), self::notices() );
		self::assertSame( 'Cruiser', $this->service( SavedBuilds::class )->find( $this->ana, $uuid )?->name );
		$this->post(
			array(
				SavedBuildHooks::FIELD => SavedBuildHooks::DELETE,
				SavedBuildHooks::UUID  => $uuid,
				SavedBuildHooks::NONCE => wp_create_nonce( SavedBuildHooks::DELETE . '_' . $uuid ),
			)
		);
		self::assertSame( array( 'The build is deleted.' ), self::notices() );
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ) );
	}

	/**
	 * Cowork's 0.8.0 review, F3 (#55; D-231): Delete has an Undo, a form under its notice that saves the build back,
	 * with its name, picks and assembly, checked and priced again as any save. It is offered once, the session keeps
	 * the build only until then, and its nonce is the deleted build's.
	 */
	/**
	 * Delete Ana's one build and give back the Undo form's fields, as the page after the delete draws them.
	 *
	 * @param string $name The build's name.
	 * @return array{0: \CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild, 1: array<string, mixed>, 2: string}
	 */
	private function deleteAndOfferUndo( string $name ): array {
		WC()->initialize_session();
		wp_set_current_user( $this->ana );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), $name ) );
		$build = $this->service( SavedBuilds::class )->of( $this->ana )[0];
		$this->post(
			array(
				SavedBuildHooks::FIELD => SavedBuildHooks::DELETE,
				SavedBuildHooks::UUID  => $build->uuid,
				SavedBuildHooks::NONCE => wp_create_nonce( SavedBuildHooks::DELETE . '_' . $build->uuid ),
			)
		);
		self::assertSame( array( 'The build is deleted.' ), self::notices() );
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ), 'gone from the table' );
		$page = $this->page();
		self::assertSame( 1, preg_match( '#<form class="cbuild-my-builds__undo"[^>]*>(.*?)</form>#s', $page, $form ), 'Undo offered' );
		preg_match_all( '#<input type="hidden" name="([^"]+)" value="([^"]*)">#', $form[1], $inputs, PREG_SET_ORDER );
		$fields = array();
		foreach ( $inputs as $input ) {
			$fields[ html_entity_decode( $input[1] ) ] = html_entity_decode( $input[2] );
		}
		return array( $build, $fields, $page );
	}

	public function test_a_deleted_build_comes_back_with_undo_as_it_was_once_and_by_its_own_nonce(): void {
		[ $build, $fields, $page ] = $this->deleteAndOfferUndo( 'Street setup' );
		self::assertStringContainsString( 'aria-label="Undo deleting Street setup">Undo</button>', $page );
		self::assertSame( array( SavedBuildHooks::FIELD, SavedBuildHooks::UUID, SavedBuildHooks::NONCE ), array_keys( $fields ), 'the form names the build; the build itself is the server\'s copy' );

		// Another customer, with the form as it was: their nonce is not the deleted build's.
		wp_set_current_user( $this->ben );
		$this->post( $fields );
		self::assertSame( array( 'That form has expired. Please try again.' ), self::notices( 'error' ) );
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ben ) );
		// No nonce at all.
		wp_set_current_user( $this->ana );
		$this->post( array( SavedBuildHooks::NONCE => '' ) + $fields );
		self::assertSame( array( 'That form has expired. Please try again.' ), self::notices( 'error' ) );
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ) );

		$to = $this->post( $fields );
		self::assertSame( array( 'The build is back in My builds.' ), self::notices() );
		self::assertSame( SavedBuildHooks::url(), $to );
		$back = $this->service( SavedBuilds::class )->of( $this->ana );
		self::assertCount( 1, $back );
		self::assertEquals( $build, $back[0], 'the same build: its UUID, name, picks, assembly and dates' );

		// Sent again, by Back or a second press: nothing more is made.
		$this->post( $fields );
		self::assertSame( array( 'The build is back in My builds.' ), self::notices() );
		self::assertCount( 1, $this->service( SavedBuilds::class )->of( $this->ana ) );
		self::assertStringNotContainsString( 'cbuild-my-builds__undo', $this->page(), 'offered once' );
	}

	// Cowork's review of 0.9.0, F1 (#66): a theme can move WooCommerce's notice far from Undo, so the form says what it
	// undoes. F2 (#67): the copy is kept 15 minutes from the Delete, not until the session ends (D-275).
	public function test_undo_says_which_build_it_brings_back_and_is_offered_for_fifteen_minutes(): void {
		$clock = new FakeClock();
		$hooks = $this->service( SavedBuildHooks::class );
		$this->container()->set(
			SavedBuildHooks::class,
			new SavedBuildHooks(
				fn(): SavedBuilds => $this->service( SavedBuilds::class ),
				fn(): MyBuildsView => $this->service( MyBuildsView::class ),
				$clock
			)
		);
		try {
			[ , $fields, $page ] = $this->deleteAndOfferUndo( 'Street setup' );
			self::assertStringContainsString( 'Street setup is deleted. You can undo this for 15 minutes.', wp_strip_all_tags( $page ) );
			self::assertStringContainsString( 'aria-label="Undo deleting Street setup">Undo</button>', $page, 'the button keeps its name' );
			$clock->advance( 15 * 60 - 1 );
			$this->post( $fields );
			self::assertSame( array( 'The build is back in My builds.' ), self::notices(), 'within the 15 minutes' );

			$this->service( SavedBuilds::class )->delete( $this->ana, $this->service( SavedBuilds::class )->of( $this->ana )[0]->uuid );
			[ , $fields ] = $this->deleteAndOfferUndo( 'Late setup' );
			$clock->advance( 15 * 60 + 1 );
			$this->post( $fields );
			self::assertSame( array( 'That build can no longer be brought back.' ), self::notices( 'error' ), 'not after them' );
			self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ) );
			// The auditor's 1.0.0 should-fix P2: and the copy is out of the session, read past its 15 minutes.
			self::assertNull( WC()->session->get( 'cbuild_deleted' ), 'the build is not kept past its Undo' );
		} finally {
			$this->container()->set( SavedBuildHooks::class, $hooks );
		}
	}

	public function test_undo_brings_back_a_build_whose_part_went_since_as_it_was(): void {
		[ $build, $fields ] = $this->deleteAndOfferUndo( 'Old wheels' );
		wp_trash_post( $this->parts['wheels'] );
		$this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->forget();
		$this->post( $fields );
		self::assertSame( array( 'The build is back in My builds.' ), self::notices() );
		self::assertSame( array(), self::notices( 'error' ) );
		$back = $this->service( SavedBuilds::class )->of( $this->ana );
		self::assertCount( 1, $back );
		self::assertSame( $build->picks, $back[0]->picks, 'the gone part kept, for Open to name' );
		self::assertStringContainsString( 'A part has changed since you saved it. Open the build to see which.', $this->page() );
	}

	public function test_undo_names_an_unnamed_build_as_the_list_did_and_is_offered_on_the_next_view_alone(): void {
		[ , $fields, $page ] = $this->deleteAndOfferUndo( '' );
		self::assertSame( 1, preg_match( '#aria-label="Undo deleting ([^"]+)">Undo</button>#', $page, $label ) );
		self::assertNotSame( '', trim( html_entity_decode( $label[1] ) ), 'named as My builds named it' );
		// A later view: the offer has passed, and the form sent from the old page says so.
		self::assertStringNotContainsString( 'cbuild-my-builds__undo', $this->page() );
		$this->post( $fields );
		self::assertSame( array( 'That build can no longer be brought back.' ), self::notices( 'error' ) );
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ) );
	}

	public function test_undo_on_a_full_account_says_so_and_keeps_nothing_over_the_most(): void {
		[ , $fields ] = $this->deleteAndOfferUndo( 'One too many' );
		$store = $this->service( \CreativeBranding\SkateboardBuilder\Port\SavedBuildStore::class );
		$now   = new \DateTimeImmutable( '2026-11-18 03:00:00', new \DateTimeZone( 'UTC' ) );
		for ( $i = 0; $i < SavedBuilds::MOST; $i++ ) {
			$store->put( new \CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild( sprintf( '00000000-0000-4000-8000-%012d', $i ), $this->ana, $this->kit, $this->picks(), true, 'Full ' . $i, $now, $now ) );
		}
		$this->post( $fields );
		self::assertSame( array( 'You have 100 saved builds, the most kept. Delete one to save another.' ), self::notices( 'error' ) );
		self::assertCount( SavedBuilds::MOST, $this->service( SavedBuilds::class )->of( $this->ana ) );
	}

	public function test_my_builds_lists_each_build_with_its_total_today_and_open_carries_its_uuid_for_its_owner_alone(): void {
		wp_set_current_user( $this->ana );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Mine' ) );
		$uuid = $this->service( SavedBuilds::class )->of( $this->ana )[0]->uuid;

		ob_start();
		$this->service( SavedBuildHooks::class )->page();
		$html = (string) ob_get_clean();
		$dom  = new \DOMXPath( self::html( $html ) );
		self::assertSame( 'Mine', trim( (string) $dom->query( '//*[contains(@class,"cbuild-my-builds__name")]' )->item( 0 )?->textContent ) );
		$open = html_entity_decode( (string) $dom->query( '//a[contains(@class,"cbuild-my-builds__open")]/@href' )->item( 0 )?->nodeValue );
		self::assertStringContainsString( MyBuildsView::PARAM . '=' . $uuid, $open );
		self::assertStringContainsString( 'cbuild%5Bstep%5D=review', $open );
		self::assertSame( 2, $dom->query( '//form[@method="post"]' )->length, 'Rename and Delete' );
		$ids = array_map( static fn( \DOMNode $node ): string => (string) $node->nodeValue, iterator_to_array( $dom->query( '//@id' ) ) );
		self::assertSame( $ids, array_values( array_unique( $ids ) ), 'no id twice' );
		self::assertStringNotContainsString( 'A part has changed', $html );

		// Opened: the page's Save build updates it, for her alone.
		$_GET[ MyBuildsView::PARAM ] = $uuid;
		self::assertSame( $uuid, $this->service( BuilderPage::class )->account()['saved'] );
		wp_set_current_user( $this->ben );
		self::assertSame( '', $this->service( BuilderPage::class )->account()['saved'], 'another customer\'s page ignores it' );
		wp_set_current_user( 0 );
		self::assertSame(
			array(
				'signedIn' => false,
				'action'   => '',
				'nonce'    => '',
				'saved'    => '',
			),
			$this->service( BuilderPage::class )->account(),
			'nothing of a customer\'s on a page a cache may keep'
		);

		// A part gone since: listed, and said to have changed (re-checked on opening, by the builder).
		wp_set_current_user( $this->ana );
		wp_trash_post( $this->parts['wheels'] );
		$this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->forget();
		ob_start();
		$this->service( SavedBuildHooks::class )->page();
		self::assertStringContainsString( 'A part has changed since you saved it. Open the build to see which.', (string) ob_get_clean() );
	}

	/**
	 * The milestone 8 walkthrough: a build's picture sat inline with its name, so a two-word name wrapped around it. On
	 * My builds, and there alone, the picture takes its own line above the name, beside WooCommerce's own styles.
	 */
	public function test_my_builds_sets_each_picture_above_its_name_and_no_other_page_gets_the_rule(): void {
		$styles = static function (): string {
			$after = wp_styles()->get_data( 'woocommerce-general', 'after' );
			return is_array( $after ) ? implode( '', $after ) : '';
		};
		// Each page gets styles of its own, and the site's are put back for the tests after this one.
		$kept = $GLOBALS['wp_styles'] ?? null;
		try {
			$GLOBALS['wp_styles'] = new \WP_Styles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A fresh set of styles for the test, restored below.
			$this->go_to( wc_get_account_endpoint_url( SavedBuildHooks::ENDPOINT ) );
			self::assertTrue( is_wc_endpoint_url( SavedBuildHooks::ENDPOINT ), 'on My builds' );
			do_action( 'wp_enqueue_scripts' );
			self::assertStringContainsString( '.cbuild-my-builds__build th img{display:block', $styles() );

			$GLOBALS['wp_styles'] = new \WP_Styles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- As above.
			$this->go_to( wc_get_account_endpoint_url( 'orders' ) );
			do_action( 'wp_enqueue_scripts' );
			self::assertStringNotContainsString( 'cbuild-my-builds', $styles(), 'not on another account page' );
		} finally {
			$GLOBALS['wp_styles'] = $kept; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored.
		}
	}

	/**
	 * The page's markup, for the signed-in customer.
	 */
	private function page(): string {
		ob_start();
		$this->service( SavedBuildHooks::class )->page();
		return (string) ob_get_clean();
	}

	/**
	 * Spec, Security: "Signed-in pages aren't served from a page cache, so those nonces stay fresh." WordPress sends a
	 * signed-in visitor no-cache headers itself (wp-includes/class-wp.php:443); a page cache that reads only
	 * DONOTCACHEPAGE is told too, once the page holds a customer's nonce.
	 */
	public function test_a_signed_in_builder_page_holding_a_nonce_is_kept_out_of_page_caches(): void {
		wp_set_current_user( $this->ana );
		self::assertNotSame( '', $this->service( BuilderPage::class )->account()['nonce'] );
		self::assertTrue( defined( 'DONOTCACHEPAGE' ) && true === constant( 'DONOTCACHEPAGE' ) );
	}

	/**
	 * Audit 5: "distinct accessible names on every control". Each row's Open, Rename, Delete and name field are named
	 * for their build, so a list of the page's buttons is not twenty "Delete"s.
	 */
	public function test_each_rows_controls_are_named_for_their_build(): void {
		wp_set_current_user( $this->ana );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Street' ) );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Park' ) );
		$dom   = new \DOMXPath( self::html( $this->page() ) );
		$names = array();
		foreach ( $dom->query( '//a[contains(@class,"cbuild-my-builds__open")] | //button | //input[@type="text"]' ) as $control ) {
			self::assertInstanceOf( \DOMElement::class, $control );
			$names[] = $control->getAttribute( 'aria-label' );
		}
		sort( $names );
		self::assertSame( array( 'Delete Park', 'Delete Street', 'Name of Park', 'Name of Street', 'Open Park', 'Open Street', 'Rename Park', 'Rename Street' ), $names );
	}

	/**
	 * D-205: a page's requests for pictures have 10 of the queue's 50 places, so carts always have room. My builds is
	 * a page: with a page's share taken, it asks for none.
	 */
	public function test_my_builds_asks_for_pictures_as_a_page_does(): void {
		wp_set_current_user( $this->ana );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Street' ) );
		as_unschedule_all_actions( \CreativeBranding\SkateboardBuilder\Service\ComposedPictures::JOB, array(), Options::QUEUE_GROUP );
		for ( $i = 1; $i <= \CreativeBranding\SkateboardBuilder\Service\ComposedPictures::PAGE_MOST; $i++ ) {
			as_enqueue_async_action( \CreativeBranding\SkateboardBuilder\Service\ComposedPictures::JOB, array( array( 'kit' => 9000 + $i, 'picks' => array( 'deck' => $i ) ) ), Options::QUEUE_GROUP );
		}
		try {
			$this->page();
			self::assertCount( \CreativeBranding\SkateboardBuilder\Service\ComposedPictures::PAGE_MOST, self::waiting( \CreativeBranding\SkateboardBuilder\Service\ComposedPictures::JOB ) );
		} finally {
			as_unschedule_all_actions( \CreativeBranding\SkateboardBuilder\Service\ComposedPictures::JOB, array(), Options::QUEUE_GROUP );
		}
	}

	/**
	 * Audit 2 and 8: My builds' queries do not grow with the builds on it.
	 */
	public function test_my_builds_makes_as_many_queries_for_twenty_builds_as_for_one(): void {
		// The test site has no placeholder image, which WordPress cannot cache and reads for each photo; a shop has one.
		update_option( 'woocommerce_placeholder_image', 0 );
		wp_set_current_user( $this->ana );
		$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Build 1' ) );
		$this->page();
		wp_cache_flush();
		$this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->forget();
		$before = get_num_queries();
		$this->page();
		$one = get_num_queries() - $before;
		for ( $i = 2; $i <= 20; $i++ ) {
			$this->post( $this->saveFields( wp_create_nonce( SavedBuildHooks::SAVE ), 'Build ' . $i ) );
		}
		$this->page();
		wp_cache_flush();
		$this->service( \CreativeBranding\SkateboardBuilder\Port\Kits::class )->forget();
		$before = get_num_queries();
		$this->page();
		$twenty = get_num_queries() - $before;
		self::assertLessThanOrEqual( $one + 2, $twenty, sprintf( '%d queries for one build, %d for twenty', $one, $twenty ) );
	}

	/**
	 * A fragment as a document.
	 */
	private static function html( string $html ): \DOMDocument {
		$document = new \DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();
		return $document;
	}
}
