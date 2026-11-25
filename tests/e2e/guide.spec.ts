import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, answered, inLocale, Page } from './support/test';
import { axe } from './support/axe';

/*
 * #134, the guided start: "Help me choose" at the builder's start, optional questions in one GET form, the builds they
 * recommend with why, and Skip from every point, with JavaScript and without (the no-js project), in French (fr-ca,
 * with the plugin's translation asked for) and right to left (rtl). The builder's shop has decks of 8, 8.25 and 8.5
 * inches, wheels of 52 and 54 mm in stock and 56 mm sold out, and one bearing, bolt and grip in the sizes a build takes.
 */

const fixture = () =>
	JSON.parse(
		readFileSync(
			path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
			'utf8'
		)
	) as { page: string };

/**
 * The builder's address with the guided start's answers.
 *
 * @param answers The answers, by name under `cbuild[_guide]`.
 */
const asked = ( answers: Record< string, string > ): string => {
	const url = new URL( fixture().page );
	for ( const [ key, value ] of Object.entries( {
		...answers,
		asked: '1',
	} ) ) {
		url.searchParams.set( `cbuild[_guide][${ key }]`, value );
	}
	return url.toString();
};

/**
 * Sends the guided start's questions: a page load without JavaScript, the store's answer with it.
 *
 * @param page The page.
 * @param js   Whether the page runs its script.
 */
async function show( page: Page, js: boolean ) {
	const button = page
		.locator( '.cbuild-guide' )
		.getByRole( 'button', { name: 'Show builds' } );
	if ( js ) {
		await button.click();
	} else {
		await answered( page, () => button.click() );
	}
	await expect(
		page
			.locator( '.cbuild-guide__builds' )
			.getByRole( 'heading', { name: 'Builds for you' } )
	).toBeVisible();
}

/**
 * The guided start's answers in the page's address.
 *
 * @param page The page.
 */
const answers = ( page: Page ): Record< string, string > =>
	Object.fromEntries(
		[ ...new URL( page.url() ).searchParams ]
			// A blank answer is sent as an empty field by the form without JavaScript: no answer either way.
			.filter(
				( [ key, value ] ) =>
					key.startsWith( 'cbuild[_guide]' ) && '' !== value
			)
			.map( ( [ key, value ] ) => [
				key.replace( /^cbuild\[_guide\]\[(\w+)\]$/, '$1' ),
				value,
			] )
	);

/**
 * Opens "Help me choose" where it is shut. With JavaScript it stays as the shopper left it when the builds come, and
 * without it the page that answers has it shut.
 *
 * @param page The page.
 */
async function open( page: Page ) {
	const ask = page.locator( '.cbuild-guide__ask' );
	if ( null === ( await ask.getAttribute( 'open' ) ) ) {
		await ask.locator( 'summary' ).click();
	}
	await expect( ask ).toHaveAttribute( 'open', '' );
}

