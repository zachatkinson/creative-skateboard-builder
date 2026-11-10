<?php
/**
 * The shop's settings, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;

/**
 * Settings a test sets.
 */
final class InMemoryShopSettings implements ShopSettings {

	public ?SourceOrder $order = null;

	public function sourceOrder(): SourceOrder {
		return $this->order ?? SourceOrder::standard();
	}
}
