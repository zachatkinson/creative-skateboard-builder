<?php
/**
 * Shipping zones that answer as told.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\ShippingRates;
use CreativeBranding\SkateboardBuilder\Port\ShippingZones;

/**
 * The rates given, or none: a shopper with no address held.
 */
final class FakeShippingZones implements ShippingZones {

	public function __construct( private ?ShippingRates $rates = null ) {}

	public function rates(): ?ShippingRates {
		return $this->rates;
	}
}
