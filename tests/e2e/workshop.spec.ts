import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, type Page } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * Milestone 8, the workshop (D-219): the order screen's Build box and the assembly ticket pass axe and work from the
 * keyboard (spec: Accessibility), and the ticket prints one board to a page with its tools left off (roadmap: "Print:
 * the ticket's print styles"). The order is placed through the Store API with the fixture's build, two boards of it.
 */

interface Fixture {
	kit: number;
	picks: Record< string, number >;
}

const fixture = (): Fixture =>
	JSON.parse(
		readFileSync(
			path.join( process.cwd(), 'tmp', 'e2e-cart.json' ),
			'utf8'
		)
	);

/**
 * An order of two boards of the fixture's build, assembled, placed with the browser's session; its ID.
 *
 * @param page The page, for its session.
 */
async function ordered( page: Page ): Promise< number > {
	const { kit, picks } = fixture();
	const cart = await page.request.get( '/wp-json/wc/store/v1/cart' );
	const nonce = cart.headers().nonce ?? '';
	// The administrator's cart is kept between runs: this order holds this build alone.
	const emptied = await page.request.delete(
		'/wp-json/wc/store/v1/cart/items',
		{ headers: { Nonce: nonce } }
	);
	expect( emptied.ok(), await emptied.text() ).toBe( true );
	const added = await page.request.post(
		'/wp-json/wc/store/v1/cart/add-item',
		{
			headers: { Nonce: nonce },
			data: { id: kit, quantity: 2, cbuild: { picks, assembly: true } },
		}
	);
	expect( added.status(), await added.text() ).toBe( 201 );
	const address = {
		first_name: 'Sam',
		last_name: 'Shopper',
		address_1: '1 Test Street',
		city: 'Testville',
		state: 'CA',
		postcode: '90210',
		country: 'US',
		email: 'shopper+1@example.com',
		phone: '5550100',
	};
	const placed = await page.request.post( '/wp-json/wc/store/v1/checkout', {
		headers: { Nonce: added.headers().nonce ?? nonce },
		data: {
			billing_address: address,
			shipping_address: address,
			payment_method: 'cheque',
			customer_note: 'Tight trucks, please.',
		},
	} );
	expect( placed.status(), await placed.text() ).toBe( 200 );
	return ( await placed.json() ).order_id as number;
}

test.describe( 'The workshop', () => {
	test.skip(
		( { javaScriptEnabled } ) => ! javaScriptEnabled,
		'An admin screen, and axe runs in the page.'
	);

	test( 'the Build box and the ticket pass axe, work from the keyboard, and the ticket prints a board a page', async ( {
		page,
		context,
	} ) => {
		await login( page );
		const order = await ordered( page );
		await page.goto(
			`/wp-admin/admin.php?page=wc-orders&action=edit&id=${ order }`
		);
		const box = page.locator( '#cbuild-build' );
		await expect( box ).toBeVisible();
		await expect(
			box.getByRole( 'heading', { level: 3 } ).first()
		).toBeVisible();
		await axe( page, 'the Build box', '#cbuild-build' );

		// The parts table scrolls on its own and is reached by the keyboard; the ticket's link opens from it.
		const print = box.getByRole( 'link', {
			name: /Print assembly ticket/,
		} );
		await print.focus();
		await expect( print ).toBeFocused();
		const [ ticket ] = await Promise.all( [
			context.waitForEvent( 'page' ),
			page.keyboard.press( 'Enter' ),
		] );
		await ticket.waitForLoadState();
		await expect( ticket.locator( 'article.cbuild-ticket' ) ).toHaveCount(
			2
		);
		await expect( ticket.locator( 'main' ) ).toContainText(
			'Tight trucks, please.'
		);
		await axe( ticket, 'the assembly ticket', 'body' );
		const boxes = ticket.getByRole( 'checkbox' );
		await boxes.first().focus();
		await ticket.keyboard.press( 'Space' );
		await expect( boxes.first() ).toBeChecked();

		await ticket.emulateMedia( { media: 'print' } );
		await expect( ticket.locator( '.cbuild-tickets__tools' ) ).toBeHidden();
		const breaks = await ticket
			.locator( 'article.cbuild-ticket' )
			.evaluateAll( ( tickets ) =>
				tickets.map( ( one ) => getComputedStyle( one ).breakAfter )
			);
		expect( breaks, 'one board to a page, no blank page after' ).toEqual( [
			'page',
			'auto',
		] );
		await ticket.close();
	} );
} );
