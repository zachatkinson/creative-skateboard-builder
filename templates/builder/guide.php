<?php
/**
 * The guided start (#134): "Help me choose" at the builder's start, a disclosure holding optional questions as one GET
 * form, and once it is sent the builds they recommend, with why, each a ready build's tile. Skip goes to the builder's
 * start from the questions or the builds; leaving an answer blank is an answer. Every word is bound to the view
 * (`BuilderView::guide()`), and the answers travel in the build's link alone.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="cbuild-guide" data-wp-interactive="creative-skateboard-builder" aria-labelledby="cbuild-guide-summary" data-wp-bind--hidden="!state.view.guide.shown">
	<details class="cbuild-guide__ask">
		<summary class="cbuild-guide__summary" id="cbuild-guide-summary" data-wp-text="state.view.guide.summary"></summary>
		<form class="cbuild-guide__form" method="get" data-wp-on--submit="actions.submit" data-wp-bind--action="state.view.action">
			<template data-wp-each--field="state.view.carry">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<p class="cbuild-guide__intro" data-wp-text="state.view.guide.intro"></p>
			<fieldset class="cbuild-guide__question" data-wp-bind--hidden="!state.view.guide.shoe.shown">
				<legend data-wp-text="state.view.guide.shoe.legend"></legend>
				<template data-wp-each--choice="state.view.guide.shoe.choices" data-wp-each-key="context.choice.value">
					<label class="cbuild-guide__choice">
						<input type="radio" data-wp-bind--name="state.view.guide.shoe.name" data-wp-bind--value="context.choice.value" data-wp-bind--checked="context.choice.checked">
						<span data-wp-text="context.choice.label"></span>
					</label>
				</template>
			</fieldset>
			<fieldset class="cbuild-guide__question">
				<legend data-wp-text="state.view.guide.style.legend"></legend>
				<template data-wp-each--choice="state.view.guide.style.choices" data-wp-each-key="context.choice.value">
					<label class="cbuild-guide__choice">
						<input type="radio" data-wp-bind--name="state.view.guide.style.name" data-wp-bind--value="context.choice.value" data-wp-bind--checked="context.choice.checked">
						<span data-wp-text="context.choice.label"></span>
					</label>
				</template>
			</fieldset>
			<p class="cbuild-guide__question">
				<label for="cbuild-guide-budget" data-wp-text="state.view.guide.budget.label"></label>
				<span class="cbuild-guide__hint" id="cbuild-guide-budget-hint" data-wp-text="state.view.guide.budget.hint"></span>
				<input class="cbuild-guide__budget" id="cbuild-guide-budget" type="text" inputmode="numeric" spellcheck="false" maxlength="6" autocomplete="off" aria-describedby="cbuild-guide-budget-hint" data-wp-bind--name="state.view.guide.budget.name" data-wp-bind--value="state.view.guide.budget.value">
			</p>
			<input type="hidden" data-wp-bind--name="state.view.guide.asks.name" data-wp-bind--value="state.view.guide.asks.value">
			<p class="cbuild-guide__actions">
				<button class="cbuild-button" type="submit" data-wp-text="state.view.guide.submit"></button>
				<a class="cbuild-guide__skip" data-wp-bind--href="state.view.guide.skip.url" data-wp-on--click="actions.follow" data-wp-text="state.view.guide.skip.text"></a>
			</p>
		</form>
	</details>
	<div class="cbuild-guide__builds" data-wp-bind--hidden="!state.view.guide.asked">
		<h3 class="cbuild-guide__heading" data-wp-text="state.view.guide.heading"></h3>
		<ul class="cbuild-guide__reasons">
			<template data-wp-each--reason="state.view.guide.reasons">
				<li data-wp-text="context.reason.text"></li>
			</template>
		</ul>
		<p class="cbuild-guide__none" data-wp-bind--hidden="!state.view.guide.hasNone" data-wp-text="state.view.guide.none"></p>
		<ul class="cbuild-guide__tiles">
			<template data-wp-each--tile="state.view.guide.tiles" data-wp-each-key="context.tile.button">
				<?php require __DIR__ . '/tile.php'; ?>
			</template>
		</ul>
		<p class="cbuild-guide__actions">
			<a class="cbuild-guide__width" data-wp-bind--hidden="!state.view.guide.width.shown" data-wp-bind--href="state.view.guide.width.url" data-wp-on--click="actions.follow" data-wp-text="state.view.guide.width.text"></a>
			<a class="cbuild-guide__skip" data-wp-bind--href="state.view.guide.skip.url" data-wp-on--click="actions.follow" data-wp-text="state.view.guide.skip.text"></a>
		</p>
	</div>
</section>
