import { readFileSync } from 'node:fs';
import path from 'node:path';
import {
	test,
	expect,
	answered,
	inLocale,
	noticeFocusDone,
	Page,
	type Locator,
} from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * Milestone 6, without JavaScript (spec: The builder, Without JavaScript and Ways in): each step is a GET form whose
 * picks travel in the address, the change panel and the review are steps of their own, and Add to cart posts to
 * WooCommerce's own handler. Every way in lands where the spec says. These run in the no-js project; with
 * JavaScript, the store takes each move through the state route without loading the page (D-148), tested after them.
 */

interface Fixture {
	kit: number;
	page: string;
	drawer: string;
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
 * Pick a part on the step shown, by its card's radio. Nothing is picked for the shopper (D-213), so a walk to the
 * review picks each part.
 *
 * @param page The page.
 * @param name The part's product name.
 */
async function choose( page: Page, name: string ) {
	await page.getByRole( 'radio', { name: `Choose ${ name }` } ).check();
}

/**
 * Press a width on the deck step, as a shopper does before the decks are listed (D-241), and wait for its decks.
 *
 * @param page  The page.
 * @param width The width as its button says it, such as 8.25″.
 */
async function ride( page: Page, width: string ) {
	const button = page.locator( '.cbuild-width__option', {
		has: page.locator( '.cbuild-chip__text', {
			hasText: new RegExp( `^${ width.replace( '.', '\\.' ) }$` ),
		} ),
	} );
	await button.click();
	await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
	// Without JavaScript the page is the server's, with no aria-busy at all.
	await expect( page.locator( '.cbuild' ) ).not.toHaveAttribute(
		'aria-busy',
		'true'
	);
}

/**
 * Pick a deck, pressing its width first where the step lists another (D-241).
 *
 * @param page  The page.
 * @param width The deck's width as its name ends: 8.0, 8.25 or 8.5.
 */
async function pickDeck( page: Page, width: '8.0' | '8.25' | '8.5' ) {
	const radio = page.getByRole( 'radio', {
		name: `Choose Builder test deck ${ width }`,
	} );
	if ( ! ( await radio.isVisible() ) ) {
		await ride( page, `${ '8.0' === width ? '8' : width }″` );
	}
	await radio.check();
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

/**
 * Wait for the first deck's pick to be answered, as a shopper sees the page settle before pressing Next. The answer
 * brings the picture in and takes the presets away, so Next moves: on a slow runner a click pressed before it and let
 * go after was lost (CI run 43520699158, WebKit).
 *
 * @param page The page.
 */
async function settled( page: Page ): Promise< void > {
	await expect
		.poll( () => build( page ) )
		.toEqual( [ 'cbuild[deck]', 'cbuild[step]', 'cbuild[_width]' ] );
}

test.describe( 'The builder without JavaScript', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			info.project.name !== 'no-js',
			'With JavaScript, the store moves the builder: the tests after these.'
		);
	} );

	// Milestone 6's audit 29: the assembly switch was a link into a state. It is a button in a GET form, so a crawler
	// never follows it (spec: SEO and sharing), and the review stays where it was. It is on the review (D-271).
	// Spec (configurator): The review and Risers (D-246). "Add risers" sits under the parts when the build has none,
	// and opens the step though nothing asks for it.
	test( 'Add risers at the review opens the risers step for a build that has none', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				grip: id( 'builder-test-grip' ),
				step: 'review',
			} )
		);
		await page.getByRole( 'link', { name: 'Add risers' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 4 of 8: Choose your risers'
		);
		await expect(
			page.getByRole( 'radio', { name: 'No risers' } )
		).toBeVisible();
	} );

	test( 'switches assembly with a button in a form, on the review', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				grip: id( 'builder-test-grip' ),
				step: 'review',
			} )
		);
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		const assembly = page.locator( '.cbuild-review__assembly' );
		await expect( assembly.locator( 'a' ) ).toHaveCount( 0 );
		const before = ( await assembly.textContent() ) ?? '';
		await assembly.getByRole( 'button' ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		expect(
			new URL( page.url() ).searchParams.has( 'cbuild[assembly]' )
		).toBe( true );
		await expect( assembly ).not.toHaveText( before );
	} );

	test( 'goes from a blank page to the cart, one GET form a step', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect(
			page.getByText( 'Start with a width.', { exact: false } )
		).toBeVisible();
		const decks = page.getByRole( 'radio', {
			name: /^Choose Builder test deck/,
		} );
		// The width first (D-241): no deck is listed until one is pressed, a button of the step's GET form.
		await expect( page.locator( '.cbuild-width__option' ) ).toHaveCount(
			3
		);
		await expect( decks ).toHaveCount( 0 );
		await ride( page, '8″' );
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect( decks ).toHaveCount( 1 );
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
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		// The kit's own setting decides assembly until the shopper changes it, so the address leaves it out. The
		// browser writes what the form sent, the step it was on among it (D-150).
		expect( build( page ) ).toEqual( [
			'cbuild[_on]',
			'cbuild[_width]',
			'cbuild[deck]',
			'cbuild[step]',
		] );
		const trucks = page.getByRole( 'radio', {
			name: 'Choose Builder test trucks 139',
		} );
		// Nothing is picked for the shopper (D-213): the trucks that fit are labelled, not chosen.
		await expect( trucks ).not.toBeChecked();
		await expect(
			page.getByText( 'Recommended for your 8″ deck' )
		).toBeVisible();
		await trucks.check();

		await next( page, 'Next: Wheels' );
		await expect( heading( page ) ).toHaveText(
			'Step 3 of 7: Choose your wheels'
		);
		// Sold out, the part is folded under "Show N sold out" (spec: Only what fits, then the sold-out fold).
		await page.locator( '.cbuild-sold-out summary' ).click();
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
			'58 mm wheels want a riser under these trucks, so the risers step appears.'
		).toHaveText( 'Step 4 of 8: Choose your risers' );
		const riser = page.getByRole( 'radio', {
			name: 'Choose Builder test risers 1/4',
		} );
		await expect(
			riser,
			'A riser is recommended, and not picked for the shopper.'
		).not.toBeChecked();
		await expect(
			page.getByRole( 'radio', { name: 'No risers' } )
		).toBeChecked();
		await riser.check();

		await next( page, 'Next: Bearings' );
		await expect( heading( page ) ).toHaveText(
			'Step 5 of 8: Choose your bearings'
		);
		await page
			.getByRole( 'radio', { name: 'Choose Builder test bearings' } )
			.check();
		await next( page, 'Next: Hardware' );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test bolts 1.25' } )
			.check();
		await next( page, 'Next: Grip tape' );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test grip' } )
			.check();
		await next( page, 'Next: Review' );
		await expect( heading( page ) ).toHaveText(
			'Step 8 of 8: Check your build'
		);

		const review = page.locator( '.cbuild-review' );
		await expect(
			review.getByText( 'Builder test deck 8.0' )
		).toBeVisible();
		await expect( review.getByText( 'Two business days' ) ).toBeVisible();
		await expect(
			review.getByText( 'An assembled board comes back as parts.' )
		).toBeVisible();
		await expect(
			review.getByRole( 'link', { name: 'Change trucks' } )
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
		await pickDeck( page, '8.5' );
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
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
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
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
	} );

	// Spec: The deck's width first. The step opens on its question alone: no count of decks, and no frame round none.
	test( 'opens the deck step on the width question alone, with no count until a width is picked', async ( {
		page,
	}, info ) => {
		await page.goto( fixture().page );
		await expect( page.locator( '.cbuild-width' ) ).toBeVisible();
		await expect( page.locator( '.cbuild-step__fieldset' ) ).toBeHidden();
		await expect(
			page.locator( '.cbuild-step__count:visible' )
		).toHaveCount( 0 );

		await ride( page, inLocale( info.project.name, '8.25″' ) );
		await expect( page.locator( '.cbuild-step__fieldset' ) ).toBeVisible();
		// "Showing 1 of 1", in any language.
		await expect(
			page.locator( '.cbuild-step__count:visible' ).first()
		).toHaveText( /1\D+1/ );
	} );

	test( 'a width pressed lists only its decks and stays on the step, and another width lists its own (D-241)', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		const decks = page.getByRole( 'radio', {
			name: /^Choose Builder test deck/,
		} );
		await answered( page, () => ride( page, '8.25″' ) );
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect( decks ).toHaveCount( 1 );
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.25' } )
		).toBeVisible();

		await answered( page, () => ride( page, '8.5″' ) );
		await expect( decks ).toHaveCount( 1 );
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
		).toBeVisible();
		await expect(
			page.locator( '.cbuild-width__option[aria-pressed="true"]' )
		).toHaveCount( 1 );
	} );

	test( "a deck's width stays on the deck step and Next leaves no filter behind", async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		await answered( page, () => ride( page, '8″' ) );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
			.check();
		await next( page, 'Next: Trucks' );
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 139',
			} ),
			'nothing is picked for the shopper (D-213)'
		).not.toBeChecked();
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
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		await expect( page.locator( '.cbuild-errors' ) ).toBeHidden();
		const review = page.locator( '.cbuild-review' );
		await expect( review.locator( '.cbuild-review__assembly' ) ).toHaveText(
			'Assembly: $15.00'
		);
		await expect( review.locator( '.cbuild-review__total' ) ).toHaveText(
			'Total: $182.00'
		);
		// All before Add to cart, in the page's order (spec: Review and add to cart). Save build, from 0.8.0, comes
		// after it for a signed-in customer, and is not what this order is about.
		expect(
			await review.evaluate( ( section ) =>
				[
					...section.querySelectorAll(
						'.cbuild-review__total, button[type="submit"].cbuild-button--primary'
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
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
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
		// The page's address, but for the preset it started from: a shared link carries no preset (audit 15).
		const shared = new URL( page.url() );
		shared.searchParams.delete( 'cbuild[preset]' );
		await expect( link ).toHaveValue( shared.toString() );
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

	// Audit, spec review S4: the spec's browser test of "the SEO tags on the clean page and on a build link".
	test( 'the clean page is indexable with its own canonical, and a build link is noindex, follow with the clean one', async ( {
		page,
	} ) => {
		const clean = fixture().page;
		const robots = page.locator( 'meta[name="robots"]' );
		const canonical = page.locator( 'link[rel="canonical"]' );

		await page.goto( clean );
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		expect(
			( await robots.getAttribute( 'content' ).catch( () => '' ) ) ?? ''
		).not.toContain( 'noindex' );
		await expect( canonical ).toHaveCount( 1 );
		await expect( canonical ).toHaveAttribute( 'href', clean );

		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		await expect( robots ).toHaveAttribute( 'content', /noindex, follow/ );
		await expect( canonical ).toHaveCount( 1 );
		await expect( canonical ).toHaveAttribute( 'href', clean );
		// The links into states the page holds are nofollow: the done steps and Back.
		const into = page.locator(
			'.cbuild a[href*="cbuild%5B"], .cbuild a[href*="cbuild["]'
		);
		expect( await into.count() ).toBeGreaterThan( 0 );
		for ( const rel of await into.evaluateAll( ( links ) =>
			links.map( ( link ) => link.getAttribute( 'rel' ) ?? '' )
		) ) {
			expect( rel ).toContain( 'nofollow' );
		}
	} );

	test( "the kit's own page shows the same builder", async ( { page } ) => {
		await page.goto( '/product/builder-test-kit/' );
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect( page.locator( '.cbuild-width__option' ) ).toHaveCount(
			3
		);
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

// Audit, spec review S2: the roadmap's "every way in built so far" on every engine and width, with JavaScript and
// without. A link is opened with page.goto alone, so the store has nothing to do until the shopper moves.
test.describe( 'Every way in by a link', () => {
	test( 'names each step of the rail with its pick, and links the next once the step has one', async ( {
		page,
	} ) => {
		const rail = page.getByRole( 'navigation', { name: 'Steps' } );
		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
		await expect(
			rail.getByRole( 'link', {
				name: 'Deck, done: Builder test deck 8.0',
				exact: true,
			} )
		).toBeVisible();
		await expect(
			rail.locator(
				'[aria-current="step"] .cbuild-progress__label .screen-reader-text'
			)
		).toHaveText( 'Trucks, current step' );
		await expect(
			rail.getByRole( 'link', { name: 'Wheels, to come' } ),
			'no link past a step with no pick'
		).toHaveCount( 0 );

		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'trucks',
			} )
		);
		await expect(
			rail.getByRole( 'link', { name: 'Wheels, to come', exact: true } ),
			'the next step, one tap away (spec: The step rail)'
		).toHaveAttribute( 'href', /cbuild%5Bstep%5D=wheels/ );
		await expect(
			rail.getByRole( 'link', { name: 'Bearings, to come' } )
		).toHaveCount( 0 );
	} );

	test( 'a card shows its brand, its name and its full price, and once the step has a pick what it changes', async ( {
		page,
	} ) => {
		const card = ( name: string ) =>
			page.locator( '.cbuild-card', {
				has: page.getByRole( 'radio', { name: `Choose ${ name }` } ),
			} );
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		const fresh = card( 'Builder test wheels 54' );
		await expect( fresh.locator( '.cbuild-card__brand' ) ).toHaveText(
			'Northside'
		);
		await expect( fresh.locator( '.cbuild-card__price' ) ).toHaveText(
			'$32.00'
		);
		await expect(
			fresh.locator( '.cbuild-card__delta' ),
			'no change before the step has a pick (D-249)'
		).toBeHidden();
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test wheels 54',
			} ),
			'the brand is said first of what describes the radio'
		).toHaveAccessibleDescription( /^Northside \$32\.00/ );

		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				step: 'wheels',
			} )
		);
		await expect(
			card( 'Builder test wheels 54' ).locator( '.cbuild-card__delta' )
		).toHaveText( '+$2.00 over your pick' );
		await expect(
			card( 'Builder test wheels 52' ).locator( '.cbuild-card__delta' )
		).toHaveText( 'Included' );
	} );

	test( "a deck's link opens on the trucks with the deck picked", async ( {
		page,
	}, info ) => {
		await page.goto( linkTo( { deck: id( 'builder-test-deck-8-5' ) } ) );
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 149',
			} ),
			'Recommended, not picked (D-213).'
		).not.toBeChecked();
		await expect(
			// French writes the width with a decimal comma.
			page.getByText(
				inLocale( info.project.name, 'Recommended for your 8.5″ deck' )
			)
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
		// The trucks the kit cannot take leave the slot empty, so the link stops at it: nothing is picked for the
		// shopper (D-213), and the trucks that fit the deck are labelled.
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		const fits = page.getByRole( 'radio', {
			name: 'Choose Builder test trucks 144',
		} );
		await expect( fits ).not.toBeChecked();
		await expect(
			page
				.locator( 'li.cbuild-card' )
				.filter( { has: fits } )
				.locator( '.cbuild-card__recommended' ),
			'The trucks that fit the deck are recommended.'
		).toBeVisible();
	} );
} );

