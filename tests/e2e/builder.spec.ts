import { readFileSync } from 'node:fs';
import path from 'node:path';
import {
	test,
	expect,
	answered,
	inLocale,
	Page,
	type Locator,
} from './support/test';
import { axe } from './support/axe';

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

	test( "the review holds the build's link to copy, and no button that would do nothing", async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		await answered( page, () =>
			page
				.locator( '.cbuild-presets' )
				.getByRole( 'button', { name: 'Start from Street' } )
				.click()
		);
		const review = page.locator( '.cbuild-review' );
		const link = review.getByLabel( 'Link to this build' );
		await expect( link ).toHaveValue( page.url() );
		await expect( link ).toHaveAttribute( 'readonly', '' );
		await expect(
			review.getByRole( 'button', { name: 'Copy link' } )
		).toBeHidden();
		// The link opens the same build for anyone, signed in or not (P12).
		const other = await page.context().browser()!.newPage();
		await other.goto( await link.inputValue() );
		await expect( other.locator( '.cbuild-review' ) ).toBeVisible();
		await other.close();
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

	test( "describes each card's radio by the rest of its card, on a step the store drew (Accessibility)", async ( {
		page,
	} ) => {
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toHaveAccessibleDescription( /\+\$\d/ );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
		expect( await stayed( page ), 'the store drew the step' ).toBe( true );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 139',
			} )
		).toHaveAccessibleDescription(
			/Fits your 8(\.0)? in deck.*Recommended for your 8(\.0)? in deck/
		);
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

	test( 'shows the pending state only once an answer has kept the shopper waiting 300 ms (Resilience)', async ( {
		page,
	} ) => {
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => {
			release = done;
		} );
		await page.route( isRoute, async ( route ) => {
			await held;
			await route.continue();
		} );
		const total = page.locator( '.cbuild-summary__total' );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		// Read in the page, so that the test's own round trips do not move the moments.
		const seen = await total.evaluate( ( element ) => {
			const opacity = () => getComputedStyle( element ).opacity;
			const at = ( ms: number ) =>
				new Promise< string >( ( done ) =>
					setTimeout( () => done( opacity() ), ms )
				);
			return Promise.all( [ at( 50 ), at( 600 ) ] );
		} );
		release();
		expect( seen, 'no flicker for a quick answer, then dimmed' ).toEqual( [
			'1',
			'0.6',
		] );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect( total ).toHaveCSS( 'opacity', '1' );
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

	test( 'filters the step without loading the page', async ( {
		page,
	}, info ) => {
		const filters = page.getByRole( 'group', { name: 'Width' } );
		// French writes the width with a decimal comma.
		const width = inLocale( info.project.name, '8.25 in (1)' );
		await filters.getByRole( 'button', { name: width } ).click();
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 1 );
		await expect(
			filters.getByRole( 'button', { name: width } )
		).toHaveAttribute( 'aria-pressed', 'true' );
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[_size]' )
		).toBe( '8.25in' );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'keeps focus on a filter and on the assembly switch, and says the new total (Accessibility)', async ( {
		page,
	}, info ) => {
		const filters = page.getByRole( 'group', { name: 'Width' } );
		const width = filters.getByRole( 'button', {
			name: inLocale( info.project.name, '8.25 in (1)' ),
		} );
		await width.focus();
		await page.keyboard.press( 'Enter' );
		await expect( width ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( width, 'the shopper keeps their place' ).toBeFocused();

		const toggle = page.locator( '.cbuild-summary__assembly a' );
		test.skip(
			! ( await toggle.isVisible() ),
			"On a phone the bar takes the summary's place, and has no switch."
		);
		const before = await toggle.textContent();
		await toggle.focus();
		await page.keyboard.press( 'Enter' );
		await expect( toggle ).not.toHaveText( before ?? '' );
		await expect( toggle ).toBeFocused();
		await expect(
			page.locator( '.cbuild' ).getByRole( 'status' )
		).toContainText( /\d/ );
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
		// The cart is empty, and the page has the styles a build in its Mini-Cart is grouped by (D-132, D-158).
		await expect(
			page.locator( 'style#cbuild-cart-inline-css' )
		).toHaveCount( 1 );
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
		// The review's own, or the phone bar's where the review's is hidden: one at each width (P11).
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.click();
		expect( ( await added ).status() ).toBe( 201 );
		const said = page.locator( '.cbuild-review__added' );
		await expect( said ).toBeFocused();
		await expect( said ).toContainText( 'The build is in your cart.' );
		await expect(
			said.getByRole( 'link', { name: 'View cart' } )
		).toBeVisible();
		// Said once, by the notice that takes focus: the status says nothing more.
		await expect(
			page.locator( '.cbuild' ).getByRole( 'status' )
		).toHaveText( '' );
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

	test( "copies the build's link and says so", async ( {
		page,
		context,
		browserName,
	} ) => {
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		if ( 'chromium' === browserName ) {
			await context.grantPermissions( [
				'clipboard-read',
				'clipboard-write',
			] );
		}
		await page
			.locator( '.cbuild-review' )
			.getByRole( 'button', { name: 'Copy link' } )
			.click();
		await expect(
			page.locator( '.cbuild' ).getByRole( 'status' )
		).toHaveText( 'Link copied.' );
		if ( 'chromium' === browserName ) {
			// Only Chromium lets a test read the clipboard back. The page's own address, but for the preset it was
			// started from, which a shared link leaves out (BuildLink::shared(), audit 15).
			const shared = new URL( page.url() );
			expect( shared.searchParams.has( 'cbuild[preset]' ) ).toBe( true );
			shared.searchParams.delete( 'cbuild[preset]' );
			expect(
				await page.evaluate( () => navigator.clipboard.readText() )
			).toBe( shared.toString() );
		}
	} );

	test( 'paints a pick within 100 ms on a CPU slowed four times (Quality bar)', async ( {
		page,
	}, info ) => {
		test.skip(
			! info.project.name.startsWith( 'chromium' ),
			'Only Chromium can slow its CPU (DevTools protocol).'
		);
		const devtools = await page.context().newCDPSession( page );
		await devtools.send( 'Emulation.setCPUThrottlingRate', { rate: 4 } );
		// From the input to the next paint, as the browser measures an interaction (Event Timing): the handlers, the
		// store's pick and the chosen card painted. An interaction under 16 ms is not reported at all.
		await page.evaluate( () => {
			const seen = window as unknown as { interactions: number[] };
			seen.interactions = [];
			new PerformanceObserver( ( list ) => {
				for ( const entry of list.getEntries() as PerformanceEventTiming[] ) {
					if ( entry.interactionId ) {
						seen.interactions.push( entry.duration );
					}
				}
			} ).observe( {
				type: 'event',
				durationThreshold: 16,
				buffered: true,
			} as PerformanceObserverInit );
		} );
		for ( const name of [
			'Choose Builder test deck 8.0',
			'Choose Builder test deck 8.25',
			'Choose Builder test deck 8.5',
		] ) {
			await page.getByRole( 'radio', { name } ).click();
			await expect( page.getByRole( 'radio', { name } ) ).toBeChecked();
			await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
				'aria-busy',
				'false'
			);
		}
		// The observer reports on the frame after; give it two.
		await page.evaluate(
			() =>
				new Promise( ( done ) =>
					requestAnimationFrame( () => requestAnimationFrame( done ) )
				)
		);
		const durations = await page.evaluate(
			() =>
				( window as unknown as { interactions: number[] } ).interactions
		);
		await devtools.send( 'Emulation.setCPUThrottlingRate', { rate: 1 } );
		test.info().annotations.push( {
			type: 'interactions (ms)',
			description: durations.join( ', ' ) || 'all under 16',
		} );
		expect( Math.max( 0, ...durations ) ).toBeLessThanOrEqual( 100 );
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

	test( 'passes axe on every state it shows (Accessibility: axe on every state)', async ( {
		page,
	}, info ) => {
		const region = '.cbuild';
		const check = ( state: string ) =>
			axe( page, `${ info.project.name }: ${ state }`, region );

		// The blank start: presets, the filters and the deck step.
		await check( 'the blank start' );

		// Filtered.
		const filters = page.getByRole( 'group', { name: 'Width' } );
		await filters
			.getByRole( 'button', {
				name: inLocale( info.project.name, '8.25 in (1)' ),
			} )
			.click();
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 1 );
		await check( 'a filtered step' );

		// The error summary, asked past an empty step.
		await page.goto( fixture().page );
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.first()
			.click();
		await expect( page.locator( '.cbuild-errors' ) ).toBeFocused();
		// Playwright's Firefox crashes in axe on this state once the store has drawn it, though the same state served
		// passes there and passes in the other engines (issue #36, D-167).
		if ( info.project.name.startsWith( 'firefox' ) ) {
			test.info().annotations.push( {
				type: 'quarantined',
				description: 'axe on the error summary in Firefox: issue #36',
			} );
		} else {
			await check( 'the error summary' );
		}

		// A later step, with the parts that do not fit under their disclosure, open.
		await page.goto( linkTo( { deck: id( 'builder-test-deck-8-5' ) } ) );
		const hidden = page.locator( '.cbuild-hidden' );
		await hidden.locator( 'summary' ).click();
		await expect( hidden.getByRole( 'link' ).first() ).toBeVisible();
		await check( 'a step with picks and its hidden parts open' );

		// The change panel.
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
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.first()
			.click();
		await expect(
			page.locator( '.cbuild-changes' ).getByRole( 'heading', {
				name: 'This change affects parts you chose',
			} )
		).toBeVisible();
		await check( 'the change panel' );

		// The review, from a preset, and the build added.
		await page.goto( fixture().page );
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		await check( 'the review' );
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.first()
			.click();
		await expect( page.locator( '.cbuild-review__added' ) ).toBeVisible();
		await check( 'the build added' );
	} );
} );

test.describe( 'The keyboard alone', () => {
	test.beforeEach( async ( { context, baseURL } ) => {
		// The shop's cart and checkout pages are the classic ones, a form a page the keyboard walks without a script.
		await context.addCookies( [
			{
				name: 'cbuild_e2e_classic',
				value: '1',
				url: baseURL || 'http://localhost:8898',
			},
		] );
	} );

	test( 'builds a board from a blank page and places its order (Accessibility: a keyboard-only build)', async ( {
		page,
		browserName,
	} ) => {
		// WebKit leaves links out of Tab's order unless Option is held, as Safari does.
		const tab = browserName === 'webkit' ? 'Alt+Tab' : 'Tab';
		const reach = async ( target: Locator, limit = 300 ) => {
			for ( let i = 0; i < limit; i++ ) {
				// A form sent without a script loads the next page; Tab waits for it, as a shopper would.
				await page.waitForLoadState( 'load' );
				const there = await target
					.evaluateAll( ( found ) =>
						found.some(
							( element ) =>
								element === element.ownerDocument.activeElement
						)
					)
					.catch( () => false );
				if ( there ) {
					return;
				}
				await page.keyboard.press( tab );
			}
			throw new Error( `Tab never reached ${ String( target ) }.` );
		};
		const press = async ( target: Locator, key = 'Enter' ) => {
			await reach( target );
			await page.keyboard.press( key );
		};

		await page.goto( fixture().page );
		await expect( heading( page ) ).toHaveText( 'Step 1 of 7: Deck' );
		const decks = page.getByRole( 'radio', {
			name: /^Choose Builder test deck/,
		} );
		await press( decks, 'Space' );
		await expect( decks.and( page.locator( ':checked' ) ) ).toHaveCount(
			1
		);

		const builder = page.locator( '.cbuild' );
		for ( let step = 0; step < 12; step++ ) {
			const now = ( await heading( page ).textContent() ) ?? '';
			if ( now.endsWith( ': Review' ) ) {
				break;
			}
			await press( builder.getByRole( 'button', { name: /^Next: / } ) );
			await expect( heading( page ) ).not.toHaveText( now );
		}
		await expect( heading( page ) ).toHaveText( /: Review$/ );

		await press( builder.getByRole( 'button', { name: 'Add to cart' } ) );
		await press(
			// The builder's own link with a script; without one, WooCommerce's notice over the page has it.
			page.getByRole( 'link', { name: /^View cart/ } )
		);
		await expect( page ).toHaveURL( /cart-test-classic-cart/ );
		// By its class: its words are WooCommerce's, in the shop's language.
		await press( page.locator( 'a.checkout-button' ) );
		await expect( page ).toHaveURL( /cart-test-classic-checkout/ );

		for ( const [ field, value ] of Object.entries( {
			billing_first_name: 'Sam',
			billing_last_name: 'Shopper',
			billing_address_1: '1 Test Street',
			billing_city: 'Testville',
			billing_postcode: '90210',
			billing_phone: '5550100',
			billing_email: 'shopper+2@example.com',
		} ) ) {
			// WooCommerce covers the form while it works the totals out again; a shopper waits for it.
			await expect(
				page.locator( '.woocommerce-checkout .blockUI' )
			).toHaveCount( 0 );
			const input = page.locator( `#${ field }` );
			await reach( input );
			await expect( input ).toBeFocused();
			// At a person's pace: selectWoo opens the country's list a moment after Tab passes it, and at machine speed
			// the keys land in its search instead.
			await page.keyboard.type( value, { delay: 30 } );
			await expect( input ).toHaveValue( value );
		}
		await expect(
			page.locator( '.woocommerce-checkout .blockUI' )
		).toHaveCount( 0 );
		const cheque = page.locator( '#payment_method_cheque' );
		if ( ! ( await cheque.isChecked() ) ) {
			await press( cheque, 'Space' );
		}
		await press( page.locator( '#place_order' ) );

		await page.waitForURL( /order-received/ );
		await expect(
			page.locator( '.woocommerce-table--order-details' )
		).toContainText( 'Builder test kit' );
	} );
} );

test.describe( "The builder's styles", () => {
	/**
	 * Whether anything in the builder is wider than the builder, or than the page.
	 *
	 * @param page The page.
	 */
	const overflows = ( page: Page ) =>
		page.locator( '.cbuild' ).evaluate( ( root ) => {
			const wide: string[] = [];
			const edge = root.getBoundingClientRect().right + 1;
			for ( const node of root.querySelectorAll< HTMLElement >( '*' ) ) {
				const box = node.getBoundingClientRect();
				if ( box.width > 0 && box.right > edge ) {
					wide.push( node.className || node.tagName );
				}
			}
			if ( root.scrollWidth > root.clientWidth + 1 ) {
				wide.push( 'the builder scrolls sideways' );
			}
			return wide;
		} );

	test( 'reflows at 320 CSS pixels with nothing scrolling sideways', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 320, height: 640 } );
		await page.goto( fixture().page );
		expect( await overflows( page ) ).toEqual( [] );
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
		expect( await overflows( page ) ).toEqual( [] );
	} );

	test( 'gives every control a target of at least 24 by 24 CSS pixels', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		const small = await page.locator( '.cbuild' ).evaluate( ( root ) => {
			const found: string[] = [];
			for ( const node of root.querySelectorAll< HTMLElement >(
				'button, input:not([type="hidden"]), summary, .cbuild-progress__link, .cbuild-card__page'
			) ) {
				const box = node.getBoundingClientRect();
				if ( box.width > 0 && ( box.width < 24 || box.height < 24 ) ) {
					found.push(
						`${ node.className || node.tagName } ${ box.width }x${ box.height }`
					);
				}
			}
			return found;
		} );
		expect( small ).toEqual( [] );
	} );

	test( 'keeps every word in view with the text spacing WCAG 1.4.12 asks a page to take', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			'The spacing is put in by a script; the styles are the same without one.'
		);
		await page.goto( fixture().page );
		await page.addStyleTag( {
			content:
				'.cbuild * { line-height: 1.5 !important; letter-spacing: 0.12em !important; word-spacing: 0.16em !important; } .cbuild p { margin-block-end: 2em !important; }',
		} );
		expect( await overflows( page ) ).toEqual( [] );
		const clipped = await page.locator( '.cbuild' ).evaluate( ( root ) => {
			const found: string[] = [];
			for ( const node of root.querySelectorAll< HTMLElement >(
				'button, label, legend, .cbuild-card__name'
			) ) {
				if (
					node.getBoundingClientRect().width > 0 &&
					( node.scrollHeight > node.clientHeight + 1 ||
						node.scrollWidth > node.clientWidth + 1 )
				) {
					found.push( node.className || node.tagName );
				}
			}
			return found;
		} );
		expect( clipped ).toEqual( [] );
	} );

	test( 'says the chosen card and the focus by a border and an outline in forced colours', async ( {
		page,
		browserName,
	} ) => {
		test.skip(
			'chromium' !== browserName,
			'Only Chromium lets a test turn forced colours on.'
		);
		await page.emulateMedia( { forcedColors: 'active' } );
		await page.goto( fixture().page );
		const cards = page.locator( '.cbuild-card' );
		await cards.first().locator( 'label' ).click();
		const width = ( index: number ) =>
			cards
				.nth( index )
				.evaluate( ( card ) =>
					parseFloat( getComputedStyle( card ).borderTopWidth )
				);
		expect( await width( 0 ) ).toBeGreaterThanOrEqual( 3 );
		expect( await width( 1 ) ).toBeLessThan( 3 );
		// Next in either direction of text; ArrowRight is the previous radio right to left.
		await page.keyboard.press( 'ArrowDown' );
		const ring = await cards
			.nth( 1 )
			.evaluate( ( card ) => getComputedStyle( card ).outlineStyle );
		expect( ring ).toBe( 'solid' );
	} );
} );

