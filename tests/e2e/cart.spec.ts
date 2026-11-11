import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, Page } from './support/test';

/**
 * Milestone 5: a build in the three carts (spec: Cart, checkout and orders, Showing it). The builder that sends a
 * build arrives in milestone 6, so each test adds the fixture's build through the Store API, as the builder will,
 * with the browser's own session.
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
 * Adds the fixture's build to the browser's cart through the Store API.
 *
 * @param page     The page, for its session.
 * @param quantity How many of the build.
 */
async function add( page: Page, quantity = 1 ) {
	const { kit, picks } = fixture();
	const cart = await page.request.get( '/wp-json/wc/store/v1/cart' );
	const answer = await page.request.post(
		'/wp-json/wc/store/v1/cart/add-item',
		{
			headers: { Nonce: cart.headers().nonce ?? '' },
			data: { id: kit, quantity, cbuild: { picks } },
		}
	);
	expect( answer.status(), await answer.text() ).toBe( 201 );
}

const JS_OFF = 'no-js';

test.describe( 'A build in the Cart block', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			info.project.name === JS_OFF,
			'The Cart block is drawn by its script.'
		);
	} );

	test( 'is grouped under its kit, and its parts are locked', async ( {
		page,
	} ) => {
		await page.goto( '/cart/' );
		await add( page );
		await page.goto( '/cart/' );

		const rows = page.locator( '.wc-block-cart-items__row' );
		await expect( rows ).toHaveCount( 7 );
		const kit = page.locator( '.wc-block-cart-items__row.cbuild-kit' );
		const parts = page.locator( '.wc-block-cart-items__row.cbuild-part' );
		await expect( kit ).toHaveCount( 1 );
		await expect( parts ).toHaveCount( 6 );
		await expect( rows.first() ).toHaveClass( /cbuild-kit/ );

		const trucks = parts.filter( { hasText: 'Cart test trucks' } );
		await expect(
			trucks.locator( '.wc-block-components-product-name' )
		).toHaveText( 'Trucks: Cart test trucks' );
		await expect(
			trucks.locator( '.wc-block-cart-item__remove-link' )
		).toHaveCount( 0 );
		// The Cart block shows a locked line's quantity as a number in a field nobody can change.
		await expect(
			trucks.locator( '.wc-block-components-quantity-selector__input' )
		).not.toBeEditable();
		await expect(
			trucks.locator( '.wc-block-components-quantity-selector__input' )
		).toHaveValue( '2' );
		await expect(
			trucks.locator( '.wc-block-components-quantity-selector__button' )
		).toHaveCount( 0 );
		await expect( trucks.locator( '.cbuild-part' ) ).toHaveText(
			'Cart test kit (Trucks)'
		);
		// The part is indented under its kit, on whichever side the language starts.
		const indent = ( row: typeof kit ) =>
			row.locator( '> :first-child' ).evaluate( ( cell ) => {
				const style = getComputedStyle( cell );
				return (
					parseFloat( style.paddingInlineStart ) +
					parseFloat( style.borderInlineStartWidth )
				);
			} );
		expect( await indent( trucks ) ).toBeGreaterThan( await indent( kit ) );

		// The kit's line has the one quantity and the one remove link, and takes the build with it.
		await expect(
			kit.locator( '.wc-block-components-quantity-selector__input' )
		).toBeEditable();
		await kit.locator( '.wc-block-cart-item__remove-link' ).click();
		await expect( rows ).toHaveCount( 0 );
	} );
} );

test.describe( 'A build in the Mini-Cart', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			info.project.name === JS_OFF,
			'The Mini-Cart is drawn by its script.'
		);
	} );

	test( 'shows after an add, with its first read of the cart held back', async ( {
		page,
	} ) => {
		// As Skatehouse's own test does: the Mini-Cart's first read of the cart after the add takes six seconds.
		let hold = false;
		let held = 0;
		await page.route( /\/wc\/store\/v1\/cart(\?|$)/, async ( route ) => {
			if ( hold && route.request().method() === 'GET' ) {
				hold = false;
				held++;
				await new Promise( ( done ) => setTimeout( done, 6000 ) );
			}
			await route.continue();
		} );
		// A build is already in the cart, as on any page a shopper comes to from the builder: the page is drawn with
		// the styles a build's lines go by. A second is then added without leaving the page.
		await add( page );
		await page.goto( '/cart-test-mini-cart/' );
		const badge = page.locator( 'main .wc-block-mini-cart__badge' );
		await expect( badge ).toHaveText( '1', { timeout: 20_000 } );
		await add( page );
		hold = true;
		// What the builder sends after an add (spec: Compatibility, the Mini-Cart), and the count follows it: one per
		// build (D-009). The page's count is drawn by the server, so it says nothing of whether the Mini-Cart's
		// script has started listening yet: the event is sent until it is heard. In the builder a shopper's own
		// click comes long after. The one read it asks for is held, and the wait inside is longer than the hold.
		await expect( async () => {
			await page.evaluate( () =>
				document.body.dispatchEvent(
					new CustomEvent( 'wc-blocks_added_to_cart', {
						bubbles: true,
						cancelable: true,
					} )
				)
			);
			await expect( badge ).toHaveText( '2', { timeout: 9000 } );
		} ).toPass( { timeout: 40_000 } );
		expect( held, 'the read the event asked for was the one held' ).toBe(
			1
		);
		// Whether the drawer opens by itself on an add is the block's setting, and off by default: the shopper opens it.
		await page.locator( 'main .wc-block-mini-cart__button' ).click();
		const drawer = page.locator( '.wc-block-mini-cart__drawer' );
		const rows = drawer.locator( '.wc-block-cart-items__row' );
		await expect( rows ).toHaveCount( 14 );
		await expect( rows.first() ).toBeVisible();

		// No line filter runs here (D-010): the part's item data carries the class, and the styles indent by it.
		const parts = drawer.locator(
			'.wc-block-cart-items__row:has(.cbuild-part)'
		);
		await expect( parts ).toHaveCount( 12 );
		const trucks = parts.filter( { hasText: 'Cart test trucks' } ).first();
		await expect( trucks.locator( '.cbuild-part' ) ).toHaveText(
			'Cart test kit (Trucks)'
		);
		const kit = rows.filter( { hasText: 'Cart test kit' } ).first();
		const indent = ( row: typeof kit ) =>
			row.locator( '> :first-child' ).evaluate( ( cell ) => {
				const style = getComputedStyle( cell );
				return (
					parseFloat( style.paddingInlineStart ) +
					parseFloat( style.borderInlineStartWidth )
				);
			} );
		expect( await indent( trucks ) ).toBeGreaterThan( await indent( kit ) );
		// A part's quantity is a number nobody can change: its buttons are hidden and its field is locked
		// (src/Blocks/BlockTypes/MiniCartProductsTableBlock.php:179, :191 and :200).
		await expect(
			trucks.locator( '.wc-block-components-quantity-selector__button' )
		).toHaveCount( 2 );
		for ( const button of await trucks
			.locator( '.wc-block-components-quantity-selector__button' )
			.all() ) {
			await expect( button ).toBeHidden();
		}
		await expect(
			trucks.locator( '.wc-block-components-quantity-selector__input' )
		).not.toBeEditable();

		// A part keeps its remove link here, and removing it removes its build and leaves the other whole.
		await trucks.locator( '.wc-block-cart-item__remove-link' ).click();
		await expect( rows ).toHaveCount( 7 );
		await expect( badge ).toHaveText( '1' );
	} );
} );

