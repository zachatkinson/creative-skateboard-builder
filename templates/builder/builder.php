<?php
/**
 * The builder: its root, the status region, the rail, then the stage and the panel with its footer (spec: The layout). Every changing word and
 * address is bound to the view (Front\BuilderView); the words written here never change with the build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cbuild" data-wp-interactive="creative-skateboard-builder" data-wp-bind--data-kit="state.view.kit" data-wp-bind--aria-busy="state.pending" data-wp-on-window--popstate="actions.back" data-wp-on-window--resize="actions.fit" data-wp-on--focusin="actions.reveal" data-wp-init="callbacks.init">
	<p class="cbuild__status screen-reader-text" role="status" aria-live="polite" data-wp-text="state.status"></p>
	<?php // The rail first: a row across the top on a desktop (D-268), the sheet's head on a tablet or a phone. ?>
	<?php require __DIR__ . '/progress.php'; ?>
	<div class="cbuild__layout">
		<?php // The stage: the board, which stays in view as the panel scrolls (spec: Desktop, Tablet, Phone). ?>
		<div class="cbuild__stage">
			<?php require __DIR__ . '/picture.php'; ?>
		</div>
		<?php // The panel: the step, scrolling inside itself on a desktop, and the footer pinned at its foot. ?>
		<div class="cbuild__panel">
			<div class="cbuild__body">
				<?php require __DIR__ . '/errors.php'; ?>
				<h2 class="cbuild__heading" id="cbuild-heading" tabindex="-1"><span class="screen-reader-text" data-wp-text="state.view.count"></span> <span data-wp-text="state.view.heading"></span></h2>
				<p class="cbuild-step__lead" data-wp-bind--hidden="!state.view.step.hasLead" data-wp-text="state.view.step.lead"></p>
				<?php require __DIR__ . '/step.php'; ?>
				<?php // The ready builds after the step's question, as another way in (spec: Ways in). ?>
				<?php require __DIR__ . '/presets.php'; ?>
				<?php require __DIR__ . '/changes.php'; ?>
				<?php require __DIR__ . '/review.php'; ?>
			</div>
			<?php require __DIR__ . '/bar.php'; ?>
		</div>
	</div>
</div>
