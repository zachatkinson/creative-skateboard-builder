<?php
/**
 * The Part specs panel.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerImage;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Reading;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecError;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKind;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Domain\Spec\StoredSpecs;
use CreativeBranding\SkateboardBuilder\Port\SpecSource;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\LayerCheck;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\LayerMeta;
use CreativeBranding\SkateboardBuilder\Support\NotOffered;
use CreativeBranding\SkateboardBuilder\Support\PartRank;
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
	private const STYLES = '.cbuild-part-specs summary{padding:8px 12px;cursor:pointer;font-weight:600}'
		// The layer's preview: the part's box at the template's scale, on a chequer that shows what is transparent.
		. '.cbuild-layer__preview{margin:0 12px 12px;display:flex;flex-direction:column;gap:6px}'
		. '.cbuild-layer__box{box-sizing:content-box;border:1px dashed #757575;background:repeating-conic-gradient(#ddd 0 25%,#fff 0 50%) 0 0/16px 16px}'
		. '.cbuild-layer__box img{display:block;inline-size:100%;block-size:100%;object-fit:contain}';

	/**
	 * The preview's scale: pixels on the screen to the millimetre of the part, so a 32 in deck is drawn 406 pixels long.
	 */
	public const PREVIEW = 0.5;


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
	 * The panel's style handle, the plugin's own (D-450).
	 */
	private const STYLE = 'cbuild-part-specs';

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
	 * The prefix of each control's ID in the product's panel. The screen's script opens a field an address names by it.
	 */
	private const FIELDS = 'cbuild-spec';

	/**
	 * The names "Not offered in the builder" is sent under, on the product and on each variation (1.2.0, #116).
	 */
	private const OFFER = 'cbuild_not_offered';

	private const VARIATION_OFFER = 'cbuild_variation_not_offered';

	/**
	 * The names the part's rank is sent under, on the product and on each variation (1.3.0, #135, D-395).
	 */
	private const RANK = 'cbuild_rank';

	private const VARIATION_RANK = 'cbuild_variation_rank';

	/**
	 * The slot whose parts need each group, by the group's first spec. Sets are any part's.
	 */
	private const array SLOTS = array(
		'deck_width'      => 'deck',
		'axle_width'      => 'trucks',
		'wheel_diameter'  => 'wheels',
		'riser_thickness' => 'risers',
		'bolt_length'     => 'hardware',
		'bearing_bore'    => 'bearings',
		'grip_width'      => 'grip',
	);

	/**
	 * The shop's kits, once read.
	 *
	 * @var list<\CreativeBranding\SkateboardBuilder\Domain\Kit\Kit>|null
	 */
	private ?array $read = null;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecWriter  $writer  Checks what is typed.
	 * @param SpecIndexer $indexer Says which value is in use, and from where.
	 * @param SpecTables  $charts  The makers' charts.
	 * @param Kits        $kits    The kits, for the slot a product fills.
	 */
	public function __construct( private SpecWriter $writer, private SpecIndexer $indexer, private SpecTables $charts, private Kits $kits ) {}

	/**
	 * The Media Library's images, for the Layer image field, since 0.7.0, given by the container through
	 * `withImages()`. A panel that an earlier release's container builds, in the request that replaces that release's
	 * files with these, has none, and leaves the field as it is (D-208).
	 *
	 * @var LayerImages|null
	 */
	private ?LayerImages $images = null;

	/**
	 * The layer image check, made once the images are known.
	 *
	 * @var LayerCheck|null
	 */
	private ?LayerCheck $check = null;

	/**
	 * The same panel, checking the Layer image field.
	 *
	 * @since 0.7.0
	 *
	 * @param LayerImages $images The Media Library's images.
	 */
	public function withImages( LayerImages $images ): self {
		$panel         = clone $this;
		$panel->images = $images;
		return $panel;
	}

	/**
	 * Style the groups' headings, and open the tab a link names.
	 *
	 * @since 0.3.0
	 */
	public function styles(): void {
		// A handle of the plugin's own with no file, not one another enqueues: WordPress prints what is added to it
		// and asks for nothing (D-450).
		wp_register_style( self::STYLE, false, array(), CBUILD_VERSION );
		wp_add_inline_style( self::STYLE, self::STYLES );
		wp_enqueue_style( self::STYLE );
		// A checkout that was never built has no script; the screen works without it, and the zip is checked for it.
		$built = is_readable( CBUILD_PATH . self::BUILT . '.asset.php' ) ? require CBUILD_PATH . self::BUILT . '.asset.php' : null;
		if ( ! is_array( $built ) || ! is_string( $built['version'] ?? null ) ) {
			return;
		}
		// The Layer image field's button opens the media modal, whose scripts come before this one (from 0.7.0).
		wp_enqueue_media();
		wp_enqueue_script( self::SCRIPT, CBUILD_URL . self::BUILT . '.js', array( 'jquery', 'wc-admin-variation-meta-boxes', 'media-views' ), $built['version'], array( 'in_footer' => true ) );
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
		$groups = $this->groups( $product_object, self::FIELDS, 'cbuild_specs', 'cbuild_spec_table' );
		$layer  = array() === $groups ? null : $this->layer( $product_object, self::FIELDS, 'cbuild_layer' );
		$offer  = self::offer( $product_object, $groups, self::FIELDS, self::OFFER );
		$rank   = self::rank( $product_object, $groups, self::FIELDS, self::RANK );
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
		$groups = $this->groups( $product, self::FIELDS . '-' . $loop, 'cbuild_variation_specs[' . $loop . ']', 'cbuild_variation_spec_table[' . $loop . ']' );
		$offer  = self::offer( $product, $groups, self::FIELDS . '-' . $loop, self::VARIATION_OFFER . '[' . $loop . ']' );
		if ( null === $offer ) {
			return;
		}
		$layer = array() === $groups ? null : $this->layer( $product, self::FIELDS . '-' . $loop, 'cbuild_variation_layer[' . $loop . ']' );
		$rank  = self::rank( $product, $groups, self::FIELDS . '-' . $loop, self::VARIATION_RANK . '[' . $loop . ']' );
		$whole = false;
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
		$layer = isset( $_POST['cbuild_layer'] ) ? wp_unslash( $_POST['cbuild_layer'] ) : null;
		$offer = isset( $_POST[ self::OFFER ] ) ? wp_unslash( $_POST[ self::OFFER ] ) : null;
		$rank  = isset( $_POST[ self::RANK ] ) ? wp_unslash( $_POST[ self::RANK ] ) : null;
		// The product data box was sent, so a box not ticked is unticked; a save without it, as another plugin's, is not.
		$form = isset( $_POST['product-type'] );
		// phpcs:enable
		if ( null !== $sent ) {
			$this->store( $product, $sent, $chart );
		}
		$this->storeLayer( $product, $layer );
		self::storeOffer( $product, $form, $offer );
		self::storeRank( $product, $rank );
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
		$layers = isset( $_POST['cbuild_variation_layer'] ) && is_array( $_POST['cbuild_variation_layer'] ) ? wp_unslash( $_POST['cbuild_variation_layer'] ) : array();
		$offers = isset( $_POST[ self::VARIATION_OFFER ] ) && is_array( $_POST[ self::VARIATION_OFFER ] ) ? wp_unslash( $_POST[ self::VARIATION_OFFER ] ) : array();
		$ranks  = isset( $_POST[ self::VARIATION_RANK ] ) && is_array( $_POST[ self::VARIATION_RANK ] ) ? wp_unslash( $_POST[ self::VARIATION_RANK ] ) : array();
		// phpcs:enable
		if ( isset( $rows[ $loop ] ) && is_array( $rows[ $loop ] ) ) {
			$this->store( $variation, $rows[ $loop ], $charts[ $loop ] ?? null );
		}
		$this->storeLayer( $variation, $layers[ $loop ] ?? null );
		// WooCommerce saves the variations its form sent, so a box not ticked in one is unticked.
		self::storeOffer( $variation, true, $offers[ $loop ] ?? null );
		self::storeRank( $variation, $ranks[ $loop ] ?? null );
	}

	/**
	 * The part's rank in its slot (1.3.0, #135, D-395): drawn where the panel has fields, and where the part has one,
	 * so it can be cleared on a part no kit takes now.
	 *
	 * @param WC_Product  $product The product or variation.
	 * @param list<mixed> $groups  The panel's groups of fields for it.
	 * @param string      $prefix  The prefix of each control's ID.
	 * @param string      $name    The name the field is sent under.
	 * @return array{id: string, name: string, value: string, open: bool, label: string, note: string}|null
	 */
	private static function rank( WC_Product $product, array $groups, string $prefix, string $name ): ?array {
		$own = PartRank::own( $product );
		if ( array() === $groups && null === $own ) {
			return null;
		}
		return array(
			'id'    => self::field( $prefix, 'rank' ),
			'name'  => $name,
			'value' => null === $own ? '' : (string) $own,
			'open'  => null !== $own,
			'label' => __( 'Rank in its slot', 'creative-skateboard-builder' ),
			'note'  => $product->is_type( 'variation' )
				? __( 'A whole number, higher being better. Left empty, the variation takes its product\'s rank.', 'creative-skateboard-builder' )
				: __( 'A whole number, higher being better. When the builder offers a better part, it offers the next rank up, and where no part in the slot has a rank, the next price up. Leave it empty for none.', 'creative-skateboard-builder' ),
		);
	}

	/**
	 * Put the rank sent on the product WooCommerce is about to write: a whole number from 0 to 999,999,999 sets it, an
	 * empty field takes it off, and anything else is not saved. A save with no field, as another plugin's, leaves it.
	 *
	 * @param WC_Product $product The product or variation.
	 * @param mixed      $sent    What the field sent, unslashed, or null.
	 */
	private static function storeRank( WC_Product $product, mixed $sent ): void {
		if ( ! is_string( $sent ) ) {
			return;
		}
		$rank = trim( sanitize_text_field( $sent ) );
		if ( '' === $rank ) {
			$product->delete_meta_data( Options::META_RANK );
		} elseif ( 1 === preg_match( PartRank::PATTERN, $rank ) ) {
			$product->update_meta_data( Options::META_RANK, $rank );
		}
	}

	/**
	 * "Not offered in the builder" (1.2.0, #116, D-351): drawn where the panel has fields, and where it is ticked, so
	 * it can be unticked on a part no kit takes now.
	 *
	 * @param WC_Product  $product The product or variation.
	 * @param list<mixed> $groups  The panel's groups of fields for it.
	 * @param string      $prefix  The prefix of each control's ID.
	 * @param string      $name    The name the box is sent under.
	 * @return array{id: string, name: string, checked: bool, label: string, note: string}|null
	 */
	private static function offer( WC_Product $product, array $groups, string $prefix, string $name ): ?array {
		$checked = NotOffered::own( $product );
		if ( array() === $groups && ! $checked ) {
			return null;
		}
		return array(
			'id'      => self::field( $prefix, 'not_offered' ),
			'name'    => $name,
			'checked' => $checked,
			'label'   => __( 'Not offered in the builder', 'creative-skateboard-builder' ),
			'note'    => $product->is_type( 'variable' )
				? __( 'Ticked, no kit offers this product\'s variations, whatever their own box says. It stays in the shop.', 'creative-skateboard-builder' )
				: __( 'Ticked, no kit offers this part. It stays in the shop, and the Catalogue check lists it.', 'creative-skateboard-builder' ),
		);
	}

	/**
	 * Tick or untick the box on the product WooCommerce is about to write. Only `1` ticks it. A box not ticked sends
	 * nothing, so on a form that held it, it is unticked, and a save of no such form leaves it. A change tells what is
	 * kept of the part, as `cbuild_parts_changed` does.
	 *
	 * @param WC_Product $product The product or variation.
	 * @param bool       $form    Whether the form the box is on was sent.
	 * @param mixed      $sent    What the box sent, unslashed, or null.
	 */
	private static function storeOffer( WC_Product $product, bool $form, mixed $sent ): void {
		if ( ! $form && null === $sent ) {
			return;
		}
		$ticked = is_string( $sent ) && '1' === sanitize_text_field( $sent );
		$was    = NotOffered::own( $product );
		if ( $was === $ticked ) {
			return;
		}
		if ( $ticked ) {
			$product->update_meta_data( Options::META_NOT_OFFERED, 'yes' );
		} else {
			$product->delete_meta_data( Options::META_NOT_OFFERED );
		}
		/** This action is documented in src/Hooks/CardHooks.php */
		do_action( 'cbuild_parts_changed', array( $product->get_id() ) );
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

		$kept = StoredSpecs::kept( $draft->fields, $draft->table, $draft->size );
		foreach ( array(
			Options::META_SPECS      => $kept['fields'],
			Options::META_SPEC_TABLE => $kept['named'],
		) as $key => $value ) {
			if ( null === $value ) {
				$product->delete_meta_data( $key );
			} else {
				$product->update_meta_data( $key, $value );
			}
		}

		foreach ( $draft->problems as $problem ) {
			$text = self::problem( $problem['key'], $problem['code'], $problem['text'] );
			if ( $product->is_type( 'variation' ) ) {
				/* translators: 1: a variation's ID. 2: a sentence saying what was not saved. */
				$text = sprintf( __( 'Variation #%1$d: %2$s', 'creative-skateboard-builder' ), $product->get_id(), $text );
			}
			// Each notice is a link to its field, which the screen's script opens and moves focus to. A variation's
			// row is loaded later and numbered by its place on the page, so its notice leads to the variation.
			$field = $product->is_type( 'variation' ) ? 'cbuild_variation_' . $product->get_id() : self::field( self::FIELDS, $problem['key'] );
			// WooCommerce prints its notices through wp_kses_post(), and what was typed is in this one.
			WC_Admin_Meta_Boxes::add_error( sprintf( '<a href="#%s">%s</a>', esc_attr( $field ), esc_html( $text ) ) );
		}
	}

	/**
	 * Check the Layer image field and put what passes on the product; say what did not. Empty clears it. A field not
	 * sent, as from a group left closed, changes nothing.
	 *
	 * @param WC_Product $product The product or variation about to be saved.
	 * @param mixed      $sent    The field, unslashed.
	 */
	private function storeLayer( WC_Product $product, mixed $sent ): void {
		if ( ! is_string( $sent ) || null === $this->images ) {
			return;
		}
		$text = trim( sanitize_text_field( $sent ) );
		if ( '' === $text ) {
			$product->delete_meta_data( Options::META_LAYER );
			return;
		}
		$id = ctype_digit( $text ) ? (int) $text : 0;
		// The image the part already has is no change, so it is not checked again: one set under 1.0.x's sizes, as a
		// grip's swatch, keeps the product saving as it did (the auditor's 1.1.0 should-fix 1, D-307). The CSV does the
		// same (SpecImport::layer()).
		if ( $id > 0 && LayerMeta::own( $product ) === $id ) {
			return;
		}
		$texts = array();
		$check = $this->check();
		foreach ( 0 === $id || null === $check ? array(
			array(
				'code' => LayerCheck::NOT_IMAGE,
				'text' => $text,
			),
		) : $check->problems( $id, $this->size( $product ), $this->cutOut( $product ) ) as $problem ) {
			$texts[] = (string) Sentences::layer( $problem['code'], LayerCheck::NOT_IMAGE === $problem['code'] ? $text : $problem['text'] );
		}
		if ( array() === $texts ) {
			$product->update_meta_data( Options::META_LAYER, (string) $id );
			return;
		}
		foreach ( $texts as $words ) {
			if ( $product->is_type( 'variation' ) ) {
				/* translators: 1: a variation's ID. 2: a sentence saying what was not saved. */
				$words = sprintf( __( 'Variation #%1$d: %2$s', 'creative-skateboard-builder' ), $product->get_id(), $words );
			}
			$field = $product->is_type( 'variation' ) ? 'cbuild_variation_' . $product->get_id() : self::field( self::FIELDS, 'layer' );
			WC_Admin_Meta_Boxes::add_error( sprintf( '<a href="#%s">%s</a>', esc_attr( $field ), esc_html( $words ) ) );
		}
	}

	/**
	 * The layer image check, from the panel's own services and the Media Library's images (D-287).
	 */
	private function check(): ?LayerCheck {
		if ( null !== $this->images ) {
			$this->check ??= new LayerCheck( $this->images, $this->indexer, $this->kits );
		}
		return $this->check;
	}

	/**
	 * The size the picture draws a part at, from the first slot it fills that the picture draws and the specs in use.
	 *
	 * @param WC_Product $product The product or variation.
	 * @return array{int, int}|null Null when it fills no such slot, or lacks the spec the size comes from.
	 */
	private function size( WC_Product $product ): ?array {
		$slots = array_values( array_intersect( Layout::DRAWN, $this->slots( $product->get_id() ) ) );
		if ( array() === $slots || $product->get_id() <= 0 ) {
			return null;
		}
		// The page's variations were resolved together (sourced()); one saved alone is resolved here, once.
		$this->sourced[ $product->get_id() ] ??= $this->indexer->resolve( $product->get_id() )[ $product->get_id() ];
		$texts                                 = array();
		foreach ( $this->sourced[ $product->get_id() ]->resolution->rows() as $row ) {
			$texts[ $row->key->value ] = $row->text;
		}
		return Layout::drawn( $slots[0], Specs::fromIndex( $texts ) );
	}

	/**
	 * The Layer image field for one product: its value, whether its group is open, its note and its preview.
	 *
	 * @param WC_Product $product The product or variation.
	 * @param string     $prefix  The prefix of the product's or the variation's controls.
	 * @param string     $name    The name it is sent under.
	 * @return array{id: string, name: string, value: string, open: bool, note: string, src: string, box: array{int, int}|null, caption: string, choose: string, title: string, types: list<string>}
	 */
	private function layer( WC_Product $product, string $prefix, string $name ): array {
		$own   = LayerMeta::own( $product );
		$size  = $this->size( $product );
		$shown = $own;
		$sheet = ! $this->cutOut( $product );
		if ( $sheet ) {
			// A grip's image is its whole sheet, shot flat, not a cut-out (F18, #103).
			$note = $product->is_type( 'variation' )
				? __( 'The whole sheet of grip, shot flat from above, a PNG, WebP or AVIF from the Media Library. Choose one, or type its attachment ID. Left empty, the variation takes its parent product\'s, or the picture shows its labelled photo. The product image and gallery stay as they are: only the builder\'s picture uses this image.', 'creative-skateboard-builder' )
				: __( 'The whole sheet of grip, shot flat from above, a PNG, WebP or AVIF from the Media Library. Choose one, or type its attachment ID. Left empty, the picture shows the part\'s labelled photo. The product image and gallery stay as they are: only the builder\'s picture uses this image.', 'creative-skateboard-builder' );
		} else {
			$note = $product->is_type( 'variation' )
				? __( 'The part\'s cut-out on a transparent background, a PNG, WebP or AVIF from the Media Library. Choose one, or type its attachment ID. Left empty, the variation takes its parent product\'s, or the picture shows its labelled photo. The product image and gallery stay as they are: only the builder\'s picture uses this image.', 'creative-skateboard-builder' )
				: __( 'The part\'s cut-out on a transparent background, a PNG, WebP or AVIF from the Media Library. Choose one, or type its attachment ID. Left empty, the picture shows the part\'s labelled photo. The product image and gallery stay as they are: only the builder\'s picture uses this image.', 'creative-skateboard-builder' );
		}
		if ( 0 === $own && $product->is_type( 'variation' ) ) {
			$whole  = wc_get_product( $product->get_parent_id() );
			$parent = $whole instanceof WC_Product ? LayerMeta::own( $whole ) : 0;
			if ( $parent > 0 ) {
				$shown = $parent;
				/* translators: %d: an attachment's ID. */
				$note .= ' ' . sprintf( __( 'In use: image #%d, from the parent product.', 'creative-skateboard-builder' ), $parent );
			}
		}
		$caption = '';
		if ( null !== $size ) {
			$needs = LayerImage::needs( $size );
			/* translators: 1: a part's width in millimetres. 2: its length. 3: a number of pixels. 4: another. */
			$caption = sprintf( __( 'Drawn %1$s by %2$s mm, nose up. The image needs at least %3$s pixels across or %4$s along.', 'creative-skateboard-builder' ), wc_format_localized_decimal( (string) round( $size[0] / 10000, 1 ) ), wc_format_localized_decimal( (string) round( $size[1] / 10000, 1 ) ), number_format_i18n( $needs[0] ), number_format_i18n( $needs[1] ) );
		}
		$src = $shown > 0 ? wp_get_attachment_image_url( $shown, 'full' ) : false;
		return array(
			'id'      => self::field( $prefix, 'layer' ),
			'name'    => $name,
			'value'   => 0 === $own ? '' : (string) $own,
			// Open where the picture draws the part, or it has an image: the same rule as the specs' groups (issue #16).
			'open'    => 0 !== $own || array() !== array_intersect( Layout::DRAWN, $this->slots( $product->get_id() ) ),
			'note'    => $note,
			'src'     => is_string( $src ) ? $src : '',
			'box'     => null === $size ? null : array( (int) ceil( $size[0] / 10000 * self::PREVIEW ), (int) ceil( $size[1] / 10000 * self::PREVIEW ) ),
			'caption' => $caption,
			// Named for the part: a page of variations has a button each (the auditor's 1.0.0 P5).
			/* translators: %s: the product's or variation's name. */
			'choose'  => sprintf( __( 'Choose an image for %s', 'creative-skateboard-builder' ), $product->get_name() ),
			'title'   => __( 'Layer image', 'creative-skateboard-builder' ),
			// The chooser's filter: the domain's one list of layer types (D-210).
			'types'   => LayerImage::TYPES,
		);
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
	 * @return list<array{label: string, open: bool, fields: list<array{id: string, name: string, label: string, value: string, note: string, options: array<int|string, string>|null, charts: array<string, array<string, string>>}>}> None when no kit takes the product and it holds nothing.
	 */
	private function groups( WC_Product $product, string $id, string $name, string $chart ): array {
		$own    = StoredSpecs::fields( $product->get_meta( Options::META_SPECS, true ) );
		$notes  = $this->inUse( $product );
		$groups = array();
		$slots  = $this->slots( $product->get_id() );
		$sets   = $this->inSets( $slots );
		foreach ( self::layout() as $label => $keys ) {
			$fields = array();
			// The group a kit's slot needs of this part is open (issue #16), and so is one with a value. A slot that
			// takes more than one unit needs the part's Number in a set (R7), so Sold in sets opens for it (issue #61).
			$open = in_array( self::SLOTS[ $keys[0]->value ] ?? '', $slots, true ) || ( $sets && SpecKey::SetSize === $keys[0] );
			if ( in_array( SpecKey::AxleWidth, $keys, true ) ) {
				$fields[] = $this->chartField( $product, self::field( $id, SpecWriter::TABLE ), $chart );
				$open     = $open || '' !== $fields[0]['value'];
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
					'id'      => self::field( $id, $key->value ),
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
		// Where the shop has kits and none takes this product, the builder needs nothing of it: no fields, unless
		// it holds a value all the same, which must be seen to be cleared (issue #23). Before the first kit there
		// is no telling, and every product has its fields.
		if ( array() !== $this->read && array() === $slots && ! in_array( true, array_column( $groups, 'open' ), true ) ) {
			return array();
		}
		return $groups;
	}

	/**
	 * Whether the part's layer image is a cut-out with clear corners: not a grip's sheet (F18, #103). A part in no drawn
	 * slot is held to the cut-out's rule.
	 *
	 * @param WC_Product $product The product or variation.
	 */
	private function cutOut( WC_Product $product ): bool {
		return LayerCheck::cutOutIn( $this->slots( $product->get_id() ) );
	}

	/**
	 * The slots, in any kit, that take this product. The kits are read once for a screen of variations.
	 *
	 * @param int $product The product's or variation's ID.
	 * @return list<string>
	 */
	private function slots( int $product ): array {
		$this->read ??= array_values( array_column( $this->kits->all(), 'kit' ) );
		return $this->kits->slotsOf( $product, ...$this->read );
	}

	/**
	 * Whether a kit slot that takes the part takes more than one unit of it, so R7 needs its Number in a set.
	 *
	 * @param list<string> $slots The keys of the slots that take the part.
	 */
	private function inSets( array $slots ): bool {
		foreach ( $this->read ?? array() as $kit ) {
			foreach ( $kit->slots->all() as $slot ) {
				if ( $slot->units > 1 && in_array( $slot->key, $slots, true ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * The specs in the order the panel shows them, under the kind of part each belongs to. Every group is shown on
	 * every part, and the one its kit slot needs is open (SLOTS).
	 *
	 * @return array<string, list<SpecKey>>
	 */
	private static function layout(): array {
		return array(
			__( 'Deck', 'creative-skateboard-builder' )   => array( SpecKey::DeckWidth, SpecKey::DeckLength, SpecKey::Wheelbase, SpecKey::HolePattern, SpecKey::NoseLength, SpecKey::TailLength, SpecKey::ConcaveDepth, SpecKey::KickAngle ),
			__( 'Trucks', 'creative-skateboard-builder' ) => array( SpecKey::AxleWidth, SpecKey::DeckMin, SpecKey::DeckMax, SpecKey::TruckHeight, SpecKey::HeightClass, SpecKey::MaxWheel, SpecKey::Baseplate, SpecKey::AxleDiameter ),
			__( 'Wheels', 'creative-skateboard-builder' ) => array( SpecKey::WheelDiameter, SpecKey::WheelWidth, SpecKey::BearingsIncluded ),
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
	 * A control's ID: the prefix, then the spec key with hyphens, or `chart` for the maker's chart.
	 *
	 * @param string $prefix The prefix of the product's or the variation's controls.
	 * @param string $key    The spec key, or `SpecWriter::TABLE`.
	 */
	private static function field( string $prefix, string $key ): string {
		return $prefix . '-' . ( SpecWriter::TABLE === $key ? 'chart' : str_replace( '_', '-', $key ) );
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
