<?php
/**
 * Kits' documents in memory.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\KitMeta;

/**
 * Kits' documents, by kit and meta key, and each write counted.
 */
final class InMemoryKitMeta implements KitMeta {

	/**
	 * Each write, kit and key.
	 *
	 * @var list<array{int, string}>
	 */
	public array $writes = array();

	/**
	 * Constructor.
	 *
	 * @param array<int, array<string, array<string, mixed>>> $documents The documents, by kit and meta key.
	 */
	public function __construct( public array $documents = array() ) {}

	public function ids(): array {
		return array_keys( $this->documents );
	}

	public function read( int $kit, string $key ): ?array {
		return $this->documents[ $kit ][ $key ] ?? null;
	}

	public function write( int $kit, string $key, array $document ): void {
		$this->writes[]                   = array( $kit, $key );
		$this->documents[ $kit ][ $key ] = $document;
	}
}
