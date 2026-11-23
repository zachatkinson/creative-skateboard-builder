<?php
/**
 * The picture (spec: The picture; D-195): the board, each part with a layer image drawn to scale on it, then what is
 * in the box, the photo of each other part labelled with its slot, brand and size, and the build in one sentence. The
 * layers are described by the sentence, so each is empty to a screen reader.
 *
 * From 0.8.0 the board has a top as well (#46): the deck's outline, the grip filled into it, the bolt heads and the
 * wheels at the edges, each layer an element the stylesheet paints from its style. One side shows at a time. The
 * grip step shows the top, and the button by the board turns it over, with the script; the sentence says which side
 * is shown.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<figure class="cbuild-picture" id="cbuild-picture" tabindex="-1" aria-labelledby="cbuild-picture-heading" data-wp-class--cbuild-picture--top="state.view.picture.turned" data-wp-class--cbuild-picture--turning="state.turning" data-wp-class--cbuild-picture--hero="state.view.picture.hero">
	<h3 class="cbuild-picture__heading" id="cbuild-picture-heading" data-wp-text="state.view.picture.heading"></h3>
	<?php // The side shown, under the heading; the caption says it with the build, so it is not read twice. A deck's photo in the board's place shows no side (#82). ?>
	<p class="cbuild-picture__side" aria-hidden="true" data-wp-bind--hidden="state.view.picture.hero"><span data-wp-bind--hidden="state.view.picture.turned" data-wp-text="state.view.picture.side.under"></span><span data-wp-bind--hidden="!state.view.picture.turned" data-wp-text="state.view.picture.side.top"></span></p>
	<?php // Until a part is picked: where the board will be, and what to do. ?>
	<p class="cbuild-picture__empty" data-wp-bind--hidden="state.view.picture.captioned" data-wp-text="state.view.picture.empty"></p>
	<div class="cbuild-board" dir="ltr" data-wp-bind--hidden="!state.view.picture.board.shown" data-wp-bind--style="state.view.picture.board.style">
		<template data-wp-each--layer="state.view.picture.board.layers" data-wp-each-key="context.layer.key">
			<picture data-wp-bind--class="context.layer.classes" data-wp-bind--style="context.layer.style">
				<template data-wp-each--source="context.layer.sources" data-wp-each-key="context.source.type">
					<source data-wp-bind--type="context.source.type" data-wp-bind--srcset="context.source.srcset" data-wp-bind--sizes="context.source.sizes">
				</template>
				<img class="cbuild-board__image" alt="" decoding="async" loading="eager" data-wp-bind--fetchpriority="context.layer.priority" data-wp-bind--src="context.layer.src" data-wp-bind--width="context.layer.width" data-wp-bind--height="context.layer.height">
			</picture>
		</template>
	</div>
	<div class="cbuild-board cbuild-board--top" dir="ltr" data-wp-bind--hidden="!state.view.picture.top.shown" data-wp-bind--style="state.view.picture.top.style">
		<template data-wp-each--layer="state.view.picture.top.layers" data-wp-each-key="context.layer.key">
			<span data-wp-bind--class="context.layer.classes" data-wp-bind--style="context.layer.style"></span>
		</template>
	</div>
	<div class="cbuild-picture__turn" data-wp-bind--hidden="!state.view.picture.turnable">
		<button class="cbuild-button" type="button" data-wp-bind--hidden="!state.canTurn" data-wp-bind--aria-pressed="state.view.picture.turned" data-wp-on--click="actions.turn" data-wp-text="state.view.picture.turn"></button>
	</div>
	<ul class="cbuild-picture__items">
		<template data-wp-each--item="state.view.picture.items" data-wp-each-key="context.item.key">
			<li data-wp-bind--class="context.item.classes" data-wp-bind--hidden="context.item.drawn">
				<img class="cbuild-picture__photo" alt="" decoding="async" data-wp-bind--loading="context.item.loading" data-wp-bind--fetchpriority="context.item.priority" data-wp-bind--hidden="!context.item.hasImage" data-wp-bind--src="context.item.src" data-wp-bind--srcset="context.item.srcset" data-wp-bind--sizes="context.item.sizes" data-wp-bind--width="context.item.width" data-wp-bind--height="context.item.height">
				<span class="cbuild-picture__label" data-wp-text="context.item.label"></span>
			</li>
		</template>
	</ul>
	<figcaption class="cbuild-picture__description" data-wp-bind--hidden="!state.view.picture.captioned"><span data-wp-text="state.view.picture.description"></span><span data-wp-bind--hidden="!state.view.picture.sided"> <span data-wp-bind--hidden="state.view.picture.turned" data-wp-text="state.view.picture.seen.under"></span><span data-wp-bind--hidden="!state.view.picture.turned" data-wp-text="state.view.picture.seen.top"></span></span></figcaption>
</figure>