test.describe( 'Motion', () => {
	type Seen = {
		name: string;
		duration: number;
		iterations: number;
		properties: string[];
		pseudo: string;
	};

	/**
	 * Watch the page for a second: every animation it runs, frame by frame, and each view transition started.
	 *
	 * @param page The page.
	 */
	const watch = ( page: Page ) =>
		page.evaluate( () => {
			const record = window as unknown as {
				seen: Map< Animation, unknown >;
				transitions: number;
				watched: boolean;
			};
			record.seen = new Map();
			record.transitions = 0;
			record.watched = false;
			const start = document.startViewTransition?.bind( document );
			if ( start ) {
				document.startViewTransition = ( ( update: () => void ) => {
					record.transitions++;
					return start( update );
				} ) as typeof document.startViewTransition;
			}
			const until = performance.now() + 1500;
			const frame = () => {
				for ( const animation of document.getAnimations() ) {
					const effect = animation.effect as KeyframeEffect | null;
					record.seen.set( animation, {
						name: ( animation as CSSAnimation ).animationName ?? '',
						duration: Number(
							effect?.getComputedTiming().duration ?? 0
						),
						iterations: Number(
							effect?.getComputedTiming().iterations ?? 0
						),
						properties: [
							...new Set(
								( effect?.getKeyframes() ?? [] ).flatMap(
									( keyframe ) =>
										Object.keys( keyframe ).filter(
											( key ) =>
												! [
													'offset',
													'computedOffset',
													'easing',
													'composite',
												].includes( key )
										)
								)
							),
						],
						pseudo: effect?.pseudoElement ?? '',
					} );
				}
				if ( performance.now() < until ) {
					window.requestAnimationFrame( frame );
				} else {
					record.watched = true;
				}
			};
			window.requestAnimationFrame( frame );
		} );

	const seen = async ( page: Page ) => {
		await expect
			.poll( () =>
				page.evaluate(
					() => ( window as unknown as { watched: boolean } ).watched
				)
			)
			.toBe( true );
		return page.evaluate( () => {
			const record = window as unknown as {
				seen: Map< Animation, Seen >;
				transitions: number;
			};
			return {
				animations: [ ...record.seen.values() ],
				transitions: record.transitions,
			};
		} );
	};

	const pickAndStep = async ( page: Page ) => {
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.first()
			.click();
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
	};

	test.beforeEach( async ( { page }, info ) => {
		test.skip(
			info.project.name === 'no-js',
			"The view transition is the store's; the card settles the same without it."
		);
		await page.goto( fixture().page );
		await page.waitForLoadState( 'load' );
	} );

	test( 'settles the chosen card and crosses steps over within 150 to 250 ms, moving only transform and opacity', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await watch( page );
		await pickAndStep( page );
		const { animations, transitions } = await seen( page );

		expect( animations.map( ( animation ) => animation.name ) ).toContain(
			'cbuild-settle'
		);
		if (
			await page.evaluate(
				() => 'function' === typeof document.startViewTransition
			)
		) {
			expect( transitions, 'the step crossed over' ).toBeGreaterThan( 0 );
		}
		for ( const animation of animations ) {
			const what = `${ animation.name } ${ animation.pseudo }`;
			expect( animation.duration, what ).toBeGreaterThanOrEqual( 150 );
			expect( animation.duration, what ).toBeLessThanOrEqual( 250 );
			expect( animation.iterations, what ).toBe( 1 );
			if ( animation.name.startsWith( 'cbuild-' ) ) {
				expect(
					animation.properties.filter(
						( property ) =>
							! [ 'transform', 'opacity' ].includes( property )
					),
					what
				).toEqual( [] );
			}
		}
	} );

	test( 'moves nothing where the shopper asks for reduced motion', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await watch( page );
		await pickAndStep( page );
		const { animations, transitions } = await seen( page );
		expect( animations ).toEqual( [] );
		expect( transitions ).toBe( 0 );
	} );
} );

