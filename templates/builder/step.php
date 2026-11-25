<?php
/**
 * A step (spec: A step; Without JavaScript): a GET form whose picks travel in the address, a fieldset of cards with
 * a native radio each, and Back and Next.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<form class="cbuild-step" data-wp-interactive="creative-skateboard-builder" id="cbuild-step-form" method="get" data-wp-on--submit="actions.submit" data-wp-on--change="actions.pick" data-wp-bind--action="state.view.action" data-wp-bind--hidden="!state.view.isStep">
	<?php
	// The form's default button, which Enter in the search presses: it sends the form as it is, so a search keeps the
	// width, where the first width button would send its own (the auditor's M10 review, 2). Out of sight and the tab
	// order, and named for no one.
	?>
	<button class="cbuild-step__default" type="submit" tabindex="-1" aria-hidden="true"></button>
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
	<fieldset class="cbuild-width" data-wp-bind--hidden="!state.view.step.shelf.width.shown" data-wp-class--cbuild-width--asking="state.view.step.shelf.width.asking">
		<legend class="cbuild-width__legend" data-wp-text="state.view.step.shelf.width.legend"></legend>
		<?php // Open while the step asks; once a width is chosen, its summary is that width with Change (spec: The deck's width first). ?>
		<details class="cbuild-width__pick" data-wp-bind--open="state.view.step.shelf.width.asking">
			<summary class="cbuild-width__chosen" data-wp-bind--hidden="state.view.step.shelf.width.asking"><span class="cbuild-width__text" data-wp-text="state.view.step.shelf.width.chosen"></span> <span class="cbuild-width__change" data-wp-text="state.view.step.shelf.width.change"></span></summary>
			<div class="cbuild-width__options">
				<template data-wp-each--width="state.view.step.shelf.width.options" data-wp-each-key="context.width.value">
					<button class="cbuild-chip cbuild-width__option" type="submit" data-wp-bind--name="state.view.step.shelf.width.name" data-wp-bind--value="context.width.value" data-wp-bind--aria-label="context.width.said" data-wp-bind--aria-pressed="context.width.pressed" data-wp-class--cbuild-chip--dimmed="context.width.dimmed"><span class="cbuild-chip__text" data-wp-text="context.width.text"></span> <span class="cbuild-chip__count" data-wp-text="context.width.count"></span></button>
				</template>
			</div>
			<p class="cbuild-width__none" data-wp-bind--hidden="!state.view.step.shelf.width.hasNone" data-wp-text="state.view.step.shelf.width.none"></p>
			<?php // "Not sure of your width?": a disclosure that opens without JavaScript, by shoe size (spec: The deck's width first). ?>
			<details class="cbuild-width__guide" data-wp-bind--hidden="!state.view.step.shelf.width.guide.shown">
				<summary data-wp-text="state.view.step.shelf.width.guide.summary"></summary>
				<table class="cbuild-width__table">
					<caption class="screen-reader-text" data-wp-text="state.view.step.shelf.width.guide.caption"></caption>
					<thead>
						<tr>
							<th scope="col" data-wp-text="state.view.step.shelf.width.guide.shoe"></th>
							<th scope="col" data-wp-text="state.view.step.shelf.width.guide.width"></th>
						</tr>
					</thead>
					<tbody>
						<template data-wp-each--row="state.view.step.shelf.width.guide.rows" data-wp-each-key="context.row.shoe">
							<tr>
								<th scope="row" data-wp-text="context.row.shoe"></th>
								<td data-wp-text="context.row.width"></td>
							</tr>
						</template>
					</tbody>
				</table>
			</details>
		</details>
	</fieldset>
	<div class="cbuild-search" role="search" data-wp-bind--hidden="!state.view.step.shelf.search.shown">
		<label class="cbuild-search__label" for="cbuild-search" data-wp-text="state.view.step.shelf.search.label"></label>
		<span class="cbuild-search__row">
			<input class="cbuild-search__input" id="cbuild-search" type="search" maxlength="100" autocomplete="off" data-wp-bind--name="state.view.step.shelf.search.name" data-wp-bind--value="state.view.step.shelf.search.value" data-wp-bind--placeholder="state.view.step.shelf.search.hint" data-wp-bind--disabled="!state.view.step.shelf.search.shown" data-wp-on--input="actions.search">
			<button class="cbuild-button cbuild-search__go" type="submit" data-wp-bind--hidden="state.live" data-wp-text="state.view.step.shelf.search.button"></button>
		</span>
	</div>
	<?php // The chips and Sort: what narrows the list and what orders it, on one line on a desktop (#122). ?>
	<div class="cbuild-step__tools">
		<div class="cbuild-chips" data-wp-bind--hidden="!state.view.step.shelf.chips.shown" data-wp-class--cbuild-chips--folded="state.folded">
			<?php // On a phone the cards come first: the chips wait behind this button once the script runs (spec: Phone). ?>
			<button class="cbuild-button cbuild-chips__toggle" type="button" aria-controls="cbuild-chips-body" hidden data-wp-bind--hidden="!state.live" data-wp-bind--aria-expanded="!state.folded" data-wp-on--click="actions.filters" data-wp-text="state.view.step.shelf.chips.toggle"></button>
			<div class="cbuild-chips__body" id="cbuild-chips-body">
				<template data-wp-each--group="state.view.step.shelf.chips.groups" data-wp-each-key="context.group.key">
					<fieldset class="cbuild-chips__group">
						<legend class="cbuild-chips__legend" data-wp-text="context.group.legend"></legend>
						<?php // On a desktop the brand chips sit in one row, and "N more" opens the rest in place; without the script every chip shows (#98). ?>
						<div class="cbuild-chips__options" data-wp-bind--id="context.group.more.id" data-wp-class--cbuild-chips__options--row="context.group.more.shown" data-wp-watch="callbacks.row">
							<template data-wp-each--chip="context.group.inputs" data-wp-each-key="context.chip.value">
								<span class="cbuild-chips__chip" data-wp-class--cbuild-chips__chip--past="state.past">
									<input class="cbuild-chips__control" type="checkbox" data-wp-bind--id="context.chip.id" data-wp-bind--name="context.chip.name" data-wp-bind--value="context.chip.value" data-wp-bind--checked="context.chip.checked" data-wp-bind--aria-label="context.chip.said" data-wp-on--change="actions.narrow">
									<label class="cbuild-chip" data-wp-bind--for="context.chip.id"><span class="cbuild-chip__text" data-wp-text="context.chip.text"></span> <span class="cbuild-chip__count" data-wp-text="context.chip.count"></span></label>
								</span>
							</template>
							<button class="cbuild-chips__more" type="button" hidden data-wp-bind--hidden="!state.rests" data-wp-class--cbuild-chips__more--idle="state.idle" data-wp-bind--aria-controls="context.group.more.id" data-wp-bind--aria-expanded="state.open" data-wp-on--click="actions.more" data-wp-text="state.rest"></button>
						</div>
					</fieldset>
				</template>
				<button class="cbuild-button cbuild-chips__apply" type="submit" data-wp-bind--hidden="state.live" data-wp-text="state.view.step.shelf.chips.apply"></button>
			</div>
		</div>
		<?php
		// Sort, on the chips' line on a desktop, the brands' on the deck step (#122). The count is said once, at the foot
		// beside Show more (spec: 30 at a time).
		?>
		<div class="cbuild-step__bar" data-wp-bind--hidden="!state.view.step.shelf.sort.shown">
			<label class="cbuild-sort">
				<span class="cbuild-sort__label" data-wp-text="state.view.step.shelf.sort.label"></span>
				<select class="cbuild-sort__select" data-wp-bind--name="state.view.step.shelf.sort.name" data-wp-bind--disabled="!state.view.step.shelf.sort.shown" data-wp-on--change="actions.narrow">
					<template data-wp-each--sort="state.view.step.shelf.sort.options" data-wp-each-key="context.sort.value">
						<option data-wp-bind--value="context.sort.value" data-wp-bind--selected="context.sort.selected" data-wp-text="context.sort.text"></option>
					</template>
				</select>
			</label>
			<button class="cbuild-button cbuild-sort__apply" type="submit" data-wp-bind--hidden="state.live" data-wp-text="state.view.step.shelf.chips.apply"></button>
		</div>
	</div>
	<details class="cbuild-filters" data-wp-bind--open="state.view.step.filters.open" data-wp-bind--hidden="!state.view.step.filters.shown">
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
	<?php
	// The step's heading names its group: the count and the question, one name for the eye and the ear (D-269). While
	// the deck step asks the width, there is nothing in it to show (spec: The deck's width first).
	?>
	<fieldset class="cbuild-step__fieldset" aria-labelledby="cbuild-heading" data-wp-bind--hidden="state.view.step.shelf.width.asking">
		<p class="cbuild-step__help" data-wp-bind--hidden="!state.view.step.hasHelp" data-wp-text="state.view.step.help"></p>
		<p class="cbuild-step__empty" data-wp-bind--hidden="!state.view.step.empty" data-wp-text="state.view.step.emptyText"></p>
		<?php // A search that lists nothing says why and offers the way on (spec: Search, when nothing is found). ?>
		<div class="cbuild-found" data-wp-bind--hidden="!state.view.step.found.shown">
			<p class="cbuild-found__text" data-wp-text="state.view.step.found.text"></p>
			<p class="cbuild-found__ways">
				<template data-wp-each--width="state.view.step.found.widths" data-wp-each-key="context.width.value">
					<button class="cbuild-button cbuild-found__width" type="submit" name="cbuild[_width]" data-wp-bind--value="context.width.value" data-wp-text="context.width.label"></button>
				</template>
				<a class="cbuild-found__why" href="#cbuild-hidden" data-wp-bind--hidden="!state.view.step.found.why.shown" data-wp-text="state.view.step.found.why.text"></a>
				<button class="cbuild-button cbuild-found__clear" type="submit" value="" data-wp-bind--name="state.view.step.found.clear.name" data-wp-bind--hidden="!state.view.step.found.clear.shown" data-wp-text="state.view.step.found.clear.text"></button>
			</p>
		</div>
		<?php // The deck step's cards are compact: the deck small and upright beside its words, two columns on a desktop (F14, #99). ?>
		<ul class="cbuild-cards" data-wp-class--cbuild-cards--compact="state.view.step.compact">
			<template data-wp-each--option="state.view.step.options" data-wp-each-key="context.option.id">
				<?php // A pointer resting on a deck's card fetches its board's image; a press asks for the pick at once (D-308). ?>
				<li data-wp-bind--class="context.option.classes" data-wp-on--pointerenter="actions.point" data-wp-on--pointerdown="actions.point" data-wp-on--pointerleave="actions.leave">
					<input class="cbuild-card__control" type="radio" data-wp-bind--id="context.option.input" data-wp-bind--name="context.option.field" data-wp-bind--value="context.option.value" data-wp-bind--checked="context.option.checked" data-wp-bind--disabled="context.option.disabled" data-wp-bind--aria-label="context.option.choose" data-wp-bind--aria-describedby="context.option.describedby">
					<label class="cbuild-card__body" data-wp-bind--for="context.option.input">
						<img class="cbuild-card__photo" alt="" loading="lazy" decoding="async" data-wp-class--cbuild-card__photo--drawn="context.option.drawn" data-wp-bind--hidden="!context.option.hasImage" data-wp-bind--src="context.option.src" data-wp-bind--srcset="context.option.srcset" data-wp-bind--sizes="context.option.sizes" data-wp-bind--width="context.option.width" data-wp-bind--height="context.option.height">
						<span class="cbuild-card__brand" data-wp-bind--id="context.option.brandId" data-wp-bind--hidden="!context.option.hasBrand" data-wp-text="context.option.brand"></span>
						<span class="cbuild-card__name" data-wp-text="context.option.name"></span>
						<span class="cbuild-card__look" data-wp-bind--hidden="!context.option.hasLook" data-wp-text="context.option.look"></span>
						<span class="cbuild-card__about" data-wp-bind--id="context.option.about">
							<?php // The full price large, and the change under it once the step has a pick (D-249). ?>
							<?php // Its rating beside the price where there is room, under it where there isn't (#137, D-392, D-393): the stars and the short score are seen, the sentence is said. ?>
							<span class="cbuild-card__priced">
								<span class="cbuild-card__price" data-wp-text="context.option.price"></span>
								<span class="cbuild-card__rating" data-wp-bind--hidden="!context.option.hasRating"><span class="cbuild-card__star" aria-hidden="true"></span><span class="cbuild-card__score" aria-hidden="true" data-wp-text="context.option.score"></span><span class="cbuild-card__rated screen-reader-text" data-wp-text="context.option.rated"></span></span>
							</span>
							<span class="cbuild-card__delta" data-wp-bind--hidden="!context.option.hasDelta" data-wp-text="context.option.delta"></span>
							<span class="cbuild-card__also" data-wp-bind--hidden="!context.option.hasAlso" data-wp-text="context.option.also"></span>
							<span data-wp-bind--class="context.option.stockClass" data-wp-text="context.option.stock"></span>
							<span data-wp-bind--class="context.option.fitClass" data-wp-text="context.option.fit"></span>
							<span class="cbuild-card__recommended" data-wp-bind--hidden="!context.option.recommended" data-wp-text="context.option.recommendedAs"></span>
							<?php // The ready builds that pick it (#137, D-392). ?>
							<span class="cbuild-card__picked" data-wp-bind--hidden="!context.option.hasPickedFor" data-wp-text="context.option.pickedFor"></span>
						</span>
					</label>
					<ul class="cbuild-card__notes" data-wp-bind--id="context.option.notesId">
						<template data-wp-each--note="context.option.notes">
							<li data-wp-text="context.note.text"></li>
						</template>
					</ul>
					<a class="cbuild-card__page" data-wp-bind--hidden="!context.option.soldOut" data-wp-bind--href="context.option.url" data-wp-text="context.option.page"></a>
					<?php // The picked card's sizes, where the shopper chooses one: picks of the step, as the cards are (D-279). ?>
					<fieldset class="cbuild-sizes cbuild-chips__group" data-wp-bind--hidden="!context.option.hasSizes">
						<legend class="cbuild-sizes__legend cbuild-chips__legend" data-wp-text="context.option.sizeGroup.legend"></legend>
						<div class="cbuild-sizes__options cbuild-chips__options">
							<template data-wp-each--size="context.option.sizeGroup.inputs" data-wp-each-key="context.size.value">
								<span class="cbuild-sizes__size cbuild-chips__chip">
									<input class="cbuild-sizes__control cbuild-chips__control" type="radio" data-wp-bind--id="context.size.id" data-wp-bind--name="context.size.name" data-wp-bind--value="context.size.value" data-wp-bind--checked="context.size.checked" data-wp-bind--disabled="context.size.disabled">
									<label class="cbuild-chip" data-wp-bind--for="context.size.id" data-wp-text="context.size.label"></label>
								</span>
							</template>
						</div>
					</fieldset>
					<?php // The pick's smart swaps, on its card alone (#135, D-397): links, so they work without the script. ?>
					<p class="cbuild-card__swaps" data-wp-bind--hidden="!context.option.hasSwaps">
						<template data-wp-each--swap="context.option.swaps" data-wp-each-key="context.swap.id">
							<a class="cbuild-swap" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--id="context.swap.id" data-wp-bind--href="context.swap.url" data-wp-text="context.swap.text"></a>
						</template>
					</p>
				</li>
			</template>
		</ul>
		<?php // The count, and Show more, where anything is listed: a step that lists nothing says why itself (#124). ?>
		<p class="cbuild-step__more" data-wp-bind--hidden="!state.view.step.more.count">
			<span class="cbuild-step__count" data-wp-text="state.view.step.more.count"></span>
			<button class="cbuild-step__show" type="submit" data-wp-bind--hidden="!state.view.step.more.shown" data-wp-bind--name="state.view.step.more.name" data-wp-bind--value="state.view.step.more.value" data-wp-text="state.view.step.more.text"></button>
		</p>
		<details class="cbuild-sold-out" data-wp-bind--hidden="!state.view.step.shelf.soldOut.shown">
			<summary class="cbuild-sold-out__summary" data-wp-text="state.view.step.shelf.soldOut.summary"></summary>
			<ul class="cbuild-cards cbuild-cards--sold-out" data-wp-class--cbuild-cards--compact="state.view.step.compact">
				<template data-wp-each--option="state.view.step.shelf.soldOut.options" data-wp-each-key="context.option.id">
					<li data-wp-bind--class="context.option.classes">
						<input class="cbuild-card__control" type="radio" data-wp-bind--id="context.option.input" data-wp-bind--name="state.view.step.name" data-wp-bind--value="context.option.value" data-wp-bind--checked="context.option.checked" data-wp-bind--disabled="context.option.disabled" data-wp-bind--aria-label="context.option.choose" data-wp-bind--aria-describedby="context.option.describedby">
						<label class="cbuild-card__body" data-wp-bind--for="context.option.input">
							<img class="cbuild-card__photo" alt="" loading="lazy" decoding="async" data-wp-class--cbuild-card__photo--drawn="context.option.drawn" data-wp-bind--hidden="!context.option.hasImage" data-wp-bind--src="context.option.src" data-wp-bind--srcset="context.option.srcset" data-wp-bind--sizes="context.option.sizes" data-wp-bind--width="context.option.width" data-wp-bind--height="context.option.height">
							<span class="cbuild-card__brand" data-wp-bind--id="context.option.brandId" data-wp-bind--hidden="!context.option.hasBrand" data-wp-text="context.option.brand"></span>
							<span class="cbuild-card__name" data-wp-text="context.option.name"></span>
							<span class="cbuild-card__look" data-wp-bind--hidden="!context.option.hasLook" data-wp-text="context.option.look"></span>
							<span class="cbuild-card__about" data-wp-bind--id="context.option.about">
								<span class="cbuild-card__price" data-wp-text="context.option.price"></span>
								<span data-wp-bind--class="context.option.stockClass" data-wp-text="context.option.stock"></span>
							</span>
						</label>
						<a class="cbuild-card__page" data-wp-bind--href="context.option.url" data-wp-text="context.option.page"></a>
					</li>
				</template>
			</ul>
		</details>
		<p class="cbuild-step__leave" data-wp-bind--hidden="!state.view.step.leave.shown">
			<input type="radio" value="0" data-wp-bind--id="state.view.step.leave.id" data-wp-bind--name="state.view.step.name" data-wp-bind--checked="state.view.step.leave.checked" data-wp-bind--disabled="!state.view.step.leave.shown">
			<label data-wp-bind--for="state.view.step.leave.id"><span data-wp-text="state.view.step.leave.label"></span> <span class="cbuild-card__delta" data-wp-text="state.view.step.leave.delta"></span></label>
		</p>
		<details class="cbuild-hidden" id="cbuild-hidden" data-wp-bind--hidden="!state.view.step.hidden.shown" data-wp-bind--open="state.view.step.found.why.shown">
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
