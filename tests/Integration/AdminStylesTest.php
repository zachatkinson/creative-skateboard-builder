<?php
/**
 * Each admin screen's own rules print on a style handle the plugin owns, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Admin\BuildTab;
use CreativeBranding\SkateboardBuilder\Admin\CheckScreen;
use CreativeBranding\SkateboardBuilder\Admin\ImportScreen;
use CreativeBranding\SkateboardBuilder\Admin\PartSpecsPanel;
use CreativeBranding\SkateboardBuilder\Admin\SpecTablesScreen;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Milestone 13's walkthrough (D-450): My builds' rules sat on WooCommerce's `woocommerce-general`, which Kadence and
 * Storefront dequeue, and were lost. The admin screens' rules sat on handles the plugin does not own either: core's
 * `common` and WooCommerce's `woocommerce_admin_styles`, printed only while another enqueues them. Each screen's rules
 * print with what the screen itself enqueues.
 */
final class AdminStylesTest extends IntegrationTestCase {

	/**
	 * Each screen, and a rule of its own.
	 *
	 * @return array<string, array{class-string, string}>
	 */
	public static function screens(): array {
		return array(
			'Catalogue check' => array( CheckScreen::class, '.cbuild-scroll{overflow-x:auto' ),
			'Import'          => array( ImportScreen::class, '.cbuild-scroll{overflow-x:auto' ),
			'Spec tables'     => array( SpecTablesScreen::class, '.cbuild-field{display:inline-block;' ),
			'Build tab'       => array( BuildTab::class, '.cbuild-kit .cbuild-kit-group{margin:0 12px 12px;' ),
			'Part specs'      => array( PartSpecsPanel::class, '.cbuild-part-specs summary{padding:8px 12px;' ),
		);
	}

	/**
	 * The screen's rules print with no other handle enqueued.
	 *
	 * @dataProvider screens
	 *
	 * @param class-string $screen The screen's class.
	 * @param string       $rule   A rule of its own.
	 */
	public function test_a_screens_rules_print_on_its_own_handle( string $screen, string $rule ): void {
		$kept = $GLOBALS['wp_styles'] ?? null;
		try {
			// A fresh set of styles: core's and WooCommerce's handles registered or not, none of them enqueued.
			$GLOBALS['wp_styles'] = new \WP_Styles(); // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- A fresh set of styles for the test, restored below.
			$service              = $this->service( $screen );
			self::assertTrue( method_exists( $service, 'styles' ) );
			$service->styles();
			ob_start();
			wp_styles()->do_items();
			self::assertStringContainsString( $rule, (string) ob_get_clean() );
		} finally {
			$GLOBALS['wp_styles'] = $kept; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- Restored.
			wp_dequeue_script( 'cbuild-part-specs' );
			wp_deregister_script( 'cbuild-part-specs' );
			wp_dequeue_script( 'cbuild-build-tab' );
			wp_deregister_script( 'cbuild-build-tab' );
		}
	}
}
