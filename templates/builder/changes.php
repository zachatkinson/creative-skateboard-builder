<?php
/**
 * The change panel (spec: A change that breaks later picks): what is kept, what no longer fits and its closest fit,
 * with Use these and Undo, each a button in a GET form. Without JavaScript it is a step of its own. A saved build that
 * changed since it was saved reopens on it too, with its new prices and totals (D-254).
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="cbuild-changes" data-wp-bind--hidden="!state.view.isChanges">
	<h3 class="cbuild-changes__heading" tabindex="-1" data-wp-bind--autofocus="state.view.isChanges" data-wp-text="state.view.changes.heading"></h3>
	<div data-wp-bind--hidden="!state.view.changes.hasItems">
		<h4 data-wp-text="state.view.changes.goneHead"></h4>
		<ul class="cbuild-changes__gone">
			<template data-wp-each--change="state.view.changes.items" data-wp-each-key="context.change.key">
				<li>
					<p data-wp-text="context.change.text"></p>
					<ul class="cbuild-changes__reasons">
						<template data-wp-each--reason="context.change.reasons">
							<li data-wp-text="context.reason.text"></li>
						</template>
					</ul>
					<p class="cbuild-changes__closest" data-wp-text="context.change.closest"></p>
				</li>
			</template>
		</ul>
	</div>
	<div data-wp-bind--hidden="!state.view.changes.hasPrices">
		<h4 data-wp-text="state.view.changes.pricesHead"></h4>
		<ul class="cbuild-changes__prices">
			<template data-wp-each--price="state.view.changes.prices">
				<li data-wp-text="context.price.text"></li>
			</template>
		</ul>
	</div>
	<p class="cbuild-changes__totals" data-wp-bind--hidden="!state.view.changes.hasTotals" data-wp-text="state.view.changes.totals"></p>
	<div data-wp-bind--hidden="!state.view.changes.hasKept">
		<h4 data-wp-text="state.view.changes.keptHead"></h4>
		<ul class="cbuild-changes__kept">
			<template data-wp-each--kept="state.view.changes.kept">
				<li data-wp-text="context.kept.text"></li>
			</template>
		</ul>
	</div>
	<div class="cbuild-changes__actions">
		<form class="cbuild-changes__use" method="get" data-wp-on--submit="actions.submit" data-wp-bind--action="state.view.action">
			<template data-wp-each--field="state.view.carry">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<template data-wp-each--field="state.view.changes.use.fields">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<button class="cbuild-button cbuild-button--primary" type="submit" data-wp-text="state.view.changes.use.text"></button>
		</form>
		<form class="cbuild-changes__undo" method="get" data-wp-on--submit="actions.submit" data-wp-bind--action="state.view.action">
			<template data-wp-each--field="state.view.carry">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<template data-wp-each--field="state.view.changes.undo.fields">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<button class="cbuild-button" type="submit" data-wp-text="state.view.changes.undo.text"></button>
		</form>
	</div>
</section>
