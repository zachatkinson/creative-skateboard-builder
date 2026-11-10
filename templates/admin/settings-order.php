<?php
/**
 * Which source wins, at WooCommerce > Settings > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 *
 * @var list<string>          $order   The sources' names, first wins, as saved or as sent.
 * @var string                $problem What is wrong with the order, '' when nothing is.
 * @var array<string, string> $sources Source => label.
 * @var list<string>          $places  The places' names: first, second, third.
 */

declare( strict_types=1 );

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?>
<tr>
	<th scope="row" class="titledesc"><?php esc_html_e( 'Which source wins', 'creative-skateboard-builder' ); ?></th>
	<td class="forminp">
		<fieldset>
			<legend class="screen-reader-text"><?php esc_html_e( 'Which source wins', 'creative-skateboard-builder' ); ?></legend>
			<p class="description" id="cbuild-order-help">
				<?php esc_html_e( 'A part\'s spec can come from three places. Where two say different things, the one placed first here is used, and the Catalogue check lists the part.', 'creative-skateboard-builder' ); ?>
			</p>
			<?php if ( '' !== $problem ) : ?>
				<div class="notice notice-error inline" id="cbuild-order-problem"><p><?php echo esc_html( $problem ); ?></p></div>
			<?php endif; ?>
			<?php foreach ( $places as $cbuild_place => $cbuild_name ) : ?>
				<p>
					<label for="cbuild-order-<?php echo esc_attr( (string) $cbuild_place ); ?>"><?php echo esc_html( $cbuild_name ); ?></label><br />
					<select
						id="cbuild-order-<?php echo esc_attr( (string) $cbuild_place ); ?>"
						name="cbuild_order[<?php echo esc_attr( (string) $cbuild_place ); ?>]"
						aria-describedby="<?php echo esc_attr( '' !== $problem ? 'cbuild-order-problem' : 'cbuild-order-help' ); ?>"
						<?php if ( '' !== $problem ) : ?>
							aria-invalid="true"
						<?php endif; ?>
					>
						<?php foreach ( $sources as $cbuild_key => $cbuild_label ) : ?>
							<option value="<?php echo esc_attr( $cbuild_key ); ?>" <?php selected( $order[ $cbuild_place ] ?? '', $cbuild_key ); ?>><?php echo esc_html( $cbuild_label ); ?></option>
						<?php endforeach; ?>
					</select>
				</p>
			<?php endforeach; ?>
		</fieldset>
	</td>
</tr>
