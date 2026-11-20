<?php
/**
 * The Build tab on a kit's product screen.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Assembly;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Preset;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitLayout;
use CreativeBranding\SkateboardBuilder\Domain\Kit\RuleSettings;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Service\KitEditor;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\StoredKit;
use WC_Admin_Meta_Boxes;
use WC_Product;
use WP_Term;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit's slots, rules, presets and assembly settings, on a tab of its product data box (spec: Admin, The kit's
 * Build tab). The tab sends fields, `Service\KitEditor` makes them the kit's documents and says what is wrong,
 * and what passes goes onto the product WooCommerce is about to write, so it is stored with the same save. A section
 * that is refused is not saved: the notices name each problem with a link to its field, and the tab shows the section
 * once more as it was typed.
 *
 * Only a kit's screen holds the fields. Any other product's form gets a sentence in their place, so the tab adds
 * nothing to a form that is already long (issue #23).
 *
 * @since 0.4.0
 */
final class BuildTab {

	/**
	 * The panel's ID, which the tab opens and the screen's script finds.
	 *
	 * @since 0.4.0
	 */
	public const PANEL = 'cbuild_build';

	/**
	 * The name the tab's fields are sent under.
	 *
	 * @since 0.4.0
	 */
	public const FIELD = 'cbuild_kit';

	/**
	 * The panel's last field. PHP keeps the first `max_input_vars` fields of a form and drops the rest with no error,
	 * and a field that is not sent reads as a choice: a rule off, a slot or a preset gone. A post without this one was
	 * cut short, and none of it is saved (Claude in Cowork's review of milestone 4, D-122).
	 */
	public const END = 'end';

	/**
	 * The prefix of every control's ID. After it come the document's name and the field's path in the document, so
	 * a problem's path leads to its field.
	 */
	private const IDS = 'cbuild-kit';

	/**
	 * The tab's script, built from assets/src/admin/build-tab.ts: it moves a slot's row and opens the field a
	 * notice's link names. It builds no markup. jQuery UI's sortable, which WooCommerce's product screen already
	 * loads, drags the rows.
	 */
	private const SCRIPT = 'cbuild-build-tab';
	private const BUILT  = 'build/admin/build-tab';

	/**
	 * The tab's styles, added to WooCommerce's admin style sheet. WooCommerce floats a field's label into a column
	 * 150 pixels wide, which these labels do not fit, least of all at phone width or in translation, so each label
	 * stands above its field. Then a row's outline, its buttons in a line with targets 30 pixels high, a group's
	 * heading 34 pixels high, and the list of what was not saved.
	 */
	private const STYLES = '.cbuild-kit .cbuild-kit-group{margin:0 12px 12px;padding:0 0 8px;border:1px solid #c3c4c7}'
		. '.cbuild-kit legend{float:none;width:auto;margin:0;margin-inline-start:8px;padding:0 4px;font-weight:600}'
		. '.cbuild-kit h3{margin:0;padding:12px}'
		. '.cbuild-kit p.form-field{margin:0;padding:6px 12px!important}'
		. '.cbuild-kit p.form-field label{float:none;display:block;width:auto;margin:0 0 4px}'
		. '.cbuild-kit p.form-field input.checkbox{float:none;margin:0;margin-inline-end:6px}'
		. '.cbuild-kit p.form-field input.checkbox+label{display:inline}'
		. '.cbuild-kit p.form-field .short,.cbuild-kit p.form-field .select2-container{float:none;width:100%!important;max-width:30em;box-sizing:border-box}'
		. '.cbuild-kit p.form-field .description{display:block;clear:both;margin:4px 0 0}'
		. '.cbuild-kit-moves{display:flex;flex-wrap:wrap;gap:8px;align-items:center;margin:0;padding:8px 12px}'
		. '.cbuild-kit-moves .button{min-height:30px}'
		. '.cbuild-kit-handle{cursor:move;padding:6px}'
		. '.cbuild-kit-problems{margin:0 12px 12px;padding:8px 12px;border-inline-start:4px solid #d63638;background:#fcf0f1}'
		. '.cbuild-kit summary{padding:8px 12px;cursor:pointer;font-weight:600}';

	/**
	 * How long a refused section is kept as typed, in seconds: until the screen is next shown, and an hour at most.
	 */
	private const KEPT = 3600;

	/**
	 * How many empty rows the riser suggestions end with.
	 */
	private const SPARE = 2;

	/**
	 * Fields that take more than one line.
	 */
	private const LINES = array( 'help', 'returns_notice' );

	/**
	 * What was not saved, by the ID of the field or of the row it belongs to: the ID of the sentence that says why.
	 *
	 * @var array<string, string>
	 */
	private array $errors = array();

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param KitEditor    $editor   Checks what is sent.
	 * @param ShopSettings $settings The shop's settings, for what a new kit's assembly starts from.
	 */
	public function __construct( private KitEditor $editor, private ShopSettings $settings ) {}

	/**
	 * Style the tab and load its script.
	 *
	 * @since 0.4.0
	 */
	public function styles(): void {
		wp_add_inline_style( 'woocommerce_admin_styles', self::STYLES );
		// A checkout that was never built has no script; the tab saves without it, and the zip is checked for it.
		$built = is_readable( CBUILD_PATH . self::BUILT . '.asset.php' ) ? require CBUILD_PATH . self::BUILT . '.asset.php' : null;
		if ( ! is_array( $built ) || ! is_string( $built['version'] ?? null ) ) {
			return;
		}
		// A preset's Photo opens the media library's modal, whose scripts come before this one (D-252).
		wp_enqueue_media();
		wp_enqueue_script( self::SCRIPT, CBUILD_URL . self::BUILT . '.js', array( 'jquery', 'jquery-ui-sortable', 'wp-a11y', 'wc-admin-product-meta-boxes', 'media-views' ), $built['version'], array( 'in_footer' => true ) );
	}

