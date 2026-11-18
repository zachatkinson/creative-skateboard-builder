<?php
/**
 * My builds, in My account (roadmap, milestone 8): each saved build's picture, name, total today and the date it was
 * saved, with Open, Rename and Delete. Rename and Delete are forms with a nonce each, posted to this page, so they work
 * without JavaScript (D-217). WooCommerce's own table classes, so the theme styles it as it styles Orders.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{uuid: string, name: string, picture: string|null, photo: string, total: string, ready: bool, saved: string, open: string|null}> $rows
 * @var string $action Where the forms post: this page.
 * @var array{uuid: string, name: string}|null $undo The build just deleted, which Undo puts back from the server's copy (D-231).
 */

defined( 'ABSPATH' ) || exit;

use CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks;
?>
<?php if ( null !== $undo ) : ?>
	<?php /* Right under WooCommerce's "The build is deleted." notice: a form that puts the build back, without JavaScript. It names the build; the build itself is the session's copy (Cowork's 0.8.0 review, F3; D-231). */ ?>
	<form class="cbuild-my-builds__undo" method="post" action="<?php echo esc_url( $action ); ?>">
		<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::FIELD ); ?>" value="<?php echo esc_attr( SavedBuildHooks::RESTORE ); ?>">
		<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::UUID ); ?>" value="<?php echo esc_attr( $undo['uuid'] ); ?>">
		<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::NONCE ); ?>" value="<?php echo esc_attr( wp_create_nonce( SavedBuildHooks::RESTORE . '_' . $undo['uuid'] ) ); ?>">
		<button type="submit" class="button" aria-label="<?php /* translators: %s: the deleted build's name. */ echo esc_attr( sprintf( __( 'Undo deleting %s', 'creative-skateboard-builder' ), $undo['name'] ) ); ?>"><?php esc_html_e( 'Undo', 'creative-skateboard-builder' ); ?></button>
	</form>
<?php endif; ?>
<?php if ( array() === $rows ) : ?>
	<p class="cbuild-my-builds__none"><?php esc_html_e( 'You have no saved builds yet. Save one from the review step of the builder, and it waits for you here.', 'creative-skateboard-builder' ); ?></p>
