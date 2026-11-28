<?php
/**
 * Plugin Name: Creative Skateboard Builder e2e admin kit
 * Description: Test fixture, mapped as a must-use plugin by .wp-env.json: an admin page (Tools > Admin kit fixture) with one form drawn by the admin kit, saved through its own POST, nonce and server check, as each of M15's screens will be. Never shipped.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

// phpcs:ignoreFile -- a test fixture outside the plugin.

use CreativeBranding\SkateboardBuilder\Admin\Form\Assets;
use CreativeBranding\SkateboardBuilder\Admin\Form\Card;
use CreativeBranding\SkateboardBuilder\Admin\Form\Errors;
use CreativeBranding\SkateboardBuilder\Admin\Form\Field;
use CreativeBranding\SkateboardBuilder\Admin\Form\Form;
use CreativeBranding\SkateboardBuilder\Admin\Form\Mount;

const CBUILD_E2E_KIT = 'cbuild_e2e_admin_kit';

/**
 * The fixture's saved values, and the errors of the last refused save.
 *
 * @return array{lead_days: string, assembly: string, unit: string, note: string, guide: list<array{size: string, from: string, to: string}>}
 */
function cbuild_e2e_kit_values(): array {
	$saved = get_option( CBUILD_E2E_KIT, array() );
	return array_merge( array( 'lead_days' => '3', 'assembly' => '', 'unit' => 'mm', 'note' => '', 'guide' => array( array( 'size' => '8', 'from' => '8', 'to' => '8.25' ) ) ), is_array( $saved ) ? $saved : array() );
}

add_action(
	'admin_menu',
	static function (): void {
		$hook = add_management_page( 'Admin kit fixture', 'Admin kit fixture', 'manage_options', 'cbuild-e2e-admin-kit', 'cbuild_e2e_kit_page' );
		add_action(
			'admin_enqueue_scripts',
			static function ( string $page ) use ( $hook ): void {
				if ( $page === $hook && defined( 'CBUILD_PATH' ) ) {
					Assets::enqueue( 'settings', CBUILD_PATH, CBUILD_URL );
				}
			}
		);
	}
);

add_action(
	'admin_post_cbuild_e2e_admin_kit',
	static function (): void {
		check_admin_referer( CBUILD_E2E_KIT );
		$posted = isset( $_POST['cbuild'] ) && is_array( $_POST['cbuild'] ) ? wp_unslash( $_POST['cbuild'] ) : array();
		$values = array(
			'lead_days' => (string) ( $posted['lead_days'] ?? '' ),
			'assembly'  => isset( $posted['assembly'] ) && '1' === $posted['assembly'] ? '1' : '',
			'unit'      => in_array( $posted['unit'] ?? '', array( 'mm', 'in' ), true ) ? (string) $posted['unit'] : 'mm',
			'note'      => sanitize_text_field( (string) ( $posted['note'] ?? '' ) ),
			'guide'     => array(),
		);
		// The width guide's rows, as either form posts them: a blank row (the no-script form's spare) is left out.
		foreach ( is_array( $posted['guide'] ?? null ) ? $posted['guide'] : array() as $row ) {
			$row = array_map( static fn( $value ): string => sanitize_text_field( (string) $value ), array_intersect_key( (array) $row, array_flip( array( 'size', 'from', 'to' ) ) ) );
			$row = array_merge( array( 'size' => '', 'from' => '', 'to' => '' ), $row );
			if ( '' !== implode( '', $row ) ) {
				$values['guide'][] = $row;
			}
		}
		$errors = array();
		if ( 1 !== preg_match( '/^\d+$/', $values['lead_days'] ) || (int) $values['lead_days'] > 30 ) {
			$errors['lead_days'] = 'Use a whole number from 0 to 30.';
		} elseif ( '1' === $values['assembly'] && '0' === $values['lead_days'] ) {
			// A rule across two fields, which only the server knows: the kit's limits are hints.
			$errors['lead_days'] = 'Offering assembly needs at least one day.';
		}
		foreach ( $values['guide'] as $at => $row ) {
			if ( '' !== $row['from'] && '' !== $row['to'] && (float) $row['from'] > (float) $row['to'] ) {
				$errors['guide'] = sprintf( 'Row %d: From is wider than To.', $at + 1 );
				break;
			}
		}
		if ( count( $values['guide'] ) > 3 ) {
			$errors['guide'] = 'The guide holds 3 rows at most.';
		}
		if ( array() === $errors ) {
			update_option( CBUILD_E2E_KIT, $values, false );
			delete_transient( CBUILD_E2E_KIT . '_refused' );
		} else {
			set_transient( CBUILD_E2E_KIT . '_refused', array( 'errors' => $errors, 'values' => $values ), 60 );
		}
		wp_safe_redirect( admin_url( 'tools.php?page=cbuild-e2e-admin-kit' . ( array() === $errors ? '&saved=1' : '' ) ) );
		exit;
	}
);