test.describe( 'The largest paint', () => {
	// Roadmap, milestone 7, Tests that must pass: "Speed: the deck layer as the largest paint, within 2.5 s in the
	// lab". Lighthouse holds the time (lighthouserc.cjs); this names the element, from the browser's own entry.
	test( "is the deck's layer on a build link's review", async ( {
		page,
		browserName,
	} ) => {
		test.skip(
			browserName === 'webkit',
			'WebKit reports no largest-contentful-paint entries.'
		);
		test.skip(
			test.info().project.name === 'no-js',
			'The browser runs no script, so it reports nothing to read.'
		);
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-54' ),
				step: 'review',
			} )
		);
		// The last entry once the deck's layer has loaded and been painted: the largest paint so far.
		const deck = page.locator( '.cbuild-board__layer--deck img' ).first();
		await expect
			.poll( () =>
				deck.evaluate(
					( img: HTMLImageElement ) =>
						img.complete && img.naturalWidth > 0
				)
			)
			.toBe( true );
		// After the layer has painted, a buffered observer's first batch holds every entry so far; the last is the
		// largest paint.
		const largest = await page.evaluate(
			() =>
				new Promise< string >( ( resolve ) => {
					requestAnimationFrame( () =>
						requestAnimationFrame( () =>
							new PerformanceObserver( ( list, observer ) => {
								observer.disconnect();
								const entries = list.getEntries() as Array<
									PerformanceEntry & {
										element?: Element | null;
									}
								>;
								const last = entries[ entries.length - 1 ];
								resolve(
									last?.element?.closest(
										'.cbuild-board__layer'
									)?.className ??
										String( last?.element?.tagName )
								);
							} ).observe( {
								type: 'largest-contentful-paint',
								buffered: true,
							} )
						)
					);
				} )
		);
		// D-204: on a phone, as in Lighthouse's lab, the deck's layer is the largest paint. On a desktop the theme's
		// heading can be larger than the strip of board above the fold, but no other image may take the paint.
		if ( ( page.viewportSize()?.width ?? 0 ) < 782 ) {
			expect( largest ).toContain( 'cbuild-board__layer--deck' );
		} else if ( ! largest.includes( 'cbuild-board__layer--deck' ) ) {
			expect( [
				'IMG',
				'PICTURE',
				'VIDEO',
				'svg',
				'image',
			] ).not.toContain( largest );
		}
	} );
} );

