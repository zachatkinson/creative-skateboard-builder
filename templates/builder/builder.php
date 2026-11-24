<?php
/**
 * The builder: its root, the status region, the rail, then the stage and the panel with its footer (spec: The layout). Every changing word and
 * address is bound to the view (Front\BuilderView); the words written here never change with the build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<?php
// The root is no region: each piece in it is one of its own, so the browser hydrates the builder a piece at a task, and
// no one task holds the page for long (D-296). What the root shows is the kit's, so it is written here; the store keeps
// aria-busy.
$cbuild_layout = $shown['view']['layout'];
$cbuild_class  = 'cbuild' . ( $cbuild_layout['stageRight'] ? ' cbuild--stage-right' : '' ) . ( $cbuild_layout['balanced'] ? ' cbuild--balanced' : '' ) . ( $cbuild_layout['subtle'] ? ' cbuild--subtle' : '' );
?>
<div class="<?php echo esc_attr( $cbuild_class ); ?>" data-kit="<?php echo esc_attr( (string) $shown['view']['kit'] ); ?>" aria-busy="false">
	<?php // The builder's first stop: past the rail to the step, seen once focused (spec: Accessibility, keyboard order). ?>
	<a class="cbuild__skip" href="#cbuild-heading"><?php esc_html_e( 'Skip to the step', 'creative-skateboard-builder' ); ?></a>
	<p class="cbuild__status screen-reader-text" role="status" aria-live="polite" data-wp-interactive="creative-skateboard-builder" data-wp-init="callbacks.init" data-wp-watch="callbacks.busy" data-wp-on-window--popstate="actions.back" data-wp-on-window--resize="actions.fit" data-wp-text="state.status"></p>
	<?php // Past the route's rate limit, or when it has not answered in time (D-364), the step keeps what it shows and says when to try again; the status region reads it out. ?>
	<p class="cbuild__limited" hidden data-wp-interactive="creative-skateboard-builder" data-wp-bind--hidden="!state.limited" data-wp-text="state.limited"></p>
	<?php // The rail first: a row across the top on a desktop (D-268), the sheet's head on a tablet or a phone. ?>
	<?php require __DIR__ . '/progress.php'; ?>
	<div class="cbuild__layout">
		<?php // The stage: the board, which stays in view as the panel scrolls (spec: Desktop, Tablet, Phone). ?>
		<div class="cbuild__stage" data-wp-interactive="creative-skateboard-builder" data-wp-class--cbuild__stage--bare="!state.view.picture.captioned">
			<?php require __DIR__ . '/picture.php'; ?>
		</div>
		<?php // The panel: the step, scrolling inside itself on a desktop, and the footer pinned at its foot. ?>
		<div class="cbuild__panel">
			<div class="cbuild__body">
				<?php // The panel's head: the way back to the shop, and the product's name where the builder holds the page's h1. ?>
				<div class="cbuild__head">
					<a class="cbuild__back" href="<?php echo esc_url( $head['shop'] ); ?>"><?php esc_html_e( 'Back to the shop', 'creative-skateboard-builder' ); ?></a>
					<?php if ( '' !== $head['title'] ) : ?>
						<h1 class="cbuild__title"><?php echo esc_html( $head['title'] ); ?></h1>
					<?php endif; ?>
				</div>
				<?php require __DIR__ . '/errors.php'; ?>
				<h2 class="cbuild__heading" id="cbuild-heading" tabindex="-1" data-wp-interactive="creative-skateboard-builder"><span class="screen-reader-text" data-wp-text="state.view.count"></span> <span data-wp-text="state.view.heading"></span></h2>
				<p class="cbuild-step__lead" data-wp-interactive="creative-skateboard-builder" data-wp-bind--hidden="!state.view.step.hasLead" data-wp-text="state.view.step.lead"></p>
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
