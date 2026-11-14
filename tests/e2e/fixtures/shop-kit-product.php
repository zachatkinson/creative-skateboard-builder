<?php
/**
 * A product's page as Kadence Shop Kit's product template draws it, from the markup Claude in Cowork's recheck of
 * 0.6.0-rc.2 quotes (finding 2): a Kadence Blocks row of two columns, the gallery block in one and the title and the
 * add-to-cart block in the other, `#buy-box`. The row's ID class is the template's own, so the plugin must not name
 * it. Kadence Blocks is not installed in CI: the grid its stylesheet gives such a row is written out below.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

// phpcs:ignoreFile -- a test fixture outside the plugin.

get_header();
while ( have_posts() ) :
	the_post();
	?>
	<style>
		.kb-row-layout-wrap { max-width: 1290px; margin-inline: auto; }
		.kt-row-column-wrap { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr); gap: 2rem; }
		@media (max-width: 767px) { .kt-row-column-wrap.kt-mobile-layout-row { grid-template-columns: minmax(0, 1fr); } }
	</style>
	<div class="kb-row-layout-wrap kb-row-layout-id192_pdp-b alignnone wp-block-kadence-rowlayout">
		<div class="kt-row-column-wrap kt-has-2-columns kt-row-layout-equal kt-tab-layout-inherit kt-mobile-layout-row kt-row-valign-top">
			<div class="wp-block-kadence-column kadence-column192_gallery">
				<div class="kt-inside-inner-col">
					<div class="wp-block-kadence-wootemplate-blocks-gallery"><?php woocommerce_show_product_images(); ?></div>
				</div>
			</div>
			<div class="wp-block-kadence-column kadence-column192_buy" id="buy-box">
				<div class="kt-inside-inner-col">
					<h1 class="product_title entry-title"><?php the_title(); ?></h1>
					<div class="wp-block-kadence-wootemplate-blocks-add-to-cart"><?php woocommerce_template_single_add_to_cart(); ?></div>
				</div>
			</div>
		</div>
	</div>
	<?php
endwhile;
get_footer();
