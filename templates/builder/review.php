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
<section class="cbuild-review" data-wp-interactive="creative-skateboard-builder" data-wp-bind--hidden="!state.view.isReview">
	<h3 class="cbuild-review__heading screen-reader-text" data-wp-text="state.view.review.heading"></h3>
	<ul class="cbuild-review__items">
		<template data-wp-each--item="state.view.review.items" data-wp-each-key="context.item.key">
			<li data-wp-bind--class="context.item.classes">
				<span class="cbuild-review__slot" data-wp-text="context.item.label"></span>
				<span class="cbuild-review__name" data-wp-text="context.item.name"></span>
				<span class="cbuild-review__fit" data-wp-text="context.item.fit"></span>
				<span class="cbuild-review__amount" data-wp-text="context.item.amount"></span>
				<a class="cbuild-button cbuild-review__change" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--href="context.item.url" data-wp-bind--aria-label="context.item.change" data-wp-text="context.item.text"></a>
			</li>
		</template>
	</ul>
	<?php // For a rider who wants risers when nothing asks for them (spec: The review, D-246). ?>
	<a class="cbuild-button cbuild-review__add" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--hidden="!state.view.review.addRisers.shown" data-wp-bind--href="state.view.review.addRisers.url" data-wp-text="state.view.review.addRisers.text"></a>
	<div class="cbuild-review__assembly">
		<p data-wp-text="state.view.review.assembly"></p>
		<form class="cbuild-review__toggle" method="get" data-wp-on--submit="actions.submit" data-wp-bind--action="state.view.action">
			<template data-wp-each--field="state.view.carry">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<template data-wp-each--field="state.view.summary.toggle.fields">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<button class="cbuild-button" type="submit" data-wp-text="state.view.summary.toggle.text"></button>
		</form>
	</div>
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
	<?php // Save build (D-217): a form to My builds for a signed-in customer, with no script of its own; a link to sign in, which comes back here, for anyone else. ?>
	<form class="cbuild-save" method="post" data-wp-bind--action="state.account.action" data-wp-bind--hidden="!state.account.signedIn">
		<template data-wp-each--field="state.view.review.saveFields">
			<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
		</template>
		<input type="hidden" name="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks::FIELD ); ?>" value="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks::SAVE ); ?>">
		<input type="hidden" name="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks::NONCE ); ?>" data-wp-bind--value="state.account.nonce">
		<input type="hidden" name="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks::UUID ); ?>" data-wp-bind--value="state.account.saved">
		<label class="cbuild-save__label" for="cbuild-save-name" data-wp-text="state.view.review.saveName"></label>
		<input class="cbuild-save__name" id="cbuild-save-name" type="text" maxlength="100" name="<?php echo esc_attr( \CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks::NAME ); ?>">
		<button class="cbuild-button" type="submit" data-wp-bind--disabled="!state.view.review.ready" data-wp-text="state.view.review.save"></button>
	</form>
	<p class="cbuild-save__sign-in" data-wp-bind--hidden="state.account.signedIn">
		<a rel="nofollow" data-wp-bind--href="state.view.review.signIn" data-wp-text="state.view.review.signInText"></a>
	</p>
	<p class="cbuild-review__added" tabindex="-1" data-wp-bind--hidden="!state.added">
		<span data-wp-text="state.view.review.added"></span>
		<a href="<?php echo esc_url( wc_get_cart_url() ); ?>"><?php esc_html_e( 'View cart', 'creative-skateboard-builder' ); ?></a>
	</p>
</section>
