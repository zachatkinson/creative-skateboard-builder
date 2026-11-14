<?php
/**
 * The error summary (spec: Keyboard and screen readers): what keeps the build from where the shopper asked to go,
 * each linking to its step.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cbuild-errors" tabindex="-1" data-wp-bind--hidden="!state.view.errors.shown">
	<h3 class="cbuild-errors__heading" data-wp-text="state.view.errors.heading"></h3>
	<ul class="cbuild-errors__list">
		<template data-wp-each--error="state.view.errors.items">
			<li><a rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--href="context.error.url" data-wp-text="context.error.text"></a></li>
		</template>
	</ul>
</div>
