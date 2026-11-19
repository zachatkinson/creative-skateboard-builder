<?php
/**
 * WooCommerce > Builder, Catalogue check.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<array{label: string, url: string, current: bool}>               $tabs  The page's tabs.
 * @var bool                                                                 $ran   Whether the check ran in this request.
 * @var int                                                                  $count  How many things it found that keep a part out, or offer it by the wrong figure.
 * @var list<array{kit: string, about: string, link: string, says: string}> $rows   Those, as far as the page lists.
 * @var int                                                                  $more   How many of those it does not list.
 * @var int                                                                  $unlaid How many parts the picture draws have no layer image.
 * @var list<array{kit: string, about: string, link: string, says: string}> $bare   Where each is offered, as far as the page lists (D-211).
 * @var int                                                                  $past   How many of those it does not list.
 * @var list<array{kit: string, about: string, link: string, says: string}> $alike  Variations that share a card, as far as the page lists (D-281).
 * @var int                                                                  $beyond How many of those it does not list.
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
	<p><?php esc_html_e( 'Finds what keeps a part out of the builder: sources that disagree about a part, values that cannot be read, and background work that failed. It reads every part and changes nothing. It also lists, apart, the parts the picture draws that have no layer image.', 'creative-skateboard-builder' ); ?></p>
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
	<?php if ( $ran && 0 === $count && 0 === $unlaid && array() === $alike ) : ?>
		<div class="notice notice-success inline" role="status"><p><?php esc_html_e( 'The check found nothing to fix.', 'creative-skateboard-builder' ); ?></p></div>
	<?php elseif ( $ran && 0 === $count ) : ?>
		<div class="notice notice-success inline" role="status"><p><?php esc_html_e( 'Nothing the check found keeps a part out of the builder.', 'creative-skateboard-builder' ); ?></p></div>
	<?php elseif ( $ran ) : ?>
		<div class="notice notice-warning inline" role="status">
			<p>
				<?php
				/* translators: %s: a number of findings. */
				echo esc_html( sprintf( _n( 'The check found %s thing to fix.', 'The check found %s things to fix.', $count, 'creative-skateboard-builder' ), number_format_i18n( $count ) ) );
				?>
			</p>
		</div>
	<?php endif; ?>
	<?php
	$cbuild_fix   = __( 'What to fix', 'creative-skateboard-builder' );
	$cbuild_lists = array(
		array( 'cbuild-found', __( 'What it found', 'creative-skateboard-builder' ), '', $rows, $more, $cbuild_fix ),
		array(
			'cbuild-unlaid',
			__( 'Parts with no layer image', 'creative-skateboard-builder' ),
			/* translators: %s: a number of parts. */
			sprintf( _n( '%s part the picture draws has no layer image. It is still offered, and the picture shows its labelled photo beside the board until one is chosen on its product page.', '%s parts the picture draws have no layer image. Each is still offered, and the picture shows its labelled photo beside the board until one is chosen on its product page.', $unlaid, 'creative-skateboard-builder' ), number_format_i18n( $unlaid ) ),
			$bare,
			$past,
			$cbuild_fix,
		),
		array(
			'cbuild-shared',
			__( 'Variations that share a card', 'creative-skateboard-builder' ),
			__( 'The builder shows a product\'s variations that share a photo and a layer image as one card, and asks for what tells them apart after the pick. Give a variation a photo of its own to give it a card of its own.', 'creative-skateboard-builder' ),
			$alike,
			$beyond,
			__( 'What it says', 'creative-skateboard-builder' ),
		),
	);
	?>
	<?php foreach ( $ran ? $cbuild_lists : array() as list( $cbuild_id, $cbuild_heading, $cbuild_intro, $cbuild_rows, $cbuild_more, $cbuild_column ) ) : ?>
		<?php
		if ( array() === $cbuild_rows ) {
			continue;
		}
		?>
		<h3 id="<?php echo esc_attr( $cbuild_id ); ?>"><?php echo esc_html( $cbuild_heading ); ?></h3>
		<?php if ( '' !== $cbuild_intro ) : ?>
			<p><?php echo esc_html( $cbuild_intro ); ?></p>
		<?php endif; ?>
		<div class="cbuild-scroll" role="region" aria-labelledby="<?php echo esc_attr( $cbuild_id ); ?>" tabindex="0">
			<table class="widefat striped">
				<thead>
					<tr>
						<th scope="col"><?php esc_html_e( 'Where', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php esc_html_e( 'Kit', 'creative-skateboard-builder' ); ?></th>
						<th scope="col"><?php echo esc_html( $cbuild_column ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php foreach ( $cbuild_rows as $cbuild_row ) : ?>
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
		<?php if ( $cbuild_more > 0 ) : ?>
			<p>
				<?php
				/* translators: %s: how many more rows a list has than it shows. */
				echo esc_html( sprintf( _n( 'And %s more.', 'And %s more.', $cbuild_more, 'creative-skateboard-builder' ), number_format_i18n( $cbuild_more ) ) );
				?>
			</p>
		<?php endif; ?>
	<?php endforeach; ?>
	<h2><?php esc_html_e( 'Read every part again', 'creative-skateboard-builder' ); ?></h2>
	<p><?php esc_html_e( 'The builder keeps an index of each part\'s specs and updates a part whenever it is saved. Use this when the index was not rebuilt, or stopped part way. It runs in the background, and parts keep what they held until they are reached.', 'creative-skateboard-builder' ); ?></p>
	<form method="post" action="<?php echo esc_url( $back ); ?>">
		<?php // The page's first form holds the field with the id; an id is not repeated. ?>
		<input type="hidden" name="_wpnonce" value="<?php echo esc_attr( wp_create_nonce( $nonce ) ); ?>" />
		<input type="hidden" name="cbuild_action" value="reindex" />
		<?php submit_button( __( 'Read every part again', 'creative-skateboard-builder' ), 'secondary', 'submit', true, array( 'id' => 'cbuild-reindex' ) ); ?>
	</form>
</div>
