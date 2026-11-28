<?php
/**
 * The markup the admin kit's script finds: the server's form, with its description beside it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin\Form;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A screen prints its form's controls as it always has, between open() and close(). The script reads the description,
 * draws DataForm beside the server's controls and hides them, and writes each value back into them, so the page works
 * the same with no script (M15's principle 1, docs/ADMIN-KIT.md).
 *
 *     <div class="cbuild-form" data-cbuild-form="{id}">
 *       {the refusals' summary, where the server refused anything}
 *       <script type="application/json" class="cbuild-form__description">{description}</script>
 *       <div class="cbuild-form__server">{the server's controls}</div>
 *     </div>
 *
 * @since 1.5.0
 */
final class Mount {

	/**
	 * The mount's start, with the form's description.
	 *
	 * @since 1.5.0
	 *
	 * @param Form $form The form.
	 */
	public static function open( Form $form ): string {
		// JSON_HEX_TAG writes < and > as \u003C and \u003E, so no field's words can end the script early.
		$json = wp_json_encode( $form->toArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES );
		return sprintf(
			'<div class="cbuild-form" data-cbuild-form="%1$s">%3$s<script type="application/json" class="cbuild-form__description">%2$s</script><div class="cbuild-form__server">',
			esc_attr( $form->id ),
			false === $json ? '{}' : $json,
			self::summary( $form )
		);
	}

	/**
	 * The id a screen gives a field's own control, which the summary's link goes to on a page with no script.
	 *
	 * @since 1.5.0
	 *
	 * @param Form   $form  The form.
	 * @param string $field The field's id.
	 */
	public static function controlId( Form $form, string $field ): string {
		return 'cbuild-form-' . $form->id . '-' . $field;
	}

	/**
	 * One notice naming every refusal, a link to each field (M15's research, recommendation 13): inline, so core's
	 * common.js leaves it in the form, and focusable, so the script can move focus to it as the page loads. None where
	 * nothing was refused.
	 *
	 * @param Form $form The form.
	 */
	private static function summary( Form $form ): string {
		$refused = $form->refusals();
		if ( array() === $refused ) {
			return '';
		}
		$items = '';
		foreach ( $refused as $one ) {
			$items .= sprintf(
				'<li><a href="#%1$s" data-cbuild-field="%2$s">%3$s</a></li>',
				esc_attr( self::controlId( $form, $one['id'] ) ),
				esc_attr( $one['id'] ),
				esc_html(
					/* translators: 1: a field's label, 2: why the server refused its value. */
					sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $one['label'], $one['message'] )
				)
			);
		}
		$count = count( $refused );
		$lead  = sprintf(
			/* translators: %d: how many fields were refused. */
			_n( '%d field needs a change before the form can be saved.', '%d fields need a change before the form can be saved.', $count, 'creative-skateboard-builder' ),
			$count
		);
		return wp_get_admin_notice(
			'<p>' . esc_html( $lead ) . '</p><ul>' . $items . '</ul>',
			array(
				'type'               => 'error',
				'additional_classes' => array( 'inline', 'cbuild-form__summary' ),
				'attributes'         => array( 'tabindex' => '-1' ),
				'paragraph_wrap'     => false,
			)
		);
	}
	/**
	 * The mount's end.
	 *
	 * @since 1.5.0
	 */
	public static function close(): string {
		return '</div></div>';
	}
}
