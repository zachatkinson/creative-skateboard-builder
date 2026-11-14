<?php
/**
 * The Cart and Checkout blocks' integration: the script that groups a build runs before the block draws.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Automattic\WooCommerce\Blocks\Integrations\IntegrationInterface;

defined( 'ABSPATH' ) || exit;

/**
 * WooCommerce makes each integration's scripts dependencies of the block's own (WooCommerce 11.3.0,
 * src/Blocks/BlockTypes/AbstractBlock.php:211-215), so `lines.js` has registered its line filters before the block's
 * first drawing, which reads them once (D-173). The handle is registered here with no file, so a page whose cart holds
 * no build loads nothing of the plugin; `ShowHooks::lines()` gives it the file where the cart holds one.
 */
final class LinesIntegration implements IntegrationInterface {

	/**
	 * The integration's name.
	 *
	 * @since 0.6.0
	 */
	public function get_name(): string {
		return 'creative-skateboard-builder';
	}

	/**
	 * Register the handle, with no file yet.
	 *
	 * @since 0.6.0
	 */
	public function initialize(): void {
		if ( ! wp_script_is( ShowHooks::HANDLE, 'registered' ) ) {
			wp_register_script( ShowHooks::HANDLE, false, array(), CBUILD_VERSION, array( 'in_footer' => true ) );
		}
	}

	/**
	 * The scripts the block's script waits for.
	 *
	 * @since 0.6.0
	 *
	 * @return list<string>
	 */
	public function get_script_handles(): array {
		return array( ShowHooks::HANDLE );
	}

	/**
	 * The editor's scripts: none, since the editor draws no cart of the shopper's.
	 *
	 * @since 0.6.0
	 *
	 * @return list<string>
	 */
	public function get_editor_script_handles(): array {
		return array();
	}

	/**
	 * Data for the scripts: none.
	 *
	 * @since 0.6.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_script_data(): array {
		return array();
	}
}
