<?php
/**
 * The 3D view (1.4.0, #139): once "View in 3D" is pressed (three-open.php, in the row with the turn), the canvas in
 * the board's place with the camera's buttons under it. Only where the kit turns the view on; until a deck is picked there is no scene, and none
 * of it shows. The store loads the 3D script on the press alone, so until then the page is the page without it.
 *
 * The canvas is named with the build's sentence, as the board's caption says it, and described by how to move it.
 * The camera's buttons are real buttons, each named here, and so are the trick buttons, where the kit offers them. Where the 3D view can't be shown, or stops, the board
 * comes back with the message under it, and nothing else on the page changes.
 *
 * The words never change with the build, so they are written here; the store shows and hides them.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;

if ( true !== ( $shown['view']['three']['shown'] ?? false ) ) {
	return;
}
?>
<div class="cbuild-3d" data-wp-bind--hidden="!state.view.scene" data-wp-watch="callbacks.scene">
	<div class="cbuild-3d__stage" data-wp-bind--hidden="!state.three.open">
		<?php // The frame holds the canvas's place, so the size the canvas is drawn at never changes the page's layout. ?>
		<div class="cbuild-3d__frame">
			<canvas class="cbuild-3d__canvas" tabindex="0" role="img" aria-describedby="cbuild-3d-keys cbuild-3d-scale" data-wp-bind--class="state.view.three.canvas" data-wp-bind--aria-label="state.view.picture.description"></canvas>
		</div>
		<?php
		// Whether a usual value stood in for any size: the server picks the words, and the store shows the one the
		// view's scene calls for. A spec key is never "0", so its first key read as a path is true in PHP and in the
		// store alike, where an empty list would be true in one and false in the other (F13, D-484).
		$cbuild_assumed = array() !== ( $shown['view']['scene']['assumed'] ?? array() );
		?>
		<p class="cbuild-3d__note" id="cbuild-3d-scale"><span<?php echo $cbuild_assumed ? ' hidden' : ''; ?> data-wp-bind--hidden="state.view.scene.assumed.0"><?php esc_html_e( 'Sizes true to scale, shapes simplified.', 'creative-skateboard-builder' ); ?></span><span<?php echo $cbuild_assumed ? '' : ' hidden'; ?> data-wp-bind--hidden="!state.view.scene.assumed.0"><?php esc_html_e( 'Sizes to scale, with usual sizes where a part lists none. Shapes simplified.', 'creative-skateboard-builder' ); ?></span></p>
		<p class="cbuild-3d__keys" id="cbuild-3d-keys"><?php esc_html_e( 'Drag or use the arrow keys to turn the board, and + and − to zoom.', 'creative-skateboard-builder' ); ?></p>
		<div class="cbuild-3d__places" role="group" aria-label="<?php esc_attr_e( 'Views of the board', 'creative-skateboard-builder' ); ?>">
			<button class="cbuild-button" type="button" value="reset" data-wp-on--click="actions.look"><?php esc_html_e( 'Reset view', 'creative-skateboard-builder' ); ?></button>
			<button class="cbuild-button" type="button" value="top" data-wp-on--click="actions.look"><?php echo esc_html_x( 'Top', 'a view of the board in 3D', 'creative-skateboard-builder' ); ?></button>
			<button class="cbuild-button" type="button" value="under" data-wp-on--click="actions.look"><?php echo esc_html_x( 'Underside', 'a view of the board in 3D', 'creative-skateboard-builder' ); ?></button>
			<button class="cbuild-button" type="button" value="graphic" data-wp-on--click="actions.look"><?php esc_html_e( 'Graphic close-up', 'creative-skateboard-builder' ); ?></button>
		</div>
		<?php
		// The trick buttons, with the kit's Trick buttons on. Each plays once, on its press alone, and while one plays
		// they say they are disabled. With reduced motion the stylesheet hides them and the view plays none (D-503).
		if ( true === ( $shown['view']['three']['tricks'] ?? false ) ) :
			$cbuild_tricks = array(
				'ollie'        => _x( 'Ollie', 'a skateboard trick', 'creative-skateboard-builder' ),
				'kickflip'     => _x( 'Kickflip', 'a skateboard trick', 'creative-skateboard-builder' ),
				'heelflip'     => _x( 'Heelflip', 'a skateboard trick', 'creative-skateboard-builder' ),
				'pop-shove-it' => _x( 'Pop shove-it', 'a skateboard trick', 'creative-skateboard-builder' ),
				'360-flip'     => _x( '360 flip', 'a skateboard trick', 'creative-skateboard-builder' ),
			);
			?>
		<div class="cbuild-3d__tricks" role="group" aria-label="<?php echo esc_attr_x( 'Tricks', 'skateboard tricks the 3D view plays', 'creative-skateboard-builder' ); ?>">
			<?php foreach ( $cbuild_tricks as $cbuild_value => $cbuild_name ) : ?>
			<button class="cbuild-button" type="button" value="<?php echo esc_attr( $cbuild_value ); ?>" data-wp-bind--aria-disabled="state.three.playing" data-wp-on--click="actions.trick"><?php echo esc_html( $cbuild_name ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php endif; ?>
	</div>
	<p class="cbuild-3d__failed" data-wp-bind--hidden="!state.three.failed"><?php esc_html_e( 'The 3D view can’t be shown here, so the board is drawn flat.', 'creative-skateboard-builder' ); ?></p>
</div>
