import { readFileSync } from 'node:fs';
import path from 'node:path';
import { test, expect, Page } from './support/test';

/**
 * Cowork's 0.8.0 review, F1 (#53; D-229): on the demo's dark Kadence, whose "contrast" preset is dark too, focus took
 * that colour and could not be seen on the near-black page, on a deck card and on the step's Next, where the theme's
 * own dark ring on buttons won. Here, on the same palette (the e2e theme mu-plugin's `demo-dark`), focus is moved by
 * the keyboard alone, and each ring must stand out from the page behind it at 3:1 or better (WCAG 2.4.11), in one of
 * its two tones, and be the builder's, not the theme's.
 */

const page = (): string =>
	(
		JSON.parse(
			readFileSync(
				path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
				'utf8'
			)
		) as { page: string }
	 ).page;

/**
 * A colour's relative luminance (WCAG 2.2), from `rgb(…)` or `rgba(…)` as computed styles write it.
 *
 * @param colour The computed colour.
 */
function luminance( colour: string ): number {
	const [ r, g, b ] = ( colour.match( /[\d.]+/g ) || [] )
		.slice( 0, 3 )
		.map( Number )
		.map( ( value ) => {
			const c = value / 255;
			return c <= 0.03928 ? c / 12.92 : ( ( c + 0.055 ) / 1.055 ) ** 2.4;
		} );
	return 0.2126 * ( r ?? 0 ) + 0.7152 * ( g ?? 0 ) + 0.0722 * ( b ?? 0 );
}

/**
 * The contrast ratio of two computed colours.
 *
 * @param a One colour.
 * @param b The other.
 */
function ratio( a: string, b: string ): number {
	const one = luminance( a );
	const two = luminance( b );
	return ( Math.max( one, two ) + 0.05 ) / ( Math.min( one, two ) + 0.05 );
}

/**
 * Press Tab until the element `selector` matches has focus, or the focus ring's element does.
 *
 * @param on       The page.
 * @param selector What should come to have focus.
 */
async function tabTo( on: Page, selector: string ) {
	for ( let i = 0; i < 120; i++ ) {
		await on.keyboard.press( 'Tab' );
		if (
			await on.evaluate(
				( s ) =>
					document.documentElement.ownerDocument.activeElement?.matches(
						s
					) ?? false,
				selector
			)
		) {
			return;
		}
	}
	throw new Error( `Tab never reached ${ selector }` );
}

/**
 * The ring around the element `selector` matches: its outline and the colour of the band its box-shadow draws.
 *
 * @param on       The page.
 * @param selector The element the ring is drawn on.
 */
async function ring( on: Page, selector: string ) {
	return on.evaluate( ( s ) => {
		const element = document.querySelector( s );
		if ( ! element ) {
			throw new Error( `No ${ s }` );
		}
		const style = getComputedStyle( element );
		const band = style.boxShadow.match( /rgba?\([^)]+\)(?![^,]*inset)/g );
		return {
			outline: style.outlineColor,
			width: parseFloat( style.outlineWidth ),
			outlineStyle: style.outlineStyle,
			band: band?.[ band.length - 1 ] ?? '',
			ground: getComputedStyle( document.body ).backgroundColor,
		};
	}, selector );
}

test.describe( 'Focus on a dark theme whose contrast colour is dark', () => {
	test.beforeEach( async ( { context, baseURL } ) => {
		const url = baseURL || 'http://localhost:8898';
		await context.addCookies( [
			{ name: 'cbuild_e2e_theme', value: 'kadence', url },
			{ name: 'cbuild_e2e_scheme', value: 'demo-dark', url },
		] );
	} );

	test( 'a deck card and the step’s Next each show a ring that stands out from the page', async ( {
		page: on,
	}, info ) => {
		test.skip(
			info.project.name !== 'chromium-desktop' &&
				info.project.name !== 'firefox-desktop',
			'Chromium and Firefox at a desktop width: the ring is the same at every width, and WebKit moves Tab past radios unless the system says otherwise.'
		);
		// A width in the link, so the step lists a deck to focus (D-241).
		const url = new URL( page() );
		url.searchParams.set( 'cbuild[_width]', '8in' );
		await on.goto( url.toString() );
		await expect( on.locator( '.cbuild' ) ).toHaveAttribute(
			'aria-busy',
			'false'
		);
		// The page is the demo's: near-black behind a dark "contrast" preset.
		expect(
			await on.evaluate( () =>
				getComputedStyle( document.documentElement )
					.getPropertyValue( '--wp--preset--color--contrast' )
					.trim()
			)
		).toBe( '#2a2a2a' );

		const checks = [
			{
				name: 'a deck card',
				focus: '.cbuild-card__control',
				drawn: '.cbuild-card:has(> .cbuild-card__control:focus-visible)',
			},
			{
				name: 'the step’s Next',
				focus: '.cbuild-step__next',
				drawn: '.cbuild-step__next:focus-visible',
			},
		];
		for ( const check of checks ) {
			await tabTo( on, check.focus );
			// A theme's button can carry a transition on every property, Kadence's does, so the ring is read once
			// it has settled.
			await expect
				.poll( async () => ( await ring( on, check.drawn ) ).width, {
					message: check.name,
				} )
				.toBe( 2 );
			const seen = await ring( on, check.drawn );
			expect( seen.outlineStyle, check.name ).toBe( 'solid' );
			expect(
				Math.max(
					ratio( seen.outline, seen.ground ),
					seen.band ? ratio( seen.band, seen.ground ) : 1
				),
				`${ check.name }: ${ JSON.stringify( seen ) }`
			).toBeGreaterThanOrEqual( 3 );
			// The two tones contrast with each other too, so the ring reads on a ground of either.
			expect(
				ratio( seen.outline, seen.band ),
				check.name
			).toBeGreaterThanOrEqual( 3 );
		}
	} );
} );