test.describe( 'The phone bar', () => {
	const PHONE = { width: 390, height: 700 };

	test( 'takes the place of the summary on a phone, and the summary has the desktop', async ( {
		page,
	} ) => {
		await page.setViewportSize( PHONE );
		await page.goto( fixture().page );
		const bar = page.locator( '.cbuild-bar' );
		await expect( bar ).toBeVisible();
		await expect( page.locator( '.cbuild-summary' ) ).toBeHidden();
		await page.setViewportSize( { width: 1280, height: 800 } );
		await expect( bar ).toBeHidden();
		await expect( page.locator( '.cbuild-summary' ) ).toBeVisible();
	} );

	for ( const theme of [ 'storefront', 'kadence', 'twentytwentyfive' ] ) {
		test( `keeps its words readable on ${ theme }'s dark colours, and its open parts list too`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				info.project.name === 'no-js',
				"axe runs as a script in the page; the bar's colours are its stylesheet's, the same with JavaScript."
			);
			// The e2e theme mu-plugin answers these for this request alone (D-162).
			const url = baseURL || 'http://localhost:8898';
			await context.addCookies( [
				{ name: 'cbuild_e2e_theme', value: theme, url },
				{ name: 'cbuild_e2e_scheme', value: 'dark', url },
			] );
			await page.setViewportSize( PHONE );
			await page.goto( fixture().page );
			const bar = page.locator( '.cbuild-bar' );
			await expect( bar ).toBeVisible();
			await axe(
				page,
				`the phone bar on ${ theme }, dark`,
				'.cbuild-bar'
			);
			await bar.locator( 'summary' ).click();
			await axe(
				page,
				`the phone bar's parts list on ${ theme }, dark`,
				'.cbuild-bar'
			);
		} );
	}

	for ( const theme of [ 'storefront', 'kadence', 'twentytwentyfive' ] ) {
		test( `stays at the foot of the screen on ${ theme }, with nothing of the theme's over it`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				info.project.name === 'no-js' && theme === 'storefront',
				"Storefront's hidden overflow is undone by the builder's script; without it the bar stays at the builder's end (D-163)."
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || 'http://localhost:8898',
				},
			] );
			await page.setViewportSize( PHONE );
			await page.goto( fixture().page );
			await expect( page.locator( '.cbuild-bar' ) ).toBeVisible();
			// Halfway down the builder, with the builder above and below the screen.
			await page.locator( '.cbuild' ).evaluate( ( root ) => {
				const box = root.getBoundingClientRect();
				window.scrollTo(
					0,
					window.scrollY + box.top + box.height / 2 - innerHeight / 2
				);
			} );
			await expect
				.poll( () =>
					page.locator( '.cbuild-bar' ).evaluate( ( bar ) => {
						const box = bar.getBoundingClientRect();
						const onScreen =
							box.top >= 0 && box.bottom <= innerHeight + 1;
						const points = [ 0.25, 0.5, 0.75 ].flatMap( ( x ) =>
							[ 0.2, 0.8 ].map( ( y ) =>
								document.elementFromPoint(
									box.left + box.width * x,
									box.top + box.height * y
								)
							)
						);
						return {
							onScreen,
							covered: points.filter(
								( node ) => ! node || ! bar.contains( node )
							).length,
						};
					} )
				)
				.toEqual( { onScreen: true, covered: 0 } );
		} );
	}

	test( 'opens the parts list above itself, and its button moves the build on', async ( {
		page,
	} ) => {
		await page.setViewportSize( PHONE );
		await page.goto( fixture().page );
		const bar = page.locator( '.cbuild-bar' );
		await page.locator( '.cbuild-card label' ).first().click();
		await bar.locator( 'summary' ).click();
		const lines = bar.locator( '.cbuild-bar__lines' );
		await expect( lines ).toBeVisible();
		const [ list, own ] = await Promise.all( [
			lines.boundingBox(),
			bar.boundingBox(),
		] );
		expect( list!.y + list!.height ).toBeLessThanOrEqual( own!.y + 1 );
		// With JavaScript the store moves the step without a page load, and without it the form is sent.
		await bar.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText( 'Step 2 of 7: Trucks' );
	} );

	test( 'never covers the control in focus', async ( { page } ) => {
		await page.setViewportSize( PHONE );
		await page.goto( fixture().page );
		const bar = page.locator( '.cbuild-bar' );
		await page.locator( '.cbuild__heading' ).focus();
		const covered: string[] = [];
		for ( let tab = 0; tab < 40; tab++ ) {
			await page.keyboard.press( 'Tab' );
			const seen = await page.evaluate( () => {
				const own = document.querySelector( '.cbuild-bar' );
				const focused = own?.ownerDocument
					.activeElement as HTMLElement | null;
				if (
					! focused?.closest( '.cbuild' ) ||
					! own ||
					own.contains( focused )
				) {
					return null;
				}
				const box = focused.getBoundingClientRect();
				const top = own.getBoundingClientRect().top;
				return box.bottom > top + 1
					? `${ focused.className || focused.tagName } ${ box.bottom } > ${ top }`
					: '';
			} );
			if ( null === seen ) {
				continue;
			}
			if ( '' !== seen ) {
				covered.push( seen );
			}
		}
		expect( covered ).toEqual( [] );
		await expect( bar ).toBeVisible();
	} );

	test( "stops where the builder ends, and never covers the theme's footer", async ( {
		page,
	} ) => {
		await page.setViewportSize( PHONE );
		await page.goto( fixture().page );
		await page.evaluate( () =>
			window.scrollTo( 0, document.documentElement.scrollHeight )
		);
		const [ bar, root ] = await Promise.all( [
			page.locator( '.cbuild-bar' ).boundingBox(),
			page.locator( '.cbuild' ).boundingBox(),
		] );
		expect( bar!.y + bar!.height ).toBeLessThanOrEqual(
			root!.y + root!.height + 1
		);
	} );
} );