test.describe( 'Help me choose', () => {
	test.beforeEach( ( {}, info ) => {
		test.skip(
			'visual' === info.project.name,
			'The visual project has its own spec'
		);
	} );

	test( 'recommends builds from the answers with why, each starting at the review, and the answers live in the link', async ( {
		page,
		javaScriptEnabled,
	}, info ) => {
		// The fr-ca project writes numbers as French does, with the plugin's own words in English.
		const local = ( text: string ) => inLocale( info.project.name, text );
		await page.goto( fixture().page );
		const guide = page.locator( '.cbuild-guide' );
		const summary = guide.locator( 'summary' );
		await expect( summary ).toHaveText( 'Help me choose' );
		// Closed until asked: a shopper who knows their width sees the deck step as before.
		await expect(
			guide.getByRole( 'group', { name: 'Where do you skate?' } )
		).toBeHidden();
		await summary.click();
		await guide
			.getByRole( 'group', { name: 'Your shoe size (US)' } )
			.getByRole( 'radio', { name: local( '9.5 and larger' ) } )
			.check();
		await guide
			.getByRole( 'group', { name: 'Where do you skate?' } )
			.getByRole( 'radio', { name: 'Street' } )
			.check();
		await show( page, false !== javaScriptEnabled );

		const builds = page.locator( '.cbuild-guide__builds' );
		await expect(
			builds.locator( '.cbuild-guide__reasons li' )
		).toHaveText( [
			local( 'Deck width 8.25″, for shoe size 9.5 and larger.' ),
			/^Wheels of 50\smm to 54\smm suit street skating\.$/,
			'2 builds fit and are in stock.',
		] );
		// Two wheels in street's sizes: the cheaper and the dearer, the middle of two being the cheaper.
		await expect( builds.locator( '.cbuild-preset__name' ) ).toHaveText( [
			'Budget build',
			'High-end build',
		] );
		await expect( builds.locator( '.cbuild-preset__total' ) ).toHaveText( [
			'$189.00',
			'$191.00',
		] );
		expect( answers( page ) ).toEqual( {
			shoe: '3',
			style: 'street',
			asked: '1',
		} );
		await expect( page.locator( '.cbuild-guide summary' ) ).toHaveText(
			'Change your answers'
		);
		await expect(
			builds.getByRole( 'link', {
				name: local( 'Build your own at 8.25″' ),
			} )
		).toBeVisible();

		const start = builds.getByRole( 'button', {
			name: 'Start from Budget build',
		} );
		if ( false === javaScriptEnabled ) {
			await answered( page, () => start.click() );
		} else {
			await start.click();
		}
		await expect(
			page.getByRole( 'heading', { level: 2, name: /^Step \d+ of \d+:/ } )
		).toHaveText( 'Step 7 of 7: Check your build' );
		await expect( page.locator( '.cbuild-errors' ) ).toBeHidden();
		await expect( page.locator( '.cbuild-review__total' ) ).toHaveText(
			'Total: $189.00'
		);
		await expect(
			page
				.locator( '.cbuild' )
				.getByRole( 'button', { name: 'Add to cart' } )
		).toBeEnabled();
		expect( answers( page ), 'a build begun carries no answers' ).toEqual(
			{}
		);
		await expect( page.locator( '.cbuild-guide' ) ).toBeHidden();
	} );

	test( 'every answer may be left blank, and a budget no build is within keeps the closest, said to be over', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto( fixture().page );
		await page.locator( '.cbuild-guide summary' ).click();
		await show( page, false !== javaScriptEnabled );
		const builds = page.locator( '.cbuild-guide__builds' );
		await expect(
			builds.locator( '.cbuild-preset__name' ).first()
		).toHaveText( 'Budget build' );
		expect( answers( page ) ).toEqual( { asked: '1' } );

		await open( page );
		await page.getByLabel( 'Your budget ($)' ).fill( '100' );
		await show( page, false !== javaScriptEnabled );
		await expect( builds.locator( '.cbuild-preset__name' ) ).toHaveText( [
			'Budget build',
		] );
		await expect(
			builds.locator( '.cbuild-guide__reasons' )
		).toContainText(
			'No build comes within your budget. The closest is $'
		);
		await expect( builds.locator( '.cbuild-preset__notes li' ) ).toHaveText(
			/^Over your budget by \$\d+\.00\.$/
		);
		await expect( page.getByLabel( 'Your budget ($)' ) ).toHaveValue(
			'100'
		);
	} );

	test( 'skips to the builder from the questions, from the builds, and by keyboard', async ( {
		page,
		javaScriptEnabled,
		browserName,
	} ) => {
		const js = false !== javaScriptEnabled;
		// Safari's Tab reaches only text fields and the like, buttons and links with Option-Tab, unless "Press Tab to
		// highlight each item" is on; Playwright's WebKit keeps that default, so its keyboard user presses Option-Tab
		// (D-408).
		const tab = 'webkit' === browserName ? 'Alt+Tab' : 'Tab';
		const start = async () =>
			expect(
				page.getByRole( 'heading', {
					level: 2,
					name: /^Step \d+ of \d+:/,
				} )
			).toHaveText( /^Step 1 of \d+:/ );
		const follow = async ( link: () => Promise< void > ) => {
			if ( js ) {
				await link();
				await expect.poll( () => answers( page ) ).toEqual( {} );
			} else {
				await answered( page, link );
			}
		};

		// From the questions, half answered.
		await page.goto( fixture().page );
		const guide = page.locator( '.cbuild-guide' );
		await guide.locator( 'summary' ).click();
		await guide.getByRole( 'radio', { name: 'Cruising' } ).check();
		await follow( () =>
			guide
				.locator( '.cbuild-guide__form' )
				.getByRole( 'link', { name: 'Skip to the builder' } )
				.click()
		);
		// The answer given is gone: a sign that changes, where the address had no answers before Skip either.
		// Shut on the page that answers without JavaScript, so found by its value, not its role.
		await expect(
			guide.locator( 'input[type="radio"][value="cruising"]' )
		).not.toBeChecked();
		await start();
		expect( answers( page ) ).toEqual( {} );
		await expect( guide.locator( 'summary' ) ).toHaveText(
			'Help me choose'
		);

		// From the builds.
		await page.goto( asked( { style: 'street' } ) );
		await follow( () =>
			page
				.locator( '.cbuild-guide__builds' )
				.getByRole( 'link', { name: 'Skip to the builder' } )
				.click()
		);
		await start();
		await expect( page.locator( '.cbuild-guide__builds' ) ).toBeHidden();

		// By keyboard alone: the disclosure opens with Enter, and Skip is reached by Tab from the last question.
		await page.goto( fixture().page );
		await guide.locator( 'summary' ).focus();
		await page.keyboard.press( 'Enter' );
		await page.getByLabel( 'Your budget ($)' ).focus();
		await page.keyboard.press( tab );
		await expect(
			guide.getByRole( 'button', { name: 'Show builds' } )
		).toBeFocused();
		await page.keyboard.press( tab );
		const skip = guide
			.locator( '.cbuild-guide__form' )
			.getByRole( 'link', { name: 'Skip to the builder' } );
		await expect( skip ).toBeFocused();
		await follow( () => page.keyboard.press( 'Enter' ) );
		await start();
	} );

	test( 'passes axe with the questions open and with the builds', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		test.skip(
			! javaScriptEnabled,
			'axe runs its script in the page, which the no-js project cannot'
		);
		await page.goto( fixture().page );
		await page.locator( '.cbuild-guide summary' ).click();
		await axe( page, 'the guided start, its questions', '.cbuild-guide' );
		await page.goto(
			asked( { shoe: '3', style: 'street', budget: '150' } )
		);
		await page.locator( '.cbuild-guide summary' ).click();
		await axe( page, 'the guided start, its builds', '.cbuild-guide' );
	} );

	test( 'asks and answers in French', async ( { page }, info ) => {
		test.skip( 'fr-ca' !== info.project.name, 'The fr-ca project alone' );
		await page.setExtraHTTPHeaders( {
			'x-cbuild-e2e-locale': 'fr_CA',
			'x-cbuild-e2e-translated': '1',
		} );
		await page.goto( asked( { shoe: '3', style: 'street' } ) );
		const guide = page.locator( '.cbuild-guide' );
		await expect( guide.locator( 'summary' ) ).toHaveText(
			'Modifier vos réponses'
		);
		await expect(
			guide.getByRole( 'heading', { name: 'Montages pour vous' } )
		).toBeVisible();
		await expect( guide.locator( '.cbuild-guide__reasons li' ) ).toHaveText(
			[
				/^Largeur de planche de 8,25\s?(″|po), pour la pointure 9,5 et plus\.$/,
				/^Des roues de 50\smm à 54\smm conviennent à la rue\.$/,
				'2 montages compatibles et en stock.',
			]
		);
		await expect( guide.locator( '.cbuild-preset__name' ) ).toHaveText( [
			'Montage économique',
			'Montage haut de gamme',
		] );
		await guide.locator( 'summary' ).click();
		await expect(
			guide.getByRole( 'group', {
				name: /^Où faites-vous de la planche\s\?$/,
			} )
		).toBeVisible();
		await axe( page, 'the guided start in French', '.cbuild-guide' );
	} );
} );
