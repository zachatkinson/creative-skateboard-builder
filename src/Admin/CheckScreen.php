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
use CreativeBranding\SkateboardBuilder\Port\Reindex;
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
	 * Whether a reindex was asked for in this request.
	 *
	 * @var bool
	 */
	private bool $asked = false;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param CatalogueCheck $check   The check.
	 * @param Kits           $kits    The kits, for their names.
	 * @param Reindex        $reindex Asks for every part to be read again.
	 */
	public function __construct( private CatalogueCheck $check, private Kits $kits, private Reindex $reindex ) {}

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
	 * Run the check, or ask for every part to be read again, when the form for it was sent.
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
		} elseif ( 'reindex' === $_POST['cbuild_action'] ) {
			$this->reindex->requestReindex();
			$this->asked = true;
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
				// The product's screen opens the tab, or the variation, the address names (PartSpecsPanel).
				$link = (string) get_edit_post_link( $owner, 'raw' ) . ( $owner === $product->get_id() ? '#cbuild_part_specs' : '#cbuild_variation_' . $product->get_id() );
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
				'says'  => Sentences::finding( $finding ),
			);
		}
		$more  = count( $found ) - count( $rows );
		$count = count( $found );
		$tabs  = BuilderHooks::tabs( BuilderHooks::TAB_CHECK );
		$nonce = self::NONCE;
		$back  = self::url();
		$none  = array() === $kits;
		$asked = $this->asked;
		include CBUILD_PATH . 'templates/admin/check.php';
	}
}