/**
 * The page: the server's form, inside the kit's mount.
 */
function cbuild_e2e_kit_page(): void {
	$refused = get_transient( CBUILD_E2E_KIT . '_refused' );
	delete_transient( CBUILD_E2E_KIT . '_refused' );
	$values = is_array( $refused ) ? $refused['values'] : cbuild_e2e_kit_values();
	$errors = Errors::none();
	foreach ( is_array( $refused ) ? $refused['errors'] : array() as $id => $message ) {
		$errors = $errors->with( $id, $message );
	}
	$form = Form::of(
		'cbuild-e2e-kit',
		array(
			Field::of( 'lead_days', 'cbuild[lead_days]', 'Assembly time', 'integer' )->help( 'Business days to assemble a board.' )->rules( true, 0, 30 )->value( $values['lead_days'] ),
			Field::of( 'assembly', 'cbuild[assembly]', 'Offer assembly', 'boolean' )->value( $values['assembly'] ),
			Field::of( 'unit', 'cbuild[unit]', 'Unit', 'text' )->control( 'select' )->choices( array( 'mm' => 'Millimetres', 'in' => 'Inches' ) )->value( $values['unit'] ),
			Field::of( 'note', 'cbuild[note]', 'Note for inches', 'text' )->visibleWhen( 'unit', array( 'in' ) )->value( $values['note'] ),
			Field::rows(
				'guide',
				'cbuild[guide]',
				'Width guide',
				array(
					Field::of( 'size', '[size]', 'Shoe size', 'text' )->rules( true, null, null, 8 ),
					Field::of( 'from', '[from]', 'From', 'number' ),
					Field::of( 'to', '[to]', 'To', 'number' ),
				),
				3
			)->help( 'Which board widths suit each shoe size.' )
				->words( 'Add a row', 'Edit the row', 'No rows yet. Add the first shoe size.', 'The guide holds 3 rows at most.' )
				->ordered()
				->listed( $values['guide'] ),
		),
		array( Card::of( 'builds', 'Builds', array( 'lead_days', 'assembly', 'unit', 'note' ) ), Card::of( 'sizes', 'Sizes', array( 'guide' ) ) )
	)->failed( $errors );
	echo '<div class="wrap"><h1>Admin kit fixture</h1>';
	if ( isset( $_GET['saved'] ) ) {
		echo '<div class="notice notice-success inline"><p>Saved.</p></div>';
	}
	// The refusals are summed up by Mount::open(), with a link to each field.
	echo '<form method="post" action="' . esc_url( admin_url( 'admin-post.php' ) ) . '">';
	wp_nonce_field( CBUILD_E2E_KIT );
	echo '<input type="hidden" name="action" value="cbuild_e2e_admin_kit">';
	echo Mount::open( $form ); // The form's description, escaped by Mount.
	printf( '<p><label>Assembly time <input type="number" id="%s" name="cbuild[lead_days]" value="%s" required min="0" max="30"></label></p>', esc_attr( Mount::controlId( $form, 'lead_days' ) ), esc_attr( $values['lead_days'] ) );
	printf( '<p><input type="hidden" name="cbuild[assembly]" value="0"><label><input type="checkbox" id="%s" name="cbuild[assembly]" value="1"%s> Offer assembly</label></p>', esc_attr( Mount::controlId( $form, 'assembly' ) ), checked( '1', $values['assembly'], false ) );
	printf( '<p><label>Unit <select name="cbuild[unit]"><option value="mm"%s>Millimetres</option><option value="in"%s>Inches</option></select></label></p>', selected( 'mm', $values['unit'], false ), selected( 'in', $values['unit'], false ) );
	printf( '<p><label>Note for inches <input type="text" name="cbuild[note]" value="%s"></label></p>', esc_attr( $values['note'] ) );
	// The width guide with no script: its rows, and a spare for one more while it holds fewer than 3.
	$rows = $values['guide'];
	if ( count( $rows ) < 3 ) {
		$rows[] = array( 'size' => '', 'from' => '', 'to' => '' );
	}
	echo '<fieldset id="' . esc_attr( Mount::controlId( $form, 'guide' ) ) . '" tabindex="-1"><legend>Width guide</legend><table><thead><tr><th>Shoe size</th><th>From</th><th>To</th></tr></thead><tbody>';
	foreach ( $rows as $at => $row ) {
		echo '<tr>';
		foreach ( array( 'size' => 'Shoe size', 'from' => 'From', 'to' => 'To' ) as $key => $label ) {
			printf( '<td><input type="text" name="cbuild[guide][%1$d][%2$s]" value="%3$s" aria-label="%4$s, row %5$d"></td>', (int) $at, esc_attr( $key ), esc_attr( $row[ $key ] ), esc_attr( $label ), (int) $at + 1 );
		}
		echo '</tr>';
	}
	echo '</tbody></table></fieldset>';
	echo Mount::close();
	submit_button( 'Save' );
	echo '</form></div>';
}