test.describe( 'The board in either direction', () => {
	// Milestone 7's plan, Internationalisation: "`rtl` asserts the layers unmirrored and the labels mirrored". The
	// board is drawn left to right in every language, as a deck is seen from below, and its layers' edges are its left
	// and top (D-195); the labelled photos beside it follow the page's direction.
	test( 'draws each layer from the left in every direction, and lays the labelled photos out in the page’s', async ( {
		page,
	} ) => {
		// Every part picked: the picture draws the picks alone (D-213).
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-54' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				grip: id( 'builder-test-grip' ),
				step: 'review',
			} )
		);
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		const board = page.locator( '#cbuild-picture .cbuild-board' ).first();
		await expect( board.locator( '.cbuild-board__layer' ) ).toHaveCount(
			7
		);
		// Measured in the board's own box, before it is turned: on a phone the stage draws the board on its side
		// (D-271), and a mirrored layer is still found from the left of the board.
		const placed = await board.evaluate( ( element ) =>
			Array.from(
				element.querySelectorAll< HTMLElement >(
					'.cbuild-board__layer'
				)
			).map( ( layer ) => ( {
				asked: parseFloat(
					layer.style.getPropertyValue( '--cbuild-layer-x' )
				),
				drawn:
					( ( layer.offsetParent === element
						? layer.offsetLeft
						: layer.getBoundingClientRect().left -
							element.getBoundingClientRect().left ) /
						( element as HTMLElement ).offsetWidth ) *
					100,
				// offsetLeft is in whole pixels: one, as a share of the board, is as close as it can measure.
				pixel: 100 / ( element as HTMLElement ).offsetWidth,
			} ) )
		);
		const sides = placed.map( ( layer ) => layer.asked );
		expect(
			Math.min( ...sides ),
			'the wheels stand out to both sides, so a mirrored layer would be found'
		).toBeLessThan( Math.max( ...sides ) );
		for ( const layer of placed ) {
			expect(
				Math.abs( layer.drawn - layer.asked ),
				'each layer is where its own x says, from the left'
			).toBeLessThan( Math.max( 0.5, layer.pixel ) );
		}

		const labels = page.locator(
			'#cbuild-picture .cbuild-picture__item:visible'
		);
		expect( await labels.count() ).toBeGreaterThan( 1 );
		const first = await labels.nth( 0 ).boundingBox();
		const second = await labels.nth( 1 ).boundingBox();
		const rtl = await page.evaluate(
			() =>
				getComputedStyle(
					document.querySelector( '.cbuild' ) as Element
				).direction === 'rtl'
		);
		expect(
			( ( first?.x ?? 0 ) - ( second?.x ?? 0 ) ) * ( rtl ? 1 : -1 ),
			'the first labelled photo starts the line on the page’s own side'
		).toBeGreaterThan( 0 );
	} );
} );

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

	// Spec: The deck's width first. The step opens on its question alone: no count of decks, and no frame round none.
	test( 'opens the deck step on the width question alone, with no count until a width is picked, with JavaScript', async ( {
		page,
	}, info ) => {
		await page.goto( fixture().page );
		await expect( page.locator( '.cbuild-width' ) ).toBeVisible();
		await expect( page.locator( '.cbuild-step__fieldset' ) ).toBeHidden();
		await expect(
			page.locator( '.cbuild-step__count:visible' )
		).toHaveCount( 0 );

		await ride( page, inLocale( info.project.name, '8.25″' ) );
		await expect( page.locator( '.cbuild-step__fieldset' ) ).toBeVisible();
		// "Showing 1 of 1", in any language.
		await expect(
			page.locator( '.cbuild-step__count:visible' ).first()
		).toHaveText( /1\D+1/ );
	} );
	test( 'moves from step to step without loading the page, and Back and Forward show the steps again', async ( {
		page,
	} ) => {
		await ride( page, '8″' );
		await pickDeck( page, '8.0' );
		// The pick's own answer puts it in the page's address, so that Back comes back to it.
		await expect
			.poll( () => build( page ) )
			.toEqual( [ 'cbuild[deck]', 'cbuild[step]', 'cbuild[_width]' ] );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		expect( await stayed( page ), 'no page was loaded' ).toBe( true );
		expect( build( page ) ).toEqual( [
			'cbuild[deck]',
			'cbuild[step]',
			'cbuild[_width]',
		] );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 139',
			} ),
			'nothing is picked for the shopper (D-213)'
		).not.toBeChecked();

		await page.goBack();
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
		await page.goForward();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		expect( await stayed( page ) ).toBe( true );

		await page
			// The bar's Back, named for the step it goes to; the panel's head has Back to the shop before it.
			.getByRole( 'link', { name: /^Back: / } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		expect( await stayed( page ) ).toBe( true );
	} );

	// Audit 22, milestone 6: the roadmap's "Back, Forward and reload"; SPEC History: a reload opens the same step.
	test( 'a reload at a step the store reached opens the same step with the same picks', async ( {
		page,
	} ) => {
		await ride( page, '8″' );
		await pickDeck( page, '8.0' );
		// As a shopper would, Next is pressed once the pick's answer has redrawn the bar: pressed as it landed, the
		// bar grew under a slow runner's click and the click missed.
		await expect
			.poll( () => build( page ) )
			.toEqual( [ 'cbuild[deck]', 'cbuild[step]', 'cbuild[_width]' ] );
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		expect( await stayed( page ), 'the store moved' ).toBe( true );
		const address = page.url();

		await page.reload();
		await page.waitForLoadState( 'load' );
		expect( await stayed( page ), 'the page was loaded again' ).toBe(
			false
		);
		expect( page.url() ).toBe( address );
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 139',
			} ),
			'nothing is picked for the shopper (D-213)'
		).not.toBeChecked();

		// The store takes over the reloaded page: Back is drawn, not loaded, and the deck is still picked.
		await mark( page );
		await page
			// The bar's Back, named for the step it goes to; the panel's head has Back to the shop before it.
			.getByRole( 'link', { name: /^Back: / } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
		expect( await stayed( page ) ).toBe( true );
	} );

	test( "describes each card's radio by the rest of its card, on a step the store drew (Accessibility)", async ( {
		page,
	} ) => {
		await ride( page, '8″' );
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } ),
			'its brand, then its full price, with no change before a pick (D-249, D-273)'
		).toHaveAccessibleDescription( /^Northside \$60\.00/ );
		await pickDeck( page, '8.0' );
		await settled( page );
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		expect( await stayed( page ), 'the store drew the step' ).toBe( true );
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test trucks 139',
			} )
		).toHaveAccessibleDescription(
			/Fits your 8(\.0)?″ deck.*Recommended for your 8(\.0)?″ deck/
		);
	} );

	test( 'asks the route once for picks made in quick succession, and paints each pick before it answers', async ( {
		page,
	} ) => {
		// Two parts in one list: the wheels on an 8″ deck and its trucks, since the deck step lists one width (D-241).
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		await page.waitForLoadState( 'load' );
		await mark( page );
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
			name: 'Choose Builder test wheels 52',
		} );
		const second = page.getByRole( 'radio', {
			name: 'Choose Builder test wheels 54',
		} );
		// Both picks in one task, as a quick shopper's land inside the store's wait: two checks from the test can fall
		// further apart than that on a slow runner, and the store rightly asks twice (CI run 43417214571, webkit-phone).
		await page.evaluate(
			( values ) => {
				for ( const value of values ) {
					document
						.querySelector< HTMLInputElement >(
							`.cbuild input[type="radio"][value="${ value }"]`
						)
						?.click();
				}
			},
			[ id( 'builder-test-wheels-52' ), id( 'builder-test-wheels-54' ) ]
		);
		await expect( first ).not.toBeChecked();
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
		expect( asked[ 0 ]!.searchParams.get( 'cbuild[wheels]' ) ).toBe(
			String( id( 'builder-test-wheels-54' ) )
		);
		expect( asked[ 0 ]!.searchParams.get( 'cbuild[step]' ) ).toBe(
			'wheels'
		);
		await expect( second ).toBeChecked();
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[wheels]' )
		).toBe( String( id( 'builder-test-wheels-54' ) ) );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'shows the pending state only once an answer has kept the shopper waiting 300 ms (Resilience)', async ( {
		page,
	} ) => {
		await ride( page, '8″' );
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => {
			release = done;
		} );
		await page.route( isRoute, async ( route ) => {
			await held;
			await route.continue();
		} );
		const total = page.locator( '.cbuild-bar__amount' );
		const deck = page.getByRole( 'radio', {
			name: 'Choose Builder test deck 8.0',
		} );
		await expect( deck ).toBeVisible();
		// Picked and read in the page, so that the test's own round trips do not move the moments: a slow runner's
		// took longer than 300 ms between a check() and the first reading, which then saw the pending state.
		const seen = await deck.evaluate( ( input, selector ) => {
			const element = document.querySelector( selector );
			const opacity = () =>
				element ? getComputedStyle( element ).opacity : '';
			const at = ( ms: number ) =>
				new Promise< string >( ( done ) =>
					setTimeout( () => done( opacity() ), ms )
				);
			( input as HTMLInputElement ).click();
			return Promise.all( [ at( 50 ), at( 600 ) ] );
		}, '.cbuild-bar__amount' );
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

	test( 'sends the form when the state route fails, the server shows the next step, and the build still reaches the cart', async ( {
		page,
	} ) => {
		await ride( page, '8″' );
		await page.route( isRoute, ( route ) => route.abort() );
		await pickDeck( page, '8.0' );
		await answered( page, () =>
			page.getByRole( 'button', { name: 'Next: Trucks' } ).click()
		);
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		expect(
			await stayed( page ),
			'the page was loaded, as without JavaScript'
		).toBe( false );
		expect( build( page ) ).toEqual( [
			'cbuild[_on]',
			'cbuild[_width]',
			'cbuild[deck]',
			'cbuild[step]',
		] );

		// Audit, spec review S3: the roadmap's "the state route forced to fail, and the build still finishes".
		await choose( page, 'Builder test trucks 139' );
		await next( page, 'Next: Wheels' );
		await choose( page, 'Builder test wheels 58' );
		await next( page, 'Next: Bearings' );
		await expect( heading( page ) ).toHaveText(
			'Step 4 of 8: Choose your risers'
		);
		await choose( page, 'Builder test risers 1/4' );
		await next( page, 'Next: Bearings' );
		await choose( page, 'Builder test bearings' );
		await next( page, 'Next: Hardware' );
		await choose( page, 'Builder test bolts 1.25' );
		await next( page, 'Next: Grip tape' );
		await choose( page, 'Builder test grip' );
		await next( page, 'Next: Review' );
		await expect( heading( page ) ).toHaveText(
			'Step 8 of 8: Check your build'
		);
		// The review's own, or the phone bar's where the review's is hidden: one at each width (P11).
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.click();
		await expect
			.poll( async () => {
				const cart = await (
					await page.request.get( '/wp-json/wc/store/v1/cart' )
				).json();
				return cart.items.map(
					( item: { name: string } ) => item.name
				);
			} )
			.toEqual(
				expect.arrayContaining( [
					'Builder test kit',
					'Builder test deck 8.0',
					'Builder test trucks 139',
					'Builder test wheels 58',
					'Builder test risers 1/4',
					'Builder test bearings',
					'Builder test grip',
				] )
			);
	} );

	test( "puts focus on each new step's heading, says the step in the title, and says each pick once", async ( {
		page,
	} ) => {
		const status = page.locator( '.cbuild' ).getByRole( 'status' );
		await expect( status ).toHaveText( '' );
		await ride( page, '8.25″' );
		await pickDeck( page, '8.25' );
		await expect(
			status,
			'the build and its total, after the pick'
		).toContainText( 'Total:' );
		await expect(
			page,
			'the page keeps its own title after a pick'
		).toHaveTitle( /^Build a complete/ );

		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
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

	// Found comparing the builder with the prototype: a block theme draws an outline on anything in focus, and the
	// heading the builder moves focus to, a place to read from and no control, was boxed after a mouse press.
	test( 'draws no ring on the heading it moves focus to after a press, on a block theme', async ( {
		page,
		context,
		baseURL,
	} ) => {
		await context.addCookies( [
			{
				name: 'cbuild_e2e_theme',
				value: 'twentytwentyfive',
				url: baseURL || 'http://localhost:8898',
			},
		] );
		await ride( page, '8.25″' );
		await pickDeck( page, '8.25' );
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toBeFocused();
		expect(
			await heading( page ).evaluate(
				( element ) => getComputedStyle( element ).outlineStyle
			),
			'the heading in focus'
		).toBe( 'none' );
	} );

	test( "swaps the deck's layer in place on a pick, says the build once, and shows a labelled photo for a part with no layer image (D-195)", async ( {
		page,
	} ) => {
		const picture = page.locator( '#cbuild-picture' );
		// The underside's deck: the top has a deck layer of its own (#46).
		const deck = picture.locator(
			'.cbuild-board:not(.cbuild-board--top) .cbuild-board__layer--deck'
		);
		const status = page.locator( '.cbuild' ).getByRole( 'status' );
		const boxed = picture
			.locator( '.cbuild-picture__item' )
			.filter( { hasText: 'Deck: ' } );
		await pickDeck( page, '8.0' );
		await expect( deck ).toHaveCount( 1 );
		await expect( deck.locator( 'img' ) ).toHaveAttribute(
			'src',
			/builder-test-deck-8-0-layer/
		);
		await expect( deck.locator( 'img' ) ).toHaveAttribute(
			'fetchpriority',
			'high'
		);
		await expect(
			deck.locator( 'source[type="image/avif"]' )
		).toHaveAttribute( 'srcset', / 407w$/ );
		await expect( boxed ).toHaveCount( 1 );
		await expect(
			boxed,
			'the deck is drawn, so its labelled photo is not shown'
		).toBeHidden();
		await deck.evaluate( ( element ) => {
			( element as HTMLElement & { kept?: boolean } ).kept = true;
		} );

		await pickDeck( page, '8.5' );
		await expect( deck.locator( 'img' ) ).toHaveAttribute(
			'src',
			/builder-test-deck-8-5-layer/
		);
		expect(
			await deck.evaluate(
				( element ) =>
					( element as HTMLElement & { kept?: boolean } ).kept ===
					true
			),
			'the same layer, swapped in place'
		).toBe( true );
		await expect( status ).toContainText( 'Builder test deck 8.5' );
		expect(
			( await status.textContent() )?.split( 'The build so far' ).length,
			'the build said once'
		).toBe( 2 );

		await ride( page, '8.25″' );
		await pickDeck( page, '8.25' );
		await expect(
			deck,
			'the 8.25 in deck has no layer image; the wheels the total counts still do'
		).toHaveCount( 0 );
		await expect( boxed, 'its labelled photo instead' ).toBeVisible();
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

	test( 'lists the decks of the width pressed without loading the page (D-241)', async ( {
		page,
	}, info ) => {
		const widths = page.locator( '.cbuild-width' );
		// French writes the width with a decimal comma.
		const width = widths.getByRole( 'button', {
			name: inLocale( info.project.name, '8.25″, 1' ),
		} );
		await width.click();
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 1 );
		await expect( width ).toHaveAttribute( 'aria-pressed', 'true' );
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[_width]' )
		).toBe( '8.25in' );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( 'keeps focus on a width and on the assembly switch, and says the new total (Accessibility)', async ( {
		page,
	}, info ) => {
		const width = page.locator( '.cbuild-width' ).getByRole( 'button', {
			name: inLocale( info.project.name, '8.25″, 1' ),
		} );
		await width.focus();
		await page.keyboard.press( 'Enter' );
		await expect( width ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( width, 'the shopper keeps their place' ).toBeFocused();

		expect( await stayed( page ), 'the width kept the page' ).toBe( true );

		// The switch is on the review at every width (D-271).
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				grip: id( 'builder-test-grip' ),
				step: 'review',
			} )
		);
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await mark( page );
		const toggle = page.locator( '.cbuild-review__toggle button' );
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
		await ride( page, '8.5″' );
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'deck',
			} )
		);
		await page.waitForLoadState( 'load' );
		await mark( page );
		// The pick's own answer opens the panel too (D-152), and "Use these" then stays at the deck. Held, the pick is
		// overtaken by Next, as a quick shopper's press does; unheld, on a slow runner the pick's panel came first and
		// hid the button the click was waiting for.
		// The pick asks for the step it is on; Next, from the same form, for the next.
		const picking = ( url: URL ) =>
			url.pathname.endsWith( '/state' ) &&
			url.searchParams.get( 'cbuild[step]' ) === 'deck';
		await page.route( picking, () => undefined );
		await pickDeck( page, '8.5' );
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();

		const panel = page.locator( '.cbuild-changes' );
		const title = panel.getByRole( 'heading', {
			name: 'This change affects parts you chose',
		} );
		await expect( title ).toBeVisible();
		await page.unroute( picking );
		await expect( title ).toBeFocused();
		await expect( page.locator( '.cbuild [role="status"]' ) ).toHaveText(
			'1 part no longer fits.'
		);
		await expect(
			panel.getByText( 'Closest fit: Builder test trucks 149' )
		).toBeVisible();
		await expect( page.locator( '.cbuild-step' ) ).toBeHidden();

		await panel.getByRole( 'button', { name: 'Use these' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
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
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
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
		// The wider width first: Back leaves the panel for the step as it was just before the pick.
		await ride( page, '8.5″' );
		const before = page.url();
		await pickDeck( page, '8.5' );

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

	test( 'a preset with a sold-out part names it on its tile and at the review, and keeps it from the cart', async ( {
		page,
	} ) => {
		const tiles = page.locator( '.cbuild-presets' );
		await expect(
			tiles.getByText( 'Wheels: Builder test wheels 56 is sold out.' )
		).toBeVisible();
		await tiles
			.getByRole( 'button', { name: 'Start from Cruiser' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		expect( await stayed( page ), 'the store drew the review' ).toBe(
			true
		);
		await expect( page.locator( '.cbuild-errors' ) ).toContainText(
			'Wheels: Builder test wheels 56 is sold out.'
		);
		// The review's own, or the phone bar's where the review's is hidden: one at each width (P11).
		await expect(
			page
				.locator( '.cbuild' )
				.getByRole( 'button', { name: 'Add to cart' } )
		).toBeDisabled();
	} );

	test( 'starts from a preset without loading the page', async ( {
		page,
	} ) => {
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
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
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
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

	// CI run 43520699158: the first deck's answer starts every later slot on its recommendation, so the picture comes
	// in and the presets go, and Next moved by 166 pixels on a desktop. WebKit drops a press that starts on a button
	// and ends off it, so a shopper's Next pressed as the answer came was lost. The page now keeps the picked card,
	// and Next under it, where they were (D-185). The answer is held here until the press starts.
	test( "keeps Next under a press the first deck's answer arrives during, and the press goes through", async ( {
		page,
	} ) => {
		let release: () => void = () => undefined;
		const pressed = new Promise< void >( ( done ) => ( release = done ) );
		await page.exposeFunction( 'cbuildPressed', () => release() );
		await page.route(
			( url ) =>
				url.pathname.endsWith( '/state' ) &&
				url.searchParams.get( 'cbuild[step]' ) === 'deck',
			async ( route ) => {
				const answer = await route.fetch();
				await Promise.race( [
					pressed,
					new Promise( ( done ) => setTimeout( done, 3000 ) ),
				] );
				await route.fulfill( { response: answer } );
			}
		);
		await page.goto( linkTo( { _width: '8in' } ) );
		await page.waitForLoadState( 'load' );
		const button = page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.filter( { visible: true } );
		await button.evaluate( ( element ) =>
			element.ownerDocument.addEventListener(
				'pointerdown',
				( event ) => {
					if ( element.contains( event.target as Node ) ) {
						(
							window as unknown as { cbuildPressed: () => void }
						 ).cbuildPressed();
					}
				},
				true
			)
		);
		const card = page.getByRole( 'radio', {
			name: 'Choose Builder test deck 8.0',
		} );
		await card.check();
		await button.scrollIntoViewIfNeeded();
		const box = ( await button.boundingBox() )!;
		await page.mouse.move( box.x + box.width / 2, box.y + box.height / 2 );
		await page.mouse.down();
		// The answer lands while the button is held, as a shopper's press takes a tenth of a second or more.
		await expect.poll( () => build( page ) ).toContain( 'cbuild[deck]' );
		await page.waitForTimeout( 100 );
		const moved = ( await button.boundingBox() )!;
		expect(
			Math.abs( moved.y - box.y ),
			'Next where it was under the press'
		).toBeLessThan( 2 );
		await page.mouse.up();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
	} );

	// Claude in Cowork's recheck, finding 10: on the demo the Mini-Cart's drawer opened on the add and took focus a
	// beat after the builder had moved it to its notice, and nothing was said. Whichever comes first, focus is left in
	// the drawer and the status says the add.
	for ( const order of [ 'first', 'a beat after' ] ) {
		test( `says the add once when the Mini-Cart's drawer opens ${ order }, and leaves focus in it`, async ( {
			page,
		} ) => {
			await page.goto( fixture().drawer );
			await page.waitForLoadState( 'load' );
			if ( order !== 'first' ) {
				// The add's event reaches the Mini-Cart 150 ms late, as the demo's drawer opened after the builder's frame.
				await page.evaluate( () => {
					let held = false;
					window.addEventListener(
						'wc-blocks_added_to_cart',
						( event ) => {
							if ( held ) {
								return;
							}
							held = true;
							event.stopImmediatePropagation();
							window.setTimeout(
								() =>
									document.body.dispatchEvent(
										new CustomEvent(
											'wc-blocks_added_to_cart',
											{ bubbles: true, cancelable: true }
										)
									),
								150
							);
						},
						{ capture: true }
					);
				} );
			}
			await page
				.locator( '.cbuild-presets' )
				.getByRole( 'button', { name: 'Start from Street' } )
				.click();
			await expect( heading( page ) ).toHaveText(
				'Step 7 of 7: Check your build'
			);
			await page
				.locator( '.cbuild' )
				.getByRole( 'button', { name: 'Add to cart' } )
				.click();
			const drawer = page.locator( '.wc-block-mini-cart__drawer' );
			await expect( drawer ).toBeVisible();
			await expect
				.poll( () =>
					drawer.evaluate( ( element ) =>
						element.contains( element.ownerDocument.activeElement )
					)
				)
				.toBe( true );
			await expect(
				page.locator( '.cbuild' ).getByRole( 'status' )
			).toHaveText( 'The build is in your cart.' );
			await expect(
				page.locator( '.cbuild-review__added' )
			).toBeVisible();
		} );
	}

	test( "copies the build's link and says so", async ( {
		page,
		context,
		browserName,
	} ) => {
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
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
		// Parts that share a list: the wheels on an 8″ deck and its trucks (D-241 lists one width of deck at a time).
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		await page.waitForLoadState( 'load' );
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
			'Choose Builder test wheels 52',
			'Choose Builder test wheels 54',
			'Choose Builder test wheels 52',
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

	// Audit, spec review S5. The Quality bar's Privacy: "no cookie of the plugin's own; no request to another host",
	// held by the browser suite as well as the integration suite.
	test( 'asks no other host and sets no cookie of its own, from a blank page to the cart (Privacy)', async ( {
		page,
		context,
	} ) => {
		await context.clearCookies();
		const origin = new URL( fixture().page ).origin;
		const hosts = new Set< string >();
		page.on( 'request', ( request ) => {
			const url = new URL( request.url() );
			if ( url.protocol.startsWith( 'http' ) ) {
				hosts.add( url.origin );
			}
		} );
		await page.goto( fixture().page );
		await page.waitForLoadState( 'load' );
		await pickDeck( page, '8.0' );
		await settled( page );
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		await page.goto( fixture().page );
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.click();
		await expect( page.locator( '.cbuild-review__added' ) ).toContainText(
			'The build is in your cart.'
		);

		expect( [ ...hosts ] ).toEqual( [ origin ] );
		// WordPress's and WooCommerce's own, which the cart needs; none is the plugin's.
		const theirs =
			/^(wp_woocommerce_session_|woocommerce_items_in_cart$|woocommerce_cart_hash$|wordpress_test_cookie$|sbjs_)/;
		const names = ( await context.cookies() ).map(
			( cookie ) => cookie.name
		);
		expect(
			names.filter( ( name ) => ! theirs.test( name ) ),
			names.join( ', ' )
		).toEqual( [] );
		expect(
			names.filter( ( name ) => /cbuild|skateboard/i.test( name ) )
		).toEqual( [] );
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
		// Axe on a dozen states: over the 60 s a test has on a slow WebKit runner (CI run 43461092467).
		test.slow();
		const region = '.cbuild';
		const check = ( state: string ) =>
			axe( page, `${ info.project.name }: ${ state }`, region );

		// The blank start: presets and the deck step's width question.
		await check( 'the blank start' );

		// A width pressed: the deck step lists its decks.
		await page
			.locator( '.cbuild-width' )
			.getByRole( 'button', {
				name: inLocale( info.project.name, '8.25″, 1' ),
			} )
			.click();
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 1 );
		await check( 'a width pressed' );

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
		// The store running before the pick, and one Next button named, as the change panel's own test does: on a slow
		// runner the first found was the bar's, which the footer covered and the store then hid (CI run 43417214571,
		// webkit-phone).
		await page.waitForLoadState( 'load' );
		// The pick's own answer opens the panel and hides the bar's Next (D-152): held, as the change panel's own test
		// holds it, Next overtakes it (CI run 43520699158, webkit-phone).
		const picking = ( url: URL ) =>
			url.pathname.endsWith( '/state' ) &&
			url.searchParams.get( 'cbuild[step]' ) === 'deck';
		await page.route( picking, () => undefined );
		await pickDeck( page, '8.5' );
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.click();
		await expect(
			page.locator( '.cbuild-changes' ).getByRole( 'heading', {
				name: 'This change affects parts you chose',
			} )
		).toBeVisible();
		await page.unroute( picking );
		await check( 'the change panel' );

		// The review, from a preset, and the build added.
		await page.goto( fixture().page );
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
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

	// Spec (configurator): Accessibility, keyboard order. A skip link first, to the step, and the first deck reached in at
	// most eight presses from it once a width is picked.
	test( 'a skip link goes to the step, and the first deck is at most eight presses after it', async ( {
		page,
		browserName,
	} ) => {
		const tab = browserName === 'webkit' ? 'Alt+Tab' : 'Tab';
		await page.goto( linkTo( { _width: '8in' } ) );
		const skip = page.getByRole( 'link', { name: 'Skip to the step' } );
		await expect( skip ).toHaveCount( 1 );
		// It is the builder's first stop.
		await expect(
			page.locator( '.cbuild a, .cbuild button, .cbuild input' ).first()
		).toHaveText( 'Skip to the step' );
		await skip.focus();
		await expect( skip, 'seen once focused' ).toBeInViewport();
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( '#cbuild-heading' ) ).toBeFocused();
		let presses = 0;
		while ( presses < 8 ) {
			await page.keyboard.press( tab );
			presses++;
			const at =
				( await page
					.locator( 'input[type="radio"][name="cbuild[deck]"]:focus' )
					.count() ) > 0;
			if ( at ) {
				break;
			}
		}
		await expect(
			page.locator( 'input[type="radio"][name="cbuild[deck]"]:focus' ),
			`a deck card within eight presses (took ${ presses })`
		).toHaveCount( 1 );
	} );

	test( 'builds a board from a blank page and places its order (Accessibility: a keyboard-only build)', async ( {
		page,
		browserName,
		javaScriptEnabled,
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
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		// The width first (D-241), as a button of the step's form.
		const width = page.locator( '.cbuild-width__option' ).first();
		await press( width );
		await expect(
			page.locator( '.cbuild-width__option' ).first()
		).toHaveAttribute( 'aria-pressed', 'true' );
		const decks = page.getByRole( 'radio', {
			name: /^Choose Builder test deck/,
		} );
		await press( decks, 'Space' );
		await expect( decks.and( page.locator( ':checked' ) ) ).toHaveCount(
			1
		);

		const builder = page.locator( '.cbuild' );
		if ( javaScriptEnabled ) {
			// After a pick, the status says the build and its total, once (spec: Keyboard and screen readers).
			await expect( builder.locator( '.cbuild__status' ) ).toHaveText(
				/^The build so far: Deck: Builder test deck.*\. Total: .+\.$/
			);
		}
		for ( let step = 0; step < 12; step++ ) {
			const now = ( await heading( page ).textContent() ) ?? '';
			if ( now.endsWith( 'Check your build' ) ) {
				break;
			}
			// Nothing is picked for the shopper (D-213): a step with no pick gets one, by Tab into its group and
			// Space on the card that takes focus. An optional slot starts on its "No" choice, which counts.
			const choices = builder.locator(
				'form.cbuild-step input[type="radio"]:not([disabled])'
			);
			if (
				( await choices.count() ) > 0 &&
				( await choices.and( page.locator( ':checked' ) ).count() ) ===
					0
			) {
				await press( choices, 'Space' );
				await expect(
					choices.and( page.locator( ':checked' ) )
				).toHaveCount( 1 );
				if ( javaScriptEnabled ) {
					await expect( builder ).toHaveAttribute(
						'aria-busy',
						'false'
					);
				}
			}
			await press( builder.getByRole( 'button', { name: /^Next: / } ) );
			await expect( heading( page ) ).not.toHaveText( now );
			// With JavaScript the builder moves focus to the new step's heading once it has drawn it (D-149). Tab
			// before that starts from the Next just pressed, which the phone bar keeps, and Enter then lands on the
			// heading as focus moves (CI run 43549190222, webkit-phone).
			if ( javaScriptEnabled ) {
				await expect( heading( page ) ).toBeFocused();
			}
			// Spec, The picture: "a written description of the build", after every change (spec review of milestone
			// 7, should-fix, owed from milestone 6): the deck chosen first is in it at every step. A step is read from
			// its heading, so the status says nothing then (D-149); it spoke after the pick, above.
			await expect(
				builder.locator( '.cbuild-picture__description' )
			).toHaveText( /^The build so far: Deck: Builder test deck.*\.$/ );
		}
		await expect( heading( page ) ).toHaveText( /: Check your build$/ );

		await press( builder.getByRole( 'button', { name: 'Add to cart' } ) );
		if ( test.info().project.name !== 'no-js' ) {
			// A shopper waits for the confirmation before moving on; the add's notice then has focus, so the next Tab
			// reaches its link. Tabbing on during the request walks focus out of the builder, where the add leaves it.
			await expect(
				builder.locator( '.cbuild-review__added' )
			).toBeFocused();
			// WooCommerce writes the cart to the session at PHP's shutdown, which can come after the add's answer has gone out, so a
			// cart page asked for 120 ms later once read it empty (CI run 44157697581, chromium-desktop; #51). The
			// cart is read until it holds the build, as a shopper's slower step to View cart would find it.
			await expect
				.poll(
					async () =>
						(
							await (
								await page.request.get(
									'/wp-json/wc/store/v1/cart'
								)
							).json()
						).items_count as number
				)
				.toBeGreaterThan( 0 );
		}
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

test.describe( 'The review with JavaScript', () => {
	// Spec (configurator): The review and Risers (D-246). "Add risers" sits under the parts when the build has none,
	// and opens the step though nothing asks for it.
	test( 'Add risers at the review opens the risers step for a build that has none', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				grip: id( 'builder-test-grip' ),
				step: 'review',
			} )
		);
		await page.getByRole( 'link', { name: 'Add risers' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 4 of 8: Choose your risers'
		);
		await expect(
			page.getByRole( 'radio', { name: 'No risers' } )
		).toBeVisible();
	} );
} );

// Spec (configurator): The deck's width first, "Not sure of your width?".
test( 'the width question opens a guide by shoe size', async ( { page } ) => {
	await page.goto( linkTo( {} ) );
	const guide = page.locator( '.cbuild-width__guide' );
	await expect( guide.getByRole( 'table' ) ).toBeHidden();
	await guide.getByText( 'Not sure of your width?' ).click();
	const table = page.getByRole( 'table', {
		name: 'Deck width by shoe size',
	} );
	await expect( table ).toBeVisible();
	await expect(
		table.getByRole( 'row', { name: '9.5 and larger 8.25″ and wider' } )
	).toBeVisible();
} );

// Cowork's review of 0.9.0, F3 (#68): on the demo's dark theme a native radio drew an unpicked card's as a white disc
// and a picked one's as a white ring, so the unpicked one looked filled in.
// A classic theme names no colour presets, so the accent is the text's colour. A fill in it once painted its words in
// the same colour as itself, and the theme's list numbers and indent showed beside the rail's own numbers.
test.describe( 'Fills in the accent on a classic theme', () => {
	for ( const [ theme, scheme ] of [
		[ 'storefront', 'light' ],
		[ 'kadence', 'dark' ],
	] as const ) {
		test( `the current step and a pressed width read on ${ theme }, ${ scheme }, and the rail has no list numbers`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const url = baseURL || 'http://localhost:8898';
			await context.addCookies( [
				{ name: 'cbuild_e2e_theme', value: theme, url },
				{ name: 'cbuild_e2e_scheme', value: scheme, url },
			] );
			await page.goto( linkTo( { _width: '8in' } ) );
			// The contrast between a fill's words, as painted, and its ground.
			const contrast = ( selector: string ) =>
				page
					.locator( selector )
					.first()
					.evaluate( ( element ) => {
						const luminance = ( colour: string ) => {
							const [ r, g, b ] = (
								colour.match( /[\d.]+/g ) ?? []
							)
								.slice( 0, 3 )
								.map( ( part ) => {
									const c = Number( part ) / 255;
									return c <= 0.04045
										? c / 12.92
										: ( ( c + 0.055 ) / 1.055 ) ** 2.4;
								} );
							return (
								0.2126 * ( r ?? 0 ) +
								0.7152 * ( g ?? 0 ) +
								0.0722 * ( b ?? 0 )
							);
						};
						const own = getComputedStyle( element );
						const words = luminance(
							own.webkitTextFillColor || own.color
						);
						const ground = luminance( own.backgroundColor );
						return (
							( Math.max( words, ground ) + 0.05 ) /
							( Math.min( words, ground ) + 0.05 )
						);
					} );
			expect(
				await contrast(
					'.cbuild-progress__step--current .cbuild-progress__label'
				),
				'the current step'
			).toBeGreaterThanOrEqual( 4.5 );
			expect(
				await contrast( '.cbuild-chip[aria-pressed="true"]' ),
				'the pressed width'
			).toBeGreaterThanOrEqual( 4.5 );

			const tiles = await page
				.locator( '.cbuild-presets__tiles' )
				.evaluate( ( element ) => {
					const own = getComputedStyle( element );
					return {
						list: own.listStyleType,
						margin: own.marginInlineStart,
						padding: own.paddingInlineStart,
					};
				} );
			// The theme's list rules leave the card alone: its unseen radio lies over the whole card, so a press anywhere
			// on it picks it (#68), and its words keep inside the card's own padding.
			const card = page.locator( '.cbuild-cards .cbuild-card' ).first();
			const box = await card.boundingBox();
			const control = await card
				.locator( '.cbuild-card__control' )
				.boundingBox();
			const words = await card
				.locator( '.cbuild-card__about' )
				.boundingBox();
			expect( box ).not.toBeNull();
			expect( control ).not.toBeNull();
			expect( words ).not.toBeNull();
			if ( box && control && words ) {
				// Inside the card's border, a pixel each side.
				const edges = [
					control.x - box.x,
					control.y - box.y,
					box.x + box.width - ( control.x + control.width ),
					box.y + box.height - ( control.y + control.height ),
				];
				for ( const edge of edges ) {
					expect(
						edge,
						'the radio, over the whole card'
					).toBeGreaterThanOrEqual( 0 );
					expect(
						edge,
						'the radio, over the whole card'
					).toBeLessThanOrEqual( 2 );
				}
				expect(
					words.x,
					"the words, inside the card's padding"
				).toBeGreaterThan( box.x + 4 );
			}

			expect( tiles, 'the ready builds' ).toEqual( {
				list: 'none',
				margin: '0px',
				padding: '0px',
			} );

			const list = await page
				.locator( '.cbuild-progress__list' )
				.evaluate( ( element ) => {
					const own = getComputedStyle( element );
					const step = getComputedStyle(
						element.querySelector( 'li' ) as Element
					);
					return {
						list: own.listStyleType,
						step: step.listStyleType,
						margin: own.marginInlineStart,
					};
				} );
			expect( list ).toEqual( {
				list: 'none',
				step: 'none',
				margin: '0px',
			} );
		} );
	}
} );

test.describe( 'Radios on a dark theme whose contrast colour is dark', () => {
	test( 'an unpicked radio is a ring and a picked one has its dot, each standing out from its ground', async ( {
		page,
		context,
		baseURL,
	} ) => {
		const url = baseURL || 'http://localhost:8898';
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: 'kadence', url },
			{ name: 'cbuild_e2e_scheme', value: 'demo-dark', url },
		] );
		await page.goto( linkTo( { _width: '8in' } ) );
		const radio = page.getByRole( 'radio', {
			name: 'Choose Builder test deck 8.0',
		} );
		// The radio's ground, its ring and its dot, each as a relative luminance, and whether the dot is drawn.
		const drawn = () =>
			radio.evaluate( ( input ) => {
				const luminance = ( colour: string ) => {
					const [ r, g, b ] = ( colour.match( /[\d.]+/g ) ?? [] )
						.slice( 0, 3 )
						.map( ( part ) => {
							const c = Number( part ) / 255;
							return c <= 0.04045
								? c / 12.92
								: ( ( c + 0.055 ) / 1.055 ) ** 2.4;
						} );
					return (
						0.2126 * ( r ?? 0 ) +
						0.7152 * ( g ?? 0 ) +
						0.0722 * ( b ?? 0 )
					);
				};
				const own = getComputedStyle( input );
				const dot = getComputedStyle( input, '::before' );
				return {
					opaque: ! /^rgba\(.*,\s*0\)$|^transparent$/.test(
						own.backgroundColor
					),
					ground: luminance( own.backgroundColor ),
					ring: luminance( own.borderTopColor ),
					dot: luminance( dot.backgroundColor ),
					shown:
						dot.content !== 'none' &&
						dot.transform !== 'matrix(0, 0, 0, 0, 0, 0)',
				};
			} );
		const contrast = ( a: number, b: number ) =>
			( Math.max( a, b ) + 0.05 ) / ( Math.min( a, b ) + 0.05 );
		const unpicked = await drawn();
		expect( unpicked.shown, 'no dot before a pick' ).toBe( false );
		expect( unpicked.opaque, 'a ground of its own, over any photo' ).toBe(
			true
		);
		expect(
			contrast( unpicked.ring, unpicked.ground ),
			'its ring stands out from its own ground (WCAG 1.4.11)'
		).toBeGreaterThanOrEqual( 3 );
		expect(
			unpicked.ground,
			'a dark disc on a dark theme, not a white one'
		).toBeLessThan( 0.2 );
		await radio.check();
		const picked = await drawn();
		expect( picked.shown, 'the dot once picked' ).toBe( true );
		expect(
			contrast( picked.dot, picked.ground ),
			'the dot stands out from the ground'
		).toBeGreaterThanOrEqual( 3 );
	} );
} );

test.describe( "The builder's styles", () => {
	// Spec (configurator): Accessibility, zoom and reflow. Below 320 px the stage stops being sticky, so the panel is
	// never squeezed out.
	test( 'the stage is sticky at 320 px and not below it', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 320, height: 640 } );
		await page.goto( linkTo( { _width: '8in' } ) );
		const stage = page.locator( '.cbuild__stage' );
		await expect( stage ).toHaveCSS( 'position', 'sticky' );
		await page.setViewportSize( { width: 300, height: 640 } );
		await expect( stage ).toHaveCSS( 'position', 'static' );
	} );

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

	// Audit 21, milestone 6. The spec's A step: filters above long lists, "and in a drawer on phones".
	test( 'asks the width open on a phone, each width a full-size target, and the same wider (D-241)', async ( {
		page,
	} ) => {
		const widths = page.locator( '.cbuild-width__option' );
		for ( const size of [
			{ width: 390, height: 844 },
			{ width: 1280, height: 900 },
		] ) {
			await page.setViewportSize( size );
			await page.goto( linkTo( { step: 'deck' } ) );
			await expect( heading( page ) ).toHaveText(
				'Step 1 of 7: Choose your deck'
			);
			await expect( widths ).toHaveCount( 3 );
			for ( const width of await widths.all() ) {
				await expect( width ).toBeVisible();
				expect(
					( await width.boundingBox() )?.height ?? 0
				).toBeGreaterThanOrEqual( 44 );
			}
			expect( await overflows( page ) ).toEqual( [] );
		}
	} );

	// Zach, 19 Nov (D-270): on a phone the rail shows the step before, the step shown and the step after; on a
	// desktop, every step.
	test( 'shows the steps either side of the one shown on a phone, and every step on a desktop', async ( {
		page,
	} ) => {
		const parts = fixture().parts;
		// Each step's name, without the state a screen reader hears after it.
		const shown = () =>
			page
				.locator( '.cbuild-progress__step' )
				.evaluateAll( ( steps ) =>
					steps
						.filter(
							( step ) =>
								'visible' ===
								getComputedStyle( step ).visibility
						)
						.map(
							( step ) =>
								step.querySelector(
									':scope > :not([hidden]) > span:first-child'
								)?.textContent ?? ''
						)
				);
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( linkTo( { step: 'deck' } ) );
		expect( await shown() ).toEqual( [ 'Deck', 'Trucks' ] );
		await page.goto(
			linkTo( {
				deck: String( parts[ 'builder-test-deck-8-25' ] ),
				trucks: String( parts[ 'builder-test-trucks-139' ] ),
				step: 'wheels',
			} )
		);
		expect( await shown() ).toEqual( [ 'Trucks', 'Wheels', 'Bearings' ] );
		// The kit's own page gives the builder a desktop's width; the test page's column is a phone's.
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( '/product/builder-test-kit/' );
		expect( await shown() ).toHaveLength( 7 );
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
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
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
		// A pressed width is a border, not a colour.
		await page.goto( linkTo( { step: 'deck', _width: '8in' } ) );
		expect(
			await page
				.locator( '.cbuild-width__option[aria-pressed="true"]' )
				.evaluate( ( chip ) =>
					parseFloat( getComputedStyle( chip ).borderTopWidth )
				)
		).toBeGreaterThanOrEqual( 3 );
		// Two cards in one list: the wheels on an 8″ deck and its trucks.
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		const cards = page.locator( '.cbuild-card' );
		await cards.first().click();
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
		await pickDeck( page, '8.0' );
		await settled( page );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.first()
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
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

	// Issue #46: "No motion under prefers-reduced-motion"; elsewhere the board turns as the step crosses over.
	for ( const motion of [ 'no-preference', 'reduce' ] as const ) {
		test( `turns the board over ${
			'reduce' === motion
				? 'without moving it under reduced motion'
				: 'in 150 to 250 ms by its transform alone'
		}`, async ( { page } ) => {
			await page.emulateMedia( { reducedMotion: motion } );
			await page.goto(
				linkTo( {
					deck: id( 'builder-test-deck-8-0' ),
					step: 'trucks',
				} )
			);
			const turn = page
				.locator( '#cbuild-picture' )
				.getByRole( 'button', { name: 'Show the top' } );
			await expect( turn ).toBeVisible();
			await watch( page );
			await turn.click();
			await expect( turn ).toHaveAttribute( 'aria-pressed', 'true' );
			const turned = ( await seen( page ) ).animations.filter(
				( animation ) => 'cbuild-turn' === animation.name
			);
			if ( 'reduce' === motion ) {
				expect( turned ).toEqual( [] );
				return;
			}
			expect( turned.length ).toBeGreaterThan( 0 );
			for ( const animation of turned ) {
				expect( animation.duration ).toBeGreaterThanOrEqual( 150 );
				expect( animation.duration ).toBeLessThanOrEqual( 250 );
				expect( animation.properties ).toEqual( [ 'transform' ] );
			}
		} );
	}
} );

// Issue #46, settled on 17 November 2026: the board has a top, drawn from the deck's own layer image, with the grip
// filled into it and a bolt head at each hole; the grip step shows it, and a button by the board turns it over.
// Spec: The layout. The stage holds the whole board at every width: beside the panel on a desktop, and in the short
// strip on top when the layout is stacked, a tablet's as a phone's, where the board is turned a quarter turn (D-280).
test.describe( 'The board on the stage', () => {
	for ( const [ theme, width, height ] of [
		[ 'kadence', 390, 844 ],
		[ 'kadence', 820, 1180 ],
		[ 'kadence', 1000, 800 ],
		[ 'kadence', 1280, 900 ],
		// Storefront's content is 786 pixels wide here: the stacked layout on a desktop's short screen.
		[ 'storefront', 1280, 900 ],
	] as const ) {
		test( `is drawn whole inside the stage on ${ theme } at ${ width } by ${ height }`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || 'http://localhost:8898',
				},
			] );
			await page.setViewportSize( { width, height } );
			// At the review, with the whole build on the board and the parts that have no layer named above it.
			await page.goto( fixture().page );
			await page
				.locator( '.cbuild-presets' )
				.getByRole( 'button', { name: 'Start from Street' } )
				.click();
			await expect( page.locator( '.cbuild-review' ) ).toBeVisible();
			await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
				'aria-busy',
				'false'
			);
			const stage = await page.locator( '.cbuild__stage' ).boundingBox();
			const board = await page
				.locator(
					'.cbuild__stage .cbuild-board:not(.cbuild-board--bar):visible'
				)
				.boundingBox();
			expect( stage ).not.toBeNull();
			expect( board ).not.toBeNull();
			if ( stage && board ) {
				expect( board.y, 'its top' ).toBeGreaterThanOrEqual(
					stage.y - 1
				);
				expect(
					board.y + board.height,
					'its foot'
				).toBeLessThanOrEqual( stage.y + stage.height + 1 );
				expect( board.x ).toBeGreaterThanOrEqual( stage.x - 1 );
				expect( board.x + board.width ).toBeLessThanOrEqual(
					stage.x + stage.width + 1
				);
			}
		} );
	}
} );

