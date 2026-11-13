<?php
/**
 * A kit's documents, as stored.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A kit product's documents as they are stored, unread: what a migration of a document's content needs.
 *
 * @since 0.6.0
 */
interface KitMeta {

	/**
	 * Every kit product's ID: all but those in the bin and those WordPress made and nobody saved.
	 *
	 * @since 0.6.0
	 *
	 * @return list<int>
	 */
	public function ids(): array;


	/**
	 * A kit's document as stored, decoded, or null where it has none or it does not decode to an object.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $kit The kit product's ID.
	 * @param string $key The document's meta key.
	 * @return array<string, mixed>|null
	 */
	public function read( int $kit, string $key ): ?array;

	/**
	 * Store a kit's document.
	 *
	 * @since 0.6.0
	 *
	 * @param int                  $kit      The kit product's ID.
	 * @param string               $key      The document's meta key.
	 * @param array<string, mixed> $document The document.
	 */
	public function write( int $kit, string $key, array $document ): void;
}
