<?php
/**
 * WooCommerce > Builder: the page's tabs.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{label: string, url: string, current: bool}> $tabs The tabs.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<nav class="nav-tab-wrapper" aria-label="<?php esc_attr_e( 'Builder', 'creative-skateboard-builder' ); ?>">
	<?php foreach ( $tabs as $cbuild_tab ) : ?>
		<a
			class="nav-tab<?php echo $cbuild_tab['current'] ? ' nav-tab-active' : ''; ?>"
			href="<?php echo esc_url( $cbuild_tab['url'] ); ?>"
			<?php if ( $cbuild_tab['current'] ) : ?>
				aria-current="page"
			<?php endif; ?>
		><?php echo esc_html( $cbuild_tab['label'] ); ?></a>
	<?php endforeach; ?>
</nav>