test.describe( 'A build in a classic cart and checkout', () => {
	test.beforeEach( async ( { context, baseURL } ) => {
		await context.addCookies( [
			{
				name: 'cbuild_e2e_classic',
				value: '1',
				url: baseURL || 'http://localhost:8898',
			},
		] );
	} );

	test( 'is grouped and locked, and is placed as an order with an offline method', async ( {
		page,
	} ) => {
		await page.goto( '/cart-test-classic-cart/' );
		await add( page );
		await page.goto( '/cart-test-classic-cart/' );

		const rows = page.locator( 'tr.woocommerce-cart-form__cart-item' );
		await expect( rows ).toHaveCount( 7 );
		await expect( rows.first() ).toHaveClass( /cbuild-kit/ );
		const parts = page.locator(
			'tr.woocommerce-cart-form__cart-item.cbuild-part'
		);
		await expect( parts ).toHaveCount( 6 );
		const trucks = parts.filter( { hasText: 'Cart test trucks' } );
		await expect( trucks.locator( 'td.product-name a' ) ).toHaveText(
			'Cart test trucks'
		);
		await expect( trucks.locator( 'td.product-name' ) ).toContainText(
			'Trucks: Cart test trucks'
		);
		await expect( trucks.locator( 'a.remove' ) ).toHaveCount( 0 );
		await expect( trucks.locator( 'input.qty' ) ).toHaveCount( 0 );
		await expect( trucks.locator( '.cbuild-quantity' ) ).toHaveText( '2' );
		// The part is indented under its kit, here and in the checkout's table.
		const indent = ( cell: ReturnType< Page[ 'locator' ] > ) =>
			cell.evaluate( ( td ) => {
				const style = getComputedStyle( td );
				return (
					parseFloat( style.paddingInlineStart ) +
					parseFloat( style.borderInlineStartWidth )
				);
			} );
		expect(
			await indent( trucks.locator( 'td.product-name' ) )
		).toBeGreaterThan(
			await indent( rows.first().locator( 'td.product-name' ) )
		);
		await expect( rows.first().locator( 'a.remove' ) ).toHaveCount( 1 );
		await expect( rows.first().locator( 'input.qty' ) ).toHaveCount( 1 );

		await page.goto( '/cart-test-classic-checkout/' );
		await expect(
			page.locator(
				'.woocommerce-checkout-review-order-table tr.cbuild-part'
			)
		).toHaveCount( 6 );
		// With its script on, the checkout draws its table again once it has asked the server for the totals.
		await page.waitForLoadState( 'networkidle' );
		const review = page.locator(
			'.woocommerce-checkout-review-order-table tbody tr'
		);
		expect(
			await indent(
				review
					.filter( { hasText: 'Cart test trucks' } )
					.locator( 'td.product-name' )
			)
		).toBeGreaterThan(
			await indent( review.first().locator( 'td.product-name' ) )
		);
		for ( const [ field, value ] of Object.entries( {
			billing_first_name: 'Sam',
			billing_last_name: 'Shopper',
			billing_address_1: '1 Test Street',
			billing_city: 'Testville',
			billing_postcode: '90210',
			billing_phone: '5550100',
			billing_email: 'shopper+1@example.com',
		} ) ) {
			await page.locator( `#${ field }` ).fill( value );
		}
		await page.locator( '#payment_method_cheque' ).check( { force: true } );
		await page.locator( '#place_order' ).click();

		await page.waitForURL( /order-received/ );
		const order = page.locator( '.woocommerce-table--order-details' );
		await expect( order ).toContainText( 'Trucks: Cart test trucks' );
		await expect( order ).toContainText( 'Assembly' );
		await expect( order ).toContainText(
			'Deck: Cart test deck × 1, Trucks: Cart test trucks × 2'
		);
	} );
} );
