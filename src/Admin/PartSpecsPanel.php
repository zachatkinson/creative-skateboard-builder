<?php
/**
 * The Part specs panel.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecError;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKind;
use CreativeBranding\SkateboardBuilder\Domain\Spec\StoredSpecs;
use CreativeBranding\SkateboardBuilder\Port\SpecSource;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;
use WC_Admin_Meta_Boxes;
use WC_Product;
use WP_Post;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Where a shop manager fills in a part's specs: a tab on a simple or variable product, and a group of fields in each
 * variation (spec: Admin, Part specs). A field holds the part's own value. Where the value in use comes from
 * somewhere else (the parent product, a mapped attribute, a maker's chart) the field says so beneath it, in text.
 * What is typed is checked by `SpecWriter`; a value that is wrong is not saved, what the part had is kept, and
 * WooCommerce's own notice says which.
 *
 * @since 0.3.0
 */
final class PartSpecsPanel {

	/**
	 * The chart field's value for "leave what is stored": what is stored is not something the list can show.
	 */
	public const KEEP = 'keep';

	/**
	 * The panel's only styles: a group's heading is a target 34 pixels high, past the 24 WCAG 2.2 asks for, where
	 * the browser's own is 18. Added to WooCommerce's admin style sheet, so the plugin ships no file for one rule.
	 */
	private const STYLES = '.cbuild-part-specs summary{padding:8px 12px;cursor:pointer;font-weight:600}';

	/**
	 * The panel's only script, built from assets/src/admin/part-specs.ts: WooCommerce's product screen opens no tab
	 * from the address, so the Catalogue check's link opens the one it names. It carries no data and builds no
	 * markup. It loads after the last of WooCommerce's product scripts (11.2.2,
	 * includes/admin/class-wc-admin-assets.php:566), so it runs once the first tab has been shown
	 * (assets/js/admin/meta-boxes.js:149) and the Variations tab loads its variations when clicked
	 * (assets/js/admin/meta-boxes-product-variation.js:636) (D-083, D-086).
	 */
	private const SCRIPT = 'cbuild-part-specs';
	private const BUILT  = 'build/admin/part-specs';

	/**
	 * How many variations of one product the sources are asked for together.
	 */
	public const TOGETHER = 50;

	/**
	 * What the sources come to for the parts drawn so far, by part. Emptied by a save.
	 *
	 * @var array<int, \CreativeBranding\SkateboardBuilder\Service\Sourced>
	 */
	private array $sourced = array();

	/**
	 * The tables, asked for once per request.
	 *
	 * @var array<int, array{name: string, table: \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable}>|null
	 */
	private ?array $tables = null;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecWriter  $writer  Checks what is typed.
	 * @param SpecIndexer $indexer Says which value is in use, and from where.
	 * @param SpecTables  $charts  The makers' charts.
	 */
	public function __construct( private SpecWriter $writer, private SpecIndexer $indexer, private SpecTables $charts ) {}

	/**
	 * Style the groups' headings, and open the tab a link names.
	 *
	 * @since 0.3.0
	 */
	public function styles(): void {
		wp_add_inline_style( 'woocommerce_admin_styles', self::STYLES );
		// A checkout that was never built has no script; the screen works without it, and the zip is checked for it.
		$built = is_readable( CBUILD_PATH . self::BUILT . '.asset.php' ) ? require CBUILD_PATH . self::BUILT . '.asset.php' : null;
		if ( ! is_array( $built ) || ! is_string( $built['version'] ?? null ) ) {
			return;
		}
		wp_enqueue_script( self::SCRIPT, CBUILD_URL . self::BUILT . '.js', array( 'jquery', 'wc-admin-variation-meta-boxes' ), $built['version'], array( 'in_footer' => true ) );
	}

	/**
	 * The tab, on simple and variable products.
	 *
	 * @since 0.3.0
	 *
	 * @param array<mixed> $tabs The product data box's tabs.
	 * @return array<mixed>
	 */
	public function tab( array $tabs ): array {
		$tabs['cbuild_specs'] = array(
			'label'    => __( 'Part specs', 'creative-skateboard-builder' ),
			'target'   => 'cbuild_part_specs',
			'class'    => array( 'show_if_simple', 'show_if_variable' ),
			'priority' => 65,
		);
		return $tabs;
	}

