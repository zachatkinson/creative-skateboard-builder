<?php
/**
 * The footer (spec: The layout, Footer): the total and the estimate, which open the parts list above it; the delivery
 * line and the notes; Back; and the step's own Next, or the review's Add to cart, each a button of its form by the
 * form's ID. Pinned under the panel on a desktop; on a tablet or a phone, the bar at the foot of the screen, sticky so
 * it stops where the builder ends (D-160), with a small picture of the board that links to the picture, and the
 * delivery line and notes at the review alone (D-250). The small board shows the side the picture shows (#46).
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cbuild-bar" data-wp-interactive="creative-skateboard-builder" data-wp-init="callbacks.ready" data-wp-bind--hidden="!state.view.bar.shown" data-wp-class--cbuild-bar--review="state.view.isReview">
	<a class="cbuild-bar__picture" href="#cbuild-picture" data-wp-bind--hidden="!state.view.bar.seen" data-wp-class--cbuild-bar__picture--top="state.view.picture.turned" data-wp-class--cbuild-bar__picture--turning="state.turning">
		<span class="cbuild-board cbuild-board--bar" dir="ltr" data-wp-bind--hidden="!state.view.bar.board.shown" data-wp-bind--style="state.view.bar.board.style">
			<template data-wp-each--layer="state.view.bar.board.layers" data-wp-each-key="context.layer.key">
				<picture data-wp-bind--class="context.layer.classes" data-wp-bind--style="context.layer.style">
					<template data-wp-each--source="context.layer.sources" data-wp-each-key="context.source.type">
						<source data-wp-bind--type="context.source.type" data-wp-bind--srcset="context.source.srcset" data-wp-bind--sizes="context.source.sizes">
					</template>
					<img class="cbuild-board__image" alt="" decoding="async" loading="lazy" data-wp-bind--src="context.layer.src" data-wp-bind--width="context.layer.width" data-wp-bind--height="context.layer.height">
				</picture>
			</template>
		</span>
		<span class="cbuild-board cbuild-board--bar cbuild-board--top" dir="ltr" data-wp-bind--hidden="!state.view.picture.top.shown" data-wp-bind--style="state.view.picture.top.style">
			<template data-wp-each--layer="state.view.picture.top.layers" data-wp-each-key="context.layer.key">
				<span data-wp-bind--class="context.layer.classes" data-wp-bind--style="context.layer.style"></span>
			</template>
		</span>
		<img data-wp-bind--hidden="state.view.bar.board.shown" data-wp-bind--class="state.view.bar.photo.classes" alt="" width="48" height="48" sizes="48px" loading="lazy" decoding="async" data-wp-bind--src="state.view.bar.photo.src" data-wp-bind--srcset="state.view.bar.photo.srcset">
		<span class="screen-reader-text" data-wp-text="state.view.bar.picture"></span>
	</a>
	<details class="cbuild-bar__parts">
		<summary class="cbuild-bar__total"><span class="cbuild-bar__label" aria-hidden="true"><?php esc_html_e( 'Total', 'creative-skateboard-builder' ); ?></span><span class="screen-reader-text" data-wp-text="state.view.bar.total"></span><span class="cbuild-bar__amount" aria-hidden="true"><span data-wp-text="state.view.bar.amount"></span><span class="cbuild-bar__estimate" data-wp-bind--hidden="!state.view.bar.hasEstimate" data-wp-text="state.view.bar.estimate"></span></span> <span class="cbuild-bar__more cbuild-bar__more--show" data-wp-text="state.view.bar.parts"></span><span class="cbuild-bar__more cbuild-bar__more--hide" data-wp-text="state.view.bar.hide"></span></summary>
		<dl class="cbuild-bar__lines">
			<template data-wp-each--row="state.view.summary.rows" data-wp-each-key="context.row.key">
				<div class="cbuild-summary__line">
					<dt><span data-wp-text="context.row.label"></span> <span class="cbuild-summary__name" data-wp-text="context.row.name"></span> <span class="cbuild-summary__count" data-wp-text="context.row.count"></span></dt>
					<dd data-wp-text="context.row.amount"></dd>
				</div>
			</template>
		</dl>
	</details>
	<div class="cbuild-bar__more-lines">
		<p class="cbuild-bar__ships" data-wp-bind--hidden="!state.view.summary.hasShips" data-wp-text="state.view.summary.ships"></p>
		<p class="cbuild-bar__arrival" data-wp-bind--hidden="!state.view.summary.hasArrival" data-wp-text="state.view.summary.arrival"></p>
		<ul class="cbuild-bar__notes">
			<template data-wp-each--note="state.view.summary.notes">
				<li data-wp-text="context.note.text"></li>
			</template>
		</ul>
	</div>
	<div class="cbuild-bar__actions">
		<a class="cbuild-button cbuild-bar__back" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--hidden="!state.view.bar.back.shown" data-wp-bind--href="state.view.bar.back.url" data-wp-bind--aria-label="state.view.bar.back.label" data-wp-text="state.view.bar.back.text"></a>
		<button class="cbuild-button cbuild-button--primary cbuild-bar__next" type="submit" form="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\BuilderView::STEP_FORM ); ?>" data-wp-bind--hidden="!state.view.bar.next.onStep" data-wp-bind--name="state.view.bar.next.name" data-wp-bind--value="state.view.bar.next.value" data-wp-text="state.view.bar.next.text"></button>
		<button class="cbuild-button cbuild-button--primary cbuild-bar__next" type="submit" form="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\BuilderView::REVIEW_FORM ); ?>" data-wp-bind--hidden="!state.view.bar.next.onReview" data-wp-bind--disabled="state.view.bar.next.disabled" data-wp-text="state.view.bar.next.text"></button>
	</div>
</div>
