import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, answered, Page } from './support/test';

/**
 * Milestone 6, without JavaScript (spec: The builder, Without JavaScript and Ways in): each step is a GET form whose
 * picks travel in the address, the change panel and the review are steps of their own, and Add to cart posts to
 * WooCommerce's own handler. Every way in lands where the spec says. These run in the no-js project; with
 * JavaScript, the store takes each move through the state route without loading the page (D-148), tested after them.
 */

interface Fixture {
	kit: number;
	page: string;
	parts: Record< string, number >;
}

const fixture = (): Fixture =>
	JSON.parse(
		readFileSync(
			path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
			'utf8'
		)
	);

/**
 * A part's ID by its SKU.
 *
 * @param sku The part's SKU, such as builder-test-deck-8-0.
 */
const id = ( sku: string ): number => {
	const found = fixture().parts[ sku ];
	if ( found === undefined ) {
		throw new Error( `The fixture has no part ${ sku }.` );
	}
	return found;
};

/**
 * The builder page's address with a build in it, written as the spec fixes it.
 *
 * @param build The build's parameters, by name under `cbuild`.
 */
const linkTo = ( build: Record< string, string | number > ): string => {
	const url = new URL( fixture().page );
	for ( const [ key, value ] of Object.entries( build ) ) {
		url.searchParams.set( `cbuild[${ key }]`, String( value ) );
	}
	return url.toString();
};

/**
 * The step's heading.
 *
 * @param page The page.
 */
const heading = ( page: Page ) =>
	page.getByRole( 'heading', { level: 2, name: /^Step \d+ of \d+:/ } );

/**
 * Sends a step's form with its Next button, and waits for the step the server answers with.
 *
 * @param page The page.
 * @param name The button's name, such as "Next: Trucks".
 */
async function next( page: Page, name: string ) {
	await answered( page, () => page.getByRole( 'button', { name } ).click() );
}

/**
 * The build in the page's address, as the server wrote it.
 *
 * @param page The page.
 */
const build = ( page: Page ): string[] =>
	[ ...new URL( page.url() ).searchParams.keys() ].filter( ( key ) =>
		key.startsWith( 'cbuild[' )
	);

