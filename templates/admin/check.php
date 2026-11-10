<?php
/**
 * WooCommerce > Builder, Catalogue check.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{label: string, url: string, current: bool}>               $tabs  The page's tabs.
 * @var bool                                                                 $ran   Whether the check ran in this request.
 * @var int                                                                  $count How many things it found.
 * @var list<array{kit: string, about: string, link: string, says: string}> $rows  What it found, as far as the page lists.
 * @var int                                                                  $more  How many it does not list.
 * @var bool                                                                 $none  Whether the shop has no kit yet.
 * @var string                                                               $nonce The form's nonce action.
 * @var string                                                               $back  The tab's own address.
 * @var bool                                                                 $asked Whether a reindex was asked for in this request.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<div class="wrap">
	<h1><?php esc_html_e( 'Builder', 'creative-skateboard-builder' ); ?></h1>
	<?php require __DIR__ . '/builder-tabs.php'; ?>
	<h2><?php esc_html_e( 'Catalogue check', 'creative-skateboard-builder' ); ?></h2>
	<p><?php esc_html_e( 'Finds what keeps a part out of the builder: sources that disagree about a part, values that cannot be read, and background work that failed. It reads every part and changes nothing.', 'creative-skateboard-builder' ); ?></p>
	<?php if ( $none ) : ?>
		<p><?php esc_html_e( 'The shop has no kit yet. Once it has one, the check also lists each kit\'s parts that lack a spec, presets that no longer fit and slots with nothing in stock.', 'creative-skateboard-builder' ); ?></p>
	<?php endif; ?>
	<form method="post" action="<?php echo esc_url( $back ); ?>">
		<?php wp_nonce_field( $nonce ); ?>
		<input type="hidden" name="cbuild_action" value="check" />
		<?php submit_button( $ran ? __( 'Run the check again', 'creative-skateboard-builder' ) : __( 'Run the check', 'creative-skateboard-builder' ), 'primary', 'submit', true ); ?>
	</form>

	<?php if ( $asked ) : ?>
		<div class="notice notice-success inline" role="status"><p><?php esc_html_e( 'Every part is being read again in the background, 100 at a time. Tools > Site Health shows how far it is.', 'creative-skateboard-builder' ); ?></p></div>
	<?php endif; ?>
	<?php if ( $ran && 0 === $count ) : ?>
		<div class="notice notice-success inline" role="status"><p><?php esc_html_e( 'The check found nothing to fix.', 'creative-skateboard-builder' ); ?></p></div>
	<?php elseif ( $ran ) : ?>
		<div class="notice notice-warning inline" role="status">
			<p>
				<?php
				/* translators: %s: a number of findings. */
				echo esc_html( sprintf( _n( 'The check found %s thing to fix.', 'The check found %s things to fix.', $count, 'creative-skateboard-builder' ), number_format_i18n( $count ) ) );
				?>
			</p>
		</div>
		<h3 id="cbuild-found"><?php esc_html_e( 'What it found', 'creative-skateboard-builder' ); ?></h3>
		<div class="cbuild-scroll" role="region" aria-labelledby="cbuild-found" tabindex="0">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Where', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Kit', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'What to fix', 'creative-skateboard-builder' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $rows as $cbuild_row ) : ?>
						<tr>
							<td>
								<?php if ( '' !== $cbuild_row['link'] ) : ?>
									<a href="<?php echo esc_url( $cbuild_row['link'] ); ?>"><?php echo esc_html( $cbuild_row['about'] ); ?></a>
								<?php else : ?>
									<?php echo esc_html( $cbuild_row['about'] ); ?>
								<?php endif; ?>
							</td>
							<td><?php echo esc_html( $cbuild_row['kit'] ); ?></td>
							<td><?php echo esc_html( $cbuild_row['says'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
		<?php if ( $more > 0 ) : ?>
			<p>
				<?php
				/* translators: %s: how many more rows a list has than it shows. */
				echo esc_html( sprintf( _n( 'And %s more.', 'And %s more.', $more, 'creative-skateboard-builder' ), number_format_i18n( $more ) ) );
				?>
			</p>
		<?php endif; ?>
	<?php endif; ?>
	<h2><?php esc_html_e( 'Read every part again', 'creative-skateboard-builder' ); ?></h2>
	<p><?php esc_html_e( 'The builder keeps an index of each part\'s specs and updates a part whenever it is saved. Use this when the index was not rebuilt, or stopped part way. It runs in the background, and parts keep what they held until they are reached.', 'creative-skateboard-builder' ); ?></p>
	<form method="post" action="<?php echo esc_url( $back ); ?>">
		<?php // The page's first form holds the field with the id; an id is not repeated. ?>
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( $nonce ) ); ?>" />
		<input type="hidden" name="cbuild_action" value="reindex" />
		<?php submit_button( __( 'Read every part again', 'creative-skateboard-builder' ), 'secondary', 'submit', true, array( 'id' => 'cbuild-reindex' ) ); ?>
	</form>
</div>