	/**
	 * The tab's panel, for the product on the screen.
	 *
	 * @since 0.3.0
	 */
	public function product(): void {
		global $product_object;
		if ( ! $product_object instanceof WC_Product ) {
			return;
		}
		$groups = $this->groups( $product_object, 'cbuild-spec', 'cbuild_specs', 'cbuild_spec_table' );
		$whole  = true;
		include CBUILD_PATH . 'templates/admin/part-specs.php';
	}

	/**
	 * A variation's fields.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $loop      The variation's row number.
	 * @param mixed $variation Its post.
	 */
	public function variation( mixed $loop, mixed $variation ): void {
		$product = $variation instanceof WP_Post ? wc_get_product( $variation->ID ) : null;
		if ( ! $product instanceof WC_Product || ! is_int( $loop ) ) {
			return;
		}
		$groups = $this->groups( $product, 'cbuild-spec-' . $loop, 'cbuild_variation_specs[' . $loop . ']', 'cbuild_variation_spec_table[' . $loop . ']' );
		$whole  = false;
		include CBUILD_PATH . 'templates/admin/part-specs.php';
	}

	/**
	 * Save the tab's panel into the product WooCommerce is about to write.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $product The product.
	 */
	public function saveProduct( mixed $product ): void {
		// Any code can fire the hook, so the capability is checked here as well as by WooCommerce.
		if ( ! $product instanceof WC_Product || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$this->sourced = array();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checks woocommerce_meta_nonce before it fires this hook (includes/admin/class-wc-admin-meta-boxes.php:228).
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitised in store().
		$sent  = isset( $_POST['cbuild_specs'] ) && is_array( $_POST['cbuild_specs'] ) ? wp_unslash( $_POST['cbuild_specs'] ) : null;
		$chart = isset( $_POST['cbuild_spec_table'] ) ? wp_unslash( $_POST['cbuild_spec_table'] ) : null;
		// phpcs:enable
		if ( null !== $sent ) {
			$this->store( $product, $sent, $chart );
		}
	}

	/**
	 * Save a variation's fields into the variation WooCommerce is about to write.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $variation The variation.
	 * @param mixed $loop      Its row number.
	 */
	public function saveVariation( mixed $variation, mixed $loop ): void {
		// WooCommerce's save_variations() checks no nonce of its own, and any code can fire the hook.
		if ( ! $variation instanceof WC_Product || ! is_int( $loop ) || ! current_user_can( 'edit_products' ) ) {
			return;
		}
		$this->sourced = array();
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- Both callers of save_variations() check a nonce first: the save-variations one (includes/class-wc-ajax.php:3137) and woocommerce_meta_nonce (includes/admin/class-wc-admin-meta-boxes.php:228).
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitised in store().
		$rows   = isset( $_POST['cbuild_variation_specs'] ) && is_array( $_POST['cbuild_variation_specs'] ) ? wp_unslash( $_POST['cbuild_variation_specs'] ) : array();
		$charts = isset( $_POST['cbuild_variation_spec_table'] ) && is_array( $_POST['cbuild_variation_spec_table'] ) ? wp_unslash( $_POST['cbuild_variation_spec_table'] ) : array();
		// phpcs:enable
		if ( isset( $rows[ $loop ] ) && is_array( $rows[ $loop ] ) ) {
			$this->store( $variation, $rows[ $loop ], $charts[ $loop ] ?? null );
		}
	}

	/**
	 * Check what was sent and put what passes on the product; say what did not.
	 *
	 * @param WC_Product   $product The product or variation about to be saved.
	 * @param array<mixed> $sent    The fields, by spec key, unslashed.
	 * @param mixed        $chart   The chart field, unslashed.
	 */
	private function store( WC_Product $product, array $sent, mixed $chart ): void {
		$typed = array();
		foreach ( SpecKey::cases() as $key ) {
			if ( isset( $sent[ $key->value ] ) && is_string( $sent[ $key->value ] ) ) {
				$typed[ $key->value ] = sanitize_text_field( $sent[ $key->value ] );
			}
		}
		$draft = $this->writer->draft(
			$typed,
			StoredSpecs::fields( $product->get_meta( Options::META_SPECS, true ) ),
			wc_get_price_decimal_separator(),
			self::named( $chart ),
			StoredSpecs::named( $product->get_meta( Options::META_SPEC_TABLE, true ) )
		);

		if ( array() === $draft->fields ) {
			$product->delete_meta_data( Options::META_SPECS );
		} else {
			$product->update_meta_data( Options::META_SPECS, $draft->fields );
		}
		if ( 0 === $draft->table && '' === $draft->size ) {
			$product->delete_meta_data( Options::META_SPEC_TABLE );
		} else {
			$product->update_meta_data(
				Options::META_SPEC_TABLE,
				array(
					'table' => $draft->table,
					'size'  => $draft->size,
				)
			);
		}

		foreach ( $draft->problems as $problem ) {
			$text = self::problem( $problem['key'], $problem['code'], $problem['text'] );
			if ( $product->is_type( 'variation' ) ) {
				/* translators: 1: a variation's ID. 2: a sentence saying what was not saved. */
				$text = sprintf( __( 'Variation #%1$d: %2$s', 'creative-skateboard-builder' ), $product->get_id(), $text );
			}
			// WooCommerce prints its notices through wp_kses_post(), and what was typed is in this one.
			WC_Admin_Meta_Boxes::add_error( esc_html( $text ) );
		}
	}

	/**
	 * The table and the size the chart field names, or null to leave what is stored.
	 *
	 * @param mixed $chart The field: '', `keep`, or a table's ID and a size with a colon between.
	 * @return array{table: int, size: string}|null
	 */
	private static function named( mixed $chart ): ?array {
		if ( ! is_string( $chart ) || self::KEEP === $chart ) {
			return null;
		}
		$parts = explode( ':', sanitize_text_field( $chart ), 2 );
		return array(
			'table' => absint( $parts[0] ),
			'size'  => $parts[1] ?? '',
		);
	}

	/**
	 * The panel's groups of fields for one product, each field with its value and where the value in use comes from.
	 *
	 * @param WC_Product $product The product or variation.
	 * @param string     $id      The prefix of each control's ID.
	 * @param string     $name    The name the fields are sent under.
	 * @param string     $chart   The name the chart field is sent under.
	 * @return list<array{label: string, open: bool, fields: list<array{id: string, name: string, label: string, value: string, note: string, options: array<int|string, string>|null, charts: array<string, array<string, string>>}>}>
	 */
	private function groups( WC_Product $product, string $id, string $name, string $chart ): array {
		$own    = StoredSpecs::fields( $product->get_meta( Options::META_SPECS, true ) );
		$notes  = $this->inUse( $product );
		$groups = array();
		foreach ( self::layout() as $label => $keys ) {
			$fields = array();
			$open   = false;
			if ( in_array( SpecKey::AxleWidth, $keys, true ) ) {
				$fields[] = $this->chartField( $product, $id . '-chart', $chart );
				$open     = '' !== $fields[0]['value'];
			}
			foreach ( $keys as $key ) {
				$value   = $own[ $key->value ] ?? '';
				$options = null;
				if ( SpecKind::Flag === $key->kind() ) {
					$options = array(
						'1' => __( 'Yes', 'creative-skateboard-builder' ),
						'0' => __( 'No', 'creative-skateboard-builder' ),
					);
				} elseif ( SpecKind::Choice === $key->kind() ) {
					$options = array();
					foreach ( $key->choices() as $choice ) {
						$options[ $choice ] = Labels::choice( $choice );
					}
				} elseif ( null !== $key->unit() ) {
					$value = Labels::typed( $value );
				}
				$open     = $open || '' !== $value || isset( $notes[ $key->value ] );
				$fields[] = array(
					'id'      => $id . '-' . str_replace( '_', '-', $key->value ),
					'name'    => $name . '[' . $key->value . ']',
					'label'   => Labels::field( $key ),
					'value'   => $value,
					'note'    => $notes[ $key->value ] ?? '',
					'options' => $options,
					'charts'  => array(),
				);
			}
			$groups[] = array(
				'label'  => $label,
				'open'   => $open,
				'fields' => $fields,
			);
		}
		return $groups;
	}

	/**
	 * The specs in the order the panel shows them, under the kind of part each belongs to. Every group is shown on
	 * every part until milestone 4, when a kit's slots say which specs a part needs.
	 *
	 * @return array<string, list<SpecKey>>
	 */
	private static function layout(): array {
		return array(
			__( 'Deck', 'creative-skateboard-builder' )   => array( SpecKey::DeckWidth, SpecKey::DeckLength, SpecKey::Wheelbase, SpecKey::HolePattern ),
			__( 'Trucks', 'creative-skateboard-builder' ) => array( SpecKey::AxleWidth, SpecKey::DeckMin, SpecKey::DeckMax, SpecKey::TruckHeight, SpecKey::HeightClass, SpecKey::MaxWheel, SpecKey::Baseplate, SpecKey::AxleDiameter ),
			__( 'Wheels', 'creative-skateboard-builder' ) => array( SpecKey::WheelDiameter ),
			__( 'Risers', 'creative-skateboard-builder' ) => array( SpecKey::RiserThickness ),
			__( 'Hardware', 'creative-skateboard-builder' ) => array( SpecKey::BoltLength ),
			__( 'Bearings', 'creative-skateboard-builder' ) => array( SpecKey::BearingBore, SpecKey::SpacersBuiltIn ),
			__( 'Grip tape', 'creative-skateboard-builder' ) => array( SpecKey::GripWidth, SpecKey::GripLength ),
			__( 'Sold in sets', 'creative-skateboard-builder' ) => array( SpecKey::SetSize ),
		);
	}

	/**
	 * The field that names a maker's chart and a size in it: one list, a group of sizes per chart.
	 *
	 * @param WC_Product $product The product or variation.
	 * @param string     $id      The control's ID.
	 * @param string     $name    The name it is sent under.
	 * @return array{id: string, name: string, label: string, value: string, note: string, options: array<int|string, string>, charts: array<string, array<string, string>>}
	 */
	private function chartField( WC_Product $product, string $id, string $name ): array {
		$named  = StoredSpecs::named( $product->get_meta( Options::META_SPEC_TABLE, true ) );
		$value  = 0 === $named['table'] && '' === $named['size'] ? '' : $named['table'] . ':' . $named['size'];
		$charts = array();
		$listed = '' === $value;
		foreach ( $this->tables() as $table => $chart ) {
			foreach ( $chart['table']->rows as $row ) {
				$charts[ $chart['name'] ][ $table . ':' . $row->size ] = $row->size;
				$listed = $listed || $table . ':' . $row->size === $value;
			}
		}
		$options = array( '' => __( 'None', 'creative-skateboard-builder' ) );
		if ( ! $listed ) {
			// A table alone, a size alone, or a name that leads nowhere: an import can store each, and the list cannot show it.
			$options[ self::KEEP ] = __( 'Leave as it is', 'creative-skateboard-builder' );
			$value                 = self::KEEP;
		}
		return array(
			'id'      => $id,
			'name'    => $name,
			'label'   => __( 'Maker\'s chart and size', 'creative-skateboard-builder' ),
			'value'   => $value,
			'note'    => __( 'A truck that names its size in a chart takes that row\'s figures.', 'creative-skateboard-builder' ),
			'options' => $options,
			'charts'  => $charts,
		);
	}

	/**
	 * For each spec whose value in use is not the part's own field: the value and where it comes from, as a
	 * sentence.
	 *
	 * @param WC_Product $product The product or variation; one not saved yet has nothing in use.
	 * @return array<string, string>
	 */
	private function inUse( WC_Product $product ): array {
		$id = $product->get_id();
		if ( $id <= 0 ) {
			return array();
		}
		if ( ! isset( $this->sourced[ $id ] ) ) {
			// WooCommerce draws a page of variations one after another. The first one asks the sources for it and
			// its siblings together, so each source loads the batch once and not once per variation.
			$together = array( $id );
			$parent   = $product->is_type( 'variation' ) ? wc_get_product( $product->get_parent_id() ) : null;
			foreach ( array_chunk( $parent instanceof WC_Product ? Cast::ints( $parent->get_children() ) : array(), self::TOGETHER ) as $siblings ) {
				if ( in_array( $id, $siblings, true ) ) {
					$together = $siblings;
				}
			}
			$this->sourced = array_replace( $this->sourced, $this->indexer->resolve( ...$together ) );
		}
		$notes = array();
		foreach ( $this->sourced[ $id ]->resolution->kept as $reading ) {
			if ( Origin::Field !== $reading->origin || '' !== $reading->ref ) {
				$notes[ $reading->key->value ] = $this->source( $reading );
			}
		}
		return $notes;
	}

	/**
	 * Where a value in use comes from.
	 *
	 * @param Reading $reading The value kept.
	 */
	private function source( Reading $reading ): string {
		$value = Labels::value( $reading->key, $reading->text );
		if ( Origin::Attribute === $reading->origin ) {
			if ( str_starts_with( $reading->ref, SpecSource::PARENT_ATTRIBUTE ) ) {
				/* translators: 1: a spec's value with its unit, such as 32 in. 2: a product attribute's name, such as Length. */
				return sprintf( __( 'In use: %1$s, from the parent product\'s %2$s attribute.', 'creative-skateboard-builder' ), $value, wc_attribute_label( substr( $reading->ref, strlen( SpecSource::PARENT_ATTRIBUTE ) ) ) );
			}
			/* translators: 1: a spec's value with its unit, such as 8.25 in. 2: a product attribute's name, such as Width. */
			return sprintf( __( 'In use: %1$s, from the %2$s attribute.', 'creative-skateboard-builder' ), $value, wc_attribute_label( $reading->ref ) );
		}
		if ( Origin::Table === $reading->origin ) {
			$parts = explode( ':', $reading->ref, 2 );
			$chart = $this->tables()[ (int) $parts[0] ]['name'] ?? $parts[0];
			/* translators: 1: a spec's value with its unit, such as 8.5 in. 2: a truck maker's name, such as Independent. 3: a size in the maker's chart, such as 149. */
			return sprintf( __( 'In use: %1$s, from %2$s\'s chart, size %3$s.', 'creative-skateboard-builder' ), $value, $chart, $parts[1] ?? '' );
		}
		/* translators: %s: a spec's value with its unit, such as 8.25 in. */
		return sprintf( __( 'In use: %s, from the parent product.', 'creative-skateboard-builder' ), $value );
	}

	/**
	 * The makers' charts, asked for once however many variations are drawn.
	 *
	 * @return array<int, array{name: string, table: \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable}>
	 */
	private function tables(): array {
		$this->tables ??= $this->charts->all();
		return $this->tables;
	}

	/**
	 * What was wrong with one value, as a sentence.
	 *
	 * @param string $key  The spec key, or `SpecWriter::TABLE`.
	 * @param string $code The problem's code.
	 * @param string $text What was typed.
	 */
	private static function problem( string $key, string $code, string $text ): string {
		if ( SpecWriter::TABLE === $key ) {
			return match ( $code ) {
				/* translators: %s: the size that was chosen. */
				SpecWriter::SIZE_MISSING => sprintf( __( 'The maker\'s chart was not saved: the chart has no size %s now. Choose again.', 'creative-skateboard-builder' ), $text ),
				SpecWriter::TABLE_MISSING => __( 'The maker\'s chart was not saved: that chart is no longer there. Choose again.', 'creative-skateboard-builder' ),
				default => __( 'The maker\'s chart was not saved. Choose again.', 'creative-skateboard-builder' ),
			};
		}
		$spec  = SpecKey::tryFrom( $key );
		$label = null === $spec ? $key : Labels::spec( $spec );
		return match ( $code ) {
			/* translators: 1: a deck's width with its unit, such as 8.6 in. 2: another, such as 8.375 in. */
			SpecWriter::RANGE => sprintf( __( 'Narrowest deck and Widest deck were not saved: the narrowest, %1$s, is wider than the widest, %2$s.', 'creative-skateboard-builder' ), ...Sentences::ends( $text ) ),
			/* translators: 1: a spec's name. 2: what was typed. */
			MeasureError::Malformed->value => sprintf( __( '%1$s was not saved: "%2$s" is not a measurement. Type a number, or a fraction such as 8 1/4.', 'creative-skateboard-builder' ), $label, $text ),
			/* translators: 1: a spec's name. 2: what was typed. */
			MeasureError::ZeroDenominator->value => sprintf( __( '%1$s was not saved: the fraction in "%2$s" divides by zero.', 'creative-skateboard-builder' ), $label, $text ),
			/* translators: 1: a spec's name. 2: what was typed. */
			MeasureError::Negative->value => sprintf( __( '%1$s was not saved: "%2$s" is below zero.', 'creative-skateboard-builder' ), $label, $text ),
			/* translators: 1: a spec's name. 2: what was typed. */
			SpecError::NotPositive->value => sprintf( __( '%1$s was not saved: "%2$s" must be more than zero.', 'creative-skateboard-builder' ), $label, $text ),
			/* translators: 1: a spec's name. 2: what was typed. */
			SpecError::TooMany->value => sprintf( __( '%1$s was not saved: "%2$s" is too large a number.', 'creative-skateboard-builder' ), $label, $text ),
			/* translators: 1: a spec's name. 2: what was typed. */
			default => sprintf( __( '%1$s was not saved: "%2$s" is not a value it takes.', 'creative-skateboard-builder' ), $label, $text ),
		};
	}
}
