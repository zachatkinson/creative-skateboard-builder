<?php
/**
 * One group of the shop's terms, at WooCommerce > Settings > Builder: a row of WooCommerce's settings table for each
 * field, its label in the heading cell and its note and its problem beside the control.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array<string, mixed>> $rows The fields: `id`, `name`, `label`, `type` (`text`, `select`, `checkbox` or
 *                                       `textarea`), `value`, `note`, `problem`, and where it has them `options`,
 *                                       `max` and `mode`.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

foreach ( $rows as $cbuild_row ) :
	$cbuild_id      = is_string( $cbuild_row['id'] ) ? $cbuild_row['id'] : '';
	$cbuild_name    = is_string( $cbuild_row['name'] ) ? $cbuild_row['name'] : '';
	$cbuild_label   = is_string( $cbuild_row['label'] ) ? $cbuild_row['label'] : '';
	$cbuild_value   = is_string( $cbuild_row['value'] ) ? $cbuild_row['value'] : '';
	$cbuild_note    = is_string( $cbuild_row['note'] ) ? $cbuild_row['note'] : '';
	$cbuild_problem = is_string( $cbuild_row['problem'] ) ? $cbuild_row['problem'] : '';
	$cbuild_max     = is_int( $cbuild_row['max'] ?? null ) ? $cbuild_row['max'] : 0;
	$cbuild_options = is_array( $cbuild_row['options'] ?? null ) ? $cbuild_row['options'] : array();
	$cbuild_said    = trim( ( '' !== $cbuild_note ? $cbuild_id . '-note ' : '' ) . ( '' !== $cbuild_problem ? $cbuild_id . '-problem' : '' ) );
	?>
	<tr>
		<th scope="row" class="titledesc"><label for="<?php echo esc_attr( $cbuild_id ); ?>"><?php echo esc_html( $cbuild_label ); ?></label></th>
		<td class="forminp">
			<?php if ( 'select' === $cbuild_row['type'] ) : ?>
				<select
					id="<?php echo esc_attr( $cbuild_id ); ?>"
					name="<?php echo esc_attr( $cbuild_name ); ?>"
					<?php if ( '' !== $cbuild_said ) : ?>
						aria-describedby="<?php echo esc_attr( $cbuild_said ); ?>"
					<?php endif; ?>
					<?php if ( '' !== $cbuild_problem ) : ?>
						aria-invalid="true"
					<?php endif; ?>
				>
					<?php foreach ( $cbuild_options as $cbuild_key => $cbuild_option ) : ?>
						<option value="<?php echo esc_attr( (string) $cbuild_key ); ?>" <?php selected( $cbuild_value, (string) $cbuild_key ); ?>><?php echo esc_html( is_string( $cbuild_option ) ? $cbuild_option : '' ); ?></option>
					<?php endforeach; ?>
				</select>
			<?php elseif ( 'checkbox' === $cbuild_row['type'] ) : ?>
				<input type="checkbox" id="<?php echo esc_attr( $cbuild_id ); ?>" name="<?php echo esc_attr( $cbuild_name ); ?>" value="1" <?php checked( '' !== $cbuild_value ); ?> />
			<?php elseif ( 'textarea' === $cbuild_row['type'] ) : ?>
				<textarea
					id="<?php echo esc_attr( $cbuild_id ); ?>"
					name="<?php echo esc_attr( $cbuild_name ); ?>"
					class="large-text"
					rows="4"
					maxlength="<?php echo esc_attr( (string) $cbuild_max ); ?>"
					<?php if ( '' !== $cbuild_said ) : ?>
						aria-describedby="<?php echo esc_attr( $cbuild_said ); ?>"
					<?php endif; ?>
					<?php if ( '' !== $cbuild_problem ) : ?>
						aria-invalid="true"
					<?php endif; ?>
				><?php echo esc_textarea( $cbuild_value ); ?></textarea>
			<?php else : ?>
				<input
					type="text"
					id="<?php echo esc_attr( $cbuild_id ); ?>"
					name="<?php echo esc_attr( $cbuild_name ); ?>"
					value="<?php echo esc_attr( $cbuild_value ); ?>"
					class="regular-text"
					autocomplete="off"
					<?php if ( 'numeric' === ( $cbuild_row['mode'] ?? '' ) ) : ?>
						inputmode="numeric"
					<?php endif; ?>
					<?php if ( '' !== $cbuild_said ) : ?>
						aria-describedby="<?php echo esc_attr( $cbuild_said ); ?>"
					<?php endif; ?>
					<?php if ( '' !== $cbuild_problem ) : ?>
						aria-invalid="true"
					<?php endif; ?>
				/>
			<?php endif; ?>
			<?php if ( '' !== $cbuild_note ) : ?>
				<p class="description" id="<?php echo esc_attr( $cbuild_id ); ?>-note"><?php echo esc_html( $cbuild_note ); ?></p>
			<?php endif; ?>
			<?php if ( '' !== $cbuild_problem ) : ?>
				<div class="notice notice-error inline" id="<?php echo esc_attr( $cbuild_id ); ?>-problem"><p><?php echo esc_html( $cbuild_problem ); ?></p></div>
			<?php endif; ?>
		</td>
	</tr>
<?php endforeach; ?>
