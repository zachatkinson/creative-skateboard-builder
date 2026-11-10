<?php
/**
 * WooCommerce > Settings > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Assembly;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
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
 * The shop's settings, through WooCommerce's settings API (spec: Admin, Settings): the attribute mapping, which
 * source wins, the build discount, where a fixed-product coupon lands, the state route's rate limit and proxy
 * header, and what a new kit's assembly starts from. WooCommerce checks `manage_woocommerce` and its nonce before it asks a tab to save
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
	 * The field each of the terms' problems is on.
	 */
	private const array FIELDS = array(
		SettingsEditor::DISCOUNT_INVALID => 'discount',
		SettingsEditor::COUPONS_INVALID  => 'coupons',
		SettingsEditor::RATE_INVALID     => 'rate',
		SettingsEditor::HEADER_INVALID   => 'header',
		SettingsEditor::FEE_INVALID      => 'fee',
		SettingsEditor::LEAD_TIME_LONG   => 'lead_time',
		SettingsEditor::RETURNS_LONG     => 'returns_notice',
	);

	/**
	 * What was sent and refused, to show again; null when nothing was.
	 *
	 * @var array{lines: list<array{attribute: string, spec: string, unit: string}>, order: list<string>, terms: array<string, string>|null, draft: SettingsDraft}|null
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
		add_action( 'woocommerce_admin_field_cbuild_terms', array( $this, 'termsField' ), 10, 1 );
	}

	/**
	 * The tab's fields: each group a heading, then the fields the plugin draws itself.
	 *
	 * @since 0.3.0
	 * @since 0.4.0 The builds, the visitors and the new kits.
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
			array(
				'title' => __( 'Builds', 'creative-skateboard-builder' ),
				'type'  => 'title',
				'desc'  => __( 'What every build is sold on. The cart reads these from the next version on.', 'creative-skateboard-builder' ),
				'id'    => 'cbuild_pricing_settings',
			),
			array(
				'type'  => 'cbuild_terms',
				'id'    => 'cbuild_terms_pricing',
				'group' => 'pricing',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cbuild_pricing_settings',
			),
			array(
				'title' => __( 'Visitors', 'creative-skateboard-builder' ),
				'type'  => 'title',
				'desc'  => __( 'The builder asks the shop for prices and stock as a visitor picks parts. The limit keeps one address from asking too often. The address is never stored: a scrambled form of it is kept for the minute it is counted in.', 'creative-skateboard-builder' ),
				'id'    => 'cbuild_requests_settings',
			),
			array(
				'type'  => 'cbuild_terms',
				'id'    => 'cbuild_terms_requests',
				'group' => 'requests',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cbuild_requests_settings',
			),
			array(
				'title' => __( 'New kits', 'creative-skateboard-builder' ),
				'type'  => 'title',
				'desc'  => __( 'What the assembly settings of a new kit start from. A kit that is already set up keeps its own.', 'creative-skateboard-builder' ),
				'id'    => 'cbuild_kits_settings',
			),
			array(
				'type'  => 'cbuild_terms',
				'id'    => 'cbuild_terms_kits',
				'group' => 'kits',
			),
			array(
				'type' => 'sectionend',
				'id'   => 'cbuild_kits_settings',
			),
		);
	}

	/**
	 * Draw one group of the terms: `pricing`, `requests` or `kits`.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $field The field as the tab lists it, with its `group`.
	 */
	public function termsField( mixed $field ): void {
		$group = is_array( $field ) ? ( $field['group'] ?? '' ) : '';
		$typed = $this->refused['terms'] ?? null;
		$wrong = array();
		foreach ( null !== $typed && null !== $this->refused ? $this->refused['draft']->problems : array() as $problem ) {
			if ( isset( self::FIELDS[ $problem['code'] ] ) ) {
				$wrong[ self::FIELDS[ $problem['code'] ] ] = self::problem( $problem['code'] );
			}
		}
		$rows = array();
		foreach ( $this->rows( $typed ?? self::typed( $this->settings->terms() ) ) as $name => $row ) {
			if ( $row['group'] === $group ) {
				$rows[] = $row + array(
					'id'      => 'cbuild-terms-' . $name,
					'name'    => 'cbuild_terms[' . $name . ']',
					'problem' => $wrong[ $name ] ?? '',
				);
			}
		}
		if ( array() !== $rows ) {
			require CBUILD_PATH . 'templates/admin/settings-terms.php';
		}
	}

	/**
	 * The terms' fields, by name, each with its group, its label, its kind of control and what it shows.
	 *
	 * @param array<string, string> $shown What each field shows.
	 * @return array<string, array<string, mixed>>
	 */
	private function rows( array $shown ): array {
		return array(
			'discount'       => array(
				'group' => 'pricing',
				'label' => __( 'Build discount (%)', 'creative-skateboard-builder' ),
				'type'  => 'text',
				'value' => $shown['discount'] ?? '',
				'note'  => __( 'A percentage taken off each part of a build. 0 for none. Below 100, with up to two decimals.', 'creative-skateboard-builder' ),
				'max'   => 5,
			),
			'coupons'        => array(
				'group'   => 'pricing',
				'label'   => __( 'A fixed product discount coupon applies to', 'creative-skateboard-builder' ),
				'type'    => 'select',
				'value'   => $shown['coupons'] ?? '',
				'note'    => __( 'A coupon that takes a fixed amount off a product, where the kit is one of its products. On every part, the amount comes off each part of the build as well.', 'creative-skateboard-builder' ),
				'options' => array(
					BuildTerms::ON_KIT   => __( 'The kit alone', 'creative-skateboard-builder' ),
					BuildTerms::ON_PARTS => __( 'The kit and every part', 'creative-skateboard-builder' ),
				),
			),
			'rate'           => array(
				'group' => 'requests',
				'label' => __( 'Requests a minute from one address', 'creative-skateboard-builder' ),
				'type'  => 'text',
				'value' => $shown['rate'] ?? '',
				'note'  => __( 'A whole number from 10 to 6000. An address that asks more often is told to wait until its minute ends.', 'creative-skateboard-builder' ),
				'max'   => 4,
				'mode'  => 'numeric',
			),
			'header'         => array(
				'group' => 'requests',
				'label' => __( 'Trusted proxy header', 'creative-skateboard-builder' ),
				'type'  => 'text',
				'value' => $shown['header'] ?? '',
				'note'  => __( 'Leave this empty unless the shop is behind a proxy or a content delivery network that puts the visitor\'s address in a request header, such as X-Forwarded-For. Name the header only when every request passes through that proxy: a visitor who can send the header themselves can get past the limit.', 'creative-skateboard-builder' ),
				'max'   => 64,
			),
			'default_on'     => array(
				'group' => 'kits',
				'label' => __( 'Assembly is chosen when a build starts', 'creative-skateboard-builder' ),
				'type'  => 'checkbox',
				'value' => $shown['default_on'] ?? '',
				'note'  => '',
			),
			'fee'            => array(
				'group' => 'kits',
				/* translators: %s: the shop's currency symbol. */
				'label' => sprintf( __( 'Fee (%s)', 'creative-skateboard-builder' ), html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ),
				'type'  => 'text',
				'value' => $shown['fee'] ?? '',
				'note'  => __( 'Charged once for a build that is put together. 0 for none.', 'creative-skateboard-builder' ),
				'max'   => 14,
			),
			'lead_time'      => array(
				'group' => 'kits',
				'label' => __( 'Lead time', 'creative-skateboard-builder' ),
				'type'  => 'text',
				'value' => $shown['lead_time'] ?? '',
				'note'  => '',
				'max'   => Assembly::MAX_LEAD_TIME,
			),
			'returns_notice' => array(
				'group' => 'kits',
				'label' => __( 'Returns notice', 'creative-skateboard-builder' ),
				'type'  => 'textarea',
				'value' => $shown['returns_notice'] ?? '',
				'note'  => '',
				'max'   => Assembly::MAX_RETURNS_NOTICE,
			),
		);
	}

	/**
	 * The terms as their fields show them: a decimal with the shop's separator.
	 *
	 * @param BuildTerms $terms The terms.
	 * @return array<string, string>
	 */
	private static function typed( BuildTerms $terms ): array {
		$stored   = $terms->toArray();
		$discount = $stored[ BuildTerms::DISCOUNT ];
		$assembly = $terms->assembly;
		$text     = static fn( mixed $value ): string => is_string( $value ) ? $value : '';
		return array(
			'discount'       => Labels::typed( $text( $discount ) ),
			'coupons'        => $terms->fixedCoupons,
			'rate'           => (string) $terms->rateLimit,
			'header'         => $terms->proxyHeader,
			'default_on'     => true === ( $assembly['default_on'] ?? false ) ? '1' : '',
			'fee'            => Labels::typed( $text( $assembly['fee'] ?? '' ) ),
			'lead_time'      => $text( $assembly['lead_time'] ?? '' ),
			'returns_notice' => $text( $assembly['returns_notice'] ?? '' ),
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
				if ( SettingsEditor::ORDER_INVALID === $problem['code'] || isset( self::FIELDS[ $problem['code'] ] ) ) {
					// The order's own field shows it.
					continue;
				}
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

		$terms = null;
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitised below.
		$typed = isset( $_POST['cbuild_terms'] ) && is_array( $_POST['cbuild_terms'] ) ? wp_unslash( $_POST['cbuild_terms'] ) : null;
		if ( null !== $typed ) {
			$terms = array();
			foreach ( array_keys( $this->rows( array() ) ) as $name ) {
				$value          = $typed[ $name ] ?? '';
				$value          = is_string( $value ) ? $value : '';
				$terms[ $name ] = 'returns_notice' === $name ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
			}
		}

		$draft = $this->editor->submit( $lines, $order, $terms, wc_get_price_decimal_separator() );
		if ( $draft->saved() ) {
			return;
		}
		$this->refused = compact( 'lines', 'order', 'terms', 'draft' );
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
			SettingsEditor::DISCOUNT_INVALID  => __( 'Enter a percentage below 100 with up to two decimals, or 0 for none.', 'creative-skateboard-builder' ),
			SettingsEditor::COUPONS_INVALID   => __( 'Choose where the coupon applies from the list.', 'creative-skateboard-builder' ),
			SettingsEditor::RATE_INVALID      => __( 'Enter a whole number from 10 to 6000.', 'creative-skateboard-builder' ),
			SettingsEditor::HEADER_INVALID    => __( 'Enter the header\'s name alone, such as X-Forwarded-For: letters, digits and hyphens, starting with a letter, 64 characters at most.', 'creative-skateboard-builder' ),
			SettingsEditor::FEE_INVALID       => __( 'Enter an amount with up to four decimals, or 0 for none.', 'creative-skateboard-builder' ),
			SettingsEditor::LEAD_TIME_LONG    => __( 'The lead time is too long. Use 200 characters or fewer.', 'creative-skateboard-builder' ),
			SettingsEditor::RETURNS_LONG      => __( 'The returns notice is too long. Use 1,000 characters or fewer.', 'creative-skateboard-builder' ),
		);
		return $words[ $code ] ?? $code;
	}
}