test.describe( 'The builder without JavaScript', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			info.project.name !== 'no-js',
			'With JavaScript, the store moves the builder: the tests after these.'
		);
	} );

	test( 'goes from a blank page to the cart, one GET form a step', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		await expect(
			page.getByText( 'Start with a width.', { exact: false } )
		).toBeVisible();
		const decks = page.getByRole( 'radio', {
			name: /^Choose Builder test deck/,
		} );
		await expect( decks ).toHaveCount( 3 );
		await expect( decks.and( page.locator( ':checked' ) ) ).toHaveCount(
			0
		);
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeEnabled();

		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		await next( page, 'Next: Trucks' );
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		// The kit's own setting decides assembly until the shopper changes it, so the address leaves it out. The
		// browser writes what the form sent, the step it was on among it (D-150).
		expect( build( page ) ).toEqual( [
			'cbuild[_on]',
			'cbuild[deck]',
			'cbuild[step]',
		] );
		const trucks = page.getByRole( 'radio', {
			name: 'Choose Builder test trucks 139',
		} );
		await expect( trucks ).toBeChecked();
		await expect(
			page.getByText( 'Recommended for your 8 in deck' )
		).toBeVisible();

		await next( page, 'Next: Wheels' );
		await expect( heading( page ) ).toHaveText( 'Step 3 of 7: Wheels' );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test wheels 56',
			} ),
			'Sold out, so it cannot be picked.'
		).toBeDisabled();
		await page
			.getByRole( 'radio', { name: 'Choose Builder test wheels 58' } )
			.check();
		await next( page, 'Next: Bearings' );
		await expect(
			heading( page ),
			'58 mm wheels want a riser under these trucks, so the risers step appears.'
		).toHaveText( 'Step 4 of 8: Risers' );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test risers 1/4',
			} ),
			'The riser the build counts is the one shown checked.'
		).toBeChecked();
		await expect(
			page.getByRole( 'radio', { name: 'No risers' } )
		).not.toBeChecked();

		await next( page, 'Next: Bearings' );
		await expect( heading( page ) ).toHaveText( 'Step 5 of 8: Bearings' );
		await next( page, 'Next: Hardware' );
		await next( page, 'Next: Grip tape' );
		await next( page, 'Next: Review' );
		await expect( heading( page ) ).toHaveText( 'Step 8 of 8: Review' );

		const review = page.locator( '.cbuild-review' );
		await expect(
			review.getByText( 'Builder test deck 8.0' )
		).toBeVisible();
		await expect( review.getByText( 'Two business days' ) ).toBeVisible();
		await expect(
			review.getByText( 'An assembled board comes back as parts.' )
		).toBeVisible();
		await expect(
			review.getByRole( 'link', { name: 'Change Trucks' } )
		).toHaveAttribute( 'rel', 'nofollow' );

		await answered( page, () =>
			review.getByRole( 'button', { name: 'Add to cart' } ).click()
		);
		const cart = await (
			await page.request.get( '/wp-json/wc/store/v1/cart' )
		).json();
		const names = cart.items.map( ( item: { name: string } ) => item.name );
		expect( names ).toEqual(
			expect.arrayContaining( [
				'Builder test kit',
				'Builder test deck 8.0',
				'Builder test trucks 139',
				'Builder test wheels 58',
				'Builder test risers 1/4',
				'Builder test bolts 1.25',
				'Builder test bearings',
				'Builder test grip',
			] )
		);
	} );

	test( 'a wider deck opens the change panel as a step, with Use these and Undo as buttons', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'deck',
			} )
		);
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
			.check();
		await next( page, 'Next: Trucks' );

		const panel = page.locator( '.cbuild-changes' );
		await expect(
			panel.getByRole( 'heading', {
				name: 'This change affects parts you chose',
			} )
		).toBeVisible();
		await expect(
			panel.getByText( 'Trucks: Builder test trucks 139 no longer fits.' )
		).toBeVisible();
		await expect(
			panel.getByText( 'Closest fit: Builder test trucks 149' )
		).toBeVisible();
		await expect( page.locator( '.cbuild-step' ) ).toBeHidden();
		await expect( panel.getByRole( 'link' ) ).toHaveCount( 0 );

		await answered( page, () =>
			panel.getByRole( 'button', { name: 'Use these' } ).click()
		);
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 149',
			} )
		).toBeChecked();
		expect( new URL( page.url() ).searchParams.get( 'cbuild[deck]' ) ).toBe(
			String( id( 'builder-test-deck-8-5' ) )
		);

		await page.goBack();
		await answered( page, () =>
			page
				.locator( '.cbuild-changes' )
				.getByRole( 'button', { name: 'Undo' } )
				.click()
		);
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
	} );

	test( "a deck's link opens on the trucks with the deck picked", async ( {
		page,
	} ) => {
		await page.goto( linkTo( { deck: id( 'builder-test-deck-8-5' ) } ) );
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 149',
			} )
		).toBeChecked();
		await expect(
			page.getByText( 'Recommended for your 8.5 in deck' )
		).toBeVisible();
	} );

	test( 'a build link in any order opens its step, and a part the kit cannot take is named', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				step: 'review',
				grip: id( 'builder-test-grip' ),
				wheels: id( 'builder-test-wheels-54' ),
				deck: id( 'builder-test-deck-8-25' ),
				trucks: id( 'builder-test-deck-8-0' ),
			} )
		);
		const errors = page.locator( '.cbuild-errors' );
		await expect( errors ).toBeVisible();
		await expect( errors.getByRole( 'link' ).first() ).toBeVisible();
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		await expect(
			page
				.locator( '.cbuild-review' )
				.getByText( 'Builder test trucks 144' ),
			'The trucks start on what fits the deck.'
		).toBeVisible();
	} );

	test( 'a width pressed lists only its decks and stays on the step, and Any width lists them all again', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		const filters = page.getByRole( 'group', { name: 'Width' } );
		await answered( page, () =>
			filters.getByRole( 'button', { name: '8.25 in (1)' } ).click()
		);
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 1 );
		await expect(
			filters.getByRole( 'button', { name: '8.25 in (1)' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( page.getByText( '1 of 1 part shown' ) ).toBeVisible();

		await answered( page, () =>
			filters.getByRole( 'button', { name: 'Any width' } ).click()
		);
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 3 );
		await expect(
			filters.getByRole( 'button', { name: 'Any width' } )
		).toBeHidden();
	} );

	test( "a deck's width filter stays on the deck step and Next leaves it behind", async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		await answered( page, () =>
			page
				.getByRole( 'group', { name: 'Width' } )
				.getByRole( 'button', { name: '8 in (1)' } )
				.click()
		);
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		await next( page, 'Next: Trucks' );
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 139',
			} )
		).toBeChecked();
		await expect(
			page.locator( 'input[type="hidden"][name="cbuild[_size]"]' ),
			"the trucks' step does not carry the deck's width on"
		).toHaveCount( 0 );
		await expect(
			page
				.getByRole( 'group', { name: 'Axle width' } )
				.getByRole( 'button', { pressed: true } )
		).toHaveCount( 0 );
	} );

	test( 'parts that do not fit are named under a disclosure with the reason', async ( {
		page,
	} ) => {
		await page.goto( linkTo( { deck: id( 'builder-test-deck-8-5' ) } ) );
		const hidden = page.locator( '.cbuild-hidden' );
		await expect( hidden ).toBeVisible();
		await expect( hidden.locator( 'summary' ) ).toContainText( 'hidden' );
		await hidden.locator( 'summary' ).click();
		await expect( hidden.getByRole( 'link' ).first() ).toBeVisible();
	} );

	test( 'a preset tile starts its build at the review, and one with a sold-out part names it and keeps it from the cart', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		const tiles = page.locator( '.cbuild-presets' );
		await expect(
			tiles.getByRole( 'heading', { name: 'Start from a ready build' } )
		).toBeVisible();
		await expect(
			tiles.getByText( 'Wheels: Builder test wheels 56 is sold out.' )
		).toBeVisible();

		await answered( page, () =>
			tiles.getByRole( 'button', { name: 'Start from Street' } ).click()
		);
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		await expect( page.locator( '.cbuild-errors' ) ).toBeHidden();
		const review = page.locator( '.cbuild-review' );
		await expect( review.locator( '.cbuild-review__assembly' ) ).toHaveText(
			'Assembly: $15.00'
		);
		await expect( review.locator( '.cbuild-review__total' ) ).toHaveText(
			'Total: $182.00'
		);
		// All before Add to cart, in the page's order (spec: Review and add to cart).
		expect(
			await review.evaluate( ( section ) =>
				[
					...section.querySelectorAll(
						'.cbuild-review__total, button[type="submit"]'
					),
				].map( ( node ) => node.className )
			)
		).toEqual( [
			'cbuild-review__total',
			'cbuild-button cbuild-button--primary',
		] );
		await expect(
			review.getByRole( 'button', { name: 'Add to cart' } )
		).toBeEnabled();
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[preset]' )
		).toBe( '0' );
		await expect( page.locator( '.cbuild-presets' ) ).toBeHidden();

		await page.goto( fixture().page );
		await answered( page, () =>
			page
				.locator( '.cbuild-presets' )
				.getByRole( 'button', { name: 'Start from Cruiser' } )
				.click()
		);
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		await expect( page.locator( '.cbuild-errors' ) ).toContainText(
			'Wheels: Builder test wheels 56 is sold out.'
		);
		await expect(
			page
				.locator( '.cbuild-review' )
				.getByRole( 'button', { name: 'Add to cart' } )
		).toBeDisabled();
	} );

	test( "the kit's own page shows the same builder", async ( { page } ) => {
		await page.goto( '/product/builder-test-kit/' );
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 3 );
	} );
} );

