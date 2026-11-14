<?php
/**
 * The summary (spec: The total): the parts so far, assembly from the first step, any build discount, the total, the
 * delivery line and what other plugins add under it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<aside class="cbuild-summary" data-wp-bind--aria-label="state.view.summary.heading">
	<h3 class="cbuild-summary__heading" data-wp-text="state.view.summary.heading"></h3>
	<p class="cbuild-summary__empty" data-wp-bind--hidden="!state.view.summary.empty" data-wp-text="state.view.summary.emptyText"></p>
	<dl class="cbuild-summary__lines">
		<template data-wp-each--row="state.view.summary.rows" data-wp-each-key="context.row.key">
			<div class="cbuild-summary__line">
				<dt><span data-wp-text="context.row.label"></span> <span class="cbuild-summary__name" data-wp-text="context.row.name"></span> <span class="cbuild-summary__count" data-wp-text="context.row.count"></span></dt>
				<dd data-wp-text="context.row.amount"></dd>
			</div>
		</template>
	</dl>
	<div class="cbuild-summary__assembly">
		<span data-wp-text="state.view.summary.assembly"></span>
		<form class="cbuild-summary__toggle" method="get" data-wp-on--submit="actions.submit" data-wp-bind--action="state.view.action">
			<template data-wp-each--field="state.view.carry">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<template data-wp-each--field="state.view.summary.toggle.fields">
				<input type="hidden" data-wp-bind--name="context.field.name" data-wp-bind--value="context.field.value">
			</template>
			<button type="submit" data-wp-text="state.view.summary.toggle.text"></button>
		</form>
	</div>
	<p class="cbuild-summary__saved" data-wp-bind--hidden="!state.view.summary.hasSaved" data-wp-text="state.view.summary.saved"></p>
	<p class="cbuild-summary__total"><span data-wp-text="state.view.summary.totalLabel"></span> <strong data-wp-text="state.view.summary.total"></strong></p>
	<p class="cbuild-summary__ships" data-wp-bind--hidden="!state.view.summary.hasShips" data-wp-text="state.view.summary.ships"></p>
	<p class="cbuild-summary__arrival" data-wp-bind--hidden="!state.view.summary.hasArrival" data-wp-text="state.view.summary.arrival"></p>
	<ul class="cbuild-summary__notes">
		<template data-wp-each--note="state.view.summary.notes">
			<li data-wp-text="context.note.text"></li>
		</template>
	</ul>
</aside>
