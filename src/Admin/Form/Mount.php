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
		// JSON_HEX_TAG writes < and > as < and >, so no field's words can end the script early.
		$json = wp_json_encode( $form->toArray(), JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_SLASHES );
		return sprintf(
			'<div class="cbuild-form" data-cbuild-form="%1$s"><script type="application/json" class="cbuild-form__description">%2$s</script><div class="cbuild-form__server">',
			esc_attr( $form->id ),
			false === $json ? '{}' : $json
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
