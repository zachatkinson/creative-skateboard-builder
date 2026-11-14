<?php
/**
 * The phone bar (spec: The total, W7; D-153, D-160): a small picture of the board, which links to the picture; the
 * total, which opens the parts list above the bar; and the step's own Next, or the review's Add to cart, each a
 * button of its form by the form's ID. The stylesheet shows it on a phone's width and the summary beside the steps
 * everywhere else, so nothing is shown or read twice.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cbuild-bar" data-wp-bind--hidden="!state.view.bar.shown">
	<a class="cbuild-bar__picture" href="#cbuild-picture" data-wp-bind--hidden="!state.view.bar.photo.hasImage">
		<img data-wp-bind--class="state.view.bar.photo.classes" alt="" width="48" height="48" sizes="48px" loading="lazy" decoding="async" data-wp-bind--src="state.view.bar.photo.src" data-wp-bind--srcset="state.view.bar.photo.srcset">
		<span class="screen-reader-text" data-wp-text="state.view.bar.picture"></span>
	</a>
	<details class="cbuild-bar__parts">
		<summary class="cbuild-bar__total"><span data-wp-text="state.view.bar.total"></span> <span class="cbuild-bar__more cbuild-bar__more--show" data-wp-text="state.view.bar.parts"></span><span class="cbuild-bar__more cbuild-bar__more--hide" data-wp-text="state.view.bar.hide"></span></summary>
		<dl class="cbuild-bar__lines">
			<template data-wp-each--row="state.view.summary.rows" data-wp-each-key="context.row.key">
				<div class="cbuild-summary__line">
					<dt><span data-wp-text="context.row.label"></span> <span class="cbuild-summary__name" data-wp-text="context.row.name"></span> <span class="cbuild-summary__count" data-wp-text="context.row.count"></span></dt>
					<dd data-wp-text="context.row.amount"></dd>
				</div>
			</template>
		</dl>
	</details>
	<button class="cbuild-button cbuild-button--primary cbuild-bar__next" type="submit" form="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\BuilderView::STEP_FORM ); ?>" data-wp-bind--hidden="!state.view.bar.next.onStep" data-wp-bind--name="state.view.bar.next.name" data-wp-bind--value="state.view.bar.next.value" data-wp-text="state.view.bar.next.text"></button>
	<button class="cbuild-button cbuild-button--primary cbuild-bar__next" type="submit" form="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\BuilderView::REVIEW_FORM ); ?>" data-wp-bind--hidden="!state.view.bar.next.onReview" data-wp-bind--disabled="state.view.bar.next.disabled" data-wp-text="state.view.bar.next.text"></button>
</div>
