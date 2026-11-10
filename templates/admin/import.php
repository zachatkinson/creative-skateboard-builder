<?php
/**
 * WooCommerce > Builder, Import and export.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{label: string, url: string, current: bool}>                             $tabs     The page's tabs.
 * @var string                                                                             $notice   What happened, '' when nothing did.
 * @var bool                                                                               $failed   Whether the notice is of something refused.
 * @var list<string>                                                                       $problems Why the file sent was not read.
 * @var string                                                                             $status   The import in hand: waiting, running, done, failed, or '' for none.
 * @var array{rows: int, done: int, changed: int, same: int, refused: int}                 $counts   Its rows, and what they do or did.
 * @var list<array{row: int, column: string, problem: string}>                             $wrong    The rows that cannot be read.
 * @var list<array{row: int, part: string, column: string, from: string, to: string}>      $changes  What a waiting import would change.
 * @var array{wrong: int, changes: int}                                                    $more     How many of each are not listed.
 * @var string                                                                             $token    The waiting import's token.
 * @var bool                                                                               $mine     Whether the waiting import is this user's to confirm.
 * @var string                                                                             $nonce    The forms' nonce action.
 * @var string                                                                             $file     The file field's name.
 * @var string                                                                             $back     The tab's own address.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Builder', 'creative-skateboard-builder' ); ?></h1>
	<?php require __DIR__ . '/builder-tabs.php'; ?>
	<?php if ( '' !== $notice ) : ?>
		<div class="notice <?php echo $failed ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>

	<?php if ( 'waiting' === $status ) : ?>
		<h2><?php esc_html_e( 'Check the import', 'creative-skateboard-builder' ); ?></h2>
		<p><?php esc_html_e( 'Nothing has been changed yet. This is what the file would do.', 'creative-skateboard-builder' ); ?></p>
		<ul>
			<li>
				<?php
				/* translators: %s: a number of parts. */
				echo esc_html( sprintf( _n( '%s part would change.', '%s parts would change.', $counts['changed'], 'creative-skateboard-builder' ), number_format_i18n( $counts['changed'] ) ) );
				?>
			</li>
			<li>
				<?php
				/* translators: %s: a number of rows. */
				echo esc_html( sprintf( _n( '%s row is the same as the shop has now.', '%s rows are the same as the shop has now.', $counts['same'], 'creative-skateboard-builder' ), number_format_i18n( $counts['same'] ) ) );
				?>
			</li>
			<li>
				<?php
				/* translators: %s: a number of rows. */
				echo esc_html( sprintf( _n( '%s row cannot be read, and would be left out.', '%s rows cannot be read, and would be left out.', $counts['refused'], 'creative-skateboard-builder' ), number_format_i18n( $counts['refused'] ) ) );
				?>
			</li>
		</ul>
		<?php if ( ! $mine ) : ?>
			<p><?php esc_html_e( 'Another person sent this file, and only they can confirm it. You can put it away and choose a file of your own.', 'creative-skateboard-builder' ); ?></p>
		<?php endif; ?>
		<form method="post" action="<?php echo esc_url( $back ); ?>">
			<?php wp_nonce_field( $nonce ); ?>
			<input type="hidden" name="cbuild_token" value="<?php echo esc_attr( $token ); ?>" />
			<p class="submit">
				<?php if ( $counts['changed'] > 0 && $mine ) : ?>
					<button type="submit" class="button button-primary" name="cbuild_action" value="confirm"><?php esc_html_e( 'Import these changes', 'creative-skateboard-builder' ); ?></button>
				<?php endif; ?>
				<button type="submit" class="button" name="cbuild_action" value="discard"><?php esc_html_e( 'Put the file away', 'creative-skateboard-builder' ); ?></button>
			</p>
		</form>
	<?php elseif ( 'running' === $status ) : ?>
		<h2><?php esc_html_e( 'The import is running', 'creative-skateboard-builder' ); ?></h2>
		<p>
			<?php
			/* translators: 1: rows done. 2: rows in the file. */
			echo esc_html( sprintf( __( '%1$s of %2$s rows done. It runs in the background, so this page can be left.', 'creative-skateboard-builder' ), number_format_i18n( $counts['done'] ), number_format_i18n( $counts['rows'] ) ) );
			?>
		</p>
		<p><a class="button" href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Check again', 'creative-skateboard-builder' ); ?></a></p>
	<?php elseif ( 'done' === $status || 'failed' === $status ) : ?>
		<h2><?php esc_html_e( 'The last import', 'creative-skateboard-builder' ); ?></h2>
		<ul>
			<li>
				<?php
				/* translators: %s: a number of parts. */
				echo esc_html( sprintf( _n( '%s part was changed.', '%s parts were changed.', $counts['changed'], 'creative-skateboard-builder' ), number_format_i18n( $counts['changed'] ) ) );
				?>
			</li>
			<li>
				<?php
				/* translators: %s: a number of rows. */
				echo esc_html( sprintf( _n( '%s row was the same as the shop had.', '%s rows were the same as the shop had.', $counts['same'], 'creative-skateboard-builder' ), number_format_i18n( $counts['same'] ) ) );
				?>
			</li>
			<li>
				<?php
				/* translators: %s: a number of rows. */
				echo esc_html( sprintf( _n( '%s row could not be read, and was left out.', '%s rows could not be read, and were left out.', $counts['refused'], 'creative-skateboard-builder' ), number_format_i18n( $counts['refused'] ) ) );
				?>
			</li>
		</ul>
	<?php endif; ?>

	<?php if ( array() !== $wrong ) : ?>
		<h3 id="cbuild-wrong"><?php esc_html_e( 'Rows that cannot be read', 'creative-skateboard-builder' ); ?></h3>
		<div class="cbuild-scroll" role="region" aria-labelledby="cbuild-wrong" tabindex="0">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Row', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Column', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What is wrong', 'creative-skateboard-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $wrong as $cbuild_problem ) : ?>
						<tr>
							<td><?php echo esc_html( number_format_i18n( $cbuild_problem['row'] ) ); ?></td>
							<td><?php echo esc_html( $cbuild_problem['column'] ); ?></td>
							<td><?php echo esc_html( $cbuild_problem['problem'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $more['wrong'] > 0 ) : ?>
			<p>
				<?php
				/* translators: %s: how many more rows a list has than it shows. */
				echo esc_html( sprintf( _n( 'And %s more.', 'And %s more.', $more['wrong'], 'creative-skateboard-builder' ), number_format_i18n( $more['wrong'] ) ) );
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( array() !== $changes ) : ?>
		<h3 id="cbuild-changes"><?php esc_html_e( 'What would change', 'creative-skateboard-builder' ); ?></h3>
		<div class="cbuild-scroll" role="region" aria-labelledby="cbuild-changes" tabindex="0">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Row', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Part', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Spec', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Now', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'After', 'creative-skateboard-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $changes as $cbuild_change ) : ?>
						<tr>
							<td><?php echo esc_html( number_format_i18n( $cbuild_change['row'] ) ); ?></td>
							<td><?php echo esc_html( $cbuild_change['part'] ); ?></td>
							<td><?php echo esc_html( $cbuild_change['column'] ); ?></td>
							<td><?php echo esc_html( $cbuild_change['from'] ); ?></td>
							<td><?php echo esc_html( $cbuild_change['to'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $more['changes'] > 0 ) : ?>
			<p>
				<?php
				/* translators: %s: how many more rows a list has than it shows. */
				echo esc_html( sprintf( _n( 'And %s more.', 'And %s more.', $more['changes'], 'creative-skateboard-builder' ), number_format_i18n( $more['changes'] ) ) );
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>

	<?php if ( array() !== $problems ) : ?>
		<div class="notice notice-error inline" id="cbuild-file-problem">
			<?php foreach ( $problems as $cbuild_problem ) : ?>
				<p><?php echo esc_html( $cbuild_problem ); ?></p>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
	<?php if ( 'running' !== $status ) : ?>
		<h2><?php esc_html_e( 'Import', 'creative-skateboard-builder' ); ?></h2>
		<p><?php esc_html_e( 'Choose a CSV file with a row for each product or variation, and a column for each spec to set. A column the file leaves out is left as it is, and an empty cell removes the value. The file is checked first and shown here; nothing changes until you confirm.', 'creative-skateboard-builder' ); ?></p>
		<form method="post" enctype="multipart/form-data" action="<?php echo esc_url( $back ); ?>">
			<?php wp_nonce_field( $nonce ); ?>
			<input type="hidden" name="cbuild_action" value="read" />
			<p>
				<label for="cbuild-file"><?php esc_html_e( 'CSV file, 2 MB and 5,000 rows at most', 'creative-skateboard-builder' ); ?></label><br />
				<input
					type="file"
					id="cbuild-file"
					name="<?php echo esc_attr( $file ); ?>"
					accept=".csv,text/csv"
					<?php if ( array() !== $problems ) : ?>
						aria-invalid="true" aria-describedby="cbuild-file-problem"
					<?php endif; ?>
				/>
			</p>
			<p>
				<label for="cbuild-key"><?php esc_html_e( 'Match parts by', 'creative-skateboard-builder' ); ?></label><br />
				<select id="cbuild-key" name="cbuild_key">
					<option value="id"><?php esc_html_e( 'Product ID, the id column', 'creative-skateboard-builder' ); ?></option>
					<option value="sku"><?php esc_html_e( 'SKU, the sku column', 'creative-skateboard-builder' ); ?></option>
				</select>
			</p>
			<?php submit_button( __( 'Check the file', 'creative-skateboard-builder' ), 'primary', 'submit', true ); ?>
		</form>
	<?php endif; ?>

	<h2><?php esc_html_e( 'Export', 'creative-skateboard-builder' ); ?></h2>
	<p><?php esc_html_e( 'A CSV file with a row for every product and variation and what each holds itself. Fill it in with a spreadsheet and import it here.', 'creative-skateboard-builder' ); ?></p>
	<form method="post" action="<?php echo esc_url( $back ); ?>">
		<?php wp_nonce_field( $nonce, '_wpnonce', true ); ?>
		<input type="hidden" name="cbuild_action" value="export" />
		<?php submit_button( __( 'Download the CSV file', 'creative-skateboard-builder' ), 'secondary', 'submit', true ); ?>
	</form>
</div>
