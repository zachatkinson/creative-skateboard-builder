<?php
/**
 * The Build box on the order screen (spec: Admin, On orders): the mismatch first, so it is read before anyone prints,
 * then each build by slot as sold, its picture, the refund help and the link to the assembly ticket.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var array{order: int, number: string, customer: string, note: string, problems: list<string>, builds: list<array{id: string, title: string, boards: int, assembly: bool, assembled: string, rows: list<array{slot: string, label: string, name: string, sku: string, quantity: int, specs: list<array{label: string, value: string}>, ticket: list<array{label: string, value: string}>}>, problems: list<string>, board: array{style: string, layers: list<array{slot: string, style: string, src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}>}|null, description: string, steps: list<string>}>, assembles: bool} $view   What the box shows.
 * @var string                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               $print  The ticket's address, empty where there is no build to print.
 * @var string                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                               $refund The refund help, empty where no build is assembled.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

$cbuild_problems = $view['problems'];
foreach ( $view['builds'] as $cbuild_build ) {
	$cbuild_problems = array_merge( $cbuild_problems, $cbuild_build['problems'] );
}
?>
<div class="cbuild-order">
	<?php if ( array() !== $cbuild_problems ) : ?>
		<div class="notice notice-warning inline cbuild-order__mismatch">
			<p><strong><?php esc_html_e( 'The order\'s lines no longer match the build. Check them before you print the assembly ticket.', 'creative-skateboard-builder' ); ?></strong></p>
			<ul>
				<?php foreach ( $cbuild_problems as $cbuild_problem ) : ?>
					<li><?php echo esc_html( $cbuild_problem ); ?></li>
				<?php endforeach; ?>
			</ul>
		</div>
	<?php endif; ?>
	<?php foreach ( $view['builds'] as $cbuild_at => $cbuild_build ) : ?>
		<?php $cbuild_heading = 'cbuild-order-build-' . $cbuild_at; ?>
		<section class="cbuild-order__build" aria-labelledby="<?php echo esc_attr( $cbuild_heading ); ?>">
			<h3 id="<?php echo esc_attr( $cbuild_heading ); ?>"><?php echo esc_html( $cbuild_build['title'] ); ?></h3>
			<p><?php echo esc_html( $cbuild_build['assembled'] ); ?></p>
			<div class="cbuild-order__parts">
				<div class="cbuild-order__scroll" role="region" aria-labelledby="<?php echo esc_attr( $cbuild_heading ); ?>" tabindex="0">
					<table class="widefat striped">
						<thead>
							<tr>
								<th scope="col"><?php esc_html_e( 'Slot', 'creative-skateboard-builder' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Part', 'creative-skateboard-builder' ); ?></th>
								<th scope="col"><?php esc_html_e( 'SKU', 'creative-skateboard-builder' ); ?></th>
								<th scope="col"><?php esc_html_e( 'A board takes', 'creative-skateboard-builder' ); ?></th>
								<th scope="col"><?php esc_html_e( 'Specs as sold', 'creative-skateboard-builder' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ( $cbuild_build['rows'] as $cbuild_row ) : ?>
								<tr>
									<th scope="row"><?php echo esc_html( $cbuild_row['label'] ); ?></th>
									<td><?php echo esc_html( $cbuild_row['name'] ); ?></td>
									<td><?php echo esc_html( $cbuild_row['sku'] ); ?></td>
									<td><?php echo esc_html( number_format_i18n( $cbuild_row['quantity'] ) ); ?></td>
									<td>
										<?php if ( array() === $cbuild_row['specs'] ) : ?>
											<?php /* translators: in the Build box's Specs as sold column, for a part with no spec the build reads. */ ?>
											<?php esc_html_e( 'No specs', 'creative-skateboard-builder' ); ?>
										<?php else : ?>
											<ul class="cbuild-order__specs">
												<?php foreach ( $cbuild_row['specs'] as $cbuild_spec ) : ?>
													<?php /* translators: 1: a spec's name, such as Deck width; 2: its value, such as 8.5″. */ ?>
													<li><?php echo esc_html( sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $cbuild_spec['label'], $cbuild_spec['value'] ) ); ?></li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php
				if ( null !== $cbuild_build['board'] ) {
					$cbuild_board     = $cbuild_build['board'];
					$cbuild_described = $cbuild_build['description'];
					require __DIR__ . '/order-board.php';
				}
				?>
			</div>
		</section>
	<?php endforeach; ?>
	<?php if ( '' !== $refund ) : ?>
		<p class="description"><?php echo esc_html( $refund ); ?></p>
	<?php endif; ?>
	<?php if ( '' !== $print ) : ?>
		<p>
			<a class="button" href="<?php echo esc_url( $print ); ?>" target="_blank" rel="noopener">
				<?php esc_html_e( 'Print assembly ticket', 'creative-skateboard-builder' ); ?>
				<span class="screen-reader-text"><?php esc_html_e( '(opens in a new tab)', 'creative-skateboard-builder' ); ?></span>
			</a>
		</p>
	<?php endif; ?>
</div>
