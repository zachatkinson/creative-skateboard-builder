<?php
/**
 * The Catalogue check, at WooCommerce > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
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
		// Parts with no layer image are listed apart, each list cut at its own length: on a large catalogue they would
		// otherwise fill the page after what keeps a part out (Cowork's M7 review, F4; D-211).
		$fixes  = array_values( array_filter( $found, static fn( CheckFinding $finding ): bool => ! in_array( $finding->code, array( CatalogueCheck::LAYER_MISSING, CatalogueCheck::LOOK_SHARED ), true ) ) );
		$layers = array_values( array_filter( $found, static fn( CheckFinding $finding ): bool => CatalogueCheck::LAYER_MISSING === $finding->code ) );
		// Variations that share a card, in a list of their own after the layers: what the builder does, not a fix (D-281).
		$looks  = array_values( array_filter( $found, static fn( CheckFinding $finding ): bool => CatalogueCheck::LOOK_SHARED === $finding->code ) );
		$alike  = $this->rows( array_slice( $looks, 0, self::SHOWN ), $kits );
		$beyond = count( $looks ) - count( $alike );
		$rows   = $this->rows( array_slice( $fixes, 0, self::SHOWN ), $kits );
		$more   = count( $fixes ) - count( $rows );
		$count  = count( $fixes );
		$bare   = $this->rows( array_slice( $layers, 0, self::SHOWN ), $kits );
		$past   = count( $layers ) - count( $bare );
		$unlaid = count( array_unique( array_map( static fn( CheckFinding $finding ): int => $finding->part, $layers ) ) );
		$tabs   = BuilderHooks::tabs( BuilderHooks::TAB_CHECK );
		$nonce  = self::NONCE;
		$back   = self::url();
		$none   = array() === $kits;
		$asked  = $this->asked;
		include CBUILD_PATH . 'templates/admin/check.php';
	}
	/**
	 * Findings as the table's rows.
	 *
	 * @param list<CheckFinding>                        $found The findings.
	 * @param array<int, array{name: string, kit: Kit}> $kits  The kits that read, by ID.
	 * @return list<array{kit: string, about: string, link: string, says: string}>
	 */
	private function rows( array $found, array $kits ): array {
		// The parts, their terms, their meta and their parents read together, so the table asks no query a row (the M9 audit's
		// minor 11). WooCommerce reads a product from these caches.
		$ids = array_values( array_unique( array_filter( array_map( static fn( CheckFinding $finding ): int => $finding->part, $found ), static fn( int $part ): bool => $part > 0 ) ) );
		if ( array() !== $ids ) {
			_prime_post_caches( $ids, true, true );
			$parents = array();
			foreach ( $ids as $id ) {
				$post = get_post( $id );
				if ( $post instanceof \WP_Post && $post->post_parent > 0 ) {
					$parents[] = $post->post_parent;
				}
			}
			_prime_post_caches( array_values( array_unique( $parents ) ), true, true );
		}
		$rows = array();
		foreach ( $found as $finding ) {
			$product = 0 === $finding->part ? null : wc_get_product( $finding->part );
			$about   = '';
			$link    = '';
			if ( $product instanceof \WC_Product ) {
				$owner = 0 === $product->get_parent_id() ? $product->get_id() : $product->get_parent_id();
				$about = '' === $product->get_sku() ? $product->get_name() : $product->get_name() . ' (' . $product->get_sku() . ')';
				// The product's screen opens the tab, or the variation, the address names (PartSpecsPanel).
				$field = CatalogueCheck::LAYER_MISSING === $finding->code ? '#cbuild-spec-layer' : '#cbuild_part_specs';
				$link  = (string) get_edit_post_link( $owner, 'raw' ) . ( $owner === $product->get_id() ? $field : '#cbuild_variation_' . $product->get_id() );
			} elseif ( 0 !== $finding->part ) {
				/* translators: %s: a product's ID. */
				$about = sprintf( __( 'Product %s', 'creative-skateboard-builder' ), (string) $finding->part );
			} elseif ( 0 === $finding->kit ) {
				$about = __( 'Background work', 'creative-skateboard-builder' );
				$link  = admin_url( 'admin.php?page=wc-status&tab=action-scheduler&status=failed' );
			}
			$kit = 0 === $finding->kit ? '' : ( $kits[ $finding->kit ]['name'] ?? '' );
			if ( 0 !== $finding->kit && '' === $kit ) {
				// A kit that does not read has no name among those that do: its product's, and a link to its Build tab.
				$kit   = get_the_title( $finding->kit );
				$about = __( 'The kit\'s Build tab', 'creative-skateboard-builder' );
				$link  = (string) get_edit_post_link( $finding->kit, 'raw' ) . '#cbuild_build';
			}
			$rows[] = array(
				'kit'   => $kit,
				'about' => $about,
				'link'  => $link,
				'says'  => Sentences::finding( $finding ),
			);
		}
		return $rows;
	}
}
