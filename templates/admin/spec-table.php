<?php
/**
 * WooCommerce > Builder: one maker's chart, a group of fields per size.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var int                                                                                        $id       The chart's ID.
 * @var string                                                                                     $name     The maker's name, as saved or as typed.
 * @var list<array{size: string, source: string, read_on: string, specs: array<string, string>}>   $rows     The rows, the blank ones last.
 * @var array<string, array{label: string, choices: array<string, string>}>                        $columns  The figures a row shows, by spec key.
 * @var array<int, list<string>>                                                                   $problems What is wrong, by row from 1; 0 is the chart.
 * @var array<int, array<string, true>>                                                            $wrong    The fields that are wrong, by row.
 * @var array{name: int, size: int, source: int}                                                   $limits   The longest of each text.
 * @var string                                                                                     $notice   What happened, '' when nothing did.
 * @var bool                                                                                       $failed   Whether the notice is of something refused.
 * @var string                                                                                     $nonce    The forms' nonce action.
 * @var string                                                                                     $action   Where the form is sent.
 * @var string                                                                                     $delete   The page that asks before deleting.
 * @var string                                                                                     $back     The list of charts.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1>
		<?php
		/* translators: %s: a truck maker's name, such as Independent. */
		echo esc_html( sprintf( __( 'Spec table: %s', 'creative-skateboard-builder' ), $name ) );
		?>
	</h1>
	<p><a href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'All spec tables', 'creative-skateboard-builder' ); ?></a></p>
	<?php if ( '' !== $notice ) : ?>
		<div class="notice <?php echo $failed ? 'notice-error' : 'notice-success'; ?>"><p><?php echo esc_html( $notice ); ?></p></div>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( $action ); ?>">
		<?php wp_nonce_field( $nonce ); ?>
		<input type="hidden" name="cbuild_action" value="save" />
		<input type="hidden" name="cbuild_table" value="<?php echo esc_attr( (string) $id ); ?>" />
		<?php if ( isset( $problems[0] ) ) : ?>
			<div class="notice notice-error inline" id="cbuild-chart-problem">
				<?php foreach ( $problems[0] as $cbuild_problem ) : ?>
					<p><?php echo esc_html( $cbuild_problem ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<p>
			<label for="cbuild-name"><?php esc_html_e( 'Maker\'s name', 'creative-skateboard-builder' ); ?></label><br />
			<input
				type="text"
				class="regular-text"
				id="cbuild-name"
				name="cbuild_name"
				value="<?php echo esc_attr( $name ); ?>"
				maxlength="<?php echo esc_attr( (string) $limits['name'] ); ?>"
				autocomplete="off"
				<?php if ( isset( $wrong[0]['name'] ) ) : ?>
					aria-invalid="true" aria-describedby="cbuild-chart-problem"
				<?php endif; ?>
			/>
		</p>

		<h2><?php esc_html_e( 'Sizes', 'creative-skateboard-builder' ); ?></h2>
		<p class="description"><?php esc_html_e( 'A group of fields per size. Leave a figure blank where the maker gives none. A measurement takes a decimal or a fraction. To remove a size, clear every field of its group. Save to add more groups.', 'creative-skateboard-builder' ); ?></p>
		<?php foreach ( $rows as $cbuild_index => $cbuild_row ) : ?>
			<?php
			$cbuild_number = $cbuild_index + 1;
			$cbuild_id     = 'cbuild-row-' . $cbuild_number;
			$cbuild_note   = $cbuild_id . '-problem';
			$cbuild_name   = 'cbuild_rows[' . $cbuild_index . ']';
			$cbuild_bad    = $wrong[ $cbuild_number ] ?? array();
			?>
			<fieldset class="cbuild-chart-row">
				<legend>
					<?php
					if ( '' === $cbuild_row['size'] ) {
						/* translators: %d: the row's number in the chart. */
						echo esc_html( sprintf( __( 'Row %d', 'creative-skateboard-builder' ), $cbuild_number ) );
					} else {
						/* translators: 1: the row's number in the chart. 2: a size in a truck maker's chart, such as 149. */
						echo esc_html( sprintf( __( 'Row %1$d: size %2$s', 'creative-skateboard-builder' ), $cbuild_number, $cbuild_row['size'] ) );
					}
					?>
				</legend>
				<?php if ( isset( $problems[ $cbuild_number ] ) ) : ?>
					<div class="notice notice-error inline" id="<?php echo esc_attr( $cbuild_note ); ?>">
						<?php foreach ( $problems[ $cbuild_number ] as $cbuild_problem ) : ?>
							<p><?php echo esc_html( $cbuild_problem ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<span class="cbuild-field">
					<label for="<?php echo esc_attr( $cbuild_id . '-size' ); ?>"><?php esc_html_e( 'Size', 'creative-skateboard-builder' ); ?></label><br />
					<input
						type="text"
						class="small-text"
						id="<?php echo esc_attr( $cbuild_id . '-size' ); ?>"
						name="<?php echo esc_attr( $cbuild_name . '[size]' ); ?>"
						value="<?php echo esc_attr( $cbuild_row['size'] ); ?>"
						maxlength="<?php echo esc_attr( (string) $limits['size'] ); ?>"
						autocomplete="off"
						<?php if ( isset( $cbuild_bad['size'] ) ) : ?>
							aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
						<?php endif; ?>
					/>
				</span>
				<?php foreach ( $columns as $cbuild_key => $cbuild_column ) : ?>
					<?php $cbuild_value = $cbuild_row['specs'][ $cbuild_key ] ?? ''; ?>
					<span class="cbuild-field">
						<label for="<?php echo esc_attr( $cbuild_id . '-' . $cbuild_key ); ?>"><?php echo esc_html( $cbuild_column['label'] ); ?></label><br />
						<?php if ( array() === $cbuild_column['choices'] ) : ?>
							<input
								type="text"
								class="small-text"
								id="<?php echo esc_attr( $cbuild_id . '-' . $cbuild_key ); ?>"
								name="<?php echo esc_attr( $cbuild_name . '[specs][' . $cbuild_key . ']' ); ?>"
								value="<?php echo esc_attr( $cbuild_value ); ?>"
								autocomplete="off"
								<?php if ( isset( $cbuild_bad[ $cbuild_key ] ) ) : ?>
									aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
								<?php endif; ?>
							/>
						<?php else : ?>
							<select
								id="<?php echo esc_attr( $cbuild_id . '-' . $cbuild_key ); ?>"
								name="<?php echo esc_attr( $cbuild_name . '[specs][' . $cbuild_key . ']' ); ?>"
								<?php if ( isset( $cbuild_bad[ $cbuild_key ] ) ) : ?>
									aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
								<?php endif; ?>
							>
								<option value=""><?php esc_html_e( 'Not set', 'creative-skateboard-builder' ); ?></option>
								<?php foreach ( $cbuild_column['choices'] as $cbuild_choice => $cbuild_label ) : ?>
									<option value="<?php echo esc_attr( $cbuild_choice ); ?>" <?php selected( $cbuild_value, $cbuild_choice ); ?>><?php echo esc_html( $cbuild_label ); ?></option>
								<?php endforeach; ?>
							</select>
						<?php endif; ?>
					</span>
				<?php endforeach; ?>
				<span class="cbuild-field">
					<label for="<?php echo esc_attr( $cbuild_id . '-source' ); ?>"><?php esc_html_e( 'Source', 'creative-skateboard-builder' ); ?></label><br />
					<input
						type="text"
						class="regular-text"
						id="<?php echo esc_attr( $cbuild_id . '-source' ); ?>"
						name="<?php echo esc_attr( $cbuild_name . '[source]' ); ?>"
						value="<?php echo esc_attr( $cbuild_row['source'] ); ?>"
						maxlength="<?php echo esc_attr( (string) $limits['source'] ); ?>"
						autocomplete="off"
						<?php if ( isset( $cbuild_bad['source'] ) ) : ?>
							aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
						<?php endif; ?>
					/>
				</span>
				<span class="cbuild-field">
					<label for="<?php echo esc_attr( $cbuild_id . '-read' ); ?>"><?php esc_html_e( 'Read on', 'creative-skateboard-builder' ); ?></label><br />
					<input
						type="date"
						id="<?php echo esc_attr( $cbuild_id . '-read' ); ?>"
						name="<?php echo esc_attr( $cbuild_name . '[read_on]' ); ?>"
						value="<?php echo esc_attr( $cbuild_row['read_on'] ); ?>"
						<?php if ( isset( $cbuild_bad['read_on'] ) ) : ?>
							aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
						<?php endif; ?>
					/>
				</span>
				<?php foreach ( $cbuild_row['specs'] as $cbuild_key => $cbuild_value ) : ?>
					<?php if ( ! isset( $columns[ $cbuild_key ] ) ) : ?>
						<input type="hidden" name="<?php echo esc_attr( $cbuild_name . '[specs][' . $cbuild_key . ']' ); ?>" value="<?php echo esc_attr( $cbuild_value ); ?>" />
					<?php endif; ?>
				<?php endforeach; ?>
			</fieldset>
		<?php endforeach; ?>
		<?php submit_button( __( 'Save chart', 'creative-skateboard-builder' ) ); ?>
	</form>
	<p><a class="button-link button-link-delete" href="<?php echo esc_url( $delete ); ?>"><?php esc_html_e( 'Delete this chart', 'creative-skateboard-builder' ); ?></a></p>
</div>
