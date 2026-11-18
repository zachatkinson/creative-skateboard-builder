<?php
/**
 * The assembly tickets: a page of their own that prints one board to a page (spec: Pricing, stock, assembly and
 * returns, Assembly). On screen, a heading, a Print button where scripts run, a link back, and the orders it could not
 * print; on paper, the tickets alone.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{id: string, order: array{order: int, number: string, customer: string, note: string, problems: list<string>, builds: list<mixed>, assembles: bool}, build: array{id: string, title: string, boards: int, assembly: bool, assembled: string, rows: list<array{slot: string, label: string, name: string, sku: string, quantity: int, specs: list<array{label: string, value: string}>, ticket: list<array{label: string, value: string}>}>, problems: list<string>, board: array{style: string, layers: list<array{slot: string, style: string, src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}>}|null, description: string, steps: list<string>}, which: string, problems: list<string>}> $pages The tickets, one to a board.
 * @var list<string> $skipped The numbers of the orders with no build to print.
 * @var int          $more    How many more orders were picked than one page prints.
 * @var int          $most    How many orders one page prints.
 * @var string       $back    Where the link back goes.
 * @var string       $handle  The page's style and script handle.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<title><?php esc_html_e( 'Assembly tickets', 'creative-skateboard-builder' ); ?></title>
	<?php wp_print_styles( $handle ); ?>
</head>
<body class="cbuild-tickets">
	<div class="cbuild-tickets__tools">
		<h1><?php esc_html_e( 'Assembly tickets', 'creative-skateboard-builder' ); ?></h1>
		<p>
			<button type="button" class="cbuild-tickets__print" hidden><?php esc_html_e( 'Print the tickets', 'creative-skateboard-builder' ); ?></button>
			<a href="<?php echo esc_url( $back ); ?>"><?php esc_html_e( 'Back to the orders', 'creative-skateboard-builder' ); ?></a>
		</p>
		<?php if ( array() === $pages ) : ?>
			<p role="status"><?php esc_html_e( 'None of these orders has a build to print.', 'creative-skateboard-builder' ); ?></p>
		<?php endif; ?>
		<?php if ( array() !== $skipped ) : ?>
			<p>
				<?php
				/* translators: %l: the order numbers, as a list. */
				echo esc_html( wp_sprintf( _n( 'Order %l has no build to print.', 'Orders %l have no build to print.', count( $skipped ), 'creative-skateboard-builder' ), $skipped ) );
				?>
			</p>
		<?php endif; ?>
		<?php if ( $more > 0 ) : ?>
			<p>
				<?php
				/* translators: 1: how many orders one page prints; 2: how many more were picked. */
				echo esc_html( sprintf( _n( 'One page prints the first %1$s orders. %2$s more was picked: print it from the next page of the list.', 'One page prints the first %1$s orders. %2$s more were picked: print them from the next page of the list.', $more, 'creative-skateboard-builder' ), number_format_i18n( $most ), number_format_i18n( $more ) ) );
				?>
			</p>
		<?php endif; ?>
	</div>
	<main>
		<?php foreach ( $pages as $cbuild_page ) : ?>
			<?php $cbuild_build = $cbuild_page['build']; ?>
			<article class="cbuild-ticket" aria-labelledby="<?php echo esc_attr( $cbuild_page['id'] ); ?>">
				<h2 id="<?php echo esc_attr( $cbuild_page['id'] ); ?>">
					<?php
					/* translators: %s: the order's number. */
					echo esc_html( sprintf( __( 'Order %s', 'creative-skateboard-builder' ), $cbuild_page['order']['number'] ) );
					?>
				</h2>
				<dl class="cbuild-ticket__facts">
					<dt><?php esc_html_e( 'Customer', 'creative-skateboard-builder' ); ?></dt>
					<dd><?php echo esc_html( $cbuild_page['order']['customer'] ); ?></dd>
					<dt><?php esc_html_e( 'Build', 'creative-skateboard-builder' ); ?></dt>
					<dd><?php echo esc_html( $cbuild_build['title'] ); ?></dd>
					<?php if ( '' !== $cbuild_page['which'] ) : ?>
						<dt><?php esc_html_e( 'Board', 'creative-skateboard-builder' ); ?></dt>
						<dd><?php echo esc_html( $cbuild_page['which'] ); ?></dd>
					<?php endif; ?>
					<dt><?php esc_html_e( 'Assembly', 'creative-skateboard-builder' ); ?></dt>
					<dd><?php echo esc_html( $cbuild_build['assembled'] ); ?></dd>
				</dl>
				<?php if ( array() !== $cbuild_page['problems'] ) : ?>
					<div class="cbuild-ticket__warning">
						<p><strong><?php esc_html_e( 'The order\'s lines no longer match this build. Check the order before you start.', 'creative-skateboard-builder' ); ?></strong></p>
						<ul>
							<?php foreach ( $cbuild_page['problems'] as $cbuild_problem ) : ?>
								<li><?php echo esc_html( $cbuild_problem ); ?></li>
							<?php endforeach; ?>
						</ul>
					</div>
				<?php endif; ?>
				<h3><?php esc_html_e( 'Parts', 'creative-skateboard-builder' ); ?></h3>
				<table>
					<thead>
						<tr>
							<th scope="col"><?php esc_html_e( 'Slot', 'creative-skateboard-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Part', 'creative-skateboard-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'SKU', 'creative-skateboard-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'How many', 'creative-skateboard-builder' ); ?></th>
							<th scope="col"><?php esc_html_e( 'Specs', 'creative-skateboard-builder' ); ?></th>
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
									<ul class="cbuild-ticket__specs">
										<?php foreach ( $cbuild_row['ticket'] as $cbuild_spec ) : ?>
											<?php /* translators: 1: a spec's name, such as Deck width; 2: its value, such as 8.5″. */ ?>
											<li><?php echo esc_html( sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $cbuild_spec['label'], $cbuild_spec['value'] ) ); ?></li>
										<?php endforeach; ?>
									</ul>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
				<?php
				if ( null !== $cbuild_build['board'] ) {
					$cbuild_board     = $cbuild_build['board'];
					$cbuild_described = $cbuild_build['description'];
					require __DIR__ . '/order-board.php';
				}
				?>
				<?php if ( '' !== $cbuild_page['order']['note'] ) : ?>
					<h3><?php esc_html_e( 'Notes', 'creative-skateboard-builder' ); ?></h3>
					<p><?php echo wp_kses( nl2br( esc_html( $cbuild_page['order']['note'] ) ), array( 'br' => array() ) ); ?></p>
				<?php endif; ?>
				<?php if ( array() !== $cbuild_build['steps'] ) : ?>
					<h3><?php esc_html_e( 'Steps', 'creative-skateboard-builder' ); ?></h3>
					<ul class="cbuild-ticket__steps">
						<?php foreach ( $cbuild_build['steps'] as $cbuild_step_at => $cbuild_step ) : ?>
							<?php $cbuild_step_id = $cbuild_page['id'] . '-step-' . $cbuild_step_at; ?>
							<li>
								<input type="checkbox" id="<?php echo esc_attr( $cbuild_step_id ); ?>">
								<label for="<?php echo esc_attr( $cbuild_step_id ); ?>"><?php echo esc_html( $cbuild_step ); ?></label>
							</li>
						<?php endforeach; ?>
					</ul>
				<?php endif; ?>
			</article>
		<?php endforeach; ?>
	</main>
	<?php wp_print_scripts( $handle ); ?>
</body>
</html>
