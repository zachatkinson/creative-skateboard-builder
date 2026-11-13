<?php
/**
 * The review (spec: Review and add to cart): the build by slot with a fit tick and a Change link each; assembly with
 * its fee and lead time, the arrival date, the total and the returns notice, all before Add to cart, which posts to
 * WooCommerce's own handler. With JavaScript, the store adds it through the Store API and says so here (D-155).
 * The build's link sits under it to copy, as a field without JavaScript and with a Copy link button with it (D-156).
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="cbuild-review" data-wp-bind--hidden="!state.view.isReview">
	<h3 class="cbuild-review__heading" data-wp-text="state.view.review.heading"></h3>
	<ul class="cbuild-review__items">
		<template data-wp-each--item="state.view.review.items" data-wp-each-key="context.item.key">
			<li data-wp-bind--class="context.item.classes">
				<span class="cbuild-review__slot" data-wp-text="context.item.label"></span>
				<span class="cbuild-review__name" data-wp-text="context.item.name"></span>
				<span class="cbuild-review__fit" data-wp-text="context.item.fit"></span>
				<a class="cbuild-review__change" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--href="context.item.url" data-wp-text="context.item.change"></a>
			</li>
		</template>
	</ul>
	<p class="cbuild-review__assembly" data-wp-text="state.view.review.assembly"></p>
	<p class="cbuild-review__lead" data-wp-bind--hidden="!state.view.review.hasLead" data-wp-text="state.view.review.lead"></p>
	<p class="cbuild-review__arrival" data-wp-bind--hidden="!state.view.review.hasArrival" data-wp-text="state.view.review.arrival"></p>
	<p class="cbuild-review__total" data-wp-text="state.view.review.total"></p>
	<p class="cbuild-review__notice" data-wp-bind--hidden="!state.view.review.hasNotice" data-wp-text="state.view.review.notice"></p>
	<p class="cbuild-review__replaces" data-wp-bind--hidden="!state.view.review.replacing" data-wp-text="state.view.review.replaces"></p>
	<form class="cbuild-review__form" id="cbuild-review-form" method="post" data-wp-on--submit="actions.add" data-wp-bind--action="state.view.review.action">
		<template data-wp-each--field="state.view.review.fields">
			<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
		</template>
		<button class="cbuild-button cbuild-button--primary" type="submit" data-wp-bind--disabled="!state.view.review.ready" data-wp-text="state.view.review.add"></button>
	</form>
	<div class="cbuild-share">
		<label class="cbuild-share__label" for="cbuild-share-link" data-wp-text="state.view.review.share"></label>
		<input class="cbuild-share__link" id="cbuild-share-link" type="url" readonly data-wp-bind--value="state.view.review.link">
		<button class="cbuild-button cbuild-share__copy" type="button" data-wp-bind--hidden="!state.canCopy" data-wp-on--click="actions.copy" data-wp-text="state.view.review.copy"></button>
	</div>
	<p class="cbuild-review__added" tabindex="-1" data-wp-bind--hidden="!state.added">
		<span data-wp-text="state.view.review.added"></span>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'View cart', 'creative-skateboard-builder' ); ?></a>
	</p>
</section>
