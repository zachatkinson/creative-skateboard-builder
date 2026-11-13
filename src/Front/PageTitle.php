<?php
/**
 * The page's title on a builder's page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec, Keyboard and screen readers: "The page title and the heading both say the step", and "Error:" first when an
 * error summary is shown (the view's `title` says both). The step is put before the title the page has anyway, its
 * SEO plugin's or WordPress's own, through one translatable pattern; the store puts each later step in the same place
 * (D-149).
 *
 * @since 0.6.0
 */
final class PageTitle {

	/**
	 * Whether the page's own title is being asked for, so that the builder's hook leaves it alone.
	 *
	 * @var bool
	 */
	private static bool $asking = false;

	/**
	 * Whether the builder's hook should leave the title alone, because the page's own is being asked for.
	 *
	 * @since 0.6.0
	 */
	public static function asking(): bool {
		return self::$asking;
	}

	/**
	 * The title the page has without the builder's step, as plain text: what an SEO plugin or WordPress writes.
	 *
	 * @since 0.6.0
	 */
	public static function base(): string {
		self::$asking = true;
		try {
			$title = wp_get_document_title();
		} finally {
			self::$asking = false;
		}
		return html_entity_decode( wp_strip_all_tags( $title ), ENT_QUOTES | ENT_HTML5, 'UTF-8' );
	}

	/**
	 * What goes before and after the step in the page's title, as plain text.
	 *
	 * @since 0.6.0
	 *
	 * @param string $base The page's own title.
	 * @return array{before: string, after: string}
	 */
	public static function around( string $base ): array {
		$mark = "\u{E000}";
		/* translators: 1: the builder's step, such as "Step 2 of 7: Trucks". 2: the page's own title, such as "Build a complete - Skatehouse". */
		$title = sprintf( __( '%1$s - %2$s', 'creative-skateboard-builder' ), $mark, $base );
		$parts = explode( $mark, $title, 2 );
		return array(
			'before' => $parts[0],
			'after'  => $parts[1] ?? '',
		);
	}
}
