<?php
/**
 * The review (spec: Review and add to cart): the build by slot with a fit tick and a Change link each, the lead time
 * and the returns notice, and Add to cart, posted to WooCommerce's own handler.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="cbuild-review" data-wp-bind--hidden="!state.view.isReview">
	<h3 class="cbuild-review__heading" data-wp-text="state.view.review.heading"></h3>
	<ul class="cbuild-review__items">
		<template data-wp-each--item="state.view.review.items" data-wp-each-key="context.item.key">
			<li class="cbuild-review__item" data-wp-class--cbuild-review__item--wrong="!context.item.fits">
				<span class="cbuild-review__slot" data-wp-text="context.item.label"></span>
				<span class="cbuild-review__name" data-wp-text="context.item.name"></span>
				<span class="cbuild-review__fit" data-wp-text="context.item.fit"></span>
				<a class="cbuild-review__change" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--href="context.item.url" data-wp-text="context.item.change"></a>
			</li>
		</template>
	</ul>
	<p class="cbuild-review__lead" data-wp-bind--hidden="!state.view.review.hasLead" data-wp-text="state.view.review.lead"></p>
	<p class="cbuild-review__notice" data-wp-bind--hidden="!state.view.review.hasNotice" data-wp-text="state.view.review.notice"></p>
	<form class="cbuild-review__form" method="post" data-wp-bind--action="state.view.review.action">
		<template data-wp-each--field="state.view.review.fields">
			<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
		</template>
		<button class="cbuild-button cbuild-button--primary" type="submit" data-wp-bind--disabled="!state.view.review.ready" data-wp-text="state.view.review.add"></button>
	</form>
</section>
