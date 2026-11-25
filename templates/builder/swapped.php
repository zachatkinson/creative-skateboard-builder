<?php
/**
 * After a smart swap (#135, D-397): what it swapped, and Undo, a link back to the picks before it, until the next move.
 * Above the step's cards and the review, and focused after the swap.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="cbuild-swapped" tabindex="-1" data-wp-interactive="creative-skateboard-builder" data-wp-bind--hidden="!state.view.swapped.shown">
	<span class="cbuild-swapped__text" data-wp-text="state.view.swapped.text"></span>
	<a class="cbuild-swapped__undo" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--href="state.view.swapped.undo.url" data-wp-text="state.view.swapped.undo.text"></a>
</p>
