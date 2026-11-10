import { test, expect, Page } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * WooCommerce > Builder, Spec tables (milestone 3): a maker's chart added, filled in by keyboard, refused whole when
 * a figure is wrong with the problem in its row, saved, and deleted after the page asks. Every form is a plain post,
 * so this runs with scripts off as well. axe-clean in each state.
 */

const PAGE = '/wp-admin/admin.php?page=cbuild';
const REGION = '#wpbody-content .wrap';
const NAME = 'Browser test chart';

// A field of one row of the chart: the row is a named group, and each field has its own label.
function field( page: Page, row: number, label: string ) {
	return page
		.getByRole( 'group', { name: new RegExp( `^Row ${ row }\\b` ) } )
		.getByLabel( label, { exact: true } );
}

async function submit( page: Page, button: string ) {
	await Promise.all( [
		page.waitForLoadState( 'load' ),
		page.getByRole( 'button', { name: button, exact: true } ).click(),
	] );
}

// Deletes the chart an earlier run left behind, if one did.
async function reset( page: Page ) {
	await page.goto( PAGE );
	const left = page.getByRole( 'link', { name: NAME, exact: true } );
	while ( ( await left.count() ) > 0 ) {
		await left.first().click();
		await page.getByRole( 'link', { name: 'Delete this chart' } ).click();
		await submit( page, 'Delete the chart' );
	}
}

test.describe( 'WooCommerce > Builder, Spec tables', () => {
	test( 'a chart is added, filled in by keyboard, refused when a figure is wrong, saved and deleted', async ( {
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
		await reset( page );

		await page.goto( PAGE );
		await expect(
			page.getByRole( 'heading', { name: 'Spec tables' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: 'Independent', exact: true } )
		).toBeVisible();
		await clean( 'the list of charts' );

		// A chart with no name is refused beside its field.
		await submit( page, 'Add chart' );
		const name = page.getByLabel( "Maker's name" );
		await expect( name ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( name ).toHaveAccessibleDescription(
			"Type the maker's name."
		);
		await clean( 'the list of charts, a name refused' );

		// By keyboard alone: the name, and Enter sends the form.
		await name.focus();
		await page.keyboard.type( NAME );
		await Promise.all( [
			page.waitForURL( /table=\d+/ ),
			page.keyboard.press( 'Enter' ),
		] );
		await expect( page.getByText( 'The chart was added.' ) ).toBeVisible();
		await expect(
			page.getByRole( 'heading', { name: `Spec table: ${ NAME }` } )
		).toBeVisible();
		await clean( 'a chart with no sizes' );

		// The size, Tab to the axle width as a fraction, Tab to the narrowest deck, which is not a measurement.
		await field( page, 1, 'Size' ).focus();
		await page.keyboard.type( '149' );
		await page.keyboard.press( 'Tab' );
		await expect( field( page, 1, 'Axle width (in)' ) ).toBeFocused();
		await page.keyboard.type( '8 1/2' );
		await page.keyboard.press( 'Tab' );
		await expect( field( page, 1, 'Narrowest deck (in)' ) ).toBeFocused();
		await page.keyboard.type( 'wide' );
		await submit( page, 'Save chart' );

		await expect( page.getByText( 'Nothing was saved.' ) ).toBeVisible();
		const wrong = field( page, 1, 'Narrowest deck (in)' );
		await expect( wrong ).toHaveValue( 'wide' );
		await expect( wrong ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( wrong ).toHaveAccessibleDescription(
			'Narrowest deck: "wide" is not a measurement. Type a number, or a fraction such as 8 1/4.'
		);
		await expect( field( page, 1, 'Size' ) ).not.toHaveAttribute(
			'aria-invalid',
			'true'
		);
		await clean( 'a chart, refused' );

		await wrong.fill( '8.375' );
		await submit( page, 'Save chart' );
		await expect( page.getByText( 'The chart was saved.' ) ).toBeVisible();
		await expect(
			page.getByRole( 'group', { name: 'Row 1: size 149' } )
		).toBeVisible();
		await expect( field( page, 1, 'Axle width (in)' ) ).toHaveValue(
			'8.5'
		);
		await expect( field( page, 1, 'Narrowest deck (in)' ) ).toHaveValue(
			'8.375'
		);
		await expect( field( page, 4, 'Size' ) ).toHaveValue( '' );
		await clean( 'a chart with a size' );

		// The page asks before it deletes, and "Keep it" goes back to the chart.
		await page.getByRole( 'link', { name: 'Delete this chart' } ).click();
		await expect(
			page.getByRole( 'heading', {
				name: `Delete the chart ${ NAME }?`,
			} )
		).toBeVisible();
		await clean( 'the question before a chart is deleted' );
		await page.getByRole( 'link', { name: 'Keep it' } ).click();
		await expect( field( page, 1, 'Size' ) ).toHaveValue( '149' );

		await page.getByRole( 'link', { name: 'Delete this chart' } ).click();
		await submit( page, 'Delete the chart' );
		await expect(
			page.getByText( 'The chart was deleted.' )
		).toBeVisible();
		await expect(
			page.getByRole( 'link', { name: NAME, exact: true } )
		).toHaveCount( 0 );
	} );
} );
