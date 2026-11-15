<?php
/**
 * The picture (spec: The picture; D-195): the board, each part with a layer image drawn to scale on it, then what is
 * in the box, the photo of each other part labelled with its slot, brand and size, and the build in one sentence. The
 * layers are described by the sentence, so each is empty to a screen reader.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<figure class="cbuild-picture" id="cbuild-picture" tabindex="-1" aria-labelledby="cbuild-picture-heading">
	<h3 class="cbuild-picture__heading" id="cbuild-picture-heading" data-wp-bind--hidden="!state.view.picture.captioned" data-wp-text="state.view.picture.heading"></h3>
	<div class="cbuild-board" data-wp-bind--hidden="!state.view.picture.board.shown" data-wp-bind--style="state.view.picture.board.style">
		<template data-wp-each--layer="state.view.picture.board.layers" data-wp-each-key="context.layer.key">
			<picture data-wp-bind--class="context.layer.classes" data-wp-bind--style="context.layer.style">
				<template data-wp-each--source="context.layer.sources" data-wp-each-key="context.source.type">
					<source data-wp-bind--type="context.source.type" data-wp-bind--srcset="context.source.srcset" data-wp-bind--sizes="context.source.sizes">
				</template>
				<img class="cbuild-board__image" alt="" decoding="async" loading="eager" data-wp-bind--fetchpriority="context.layer.priority" data-wp-bind--src="context.layer.src" data-wp-bind--width="context.layer.width" data-wp-bind--height="context.layer.height">
			</picture>
		</template>
	</div>
	<ul class="cbuild-picture__items">
		<template data-wp-each--item="state.view.picture.items" data-wp-each-key="context.item.key">
			<li data-wp-bind--class="context.item.classes" data-wp-bind--hidden="context.item.drawn">
				<img class="cbuild-picture__photo" alt="" decoding="async" data-wp-bind--loading="context.item.loading" data-wp-bind--fetchpriority="context.item.priority" data-wp-bind--hidden="!context.item.hasImage" data-wp-bind--src="context.item.src" data-wp-bind--srcset="context.item.srcset" data-wp-bind--sizes="context.item.sizes" data-wp-bind--width="context.item.width" data-wp-bind--height="context.item.height">
				<span class="cbuild-picture__label" data-wp-text="context.item.label"></span>
			</li>
		</template>
	</ul>
	<figcaption class="cbuild-picture__description" data-wp-bind--hidden="!state.view.picture.captioned" data-wp-text="state.view.picture.description"></figcaption>
</figure>
