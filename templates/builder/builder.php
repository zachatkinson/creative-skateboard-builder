<?php
/**
 * The builder: its root, the status region, and each piece in the order a shopper meets it. Every changing word and
 * address is bound to the view (Front\BuilderView); the words written here never change with the build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<div class="cbuild" data-wp-interactive="creative-skateboard-builder" data-wp-bind--data-kit="state.view.kit">
	<p class="cbuild__status screen-reader-text" role="status" aria-live="polite" data-wp-text="state.status"></p>
	<?php require __DIR__ . '/errors.php'; ?>
	<?php require __DIR__ . '/progress.php'; ?>
	<h2 class="cbuild__heading" tabindex="-1" data-wp-text="state.view.heading"></h2>
	<div class="cbuild__layout">
		<div class="cbuild__main">
			<?php require __DIR__ . '/picture.php'; ?>
			<?php require __DIR__ . '/step.php'; ?>
			<?php require __DIR__ . '/changes.php'; ?>
			<?php require __DIR__ . '/review.php'; ?>
		</div>
		<?php require __DIR__ . '/summary.php'; ?>
	</div>
</div>
