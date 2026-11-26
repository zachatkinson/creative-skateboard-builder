import { randomUUID } from 'node:crypto';
import { mkdirSync, readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import {
	test,
	expect,
	answered,
	emojiDone,
	inLocale,
	noticeFocusDone,
	Page,
	type Locator,
	SITE,
} from './support/test';
import type { BrowserContext } from '@playwright/test';
import { axe } from './support/axe';
import {
	holdAnswers,
	now as pageNow,
	shiftsSince,
	themeHeader,
	watchShifts,
} from './support/held';
import { wp } from './support/cli';
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
	/** The shelf kit (tests/e2e/shelf.spec.ts): 46 decks at 8.25″ in stock over four brands. */
	shelf: { kit: number; page: string };
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
			// French writes the decimal comma: 8,25″.
			hasText: new RegExp( `^${ width.replace( '.', '[.,]' ) }$` ),
		} ),
	} );
	// Once one is chosen the widths fold behind the line that says it, with Change.
	const chosen = page.locator( '.cbuild-width__chosen' );
	if ( await chosen.isVisible() ) {
		await chosen.click();
	}
	await button.click();
	await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
	// The store folds the question at the press (D-356) and keeps its mark until the answer is in. Without JavaScript
	// the page is the server's, with neither the mark nor aria-busy.
	await expect( page.locator( '.cbuild[data-folded]' ) ).toHaveCount( 0 );
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

	// #100, F15: without the script nothing is dimmed, Next sends the form, and the server answers as it always has.
	test( 'Next with no pick is not dimmed, sends the form, and the server answers with its error summary', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		const button = page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.filter( { visible: true } );
		await expect( button ).not.toHaveAttribute( 'aria-disabled' );
		await next( page, 'Next: Trucks' );
		const summary = page.locator( '.cbuild-errors' );
		await expect( summary ).toBeVisible();
		await expect(
			summary.getByRole( 'link', {
				name: 'Deck: nothing is chosen yet.',
			} )
		).toBeVisible();
		await expect( page ).toHaveTitle( /^Error: Step 1 of 7: Deck - / );
		await expect( page.locator( '.cbuild-bar__missing' ) ).toHaveText( '' );
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

		// The footer's Add to cart: the review's own gives way to it at every width (P11; spec: The layout, Footer).
		await answered( page, () =>
			page
				.locator( '.cbuild' )
				.getByRole( 'button', { name: 'Add to cart' } )
				.click()
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

	test( 'a preset tile starts its build at the review, and one with a sold-out part has no tile, opens by its link, names it and keeps it from the cart', async ( {
		page,
	} ) => {
		await page.goto( fixture().page );
		const tiles = page.locator( '.cbuild-presets' );
		await expect(
			tiles.getByRole( 'heading', { name: 'Start from a ready build' } )
		).toBeVisible();
		// #104 (R1): Cruiser's wheels are sold out, so its tile is left out; the Catalogue check lists it.
		await expect(
			tiles.getByRole( 'button', { name: 'Start from Cruiser' } )
		).toHaveCount( 0 );
		await expect(
			tiles.getByText( 'Wheels: Builder test wheels 56 is sold out.' )
		).toHaveCount( 0 );

		await answered( page, () =>
			tiles.getByRole( 'button', { name: 'Start from Street' } ).click()
		);
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		await expect( page.locator( '.cbuild-errors' ) ).toBeHidden();
		const review = page.locator( '.cbuild-review' );
		// The line's sentence: its Remove assembly button is beside it.
		await expect(
			review.locator( '.cbuild-review__assembly > p' )
		).toHaveText( 'Assembly: $15.00' );
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
			page
				.locator( '.cbuild' )
				.getByRole( 'button', { name: 'Add to cart' } )
		).toBeEnabled();
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[preset]' )
		).toBe( '0' );
		await expect( page.locator( '.cbuild-presets' ) ).toBeHidden();

		// F6: a link with cbuild[preset] still opens it.
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-5' ),
				trucks: id( 'builder-test-trucks-149' ),
				wheels: id( 'builder-test-wheels-56' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				grip: id( 'builder-test-grip' ),
				step: 'review',
				preset: 1,
			} )
		);
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		await expect( page.locator( '.cbuild-errors' ) ).toContainText(
			'Wheels: Builder test wheels 56 is sold out.'
		);
		await expect(
			page
				.locator( '.cbuild' )
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
			// A request the store has given up on, as the one asked ahead for Next once a pick is made, is gone.
			await route.continue().catch( () => undefined );
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
		// What the store asks once the answer is shown, ahead of a press of the lit Next (D-355), is no pick's.
		expect(
			asked.filter(
				( url ) => 'wheels' === url.searchParams.get( 'cbuild[step]' )
			)
		).toHaveLength( 1 );
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
			// A request the store has given up on, as the one asked ahead for Next once a pick is made, is gone.
			await route.continue().catch( () => undefined );
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

	test( 'gives the route 10 s to answer, then stops waiting, keeps the step and says the answer did not come (D-364)', async ( {
		page,
	} ) => {
		test.slow();
		await ride( page, '8″' );
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => {
			release = done;
		} );
		await page.route( isRoute, async ( route ) => {
			await held;
			// The page gave the request up: there is no one to answer.
			await route.continue().catch( () => undefined );
		} );
		const deck = page.getByRole( 'radio', {
			name: 'Choose Builder test deck 8.0',
		} );
		const asked = Date.now();
		await deck.check();
		const builder = page.locator( '.cbuild' );
		await expect( builder ).toHaveAttribute( 'aria-busy', 'true' );
		// In English in every project, fr-ca's included: french.spec.ts reads it in French.
		const late = 'The shop did not answer in time. Try again in a moment.';
		await expect( page.locator( '.cbuild__limited' ) ).toHaveText( late, {
			timeout: 20000,
		} );
		expect(
			Date.now() - asked,
			'not before the limit'
		).toBeGreaterThanOrEqual( 10000 );
		await expect( page.locator( '.cbuild__status' ) ).toHaveText( late );
		await expect( builder ).toHaveAttribute( 'aria-busy', 'false' );
		await expect( deck, 'the step keeps what it shows' ).toBeChecked();
		expect(
			await page.evaluate( () => document.getAnimations().length ),
			'no moment plays'
		).toBe( 0 );
		expect( await stayed( page ), 'no page was loaded' ).toBe( true );
		// The auditor's M12 minor 7: axe on the state D-364 added, the limit said after a pick.
		await axe( page, 'the 10 s limit said after a pick', '.cbuild' );
		release();
		await page.unroute( isRoute );
		// Asked again, the answer comes and the line goes.
		await page.getByRole( 'button', { name: 'Next: Trucks' } ).click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		await expect( page.locator( '.cbuild__limited' ) ).toBeHidden();
	} );

	// The auditor's second pass on M12, should-fix 3: a width press the limit stopped kept its fold, and only Change
	// asked the question again (D-364).
	test( 'asks the width question again, its options in view, when a width press hits the rate limit', async ( {
		page,
	} ) => {
		const limit = 'Too many requests. Try again in 20 seconds.';
		await page.route( isRoute, ( route ) =>
			route.fulfill( {
				status: 429,
				contentType: 'application/json',
				body: JSON.stringify( {
					code: 'cbuild_rate_limited',
					message: limit,
				} ),
			} )
		);
		const options = page.locator( '.cbuild-width__option' );
		const button = options.filter( {
			has: page.locator( '.cbuild-chip__text', { hasText: /^8″$/ } ),
		} );
		await button.click();
		await expect( page.locator( '.cbuild__limited' ) ).toHaveText( limit );
		await expect( page.locator( '.cbuild__status' ) ).toHaveText( limit );
		await expect( page.locator( '.cbuild[data-folded]' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cbuild-width__chosen' ) ).toBeHidden();
		for ( const option of await options.all() ) {
			await expect( option ).toBeVisible();
			await expect( option ).toHaveAttribute( 'aria-pressed', 'false' );
		}
		await expect( button, 'focus on the width pressed' ).toBeFocused();
		expect( await stayed( page ), 'no page was loaded' ).toBe( true );
		// The auditor's M12 minor 7: axe on the question asked again, with the rate limit's words (D-364).
		await axe(
			page,
			'a width press stopped by the rate limit, the question open again',
			'.cbuild'
		);
		// Pressed again once the limit is past, the width lists its decks.
		await page.unroute( isRoute );
		await button.click();
		await expect( button ).toHaveAttribute( 'aria-pressed', 'true' );
		await expect(
			page.getByRole( 'radio', { name: /^Choose Builder test deck/ } )
		).toHaveCount( 1 );
		await expect( page.locator( '.cbuild__limited' ) ).toBeHidden();
	} );

	// The auditor's M12 minor 7: axe on the other two of D-364's states, the 10 s limit after a width press, with the
	// question open again, and the rate limit after a pick.
	test( 'passes axe on the 10 s limit after a width press and the rate limit after a pick (D-364)', async ( {
		page,
	} ) => {
		test.slow();
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => {
			release = done;
		} );
		await page.route( isRoute, async ( route ) => {
			await held;
			// The page gave the request up: there is no one to answer.
			await route.continue().catch( () => undefined );
		} );
		const button = page.locator( '.cbuild-width__option', {
			has: page.locator( '.cbuild-chip__text', { hasText: /^8″$/ } ),
		} );
		await button.click();
		// In English in every project, fr-ca's included: french.spec.ts reads it in French.
		const late = 'The shop did not answer in time. Try again in a moment.';
		await expect( page.locator( '.cbuild__limited' ) ).toHaveText( late, {
			timeout: 20000,
		} );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect( page.locator( '.cbuild-width__chosen' ) ).toBeHidden();
		await expect( button ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( button, 'focus on the width pressed' ).toBeFocused();
		await axe(
			page,
			'the 10 s limit after a width press, the question open again',
			'.cbuild'
		);
		release();
		await page.unroute( isRoute );

		await ride( page, '8″' );
		const limit = 'Too many requests. Try again in 20 seconds.';
		await page.route( isRoute, ( route ) =>
			route.fulfill( {
				status: 429,
				contentType: 'application/json',
				body: JSON.stringify( {
					code: 'cbuild_rate_limited',
					message: limit,
				} ),
			} )
		);
		const deck = page.getByRole( 'radio', {
			name: 'Choose Builder test deck 8.0',
		} );
		await deck.check();
		await expect( page.locator( '.cbuild__limited' ) ).toHaveText( limit );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect( deck, 'the step keeps what it shows' ).toBeChecked();
		await axe( page, 'the rate limit said after a pick', '.cbuild' );
		expect( await stayed( page ), 'no page was loaded' ).toBe( true );
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
				url: baseURL || SITE,
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

	// #100, F15 (Cowork's 1.0.0 review, the 18 November mockup): Next is dimmed until the step has a pick, and stays in
	// the tab order. Pressed, it says what is missing beside it, in a polite live region, and asks the server nothing:
	// the shopper has done nothing wrong, so there is no error summary.
	test( 'dims Next until the step has a pick, and a press says what is missing beside it, with no error summary', async ( {
		page,
	} ) => {
		const asked: URL[] = [];
		page.on( 'request', ( request ) => {
			if ( isRoute( request.url() ) ) {
				asked.push( new URL( request.url() ) );
			}
		} );
		const button = page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.filter( { visible: true } );
		const said = page.locator( '.cbuild-bar__missing' );
		await expect( button ).toHaveAttribute( 'aria-disabled', 'true' );
		// Playwright's toBeEnabled() counts aria-disabled as disabled, so the attribute itself is what is checked.
		await expect(
			button,
			'aria-disabled, never disabled'
		).not.toHaveAttribute( 'disabled' );
		await expect(
			page.locator( '.cbuild [role="status"]' ),
			'one status region'
		).toHaveCount( 1 );
		await expect( said ).toHaveText( '' );

		await button.focus();
		await expect( button, 'still in the tab order' ).toBeFocused();
		await page.keyboard.press( 'Enter' );
		await expect( said ).toHaveText( 'Choose your deck first.' );
		await expect( said ).toBeVisible();
		await expect( button ).toHaveAccessibleDescription(
			'Choose your deck first.'
		);
		await expect( button, 'focus stays on Next' ).toBeFocused();
		await expect( page.locator( '.cbuild-errors' ) ).toBeHidden();
		await expect( heading( page ) ).toHaveText(
			'Step 1 of 7: Choose your deck'
		);
		await expect( page ).not.toHaveTitle( /^Error:/ );
		// A dimmed Next is aria-disabled, which Playwright's click() waits on as disabled; a shopper can press it, so the click is forced (#100).
		await button.click( { force: true } );
		await expect( said ).toHaveText( 'Choose your deck first.' );
		expect( asked, 'nothing sent for a press with no pick' ).toHaveLength(
			0
		);
		await axe( page, 'Next pressed with no pick', '.cbuild-bar' );

		// A pick lights Next at once and clears what it said; Next then goes on.
		await pickDeck( page, '8.0' );
		await expect( button ).toHaveAttribute( 'aria-disabled', 'false' );
		await expect( said ).toHaveText( '' );
		await settled( page );
		await button.click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		await expect(
			page
				.locator( '.cbuild' )
				.getByRole( 'button', { name: 'Next: Wheels' } )
				.filter( { visible: true } ),
			'the trucks step has no pick yet'
		).toHaveAttribute( 'aria-disabled', 'true' );
		expect( await stayed( page ) ).toBe( true );
	} );

	// The error summary still comes where the server answers with one: a link that asks for a step past an empty one,
	// here reached again with the browser's Back, which the store asks the route for.
	test( 'puts focus on the error summary when Back returns to a step asked for past an empty one, and says Error first', async ( {
		page,
	} ) => {
		await page.goto( linkTo( { step: 'trucks' } ) );
		await page.waitForLoadState( 'load' );
		const summary = page.locator( '.cbuild-errors' );
		const error = summary.getByRole( 'link', {
			name: 'Deck: nothing is chosen yet.',
		} );
		await expect( error ).toBeVisible();
		await mark( page );
		await error.click();
		await expect( summary ).toBeHidden();
		await page.goBack();
		await expect( summary ).toBeVisible();
		await expect( summary ).toBeFocused();
		await expect( page ).toHaveTitle( /^Error: Step 1 of 7: Deck - / );
		await expect( error ).toBeVisible();
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
		// The width is now the step's first line, with Change, which opens the widths again (spec: The deck's
		// width first).
		const chosen = widths.locator( '.cbuild-width__chosen' );
		await expect( chosen ).toContainText(
			inLocale( info.project.name, '8.25″' )
		);
		await chosen.click();
		await expect( width ).toHaveAttribute( 'aria-pressed', 'true' );
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[_width]' )
		).toBe( '8.25in' );
		expect( await stayed( page ) ).toBe( true );
	} );

	test( "keeps the widths open where Change was pressed before the width's answer came", async ( {
		page,
	}, info ) => {
		// A width folds its question at the press (D-356), so its Change can be pressed before the answer; the answer
		// closed the widths again, and the shopper's Change was lost (CI run 45590634639, webkit-phone).
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => {
			release = done;
		} );
		await page.route( isRoute, async ( route ) => {
			await held;
			await route.continue().catch( () => undefined );
		} );
		const width = page.locator( '.cbuild-width' ).getByRole( 'button', {
			name: inLocale( info.project.name, '8.25″, 1' ),
		} );
		await width.focus();
		await page.keyboard.press( 'Enter' );
		const chosen = page.locator( '.cbuild-width__chosen' );
		await expect( chosen ).toBeFocused();
		await page.keyboard.press( 'Enter' );
		await expect( width, 'Change opens the widths' ).toBeVisible();
		release();
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect( page.locator( '.cbuild[data-folded]' ) ).toHaveCount( 0 );
		await expect( width, 'the answer keeps them open' ).toBeVisible();
		await expect( width ).toHaveAttribute( 'aria-pressed', 'true' );
	} );

	test( 'keeps focus on a width and on the assembly switch, and says the new total (Accessibility)', async ( {
		page,
	}, info ) => {
		const width = page.locator( '.cbuild-width' ).getByRole( 'button', {
			name: inLocale( info.project.name, '8.25″, 1' ),
		} );
		await width.focus();
		await page.keyboard.press( 'Enter' );
		// The widths fold away once one is chosen, and focus goes to the line that says it, with Change.
		const chosen = page.locator( '.cbuild-width__chosen' );
		await expect( chosen, 'the shopper keeps their place' ).toBeFocused();
		await expect( chosen ).toContainText(
			inLocale( info.project.name, '8.25″' )
		);
		await page.keyboard.press( 'Enter' );
		await expect( width ).toHaveAttribute( 'aria-pressed', 'true' );
		// The question folds at the press (D-356); its answer is in once the mark is gone.
		await expect( page.locator( '.cbuild[data-folded]' ) ).toHaveCount( 0 );

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

	test( 'a preset with a sold-out part has no tile, and the store draws the rest', async ( {
		page,
	} ) => {
		// #104 (R1): Cruiser's wheels are sold out. Its link still opens it, which the test without the store checks.
		const tiles = page.locator( '.cbuild-presets' );
		await expect(
			tiles.getByRole( 'button', { name: 'Start from Street' } )
		).toBeVisible();
		await expect(
			tiles.getByRole( 'button', { name: 'Start from Cruiser' } )
		).toHaveCount( 0 );
		await expect(
			tiles.getByText( 'Wheels: Builder test wheels 56 is sold out.' )
		).toHaveCount( 0 );
		await tiles
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		expect( await stayed( page ), 'the store drew the review' ).toBe(
			true
		);
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
		// Every region hydrated before the pick, as goto() waits for (D-296): a pick before the store ran only checked the
		// radio and asked nothing, so the address kept its width alone (#70, seen before the ready mark existed).
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'data-ready',
			''
		);
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
		// With the motion on, as a shopper who has not asked for less has it (milestone 11).
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		// Parts that share a list: the wheels on an 8″ deck and its trucks (D-241 lists one width of deck at a time).
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		// The pick is timed, not core's emoji polyfill reading the page after its load (#49).
		await emojiDone( page );
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
		// Nor anything in the browser's storage (the spec reviewer's M10 minor M6): WooCommerce's cart fragments and
		// WordPress's emoji check are theirs, and nothing else is kept.
		const stored = await page.evaluate( () =>
			[ localStorage, sessionStorage ].flatMap( ( storage ) =>
				Array.from( { length: storage.length }, ( _, at ) =>
					String( storage.key( at ) )
				)
			)
		);
		expect(
			stored.filter(
				( key ) =>
					! /^(wc_(fragments|cart_hash|cart_created)|wpEmojiSettingsSupports$)/.test(
						key
					)
			),
			stored.join( ', ' )
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

		// Stacked, the bar sticks over the screen's foot from the builder's top (D-382), so on a short phone
		// (webkit-phone, 390 by 664) a page's first screen ends with "Back to the shop" partly under it, and axe counts a
		// target it covers as too small wherever the page happens to stand (D-288, D-380). On a page just loaded, the
		// panel's head is brought into view as a shopper scrolls to it, clear of the bar by the page's scroll padding
		// (D-388).
		const toHead = () =>
			page
				.locator( '.cbuild__head' )
				.evaluate( ( head ) =>
					head.scrollIntoView( { block: 'nearest' } )
				);

		// The blank start: presets and the deck step's width question.
		await toHead();
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
		// Where the layout is stacked, the bar covers the foot of the window, and axe counts a target it covers as too
		// small wherever the page happens to stand: on a tablet, "Not sure of your width?" (D-288). The width question
		// is brought into view as a shopper scrolls it, clear of the bar by the page's scroll padding.
		await page
			.locator( '.cbuild-width' )
			.evaluate( ( width ) =>
				width.scrollIntoView( { block: 'nearest' } )
			);
		await check( 'a width pressed' );

		// Next pressed with no pick: dimmed, and what is missing said beside it (#100).
		// A dimmed Next is aria-disabled, which Playwright's click() waits on as disabled; a shopper can press it, so the click is forced (#100).
		await page.goto( fixture().page );
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.filter( { visible: true } )
			.click( { force: true } );
		await expect( page.locator( '.cbuild-bar__missing' ) ).toHaveText(
			'Choose your deck first.'
		);
		await check( 'Next pressed with no pick' );

		// The error summary, asked past an empty step by a link: with the script, Next no longer asks for it (#100).
		await page.goto( linkTo( { step: 'trucks' } ) );
		await expect( page.locator( '.cbuild-errors' ) ).toBeVisible();
		// Served, in every engine. Firefox's crash was in axe over the summary the store drew after Next (#36, D-167),
		// which Next no longer asks for (#100).
		await toHead();
		// Since Help me choose's link sits above the width question (#134), a tablet's first screen ends on "Not sure of
		// your width?" under the bar's top, as in "a width pressed" above (D-288): the question is brought into view as a
		// shopper scrolls to it.
		await page
			.locator( '.cbuild-width' )
			.evaluate( ( width ) =>
				width.scrollIntoView( { block: 'nearest' } )
			);
		await check( 'the error summary' );

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
				url: baseURL || SITE,
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
		// The question folds at the press (D-356); the decks are drawn once its answer is in and the mark is gone.
		await expect( page.locator( '.cbuild[data-folded]' ) ).toHaveCount( 0 );
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
			// Then straight on to View cart, as a shopper would: the add keeps the cart before it answers, so the cart page
			// holds the build however soon it is asked for (#51, #108).
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

	// Issues #51 and #108: WooCommerce saves the session as a request ends, and the add's answer had gone out before,
	// so a cart asked for as soon as the add said so read empty. Here the add's request ends a second late, as a loaded
	// server's can (tests/e2e/mu-plugins/cbuild-e2e-slow-end.php); the add keeps the cart before it answers.
	test( 'the cart holds the build as soon as the add says it is in', async ( {
		page,
		context,
		baseURL,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			'Without a script the add is a form post, and its answer is a page WooCommerce sends after the session is saved.'
		);
		const url = baseURL || SITE;
		await context.addCookies( [
			{ name: 'cbuild_e2e_slow_end', value: '1', url },
			{ name: 'cbuild_e2e_classic', value: '1', url },
		] );
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
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.first()
			.click();
		const added = page.locator( '.cbuild-review__added' );
		await expect( added ).toBeVisible();
		const cart = await page.request.get( '/wp-json/wc/store/v1/cart' );
		expect(
			( ( await cart.json() ) as { items_count: number } ).items_count,
			'the cart, read the moment the add says the build is in'
		).toBeGreaterThan( 0 );
		await added.getByRole( 'link', { name: /^View cart/ } ).click();
		await expect( page.locator( '.woocommerce-cart-form' ) ).toContainText(
			'Builder test kit'
		);
	} );
} );

// #98 (F13), to the mockup of 18 November: on a desktop the brand chips sit in one row under the search, the brands
// with the most decks first, and "N more" at the end opens the rest in place with focus on the first new chip. Phones
// keep the Filters fold (D-279), and without the script every chip shows. The shelf kit's four brands fit in one row
// at 1280 px, so the test widens each chip until they don't: the store measures the row as the page lays it out.
test.describe( 'The brand chips in one row', () => {
	const shelfAt = (): string => {
		const url = new URL( fixture().shelf.page );
		url.searchParams.set( 'cbuild[_width]', '8.25in' );
		return url.toString();
	};

	test( 'shows what fits beside N more, which opens the rest by the keyboard with focus on the first new chip', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		test.skip(
			info.project.name.endsWith( '-phone' ),
			'A phone keeps the Filters fold; the phone part is below.'
		);
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( shelfAt() );
		const group = page.locator( '.cbuild-chips__group' ).first();
		const chips = group.locator( '.cbuild-chips__chip' );
		const more = group.locator( '.cbuild-chips__more' );
		await expect( chips ).toHaveCount( 4 );
		if ( ! javaScriptEnabled ) {
			// Nothing hidden that the script would have to show.
			for ( const chip of await chips.all() ) {
				await expect( chip ).toBeVisible();
			}
			await expect( more ).toBeHidden();
			return;
		}
		await page.locator( '.cbuild[data-ready]' ).waitFor();
		// Chips small enough that all four fit in the row, whatever the panel's width: no N more.
		const small = await page.addStyleTag( {
			content:
				'.cbuild .cbuild-chips__group:first-of-type .cbuild-chip { min-inline-size: 0; padding-inline: 0.25em; font-size: 0.625rem; }',
		} );
		for ( const chip of await chips.all() ) {
			await expect( chip ).toBeVisible();
		}
		await expect( more ).toBeHidden();
		await small.evaluate( ( style ) => ( style as Element ).remove() );

		// Each chip at least 12rem wide: some fit beside the button, never all. 15rem left none beside "N more brands".
		await page.addStyleTag( {
			content:
				'.cbuild .cbuild-chips__group:first-of-type .cbuild-chip { min-inline-size: 12rem; }',
		} );
		await expect( more ).toBeVisible();
		await expect( more ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( more ).toHaveAttribute(
			'aria-controls',
			( await group
				.locator( '.cbuild-chips__options' )
				.getAttribute( 'id' ) ) ?? ''
		);
		const shown = await chips.evaluateAll(
			( all ) =>
				all.filter(
					( chip ) => 'hidden' !== getComputedStyle( chip ).visibility
				).length
		);
		expect( shown ).toBeGreaterThan( 0 );
		expect( shown ).toBeLessThan( 4 );
		await expect( more ).toHaveText( new RegExp( `^${ 4 - shown }\\D` ) );
		// One row: every chip shown and the button share a line.
		const tops = await group
			.locator( '.cbuild-chips__chip, .cbuild-chips__more' )
			.evaluateAll( ( all ) =>
				all
					.filter(
						( node ) =>
							'hidden' !== getComputedStyle( node ).visibility
					)
					.map( ( node ) =>
						Math.round( node.getBoundingClientRect().top )
					)
			);
		expect( new Set( tops ).size ).toBe( 1 );
		// The chips past the row are out of the tab order: Tab from the last shown chip reaches the button. Safari
		// tabs to no button unless the shopper asks it to, so there the button is focused as a shopper would reach it.
		await chips
			.nth( shown - 1 )
			.locator( 'input' )
			.focus();
		if ( info.project.name.startsWith( 'webkit' ) ) {
			await more.focus();
		} else {
			await page.keyboard.press( 'Tab' );
		}
		await expect( more ).toBeFocused();

		await page.keyboard.press( 'Enter' );
		await expect( more ).toHaveAttribute( 'aria-expanded', 'true' );
		await expect( chips.nth( shown ).locator( 'input' ) ).toBeFocused();
		for ( const chip of await chips.all() ) {
			await expect( chip ).toBeVisible();
		}
		// Show fewer, in the shopper's language.
		await expect( more ).not.toHaveText( /^\d/ );
		await axe( page, 'the brand chips opened', '.cbuild-chips' );

		// A chip chosen past the row stays in view once the rest fold again.
		await page.keyboard.press( 'Space' );
		await expect( chips.nth( shown ).locator( 'input' ) ).toBeChecked();
		await expect( chips.nth( shown ).locator( 'input' ) ).toBeFocused();
		await more.click();
		await expect( more ).toHaveAttribute( 'aria-expanded', 'false' );
		await expect( chips.nth( shown ) ).toBeVisible();
	} );

	// The interim audit of M11: a page that will run the script clips the row to one line until the store folds it. If
	// the script never comes (a blocker, a policy, an error), the clip is lifted after a while, so no brand is cut off
	// and no focusable chip is out of sight.
	test( 'shows every brand if the script never comes', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		test.skip(
			! javaScriptEnabled || info.project.name.endsWith( '-phone' ),
			'Only a desktop with the script clips the row.'
		);
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.route(
			( url ) => url.href.startsWith( shelfAt().split( '?' )[ 0 ]! ),
			async ( route ) => {
				const response = await route.fetch();
				const body = ( await response.text() ).replace(
					'</head>',
					'<style>.cbuild .cbuild-chips__group:first-of-type .cbuild-chip { min-inline-size: 15rem; }</style></head>'
				);
				await route.fulfill( { response, body } );
			}
		);
		await page.route( /\/build\/builder\/view\.js/, ( route ) =>
			route.abort()
		);
		// Not the fixture's goto, which waits for the ready mark the store never sets here.
		await page.goto( shelfAt(), { waitUntil: 'load' } ).catch( () => null );
		const chips = page
			.locator( '.cbuild-chips__group' )
			.first()
			.locator( '.cbuild-chips__chip' );
		expect( await chips.count() ).toBeGreaterThan( 3 );
		// Past the 4 s the clip waits for, each chip scrolled to is seen: one cut off by the clip stays out of sight.
		await page.waitForTimeout( 4500 );
		for ( const chip of await chips.all() ) {
			await chip.scrollIntoViewIfNeeded();
			await expect( chip ).toBeInViewport();
		}
	} );

	// #107: the no-script submits (the search's Go, the chips' and the sort's Apply) were shown until the store hid them
	// as it hydrated, and the step's cards moved up by their height. A page that will run the script hides them from
	// the first paint; if the script never comes, they come back, so the form still sends.
	test( 'moves no card as the store hydrates, and keeps the submits for a page whose script never comes', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		test.skip(
			! javaScriptEnabled || info.project.name.endsWith( '-phone' ),
			'Only a page with the script hides the submits; a phone folds the chips behind Filters.'
		);
		await page.setViewportSize( { width: 1280, height: 2000 } );
		await page.route( /\/build\/builder\/view\.js/, async ( route ) => {
			await new Promise( ( done ) => setTimeout( done, 1000 ) );
			await route.continue();
		} );
		await page.addInitScript( () => {
			document.addEventListener( 'DOMContentLoaded', () => {
				( window as unknown as { first?: number | undefined } ).first =
					document
						.querySelector( '.cbuild-cards' )
						?.getBoundingClientRect().top;
			} );
		} );
		await page.goto( shelfAt() );
		await page.locator( '.cbuild[data-ready]' ).waitFor();
		const cards = await page.evaluate( () => ( {
			first: ( window as unknown as { first?: number | undefined } )
				.first,
			ready: document
				.querySelector( '.cbuild-cards' )
				?.getBoundingClientRect().top,
		} ) );
		expect(
			cards.first,
			'the cards are in the first layout'
		).toBeGreaterThan( 0 );
		expect(
			cards.ready,
			'the cards stay where the first layout put them'
		).toBe( cards.first );

		await page.unrouteAll( { behavior: 'ignoreErrors' } );
		await page.route( /\/build\/builder\/view\.js/, ( route ) =>
			route.abort()
		);
		await page.goto( shelfAt(), { waitUntil: 'load' } ).catch( () => null );
		await page.waitForTimeout( 4500 );
		await expect(
			page.locator( '.cbuild-chips__apply' ),
			'Apply is back for a page whose script never came'
		).toBeVisible();
		await expect( page.locator( '.cbuild-search__go' ) ).toBeVisible();
	} );

	test( 'keeps the row one line from the first paint, so folding it moves nothing under it', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		test.skip(
			! javaScriptEnabled || info.project.name.endsWith( '-phone' ),
			'Only a desktop with the script folds the row.'
		);
		await page.setViewportSize( { width: 1280, height: 900 } );
		// Chips a third of the row wide from the first paint, so the row the server sends wraps and the script folds it.
		// The style goes in the page's own head, so the row wraps from the first paint, before any script runs.
		await page.route(
			( url ) => url.href.startsWith( shelfAt().split( '?' )[ 0 ]! ),
			async ( route ) => {
				const response = await route.fetch();
				const body = ( await response.text() ).replace(
					'</head>',
					'<style>.cbuild .cbuild-chips__group:first-of-type .cbuild-chip { min-inline-size: 12rem; }</style></head>'
				);
				await route.fulfill( { response, body } );
			}
		);
		// The builder's script held back a second, as on a slow phone or network, so the page the server sent is
		// painted before the store folds the row: a fast local site would hydrate before its first paint and hide a shift.
		await page.route( /\/build\/builder\/view\.js/, async ( route ) => {
			await new Promise( ( done ) => setTimeout( done, 1000 ) );
			await route.continue();
		} );
		// The row's height as the page the server sent is laid out, before the store runs.
		await page.addInitScript( () => {
			document.addEventListener( 'DOMContentLoaded', () => {
				( window as unknown as { first?: number | undefined } ).first =
					document
						.querySelector( '.cbuild-chips__options--row' )
						?.getBoundingClientRect().height;
			} );
		} );
		await page.goto( shelfAt() );
		await page.locator( '.cbuild[data-ready]' ).waitFor();
		const group = page.locator( '.cbuild-chips__group' ).first();
		await expect( group.locator( '.cbuild-chips__more' ) ).toBeVisible();
		const heights = await page.evaluate( () => ( {
			first: ( window as unknown as { first?: number | undefined } )
				.first,
			folded: document
				.querySelector( '.cbuild-chips__options--row' )
				?.getBoundingClientRect().height,
		} ) );
		expect(
			heights.first,
			'the row is in the first layout'
		).toBeGreaterThan( 0 );
		expect(
			heights.first,
			'the row keeps its height as the store folds it, so nothing under it moves'
		).toBe( heights.folded );
	} );

	test( 'keeps the chips behind Filters on a phone, every brand there and no N more', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip( ! javaScriptEnabled, 'The fold is the script’s.' );
		await page.setViewportSize( { width: 390, height: 844 } );
		await page.goto( shelfAt() );
		await page.locator( '.cbuild[data-ready]' ).waitFor();
		const group = page.locator( '.cbuild-chips__group' ).first();
		await page.locator( '.cbuild-chips__toggle' ).click();
		for ( const chip of await group
			.locator( '.cbuild-chips__chip' )
			.all() ) {
			await expect( chip ).toBeVisible();
		}
		await expect( group.locator( '.cbuild-chips__more' ) ).toBeHidden();
	} );
} );

// Spec (configurator): The deck's width first, "Not sure of your width?".
test( 'the width question opens a guide by shoe size', async ( {
	page,
}, info ) => {
	await page.goto( linkTo( {} ) );
	const guide = page.locator( '.cbuild-width__guide' );
	await expect( guide.getByRole( 'table' ) ).toBeHidden();
	await guide.getByText( 'Not sure of your width?' ).click();
	const table = page.getByRole( 'table', {
		name: 'Deck width by shoe size',
	} );
	await expect( table ).toBeVisible();
	await expect(
		table.getByRole( 'row', {
			name: inLocale(
				info.project.name,
				'9.5 and larger 8.25″ and wider'
			),
		} )
	).toBeVisible();
} );

// #114: a kit's own width guide, edited in its Build tab: the shop's rows in place of the plugin's, and a guide hidden
// is gone from the page, not hidden by a style alone. The kit's settings are put back after.
test.describe( "A kit's own width guide", () => {
	const layout = ( guide: string ) =>
		runPhp(
			`$k = wc_get_product( ${
				fixture().kit
			} ); if ( false === get_option( 'cbuild_e2e_layout' ) ) { update_option( 'cbuild_e2e_layout', array( 'had' => $k->documents()['_cbuild_settings'] ) ); } $k->setDocument( '_cbuild_settings', array( 'schema_version' => 1, 'width_guide' => json_decode( '${ guide }', true ) ) ); $k->save();`
		);
	test.afterAll( () => {
		runPhp(
			`$k = wc_get_product( ${
				fixture().kit
			} ); $had = get_option( 'cbuild_e2e_layout' )['had'] ?? ''; if ( is_array( $had ) ) { $k->setDocument( '_cbuild_settings', $had ); } else { $k->delete_meta_data( '_cbuild_settings' ); } $k->save(); delete_option( 'cbuild_e2e_layout' );`
		);
	} );

	test( "the guide shows the shop's rows, and a hidden one is not on the page", async ( {
		page,
		javaScriptEnabled,
	} ) => {
		layout(
			JSON.stringify( {
				rows: [
					{ shoe: 'EU 38 to 40', from: '7.75', to: '8' },
					{ shoe: 'EU 41 and up', from: '8.25' },
				],
			} )
		);
		await page.goto( linkTo( {} ) );
		const guide = page.locator( '.cbuild-width__guide' );
		await guide.locator( 'summary' ).click();
		const table = guide.getByRole( 'table' );
		await expect( table ).toBeVisible();
		await expect( table.locator( 'tbody tr' ) ).toHaveCount( 2 );
		await expect(
			table.getByRole( 'row', { name: /^EU 38 to 40 / } )
		).toBeVisible();
		await expect(
			table.getByRole( 'row', { name: /^EU 41 and up / } )
		).toBeVisible();
		// axe runs its script in the page, which the no-js project cannot.
		if ( javaScriptEnabled ) {
			await axe( page, "a kit's own width guide", '.cbuild-width' );
		}

		layout(
			JSON.stringify( {
				shown: false,
				rows: [ { shoe: 'EU 38 to 40', from: '7.75', to: '8' } ],
			} )
		);
		await page.goto( linkTo( {} ) );
		await expect( page.locator( '.cbuild-width' ) ).toBeVisible();
		await expect( guide ).toBeHidden();
		await expect( guide.locator( 'tbody tr' ) ).toHaveCount( 0 );
		await expect( guide.locator( 'summary' ) ).toHaveText( '' );
		expect( await page.content() ).not.toContain( 'EU 38 to 40' );
	} );
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
		// Found by axe on the bar (the auditor's M12 minor 7): Storefront's text is #6d6d6d, 5.2:1 on white, and the
		// builder's muted words, the text at 72 %, were 2.95:1 (D-380).
		test( `passes axe on the blank start on ${ theme }, ${ scheme }, its muted words included`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				info.project.name === 'no-js',
				"axe runs as a script in the page; the colours are the stylesheet's, the same with JavaScript."
			);
			const url = baseURL || SITE;
			await context.addCookies( [
				{ name: 'cbuild_e2e_theme', value: theme, url },
				{ name: 'cbuild_e2e_scheme', value: scheme, url },
			] );
			// The colours are the same at every width. The bar sticks over the screen's foot, and whatever the first
			// screen leaves under it, "Back to the shop" under Storefront's tall header, axe reads as a covered target;
			// the bar's own tests hold focus clear of it (D-160). So a desktop, scrolled to the panel's head.
			await page.setViewportSize( { width: 1280, height: 900 } );
			await page.goto( fixture().page );
			await expect( page.locator( '.cbuild-step__lead' ) ).toBeVisible();
			await page
				.locator( '.cbuild__back' )
				.evaluate( ( link ) =>
					link.scrollIntoView( { block: 'center' } )
				);
			await axe(
				page,
				`the blank start on ${ theme }, ${ scheme }`,
				'.cbuild'
			);
		} );

		test( `the current step and a pressed width read on ${ theme }, ${ scheme }, and the rail has no list numbers`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const url = baseURL || SITE;
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
		const url = baseURL || SITE;
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

// Milestone 11's walkthrough (#100, F15): on Kadence the step's Next had aria-disabled and was never dimmed, since the
// theme's rule for every `button` outweighed the dimmed look at no weight; Storefront's did too. Pressed, Next keeps
// focus, and on a desktop the pointer is over it, so the look must hold under a theme's `button:focus` and
// `button:hover` as well. The look is D-310's: the builder's muted words and border on its surface.
test.describe( 'The dimmed Next on a theme (#100)', () => {
	for ( const theme of [ 'twentytwentyfive', 'storefront', 'kadence' ] ) {
		test( `is dimmed on ${ theme } at rest, focused and under the pointer, and lit by a pick`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				info.project.name === 'no-js',
				'Next is dimmed by the script alone (D-310).'
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.goto(
				linkTo( {
					deck: id( 'builder-test-deck-8-0' ),
					step: 'trucks',
				} )
			);
			const button = page
				.locator( '.cbuild-bar__next' )
				.filter( { visible: true } );
			await expect( button ).toHaveAttribute( 'aria-disabled', 'true' );
			/**
			 * The button's colours, and the dimmed look's as the builder's tokens give them in the bar.
			 */
			const look = () =>
				button.evaluate( ( dim ) => {
					const token = document.createElement( 'span' );
					token.style.cssText =
						'background-color: var(--cbuild-surface); color: var(--cbuild-muted); border: 1px solid var(--cbuild-muted)';
					dim.parentElement?.append( token );
					const want = getComputedStyle( token );
					const own = getComputedStyle( dim );
					const both = {
						shown: [
							own.backgroundColor,
							own.color,
							own.borderTopColor,
						].join( ' | ' ),
						dimmed: [
							want.backgroundColor,
							want.color,
							want.borderTopColor,
						].join( ' | ' ),
					};
					token.remove();
					return both;
				} );
			// A theme's button may ease between its colours (Kadence's does), so the look is read once it has settled.
			const dimmed = () =>
				look().then( ( { shown, dimmed: want } ) =>
					shown === want ? 'dimmed' : `${ shown }, not ${ want }`
				);
			await page.mouse.move( 0, 0 );
			await expect.poll( dimmed, 'at rest' ).toBe( 'dimmed' );

			await button.focus();
			await page.keyboard.press( 'Enter' );
			await expect( page.locator( '.cbuild-bar__missing' ) ).toHaveText(
				'Choose your trucks first.'
			);
			await expect( button ).toBeFocused();
			await expect
				.poll( dimmed, 'focused after a press' )
				.toBe( 'dimmed' );

			if ( ! info.project.name.endsWith( '-phone' ) ) {
				await button.hover( { force: true } );
				await expect
					.poll( dimmed, 'under the pointer' )
					.toBe( 'dimmed' );
			}

			await choose( page, 'Builder test trucks 139' );
			await expect( button ).toHaveAttribute( 'aria-disabled', 'false' );
			await expect
				.poll( dimmed, 'lit once the step has a pick' )
				.not.toBe( 'dimmed' );
		} );

		// Milestone 11's walkthrough: on a phone the words a press says sat over the bar's own, and the estimate read
		// "Complete from about $29" under them. They cover none of the bar's words, and none is cut.
		test( `says what is missing clear of the bar's words on a phone on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				info.project.name === 'no-js',
				'Next is dimmed by the script alone (D-310).'
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize( { width: 390, height: 844 } );
			// On the kit's own page, in the theme's content width, where Back and Next take a row under the total.
			const kit = new URL(
				'/product/builder-test-kit/',
				baseURL || SITE
			);
			kit.searchParams.set(
				'cbuild[deck]',
				String( id( 'builder-test-deck-8-0' ) )
			);
			kit.searchParams.set( 'cbuild[step]', 'trucks' );
			await page.goto( kit.toString() );
			const bar = page.locator( '.cbuild-bar' );
			await expect(
				bar.locator( '.cbuild-bar__estimate' )
			).toBeVisible();
			const button = bar
				.locator( '.cbuild-bar__next' )
				.filter( { visible: true } );
			await expect( button ).toHaveAttribute( 'aria-disabled', 'true' );
			// A dimmed Next is aria-disabled, which Playwright's click() waits on as disabled; a shopper can tap it.
			await button.click( { force: true } );
			await expect( bar.locator( '.cbuild-bar__missing' ) ).toHaveText(
				'Choose your trucks first.'
			);
			const crossed = await bar.evaluate( ( shown ) => {
				const said = shown
					.querySelector( '.cbuild-bar__missing' )
					?.getBoundingClientRect();
				if ( ! said ) {
					return [ 'no message' ];
				}
				const words = [
					'.cbuild-bar__label',
					'.cbuild-bar__amount > :first-child',
					'.cbuild-bar__estimate',
					'.cbuild-bar__more--show',
					'.cbuild-bar__back',
					'.cbuild-bar__next:not([hidden])',
				];
				return words.flatMap( ( selector ) => {
					const box = shown
						.querySelector( selector )
						?.getBoundingClientRect();
					if ( ! box || box.width === 0 ) {
						return [];
					}
					const across =
						Math.min( box.right, said.right ) -
						Math.max( box.left, said.left );
					const down =
						Math.min( box.bottom, said.bottom ) -
						Math.max( box.top, said.top );
					return across > 0.5 && down > 0.5
						? [
								`${ selector } by ${ Math.round(
									across
								) } by ${ Math.round( down ) }`,
							]
						: [];
				} );
			} );
			expect( crossed, "the bar's words the message covers" ).toEqual(
				[]
			);
			const estimate = await bar
				.locator( '.cbuild-bar__estimate' )
				.evaluate( ( shown ) => ( {
					scroll: shown.scrollWidth,
					client: shown.clientWidth,
				} ) );
			expect( estimate.scroll, 'the estimate whole' ).toBeLessThanOrEqual(
				estimate.client
			);
			const said = ( await bar
				.locator( '.cbuild-bar__missing' )
				.boundingBox() )!;
			expect( said.x, 'on the screen' ).toBeGreaterThanOrEqual( 0 );
			expect( said.x + said.width ).toBeLessThanOrEqual( 390 );
		} );
	}
} );

// Cowork's 1.0.0 review, F11 (#96): a theme's plain rules for a fieldset, a button and a heading outranked the
// builder's resets, which sat at no weight inside :where(). On Kadence the fieldsets were boxed and padded, Filters
// and Show the top took the theme's button fill, Filters showed on a desktop, and the step's heading had 60 px above
// it. Storefront boxes and pads a fieldset too. Twenty Twenty-Five, which styles none of them, must look as it did.
test.describe( "A theme's own fieldset, button and heading rules (#96)", () => {
	for ( const theme of [ 'kadence', 'storefront', 'twentytwentyfive' ] ) {
		test( `leave the builder's fieldsets, plain buttons and step heading as the builder draws them on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const url = baseURL || 'http://localhost:8898';
			await context.addCookies( [
				{ name: 'cbuild_e2e_theme', value: theme, url },
			] );
			await page.setViewportSize( { width: 1280, height: 900 } );
			// The deck step with a width picked: the width line, the brand chips and the step's cards, each a fieldset.
			await page.goto( linkTo( { _width: '8in' } ) );
			await expect(
				page.locator( '.cbuild-step__fieldset' )
			).toBeVisible();
			// Each shown fieldset, by its class, with any border, padding or margin a theme gave it. The sizes and the
			// filters' groups keep the small space the builder puts above them.
			const boxed = await page
				.locator( '.cbuild fieldset' )
				.evaluateAll( ( sets ) =>
					sets
						.filter( ( set ) => set.getClientRects().length > 0 )
						.flatMap( ( set ) => {
							const own = getComputedStyle( set );
							const spaced = ! set.matches(
								'.cbuild-sizes, .cbuild-filters__group'
							);
							const wrong = [
								[ 'border', own.borderTopWidth ],
								[ 'border', own.borderInlineStartWidth ],
								[ 'padding', own.paddingTop ],
								[ 'padding', own.paddingInlineStart ],
								[ 'padding', own.paddingInlineEnd ],
								[ 'margin', own.marginInlineStart ],
								...( spaced
									? [
											[ 'margin', own.marginTop ],
											[ 'margin', own.marginBottom ],
										]
									: [] ),
							].filter( ( [ , value ] ) => value !== '0px' );
							return wrong.map(
								( [ what, value ] ) =>
									`${ set.className } ${ what } ${ value }`
							);
						} )
				);
			expect( boxed, 'no fieldset boxed, padded or spaced' ).toEqual(
				[]
			);
			// The step's heading, under Back to the shop, with no theme margin round it.
			const title = page.locator( '.cbuild__heading' );
			await expect( title ).toHaveCSS( 'margin-top', '0px' );
			await expect( title ).toHaveCSS( 'margin-bottom', '0px' );
			// Filters, which only a phone needs: the chips are open beside it from 48rem.
			await expect(
				page.locator( '.cbuild-chips__toggle' )
			).toBeHidden();
			// Without the script the board is never turned, so there is no Show the top to look at.
			if ( test.info().project.name === 'no-js' ) {
				return;
			}
			await page.goto(
				linkTo( {
					deck: id( 'builder-test-deck-8-0' ),
					step: 'trucks',
				} )
			);
			// The side not shown: a plain button (F28, #128).
			const turn = page
				.locator( '.cbuild__stage .cbuild-picture__sides' )
				.getByRole( 'button', { name: 'Top', exact: true } );
			await expect( turn ).toBeVisible();
			// The picture is a grid whatever the theme's rule for a figure (Storefront's normalize sets every figure to a
			// block), so the button keeps its row under the board and nothing of the board lies over it.
			await expect(
				page.locator( '.cbuild__stage .cbuild-picture' )
			).toHaveCSS( 'display', 'grid' );
			expect(
				await turn.evaluate( ( button ) => {
					button.scrollIntoView( { block: 'center' } );
					const box = button.getBoundingClientRect();
					const top = document.elementFromPoint(
						box.x + box.width / 2,
						box.y + box.height / 2
					);
					return button.contains( top )
						? 'the button'
						: top?.outerHTML.slice( 0, 120 );
				} ),
				"what is on top of the button's middle"
			).toBe( 'the button' );
			// A plain button is drawn second to the step's one action: no fill and no shadow of the theme's, at rest or
			// under the pointer, whose rule on Kadence outweighs a single class.
			for ( const state of [ 'at rest', 'under the pointer' ] ) {
				if ( state === 'under the pointer' ) {
					await turn.hover();
				}
				await expect( turn, state ).toHaveCSS(
					'background-color',
					'rgba(0, 0, 0, 0)'
				);
				await expect( turn, state ).toHaveCSS( 'box-shadow', 'none' );
				// The builder's inline-flex, made a flex box by its row: not the theme's inline-block.
				await expect( turn, state ).toHaveCSS( 'display', /flex$/ );
			}
			// What the builder hides stays hidden, whatever the theme's `display` for a button.
			expect(
				await page
					.locator( '.cbuild button[hidden]' )
					.evaluateAll(
						( hidden ) =>
							hidden.filter(
								( button ) => button.getClientRects().length > 0
							).length
					),
				'no hidden button shown'
			).toBe( 0 );
		} );
	}

	test( 'leave Filters a plain button on a phone, on kadence', async ( {
		page,
		context,
		baseURL,
	} ) => {
		test.skip(
			test.info().project.name === 'no-js',
			'Filters is the script’s: without it the chips are open.'
		);
		const url = baseURL || 'http://localhost:8898';
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: 'kadence', url },
		] );
		await page.setViewportSize( { width: 390, height: 844 } );
		// The shelf kit's deck step, with enough decks to have chips behind Filters.
		const shelf = new URL( fixture().shelf.page );
		shelf.searchParams.set( 'cbuild[_width]', '8.25in' );
		await page.goto( shelf.toString() );
		const filters = page.locator( '.cbuild-chips__toggle' );
		await expect( filters ).toBeVisible();
		await expect( filters ).toHaveCSS(
			'background-color',
			'rgba(0, 0, 0, 0)'
		);
		await expect( filters ).toHaveCSS( 'box-shadow', 'none' );
	} );
} );

// Cowork's 1.0.0 review, F12 (#97): Board size's labels are "Large, 60 % of the width" and "Balanced, 50 % of the
// width", but the panel stopped growing at 520 or 640 px, so past about 1,300 px the board took more. The board's
// share holds at every desktop width, with the stage on either side.
test.describe( 'Board size on a desktop (#97)', () => {
	for ( const width of [ 1280, 1440, 1920 ] ) {
		test( `keeps the board at 60 % on large and 50 % on balanced at ${ width } px`, async ( {
			page,
		} ) => {
			await page.setViewportSize( { width, height: 900 } );
			// The kit's own page gives the builder the screen's width (D-283).
			await page.goto( '/product/builder-test-kit/' );
			await expect( page.locator( '.cbuild__layout' ) ).toHaveCSS(
				'display',
				'grid'
			);
			// The builder's own classes for the kit's Layout (templates/builder/builder.php), set here so one kit
			// serves every case; the setting that sets them is the integration suite's.
			const share = ( classes: string[] ) =>
				page.locator( '.cbuild' ).evaluate( ( root, set ) => {
					root.classList.remove(
						'cbuild--balanced',
						'cbuild--stage-right'
					);
					root.classList.add( ...set );
					const layout = root
						.querySelector( '.cbuild__layout' )
						?.getBoundingClientRect();
					const stage = root
						.querySelector( '.cbuild__stage' )
						?.getBoundingClientRect();
					return layout && stage ? stage.width / layout.width : 0;
				}, classes );
			for ( const [ classes, expected ] of [
				[ [], 0.6 ],
				[ [ 'cbuild--balanced' ], 0.5 ],
				[ [ 'cbuild--stage-right' ], 0.6 ],
				[ [ 'cbuild--stage-right', 'cbuild--balanced' ], 0.5 ],
			] as const ) {
				expect(
					await share( [ ...classes ] ),
					`the board's share with ${
						classes.join( ' and ' ) || 'the defaults'
					}`
				).toBeCloseTo( expected, 2 );
			}
		} );
	}
} );

test.describe( "The builder's styles", () => {
	// Spec (configurator): Accessibility, zoom and reflow. Below 320 px the stage stops being sticky, so the panel is
	// never squeezed out.
	test( 'the stage is sticky at 320 px and not below it', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 320, height: 640 } );
		// With a part to show: until there is one, the stage scrolls with the page at any width (#82).
		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
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
			// Inside a box that hides its overflow within the edge, nothing shows past it. Firefox gives the text of
			// a visually hidden caption its full width, though the caption clips it to a pixel.
			const clipped = ( node: Element ): boolean => {
				for (
					let up = node.parentElement;
					up && up !== root;
					up = up.parentElement
				) {
					if (
						'visible' !== getComputedStyle( up ).overflowX &&
						up.getBoundingClientRect().right <= edge
					) {
						return true;
					}
				}
				return false;
			};
			for ( const node of root.querySelectorAll< HTMLElement >( '*' ) ) {
				const box = node.getBoundingClientRect();
				if ( box.width > 0 && box.right > edge && ! clipped( node ) ) {
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

	// Zach, 20 Nov (#101, F16, D-330, which replaces D-270): on a phone every step is in one row that never scrolls,
	// the step shown with its name and the others as numbered pills, each with its name as its tooltip; on a desktop,
	// every step with its name.
	test( 'shows every step in one row on a phone, the one shown named, and every step named on a desktop', async ( {
		page,
	} ) => {
		/**
		 * The rail as drawn: each step's name, whether that name shows, its tooltip, its pill's box, and the list's.
		 */
		const rail = () =>
			page.locator( '.cbuild-progress__list' ).evaluate( ( list ) => {
				const edge = list.getBoundingClientRect();
				return {
					left: edge.left,
					right: edge.right,
					scrolls: list.scrollWidth > list.clientWidth + 1,
					steps: [
						...list.querySelectorAll< HTMLElement >(
							'.cbuild-progress__step'
						),
					].map( ( step ) => {
						const pill = step.querySelector< HTMLElement >(
							':scope > :not([hidden])'
						);
						const name = pill?.querySelector< HTMLElement >(
							':scope > [aria-hidden="true"]'
						);
						const box = pill?.getBoundingClientRect();
						const said = name?.getBoundingClientRect();
						return {
							name: name?.textContent ?? '',
							named:
								!! name?.checkVisibility( {
									visibilityProperty: true,
								} ) &&
								( said?.width ?? 0 ) > 0 &&
								( said?.left ?? 0 ) >= edge.left - 1 &&
								( said?.right ?? 0 ) <= edge.right + 1,
							tip: step.getAttribute( 'title' ),
							current: step.matches(
								'.cbuild-progress__step--current'
							),
							link: 'A' === pill?.tagName,
							visible: !! pill?.checkVisibility( {
								visibilityProperty: true,
							} ),
							top: Math.round( box?.top ?? 0 ),
							left: box?.left ?? 0,
							right: box?.right ?? 0,
							width: box?.width ?? 0,
							height: box?.height ?? 0,
						};
					} ),
				};
			} );
		const names = [
			'Deck',
			'Trucks',
			'Wheels',
			'Bearings',
			'Hardware',
			'Grip tape',
			'Review',
		];
		const builds = [
			{ step: 'deck' },
			{
				deck: id( 'builder-test-deck-8-25' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			},
			// The widest name, with the step after it a link.
			{
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				step: 'hardware',
			},
			// Six links and the review.
			{
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1' ),
				grip: id( 'builder-test-grip' ),
				step: 'review',
			},
		];
		for ( const width of [ 320, 390, 430 ] ) {
			await page.setViewportSize( { width, height: 844 } );
			for ( const picks of builds ) {
				await page.goto( linkTo( picks ) );
				await expect( heading( page ) ).toBeVisible();
				const at = `${ width } px, step ${ picks.step }`;
				const drawn = await rail();
				expect( drawn.scrolls, `${ at }: the rail scrolls` ).toBe(
					false
				);
				expect(
					drawn.steps.map( ( step ) => step.tip ),
					`${ at }: each step's name is its tooltip`
				).toEqual( names );
				expect(
					drawn.steps.filter( ( step ) => step.visible ),
					`${ at }: every step is shown`
				).toHaveLength( 7 );
				expect(
					new Set( drawn.steps.map( ( step ) => step.top ) ).size,
					`${ at }: the steps are in one row`
				).toBe( 1 );
				for ( const step of drawn.steps ) {
					expect(
						step.left >= drawn.left - 1 &&
							step.right <= drawn.right + 1,
						`${ at }: ${ step.name } is inside the rail`
					).toBe( true );
					expect(
						step.named,
						`${ at }: ${ step.name } shows its name`
					).toBe( step.current );
					if ( step.link ) {
						expect(
							Math.min( step.width, step.height ),
							`${ at }: ${ step.name } is a 44 px target`
						).toBeGreaterThanOrEqual( 44 );
					}
				}
			}
		}
		// The kit's own page gives the builder a desktop's width; the test page's column is a phone's.
		await page.setViewportSize( { width: 1280, height: 900 } );
		await page.goto( '/product/builder-test-kit/' );
		const desktop = await rail();
		expect(
			desktop.steps
				.filter( ( step ) => step.named )
				.map( ( step ) => step.name )
		).toEqual( names );
	} );

	// Cowork's 1.1.0 review, F25 (#123): at 320, 390 and 430 px the pills ran from the screen's left edge to its right,
	// and on Kadence the step shown had its name under its pill, below the rail's border, so it read as the panel's.
	// The rail keeps the page's 16 px gutter where the row has room, and its border is drawn under the name.
	for ( const theme of [ 'kadence', 'storefront', 'twentytwentyfive' ] ) {
		test( `keeps the phone rail inside the gutter, and the step's name above its border, on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			const builds = [
				{ step: 'deck' },
				// The widest name, which from 26rem sits in its pill.
				{
					deck: id( 'builder-test-deck-8-0' ),
					trucks: id( 'builder-test-trucks-139' ),
					wheels: id( 'builder-test-wheels-52' ),
					bearings: id( 'builder-test-bearings' ),
					hardware: id( 'builder-test-bolts-1' ),
					step: 'hardware',
				},
			];
			for ( const width of [ 320, 390, 430 ] ) {
				await page.setViewportSize( { width, height: 844 } );
				for ( const picks of builds ) {
					await page.goto( linkTo( picks ) );
					await expect( heading( page ) ).toBeVisible();
					const at = `${ width } px, step ${ picks.step }`;
					const drawn = await page
						.locator( '.cbuild-progress' )
						.evaluate( ( nav ) => {
							const edge = nav.getBoundingClientRect();
							const pills = [
								...nav.querySelectorAll< HTMLElement >(
									'.cbuild-progress__step > :not([hidden])'
								),
							].map( ( pill ) => pill.getBoundingClientRect() );
							const name = nav
								.querySelector(
									'.cbuild-progress__step--current .cbuild-progress__label > [aria-hidden="true"]'
								)
								?.getBoundingClientRect();
							return {
								left: edge.left,
								right: edge.right,
								// The border's top edge: the rail's foot.
								foot:
									edge.bottom -
									parseFloat(
										getComputedStyle( nav )
											.borderBottomWidth
									),
								rows: new Set(
									pills.map( ( box ) =>
										Math.round( box.top )
									)
								).size,
								first: Math.min(
									...pills.map( ( box ) => box.left )
								),
								last: Math.max(
									...pills.map( ( box ) => box.right )
								),
								// The row's own width: the pills and the least space between them.
								row:
									pills.reduce(
										( sum, box ) => sum + box.width,
										0
									) +
									( pills.length - 1 ) * 2,
								name: name
									? {
											bottom: name.bottom,
											left: name.left,
											right: name.right,
										}
									: null,
							};
						} );
					// The gutter the row leaves room for: 16 px a side at most, none where the pills fill the rail.
					const room = drawn.right - drawn.left - drawn.row;
					const gutter = Math.max( 0, Math.min( 16, room / 2 ) );
					if ( room >= 0 ) {
						expect( drawn.rows, `${ at }: one row` ).toBe( 1 );
					}
					expect(
						Math.round( drawn.first - drawn.left ),
						`${ at }: the rail's start inside the gutter`
					).toBeGreaterThanOrEqual( Math.floor( gutter ) - 1 );
					expect(
						Math.round( drawn.right - drawn.last ),
						`${ at }: the rail's end inside the gutter`
					).toBeGreaterThanOrEqual( Math.floor( gutter ) - 1 );
					expect(
						drawn.name,
						`${ at }: the name shows`
					).not.toBeNull();
					expect(
						Math.round( drawn.name!.bottom - drawn.foot ),
						`${ at }: the name above the rail's border`
					).toBeLessThanOrEqual( 0 );
					expect(
						drawn.name!.left >= drawn.left - 0.5 &&
							drawn.name!.right <= drawn.right + 0.5,
						`${ at }: the name inside the rail`
					).toBe( true );
				}
			}
		} );
	}

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

	// Spec (configurator): "Targets: 44 px on a phone" (the spec reviewer's M10 minor M7): the widths, the cards'
	// controls, the chips, Sort and Show more, on the steps that have them and the review.
	test( 'gives every control a target of at least 44 CSS pixels on a phone', async ( {
		page,
	} ) => {
		test.skip(
			( page.viewportSize()?.width ?? 1280 ) >= 640,
			"A phone's targets; a desktop's are 24 px."
		);
		const small = () =>
			page.locator( '.cbuild' ).evaluate( ( root ) => {
				const found: string[] = [];
				for ( const node of root.querySelectorAll< HTMLElement >(
					// "Back to the shop" and "Sign in to save this build" too (#86).
					'button, select, input[type="search"], summary, .cbuild-chip, .cbuild-progress__link, .cbuild-card__page, .cbuild__back, .cbuild-save__sign-in a'
				) ) {
					const box = node.getBoundingClientRect();
					if (
						box.width > 0 &&
						( box.width < 44 || box.height < 44 )
					) {
						found.push(
							`${ node.className || node.tagName } ${ Math.round( box.width ) }x${ Math.round( box.height ) }`
						);
					}
				}
				return found;
			} );
		for ( const link of [
			linkTo( { step: 'deck' } ),
			linkTo( { step: 'deck', _width: '8in' } ),
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} ),
		] ) {
			await page.goto( link );
			await expect( heading( page ) ).toBeVisible();
			expect( await small(), link ).toEqual( [] );
		}
		await page.goto( fixture().page );
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		expect( await small(), 'the review' ).toEqual( [] );
	} );

	// ROADMAP, milestone 10: "200 % zoom" (the spec reviewer's M10 minor M8). A 1,280 px screen at 200 % is 640 CSS
	// pixels across: the builder lays out as a tablet's, every step and the review in view, nothing scrolling sideways.
	test( 'takes 200 % zoom with nothing scrolling sideways', async ( {
		page,
	} ) => {
		await page.setViewportSize( { width: 640, height: 450 } );
		for ( const link of [
			linkTo( { step: 'deck' } ),
			linkTo( { step: 'deck', _width: '8in' } ),
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} ),
		] ) {
			await page.goto( link );
			await expect( heading( page ) ).toBeVisible();
			expect( await overflows( page ), link ).toEqual( [] );
		}
		await page.goto( fixture().page );
		await page
			.locator( '.cbuild-presets' )
			.getByRole( 'button', { name: 'Start from Street' } )
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		expect( await overflows( page ) ).toEqual( [] );
		await expect(
			page
				.locator( '.cbuild' )
				.getByRole( 'button', { name: 'Add to cart' } )
		).toBeVisible();
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

	// The M9 audit's minor: a pressed filter keeps its inset line in the accent under the focus ring, as a chosen card
	// does, so being pressed still shows while it has focus.
	test( "keeps a pressed filter's inset line under the focus ring", async ( {
		page,
	} ) => {
		await page.goto( linkTo( { step: 'deck' } ) );
		const pressed = page.locator(
			'.cbuild-filters__option[aria-pressed="true"]'
		);
		// A filter as the step's template draws it, pressed. The store hydrates the builder after the page's load, in
		// pieces, and on a slow runner took a button added before it away (CI run 44737515082, webkit-phone): added
		// again until it stays and holds focus.
		await expect( async () => {
			await page.evaluate( () => {
				if (
					document.querySelector(
						'.cbuild-filters__option[aria-pressed="true"]'
					)
				) {
					return;
				}
				const option = document.createElement( 'button' );
				option.className = 'cbuild-filters__option';
				option.setAttribute( 'aria-pressed', 'true' );
				option.textContent = 'Pressed';
				document.querySelector( '.cbuild' )?.append( option );
			} );
			await page.keyboard.press( 'Shift' );
			await pressed.focus( { timeout: 1000 } );
			await expect( pressed ).toBeFocused( { timeout: 1000 } );
		} ).toPass();
		const shadows = await pressed.evaluate( ( option ) => ( {
			ring: option.matches( ':focus-visible' ),
			shadow: getComputedStyle( option ).boxShadow,
		} ) );
		expect( shadows.ring ).toBe( true );
		expect( shadows.shadow ).toContain( 'inset' );
	} );

	test( 'says the chosen card and the focus by a border and an outline in forced colours', async ( {
		page,
		browserName,
		javaScriptEnabled,
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
		// The click's answer drawn before the arrow, so the arrow's choice is never drawn over by it. Waited for by the
		// answer itself: the pick asks 150 ms after the click (D-308), so aria-busy can still be false before it asks,
		// and under load the answer came after the arrow (three lanes, 23 November).
		// Without the script a click only checks the radio: nothing is asked.
		const picked =
			false === javaScriptEnabled
				? Promise.resolve()
				: page.waitForResponse(
						( response ) =>
							response.url().includes( '/state' ) &&
							response.url().includes( 'wheels' )
					);
		await cards.first().click();
		await picked;
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		const width = ( index: number ) =>
			cards
				.nth( index )
				.evaluate( ( card ) =>
					parseFloat( getComputedStyle( card ).borderTopWidth )
				);
		// Polled: under load the answer is drawn a task after it comes, and aria-busy can read false before it is (three
		// lanes, the M11 audit's /verify: "Expected: >= 3 / Received: 1").
		await expect.poll( () => width( 0 ) ).toBeGreaterThanOrEqual( 3 );
		expect( await width( 1 ) ).toBeLessThan( 3 );
		// Next in either direction of text; ArrowRight is the previous radio right to left.
		await page.keyboard.press( 'ArrowDown' );
		const ring = await cards
			.nth( 1 )
			.evaluate( ( card ) => getComputedStyle( card ).outlineStyle );
		expect( ring ).toBe( 'solid' );
		// And in the system's Highlight colour, as its border is: the arrow chose it as it moved the focus (the M9
		// audit's minor).
		// Polled: the arrow's pick asks the route 150 ms later (D-308), and its answer is drawn after that.
		await expect
			.poll( () =>
				cards.nth( 1 ).evaluate( ( card ) => {
					const probe = document.createElement( 'span' );
					probe.style.color = 'Highlight';
					document.body.append( probe );
					const highlight = getComputedStyle( probe ).color;
					probe.remove();
					return {
						border:
							getComputedStyle( card ).borderTopColor ===
							highlight,
						outline:
							getComputedStyle( card ).outlineColor === highlight,
					};
				} )
			)
			.toEqual( { border: true, outline: true } );
	} );
} );

// Spec (configurator): Milestone 11, the motion (D-256, D-305). Each moment moves for its time in the spec's table, by
// `transform` and `opacity` alone; under reduced motion or the kit's subtle each is a fade of 150 ms or less; a step
// crosses over by a view transition, faded where motion is calm, and at once where the browser has none.
test.describe( 'Motion', () => {
	type Seen = {
		id: string;
		name: string;
		duration: number;
		delay: number;
		iterations: number;
		properties: string[];
		pseudo: string;
	};

	/**
	 * Watch the page for a second and a half: every animation it runs, and each view transition started.
	 *
	 * @param page The page.
	 */
	const watch = ( page: Page ) =>
		page.evaluate( () => {
			const record = window as unknown as {
				seen: Map< Animation, unknown >;
				transitions: number;
				watched: boolean;
				amounts: string[];
				places: string[];
				said: { amount: string; status: string }[];
			};
			record.seen = new Map();
			record.transitions = 0;
			record.watched = false;
			record.amounts = [];
			record.places = [];
			record.said = [];
			// Each animation is noted as it is made, as well as at each frame: on a slow runner a frame can come after a
			// 220 ms moment with no delay has already ended, and getAnimations() no longer holds it (CI run 45590634639,
			// webkit-phone, the first wheel's).
			const note = ( animation: Animation ) => {
				if ( record.seen.has( animation ) ) {
					return;
				}
				const effect = animation.effect as KeyframeEffect | null;
				record.seen.set( animation, {
					id: animation.id,
					name: ( animation as CSSAnimation ).animationName ?? '',
					duration: Number(
						effect?.getComputedTiming().duration ?? 0
					),
					delay: Number( effect?.getTiming().delay ?? 0 ),
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
			};
			const animate = Element.prototype.animate;
			Element.prototype.animate = function (
				this: Element,
				...args: Parameters< Element[ 'animate' ] >
			) {
				const animation = animate.apply( this, args );
				// Once the caller has named it: the motion module sets its id after animate() returns.
				queueMicrotask( () => note( animation ) );
				return animation;
			};
			const start = document.startViewTransition?.bind( document );
			if ( start ) {
				document.startViewTransition = ( ( update: () => void ) => {
					record.transitions++;
					crossing++;
					const transition = start( update );
					transition.finished.finally( () => crossing-- );
					return transition;
				} ) as typeof document.startViewTransition;
			}
			// Watched for 1.5 s at least, and then until the page has been still for half a second: on a slow runner the
			// step crossed over after a fixed 1.5 s had run out, and its panel's slide went unseen (CI run 45356034288,
			// webkit-desktop). Eight seconds at most.
			const began = performance.now();
			const until = began + 1500;
			let still = began;
			let crossing = 0;
			const frame = () => {
				const amount = document.querySelector(
					'.cbuild-bar__amount > span:first-child'
				)?.textContent;
				// What the status region holds at each frame where it or the bar's amount changed: a screen reader hears
				// the total from it once, never a frame of the count (spec: The counting total).
				const status =
					document.querySelector( '.cbuild__status' )?.textContent ??
					'';
				const last = record.said.at( -1 );
				if (
					! last ||
					last.status !== status ||
					( amount && last.amount !== amount )
				) {
					record.said.push( { amount: amount ?? '', status } );
				}
				if ( amount && amount !== record.amounts.at( -1 ) ) {
					record.amounts.push( amount );
					// Where the bar, its total and its controls are at each amount, and their sizes: the count must not move
					// or grow them. Each frame is written as the bar's size with each control's place and size inside it,
					// then where the bar is on the screen, then the page's scroll. The bar sticks to the foot of the screen
					// and stops where the builder ends, so a scroll of the page under it (as the picked card is kept in its
					// place, which WebKit did during the count) moves it on the page, or on the screen, and is no shift.
					const own = document.querySelector( '.cbuild-bar' );
					const frame0 = own?.getBoundingClientRect();
					const inside = [
						...document.querySelectorAll< HTMLElement >(
							'.cbuild-bar, .cbuild-bar__amount, .cbuild-bar a, .cbuild-bar button'
						),
					]
						.filter( ( control ) => null !== control.offsetParent )
						.map( ( control ) => {
							const box = control.getBoundingClientRect();
							return `${ Math.round( box.left - ( frame0?.left ?? 0 ) ) },${ Math.round( box.top - ( frame0?.top ?? 0 ) ) },${ Math.round( box.width ) }x${ Math.round( box.height ) }`;
						} )
						.join( ' ' );
					record.places.push(
						`${ inside } | ${ Math.round(
							frame0?.left ?? 0
						) },${ Math.round( frame0?.top ?? 0 ) } | ${ Math.round(
							window.scrollY
						) }`
					);
				}
				document.getAnimations().forEach( note );
				const now = performance.now();
				if (
					crossing > 0 ||
					document.getAnimations().length > 0 ||
					'true' ===
						document
							.querySelector( '.cbuild' )
							?.getAttribute( 'aria-busy' )
				) {
					still = now;
				}
				if (
					now < began + 8000 &&
					( now < until || now - still < 500 )
				) {
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
				amounts: string[];
				places: string[];
				said: { amount: string; status: string }[];
			};
			return {
				animations: [ ...record.seen.values() ],
				transitions: record.transitions,
				amounts: record.amounts,
				places: record.places,
				said: record.said,
			};
		} );
	};

	/**
	 * The builder's own moments, by the name the motion module gives each.
	 *
	 * @param animations What the page ran.
	 * @param name       The moment's name, such as cbuild-deck.
	 */
	const moment = ( animations: Seen[], name: string ) =>
		animations.filter( ( animation ) => name === animation.id );

	const pickAndStep = async ( page: Page ) => {
		await pickDeck( page, '8.0' );
		await settled( page );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		// Next once the count has landed on the server's total, as the test's three moments follow one another. Pressed
		// during the count, the press ends it (a press ends every moment) and takes the deck step's width line away
		// (D-408): on webkit-phone under load the count was still running then, and its last frame was drawn on the
		// shorter step (#152, D-512). The count's own frames are what this test holds still.
		const total = page.locator( '.cbuild-bar__total .screen-reader-text' );
		await expect
			.poll( async () => {
				const amount = await page
					.locator( '.cbuild-bar__amount > :first-child' )
					.textContent();
				return ( await total.textContent() )?.endsWith(
					` ${ amount }`
				);
			} )
			.toBe( true );
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.first()
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
	};

	/**
	 * Every one of the builder's moments, and of the step's view transition, moves transform and opacity alone.
	 *
	 * @param animations What the page ran.
	 */
	const onlyTransformAndOpacity = ( animations: Seen[] ) => {
		for ( const animation of animations ) {
			if (
				animation.id.startsWith( 'cbuild-' ) ||
				animation.name.startsWith( 'cbuild-' )
			) {
				expect(
					animation.properties.filter(
						( property ) =>
							! [ 'transform', 'opacity' ].includes( property )
					),
					`${ animation.id || animation.name } ${ animation.pseudo }`
				).toEqual( [] );
				// The line that says a slow answer is drawing runs four times (D-308), and its own test holds that.
				if ( 'cbuild-drawing' !== animation.name ) {
					expect( animation.iterations ).toBe( 1 );
				}
			}
		}
	};

	/**
	 * Under reduced motion or subtle: each of the builder's moments a fade, opacity alone, of 150 ms or less.
	 *
	 * @param animations What the page ran.
	 */
	const faded = ( animations: Seen[] ) => {
		for ( const animation of animations ) {
			const what = `${ animation.id || animation.name } ${
				animation.pseudo
			}`;
			expect( animation.duration, what ).toBeLessThanOrEqual( 150 );
			if ( animation.id.startsWith( 'cbuild-' ) ) {
				expect( animation.properties, what ).toEqual( [ 'opacity' ] );
				expect( animation.delay, what ).toBe( 0 );
			}
		}
	};

	/**
	 * Every moment in the spec's table, each as its name, the link that leads to it (null: the builder's own page) and
	 * the press that plays it.
	 *
	 * @param page The page.
	 */
	const momentsOf = (
		page: Page
	): [
		string,
		Record< string, string | number > | null,
		() => Promise< void >,
	][] => {
		const parts = {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-54' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1' ),
		};
		const builder = page.locator( '.cbuild' );
		return [
			[ 'deck', null, () => pickDeck( page, '8.0' ) ],
			[
				'step',
				{ deck: parts.deck, step: 'deck' },
				() =>
					builder
						.getByRole( 'button', { name: 'Next: Trucks' } )
						.first()
						.click(),
			],
			[
				'trucks',
				{ deck: parts.deck, step: 'trucks' },
				() => choose( page, 'Builder test trucks 139' ),
			],
			[
				'wheels',
				{ deck: parts.deck, trucks: parts.trucks, step: 'wheels' },
				() => choose( page, 'Builder test wheels 54' ),
			],
			[
				'swapped',
				{ ...parts, step: 'wheels' },
				() => choose( page, 'Builder test wheels 52' ),
			],
			[
				'grip',
				{ ...parts, step: 'grip' },
				() => choose( page, 'Builder test grip' ),
			],
			[
				'removed',
				// The risers are the part a shopper can leave off (D-246).
				{
					...parts,
					risers: id( 'builder-test-risers-1-4' ),
					step: 'risers',
				},
				() =>
					builder.getByRole( 'radio', { name: 'No risers' } ).check(),
			],
			[
				'turn',
				{ deck: parts.deck, step: 'trucks' },
				() =>
					page
						.locator( '#cbuild-picture' )
						.getByRole( 'button', { name: 'Top', exact: true } )
						.click(),
			],
			[
				'cart',
				{ ...parts, grip: id( 'builder-test-grip' ), step: 'review' },
				() =>
					builder
						.getByRole( 'button', { name: 'Add to cart' } )
						.first()
						.click(),
			],
		];
	};

	test.beforeEach( async ( { page }, info ) => {
		test.skip(
			info.project.name === 'no-js',
			"The moments are the store's; without it the page changes at once."
		);
		await page.goto( fixture().page );
		await page.waitForLoadState( 'load' );
	} );

	test( 'the deck settles onto the stage in 280 ms, the total counts to the server’s, and the step crosses over', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await watch( page );
		await pickAndStep( page );
		const { animations, transitions, amounts, places, said } =
			await seen( page );

		const deck = moment( animations, 'cbuild-deck' );
		expect( deck ).toHaveLength( 1 );
		expect( deck[ 0 ]!.duration ).toBe( 280 );
		expect( deck[ 0 ]!.properties.sort() ).toEqual( [
			'opacity',
			'transform',
		] );
		expect( animations.map( ( animation ) => animation.name ) ).toContain(
			'cbuild-settle'
		);
		if (
			await page.evaluate(
				() => 'function' === typeof document.startViewTransition
			)
		) {
			expect( transitions, 'the step crossed over' ).toBeGreaterThan( 0 );
			expect(
				animations.some(
					( animation ) =>
						'cbuild-panel-in' === animation.name &&
						200 === animation.duration
				),
				'the panel slid in for 200 ms'
			).toBe( true );
		}
		onlyTransformAndOpacity( animations );
		// The total counted up from nothing through amounts between, and ended on the server's own string; the
		// frames are hidden from a screen reader, which hears the total from the status region.
		const bar = page.locator( '.cbuild-bar__amount' );
		await expect( bar ).toHaveAttribute( 'aria-hidden', 'true' );
		const total = await bar
			.locator( ':scope > span:first-child' )
			.textContent();
		expect( amounts.at( -1 ) ).toBe( total );
		expect( amounts.length, amounts.join( ' | ' ) ).toBeGreaterThan( 2 );
		// While it counts, the bar, its total and its controls hold the place and size the last amount gives them: no
		// layout shift, which CLS itself leaves out within 500 ms of the press (ROADMAP: Milestone 11).
		const [ inside0, screen0, scroll0 ] = places.at( -1 )!.split( ' | ' );
		expect(
			places.slice( 1 ).filter( ( place ) => {
				const [ inside, screen, scroll ] = place.split( ' | ' );
				return (
					inside !== inside0 ||
					( scroll === scroll0 && screen !== screen0 )
				);
			} ),
			amounts
				.map( ( amount, at ) => `${ amount }: ${ places[ at ] }` )
				.join( '\n' )
		).toEqual( [] );
		// A screen reader hears the total once, from the status region (spec: The counting total; M11 audit, finding
		// 3): the region took the total's sentence once, and held it while the amounts between were drawn.
		const story = said
			.map( ( one ) => `${ one.amount } | ${ one.status }` )
			.join( '\n' );
		const spoken = said
			.map( ( one ) => one.status )
			.filter( ( status, at, all ) => status !== all[ at - 1 ] );
		const between = said.filter(
			( one ) =>
				'' !== one.amount &&
				one.amount !== amounts[ 0 ] &&
				one.amount !== amounts.at( -1 )
		);
		expect( between.length, story ).toBeGreaterThan( 0 );
		// While it counted, the region held one sentence, with the total in it once (the width pressed before the pick
		// said its own total, before the count).
		const held = new Set( between.map( ( one ) => one.status ) );
		expect( held.size, `the status while it counted\n${ story }` ).toBe(
			1
		);
		const sentence = [ ...held ][ 0 ]!;
		expect( sentence.split( 'Total:' ), story ).toHaveLength( 2 );
		// It was set once, not once a frame: the region spoke it once.
		expect(
			spoken.filter( ( status ) => sentence === status ),
			story
		).toHaveLength( 1 );
	} );

	// "Never on load" (M11 audit, finding 8): a card settles when the shopper picks it, not when a link with a pick
	// is loaded and its card is drawn checked; and it still does on a pick.
	test( 'a card settles at a pick, never as a link with a pick loads', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		// Every CSS animation that starts on the page, from its first paint.
		await page.addInitScript( () => {
			const started: string[] = [];
			( window as unknown as { started: string[] } ).started = started;
			document.addEventListener(
				'animationstart',
				( event ) => started.push( event.animationName ),
				true
			);
		} );
		const started = () =>
			page.evaluate(
				() => ( window as unknown as { started: string[] } ).started
			);
		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'deck' } )
		);
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'data-ready',
			''
		);
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test deck 8.0' } )
		).toBeChecked();
		// Two frames for a settle that had started to be seen.
		await page.evaluate(
			() =>
				new Promise( ( done ) =>
					requestAnimationFrame( () => requestAnimationFrame( done ) )
				)
		);
		expect(
			await started(),
			'nothing settles as the page loads'
		).not.toContain( 'cbuild-settle' );
		expect(
			await page.evaluate( () =>
				document
					.getAnimations()
					.map(
						( animation ) =>
							( animation as CSSAnimation ).animationName
					)
			)
		).not.toContain( 'cbuild-settle' );

		await pickDeck( page, '8.25' );
		await expect
			.poll( started, { message: 'the card picked settles' } )
			.toContain( 'cbuild-settle' );
	} );

	test( 'the trucks slide in 60 ms apart, the wheels 40 ms apart and turning, each for its time', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'data-ready',
			''
		);
		await watch( page );
		await choose( page, 'Builder test trucks 139' );
		let { animations } = await seen( page );
		const trucks = moment( animations, 'cbuild-trucks' );
		expect( trucks.map( ( animation ) => animation.delay ) ).toEqual( [
			0, 60,
		] );
		expect( trucks.map( ( animation ) => animation.duration ) ).toEqual( [
			240, 240,
		] );
		onlyTransformAndOpacity( animations );

		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'data-ready',
			''
		);
		await watch( page );
		await choose( page, 'Builder test wheels 54' );
		( { animations } = await seen( page ) );
		const wheels = moment( animations, 'cbuild-wheels' );
		expect( wheels.map( ( animation ) => animation.delay ) ).toEqual( [
			0, 40, 80, 120,
		] );
		expect(
			new Set( wheels.map( ( animation ) => animation.duration ) )
		).toEqual( new Set( [ 220 ] ) );
		onlyTransformAndOpacity( animations );
	} );

	/**
	 * The trucks' step, ready, with the deck on the board.
	 *
	 * @param page The page.
	 */
	const atTrucks = async ( page: Page ) => {
		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'data-ready',
			''
		);
	};

	// The changed area's highlight (#138, D-413): after a pick, the part's area of the board is outlined.
	test( 'a part picked is outlined on the board for 600 ms, on each of its layers, by transform and opacity alone', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await atTrucks( page );
		await watch( page );
		await choose( page, 'Builder test trucks 139' );
		const { animations } = await seen( page );
		const outlines = moment( animations, 'cbuild-outline' );
		expect( outlines, 'one outline a truck' ).toHaveLength( 2 );
		for ( const outlined of outlines ) {
			expect( outlined.duration ).toBe( 600 );
			expect( outlined.delay ).toBe( 0 );
			expect( outlined.iterations ).toBe( 1 );
			expect( outlined.pseudo ).toBe( '::after' );
			expect( [ ...outlined.properties ].sort() ).toEqual( [
				'opacity',
				'transform',
			] );
		}
		onlyTransformAndOpacity( animations );
		// The build sentence still says what changed: the outline is for the eye alone.
		await expect(
			page.locator( '#cbuild-picture .cbuild-picture__description' )
		).toContainText( 'Builder test trucks 139' );
		await expect(
			page.locator(
				'#cbuild-picture .cbuild-board__layer[data-outlined]'
			)
		).toHaveCount( 0 );
	} );

	for ( const calm of [ 'reduced motion', 'subtle' ] as const ) {
		test( `under ${ calm } the outline shows at once and fades in 150 ms or less, opacity alone`, async ( {
			page,
		} ) => {
			await page.emulateMedia( {
				reducedMotion:
					'reduced motion' === calm ? 'reduce' : 'no-preference',
			} );
			await atTrucks( page );
			if ( 'subtle' === calm ) {
				await page
					.locator( '.cbuild' )
					.evaluate( ( root ) =>
						root.classList.add( 'cbuild--subtle' )
					);
			}
			await watch( page );
			await choose( page, 'Builder test trucks 139' );
			const outlined = page.locator(
				'#cbuild-picture .cbuild-board__layer[data-outlined]'
			);
			// Shown without a move: each truck's layer marked, its outline drawn whole.
			await expect( outlined ).toHaveCount( 2 );
			expect(
				await outlined
					.first()
					.evaluate(
						( layer ) =>
							getComputedStyle( layer, '::after' ).opacity
					)
			).toBe( '1' );
			const { animations } = await seen( page );
			const outlines = moment( animations, 'cbuild-outline' );
			expect( outlines, 'one fade a truck' ).toHaveLength( 2 );
			for ( const fade of outlines ) {
				expect( fade.properties ).toEqual( [ 'opacity' ] );
				expect( fade.pseudo ).toBe( '::after' );
			}
			faded( animations );
			await expect( outlined ).toHaveCount( 0 );
		} );
	}

	for ( const motion of [ 'no-preference', 'reduce' ] as const ) {
		test( `the outline moves nothing on the page${
			'reduce' === motion ? ' under reduced motion' : ''
		}: no layer of the board changes its place or size, and no layout shift`, async ( {
			page,
		}, info ) => {
			await page.emulateMedia( { reducedMotion: motion } );
			await atTrucks( page );
			// The pick's answer held a second, as the demo's host takes it (D-406).
			await holdAnswers( page );
			const chromium = info.project.name.startsWith( 'chromium' );
			if ( chromium ) {
				await watchShifts( page );
			}
			const from = await pageNow( page );
			await page.evaluate( () => {
				const record = window as unknown as {
					boxes: Set< string >;
					outlined: number;
				};
				record.boxes = new Set();
				record.outlined = 0;
				// At each frame an outline is shown or playing: where each of the board's layers and images is laid out,
				// its offsets, which a transform does not move. Every such frame must be the same.
				const began = performance.now();
				const frame = () => {
					const board = document.querySelector(
						'#cbuild-picture > .cbuild-board:not(.cbuild-board--top)'
					);
					const playing = document
						.getAnimations()
						.some(
							( animation ) => 'cbuild-outline' === animation.id
						);
					if (
						board &&
						( playing || board.querySelector( '[data-outlined]' ) )
					) {
						record.outlined++;
						record.boxes.add(
							[
								board,
								...board.querySelectorAll< HTMLElement >(
									'.cbuild-board__layer, .cbuild-board__image'
								),
							]
								.map( ( element ) => {
									const box = element as HTMLElement;
									return `${ box.offsetLeft },${ box.offsetTop },${ box.offsetWidth }x${ box.offsetHeight }`;
								} )
								.join( ' ' )
						);
					}
					if ( performance.now() < began + 4000 ) {
						window.requestAnimationFrame( frame );
					}
				};
				window.requestAnimationFrame( frame );
			} );
			await choose( page, 'Builder test trucks 139' );
			await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
				'aria-busy',
				'false',
				{ timeout: 10_000 }
			);
			await expect(
				page.locator(
					'#cbuild-picture .cbuild-board__layer[data-outlined]'
				)
			).toHaveCount( 0 );
			await page.waitForTimeout( 1500 );
			const { boxes, outlined } = await page.evaluate( () => {
				const record = window as unknown as {
					boxes: Set< string >;
					outlined: number;
				};
				return {
					boxes: [ ...record.boxes ],
					outlined: record.outlined,
				};
			} );
			expect( outlined, 'the outline was seen' ).toBeGreaterThan( 0 );
			expect(
				boxes,
				'the board laid out the same at each frame'
			).toHaveLength( 1 );
			if ( chromium ) {
				const { counted, said } = await shiftsSince( page, from );
				expect( counted, `layout shift: ${ said }` ).toBe( 0 );
			}
		} );
	}

	for ( const calm of [ 'reduced motion', 'subtle' ] as const ) {
		test( `under ${ calm } every moment is a fade of 150 ms or less, opacity alone`, async ( {
			page,
		} ) => {
			await page.emulateMedia( {
				reducedMotion:
					'reduced motion' === calm ? 'reduce' : 'no-preference',
			} );
			if ( 'subtle' === calm ) {
				// The kit's Motion on subtle puts the class on the builder's root, as the unit tests show the server
				// does; the store reads it at each moment.
				await page
					.locator( '.cbuild' )
					.evaluate( ( root ) =>
						root.classList.add( 'cbuild--subtle' )
					);
			}
			await watch( page );
			await pickAndStep( page );
			const { animations, transitions, amounts } = await seen( page );
			expect( moment( animations, 'cbuild-deck' ) ).toHaveLength( 1 );
			faded( animations );
			if (
				await page.evaluate(
					() => 'function' === typeof document.startViewTransition
				)
			) {
				expect( transitions, 'the step faded over' ).toBeGreaterThan(
					0
				);
			}
			// The total changes at once: no amount between.
			expect( amounts.length, amounts.join( ' | ' ) ).toBeLessThanOrEqual(
				2
			);
			expect(
				animations.filter(
					( animation ) => 'cbuild-settle' === animation.name
				),
				'the card does not settle'
			).toEqual( [] );
		} );
	}

	// Every moment in the spec's table, not the deck and the step alone, is a fade under both calm modes, and Add to
	// cart plays no move of its own: the Mini-Cart's count changing is the moment (M11 audit, finding 2).
	for ( const calm of [ 'reduced motion', 'subtle' ] as const ) {
		test( `under ${ calm } each moment in the table is a fade of 150 ms or less, and Add to cart sends nothing off`, async ( {
			page,
		} ) => {
			test.setTimeout( 120_000 );
			await page.emulateMedia( {
				reducedMotion:
					'reduced motion' === calm ? 'reduce' : 'no-preference',
			} );
			const builder = page.locator( '.cbuild' );
			for ( const [ name, link, act ] of momentsOf( page ) ) {
				await page.goto(
					null === link ? fixture().page : linkTo( link )
				);
				await expect( builder ).toHaveAttribute( 'data-ready', '' );
				if ( 'subtle' === calm ) {
					// As the server marks a kit whose Motion is subtle (see the test above).
					await builder.evaluate( ( root ) =>
						root.classList.add( 'cbuild--subtle' )
					);
				}
				await watch( page );
				await act();
				await expect( builder ).toHaveAttribute( 'aria-busy', 'false' );
				const { animations } = await seen( page );
				await test.step( name, () => faded( animations ) );
				if ( 'cart' === name ) {
					expect(
						moment( animations, 'cbuild-cart' ),
						'the board is not sent off'
					).toEqual( [] );
				}
			}
		} );
	}

	test( 'a step changes at once where the browser has no view transitions', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await page.evaluate( () => {
			Object.defineProperty( document, 'startViewTransition', {
				value: undefined,
				configurable: true,
			} );
		} );
		// Next's answer, asked ahead once the deck's answer lights it (D-355): in hand before the press.
		const ahead = page.waitForResponse(
			( response ) =>
				isRoute( response.url() ) &&
				response.url().includes( 'cbuild%5Bstep%5D=trucks' )
		);
		await pickDeck( page, '8.0' );
		await settled( page );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await ( await ahead ).finished();
		// Watched once the API is gone, so nothing of the watch's own can start a transition; each frame counted, the
		// frame the step's answer came in, and the first frame that shows the new heading (M11 audit, finding 4).
		await watch( page );
		await page.evaluate( () => {
			const record = window as unknown as {
				frame: number;
				answered: number[];
				headed: number;
			};
			record.frame = 0;
			record.answered = [];
			record.headed = -1;
			const was =
				document.querySelector( '.cbuild__heading' )?.textContent;
			const tick = () => {
				record.frame++;
				if (
					record.headed < 0 &&
					document.querySelector( '.cbuild__heading' )
						?.textContent !== was
				) {
					record.headed = record.frame;
				}
				window.requestAnimationFrame( tick );
			};
			window.requestAnimationFrame( tick );
			const own = window.fetch.bind( window );
			window.fetch = async ( ...args: Parameters< typeof fetch > ) => {
				const response = await own( ...args );
				if ( String( args[ 0 ] ).includes( '/cbuild/v1/' ) ) {
					await response.clone().text();
					record.answered.push( record.frame );
				}
				return response;
			};
			// Next's answer is asked ahead once it lights (D-355) and is in hand before the press: the press is the
			// moment the answer is there to show.
			document.addEventListener(
				'click',
				() => record.answered.push( record.frame ),
				{ capture: true }
			);
		} );
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Next: Trucks' } )
			.first()
			.click();
		await expect( heading( page ) ).toHaveText(
			'Step 2 of 7: Choose your trucks'
		);
		const { animations, transitions } = await seen( page );
		expect( transitions, 'no view transition started' ).toBe( 0 );
		expect(
			animations.filter( ( animation ) =>
				[ 'cbuild-panel-in', 'cbuild-panel-out' ].includes(
					animation.name
				)
			),
			'the panel did not slide'
		).toEqual( [] );
		const { answers, headed } = await page.evaluate( () => {
			const record = window as unknown as {
				answered: number[];
				headed: number;
			};
			return { answers: record.answered, headed: record.headed };
		} );
		const answer = answers.filter( ( at ) => at <= headed ).at( -1 );
		expect( answer, `answers at ${ answers.join( ', ' ) }` ).toBeDefined();
		expect(
			headed - answer!,
			'the heading changed in the frame after the answer'
		).toBeLessThanOrEqual( 1 );
		await expect(
			page.evaluate( () =>
				document.documentElement.classList.contains( 'cbuild-crossing' )
			)
		).resolves.toBe( false );
	} );

	test( 'the grip lays down on the top in 300 ms, a part swapped settles in 200 ms, and Add to cart sends the board off in 400 ms', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		const parts = {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-54' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1' ),
		};
		const ready = () =>
			expect( page.locator( '.cbuild' ) ).toHaveAttribute(
				'data-ready',
				''
			);

		await page.goto( linkTo( { ...parts, step: 'grip' } ) );
		await ready();
		await watch( page );
		await choose( page, 'Builder test grip' );
		let { animations } = await seen( page );
		const grip = moment( animations, 'cbuild-grip' );
		expect( grip ).toHaveLength( 1 );
		expect( grip[ 0 ]!.duration ).toBe( 300 );
		expect( grip[ 0 ]!.properties ).toEqual( [ 'transform' ] );

		await page.goto( linkTo( { ...parts, step: 'wheels' } ) );
		await ready();
		await watch( page );
		await choose( page, 'Builder test wheels 52' );
		( { animations } = await seen( page ) );
		const swapped = moment( animations, 'cbuild-swapped' );
		expect( swapped.length ).toBeGreaterThan( 0 );
		expect(
			new Set( swapped.map( ( animation ) => animation.duration ) )
		).toEqual( new Set( [ 200 ] ) );
		expect(
			moment( animations, 'cbuild-wheels' ),
			'a swap, not new wheels'
		).toEqual( [] );
		onlyTransformAndOpacity( animations );

		await page.goto(
			linkTo( {
				...parts,
				grip: id( 'builder-test-grip' ),
				step: 'review',
			} )
		);
		await ready();
		await watch( page );
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.first()
			.click();
		( { animations } = await seen( page ) );
		const cart = moment( animations, 'cbuild-cart' );
		expect( cart ).toHaveLength( 1 );
		expect( cart[ 0 ]!.duration ).toBe( 400 );
		onlyTransformAndOpacity( animations );

		// A part removed: the risers left off, the stage's other photos close the gap in 180 ms (M11 audit, finding 2).
		await page.goto(
			linkTo( {
				...parts,
				risers: id( 'builder-test-risers-1-4' ),
				step: 'risers',
			} )
		);
		await ready();
		await watch( page );
		await page
			.locator( '.cbuild' )
			.getByRole( 'radio', { name: 'No risers' } )
			.check();
		( { animations } = await seen( page ) );
		const removed = moment( animations, 'cbuild-removed' );
		expect( removed.length ).toBeGreaterThan( 0 );
		expect(
			new Set( removed.map( ( animation ) => animation.duration ) )
		).toEqual( new Set( [ 180 ] ) );
		onlyTransformAndOpacity( animations );

		// The price changes: the estimate under the total fades in over 300 ms as the first deck brings it.
		await page.goto( fixture().page );
		await ready();
		await watch( page );
		await pickDeck( page, '8.0' );
		await settled( page );
		( { animations } = await seen( page ) );
		const total = moment( animations, 'cbuild-total' );
		expect( total ).toHaveLength( 1 );
		expect( total[ 0 ]!.duration ).toBe( 300 );
		expect( total[ 0 ]!.properties ).toEqual( [ 'opacity' ] );
	} );

	// Issue #46: the board turns over as a card does; under reduced motion it crosses over with a fade.
	for ( const motion of [ 'no-preference', 'reduce' ] as const ) {
		test( `turns the board over ${
			'reduce' === motion
				? 'with a fade under reduced motion'
				: 'in 400 ms by its transform alone'
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
				.getByRole( 'button', { name: 'Top', exact: true } );
			await expect( turn ).toBeVisible();
			await watch( page );
			await turn.click();
			await expect( turn ).toHaveAttribute( 'aria-pressed', 'true' );
			const turned = moment(
				( await seen( page ) ).animations,
				'cbuild-turn'
			);
			expect( turned ).toHaveLength( 1 );
			if ( 'reduce' === motion ) {
				faded( turned );
				return;
			}
			expect( turned[ 0 ]!.duration ).toBe( 400 );
			expect( turned[ 0 ]!.properties ).toEqual( [ 'transform' ] );
		} );
	}

	// Milestone 11's speed gates (ROADMAP: Milestone 11), as Chrome's guidance measures motion (D-306): with motion on
	// and the CPU slowed four times, each moment's interaction is under 200 ms from the input to the next paint (INP,
	// from Event Timing entries: no new tool); nothing shifts outside the input's 500 ms (CLS); and every animation the
	// trace shows runs on the compositor, off the main thread, so a slow CPU cannot make it stutter, or else changes
	// nothing that shows (Chromium's kAnimationHasNoVisibleChange, as the browser's own view-transition blend). The
	// trace is kept in tmp/motion-traces, which CI keeps as an artifact. The count's own frames, on the main thread,
	// are held still by the deck's test above.
	test( 'each moment answers within 200 ms on a CPU slowed four times, shifts nothing, runs on the compositor, and leaves a trace', async ( {
		page,
		browser,
	}, info ) => {
		test.skip(
			! info.project.name.startsWith( 'chromium' ),
			'Only Chromium can slow its CPU and keep a trace (DevTools protocol).'
		);
		test.setTimeout( 180_000 );
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		const builder = page.locator( '.cbuild' );
		const moments = momentsOf( page );
		const traces = path.join( process.cwd(), 'tmp', 'motion-traces' );
		mkdirSync( traces, { recursive: true } );
		// third_party/blink/renderer/core/animation/compositor_animations.h, FailureReason.
		const NO_VISIBLE_CHANGE = 2 ** 17;
		const measured: string[] = [];
		const devtools = await page.context().newCDPSession( page );
		for ( const [ name, link, act ] of moments ) {
			await page.goto( null === link ? fixture().page : linkTo( link ) );
			await expect( builder ).toHaveAttribute( 'data-ready', '' );
			// Each moment is timed, not core's emoji polyfill reading the page as it loads (#49).
			await emojiDone( page );
			await devtools.send( 'Emulation.setCPUThrottlingRate', {
				rate: 4,
			} );
			await page.evaluate( () => {
				const recorded = window as unknown as {
					interactions: number[];
					shifts: number[];
					frames: number[];
				};
				recorded.interactions = [];
				recorded.shifts = [];
				recorded.frames = [];
				new PerformanceObserver( ( list ) => {
					for ( const entry of list.getEntries() as PerformanceEventTiming[] ) {
						if ( entry.interactionId ) {
							recorded.interactions.push( entry.duration );
						}
					}
				} ).observe( {
					type: 'event',
					durationThreshold: 16,
				} as PerformanceObserverInit );
				new PerformanceObserver( ( list ) => {
					for ( const entry of list.getEntries() as unknown as {
						value: number;
						hadRecentInput: boolean;
					}[] ) {
						if ( ! entry.hadRecentInput ) {
							recorded.shifts.push( entry.value );
						}
					}
				} ).observe( { type: 'layout-shift' } );
				// Recorded, not held: the frames over 50 ms, the page's work and WooCommerce's as well as the builder's.
				new PerformanceObserver( ( list ) => {
					for ( const entry of list.getEntries() ) {
						recorded.frames.push( entry.duration );
					}
				} ).observe( { type: 'long-animation-frame' } );
			} );
			const trace = path.join(
				traces,
				`${ info.project.name }-${ name }.json`
			);
			await browser.startTracing( page, { path: trace } );
			await act();
			await expect( builder ).toHaveAttribute( 'aria-busy', 'false' );
			// The longest moment is the cart's 400 ms; then two frames for the observers to report.
			await page.waitForTimeout( 600 );
			await page.evaluate(
				() =>
					new Promise( ( done ) =>
						requestAnimationFrame( () =>
							requestAnimationFrame( done )
						)
					)
			);
			await browser.stopTracing();
			const { interactions, shifts, frames } = await page.evaluate(
				() => {
					const recorded = window as unknown as {
						interactions: number[];
						shifts: number[];
						frames: number[];
					};
					return {
						interactions: recorded.interactions,
						shifts: recorded.shifts,
						frames: recorded.frames,
					};
				}
			);
			await devtools.send( 'Emulation.setCPUThrottlingRate', {
				rate: 1,
			} );
			// Each animation in the trace, by its id: what it animated, and every reason it could not be composited.
			type Event = {
				name?: string;
				id?: string;
				id2?: { local?: string };
				args?: {
					data?: {
						displayName?: string;
						nodeName?: string;
						compositeFailed?: number;
					};
				};
			};
			const events = (
				JSON.parse( readFileSync( trace, 'utf8' ) ) as {
					traceEvents: Event[];
				}
			 ).traceEvents.filter( ( event ) => 'Animation' === event.name );
			const animations = new Map<
				string,
				{ what: string; failed: number[] }
			>();
			for ( const event of events ) {
				const key = event.id2?.local ?? event.id ?? '';
				const one = animations.get( key ) ?? { what: '', failed: [] };
				const data = event.args?.data;
				if ( data?.nodeName ) {
					one.what = `${ data.displayName || '(script)' } on ${
						data.nodeName
					}`;
				}
				if ( data?.compositeFailed ) {
					one.failed.push( data.compositeFailed );
				}
				animations.set( key, one );
			}
			const stuck = [ ...animations.values() ]
				.filter( ( one ) =>
					one.failed.some(
						( reason ) =>
							0 === Math.floor( reason / NO_VISIBLE_CHANGE ) % 2
					)
				)
				.map(
					( one ) => `${ one.what }: ${ one.failed.join( ', ' ) }`
				);
			const longest = Math.max( 0, ...interactions );
			const shifted = shifts.reduce( ( sum, value ) => sum + value, 0 );
			measured.push(
				`${ name }: interaction ${ longest } ms, CLS ${ shifted.toFixed(
					4
				) }, ${ animations.size } animations, ${
					stuck.length
				} off the compositor, longest frame ${ Math.round(
					Math.max( 0, ...frames )
				) } ms`
			);
			expect
				.soft( longest, `${ name }'s interaction` )
				.toBeLessThan( 200 );
			expect.soft( shifted, `${ name }'s layout shift` ).toBe( 0 );
			expect
				.soft( stuck, `${ name }'s animations off the compositor` )
				.toEqual( [] );
			expect
				.soft( animations.size, `${ name }'s trace has its animations` )
				.toBeGreaterThan( 0 );
		}
		info.annotations.push( {
			type: 'moments at 4×',
			description: measured.join( '; ' ),
		} );
	} );

	// Cowork's review of 1.1.0, F20 (#118): on a real host the state route answers 700 to 1,100 ms after a press, past the
	// 500 ms after an input that CLS leaves out, so whatever the answer moves counts. CI's local route answers within
	// 500 ms, so the gate above never saw it. Here every answer is held back a second, the demo's (D-406), under a theme's
	// 129 px header as on the demo, and each press is read for the builder's shifts CLS counts (no recent input): a width
	// under 0.1, as web.dev counts good; the deck, Next and a truck, D-306's moments, none at all.
	test( 'what an answer a second late moves is no layout shift: a width, a deck, Next and a truck', async ( {
		page,
	}, info ) => {
		test.skip(
			! info.project.name.startsWith( 'chromium' ),
			'Only Chromium reports layout shift (the Layout Instability API).'
		);
		test.setTimeout( 120_000 );
		const builder = page.locator( '.cbuild' );
		await page.goto( fixture().page );
		await expect( builder ).toHaveAttribute( 'data-ready', '' );
		await emojiDone( page );
		await themeHeader( page, 129 );
		await holdAnswers( page );
		await watchShifts( page );
		// Each press, the most it may shift, and what its answer puts in the page's address once it is shown. The deck is
		// picked scrolled to its card. Next is pressed a second after the deck's answer is drawn, as a shopper looks at
		// the board first.
		const presses: [ string, number, () => Promise< void >, string ][] = [
			[
				'a width',
				0.1,
				() =>
					page
						.locator( '.cbuild-width__option', {
							has: page.locator( '.cbuild-chip__text', {
								hasText: /^8″$/,
							} ),
						} )
						.click(),
				'cbuild%5B_width%5D=8in',
			],
			[
				'a deck',
				0,
				// Picked with its card in the middle of the screen, the stage scrolled away, as a phone's shopper picks.
				async () => {
					await page
						.getByRole( 'radio', {
							name: 'Choose Builder test deck 8.0',
						} )
						.evaluate( ( radio ) =>
							radio
								.closest( 'li' )
								?.scrollIntoView( { block: 'center' } )
						);
					await choose( page, 'Builder test deck 8.0' );
				},
				`cbuild%5Bdeck%5D=${ id( 'builder-test-deck-8-0' ) }`,
			],
			[
				'Next',
				0,
				() =>
					builder
						.getByRole( 'button', { name: 'Next: Trucks' } )
						.first()
						.click(),
				'cbuild%5Bstep%5D=trucks',
			],
			[
				'a truck',
				0,
				() => choose( page, 'Builder test trucks 139' ),
				`cbuild%5Btrucks%5D=${ id( 'builder-test-trucks-139' ) }`,
			],
		];
		const measured: string[] = [];
		for ( const [ name, most, press, shown ] of presses ) {
			const from = await pageNow( page );
			await press();
			await expect
				.poll( () => page.url(), { timeout: 10_000 } )
				.toContain( shown );
			await expect( builder ).toHaveAttribute( 'aria-busy', 'false' );
			// The answer drawn and its pictures painted.
			await page.waitForTimeout( 1000 );
			const { counted, said } = await shiftsSince( page, from );
			measured.push( `${ name }: ${ said }` );
			if ( 0 === most ) {
				expect
					.soft( counted, `${ name }'s layout shift: ${ said }` )
					.toBe( 0 );
			} else {
				expect
					.soft( counted, `${ name }'s layout shift: ${ said }` )
					.toBeLessThan( most );
			}
		}
		info.annotations.push( {
			type: 'layout shift, answers a second late',
			description: measured.join( '\n' ),
		} );
		// eslint-disable-next-line no-console
		console.log( measured.join( '\n' ) );
	} );

	// D-406 found the step's moment shifting with the answers held: Next pressed and its answer a second late, whether
	// the press asked for it or took the answer asked ahead (D-355) still on its way, as a shopper who knows what they
	// want presses Next at once. The deck step's width line, which no later step has, stayed until the answer took it,
	// and the cards moved up under the step's heading: 0.021 on a desktop and 0.040 on a phone (D-408). The bar's Back,
	// which comes in beside Next on the second step, moved Next and the total too, 0.001 and 0.004: its place is now
	// kept on the first step once the build has a part (#152, D-512).
	test( 'Next pressed with its answer a second late moves nothing of the step or the bar when the answer comes, asked at the press or ahead', async ( {
		page,
	}, info ) => {
		test.skip(
			! info.project.name.startsWith( 'chromium' ),
			'Only Chromium reports layout shift (the Layout Instability API).'
		);
		test.setTimeout( 60_000 );
		const builder = page.locator( '.cbuild' );
		const deck = id( 'builder-test-deck-8-0' );
		// A request for the trucks' step: the trucks' answer asks ahead for the wheels in its turn.
		const trucks = ( request: { url: () => string } ) => {
			const url = new URL( request.url() );
			return (
				url.pathname.endsWith( '/state' ) &&
				'trucks' === url.searchParams.get( 'cbuild[step]' )
			);
		};
		// The page opened on the deck step with a deck, Next asking at its press; and a deck picked, Next pressed as
		// soon as the deck's answer is drawn, while the answer asked ahead for it is held.
		const ways: [
			string,
			Record< string, string | number >,
			() => Promise< void >,
			number,
		][] = [
			[ 'at the press', { deck, step: 'deck' }, async () => {}, 1 ],
			[
				'asked ahead',
				{ _width: '8in', step: 'deck' },
				// Next pressed as soon as the deck's answer has asked ahead for it.
				async () => {
					const ahead = page.waitForRequest( trucks );
					await choose( page, 'Builder test deck 8.0' );
					await ahead;
				},
				0,
			],
		];
		const measured: string[] = [];
		for ( const [ way, link, before, asks ] of ways ) {
			await page.unrouteAll( { behavior: 'ignoreErrors' } );
			const held = await holdAnswers( page );
			await page.goto( linkTo( link ) );
			await expect( builder ).toHaveAttribute( 'data-ready', '' );
			await emojiDone( page );
			await themeHeader( page, 129 );
			await watchShifts( page );
			// The trucks asked for, at the press or ahead of it, and when each answer came.
			const asked: number[] = [];
			const came: number[] = [];
			const ask = ( request: { url: () => string } ) => {
				if ( trucks( request ) ) {
					asked.push( Date.now() );
				}
			};
			const answer = ( request: { url: () => string } ) => {
				if ( trucks( request ) ) {
					came.push( Date.now() );
				}
			};
			page.on( 'request', ask );
			page.on( 'requestfinished', answer );
			await before();
			const from = await pageNow( page );
			const pressed = Date.now();
			await builder
				.getByRole( 'button', { name: 'Next: Trucks' } )
				.first()
				.click();
			await expect
				.poll( () => page.url(), { timeout: 10_000 } )
				.toContain( 'cbuild%5Bstep%5D=trucks' );
			await held.drained();
			await expect( builder ).toHaveAttribute( 'aria-busy', 'false' );
			// The answer drawn and its pictures painted.
			await page.waitForTimeout( 1000 );
			page.off( 'request', ask );
			page.off( 'requestfinished', answer );
			expect(
				asked.filter( ( at ) => at >= pressed ).length,
				`${ way }: asked at the press`
			).toBe( asks );
			expect( asked.length, `${ way }: the trucks asked once` ).toBe( 1 );
			// Past the 500 ms after the press that CLS leaves out, or the press measures nothing.
			expect(
				( came[ 0 ] ?? 0 ) - pressed,
				`${ way }: the answer came late`
			).toBeGreaterThan( 500 );
			const { shifts, said } = await shiftsSince( page, from );
			measured.push( `${ way }: ${ said }` );
			// Every shift that moves a node of the builder's, the bar's included: Back's place is kept on the first step
			// (#152, D-512). A theme's footer the taller step moves down in the same frame is the page's own (D-406): a
			// builder node in that frame that kept its place moved nothing.
			const step = shifts
				.filter( ( shift ) =>
					shift.sources.some( ( source ) => {
						const [ name, move ] = source.split( ' ' );
						const [ was, now ] = ( move ?? '' ).split( '→' );
						return (
							( name ?? '' ).startsWith( 'cbuild' ) && was !== now
						);
					} )
				)
				.reduce( ( sum, shift ) => sum + shift.value, 0 );
			expect
				.soft( step, `${ way }: the builder's layout shift: ${ said }` )
				.toBe( 0 );
		}
		info.annotations.push( {
			type: 'layout shift, Next a second late',
			description: measured.join( '\n' ),
		} );
		// eslint-disable-next-line no-console
		console.log( measured.join( '\n' ) );
	} );
} );

// Issue #46, settled on 17 November 2026: the board has a top, drawn from the deck's own layer image, with the grip
// filled into it and a bolt head at each hole; the grip step shows it, and a button by the board turns it over.
// Spec: The layout. The stage holds the whole board at every width: beside the panel on a desktop, and in the short
// strip on top when the layout is stacked, a tablet's as a phone's, where the board is turned a quarter turn (D-280).
// The slow deck swap (M11, D-308; NN/g's response limits, Baymard's loading feedback, web.dev's intent preloading): the
// board's image is fetched while the pointer rests on a deck's card, a press asks at once, and on a slow network the
// old board stays whole, dimmed with a running line from 300 ms, until the new one can be drawn whole.
test.describe( 'The deck swap', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			! info.project.name.startsWith( 'chromium' ) ||
				info.project.name.includes( 'phone' ),
			'a pointer to rest, and the network slowed through Chromium'
		);
	} );

	const start = ( page: Page ) =>
		page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				step: 'deck',
				_width: '8.5in',
			} )
		);
	const card = ( page: Page ) =>
		page.locator( '.cbuild-cards > li' ).filter( {
			has: page.getByRole( 'radio', {
				name: 'Choose Builder test deck 8.5',
			} ),
		} );

	test( "fetches a deck's board image while the pointer rests on its card, and asks for a pressed pick at once", async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await start( page );
		await expect( page.locator( '.cbuild[data-ready]' ) ).toHaveCount( 1 );
		const layers = () =>
			page.evaluate( () =>
				performance
					.getEntriesByType( 'resource' )
					.filter( ( entry ) =>
						/\/creative-skateboard-builder\/layers\/|-layer[^/]*\.png/.test(
							entry.name
						)
					)
					.map( ( entry ) => entry.name )
			);
		const before = await layers();
		await card( page ).hover();
		await expect
			.poll( async () => ( await layers() ).length, {
				message: "the 8.5 deck's board image, before any pick",
			} )
			.toBeGreaterThan( before.length );
		await expect( page, 'pointing picks nothing' ).toHaveURL(
			new RegExp( `deck%5D=${ id( 'builder-test-deck-8-0' ) }(&|$)` )
		);

		await page.evaluate( () => {
			const w = window as unknown as { asked?: number; pressed?: number };
			document.addEventListener(
				'pointerdown',
				() => {
					w.pressed = performance.now();
				},
				true
			);
			new PerformanceObserver( ( list ) => {
				for ( const entry of list.getEntries() ) {
					if (
						/cbuild(\/|%2F)v1/i.test( entry.name ) &&
						undefined === w.asked
					) {
						w.asked = entry.startTime;
					}
				}
			} ).observe( { type: 'resource' } );
		} );
		// The card's radio covers it, as the shopper's press lands.
		await card( page ).getByRole( 'radio' ).click();
		await expect
			.poll( () =>
				page.evaluate( () => {
					const w = window as unknown as {
						asked?: number;
						pressed?: number;
					};
					return undefined === w.asked || undefined === w.pressed
						? null
						: w.asked - w.pressed;
				} )
			)
			.not.toBeNull();
		const waited = await page.evaluate( () => {
			const w = window as unknown as { asked: number; pressed: number };
			return w.asked - w.pressed;
		} );
		expect( waited, 'asked at once, not 150 ms after' ).toBeLessThan( 100 );
	} );

	// The interim audit of M11: nothing loops (SPEC-CONFIGURATOR: Motion; WCAG 2.2.2). The line that says a picture is on
	// its way runs four times at most, under five seconds, and the dimmed board goes on saying it after.
	// Where motion is calm the wait is said by the dim alone, a fade of 150 ms or less like every moment there (D-256):
	// the dim's 200 ms transition was caught under subtle on Firefox, whenever an answer took more than 300 ms.
	for ( const calm of [ 'reduced motion', 'subtle' ] as const ) {
		test( `under ${ calm } dims the board for a slow answer by a fade of 150 ms or less, with no running line`, async ( {
			page,
		} ) => {
			await page.emulateMedia( {
				reducedMotion:
					'reduced motion' === calm ? 'reduce' : 'no-preference',
			} );
			await start( page );
			const builder = page.locator( '.cbuild[data-ready]' );
			await expect( builder ).toHaveCount( 1 );
			if ( 'subtle' === calm ) {
				await builder.evaluate( ( root ) =>
					root.classList.add( 'cbuild--subtle' )
				);
			}
			let release: () => void = () => undefined;
			const held = new Promise< void >( ( done ) => ( release = done ) );
			await page.route(
				( url ) => url.pathname.endsWith( '/state' ),
				async ( route ) => {
					const answer = await route.fetch();
					await held;
					await route
						.fulfill( { response: answer } )
						.catch( () => null );
				}
			);
			await page
				.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
				.focus();
			await page.keyboard.press( 'Space' );
			const picture = page.locator( '#cbuild-picture' );
			await expect( picture ).toHaveAttribute( 'aria-busy', 'true', {
				timeout: 10_000,
			} );
			await expect( picture ).toHaveClass( /cbuild-picture--drawing/ );
			const moving = await page.evaluate( () =>
				document
					.getAnimations()
					.filter( ( one ) =>
						(
							one.effect as KeyframeEffect | null
						 )?.target?.closest?.( '#cbuild-picture' )
					)
					.map( ( one ) => ( {
						name:
							( one as CSSAnimation ).animationName ??
							( one as CSSTransition ).transitionProperty ??
							'',
						duration: Number(
							one.effect?.getComputedTiming().duration ?? 0
						),
					} ) )
					.filter( ( one ) => one.duration > 150 )
			);
			// The dim's own transition, read as set: it may have ended by the time the animations above are read.
			const dim = await picture
				.locator( '.cbuild-board' )
				.first()
				.evaluate( ( board ) =>
					Math.max(
						...getComputedStyle( board )
							.transitionDuration.split( ',' )
							.map( ( one ) => parseFloat( one ) * 1000 )
					)
				);
			release();
			expect(
				moving,
				'nothing in the picture runs past a 150 ms fade'
			).toEqual( [] );
			expect( dim, 'the dim itself' ).toBeLessThanOrEqual( 150 );
		} );
	}

	test( 'stops the line that says the board is drawing within five seconds, and keeps the board dimmed', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await start( page );
		await expect( page.locator( '.cbuild[data-ready]' ) ).toHaveCount( 1 );
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => ( release = done ) );
		await page.route(
			( url ) => url.pathname.endsWith( '/state' ),
			async ( route ) => {
				const answer = await route.fetch();
				await held;
				await route.fulfill( { response: answer } ).catch( () => null );
			}
		);
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
			.focus();
		await page.keyboard.press( 'Space' );
		const picture = page.locator( '#cbuild-picture' );
		await expect( picture ).toHaveAttribute( 'aria-busy', 'true', {
			timeout: 10_000,
		} );
		const running = () =>
			page.evaluate(
				() =>
					document
						.getAnimations()
						.filter(
							( one ) =>
								'cbuild-drawing' ===
									( one as CSSAnimation ).animationName &&
								'running' === one.playState
						).length
			);
		expect( await running(), 'the line runs at first' ).toBe( 1 );
		await page.waitForTimeout( 5000 );
		expect( await running(), 'the line has stopped' ).toBe( 0 );
		await expect( picture ).toHaveAttribute( 'aria-busy', 'true' );
		release();
	} );

	test( 'on Slow 4G keeps the old board whole, says it is drawing from 300 ms, and draws the new one whole', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await start( page );
		await expect( page.locator( '.cbuild[data-ready]' ) ).toHaveCount( 1 );
		const cdp = await page.context().newCDPSession( page );
		// Chrome DevTools' Slow 4G.
		await cdp.send( 'Network.emulateNetworkConditions', {
			offline: false,
			latency: 562.5,
			downloadThroughput: ( 1.4 * 1024 * 1024 ) / 8,
			uploadThroughput: ( 675 * 1024 ) / 8,
		} );
		await page.evaluate( () => {
			const w = window as unknown as {
				seen: Array< {
					at: number;
					drawing: boolean;
					complete: boolean;
					src: string;
				} >;
				t0: number;
			};
			w.seen = [];
			const picture = document.querySelector( '#cbuild-picture' )!;
			const note = () => {
				const image = picture.querySelector< HTMLImageElement >(
					'.cbuild-board:not(.cbuild-board--top) .cbuild-board__layer--deck img'
				);
				w.seen.push( {
					at: performance.now() - w.t0,
					drawing: 'true' === picture.getAttribute( 'aria-busy' ),
					complete:
						!! image && image.complete && image.naturalWidth > 0,
					src: image?.getAttribute( 'src' ) ?? '',
				} );
			};
			new MutationObserver( note ).observe( picture, {
				attributes: true,
				subtree: true,
				attributeFilter: [ 'aria-busy', 'src', 'srcset', 'class' ],
			} );
			document.addEventListener(
				'pointerdown',
				() => {
					w.t0 = performance.now();
				},
				true
			);
		} );
		const was = await page
			.locator(
				'#cbuild-picture .cbuild-board:not(.cbuild-board--top) .cbuild-board__layer--deck img'
			)
			.evaluate(
				( image: HTMLImageElement ) => image.getAttribute( 'src' ) ?? ''
			);
		// The keyboard's way in, so no resting pointer fetched the image first.
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
			.focus();
		await page.evaluate( () => {
			( window as unknown as { t0: number } ).t0 = performance.now();
		} );
		await page.keyboard.press( 'Space' );
		await expect(
			page.locator( '#cbuild-picture' ),
			'says it is drawing'
		).toHaveAttribute( 'aria-busy', 'true', { timeout: 10_000 } );
		await expect(
			page.locator( '#cbuild-picture' ),
			'until the board is drawn'
		).toHaveAttribute( 'aria-busy', 'false', { timeout: 20_000 } );
		const seen = await page.evaluate(
			() =>
				(
					window as unknown as {
						seen: Array< {
							at: number;
							drawing: boolean;
							complete: boolean;
							src: string;
						} >;
					}
				 ).seen
		);
		const first = seen.find( ( entry ) => entry.drawing );
		expect(
			first?.at,
			'not before 300 ms: a quick answer never flickers'
		).toBeGreaterThanOrEqual( 290 );
		const swapped = seen.find(
			( entry ) => '' !== entry.src && entry.src !== was
		);
		expect( swapped, 'the new deck drawn' ).toBeDefined();
		expect(
			swapped?.complete,
			"whole when it takes the old one's place"
		).toBe( true );
		await cdp.send( 'Network.emulateNetworkConditions', {
			offline: false,
			latency: 0,
			downloadThroughput: -1,
			uploadThroughput: -1,
		} );
	} );
} );

// Cowork's review of 1.1.0, F23 (#121; D-357): the board a deck's card carries, which pointing at the card fetched
// (D-308), is drawn at the press, once decoded, while the route is still asked; the rest of the picture keeps the cue
// until the answer, which confirms the deck, or refuses it and puts the board back with the reason. Every engine, as a
// press is every shopper's; the route is held back with `page.route`, which each of them has.
test.describe( 'The deck drawn at its press', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			'no-js' === info.project.name,
			'The store draws the board; without it the page is the server’s.'
		);
	} );

	const deckImage =
		'.cbuild-board:not(.cbuild-board--top) .cbuild-board__layer--deck img';

	/**
	 * The deck's image on the board as drawn: its layer's file, and whether what the browser draws of it is whole.
	 *
	 * @param page The page.
	 */
	const drawn = ( page: Page ) =>
		page
			.locator( `#cbuild-picture ${ deckImage }` )
			.evaluate( ( image: HTMLImageElement ) => ( {
				// The layer's own file, which names the part; the browser draws one of its renditions.
				file:
					( image.getAttribute( 'src' ) ?? '' ).split( '/' ).pop() ??
					'',
				whole: image.complete && image.naturalWidth > 0,
			} ) );

	/**
	 * The board at 8.5 in with the 8.0 deck and the 139 trucks on it, the route held until the test lets it answer,
	 * asked as the test would have it asked, and the 8.5 deck pressed.
	 *
	 * @param page   The page.
	 * @param ask    The address the route is asked.
	 * @param motion Whether motion is reduced.
	 */
	async function pressed(
		page: Page,
		ask: ( url: URL ) => URL,
		motion: 'reduce' | 'no-preference'
	): Promise< { was: string; release: () => void } > {
		await page.emulateMedia( { reducedMotion: motion } );
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'deck',
				_width: '8.5in',
			} )
		);
		await expect( page.locator( '.cbuild[data-ready]' ) ).toHaveCount( 1 );
		const { file: was } = await drawn( page );
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => ( release = done ) );
		await page.route(
			( url ) => url.pathname.endsWith( '/state' ),
			async ( route ) => {
				const answer = await route
					.fetch( {
						url: ask( new URL( route.request().url() ) ).toString(),
					} )
					.catch( () => null );
				await held;
				if ( answer ) {
					await route
						.fulfill( { response: answer } )
						.catch( () => null );
				}
			}
		);
		await page
			.getByRole( 'radio', { name: 'Choose Builder test deck 8.5' } )
			.click();
		return { was, release };
	}

	test( "draws the pressed deck's board, whole, before its late answer, with the cue on the rest", async ( {
		page,
	} ) => {
		const { was, release } = await pressed(
			page,
			( url ) => url,
			'no-preference'
		);
		await expect
			.poll( async () => ( await drawn( page ) ).file, {
				message: 'the 8.5 deck, the answer still held',
				timeout: 5_000,
			} )
			.toContain( 'deck-8-5' );
		expect( was ).toContain( 'deck-8-0' );
		expect( ( await drawn( page ) ).whole, 'whole when drawn' ).toBe(
			true
		);
		// The cue stays on the rest of the picture, from 300 ms on, until the answer: the trucks dimmed, the deck not.
		const picture = page.locator( '#cbuild-picture' );
		await expect( picture ).toHaveAttribute( 'aria-busy', 'true' );
		const opacity = ( selector: string ) =>
			picture
				.locator(
					`.cbuild-board:not(.cbuild-board--top) > ${ selector }`
				)
				.first()
				// As drawn: the layer's own opacity times the board's and the picture's around it.
				.evaluate( ( layer ) => {
					let seen = 1;
					for (
						let at: Element | null = layer;
						at && 'cbuild-picture' !== at.id;
						at = at.parentElement
					) {
						seen *= Number( getComputedStyle( at ).opacity );
					}
					return seen;
				} );
		await expect
			.poll( () => opacity( '.cbuild-board__layer--trucks' ) )
			.toBeLessThan( 1 );
		await expect
			.poll( () => opacity( '.cbuild-board__layer--deck' ) )
			.toBe( 1 );
		release();
		await expect( picture ).toHaveAttribute( 'aria-busy', 'false' );
		expect( ( await drawn( page ) ).file ).toContain( 'deck-8-5' );
		await expect( page ).toHaveURL(
			new RegExp( `deck%5D=${ id( 'builder-test-deck-8-5' ) }(&|$)` )
		);
	} );

	// The route refuses a deck as it refuses any part it does not offer: asked for a truck as the deck, it answers with
	// the error summary and no deck, as for one sold out since the page was drawn.
	test( 'puts the board back when the answer refuses the deck, and says why', async ( {
		page,
	} ) => {
		const { release } = await pressed(
			page,
			( url ) => {
				url.searchParams.set(
					'cbuild[deck]',
					String( id( 'builder-test-trucks-139' ) )
				);
				return url;
			},
			'no-preference'
		);
		await expect
			.poll( async () => ( await drawn( page ) ).file, {
				timeout: 5_000,
			} )
			.toContain( 'deck-8-5' );
		release();
		await expect( page.locator( '.cbuild-errors' ) ).toContainText(
			'Deck: the part chosen is not one this kit offers here.'
		);
		await expect(
			page.locator( `#cbuild-picture ${ deckImage }` ),
			'the pressed deck is no longer drawn'
		).toHaveCount( 0 );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
	} );

	test( "under reduced motion draws the pressed deck's board at the press by a fade of 150 ms or less", async ( {
		page,
	} ) => {
		await page.addInitScript( () => {
			const w = window as unknown as {
				swaps: { duration: number; properties: string[] }[];
			};
			w.swaps = [];
			const animate = Element.prototype.animate;
			Element.prototype.animate = function ( frames, options ) {
				const animation = animate.call( this, frames, options );
				queueMicrotask( () => {
					if ( 'cbuild-swapped' !== animation.id ) {
						return;
					}
					const keyframes = (
						animation.effect as KeyframeEffect
					 ).getKeyframes() as Keyframe[];
					w.swaps.push( {
						duration: Number(
							animation.effect?.getComputedTiming().duration ?? 0
						),
						properties: [
							...new Set(
								keyframes.flatMap( ( frame ) =>
									Object.keys( frame ).filter(
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
					} );
				} );
				return animation;
			};
		} );
		const { release } = await pressed( page, ( url ) => url, 'reduce' );
		await expect
			.poll( async () => ( await drawn( page ) ).file, {
				timeout: 5_000,
			} )
			.toContain( 'deck-8-5' );
		await expect
			.poll( () =>
				page.evaluate(
					() =>
						( window as unknown as { swaps: unknown[] } ).swaps
							.length
				)
			)
			.toBeGreaterThan( 0 );
		const swaps = await page.evaluate(
			() =>
				(
					window as unknown as {
						swaps: { duration: number; properties: string[] }[];
					}
				 ).swaps
		);
		release();
		for ( const swap of swaps ) {
			expect( swap.duration ).toBeLessThanOrEqual( 150 );
			expect( swap.properties ).toEqual( [ 'opacity' ] );
		}
	} );
} );

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
					url: baseURL || SITE,
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
			// The stage and the board measured together, once the picture has settled: measured apart, straight after
			// the answer, the board was once read before the stage's new height (D-288). One that never fits still fails.
			await expect
				.poll( () =>
					page.evaluate( () => {
						const stage = document
							.querySelector( '.cbuild__stage' )
							?.getBoundingClientRect();
						const board = [
							...document.querySelectorAll< HTMLElement >(
								'.cbuild__stage .cbuild-board:not(.cbuild-board--bar)'
							),
						]
							.find( ( one ) => one.offsetParent !== null )
							?.getBoundingClientRect();
						if ( ! stage || ! board ) {
							return 'no stage or no board';
						}
						const over = {
							top: stage.top - board.top,
							foot: board.bottom - stage.bottom,
							start: stage.left - board.left,
							end: board.right - stage.right,
						};
						return Object.entries( over )
							.filter( ( [ , by ] ) => by > 1 )
							.map( ( [ edge, by ] ) => `${ edge } by ${ by }` );
					} )
				)
				.toEqual( [] );
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
	// The two buttons by the board, a side each (F28, #128).
	const topSide = ( page: Page ) =>
		picture( page ).getByRole( 'button', { name: 'Top', exact: true } );
	const underSide = ( page: Page ) =>
		picture( page ).getByRole( 'button', {
			name: 'Underside',
			exact: true,
		} );

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

	// Cowork's review of 1.1.0, F27 (#127): "Each step opens on its part's side: deck, trucks and wheels underneath;
	// hardware and grip on top; review underneath." The hardware step showed the underside, where no bolt head is drawn.
	// By each step's link, so the page is the server's with the script or without.
	test( "each step opens on the side its part is seen on, the hardware's bolt heads on the top", async ( {
		page,
	} ) => {
		const picks = {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-54' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1-25' ),
			grip: id( 'builder-test-grip' ),
		};
		const sides: Record< string, string > = {};
		for ( const step of [
			'deck',
			'trucks',
			'wheels',
			'risers',
			'bearings',
			'hardware',
			'grip',
			'review',
		] ) {
			await page.goto( linkTo( { ...picks, step } ) );
			// One side drawn at a time.
			await expect(
				picture( page ).locator( '.cbuild-board:visible' )
			).toHaveCount( 1 );
			sides[ step ] = ( await top( page ).isVisible() )
				? 'top'
				: 'underside';
			if ( 'hardware' === step ) {
				await expect(
					top( page ).locator(
						'.cbuild-board__layer--hardware:visible'
					),
					"the shopper's bolts, a head at each hole"
				).toHaveCount( 8 );
				await expect( description( page ) ).toContainText(
					'Seen from the top.'
				);
			}
		}
		expect( sides ).toEqual( {
			deck: 'underside',
			trucks: 'underside',
			wheels: 'underside',
			risers: 'underside',
			bearings: 'underside',
			hardware: 'top',
			grip: 'top',
			review: 'underside',
		} );
	} );

	// Cowork's 1.0.0 review, F18 (#103): a grip's image is its whole sheet, drawn once at its real size, centred on the deck
	// and cut by its outline. A printed sheet, a plain one and a 1.0.x swatch, on an 8 in deck and an 8.75 in one.
	test( 'a grip sheet is drawn once at its size centred on the deck, and a 1.0.x swatch is repeated as before', async ( {
		page,
	} ) => {
		const grip = id( 'builder-test-grip' );
		const had = wp( `post meta get ${ grip } _cbuild_layer` );
		const sheets: Record< string, string > = {};
		for ( const name of [ 'grip-sheet-printed', 'grip-sheet-plain' ] ) {
			runPhp(
				`require_once ABSPATH . 'wp-admin/includes/image.php'; $u = wp_upload_bits( '${ name }-e2e.png', null, (string) file_get_contents( WP_PLUGIN_DIR . '/creative-skateboard-builder/tests/e2e/fixtures/layers/${ name }.png' ) ); $a = wp_insert_attachment( array( 'post_mime_type' => 'image/png', 'post_title' => '${ name }', 'post_status' => 'inherit' ), $u['file'] ); wp_update_attachment_metadata( $a, wp_generate_attachment_metadata( $a, $u['file'] ) ); update_option( 'cbuild_e2e_${ name.replace(
					/-/g,
					'_'
				) }', $a );`
			);
			sheets[ name ] = wp(
				`option get cbuild_e2e_${ name.replace( /-/g, '_' ) }`
			);
		}
		const layer = ( attachment: string ) =>
			runPhp(
				`$p = wc_get_product( ${ grip } ); $p->update_meta_data( '_cbuild_layer', '${ attachment }' ); $cbuild_index( $p );`
			);
		const drawn = async ( deck: string ) => {
			await page.goto(
				linkTo( {
					deck: id( deck ),
					trucks: id(
						'builder-test-deck-8-0' === deck
							? 'builder-test-trucks-139'
							: 'builder-test-trucks-149'
					),
					wheels: id( 'builder-test-wheels-54' ),
					bearings: id( 'builder-test-bearings' ),
					hardware: id( 'builder-test-bolts-1-25' ),
					grip,
					step: 'grip',
				} )
			);
			const on = top( page ).locator( '.cbuild-board__layer--grip' );
			await expect( on ).toBeVisible();
			return on.evaluate( ( span ) => {
				const style = getComputedStyle( span );
				const deckLayer = span.parentElement?.querySelector(
					'.cbuild-board__layer--deck'
				);
				const box = span.getBoundingClientRect();
				const deckBox = deckLayer?.getBoundingClientRect();
				return {
					size: style.backgroundSize,
					repeat: style.backgroundRepeat,
					position: style.backgroundPosition,
					box: deckBox
						? [
								Math.round( box.width - deckBox.width ),
								Math.round( box.height - deckBox.height ),
							]
						: null,
					below: [ ...( span.parentElement?.children ?? [] ) ]
						.slice(
							[
								...( span.parentElement?.children ?? [] ),
							].indexOf( span )
						)
						.some( ( after ) =>
							after.matches( '.cbuild-board__layer--hardware' )
						),
				};
			} );
		};
		try {
			setSpecs( 'builder-test-deck-8-5', { deck_width: '8.7500' } );
			for ( const name of [ 'grip-sheet-printed', 'grip-sheet-plain' ] ) {
				layer( sheets[ name ] ?? '' );
				// The usual 9 by 33 in sheet: on the 8 by 31.5 in deck, and on the 8.75 by 32 in one, where it is narrower
				// than the deck and the maple shows at its edges.
				for ( const [ deck, size ] of [
					[ 'builder-test-deck-8-0', '112.5% 104.762%' ],
					[ 'builder-test-deck-8-5', '102.857% 103.125%' ],
				] as const ) {
					const seen = await drawn( deck );
					// As numbers to three places: WebKit writes the percentage it computed to six (104.761902%).
					expect(
						seen.size
							.split( ' ' )
							.map( ( part ) => parseFloat( part ).toFixed( 3 ) ),
						`${ name } on ${ deck }`
					).toEqual(
						size
							.split( ' ' )
							.map( ( part ) => parseFloat( part ).toFixed( 3 ) )
					);
					expect( seen.repeat, 'drawn once' ).toBe( 'no-repeat' );
					expect( seen.position, 'centred' ).toBe( '50% 50%' );
					expect(
						seen.box,
						"the deck's box, cut by its outline"
					).toEqual( [ 0, 0 ] );
					expect( seen.below, 'under the bolt heads' ).toBe( true );
				}
			}
			layer( had );
			const swatch = await drawn( 'builder-test-deck-8-0' );
			expect( swatch.repeat, 'a 1.0.x swatch nose to tail' ).toBe(
				'repeat-x'
			);
			expect( swatch.size ).toBe( 'auto 100%' );
		} finally {
			layer( had );
			setSpecs( 'builder-test-deck-8-5', { deck_width: '8.5000' } );
			for ( const attachment of Object.values( sheets ) ) {
				wp( `post delete ${ attachment } --force` );
			}
		}
	} );

	// F27 (#127): "A turn the shopper makes lasts until they change step." Pressed on the wheels, the next step showed
	// the top. F28 (#128): "a two-option control, Underside and Top", the side shown marked to the eye and to
	// assistive tech.
	test( 'turns with Underside and Top from the keyboard, says the side, shows it in the bar, and keeps it until the step changes', async ( {
		page,
	}, info ) => {
		test.skip(
			info.project.name === 'no-js',
			"The buttons are the script's: without them the step's own side is shown."
		);
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		await expect(
			picture( page ).getByRole( 'group', { name: 'Your board' } )
		).toBeVisible();
		/**
		 * Which side the buttons say is shown, to assistive tech, and whether each looks it.
		 */
		const said = async () => [
			await underSide( page ).getAttribute( 'aria-pressed' ),
			await topSide( page ).getAttribute( 'aria-pressed' ),
		];
		await expect.poll( said ).toEqual( [ 'true', 'false' ] );
		// The auditor's M12 minor 7: axe on the group with each side pressed (D-369).
		await axe(
			page,
			'the two sides, the underside pressed',
			'#cbuild-picture'
		);
		await topSide( page ).focus();
		await page.keyboard.press( 'Enter' );
		await expect.poll( said ).toEqual( [ 'false', 'true' ] );
		await expect(
			topSide( page ),
			'the shopper keeps their place'
		).toBeFocused();
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

		// A pick is answered on the same step: the shopper's side stays.
		await choose( page, 'Builder test wheels 54' );
		// Answered once the top draws the wheels picked at its edges.
		await expect(
			top( page ).locator( '.cbuild-board__layer--wheels' )
		).toHaveCount( 4 );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect
			.poll( said, 'the step keeps the turn' )
			.toEqual( [ 'false', 'true' ] );
		await expect( top( page ) ).toBeVisible();

		await page.getByRole( 'button', { name: 'Next: Bearings' } ).click();
		await expect( heading( page ) ).toHaveText( /: Choose your bearings$/ );
		// The step has landed when focus is on its heading and the builder is no longer busy: pressed before, focus
		// went on to the heading under the key (2 runs in 10 before this wait).
		await expect( heading( page ) ).toBeFocused();
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect
			.poll(
				said,
				"the bearings step opens underneath: the turn was the last step's"
			)
			.toEqual( [ 'true', 'false' ] );
		await expect( under( page ) ).toBeVisible();
		await expect( description( page ) ).toContainText(
			'Seen from underneath.'
		);

		await choose( page, 'Builder test bearings' );
		await page.getByRole( 'button', { name: 'Next: Hardware' } ).click();
		await expect( heading( page ) ).toHaveText( /: Choose your hardware$/ );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		await expect
			.poll( said, 'the hardware step opens on its own side, the top' )
			.toEqual( [ 'false', 'true' ] );
		await expect( top( page ) ).toBeVisible();
		await underSide( page ).focus();
		await page.keyboard.press( 'Space' );
		await expect.poll( said ).toEqual( [ 'true', 'false' ] );
		await expect( under( page ) ).toBeVisible();
		await expect( description( page ) ).toContainText(
			'Seen from underneath.'
		);
		// Pressed again, the side shown stays.
		await page.keyboard.press( 'Space' );
		await expect.poll( said ).toEqual( [ 'true', 'false' ] );
		await expect( under( page ) ).toBeVisible();
	} );

	// F28 (#128): "the turn button reads Show the top whether or not the top is showing, and looks the same in both
	// states". The side shown is filled, bold and ticked; the other plain. Each at least 24 by 24 px (WCAG 2.5.8) and
	// ringed in focus.
	test( 'marks the side shown on its button by more than colour, each a target of 24 px or more with a focus ring', async ( {
		page,
		browserName,
	}, info ) => {
		test.skip(
			info.project.name === 'no-js',
			"The buttons are the script's: without them the step's own side is shown."
		);
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				step: 'trucks',
			} )
		);
		/**
		 * How a button is drawn: its fill, its weight, its tick and its size.
		 *
		 * @param button The button.
		 */
		const look = ( button: Locator ) =>
			button.evaluate( ( shown ) => {
				const style = getComputedStyle( shown );
				const tick = getComputedStyle( shown, '::before' );
				const box = shown.getBoundingClientRect();
				return {
					filled: 'rgba(0, 0, 0, 0)' !== style.backgroundColor,
					bold: parseInt( style.fontWeight, 10 ) >= 600,
					ticked:
						'none' !== tick.content && 0 < parseFloat( tick.width ),
					big: box.width >= 24 && box.height >= 24,
				};
			} );
		const pressed = { filled: true, bold: true, ticked: true, big: true };
		const plain = { filled: false, bold: false, ticked: false, big: true };
		await expect.poll( () => look( underSide( page ) ) ).toEqual( pressed );
		await expect.poll( () => look( topSide( page ) ) ).toEqual( plain );
		await topSide( page ).click();
		await expect.poll( () => look( topSide( page ) ) ).toEqual( pressed );
		await expect.poll( () => look( underSide( page ) ) ).toEqual( plain );
		await axe( page, 'the two sides, the top pressed', '#cbuild-picture' );
		// WebKit on macOS moves to a button with Option and Tab, as a Safari user does.
		const tab = browserName === 'webkit' ? 'Alt+Tab' : 'Tab';
		for ( const side of [ underSide( page ), topSide( page ) ] ) {
			await side.focus();
			await page.keyboard.press( `Shift+${ tab }` );
			await page.keyboard.press( tab );
			await expect( side ).toBeFocused();
			await expect( side ).toHaveCSS( 'outline-style', 'solid' );
		}
	} );

	// Cowork's review of 1.0.0, F17 (#102): on a phone the board lay across the stage's whole width, and the button
	// that turns it sat over its tail. The mockup has the button at the stage's foot, under the board. Found by its
	// class, so the French and right-to-left projects, whose words differ, measure it too.
	for ( const [ width, height, at ] of [
		[ 390, 844, 'wheels' ],
		[ 820, 1180, 'wheels' ],
		[ 1280, 900, 'wheels' ],
		// The whole board, as the review's baselines showed the button over its tail.
		[ 390, 844, 'review' ],
		[ 820, 1180, 'review' ],
		[ 1280, 900, 'review' ],
	] as const ) {
		test( `keeps the button that turns the board clear of either side of it at ${ width } by ${ height }, on the ${ at }`, async ( {
			page,
		}, info ) => {
			test.skip(
				info.project.name === 'no-js',
				"The button is the script's: without it there is nothing over the board."
			);
			await page.setViewportSize( { width, height } );
			if ( 'review' === at ) {
				await page.goto( fixture().page );
				// With the script a ready build opens in place, with no page load.
				await page
					.locator( '.cbuild-presets' )
					.getByRole( 'button', { name: 'Start from Street' } )
					.click();
				await expect( heading( page ) ).toHaveText(
					/: Check your build$/
				);
			} else {
				await page.goto(
					linkTo( {
						deck: id( 'builder-test-deck-8-0' ),
						trucks: id( 'builder-test-trucks-139' ),
						step: 'wheels',
					} )
				);
			}
			const turn = picture( page ).locator( '.cbuild-picture__sides' );
			await expect( turn ).toBeVisible();
			/**
			 * The drawn layers of the side shown that the button's box crosses, each with the overlap.
			 *
			 * @param shown The side's board.
			 */
			const covered = ( shown: Locator ) =>
				shown.evaluate( ( board ) => {
					const button = document
						.querySelector(
							'#cbuild-picture .cbuild-picture__sides'
						)
						?.getBoundingClientRect();
					if ( ! button ) {
						return [ 'no button' ];
					}
					return [
						...board.querySelectorAll< HTMLElement >(
							'.cbuild-board__layer'
						),
					]
						.map( ( layer ) => {
							// A turned board's layers measure as the boxes they fill on the screen.
							const box = layer.getBoundingClientRect();
							const across =
								Math.min( box.right, button.right ) -
								Math.max( box.left, button.left );
							const down =
								Math.min( box.bottom, button.bottom ) -
								Math.max( box.top, button.top );
							return box.width > 0 && across > 0.5 && down > 0.5
								? `${ layer.className } by ${ Math.round(
										across
									) } by ${ Math.round( down ) }`
								: '';
						} )
						.filter( Boolean );
				} );
			await expect( under( page ) ).toBeVisible();
			await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
				'aria-busy',
				'false'
			);
			await expect
				.poll( () => covered( under( page ) ), 'the underside' )
				.toEqual( [] );
			const box = ( await turn.boundingBox() )!;
			// Where it is on the stage, not the screen or the page: the click may scroll the page, and on a phone the
			// stage is sticky.
			const onPage = () =>
				turn.evaluate(
					( button ) =>
						button.getBoundingClientRect().top -
						( document
							.querySelector( '#cbuild-picture' )
							?.getBoundingClientRect().top ?? 0 )
				);
			const before = await onPage();
			// Below the board's whole box, not only clear of what is drawn: a longer deck than the fixture's fills it.
			const board = ( await under( page ).boundingBox() )!;
			expect(
				box.y,
				'the button sits under the board'
			).toBeGreaterThanOrEqual( board.y + board.height - 1 );
			expect( box.height ).toBeGreaterThanOrEqual( 44 );
			expect( box.width ).toBeGreaterThanOrEqual( 44 );

			await topSide( page ).click();
			await expect( topSide( page ) ).toHaveAttribute(
				'aria-pressed',
				'true'
			);
			await expect( top( page ) ).toBeVisible();
			// Measured once the turn's motion has settled, where the top rests.
			await page.waitForFunction(
				() =>
					document
						.querySelector( '#cbuild-picture' )
						?.getAnimations( { subtree: true } )
						.every( ( one ) => 'running' !== one.playState ) ?? true
			);
			await expect
				.poll( () => covered( top( page ) ), 'the top' )
				.toEqual( [] );
			const turned = ( await turn.boundingBox() )!;
			expect( turned.height ).toBeGreaterThanOrEqual( 44 );
			expect(
				Math.abs( ( await onPage() ) - before ),
				'the button stays put on the stage as the board turns'
			).toBeLessThan( 1 );
		} );
	}

	// A phone held sideways: the stage is about 133 px high, too short for the heading, the side line, the board and
	// the button stacked. The button goes up beside the heading, so the board keeps a row of its own, drawn, and
	// clear of the button (M11's walkthrough fix had left it no height at all).
	for ( const theme of [ 'twentytwentyfive', 'kadence', 'storefront' ] ) {
		test( `draws the board clear of the button on a phone held sideways on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				info.project.name === 'no-js',
				"The button is the script's: without it there is nothing to turn."
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize( { width: 844, height: 390 } );
			await page.goto(
				linkTo( {
					deck: id( 'builder-test-deck-8-0' ),
					step: 'trucks',
				} )
			);
			const turn = picture( page ).locator( '.cbuild-picture__sides' );
			await expect( turn ).toBeVisible();
			await expect
				.poll( () =>
					picture( page ).evaluate( ( figure ) => {
						if (
							figure
								.getAnimations( { subtree: true } )
								.some( ( one ) => 'running' === one.playState )
						) {
							return 'moving';
						}
						const board = figure
							.querySelector(
								'.cbuild-board:not(.cbuild-board--top) .cbuild-board__image'
							)
							?.getBoundingClientRect();
						const button = figure
							.querySelector( '.cbuild-picture__sides' )
							?.getBoundingClientRect();
						if ( ! board || ! button ) {
							return 'missing';
						}
						if ( board.width < 80 || board.height < 20 ) {
							return `the board is ${ Math.round( board.width ) } by ${ Math.round( board.height ) }`;
						}
						const apart =
							board.right <= button.left ||
							button.right <= board.left ||
							board.bottom <= button.top ||
							button.bottom <= board.top;
						return apart ? 'drawn, clear' : 'under the button';
					} )
				)
				.toBe( 'drawn, clear' );
		} );
	}

	// Cowork's 1.1.0 review, F21 (#119): held sideways (750 by 342 on Kadence) the stage was a strip 116 px high, the
	// board 47 by 13 px, and the heading's box 1 px high, so the side line was drawn over it. The board is to be no
	// smaller than on a 320 px phone held upright, 261 by 69 px, and no two of the stage's words may overlap.
	for ( const theme of [ 'twentytwentyfive', 'kadence', 'storefront' ] ) {
		for ( const [ width, height ] of [
			[ 750, 342 ],
			[ 844, 390 ],
		] as const ) {
			test( `draws the board at least 261 by 69 with no words over each other, held sideways at ${ width } by ${ height } on ${ theme }`, async ( {
				page,
				context,
				baseURL,
			} ) => {
				await context.addCookies( [
					{
						name: 'cbuild_e2e_theme',
						value: theme,
						url: baseURL || SITE,
					},
				] );
				await page.setViewportSize( { width, height } );
				await page.goto(
					linkTo( {
						deck: id( 'builder-test-deck-8-0' ),
						step: 'trucks',
					} )
				);
				await expect( under( page ) ).toBeVisible();
				/**
				 * What is wrong with the stage as it rests: the board shown smaller than 261 by 69, any of its words
				 * drawn over another or over the board, or any of them past the stage's edge, where it is cut off.
				 *
				 * @param side The board shown, the underside or the top.
				 */
				const wrong = ( side: string ) =>
					picture( page ).evaluate( ( figure, shownSide ) => {
						if (
							figure
								.getAnimations( { subtree: true } )
								.some( ( one ) => 'running' === one.playState )
						) {
							return 'moving';
						}
						const box = figure
							.querySelector( shownSide )
							?.getBoundingClientRect();
						if ( ! box ) {
							return 'no board';
						}
						const long = Math.max( box.width, box.height );
						const short = Math.min( box.width, box.height );
						const said: string[] = [];
						if ( long < 261 - 0.5 || short < 69 - 0.5 ) {
							said.push(
								`the board is ${ Math.round( box.width ) } by ${ Math.round( box.height ) }`
							);
						}
						// The stage's words as they are drawn: the heading's, the side line's and the button's
						// lines, from the text itself, so a heading whose box is 1 px high still shows its words
						// where they are. Each line is as high as its line-height, about its middle: a font's
						// ascent and descent reach past it (Twenty Twenty-Five's heading by 3 px) with no glyph
						// there.
						const words: { name: string; box: DOMRect }[] = [];
						for ( const [ name, selector ] of [
							[ 'the heading', '.cbuild-picture__heading' ],
							[ 'the side line', '.cbuild-picture__side' ],
							[ 'the button', '.cbuild-picture__sides' ],
						] as const ) {
							const shown = figure.querySelector( selector );
							if (
								! shown ||
								0 === shown.getClientRects().length ||
								'hidden' ===
									getComputedStyle( shown ).visibility
							) {
								continue;
							}
							const range = document.createRange();
							range.selectNodeContents( shown );
							const high = parseFloat(
								getComputedStyle( shown ).lineHeight
							);
							for ( const line of range.getClientRects() ) {
								if ( line.width > 0 && line.height > 0 ) {
									const lined =
										high > 0 && high < line.height
											? high
											: line.height;
									words.push( {
										name,
										box: new DOMRect(
											line.left,
											line.top +
												( line.height - lined ) / 2,
											line.width,
											lined
										),
									} );
								}
							}
						}
						words.push( { name: 'the board', box } );
						// The stage cuts off what runs past its edges: each word, the button's whole box and the
						// board inside it.
						const stage = figure
							.closest( '.cbuild__stage' )
							?.getBoundingClientRect();
						const button = figure
							.querySelector( '.cbuild-picture__sides' )
							?.getBoundingClientRect();
						for ( const one of [
							...words,
							...( button && button.height > 0
								? [ { name: 'the button', box: button } ]
								: [] ),
						] ) {
							if (
								stage &&
								( one.box.top < stage.top - 0.5 ||
									one.box.bottom > stage.bottom + 0.5 ||
									one.box.left < stage.left - 0.5 ||
									one.box.right > stage.right + 0.5 )
							) {
								said.push( `${ one.name } past the stage` );
							}
						}
						const meet = ( a: DOMRect, b: DOMRect ) =>
							Math.min( a.right, b.right ) -
								Math.max( a.left, b.left ) >
								0.5 &&
							Math.min( a.bottom, b.bottom ) -
								Math.max( a.top, b.top ) >
								0.5;
						words.forEach( ( one, at ) => {
							for ( const other of words.slice( at + 1 ) ) {
								if (
									one.name !== other.name &&
									meet( one.box, other.box )
								) {
									said.push(
										`${ one.name } over ${ other.name }`
									);
								}
							}
						} );
						return said.length
							? [ ...new Set( said ) ].join( '; ' )
							: 'whole, clear';
					}, side );
				await expect
					.poll( () =>
						wrong( '.cbuild-board:not(.cbuild-board--top)' )
					)
					.toBe( 'whole, clear' );
				// Turned over, the button says "Show the underside", the longer name.
				const turn = picture( page ).locator(
					'.cbuild-picture__sides'
				);
				if ( ! ( await turn.isVisible() ) ) {
					return;
				}
				await topSide( page ).click();
				await expect( topSide( page ) ).toHaveAttribute(
					'aria-pressed',
					'true'
				);
				await expect
					.poll( () => wrong( '.cbuild-board--top' ) )
					.toBe( 'whole, clear' );
			} );
		}
	}

	// Milestone 11's walkthrough: on Kadence at 390 px the button lay about 5 px over the board's lower edge, where the
	// theme's taller heading left the board's row shorter. Each side's whole box, on the themes with a heading of their
	// own, held and stacked.
	for ( const theme of [ 'kadence', 'storefront' ] ) {
		for ( const [ width, height ] of [
			[ 390, 844 ],
			[ 820, 1180 ],
		] as const ) {
			test( `keeps the button under either side of the board on ${ theme } at ${ width } by ${ height }`, async ( {
				page,
				context,
				baseURL,
			}, info ) => {
				test.skip(
					info.project.name === 'no-js',
					"The button is the script's: without it there is nothing to turn."
				);
				await context.addCookies( [
					{
						name: 'cbuild_e2e_theme',
						value: theme,
						url: baseURL || SITE,
					},
				] );
				await page.setViewportSize( { width, height } );
				await page.goto( fixture().page );
				await page
					.locator( '.cbuild-presets' )
					.getByRole( 'button', { name: 'Start from Street' } )
					.click();
				await expect( heading( page ) ).toHaveText(
					/: Check your build$/
				);
				await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
					'aria-busy',
					'false'
				);
				const turn = picture( page ).locator(
					'.cbuild-picture__sides'
				);
				await expect( turn ).toBeVisible();
				/**
				 * How far the side's board reaches past the button's top, measured together once its motion has settled.
				 *
				 * @param shown The side's board.
				 */
				const over = ( shown: Locator ) =>
					shown.evaluate( ( board ) => {
						const button = document
							.querySelector(
								'#cbuild-picture .cbuild-picture__sides'
							)
							?.getBoundingClientRect();
						const still =
							document
								.querySelector( '#cbuild-picture' )
								?.getAnimations( { subtree: true } )
								.every(
									( one ) => 'running' !== one.playState
								) ?? true;
						if ( ! button || ! still ) {
							return 'not yet';
						}
						const by =
							board.getBoundingClientRect().bottom - button.top;
						return by > 0.5
							? `over it by ${ by.toFixed( 1 ) }`
							: 'clear';
					} );
				await expect
					.poll( () => over( under( page ) ), 'the underside' )
					.toBe( 'clear' );

				await topSide( page ).click();
				await expect( topSide( page ) ).toHaveAttribute(
					'aria-pressed',
					'true'
				);
				await expect( top( page ) ).toBeVisible();
				await expect
					.poll( () => over( top( page ) ), 'the top' )
					.toBe( 'clear' );
			} );
		}
	}

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
			picture( page ).getByRole( 'button', { name: 'Top', exact: true } )
		).toHaveCount( 0 );
		await expect( top( page ) ).toBeHidden();
		// The deck's photo takes the board's place (#82): nothing is drawn, so neither side is named, and each part
		// shows its labelled photo.
		await expect( picture( page ) ).toHaveClass( /cbuild-picture--hero/ );
		await expect( description( page ) ).not.toContainText( 'Seen from', {
			useInnerText: true,
		} );
		await expect( under( page ) ).toBeHidden();
		if ( info.project.name === 'no-js' ) {
			await page.goto(
				linkTo( {
					deck: id( 'builder-test-deck-8-0' ),
					step: 'trucks',
				} )
			);
			await expect(
				picture( page ).getByRole( 'button', {
					name: 'Top',
					exact: true,
				} )
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
			const url = baseURL || SITE;
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
			const url = baseURL || SITE;
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
		const url = baseURL || SITE;
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
					url: baseURL || SITE,
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
					const estimate = summary.querySelector< HTMLElement >(
						'.cbuild-bar__estimate'
					)!;
					return {
						shown: sum.getClientRects().length,
						estimate: estimate.getBoundingClientRect().height,
						said:
							! estimate.hidden &&
							'visible' ===
								getComputedStyle( estimate ).visibility,
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
			// The auditor's M12 minor 7: axe on the bar of a blank build and of a started one (D-374).
			await axe(
				page,
				`the bar of a blank build on ${ theme }`,
				'.cbuild-bar'
			);
			await pickDeck( page, '8.0' );
			await settled( page );
			await expect( total ).not.toContainText( '$15.00' );
			const after = await read();
			// Beside the board's picture "Total:" broke the line on CI's fonts, and the bar grew under the thumb
			// (D-207): the amount alone is shown.
			expect( after.shown, 'the amount on one line' ).toBe( 1 );
			// The deck brings the estimate, a line of its own under the amount (F2, D-230). A blank build can have none,
			// so it keeps no line for one, which would take a row of decks from the first view (F24; D-374). The deck's
			// press keeps the line, so the estimate an answer brings late moves nothing (F20, #118; D-356): the bar grows
			// by that line alone, and a wider total never grows it.
			expect( before.said, 'no estimate on a blank build' ).toBe( false );
			expect( before.estimate, 'and no line kept for one' ).toBe( 0 );
			expect( after.said, 'the estimate, once a deck is picked' ).toBe(
				true
			);
			expect(
				Math.abs( after.height - before.height - after.estimate ),
				"the bar grows by the estimate's line alone"
			).toBeLessThanOrEqual( 1 );
			await axe(
				page,
				`the bar of a started build on ${ theme }`,
				'.cbuild-bar'
			);
			await total.evaluate( ( summary ) => {
				(
					summary.querySelector(
						'.cbuild-bar__estimate'
					) as HTMLElement
				 ).style.display = 'none';
			} );
			// With its line taken out as well, the bar is the blank build's: a wider total adds nothing.
			expect(
				Math.abs( ( await read() ).height - before.height ),
				'as high with a wider total, the estimate aside'
			).toBeLessThanOrEqual( 1 );
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
			const url = baseURL || SITE;
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
					url: baseURL || SITE,
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
			// A request the store has given up on, as the one asked ahead for Next once a pick is made, is gone.
			await route.continue().catch( () => undefined );
		} );
		await page.goto( linkTo( { _width: '8in' } ) );
		const bar = page.locator( '.cbuild-bar' );
		// Listened for first: a press asks at once (D-308), so the request leaves with the click.
		const asked = page.waitForRequest( /\/cbuild\/v1\/kits\/\d+\/state/ );
		await page.locator( '.cbuild-card' ).first().click();
		await asked;
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
			// A request the store has given up on, as the one asked ahead for Next once a pick is made, is gone.
			await route.continue().catch( () => undefined );
		} );
		await page.goto( linkTo( { _width: '8in' } ) );
		// Listened for first: a press asks at once (D-308), so the request leaves with the click.
		const asked = page.waitForRequest( /\/cbuild\/v1\/kits\/\d+\/state/ );
		await page.locator( '.cbuild-card' ).first().click();
		await asked;
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

	for ( const [ name, kind ] of [
		[ 'a blank build', 'blank' ],
		// Cowork's 0.8.0 review, F2 (D-230): with the estimate the bar is taller.
		[ 'the bar giving the estimate', 'estimate' ],
		// M11 audit, finding 7 (WCAG 2.4.11): what a press of the dimmed Next says sits over the bar, and covers what is
		// under it as the bar does.
		[ 'with what the dimmed Next says shown over the bar', 'missing' ],
	] as const ) {
		test( `never covers the control in focus, ${ name }`, async ( {
			page,
			javaScriptEnabled,
		} ) => {
			test.skip(
				'missing' === kind && ! javaScriptEnabled,
				'Without JavaScript Next is never dimmed: the form is sent.'
			);
			await page.setViewportSize( PHONE );
			const at = new URL( fixture().page );
			if ( 'estimate' === kind ) {
				at.searchParams.set(
					'cbuild[deck]',
					String( fixture().parts[ 'builder-test-deck-8-25' ] )
				);
				at.searchParams.set( 'cbuild[step]', 'trucks' );
			}
			if ( 'missing' === kind ) {
				// The decks listed, none picked, so the cards run down under the bar.
				at.searchParams.set( 'cbuild[_width]', '8in' );
			}
			await page.goto( at.toString() );
			if ( 'estimate' === kind ) {
				await expect(
					page.locator( '.cbuild-bar__estimate' )
				).toBeVisible();
			}
			const bar = page.locator( '.cbuild-bar' );
			const missing = bar.locator( '.cbuild-bar__missing' );
			if ( 'missing' === kind ) {
				await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
					'data-ready',
					''
				);
				// From the keyboard: Playwright will not click a control that says it is disabled.
				await bar.locator( '.cbuild-bar__next:visible' ).focus();
				await page.keyboard.press( 'Enter' );
				await expect( missing ).not.toBeEmpty();
			}
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
					// The bar's top, or the top of what it says over itself while it says something.
					const said = own
						.querySelector( '.cbuild-bar__missing' )
						?.getBoundingClientRect();
					const top = Math.min(
						own.getBoundingClientRect().top,
						said && said.height > 0 ? said.top : Infinity
					);
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
			if ( 'missing' === kind ) {
				await expect( missing, 'still said' ).not.toBeEmpty();
			}
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

/**
 * The builder at full width, on the page that holds the kit's Builder block (M12's walkthrough), on each theme at a
 * phone's 390 by 844.
 */
test.describe( 'The full-width builder on a theme', () => {
	const PHONE = { width: 390, height: 844 };

	/**
	 * The page on a theme, at a phone's size unless another is given, on the trucks step with a deck and trucks picked.
	 *
	 * @param page          The page.
	 * @param context       The browser context.
	 * @param theme         The theme's slug.
	 * @param baseURL       The site.
	 * @param screen        The window's size.
	 * @param screen.width  Its width.
	 * @param screen.height Its height.
	 */
	async function opened(
		page: Page,
		context: import( '@playwright/test' ).BrowserContext,
		theme: string,
		baseURL: string | undefined,
		screen: { width: number; height: number } = PHONE
	): Promise< void > {
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: theme, url: baseURL || SITE },
		] );
		await page.setViewportSize( screen );
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'trucks',
			} )
		);
		await page.evaluate( async () => {
			await document.fonts.ready;
		} );
	}

	for ( const theme of [ 'kadence', 'storefront', 'twentytwentyfive' ] ) {
		// Kadence and Storefront: the bar stood 804 to 903 px on the 844 px screen. Sticky inside the panel, it could rise
		// no higher than the panel's top, which the page's title, the stage and the rail put near the screen's foot.
		test( `shows the whole bar in the first view on ${ theme }, a part picked`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		} ) => {
			test.skip(
				! javaScriptEnabled && 'storefront' === theme,
				"Storefront's overflow: hidden stops a sticky box without the script (D-163)."
			);
			await opened( page, context, theme, baseURL );
			const bar = page.locator( '.cbuild-bar' );
			await expect( bar ).toBeVisible();
			await expect
				.poll(
					() =>
						bar.evaluate( ( element ) => {
							const box = element.getBoundingClientRect();
							return box.top >= 0 &&
								box.bottom <= window.innerHeight + 0.5
								? 'whole'
								: `${ Math.round( box.top ) } to ${ Math.round(
										box.bottom
									) } on ${ window.innerHeight } px`;
						} ),
					{ message: 'the bar, whole on the screen' }
				)
				.toBe( 'whole' );
		} );
	}

	// The limit's line (D-364) was drawn at the builder's top: 110 px above the screen on a phone scrolled to the card
	// pressed, and at full width with no gutter, its rule at 0 px and its words at 11. It is drawn over the bar, in the
	// panel's column, and moves nothing as it comes.
	for ( const [ size, screen ] of [
		[ 'phone', PHONE ],
		[ 'desktop', { width: 1440, height: 900 } ],
	] as const ) {
		test( `says the limit in view at the card pressed, in the panel's column, on a ${ size } (D-364)`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		} ) => {
			test.skip( ! javaScriptEnabled, "The limit is the store's." );
			test.slow();
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: 'kadence',
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize( screen );
			await page.goto( linkTo( { _width: '8in' } ) );
			await expect( page.locator( '.cbuild[data-ready]' ) ).toHaveCount(
				1
			);
			const deck = page.getByRole( 'radio', {
				name: 'Choose Builder test deck 8.0',
			} );
			// Where a shopper presses it: the card in the middle of the screen.
			await deck.evaluate( ( radio ) =>
				( radio.closest( 'li' ) ?? radio ).scrollIntoView( {
					block: 'center',
				} )
			);
			await page.evaluate( () => {
				const shifts: number[] = [];
				(
					window as unknown as { cbuildShifts: number[] }
				 ).cbuildShifts = shifts;
				new PerformanceObserver( ( list ) => {
					for ( const entry of list.getEntries() as unknown as {
						value: number;
						hadRecentInput: boolean;
					}[] ) {
						if ( ! entry.hadRecentInput ) {
							shifts.push( entry.value );
						}
					}
				} ).observe( { type: 'layout-shift' } );
			} );
			let release: () => void = () => undefined;
			const held = new Promise< void >( ( done ) => {
				release = done;
			} );
			await page.route( isRoute, async ( route ) => {
				await held;
				await route.continue().catch( () => undefined );
			} );
			await deck.check();
			const late =
				'The shop did not answer in time. Try again in a moment.';
			const line = page.locator( '.cbuild__limited' );
			await expect( line ).toHaveText( late, { timeout: 20000 } );
			await expect( page.locator( '.cbuild__status' ) ).toHaveText(
				late
			);
			// A moment for anything the line moved to be reported.
			await page.waitForTimeout( 500 );
			const seen = await line.evaluate( ( element ) => {
				const box = element.getBoundingClientRect();
				const words = document
					.querySelector( '.cbuild__heading' )!
					.getBoundingClientRect();
				// A press passes through it, so it is found by the point only while it takes presses.
				( element as HTMLElement ).style.pointerEvents = 'auto';
				const at = document.elementFromPoint(
					box.left + box.width / 2,
					box.top + box.height / 2
				);
				( element as HTMLElement ).style.pointerEvents = '';
				return {
					top: Math.round( box.top ),
					bottom: Math.round( box.bottom ),
					left: Math.round( box.left ),
					right: Math.round( box.right ),
					from: Math.round( words.left ),
					to: Math.round( words.right ),
					screen: window.innerHeight,
					drawn: !! at && element.contains( at ),
					shift: (
						window as unknown as { cbuildShifts: number[] }
					 ).cbuildShifts.reduce( ( sum, value ) => sum + value, 0 ),
				};
			} );
			release();
			await page.unroute( isRoute );
			expect(
				seen.top >= 0 && seen.bottom <= seen.screen,
				`on the screen: ${ seen.top } to ${ seen.bottom } on ${ seen.screen } px`
			).toBe( true );
			expect( seen.drawn, 'drawn over what is under it' ).toBe( true );
			expect(
				seen.left >= seen.from - 1 && seen.right <= seen.to + 1,
				`in the panel's column, ${ seen.from } to ${ seen.to } px: ${ seen.left } to ${ seen.right } px`
			).toBe( true );
			expect( seen.shift, 'nothing moved as it came' ).toBe( 0 );
		} );
	}

	// The auditor's third pass on M12: the line comes 10 s after the press, over the bar's top edge, where a deck the
	// shopper picked with Space just above the bar keeps focus. It covered the card, and nothing scrolled it clear: on a
	// phone the page's scroll padding grew, and on a desktop the panel, which scrolls inside itself, had none (D-383).
	for ( const [ size, screen, long ] of [
		[ 'phone', PHONE, false ],
		[ 'desktop', { width: 1440, height: 900 }, false ],
		// The shelf kit's 46 decks: the panel scrolls inside itself, under its own scroll padding.
		[ 'desktop, the panel scrolled', { width: 1440, height: 900 }, true ],
	] as const ) {
		test( `keeps the card in focus clear of the limit's line on a ${ size } (D-383)`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		} ) => {
			test.skip( ! javaScriptEnabled, "The limit is the store's." );
			test.slow();
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: 'kadence',
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize( screen );
			if ( long ) {
				const at = new URL( fixture().shelf.page );
				at.searchParams.set( 'cbuild[_width]', '8.25in' );
				await page.goto( at.toString() );
			} else {
				await page.goto( linkTo( { _width: '8in' } ) );
			}
			await expect( page.locator( '.cbuild[data-ready]' ) ).toHaveCount(
				1
			);
			// The step's last deck, placed with its foot 4 px above the bar, and focused there.
			const value = await page
				.locator(
					'.cbuild-step .cbuild-cards:not(.cbuild-cards--sold-out) .cbuild-card__control:not([disabled]):not(:checked)'
				)
				.last()
				.getAttribute( 'value' );
			const card = page.locator(
				`.cbuild-step .cbuild-card__control[value="${ value }"]`
			);
			const placed = await card.evaluate( ( input, panel ) => {
				const bar = document.querySelector( '.cbuild-bar' )!;
				// On a desktop the bar is under the panel: the page brought to it first, and with the long step to its
				// foot, so the page can scroll no further and only the panel can move the card.
				bar.scrollIntoView( { block: 'nearest' } );
				if ( panel ) {
					window.scrollTo( 0, document.documentElement.scrollHeight );
				}
				const body = input.closest< HTMLElement >( '.cbuild__body' );
				const by =
					input.getBoundingClientRect().bottom -
					( bar.getBoundingClientRect().top - 4 );
				if (
					body &&
					'auto' === window.getComputedStyle( body ).overflowY &&
					body.scrollHeight > body.clientHeight
				) {
					body.scrollTop += by;
				} else {
					window.scrollBy( 0, by );
				}
				( input as HTMLElement ).focus( { preventScroll: true } );
				return Math.round(
					bar.getBoundingClientRect().top -
						input.getBoundingClientRect().bottom
				);
			}, long );
			if ( long ) {
				expect(
					await page
						.locator( '.cbuild__body' )
						.evaluate( ( body ) => body.scrollTop ),
					'the panel scrolled inside itself'
				).toBeGreaterThan( 0 );
			}
			expect( placed, 'the card just above the bar' ).toBeGreaterThan(
				0
			);
			expect( placed, 'the card just above the bar' ).toBeLessThan( 12 );
			let release: () => void = () => undefined;
			const held = new Promise< void >( ( done ) => {
				release = done;
			} );
			await page.route( isRoute, async ( route ) => {
				await held;
				await route.continue().catch( () => undefined );
			} );
			await page.keyboard.press( 'Space' );
			await expect( card ).toBeChecked();
			const line = page.locator( '.cbuild__limited' );
			await expect( line ).not.toBeEmpty( { timeout: 20000 } );
			await expect
				.poll(
					() =>
						page.evaluate( () => {
							const own =
								document.querySelector( '.cbuild__limited' )!;
							const focused = own.ownerDocument.activeElement!;
							const said = own.getBoundingClientRect();
							const box = focused.getBoundingClientRect();
							return said.height > 0 &&
								box.bottom <= said.top + 1 &&
								box.top >= 0 &&
								box.bottom <= window.innerHeight
								? 'clear'
								: `${ focused.className }: ${ Math.round(
										box.top
									) } to ${ Math.round(
										box.bottom
									) }, the line from ${ Math.round( said.top ) }`;
						} ),
					{ message: 'the card in focus, clear of the line' }
				)
				.toBe( 'clear' );
			await expect( card, 'focus kept' ).toBeFocused();
			release();
			await page.unroute( isRoute );
		} );
	}

	// At full width the builder spans what the theme gives a full-width block, the screen on Kadence and Twenty
	// Twenty-Five. Stacked, its words, picture and buttons keep the page's gutter: on a phone "Your board" and the
	// picture were 7 px from the edge on Kadence, and 10 px on Twenty Twenty-Five, beside a heading at 16 and 30. Beside
	// the stage, the frame's panes keep the builder's gap, and nothing is under 16 px from the edge (D-384).
	for ( const theme of [ 'kadence', 'storefront', 'twentytwentyfive' ] ) {
		for ( const [ size, screen ] of [
			[ 'phone', PHONE ],
			[ 'desktop', { width: 1440, height: 900 } ],
		] as const ) {
			test( `keeps the page's gutter on ${ theme } on a ${ size }`, async ( {
				page,
				context,
				baseURL,
			} ) => {
				await opened( page, context, theme, baseURL, screen );
				await expect( page.locator( '.cbuild-bar' ) ).toBeVisible();
				const near = await page.evaluate( ( stacked ) => {
					// The page's gutter, as the stacked builder reads it: the theme's root padding, never under 16 px.
					const probe = document.createElement( 'div' );
					probe.style.paddingLeft = stacked
						? 'max(1rem, var(--wp--style--root--padding-left, 0px))'
						: '1rem';
					probe.style.paddingRight = stacked
						? 'max(1rem, var(--wp--style--root--padding-right, 0px))'
						: '1rem';
					document.body.append( probe );
					const gutter = {
						left: parseFloat(
							getComputedStyle( probe ).paddingLeft
						),
						right: parseFloat(
							getComputedStyle( probe ).paddingRight
						),
					};
					probe.remove();
					const width = document.documentElement.clientWidth;
					return [
						'.cbuild-picture__heading',
						'.cbuild-picture',
						'.cbuild__heading',
						'.cbuild-bar__total',
						'.cbuild-bar__next:not([hidden])',
					].flatMap( ( selector ) => {
						const box = document
							.querySelector( selector )!
							.getBoundingClientRect();
						return box.left < gutter.left - 0.5 ||
							width - box.right < gutter.right - 0.5
							? [
									`${ selector }: ${ Math.round(
										box.left
									) } to ${ Math.round(
										box.right
									) } px of ${ width }, the gutter ${
										gutter.left
									} and ${ gutter.right }`,
								]
							: [];
					} );
				}, 'phone' === size );
				expect(
					near,
					'nothing nearer the edge than the gutter'
				).toEqual( [] );
			} );
		}
	}
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
		// A customer of this test's own, with a cart nothing else writes. The administrator's session is also saved
		// whole by requests the test doesn't make, each with the cart it read as it started: WordPress's heartbeat on
		// an admin screen, and Action Scheduler's queue runner, sent with the administrator's cookies. Under load one
		// ended seconds after the test that sent it, and the cart read 2 before the saves and 0 after (#145).
		await customer( page );
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

// #136 (D-411): up to three saved builds side by side under My builds, chosen by keyboard, in a real table whose
// columns and rows are headed; on a phone a build a column, the table scrolling sideways in its own region and never
// the page. The rows that differ and the gap between the totals are said in words. With JavaScript and without, at
// each width, in French on the fr-ca project.
test.describe( 'Compare saved builds (#136)', () => {
	const names = [ 'Street setup', 'Park setup', 'Bowl setup' ];

	/** The test's own builds of the saved builds customer, taken away. */
	const clear = () =>
		runPhp(
			`$u = get_user_by( 'login', 'e2e-saver' ); if ( $u ) { $s = CreativeBranding\\SkateboardBuilder\\Plugin::container()->get( CreativeBranding\\SkateboardBuilder\\Service\\SavedBuilds::class ); foreach ( $s->of( $u->ID ) as $b ) { if ( in_array( $b->name, ${ JSON.stringify(
				names
			) }, true ) ) { $s->delete( $u->ID, $b->uuid ); } } }`
		);

	test.afterAll( clear );

	test( 'three builds compared by keyboard in a table that says what differs in words', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		await customer( page );
		clear();
		const street = {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-54' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1-25' ),
			grip: id( 'builder-test-grip' ),
		};
		// Park: wheels $2 cheaper. Bowl: a wider deck and its trucks, dearer.
		const builds = [
			street,
			{ ...street, wheels: id( 'builder-test-wheels-52' ) },
			{
				...street,
				deck: id( 'builder-test-deck-8-5' ),
				trucks: id( 'builder-test-trucks-149' ),
			},
		];
		runPhp(
			`$u = get_user_by( 'login', 'e2e-saver' ); $s = CreativeBranding\\SkateboardBuilder\\Plugin::container()->get( CreativeBranding\\SkateboardBuilder\\Service\\SavedBuilds::class ); foreach ( json_decode( '${ JSON.stringify(
				builds.map( ( picks, at ) => ( { name: names[ at ], picks } ) )
			) }', true ) as $b ) { if ( 'saved' !== $s->save( $u->ID, ${
				fixture().kit
			}, $b['picks'], true, $b['name'] )['code'] ) { throw new RuntimeException( $b['name'] ); } }`
		);
		const french = 'fr-ca' === info.project.name;
		if ( french ) {
			await page.setExtraHTTPHeaders( {
				'x-cbuild-e2e-locale': 'fr_CA',
				'x-cbuild-e2e-translated': '1',
			} );
		}
		const say = ( english: string, inFrench: string ) =>
			french ? inFrench : english;
		await page.goto( '/my-account/my-builds/' );

		// Each row names its build on a phone too, where WooCommerce's small-screen styles hide a responsive table's
		// row headers, so a box is never ticked for a build the shopper can't see (D-412).
		for ( const name of names ) {
			await expect(
				page.locator( '.cbuild-my-builds__name' ).filter( {
					hasText: name,
				} ),
				'the row says which build it is'
			).toBeVisible();
		}

		// Chosen by keyboard: each build's box, then the one button.
		for ( const name of names ) {
			const box = page.getByRole( 'checkbox', {
				name: say( `Compare ${ name }`, `Comparer ${ name }` ),
			} );
			await box.focus();
			await page.keyboard.press( 'Space' );
			await expect( box ).toBeChecked();
		}
		await page
			.getByRole( 'button', {
				name: say(
					'Compare the ticked builds',
					'Comparer les montages cochés'
				),
			} )
			.focus();
		await answered( page, () => page.keyboard.press( 'Enter' ) );
		expect( decodeURIComponent( page.url() ) ).toContain(
			'cbuild_compare[]='
		);

		const caption = say(
			"Your builds side by side, at today's prices",
			'Vos montages côte à côte, aux prix d’aujourd’hui'
		);
		const table = page.getByRole( 'table', { name: caption } );
		await expect( table ).toBeVisible();
		await expect( table.getByRole( 'columnheader' ) ).toHaveText( [
			say( 'Part', 'Pièce' ),
			// In the order chosen: My builds lists the last saved first.
			/^\s*Bowl setup/,
			/^\s*Park setup/,
			/^\s*Street setup/,
		] );
		const differs = say( 'Differs', 'Diffère' );
		await expect(
			table.getByRole( 'rowheader', { name: `Wheels ${ differs }` } ),
			'a row that differs says so in words'
		).toBeVisible();
		await expect(
			table.getByRole( 'rowheader', {
				name: 'Bearings',
				exact: true,
			} ),
			'a row the same in each is not marked'
		).toBeVisible();
		const gaps = table
			.getByRole( 'row' )
			.filter( {
				has: page.getByRole( 'rowheader', {
					name: say( 'Price difference', 'Écart de prix' ),
				} ),
			} )
			.getByRole( 'cell' );
		await expect( gaps ).toHaveText( [
			/ more than the lowest$| de plus que le plus bas$/,
			say( 'Lowest total', 'Total le plus bas' ),
			/ more than the lowest$| de plus que le plus bas$/,
		] );
		await expect(
			table.getByRole( 'link', {
				name: say( 'Open Park setup', 'Ouvrir Park setup' ),
			} )
		).toBeVisible();

		// The region takes focus, to scroll by keyboard; the page itself never scrolls sideways.
		const region = page.getByRole( 'region', { name: caption } );
		// Reached by Tab from the link before it: in the tab order, not only focusable by a script.
		await page
			.getByRole( 'link', {
				name: say( 'Back to My builds', 'Retour à Mes montages' ),
			} )
			.focus();
		await page.keyboard.press( 'Tab' );
		await expect( region ).toBeFocused();
		const sizes = () =>
			page.evaluate( () => {
				const box = document.querySelector( '.cbuild-compare' )!;
				return {
					scroll: box.scrollWidth,
					client: box.clientWidth,
					left: Math.abs( box.scrollLeft ),
					page: document.documentElement.scrollWidth,
					view: document.documentElement.clientWidth,
					columns: [
						...document.querySelectorAll(
							'.cbuild-compare__build'
						),
					].map( ( th ) => th.getBoundingClientRect().width ),
				};
			} );
		const measured = await sizes();
		expect(
			measured.page,
			'no sideways scroll of the page'
		).toBeLessThanOrEqual( measured.view );
		for ( const width of measured.columns ) {
			expect(
				width,
				'a build a column, at least 14rem'
			).toBeGreaterThanOrEqual( 14 * 16 - 1 );
		}
		if ( info.project.name.includes( 'phone' ) ) {
			expect(
				measured.columns[ 0 ],
				'about one build in view at a time'
			).toBeGreaterThan( measured.client / 2 );
			expect( measured.scroll, 'the region scrolls' ).toBeGreaterThan(
				measured.client
			);
			// The arrow keys scroll the focused region in Chromium and Firefox. Playwright's WebKit scrolled nothing for
			// them here, so there the region's focus and its overflow, which are the page's part, are what is held.
			if ( ! info.project.name.startsWith( 'webkit' ) ) {
				await page.keyboard.press( 'ArrowRight' );
				await expect
					.poll( async () => ( await sizes() ).left )
					.toBeGreaterThan( 0 );
			}
		}
		if ( javaScriptEnabled ) {
			await axe(
				page,
				`${ info.project.name }: compare`,
				'.woocommerce-MyAccount-content'
			);
		}

		await page
			.getByRole( 'link', {
				name: say( 'Back to My builds', 'Retour à Mes montages' ),
			} )
			.click();
		await expect( page.locator( '.cbuild-my-builds' ) ).toBeVisible();
	} );

	// Milestone 13's walkthrough (D-450): Kadence and Storefront dequeue WooCommerce's `woocommerce-general`, which
	// held the compare's rules, so on a phone the table lost its scroll region and the third build was cut off where
	// no one could reach it, and the row tints and the Differs mark were plain text. On the plugin's own handle, every
	// build's column is reached by scrolling the region, on either theme.
	for ( const theme of [ 'kadence', 'storefront' ] ) {
		test( `every build's column is reached in the compare's region on a phone, on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			await customer( page );
			clear();
			const street = {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-54' ),
				bearings: id( 'builder-test-bearings' ),
				hardware: id( 'builder-test-bolts-1-25' ),
				grip: id( 'builder-test-grip' ),
			};
			const builds = [
				street,
				{ ...street, wheels: id( 'builder-test-wheels-52' ) },
				{
					...street,
					deck: id( 'builder-test-deck-8-5' ),
					trucks: id( 'builder-test-trucks-149' ),
				},
			];
			runPhp(
				`$u = get_user_by( 'login', 'e2e-saver' ); $s = CreativeBranding\\SkateboardBuilder\\Plugin::container()->get( CreativeBranding\\SkateboardBuilder\\Service\\SavedBuilds::class ); foreach ( json_decode( '${ JSON.stringify(
					builds.map( ( picks, at ) => ( {
						name: names[ at ],
						picks,
					} ) )
				) }', true ) as $b ) { if ( 'saved' !== $s->save( $u->ID, ${
					fixture().kit
				}, $b['picks'], true, $b['name'] )['code'] ) { throw new RuntimeException( $b['name'] ); } }`
			);
			const french = 'fr-ca' === info.project.name;
			if ( french ) {
				await page.setExtraHTTPHeaders( {
					'x-cbuild-e2e-locale': 'fr_CA',
					'x-cbuild-e2e-translated': '1',
				} );
			}
			const say = ( english: string, inFrench: string ) =>
				french ? inFrench : english;
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize( { width: 390, height: 844 } );
			await page.goto( '/my-account/my-builds/' );
			for ( const name of names ) {
				await page
					.getByRole( 'checkbox', {
						name: say( `Compare ${ name }`, `Comparer ${ name }` ),
					} )
					.check();
			}
			await answered( page, () =>
				page
					.getByRole( 'button', {
						name: say(
							'Compare the ticked builds',
							'Comparer les montages cochés'
						),
					} )
					.click()
			);
			const region = page.locator( '.cbuild-compare' );
			await expect( region ).toBeVisible();
			const columns = page.locator( '.cbuild-compare__build' );
			await expect( columns ).toHaveCount( 3 );
			expect(
				await region.evaluate(
					( box ) => getComputedStyle( box ).overflowX
				),
				'the region scrolls sideways'
			).toBe( 'auto' );
			// Each column, scrolled into view inside the region (in either direction, for rtl), lies inside it.
			for ( let at = 0; at < 3; at++ ) {
				const inside = await page.evaluate( ( index ) => {
					const box = document.querySelector( '.cbuild-compare' )!;
					const th = document.querySelectorAll(
						'.cbuild-compare__build'
					)[ index ]!;
					th.scrollIntoView( {
						block: 'nearest',
						inline: 'nearest',
					} );
					const outer = box.getBoundingClientRect();
					const cell = th.getBoundingClientRect();
					return {
						left: cell.left >= outer.left - 1,
						right: cell.right <= outer.right + 1,
						page:
							document.documentElement.scrollWidth <=
							document.documentElement.clientWidth,
					};
				}, at );
				expect(
					inside,
					`build ${ at + 1 }'s column reached in the region, and never by scrolling the page`
				).toEqual( { left: true, right: true, page: true } );
			}
			const differs = page
				.locator( '.cbuild-compare__mark' )
				.filter( { hasText: say( 'Differs', 'Diffère' ) } )
				.first();
			await differs.scrollIntoViewIfNeeded();
			await expect( differs, 'the Differs mark' ).toBeVisible();
			expect(
				await differs.evaluate(
					( el ) => getComputedStyle( el ).borderTopStyle
				),
				'drawn as a mark, not plain words'
			).toBe( 'solid' );
		} );
	}
} );

/**
 * Presses a button and waits for the step whose heading it leads to, with JavaScript or without: the store moves the
 * builder without loading the page, and the form loads the next one.
 *
 * @param page   The page.
 * @param name   The button's name, or a pattern where it differs with JavaScript and without.
 * @param titled The heading the step it leads to has.
 */
async function moveTo( page: Page, name: string | RegExp, titled: RegExp ) {
	await page.getByRole( 'button', { name, exact: true } ).click();
	await expect( heading( page ) ).toHaveText( titled );
	await expect( page.locator( '.cbuild' ) ).not.toHaveAttribute(
		'aria-busy',
		'true'
	);
}

/**
 * Runs PHP in the browser site, the plugin loaded, with `$cbuild_index`, which saves a part and indexes it again as
 * the fixture does.
 *
 * @param code The PHP.
 */
function runPhp( code: string ): void {
	const index =
		'$cbuild_index = static function ( WC_Product $p ): void { $p->save(); CreativeBranding\\SkateboardBuilder\\Plugin::container()->get( CreativeBranding\\SkateboardBuilder\\Service\\SpecIndexer::class )->index( $p->get_id() ); };';
	// A file per worker: two workers writing one file could each run the other's code.
	const name = `e2e-eval-${ process.pid }.php`;
	writeFileSync(
		path.join( process.cwd(), 'tmp', name ),
		`<?php\n${ index }\n${ code }\n`
	);
	wp(
		`eval-file wp-content/plugins/creative-skateboard-builder/tmp/${ name }`
	);
}

/**
 * The kit's Ready-build picks on cards and Ratings on cards (D-409), each set as said, or its key taken out with null,
 * as a kit saved before 1.3.0 has it. The kit's other settings are kept.
 *
 * @param picks   Whether a card says the ready builds that pick its part.
 * @param ratings Whether a card shows its part's rating.
 */
function cardSwitches( picks: boolean | null, ratings: boolean | null ): void {
	const set = ( key: string, on: boolean | null ): string =>
		null === on ? `unset( $d['${ key }'] );` : `$d['${ key }'] = ${ on };`;
	runPhp(
		`$k = wc_get_product( ${
			fixture().kit
		} ); $d = $k->documents()['_cbuild_settings'] ?? array( 'schema_version' => 1 ); ${ set(
			'picks',
			picks
		) } ${ set(
			'ratings',
			ratings
		) } $k->setDocument( '_cbuild_settings', $d ); $k->save();`
	);
}

/**
 * Makes the page's browser the saved builds test's own customer, made on the first run, with a password set for this
 * run alone: no other test signs in as them, so their cart is theirs (#145).
 *
 * @param page The page.
 */
async function customer( page: Page ): Promise< void > {
	const user = 'e2e-saver';
	const pass = randomUUID();
	runPhp(
		`$u = get_user_by( 'login', '${ user }' ); if ( ! $u ) { wp_insert_user( array( 'user_login' => '${ user }', 'user_email' => 'shopper+40@example.com', 'user_pass' => '${ pass }', 'role' => 'customer' ) ); } else { wp_set_password( '${ pass }', $u->ID ); }`
	);
	// WordPress refuses a login from a client that did not take its test cookie, which the form's own page sets.
	await page.request.get( '/wp-login.php' );
	const answer = await page.request.post( '/wp-login.php', {
		form: {
			log: user,
			pwd: pass,
			'wp-submit': 'Log In',
			redirect_to: '/my-account/',
			testcookie: '1',
		},
	} );
	expect( answer.url(), 'the customer is signed in' ).toContain(
		'/my-account/'
	);
}

/**
 * A part's own specs set to these, or taken off with null, as the Part specs panel would save them.
 *
 * @param sku    The part's SKU.
 * @param values The specs to set.
 */
function setSpecs(
	sku: string,
	values: Record< string, string | null >
): void {
	runPhp(
		`$p = wc_get_product( ${ id( sku ) } ); $s = (array) $p->get_meta( '_cbuild_specs', true ); foreach ( json_decode( '${ JSON.stringify(
			values
		) }', true ) as $k => $v ) { if ( null === $v ) { unset( $s[ $k ] ); } else { $s[ $k ] = $v; } } $p->update_meta_data( '_cbuild_specs', $s ); $cbuild_index( $p );`
	);
}

/**
 * A part of the test kit made for one test, in a slot's category, or deleted with its SKU.
 *
 * @param sku   Its SKU.
 * @param slot  The slot whose category it is in, or '' to delete it.
 * @param price Its price.
 * @param own   Its specs.
 */
function tempPart(
	sku: string,
	slot: string,
	price = '5',
	own: Record< string, string > = {}
): void {
	if ( '' === slot ) {
		runPhp(
			`$i = wc_get_product_id_by_sku( '${ sku }' ); if ( $i ) { wc_get_product( $i )->delete( true ); }`
		);
		return;
	}
	runPhp(
		`$p = new WC_Product_Simple( wc_get_product_id_by_sku( '${ sku }' ) ); $p->set_name( '${ sku.replace(
			/-/g,
			' '
		) }' ); $p->set_sku( '${ sku }' ); $p->set_regular_price( '${ price }' ); $p->set_status( 'publish' ); $p->set_category_ids( array( get_term_by( 'slug', 'builder-test-${ slot }', 'product_cat' )->term_id ) ); $p->update_meta_data( '_cbuild_specs', json_decode( '${ JSON.stringify(
			own
		) }', true ) ); $cbuild_index( $p );`
	);
}

// Spec: The steps that appear only when needed (D-246), and the roadmap's "the bearings step skipped" and "the
// risers step and the bolts following, on the test kit's 54 mm and 56 mm wheels, with bolts long enough for a ½″
// riser and without". The spec reviewer's M10 finding B2.
test.describe( 'The steps that appear only when needed', () => {
	test.afterAll( () => {
		setSpecs( 'builder-test-wheels-54', { bearings_included: null } );
		setSpecs( 'builder-test-trucks-139', { max_wheel: '56.0000' } );
		runPhp(
			`$p = wc_get_product( ${ id(
				'builder-test-wheels-56'
			) } ); $p->set_stock_status( 'outofstock' ); $cbuild_index( $p );`
		);
		tempPart( 'builder-test-risers-1-2', '' );
		tempPart( 'builder-test-bolts-1-5', '' );
	} );

	test( 'wheels sold with their bearings skip the bearings step, and the review says so', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		setSpecs( 'builder-test-wheels-54', { bearings_included: '1' } );
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'wheels',
			} )
		);
		await choose( page, 'Builder test wheels 54' );
		// Without JavaScript the button names the step after the wheels as the page had it, before the wheels were
		// sent; the server then skips the bearings. With it, the store names the hardware once the wheels are picked.
		await moveTo(
			page,
			javaScriptEnabled
				? 'Next: Hardware'
				: /^Next: (Bearings|Hardware)$/,
			/^Step \d+ of 6: Choose your hardware$/
		);
		await expect(
			page.locator( '.cbuild-progress' ).getByText( 'Bearings' )
		).toHaveCount( 0 );
		await choose( page, 'Builder test bolts 1.25' );
		await moveTo( page, 'Next: Grip tape', /: Choose your grip tape$/ );
		await choose( page, 'Builder test grip' );
		await moveTo( page, 'Next: Review', /: Check your build$/ );
		await expect(
			page
				.locator( '.cbuild-review' )
				.getByText( 'Included with your wheels' )
		).toBeVisible();
		setSpecs( 'builder-test-wheels-54', { bearings_included: null } );
	} );

	test( 'wheels over the trucks are hidden when no bolt is long enough for the riser they need, and offered when one is', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		// The trucks take 52 mm: 54 mm wheels are 2 mm over and need ¼″ risers, 56 mm wheels 4 mm over and ½″
		// (R3's table). A ½″ riser needs a bolt of 1⅜″ (R4), longer than the kit's 1¼″.
		setSpecs( 'builder-test-trucks-139', { max_wheel: '52.0000' } );
		runPhp(
			`$p = wc_get_product( ${ id(
				'builder-test-wheels-56'
			) } ); $p->set_stock_status( 'instock' ); $cbuild_index( $p );`
		);
		tempPart( 'builder-test-risers-1-2', 'risers', '7', {
			riser_thickness: '0.5000',
			set_size: '2',
		} );
		const wheels = linkTo( {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			step: 'wheels',
		} );

		// Without: the 56 mm wheels are hidden with R4's reason; the 54 mm say what they need.
		await page.goto( wheels );
		await expect(
			page.getByRole( 'radio', { name: 'Choose Builder test wheels 56' } )
		).toHaveCount( 0 );
		const hidden = page.locator( '.cbuild-hidden' );
		await hidden.locator( 'summary' ).click();
		await expect(
			hidden
				.locator( 'li' )
				.filter( { hasText: 'Builder test wheels 56' } )
				.getByText(
					'Needs ½″ risers, and no bolts here are long enough for them.'
				)
		).toBeVisible();
		await expect(
			page
				.locator( '.cbuild-card' )
				.filter( { hasText: 'Builder test wheels 54' } )
				.getByText( 'Needs ¼″ risers with your trucks' )
		).toBeVisible();

		// With a 1½″ bolt: the 56 mm wheels are offered, the risers step opens on the ½″ riser, and the bolts follow.
		tempPart( 'builder-test-bolts-1-5', 'hardware', '6', {
			bolt_length: '1.5000',
			set_size: '8',
		} );
		await page.goto( wheels );
		await expect(
			page
				.locator( '.cbuild-card' )
				.filter( { hasText: 'Builder test wheels 56' } )
				.getByText( 'Needs ½″ risers with your trucks' )
		).toBeVisible();
		await choose( page, 'Builder test wheels 56' );
		// Without JavaScript the button names the step after the wheels as the page had it; the server then opens the
		// risers. With it, the store names the risers once the route answers: Next is lit at the pick (#100), and a
		// press of "Next: Bearings" raced the answer, which on WebKit came first about one run in ten.
		await moveTo(
			page,
			javaScriptEnabled ? 'Next: Risers' : 'Next: Bearings',
			/: Choose your risers$/
		);
		await expect(
			page.getByText(
				'Your 56 mm wheels need ½″ risers with these trucks'
			)
		).toBeVisible();
		await expect(
			page.getByRole( 'radio', { name: 'No risers' } ),
			'Never added silently.'
		).toBeChecked();
		await choose( page, 'builder test risers 1 2' );
		await moveTo( page, 'Next: Bearings', /: Choose your bearings$/ );
		await choose( page, 'Builder test bearings' );
		await moveTo( page, 'Next: Hardware', /: Choose your hardware$/ );
		await expect(
			page.getByRole( 'radio', { name: 'Choose builder test bolts 1 5' } )
		).toBeVisible();
		await expect(
			page.getByRole( 'radio', {
				name: 'Choose Builder test bolts 1.25',
			} ),
			'too short for the riser'
		).toHaveCount( 0 );
	} );
} );

// Spec: A saved build that has changed (D-254), and "Build a complete with this deck". The spec reviewer's M10
// finding B3.
test.describe( 'A saved build that has changed, and the deck page', () => {
	test.afterAll( () => {
		runPhp(
			`$p = wc_get_product( ${ id( 'builder-test-wheels-54' ) } ); $p->set_stock_status( 'instock' ); $cbuild_index( $p );`
		);
		runPhp(
			`$p = wc_get_product( ${ id( 'builder-test-bearings' ) } ); $p->set_regular_price( '15' ); $cbuild_index( $p );`
		);
	} );

	test( 'opens with what changed, its heading in focus, and Use these or Choose myself', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		await login( page );
		const name = `Changed ${ info.project.name } ${ Date.now() }`;
		// Its build is deleted however the test ends: one left a run reached My builds' cap of 100 on a lane's site, and
		// every save after it was refused (M12's combined /verify).
		try {
			await page.goto(
				linkTo( {
					deck: id( 'builder-test-deck-8-0' ),
					trucks: id( 'builder-test-trucks-139' ),
					wheels: id( 'builder-test-wheels-54' ),
					bearings: id( 'builder-test-bearings' ),
					hardware: id( 'builder-test-bolts-1-25' ),
					grip: id( 'builder-test-grip' ),
					step: 'review',
				} )
			);
			await page.getByLabel( 'Name it (optional)' ).fill( name );
			await answered( page, () =>
				page.getByRole( 'button', { name: 'Save build' } ).click()
			);
			await expect( page ).toHaveURL( /my-builds/ );

			runPhp(
				`$p = wc_get_product( ${ id( 'builder-test-wheels-54' ) } ); $p->set_stock_status( 'outofstock' ); $cbuild_index( $p );`
			);
			runPhp(
				`$p = wc_get_product( ${ id( 'builder-test-bearings' ) } ); $p->set_regular_price( '16' ); $cbuild_index( $p );`
			);
			const open = async () => {
				await page.goto( '/my-account/my-builds/' );
				await page
					.locator( '.cbuild-my-builds__build' )
					.filter( { hasText: name } )
					.getByRole( 'link', { name: 'Open' } )
					.click();
				const changed = page.getByRole( 'heading', {
					name: 'Your build has changed',
				} );
				await expect( changed ).toBeVisible();
				if ( javaScriptEnabled ) {
					await expect( changed ).toBeFocused();
				}
				return changed;
			};

			await open();
			await expect(
				page.getByText( 'Wheels: Builder test wheels 54 is sold out.' )
			).toBeVisible();
			await expect(
				page.getByText( /^Closest fit: Builder test wheels / )
			).toBeVisible();
			await expect(
				page.getByText(
					/^Builder test bearings: .*15\.00 when you saved, .*16\.00 now$/
				)
			).toBeVisible();
			if ( javaScriptEnabled ) {
				await axe( page, 'a saved build that has changed', '.cbuild' );
			}
			await moveTo( page, 'Use these', /: Check your build$/ );
			// The review's heading is on the page under the panel already, so the panel's going away is the move's end.
			await expect(
				page.getByRole( 'heading', { name: 'Your build has changed' } )
			).toHaveCount( 0 );
			await page.waitForLoadState( 'load' );
			await expect(
				page
					.locator( '.cbuild-review' )
					.getByText( 'Builder test wheels 54' )
			).toHaveCount( 0 );

			await open();
			await moveTo( page, 'Choose myself', /: Choose your wheels$/ );
		} finally {
			runPhp(
				`global $wpdb; $wpdb->delete( \\CreativeBranding\\SkateboardBuilder\\Support\\Tables::builds(), array( 'name' => '${ name }' ) );`
			);
		}
	} );

	// The spec reviewer's M10 minor M3: "with that deck picked, its width with it". On a variable deck's page the
	// button follows the variation chosen in WooCommerce's form, and goes back to its own link when it is cleared.
	test( "a variable deck's button opens the variation chosen in its form", async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			"WooCommerce's variation form needs a script; without one the button opens the first in stock."
		);
		runPhp(
			`$a = new WC_Product_Attribute(); $a->set_name( 'width' ); $a->set_options( array( '8.0', '8.5' ) ); $a->set_variation( true ); $p = new WC_Product_Variable( wc_get_product_id_by_sku( 'builder-test-deck-variable' ) ); $p->set_name( 'Builder test variable deck' ); $p->set_sku( 'builder-test-deck-variable' ); $p->set_status( 'publish' ); $p->set_attributes( array( $a ) ); $p->set_category_ids( array( get_term_by( 'slug', 'builder-test-deck', 'product_cat' )->term_id ) ); $p->save(); foreach ( array( '8.0' => '8.0000', '8.5' => '8.5000' ) as $o => $w ) { $v = new WC_Product_Variation( wc_get_product_id_by_sku( 'builder-test-deck-variable-' . $o ) ); $v->set_parent_id( $p->get_id() ); $v->set_sku( 'builder-test-deck-variable-' . $o ); $v->set_attributes( array( 'width' => $o ) ); $v->set_regular_price( '60' ); $v->update_meta_data( '_cbuild_specs', array( 'deck_width' => $w ) ); $cbuild_index( $v ); } $cbuild_index( wc_get_product( $p->get_id() ) );`
		);
		try {
			const idOf = ( sku: string ) =>
				wp( `eval "echo wc_get_product_id_by_sku( '${ sku }' );"` );
			const wide = idOf( 'builder-test-deck-variable-8.5' );
			await page.goto( `/?p=${ idOf( 'builder-test-deck-variable' ) }` );
			const start = page.getByRole( 'link', {
				name: 'Build a complete with this deck',
			} );
			const own = await start.getAttribute( 'href' );
			await page
				.locator( 'select[name="attribute_width"]' )
				.selectOption( '8.5' );
			await expect( start ).toHaveAttribute(
				'href',
				new RegExp( `cbuild%5Bdeck%5D=${ wide }$` )
			);
			await page
				.locator( 'select[name="attribute_width"]' )
				.selectOption( '' );
			await expect( start ).toHaveAttribute( 'href', own ?? '' );
			await page
				.locator( 'select[name="attribute_width"]' )
				.selectOption( '8.5' );
			// WooCommerce slides the variation's details open, which moves the button under a press: in WebKit one
			// press in eight landed where it had been and went nowhere. The press waits for the page to be still.
			await page.waitForFunction(
				() =>
					0 ===
					(
						window as unknown as {
							jQuery: ( selector: string ) => { length: number };
						}
					 ).jQuery( ':animated' ).length
			);
			await start.click();
			await expect( heading( page ) ).toHaveText(
				/: Choose your trucks$/
			);
			expect( page.url() ).toContain( `cbuild%5Bdeck%5D=${ wide }` );
		} finally {
			runPhp(
				`foreach ( array( 'builder-test-deck-variable-8.0', 'builder-test-deck-variable-8.5', 'builder-test-deck-variable' ) as $s ) { $i = wc_get_product_id_by_sku( $s ); if ( $i ) { wc_get_product( $i )->delete( true ); } }`
			);
		}
	} );

	test( "a deck's page starts a build with that deck, past the width question", async ( {
		page,
	}, info ) => {
		await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
		const start = page.getByRole( 'link', {
			name: 'Build a complete with this deck',
		} );
		await expect( start ).toHaveAttribute( 'rel', /nofollow/ );
		await start.click();
		await expect( heading( page ) ).toHaveText( /: Choose your trucks$/ );
		expect( page.url() ).toContain(
			`cbuild%5Bdeck%5D=${ id( 'builder-test-deck-8-25' ) }`
		);
		await expect( page.locator( '.cbuild-width' ) ).toBeHidden();
		// The deck's width came with it (the spec reviewer's M10 m3): back on the deck step, 8.25″ is the width shown.
		await page
			.locator( '.cbuild-progress__step' )
			.first()
			.getByRole( 'link' )
			.click();
		await expect( heading( page ) ).toHaveText( /: Choose your deck$/ );
		await expect(
			page.locator( '.cbuild-width__option[aria-pressed="true"]' )
		).toContainText( inLocale( info.project.name, '8.25' ) );
	} );
} );

// #133 (D-377): "Build a complete with this deck" is drawn as the theme's own button, the page's second action. Measured
// at 1280 px before: Kadence drew it as Add to cart's twin, 3 px taller; Storefront in its grey secondary; Twenty
// Twenty-Five as an underlined link. Now on each theme it is a button, with Add to cart's weight and, on a line of its
// own, its height, in colours that are not Add to cart's.
test.describe( 'The start button on a theme (#133)', () => {
	/**
	 * Tabs from Add to cart to the start button on a theme, and expects its outline, ring or background to differ from
	 * how it rests.
	 *
	 * @param page    The page.
	 * @param context The browser context, for the theme's cookie.
	 * @param baseURL The site.
	 * @param theme   The theme.
	 * @param engine  The browser's engine: WebKit, as Safari, tabs to a link with Option held.
	 * @param styles  A theme's own rules, added to the page.
	 */
	const tabbedMark = async (
		page: Page,
		context: BrowserContext,
		baseURL: string | undefined,
		theme: string,
		engine: string,
		styles = ''
	): Promise< void > => {
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: theme, url: baseURL || SITE },
		] );
		await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
		if ( styles ) {
			await page.addStyleTag( { content: styles } );
		}
		const start = page.locator( '.cbuild-start__link' );
		await expect( start ).toBeVisible();
		const focusMark = () =>
			start.evaluate( ( link ) => {
				const style = getComputedStyle( link );
				return `outline ${ style.outlineStyle } ${ style.outlineWidth } ${ style.outlineColor } | shadow ${ style.boxShadow } | background ${ style.backgroundColor }`;
			} );
		const rest = await focusMark();
		await page.locator( 'form.cart .single_add_to_cart_button' ).focus();
		for ( let i = 0; i < 10; i++ ) {
			await page.keyboard.press(
				'webkit' === engine ? 'Alt+Tab' : 'Tab'
			);
			if (
				await start.evaluate(
					( link ) => link === link.ownerDocument.activeElement
				)
			) {
				break;
			}
		}
		await expect( start, 'tabbed to' ).toBeFocused();
		await expect
			.poll( focusMark, { message: `a mark of focus, from ${ rest }` } )
			.not.toBe( rest );
	};

	for ( const theme of [ 'kadence', 'storefront', 'twentytwentyfive' ] ) {
		test( `is ${ theme }'s button, second to Add to cart, with its weight and height`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		}, info ) => {
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			if ( ! info.project.name.includes( 'phone' ) ) {
				await page.setViewportSize( { width: 1280, height: 900 } );
			}
			await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
			const start = page.locator( '.cbuild-start__link' );
			await expect( start ).toBeVisible();
			const add = page.locator( 'form.cart .single_add_to_cart_button' );
			await expect( add ).toBeVisible();
			// Fonts in, so both are measured as drawn.
			await page.evaluate( async () => {
				await document.fonts.ready;
			} );
			const drawn = ( locator: Locator ) =>
				locator.evaluate( ( element ) => {
					const style = getComputedStyle( element );
					const range = document.createRange();
					range.selectNodeContents( element );
					// The words' lines, by their tops.
					const lines = new Set(
						[ ...range.getClientRects() ].map( ( rect ) =>
							Math.round( rect.top )
						)
					).size;
					return {
						weight: style.fontWeight,
						height: element.getBoundingClientRect().height,
						lines,
						underline:
							style.textDecorationLine.includes( 'underline' ),
						background: style.backgroundColor,
						color: style.color,
						border:
							'none' !== style.borderTopStyle &&
							parseFloat( style.borderTopWidth ) > 0,
						ring: 'none' !== style.boxShadow,
						filled: ! /^(transparent|rgba\(.*, 0\))$/.test(
							style.backgroundColor
						),
						padding:
							parseFloat( style.paddingInlineStart ) > 0 &&
							parseFloat( style.paddingInlineEnd ) > 0,
					};
				} );
			const ours = await drawn( start );
			const theirs = await drawn( add );

			expect( ours.underline, 'no underline' ).toBe( false );
			expect(
				ours.filled || ours.border || ours.ring,
				'a background, a border or a ring'
			).toBe( true );
			expect( ours.padding, 'padding' ).toBe( true );
			// In line with the quantity and Add to cart: on Twenty Twenty-Five WooCommerce's add-to-cart form block pads
			// every child after its first by 4 px (M12's walkthrough, #133).
			const edges = await start.evaluate( ( link ) => {
				const form = document
					.querySelector( 'form.cart' )!
					.getBoundingClientRect();
				const box = link.getBoundingClientRect();
				return 'rtl' === getComputedStyle( link ).direction
					? { ours: box.right, theirs: form.right }
					: { ours: box.left, theirs: form.left };
			} );
			expect(
				Math.abs( edges.ours - edges.theirs ),
				`in line with the quantity and Add to cart: ${ edges.ours } and ${ edges.theirs } px`
			).toBeLessThanOrEqual( 0.5 );
			if ( ! javaScriptEnabled ) {
				// Add to cart's colours, weight and height as drawn are read by the page's script (D-377); without it
				// the button is the theme's own as it stands, which on Kadence and Twenty Twenty-Five is Add to cart's.
				return;
			}
			expect(
				`${ ours.background } | ${ ours.color }`,
				"its colours are not Add to cart's"
			).not.toBe( `${ theirs.background } | ${ theirs.color }` );
			expect( ours.weight, "Add to cart's weight" ).toBe( theirs.weight );
			if ( 1 === ours.lines && 1 === theirs.lines ) {
				expect(
					Math.abs( ours.height - theirs.height ),
					`Add to cart's height: ${ ours.height } and ${ theirs.height } px`
				).toBeLessThanOrEqual( 0.5 );
			}
			// The auditor's M12 minor 7: axe on the button as the theme draws it, with its colours copied (D-377).
			await axe(
				page,
				`the start button on ${ theme }`,
				'.cbuild-start'
			);
		} );

		// A theme's button may change size at a breakpoint: the copy follows Add to cart from 1280 px to a phone's 390.
		test( `keeps Add to cart's weight and height on ${ theme } from 1280 to 390 px`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		}, info ) => {
			test.skip( ! javaScriptEnabled, 'The copy is the script (D-377).' );
			test.skip(
				info.project.name.includes( 'phone' ),
				'A desktop window, narrowed.'
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize( { width: 1280, height: 900 } );
			await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
			const start = page.locator( '.cbuild-start__link' );
			await expect( start ).toBeVisible();
			await page.evaluate( async () => {
				await document.fonts.ready;
			} );
			/**
			 * What the two buttons differ by: weight, font size, line height, the block padding with the borders, and the
			 * height where both are on one line.
			 */
			const differ = () =>
				start.evaluate( ( ours ) => {
					const add = document.querySelector(
						'form.cart .single_add_to_cart_button'
					);
					if ( ! add ) {
						return [ 'no Add to cart' ];
					}
					const read = ( element: Element ) => {
						const style = getComputedStyle( element );
						const range = document.createRange();
						range.selectNodeContents( element );
						const block = [
							'paddingTop',
							'paddingBottom',
							'borderTopWidth',
							'borderBottomWidth',
						].reduce(
							( sum, key ) =>
								sum +
								parseFloat(
									style.getPropertyValue(
										key.replace(
											/[A-Z]/g,
											( letter ) =>
												`-${ letter.toLowerCase() }`
										)
									)
								),
							0
						);
						return {
							weight: style.fontWeight,
							size: style.fontSize,
							line: style.lineHeight,
							block: Math.round( block * 2 ) / 2,
							height: element.getBoundingClientRect().height,
							lines: new Set(
								[ ...range.getClientRects() ].map( ( rect ) =>
									Math.round( rect.top )
								)
							).size,
						};
					};
					const a = read( add );
					const b = read( ours );
					const off = [ 'weight', 'size', 'line', 'block' ]
						.filter(
							( key ) =>
								a[ key as keyof typeof a ] !==
								b[ key as keyof typeof b ]
						)
						.map(
							( key ) =>
								`${ key }: ${ b[ key as keyof typeof b ] } and ${
									a[ key as keyof typeof a ]
								}`
						);
					if (
						1 === a.lines &&
						1 === b.lines &&
						Math.abs( a.height - b.height ) > 0.5
					) {
						off.push( `height: ${ b.height } and ${ a.height }` );
					}
					return off;
				} );
			await expect
				.poll( differ, { message: 'at 1280 px' } )
				.toEqual( [] );
			await page.setViewportSize( { width: 390, height: 844 } );
			await expect.poll( differ, { message: 'at 390 px' } ).toEqual( [] );
		} );

		// The auditor's third pass on M12, a minor: the outline pinned background and ring at `:focus` too, so a
		// theme's focus mark drawn as a shadow or a background was lost. Tabbed to, the button shows the theme's mark.
		test( `shows ${ theme }'s focus mark when tabbed to`, async ( {
			page,
			context,
			baseURL,
			browserName,
		} ) => {
			await tabbedMark( page, context, baseURL, theme, browserName );
		} );
	}

	// A theme whose focus mark is a shadow alone, as a theme may draw it on `.button:focus`.
	test( "shows a theme's focus mark drawn as a shadow when tabbed to", async ( {
		page,
		context,
		baseURL,
		browserName,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			"The theme's rules are added by script; the outline is the script's (D-377)."
		);
		await tabbedMark(
			page,
			context,
			baseURL,
			'kadence',
			browserName,
			'.button:focus{outline:none;box-shadow:0 0 0 3px rgb(26, 32, 44)}'
		);
	} );
} );

// The auditor's third pass on M12, should-fix: where the theme draws both buttons alike, the outline took Add to cart's
// fill as the start button's words and ring, read against nothing. On Kadence with a light accent and dark words, as
// a shop sets them in the Customizer, that fill read about 1.6:1 on the white page (WCAG 1.4.3, 1.4.11).
test.describe( 'The start button on a light accent', () => {
	/**
	 * Kadence's button colours in the site's theme mods, as the Customizer's Buttons panel keeps them; null puts back
	 * what the site had. The e2e theme fixture shows Kadence by cookie and leaves the active theme as it is, so
	 * Kadence reads the active theme's mods.
	 *
	 * @param colours The fill and the words, or null.
	 */
	const accent = ( colours: { fill: string; words: string } | null ): void =>
		runPhp(
			null === colours
				? "$o = 'theme_mods_' . get_option( 'stylesheet' ); $b = get_option( 'cbuild_e2e_theme_mods', null ); delete_option( 'cbuild_e2e_theme_mods' ); if ( is_array( $b ) && is_array( $b['mods'] ?? null ) ) { update_option( $o, $b['mods'] ); } elseif ( is_array( $b ) ) { delete_option( $o ); }"
				: `$o = 'theme_mods_' . get_option( 'stylesheet' ); if ( false === get_option( 'cbuild_e2e_theme_mods' ) ) { update_option( 'cbuild_e2e_theme_mods', array( 'mods' => get_option( $o, null ) ), false ); } $m = (array) get_option( $o, array() ); $m['buttons_background'] = array( 'color' => '${ colours.fill }', 'hover' => '${ colours.fill }' ); $m['buttons_color'] = array( 'color' => '${ colours.words }', 'hover' => '${ colours.words }' ); update_option( $o, $m );`
		);

	test.beforeAll( () => accent( { fill: '#F7C948', words: '#1A202C' } ) );
	test.afterAll( () => accent( null ) );

	test( 'reads against the page on Kadence, and is still second to Add to cart', async ( {
		page,
		context,
		baseURL,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			"The outline is the script's (D-377); without it the button is the theme's own."
		);
		await context.addCookies( [
			{
				name: 'cbuild_e2e_theme',
				value: 'kadence',
				url: baseURL || SITE,
			},
		] );
		await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
		const start = page.locator( '.cbuild-start__link' );
		const add = page.locator( 'form.cart .single_add_to_cart_button' );
		await expect( start ).toBeVisible();
		await expect(
			add,
			"the theme mods are Kadence's: Add to cart's light fill"
		).toHaveCSS( 'background-color', 'rgb(247, 201, 72)' );
		await axe(
			page,
			'the start button on a light accent',
			'.cbuild-start'
		);
		const colours = ( locator: Locator ) =>
			locator.evaluate( ( element ) => {
				const style = getComputedStyle( element );
				return `${ style.backgroundColor } | ${ style.color }`;
			} );
		expect(
			await colours( start ),
			"its colours are not Add to cart's"
		).not.toBe( await colours( add ) );
	} );
} );

// Cowork's review of 1.2.0, F32 (#149, D-416): on the demo a Kadence Blocks row coloured its links cream, and the start
// button, a link, took them on Add to cart's yellow fill: 1.20:1. The script took the button for Add to cart's twin only
// where both its fill and its words were Add to cart's, so it kept the theme's unreadable button. The hostile theme
// fixture draws the same: a dark row round the add-to-cart form whose links are cream, and every button yellow.
test.describe( "The start button under a row's link colour (#149)", () => {
	for ( const theme of [ 'twentytwentyfive', 'kadence', 'storefront' ] ) {
		test( `reads at 4.5:1 on ${ theme }, and is still second to Add to cart`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		} ) => {
			test.skip(
				! javaScriptEnabled,
				"Add to cart's colours are read by the script (D-377); without it the button is the theme's own."
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
				{
					name: 'cbuild_e2e_hostile',
					value: '1',
					url: baseURL || SITE,
				},
			] );
			await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
			const start = page.locator( '.cbuild-start__link' );
			const add = page.locator( 'form.cart .single_add_to_cart_button' );
			await expect( start ).toBeVisible();
			await expect(
				page.locator( '.cbuild-e2e-row .cbuild-start__link' ),
				"the fixture's row is round the button"
			).toHaveCount( 1 );
			// The words against the fill they sit on: the button's own, or the first one behind it that is not
			// transparent.
			const contrast = () =>
				start.evaluate( ( link ) => {
					const channels = ( colour: string ) =>
						( colour.match( /[\d.]+/g ) ?? [] ).map( Number );
					const luminance = ( colour: string ) =>
						channels( colour )
							.slice( 0, 3 )
							.map( ( part ) => {
								const c = part / 255;
								return c <= 0.04045
									? c / 12.92
									: ( ( c + 0.055 ) / 1.055 ) ** 2.4;
							} )
							.reduce(
								( sum, c, at ) =>
									sum + [ 0.2126, 0.7152, 0.0722 ][ at ]! * c,
								0
							);
					let fill = 'rgb(255, 255, 255)';
					for (
						let element: Element | null = link;
						element;
						element = element.parentElement
					) {
						const ground =
							getComputedStyle( element ).backgroundColor;
						const parts = channels( ground );
						if ( 3 === parts.length || ( parts[ 3 ] ?? 0 ) > 0 ) {
							fill = ground;
							break;
						}
					}
					const words = getComputedStyle( link ).color;
					const [ a, b ] = [ luminance( words ), luminance( fill ) ];
					return {
						words,
						fill,
						ratio:
							Math.round(
								( ( Math.max( a, b ) + 0.05 ) /
									( Math.min( a, b ) + 0.05 ) ) *
									100
							) / 100,
					};
				} );
			const read = await contrast();
			expect(
				read.ratio,
				`its words ${ read.words } on ${ read.fill }`
			).toBeGreaterThanOrEqual( 4.5 );
			// Focused, the button is the theme's own with its focus mark (D-377), and its words still read.
			await start.focus();
			const focused = await contrast();
			expect(
				focused.ratio,
				`focused, its words ${ focused.words } on ${ focused.fill }`
			).toBeGreaterThanOrEqual( 4.5 );
			await start.blur();
			const colours = ( locator: Locator ) =>
				locator.evaluate( ( element ) => {
					const style = getComputedStyle( element );
					return `${ style.backgroundColor } | ${ style.color }`;
				} );
			expect(
				await colours( start ),
				"its colours are not Add to cart's"
			).not.toBe( await colours( add ) );
			await axe(
				page,
				`the start button under a row's link colour on ${ theme }`,
				'.cbuild-start'
			);
		} );
	}
} );

// #115 (D-349): a kit's Show on its parts' pages. Off on every kit that offers the deck, its page has no "Build a
// complete with this deck"; on again, the button is back where it was.
test.describe( "A kit's Show on its parts' pages", () => {
	/**
	 * Every kit's Show on its parts' pages set, or taken off the kit's settings as a kit saved before 1.2.0 has it.
	 *
	 * @param on True or false, or null to take the setting off.
	 */
	const startButton = ( on: boolean | null ): void =>
		runPhp(
			`foreach ( wc_get_products( array( 'type' => 'cbuild_kit', 'limit' => -1, 'status' => 'any' ) ) as $k ) { $d = $k->documents()['_cbuild_settings'] ?? array( 'schema_version' => 1 ); if ( null === ${ JSON.stringify(
				on
			) } ) { unset( $d['start_button'] ); } else { $d['start_button'] = ${ JSON.stringify(
				on
			) }; } $k->setDocument( '_cbuild_settings', $d ); $k->save(); }`
		);

	test.afterAll( () => startButton( null ) );

	test( "off, a deck's page has no button; on, it is back", async ( {
		page,
	} ) => {
		const deck = `/?p=${ id( 'builder-test-deck-8-25' ) }`;
		const start = page.getByRole( 'link', {
			name: 'Build a complete with this deck',
		} );
		await page.goto( deck );
		await expect( start ).toBeVisible();
		const before = await start.getAttribute( 'href' );

		startButton( false );
		await page.goto( deck );
		await expect(
			page.locator( 'form.cart' ).first(),
			'the page is drawn'
		).toBeVisible();
		await expect( start ).toHaveCount( 0 );

		startButton( true );
		await page.goto( deck );
		await expect( start ).toHaveAttribute( 'href', before ?? '' );
	} );
} );

// #113 (D-348): a kit's Builder page. Chosen in the Build tab, the kit's own page sends a shopper there with the build
// they came with, and "Build a complete with this deck" opens it; a page that has lost the kit's block is not used.
test.describe( "A kit's Builder page", () => {
	/**
	 * The test kit's Builder page, by the address of a page on the site, or taken off its settings with null.
	 *
	 * @param url The page's address, or null.
	 */
	const builderPage = ( url: string | null ): void =>
		runPhp(
			`$k = wc_get_product( ${ fixture().kit } ); $d = $k->documents()['_cbuild_settings'] ?? array( 'schema_version' => 1 ); $u = ${ JSON.stringify(
				url
			) }; if ( null === $u ) { unset( $d['builder_page'] ); } else { $d['builder_page'] = url_to_postid( $u ); } $k->setDocument( '_cbuild_settings', $d ); $k->save();`
		);

	test.afterAll( () => builderPage( null ) );

	test( "the kit's page and a deck's page send a shopper to it, and not to a page without the kit's block", async ( {
		page,
	} ) => {
		const there = fixture().page;
		const kitPage = `/?p=${ fixture().kit }`;
		const deck = `/?p=${ id( 'builder-test-deck-8-25' ) }`;
		const start = page.getByRole( 'link', {
			name: 'Build a complete with this deck',
		} );

		builderPage( there );
		await page.goto( `${ kitPage }&cbuild[step]=trucks` );
		expect( page.url().startsWith( there ), page.url() ).toBe( true );
		expect( decodeURIComponent( page.url() ) ).toContain(
			'cbuild[step]=trucks'
		);
		await expect( heading( page ) ).toBeVisible();
		await page.goto( deck );
		expect( await start.getAttribute( 'href' ) ).toMatch(
			new RegExp( `^${ there.replace( /[.*+?^${}()|[\]\\]/g, '\\$&' ) }` )
		);

		// The shelf's page holds another kit's builder: the kit's own page is used.
		builderPage( fixture().shelf.page );
		await page.goto( kitPage );
		expect( page.url().startsWith( there ) ).toBe( false );
		await expect( heading( page ) ).toBeVisible();
		await page.goto( deck );
		expect( ( await start.getAttribute( 'href' ) ) ?? '' ).not.toContain(
			new URL( there ).search || new URL( there ).pathname
		);
	} );
} );

// #141 (D-410): a kit is bought through its builder, never alone, so neither its page nor the Builder block's page
// prints a Product in its JSON-LD, which would offer the kit at its assembly fee. A part's page keeps WooCommerce's. On
// each theme, since a classic theme and a block theme make WooCommerce's markup from different templates. Yoast SEO's
// graph is held by BuilderYoastTest, in the integration site where Yoast is.
test.describe( 'No Product markup for a kit (#141)', () => {
	/**
	 * Every `@type` in the page's JSON-LD.
	 *
	 * @param page The page.
	 */
	const types = async ( page: Page ): Promise< string[] > =>
		page
			.locator( 'script[type="application/ld+json"]' )
			.evaluateAll( ( scripts ) => {
				const found: string[] = [];
				const walk = ( node: unknown ): void => {
					if ( Array.isArray( node ) ) {
						node.forEach( walk );
					} else if ( node && 'object' === typeof node ) {
						for ( const [ key, value ] of Object.entries( node ) ) {
							if ( '@type' === key ) {
								found.push( ...[ value ].flat().map( String ) );
							}
							walk( value );
						}
					}
				};
				scripts.forEach( ( script ) =>
					walk( JSON.parse( script.textContent ?? 'null' ) )
				);
				return found;
			} );

	for ( const theme of [ 'kadence', 'storefront', 'twentytwentyfive' ] ) {
		test( `on ${ theme } a kit's page and the Builder page print no Product, and a part's page keeps it`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.goto( '/product/builder-test-kit/' );
			await expect( page.locator( '.cbuild' ).first() ).toBeAttached();
			expect( await types( page ), "the kit's page" ).not.toContain(
				'Product'
			);
			await page.goto( fixture().page );
			await expect( page.locator( '.cbuild' ).first() ).toBeAttached();
			expect( await types( page ), 'the Builder page' ).not.toContain(
				'Product'
			);
			await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
			expect( await types( page ), "a part's page" ).toContain(
				'Product'
			);
		} );
	}
} );

// #81 (D-301): a deck picked at one width, whose look is made in another, moves to that look when the shopper asks for
// the other width; the status says so, and the cart gets the other width's variation (M11 audit, finding 10). One
// variable deck, two widths of one look, made for the test and taken away after it.
test.describe( 'A picked deck in a new width (#81)', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			! [ 'chromium-desktop', 'webkit-phone' ].includes(
				info.project.name
			),
			"One desktop and one phone engine: the move is the server's, the same in each."
		);
	} );

	test.beforeAll( () => {
		runPhp(
			`$a = new WC_Product_Attribute(); $a->set_name( 'width' ); $a->set_options( array( '8.0', '8.5' ) ); $a->set_variation( true ); $p = new WC_Product_Variable( wc_get_product_id_by_sku( 'builder-test-deck-look' ) ); $p->set_name( 'Builder test look deck' ); $p->set_sku( 'builder-test-deck-look' ); $p->set_status( 'publish' ); $p->set_attributes( array( $a ) ); $p->set_category_ids( array( get_term_by( 'slug', 'builder-test-deck', 'product_cat' )->term_id ) ); $p->save(); foreach ( array( '8.0' => array( '8.0000', '31.5000' ), '8.5' => array( '8.5000', '32.0000' ) ) as $o => $w ) { $v = new WC_Product_Variation( wc_get_product_id_by_sku( 'builder-test-deck-look-' . $o ) ); $v->set_parent_id( $p->get_id() ); $v->set_sku( 'builder-test-deck-look-' . $o ); $v->set_attributes( array( 'width' => $o ) ); $v->set_regular_price( '60' ); $v->set_stock_status( 'instock' ); $v->update_meta_data( '_cbuild_specs', array( 'deck_width' => $w[0], 'deck_length' => $w[1], 'hole_pattern' => 'new_school' ) ); $cbuild_index( $v ); } WC_Product_Variable::sync( $p->get_id() ); $cbuild_index( wc_get_product( $p->get_id() ) );`
		);
	} );

	test.afterAll( () => {
		runPhp(
			`foreach ( array( 'builder-test-deck-look-8.0', 'builder-test-deck-look-8.5', 'builder-test-deck-look' ) as $s ) { $i = wc_get_product_id_by_sku( $s ); if ( $i ) { wc_get_product( $i )->delete( true ); } }`
		);
	} );

	test( 'moves the deck to its look in the width asked, says so, and the cart gets that width', async ( {
		page,
	} ) => {
		const idOf = ( sku: string ) =>
			Number(
				wp( `eval "echo wc_get_product_id_by_sku( '${ sku }' );"` )
			);
		const narrow = idOf( 'builder-test-deck-look-8.0' );
		const wide = idOf( 'builder-test-deck-look-8.5' );
		expect( narrow ).toBeGreaterThan( 0 );
		expect( wide ).toBeGreaterThan( 0 );
		await page.goto( linkTo( { deck: narrow, step: 'deck' } ) );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'data-ready',
			''
		);
		await ride( page, '8.5″' );
		await expect( page.locator( '.cbuild__status' ) ).toContainText(
			'Builder test look deck is now 8.5″.'
		);
		await expect
			.poll( () =>
				new URL( page.url() ).searchParams.get( 'cbuild[deck]' )
			)
			.toBe( String( wide ) );

		// The rest of the build at the review, from the address the move left, and Add to cart.
		const review = new URL( page.url() );
		for ( const [ slot, sku ] of [
			[ 'trucks', 'builder-test-trucks-149' ],
			[ 'wheels', 'builder-test-wheels-54' ],
			[ 'bearings', 'builder-test-bearings' ],
			[ 'hardware', 'builder-test-bolts-1' ],
			[ 'grip', 'builder-test-grip' ],
		] as const ) {
			review.searchParams.set( `cbuild[${ slot }]`, String( id( sku ) ) );
		}
		review.searchParams.set( 'cbuild[step]', 'review' );
		review.searchParams.delete( 'cbuild[_width]' );
		await page.goto( review.toString() );
		await expect( heading( page ) ).toHaveText(
			'Step 7 of 7: Check your build'
		);
		await page
			.locator( '.cbuild' )
			.getByRole( 'button', { name: 'Add to cart' } )
			.first()
			.click();
		await expect( page.locator( '.cbuild-review__added' ) ).toBeVisible();
		const cart = ( await (
			await page.request.get( '/wp-json/wc/store/v1/cart' )
		).json() ) as {
			items: { id: number; variation: { value: string }[] }[];
		};
		const deck = cart.items.find( ( item ) =>
			[ narrow, wide ].includes( item.id )
		);
		expect( deck?.id, "the cart's deck line" ).toBe( wide );
		expect( deck?.variation.map( ( one ) => one.value ) ).toEqual( [
			'8.5',
		] );
	} );
} );

/**
 * #80 (Cowork's 1.0.0 F1): a mouse press that goes down while the builder hydrates and comes up after. Hydration takes
 * the pressed control out of the page and puts it back, and a browser drops the click of a press whose element left the
 * page between down and up. Each yield of the runtime is slowed here, so hydration is still under way when the press
 * goes down, as it is for some 100 ms on a real page in WebKit (D-296).
 */
test.describe( 'A mouse press held while the builder hydrates', () => {
	const cases: Array< [ string, string, string ] > = [
		[ 'a width', '', '.cbuild-width__option' ],
		[
			'a deck card',
			'?cbuild%5B_width%5D=8in&cbuild%5B_on%5D=deck',
			'.cbuild-card:not(.cbuild-card--sold-out) label.cbuild-card__body[for]',
		],
		[ 'a ready build', '', '.cbuild-presets button' ],
	];
	for ( const [ name, query, selector ] of cases ) {
		test( `goes through on ${ name }`, async ( { context }, info ) => {
			test.skip(
				! info.project.name.endsWith( '-desktop' ),
				"A mouse on a desktop: a finger's tap is not affected, and a phone's layout settles under the press."
			);
			// A page of the context's own: the fixture's page waits for the ready mark after each load, and this press
			// must go down before it.
			const page = await context.newPage();
			// Every control in view unscrolled, so the press lands where the test measured it.
			await page.setViewportSize( { width: 1440, height: 1800 } );
			await page.addInitScript( () => {
				Object.defineProperty( window, 'scheduler', {
					value: {
						yield: () =>
							new Promise( ( done ) => setTimeout( done, 150 ) ),
					},
				} );
			} );
			let moved = 0;
			let pressed = false;
			page.on( 'request', ( request ) => {
				if (
					pressed &&
					( ( request.isNavigationRequest() &&
						request.frame() === page.mainFrame() ) ||
						/cbuild\/v1\/kits\/\d+\/state/.test( request.url() ) )
				) {
					moved++;
				}
			} );
			await page.goto( fixture().page + query, {
				waitUntil: 'domcontentloaded',
			} );
			const control = page.locator( selector ).first();
			await control.waitFor();
			const box = await control.boundingBox();
			expect( box ).not.toBeNull();
			expect(
				await page.locator( '.cbuild[data-ready]' ).count(),
				'pressed before the builder is hydrated'
			).toBe( 0 );
			await page.mouse.move(
				( box?.x ?? 0 ) + ( box?.width ?? 0 ) / 2,
				( box?.y ?? 0 ) + ( box?.height ?? 0 ) / 2
			);
			pressed = true;
			await page.mouse.down();
			await page.locator( '.cbuild[data-ready]' ).waitFor( {
				state: 'attached',
				timeout: 15000,
			} );
			await page.mouse.up();
			await expect
				.poll( () => moved, {
					message: "a page load or the store's answer",
					timeout: 5000,
				} )
				.toBeGreaterThan( 0 );
			await page.close();
		} );
	}
} );

/**
 * #120 (Cowork's 1.1.0 F22): a mouse press that goes down before the store's script has run, so before its press
 * listener, and comes up after the ready mark. The script is held back 500 ms, and until the press, so the press lands
 * while the page is the server's alone; hydration then takes the pressed control out and puts it back, as in #80.
 */
test.describe( "A mouse press that starts before the builder's script runs", () => {
	test( 'goes through on a width', async ( { context }, info ) => {
		test.skip(
			! info.project.name.endsWith( '-desktop' ),
			"A mouse on a desktop: a finger's tap is not affected, and a phone's layout settles under the press."
		);
		// A page of the context's own: the fixture's page waits for the ready mark after each load.
		const page = await context.newPage();
		await page.setViewportSize( { width: 1440, height: 1800 } );
		let down: () => void = () => undefined;
		const pressing = new Promise< void >( ( done ) => {
			down = done;
		} );
		let served = false;
		await page.route( /\/build\/builder\/view\.js/, async ( route ) => {
			await Promise.all( [
				pressing,
				new Promise( ( done ) => setTimeout( done, 500 ) ),
			] );
			served = true;
			await route.continue();
		} );
		let moved = 0;
		let pressed = false;
		page.on( 'request', ( request ) => {
			if (
				pressed &&
				( ( request.isNavigationRequest() &&
					request.frame() === page.mainFrame() ) ||
					/cbuild\/v1\/kits\/\d+\/state/.test( request.url() ) )
			) {
				moved++;
			}
		} );
		await page.goto( fixture().page, { waitUntil: 'commit' } );
		const control = page.locator( '.cbuild-width__option' ).first();
		await control.waitFor();
		const box = await control.boundingBox();
		expect( box ).not.toBeNull();
		await page.mouse.move(
			( box?.x ?? 0 ) + ( box?.width ?? 0 ) / 2,
			( box?.y ?? 0 ) + ( box?.height ?? 0 ) / 2
		);
		pressed = true;
		await page.mouse.down();
		expect( served, "pressed before the store's script came" ).toBe(
			false
		);
		down();
		await page.locator( '.cbuild[data-ready]' ).waitFor( {
			state: 'attached',
			timeout: 15000,
		} );
		await page.mouse.up();
		await expect
			.poll( () => moved, {
				message: "a page load or the store's answer",
				timeout: 5000,
			} )
			.toBeGreaterThan( 0 );
		await page.close();
	} );
} );

test.describe( "The panel's own scroll while the builder hydrates (#94)", () => {
	test( 'stays where the shopper put it', async ( { context }, info ) => {
		test.skip(
			! info.project.name.endsWith( '-desktop' ),
			'The panel scrolls inside itself on a desktop; on a tablet or a phone the page scrolls.'
		);
		// A page of the context's own: the fixture's page waits for the ready mark after each load, and this scroll
		// must come before it.
		const page = await context.newPage();
		// A window short enough that the panel scrolls.
		await page.setViewportSize( { width: 1280, height: 720 } );
		await page.addInitScript( () => {
			Object.defineProperty( window, 'scheduler', {
				value: {
					yield: () =>
						new Promise( ( done ) => setTimeout( done, 150 ) ),
				},
			} );
		} );
		await page.goto( fixture().page, { waitUntil: 'domcontentloaded' } );
		const body = page.locator( '.cbuild__body' );
		await body.waitFor();
		const scrolled = await body.evaluate( ( element ) => {
			element.scrollTop = element.scrollHeight;
			return element.scrollTop;
		} );
		expect(
			await page.locator( '.cbuild[data-ready]' ).count(),
			'scrolled before the builder is hydrated'
		).toBe( 0 );
		expect( scrolled, 'the panel scrolls' ).toBeGreaterThan( 100 );
		await page.locator( '.cbuild[data-ready]' ).waitFor( {
			state: 'attached',
			timeout: 15000,
		} );
		expect(
			await body.evaluate( ( element ) => element.scrollTop ),
			"the panel's scroll after hydration"
		).toBe( scrolled );
		await page.close();
	} );
} );

test.describe( 'A deck with no layer image on the stage (#82)', () => {
	test( "shows the deck's photo at the board's size, and the stage sticks only once there is a part to show", async ( {
		page,
	}, info ) => {
		// A radio sends nothing without the script: the build link's case, without it, is the test of the top above.
		test.skip( info.project.name === 'no-js', 'a pick needs the script' );
		// Stacked, as on a tablet or a phone: the stage's container narrower than 64rem.
		const narrow = ( page.viewportSize()?.width ?? 0 ) < 1024;
		await page.goto( fixture().page );
		const stage = page.locator( '.cbuild__stage' );
		const position = () =>
			stage.evaluate(
				( element ) => getComputedStyle( element ).position
			);
		// Before a pick, on a tablet or a phone, the outline scrolls with the page and leaves the screen to the step.
		expect( await position() ).toBe( narrow ? 'static' : 'relative' );

		// The 8.25 in deck has no layer image: its photo takes the board's place.
		await pickDeck( page, '8.25' );
		const picture = page.locator( '#cbuild-picture' );
		await expect( picture ).toHaveClass( /cbuild-picture--hero/ );
		const photo = picture.locator(
			'.cbuild-picture__item:first-child .cbuild-picture__photo'
		);
		await expect( photo ).toBeVisible();
		await expect
			.poll( async () => {
				const [ shown, room ] = await Promise.all( [
					photo.boundingBox(),
					stage.boundingBox(),
				] );
				return ( shown?.height ?? 0 ) / ( room?.height ?? 1 );
			}, "the deck's photo fills the stage's height as the board would" )
			.toBeGreaterThan( narrow ? 0.4 : 0.6 );
		expect( await position() ).toBe( narrow ? 'sticky' : 'relative' );

		// With a layer image the board is drawn, as before.
		await pickDeck( page, '8.0' );
		await expect( picture ).not.toHaveClass( /cbuild-picture--hero/ );
		await expect(
			picture.locator(
				'.cbuild-board:not(.cbuild-board--top) .cbuild-board__layer--deck'
			)
		).toHaveCount( 1 );
	} );

	// Milestone 11's walkthrough: on a kit's own page the photo was drawn at its file's height, since a theme's or
	// WooCommerce's rule for every image (`img`, `.woocommerce img`: height auto) outweighed the size the stage gives it
	// at no weight. Taller than its item, it lay under the deck's own caption and the other parts' tiles.
	for ( const theme of [ 'twentytwentyfive', 'storefront', 'kadence' ] ) {
		for ( const [ width, height ] of [
			[ 1280, 900 ],
			[ 390, 844 ],
		] as const ) {
			test( `keeps the deck's photo inside its own place on ${ theme } at ${ width } by ${ height }, clear of its caption and the other tiles`, async ( {
				page,
				context,
				baseURL,
			}, info ) => {
				test.skip(
					info.project.name === 'no-js',
					"Without the script the photo stands in for no board: the picture lists each part as a tile of its own, the deck's caption under it."
				);
				await context.addCookies( [
					{
						name: 'cbuild_e2e_theme',
						value: theme,
						url: baseURL || SITE,
					},
				] );
				await page.setViewportSize( { width, height } );
				const kit = new URL(
					'/product/builder-test-kit/',
					baseURL || SITE
				);
				kit.searchParams.set(
					'cbuild[deck]',
					String( id( 'builder-test-deck-8-25' ) )
				);
				kit.searchParams.set(
					'cbuild[trucks]',
					String( id( 'builder-test-trucks-139' ) )
				);
				kit.searchParams.set( 'cbuild[step]', 'wheels' );
				await page.goto( kit.toString() );
				const picture = page.locator(
					'.cbuild__stage #cbuild-picture'
				);
				await expect( picture ).toHaveClass( /cbuild-picture--hero/ );
				const photo = picture.locator(
					'.cbuild-picture__item:first-child .cbuild-picture__photo'
				);
				await expect( photo ).toBeVisible();
				await expect
					.poll( () =>
						photo.evaluate(
							( image ) =>
								( image as HTMLImageElement ).complete &&
								( image as HTMLImageElement ).naturalHeight > 0
						)
					)
					.toBe( true );
				const wrong = () =>
					picture.evaluate( ( figure ) => {
						const items = [
							...figure.querySelectorAll< HTMLElement >(
								'.cbuild-picture__item'
							),
						].filter(
							( item ) => item.getClientRects().length > 0
						);
						const first = items[ 0 ];
						const image = first
							?.querySelector( '.cbuild-picture__photo' )
							?.getBoundingClientRect();
						const label = first
							?.querySelector( '.cbuild-picture__label' )
							?.getBoundingClientRect();
						if ( ! first || ! image || ! label ) {
							return [ 'no photo' ];
						}
						const own = first.getBoundingClientRect();
						const meets = ( box: DOMRect ) =>
							Math.min( box.right, image.right ) -
								Math.max( box.left, image.left ) >
								0.5 &&
							Math.min( box.bottom, image.bottom ) -
								Math.max( box.top, image.top ) >
								0.5;
						const found: string[] = [];
						const past = {
							top: own.top - image.top,
							foot: image.bottom - own.bottom,
							start: own.left - image.left,
							end: image.right - own.right,
						};
						for ( const [ edge, by ] of Object.entries( past ) ) {
							if ( by > 1 ) {
								found.push(
									`past its item's ${ edge } by ${ Math.round(
										by
									) }`
								);
							}
						}
						if ( meets( label ) ) {
							found.push( 'under its caption' );
						}
						items.slice( 1 ).forEach( ( item, at ) => {
							if ( meets( item.getBoundingClientRect() ) ) {
								found.push( `under tile ${ at + 2 }` );
							}
						} );
						return found;
					} );
				await expect
					.poll( wrong, "the deck's photo, where it lies" )
					.toEqual( [] );
			} );
		}
	}
} );

// Cowork's 1.0.0 review, F14 (#99): the deck step's cards are compact, the deck small and upright at the start from its
// layer image, cut out on the card's own ground, or its photo where it has none; then the brand, the name, the price
// and "Also in…" or "Only in…". The card keeps its reading order, its 44 px target and its focus ring; other steps keep
// theirs. Rendered by the server, so the no-js project holds it too. Six in view at 1440 by 900 is the shelf kit's
// test, which lists enough decks (shelf.spec.ts).
test.describe( 'Compact deck cards (F14)', () => {
	const card = ( page: Page, width: '8.0' | '8.25' | '8.5' ) =>
		page.locator( '.cbuild-cards > li' ).filter( {
			has: page.getByRole( 'radio', {
				name: `Choose Builder test deck ${ width }`,
			} ),
		} );

	test( "draws a deck's layer image small and upright, its photo where it has none, and keeps other steps' cards", async ( {
		page,
	} ) => {
		await page.goto( linkTo( { step: 'deck', _width: '8.5in' } ) );
		await expect( page.locator( '.cbuild-cards' ).first() ).toHaveClass(
			/cbuild-cards--compact/
		);
		const drawn = card( page, '8.5' ).locator( '.cbuild-card__photo' );
		await expect( drawn ).toHaveClass( /cbuild-card__photo--drawn/ );
		// The layer image's WebP renditions, made for the picture (D-194, D-214), offered at the card's small size.
		await expect( drawn ).toHaveAttribute( 'srcset', /\.webp \d+w/ );
		// The server's markup keeps core's `auto, ` for a lazy image (wp-includes/media.php:1267, 2165); the store's re-render has
		// the size alone. Either way the rendition is chosen for the card's small size.
		await expect( drawn ).toHaveAttribute( 'sizes', /^(auto, )?\d+px$/ );
		const shape = await drawn.evaluate( ( image ) => ( {
			width: Number( image.getAttribute( 'width' ) ),
			height: Number( image.getAttribute( 'height' ) ),
		} ) );
		expect( shape.height, 'upright' ).toBeGreaterThan( shape.width );
		await expect(
			card( page, '8.5' ).locator( '.cbuild-card__also' )
		).toHaveText( /^(Also|Only) in / );

		const box = await card( page, '8.5' ).boundingBox();
		expect( box?.height ?? 0, 'a 44 px target' ).toBeGreaterThanOrEqual(
			44
		);
		expect( box?.height ?? 999, 'compact' ).toBeLessThanOrEqual( 170 );
		// The radio is named by its part, and the rest of the card describes it, as before.
		await expect(
			card( page, '8.5' ).getByRole( 'radio' )
		).toHaveAttribute( 'aria-describedby', /-about .*-notes$/ );

		await page.goto( linkTo( { step: 'deck', _width: '8.25in' } ) );
		await expect(
			card( page, '8.25' ).locator( '.cbuild-card__photo' )
		).not.toHaveClass( /cbuild-card__photo--drawn/ );

		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
		await expect( page.locator( '.cbuild-cards' ).first() ).not.toHaveClass(
			/cbuild-cards--compact/
		);
	} );

	// #130 (D-377): the deck's picture and its words are kept apart by the builder's gap, the space the cards keep, not
	// the theme's smallest step (0.5rem by default, less on some themes), at which the words read as pressed against a
	// tall deck. At least 16 px on each theme, beside a layer image (8.5″) and a photo (8.25″), and the words still
	// clear of the chosen card's tick (D-322). The rows are the shelf kit's test, which lists enough decks.
	for ( const theme of [ 'twentytwentyfive', 'storefront', 'kadence' ] ) {
		test( `keeps 16 px or more between the deck and its words on ${ theme }, clear of the tick`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			if ( ! info.project.name.includes( 'phone' ) ) {
				await page.setViewportSize( { width: 1440, height: 900 } );
			}
			for ( const width of [ '8.5', '8.25' ] as const ) {
				await page.goto(
					linkTo( { step: 'deck', _width: `${ width }in` } )
				);
				const item = card( page, width );
				await expect(
					item.locator( '.cbuild-card__photo' )
				).toBeVisible();
				// Chosen as the shopper's own pick paints it, so its tick is drawn; nothing is sent.
				await item
					.locator( '.cbuild-card__control' )
					.evaluate( ( radio ) => {
						( radio as HTMLInputElement ).checked = true;
					} );
				const measured = await item.evaluate( ( li ) => {
					const rtl = 'rtl' === getComputedStyle( li ).direction;
					const box = li
						.querySelector( '.cbuild-card__photo' )
						?.getBoundingClientRect();
					const words = [
						...li.querySelectorAll(
							'.cbuild-card__body > :not(.cbuild-card__photo)'
						),
					];
					const boxes = words
						.map( ( word ) => word.getBoundingClientRect() )
						.filter( ( word ) => word.width > 0 );
					const gap = box
						? Math.min(
								...boxes.map( ( word ) =>
									rtl
										? box.left - word.right
										: word.left - box.right
								)
							)
						: -1;
					// The tick where the chosen card draws it: its ::before, from the card's padding box.
					const own = li.getBoundingClientRect();
					const look = getComputedStyle( li );
					const tick = getComputedStyle( li, '::before' );
					const size = parseFloat( tick.width );
					const top =
						own.top +
						parseFloat( look.borderTopWidth ) +
						parseFloat( tick.top );
					const left = rtl
						? own.left +
							parseFloat( look.borderLeftWidth ) +
							parseFloat( tick.left )
						: own.right -
							parseFloat( look.borderRightWidth ) -
							parseFloat( tick.right ) -
							size;
					// Any line of the words under the tick.
					const range = document.createRange();
					const under = words.some( ( word ) => {
						range.selectNodeContents( word );
						return [ ...range.getClientRects() ].some(
							( line ) =>
								line.width > 0 &&
								line.left < left + size &&
								line.right > left &&
								line.top < top + size &&
								line.bottom > top
						);
					} );
					return {
						gap,
						under,
						tick: 'none' !== tick.content && size > 0,
					};
				} );
				expect(
					measured.gap,
					`${ width }: the deck and its words, 16 px apart or more`
				).toBeGreaterThanOrEqual( 15.5 );
				expect( measured.tick, `${ width }: the tick is drawn` ).toBe(
					true
				);
				expect(
					measured.under,
					`${ width }: the words clear of the tick`
				).toBe( false );
			}
		} );
	}

	test( 'rings the compact card, not the radio inside it, when the keyboard reaches it', async ( {
		page,
	}, info ) => {
		test.skip( info.project.name.includes( 'phone' ), 'a keyboard' );
		await page.goto( linkTo( { step: 'deck', _width: '8.5in' } ) );
		// A key pressed first, so the focus that follows is the keyboard's, and :focus-visible.
		await page.keyboard.press( 'Shift' );
		await card( page, '8.5' ).getByRole( 'radio' ).focus();
		const ring = await card( page, '8.5' ).evaluate(
			( item ) => getComputedStyle( item ).outlineStyle
		);
		expect( ring ).not.toBe( 'none' );
	} );
} );

/**
 * #131 (D-391): every step's cards put the part's picture beside its words, as the deck step's compact cards do, in a
 * square 5rem box, the size the square photos of trucks, wheels and bearings still show their colour at. Each step holds
 * at least as many whole cards in a screen as it did with the photo on top: measured on the three themes at 1440 by 900
 * and 390 by 844, the step's first card at the window's top and the list filled to 24 with copies of it, the bar's top
 * the screen's foot. The deck step's compact cards are as they were.
 */
test.describe( 'Cards side by side on every step (#131)', () => {
	// The step, with a part picked in each step before it.
	const at = ( step: string ): string => {
		const picks: Record< string, number > = {};
		for ( const [ slot, part ] of Object.entries( {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-52' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1' ),
		} ) ) {
			if ( slot === step ) {
				break;
			}
			picks[ slot ] = part;
		}
		return linkTo( { ...picks, step } );
	};
	const steps = [ 'trucks', 'wheels', 'hardware', 'grip' ] as const;

	for ( const step of steps ) {
		test( `puts the picture in a 5rem square beside the words on the ${ step } step`, async ( {
			page,
			javaScriptEnabled,
		} ) => {
			test.skip(
				! javaScriptEnabled,
				'Measured by a script in the page; the layout is the stylesheet’s, the same without one.'
			);
			await page.goto( at( step ) );
			const item = page
				.locator( '.cbuild-cards:not(.cbuild-cards--compact) > li' )
				.filter( {
					has: page.locator( '.cbuild-card__photo:visible' ),
				} )
				.first();
			await expect( item ).toBeVisible();
			const measured = await item.evaluate( ( li ) => {
				const rem = parseFloat(
					getComputedStyle( document.documentElement ).fontSize
				);
				const photo = li
					.querySelector( '.cbuild-card__photo' )
					?.getBoundingClientRect();
				const words = [
					...li.querySelectorAll(
						'.cbuild-card__body > :not(.cbuild-card__photo)'
					),
				]
					.map( ( word ) => word.getBoundingClientRect() )
					.filter( ( word ) => word.width > 0 );
				return {
					rem,
					width: photo?.width ?? 0,
					height: photo?.height ?? 0,
					// Beside: no word over the picture's column, and the first word starts within its height.
					clear: words.every(
						( word ) =>
							! photo ||
							word.left >= photo.right ||
							word.right <= photo.left
					),
					beside:
						!! photo &&
						( words[ 0 ]?.top ?? Number.POSITIVE_INFINITY ) <
							photo.bottom,
				};
			} );
			expect( measured.width, 'the picture 5rem wide' ).toBeCloseTo(
				5 * measured.rem,
				0
			);
			expect( measured.height, 'and square' ).toBeCloseTo(
				measured.width,
				0
			);
			expect( measured.clear, 'no word over the picture' ).toBe( true );
			expect( measured.beside, 'the words beside it' ).toBe( true );
			// The radio is named by its part and the rest of the card describes it, in the same order as before.
			await expect( item.getByRole( 'radio' ) ).toHaveAttribute(
				'aria-describedby',
				/-about .*-notes$/
			);
			await axe( page, `the ${ step } step's cards`, '.cbuild-step' );
		} );
	}

	/**
	 * The whole cards above the bar on each step, the step's first card at the window's top and the list filled to 24
	 * with copies of it.
	 *
	 * @param page   The page.
	 * @param byHand Whether the first card's pick and rating are hidden here, to make it plain (D-393). Without, the
	 *               card is as the server drew it, and must have neither shown (D-409).
	 */
	const countWhole = async (
		page: Page,
		byHand: boolean
	): Promise< Record< string, number > > => {
		const counted: Record< string, number > = {};
		for ( const step of [ ...steps, 'bearings' ] as const ) {
			await page.goto( at( step ) );
			await page.waitForSelector( '.cbuild[data-ready]' );
			await page.evaluate( () => document.fonts.ready );
			const list = page
				.locator( '.cbuild-cards:not(.cbuild-cards--sold-out)' )
				.first();
			if ( ! byHand ) {
				await expect(
					list.locator(
						'.cbuild-card__picked:visible, .cbuild-card__rating:visible'
					),
					`no pick or rating on the ${ step } step`
				).toHaveCount( 0 );
			}
			counted[ step ] = await list.evaluate( ( cards, hide ) => {
				const first = cards.querySelector( ':scope > li' );
				if ( hide ) {
					// A plain card: no ready build's pick and no rating (D-393).
					first
						?.querySelectorAll< HTMLElement >(
							'.cbuild-card__picked, .cbuild-card__rating'
						)
						.forEach( ( extra ) => {
							extra.hidden = true;
						} );
				}
				while (
					first &&
					cards.querySelectorAll( ':scope > li' ).length < 24
				) {
					cards.append( first.cloneNode( true ) );
				}
				first?.scrollIntoView( { block: 'start' } );
				const bar = document
					.querySelector( '.cbuild-bar:not([hidden])' )
					?.getBoundingClientRect();
				const foot =
					bar && bar.top > 0 && bar.top < innerHeight
						? bar.top
						: innerHeight;
				return [ ...cards.querySelectorAll( ':scope > li' ) ].filter(
					( item ) => {
						const box = item.getBoundingClientRect();
						return box.top >= 0 && box.bottom <= foot;
					}
				).length;
			}, byHand );
		}
		return counted;
	};

	// The step's first card at the window's top, the list filled to 24 with copies of it: the whole cards above the bar.
	// Before #131, at 1440 by 900: trucks 2, 2, 3; wheels 2, 2, 3; bearings 8, 8, 9; hardware 8, 8, 6; grip 2, 2, 3 on
	// Kadence, Twenty Twenty-Five and Storefront. At 390 by 844 each step's count is as it was (D-391). The card is
	// plain: a ready build's pick and a rating are a line more where a part has them, held by the test after (D-393).
	const held: Record<
		string,
		Record<
			'desktop' | 'phone',
			Record< ( typeof steps )[ number ] | 'bearings', number >
		>
	> = {
		kadence: {
			desktop: {
				trucks: 6,
				wheels: 6,
				bearings: 8,
				hardware: 12,
				grip: 8,
			},
			phone: { trucks: 2, wheels: 2, bearings: 3, hardware: 3, grip: 3 },
		},
		twentytwentyfive: {
			desktop: {
				trucks: 6,
				wheels: 6,
				bearings: 8,
				hardware: 10,
				grip: 6,
			},
			phone: { trucks: 2, wheels: 2, bearings: 3, hardware: 3, grip: 2 },
		},
		storefront: {
			desktop: {
				trucks: 6,
				wheels: 6,
				bearings: 9,
				hardware: 12,
				grip: 9,
			},
			phone: { trucks: 2, wheels: 2, bearings: 2, hardware: 3, grip: 2 },
		},
	};
	for ( const [ theme, sizes ] of Object.entries( held ) ) {
		test( `holds as many whole cards in a screen on every step on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				'chromium-desktop' !== info.project.name &&
					'chromium-phone' !== info.project.name,
				'A layout count, one engine at each width.'
			);
			const phone = info.project.name.includes( 'phone' );
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize(
				phone
					? { width: 390, height: 844 }
					: { width: 1440, height: 900 }
			);
			const counted = await countWhole( page, true );
			for ( const [ step, least ] of Object.entries(
				sizes[ phone ? 'phone' : 'desktop' ]
			) ) {
				expect(
					counted[ step ],
					`whole ${ step } cards in a screen`
				).toBeGreaterThanOrEqual( least );
			}
		} );

		// D-409: with the kit's Ready-build picks on cards and Ratings on cards off, the server draws each card plain,
		// with no line kept for either, and the step holds as many whole cards as #131's plain card does.
		test( `holds as many whole cards with the kit's picks and ratings off on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				'chromium-desktop' !== info.project.name &&
					'chromium-phone' !== info.project.name,
				'A layout count, one engine at each width.'
			);
			const phone = info.project.name.includes( 'phone' );
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize(
				phone
					? { width: 390, height: 844 }
					: { width: 1440, height: 900 }
			);
			cardSwitches( false, false );
			try {
				const counted = await countWhole( page, false );
				for ( const [ step, least ] of Object.entries(
					sizes[ phone ? 'phone' : 'desktop' ]
				) ) {
					expect(
						counted[ step ],
						`whole ${ step } cards in a screen, both off`
					).toBeGreaterThanOrEqual( least );
				}
			} finally {
				cardSwitches( null, null );
			}
		} );

		// A part a ready build picks and the shop rates (the trucks 139, Street's, rated 4.5): its rating sits beside
		// its price, so the card is a line taller at most, the "Picked for" line, at either width (#137, D-393).
		test( `a card with a ready build's pick and a rating is a line taller at most on ${ theme }`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				'chromium-desktop' !== info.project.name &&
					'chromium-phone' !== info.project.name,
				'A layout measure, one engine at each width.'
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.setViewportSize(
				info.project.name.includes( 'phone' )
					? { width: 390, height: 844 }
					: { width: 1440, height: 900 }
			);
			await page.goto( at( 'trucks' ) );
			await page.waitForSelector( '.cbuild[data-ready]' );
			await page.evaluate( () => document.fonts.ready );
			const card = page
				.locator( '.cbuild-cards > li' )
				.filter( { hasText: 'Picked for Street' } )
				.first();
			await expect(
				card.locator( '.cbuild-card__rating' ),
				'rated'
			).toBeVisible();
			const measured = await card.evaluate( ( item ) => {
				const picked = item.querySelector< HTMLElement >(
					'.cbuild-card__picked'
				);
				const rating = item.querySelector< HTMLElement >(
					'.cbuild-card__rating'
				);
				const price = item.querySelector( '.cbuild-card__price' );
				const rich = item.getBoundingClientRect().height;
				const line = picked ? picked.getBoundingClientRect().height : 0;
				const beside =
					!! rating &&
					!! price &&
					Math.abs(
						rating.getBoundingClientRect().top -
							price.getBoundingClientRect().top
					) < price.getBoundingClientRect().height;
				if ( picked && rating ) {
					picked.hidden = true;
					rating.hidden = true;
				}
				const plain = item.getBoundingClientRect().height;
				const gap = parseFloat(
					getComputedStyle(
						item.querySelector( '.cbuild-card__about' ) ?? item
					).rowGap
				);
				return { rich, plain, line, gap, beside };
			} );
			expect( measured.line, 'one line of small type' ).toBeLessThan(
				30
			);
			expect(
				measured.rich - measured.plain,
				'the pick and the rating add a line at most'
			).toBeLessThanOrEqual( measured.line + measured.gap + 1 );
			expect( measured.beside, 'the rating beside the price' ).toBe(
				true
			);
		} );
	}

	// Nothing moves as a picture loads: each card's picture box is set before its file comes (CLS 0).
	test( 'moves nothing as the pictures load', async ( {
		page,
		browserName,
		javaScriptEnabled,
	} ) => {
		test.skip(
			'chromium' !== browserName || ! javaScriptEnabled,
			'Layout shift entries are Chromium’s, read by a script.'
		);
		// Every file held back until the cards are in view, so they are laid out before any picture comes: a shift is
		// counted only where it is seen.
		let release = (): void => undefined;
		const gate = new Promise< void >( ( resolve ) => {
			release = resolve;
		} );
		await page.route(
			/\/wp-content\/uploads\/.*\.(png|jpe?g|webp)$/,
			async ( route ) => {
				await gate;
				await route.continue();
			}
		);
		await page.addInitScript( () => {
			( window as unknown as { shifted: number } ).shifted = 0;
			new PerformanceObserver( ( list ) => {
				for ( const entry of list.getEntries() as Array<
					PerformanceEntry & {
						value: number;
						hadRecentInput: boolean;
						sources: Array< { node: Node | null } >;
					}
				> ) {
					// The step's own shifts: a theme's header settling as its font arrives is the theme's.
					const ours = entry.sources.some(
						( source ) =>
							source.node instanceof Element &&
							null !== source.node.closest( '.cbuild-step' )
					);
					if ( ! entry.hadRecentInput && ours ) {
						( window as unknown as { shifted: number } ).shifted +=
							entry.value;
					}
				}
			} ).observe( { type: 'layout-shift', buffered: true } );
		} );
		await page.goto( at( 'wheels' ) );
		const photos = page.locator(
			'.cbuild-cards:not(.cbuild-cards--sold-out) .cbuild-card__photo:not([hidden])'
		);
		await expect( photos.first() ).toBeAttached();
		await page
			.locator( '.cbuild-cards' )
			.first()
			.evaluate( ( list ) => list.scrollIntoView( { block: 'start' } ) );
		await page.evaluate(
			() =>
				new Promise( ( resolve ) =>
					requestAnimationFrame( () =>
						requestAnimationFrame( resolve )
					)
				)
		);
		release();
		await expect
			.poll( () =>
				photos.evaluateAll( ( images ) =>
					images.every(
						( image ) =>
							( image as HTMLImageElement ).complete &&
							( image as HTMLImageElement ).naturalWidth > 0
					)
				)
			)
			.toBe( true );
		// Two frames for the last image's paint, and its shift if it made one.
		await page.evaluate(
			() =>
				new Promise( ( resolve ) =>
					requestAnimationFrame( () =>
						requestAnimationFrame( resolve )
					)
				)
		);
		const shifted = await page.evaluate(
			() => ( window as unknown as { shifted: number } ).shifted
		);
		expect( shifted, "the step's layout shift as the pictures load" ).toBe(
			0
		);
	} );
} );

/**
 * Issue #137 (D-392): a part a ready build picks says so on its card, and a part with reviews shows its stars and,
 * in words a screen reader says with the card, its rating and count. The fixture's 139 trucks are Street's and rated 5
 * and 4; its 52 mm wheels are Street's, unrated; its 54 mm wheels are no ready build's, rated 3. Cruiser holds a sold
 * out wheel, so it is no tile, and no card names it: Street's bearings, Cruiser's too, say Street alone.
 */
test.describe( "Ready builds' picks and ratings on the cards (#137)", () => {
	const wheels = () =>
		linkTo( {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			step: 'wheels',
		} );
	const card = ( page: Page, name: RegExp ) =>
		page
			.locator( '.cbuild-step .cbuild-cards > li' )
			.filter( { has: page.getByRole( 'radio', { name } ) } );

	// The average in the language's decimal separator: a comma in fr-ca's site, French with its words in English (D-435).
	const half = () => ( 'fr-ca' === test.info().project.name ? '4,5' : '4.5' );

	test( 'says which ready build picks a part, and its rating, in words beside the stars', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
		const rated = card( page, /^Choose Builder test trucks 139/ );
		await expect( rated.locator( '.cbuild-card__picked' ) ).toHaveText(
			'Picked for Street'
		);
		await expect( rated.locator( '.cbuild-card__rated' ) ).toHaveText(
			`${ half() } out of 5, 2 reviews`
		);
		// One star and the short score are seen, and stand for the sentence, which is said (D-393): the rating beside
		// the price, read after it, and the pick after the fit.
		const star = rated.locator( '.cbuild-card__star' );
		await expect( star ).toBeVisible();
		await expect( star ).toHaveAttribute( 'aria-hidden', 'true' );
		const score = rated.locator( '.cbuild-card__score' );
		await expect( score ).toHaveText( `${ half() } (2)` );
		await expect( score ).toBeVisible();
		await expect( score ).toHaveAttribute( 'aria-hidden', 'true' );
		await expect( rated.getByRole( 'radio' ) ).toHaveAccessibleDescription(
			new RegExp( `${ half() } out of 5, 2 reviews.*Picked for Street` )
		);
		await expect(
			rated.getByRole( 'radio' ),
			'the short score is not said'
		).not.toHaveAccessibleDescription( /\(2\)/ );
		// Measured from the boxes Playwright reads, so the page needs no script of its own.
		const starBox = await star.boundingBox();
		const scoreBox = await score.boundingBox();
		const rtl =
			'rtl' === ( await page.locator( 'html' ).getAttribute( 'dir' ) );
		const shown = {
			width: starBox?.width ?? 0,
			height: starBox?.height ?? 0,
			// Before it in the line's own direction: left of it, or right of it in a right-to-left page.
			before:
				!! starBox &&
				!! scoreBox &&
				( rtl
					? starBox.x >= scoreBox.x + scoreBox.width - 1
					: starBox.x + starBox.width <= scoreBox.x + 1 ),
		};
		expect( shown.width, 'one star' ).toBeCloseTo( shown.height, 0 );
		expect( shown.height, 'drawn' ).toBeGreaterThan( 8 );
		expect( shown.before, 'before the score' ).toBe( true );

		await page.goto( wheels() );
		const picked = card( page, /^Choose Builder test wheels 52/ );
		await expect( picked.locator( '.cbuild-card__picked' ) ).toHaveText(
			'Picked for Street'
		);
		await expect( picked.locator( '.cbuild-card__rating' ) ).toBeHidden();
		const reviewed = card( page, /^Choose Builder test wheels 54/ );
		await expect( reviewed.locator( '.cbuild-card__picked' ) ).toBeHidden();
		await expect( reviewed.locator( '.cbuild-card__rated' ) ).toHaveText(
			'3 out of 5, 1 review'
		);
		await expect( reviewed.locator( '.cbuild-card__score' ) ).toHaveText(
			'3 (1)'
		);
		if ( javaScriptEnabled ) {
			// axe runs its script in the page, which the no-js project cannot.
			await axe(
				page,
				"the wheel step's picks and ratings",
				'.cbuild-step'
			);
			// The store draws them again from the answer, as the server did.
			await choose( page, 'Builder test wheels 54' );
			await expect(
				reviewed.locator( '.cbuild-card__rated' )
			).toHaveText( '3 out of 5, 1 review' );
			await expect( picked.locator( '.cbuild-card__picked' ) ).toHaveText(
				'Picked for Street'
			);
		}

		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				wheels: id( 'builder-test-wheels-52' ),
				step: 'bearings',
			} )
		);
		await expect(
			card( page, /^Choose Builder test bearings/ ).locator(
				'.cbuild-card__picked'
			)
		).toHaveText( 'Picked for Street' );
	} );

	// D-409, the kit's switches: Ready-build picks on cards off, no card says a ready build picks it; Ratings on cards
	// off, no card has a star or a score. Each leaves the other, and the card keeps no room for what it no longer says:
	// it is as tall as the same card with that line hidden by hand.
	test.describe( "with the kit's switches off (D-409)", () => {
		test.describe.configure( { mode: 'serial' } );
		test.afterAll( () => cardSwitches( null, null ) );
		const trucks = () =>
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } );
		// The 139s' card's height, measured in the page, with what is said hidden by hand first.
		const tall = ( page: Page, hide: string ): Promise< number > =>
			card( page, /^Choose Builder test trucks 139/ ).evaluate(
				( item, selector ) => {
					item.querySelectorAll< HTMLElement >( selector ).forEach(
						( shown ) => {
							shown.hidden = true;
						}
					);
					return item.getBoundingClientRect().height;
				},
				hide
			);

		test( 'Ready-build picks on cards off: no card says one, the rating stays, and no line is kept', async ( {
			page,
			javaScriptEnabled,
		} ) => {
			let plain = 0;
			if ( javaScriptEnabled ) {
				cardSwitches( null, null );
				await page.goto( trucks() );
				await expect(
					card( page, /^Choose Builder test trucks 139/ ).locator(
						'.cbuild-card__picked'
					)
				).toHaveText( 'Picked for Street' );
				plain = await tall( page, '.cbuild-card__picked' );
			}
			cardSwitches( false, null );
			for ( const link of [ trucks(), wheels() ] ) {
				await page.goto( link );
				await expect(
					page.locator( '.cbuild-step .cbuild-cards' ).first()
				).toBeVisible();
				await expect(
					page.locator( '.cbuild-card__picked:visible' )
				).toHaveCount( 0 );
				await expect(
					page.locator( '.cbuild-step' )
				).not.toContainText( 'Picked for' );
			}
			await page.goto( trucks() );
			const rated = card( page, /^Choose Builder test trucks 139/ );
			await expect( rated.locator( '.cbuild-card__rated' ) ).toHaveText(
				`${ half() } out of 5, 2 reviews`
			);
			await expect( rated.locator( '.cbuild-card__star' ) ).toBeVisible();
			await expect(
				rated.getByRole( 'radio' )
			).not.toHaveAccessibleDescription( /Picked for/ );
			if ( javaScriptEnabled ) {
				expect(
					await tall( page, '.cbuild-card__nothing' ),
					'no room kept for the line'
				).toBeCloseTo( plain, 0 );
				// The store draws the cards again from the answer, as the server did.
				await choose( page, 'Builder test trucks 139' );
				await expect(
					page.locator( '.cbuild-card__picked:visible' )
				).toHaveCount( 0 );
				await expect(
					rated.locator( '.cbuild-card__rated' )
				).toHaveText( `${ half() } out of 5, 2 reviews` );
			}
		} );

		test( 'Ratings on cards off: no star or score, the pick stays, and no room is kept', async ( {
			page,
			javaScriptEnabled,
		} ) => {
			let plain = 0;
			if ( javaScriptEnabled ) {
				cardSwitches( null, null );
				await page.goto( trucks() );
				await expect(
					card( page, /^Choose Builder test trucks 139/ ).locator(
						'.cbuild-card__score'
					)
				).toHaveText( `${ half() } (2)` );
				plain = await tall( page, '.cbuild-card__rating' );
			}
			cardSwitches( null, false );
			for ( const link of [ trucks(), wheels() ] ) {
				await page.goto( link );
				await expect(
					page.locator( '.cbuild-step .cbuild-cards' ).first()
				).toBeVisible();
				await expect(
					page.locator(
						'.cbuild-card__rating:visible, .cbuild-card__star:visible, .cbuild-card__score:visible'
					)
				).toHaveCount( 0 );
			}
			await page.goto( trucks() );
			const picked = card( page, /^Choose Builder test trucks 139/ );
			await expect( picked.locator( '.cbuild-card__picked' ) ).toHaveText(
				'Picked for Street'
			);
			await expect(
				picked.getByRole( 'radio' )
			).not.toHaveAccessibleDescription( /out of 5/ );
			if ( javaScriptEnabled ) {
				expect(
					await tall( page, '.cbuild-card__nothing' ),
					'no room kept for the rating'
				).toBeCloseTo( plain, 0 );
				await choose( page, 'Builder test trucks 139' );
				await expect(
					page.locator( '.cbuild-card__rating:visible' )
				).toHaveCount( 0 );
				await expect(
					picked.locator( '.cbuild-card__picked' )
				).toHaveText( 'Picked for Street' );
			}
		} );
	} );

	test( "says them in French, its decimal separator the language's", async ( {
		page,
	}, info ) => {
		test.skip( 'fr-ca' !== info.project.name, 'The fr-ca project alone' );
		await page.setExtraHTTPHeaders( {
			'x-cbuild-e2e-locale': 'fr_CA',
			'x-cbuild-e2e-translated': '1',
		} );
		await page.goto(
			linkTo( { deck: id( 'builder-test-deck-8-0' ), step: 'trucks' } )
		);
		const rated = page
			.locator( '.cbuild-step .cbuild-cards > li' )
			.filter( {
				has: page.getByRole( 'radio', {
					name: /^Choisir Builder test trucks 139/,
				} ),
			} );
		await expect( rated.locator( '.cbuild-card__picked' ) ).toHaveText(
			'Choisi pour Street'
		);
		// The language's decimal separator, a comma in French, as the count is written: a rating is no price, whose
		// separator, a point in the fixture's shop, is the shop's (D-435).
		await expect( rated.locator( '.cbuild-card__rated' ) ).toHaveText(
			'4,5 sur 5, 2 avis'
		);
		await expect( rated.locator( '.cbuild-card__score' ) ).toHaveText(
			'4,5 (2)'
		);
		await axe( page, 'the trucks step in French', '.cbuild-step' );
	} );
} );

/**
 * Issue #143: what a companion plugin (Mature Content) may rely on. The builder sends `cbuild:rendered` once it is
 * hydrated and once after each answer it draws, and an element another plugin puts in the stage or the step's form
 * stays there through a pick, a step change and the change panel, as the Interactivity API redraws around it.
 */
test.describe( 'What another plugin may rely on (#143)', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			info.project.name === 'no-js',
			'The event and the redraws are the script’s.'
		);
	} );

	/**
	 * Each `cbuild:rendered` the page has heard, its detail and whether it bubbled from the builder.
	 *
	 * @param page The page.
	 */
	const rendered = ( page: Page ) =>
		page.evaluate(
			() =>
				( window as unknown as { cbuildRendered: unknown[] } )
					.cbuildRendered
		);

	/**
	 * Whether each element the test put in the builder is still where it was put: connected, in the same parent, and
	 * still its first or last element child. In the place above the cards it is the place's only child (#151).
	 *
	 * @param page The page.
	 */
	const kept = ( page: Page ) =>
		page.evaluate( () =>
			(
				window as unknown as {
					cbuildOwn: {
						element: Element;
						parent: Element;
						first: boolean;
					}[];
				}
			 ).cbuildOwn.map(
				( { element, parent, first } ) =>
					element.isConnected &&
					element.parentElement === parent &&
					element ===
						( first
							? parent.firstElementChild
							: parent.lastElementChild )
			)
		);

	test( 'sends cbuild:rendered once hydrated and once per answer, and keeps what another plugin adds to the stage and the step', async ( {
		page,
	}, info ) => {
		await page.addInitScript( () => {
			const heard: unknown[] = [];
			(
				window as unknown as { cbuildRendered: unknown[] }
			 ).cbuildRendered = heard;
			document.addEventListener( 'cbuild:rendered', ( event ) => {
				heard.push( {
					...( event as CustomEvent ).detail,
					bubbles: event.bubbles,
					fromBuilder:
						event.target instanceof Element &&
						event.target.matches( '.cbuild' ),
				} );
			} );
		} );
		await page.goto(
			linkTo( {
				deck: id( 'builder-test-deck-8-0' ),
				trucks: id( 'builder-test-trucks-139' ),
				step: 'deck',
			} )
		);
		await mark( page );
		const kit = fixture().kit;
		const seen = ( step: string ) => ( {
			kit,
			step,
			bubbles: true,
			fromBuilder: true,
		} );
		const steps: ReturnType< typeof seen >[] = [ seen( 'deck' ) ];
		await expect.poll( () => rendered( page ) ).toEqual( steps );

		// A script that loads after hydration finds `data-ready` already set, and hears no earlier event replayed: it
		// checks the attribute first, then listens for the redraws to come (D-376).
		const late = await page.evaluate( async () => {
			const heard: unknown[] = [];
			const ready =
				null !== document.querySelector( '.cbuild[data-ready]' );
			document.addEventListener( 'cbuild:rendered', ( event ) => {
				heard.push( ( event as CustomEvent ).detail );
			} );
			( window as unknown as { cbuildLate: unknown[] } ).cbuildLate =
				heard;
			await new Promise( ( done ) =>
				window.requestAnimationFrame( () =>
					window.setTimeout( done, 100 )
				)
			);
			return { ready, heard: [ ...heard ] };
		} );
		expect( late ).toEqual( { ready: true, heard: [] } );

		// Another plugin's own elements, put in once the builder is ready, where Mature Content 1.6.0 puts them: its
		// veil the last child of the stage, and its notice the first child of the step's form. From 1.3.0 a third in the
		// place just above the step's cards, where Mature Content asked to put its notice (#151, D-407).
		await page.evaluate( () => {
			const own = ( parent: Element, first: boolean ) => {
				const element = document.createElement( 'div' );
				element.className = 'another-plugin';
				element.textContent = 'Another plugin';
				if ( first ) {
					parent.prepend( element );
				} else {
					parent.append( element );
				}
				return { element, parent, first };
			};
			(
				window as unknown as {
					cbuildOwn: {
						element: Element;
						parent: Element;
						first: boolean;
					}[];
				}
			 ).cbuildOwn = [
				own( document.querySelector( '.cbuild__stage' )!, false ),
				own( document.querySelector( '.cbuild-step' )!, true ),
				own(
					document.querySelector( '.cbuild-step__above-cards' )!,
					true
				),
			];
		} );
		const all = [ true, true, true ];

		// A list narrowed by a width: an answer on the same step.
		await ride( page, inLocale( info.project.name, '8.5″' ) );
		steps.push( seen( 'deck' ) );
		await expect.poll( () => rendered( page ) ).toEqual( steps );
		expect( await kept( page ) ).toEqual( all );

		expect(
			await page.evaluate(
				() =>
					( window as unknown as { cbuildLate: unknown[] } )
						.cbuildLate
			),
			'the late listener hears the redraws after it, each once'
		).toEqual( [ { kit, step: 'deck' } ] );

		// A pick that opens the change panel, which hides the step's form.
		await pickDeck( page, '8.5' );
		await expect( page.locator( '.cbuild-changes' ) ).toBeVisible();
		steps.push( seen( 'deck' ) );
		await expect.poll( () => rendered( page ) ).toEqual( steps );
		expect( await kept( page ) ).toEqual( all );

		// Use these: the panel gives way to the deck step it opened over, with the closest trucks.
		await page.locator( '.cbuild-changes__use button' ).click();
		await expect( page.locator( '.cbuild-step' ) ).toBeVisible();
		steps.push( seen( 'deck' ) );
		await expect.poll( () => rendered( page ) ).toEqual( steps );
		expect( await kept( page ) ).toEqual( all );

		// A fold opened, the widths behind their line: no answer, so no event.
		await page.locator( '.cbuild-width__chosen' ).click();
		await expect(
			page.locator( 'details.cbuild-width__pick' )
		).toHaveAttribute( 'open', '' );
		await page.waitForTimeout( 300 );
		expect( await rendered( page ), 'a fold opened' ).toEqual( steps );
		expect( await kept( page ) ).toEqual( all );

		// Next twice: the trucks, with the closest picked, then the step after them, which has nothing picked.
		let after = 'deck';
		for ( let move = 0; move < 2; move++ ) {
			const from = after;
			await page.locator( '.cbuild-bar__next:visible' ).click();
			await expect
				.poll( () =>
					new URL( page.url() ).searchParams.get( 'cbuild[step]' )
				)
				.not.toBe( from );
			after = String(
				new URL( page.url() ).searchParams.get( 'cbuild[step]' )
			);
			steps.push( seen( after ) );
			await expect.poll( () => rendered( page ) ).toEqual( steps );
			expect( await kept( page ) ).toEqual( all );
		}

		// A pick on it: nothing is sent until its answer is drawn.
		let release: () => void = () => undefined;
		const held = new Promise< void >( ( done ) => {
			release = done;
		} );
		const state = ( url: URL ) => url.pathname.endsWith( '/state' );
		await page.route( state, async ( route ) => {
			await held;
			// A request the store has given up on, as the one asked ahead for Next once a pick is made, is gone.
			await route.continue().catch( () => undefined );
		} );
		await page
			.locator(
				'.cbuild-step .cbuild-cards:not(.cbuild-cards--sold-out) .cbuild-card__control:not([disabled]):not(:checked)'
			)
			.first()
			.check();
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'true'
		);
		expect( await rendered( page ), 'not before the answer' ).toEqual(
			steps
		);
		release();
		await page.unroute( state );
		await expect( page.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		steps.push( seen( after ) );
		await expect.poll( () => rendered( page ) ).toEqual( steps );
		expect( await kept( page ) ).toEqual( all );

		// A turn of the board, Underside or Top, whichever is not shown: no answer, so no event.
		const sides = page.locator( '.cbuild-picture__turn .cbuild-button' );
		await expect( sides.first() ).toBeVisible();
		const turn =
			'true' === ( await sides.first().getAttribute( 'aria-pressed' ) )
				? sides.last()
				: sides.first();
		await turn.click();
		await expect( turn ).toHaveAttribute( 'aria-pressed', 'true' );
		await page.waitForTimeout( 600 );
		expect( await rendered( page ), 'a turn of the board' ).toEqual(
			steps
		);
		expect( await kept( page ) ).toEqual( all );
		await expect( page.locator( '.another-plugin' ) ).toHaveCount( 3 );
		// The place's own element alone, and nothing of the builder's in it, after every redraw.
		expect(
			await page
				.locator( '.cbuild-step__above-cards' )
				.evaluate( ( place ) =>
					[ ...place.childNodes ].map( ( child ) =>
						child instanceof Element
							? child.className
							: ( child.textContent ?? '' ).trim()
					)
				)
		).toEqual( [ 'another-plugin' ] );
		expect( await stayed( page ) ).toBe( true );
	} );
} );

/**
 * #132 (D-403): each row of the review shows its part's thumbnail, the picture and rendition its card shows, so a part
 * already seen loads nothing new. The thumbnail is decorative, its name beside it, and its place, a 3rem square, is
 * kept on a row with no picture, so nothing moves. A part another plugin flags, as Mature Content flags one, blurs
 * there too (spec: "A flagged part blurs on its card in a step, in the parts picture and in the review").
 */
test.describe( 'Thumbnails in the review (#132)', () => {
	const picks = {
		deck: id( 'builder-test-deck-8-0' ),
		trucks: id( 'builder-test-trucks-139' ),
		wheels: id( 'builder-test-wheels-52' ),
		bearings: id( 'builder-test-bearings' ),
		// The 1 in bolts have no photo and no layer image: their row has no picture.
		hardware: id( 'builder-test-bolts-1' ),
		grip: id( 'builder-test-grip' ),
	};
	const row = ( page: Page, slot: string ) =>
		page.locator( '.cbuild-review__items > li' ).filter( {
			has: page.locator( '.cbuild-review__slot', { hasText: slot } ),
		} );
	const loaded = ( photo: Locator ) =>
		photo.evaluate(
			( img: HTMLImageElement ) => img.complete && img.naturalWidth > 0
		);
	// The picture the chosen card shows on a step, as the browser chose it.
	const chosen = async ( page: Page, step: string ): Promise< string > => {
		await page.goto( linkTo( { ...picks, step } ) );
		const photo = page
			.locator( '.cbuild-card--chosen .cbuild-card__photo' )
			.first();
		await expect( photo ).toBeVisible();
		await expect.poll( () => loaded( photo ) ).toBe( true );
		return photo.evaluate( ( img: HTMLImageElement ) => img.currentSrc );
	};

	test( "shows each part's card picture on its row, and keeps the place on a row with none", async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			'Measured by a script in the page; the review is the server’s, the same without one.'
		);
		const deck = await chosen( page, 'deck' );
		const trucks = await chosen( page, 'trucks' );

		await page.goto( linkTo( { ...picks, step: 'review' } ) );
		const rows = page.locator( '.cbuild-review__items > li' );
		await expect( rows.first() ).toBeVisible();
		const thumbs = rows.locator( '.cbuild-review__photo' );
		await expect( thumbs ).toHaveCount( await rows.count() );
		for ( const thumb of await thumbs.all() ) {
			await expect(
				thumb,
				'decorative: the name is beside it'
			).toHaveAttribute( 'alt', '' );
		}

		for ( const [ slot, src ] of [
			[ 'Deck', deck ],
			[ 'Trucks', trucks ],
		] as const ) {
			const photo = row( page, slot ).locator( '.cbuild-review__photo' );
			await expect( photo ).toBeVisible();
			await expect.poll( () => loaded( photo ) ).toBe( true );
			expect(
				await photo.evaluate(
					( img: HTMLImageElement ) => img.currentSrc
				),
				`the ${ slot }' card's picture, in the same rendition`
			).toBe( src );
		}
		await expect(
			row( page, 'Hardware' ).locator( '.cbuild-review__photo' ),
			'the bolts have no picture'
		).toBeHidden();

		// Every row's place is the same 3rem square, a picture in it or not.
		const boxes = await rows.evaluateAll( ( items ) => {
			const rem = parseFloat(
				getComputedStyle( document.documentElement ).fontSize
			);
			return items.map( ( item ) => {
				const box = item
					.querySelector( '.cbuild-review__thumb' )
					?.getBoundingClientRect();
				return [
					Math.round( ( ( box?.width ?? 0 ) / rem ) * 100 ) / 100,
					Math.round( ( ( box?.height ?? 0 ) / rem ) * 100 ) / 100,
				];
			} );
		} );
		expect( boxes ).toEqual( boxes.map( () => [ 3, 3 ] ) );
		await axe( page, 'the review with its thumbnails', '.cbuild-review' );
	} );

	// Every file held back until the review is in view, so its rows are laid out before any thumbnail comes, as the
	// cards' pictures are tested (#131): the list keeps its height as each loads, and in Chromium, which reports layout
	// shifts, nothing in the review moves.
	test( 'moves nothing as the thumbnails load', async ( {
		page,
		browserName,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			'Each picture is waited for by a script in the page; the layout is the stylesheet’s, the same without one.'
		);
		let release = (): void => undefined;
		const gate = new Promise< void >( ( resolve ) => {
			release = resolve;
		} );
		await page.route(
			/\/wp-content\/uploads\/.*\.(png|jpe?g|webp)$/,
			async ( route ) => {
				await gate;
				await route.continue();
			}
		);
		await page.addInitScript( () => {
			( window as unknown as { shifted: number } ).shifted = 0;
			if (
				! PerformanceObserver.supportedEntryTypes.includes(
					'layout-shift'
				)
			) {
				return;
			}
			new PerformanceObserver( ( list ) => {
				for ( const entry of list.getEntries() as Array<
					PerformanceEntry & {
						value: number;
						hadRecentInput: boolean;
						sources: Array< { node: Node | null } >;
					}
				> ) {
					// The review's own shifts: a theme's header settling as its font arrives is the theme's.
					const ours = entry.sources.some(
						( source ) =>
							source.node instanceof Element &&
							null !== source.node.closest( '.cbuild-review' )
					);
					// From the gate's release on, once the fonts are in: the theme's font arriving reflows the review's
					// own words too, before any thumbnail could load (D-424).
					const from = (
						window as unknown as { shiftsFrom?: number }
					 ).shiftsFrom;
					const loading =
						undefined !== from && entry.startTime >= from;
					if ( ! entry.hadRecentInput && ours && loading ) {
						( window as unknown as { shifted: number } ).shifted +=
							entry.value;
					}
				}
			} ).observe( { type: 'layout-shift', buffered: true } );
		} );
		// The page's own pictures are held too, so its load event waits on the gate: the builder's ready mark instead.
		await page.goto( linkTo( { ...picks, step: 'review' } ), {
			waitUntil: 'domcontentloaded',
		} );
		await page.waitForSelector( '.cbuild[data-ready]' );
		const items = page.locator( '.cbuild-review__items' );
		await expect( items ).toBeVisible();
		await items.evaluate( ( list ) =>
			list.scrollIntoView( { block: 'start' } )
		);
		const height = async () =>
			items.evaluate( ( list ) => list.getBoundingClientRect().height );
		// The fonts that are loading, not document.fonts.ready: WebKit holds that until the page's load event, which waits
		// on the gate.
		await page.evaluate( () =>
			Promise.all(
				[ ...document.fonts ]
					.filter( ( font ) => 'loading' === font.status )
					.map( ( font ) => font.loaded )
			)
		);
		const before = await height();
		await page.evaluate( () => {
			( window as unknown as { shiftsFrom?: number } ).shiftsFrom =
				performance.now();
		} );
		release();
		const photos = page.locator( '.cbuild-review__photo:not([hidden])' );
		const count = await photos.count();
		expect( count, 'thumbnails to load' ).toBeGreaterThan( 0 );
		for ( let at = 0; at < count; at++ ) {
			const photo = photos.nth( at );
			await photo.evaluate( ( image ) =>
				image.scrollIntoView( { block: 'center' } )
			);
			await expect
				.poll( () =>
					photo.evaluate(
						( image ) =>
							( image as HTMLImageElement ).complete &&
							( image as HTMLImageElement ).naturalWidth > 0
					)
				)
				.toBe( true );
		}
		// Two frames for the last picture's paint, and its shift if it made one.
		await page.evaluate(
			() =>
				new Promise( ( resolve ) =>
					requestAnimationFrame( () =>
						requestAnimationFrame( resolve )
					)
				)
		);
		expect( await height(), 'the list as tall as before' ).toBeCloseTo(
			before,
			1
		);
		if ( 'chromium' === browserName ) {
			const shifted = await page.evaluate(
				() => ( window as unknown as { shifted: number } ).shifted
			);
			expect( shifted, "the review's layout shift" ).toBe( 0 );
		}
	} );

	// tests/e2e/mu-plugins/cbuild-e2e-flag.php flags the deck by cbuild_part_classes and blurs what it reaches, either
	// way Mature Content does: as a whole (`mcw-mature`), every picture of it; or in its layer image alone
	// (`mcw-mature-layer`), the pictures marked drawn and the board, its photo left sharp (D-398). The 8.0 in deck's
	// row shows its layer image, as its card does; the 8.25 in deck has none, so its row shows its photo.
	for ( const [ flag, cookie ] of [
		[ 'as a whole', 'cbuild_e2e_flag' ],
		[ 'in its layer image alone', 'cbuild_e2e_flag_layer' ],
	] as const ) {
		test( `blurs a flagged part's thumbnail, as Mature Content blurs a deck flagged ${ flag }`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const layer = 'cbuild_e2e_flag_layer' === cookie;
			const photo = ( slot: string ) =>
				row( page, slot ).locator( '.cbuild-review__photo' );
			const filter = ( slot: string ) =>
				photo( slot ).evaluate(
					( img ) => getComputedStyle( img ).filter
				);
			for ( const [ deck, drawn ] of [
				[ id( 'builder-test-deck-8-0' ), true ],
				[ id( 'builder-test-deck-8-25' ), false ],
			] as const ) {
				await context.clearCookies( { name: cookie } );
				await context.addCookies( [
					{
						name: cookie,
						value: String( deck ),
						url: baseURL || SITE,
					},
				] );
				await page.goto( linkTo( { ...picks, deck, step: 'review' } ) );
				await expect( photo( 'Deck' ) ).toBeVisible();
				await expect( photo( 'Deck' ) ).toHaveClass(
					layer
						? /(^| )cbuild-e2e-flagged-layer( |$)/
						: /(^| )cbuild-e2e-flagged( |$)/
				);
				if ( drawn ) {
					await expect(
						photo( 'Deck' ),
						'its layer image, marked as its card is'
					).toHaveClass( /(^| )cbuild-review__photo--drawn( |$)/ );
				} else {
					await expect(
						photo( 'Deck' ),
						'its photo'
					).not.toHaveClass( /cbuild-review__photo--drawn/ );
				}
				if ( drawn || ! layer ) {
					expect(
						await filter( 'Deck' ),
						drawn
							? 'the flagged layer image'
							: 'the deck flagged whole'
					).toMatch( /^blur\(/ );
				} else {
					expect(
						await filter( 'Deck' ),
						'a photo stays sharp when only the layer image is flagged'
					).toBe( 'none' );
				}
				expect(
					await filter( 'Trucks' ),
					'the trucks, not flagged'
				).toBe( 'none' );
			}
		} );
	}
} );

/**
 * #135 (D-394 to D-397): smart swaps. On the review the same board for less, and under each row its part for less and
 * the next better one; on a step, under the picked card. Each is a link, so it works without the script; one press
 * swaps, the total changes by what the control said, and one Undo puts the build back. With the script, focus goes
 * to the notice that says the swap, then back to the control after Undo. A kit with its switch off shows none.
 */
test.describe( 'Smart swaps (#135)', () => {
	test.describe.configure( { mode: 'serial' } );
	// A card's height in the step's list, the sold-out cards' closed list left out. It must be shown: a card not drawn
	// measures 0, which would say nothing.
	const height = async ( page: Page, name: string ): Promise< number > => {
		const card = page
			.locator( '.cbuild-cards:not(.cbuild-cards--sold-out) > li' )
			.filter( { hasText: name } );
		await expect( card ).toHaveCount( 1 );
		await expect( card ).toBeVisible();
		return card.evaluate( ( li ) => li.getBoundingClientRect().height );
	};
	// The 54s and the 1.25 in bolts: the 52s are $2 less and the 1 in bolts $1 less, and both fit; nothing better fits
	// (the 56s are sold out, the 58s need risers, and no bolt is longer).
	const picks = {
		deck: id( 'builder-test-deck-8-0' ),
		trucks: id( 'builder-test-trucks-139' ),
		wheels: id( 'builder-test-wheels-54' ),
		bearings: id( 'builder-test-bearings' ),
		hardware: id( 'builder-test-bolts-1-25' ),
		grip: id( 'builder-test-grip' ),
	};
	// An amount of money in a text, in minor units, in either language's way of writing it.
	const money = ( text: string ): number => {
		const found = text.match( /\d+[.,]\d{2}/ );
		expect( found, `an amount in "${ text }"` ).not.toBeNull();
		return Number( ( found?.[ 0 ] ?? '' ).replace( /\D/g, '' ) );
	};
	const total = async ( page: Page ): Promise< number > =>
		money(
			( await page.locator( '.cbuild-review__total' ).textContent() ) ??
				''
		);
	const row = ( page: Page, slot: string ) =>
		page.locator( '.cbuild-review__items > li' ).filter( {
			has: page.locator( '.cbuild-review__slot', { hasText: slot } ),
		} );
	const settings = ( document: string ) =>
		runPhp(
			`$k = wc_get_product( ${
				fixture().kit
			} ); if ( false === get_option( 'cbuild_e2e_swaps' ) ) { update_option( 'cbuild_e2e_swaps', array( 'had' => $k->documents()['_cbuild_settings'] ) ); } $k->setDocument( '_cbuild_settings', ${ document } ); $k->save();`
		);
	test.afterAll( () => {
		runPhp(
			`$k = wc_get_product( ${
				fixture().kit
			} ); $o = get_option( 'cbuild_e2e_swaps' ); if ( false !== $o ) { $had = $o['had'] ?? ''; if ( is_array( $had ) ) { $k->setDocument( '_cbuild_settings', $had ); } else { $k->delete_meta_data( '_cbuild_settings' ); } $k->save(); delete_option( 'cbuild_e2e_swaps' ); }`
		);
	} );

	test( 'the same board for less swaps the build, the total falls by what it said, and Undo puts it back', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto( linkTo( { ...picks, step: 'review' } ) );
		const board = page.locator( '#cbuild-swap-board' );
		await expect( board ).toBeVisible();
		await expect( board ).toHaveAttribute( 'rel', 'nofollow' );
		const said = money( ( await board.textContent() ) ?? '' );
		expect( said, 'the wheels $2 less and the bolts $1' ).toBe( 300 );
		const before = await total( page );
		await expect(
			row( page, 'Wheels' ).locator( '.cbuild-swap' ),
			'the wheels for less alone: nothing better fits'
		).toHaveCount( 1 );
		await expect(
			row( page, 'Deck' ).locator( '.cbuild-swap' )
		).toHaveCount( 0 );
		await expect( page.locator( '.cbuild-swapped' ) ).toBeHidden();
		if ( javaScriptEnabled ) {
			await axe( page, 'the review with smart swaps', '.cbuild-review' );
		}

		await board.click();
		const notice = page.locator( '.cbuild-swapped' );
		await expect( notice ).toBeVisible();
		await expect.poll( () => total( page ) ).toBe( before - said );
		await expect(
			row( page, 'Wheels' ).locator( '.cbuild-review__name' )
		).toContainText( '52' );
		await expect(
			row( page, 'Hardware' ).locator( '.cbuild-review__name' )
		).toHaveText( /\b1$/ );
		await expect(
			page.locator( '#cbuild-swap-board' ),
			'nothing left for less'
		).toBeHidden();
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[_undo][wheels]' )
		).toBe( String( picks.wheels ) );
		if ( javaScriptEnabled ) {
			await expect(
				notice,
				'focus on what the swap did, beside its Undo'
			).toBeFocused();
			// The notice is focused and read; the polite status says the build and its total alone, so the swap is
			// not heard twice (D-432).
			const swappedText =
				( await notice
					.locator( '.cbuild-swapped__text' )
					.textContent() ) ?? '';
			expect( swappedText ).not.toBe( '' );
			await expect( page.locator( '.cbuild__status' ) ).toContainText(
				'Total'
			);
			await expect( page.locator( '.cbuild__status' ) ).not.toContainText(
				swappedText
			);
			await axe( page, 'the review after a swap', '.cbuild__body' );
		}

		await notice.locator( '.cbuild-swapped__undo' ).click();
		await expect( notice ).toBeHidden();
		await expect.poll( () => total( page ) ).toBe( before );
		await expect(
			row( page, 'Wheels' ).locator( '.cbuild-review__name' )
		).toContainText( '54' );
		expect(
			new URL( page.url() ).searchParams.get( 'cbuild[_undo][wheels]' ),
			'nothing left to undo'
		).toBeNull();
		if ( javaScriptEnabled ) {
			await expect(
				page.locator( '#cbuild-swap-board' ),
				'focus back on the swap'
			).toBeFocused();
			// Back undoes too: the swap was a step of the history.
			await board.click();
			await expect( notice ).toBeVisible();
			await page.goBack();
			await expect( notice ).toBeHidden();
			await expect.poll( () => total( page ) ).toBe( before );
		}
	} );

	test( 'the picked card offers its swap, plain cards none and no taller, and Undo comes back to it', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		// With nothing picked on the step, no swap anywhere.
		const { wheels, ...rest } = picks;
		expect( wheels ).toBeGreaterThan( 0 );
		await page.goto( linkTo( { ...rest, step: 'wheels' } ) );
		await expect( page.locator( '.cbuild-swap:visible' ) ).toHaveCount( 0 );

		await page.goto( linkTo( { ...picks, step: 'wheels' } ) );
		const chosen = page.locator( '.cbuild-card--chosen' );
		const swap = chosen.locator( '.cbuild-swap' );
		await expect( swap ).toHaveCount( 1 );
		await expect( swap ).toHaveAttribute( 'id', 'cbuild-swap-wheels-less' );
		await expect(
			page.locator(
				'.cbuild-cards > li:not(.cbuild-card--chosen) .cbuild-card__swaps:not([hidden])'
			)
		).toHaveCount( 0 );
		if ( javaScriptEnabled ) {
			await axe( page, 'the picked card with its swap', '.cbuild-step' );
		}

		await swap.click();
		const notice = page.locator( '.cbuild-swapped' );
		await expect( notice ).toBeVisible();
		await expect( page.locator( '.cbuild-card--chosen' ) ).toContainText(
			'52'
		);
		if ( javaScriptEnabled ) {
			await expect( notice ).toBeFocused();
		}
		await notice.locator( '.cbuild-swapped__undo' ).click();
		await expect( notice ).toBeHidden();
		await expect( page.locator( '.cbuild-card--chosen' ) ).toContainText(
			'54'
		);
		if ( javaScriptEnabled ) {
			await expect(
				page.locator( '#cbuild-swap-wheels-less' ),
				'focus back on the swap'
			).toBeFocused();
		}
	} );

	test( 'a kit with Offer smart swaps off shows no swap control and leaves no room for one', async ( {
		page,
	} ) => {
		// The 58s' card, a plain card, on the wheels step with the 54s picked and their swap on offer. Measured here, so
		// the test holds when it runs alone (D-430).
		settings( "array( 'schema_version' => 1, 'swaps' => true )" );
		await page.goto( linkTo( { ...picks, step: 'wheels' } ) );
		await expect(
			page.locator( '.cbuild-card--chosen .cbuild-swap' )
		).toHaveCount( 1 );
		const plainBeside = await height( page, 'wheels 58' );

		settings( "array( 'schema_version' => 1, 'swaps' => false )" );
		await page.goto( linkTo( { ...picks, step: 'review' } ) );
		await expect( page.locator( '.cbuild-review__total' ) ).toBeVisible();
		await expect( page.locator( '.cbuild-swap:visible' ) ).toHaveCount( 0 );
		await expect( page.locator( '.cbuild-review__board' ) ).toBeHidden();
		for ( const swaps of await page
			.locator( '.cbuild-review__swaps' )
			.all() ) {
			await expect( swaps ).toBeHidden();
		}
		await page.goto( linkTo( { ...picks, step: 'wheels' } ) );
		await expect( page.locator( '.cbuild-card--chosen' ) ).toBeVisible();
		await expect( page.locator( '.cbuild-swap:visible' ) ).toHaveCount( 0 );
		expect(
			await height( page, 'wheels 58' ),
			'a plain card no taller beside a swap than with none'
		).toBeCloseTo( plainBeside, 0 );
	} );
} );

// #153 (D-510): on a phone, in the theme's content width, the bar's buttons took a row of their own under the total:
// on the trucks step in English on Twenty Twenty-Five and Storefront, and at the review in French on every theme, up to
// 199 px of bar. Next's and Add to cart's words now break inside the button, so the row stays beside the total: the
// stated limit is the buttons beside the total at 390 px, in English and French, on the three themes, with each
// button at least the 44 px target and no word cut.
test.describe( 'The bar on a phone (#153)', () => {
	const builds = {
		step: {
			deck: 'builder-test-deck-8-0',
			step: 'trucks',
		},
		review: {
			deck: 'builder-test-deck-8-0',
			trucks: 'builder-test-trucks-139',
			wheels: 'builder-test-wheels-54',
			bearings: 'builder-test-bearings',
			hardware: 'builder-test-bolts-1-25',
			grip: 'builder-test-grip',
			step: 'review',
		},
	};
	const places: [ string, keyof typeof builds, boolean ][] = [
		[ 'the trucks step', 'step', false ],
		[ 'the review', 'review', false ],
		[ 'the review with its delivery lines', 'review', true ],
	];
	for ( const theme of [ 'twentytwentyfive', 'kadence', 'storefront' ] ) {
		for ( const french of [ false, true ] ) {
			const language = french ? 'French' : 'English';
			test( `keeps Back and Next beside the total at 390 px on ${ theme } in ${ language }`, async ( {
				page,
				context,
				baseURL,
			} ) => {
				const url = baseURL || SITE;
				if ( french ) {
					await page.setExtraHTTPHeaders( {
						'x-cbuild-e2e-locale': 'fr_CA',
						'x-cbuild-e2e-translated': '1',
					} );
				}
				await page.setViewportSize( { width: 390, height: 844 } );
				for ( const [ name, where, delivery ] of places ) {
					await context.clearCookies();
					await context.addCookies( [
						{ name: 'cbuild_e2e_theme', value: theme, url },
						...( delivery
							? [
									{
										name: 'cbuild_e2e_delivery',
										value: '1',
										url,
									},
								]
							: [] ),
					] );
					// On the kit's own page, in the theme's content width.
					const kit = new URL( '/product/builder-test-kit/', url );
					for ( const [ key, value ] of Object.entries(
						builds[ where ]
					) ) {
						kit.searchParams.set(
							`cbuild[${ key }]`,
							'step' === key ? value : String( id( value ) )
						);
					}
					await page.goto( kit.toString() );
					const bar = page.locator( '.cbuild-bar' );
					await expect( bar.locator( 'summary' ) ).toBeVisible();
					if ( delivery ) {
						await expect(
							bar.locator( '.cbuild-bar__ships' )
						).toBeVisible();
					}
					const measured = await bar.evaluate( ( shown ) => {
						const total = shown
							.querySelector( '.cbuild-bar__parts' )!
							.getBoundingClientRect();
						const buttons = [
							...shown.querySelectorAll< HTMLElement >(
								'.cbuild-bar__actions .cbuild-button'
							),
						].filter( ( button ) => button.checkVisibility() );
						// The total's own words, by their text, not their boxes: an unbroken line wider than the total's
						// column runs out of it.
						const words = [
							'.cbuild-bar__label',
							'.cbuild-bar__amount > :first-child',
							'.cbuild-bar__estimate',
							'.cbuild-bar__more--show',
						].flatMap( ( selector ) => {
							const element =
								shown.querySelector< HTMLElement >( selector );
							if ( ! element?.checkVisibility() ) {
								return [];
							}
							const range = document.createRange();
							range.selectNodeContents( element );
							return [ range.getBoundingClientRect() ];
						} );
						return {
							height: Math.round(
								shown.getBoundingClientRect().height
							),
							buttons: buttons.map( ( button ) => {
								const box = button.getBoundingClientRect();
								return {
									name: button.textContent?.trim() ?? '',
									over: words.some(
										( word ) =>
											word.left < box.right - 1 &&
											word.right > box.left + 1 &&
											word.top < box.bottom - 1 &&
											word.bottom > box.top + 1
									),
									beside:
										box.top < total.bottom - 1 &&
										box.bottom > total.top + 1 &&
										( box.left >= total.right - 1 ||
											box.right <= total.left + 1 ),
									width: box.width,
									height: box.height,
									cut:
										button.scrollWidth >
											button.clientWidth + 1 ||
										button.scrollHeight >
											button.clientHeight + 1,
									inside: box.right <= 390 && box.left >= 0,
								};
							} ),
						};
					} );
					expect(
						measured.buttons.length,
						`Back and Next on ${ name }`
					).toBe( 2 );
					for ( const button of measured.buttons ) {
						const said = `${ button.name } on ${ name }, in a bar ${ measured.height } px high`;
						expect(
							button.beside,
							`${ said }: beside the total`
						).toBe( true );
						expect( button.cut, `${ said }: no word cut` ).toBe(
							false
						);
						expect(
							button.over,
							`${ said }: clear of the total's words`
						).toBe( false );
						expect(
							button.inside,
							`${ said }: on the screen`
						).toBe( true );
						expect(
							Math.min( button.width, button.height ),
							`${ said }: the 44 px target`
						).toBeGreaterThanOrEqual( 44 );
					}
				}
			} );
		}
	}
} );

// A theme's rules that outrank the builder's own (Cowork's review of 1.2.0, F32 and F33, D-416): the hostile theme
// fixture (tests/e2e/mu-plugins/cbuild-e2e-hostile.php) prints, after every stylesheet, a row's link colour, a
// paragraph's and a heading's margins at a class and an element, and every button in its own padding and colours.
test.describe( 'A hostile theme', () => {
	/**
	 * The fixture on or off for the next page, with the bar's delivery and arrival lines said, as another plugin says
	 * them on the demo.
	 *
	 * @param context The browser context.
	 * @param baseURL The site.
	 * @param on      Whether the hostile rules are printed.
	 */
	const hostile = async (
		context: BrowserContext,
		baseURL: string | undefined,
		on: boolean
	): Promise< void > => {
		const url = baseURL || SITE;
		await context.clearCookies( { name: 'cbuild_e2e_hostile' } );
		await context.addCookies( [
			{ name: 'cbuild_e2e_delivery', value: '1', url },
			...( on
				? [ { name: 'cbuild_e2e_hostile', value: '1', url } ]
				: [] ),
		] );
	};

	const review = () =>
		linkTo( {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-54' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1-25' ),
			grip: id( 'builder-test-grip' ),
			step: 'review',
		} );

	/**
	 * A phone's width, where the bar holds the delivery lines at the review (D-250): a desktop project's window
	 * narrowed to an iPhone 13's, as Cowork measured.
	 *
	 * @param page    The page.
	 * @param project The project's name.
	 */
	const phone = async ( page: Page, project: string ): Promise< void > => {
		if ( ! project.includes( 'phone' ) ) {
			await page.setViewportSize( { width: 390, height: 664 } );
		}
	};

	// Back and Next are at the reader's own base size and a padding of their own, so they fit beside the total on a
	// phone; a theme's padding for every `button` outranked that at no weight, and on Twenty Twenty-Five the bar's
	// buttons went under the total, 54 px more of bar.
	test( "keeps Back and Next at the bar's own size under a theme's button padding", async ( {
		page,
		context,
		baseURL,
	}, info ) => {
		await phone( page, info.project.name );
		const sizes = () =>
			page.locator( '.cbuild-bar__actions' ).evaluate( ( actions ) =>
				[ ...actions.querySelectorAll( '.cbuild-button' ) ]
					.filter( ( button ) => button.checkVisibility() )
					.map( ( button ) => {
						const style = getComputedStyle( button );
						return `${ button.className }: ${ style.paddingInlineStart } ${ style.paddingInlineEnd } ${ style.fontSize }`;
					} )
			);
		await hostile( context, baseURL, false );
		await page.goto( review() );
		const own = await sizes();
		expect( own.length, 'Back and Add to cart' ).toBe( 2 );
		await hostile( context, baseURL, true );
		await page.goto( review() );
		expect( await sizes(), 'with the hostile theme' ).toEqual( own );
	} );

	// Cowork's review of 1.2.0, F33 (#150): at the review on a phone the bar holds the delivery and arrival lines, and
	// Kadence's `.single-content p`, a class and an element, gave each 32 px below over the builder's `:where()` reset:
	// 64 px of the demo's 198 px bar on an iPhone 13.
	test( "keeps the bar no taller at the review on a phone under a theme's paragraph margin (#150)", async ( {
		page,
		context,
		baseURL,
	}, info ) => {
		await phone( page, info.project.name );
		const bar = page.locator( '.cbuild-bar' );
		const lines = page.locator( '.cbuild-bar__more-lines' );
		const measured = () =>
			bar.evaluate( ( element ) => ( {
				height: element.getBoundingClientRect().height,
				margins: [
					...element.querySelectorAll(
						'.cbuild-bar__more-lines > *'
					),
				].map(
					( line ) =>
						`${ line.className }: ${
							getComputedStyle( line ).marginBlockStart
						} ${ getComputedStyle( line ).marginBlockEnd }`
				),
			} ) );
		await hostile( context, baseURL, false );
		await page.goto( review() );
		await expect( lines ).toContainText( 'This build ships free' );
		await expect( lines ).toContainText( 'Arrives' );
		const own = await measured();
		await hostile( context, baseURL, true );
		await page.goto( review() );
		await expect( lines ).toContainText( 'This build ships free' );
		const theirs = await measured();
		expect( theirs.margins, "the lines' margins" ).toEqual( own.margins );
		expect(
			theirs.height,
			`the bar, ${ own.height } px without the theme's rules`
		).toBeLessThanOrEqual( own.height + 0.5 );
	} );

	// The sweep: the builder's start, a step, the review and a deck's page, each with the fixture off and then on. The
	// bar and the first card or ready build may differ by no more than LIMIT px, a rounding's worth, and axe finds no
	// failure, colour contrast among them, with the fixture on.
	const LIMIT = 2;
	const sweep: [ string, () => string ][] = [
		[ 'the start', () => fixture().page ],
		[ 'a step', () => linkTo( { _width: '8in' } ) ],
		[ 'the review', review ],
	];
	for ( const [ name, address ] of sweep ) {
		test( `keeps ${ name }'s bar and cards, and its contrast, under the hostile theme`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		} ) => {
			// Read by the page's layout alone, so the same with JavaScript off.
			const heights = async () => {
				const height = async ( selector: string ) => {
					const shown = page
						.locator( selector )
						.filter( { visible: true } )
						.first();
					const box = ( await shown.count() )
						? await shown.boundingBox()
						: null;
					return box ? Math.round( box.height * 2 ) / 2 : null;
				};
				return {
					bar: await height( '.cbuild-bar' ),
					card: await height( '.cbuild-cards > li, .cbuild-preset' ),
				};
			};
			await hostile( context, baseURL, false );
			await page.goto( address() );
			const own = await heights();
			expect(
				own.bar ?? own.card,
				'a bar or a card to measure'
			).not.toBeNull();
			await hostile( context, baseURL, true );
			await page.goto( address() );
			const theirs = await heights();
			for ( const key of [ 'bar', 'card' ] as const ) {
				expect(
					null === theirs[ key ],
					`the ${ key } shown as without the theme's rules`
				).toBe( null === own[ key ] );
				expect(
					Math.abs( ( theirs[ key ] ?? 0 ) - ( own[ key ] ?? 0 ) ),
					`the ${ key }: ${ theirs[ key ] } px, ${ own[ key ] } px without the theme's rules`
				).toBeLessThanOrEqual( LIMIT );
			}
			if ( javaScriptEnabled ) {
				// axe runs in the page, which a browser with JavaScript off does not allow.
				await axe(
					page,
					`${ name } under the hostile theme`,
					'.cbuild'
				);
			}
		} );
	}

	test( "keeps a deck page's start button readable under the hostile theme", async ( {
		page,
		context,
		baseURL,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			"The button's colours are the script's (D-377, D-416); without it the button is the theme's own."
		);
		await hostile( context, baseURL, true );
		await page.goto( `/?p=${ id( 'builder-test-deck-8-25' ) }` );
		await expect( page.locator( '.cbuild-start__link' ) ).toBeVisible();
		await axe(
			page,
			'a deck page under the hostile theme',
			'.cbuild-start'
		);
	} );

	// The wide sweep (D-419): every text block the builder draws, measured with the fixture off and then on, on the
	// start with "Help me choose" open and with its answers sent, a deck step after a width, a hardware step, the review
	// and the review with an error, the change panel, and My builds' compare; at the project's width and, on a desktop
	// project, a phone's too. Each block's top within its screen and its height may differ by no more than LIMIT px: a
	// theme's margins for its paragraphs, headings and lists are its content's, not the builder's.
	type Block = {
		top: number;
		height: number;
		below: number;
		margin: string;
	};
	const guided = (): string => {
		const url = new URL( fixture().page );
		for ( const [ key, value ] of Object.entries( {
			shoe: '3',
			style: 'street',
			asked: '1',
		} ) ) {
			url.searchParams.set( `cbuild[_guide][${ key }]`, value );
		}
		return url.toString();
	};
	const saved = [ 'Hostile street', 'Hostile park' ];
	const unsave = () =>
		runPhp(
			`$u = get_user_by( 'login', 'e2e-saver' ); if ( $u ) { $s = CreativeBranding\\SkateboardBuilder\\Plugin::container()->get( CreativeBranding\\SkateboardBuilder\\Service\\SavedBuilds::class ); foreach ( $s->of( $u->ID ) as $b ) { if ( in_array( $b->name, ${ JSON.stringify(
				saved
			) }, true ) ) { $s->delete( $u->ID, $b->uuid ); } } }`
		);
	const screens: [
		string,
		string,
		( page: Page, js: boolean ) => Promise< void >,
	][] = [
		[
			'the start with Help me choose open',
			'.cbuild',
			async ( page ) => {
				await page.goto( fixture().page );
				const ask = page.locator( '.cbuild-guide__ask' );
				await ask.locator( 'summary' ).click();
				await expect( ask ).toHaveAttribute( 'open', '' );
			},
		],
		[
			"the start with the guide's answers sent",
			'.cbuild',
			async ( page ) => {
				await page.goto( guided() );
				await expect(
					page.locator( '.cbuild-guide__builds' )
				).toBeVisible();
			},
		],
		[
			'a deck step after a width',
			'.cbuild',
			async ( page ) => {
				await page.goto( linkTo( { _width: '8in' } ) );
			},
		],
		[
			'a hardware step',
			'.cbuild',
			async ( page ) => {
				await page.goto(
					linkTo( {
						deck: id( 'builder-test-deck-8-0' ),
						trucks: id( 'builder-test-trucks-139' ),
						wheels: id( 'builder-test-wheels-54' ),
						bearings: id( 'builder-test-bearings' ),
						step: 'hardware',
					} )
				);
			},
		],
		[
			'the review',
			'.cbuild',
			async ( page ) => {
				await page.goto( review() );
			},
		],
		[
			'the review with an error',
			'.cbuild',
			async ( page ) => {
				await page.goto(
					linkTo( {
						deck: id( 'builder-test-deck-8-5' ),
						trucks: id( 'builder-test-trucks-149' ),
						wheels: id( 'builder-test-wheels-56' ),
						bearings: id( 'builder-test-bearings' ),
						hardware: id( 'builder-test-bolts-1' ),
						grip: id( 'builder-test-grip' ),
						step: 'review',
						preset: 1,
					} )
				);
				await expect( page.locator( '.cbuild-errors' ) ).toBeVisible();
			},
		],
		[
			'the change panel',
			'.cbuild',
			async ( page, js ) => {
				await page.goto(
					linkTo( {
						deck: id( 'builder-test-deck-8-0' ),
						trucks: id( 'builder-test-trucks-139' ),
						step: 'deck',
					} )
				);
				// With the script the pick itself opens the panel; without it, Next sends the form and the server
				// answers with the panel as a step of its own.
				await pickDeck( page, '8.5' );
				if ( ! js ) {
					await next( page, 'Next: Trucks' );
				}
				await expect( page.locator( '.cbuild-changes' ) ).toBeVisible();
			},
		],
	];

	/**
	 * Every text block shown in a screen's root: its top within its parent's box and its height, to the half pixel, by
	 * its element, its place among the root's elements of that name and its classes; and its block margins, to say why.
	 *
	 * @param page The page.
	 * @param root The screen's root.
	 */
	const blocks = async (
		page: Page,
		root: string
	): Promise< Map< string, Block > > => {
		// A primary button keeps the theme's own fill and padding (D-321), so the change panel's Use these is taller
		// under the fixture's button rule; held at one size in both runs, it moves none of the words measured. The
		// bar's buttons have their own test (D-417).
		// Added by hand: addStyleTag waits for a load event that a page without JavaScript never sends.
		await page.evaluate( () => {
			const style = document.createElement( 'style' );
			style.textContent =
				'.cbuild .cbuild-button--primary{padding-block:0!important}';
			document.head.append( style );
		} );
		return new Map(
			await page
				.locator( root )
				.filter( { visible: true } )
				.first()
				.evaluate( ( scope ) => {
					const half = ( value: number ) =>
						Math.round( value * 2 ) / 2;
					const found: [ string, Block ][] = [];
					for ( const tag of [
						'p',
						'h1',
						'h2',
						'h3',
						'h4',
						'h5',
						'h6',
						'ul',
						'ol',
						'dl',
						'li',
						'dt',
						'dd',
						'legend',
						'summary',
						'figcaption',
						'caption',
						'table',
						'th',
						'td',
					] ) {
						scope
							.querySelectorAll( tag )
							.forEach( ( block, at ) => {
								const box = block.getBoundingClientRect();
								// A screen reader's text is off the screen, where a margin moves nothing.
								if (
									! block.checkVisibility() ||
									box.height <= 1 ||
									block.closest( '.screen-reader-text' )
								) {
									return;
								}
								const parent = (
									block.parentElement ?? scope
								).getBoundingClientRect();
								const style = getComputedStyle( block );
								const classes = [ ...block.classList ]
									.map( ( name ) => `.${ name }` )
									.join( '' );
								found.push( [
									`${ tag }[${ at }]${ classes }`,
									{
										top: half( box.top - parent.top ),
										height: half( box.height ),
										below: half(
											parent.bottom - box.bottom
										),
										margin: `${ style.marginBlockStart } ${ style.marginBlockEnd }`,
									},
								] );
							} );
					}
					return found;
				} )
		);
	};

	/**
	 * The blocks a theme's rules moved by more than LIMIT px, or showed or hid.
	 *
	 * @param own    The blocks with the fixture off.
	 * @param theirs The same with it on.
	 */
	const moved = (
		own: Map< string, Block >,
		theirs: Map< string, Block >
	): string[] => [
		...[ ...own ].flatMap( ( [ key, box ] ) => {
			const other = theirs.get( key );
			if ( ! other ) {
				return [ `${ key }: hidden` ];
			}
			return Math.abs( other.top - box.top ) > LIMIT ||
				Math.abs( other.height - box.height ) > LIMIT ||
				Math.abs( other.below - box.below ) > LIMIT
				? [
						`${ key }: top ${ other.top }, height ${ other.height }, below ${ other.below }, margin ${ other.margin }; ${ box.top }, ${ box.height }, ${ box.below }, ${ box.margin } without`,
					]
				: [];
		} ),
		...[ ...theirs.keys() ]
			.filter( ( key ) => ! own.has( key ) )
			.map( ( key ) => `${ key }: shown` ),
	];

	/**
	 * The widths a screen is measured at: the project's own, and on a desktop project a phone's too.
	 *
	 * @param project The project's name.
	 */
	const widths = ( project: string ) =>
		project.includes( 'phone' )
			? [ null ]
			: [ null, { width: 390, height: 844 } ];

	for ( const [ name, root, open ] of screens ) {
		test( `keeps every text block of ${ name } where the builder puts it under the hostile theme`, async ( {
			page,
			context,
			baseURL,
			javaScriptEnabled,
		}, info ) => {
			const found: string[] = [];
			for ( const size of widths( info.project.name ) ) {
				if ( size ) {
					await page.setViewportSize( size );
				}
				const width = page.viewportSize()?.width;
				await hostile( context, baseURL, false );
				await open( page, javaScriptEnabled );
				const own = await blocks( page, root );
				expect( own.size, 'text blocks to measure' ).toBeGreaterThan(
					0
				);
				await hostile( context, baseURL, true );
				await open( page, javaScriptEnabled );
				const theirs = await blocks( page, root );
				found.push(
					...moved( own, theirs ).map(
						( line ) => `${ width } px, ${ line }`
					)
				);
			}
			expect( found, `${ name }: the blocks a theme moved` ).toEqual(
				[]
			);
		} );
	}

	test( "keeps every text block of My builds' compare where the builder puts it under the hostile theme", async ( {
		page,
		context,
		baseURL,
	}, info ) => {
		await customer( page );
		unsave();
		const street = {
			deck: id( 'builder-test-deck-8-0' ),
			trucks: id( 'builder-test-trucks-139' ),
			wheels: id( 'builder-test-wheels-54' ),
			bearings: id( 'builder-test-bearings' ),
			hardware: id( 'builder-test-bolts-1-25' ),
			grip: id( 'builder-test-grip' ),
		};
		const builds = [
			street,
			{ ...street, wheels: id( 'builder-test-wheels-52' ) },
		];
		runPhp(
			`$u = get_user_by( 'login', 'e2e-saver' ); $s = CreativeBranding\\SkateboardBuilder\\Plugin::container()->get( CreativeBranding\\SkateboardBuilder\\Service\\SavedBuilds::class ); foreach ( json_decode( '${ JSON.stringify(
				builds.map( ( picks, at ) => ( { name: saved[ at ], picks } ) )
			) }', true ) as $b ) { if ( 'saved' !== $s->save( $u->ID, ${
				fixture().kit
			}, $b['picks'], true, $b['name'] )['code'] ) { throw new RuntimeException( $b['name'] ); } }`
		);
		try {
			await hostile( context, baseURL, false );
			await page.goto( '/my-account/my-builds/' );
			for ( const name of saved ) {
				await page
					.getByRole( 'checkbox', { name: `Compare ${ name }` } )
					.check();
			}
			await answered( page, () =>
				page
					.getByRole( 'button', {
						name: 'Compare the ticked builds',
					} )
					.click()
			);
			const address = page.url();
			// The compare's own table. The link back to My builds above it is a paragraph of the account page, spaced
			// as the theme spaces that page's words, WooCommerce's own among them (D-419).
			const root = '.cbuild-compare';
			const found: string[] = [];
			for ( const size of widths( info.project.name ) ) {
				if ( size ) {
					await page.setViewportSize( size );
				}
				const width = page.viewportSize()?.width;
				await hostile( context, baseURL, false );
				await page.goto( address );
				await expect( page.locator( '.cbuild-compare' ) ).toBeVisible();
				const own = await blocks( page, root );
				await hostile( context, baseURL, true );
				await page.goto( address );
				const theirs = await blocks( page, root );
				found.push(
					...moved( own, theirs ).map(
						( line ) => `${ width } px, ${ line }`
					)
				);
			}
			expect( found, 'the compare: the blocks a theme moved' ).toEqual(
				[]
			);
		} finally {
			unsave();
		}
	} );
} );
