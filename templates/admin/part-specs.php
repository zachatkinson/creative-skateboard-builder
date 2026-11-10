<?php
/**
 * The Part specs panel: a tab of the product data box, or a group of fields in a variation.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{label: string, open: bool, fields: list<array{id: string, name: string, label: string, value: string, note: string, options: array<int|string, string>|null, charts: array<string, array<string, string>>}>}> $groups The groups of fields.
 * @var bool $whole Whether this is the tab's panel (true) or a variation's fields (false).
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cbuild_row = $whole ? 'form-field' : 'form-row form-row-full';
?>
<?php if ( $whole ) : ?>
<div id="cbuild_part_specs" class="panel woocommerce_options_panel hidden">
	<div class="options_group">
<?php endif; ?>
<?php if ( $whole ) : ?>
<div class="cbuild-part-specs">
<?php else : ?>
<fieldset class="cbuild-part-specs">
	<legend><strong><?php esc_html_e( 'Part specs', 'creative-skateboard-builder' ); ?></strong></legend>
<?php endif; ?>
	<p class="<?php echo esc_attr( $cbuild_row ); ?>">
		<?php if ( $whole ) : ?>
			<?php esc_html_e( 'What the builder needs to know of this part to check that it fits. Fill in the group that matches it and leave the rest. A measurement takes a decimal or a fraction: 8.25 or 8 1/4. On a variable product, each variation takes these values unless it has its own.', 'creative-skateboard-builder' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'A field left blank takes the parent product\'s value.', 'creative-skateboard-builder' ); ?>
		<?php endif; ?>
	</p>
	<?php // A closed group holds nothing, so its fields are disabled and add nothing to the product's form; the screen's script enables them when the group opens (issue #23). ?>
	<?php foreach ( $groups as $cbuild_group ) : ?>
		<details<?php echo $cbuild_group['open'] ? ' open' : ''; ?>>
			<summary><?php echo esc_html( $cbuild_group['label'] ); ?></summary>
			<?php foreach ( $cbuild_group['fields'] as $cbuild_field ) : ?>
				<p class="<?php echo esc_attr( $cbuild_row ); ?>">
					<label for="<?php echo esc_attr( $cbuild_field['id'] ); ?>"><?php echo esc_html( $cbuild_field['label'] ); ?></label>
					<?php if ( null === $cbuild_field['options'] ) : ?>
						<input
							type="text"
							class="short"
							id="<?php echo esc_attr( $cbuild_field['id'] ); ?>"
							name="<?php echo esc_attr( $cbuild_field['name'] ); ?>"
							value="<?php echo esc_attr( $cbuild_field['value'] ); ?>"
							autocomplete="off"
							<?php disabled( ! $cbuild_group['open'] ); ?>
							<?php if ( '' !== $cbuild_field['note'] ) : ?>
								aria-describedby="<?php echo esc_attr( $cbuild_field['id'] . '-note' ); ?>"
							<?php endif; ?>
						/>
					<?php else : ?>
						<select
							class="short"
							id="<?php echo esc_attr( $cbuild_field['id'] ); ?>"
							name="<?php echo esc_attr( $cbuild_field['name'] ); ?>"
							<?php disabled( ! $cbuild_group['open'] ); ?>
							<?php if ( '' !== $cbuild_field['note'] ) : ?>
								aria-describedby="<?php echo esc_attr( $cbuild_field['id'] . '-note' ); ?>"
							<?php endif; ?>
						>
							<?php if ( ! isset( $cbuild_field['options'][''] ) ) : ?>
								<option value=""><?php esc_html_e( 'Not set', 'creative-skateboard-builder' ); ?></option>
							<?php endif; ?>
							<?php foreach ( $cbuild_field['options'] as $cbuild_value => $cbuild_label ) : ?>
								<option value="<?php echo esc_attr( (string) $cbuild_value ); ?>" <?php selected( $cbuild_field['value'], (string) $cbuild_value ); ?>><?php echo esc_html( $cbuild_label ); ?></option>
							<?php endforeach; ?>
							<?php foreach ( $cbuild_field['charts'] as $cbuild_chart => $cbuild_sizes ) : ?>
								<optgroup label="<?php echo esc_attr( $cbuild_chart ); ?>">
									<?php foreach ( $cbuild_sizes as $cbuild_value => $cbuild_label ) : ?>
										<option value="<?php echo esc_attr( (string) $cbuild_value ); ?>" <?php selected( $cbuild_field['value'], (string) $cbuild_value ); ?>><?php echo esc_html( $cbuild_label ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
					<?php endif; ?>
					<?php if ( '' !== $cbuild_field['note'] ) : ?>
						<span class="description" id="<?php echo esc_attr( $cbuild_field['id'] . '-note' ); ?>"><?php echo esc_html( $cbuild_field['note'] ); ?></span>
					<?php endif; ?>
				</p>
			<?php endforeach; ?>
		</details>
	<?php endforeach; ?>
<?php if ( $whole ) : ?>
</div>
	</div>
</div>
<?php else : ?>
</fieldset>
<?php endif; ?>
