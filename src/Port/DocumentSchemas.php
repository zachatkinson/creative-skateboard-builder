<?php
/**
 * The schemas of the documents the plugin stores.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Port;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A document is checked against its JSON Schema where it is stored and where it is read (spec: Data model). The
 * schemas ship in `schema/`, and WordPress's own validator reads them.
 *
 * @since 0.4.0
 */
interface DocumentSchemas {

	/**
	 * Why a document is refused, as the validator's code, or null when its schema accepts it.
	 *
	 * @since 0.4.0
	 *
	 * @param string $name     The document's name: `slots`, `rules`, `presets` or `assembly` for a kit's.
	 * @param mixed  $document The decoded document.
	 */
	public function refusal( string $name, mixed $document ): ?string;
}
