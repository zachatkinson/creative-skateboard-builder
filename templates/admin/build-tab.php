<?php
/**
 * The Build tab: a kit's slots, rules, presets and assembly settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var string $marker The name of the panel's last field.
 * @var list<array{id: string, title: string, intro: string, refused: bool, problems: list<array{error: string, field: string, text: string}>, groups: list<array{id: string, legend: string, moves: bool, fold: string, open: bool, fields: list<array<string, mixed>>, more: list<array<string, mixed>>}>}> $sections The tab's sections. None on a product that is not a kit.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div id="cbuild_build" class="panel woocommerce_options_panel hidden cbuild-kit">
	<?php if ( array() === $sections ) : ?>
		<div class="options_group">
			<p class="form-field"><?php esc_html_e( 'Save the product as a Builder kit, and its slots, rules, presets and assembly settings appear here.', 'creative-skateboard-builder' ); ?></p>
		</div>
	<?php endif; ?>
	<?php foreach ( $sections as $cbuild_section ) : ?>
		<div class="options_group" id="<?php echo esc_attr( $cbuild_section['id'] ); ?>">
			<h3><?php echo esc_html( $cbuild_section['title'] ); ?></h3>
			<p class="form-field"><?php echo esc_html( $cbuild_section['intro'] ); ?></p>
			<?php if ( $cbuild_section['refused'] ) : ?>
				<div class="cbuild-kit-problems">
					<p><strong><?php esc_html_e( 'Not saved. This is what you typed, and what to put right:', 'creative-skateboard-builder' ); ?></strong></p>
					<ul>
						<?php foreach ( $cbuild_section['problems'] as $cbuild_problem ) : ?>
							<li id="<?php echo esc_attr( $cbuild_problem['error'] ); ?>"><a href="#<?php echo esc_attr( $cbuild_problem['field'] ); ?>"><?php echo esc_html( $cbuild_problem['text'] ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			<?php endif; ?>
			<?php
			$cbuild_moves = in_array( true, array_column( $cbuild_section['groups'], 'moves' ), true );
			// The screen's script says where a row went, in these words, once it has moved it.
			/* translators: 1: a slot's label. 2: its new place in the list. 3: how many slots there are. */
			$cbuild_moved = __( '%1$s is now slot %2$d of %3$d.', 'creative-skateboard-builder' );
			?>
			<div
				<?php if ( $cbuild_moves ) : ?>
					class="cbuild-kit-rows"
					data-cbuild-moved="<?php echo esc_attr( $cbuild_moved ); ?>"
				<?php endif; ?>
			>
				<?php foreach ( $cbuild_section['groups'] as $cbuild_group ) : ?>
					<?php
					// A group with no legend of its own is named by its section's heading: a plain box, not a fieldset
					// a screen reader announces with no name (Cowork's M7 review, M5).
					$cbuild_tag = '' === $cbuild_group['legend'] ? 'div' : 'fieldset';
					?>
					<<?php echo esc_html( $cbuild_tag ); ?> id="<?php echo esc_attr( $cbuild_group['id'] ); ?>" class="<?php echo esc_attr( trim( 'cbuild-kit-group ' . ( $cbuild_group['moves'] ? 'cbuild-kit-row' : '' ) ) ); ?>">
						<?php if ( '' !== $cbuild_group['legend'] ) : ?>
							<legend id="<?php echo esc_attr( $cbuild_group['id'] . '-legend' ); ?>"><?php echo esc_html( $cbuild_group['legend'] ); ?></legend>
						<?php endif; ?>
						<?php if ( $cbuild_group['moves'] ) : ?>
							<p class="cbuild-kit-moves">
								<span class="cbuild-kit-handle dashicons dashicons-menu" aria-hidden="true"></span>
								<?php // Each button is named with its row: "Move up Slot: Trucks", not "Move up" seven times over. ?>
								<button type="button" class="button" data-cbuild-move="up" id="<?php echo esc_attr( $cbuild_group['id'] . '-up' ); ?>"><?php esc_html_e( 'Move up', 'creative-skateboard-builder' ); ?> <span class="screen-reader-text"><?php echo esc_html( $cbuild_group['legend'] ); ?></span></button>
								<button type="button" class="button" data-cbuild-move="down" id="<?php echo esc_attr( $cbuild_group['id'] . '-down' ); ?>"><?php esc_html_e( 'Move down', 'creative-skateboard-builder' ); ?> <span class="screen-reader-text"><?php echo esc_html( $cbuild_group['legend'] ); ?></span></button>
							</p>
						<?php endif; ?>
						<?php if ( '' !== $cbuild_group['fold'] ) : ?>
							<details class="cbuild-kit-fold"<?php echo $cbuild_group['open'] ? ' open' : ''; ?>>
								<summary
									id="<?php echo esc_attr( $cbuild_group['id'] . '-fold' ); ?>"
									<?php if ( '' !== $cbuild_group['legend'] ) : ?>
										aria-labelledby="<?php echo esc_attr( $cbuild_group['id'] . '-fold ' . $cbuild_group['id'] . '-legend' ); ?>"
									<?php endif; ?>
								><?php echo esc_html( $cbuild_group['fold'] ); ?></summary>
						<?php endif; ?>
						<?php
						foreach ( array( $cbuild_group['fields'], $cbuild_group['more'] ) as $cbuild_at => $cbuild_fields ) :
							if ( array() === $cbuild_fields ) {
								continue;
							}
							$cbuild_chosen = array_filter( array_column( $cbuild_fields, 'value' ) );
							?>
							<?php if ( 1 === $cbuild_at ) : ?>
								<details<?php echo array() === $cbuild_chosen ? '' : ' open'; ?>>
									<summary
										id="<?php echo esc_attr( $cbuild_group['id'] . '-more' ); ?>"
										<?php if ( '' !== $cbuild_group['legend'] ) : ?>
											aria-labelledby="<?php echo esc_attr( $cbuild_group['id'] . '-more ' . $cbuild_group['id'] . '-legend' ); ?>"
										<?php endif; ?>
									><?php esc_html_e( 'Attribute values', 'creative-skateboard-builder' ); ?></summary>
							<?php endif; ?>
							<?php foreach ( $cbuild_fields as $cbuild_field ) : ?>
								<?php
								$cbuild_describes = trim( ( '' === $cbuild_field['note'] ? '' : $cbuild_field['id'] . '-note' ) . ' ' . $cbuild_field['error'] );
								if ( 'hidden' === $cbuild_field['type'] ) :
									?>
									<input type="hidden" name="<?php echo esc_attr( $cbuild_field['name'] ); ?>" value="<?php echo esc_attr( $cbuild_field['value'] ); ?>" />
									<?php
									continue;
								endif;
								if ( 'static' === $cbuild_field['type'] ) :
									?>
									<p class="form-field" id="<?php echo esc_attr( $cbuild_field['id'] ); ?>"><?php echo esc_html( $cbuild_field['value'] ); ?></p>
									<?php
									continue;
								endif;
								?>
								<p class="form-field">
									<?php if ( 'checkbox' !== $cbuild_field['type'] ) : ?>
										<label for="<?php echo esc_attr( $cbuild_field['id'] ); ?>"><?php echo esc_html( $cbuild_field['label'] ); ?></label>
									<?php endif; ?>
									<?php if ( 'textarea' === $cbuild_field['type'] ) : ?>
										<textarea
											class="short"
											rows="2"
											id="<?php echo esc_attr( $cbuild_field['id'] ); ?>"
											name="<?php echo esc_attr( $cbuild_field['name'] ); ?>"
											<?php if ( $cbuild_field['max'] > 0 ) : ?>
												maxlength="<?php echo esc_attr( (string) $cbuild_field['max'] ); ?>"
											<?php endif; ?>
											<?php if ( '' !== $cbuild_describes ) : ?>
												aria-describedby="<?php echo esc_attr( $cbuild_describes ); ?>"
											<?php endif; ?>
											<?php if ( '' !== $cbuild_field['error'] ) : ?>
												aria-invalid="true"
											<?php endif; ?>
										><?php echo esc_textarea( $cbuild_field['value'] ); ?></textarea>
									<?php elseif ( 'checkbox' === $cbuild_field['type'] ) : ?>
										<input
											type="checkbox"
											class="checkbox"
											id="<?php echo esc_attr( $cbuild_field['id'] ); ?>"
											name="<?php echo esc_attr( $cbuild_field['name'] ); ?>"
											value="1"
											<?php checked( '1', $cbuild_field['value'] ); ?>
											<?php if ( '' !== $cbuild_describes ) : ?>
												aria-describedby="<?php echo esc_attr( $cbuild_describes ); ?>"
											<?php endif; ?>
											<?php if ( '' !== $cbuild_field['error'] ) : ?>
												aria-invalid="true"
											<?php endif; ?>
										/>
										<label for="<?php echo esc_attr( $cbuild_field['id'] ); ?>"><?php echo esc_html( $cbuild_field['label'] ); ?></label>
									<?php elseif ( 'text' === $cbuild_field['type'] ) : ?>
										<input
											type="text"
											class="short"
											id="<?php echo esc_attr( $cbuild_field['id'] ); ?>"
											name="<?php echo esc_attr( $cbuild_field['name'] ); ?>"
											value="<?php echo esc_attr( $cbuild_field['value'] ); ?>"
											autocomplete="off"
											<?php if ( $cbuild_field['max'] > 0 ) : ?>
												maxlength="<?php echo esc_attr( (string) $cbuild_field['max'] ); ?>"
											<?php endif; ?>
											<?php if ( '' !== $cbuild_describes ) : ?>
												aria-describedby="<?php echo esc_attr( $cbuild_describes ); ?>"
											<?php endif; ?>
											<?php if ( '' !== $cbuild_field['error'] ) : ?>
												aria-invalid="true"
											<?php endif; ?>
										/>
									<?php else : ?>
										<select
											class="short <?php echo esc_attr( $cbuild_field['class'] ); ?>"
											id="<?php echo esc_attr( $cbuild_field['id'] ); ?>"
											name="<?php echo esc_attr( $cbuild_field['name'] ); ?>"
											<?php if ( $cbuild_field['multiple'] ) : ?>
												multiple="multiple"
											<?php endif; ?>
											<?php foreach ( $cbuild_field['data'] as $cbuild_name => $cbuild_value ) : ?>
												data-<?php echo esc_attr( $cbuild_name ); ?>="<?php echo esc_attr( $cbuild_value ); ?>"
											<?php endforeach; ?>
											<?php if ( '' !== $cbuild_describes ) : ?>
												aria-describedby="<?php echo esc_attr( $cbuild_describes ); ?>"
											<?php endif; ?>
											<?php if ( '' !== $cbuild_field['error'] ) : ?>
												aria-invalid="true"
											<?php endif; ?>
										>
											<?php if ( 'search' === $cbuild_field['type'] ) : ?>
												<?php if ( ! $cbuild_field['multiple'] ) : ?>
													<option value=""></option>
												<?php endif; ?>
												<?php foreach ( $cbuild_field['value'] as $cbuild_value => $cbuild_label ) : ?>
													<option value="<?php echo esc_attr( (string) $cbuild_value ); ?>" selected="selected"><?php echo esc_html( $cbuild_label ); ?></option>
												<?php endforeach; ?>
											<?php else : ?>
												<?php foreach ( $cbuild_field['options'] as $cbuild_value => $cbuild_label ) : ?>
													<option value="<?php echo esc_attr( (string) $cbuild_value ); ?>" <?php selected( $cbuild_field['value'], (string) $cbuild_value ); ?>><?php echo esc_html( $cbuild_label ); ?></option>
												<?php endforeach; ?>
											<?php endif; ?>
										</select>
									<?php endif; ?>
									<?php if ( '' !== $cbuild_field['note'] ) : ?>
										<span class="description" id="<?php echo esc_attr( $cbuild_field['id'] . '-note' ); ?>"><?php echo esc_html( $cbuild_field['note'] ); ?></span>
									<?php endif; ?>
								</p>
							<?php endforeach; ?>
							<?php if ( 1 === $cbuild_at ) : ?>
								</details>
							<?php endif; ?>
						<?php endforeach; ?>
						<?php if ( '' !== $cbuild_group['fold'] ) : ?>
							</details>
						<?php endif; ?>
					</<?php echo esc_html( $cbuild_tag ); ?>>
				<?php endforeach; ?>
			</div>
		</div>
	<?php endforeach; ?>
	<?php if ( array() !== $sections ) : ?>
		<?php // The last field of the panel: a post that arrives without it was cut short, and is not saved. ?>
		<input type="hidden" name="<?php echo esc_attr( $marker ); ?>" value="1" />
	<?php endif; ?>
</div>
