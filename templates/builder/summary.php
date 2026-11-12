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
	<p class="cbuild-summary__assembly"><span data-wp-text="state.view.summary.assembly"></span> <a rel="nofollow" data-wp-bind--href="state.view.summary.toggle.url" data-wp-text="state.view.summary.toggle.text"></a></p>
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
