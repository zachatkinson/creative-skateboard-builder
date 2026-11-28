import { test, expect, Page } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * The admin kit (M15, docs/ADMIN-KIT.md), on its fixture page (tests/e2e/mu-plugins/cbuild-e2e-admin-kit.php): the
 * server's form inside the kit's mount, drawn by DataForm 22. Each M15 screen is built on what this holds: an edit
 * posts through the server's own controls, the server's refusal is named on its field until it is edited, a field
 * shows only when its condition holds, and with no script the server's form is the page.
 */

const PAGE = '/wp-admin/tools.php?page=cbuild-e2e-admin-kit';

async function save( page: Page ) {
	await Promise.all( [
		page.waitForURL( /page=cbuild-e2e-admin-kit/ ),
		page.getByRole( 'button', { name: 'Save', exact: true } ).click(),
	] );
}

test.describe( 'The admin kit', () => {
	test( 'DataForm stands for the server form, and an edit saves through it', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, 'DataForm is drawn by the script.' );
		await login( page );
		await page.goto( PAGE );
		const kit = page.locator( '.cbuild-form__app' );
		await expect( kit.getByLabel( 'Assembly time' ) ).toBeVisible();
		await expect( page.locator( '.cbuild-form__server' ) ).toBeHidden();
		// The fixture's own part: WordPress's menu around it is core's markup.
		await axe( page, 'the kit drawn', '.wrap' );

		await kit.getByLabel( 'Assembly time' ).fill( '7' );
		await save( page );
		await expect( page.getByText( 'Saved.' ) ).toBeVisible();
		await expect( kit.getByLabel( 'Assembly time' ) ).toHaveValue( '7' );
	} );

	test( "the server's refusal is named on its field, and goes once the field is edited", async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, 'DataForm is drawn by the script.' );
		await login( page );
		await page.goto( PAGE );
		const kit = page.locator( '.cbuild-form__app' );
		// A rule across two fields that only the server holds, so the server is the one to say no.
		const refusal = 'Offering assembly needs at least one day.';
		await kit.getByLabel( 'Offer assembly' ).check();
		await kit.getByLabel( 'Assembly time' ).fill( '0' );
		await save( page );
		await expect( kit.getByText( refusal ) ).toBeVisible();
		await axe( page, 'a refused field', '.wrap' );
		await kit.getByLabel( 'Assembly time' ).fill( '4' );
		await expect( kit.getByText( refusal ) ).toHaveCount( 0 );
		await save( page );
		await expect( page.getByText( 'Saved.' ) ).toBeVisible();
		await kit.getByLabel( 'Offer assembly' ).uncheck();
		await save( page );
	} );

	test( 'a field shows only while its condition holds', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, 'DataForm is drawn by the script.' );
		await login( page );
		await page.goto( PAGE );
		const kit = page.locator( '.cbuild-form__app' );
		await kit.getByLabel( 'Unit' ).selectOption( 'mm' );
		await expect( kit.getByLabel( 'Note for inches' ) ).toHaveCount( 0 );
		await kit.getByLabel( 'Unit' ).selectOption( 'in' );
		await kit.getByLabel( 'Note for inches' ).fill( 'Imperial trucks' );
		await save( page );
		await expect( kit.getByLabel( 'Note for inches' ) ).toHaveValue(
			'Imperial trucks'
		);
		await kit.getByLabel( 'Unit' ).selectOption( 'mm' );
		await save( page );
	} );

	test( "with no script the server's form is the page, and saves", async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( javaScriptEnabled, 'The page as the server sent it.' );
		await login( page );
		await page.goto( PAGE );
		const server = page.locator( '.cbuild-form__server' );
		await expect( server.getByLabel( 'Assembly time' ) ).toBeVisible();
		await server.getByLabel( 'Assembly time' ).fill( '5' );
		await save( page );
		await expect( page.getByText( 'Saved.' ) ).toBeVisible();
		// axe runs in the page's script, which this project turns off; the drawn form's axe runs above.
		await expect( server.getByLabel( 'Assembly time' ) ).toHaveValue( '5' );
	} );
} );
