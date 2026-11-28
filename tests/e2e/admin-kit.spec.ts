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

	// The browser's own prompt, only after a change, and never for the Save (docs/ADMIN-KIT.md).
	test( 'leaving the page warns only once something differs, and a Save leaves without a word', async ( {
		page,
		context,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, "The warning is the script's." );
		await login( page );
		const prompts: string[] = [];
		const listen = ( on: Page ) =>
			on.on( 'dialog', ( dialog ) => {
				prompts.push( dialog.type() );
				// Stay on the page.
				void dialog.dismiss();
			} );

		// Looked at, and left: no prompt. A click gives the page the user activation a prompt needs.
		const looked = await context.newPage();
		listen( looked );
		await looked.goto( PAGE );
		await looked
			.locator( '.cbuild-form__app' )
			.getByLabel( 'Assembly time' )
			.click();
		await looked.close( { runBeforeUnload: true } );
		await expect.poll( () => looked.isClosed() ).toBe( true );
		expect( prompts ).toEqual( [] );

		// Changed, and left: the prompt, and the page stays.
		listen( page );
		await page.goto( PAGE );
		const kit = page.locator( '.cbuild-form__app' );
		// Typed, not filled: Firefox and WebKit give a page the user activation a prompt needs only for trusted input.
		await kit.getByLabel( 'Assembly time' ).click();
		await kit.getByLabel( 'Assembly time' ).selectText();
		await page.keyboard.type( '6' );
		await page.close( { runBeforeUnload: true } );
		await expect.poll( () => prompts ).toEqual( [ 'beforeunload' ] );
		expect( page.isClosed() ).toBe( false );

		// Saved: no prompt.
		await save( page );
		await expect( page.getByText( 'Saved.' ) ).toBeVisible();
		expect( prompts ).toEqual( [ 'beforeunload' ] );
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
		// The summary names it, has focus as the page loads, and its link lands on DataForm's own control.
		const summary = page.locator( '.cbuild-form__summary' );
		await expect( summary ).toBeFocused();
		await expect( summary ).toContainText(
			'1 field needs a change before the form can be saved.'
		);
		await summary
			.getByRole( 'link', { name: `Assembly time: ${ refusal }` } )
			.click();
		await expect( kit.getByLabel( 'Assembly time' ) ).toBeFocused();
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

	test( 'a list adds in a modal, edits, moves, removes with an Undo, and saves in its order', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, 'The table is drawn by the script.' );
		await login( page );
		await page.goto( PAGE );
		const list = page.locator( '.cbuild-form__list' );
		const sizes = () =>
			list
				.getByRole( 'row' )
				.filter( { has: page.getByRole( 'cell' ) } )
				.locator( 'td:first-of-type' );
		const act = async ( size: string, action: string ) => {
			await list
				.getByRole( 'row' )
				.filter( {
					has: page.getByRole( 'cell', { name: size, exact: true } ),
				} )
				.getByRole( 'button', { name: 'Actions' } )
				.click();
			await page.getByRole( 'menuitem', { name: action } ).click();
		};
		const add = list.getByRole( 'button', { name: 'Add a row' } );
		const addRow = async ( size: string, from: string, to: string ) => {
			await add.click();
			const modal = page.getByRole( 'dialog', { name: 'Add a row' } );
			await modal.getByLabel( 'Shoe size' ).fill( size );
			await modal.getByLabel( 'From', { exact: true } ).fill( from );
			await modal.getByLabel( 'To', { exact: true } ).fill( to );
			await modal.getByRole( 'button', { name: 'Done' } ).click();
			await expect( modal ).toHaveCount( 0 );
		};
		await expect( sizes() ).toHaveText( [ '8' ] );
		await axe( page, 'a list', '.wrap' );

		// The modal's own form: a required column holds it open, and Enter is Done.
		await add.click();
		const modal = page.getByRole( 'dialog', { name: 'Add a row' } );
		await modal.getByRole( 'button', { name: 'Done' } ).click();
		await expect( modal ).toBeVisible();
		await axe( page, "a list's modal", '[role="dialog"]' );
		await modal.getByLabel( 'Shoe size' ).fill( '10' );
		await modal.getByLabel( 'From', { exact: true } ).fill( '8.25' );
		await modal.getByLabel( 'To', { exact: true } ).fill( '8.5' );
		await modal.getByLabel( 'To', { exact: true } ).press( 'Enter' );
		await expect( modal ).toHaveCount( 0 );
		await expect( sizes() ).toHaveText( [ '8', '10' ] );

		await act( '10', 'Move up' );
		await expect( sizes() ).toHaveText( [ '10', '8' ] );

		await act( '8', 'Remove' );
		await expect( sizes() ).toHaveText( [ '10' ] );
		const snackbar = page.locator( '.cbuild-form__snackbar' );
		await expect( snackbar ).toContainText( 'Row removed.' );
		await expect( add ).toBeFocused();
		await snackbar.getByRole( 'button', { name: 'Undo' } ).click();
		await expect( sizes() ).toHaveText( [ '10', '8' ] );
		await expect( snackbar ).toHaveCount( 0 );

		await act( '8', 'Edit' );
		const edit = page.getByRole( 'dialog', { name: 'Edit the row' } );
		await expect( edit.getByLabel( 'To', { exact: true } ) ).toHaveValue(
			'8.25'
		);
		await edit.getByLabel( 'Shoe size' ).fill( '9' );
		await edit.getByRole( 'button', { name: 'Done' } ).click();
		await expect( sizes() ).toHaveText( [ '10', '9' ] );

		await addRow( '12', '8.5', '9' );
		await expect( add ).toBeDisabled();
		await expect( list ).toContainText( 'The guide holds 3 rows at most.' );

		await save( page );
		await expect( page.getByText( 'Saved.' ) ).toBeVisible();
		await expect( sizes() ).toHaveText( [ '10', '9', '12' ] );

		// The server's refusal of the list: named in the summary, and under the table.
		await act( '9', 'Edit' );
		await page
			.getByRole( 'dialog', { name: 'Edit the row' } )
			.getByLabel( 'From', { exact: true } )
			.fill( '9.5' );
		await page
			.getByRole( 'dialog', { name: 'Edit the row' } )
			.getByRole( 'button', { name: 'Done' } )
			.click();
		await save( page );
		const refusal = 'Row 2: From is wider than To.';
		await expect( page.locator( '.cbuild-form__summary' ) ).toContainText(
			`Width guide: ${ refusal }`
		);
		await expect( list ).toContainText( refusal );
		await page
			.locator( '.cbuild-form__summary' )
			.getByRole( 'link', { name: `Width guide: ${ refusal }` } )
			.click();
		await expect( list ).toBeFocused();
		await expect( list ).toHaveAccessibleDescription( refusal );

		// Back to the one row the other tests start from.
		await act( '12', 'Remove' );
		await act( '9', 'Remove' );
		await act( '10', 'Edit' );
		const back = page.getByRole( 'dialog', { name: 'Edit the row' } );
		await back.getByLabel( 'Shoe size' ).fill( '8' );
		await back.getByLabel( 'From', { exact: true } ).fill( '8' );
		await back.getByLabel( 'To', { exact: true } ).fill( '8.25' );
		await back.getByRole( 'button', { name: 'Done' } ).click();
		await save( page );
		await expect( sizes() ).toHaveText( [ '8' ] );
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

		// A refusal: the summary's link goes to the server's own control.
		await server.getByLabel( 'Offer assembly' ).check();
		await server.getByLabel( 'Assembly time' ).fill( '0' );
		await save( page );
		const refusal =
			'Assembly time: Offering assembly needs at least one day.';
		await page
			.locator( '.cbuild-form__summary' )
			.getByRole( 'link', { name: refusal } )
			.click();
		await expect( page ).toHaveURL(
			/#cbuild-form-cbuild-e2e-kit-lead_days$/
		);
		await expect( server.getByLabel( 'Assembly time' ) ).toBeFocused();
		await server.getByLabel( 'Assembly time' ).fill( '5' );
		await server.getByLabel( 'Offer assembly' ).uncheck();
		await save( page );

		// A list with no script: its rows as inputs, and a spare for one more.
		await server.getByLabel( 'Shoe size, row 2' ).fill( '10' );
		await server.getByLabel( 'From, row 2' ).fill( '8.25' );
		await save( page );
		await expect( server.getByLabel( 'Shoe size, row 2' ) ).toHaveValue(
			'10'
		);
		await expect( server.getByLabel( 'Shoe size, row 3' ) ).toHaveValue(
			''
		);
		await server.getByLabel( 'Shoe size, row 2' ).fill( '' );
		await server.getByLabel( 'From, row 2' ).fill( '' );
		await save( page );
		await expect( server.getByLabel( 'Shoe size, row 3' ) ).toHaveCount(
			0
		);
	} );
} );