test.describe( 'The board turned over', () => {
	const picture = ( page: Page ) => page.locator( '#cbuild-picture' );
	const top = ( page: Page ) =>
		picture( page ).locator( '.cbuild-board--top' );
	const under = ( page: Page ) =>
		picture( page ).locator( '.cbuild-board:not(.cbuild-board--top)' );
	const description = ( page: Page ) =>
		picture( page ).locator( '.cbuild-picture__description' );

	test( 'the grip step shows the top, filled with the grip and the bolt heads, and the next step the underside', async ( {
		page,
	} ) => {
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-54' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1-25' ),
				step: 'grip',
			} )
		);
		await expect( heading( page ) ).toHaveText(
			/: Choose your grip tape$/
		);
		await expect( top( page ) ).toBeVisible();
		await expect( under( page ) ).toBeHidden();
		await expect(
			top( page ).locator( '.cbuild-board__layer--hardware' ),
			'a head at each of the eight holes'
		).toHaveCount( 8 );
		await expect(
			top( page ).locator( '.cbuild-board__layer--wheels' )
		).toHaveCount( 4 );
		await expect( description( page ) ).toContainText(
			'Seen from the top.'
		);
		await expect(
			picture( page )
				.locator( '.cbuild-picture__item:visible' )
				.filter( { hasText: 'Hardware: ' } ),
			'the heads are drawn, so the bolts have no labelled photo'
		).toHaveCount( 0 );

		// With the grip picked: by its link, so the page is drawn with it with the script or without.
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-54' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1-25' ),
				grip: id( 'builder-test-grip' ),
				step: 'grip',
			} )
		);
		await expect(
			top( page ).locator( '.cbuild-board__layer--grip' )
		).toHaveCount( 1 );
		const grip = await top( page )
			.locator( '.cbuild-board__layer--grip' )
			.evaluate( ( layer ) => {
				const style = getComputedStyle( layer );
				return {
					image: style.backgroundImage,
					mask: style.maskImage || style.webkitMaskImage,
				};
			} );
		const deck = await top( page )
			.locator( '.cbuild-board__layer--deck' )
			.evaluate( ( layer ) => {
				const style = getComputedStyle( layer );
				return style.maskImage || style.webkitMaskImage;
			} );
		// The layer image or its resized copy, which wp-env makes (D-214): named by the file or by its attachment.
		expect( grip.image, 'the swatch' ).toMatch(
			/builder-test-grip|\/layers\/\d+-/
		);
		expect( grip.mask, "in the deck's outline" ).toBe( deck );
		expect( grip.image, 'the swatch is not the deck' ).not.toBe(
			grip.mask
		);

		// With the script or without: the click answers in place or loads the next page.
		await page.getByRole( 'button', { name: 'Next: Review' } ).click();
		await expect( heading( page ) ).toHaveText( /: Check your build$/ );
		await expect( under( page ) ).toBeVisible();
		await expect( top( page ) ).toBeHidden();
		await expect( description( page ) ).toContainText(
			'Seen from underneath.'
		);
	} );

	test( "turns with its button from the keyboard, says the side, keeps the shopper's side and shows it in the bar", async ( {
		page,
	}, info ) => {
		test.skip(
			info.project.name === 'no-js',
			"The button is the script's: without it the step's own side is shown."
		);
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		const turn = picture( page ).getByRole( 'button', {
			name: 'Show the top',
		} );
		await expect( turn ).toHaveAttribute( 'aria-pressed', 'false' );
		await turn.focus();
		await page.keyboard.press( 'Enter' );
		await expect( turn ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect( turn, 'the shopper keeps their place' ).toBeFocused();
		await expect( top( page ) ).toBeVisible();
		await expect( under( page ) ).toBeHidden();
		await expect( description( page ) ).toContainText(
			'Seen from the top.'
		);
		const bar = page.locator( '.cbuild-bar__picture' );
		if ( await bar.isVisible() ) {
			await expect(
				bar.locator( '.cbuild-board--top' ),
				"the phone bar's board shows the same side"
			).toBeVisible();
			await expect(
				bar.locator( '.cbuild-board:not(.cbuild-board--top)' )
			).toBeHidden();
		}
		await axe( page, 'the board turned to its top', '#cbuild-picture' );

		await choose( page, 'Builder test wheels 54' );
		await page.getByRole( 'button', { name: 'Next: Bearings' } ).click();
		await expect( heading( page ) ).toHaveText( /: Choose your bearings$/ );
		// The step has landed when focus is on its heading and the builder is no longer busy: pressed before, focus
		// went on to the heading under the key (2 runs in 10 before this wait).
		await expect( heading( page ) ).toBeFocused();
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect(
			turn,
			'the top stays on a step that shows the underside: the shopper chose it'
		).toHaveAttribute( 'aria-pressed', 'true' );
		await turn.focus();
		await page.keyboard.press( 'Space' );
		await expect( turn ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( under( page ) ).toBeVisible();
		await expect( description( page ) ).toContainText(
			'Seen from underneath.'
		);
	} );

	test( 'has no button without the script, and a deck with no layer image has no top', async ( {
		page,
	}, info ) => {
		// Every slot before the grip picked: nothing is picked for the shopper (D-213), so a link stops at the first
		// empty one.
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-25' ),
				trucks: id( 'builder-test-trucks-149' ),
				wheels: id( 'builder-test-wheels-54' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1-25' ),
				step: 'grip',
			} )
		);
		await expect( heading( page ) ).toHaveText(
			/: Choose your grip tape$/
		);
		await expect(
			picture( page ).getByRole( 'button', { name: 'Show the top' } )
		).toHaveCount( 0 );
		await expect( top( page ) ).toBeHidden();
		// The underside is drawn, from the trucks and wheels, and is the only side there is.
		await expect( description( page ) ).toContainText(
			'Seen from underneath.',
			{ useInnerText: true }
		);
		await expect( description( page ) ).not.toContainText(
			'Seen from the top.',
			{ useInnerText: true }
		);
		if ( info.project.name === 'no-js' ) {
			await page.goto(
				linkTo( {
					deck: id( 'builder-test-deck-8-0' ),
					step: 'trucks',
				} )
			);
			await expect(
				picture( page ).getByRole( 'button', { name: 'Show the top' } )
			).toHaveCount( 0 );
			await expect( under( page ) ).toBeVisible();
		}
	} );
} );

