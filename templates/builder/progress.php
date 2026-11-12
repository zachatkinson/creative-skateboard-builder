<?php
/**
 * The progress list (spec: A step, where the shopper is): each step done, current or to come, the done ones linking
 * back with every pick kept.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<nav class="cbuild-progress" aria-label="<?php esc_attr_e( 'Steps', 'creative-skateboard-builder' ); ?>">
	<ol class="cbuild-progress__list">
		<template data-wp-each--place="state.view.progress" data-wp-each-key="context.place.key">
			<li class="cbuild-progress__step" data-wp-class--cbuild-progress__step--done="context.place.done" data-wp-class--cbuild-progress__step--current="context.place.current" data-wp-bind--aria-current="context.place.aria">
				<a class="cbuild-progress__link" rel="nofollow" data-wp-on--click="actions.follow" data-wp-bind--hidden="!context.place.done" data-wp-bind--href="context.place.url"><span data-wp-text="context.place.label"></span><span class="screen-reader-text" data-wp-text="context.place.state"></span></a>
				<span class="cbuild-progress__label" data-wp-bind--hidden="context.place.done"><span data-wp-text="context.place.label"></span><span class="screen-reader-text" data-wp-text="context.place.state"></span></span>
			</li>
		</template>
	</ol>
</nav>
