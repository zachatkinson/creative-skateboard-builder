<?php
/**
 * Saved builds in WordPress's privacy tools, and when an account goes.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Privacy;

use Closure;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Install\NetworkFile;
use CreativeBranding\SkateboardBuilder\Install\Sites;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WP_User;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Saved builds are the plugin's only personal data (docs/PRIVACY.md). WordPress's exporter and eraser find them by the
 * account's email address (wp-admin/includes/privacy-tools.php:822, :955); a deleted account's go with it, on its
 * site with `delete_user` (wp-admin/includes/user.php:386) and on every site of a network with `wpmu_delete_user`
 * (wp-admin/includes/ms.php:174); and the suggested policy text says what is kept, on `admin_init`, as
 * `wp_add_privacy_policy_content()` asks (wp-admin/includes/plugin.php:2417). D-220.
 *
 * @since 0.8.0
 */
final class PrivacyHooks implements Hookable {

	/**
	 * The exporter's and eraser's ID.
	 *
	 * @since 0.8.0
	 */
	public const string ID = 'creative-skateboard-builder';

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param Closure(): SavedBuilds $saved The customer's builds, built when a tool or a deletion asks.
	 */
	public function __construct( private Closure $saved ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.8.0
	 */
	public function register(): void {
		add_filter( 'wp_privacy_personal_data_exporters', array( $this, 'exporters' ) );
		add_filter( 'wp_privacy_personal_data_erasers', array( $this, 'erasers' ) );
		add_action( 'delete_user', array( $this, 'deleted' ) );
		add_action( 'wpmu_delete_user', array( $this, 'deletedFromNetwork' ) );
		add_action( 'admin_init', array( $this, 'policy' ) );
	}

	/**
	 * WordPress's exporters, with saved builds.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $exporters The exporters by ID.
	 * @return mixed
	 */
	public function exporters( mixed $exporters ): mixed {
		if ( is_array( $exporters ) ) {
			$exporters[ self::ID ] = array(
				'exporter_friendly_name' => __( 'Saved builds', 'creative-skateboard-builder' ),
				'callback'               => array( $this, 'export' ),
			);
		}
		return $exporters;
	}

	/**
	 * WordPress's erasers, with saved builds.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $erasers The erasers by ID.
	 * @return mixed
	 */
	public function erasers( mixed $erasers ): mixed {
		if ( is_array( $erasers ) ) {
			$erasers[ self::ID ] = array(
				'eraser_friendly_name' => __( 'Saved builds', 'creative-skateboard-builder' ),
				'callback'             => array( $this, 'erase' ),
			);
		}
		return $erasers;
	}

	/**
	 * The saved builds of the account with this email address, one item each, all on the first page: a customer keeps
	 * 100 at most.
	 *
	 * @since 0.8.0
	 *
	 * @param string $email The address.
	 * @param int    $page  The page WordPress asks for, from 1.
	 * @return array{data: list<array{group_id: string, group_label: string, group_description: string, item_id: string, data: list<array{name: string, value: string}>}>, done: bool}
	 */
	public function export( string $email, int $page = 1 ): array {
		$user = get_user_by( 'email', $email );
		if ( ! $user instanceof WP_User || $page > 1 ) {
			return array(
				'data' => array(),
				'done' => true,
			);
		}
		$items = array();
		foreach ( ( $this->saved )()->of( $user->ID ) as $build ) {
			$parts = array();
			foreach ( $build->picks as $part ) {
				$parts[] = self::title( $part );
			}
			$items[] = array(
				'group_id'          => 'cbuild-saved-builds',
				'group_label'       => __( 'Saved builds', 'creative-skateboard-builder' ),
				'group_description' => __( 'Builds saved under My builds.', 'creative-skateboard-builder' ),
				'item_id'           => 'cbuild-build-' . $build->uuid,
				'data'              => array(
					array(
						'name'  => __( 'Name', 'creative-skateboard-builder' ),
						'value' => $build->name,
					),
					array(
						'name'  => __( 'Kit', 'creative-skateboard-builder' ),
						'value' => self::title( $build->kit ),
					),
					array(
						'name'  => __( 'Parts', 'creative-skateboard-builder' ),
						'value' => Words::joined( $parts ),
					),
					array(
						'name'  => __( 'Assembly', 'creative-skateboard-builder' ),
						'value' => $build->assembly ? __( 'Yes', 'creative-skateboard-builder' ) : __( 'No', 'creative-skateboard-builder' ),
					),
					array(
						'name'  => __( 'Saved first', 'creative-skateboard-builder' ),
						'value' => $build->created->format( 'Y-m-d H:i:s' ) . ' UTC',
					),
					array(
						'name'  => __( 'Saved last', 'creative-skateboard-builder' ),
						'value' => $build->updated->format( 'Y-m-d H:i:s' ) . ' UTC',
					),
				),
			);
		}
		return array(
			'data' => $items,
			'done' => true,
		);
	}

	/**
	 * Delete the saved builds of the account with this email address. Nothing is kept.
	 *
	 * @since 0.8.0
	 *
	 * @param string $email The address.
	 * @param int    $page  The page WordPress asks for, from 1.
	 * @return array{items_removed: bool, items_retained: bool, messages: list<string>, done: bool}
	 */
	public function erase( string $email, int $page = 1 ): array {
		$user    = get_user_by( 'email', $email );
		$removed = $user instanceof WP_User && $page < 2 && ( $this->saved )()->forget( $user->ID ) > 0;
		return array(
			'items_removed'  => $removed,
			'items_retained' => false,
			'messages'       => array(),
			'done'           => true,
		);
	}

	/**
	 * An account deleted from this site: its builds here go with it.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $user The user's ID.
	 */
	public function deleted( mixed $user ): void {
		( $this->saved )()->forget( is_numeric( $user ) ? (int) $user : 0 );
	}

	/**
	 * An account deleted from the network: its builds go from every site the plugin is installed on, since the plugin
	 * may be active on only some of them. The must-use file does it where it is in place; this visits each site only
	 * where the file could not be written, which Site Health reports.
	 *
	 * @since 0.8.0
	 *
	 * @param mixed $user The user's ID.
	 */
	public function deletedFromNetwork( mixed $user ): void {
		if ( NetworkFile::inPlace() ) {
			// The must-use file does it, in one query to find the tables and a delete each (#8, D-220).
			return;
		}
		$id    = is_numeric( $user ) ? (int) $user : 0;
		$saved = ( $this->saved )();
		Sites::each(
			static function () use ( $id, $saved ): void {
				if ( false !== get_option( Options::DB_VERSION ) ) {
					$saved->forget( $id );
				}
			}
		);
	}

	/**
	 * The suggested text for the site's privacy policy, in the site's language.
	 *
	 * @since 0.8.0
	 */
	public function policy(): void {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$text = '<p>' . esc_html__( 'When you are signed in, you can save a skateboard build from the builder. We keep each saved build with your account: the parts you picked, whether you asked for assembly, the name you gave it, and when you saved it. You find them under My builds in your account, where you can rename or delete them.', 'creative-skateboard-builder' ) . '</p>'
			. '<p>' . esc_html__( 'Saved builds are kept until you delete them or your account is deleted. They are included when you ask us for a copy of your personal data, and deleted when you ask us to erase it.', 'creative-skateboard-builder' ) . '</p>'
			. '<p>' . esc_html__( 'A link to a build holds only the parts picked, and nothing about you. A picture of a build holds only the parts\' images.', 'creative-skateboard-builder' ) . '</p>';
		wp_add_privacy_policy_content( __( 'Creative Skateboard Builder', 'creative-skateboard-builder' ), wp_kses_post( $text ) );
	}

	/**
	 * A product's name, or its ID where the shop no longer has it.
	 *
	 * @param int $id The product's ID.
	 */
	private static function title( int $id ): string {
		$product = wc_get_product( $id );
		return $product instanceof \WC_Product ? $product->get_name() : '#' . $id;
	}
}
