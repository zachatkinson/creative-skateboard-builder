<?php
/**
 * The shop's settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
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

	/**
	 * Which attribute feeds which spec, and in which unit. None until the shop maps one.
	 *
	 * @since 0.3.0
	 *
	 * @return list<AttributeMap>
	 */
	public function attributeMapping(): array;

	/**
	 * Save both, leaving the shop's other settings as they are.
	 *
	 * @since 0.3.0
	 *
	 * @param list<AttributeMap> $mapping The mapping.
	 * @param SourceOrder        $order   Which source wins.
	 */
	public function save( array $mapping, SourceOrder $order ): void;
}
