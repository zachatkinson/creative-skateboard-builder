import { test, expect, Page, answered } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * WooCommerce > Settings > Builder (milestone 3): the attribute mapping and which source wins. Saved by keyboard,
 * refused whole when a line is wrong, with the problem beside its field, and axe-clean in each state.
 */

const TAB = '/wp-admin/admin.php?page=wc-settings&tab=cbuild';

// WooCommerce's own tab bar and save bar wrap the form; the plugin's fields are the table inside it.
const FIELDS = '#mainform .form-table';

// A control of one line of the mapping: the line is a named group, and each control has its own label.
function field( page: Page, line: number, label: string ) {
	return page
		.getByRole( 'group', { name: `Line ${ line }`, exact: true } )
		.getByLabel( label );
}

async function save( page: Page ) {
	await answered( page, () =>
		page.getByRole( 'button', { name: 'Save changes' } ).click()
	);
}

// Leaves the mapping empty and the order as a new shop has it, whatever an earlier run left.
async function reset( page: Page ) {
	await page.goto( TAB );
	const attributes = page.locator( 'input[name^="cbuild_mapping"]' );
	const lines = await attributes.count();
	for ( let line = 0; line < lines; line++ ) {
		await attributes.nth( line ).fill( '' );
		await page
			.locator( `select[name="cbuild_mapping[${ line }][spec]"]` )
			.selectOption( '' );
	}
	await page.getByLabel( 'First' ).selectOption( 'table' );
	await page.getByLabel( 'Second' ).selectOption( 'field' );
	await page.getByLabel( 'Third' ).selectOption( 'attribute' );
	// WooCommerce enables its save button on a change; one field is touched so it always is.
	await attributes.first().pressSequentially( 'x' );
	await attributes.first().fill( '' );
	await save( page );
}

test.describe( 'WooCommerce > Settings > Builder', () => {
	test.skip(
		( { javaScriptEnabled } ) => ! javaScriptEnabled,
		"WooCommerce's settings screen keeps its save button disabled until a script sees a change."
	);

	test( 'the mapping and the order are saved by keyboard, and a wrong line saves nothing', async ( {
		page,
	} ) => {
		await login( page );
		await reset( page );

		await page.goto( TAB );
		await expect(
			page.getByRole( 'heading', { name: 'Part specs' } )
		).toBeVisible();
		await axe( page, 'the Builder tab, empty', FIELDS );

		// By keyboard alone: the attribute, then Tab to its spec and its unit.
		const attribute = field( page, 1, 'Attribute' );
		await attribute.focus();
		await page.keyboard.type( 'pa_width' );
		await page.keyboard.press( 'Tab' );
		await expect( field( page, 1, 'Spec' ) ).toBeFocused();
		await field( page, 1, 'Spec' ).selectOption( 'deck_width' );
		await page.keyboard.press( 'Tab' );
		await expect( field( page, 1, 'Unit' ) ).toBeFocused();
		await field( page, 1, 'Unit' ).selectOption( 'in' );
		await page.keyboard.press( 'Tab' );
		await expect( field( page, 2, 'Attribute' ) ).toBeFocused();

		await page.getByLabel( 'First' ).selectOption( 'field' );
		await page.getByLabel( 'Second' ).selectOption( 'table' );
		await page.getByLabel( 'Third' ).selectOption( 'attribute' );
		await save( page );
		await expect(
			page.getByText( 'Your settings have been saved.' )
		).toBeVisible();

		// Saved, and three blank lines follow the one kept.
		await page.goto( TAB );
		await expect( field( page, 1, 'Attribute' ) ).toHaveValue( 'pa_width' );
		await expect( field( page, 1, 'Spec' ) ).toHaveValue( 'deck_width' );
		await expect( field( page, 1, 'Unit' ) ).toHaveValue( 'in' );
		await expect( page.getByLabel( 'First' ) ).toHaveValue( 'field' );
		await expect(
			page.locator( 'input[name^="cbuild_mapping"]' )
		).toHaveCount( 4 );
		await axe( page, 'the Builder tab, with a line', FIELDS );

		// A line with no spec, and a source placed twice: nothing is saved, and each problem is beside its field.
		await field( page, 2, 'Attribute' ).fill( 'pa_length' );
		await page.getByLabel( 'Second' ).selectOption( 'field' );
		await save( page );
		await expect(
			page.getByText( 'The settings were not saved.' )
		).toBeVisible();
		const wrong = field( page, 2, 'Attribute' );
		await expect( wrong ).toHaveValue( 'pa_length' );
		await expect( wrong ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( wrong ).toHaveAccessibleDescription(
			'Choose the spec this attribute feeds, or clear the line.'
		);
		await expect( page.getByLabel( 'Second' ) ).toHaveAccessibleDescription(
			'Put each source in one place: first, second and third.'
		);
		await expect( field( page, 1, 'Attribute' ) ).not.toHaveAttribute(
			'aria-invalid',
			'true'
		);
		await axe( page, 'the Builder tab, refused', FIELDS );

		await page.goto( TAB );
		await expect( field( page, 2, 'Attribute' ) ).toHaveValue( '' );
		await expect( page.getByLabel( 'Second' ) ).toHaveValue( 'table' );

		await reset( page );
		await page.goto( TAB );
		await expect( field( page, 1, 'Attribute' ) ).toHaveValue( '' );
	} );
} );
