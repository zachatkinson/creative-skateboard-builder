<?php
/**
 * Saved builds in WordPress's privacy tools, a deleted account, and a network's deletions.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Install\Activator;
use CreativeBranding\SkateboardBuilder\Install\NetworkFile;
use CreativeBranding\SkateboardBuilder\Install\Uninstaller;
use CreativeBranding\SkateboardBuilder\Privacy\PrivacyHooks;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use WP_Privacy_Policy_Content;

/**
 * Roadmap, milestone 8: the exporter and eraser for saved builds, a deleted account's builds, the policy text, and
 * #8, a deleted network site's tables (D-220).
 */
final class PrivacyTest extends CartTestCase {

	private int $ana;

	private int $ben;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::builds() ) );
		// The network's test commits its users (a real table's CREATE does); an earlier run's are deleted first.
		require_once ABSPATH . 'wp-admin/includes/user.php';
		require_once ABSPATH . 'wp-admin/includes/ms.php';
		foreach ( array( 'shopper+1@example.com', 'shopper+2@example.com' ) as $address ) {
			$left = get_user_by( 'email', $address );
			if ( $left instanceof \WP_User ) {
				is_multisite() ? wpmu_delete_user( $left->ID ) : wp_delete_user( $left->ID );
			}
		}
		$this->ana = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => 'shopper+1@example.com' ) );
		$this->ben = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => 'shopper+2@example.com' ) );
		$this->service( PrivacyHooks::class )->register();
	}

	private function save( int $user, string $name ): string {
		$saved = $this->service( SavedBuilds::class )->save( $user, $this->kit, $this->picks(), true, $name );
		self::assertSame( SavedBuilds::SAVED, $saved['code'] );
		return $saved['uuid'];
	}

	public function test_the_exporter_gives_each_of_the_accounts_builds_and_no_one_elses(): void {
		$uuid = $this->save( $this->ana, 'Street' );
		$this->save( $this->ben, 'Ben\'s' );
		$exporters = apply_filters( 'wp_privacy_personal_data_exporters', array() );
		self::assertSame( 'Saved builds', $exporters['creative-skateboard-builder']['exporter_friendly_name'] ?? null );
		$export = call_user_func( $exporters['creative-skateboard-builder']['callback'], 'shopper+1@example.com', 1 );
		self::assertTrue( $export['done'] );
		self::assertCount( 1, $export['data'] );
		$item = $export['data'][0];
		self::assertSame( 'cbuild-saved-builds', $item['group_id'] );
		self::assertSame( 'cbuild-build-' . $uuid, $item['item_id'] );
		$data = array_column( $item['data'], 'value', 'name' );
		self::assertSame( 'Street', $data['Name'] );
		self::assertSame( 'Complete', $data['Kit'] );
		self::assertStringContainsString( 'Deck', $data['Parts'] );
		self::assertSame( 'Yes', $data['Assembly'] );
		self::assertMatchesRegularExpression( '/\A\d{4}-\d\d-\d\d \d\d:\d\d:\d\d UTC\z/', $data['Saved first'] );
		self::assertStringNotContainsString( 'Ben', (string) wp_json_encode( $export ) );

		self::assertSame( array( 'data' => array(), 'done' => true ), call_user_func( $exporters['creative-skateboard-builder']['callback'], 'shopper+9@example.com', 1 ), 'an address with no account' );
		self::assertSame( array(), call_user_func( $exporters['creative-skateboard-builder']['callback'], 'shopper+1@example.com', 2 )['data'], 'all on the first page' );
	}

	public function test_the_eraser_deletes_the_accounts_builds_and_keeps_no_one_elses(): void {
		$this->save( $this->ana, 'Street' );
		$this->save( $this->ana, 'Park' );
		$this->save( $this->ben, 'Ben\'s' );
		$erasers = apply_filters( 'wp_privacy_personal_data_erasers', array() );
		self::assertSame( 'Saved builds', $erasers['creative-skateboard-builder']['eraser_friendly_name'] ?? null );
		$erased = call_user_func( $erasers['creative-skateboard-builder']['callback'], 'shopper+1@example.com', 1 );
		self::assertSame(
			array(
				'items_removed'  => true,
				'items_retained' => false,
				'messages'       => array(),
				'done'           => true,
			),
			$erased
		);
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ) );
		self::assertCount( 1, $this->service( SavedBuilds::class )->of( $this->ben ) );
		self::assertFalse( call_user_func( $erasers['creative-skateboard-builder']['callback'], 'shopper+1@example.com', 1 )['items_removed'], 'nothing left the second time' );
	}

	public function test_a_deleted_account_takes_its_builds_with_it(): void {
		require_once ABSPATH . 'wp-admin/includes/user.php';
		$this->save( $this->ana, 'Street' );
		$this->save( $this->ben, 'Ben\'s' );
		wp_delete_user( $this->ana );
		self::assertSame( array(), $this->service( SavedBuilds::class )->of( $this->ana ) );
		self::assertCount( 1, $this->service( SavedBuilds::class )->of( $this->ben ) );
	}

	public function test_the_policy_text_says_what_is_kept_and_for_how_long(): void {
		require_once ABSPATH . 'wp-admin/includes/misc.php';
		require_once ABSPATH . 'wp-admin/includes/class-wp-privacy-policy-content.php';
		// In wp-admin, during admin_init, as the function asks (wp-admin/includes/plugin.php:2411, :2422).
		global $wp_current_filter;
		set_current_screen( 'dashboard' );
		$wp_current_filter[] = 'admin_init';
		try {
			$this->service( PrivacyHooks::class )->policy();
		} finally {
			array_pop( $wp_current_filter );
			set_current_screen( 'front' );
		}
		$text = (string) wp_json_encode( WP_Privacy_Policy_Content::get_suggested_policy_text() );
		self::assertStringContainsString( 'Creative Skateboard Builder', $text );
		self::assertStringContainsString( 'We keep each saved build with your account', $text );
		self::assertStringContainsString( 'kept until you delete them or your account is deleted', $text );
	}

	/**
	 * Issue #8: with the plugin active on one site of a network alone, deleting that site from the network's admin,
	 * where none of the plugin's code is loaded, drops its tables; and deleting an account there deletes its builds.
	 */
	public function test_a_network_drops_a_deleted_sites_tables_and_a_deleted_accounts_builds_without_the_plugin(): void {
		if ( ! is_multisite() ) {
			self::markTestSkipped( 'The multisite leg (composer test:integration:multisite).' );
		}
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/ms.php';
		$site = (int) self::factory()->blog->create();
		NetworkFile::remove();
		self::assertTrue( NetworkFile::ensure(), 'the must-use folder is writable here' );
		self::assertTrue( NetworkFile::inPlace() );
		try {
			$this->withRealTables(
				function () use ( $site, $wpdb ): void {
					switch_to_blog( $site );
					Activator::activate( false );
					$builds = Tables::builds();
					$wpdb->insert(
						$builds,
						array(
							'uuid'       => '00000000-0000-4000-8000-000000000001',
							'user_id'    => $this->ana,
							'kit_id'     => 1,
							'picks'      => '{}',
							'assembly'   => 0,
							'name'       => '',
							'created_at' => '2026-11-17 00:00:00',
							'updated_at' => '2026-11-17 00:00:00',
						)
					);
					restore_current_blog();

					// The network's admin, where the plugin is not loaded: its own hooks are gone, the must-use file's stay.
					remove_filter( 'wpmu_drop_tables', array( Uninstaller::class, 'siteTables' ) );
					remove_all_actions( 'wpmu_delete_user' );
					require NetworkFile::path();

					wpmu_delete_user( $this->ana );
					self::assertSame( '0', (string) $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i', $builds ) ), 'the account\'s builds on the other site' );

					wp_delete_site( $site );
					foreach ( Tables::all() as $table ) {
						self::assertNull( $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->get_blog_prefix( $site ) . Tables::PREFIX . $table ) ), $table . ' outlived its site' );
					}
				}
			);
		} finally {
			// A real table's CREATE commits the test's users, so they are deleted here, not rolled back.
			wpmu_delete_user( $this->ben );
			add_filter( 'wpmu_drop_tables', array( Uninstaller::class, 'siteTables' ) );
			NetworkFile::remove();
		}
		self::assertFileDoesNotExist( NetworkFile::path() );
	}

	public function test_a_single_site_needs_no_network_file(): void {
		if ( is_multisite() ) {
			self::markTestSkipped( 'A single site.' );
		}
		self::assertTrue( NetworkFile::inPlace() );
		self::assertTrue( NetworkFile::ensure() );
		self::assertFileDoesNotExist( NetworkFile::path() );
	}
}
