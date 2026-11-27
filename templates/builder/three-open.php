<?php
/**
 * View in 3D (1.4.0, #139), where the kit offers the 3D view and the build has a deck: in the row under the board with
 * the turn, after it, so a press is followed by the canvas it opens, and laid out before it, at the row's start. The row
 * wraps, so in a narrow picture or in longer words neither covers the other (F7, D-483).
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;

if ( true !== ( $shown['view']['three']['shown'] ?? false ) ) {
	return;
}
?>
<?php // Without the script the button does nothing, so it shows once the store runs; its place is kept until then. ?>
<button class="cbuild-button cbuild-3d__open" type="button" data-wp-bind--hidden="!state.view.scene" data-wp-class--cbuild-3d__open--waiting="!state.live" data-wp-bind--aria-pressed="state.three.open" data-wp-bind--aria-busy="state.three.loading" data-wp-on--click="actions.three"><?php esc_html_e( 'View in 3D', 'creative-skateboard-builder' ); ?></button>
