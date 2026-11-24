<?php
/**
 * The Part specs panel: a tab of the product data box, or a group of fields in a variation.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{label: string, open: bool, fields: list<array{id: string, name: string, label: string, value: string, note: string, options: array<int|string, string>|null, charts: array<string, array<string, string>>}>}> $groups The groups of fields. None on the tab of a product no kit takes.
 * @var bool $whole Whether this is the tab's panel (true) or a variation's fields (false).
 * @var array{id: string, name: string, value: string, open: bool, note: string, src: string, box: array{int, int}|null, caption: string, choose: string, title: string, types: list<string>}|null $layer The Layer image field (from 0.7.0). None where the panel has no groups.
 * @var array{id: string, name: string, checked: bool, label: string, note: string}|null $offer "Not offered in the builder" (from 1.2.0). None where the panel has no groups and it is not ticked.
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
		<?php if ( array() === $groups ) : ?>
			<?php esc_html_e( 'No kit takes this product, so the builder needs nothing of it. To make it a part, put it in a category or brand that a kit\'s slot takes, or name it in a slot, then save. Its fields appear here.', 'creative-skateboard-builder' ); ?>
		<?php elseif ( $whole ) : ?>
			<?php esc_html_e( 'What the builder needs to know of this part to check that it fits. Fill in the group that matches it and leave the rest. A measurement takes a decimal or a fraction: 8.25 or 8 1/4. On a variable product, each variation takes these values unless it has its own.', 'creative-skateboard-builder' ); ?>
		<?php else : ?>
			<?php esc_html_e( 'A field left blank takes the parent product\'s value.', 'creative-skateboard-builder' ); ?>
		<?php endif; ?>
	</p>
	<?php if ( null !== $offer ) : ?>
		<?php // A box not ticked sends nothing, so it adds no field to a page of variations (issue #23). ?>
		<p class="<?php echo esc_attr( $cbuild_row ); ?> cbuild-not-offered">
			<input
				type="checkbox"
				class="checkbox"
				id="<?php echo esc_attr( $offer['id'] ); ?>"
				name="<?php echo esc_attr( $offer['name'] ); ?>"
				value="1"
				<?php checked( $offer['checked'] ); ?>
				aria-describedby="<?php echo esc_attr( $offer['id'] . '-note' ); ?>"
			/>
			<label for="<?php echo esc_attr( $offer['id'] ); ?>"><?php echo esc_html( $offer['label'] ); ?></label>
			<span class="description" id="<?php echo esc_attr( $offer['id'] . '-note' ); ?>"><?php echo esc_html( $offer['note'] ); ?></span>
		</p>
	<?php endif; ?>
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
	<?php if ( null !== $layer ) : ?>
		<?php // The button opens WordPress's media modal; without the script the field takes an attachment's ID, and the button stays hidden. ?>
		<?php $cbuild_captioned = ( null !== $layer['box'] || '' !== $layer['src'] ) && '' !== $layer['caption']; ?>
		<details class="cbuild-layer"<?php echo $layer['open'] ? ' open' : ''; ?>>
			<summary><?php esc_html_e( 'Picture', 'creative-skateboard-builder' ); ?></summary>
			<p class="<?php echo esc_attr( $cbuild_row ); ?>">
				<label for="<?php echo esc_attr( $layer['id'] ); ?>"><?php esc_html_e( 'Layer image', 'creative-skateboard-builder' ); ?></label>
				<input
					type="text"
					inputmode="numeric"
					class="short cbuild-layer__id"
					id="<?php echo esc_attr( $layer['id'] ); ?>"
					name="<?php echo esc_attr( $layer['name'] ); ?>"
					value="<?php echo esc_attr( $layer['value'] ); ?>"
					autocomplete="off"
					aria-describedby="<?php echo esc_attr( $layer['id'] . '-note' . ( $cbuild_captioned ? ' ' . $layer['id'] . '-caption' : '' ) ); ?>"
					<?php disabled( ! $layer['open'] ); ?>
				/>
				<button type="button" class="button cbuild-layer__choose" data-cbuild-layer="<?php echo esc_attr( $layer['id'] ); ?>" data-cbuild-title="<?php echo esc_attr( $layer['title'] ); ?>" data-cbuild-types="<?php echo esc_attr( implode( ',', $layer['types'] ) ); ?>" hidden><?php echo esc_html( $layer['choose'] ); ?></button>
				<span class="description" id="<?php echo esc_attr( $layer['id'] . '-note' ); ?>"><?php echo esc_html( $layer['note'] ); ?></span>
			</p>
			<?php if ( null !== $layer['box'] || '' !== $layer['src'] ) : ?>
				<figure class="cbuild-layer__preview" id="<?php echo esc_attr( $layer['id'] . '-preview' ); ?>">
					<div class="cbuild-layer__box"<?php echo null === $layer['box'] ? '' : ' style="' . esc_attr( sprintf( 'inline-size:%dpx;block-size:%dpx', $layer['box'][0], $layer['box'][1] ) ) . '"'; ?>>
						<img alt="" src="<?php echo esc_url( $layer['src'] ); ?>"<?php echo '' === $layer['src'] ? ' hidden' : ''; ?>>
					</div>
					<?php if ( $cbuild_captioned ) : ?>
						<figcaption class="description" id="<?php echo esc_attr( $layer['id'] . '-caption' ); ?>"><?php echo esc_html( $layer['caption'] ); ?></figcaption>
					<?php endif; ?>
				</figure>
			<?php endif; ?>
		</details>
	<?php endif; ?>
<?php if ( $whole ) : ?>
</div>
	</div>
</div>
<?php else : ?>
</fieldset>
<?php endif; ?>
