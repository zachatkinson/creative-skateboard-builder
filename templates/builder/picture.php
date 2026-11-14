<?php
/**
 * The picture, for now (spec: The picture): what is in the box, each part's photo labelled with its slot, brand and
 * size, and the build in one sentence.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<figure class="cbuild-picture" id="cbuild-picture" tabindex="-1">
	<ul class="cbuild-picture__items" data-wp-bind--aria-label="state.view.picture.heading">
		<template data-wp-each--item="state.view.picture.items" data-wp-each-key="context.item.key">
			<li data-wp-bind--class="context.item.classes">
				<img class="cbuild-picture__photo" alt="" decoding="async" data-wp-bind--loading="context.item.loading" data-wp-bind--fetchpriority="context.item.priority" data-wp-bind--hidden="!context.item.hasImage" data-wp-bind--src="context.item.src" data-wp-bind--srcset="context.item.srcset" data-wp-bind--sizes="context.item.sizes" data-wp-bind--width="context.item.width" data-wp-bind--height="context.item.height">
				<span class="cbuild-picture__label" data-wp-text="context.item.label"></span>
			</li>
		</template>
	</ul>
	<figcaption class="cbuild-picture__description" data-wp-bind--hidden="!state.view.picture.captioned" data-wp-text="state.view.picture.description"></figcaption>
</figure>
