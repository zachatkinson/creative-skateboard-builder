import { execSync } from 'node:child_process';
import { readFileSync } from 'node:fs';
import { test, expect, Page } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * WooCommerce > Builder, Import and export (milestone 3): the sheet downloaded, a file refused whole, a file shown
 * before anything is written, then confirmed and written in the background. Every form is a plain post, so this runs
 * with scripts off as well. axe-clean in each state.
 */

const TAB = '/wp-admin/admin.php?page=cbuild&tab=import';
const REGION = '#wpbody-content .wrap';
const SKU = 'smoke-test-deck';

async function submit( page: Page, button: string ) {
	await Promise.all( [
		page.waitForLoadState( 'load' ),
		page.getByRole( 'button', { name: button, exact: true } ).click(),
	] );
}

// A file chosen and sent to be checked, matched by SKU.
async function check( page: Page, name: string, text: string ) {
	await page.getByLabel( /^CSV file/ ).setInputFiles( {
		name,
		mimeType: 'text/csv',
		buffer: Buffer.from( text ),
	} );
	await page.getByLabel( 'Match parts by' ).selectOption( 'sku' );
	await submit( page, 'Check the file' );
}

// Runs the import's job and waits until the tab says it is done. The shop's own requests start the queue's runner
// too, so the job may be running in another process already; then this asks again until that one has finished.
async function finish( page: Page ) {
	await expect( async () => {
		execSync(
			'npx wp-env run cli -- wp action-scheduler run --hooks=cbuild_import --quiet',
			// Quiet: Action Scheduler's own command warns under PHP 8.5, and a job that did not run fails the wait below.
			{ stdio: 'ignore' }
		);
		await page.goto( TAB );
		await expect(
			page.getByRole( 'heading', { name: 'The last import' } )
		).toBeVisible( { timeout: 1000 } );
	} ).toPass( { timeout: 60_000 } );
}

// Sets the deck's wheelbase, confirming the import, and puts away whatever an earlier run left waiting.
async function reset( page: Page, wheelbase: string ) {
	await page.goto( TAB );
	const waiting = page.getByRole( 'button', { name: 'Put the file away' } );
	if ( ( await waiting.count() ) > 0 ) {
		await submit( page, 'Put the file away' );
		await expect(
			page.getByText( 'The import was put away.' )
		).toBeVisible();
	}
	await check(
		page,
		'reset.csv',
		`sku,wheelbase\n${ SKU },${ wheelbase }\n`
	);
	// The page that answers is waited for by its heading: a count of buttons does not wait.
	await expect(
		page.getByRole( 'heading', { name: 'Check the import' } )
	).toBeVisible();
	const confirm = page.getByRole( 'button', {
		name: 'Import these changes',
	} );
	if ( ( await confirm.count() ) > 0 ) {
		await submit( page, 'Import these changes' );
		await expect(
			page.getByText( 'The import has started.' )
		).toBeVisible();
		await finish( page );
	} else {
		await submit( page, 'Put the file away' );
		await expect(
			page.getByText( 'The import was put away.' )
		).toBeVisible();
	}
}

