<?php
/**
 * The shop's settings, in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;

/**
 * Settings a test sets.
 */
final class InMemoryShopSettings implements ShopSettings {

	public ?SourceOrder $order = null;

	/**
	 * The mapping.
	 *
	 * @var list<\CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap>
	 */
	public array $mapping = array();

	public function attributeMapping(): array {
		return $this->mapping;
	}

	public function sourceOrder(): SourceOrder {
		return $this->order ?? SourceOrder::standard();
	}

	public int $saves = 0;

	public ?BuildTerms $terms = null;

	public function terms(): BuildTerms {
		return $this->terms ?? BuildTerms::read( null );
	}

	public function save( array $mapping, SourceOrder $order, ?BuildTerms $terms = null ): void {
		$this->mapping = $mapping;
		$this->order   = $order;
		$this->terms   = $terms ?? $this->terms;
		++$this->saves;
	}

	public function indexedFrom( mixed $stored ): array {
		return \CreativeBranding\SkateboardBuilder\Adapter\OptionShopSettings::indexed( $stored );
	}
}