	/**
	 * The tab, on kits alone.
	 *
	 * @since 0.4.0
	 *
	 * @param array<mixed> $tabs The product data box's tabs.
	 * @return array<mixed>
	 */
	public function tab( array $tabs ): array {
		// Drawn for who can save it: the save below takes nothing from anyone else.
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return $tabs;
		}
		$tabs[ self::PANEL ] = array(
			'label'    => __( 'Build', 'creative-skateboard-builder' ),
			'target'   => self::PANEL,
			'class'    => array( 'show_if_' . Options::PRODUCT_TYPE_KIT ),
			'priority' => 15,
		);
		return $tabs;
	}

	/**
	 * The tab's panel, for the product on the screen.
	 *
	 * @since 0.4.0
	 */
	public function panel(): void {
		global $product_object;
		if ( ! $product_object instanceof WC_Product || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$marker   = self::FIELD . '[' . self::END . ']';
		$sections = $product_object->is_type( Options::PRODUCT_TYPE_KIT ) ? $this->sections( $product_object ) : array();
		include CBUILD_PATH . 'templates/admin/build-tab.php';
	}

	/**
	 * Save the tab into the kit WooCommerce is about to write.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $product The product.
	 */
	public function save( mixed $product ): void {
		// Any code can fire the hook, so the capability is checked here as well as by WooCommerce.
		if ( ! $product instanceof WC_Product || ! $product->is_type( Options::PRODUCT_TYPE_KIT ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Missing -- WooCommerce checks woocommerce_meta_nonce before it fires this hook (includes/admin/class-wc-admin-meta-boxes.php:228).
		// phpcs:disable WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each value is sanitised in clean().
		$sent = isset( $_POST[ self::FIELD ] ) && is_array( $_POST[ self::FIELD ] ) ? self::clean( wp_unslash( $_POST[ self::FIELD ] ) ) : null;
		// phpcs:enable
		if ( null === $sent ) {
			return;
		}
		if ( is_array( $sent['slots'] ?? null ) ) {
			// A row ticked to be removed is not sent on.
			$sent['slots'] = array_filter( $sent['slots'], static fn( mixed $row ): bool => ! is_array( $row ) || '' === ( $row['remove'] ?? '' ) );
		}

		$whole = '1' === ( $sent[ self::END ] ?? '' );
		unset( $sent[ self::END ] );

		$stored = StoredKit::named( $product );
		$draft  = $this->editor->draft( $sent, $stored, wc_get_price_decimal_separator() );
		if ( ! $whole ) {
			// Cut short on its way here: nothing is saved, and what did arrive is kept as typed.
			$text     = __( 'The Build tab was not saved, since only part of this product\'s form reached the server. PHP\'s max_input_vars setting caps how many fields one save may send. Ask the host to raise it, then save again.', 'creative-skateboard-builder' );
			$typed    = array_replace( $draft->documents, $draft->refused );
			$problems = array();
			foreach ( array_keys( $typed ) as $name ) {
				$problems[] = array(
					'document' => $name,
					'field'    => self::IDS . '-' . $name,
					'error'    => self::IDS . '-error-cut-' . $name,
					'text'     => $text,
				);
			}
			self::notice( self::PANEL, $text );
			set_transient(
				self::kept( $product->get_id() ),
				array(
					'refused'  => $typed,
					'problems' => $problems,
				),
				self::KEPT
			);
			return;
		}
		$keys = Options::kitDocumentNames();
		foreach ( $draft->documents as $name => $document ) {
			StoredKit::write( $product, $keys[ $name ], $document );
		}

		// The kit as the owner now sees it: what was saved, what was refused as typed, and what was left alone.
		$shown    = array_replace( $stored, $draft->documents, $draft->refused );
		$problems = array();
		foreach ( array_keys( $draft->refused ) as $name ) {
			self::notice( self::IDS . '-' . $name, self::refused( $name ) );
		}
		foreach ( $draft->problems as $at => $problem ) {
			$field      = self::id( $problem['document'], $problem['path'] );
			$text       = self::problem( $problem, $shown );
			$problems[] = array(
				'document' => $problem['document'],
				'field'    => $field,
				'error'    => self::IDS . '-error-' . $at,
				'text'     => $text,
			);
			self::notice( $field, $text );
		}
		foreach ( $draft->unoffered as $kept ) {
			$part = (int) ( $kept['values']['part'] ?? 0 );
			self::notice(
				self::id( KitEditor::PRESETS, $kept['path'] ),
				sprintf(
					/* translators: 1: a preset's name. 2: a product's name. 3: a slot's label, such as Trucks. */
					__( '%1$s holds %2$s, which the %3$s slot doesn\'t offer now. The preset is kept as it is, and its tile says so until the part is offered again.', 'creative-skateboard-builder' ),
					$kept['values']['preset'] ?? '',
					self::products( array( $part ) )[ $part ] ?? '',
					self::slotLabel( $shown, $kept['values']['slot'] ?? '' )
				)
			);
		}
		foreach ( $draft->notes as $note ) {
			$row = array_search( $note['slot'], array_column( self::rows( $shown[ KitEditor::SLOTS ] ?? null, 'slots' ), 'key' ), true );
			self::notice(
				self::IDS . '-slots' . ( false === $row ? '' : '-' . $row . '-units' ),
				sprintf(
					/* translators: 1: a number of parts. 2: a slot's label, such as Wheels. */
					_n(
						'%1$d part the %2$s slot takes has no set size, so the builder cannot tell how many a build needs and does not offer it. Fill in Number in a set on its Part specs.',
						'%1$d parts the %2$s slot takes have no set size, so the builder cannot tell how many a build needs and does not offer them. Fill in Number in a set on their Part specs.',
						$note['count'],
						'creative-skateboard-builder'
					),
					$note['count'],
					self::slotLabel( $shown, $note['slot'] )
				)
			);
		}

		$kept = self::kept( $product->get_id() );
		if ( array() === $draft->refused ) {
			delete_transient( $kept );
		} else {
			set_transient(
				$kept,
				array(
					'refused'  => $draft->refused,
					'problems' => $problems,
				),
				self::KEPT
			);
		}
	}

	/**
	 * The tab's sections, each with its groups of fields.
	 *
	 * @param WC_Product $product The kit.
	 * @return list<array{id: string, title: string, intro: string, refused: bool, problems: list<array{error: string, field: string, text: string}>, groups: list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>}>
	 */
	private function sections( WC_Product $product ): array {
		$stored = StoredKit::named( $product );
		$kept   = get_transient( self::kept( $product->get_id() ) );
		delete_transient( self::kept( $product->get_id() ) );
		$refused  = is_array( $kept ) && is_array( $kept['refused'] ?? null ) ? $kept['refused'] : array();
		$problems = is_array( $kept ) && is_array( $kept['problems'] ?? null ) ? $kept['problems'] : array();

		$this->errors = array();
		$listed       = array();
		foreach ( $problems as $problem ) {
			if ( is_array( $problem ) && is_string( $problem['document'] ?? null ) && is_string( $problem['field'] ?? null ) && is_string( $problem['error'] ?? null ) && is_string( $problem['text'] ?? null ) ) {
				$this->errors[ $problem['field'] ] ??= $problem['error'];
				$listed[ $problem['document'] ][]    = array(
					'error' => $problem['error'],
					'field' => $problem['field'],
					'text'  => $problem['text'],
				);
			}
		}

		$shown = array();
		foreach ( array_keys( Options::kitDocumentNames() ) as $name ) {
			$typed          = $refused[ $name ] ?? null;
			$shown[ $name ] = is_array( $typed ) ? $typed : ( $stored[ $name ] ?? $this->fresh( $name ) );
		}
		$known = array_column( self::rows( $stored[ KitEditor::SLOTS ] ?? null, 'slots' ), 'key' );

		$sections = array();
		foreach ( array(
			KitEditor::SLOTS    => array(
				__( 'Slots', 'creative-skateboard-builder' ),
				__( 'The parts a build is made of, in the order the builder asks for them. The deck comes first, since every other part is checked against it. What may fill a slot: a product in every category and brand named here that also has each attribute value named, and any product named itself. A slot\'s key is made when it is first saved and does not change, since builds and orders store it.', 'creative-skateboard-builder' ),
				$this->slots( $shown[ KitEditor::SLOTS ], $known ),
			),
			KitEditor::RULES    => array(
				__( 'Rules', 'creative-skateboard-builder' ),
				__( 'What the builder checks before it offers a part. A rule that is off checks nothing. A measurement takes a decimal or a fraction: 0.25 or 1/4.', 'creative-skateboard-builder' ),
				$this->rules( $shown[ KitEditor::RULES ] ),
			),
			KitEditor::PRESETS  => array(
				__( 'Presets', 'creative-skateboard-builder' ),
				__( 'Up to six builds a shopper can start from. Each is saved only when its parts are ones its slots take and the rules let them go together. Leave a preset\'s name and parts empty to have none.', 'creative-skateboard-builder' ),
				$this->presets( $shown[ KitEditor::PRESETS ], $shown[ KitEditor::SLOTS ] ),
			),
			KitEditor::ASSEMBLY => array(
				__( 'Assembly', 'creative-skateboard-builder' ),
				__( 'What the shop charges to put a build together, and what the shopper is told of it.', 'creative-skateboard-builder' ),
				$this->assembly( $shown[ KitEditor::ASSEMBLY ] ),
			),
			KitEditor::PICTURE  => array(
				__( 'Picture', 'creative-skateboard-builder' ),
				__( 'How the picture of a build stacks its parts, seen from underneath with the nose up. Layer 1 is drawn first, at the bottom. A part with no layer image shows its labelled photo instead.', 'creative-skateboard-builder' ),
				$this->picture( $shown[ KitEditor::PICTURE ], $shown[ KitEditor::SLOTS ] ),
			),
			KitEditor::LAYOUT   => array(
				__( 'Layout', 'creative-skateboard-builder' ),
				__( 'How the builder is laid out on a desktop, and which search its steps use. A phone always shows the board above the step.', 'creative-skateboard-builder' ),
				$this->layout( $shown[ KitEditor::LAYOUT ] ),
			),
		) as $name => $section ) {
			$sections[] = array(
				'id'       => self::IDS . '-' . $name,
				'title'    => $section[0],
				'intro'    => $section[1],
				'refused'  => isset( $refused[ $name ] ),
				'problems' => $listed[ $name ] ?? array(),
				'groups'   => $section[2],
			);
		}
		return $sections;
	}

	/**
	 * The slots' rows, and an empty one for a new slot while the kit has room.
	 *
	 * @param array<mixed> $document The slots document to show.
	 * @param list<mixed>  $known    The keys the stored kit's slots have.
	 * @return list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>
	 */
	private function slots( array $document, array $known ): array {
		$rows = self::rows( $document, 'slots' );
		if ( count( $rows ) < Slots::MAX ) {
			$rows[] = array( 'units' => 1 );
		}
		$kinds  = array( '' => __( 'Something else', 'creative-skateboard-builder' ) ) + self::kinds();
		$groups = array();
		foreach ( $rows as $at => $row ) {
			$id      = self::IDS . '-slots-' . $at;
			$name    = self::FIELD . '[slots][' . $at . ']';
			$key     = self::text( $row, 'key' );
			$label   = self::text( $row, 'label' );
			$sources = is_array( $row['sources'] ?? null ) ? $row['sources'] : array();
			$new     = '' === $key && '' === $label;
			$fields  = array( $this->field( $id . '-hidden-key', $name . '[key]', '', 'hidden', $key ) );
			if ( ! in_array( $key, $known, true ) ) {
				$fields[] = $this->field(
					$id . '-key',
					$name . '[kind]',
					__( 'Kind of part', 'creative-skateboard-builder' ),
					'select',
					in_array( $key, KitEditor::KINDS, true ) ? $key : '',
					array(
						'options' => $kinds,
						'note'    => __( 'The rules check a deck, trucks, wheels and the rest by their kind. A kit takes one slot of each. It cannot be changed once the slot is saved.', 'creative-skateboard-builder' ),
					)
				);
			}
			$fields[] = $this->field(
				$id . '-label',
				$name . '[label]',
				__( 'Label', 'creative-skateboard-builder' ),
				'text',
				$label,
				array(
					'max'  => Slot::MAX_LABEL,
					'note' => $new ? __( 'Type a label to add a slot. Left empty, nothing is added.', 'creative-skateboard-builder' ) : '',
				)
			);
			$fields[] = $this->field( $id . '-help', $name . '[help]', __( 'Help text', 'creative-skateboard-builder' ), 'textarea', self::text( $row, 'help' ), array( 'max' => Slot::MAX_HELP ) );
			$fields[] = $this->field( $id . '-required', $name . '[required]', __( 'A build must have one', 'creative-skateboard-builder' ), 'checkbox', true === ( $row['required'] ?? false ) ? '1' : '' );
			$fields[] = $this->field(
				$id . '-units',
				$name . '[units]',
				__( 'Units per build', 'creative-skateboard-builder' ),
				'text',
				self::text( $row, 'units' ),
				array( 'note' => __( 'How many a build takes: 2 trucks, 4 wheels, 8 bearings. The builder adds as many of the part as cover it.', 'creative-skateboard-builder' ) )
			);
			$fields[] = $this->field(
				$id . '-sources-categories',
				$name . '[categories][]',
				__( 'Categories', 'creative-skateboard-builder' ),
				'search',
				self::terms( $sources['categories'] ?? null ),
				array(
					'class'    => 'wc-category-search',
					'multiple' => true,
					'data'     => array(
						'return_id'   => 'id',
						'placeholder' => __( 'Search for a category', 'creative-skateboard-builder' ),
					),
				)
			);
			if ( taxonomy_exists( 'product_brand' ) ) {
				$fields[] = $this->field(
					$id . '-sources-brands',
					$name . '[brands][]',
					__( 'Brands', 'creative-skateboard-builder' ),
					'search',
					self::terms( $sources['brands'] ?? null ),
					array(
						'class'    => 'wc-taxonomy-term-search',
						'multiple' => true,
						'data'     => array(
							'taxonomy'    => 'product_brand',
							'return_id'   => 'id',
							'placeholder' => __( 'Search for a brand', 'creative-skateboard-builder' ),
						),
					)
				);
			}
			$fields[] = $this->field(
				$id . '-sources-products',
				$name . '[products][]',
				__( 'Products', 'creative-skateboard-builder' ),
				'search',
				self::products( $sources['products'] ?? null ),
				array(
					'class'    => 'wc-product-search',
					'multiple' => true,
					'data'     => array(
						'action'      => 'woocommerce_json_search_products_and_variations',
						'placeholder' => __( 'Search for a product', 'creative-skateboard-builder' ),
					),
				)
			);
			$fields[] = $this->field(
				$id . '-default',
				$name . '[default]',
				__( 'Default part', 'creative-skateboard-builder' ),
				'search',
				self::products( array( $row['default'] ?? null ) ),
				array(
					'class' => 'wc-product-search',
					'data'  => array(
						'action'      => 'woocommerce_json_search_products_and_variations',
						'allow_clear' => 'true',
						'placeholder' => __( 'None', 'creative-skateboard-builder' ),
					),
				)
			);
			if ( ! $new ) {
				$fields[] = $this->field(
					$id . '-remove',
					$name . '[remove]',
					__( 'Remove this slot when the kit is saved', 'creative-skateboard-builder' ),
					'checkbox',
					'',
					array( 'note' => __( 'A preset that names the slot must let it go first.', 'creative-skateboard-builder' ) )
				);
			}

			$chosen = array();
			foreach ( is_array( $sources['attributes'] ?? null ) ? $sources['attributes'] : array() as $attribute ) {
				if ( is_array( $attribute ) && is_string( $attribute['taxonomy'] ?? null ) ) {
					$chosen[ $attribute['taxonomy'] ] = $attribute['terms'] ?? null;
				}
			}
			$more = array();
			foreach ( wc_get_attribute_taxonomies() as $attribute ) {
				$taxonomy = wc_attribute_taxonomy_name( $attribute->attribute_name );
				$more[]   = $this->field(
					$id . '-sources-attributes-' . $taxonomy,
					$name . '[attributes][' . $taxonomy . '][]',
					/* translators: %s: a product attribute's name, such as Width. */
					sprintf( __( '%s values', 'creative-skateboard-builder' ), $attribute->attribute_label ),
					'search',
					self::terms( $chosen[ $taxonomy ] ?? null ),
					array(
						'class'    => 'wc-taxonomy-term-search',
						'multiple' => true,
						'data'     => array(
							'taxonomy'             => $taxonomy,
							'return_id'            => 'id',
							'minimum_input_length' => '1',
							'placeholder'          => __( 'Search for a value', 'creative-skateboard-builder' ),
						),
					)
				);
			}

			$groups[] = array(
				'id'     => $id,
				/* translators: %s: a slot's label, such as Trucks. */
				'legend' => $new ? __( 'New slot', 'creative-skateboard-builder' ) : sprintf( __( 'Slot: %s', 'creative-skateboard-builder' ), '' === $label ? $key : $label ),
				'moves'  => true,
				'fold'   => __( 'Settings', 'creative-skateboard-builder' ),
				'open'   => self::wrong( array( ...$fields, ...$more ) ),
				'fields' => $fields,
				'more'   => $more,
			);
		}
		return $groups;
	}

	/**
	 * The rules, a group each, with R7 and R8 said and not set.
	 *
	 * @param array<mixed> $document The rules document to show.
	 * @return list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>
	 */
	private function rules( array $document ): array {
		$id     = self::IDS . '-rules';
		$name   = self::FIELD . '[rules]';
		$rule   = static fn( string $key ): array => is_array( $document[ $key ] ?? null ) ? $document[ $key ] : array();
		$switch = fn( string $key ): array => $this->field( $id . '-' . $key . '-enabled', $name . '[' . $key . '][enabled]', __( 'Check this rule', 'creative-skateboard-builder' ), 'checkbox', true === ( $rule( $key )['enabled'] ?? false ) ? '1' : '' );
		$text   = fn( string $key, string $field, string $label, mixed $value, string $note = '' ): array => $this->field( $id . '-' . $key . '-' . str_replace( '.', '-', $field ), $name . '[' . $key . '][' . str_replace( '.', '][', $field ) . ']', $label, 'text', self::measure( $value ), array( 'note' => $note ) );

		$r1 = $rule( 'r1' );
		$r3 = $rule( 'r3' );
		$r6 = $rule( 'r6' );

		$limits = is_array( $r3['limits'] ?? null ) ? $r3['limits'] : array();
		$third  = array(
			$switch( 'r3' ),
			$text( 'r3', 'limits.low', __( 'Largest wheel on a low truck (mm)', 'creative-skateboard-builder' ), $limits['low'] ?? '', __( 'Used where a truck gives no figure of its own.', 'creative-skateboard-builder' ) ),
			$text( 'r3', 'limits.mid', __( 'Largest wheel on a mid truck (mm)', 'creative-skateboard-builder' ), $limits['mid'] ?? '' ),
			$text( 'r3', 'limits.high', __( 'Largest wheel on a high truck (mm)', 'creative-skateboard-builder' ), $limits['high'] ?? '' ),
		);
		$risers = self::rows( $r3, 'risers' );
		$spare  = min( RuleSettings::MAX_RISER_STEPS, count( $risers ) + self::SPARE );
		for ( $at = 0; $at < $spare; ++$at ) {
			$row = $risers[ $at ] ?? array();
			/* translators: %d: a row's number. */
			$third[] = $text( 'r3', 'risers.' . $at . '.over', sprintf( __( 'Suggestion %d: for a wheel over the limit by up to (mm)', 'creative-skateboard-builder' ), $at + 1 ), $row['over'] ?? '' );
			/* translators: %d: a row's number. */
			$third[] = $text( 'r3', 'risers.' . $at . '.riser', sprintf( __( 'Suggestion %d: the riser to suggest (in)', 'creative-skateboard-builder' ), $at + 1 ), $row['riser'] ?? '' );
		}

		$groups = array(
			array(
				__( 'R1. Deck width and truck size', 'creative-skateboard-builder' ),
				'r1',
				array(
					$switch( 'r1' ),
					$text( 'r1', 'range_tolerance', __( 'Allowed outside the maker\'s deck range (in)', 'creative-skateboard-builder' ), $r1['range_tolerance'] ?? '', __( 'A truck fits a deck inside the range its maker publishes. 0 holds to the range.', 'creative-skateboard-builder' ) ),
					$text( 'r1', 'axle_fit', __( 'With no range: the axle fits within (in)', 'creative-skateboard-builder' ), $r1['axle_fit'] ?? '', __( 'How far the axle\'s width may be from the deck\'s.', 'creative-skateboard-builder' ) ),
					$text( 'r1', 'axle_warn', __( 'With no range: the axle warns within (in)', 'creative-skateboard-builder' ), $r1['axle_warn'] ?? '', __( 'Further than this and the truck is not offered.', 'creative-skateboard-builder' ) ),
				),
			),
			array( __( 'R2. Mounting holes', 'creative-skateboard-builder' ), 'r2', array( $switch( 'r2' ) ) ),
			array( __( 'R3. Wheels, truck height and risers', 'creative-skateboard-builder' ), 'r3', $third ),
			array( __( 'R4. Bolts and risers', 'creative-skateboard-builder' ), 'r4', array( $switch( 'r4' ) ) ),
			array( __( 'R5. Bearings', 'creative-skateboard-builder' ), 'r5', array( $switch( 'r5' ) ) ),
			array(
				__( 'R6. Grip', 'creative-skateboard-builder' ),
				'r6',
				array(
					$switch( 'r6' ),
					$this->field( $id . '-r6-check_length', $name . '[r6][check_length]', __( 'Check the sheet\'s length as well as its width', 'creative-skateboard-builder' ), 'checkbox', true === ( $r6['check_length'] ?? false ) ? '1' : '' ),
				),
			),
			array(
				__( 'R7. Quantities', 'creative-skateboard-builder' ),
				'r7',
				array( $this->field( $id . '-r7', '', '', 'static', __( 'Always checked. Each slot says how many units a build takes, and each part how many it holds in a set. Saving the kit says which parts have no set size.', 'creative-skateboard-builder' ) ) ),
			),
			array(
				__( 'R8. Wheelbase and deck length', 'creative-skateboard-builder' ),
				'r8',
				array( $this->field( $id . '-r8', '', '', 'static', __( 'No rule. These change how a board turns, not whether its parts fit. The builder shows them on a deck where they are known.', 'creative-skateboard-builder' ) ) ),
			),
		);
		return array_values(
			array_map(
				static fn( array $group ): array => array(
					'id'     => $id . '-' . $group[1],
					'legend' => $group[0],
					'moves'  => false,
					'fold'   => '',
					'open'   => true,
					'fields' => $group[2],
					'more'   => array(),
				),
				$groups
			)
		);
	}

	/**
	 * Six presets, the empty ones after those the kit has.
	 *
	 * @param array<mixed> $document The presets document to show.
	 * @param array<mixed> $slots    The slots document shown beside it.
	 * @return list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>
	 */
	private function presets( array $document, array $slots ): array {
		$rows   = self::rows( $document, 'presets' );
		$groups = array();
		$shown  = max( Kit::MAX_PRESETS, count( $rows ) );
		for ( $at = 0; $at < $shown; ++$at ) {
			$row   = $rows[ $at ] ?? array();
			$id    = self::IDS . '-presets-' . $at;
			$name  = self::FIELD . '[presets][' . $at . ']';
			$picks = is_array( $row['picks'] ?? null ) ? $row['picks'] : array();
			/* translators: %d: a preset's number, 1 to 6. */
			$legend = sprintf( __( 'Preset %d', 'creative-skateboard-builder' ), $at + 1 );
			$fields = array(
				$this->field( $id . '-name', $name . '[name]', __( 'Name', 'creative-skateboard-builder' ), 'text', self::text( $row, 'name' ), array( 'max' => Preset::MAX_NAME ) ),
				$this->field( $id . '-description', $name . '[description]', __( 'Description', 'creative-skateboard-builder' ), 'text', self::text( $row, 'description' ), array( 'max' => Preset::MAX_DESCRIPTION ) ),
				$this->field(
					$id . '-image',
					$name . '[image]',
					__( 'Photo', 'creative-skateboard-builder' ),
					'text',
					self::text( $row, 'image' ),
					array(
						'note'   => __( 'A rider photo for its tile, by its ID in the media library. Without one, the tile shows the build’s picture.', 'creative-skateboard-builder' ),
						'choose' => array(
							'title'   => __( 'Choose a photo', 'creative-skateboard-builder' ),
							'label'   => sprintf(
								/* translators: %s: a preset's name, or "Preset 2" for one with none. */
								__( 'Choose a photo for %s', 'creative-skateboard-builder' ),
								'' !== self::text( $row, 'name' ) ? self::text( $row, 'name' ) : $legend
							),
							'preview' => (string) wp_get_attachment_image_url( (int) self::text( $row, 'image' ), 'thumbnail' ),
						),
					)
				),
			);
			foreach ( self::rows( $slots, 'slots' ) as $slot ) {
				$key = self::text( $slot, 'key' );
				if ( '' === $key ) {
					continue;
				}
				$label    = self::text( $slot, 'label' );
				$fields[] = $this->field(
					$id . '-picks-' . $key,
					$name . '[picks][' . $key . ']',
					'' === $label ? $key : $label,
					'search',
					self::products( array( $picks[ $key ] ?? null ) ),
					array(
						'class' => 'wc-product-search',
						'data'  => array(
							'action'      => 'woocommerce_json_search_products_and_variations',
							'allow_clear' => 'true',
							'placeholder' => __( 'None', 'creative-skateboard-builder' ),
						),
					)
				);
			}
			$groups[] = array(
				'id'     => $id,
				'legend' => $legend,
				'moves'  => false,
				'fold'   => __( 'Name and parts', 'creative-skateboard-builder' ),
				'open'   => array() !== $row || self::wrong( $fields ),
				'fields' => $fields,
				'more'   => array(),
			);
		}
		return $groups;
	}

	/**
	 * The assembly settings.
	 *
	 * @param array<mixed> $document The assembly document to show.
	 * @return list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>
	 */
	private function assembly( array $document ): array {
		$id   = self::IDS . '-assembly';
		$name = self::FIELD . '[assembly]';
		return array(
			array(
				'id'     => $id . '-fields',
				'legend' => '',
				'moves'  => false,
				'fold'   => '',
				'open'   => true,
				'fields' => array(
					$this->field( $id . '-default_on', $name . '[default_on]', __( 'Assembly is chosen when a build starts', 'creative-skateboard-builder' ), 'checkbox', true === ( $document['default_on'] ?? false ) ? '1' : '' ),
					/* translators: %s: the shop's currency symbol. */
					$this->field( $id . '-fee', $name . '[fee]', sprintf( __( 'Fee (%s)', 'creative-skateboard-builder' ), html_entity_decode( get_woocommerce_currency_symbol(), ENT_QUOTES, 'UTF-8' ) ), 'text', self::measure( $document['fee'] ?? '' ), array( 'note' => __( 'Charged once for a build that is put together. 0 for none.', 'creative-skateboard-builder' ) ) ),
					$this->field(
						$id . '-lead_time',
						$name . '[lead_time]',
						__( 'Lead time', 'creative-skateboard-builder' ),
						'text',
						self::text( $document, 'lead_time' ),
						array(
							'max'  => Assembly::MAX_LEAD_TIME,
							'note' => __( 'How long putting a build together takes, such as 2 business days. The review says it after "Assembly time:".', 'creative-skateboard-builder' ),
						)
					),
					$this->field( $id . '-returns_notice', $name . '[returns_notice]', __( 'Returns notice', 'creative-skateboard-builder' ), 'textarea', self::text( $document, 'returns_notice' ), array( 'max' => Assembly::MAX_RETURNS_NOTICE ) ),
				),
				'more'   => array(),
			),
		);
	}

	/**
	 * The picture settings: a layer for each slot the picture draws, and where the trucks sit on a deck that gives
	 * no wheelbase (D-192).
	 *
	 * @param array<mixed> $document The picture document to show.
	 * @param array<mixed> $slots    The slots document to show.
	 * @return list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>
	 */
	private function picture( array $document, array $slots ): array {
		$id     = self::IDS . '-picture';
		$name   = self::FIELD . '[picture]';
		$drawn  = array();
		$labels = array();
		foreach ( self::rows( $slots, 'slots' ) as $row ) {
			$key = self::text( $row, 'key' );
			if ( in_array( $key, Layout::DRAWN, true ) ) {
				$drawn[]        = $key;
				$labels[ $key ] = '' === self::text( $row, 'label' ) ? $key : self::text( $row, 'label' );
			}
		}
		// The order in force: the document's, then the slots it leaves out in the usual order.
		$order = is_array( $document['order'] ?? null ) ? array_values( array_intersect( array_filter( $document['order'], 'is_string' ), $drawn ) ) : array();
		foreach ( Layout::DRAWN as $key ) {
			if ( in_array( $key, $drawn, true ) && ! in_array( $key, $order, true ) ) {
				$order[] = $key;
			}
		}
		$numbers = array();
		foreach ( range( 1, max( 1, count( $order ) ) ) as $number ) {
			$numbers[ (string) $number ] = 1 === $number ? __( '1, the bottom', 'creative-skateboard-builder' ) : number_format_i18n( $number );
		}
		$fields = array();
		foreach ( $order as $at => $key ) {
			$fields[] = $this->field(
				$id . '-layers-' . $key,
				$name . '[layers][' . $key . ']',
				/* translators: %s: a slot's label, such as Trucks. */
				sprintf( __( '%s layer', 'creative-skateboard-builder' ), $labels[ $key ] ?? $key ),
				'select',
				(string) ( $at + 1 ),
				array( 'options' => $numbers )
			);
		}
		$fields[] = $this->field(
			$id . '-wheelbase',
			$name . '[wheelbase]',
			__( 'Wheelbase where a deck gives none (in)', 'creative-skateboard-builder' ),
			'text',
			self::measure( is_string( $document['wheelbase'] ?? null ) ? $document['wheelbase'] : '' ),
			array( 'note' => __( 'Where the picture puts the trucks on a deck whose wheelbase is not in its specs, from 10 to 20 in. Leave it empty for the usual on a deck of its width and length.', 'creative-skateboard-builder' ) )
		);
		return array(
			array(
				'id'     => $id . '-fields',
				'legend' => '',
				'moves'  => false,
				'fold'   => '',
				'open'   => true,
				'fields' => $fields,
				'more'   => array(),
			),
		);
	}

	/**
	 * The Layout section's fields (D-255): the stage's side and the board's size on a desktop, and the steps' search.
	 *
	 * @param array<mixed> $document The layout document to show.
	 * @return list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>
	 */
	private function layout( array $document ): array {
		$id     = self::IDS . '-layout';
		$name   = self::FIELD . '[layout]';
		$chosen = static fn( string $key, array $choices ): string => is_string( $document[ $key ] ?? null ) ? $document[ $key ] : $choices[0];
		$fields = array(
			$this->field(
				$id . '-stage-side',
				$name . '[stage_side]',
				__( 'Stage side on a desktop', 'creative-skateboard-builder' ),
				'select',
				$chosen( 'stage_side', KitLayout::SIDES ),
				array(
					'options' => array(
						'left'  => __( 'Left', 'creative-skateboard-builder' ),
						'right' => __( 'Right', 'creative-skateboard-builder' ),
					),
				)
			),
			$this->field(
				$id . '-board-size',
				$name . '[board_size]',
				__( 'Board size on a desktop', 'creative-skateboard-builder' ),
				'select',
				$chosen( 'board_size', KitLayout::SIZES ),
				array(
					'options' => array(
						'large'    => __( 'Large, 60 % of the width', 'creative-skateboard-builder' ),
						'balanced' => __( 'Balanced, 50 % of the width', 'creative-skateboard-builder' ),
					),
				)
			),
			$this->field(
				$id . '-search',
				$name . '[search]',
				__( 'Search in the steps', 'creative-skateboard-builder' ),
				'select',
				$chosen( 'search', KitLayout::SEARCHES ),
				array(
					'options' => array(
						'creative_search' => __( 'Creative Search where it is active, the builder\'s own otherwise', 'creative-skateboard-builder' ),
						'own'             => __( 'The builder\'s own always', 'creative-skateboard-builder' ),
					),
					'note'    => __( 'Creative Search corrects typing errors and ranks matches by how well they match. The builder\'s own search matches the words as typed.', 'creative-skateboard-builder' ),
				)
			),
		);
		return array(
			array(
				'id'     => $id . '-fields',
				'legend' => '',
				'moves'  => false,
				'fold'   => '',
				'open'   => true,
				'fields' => $fields,
				'more'   => array(),
			),
		);
	}

	/**
	 * A field as the template draws it.
	 *
	 * @param string                    $id    The control's ID.
	 * @param string                    $name  The name it is sent under.
	 * @param string                    $label Its label.
	 * @param string                    $type  `text`, `textarea`, `checkbox`, `select`, `search`, `hidden` or `static`.
	 * @param string|array<int, string> $value Its value; for a search field, what is chosen, by ID.
	 * @param array<string, mixed>      $more  `options`, `note`, `class`, `multiple`, `data` and `max`, where it has them.
	 * @return array<string, mixed>
	 */
	private function field( string $id, string $name, string $label, string $type, string|array $value, array $more = array() ): array {
		$error = $this->errors[ $id ] ?? '';
		foreach ( $this->errors as $field => $sentence ) {
			// A problem deeper than a field, an ID in a list of them, is the field's.
			if ( '' === $error && str_starts_with( $field, $id . '-' ) && 'hidden' !== $type ) {
				$error = $sentence;
			}
		}
		return $more + array(
			'id'       => $id,
			'name'     => $name,
			'label'    => $label,
			'type'     => $type,
			'value'    => $value,
			'error'    => $error,
			'options'  => array(),
			'note'     => '',
			'class'    => '',
			'multiple' => false,
			'data'     => array(),
			'max'      => 0,
			'choose'   => array(),
		);
	}

	/**
	 * Whether any of these fields was not saved.
	 *
	 * @param list<array<string, mixed>> $fields The fields.
	 */
	private static function wrong( array $fields ): bool {
		foreach ( $fields as $field ) {
			if ( '' !== ( $field['error'] ?? '' ) ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * What a new kit's tab starts from: the seven parts of a complete, the rules as seeded, no presets, and assembly
	 * as the shop's settings have it for a new kit.
	 *
	 * @param string $name The document's name.
	 * @return array<string, mixed>
	 */
	private function fresh( string $name ): array {
		if ( KitEditor::RULES === $name ) {
			return RuleSettings::DEFAULTS;
		}
		if ( KitEditor::ASSEMBLY === $name ) {
			return $this->settings->terms()->assembly;
		}
		if ( KitEditor::PICTURE === $name || KitEditor::LAYOUT === $name ) {
			return array( 'schema_version' => 1 );
		}
		if ( KitEditor::SLOTS !== $name ) {
			return array();
		}
		$labels = self::kinds();
		$slots  = array();
		foreach ( array(
			SlotKey::DECK     => array( true, 1 ),
			SlotKey::TRUCKS   => array( true, 2 ),
			SlotKey::WHEELS   => array( true, 4 ),
			SlotKey::RISERS   => array( false, 2 ),
			SlotKey::BEARINGS => array( true, 8 ),
			SlotKey::HARDWARE => array( true, 8 ),
			SlotKey::GRIP     => array( true, 1 ),
		) as $key => $slot ) {
			$slots[] = array(
				'key'      => $key,
				'label'    => $labels[ $key ],
				'required' => $slot[0],
				'units'    => $slot[1],
			);
		}
		return array( 'slots' => $slots );
	}

	/**
	 * The kinds of part the rules read, by slot key.
	 *
	 * @return array<string, string>
	 */
	private static function kinds(): array {
		return array(
			SlotKey::DECK     => __( 'Deck', 'creative-skateboard-builder' ),
			SlotKey::TRUCKS   => __( 'Trucks', 'creative-skateboard-builder' ),
			SlotKey::WHEELS   => __( 'Wheels', 'creative-skateboard-builder' ),
			SlotKey::RISERS   => __( 'Risers', 'creative-skateboard-builder' ),
			SlotKey::BEARINGS => __( 'Bearings', 'creative-skateboard-builder' ),
			SlotKey::HARDWARE => __( 'Hardware', 'creative-skateboard-builder' ),
			SlotKey::GRIP     => __( 'Grip tape', 'creative-skateboard-builder' ),
			SlotKey::SPACERS  => __( 'Spacers', 'creative-skateboard-builder' ),
		);
	}

	/**
	 * A problem as a sentence that names its field.
	 *
	 * @param array{document: string, code: string, path: string, values: array<string, string>} $problem The problem.
	 * @param array<string, array<mixed>|null>                                                   $shown   The kit's documents as the tab will show them.
	 */
	private static function problem( array $problem, array $shown ): string {
		$values = $problem['values'];
		$part   = (int) ( $values['part'] ?? 0 );
		return Sentences::kit(
			$problem['code'],
			self::named( $problem['document'], $problem['path'], $shown ),
			array(
				'preset'  => $values['preset'] ?? '',
				'slot'    => self::slotLabel( $shown, $values['slot'] ?? '' ),
				'part'    => $part > 0 ? ( self::products( array( $part ) )[ $part ] ?? '' ) : '',
				'rule'    => strtoupper( $values['rule'] ?? '' ),
				'message' => $values['message'] ?? '',
			)
		);
	}

	/**
	 * A field's name as the notices say it: where it is, then its label.
	 *
	 * @param string                           $document The document's name.
	 * @param string                           $path     The field's path in the document.
	 * @param array<string, array<mixed>|null> $shown    The kit's documents as the tab will show them.
	 */
	private static function named( string $document, string $path, array $shown ): string {
		$labels = array(
			'key'                => __( 'Kind of part', 'creative-skateboard-builder' ),
			'label'              => __( 'Label', 'creative-skateboard-builder' ),
			'help'               => __( 'Help text', 'creative-skateboard-builder' ),
			'units'              => __( 'Units per build', 'creative-skateboard-builder' ),
			'default'            => __( 'Default part', 'creative-skateboard-builder' ),
			'sources.categories' => __( 'Categories', 'creative-skateboard-builder' ),
			'sources.brands'     => __( 'Brands', 'creative-skateboard-builder' ),
			'sources.products'   => __( 'Products', 'creative-skateboard-builder' ),
			'sources.attributes' => __( 'Attribute values', 'creative-skateboard-builder' ),
			'name'               => __( 'Name', 'creative-skateboard-builder' ),
			'description'        => __( 'Description', 'creative-skateboard-builder' ),
			'fee'                => __( 'Fee', 'creative-skateboard-builder' ),
			'lead_time'          => __( 'Lead time', 'creative-skateboard-builder' ),
			'returns_notice'     => __( 'Returns notice', 'creative-skateboard-builder' ),
			'range_tolerance'    => __( 'Allowed outside the maker\'s deck range', 'creative-skateboard-builder' ),
			'axle_fit'           => __( 'The axle fits within', 'creative-skateboard-builder' ),
			'axle_warn'          => __( 'The axle warns within', 'creative-skateboard-builder' ),
			'limits.low'         => __( 'Largest wheel on a low truck', 'creative-skateboard-builder' ),
			'limits.mid'         => __( 'Largest wheel on a mid truck', 'creative-skateboard-builder' ),
			'limits.high'        => __( 'Largest wheel on a high truck', 'creative-skateboard-builder' ),
			'over'               => __( 'Wheel over the limit by up to', 'creative-skateboard-builder' ),
			'riser'              => __( 'The riser to suggest', 'creative-skateboard-builder' ),
			'wheelbase'          => __( 'Wheelbase where a deck gives none', 'creative-skateboard-builder' ),
		);
		$found  = array();
		if ( 1 === preg_match( '/\A(slots|presets)\[(\d+)\]\.?(.*)\z/', $path, $found ) ) {
			$at    = (int) $found[2];
			$row   = self::rows( $shown[ $document ] ?? null, $found[1] )[ $at ] ?? array();
			$field = (string) preg_replace( '/\[\d+\].*\z/', '', $found[3] );
			if ( 'presets' === $found[1] ) {
				$label = str_starts_with( $field, 'picks.' ) ? self::slotLabel( $shown, substr( $field, 6 ) ) : ( $labels[ $field ] ?? '' );
				if ( '' === $label ) {
					/* translators: %d: a preset's number, 1 to 6. */
					return sprintf( __( 'Preset %d', 'creative-skateboard-builder' ), $at + 1 );
				}
				/* translators: 1: a preset's number. 2: a field's label, such as Name, or a slot's, such as Trucks. */
				return sprintf( __( 'Preset %1$d, %2$s', 'creative-skateboard-builder' ), $at + 1, $label );
			}
			$slot  = self::text( $row, 'label' );
			$label = $labels[ $field ] ?? '';
			if ( '' === $slot ) {
				if ( '' === $label ) {
					/* translators: %d: a slot's place in the list. */
					return sprintf( __( 'Slot %d', 'creative-skateboard-builder' ), $at + 1 );
				}
				/* translators: 1: a slot's place in the list. 2: a field's label, such as Units per build. */
				return sprintf( __( 'Slot %1$d, %2$s', 'creative-skateboard-builder' ), $at + 1, $label );
			}
			if ( '' === $label ) {
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( 'The %s slot', 'creative-skateboard-builder' ), $slot );
			}
			/* translators: 1: a slot's label, such as Trucks. 2: a field's label, such as Units per build. */
			return sprintf( __( 'The %1$s slot, %2$s', 'creative-skateboard-builder' ), $slot, $label );
		}
		if ( 1 === preg_match( '/\A(r[1-6])\.(.+)\z/', $path, $found ) ) {
			$field = (string) preg_replace( '/\Arisers\[\d+\]\./', '', $found[2] );
			/* translators: 1: a rule's number, such as R1. 2: a setting's label. */
			return sprintf( __( '%1$s, %2$s', 'creative-skateboard-builder' ), strtoupper( $found[1] ), $labels[ $field ] ?? $found[2] );
		}
		return $labels[ $path ] ?? self::title( $document );
	}

	/**
	 * A section's name.
	 *
	 * @param string $document The document's name.
	 */
	private static function title( string $document ): string {
		return match ( $document ) {
			KitEditor::SLOTS   => __( 'Slots', 'creative-skateboard-builder' ),
			KitEditor::RULES   => __( 'Rules', 'creative-skateboard-builder' ),
			KitEditor::PRESETS => __( 'Presets', 'creative-skateboard-builder' ),
			KitEditor::PICTURE => __( 'Picture', 'creative-skateboard-builder' ),
			default            => __( 'Assembly', 'creative-skateboard-builder' ),
		};
	}

	/**
	 * That a section was not saved.
	 *
	 * @param string $document The document's name.
	 */
	private static function refused( string $document ): string {
		return match ( $document ) {
			KitEditor::SLOTS   => __( 'The kit\'s slots were not saved. The Build tab shows them as you typed them, with what to put right.', 'creative-skateboard-builder' ),
			KitEditor::RULES   => __( 'The kit\'s rules were not saved. The Build tab shows them as you typed them, with what to put right.', 'creative-skateboard-builder' ),
			KitEditor::PRESETS => __( 'The kit\'s presets were not saved. The Build tab shows them as you typed them, with what to put right.', 'creative-skateboard-builder' ),
			KitEditor::PICTURE => __( 'The kit\'s picture settings were not saved. The Build tab shows them as you typed them, with what to put right.', 'creative-skateboard-builder' ),
			KitEditor::LAYOUT  => __( 'The kit\'s layout was not saved. The Build tab shows it as you chose it, with what to put right.', 'creative-skateboard-builder' ),
			default            => __( 'The kit\'s assembly settings were not saved. The Build tab shows them as you typed them, with what to put right.', 'creative-skateboard-builder' ),
		};
	}

	/**
	 * A slot's label, or its key where it has none.
	 *
	 * @param array<string, array<mixed>|null> $shown The kit's documents as the tab will show them.
	 * @param string                           $key   The slot's key.
	 */
	private static function slotLabel( array $shown, string $key ): string {
		foreach ( self::rows( $shown[ KitEditor::SLOTS ] ?? null, 'slots' ) as $slot ) {
			if ( self::text( $slot, 'key' ) === $key && '' !== self::text( $slot, 'label' ) ) {
				return self::text( $slot, 'label' );
			}
		}
		return $key;
	}

	/**
	 * A notice after the save, which leads to its field.
	 *
	 * @param string $field The field's ID.
	 * @param string $text  The sentence.
	 */
	private static function notice( string $field, string $text ): void {
		// WooCommerce prints its notices through wp_kses_post(), and what was typed is in these.
		WC_Admin_Meta_Boxes::add_error( sprintf( '<a href="#%s">%s</a>', esc_attr( $field ), esc_html( $text ) ) );
	}

	/**
	 * A control's ID from its document and its path there: `slots[2].units` is `cbuild-kit-slots-2-units`.
	 *
	 * @param string $document The document's name.
	 * @param string $path     The field's path in the document.
	 */
	private static function id( string $document, string $path ): string {
		$path = str_starts_with( $path, $document . '[' ) || $path === $document ? substr( $path, strlen( $document ) ) : $path;
		$tail = trim( (string) preg_replace( '/[\[\].]+/', '-', $path ), '-' );
		return self::IDS . '-' . $document . ( '' === $tail ? '' : '-' . $tail );
	}

	/**
	 * The transient a kit's refused sections are kept in for the person who sent them.
	 *
	 * @param int $kit The kit's ID.
	 */
	private static function kept( int $kit ): string {
		return Options::TRANSIENT_PREFIX . 'kit_draft_' . $kit . '_' . get_current_user_id();
	}

	/**
	 * What was sent, each value as plain text. A field that takes lines keeps them.
	 *
	 * @param mixed $value What was sent, unslashed.
	 * @param bool  $lines Whether the value may hold more than one line.
	 * @return ($value is array ? array<mixed> : string)
	 */
	private static function clean( mixed $value, bool $lines = false ): array|string {
		if ( is_array( $value ) ) {
			$cleaned = array();
			foreach ( $value as $key => $inner ) {
				$cleaned[ $key ] = self::clean( $inner, in_array( $key, self::LINES, true ) );
			}
			return $cleaned;
		}
		if ( ! is_string( $value ) ) {
			return '';
		}
		return $lines ? sanitize_textarea_field( $value ) : sanitize_text_field( $value );
	}

	/**
	 * A document's list of rows, each a row; none where the document has no such list.
	 *
	 * @param mixed  $document The document.
	 * @param string $named    The list's name.
	 * @return list<array<mixed>>
	 */
	private static function rows( mixed $document, string $named ): array {
		$rows = is_array( $document ) && is_array( $document[ $named ] ?? null ) ? $document[ $named ] : array();
		return array_values( array_filter( $rows, 'is_array' ) );
	}

	/**
	 * A field of a row as text; a number as its digits.
	 *
	 * @param array<mixed> $row  The row.
	 * @param string       $name The field.
	 */
	private static function text( array $row, string $name ): string {
		$value = $row[ $name ] ?? '';
		return is_string( $value ) || is_int( $value ) ? (string) $value : '';
	}

	/**
	 * A measure or an amount as its field shows it: a decimal takes the shop's separator, anything else is as typed.
	 *
	 * @param mixed $value The document's value.
	 */
	private static function measure( mixed $value ): string {
		if ( ! is_string( $value ) ) {
			return '';
		}
		return 1 === preg_match( '/\A\d+(\.\d+)?\z/', $value ) ? Labels::typed( $value ) : $value;
	}

	/**
	 * The names of the terms a list of IDs holds, by ID; one that is gone shows its number.
	 *
	 * @param mixed $ids The IDs.
	 * @return array<int, string>
	 */
	private static function terms( mixed $ids ): array {
		$names = array();
		$ids   = is_array( $ids ) ? array_values( array_filter( $ids, static fn( mixed $id ): bool => is_int( $id ) && $id > 0 ) ) : array();
		if ( count( $ids ) > 1 ) {
			_prime_term_caches( $ids );
		}
		foreach ( $ids as $id ) {
			$term         = get_term( $id );
			$names[ $id ] = $term instanceof WP_Term ? $term->name : '#' . $id;
		}
		return $names;
	}

	/**
	 * The names of the products a list of IDs holds, by ID; one that is gone shows its number.
	 *
	 * @param mixed $ids The IDs.
	 * @return array<int, string>
	 */
	private static function products( mixed $ids ): array {
		$names = array();
		$ids   = is_array( $ids ) ? array_values( array_filter( $ids, static fn( mixed $id ): bool => is_int( $id ) && $id > 0 ) ) : array();
		// Read together, so a slot's long list of products asks no query a product (the M9 audit's minor 11).
		if ( count( $ids ) > 1 ) {
			_prime_post_caches( $ids, true, true );
		}
		foreach ( $ids as $id ) {
			$product      = wc_get_product( $id );
			$names[ $id ] = $product instanceof WC_Product ? wp_strip_all_tags( $product->get_formatted_name() ) : '#' . $id;
		}
		return $names;
	}
}