test.describe( 'Every control a name of its own (P11)', () => {
	for ( const [ width, height ] of [
		[ 390, 700 ],
		[ 1280, 800 ],
	] as const ) {
		test( `at ${ width } pixels, on a step and at the review`, async ( {
			page,
		} ) => {
			await page.setViewportSize( { width, height } );
			await page.goto( fixture().page );
			const names = () =>
				page
					.locator( '.cbuild' )
					.getByRole( 'button' )
					.evaluateAll( ( buttons ) =>
						buttons
							.filter(
								( button ) =>
									( button as HTMLElement ).getClientRects()
										.length > 0
							)
							.map( ( button ) =>
								(
									button.getAttribute( 'aria-label' ) ??
									button.textContent ??
									''
								).trim()
							)
					);
			const twice = ( list: string[] ) =>
				list.filter( ( name, at ) => list.indexOf( name ) !== at );
			expect( twice( await names() ) ).toEqual( [] );
			await page
				.locator( '.cbuild-presets' )
				.getByRole( 'button', { name: 'Start from Street' } )
				.click();
			await expect( heading( page ) ).toHaveText( 'Step 7 of 7: Review' );
			expect( twice( await names() ) ).toEqual( [] );
			await expect(
				page.getByRole( 'button', { name: 'Add to cart' } )
			).toHaveCount( 1 );
		} );
	}
} );
