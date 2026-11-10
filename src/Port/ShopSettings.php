<?php
/**
 * The shop's settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What a shop set at WooCommerce > Settings > Builder, as the services read it (spec: Admin, Settings). Each setting
 * arrives with the milestone that reads it.
 *
 * @since 0.3.0
 */
interface ShopSettings {

	/**
	 * Which source wins where two disagree: the maker's chart first unless the shop says otherwise (D-026).
	 *
	 * @since 0.3.0
	 */
	public function sourceOrder(): SourceOrder;
}
