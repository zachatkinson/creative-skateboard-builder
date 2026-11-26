<?php
/**
 * A ready build's tile, in a list of them (spec: Presets; #134): one GET form whose button starts the build at the
 * review. Each shows its picture, its name, a line on what it is, its total and anything to note. The shop's presets
 * and the guided start's builds are both these tiles, from one view (`BuilderView::tile()`), bound to `context.tile`.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<li class="cbuild-preset">
	<form class="cbuild-preset__form" method="get" data-wp-on--submit="actions.submit" data-wp-bind--action="state.view.action">
		<template data-wp-each--field="state.view.carry">
			<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
		</template>
		<template data-wp-each--field="context.tile.fields">
			<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
		</template>
		<img class="cbuild-preset__photo" data-wp-bind--class="context.tile.photoClasses" alt="" loading="lazy" decoding="async" data-wp-bind--hidden="!context.tile.hasImage" data-wp-bind--src="context.tile.src" data-wp-bind--srcset="context.tile.srcset" data-wp-bind--sizes="context.tile.sizes" data-wp-bind--width="context.tile.width" data-wp-bind--height="context.tile.height">
		<p class="cbuild-preset__name" data-wp-text="context.tile.name"></p>
		<p class="cbuild-preset__description" data-wp-bind--hidden="!context.tile.hasDescription" data-wp-text="context.tile.description"></p>
		<p class="cbuild-preset__total" data-wp-bind--hidden="!context.tile.hasTotal" data-wp-text="context.tile.total"></p>
		<ul class="cbuild-preset__notes">
			<template data-wp-each--note="context.tile.notes">
				<li data-wp-text="context.note.text"></li>
			</template>
		</ul>
		<button class="cbuild-button cbuild-preset__start" type="submit" data-wp-text="context.tile.button"></button>
	</form>
</li>
