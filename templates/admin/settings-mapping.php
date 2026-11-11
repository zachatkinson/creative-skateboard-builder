<?php
/**
 * The attribute mapping, at WooCommerce > Settings > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{attribute: string, spec: string, unit: string}> $lines      The lines, the blank ones last.
 * @var array<int, list<string>>                                   $problems   What is wrong, by line from 1; 0 is the whole mapping.
 * @var array<int, true>                                           $units_at   The lines whose problem is their unit.
 * @var array<string, string>                                      $attributes The shop's attributes: taxonomy => label.
 * @var array<string, string>                                      $specs      Spec key => label.
 * @var array<string, string>                                      $units      Unit => label.
 * @var int                                                        $max        The longest attribute name.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tr>
	<th scope="row" class="titledesc"><?php esc_html_e( 'Attribute mapping', 'creative-skateboard-builder' ); ?></th>
	<td class="forminp">
		<p class="description" id="cbuild-mapping-help">
			<?php esc_html_e( 'Read a spec from a product attribute your shop already fills in: the deck\'s width from the Width attribute, for one. Choose one of the shop\'s attributes from the list, which names each as WooCommerce stores it (pa_width), or type the name of an attribute a product has of its own. A variation with no value of its own takes its parent\'s. The unit is what a value with no unit is in. To remove a line, clear its attribute and its spec. Save to add more lines.', 'creative-skateboard-builder' ); ?>
		</p>
		<?php if ( isset( $problems[0] ) ) : ?>
			<div class="notice notice-error inline" id="cbuild-mapping-problem">
				<?php foreach ( $problems[0] as $cbuild_problem ) : ?>
					<p><?php echo esc_html( $cbuild_problem ); ?></p>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
		<datalist id="cbuild-attributes">
			<?php foreach ( $attributes as $cbuild_name => $cbuild_label ) : ?>
				<option value="<?php echo esc_attr( $cbuild_name ); ?>" label="<?php echo esc_attr( $cbuild_label ); ?>"></option>
			<?php endforeach; ?>
		</datalist>
		<?php foreach ( $lines as $cbuild_index => $cbuild_line ) : ?>
			<?php
			$cbuild_number = $cbuild_index + 1;
			$cbuild_wrong  = $problems[ $cbuild_number ] ?? array();
			$cbuild_note   = 'cbuild-mapping-problem-' . $cbuild_number;
			$cbuild_id     = 'cbuild-mapping-' . $cbuild_number;
			?>
			<fieldset class="cbuild-mapping-line">
				<legend>
					<?php
					/* translators: %d: the line's number in the attribute mapping. */
					echo esc_html( sprintf( __( 'Line %d', 'creative-skateboard-builder' ), $cbuild_number ) );
					?>
				</legend>
				<?php if ( array() !== $cbuild_wrong ) : ?>
					<div class="notice notice-error inline" id="<?php echo esc_attr( $cbuild_note ); ?>">
						<?php foreach ( $cbuild_wrong as $cbuild_problem ) : ?>
							<p><?php echo esc_html( $cbuild_problem ); ?></p>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
				<p>
					<label for="<?php echo esc_attr( $cbuild_id . '-attribute' ); ?>"><?php esc_html_e( 'Attribute', 'creative-skateboard-builder' ); ?></label><br />
					<input
						type="text"
						id="<?php echo esc_attr( $cbuild_id . '-attribute' ); ?>"
						name="cbuild_mapping[<?php echo esc_attr( (string) $cbuild_index ); ?>][attribute]"
						value="<?php echo esc_attr( $cbuild_line['attribute'] ); ?>"
						list="cbuild-attributes"
						maxlength="<?php echo esc_attr( (string) $max ); ?>"
						autocomplete="off"
						<?php if ( array() !== $cbuild_wrong ) : ?>
							aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
						<?php endif; ?>
					/>
				</p>
				<p>
					<label for="<?php echo esc_attr( $cbuild_id . '-spec' ); ?>"><?php esc_html_e( 'Spec', 'creative-skateboard-builder' ); ?></label><br />
					<select
						id="<?php echo esc_attr( $cbuild_id . '-spec' ); ?>"
						name="cbuild_mapping[<?php echo esc_attr( (string) $cbuild_index ); ?>][spec]"
						<?php if ( array() !== $cbuild_wrong ) : ?>
							aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
						<?php endif; ?>
					>
						<option value=""><?php esc_html_e( 'No spec', 'creative-skateboard-builder' ); ?></option>
						<?php foreach ( $specs as $cbuild_key => $cbuild_label ) : ?>
							<option value="<?php echo esc_attr( $cbuild_key ); ?>" <?php selected( $cbuild_line['spec'], $cbuild_key ); ?>><?php echo esc_html( $cbuild_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
				<p>
					<label for="<?php echo esc_attr( $cbuild_id . '-unit' ); ?>"><?php esc_html_e( 'Unit', 'creative-skateboard-builder' ); ?></label><br />
					<select
						id="<?php echo esc_attr( $cbuild_id . '-unit' ); ?>"
						name="cbuild_mapping[<?php echo esc_attr( (string) $cbuild_index ); ?>][unit]"
						<?php if ( isset( $units_at[ $cbuild_number ] ) ) : ?>
							aria-invalid="true" aria-describedby="<?php echo esc_attr( $cbuild_note ); ?>"
						<?php endif; ?>
					>
						<option value=""><?php esc_html_e( 'The spec\'s own', 'creative-skateboard-builder' ); ?></option>
						<?php foreach ( $units as $cbuild_key => $cbuild_label ) : ?>
							<option value="<?php echo esc_attr( $cbuild_key ); ?>" <?php selected( $cbuild_line['unit'], $cbuild_key ); ?>><?php echo esc_html( $cbuild_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			</fieldset>
		<?php endforeach; ?>
	</td>
</tr>