/**
 * Whether a request is the state route's.
 *
 * @param url The request's address.
 */
const isRoute = ( url: URL | string ): boolean =>
	/cbuild(%2F|\/)v1(%2F|\/)kits(%2F|\/)\d+(%2F|\/)state/.test(
		String( url )
	);

/**
 * Marks the page, so that a test can tell whether it was loaded again.
 *
 * @param page The page.
 */
async function mark( page: Page ) {
	await page.evaluate( () => {
		( window as unknown as { cbuildStayed: boolean } ).cbuildStayed = true;
	} );
}

/**
 * Whether the page marked is the one still shown.
 *
 * @param page The page.
 */
const stayed = ( page: Page ): Promise< boolean > =>
	page.evaluate(
		() =>
			( window as unknown as { cbuildStayed?: boolean } ).cbuildStayed ===
			true
	);

test.describe( 'The builder with JavaScript', () => {
	test.beforeEach( async ( { page }, info ) => {
		test.skip(
			info.project.name === 'no-js',
			'The store needs JavaScript; the tests above run without it.'
		);
		await page.goto( fixture().page );
		await page.waitForLoadState( 'load' );
		await mark( page );
	} );

	test( 'moves from step to step without loading the page, and Back and Forward show the steps again', async ( {
		page,
	} ) => {
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		// The pick's own answer puts it in the page's address, so that Back comes back to it.
		await expect
			.poll( () => build( page ) )
			.toEqual( [ 'cbuild[deck]', 'cbuild[step]' ] );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		expect( await stayed( page ), 'no page was loaded' ).toBe( true );
		expect( build( page ) ).toEqual( [ 'cbuild[deck]', 'cbuild[step]' ] );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 139',
			} )
		).toBeChecked();

		await page.goBack();
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
		await page.goForward();
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		expect( await stayed( page ) ).toBe( true );

		await page
			.getByRole( 'link', { name: 'Back', exact: false } )
			.first()
			.click();
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'asks the route once for picks made in quick succession, and paints each pick before it answers', async ( {
		page,
	} ) => {
		const asked: URL[] = [];
		page.on( 'request', ( request ) => {
			if ( isRoute( request.url() ) ) {
				asked.push( new URL( request.url() ) );
			}
		} );
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => {
			release = done;
		} );
		await page.route( isRoute, async ( route ) => {
			await held;
			await route.continue();
		} );
		const first = page.getByRole( 'radio', {
			name: 'Choose Builder test deck 8.0',
		} );
		const second = page.getByRole( 'radio', {
			name: 'Choose Builder test deck 8.25',
		} );
		await first.check();
		await second.check();
		await expect(
			second,
			'painted while the route has not answered'
		).toBeChecked();
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'true'
		);
		await expect.poll( () => asked.length ).toBe( 1 );
		release();
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		expect( asked ).toHaveLength( 1 );
		expect( asked[ 0 ]!.searchParams.get( 'cbuild[deck]' ) ).toBe(
			String( id( 'builder-test-deck-8-25' ) )
		);
		expect( asked[ 0 ]!.searchParams.get( 'cbuild[step]' ) ).toBe( 'deck' );
		await expect( second ).toBeChecked();
		expect( new URL( page.url() ).searchParams.get( 'cbuild[deck]' ) ).toBe(
			String( id( 'builder-test-deck-8-25' ) )
		);
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'sends the form when the state route fails, and the server shows the next step', async ( {
		page,
	} ) => {
		await page.route( isRoute, ( route ) => route.abort() );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		await answered( page, () =>
			page.getByRole( 'button', { name: 'Next: Trucks' } ).click()
		);
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		expect(
			await stayed( page ),
			'the page was loaded, as without JavaScript'
		).toBe( false );
		expect( build( page ) ).toEqual( [
			'cbuild[_on]',
			'cbuild[deck]',
			'cbuild[step]',
		] );
	} );

	test( "puts focus on each new step's heading, says the step in the title, and says each pick once", async ( {
		page,
	} ) => {
		const status = page.locator( '.cbuild' ).getByRole( 'status' );
		await expect( status ).toHaveText( '' );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.25' } )
			.check();
		await expect(
			status,
			'the build and its total, after the pick'
		).toContainText( 'Total:' );
		await expect(
			page,
			'the page keeps its own title after a pick'
		).toHaveTitle( /^Build a complete/ );

		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		await expect( heading( page ) ).toBeFocused();
		await expect( page ).toHaveTitle( /^Step 2 of 7: Trucks - / );
		await expect(
			status,
			'the heading is read; the region says nothing'
		).toHaveText( '' );

		await page.goBack();
		await expect( heading( page ) ).toBeFocused();
		await expect( page ).toHaveTitle( /^Step 1 of 7: Deck - / );
	} );

	test( 'puts focus on the error summary when a step is asked for past an empty one, and says Error first', async ( {
		page,
	} ) => {
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		const summary = page.locator( '.cbuild-errors' );
		await expect( summary ).toBeVisible();
		await expect( summary ).toBeFocused();
		await expect( page ).toHaveTitle( /^Error: Step 1 of 7: Deck - / );
		await expect(
			summary.getByRole( 'link', {
				name: 'Deck: nothing is chosen yet.',
			} )
		).toBeVisible();
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'filters the step without loading the page', async ( { page } ) => {
		const filters = page.getByRole( 'group', { name: 'Width' } );
		await filters.getByRole( 'button', { name: '8.25 in (1)' } ).click();
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 1 );
		await expect(
			filters.getByRole( 'button', { name: '8.25 in (1)' } )
		).toHaveAttribute( 'aria-pressed', 'true' );
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[_size]' )
		).toBe( '8.25in' );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'a wider deck opens the change panel with focus on its heading, and Use these and Undo stay on the page', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'deck',
			} )
		);
		await page.waitForLoadState( 'load' );
		await mark( page );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
			.check();
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();

		const panel = page.locator( '.cbuild-changes' );
		const title = panel.getByRole( 'heading', {
			name: 'This change affects parts you chose',
		} );
		await expect( title ).toBeVisible();
		await expect( title ).toBeFocused();
		await expect( page.locator( '.cbuild [role="status"]' ) ).toHaveText(
			'1 part no longer fits.'
		);
		await expect(
			panel.getByText( 'Closest fit: Builder test trucks 149' )
		).toBeVisible();
		await expect( page.locator( '.cbuild-step' ) ).toBeHidden();

		await panel.getByRole( 'button', { name: 'Use these' } ).click();
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		await expect( heading( page ) ).toBeFocused();
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 149',
			} )
		).toBeChecked();
		await expect
			.poll( () =>
				new URL( page.url() ).searchParams.get( 'cbuild[deck]' )
			)
			.toBe( String( id( 'builder-test-deck-8-5' ) ) );

		await page.goBack();
		await expect( title ).toBeVisible();
		await panel.getByRole( 'button', { name: 'Undo' } ).click();
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'a wider deck picked and left opens the change panel as a step of its own, which Back leaves', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'deck',
			} )
		);
		await page.waitForLoadState( 'load' );
		await mark( page );
		const before = page.url();
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
			.check();

		const title = page.locator( '.cbuild-changes' ).getByRole( 'heading', {
			name: 'This change affects parts you chose',
		} );
		await expect( title ).toBeFocused();
		await expect( page ).toHaveTitle( /^Step 1 of 7: Deck - / );
		await expect( page.locator( '.cbuild [role="status"]' ) ).toHaveText(
			'1 part no longer fits.'
		);

		await page.goBack();
		await expect( page.locator( '.cbuild-step' ) ).toBeVisible();
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
		expect( page.url() ).toBe( before );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'starts from a preset without loading the page', async ( {
		page,
	} ) => {
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		await expect( heading( page ) ).toBeFocused();
		await expect( page.locator( '.cbuild-presets' ) ).toBeHidden();
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[preset]' )
		).toBe( '0' );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'adds the build without loading the page, tells the carts and the shop, and says so', async ( {
		page,
	} ) => {
		await page.evaluate( () => {
			const heard = window as unknown as { cbuildHeard: unknown[] };
			heard.cbuildHeard = [];
			document.body.addEventListener( 'wc-blocks_added_to_cart', () =>
				heard.cbuildHeard.push( 'wc-blocks_added_to_cart' )
			);
			document.addEventListener( 'cbuild:added', ( event ) =>
				heard.cbuildHeard.push( ( event as CustomEvent ).detail )
			);
		} );
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		// What the event is to say: the review form's own fields.
		const sent = await page
			.locator( '#cbuild-review-form' )
			.evaluate( ( form ) => {
				const picks: Record< string, number > = {};
				let assembly = false;
				for ( const [ name, value ] of new FormData(
					form as HTMLFormElement
				) ) {
					const slot = /^cbuild\[picks\]\[(\w+)\]$/.exec( name );
					if ( null !== slot ) {
						picks[ slot[ 1 ]! ] = Number( value );
					} else if ( 'cbuild[assembly]' === name ) {
						assembly = '1' === value;
					}
				}
				return { picks, assembly };
			} );
		expect( Object.keys( sent.picks ) ).toHaveLength( 6 );
		const added = page.waitForResponse(
			( response ) =>
				/wc(%2F|\/)store(%2F|\/)v1(%2F|\/)cart(%2F|\/)add-item/.test(
					response.url()
				) && response.request().method() === 'POST'
		);
		await page
			.locator( '.cbuild-review' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.click();
		expect( ( await added ).status() ).toBe( 201 );
		const said = page.locator( '.cbuild-review__added' );
		await expect( said ).toBeFocused();
		await expect( said ).toContainText( 'The build is in your cart.' );
		await expect(
			said.getByRole( 'link', { name: 'View cart' } )
		).toBeVisible();
		await expect(
			page.locator( '.cbuild' ).getByRole( 'status' )
		).toHaveText( 'The build is in your cart.' );
		expect( await stayed( page ) ).toBe( true );
		const heard = await page.evaluate(
			() =>
				( window as unknown as { cbuildHeard: unknown[] } ).cbuildHeard
		);
		expect( heard[ 0 ] ).toBe( 'wc-blocks_added_to_cart' );
		expect( heard[ 1 ] ).toEqual( {
			kit: fixture().kit,
			...sent,
		} );
		// The cart has the build: one count per build (D-009).
		await page.goto( '/cart-test-mini-cart/' );
		await expect(
			page.locator( 'main .wc-block-mini-cart__badge' )
		).toHaveText( '1', { timeout: 20_000 } );
	} );

	test( 'keeps its store private', async ( { page } ) => {
		const read = await page.evaluate( async () => {
			const name = '@wordpress/interactivity';
			const api = ( await import( /* @vite-ignore */ name ) ) as {
				store: ( namespace: string ) => unknown;
			};
			try {
				api.store( 'creative-skateboard-builder' );
				return 'read';
			} catch ( error ) {
				return String( error );
			}
		} );
		expect( read ).toMatch( /private store|lock/i );
	} );
} );
