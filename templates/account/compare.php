<?php
/**
 * My builds' compare (#136, D-411): up to three saved builds side by side, a column a build and a row a slot, at
 * today's prices. A real table, its columns and rows headed, in a region that scrolls sideways on a phone and takes
 * the keyboard's focus to scroll. The rows whose parts differ say so in their header, and the gap between the totals
 * is said in words; the colour beside them is never the only sign.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var array{builds: list<array{uuid: string, name: string, open: string|null}>, rows: list<array{label: string, differs: bool, cells: list<array{name: string, price: string, notes: list<string>}>}>, totals: list<array{total: string, notes: list<string>}>, gaps: list<array{text: string, lowest: bool}>, more: bool} $table The builds compared.
 * @var string $back My builds' address.
 */

defined( 'ABSPATH' ) || exit;
?>
<p class="cbuild-compare__back"><a href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Back to My builds', 'creative-skateboard-builder' ); ?></a></p>
<?php if ( $table['more'] ) : ?>
	<p class="cbuild-compare__said"><?php esc_html_e( 'Up to three builds are compared at once. These are the first three you chose.', 'creative-skateboard-builder' ); ?></p>
<?php endif; ?>
<div class="cbuild-compare" role="region" aria-labelledby="cbuild-compare-caption" tabindex="0">
	<table class="cbuild-compare__table shop_table">
		<caption id="cbuild-compare-caption"><?php esc_html_e( 'Your builds side by side, at today\'s prices', 'creative-skateboard-builder' ); ?></caption>
		<thead>
			<tr>
				<th scope="col"><?php esc_html_e( 'Part', 'creative-skateboard-builder' ); ?></th>
				<?php foreach ( $table['builds'] as $cbuild_build ) : ?>
					<th scope="col" class="cbuild-compare__build">
						<span class="cbuild-compare__name"><?php echo esc_html( $cbuild_build['name'] ); ?></span>
						<?php if ( null !== $cbuild_build['open'] ) : ?>
							<a class="cbuild-compare__open" rel="nofollow" href="<?php echo esc_url( $cbuild_build['open'] ); ?>" aria-label="<?php /* translators: %s: the saved build's name. */ echo esc_attr( sprintf( __( 'Open %s', 'creative-skateboard-builder' ), $cbuild_build['name'] ) ); ?>"><?php esc_html_e( 'Open', 'creative-skateboard-builder' ); ?></a>
						<?php endif; ?>
					</th>
				<?php endforeach; ?>
			</tr>
		</thead>
		<tbody>
			<?php foreach ( $table['rows'] as $cbuild_row ) : ?>
				<tr>
					<th scope="row">
						<?php echo esc_html( $cbuild_row['label'] ); ?>
						<?php if ( $cbuild_row['differs'] ) : ?>
							<span class="cbuild-compare__mark"><?php esc_html_e( 'Differs', 'creative-skateboard-builder' ); ?></span>
						<?php endif; ?>
					</th>
					<?php foreach ( $cbuild_row['cells'] as $cbuild_cell ) : ?>
						<td<?php echo $cbuild_row['differs'] ? ' class="cbuild-compare__differs"' : ''; ?>>
							<?php if ( '' !== $cbuild_cell['name'] ) : ?>
								<span class="cbuild-compare__part"><?php echo esc_html( $cbuild_cell['name'] ); ?></span>
							<?php elseif ( array() === $cbuild_cell['notes'] ) : ?>
								<span class="cbuild-compare__part"><?php esc_html_e( 'None', 'creative-skateboard-builder' ); ?></span>
							<?php endif; ?>
							<?php if ( '' !== $cbuild_cell['price'] ) : ?>
								<span class="cbuild-compare__price"><?php echo esc_html( $cbuild_cell['price'] ); ?></span>
							<?php endif; ?>
							<?php foreach ( $cbuild_cell['notes'] as $cbuild_note ) : ?>
								<span class="cbuild-compare__note"><?php echo esc_html( $cbuild_note ); ?></span>
							<?php endforeach; ?>
						</td>
					<?php endforeach; ?>
				</tr>
			<?php endforeach; ?>
			<tr class="cbuild-compare__totals">
				<th scope="row"><?php esc_html_e( 'Total today', 'creative-skateboard-builder' ); ?></th>
				<?php foreach ( $table['totals'] as $cbuild_total ) : ?>
					<td>
						<?php if ( '' !== $cbuild_total['total'] ) : ?>
							<strong class="cbuild-compare__price"><?php echo esc_html( $cbuild_total['total'] ); ?></strong>
						<?php endif; ?>
						<?php foreach ( $cbuild_total['notes'] as $cbuild_note ) : ?>
							<span class="cbuild-compare__note"><?php echo esc_html( $cbuild_note ); ?></span>
						<?php endforeach; ?>
					</td>
				<?php endforeach; ?>
			</tr>
			<tr class="cbuild-compare__gaps">
				<th scope="row"><?php esc_html_e( 'Price difference', 'creative-skateboard-builder' ); ?></th>
				<?php foreach ( $table['gaps'] as $cbuild_gap ) : ?>
					<td<?php echo $cbuild_gap['lowest'] ? ' class="cbuild-compare__lowest"' : ''; ?>><?php echo esc_html( $cbuild_gap['text'] ); ?></td>
				<?php endforeach; ?>
			</tr>
		</tbody>
	</table>
</div>