// Cowork's review, should-fix 2, 3 and 9, minor 23 (D-174): on a classic theme's product template the builder had
// the summary column beside the gallery's placeholder, the assembly fee stood under the kit's title as its price, and
// under Kadence the filters' drawer heading showed on a desktop and the filters took its primary button colour.
test.describe( "A kit's own page", () => {
	for ( const theme of [ 'kadence', 'storefront', 'twentytwentyfive' ] ) {
		test( `gives the builder the content's width on ${ theme }, with no gallery and no price under the title, and one h1`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const url = baseURL || 'http://localhost:8898';
			await context.addCookies( [
				{ name: 'cbuild_e2e_theme', value: theme, url },
			] );
			await page.setViewportSize( { width: 1280, height: 900 } );
			await page.goto( '/product/cart-test-kit/' );
			const builder = page.locator( '.cbuild' ).first();
			await expect( builder ).toBeVisible();
			// One h1, the builder's, where it takes the place of the theme's title, and a way back to the shop (spec:
			// Where it renders). A classic theme's summary keeps no title or form of its own, and the builder is
			// before it, not in it.
			await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
			await expect( builder.locator( 'h1.cbuild__title' ) ).toHaveText(
				'Cart test kit'
			);
			await expect(
				builder.getByRole( 'link', { name: 'Back to the shop' } )
			).toHaveAttribute( 'href', /\/shop\/$/ );
			await expect( page.locator( '.summary .cbuild' ) ).toHaveCount( 0 );
			await expect( page.locator( '.summary form.cart' ) ).toHaveCount(
				0
			);
			const width = ( await builder.boundingBox() )?.width ?? 0;
			// The theme's main column, which on Storefront sits beside its sidebar.
			const main =
				( await page.locator( 'main' ).first().boundingBox() )?.width ??
				0;
			expect(
				width,
				'the builder has the content, not a column of it'
			).toBeGreaterThan( main * 0.85 );
			await expect(
				page
					.locator(
						'.woocommerce-product-gallery, .wp-block-woocommerce-product-image-gallery'
					)
					.filter( { visible: true } )
			).toHaveCount( 0 );
			await expect(
				page
					.locator(
						'.summary > .price, .wp-block-column:has(.cbuild) .wp-block-woocommerce-product-price'
					)
					.filter( { hasText: '$' } )
			).toHaveCount( 0 );
			// From 64rem the stage and the panel side by side: the panel after the stage, to its right, or to its left
			// in a right-to-left language. Narrower, as Storefront's content column is on a desktop, the panel is under
			// the stage, as on a tablet (D-268, D-271).
			const steps = await page
				.locator( '.cbuild__stage' )
				.first()
				.boundingBox();
			const summary = await page
				.locator( '.cbuild__panel' )
				.first()
				.boundingBox();
			const wide =
				width >=
				64 *
					parseFloat(
						await page.evaluate(
							() =>
								getComputedStyle( document.documentElement )
									.fontSize
						)
					);
			const centre = ( box: typeof steps ) =>
				( box?.x ?? 0 ) + ( box?.width ?? 0 ) / 2;
			const rtl = await page.evaluate(
				() =>
					getComputedStyle( document.documentElement ).direction ===
					'rtl'
			);
			if ( wide ) {
				expect(
					( centre( summary ) - centre( steps ) ) * ( rtl ? -1 : 1 ),
					'the panel beside the stage, after it'
				).toBeGreaterThan( ( steps?.width ?? 0 ) / 2 );
			} else {
				expect(
					summary?.y ?? 0,
					'the panel under the stage'
				).toBeGreaterThanOrEqual(
					( steps?.y ?? 0 ) + ( steps?.height ?? 0 ) - 1
				);
			}
		} );
	}

	// Claude in Cowork's recheck, finding 2: under Kadence Shop Kit's product template the gallery's placeholder was
	// still drawn and the builder had the row's second column. As Zach answered it, the plugin hides the column that
	// holds Shop Kit's gallery block and lets the one that holds the builder span the row, by Shop Kit's block classes
	// and never the row's own ID (tests/e2e/fixtures/shop-kit-product.php has the markup the report quotes).
	for ( const size of [
		{ name: 'desktop', width: 1440, height: 900 },
		{ name: 'phone', width: 412, height: 900 },
	] ) {
		test( `gives the builder the row under Shop Kit's product template, with no gallery, on a ${ size.name }`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const url = baseURL || 'http://localhost:8898';
			await context.addCookies( [
				{ name: 'cbuild_e2e_theme', value: 'kadence', url },
				{ name: 'cbuild_e2e_template', value: 'shop-kit', url },
			] );
			await page.setViewportSize( size );
			await page.goto( '/product/cart-test-kit/' );
			const row = page.locator( '.kt-row-column-wrap' );
			await expect( row ).toHaveCount( 1 );
			await expect(
				page.locator( '.wp-block-kadence-wootemplate-blocks-gallery' )
			).toBeHidden();
			const builder = page.locator( '.cbuild' ).first();
			await expect( builder ).toBeVisible();
			const width = ( await builder.boundingBox() )?.width ?? 0;
			const across = ( await row.boundingBox() )?.width ?? 0;
			expect( width, 'the builder has the row' ).toBeGreaterThan(
				across * 0.95
			);
			// The template's own title is the page's one h1: the builder adds none, and still has its way back.
			await expect( page.locator( 'h1' ) ).toHaveCount( 1 );
			await expect( builder.locator( 'h1' ) ).toHaveCount( 0 );
			await expect(
				builder.getByRole( 'link', { name: 'Back to the shop' } )
			).toBeVisible();
			// The title, then the builder: nothing of the gallery's above them.
			const title = await page.locator( '#buy-box h1' ).boundingBox();
			expect( title?.y ?? 9999 ).toBeLessThan(
				( ( await row.boundingBox() )?.y ?? 0 ) + 10
			);
			if ( size.name === 'desktop' ) {
				const steps = await page
					.locator( '.cbuild__stage' )
					.first()
					.boundingBox();
				const summary = await page
					.locator( '.cbuild__panel' )
					.first()
					.boundingBox();
				// Beside it on either side, as a right-to-left page has it on the left: it starts above its foot.
				expect(
					summary?.y ?? 99999,
					'the panel beside the stage, not under it'
				).toBeLessThan( ( steps?.y ?? 0 ) + ( steps?.height ?? 0 ) );
			}
		} );
	}

	test( 'draws the widths second to Next, under Kadence', async ( {
		page,
		context,
		baseURL,
	} ) => {
		const url = baseURL || 'http://localhost:8898';
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: 'kadence', url },
		] );
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( fixture().page );
		const width = page.locator( '.cbuild-width__option' ).first();
		await expect( width ).toBeVisible();
		// The Next a shopper sees: the footer's (D-271).
		const action = page.locator( '.cbuild-bar__next:not([hidden])' );
		const ground = ( locator: typeof width ) =>
			locator.evaluate(
				( element ) => getComputedStyle( element ).backgroundColor
			);
		expect( await ground( width ) ).toBe( 'rgba(0, 0, 0, 0)' );
		expect( await ground( action ) ).not.toBe( 'rgba(0, 0, 0, 0)' );
	} );
} );

