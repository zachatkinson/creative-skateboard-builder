import { test, expect, Page } from './support/test';
import { axe } from './support/axe';
import { wp } from './support/cli';
import { login } from './support/login';

/**
 * WooCommerce > Builder, Catalogue check (milestone 3): the check runs when asked, lists a part that names a chart
 * the shop no longer has, and links to the part's own fields. The form is a plain post, so this runs with scripts
 * off as well. axe-clean before the check and with its findings.
 */

const TAB = '/wp-admin/admin.php?page=cbuild&tab=check';
const REGION = '#wpbody-content .wrap';
const SKU = 'smoke-test-deck';
const FOUND = "It names a maker's chart that is no longer there.";

// Takes the chart's name off the part. WP-CLI's own delete fails when there is nothing to delete; this does not.
function forget( part: string ) {
	wp( `eval "delete_post_meta( ${ part }, '_cbuild_spec_table' );"` );
}

async function run( page: Page, button: string ) {
	await page.getByRole( 'button', { name: button, exact: true } ).click();
	await expect( page.getByRole( 'status' ) ).toBeVisible();
}

test.describe( 'WooCommerce > Builder, Catalogue check', () => {
	let deck = '';

	test.beforeAll( () => {
		deck = wp( `wc product list --sku=${ SKU } --field=id --user=1` );
	} );

	test.afterAll( () => {
		forget( deck );
	} );

	test( 'the check lists a part that names a missing chart, with a link to its fields', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		wp(
			`post meta update ${ deck } _cbuild_spec_table '{"table":999999,"size":"1"}' --format=json`
		);
		await login( page );
		await page.goto( TAB );
		await expect(
			page.getByRole( 'heading', { name: 'Catalogue check', level: 2 } )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: 'Catalogue check' } )
		).toHaveAttribute( 'aria-current', 'page' );
		// Nothing is read until the check is asked for.
		await expect( page.getByRole( 'status' ) ).toHaveCount( 0 );
		if ( javaScriptEnabled ) {
			await axe( page, 'the Catalogue check, before it runs', REGION );
		}

		// By keyboard: the button is the form's only control.
		await page.getByRole( 'button', { name: 'Run the check' } ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( page.getByRole( 'status' ) ).toContainText(
			/The check found \d+ things? to fix\./
		);
		const row = page.getByRole( 'row' ).filter( { hasText: FOUND } );
		await expect( row ).toHaveCount( 1 );
		await expect( row.getByRole( 'link' ) ).toHaveAttribute(
			'href',
			new RegExp( `post=${ deck }&action=edit#cbuild_part_specs$` )
		);
		await expect(
			page.getByRole( 'region', { name: 'What it found' } )
		).toBeVisible();
		if ( javaScriptEnabled ) {
			await axe( page, 'the Catalogue check, with findings', REGION );
		}

		// The link lands on the part's own screen, with its Part specs open where scripts run.
		await row.getByRole( 'link' ).click();
		await expect( page ).toHaveURL( /post\.php\?post=\d+&action=edit/ );
		if ( javaScriptEnabled ) {
			await expect( page.locator( '#cbuild_part_specs' ) ).toBeVisible();
			// Focus follows: a keyboard lands in the panel, not at the top of the page.
			await expect(
				page.locator( '#cbuild_part_specs :focus' )
			).toHaveCount( 1 );
			await expect(
				page.locator( '#general_product_data' )
			).toBeHidden();
		}

		// Fixed, the part is no longer listed.
		forget( deck );
		await page.goto( TAB );
		await run( page, 'Run the check' );
		await expect(
			page.getByRole( 'row' ).filter( { hasText: FOUND } )
		).toHaveCount( 0 );
		await expect(
			page.getByRole( 'button', { name: 'Run the check again' } )
		).toBeVisible();
	} );
} );
