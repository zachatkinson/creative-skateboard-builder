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
<section class="cbuild-presets" aria-labelledby="cbuild-presets-heading" data-wp-bind--hidden="!state.view.presets.shown">
	<h3 class="cbuild-presets__heading" id="cbuild-presets-heading" data-wp-text="state.view.presets.heading"></h3>
	<ul class="cbuild-presets__tiles">
		<template data-wp-each--tile="state.view.presets.tiles" data-wp-each-key="context.tile.button">
			<li class="cbuild-preset">
				<form class="cbuild-preset__form" method="get" data-wp-on--submit="actions.submit" data-wp-bind--action="state.view.action">
					<template data-wp-each--field="state.view.carry">
						<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
					</template>
					<template data-wp-each--field="context.tile.fields">
						<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
					</template>
					<img class="cbuild-preset__photo" alt="" loading="lazy" decoding="async" data-wp-bind--hidden="!context.tile.hasImage" data-wp-bind--src="context.tile.src" data-wp-bind--srcset="context.tile.srcset" data-wp-bind--sizes="context.tile.sizes" data-wp-bind--width="context.tile.width" data-wp-bind--height="context.tile.height">
					<p class="cbuild-preset__name" data-wp-text="context.tile.name"></p>
					<p class="cbuild-preset__description" data-wp-bind--hidden="!context.tile.hasDescription" data-wp-text="context.tile.description"></p>
					<p class="cbuild-preset__total" data-wp-text="context.tile.total"></p>
					<ul class="cbuild-preset__notes">
						<template data-wp-each--note="context.tile.notes">
							<li data-wp-text="context.note.text"></li>
						</template>
					</ul>
					<button class="cbuild-button cbuild-preset__start" type="submit" data-wp-text="context.tile.button"></button>
				</form>
			</li>
		</template>
	</ul>
</section>
