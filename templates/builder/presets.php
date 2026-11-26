<?php
/**
 * The preset tiles (spec: Presets): at the start, one GET form a preset, its button starting the build at the review.
 * Each tile shows its picture, for now its deck's photo, and says its total, who it is for, and any part that keeps it
 * from the cart.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="cbuild-presets" data-wp-interactive="creative-skateboard-builder" aria-labelledby="cbuild-presets-heading" data-wp-bind--hidden="!state.view.presets.shown">
	<h3 class="cbuild-presets__heading" id="cbuild-presets-heading" data-wp-text="state.view.presets.heading"></h3>
	<ul class="cbuild-presets__tiles">
		<template data-wp-each--tile="state.view.presets.tiles" data-wp-each-key="context.tile.button">
			<?php require __DIR__ . '/tile.php'; ?>
		</template>
	</ul>
</section>
