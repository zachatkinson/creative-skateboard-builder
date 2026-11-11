<?php
/**
 * A cart in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\Cart;
use CreativeBranding\SkateboardBuilder\Port\CartLine;

/**
 * Holds lines as WooCommerce's cart does, with keys of its own making.
 */
final class InMemoryCart implements Cart {

	/**
	 * The lines, by key.
	 *
	 * @var array<string, CartLine>
	 */
	public array $lines = array();

	/**
	 * The parts the cart will not add, as WooCommerce refuses one it cannot sell.
	 *
	 * @var list<int>
	 */
	public array $refuses = array();

	/**
	 * The keys removed, in order.
	 *
	 * @var list<string>
	 */
	public array $removed = array();

	private int $next = 0;

	/**
	 * Put a line in the cart as a shopper's request does.
	 *
	 * @param int   $part     What it sells.
	 * @param int   $quantity How many.
	 * @param mixed $build    The key `cbuild`, for a kit's line.
	 */
	public function put( int $part, int $quantity = 1, mixed $build = null ): string {
		$key                 = 'line-' . ( ++$this->next );
		$this->lines[ $key ] = new CartLine( $key, $part, $quantity, $build );
		return $key;
	}

	public function lines(): array {
		return $this->lines;
	}

	public function add( int $part, int $quantity, string $kit, string $slot ): ?string {
		if ( in_array( $part, $this->refuses, true ) ) {
			return null;
		}
		$key                 = 'line-' . ( ++$this->next );
		$this->lines[ $key ] = new CartLine( $key, $part, $quantity, null, array(), $kit, $slot );
		return $key;
	}

	public function remove( string $key ): void {
		$this->removed[] = $key;
		unset( $this->lines[ $key ] );
	}

	public function setQuantity( string $key, int $quantity ): void {
		$line                = $this->lines[ $key ];
		$this->lines[ $key ] = new CartLine( $key, $line->part, $quantity, $line->build, $line->children, $line->kit, $line->slot );
	}

	public function setChildren( string $key, array $children ): void {
		$line                = $this->lines[ $key ];
		$this->lines[ $key ] = new CartLine( $key, $line->part, $line->quantity, $line->build, $children, $line->kit, $line->slot );
	}
}