test.describe( 'WooCommerce > Builder, Import and export', () => {
	test( 'the sheet is downloaded, and a file is refused, shown, confirmed and written', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		// axe is a script in the page, so it cannot run in the project with scripts off; the others run it.
		const clean = async ( name: string ) => {
			if ( javaScriptEnabled ) {
				await axe( page, name, REGION );
			}
		};
		await login( page );
		await reset( page, '' );

		// The tab is reached from the charts, and says which tab it is.
		await page.goto( '/wp-admin/admin.php?page=cbuild' );
		await page
			.getByRole( 'navigation', { name: 'Builder' } )
			.getByRole( 'link', { name: 'Import and export' } )
			.click();
		await expect(
			page.getByRole( 'link', { name: 'Import and export' } )
		).toHaveAttribute( 'aria-current', 'page' );
		await expect(
			page.getByRole( 'heading', { name: 'Export', exact: true } )
		).toBeVisible();
		await clean( 'the import tab' );

		// The export is a file, with a row for the deck.
		const [ download ] = await Promise.all( [
			page.waitForEvent( 'download' ),
			page
				.getByRole( 'button', { name: 'Download the CSV file' } )
				.click(),
		] );
		expect( download.suggestedFilename() ).toBe( 'part-specs.csv' );
		const sheet = readFileSync( await download.path(), 'utf8' );
		expect( sheet ).toContain( 'id,sku,name,spec_table,spec_size,' );
		expect( sheet ).toMatch(
			new RegExp( `\\d+,${ SKU },Smoke test deck,` )
		);

		// No file chosen: refused beside the field.
		await page.goto( TAB );
		await submit( page, 'Check the file' );
		const file = page.getByLabel( /^CSV file/ );
		await expect( file ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( file ).toHaveAccessibleDescription(
			'Choose a CSV file first.'
		);
		await clean( 'the import tab, no file' );

		// A file that is not a CSV, and one with nothing to import.
		await check( page, 'specs.txt', 'sku,wheelbase\nA,1\n' );
		await expect(
			page.getByLabel( /^CSV file/ )
		).toHaveAccessibleDescription(
			'That is not a .csv file. Save the sheet as CSV and choose it again.'
		);
		await check( page, 'specs.csv', 'sku,name\nA,B\n' );
		await expect(
			page.getByText( 'The file was not read. Nothing has changed.' )
		).toBeVisible();
		await expect(
			page.getByText( 'The file has no spec columns' )
		).toBeVisible();

		// A file with a row to write and a row it cannot read: shown first, with nothing written.
		await check(
			page,
			'specs.csv',
			`sku,wheelbase\n${ SKU },14 1/4\nno-such-part,14\n`
		);
		await expect(
			page.getByRole( 'heading', { name: 'Check the import' } )
		).toBeVisible();
		await expect( page.getByText( '1 part would change.' ) ).toBeVisible();
		await expect(
			page.getByText( '1 row cannot be read, and would be left out.' )
		).toBeVisible();
		const changes = page.getByRole( 'region', {
			name: 'What would change',
		} );
		await expect(
			changes.getByRole( 'row', {
				name: '2 Smoke test deck Wheelbase Nothing 14.25 in',
			} )
		).toBeVisible();
		await expect(
			page
				.getByRole( 'region', { name: 'Rows that cannot be read' } )
				.getByRole( 'row', {
					name: '3 SKU No product is "no-such-part".',
				} )
		).toBeVisible();
		await clean( 'an import, waiting' );

		// Confirmed by keyboard: it runs in the background.
		await page
			.getByRole( 'button', { name: 'Import these changes' } )
			.focus();
		await Promise.all( [
			page.waitForURL( /notice=started/ ),
			page.keyboard.press( 'Enter' ),
		] );
		await expect(
			page.getByText( 'The import has started.' )
		).toBeVisible();
		// The shop's own requests start the queue, so the job may have run by now; when it has not, the page says so.
		const running = page.getByRole( 'link', { name: 'Check again' } );
		if ( ( await running.count() ) > 0 ) {
			await expect( page.getByLabel( /^CSV file/ ) ).toHaveCount( 0 );
			await clean( 'an import, running' );
		}

		await finish( page );
		await expect(
			page.getByRole( 'heading', { name: 'The last import' } )
		).toBeVisible();
		await expect( page.getByText( '1 part was changed.' ) ).toBeVisible();
		await expect(
			page.getByText( '1 row could not be read, and was left out.' )
		).toBeVisible();
		await clean( 'an import, done' );

		// The same file again changes nothing, so there is nothing to confirm.
		await check( page, 'specs.csv', `sku,wheelbase\n${ SKU },14.25\n` );
		await expect( page.getByText( '0 parts would change.' ) ).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'Import these changes' } )
		).toHaveCount( 0 );
		await submit( page, 'Put the file away' );
		await expect(
			page.getByText( 'The import was put away.' )
		).toBeVisible();

		await reset( page, '' );
	} );
} );