<?php else : ?>
<table class="cbuild-my-builds shop_table shop_table_responsive">
	<thead>
		<tr>
			<th scope="col"><?php esc_html_e( 'Build', 'creative-skateboard-builder' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Total today', 'creative-skateboard-builder' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Saved', 'creative-skateboard-builder' ); ?></th>
			<th scope="col"><?php esc_html_e( 'Actions', 'creative-skateboard-builder' ); ?></th>
		</tr>
	</thead>
	<tbody>
	<?php foreach ( $rows as $cbuild_row ) : ?>
		<?php $cbuild_id = 'cbuild-build-' . $cbuild_row['uuid']; ?>
		<?php /* Each control is named for its build, so a screen reader's list of them is not a column of Deletes. */ ?>
		<tr class="cbuild-my-builds__build">
			<th scope="row" data-title="<?php esc_attr_e( 'Build', 'creative-skateboard-builder' ); ?>">
				<?php if ( null !== $cbuild_row['picture'] ) : ?>
					<img class="cbuild-my-builds__picture" src="<?php echo esc_url( $cbuild_row['picture'] ); ?>" alt="" width="96" height="96" loading="lazy" decoding="async">
				<?php else : ?>
					<?php echo wp_kses_post( $cbuild_row['photo'] ); ?>
				<?php endif; ?>
				<span class="cbuild-my-builds__name" id="<?php echo esc_attr( $cbuild_id ); ?>"><?php echo esc_html( $cbuild_row['name'] ); ?></span>
			</th>
			<td data-title="<?php esc_attr_e( 'Total today', 'creative-skateboard-builder' ); ?>">
				<?php if ( $cbuild_row['ready'] ) : ?>
					<?php echo esc_html( $cbuild_row['total'] ); ?>
				<?php else : ?>
					<?php esc_html_e( 'A part has changed since you saved it. Open the build to see which.', 'creative-skateboard-builder' ); ?>
				<?php endif; ?>
			</td>
			<td data-title="<?php esc_attr_e( 'Saved', 'creative-skateboard-builder' ); ?>"><?php echo esc_html( $cbuild_row['saved'] ); ?></td>
			<td data-title="<?php esc_attr_e( 'Actions', 'creative-skateboard-builder' ); ?>">
				<?php if ( null !== $cbuild_row['open'] ) : ?>
					<a class="button cbuild-my-builds__open" rel="nofollow" href="<?php echo esc_url( $cbuild_row['open'] ); ?>" aria-label="<?php /* translators: %s: the saved build's name. */ echo esc_attr( sprintf( __( 'Open %s', 'creative-skateboard-builder' ), $cbuild_row['name'] ) ); ?>"><?php esc_html_e( 'Open', 'creative-skateboard-builder' ); ?></a>
				<?php else : ?>
					<span class="cbuild-my-builds__gone"><?php esc_html_e( 'The shop no longer sells this kit.', 'creative-skateboard-builder' ); ?></span>
				<?php endif; ?>
				<form class="cbuild-my-builds__rename" method="post" action="<?php echo esc_url( $action ); ?>">
					<label for="<?php echo esc_attr( $cbuild_id . '-name' ); ?>"><?php esc_html_e( 'Name', 'creative-skateboard-builder' ); ?></label>
					<input type="text" id="<?php echo esc_attr( $cbuild_id . '-name' ); ?>" name="<?php echo esc_attr( SavedBuildHooks::NAME ); ?>" value="<?php echo esc_attr( $cbuild_row['name'] ); ?>" maxlength="100" aria-label="<?php /* translators: %s: the saved build's name. */ echo esc_attr( sprintf( __( 'Name of %s', 'creative-skateboard-builder' ), $cbuild_row['name'] ) ); ?>">
					<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::FIELD ); ?>" value="<?php echo esc_attr( SavedBuildHooks::RENAME ); ?>">
					<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::UUID ); ?>" value="<?php echo esc_attr( $cbuild_row['uuid'] ); ?>">
					<?php /* A nonce field each, without wp_nonce_field()'s id, which every row would repeat. */ ?>
					<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::NONCE ); ?>" value="<?php echo esc_attr( wp_create_nonce( SavedBuildHooks::RENAME . '_' . $cbuild_row['uuid'] ) ); ?>">
					<button type="submit" class="button" aria-label="<?php /* translators: %s: the saved build's name. */ echo esc_attr( sprintf( __( 'Rename %s', 'creative-skateboard-builder' ), $cbuild_row['name'] ) ); ?>"><?php esc_html_e( 'Rename', 'creative-skateboard-builder' ); ?></button>
				</form>
				<form class="cbuild-my-builds__delete" method="post" action="<?php echo esc_url( $action ); ?>">
					<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::FIELD ); ?>" value="<?php echo esc_attr( SavedBuildHooks::DELETE ); ?>">
					<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::UUID ); ?>" value="<?php echo esc_attr( $cbuild_row['uuid'] ); ?>">
					<?php /* A nonce field each, without wp_nonce_field()'s id, which every row would repeat. */ ?>
					<input type="hidden" name="<?php echo esc_attr( SavedBuildHooks::NONCE ); ?>" value="<?php echo esc_attr( wp_create_nonce( SavedBuildHooks::DELETE . '_' . $cbuild_row['uuid'] ) ); ?>">
					<button type="submit" class="button" aria-label="<?php /* translators: %s: the saved build's name. */ echo esc_attr( sprintf( __( 'Delete %s', 'creative-skateboard-builder' ), $cbuild_row['name'] ) ); ?>"><?php esc_html_e( 'Delete', 'creative-skateboard-builder' ); ?></button>
				</form>
			</td>
		</tr>
	<?php endforeach; ?>
	</tbody>
</table>
<?php endif; ?>