test.describe( 'The phone bar', () => {
	const PHONE = { width: 390, height: 700 };

	// The prototype's footer: the same bar holds the total on a phone and under the panel on a desktop, and the
	// summary's sidebar is gone (D-271).
	test( 'holds the total on a phone and on a desktop, with no summary beside it', async ( {
		page,
	} ) => {
		await page.setViewportSize( PHONE );
		await page.goto( fixture().page );
		const bar = page.locator( '.cbuild-bar' );
		await expect( bar ).toBeVisible();
		await expect( page.locator( '.cbuild-summary' ) ).toHaveCount( 0 );
		// Cowork's review, minor 18: the disclosure says what it does, open and shut.
		const summary = bar.locator( 'summary' );
		await expect( summary ).toHaveText( /Show the parts$/, {
			useInnerText: true,
		} );
		await summary.click();
		await expect( summary ).toHaveText( /Hide the parts$/, {
			useInnerText: true,
		} );
		await summary.click();
		await page.setViewportSize( { width: 1280, height: 800 } );
		await expect( bar ).toBeVisible();
		await expect( bar.locator( '.cbuild-bar__amount' ) ).toBeVisible();
		// Zach's call on the comparison with the prototype: the sum is labelled "Total" above it, as the prototype's is.
		// A screen reader hears the total once, in its own words, so the label is not read.
		const label = bar.locator( '.cbuild-bar__label' );
		await expect( label ).toHaveText( 'Total' );
		await expect( label ).toHaveAttribute( 'aria-hidden', 'true' );
		const above = await label.boundingBox();
		const amount = await bar.locator( '.cbuild-bar__amount' ).boundingBox();
		expect(
			above && amount && above.y + above.height <= amount.y + 1,
			'the label above the sum'
		).toBe( true );
	} );

	// Claude in Cowork's recheck, N6: at 412 pixels under Kadence, "Show the parts" broke over two lines beside the
	// total, and the theme's marker for a disclosure showed beside it. Under the total, whole, it also leaves the bar
	// as high whatever the total is, so Next stays where it was as a pick widens it.
	for ( const theme of [ 'storefront', 'kadence', 'twentytwentyfive' ] ) {
		test( `says "Show the parts" whole under the total on ${ theme }, with no marker, and keeps its height`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		} ) => {
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || 'http://localhost:8898',
				},
			] );
			await page.setViewportSize( { width: 412, height: 900 } );
			await page.goto( fixture().page );
			const bar = page.locator( '.cbuild-bar' );
			const total = bar.locator( 'summary' );
			await expect( total ).toBeVisible();
			const read = () =>
				total.evaluate( ( summary ) => {
					const more = summary.querySelector(
						'.cbuild-bar__more--show'
					)!;
					// The amount's own text, inside the shown part, which holds the estimate's line under it (D-230).
					const sum = summary.querySelector(
						'.cbuild-bar__amount > :first-child'
					)!;
					const estimate = summary.querySelector(
						'.cbuild-bar__estimate'
					)!;
					return {
						shown: sum.getClientRects().length,
						estimate: estimate.getBoundingClientRect().height,
						display: getComputedStyle( summary ).display,
						lines: more.getClientRects().length,
						under:
							more.getBoundingClientRect().top >=
							sum.getBoundingClientRect().bottom - 1,
						height: Math.round(
							summary
								.closest( '.cbuild-bar' )!
								.getBoundingClientRect().height
						),
					};
				} );
			const before = await read();
			expect( before.display, 'no list item, so no marker' ).not.toBe(
				'list-item'
			);
			expect( before.lines, '"Show the parts" on one line' ).toBe( 1 );
			expect( before.under, 'under the total' ).toBe( true );
			// Without JavaScript a pick changes the total with the next page, which draws the bar afresh.
			if ( ! javaScriptEnabled ) {
				return;
			}
			await pickDeck( page, '8.0' );
			await settled( page );
			await expect( total ).not.toContainText( '$15.00' );
			const after = await read();
			// Beside the board's picture "Total:" broke the line on CI's fonts, and the bar grew under the thumb
			// (D-207): the amount alone is shown.
			expect( after.shown, 'the amount on one line' ).toBe( 1 );
			// The deck brings the estimate, a line of its own under the amount (F2, D-230), which may grow the bar by
			// at most that line. Without it the bar is as high as before: a wider total alone never grows it.
			expect( before.estimate, 'no estimate on a blank build' ).toBe( 0 );
			expect(
				after.estimate,
				'the estimate, once a deck is picked'
			).toBeGreaterThan( 0 );
			expect(
				after.height - before.height,
				"grown by the estimate's line at most"
			).toBeLessThanOrEqual( Math.ceil( after.estimate ) );
			await total.evaluate( ( summary ) => {
				(
					summary.querySelector(
						'.cbuild-bar__estimate'
					) as HTMLElement
				 ).style.display = 'none';
			} );
			expect(
				( await read() ).height,
				'as high with a wider total, the estimate aside'
			).toBe( before.height );
		} );

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

	// The auditor's M10 review, 1 (WCAG 2.4.11): on a phone the stage sticks at the top of the screen, and a control
	// that takes focus going back up the step, or the heading Next moves focus to, is never behind it.
	// At a phone's width and a tablet's, where the stage sticks on top too (the spec reviewer's M10 S7).
	for ( const [ width, height ] of [
		[ 390, 700 ],
		[ 820, 1180 ],
	] as const ) {
		test( `keeps the control in focus clear of the stage on top at ${ width } by ${ height }, going back up the step and after Next`, async ( {
			page,
			javaScriptEnabled,
		} ) => {
			await page.setViewportSize( { width, height } );
			await page.goto( linkTo( { _width: '8in' } ) );
			await expect(
				page.locator( '.cbuild-card' ).first()
			).toBeVisible();
			const seen = () =>
				page.evaluate( () => {
					const focused = document.body.ownerDocument.activeElement;
					if ( ! ( focused instanceof HTMLElement ) ) {
						return 'nothing in focus';
					}
					const box = focused.getBoundingClientRect();
					const at = document.elementFromPoint(
						box.left + Math.min( box.width / 2, 20 ),
						box.top + Math.min( box.height / 2, 10 )
					);
					if ( at?.closest( '.cbuild__stage' ) ) {
						return 'behind the stage';
					}
					if (
						at?.closest( '.cbuild-bar' ) &&
						! focused.closest( '.cbuild-bar' )
					) {
						return 'under the bar';
					}
					return 'clear';
				} );
			await page.evaluate( () =>
				window.scrollTo( 0, document.documentElement.scrollHeight )
			);
			await page
				.locator( '.cbuild-cards input[type="radio"]' )
				.last()
				.focus();
			for ( let back = 0; back < 6; back++ ) {
				await page.keyboard.press( 'Shift+Tab' );
				expect( await seen(), `after ${ back + 1 } Shift+Tab` ).toBe(
					'clear'
				);
			}
			if ( ! javaScriptEnabled ) {
				return;
			}
			await page.locator( '.cbuild-card' ).first().click();
			await settled( page );
			await page.evaluate( () =>
				window.scrollTo( 0, document.documentElement.scrollHeight )
			);
			await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
			await expect( heading( page ) ).toBeFocused();
			expect( await seen(), "the next step's heading" ).toBe( 'clear' );
		} );
	}

	test( 'opens the parts list above itself, and its button moves the build on', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.setViewportSize( PHONE );
		await page.goto( linkTo( { _width: '8in' } ) );
		const bar = page.locator( '.cbuild-bar' );
		await page.locator( '.cbuild-card' ).first().click();
		// The shopper opens the list on the page the pick's answer drew, as `settled()` says: a tap pressed as the
		// answer scrolled the page under the bar was lost in WebKit (CI run 43554697648). The two tests after this
		// one hold the list open across the answer.
		if ( javaScriptEnabled ) {
			await settled( page );
		}
		await bar.locator( 'summary' ).click();
		const lines = bar.locator( '.cbuild-bar__lines' );
		await expect( lines ).toBeVisible();
		const [ list, own ] = await Promise.all( [
			lines.boundingBox(),
			bar.boundingBox(),
		] );
		// Compared in sixtieths of a pixel, the unit Firefox lays out in: with the estimate the bar's top falls on a
		// fraction, and Firefox gave the sum 599.8333740 against 599.8333129 for one and the same line (CI run
		// 44264444652, D-234).
		const sixtieths = ( px: number ) => Math.round( px * 60 );
		expect( sixtieths( list!.y + list!.height ) ).toBeLessThanOrEqual(
			sixtieths( own!.y + 1 )
		);
		// With JavaScript the store moves the step without a page load, and without it the form is sent.
		await bar.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
	} );

	test( 'keeps the parts list open when a pick is answered after it opens', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			'Without JavaScript a pick is answered by a page load.'
		);
		await page.setViewportSize( PHONE );
		let release = (): void => {};
		const held = new Promise< void >( ( done ) => ( release = done ) );
		await page.route( /\/cbuild\/v1\/kits\/\d+\/state/, async ( route ) => {
			await held;
			await route.continue();
		} );
		await page.goto( linkTo( { _width: '8in' } ) );
		const bar = page.locator( '.cbuild-bar' );
		await page.locator( '.cbuild-card' ).first().click();
		await page.waitForRequest( /\/cbuild\/v1\/kits\/\d+\/state/ );
		await bar.locator( 'summary' ).click();
		const lines = bar.locator( '.cbuild-bar__lines' );
		await expect( lines ).toBeVisible();
		const reply = page.waitForResponse( /\/cbuild\/v1\/kits\/\d+\/state/ );
		release();
		await reply;
		await settled( page );
		await expect( lines ).toBeVisible();
	} );

	test( 'opens the parts list on a press held while a pick is answered', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			'Without JavaScript a pick is answered by a page load.'
		);
		await page.setViewportSize( PHONE );
		let release = (): void => {};
		const held = new Promise< void >( ( done ) => ( release = done ) );
		await page.route( /\/cbuild\/v1\/kits\/\d+\/state/, async ( route ) => {
			await held;
			await route.continue();
		} );
		await page.goto( linkTo( { _width: '8in' } ) );
		await page.locator( '.cbuild-card' ).first().click();
		await page.waitForRequest( /\/cbuild\/v1\/kits\/\d+\/state/ );
		const total = await page.locator( '.cbuild-bar summary' ).boundingBox();
		await page.mouse.move(
			( total?.x ?? 0 ) + ( total?.width ?? 0 ) / 2,
			( total?.y ?? 0 ) + ( total?.height ?? 0 ) / 2
		);
		await page.mouse.down();
		const reply = page.waitForResponse( /\/cbuild\/v1\/kits\/\d+\/state/ );
		release();
		await reply;
		await settled( page );
		await page.mouse.up();
		await expect( page.locator( '.cbuild-bar__lines' ) ).toBeVisible();
	} );

	for ( const [ name, picked ] of [
		[ 'a blank build', false ],
		// Cowork's 0.8.0 review, F2 (D-230): with the estimate the bar is taller.
		[ 'the bar giving the estimate', true ],
	] as const ) {
		test( `never covers the control in focus, ${ name }`, async ( {
			page,
		} ) => {
			await page.setViewportSize( PHONE );
			const at = new URL( fixture().page );
			if ( picked ) {
				at.searchParams.set(
					'cbuild[deck]',
					String( fixture().parts[ 'builder-test-deck-8-25' ] )
				);
				at.searchParams.set( 'cbuild[step]', 'trucks' );
			}
			await page.goto( at.toString() );
			if ( picked ) {
				await expect(
					page.locator( '.cbuild-bar__estimate' )
				).toBeVisible();
			}
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
	}

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
			await expect( heading( page ) ).toHaveText(
				'Step 7 of 7: Check your build'
			);
			expect( twice( await names() ) ).toEqual( [] );
			await expect(
				page.getByRole( 'button', { name: 'Add to cart' } )
			).toHaveCount( 1 );
		} );
	}
} );

