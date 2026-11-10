<?php
/**
 * The floors, checked before the autoloader loads (D-023).
 *
 * The main file requires this file before Composer's autoloader, so it must parse on PHP 7.4, the oldest PHP
 * WordPress 7.0 and 7.1 run on: no union types, no readonly, no enums, no match, no named arguments. CI lints it
 * on 7.4.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Support;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The PHP, WordPress and WooCommerce floors (D-004), and the one notice shown below any of them.
 */
final class Requirements {

	public const PHP         = '8.5';
	public const WORDPRESS   = '7.0';
	public const WOOCOMMERCE = '11.2';

	public const SHORT_PHP         = 'php';
	public const SHORT_WORDPRESS   = 'wordpress';
	public const SHORT_WOOCOMMERCE = 'woocommerce';

	/**
	 * Which platform floor a site is below, or null when PHP and WordPress are both new enough.
	 *
	 * @since 0.1.0
	 *
	 * @param string $php       The running PHP version.
	 * @param string $wordpress The running WordPress version.
	 */
	public static function platformShortfall( string $php, string $wordpress ): ?string {
		if ( version_compare( $php, self::PHP, '<' ) ) {
			return self::SHORT_PHP;
		}
		if ( version_compare( $wordpress, self::WORDPRESS, '<' ) ) {
			return self::SHORT_WORDPRESS;
		}
		return null;
	}

	/**
	 * Whether WooCommerce is missing or too old.
	 *
	 * @since 0.1.0
	 *
	 * @param string|null $woocommerce WooCommerce's version, or null when it is not active.
	 */
	public static function woocommerceShortfall( ?string $woocommerce ): ?string {
		if ( null === $woocommerce || version_compare( $woocommerce, self::WOOCOMMERCE, '<' ) ) {
			return self::SHORT_WOOCOMMERCE;
		}
		return null;
	}

	/**
	 * The notice shown when any floor is not met, escaped for wp_admin_notice().
	 *
	 * @since 0.1.0
	 */
	public static function notice(): string {
		return sprintf(
			/* translators: 1: the PHP version the plugin needs, 2: the WordPress version, 3: the WooCommerce version. */
			esc_html__( 'Creative Skateboard Builder needs PHP %1$s, WordPress %2$s and WooCommerce %3$s or newer, and does nothing until they are in place.', 'creative-skateboard-builder' ),
			self::PHP,
			self::WORDPRESS,
			self::WOOCOMMERCE
		);
	}

	/**
	 * The notice's markup, for a WordPress without wp_admin_notice() (below 6.4): the notice for a WordPress below
	 * the floor must not itself be a fatal error there.
	 *
	 * @since 0.1.0
	 *
	 * @param string $notice The notice, already escaped.
	 */
	public static function noticeMarkup( string $notice ): string {
		return '<div class="notice notice-error"><p>' . $notice . '</p></div>';
	}

	/**
	 * Print the notice: through wp_admin_notice() where WordPress has it, as its own markup where it does not.
	 *
	 * @since 0.1.0
	 *
	 * @param string $notice The notice, already escaped.
	 */
	public static function printNotice( string $notice ): void {
		if ( function_exists( 'wp_admin_notice' ) ) {
			wp_admin_notice( $notice, array( 'type' => 'error' ) );
			return;
		}
		echo wp_kses_post( self::noticeMarkup( $notice ) );
	}
}
