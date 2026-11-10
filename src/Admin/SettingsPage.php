<?php
/**
 * WooCommerce > Settings > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Service\SettingsDraft;
use CreativeBranding\SkateboardBuilder\Service\SettingsEditor;
use WC_Admin_Settings;
use WC_Settings_Page;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The shop's settings, through WooCommerce's settings API (spec: Admin, Settings): the attribute mapping and which
 * source wins. WooCommerce checks `manage_woocommerce` and its nonce before it asks a tab to save
 * (includes/admin/class-wc-admin-settings.php:87 and :91 in 11.2.2); this checks both again, since any code can fire
 * the hook. What is sent goes to `Service\SettingsEditor`, which saves all of it or none. When it saves none, what
 * the person typed is shown again with each problem beside its line.
 *
 * @since 0.3.0
 */
final class SettingsPage extends WC_Settings_Page {

	/**
	 * How many blank lines the mapping ends with. More appear after each save, so the form needs no script.
	 */
	public const BLANK_LINES = 3;

	/**
	 * What was sent and refused, to show again; null when nothing was.
	 *
	 * @var array{lines: list<array{attribute: string, spec: string, unit: string}>, order: list<string>, draft: SettingsDraft}|null
	 */
	private ?array $refused = null;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ShopSettings   $settings The shop's settings.
	 * @param SettingsEditor $editor   Checks and saves them.
	 */
	public function __construct( private ShopSettings $settings, private SettingsEditor $editor ) {
		$this->id    = 'cbuild';
		$this->label = __( 'Builder', 'creative-skateboard-builder' );
		parent::__construct();
		add_action( 'woocommerce_admin_field_cbuild_mapping', array( $this, 'mappingField' ), 10, 0 );
		add_action( 'woocommerce_admin_field_cbuild_order', array( $this, 'orderField' ), 10, 0 );
	}

	/**
	 * The tab's fields: a heading, then the two the plugin draws itself.
	 *
	 * @since 0.3.0
	 *
	 * @param string $section_id The section; the tab has one.
	 * @return list<array<string, string>>
	 */
	protected function get_settings_for_section_core( $section_id ) {
		unset( $section_id );
		return array(
			array(
				'title' => __( 'Part specs', 'creative-skateboard-builder' ),
				'type'  => 'title',
				'desc'  => __( 'Where the builder reads each part\'s specs. Saving a change here reads every part again, in the background.', 'creative-skateboard-builder' ),
				'id'    => 'cbuild_specs_settings',
			),
			array(
				'type' => 'cbuild_mapping',
				'id'   => 'cbuild_mapping',
			),
			array(
				'type' => 'cbuild_order',
				'id'   => 'cbuild_order',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cbuild_specs_settings',
			),
		);
	}

	/**
	 * Draw the attribute mapping.
	 *
	 * @since 0.3.0
	 */
	public function mappingField(): void {
		$lines    = array();
		$problems = array();
		$units_at = array();
		if ( null !== $this->refused ) {
			$lines = $this->refused['lines'];
			foreach ( $this->refused['draft']->problems as $problem ) {
				$problems[ $problem['line'] ][] = self::problem( $problem['code'] );
				if ( in_array( $problem['code'], array( SettingsEditor::UNIT_UNKNOWN, SettingsEditor::UNIT_NOT_MEASURE ), true ) ) {
					$units_at[ $problem['line'] ] = true;
				}
			}
		} else {
			foreach ( $this->settings->attributeMapping() as $map ) {
				$lines[] = $map->toArray();
			}
		}
		for ( $blank = 0; $blank < self::BLANK_LINES; $blank++ ) {
			$lines[] = array(
				'attribute' => '',
				'spec'      => '',
				'unit'      => '',
			);
		}
		$attributes = array();
		foreach ( wc_get_attribute_taxonomies() as $taxonomy ) {
			$attributes[ wc_attribute_taxonomy_name( $taxonomy->attribute_name ) ] = $taxonomy->attribute_label;
		}
		$specs = array();
		foreach ( SpecKey::cases() as $key ) {
			$specs[ $key->value ] = Labels::spec( $key );
		}
		$units = array();
		foreach ( Unit::cases() as $unit ) {
			$units[ $unit->value ] = Labels::unit( $unit );
		}
		$max = AttributeMap::MAX_NAME;
		require CBUILD_PATH . 'templates/admin/settings-mapping.php';
	}

