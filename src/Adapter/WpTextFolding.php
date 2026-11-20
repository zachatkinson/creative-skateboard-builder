<?php
/**
 * Search's folding, by WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Adapter;

use CreativeBranding\SkateboardBuilder\Port\TextFolding;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Folds with WordPress's `remove_accents()`, in the site's locale, then into lower case: with mbstring where PHP has
 * it, so a letter outside ASCII is lowered too.
 *
 * @since 1.0.0
 */
final class WpTextFolding implements TextFolding {

	/**
	 * The text folded.
	 *
	 * @since 1.0.0
	 *
	 * @param string $text Plain text.
	 */
	public function fold( string $text ): string {
		$plain = remove_accents( $text );
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $plain, 'UTF-8' ) : strtolower( $plain );
	}
}
