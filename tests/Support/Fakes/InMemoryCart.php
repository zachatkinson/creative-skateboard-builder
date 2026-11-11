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
use CreativeBranding\SkateboardBuilder\Service\BuildLines;
use RuntimeException;

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

	/**
	 * The lines removed that an undo can bring back, by key.
	 *
	 * @var array<string, CartLine>
	 */
	public array $bin = array();

	/**
	 * Each quantity set, by key: the last one.
	 *
	 * @var array<string, int>
	 */
	public array $resized = array();

	/**
	 * The service told of each change, as WooCommerce's hooks tell it.
	 */
	public ?BuildLines $hears = null;

	/**
	 * The lines the cart throws on being asked to remove.
	 *
	 * @var list<string>
	 */
	public array $stuck = array();

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
		if ( ! isset( $this->lines[ $key ] ) ) {
			return;
		}
		if ( in_array( $key, $this->stuck, true ) ) {
			throw new RuntimeException( 'The cart would not remove ' . $key . '.' );
		}
		// As WooCommerce does: the line is still there while its removal is announced.
		$this->hears?->removing( $key );
		if ( isset( $this->lines[ $key ] ) ) {
			$this->removed[]   = $key;
			$this->bin[ $key ] = $this->lines[ $key ];
			unset( $this->lines[ $key ] );
		}
	}

	public function restore( string $key ): void {
		if ( isset( $this->bin[ $key ] ) ) {
			$this->lines[ $key ] = $this->bin[ $key ];
			unset( $this->bin[ $key ] );
			$this->hears?->restored( $key );
		}
	}

	public function setQuantity( string $key, int $quantity ): void {
		$line                  = $this->lines[ $key ];
		$this->resized[ $key ] = $quantity;
		$this->lines[ $key ]   = new CartLine( $key, $line->part, $quantity, $line->build, $line->children, $line->kit, $line->slot );
		$this->hears?->resized( $key );
	}

	public function setChildren( string $key, array $children ): void {
		$line                = $this->lines[ $key ];
		$this->lines[ $key ] = new CartLine( $key, $line->part, $line->quantity, $line->build, $children, $line->kit, $line->slot );
	}
}