	/**
	 * Draw which source wins.
	 *
	 * @since 0.3.0
	 */
	public function orderField(): void {
		$order   = null !== $this->refused ? $this->refused['order'] : $this->settings->sourceOrder()->names();
		$problem = null !== $this->refused && null === $this->refused['draft']->order ? self::problem( SettingsEditor::ORDER_INVALID ) : '';
		$sources = array();
		foreach ( Origin::cases() as $origin ) {
			$sources[ $origin->value ] = Labels::source( $origin );
		}
		$places = array(
			__( 'First', 'creative-skateboard-builder' ),
			__( 'Second', 'creative-skateboard-builder' ),
			__( 'Third', 'creative-skateboard-builder' ),
		);
		require CBUILD_PATH . 'templates/admin/settings-order.php';
	}

	/**
	 * Save what was sent, or keep it to show again with what is wrong.
	 *
	 * @since 0.3.0
	 */
	public function save(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		check_admin_referer( 'woocommerce-settings' );

		$lines = array();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitised in line().
		$sent = isset( $_POST['cbuild_mapping'] ) && is_array( $_POST['cbuild_mapping'] ) ? wp_unslash( $_POST['cbuild_mapping'] ) : array();
		foreach ( $sent as $line ) {
			$lines[] = self::line( $line );
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each name is sanitised below.
		$places = isset( $_POST['cbuild_order'] ) && is_array( $_POST['cbuild_order'] ) ? wp_unslash( $_POST['cbuild_order'] ) : array();
		$order  = array();
		foreach ( $places as $name ) {
			$order[] = is_string( $name ) ? sanitize_key( $name ) : '';
		}

		$draft = $this->editor->submit( $lines, $order );
		if ( $draft->saved() ) {
			return;
		}
		$this->refused = compact( 'lines', 'order', 'draft' );
		WC_Admin_Settings::add_error( __( 'The settings were not saved. Each problem is shown beside its field.', 'creative-skateboard-builder' ) );
	}

	/**
	 * One line of the mapping as it was sent, each field as plain text.
	 *
	 * @param mixed $line The line.
	 * @return array{attribute: string, spec: string, unit: string}
	 */
	private static function line( mixed $line ): array {
		$text = static function ( string $field ) use ( $line ): string {
			$value = is_array( $line ) ? ( $line[ $field ] ?? '' ) : '';
			return is_string( $value ) ? sanitize_text_field( $value ) : '';
		};
		return array(
			'attribute' => $text( 'attribute' ),
			'spec'      => $text( 'spec' ),
			'unit'      => $text( 'unit' ),
		);
	}

	/**
	 * The words for a problem's code.
	 *
	 * @param string $code The code.
	 */
	private static function problem( string $code ): string {
		$words = array(
			SettingsEditor::ATTRIBUTE_MISSING => __( 'Choose the attribute this spec is read from, or clear the line.', 'creative-skateboard-builder' ),
			SettingsEditor::SPEC_MISSING      => __( 'Choose the spec this attribute feeds, or clear the line.', 'creative-skateboard-builder' ),
			SettingsEditor::ATTRIBUTE_LONG    => __( 'The attribute\'s name is too long. Use 190 characters or fewer.', 'creative-skateboard-builder' ),
			SettingsEditor::SPEC_UNKNOWN      => __( 'Choose a spec from the list.', 'creative-skateboard-builder' ),
			SettingsEditor::UNIT_UNKNOWN      => __( 'Choose a unit from the list.', 'creative-skateboard-builder' ),
			SettingsEditor::UNIT_NOT_MEASURE  => __( 'This spec is not a length, so it has no unit. Set the unit to the spec\'s own.', 'creative-skateboard-builder' ),
			SettingsEditor::DUPLICATE         => __( 'This attribute already feeds this spec on a line above. Clear one of the two.', 'creative-skateboard-builder' ),
			SettingsEditor::TOO_MANY          => __( 'The mapping holds 50 lines at most. Clear some lines.', 'creative-skateboard-builder' ),
			SettingsEditor::ORDER_INVALID     => __( 'Put each source in one place: first, second and third.', 'creative-skateboard-builder' ),
		);
		return $words[ $code ] ?? $code;
	}
}
