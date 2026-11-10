<?php
/**
 * Kit products for the integration tests.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support;

use CreativeBranding\SkateboardBuilder\Adapter\KitProduct;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;

/**
 * Makes a saved kit with the domain fixtures' documents, which the schemas accept (KitSchemasTest).
 */
final class KitFactory {

	/**
	 * A published kit with the complete set of documents.
	 *
	 * @param string                                   $name      The kit's name.
	 * @param array<string, array<string, mixed>|null> $documents Documents in place of the fixtures', by meta key; null leaves one out.
	 */
	public static function kit( string $name = 'Complete skateboard', array $documents = array() ): KitProduct {
		$documents += array(
			Options::META_KIT_SLOTS    => KitDocuments::slots(),
			Options::META_KIT_RULES    => KitDocuments::rules(),
			Options::META_KIT_PRESETS  => KitDocuments::presets(),
			Options::META_KIT_ASSEMBLY => KitDocuments::assembly(),
		);
		$kit        = new KitProduct();
		$kit->set_name( $name );
		$kit->set_status( 'publish' );
		foreach ( $documents as $key => $document ) {
			if ( null !== $document ) {
				$kit->setDocument( $key, $document );
			}
		}
		$kit->save();
		return $kit;
	}
}
