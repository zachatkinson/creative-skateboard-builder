import { readFileSync, writeFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, answered, inLocale, Page, SITE } from './support/test';
import { axe } from './support/axe';
import { wp } from './support/cli';

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
		// F40 (#163): the closest is shown alone, and the count is of every build that fits.
		await expect(
			builds.locator( '.cbuild-guide__reasons li' ).last()
		).toHaveText( /^[2-9] builds fit and are in stock\.$/ );
		await expect( page.getByLabel( 'Your budget ($)' ) ).toHaveValue(
			'100'
		);
	} );

	// F41 (#164): a place for another plugin's own element just above the builds' tiles, as `.cbuild-step__above-cards`
	// is above the cards, so a notice about a tile sits by it. Rendered empty, it is out of the layout until used, and
	// an element put there stays, alone, through the builds a new answer brings.
	test( "keeps another plugin's element in the place above the builds", async ( {
		page,
		javaScriptEnabled,
	} ) => {
		await page.goto( fixture().page );
		await page.locator( '.cbuild-guide summary' ).click();
		const place = page.locator( '.cbuild-guide__above-builds' );
		await expect( place, 'rendered by the server' ).toHaveCount( 1 );
		test.skip( false === javaScriptEnabled, 'a script puts the element' );
		await show( page, true );
		await expect( place ).toBeHidden();
		const tiles = page.locator( '.cbuild-guide__tiles' );
		await page.evaluate( () => {
			const element = document.createElement( 'p' );
			element.className = 'another-plugin';
			element.textContent = 'Another plugin';
			document
				.querySelector( '.cbuild-guide__above-builds' )!
				.append( element );
			( window as unknown as { cbuildOwn: Element } ).cbuildOwn = element;
		} );
		await expect( place ).toBeVisible();
		const below = async () =>
			( await tiles.boundingBox() )!.y - ( await place.boundingBox() )!.y;
		expect( await below(), 'just above the tiles' ).toBeGreaterThan( 0 );

		await open( page );
		await page.getByLabel( 'Your budget ($)' ).fill( '100' );
		await show( page, true );
		await expect(
			page.locator( '.cbuild-guide__builds .cbuild-preset__name' )
		).toHaveText( [ 'Budget build' ] );
		expect(
			await place.evaluate(
				( own ) =>
					own.firstElementChild ===
						( window as unknown as { cbuildOwn: Element } )
							.cbuildOwn && 1 === own.childNodes.length
			),
			'the same element, alone'
		).toBe( true );
		expect( await below() ).toBeGreaterThan( 0 );
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

/**
 * The test kit's Help me choose set, or taken off its settings as a kit saved before 1.3.0 has it (D-409).
 *
 * @param on True or false, or null to take the setting off.
 */
const helpMeChoose = ( on: boolean | null ): void => {
	// A file per worker, as builder.spec's runPhp() (D-422).
	const name = `e2e-eval-${ process.pid }.php`;
	const file = path.join( process.cwd(), 'tmp', name );
	writeFileSync(
		file,
		`<?php\n$k = wc_get_product( ${
			JSON.parse(
				readFileSync(
					path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
					'utf8'
				)
			).kit
		} ); $d = $k->documents()['_cbuild_settings'] ?? array( 'schema_version' => 1 ); if ( null === ${ JSON.stringify(
			on
		) } ) { unset( $d['guide'] ); } else { $d['guide'] = ${ JSON.stringify(
			on
		) }; } $k->setDocument( '_cbuild_settings', $d ); $k->save();\n`
	);
	wp(
		`eval-file wp-content/plugins/creative-skateboard-builder/tmp/${ name }`
	);
};

// D-409: the kit's Help me choose, in its Build tab. Off, the page draws none of the guide, with JavaScript or without:
// no disclosure, no form, no label, no builds, even for answers in the link, and the step follows the lead at the gap
// the builder leaves between any two of its pieces, so nothing marks where the guide was. Once a width is pressed the
// decks fill the same screen as with the guide on.
test.describe( 'Help me choose turned off', () => {
	test.afterAll( () => helpMeChoose( null ) );

	test( 'draws no guide, leaves no gap, and the decks fill the screen as before', async ( {
		page,
		javaScriptEnabled,
	} ) => {
		const js = false !== javaScriptEnabled;
		// The gap from the step's lead to what follows it, and the whole deck cards in view once a width is chosen,
		// the step at the window's top.
		const after = () =>
			page.evaluate( () => {
				const lead = document
					.querySelector( '.cbuild-step__lead' )
					?.getBoundingClientRect();
				const next = [
					...document.querySelectorAll< HTMLElement >(
						'.cbuild__body > *'
					),
				].find(
					( piece ) =>
						piece.checkVisibility() &&
						lead &&
						piece.getBoundingClientRect().top >= lead.bottom
				);
				return {
					next: next?.classList[ 0 ] ?? '',
					gap: lead
						? Math.round(
								( next?.getBoundingClientRect().top ?? 0 ) -
									lead.bottom
							)
						: -1,
				};
			} );
		const whole = async () => {
			await page.goto( `${ fixture().page }?cbuild[_width]=8in` );
			await page
				.locator( '.cbuild-step' )
				.evaluate( ( step ) =>
					step.scrollIntoView( { block: 'start' } )
				);
			return page.locator( '.cbuild-cards > li' ).evaluateAll(
				( items ) =>
					items.filter( ( item ) => {
						const box = item.getBoundingClientRect();
						const bar = document
							.querySelector( '.cbuild-bar:not([hidden])' )
							?.getBoundingClientRect();
						const foot =
							bar && bar.top > 0 && bar.top < innerHeight
								? bar.top
								: innerHeight;
						return (
							box.height > 0 && box.top >= 0 && box.bottom <= foot
						);
					} ).length
			);
		};

		await page.goto( fixture().page );
		await expect( page.locator( '.cbuild-guide' ) ).toBeVisible();
		const on = await after();
		expect( on.next, 'on, the guide follows the lead' ).toBe(
			'cbuild-guide'
		);
		const decks = await whole();
		expect(
			decks,
			'whole decks in view with the guide on'
		).toBeGreaterThan( 0 );

		helpMeChoose( false );
		for ( const address of [
			fixture().page,
			asked( { style: 'street', budget: '150' } ),
		] ) {
			await page.goto( address );
			await expect( page.locator( '.cbuild-step' ) ).toBeVisible();
			await expect(
				page.locator( '.cbuild-guide, [class*="cbuild-guide__"]' ),
				'nothing of the guide'
			).toHaveCount( 0 );
			await expect(
				page.getByText(
					/Help me choose|Builds for you|Change your answers/
				)
			).toHaveCount( 0 );
			await expect(
				page.locator( 'label[for^="cbuild-guide"]' )
			).toHaveCount( 0 );
			const off = await after();
			expect( off.next, 'the step follows the lead' ).toBe(
				'cbuild-step'
			);
			expect( off.gap, 'at the gap the guide had' ).toBe( on.gap );
			if ( js ) {
				await expect(
					page.locator( '.cbuild' ),
					'the builder hydrated'
				).toHaveAttribute( 'data-ready', '' );
			}
		}
		expect( await whole(), 'whole decks in view' ).toBe( decks );
	} );
} );

// Cowork, for Mature Content: the guide's builds are ready builds' tiles, whose picture takes each part's classes from
// `cbuild_part_classes` (D-309). tests/e2e/mu-plugins/cbuild-e2e-flag.php flags a deck either way Mature Content does:
// as a whole (`mcw-mature`), every picture of it blurs; or in its layer image alone (`mcw-mature-layer`), the pictures
// marked as the layer image (`…__photo--drawn`) blur and a photo stays sharp (D-398). With every answer blank the guide
// recommends three builds: the 8 in deck, whose tile shows its layer image since it has no photo, the 8.25 in deck, its
// photo, and the 8.5 in deck (D-408).
test.describe( "A flagged deck in the guide's builds", () => {
	const parts = () =>
		JSON.parse(
			readFileSync(
				path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
				'utf8'
			)
		).parts as Record< string, number >;
	for ( const [ flag, cookie ] of [
		[ 'as a whole', 'cbuild_e2e_flag' ],
		[ 'in its layer image alone', 'cbuild_e2e_flag_layer' ],
	] as const ) {
		test( `blurs the tile of a deck flagged ${ flag } where it shows what is flagged`, async ( {
			page,
			context,
			baseURL,
		} ) => {
			const layer = 'cbuild_e2e_flag_layer' === cookie;
			const tiles = page.locator( '.cbuild-guide__tiles .cbuild-preset' );
			const photo = ( deck: number ) =>
				tiles
					.filter( {
						has: page.locator(
							`input[name="cbuild[deck]"][value="${ deck }"]`
						),
					} )
					.locator( '.cbuild-preset__photo' );
			const decks = [
				Number( parts()[ 'builder-test-deck-8-0' ] ),
				Number( parts()[ 'builder-test-deck-8-25' ] ),
				Number( parts()[ 'builder-test-deck-8-5' ] ),
			] as const;
			for ( const [ deck, drawn ] of [
				[ decks[ 0 ], true ],
				[ decks[ 1 ], false ],
			] as const ) {
				await context.clearCookies( { name: cookie } );
				await context.addCookies( [
					{
						name: cookie,
						value: String( deck ),
						url: baseURL || SITE,
					},
				] );
				await page.goto( asked( {} ) );
				await expect( tiles ).toHaveCount( 3 );
				await expect( photo( deck ) ).toBeVisible();
				await expect( photo( deck ) ).toHaveClass(
					layer
						? /(^| )cbuild-e2e-flagged-layer( |$)/
						: /(^| )cbuild-e2e-flagged( |$)/
				);
				if ( drawn ) {
					await expect(
						photo( deck ),
						'its layer image, marked as its card is'
					).toHaveClass( /(^| )cbuild-preset__photo--drawn( |$)/ );
				} else {
					await expect(
						photo( deck ),
						'its photo, not marked'
					).not.toHaveClass( /__photo--drawn/ );
				}
				if ( drawn || ! layer ) {
					await expect(
						photo( deck ),
						drawn
							? 'the flagged layer image'
							: 'the deck flagged whole'
					).toHaveCSS( 'filter', /^blur\(/ );
				} else {
					await expect(
						photo( deck ),
						'a photo stays sharp when only the layer image is flagged'
					).toHaveCSS( 'filter', 'none' );
				}
				for ( const other of decks.filter( ( one ) => one !== deck ) ) {
					await expect(
						photo( other ),
						'a build with another deck'
					).toHaveCSS( 'filter', 'none' );
				}
			}
		} );
	}
} );

// Milestone 13's walkthrough (D-461): a tile shows its whole picture, as WordPress keeps the file, never a crop of it.
// The 8.25 in deck's photo came in the shop's thumbnail size, which WooCommerce crops square, and its tile showed the
// middle of a tall deck. Each tile's picture keeps its file's shape, and the tile fits it in whole, on a block theme
// and on Kadence; the ready builds' tiles share the template and are held to the same.
test.describe( "A build's tile shows its whole picture", () => {
	for ( const theme of [ 'twentytwentyfive', 'kadence' ] as const ) {
		test( `on ${ theme }, the guide's and the ready builds' tiles`, async ( {
			page,
			context,
			baseURL,
		}, info ) => {
			test.skip(
				'visual' === info.project.name,
				'The visual project has its own spec'
			);
			await context.addCookies( [
				{
					name: 'cbuild_e2e_theme',
					value: theme,
					url: baseURL || SITE,
				},
			] );
			await page.goto( asked( {} ) );
			const guide = page.locator( '.cbuild-guide__tiles .cbuild-preset' );
			await expect( guide ).toHaveCount( 3 );
			const pictures = page.locator( '.cbuild-preset__photo:visible' );
			await expect
				.poll(
					() => pictures.count(),
					"the guide's three and the ready builds'"
				)
				.toBeGreaterThan( 3 );
			// Each picture's shape against its file's as uploaded: WordPress names a size it makes "-<w>x<h>" after
			// the file's name, so the name without it is the whole file.
			const shapes = await pictures.evaluateAll( ( images ) =>
				Promise.all(
					( images as HTMLImageElement[] ).map( async ( image ) => {
						image.loading = 'eager';
						await image.decode().catch( () => undefined );
						const whole = new Image();
						whole.src = image.currentSrc.replace(
							/-\d+x\d+(\.\w+)$/,
							'$1'
						);
						await whole.decode().catch( () => undefined );
						return {
							src: image.currentSrc,
							shown: image.naturalWidth / image.naturalHeight,
							file: whole.naturalWidth / whole.naturalHeight,
							fit: getComputedStyle( image ).objectFit,
						};
					} )
				)
			);
			for ( const shape of shapes ) {
				expect( shape.fit, `${ shape.src } fitted in whole` ).toBe(
					'contain'
				);
				expect(
					Math.abs( shape.shown / shape.file - 1 ),
					`${ shape.src } keeps its file's shape`
				).toBeLessThan( 0.03 );
			}
		} );
	}
} );
