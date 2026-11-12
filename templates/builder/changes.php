<?php
/**
 * The change panel (spec: A change that breaks later picks): what is kept, what no longer fits and its closest fit,
 * with Use these and Undo. Without JavaScript it is a step of its own.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<section class="cbuild-changes" data-wp-bind--hidden="!state.view.isChanges">
	<h3 class="cbuild-changes__heading" tabindex="-1" data-wp-text="state.view.changes.heading"></h3>
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
	<div data-wp-bind--hidden="!state.view.changes.hasKept">
		<h4 data-wp-text="state.view.changes.keptHead"></h4>
		<ul class="cbuild-changes__kept">
			<template data-wp-each--kept="state.view.changes.kept">
				<li data-wp-text="context.kept.text"></li>
			</template>
		</ul>
	</div>
	<p class="cbuild-changes__actions">
		<a class="cbuild-button cbuild-button--primary" rel="nofollow" data-wp-bind--href="state.view.changes.use.url" data-wp-text="state.view.changes.use.text"></a>
		<a class="cbuild-button" rel="nofollow" data-wp-bind--href="state.view.changes.undo.url" data-wp-text="state.view.changes.undo.text"></a>
	</p>
</section>