// Roadmap, milestone 8 (D-217): a signed-in customer saves the review, finds it under My builds, opens it as a way
// into the builder, saves it again over itself, and deletes it; a visitor is asked to sign in. With the script and
// without it: every form is a plain post.
test.describe( 'Saved builds', () => {
	const full = () =>
		linkTo( {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-54' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1-25' ),
			grip: id( 'builder-test-grip' ),
			step: 'review',
		} );

	test( 'a visitor is asked to sign in, and comes back to the same build', async ( {
		page,
	} ) => {
		await page.goto( full() );
		await expect( heading( page ) ).toHaveText( /: Check your build$/ );
		await expect(
			page.getByRole( 'button', { name: 'Save build' } )
		).toBeHidden();
		const signIn = page.getByRole( 'link', {
			name: 'Sign in to save this build',
		} );
		await expect( signIn ).toBeVisible();
		const to = new URL( ( await signIn.getAttribute( 'href' ) ) || '' );
		expect( to.pathname ).toContain( 'wp-login.php' );
		const back = to.searchParams.get( 'redirect_to' ) || '';
		expect( back ).toContain( 'cbuild' );
		expect( back ).toContain( String( id( 'builder-test-deck-8-0' ) ) );
	} );

	test( 'a customer saves, opens, saves over and deletes a build under My builds', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		await login( page );
		// Saving is no add: the cart's lines are as they were after both saves (CI run 44119225891).
		const lines = async () =>
			(
				await (
					await page.request.get( '/wp-json/wc/store/v1/cart' )
				).json()
			).items_count as number;
		const before = await lines();
		const name = `E2E ${ info.project.name } ${ Date.now() }`;
		await page.goto( full() );
		await expect( heading( page ) ).toHaveText( /: Check your build$/ );
		await page.getByLabel( 'Name it (optional)' ).fill( name );
		await answered( page, () =>
			page.getByRole( 'button', { name: 'Save build' } ).click()
		);
		await expect( page ).toHaveURL( /my-builds/ );
		await expect(
			page.getByText( 'The build is saved. It is listed below.' )
		).toBeVisible();
		const row = page
			.locator( '.cbuild-my-builds__build' )
			.filter( { hasText: name } );
		await expect( row ).toHaveCount( 1 );
		if ( javaScriptEnabled ) {
			// axe runs in the page, so not where scripts are off.
			await axe( page, 'My builds', '.woocommerce-MyAccount-content' );
		}

		// Open: the builder at the review, checked again, and Save build there updates it.
		await row.getByRole( 'link', { name: 'Open' } ).click();
		await expect( heading( page ) ).toHaveText( /: Check your build$/ );
		expect( page.url() ).toContain( 'cbuild_saved=' );
		await expect(
			page.getByLabel( 'Link to this build' ),
			'the saved build is no part of a shared link'
		).not.toHaveValue( /cbuild_saved/ );
		// answered() waits for the next page's load, so the page it acts on must have had its own: WebKit, pressed
		// before then, sent no form and the wait took the opened page's load for the answer (CI run 44119225891, #50).
		await page.waitForLoadState( 'load' );
		await answered( page, () =>
			page.getByRole( 'button', { name: 'Save build' } ).click()
		);
		await expect(
			page.getByText( 'Your saved build is updated.' )
		).toBeVisible();
		await expect(
			page
				.locator( '.cbuild-my-builds__build' )
				.filter( { hasText: name } ),
			'kept its name, and is one build still'
		).toHaveCount( 1 );
		expect( await lines(), 'nothing was added to the cart' ).toBe( before );

		await answered( page, () =>
			page
				.locator( '.cbuild-my-builds__build' )
				.filter( { hasText: name } )
				.getByRole( 'button', { name: 'Delete' } )
				.click()
		);
		await expect( page.getByText( 'The build is deleted.' ) ).toBeVisible();
		await expect(
			page
				.locator( '.cbuild-my-builds__build' )
				.filter( { hasText: name } )
		).toHaveCount( 0 );

		// Undo (Cowork's 0.8.0 review, F3; D-231): named for the build, reached and pressed by keyboard, and the build
		// is back, with and without JavaScript.
		const undo = page.getByRole( 'button', {
			name: `Undo deleting ${ name }`,
		} );
		await expect( undo ).toBeVisible();
		if ( javaScriptEnabled ) {
			await axe(
				page,
				`${ info.project.name }: My builds, Undo offered`,
				'.woocommerce-MyAccount-content'
			);
			// WooCommerce puts focus on its notice as the page loads and again 500 ms later. Focus taken before the
			// second was moved under the press, and Enter fell on the notice (CI runs 44289674928, 44307517356): the
			// shopper reaches Undo after both.
			await noticeFocusDone( page );
			await expect(
				page
					.locator( '[role="alert"]' )
					.filter( { hasText: 'The build is deleted.' } )
			).toBeFocused();
		}
		await undo.focus();
		await expect( undo ).toBeFocused();
		await answered( page, () => page.keyboard.press( 'Enter' ) );
		await expect(
			page.getByText( 'The build is back in My builds.' )
		).toBeVisible();
		const back = page
			.locator( '.cbuild-my-builds__build' )
			.filter( { hasText: name } );
		await expect( back ).toHaveCount( 1 );
		if ( javaScriptEnabled ) {
			// Focus goes where WooCommerce puts it after any action in My account: on its notice, which says the
			// build is back (woocommerce.js, focus_populate_live_region; D-231).
			await expect(
				page
					.locator( '[role="alert"]' )
					.filter( { hasText: 'The build is back in My builds.' } ),
				'focus on the notice that says so'
			).toBeFocused();
		}
		await expect(
			page.locator( '.cbuild-my-builds__undo' ),
			'offered once'
		).toHaveCount( 0 );
		await answered( page, () =>
			back.getByRole( 'button', { name: 'Delete' } ).click()
		);
	} );
} );
