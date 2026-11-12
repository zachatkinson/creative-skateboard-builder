<?php
/**
 * A step (spec: A step; Without JavaScript): a GET form whose picks travel in the address, a fieldset of cards with
 * a native radio each, and Back and Next.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="cbuild-step" method="get" data-wp-on--submit="actions.submit" data-wp-on--change="actions.pick" data-wp-bind--action="state.view.action" data-wp-bind--hidden="!state.view.isStep">
	<template data-wp-each--field="state.view.carry">
		<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
	</template>
	<template data-wp-each--field="state.view.step.before">
		<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
	</template>
	<template data-wp-each--field="state.view.step.after">
		<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
	</template>
	<template data-wp-each--field="state.view.step.tail">
		<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
	</template>
	<details class="cbuild-filters" open data-wp-bind--hidden="!state.view.step.filters.shown">
		<summary class="cbuild-filters__heading" data-wp-text="state.view.step.filters.heading"></summary>
		<template data-wp-each--group="state.view.step.filters.groups" data-wp-each-key="context.group.key">
			<fieldset class="cbuild-filters__group">
				<legend data-wp-text="context.group.legend"></legend>
				<template data-wp-each--choice="context.group.options" data-wp-each-key="context.choice.value">
					<button class="cbuild-filters__option" type="submit" data-wp-bind--name="context.group.name" data-wp-bind--value="context.choice.value" data-wp-bind--aria-pressed="context.choice.pressed" data-wp-text="context.choice.text"></button>
				</template>
				<button class="cbuild-filters__clear" type="submit" value="" data-wp-bind--name="context.group.name" data-wp-bind--hidden="!context.group.clear.shown" data-wp-text="context.group.clear.text"></button>
			</fieldset>
		</template>
	</details>
	<fieldset class="cbuild-step__fieldset">
		<legend class="cbuild-step__legend" data-wp-text="state.view.step.legend"></legend>
		<p class="cbuild-step__lead" data-wp-bind--hidden="!state.view.step.hasLead" data-wp-text="state.view.step.lead"></p>
		<p class="cbuild-step__help" data-wp-bind--hidden="!state.view.step.hasHelp" data-wp-text="state.view.step.help"></p>
		<p class="cbuild-step__empty" data-wp-bind--hidden="!state.view.step.empty" data-wp-text="state.view.step.emptyText"></p>
		<ul class="cbuild-cards">
			<template data-wp-each--option="state.view.step.options" data-wp-each-key="context.option.id">
				<li data-wp-bind--class="context.option.classes">
					<input class="cbuild-card__control" type="radio" data-wp-bind--id="context.option.input" data-wp-bind--name="state.view.step.name" data-wp-bind--value="context.option.value" data-wp-bind--checked="context.option.checked" data-wp-bind--disabled="context.option.disabled" data-wp-bind--aria-label="context.option.choose">
					<label class="cbuild-card__body" data-wp-bind--for="context.option.input">
						<img class="cbuild-card__photo" alt="" loading="lazy" decoding="async" data-wp-bind--hidden="!context.option.hasImage" data-wp-bind--src="context.option.src" data-wp-bind--srcset="context.option.srcset" data-wp-bind--sizes="context.option.sizes" data-wp-bind--width="context.option.width" data-wp-bind--height="context.option.height">
						<span class="cbuild-card__name" data-wp-text="context.option.name"></span>
						<span class="cbuild-card__delta" data-wp-text="context.option.delta"></span>
						<span data-wp-bind--class="context.option.stockClass" data-wp-text="context.option.stock"></span>
						<span data-wp-bind--class="context.option.fitClass" data-wp-text="context.option.fit"></span>
						<span class="cbuild-card__recommended" data-wp-bind--hidden="!context.option.recommended" data-wp-text="context.option.recommendedAs"></span>
					</label>
					<ul class="cbuild-card__notes">
						<template data-wp-each--note="context.option.notes">
							<li data-wp-text="context.note.text"></li>
						</template>
					</ul>
					<a class="cbuild-card__page" data-wp-bind--hidden="!context.option.soldOut" data-wp-bind--href="context.option.url" data-wp-text="context.option.page"></a>
				</li>
			</template>
		</ul>
		<p class="cbuild-step__more">
			<span class="cbuild-step__count" data-wp-text="state.view.step.more.count"></span>
			<button class="cbuild-step__show" type="submit" data-wp-bind--hidden="!state.view.step.more.shown" data-wp-bind--name="state.view.step.more.name" data-wp-bind--value="state.view.step.more.value" data-wp-text="state.view.step.more.text"></button>
		</p>
		<p class="cbuild-step__leave" data-wp-bind--hidden="!state.view.step.leave.shown">
			<input type="radio" value="0" data-wp-bind--id="state.view.step.leave.id" data-wp-bind--name="state.view.step.name" data-wp-bind--checked="state.view.step.leave.checked">
			<label data-wp-bind--for="state.view.step.leave.id"><span data-wp-text="state.view.step.leave.label"></span> <span class="cbuild-card__delta" data-wp-text="state.view.step.leave.delta"></span></label>
		</p>
		<details class="cbuild-hidden" data-wp-bind--hidden="!state.view.step.hidden.shown">
			<summary class="cbuild-hidden__summary" data-wp-text="state.view.step.hidden.summary"></summary>
			<ul class="cbuild-hidden__groups">
				<template data-wp-each--group="state.view.step.hidden.groups">
					<li data-wp-text="context.group.text"></li>
				</template>
			</ul>
			<ul class="cbuild-hidden__parts">
				<template data-wp-each--part="state.view.step.hidden.items" data-wp-each-key="context.part.id">
					<li class="cbuild-hidden__part">
						<a class="cbuild-hidden__name" data-wp-bind--href="context.part.url" data-wp-text="context.part.name"></a>
						<ul class="cbuild-hidden__reasons">
							<template data-wp-each--reason="context.part.reasons">
								<li data-wp-text="context.reason.text"></li>
							</template>
						</ul>
					</li>
				</template>
			</ul>
		</details>
	</fieldset>
	<div class="cbuild-step__nav">
		<a class="cbuild-step__back" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--hidden="!state.view.step.back.shown" data-wp-bind--href="state.view.step.back.url" data-wp-text="state.view.step.back.text"></a>
		<button class="cbuild-step__next" type="submit" data-wp-bind--name="state.view.step.stepName" data-wp-bind--value="state.view.step.next.value" data-wp-text="state.view.step.next.text"></button>
	</div>
</form>
