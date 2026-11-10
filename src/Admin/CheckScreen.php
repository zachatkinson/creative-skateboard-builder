<?php
/**
 * The Catalogue check, at WooCommerce > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A button that runs the check, and what it found: each finding a sentence, with a link to where it is fixed (spec:
 * Admin, Catalogue check). The check reads and writes nothing, so its answer is drawn in the request that asked,
 * and asking is a post with a nonce so that nothing but a person starts it. Nothing here needs a script.
 *
 * @since 0.3.0
 */
final class CheckScreen {

	/**
	 * The form's nonce action.
	 */
	public const NONCE = 'cbuild_check';

	/**
	 * The most findings the page lists.
	 */
	public const SHOWN = 500;

	/**
	 * What the check found, null when it has not been run in this request.
	 *
	 * @var list<CheckFinding>|null
	 */
	private ?array $found = null;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param CatalogueCheck $check The check.
	 * @param Kits           $kits  The kits, for their names.
	 */
	public function __construct( private CatalogueCheck $check, private Kits $kits ) {}

	/**
	 * The tab's address.
	 *
	 * @since 0.3.0
	 */
	public static function url(): string {
		return add_query_arg(
			array(
				'page' => BuilderHooks::PAGE,
				'tab'  => BuilderHooks::TAB_CHECK,
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Style the table, as the import's tab does.
	 *
	 * @since 0.3.0
	 */
	public function styles(): void {
		wp_add_inline_style( 'common', '.cbuild-scroll{overflow-x:auto;margin:0 0 16px}' );
	}

	/**
	 * Run the check when its form was sent.
	 *
	 * @since 0.3.0
	 *
	 * @return null Always: the page that asked draws the answer.
	 */
	public function handle(): null {
		if ( ! isset( $_POST['cbuild_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}
		check_admin_referer( self::NONCE );
		if ( 'check' === $_POST['cbuild_action'] ) {
			$this->found = $this->check->run();
		}
		return null;
	}

	/**
	 * Draw the tab.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$ran   = null !== $this->found;
		$found = $this->found ?? array();
		$kits  = $this->kits->all();
		$rows  = array();
		foreach ( array_slice( $found, 0, self::SHOWN ) as $finding ) {
			$product = 0 === $finding->part ? null : wc_get_product( $finding->part );
			$about   = '';
			$link    = '';
			if ( $product instanceof \WC_Product ) {
				$owner = 0 === $product->get_parent_id() ? $product->get_id() : $product->get_parent_id();
				$about = '' === $product->get_sku() ? $product->get_name() : $product->get_name() . ' (' . $product->get_sku() . ')';
				$link  = (string) get_edit_post_link( $owner, 'raw' ) . '#cbuild_part_specs';
			} elseif ( 0 !== $finding->part ) {
				/* translators: %s: a product's ID. */
				$about = sprintf( __( 'Product %s', 'creative-skateboard-builder' ), (string) $finding->part );
			} elseif ( 0 === $finding->kit ) {
				$about = __( 'Background work', 'creative-skateboard-builder' );
				$link  = admin_url( 'admin.php?page=wc-status&tab=action-scheduler&status=failed' );
			}
			$rows[] = array(
				'kit'   => 0 === $finding->kit ? '' : ( $kits[ $finding->kit ]['name'] ?? (string) $finding->kit ),
				'about' => $about,
				'link'  => $link,
				'says'  => self::sentence( $finding ),
			);
		}
		$more  = count( $found ) - count( $rows );
		$count = count( $found );
		$tabs  = BuilderHooks::tabs( BuilderHooks::TAB_CHECK );
		$nonce = self::NONCE;
		$back  = self::url();
		$none  = array() === $kits;
		include CBUILD_PATH . 'templates/admin/check.php';
	}

	/**
	 * A finding as a sentence.
	 *
	 * @param CheckFinding $finding The finding.
	 */
	private static function sentence( CheckFinding $finding ): string {
		$key    = SpecKey::tryFrom( $finding->spec );
		$spec   = null === $key ? $finding->spec : Labels::spec( $key );
		$values = $finding->values;
		$value  = static fn( string $name ): string => null === $key || '' === ( $values[ $name ] ?? '' ) ? ( $values[ $name ] ?? '' ) : Labels::value( $key, $values[ $name ] );
		$source = static fn( string $name ): string => Labels::source( Origin::tryFrom( $values[ $name ] ?? '' ) ?? Origin::Field );
		switch ( $finding->code ) {
			case CatalogueCheck::DISAGREE:
				/* translators: 1: a spec's name. 2: a source, such as "The maker's chart". 3: a value. 4: another source. 5: its value. */
				return sprintf( __( '%1$s: two sources disagree. %2$s says %3$s, which is used. %4$s says %5$s.', 'creative-skateboard-builder' ), $spec, $source( 'kept_from' ), $value( 'kept' ), $source( 'other_from' ), $value( 'other' ) );
			case CatalogueCheck::UNREAD:
				if ( 'table_missing' === ( $values['reason'] ?? '' ) ) {
					return __( 'It names a maker\'s chart that is no longer there. Choose another on its Part specs.', 'creative-skateboard-builder' );
				}
				if ( 'size_missing' === ( $values['reason'] ?? '' ) ) {
					/* translators: %s: a size in a maker's chart. */
					return sprintf( __( 'It names the size "%s", which its maker\'s chart does not have.', 'creative-skateboard-builder' ), $values['text'] ?? '' );
				}
				/* translators: 1: a spec's name. 2: a source. 3: what the source holds. */
				return sprintf( __( '%1$s: %2$s holds "%3$s", which cannot be read as a value.', 'creative-skateboard-builder' ), $spec, $source( 'from' ), $values['text'] ?? '' );
			case CatalogueCheck::REINDEX_FAILED:
				return __( 'Reading every part again stopped after five failures. Save the Builder settings to start it again; WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' );
			case CatalogueCheck::IMPORT_FAILED:
				return __( 'The last specs import stopped before its end. Import the file again; WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' );
			case CatalogueCheck::JOBS_FAILED:
				/* translators: %s: a number of jobs. */
				return sprintf( _n( '%s background job of the builder has failed.', '%s background jobs of the builder have failed.', (int) ( $values['count'] ?? '0' ), 'creative-skateboard-builder' ), number_format_i18n( (int) ( $values['count'] ?? '0' ) ) );
			case CatalogueCheck::SLOT_EMPTY:
				/* translators: %s: a slot's key, such as trucks. */
				return sprintf( __( 'The slot "%s" has no parts.', 'creative-skateboard-builder' ), $finding->slot );
			case CatalogueCheck::SLOT_NO_STOCK:
				/* translators: %s: a slot's key, such as trucks. */
				return sprintf( __( 'The slot "%s" has nothing in stock.', 'creative-skateboard-builder' ), $finding->slot );
			case CatalogueCheck::SPEC_MISSING:
				/* translators: 1: a spec's name. 2: a slot's key. */
				return sprintf( __( '%1$s is missing, so the part is not offered in the slot "%2$s".', 'creative-skateboard-builder' ), $spec, $finding->slot );
			case CatalogueCheck::ASSUMED:
				/* translators: %s: a spec's name. */
				return sprintf( __( '%s is not filled in, so the builder assumes the usual one.', 'creative-skateboard-builder' ), $spec );
			case CatalogueCheck::PRESET:
				$problems = array(
					/* translators: 1: a preset's name. 2: a slot's key. */
					'part_not_in_slot' => __( 'The preset "%1$s" names it for the slot "%2$s", and it is not one of that slot\'s parts now.', 'creative-skateboard-builder' ),
					/* translators: 1: a preset's name. 2: a slot's key. */
					'part_sold_out'    => __( 'The preset "%1$s" names it for the slot "%2$s", and it is sold out.', 'creative-skateboard-builder' ),
				);
				/* translators: 1: a preset's name. 2: a slot's key. */
				return sprintf( $problems[ $values['problem'] ?? '' ] ?? __( 'The preset "%1$s" names it for the slot "%2$s", and it no longer fits the preset\'s other parts.', 'creative-skateboard-builder' ), $values['preset'] ?? '', $finding->slot );
		}
		return $finding->code;
	}
}
