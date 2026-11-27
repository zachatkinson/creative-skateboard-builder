import { readFileSync } from 'node:fs';
import path from 'node:path';
import type { BrowserContext } from '@playwright/test';
import { axe } from './support/axe';
import { expect, test, Page, SITE } from './support/test';

/**
 * Milestone 14, the 3D view (spec: Ready for 3D; #139). The 3D test kit is the builder kit with View in 3D on, and its
 * page the builder page's words, so the two differ by the switch alone. Until the server's scene lands the e2e scene
 * mu-plugin gives the view the scene contract's fixture, by cookie (tests/e2e/mu-plugins/cbuild-e2e-scene.php).
 *
 * The view's frames and memory are read through its probe, which the page turns on only with `data-cbuild-probe` on
 * its root: `frames` is three.js's own count of renders, so a test reads what was drawn, not what was asked.
 */

interface Fixture {
	kit: number;
	page: string;
	parts: Record< string, number >;
	three: { kit: number; page: string; quiet: string };
}

interface Probe {
	frames: number;
	geometries: number;
	textures: number;
	groups: Record< string, string >;
	closed: boolean;
	playing: boolean;
	board: number[];
}

const fixture = (): Fixture =>
	JSON.parse(
		readFileSync(
			path.join( process.cwd(), 'tmp', 'e2e-builder.json' ),
			'utf8'
		)
	);

/**
 * A page's address with a build on the wheels step: a deck, trucks and wheels picked, so the scene has each part.
 *
 * @param page The page's address.
 */
const build = ( page: string ): string => {
	const url = new URL( page );
	const { parts } = fixture();
	for ( const [ slot, sku ] of [
		[ 'deck', 'builder-test-deck-8-0' ],
		[ 'trucks', 'builder-test-trucks-139' ],
		[ 'wheels', 'builder-test-wheels-52' ],
	] as const ) {
		url.searchParams.set( `cbuild[${ slot }]`, String( parts[ sku ] ) );
	}
	url.searchParams.set( 'cbuild[step]', 'wheels' );
	return url.toString();
};

/**
 * Give the page's requests the e2e scene.
 *
 * @param context The browser's context.
 * @param value   `fixture`, or `wheels` for the fixture with other wheels.
 */
async function scene( context: BrowserContext, value = 'fixture' ) {
	await context.addCookies( [
		{ name: 'cbuild_e2e_scene', value, url: SITE },
	] );
}

/**
 * Turn the view's probe on, before the page's scripts run.
 *
 * @param page The page.
 */
async function probing( page: Page ) {
	await page.addInitScript( () => {
		const mark = () =>
			document.documentElement?.setAttribute( 'data-cbuild-probe', '' );
		if ( document.documentElement ) {
			mark();
		} else {
			document.addEventListener( 'readystatechange', mark, {
				once: true,
			} );
		}
	} );
}

const probe = ( page: Page ): Promise< Probe | null > =>
	page.evaluate(
		() =>
			(
				window as Window & { cbuildProbe?: { probe: () => Probe } }
			 ).cbuildProbe?.probe() ?? null
	);

const frames = async ( page: Page ): Promise< number > =>
	( await probe( page ) )?.frames ?? -1;

/**
 * Wait until the view draws nothing more: its first frame, the room's lighting and the textures in, each drawn once.
 *
 * @param page The page.
 */
async function still( page: Page ) {
	let last = -2;
	await expect
		.poll(
			async () => {
				const now = await frames( page );
				const same = now === last && now > 0;
				last = now;
				return same;
			},
			{ intervals: [ 600 ], timeout: 20_000 }
		)
		.toBe( true );
}

/**
 * Whether the engine draws WebGL 2 here. Each engine Playwright runs does; a test that needs it says so if one stops.
 *
 * @param page The page.
 */
const drawsWebGL = ( page: Page ): Promise< boolean > =>
	page.evaluate(
		() => null !== document.createElement( 'canvas' ).getContext( 'webgl2' )
	);

/**
 * Open the 3D view on the 3D kit's page and wait until it is still.
 *
 * @param page The page.
 */
async function open( page: Page ) {
	await page.goto( build( fixture().three.page ) );
	test.skip(
		! ( await drawsWebGL( page ) ),
		'This browser has no WebGL 2 here; the fallback tests cover it.'
	);
	await page.getByRole( 'button', { name: 'View in 3D' } ).click();
	await expect(
		page.getByRole( 'button', { name: 'View in 3D' } )
	).toHaveAttribute( 'aria-pressed', 'true' );
	await expect( page.locator( '.cbuild-3d__canvas' ) ).toBeVisible();
	await still( page );
}

/**
 * The 2D board is back, the message under it, and the build goes on: Next still takes the shopper on.
 *
 * @param page The page.
 */
async function flat( page: Page ) {
	await expect( page.locator( '.cbuild-3d__failed' ) ).toBeVisible();
	await expect( page.locator( '.cbuild-3d__failed' ) ).toHaveText(
		'The 3D view can’t be shown here, so the board is drawn flat.'
	);
	await expect( page.locator( '.cbuild-3d__stage' ) ).toBeHidden();
	await expect(
		page.getByRole( 'button', { name: 'View in 3D' } )
	).toHaveAttribute( 'aria-pressed', 'false' );
	await expect(
		page.locator( '.cbuild__stage .cbuild-board' ).first()
	).toBeVisible();
	await page.getByRole( 'button', { name: /^Next/ } ).click();
	await expect( page.locator( '#cbuild-heading' ) ).toHaveText( /bearings/i );
}

test.describe( '3D view (milestone 14)', () => {
	test.skip(
		( { javaScriptEnabled } ) => false === javaScriptEnabled,
		'The 3D view needs the store; the no-JavaScript test below covers the page without it.'
	);

	test.beforeEach( async ( { context, page } ) => {
		await scene( context );
		await probing( page );
	} );

	test( 'opens only on the press: its script and nothing else of it loads before', async ( {
		page,
	} ) => {
		const asked: string[] = [];
		page.on( 'request', ( request ) => asked.push( request.url() ) );
		await page.goto( build( fixture().three.page ) );
		await expect( page.locator( '.cbuild-3d__stage' ) ).toBeHidden();
		expect(
			asked.filter( ( url ) => url.includes( '/build/builder/three' ) )
		).toEqual( [] );
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await expect( page.locator( '.cbuild-3d__stage' ) ).toBeVisible();
		expect(
			asked.filter( ( url ) => url.includes( '/build/builder/three.js' ) )
		).toHaveLength( 1 );
	} );

	test( 'draws nothing over 5 s left alone', async ( { page } ) => {
		await open( page );
		const before = await frames( page );
		await page.waitForTimeout( 5000 );
		expect( await frames( page ) ).toBe( before );
	} );

	test( 'draws nothing while off the screen, and the change once it is back', async ( {
		page,
	} ) => {
		await open( page );
		// The builder pushed far down the page, the page at its top: the canvas is off the screen, its size the same.
		await page.evaluate( () => {
			const spacer = document.createElement( 'div' );
			spacer.style.blockSize = '4000px';
			document.body.prepend( spacer );
			window.scrollTo( 0, 0 );
		} );
		await page.waitForTimeout( 300 );
		const before = await frames( page );
		await page.evaluate( () =>
			document.querySelector( '.cbuild-3d__canvas' )?.dispatchEvent(
				new KeyboardEvent( 'keydown', {
					key: 'ArrowLeft',
					bubbles: true,
				} )
			)
		);
		await page.waitForTimeout( 1000 );
		expect( await frames( page ) ).toBe( before );
		await page.locator( '.cbuild-3d__canvas' ).scrollIntoViewIfNeeded();
		await expect.poll( () => frames( page ) ).toBeGreaterThan( before );
	} );

	test( 'draws nothing while the tab is hidden, and the change once it shows', async ( {
		page,
	} ) => {
		await open( page );
		const hide = ( hidden: boolean ) =>
			page.evaluate( ( on ) => {
				Object.defineProperty( document, 'hidden', {
					configurable: true,
					get: () => on,
				} );
				Object.defineProperty( document, 'visibilityState', {
					configurable: true,
					get: () => ( on ? 'hidden' : 'visible' ),
				} );
				document.dispatchEvent( new Event( 'visibilitychange' ) );
			}, hidden );
		await hide( true );
		const before = await frames( page );
		await page.locator( '.cbuild-3d__canvas' ).focus();
		await page.keyboard.press( 'ArrowLeft' );
		await page.waitForTimeout( 1000 );
		expect( await frames( page ) ).toBe( before );
		await hide( false );
		await expect.poll( () => frames( page ) ).toBeGreaterThan( before );
	} );

	test( 'a part changed swaps that part’s group alone', async ( {
		page,
	} ) => {
		await open( page );
		const was = ( await probe( page ) )?.groups ?? {};
		// The server's own scene of the build at its step (lane 1): the deck, trucks and wheels picked, no hardware yet.
		expect( Object.keys( was ).sort() ).toEqual(
			[ 'deck', 'trucks', 'wheels' ].sort()
		);
		// Other wheels picked: the next answer's scene has their sizes, and the same deck and trucks.
		await page
			.getByRole( 'radio', { name: 'Choose Builder test wheels 54' } )
			.check();
		await expect
			.poll( async () => ( await probe( page ) )?.groups.wheels )
			.not.toBe( was.wheels );
		const now = ( await probe( page ) )?.groups ?? {};
		expect( now.deck ).toBe( was.deck );
		expect( now.trucks ).toBe( was.trucks );
	} );

	// F1: a texture whose image fails leaves its part in the part's colour, never black.
	test( 'a texture that fails to load leaves the part in its colour', async ( {
		page,
		context,
	} ) => {
		// The visual tests' board, whose textures are the fixture's own files, and each of them not found.
		await scene( context, 'pinned' );
		await page.route( '**/tests/e2e/fixtures/layers/**', ( route ) =>
			route.fulfill( { status: 404, body: '' } )
		);
		await open( page );
		await page
			.getByRole( 'group', { name: 'Views of the board' } )
			.getByRole( 'button', { name: 'Underside', exact: true } )
			.click();
		await still( page );
		const canvas = page.locator( '.cbuild-3d__canvas' );
		const shot = ( await canvas.screenshot() ).toString( 'base64' );
		// The middle of the underside, between the trucks: the deck's bottom, in maple (#dcbf8f), lit.
		const [ r, g, b ] = await page.evaluate( async ( png ) => {
			const image = new Image();
			image.src = `data:image/png;base64,${ png }`;
			await image.decode();
			const sheet = document.createElement( 'canvas' );
			sheet.width = image.width;
			sheet.height = image.height;
			const pen = sheet.getContext( '2d' )!;
			pen.drawImage( image, 0, 0 );
			return Array.from(
				pen.getImageData(
					Math.floor( image.width / 2 ),
					Math.floor( image.height / 2 ),
					1,
					1
				).data
			);
		}, shot );
		expect( r! + g! + b! ).toBeGreaterThan( 3 * 110 );
		expect( r! ).toBeGreaterThan( b! );
	} );

	test( 'closing gives back every geometry and texture', async ( {
		page,
	} ) => {
		await open( page );
		const shown = await probe( page );
		expect( shown?.geometries ).toBeGreaterThan( 0 );
		expect( shown?.textures ).toBeGreaterThan( 0 );
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await expect( page.locator( '.cbuild-3d__stage' ) ).toBeHidden();
		const closed = await probe( page );
		expect( closed?.closed ).toBe( true );
		expect( closed?.geometries ).toBe( 0 );
		expect( closed?.textures ).toBe( 0 );
		await expect(
			page.locator( '.cbuild__stage .cbuild-board' ).first()
		).toBeVisible();
	} );

	test( 'turns and zooms from the keyboard, with focus shown, and its buttons move the camera', async ( {
		page,
	} ) => {
		await open( page );
		await page.getByRole( 'button', { name: 'View in 3D' } ).focus();
		await page.keyboard.press( 'Tab' );
		const canvas = page.locator( '.cbuild-3d__canvas' );
		await expect( canvas ).toBeFocused();
		expect(
			await canvas.evaluate( ( shown ) =>
				shown.matches( ':focus-visible' )
			)
		).toBe( true );
		expect(
			await canvas.evaluate(
				( shown ) => getComputedStyle( shown ).outlineStyle
			)
		).not.toBe( 'none' );
		// Named with the build's sentence, and told how to move it.
		await expect( canvas ).toHaveAttribute( 'role', 'img' );
		await expect( canvas ).toHaveAccessibleName( /\S/ );
		await expect( canvas ).toHaveAccessibleDescription(
			/arrow keys to turn the board/
		);
		for ( const key of [ 'ArrowLeft', 'ArrowUp', '+', '-' ] ) {
			const before = await frames( page );
			await page.keyboard.press( key );
			await expect.poll( () => frames( page ) ).toBeGreaterThan( before );
		}
		for ( const name of [
			'Top',
			'Underside',
			'Graphic close-up',
			'Reset view',
		] ) {
			const before = await frames( page );
			await page
				.getByRole( 'group', { name: 'Views of the board' } )
				.getByRole( 'button', { name, exact: true } )
				.press( 'Enter' );
			await expect.poll( () => frames( page ) ).toBeGreaterThan( before );
		}
	} );

	test( 'with reduced motion the camera goes at once and nothing moves on its own', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page );
		const before = await frames( page );
		await page
			.getByRole( 'group', { name: 'Views of the board' } )
			.getByRole( 'button', { name: 'Top', exact: true } )
			.click();
		await page.waitForTimeout( 1000 );
		// One frame for the new place, where a move would draw some 30 over its 500 ms.
		expect( ( await frames( page ) ) - before ).toBeLessThanOrEqual( 2 );
		const after = await frames( page );
		await page.waitForTimeout( 3000 );
		expect( await frames( page ) ).toBe( after );
	} );

	test( 'offers no trick where the kit’s Trick buttons is off', async ( {
		page,
	} ) => {
		await page.goto( build( fixture().three.quiet ) );
		test.skip(
			! ( await drawsWebGL( page ) ),
			'This browser has no WebGL 2 here; the fallback tests cover it.'
		);
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await expect(
			page.getByRole( 'group', { name: 'Views of the board' } )
		).toBeVisible();
		await still( page );
		await expect( page.locator( '.cbuild-3d__tricks' ) ).toHaveCount( 0 );
		await expect(
			page.getByRole( 'group', { name: 'Tricks' } )
		).toHaveCount( 0 );
	} );

	test( 'a trick plays on its press alone, its buttons disabled, and lands where it took off', async ( {
		page,
	} ) => {
		await open( page );
		const tricks = page.getByRole( 'group', { name: 'Tricks' } );
		await expect( tricks.getByRole( 'button' ) ).toHaveText( [
			'Ollie',
			'Kickflip',
			'Heelflip',
			'Pop shove-it',
			'360 flip',
		] );
		// None plays on its own.
		const rest = await probe( page );
		expect( rest!.board ).toEqual( [ 0, 0, 0, 0, 0, 0 ] );
		expect( rest!.playing ).toBe( false );
		await page.waitForTimeout( 1500 );
		expect( await frames( page ) ).toBe( rest!.frames );
		for ( const one of await tricks.getByRole( 'button' ).all() ) {
			await expect( one ).toHaveAttribute( 'aria-disabled', 'false' );
		}
		// The press and the landing, timed in the page: from the click to the buttons free again.
		const timed = page.evaluate(
			() =>
				new Promise< { took: number; high: number } >( ( done ) => {
					const button = document.querySelector< HTMLButtonElement >(
						'.cbuild-3d__tricks [value="kickflip"]'
					)!;
					const probed = (
						window as Window & {
							cbuildProbe?: {
								probe: () => { board: number[] };
							};
						}
					 ).cbuildProbe!;
					let high = 0;
					const start = performance.now();
					const watch = () => {
						high = Math.max( high, probed.probe().board[ 2 ]! );
						if (
							'false' === button.getAttribute( 'aria-disabled' )
						) {
							done( { took: performance.now() - start, high } );
							return;
						}
						requestAnimationFrame( watch );
					};
					button.click();
					requestAnimationFrame( watch );
				} )
		);
		await expect(
			tricks.getByRole( 'button', { name: 'Ollie' } )
		).toHaveAttribute( 'aria-disabled', 'true' );
		const { took, high } = await timed;
		// A kickflip is 1,200 ms; a frame either side, and a slow machine's late frames, are allowed for.
		expect( took ).toBeGreaterThanOrEqual( 1150 );
		expect( took ).toBeLessThan( 3000 );
		expect( high ).toBeGreaterThan( 100 );
		const landed = await probe( page );
		expect( landed!.board ).toEqual( [ 0, 0, 0, 0, 0, 0 ] );
		expect( landed!.playing ).toBe( false );
		// Some 70 frames at 60 a second; at least a frame for every 100 ms on a loaded machine.
		expect( landed!.frames - rest!.frames ).toBeGreaterThan( 12 );
		for ( const one of await tricks.getByRole( 'button' ).all() ) {
			await expect( one ).toHaveAttribute( 'aria-disabled', 'false' );
		}
		await still( page );
		// Landed, it draws nothing more.
		const after = await frames( page );
		await page.waitForTimeout( 1500 );
		expect( await frames( page ) ).toBe( after );
	} );

	test( 'a trick is played from the keyboard, and keeps focus on its button', async ( {
		page,
	} ) => {
		await open( page );
		const ollie = page
			.getByRole( 'group', { name: 'Tricks' } )
			.getByRole( 'button', { name: 'Ollie' } );
		// Measured where a shopper sees the buttons, not under the phone's bar at the scroll the press left.
		await ollie.evaluate( ( shown ) =>
			shown.scrollIntoView( { block: 'center' } )
		);
		await ollie.focus();
		await page.keyboard.press( 'Enter' );
		await expect( ollie ).toHaveAttribute( 'aria-disabled', 'true' );
		await expect( ollie ).toBeFocused();
		// While it plays, the meets-WCAG check reads the buttons disabled.
		await axe( page, 'the 3D view, a trick playing', '.cbuild-3d' );
		await expect( ollie ).toHaveAttribute( 'aria-disabled', 'false', {
			timeout: 5000,
		} );
		await expect( ollie ).toBeFocused();
		expect( ( await probe( page ) )!.board ).toEqual( [
			0, 0, 0, 0, 0, 0,
		] );
	} );

	test( 'with reduced motion, or Motion subtle, no trick is offered and none plays', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page );
		await expect( page.locator( '.cbuild-3d__tricks' ) ).toBeHidden();
		await expect(
			page.getByRole( 'group', { name: 'Tricks' } )
		).toHaveCount( 0 );
		const before = await frames( page );
		const played = await page.evaluate( () => {
			const probed = (
				window as Window & {
					cbuildProbe?: {
						play: ( trick: unknown ) => Promise< boolean >;
						tricks: Record< string, unknown >;
					};
				}
			 ).cbuildProbe!;
			return probed.play( probed.tricks.ollie );
		} );
		expect( played ).toBe( false );
		await page.waitForTimeout( 1000 );
		expect( await frames( page ) ).toBe( before );
		// As the open view's own check: its controls measured where a shopper sees them on a phone.
		await page
			.locator( '.cbuild-3d__open' )
			.evaluate( ( shown ) =>
				shown.scrollIntoView( { block: 'center' } )
			);
		await axe( page, 'the 3D view, reduced motion', '.cbuild' );
		// The kit's Motion subtle hides them too.
		await page.emulateMedia( { reducedMotion: 'no-preference' } );
		await expect( page.locator( '.cbuild-3d__tricks' ) ).toBeVisible();
		await page
			.locator( '.cbuild' )
			.evaluate( ( root ) => root.classList.add( 'cbuild--subtle' ) );
		await expect( page.locator( '.cbuild-3d__tricks' ) ).toBeHidden();
	} );

	test( 'its ground is the builder’s, and a theme’s own where it sets one', async ( {
		page,
	} ) => {
		await open( page );
		const read = () =>
			page.evaluate( () => {
				const frame = document.querySelector( '.cbuild-3d__frame' )!;
				const canvas =
					document.querySelector< HTMLCanvasElement >(
						'.cbuild-3d__canvas'
					)!;
				const sample = document.createElement( 'span' );
				sample.style.background = 'var(--cbuild-ground)';
				frame.append( sample );
				const ground = getComputedStyle( sample ).backgroundColor;
				sample.remove();
				return {
					frame: getComputedStyle( frame ).backgroundColor,
					ground,
					canvas: getComputedStyle( canvas ).backgroundColor,
					// The renderer's own context: a canvas gives back the context it already has.
					alpha: (
						canvas.getContext( 'webgl2' ) as WebGL2RenderingContext
					 ).getContextAttributes()?.alpha,
				};
			} );
		const shown = await read();
		expect( shown.ground ).not.toBe( 'rgba(0, 0, 0, 0)' );
		expect( shown.frame ).toBe( shown.ground );
		expect( shown.canvas ).toBe( 'rgba(0, 0, 0, 0)' );
		expect( shown.alpha ).toBe( true );
		await page
			.locator( '.cbuild' )
			.evaluate( ( root: HTMLElement ) =>
				root.style.setProperty(
					'--cbuild-3d-ground',
					'rgb(10, 20, 30)'
				)
			);
		expect( ( await read() ).frame ).toBe( 'rgb(10, 20, 30)' );
	} );

	test( 'on a touch screen a vertical swipe scrolls the page', async ( {
		page,
	} ) => {
		await open( page );
		expect(
			await page
				.locator( '.cbuild-3d__canvas' )
				.evaluate( ( shown ) => getComputedStyle( shown ).touchAction )
		).toBe( 'pan-y' );
	} );

	// D-468: a plugin blurs the view by class, the deck's from cbuild_part_classes, and puts its veil in the frame,
	// which the view never replaces: the veil stays through a part's change, a step's and a close and reopen.
	test( 'a plugin’s class stays on the canvas and its veil in the frame through every change', async ( {
		page,
		context,
	} ) => {
		await context.addCookies( [
			{
				name: 'cbuild_e2e_veil',
				value: String( fixture().parts[ 'builder-test-deck-8-0' ] ),
				url: SITE,
			},
		] );
		await open( page );
		const canvas = page.locator( '.cbuild-3d__canvas' );
		const veil = page.locator( '.cbuild-3d__frame > .cbuild-e2e-veil' );
		const marked = () =>
			page
				.locator( '.cbuild-3d__frame' )
				.evaluate( ( frame ) =>
					frame.setAttribute( 'data-e2e-frame', 'kept' )
				);
		await marked();
		const kept = async ( when: string ) => {
			await expect( canvas, when ).toHaveClass( /(^| )is-flagged( |$)/ );
			await expect( canvas, when ).toHaveClass( /cbuild-3d__canvas/ );
			await expect( veil, when ).toHaveCount( 1 );
			await expect(
				page.locator( '.cbuild-3d__frame[data-e2e-frame="kept"]' ),
				when
			).toHaveCount( 1 );
			expect(
				await page
					.locator( '.cbuild-3d__frame' )
					.evaluate(
						( frame ) => getComputedStyle( frame ).position
					),
				when
			).toBe( 'relative' );
		};
		await kept( 'open' );
		// A redraw: the camera moved, from a button the veil must not cover.
		const before = await frames( page );
		await page
			.getByRole( 'group', { name: 'Views of the board' } )
			.getByRole( 'button', { name: 'Top', exact: true } )
			.click();
		await expect.poll( () => frames( page ) ).toBeGreaterThan( before );
		await kept( 'after a redraw' );
		await page
			.getByRole( 'radio', { name: 'Choose Builder test wheels 54' } )
			.check();
		await expect( page.locator( '.cbuild' ) ).not.toHaveAttribute(
			'aria-busy',
			'true'
		);
		await kept( 'after a part change' );
		await page.getByRole( 'button', { name: /^Next/ } ).click();
		await expect( page.locator( '#cbuild-heading' ) ).toHaveText(
			/bearings/i
		);
		await kept( 'after a step change' );
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await expect( page.locator( '.cbuild-3d__stage' ) ).toBeHidden();
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await expect( page.locator( '.cbuild-3d__stage' ) ).toBeVisible();
		await kept( 'closed and opened again' );
		// The plugin's reveal lifts its blur, by its own class on the page's root.
		expect(
			await canvas.evaluate(
				( shown ) => getComputedStyle( shown ).filter
			)
		).toContain( 'blur' );
		await page.getByRole( 'button', { name: 'Show', exact: true } ).click();
		expect(
			await canvas.evaluate(
				( shown ) => getComputedStyle( shown ).filter
			)
		).toBe( 'none' );
	} );

	test( 'meets WCAG 2.2 AA open', async ( { page } ) => {
		await open( page );
		// On a phone the open view is taller than the screen: its controls are measured where a shopper sees them,
		// not where the bar fixed to the foot of the screen lies over them at the scroll the press left.
		await page
			.locator( '.cbuild-3d__open' )
			.evaluate( ( shown ) =>
				shown.scrollIntoView( { block: 'center' } )
			);
		await axe( page, 'the 3D view, open', '.cbuild' );
	} );

	test( 'without WebGL the board stays, with the message, and the build goes on', async ( {
		page,
	} ) => {
		await page.addInitScript( () => {
			const made = HTMLCanvasElement.prototype.getContext;
			HTMLCanvasElement.prototype.getContext = function (
				this: HTMLCanvasElement,
				kind: string,
				...rest: unknown[]
			) {
				return kind.startsWith( 'webgl' )
					? null
					: ( made as ( ...a: unknown[] ) => unknown ).call(
							this,
							kind,
							...rest
						);
			} as typeof made;
		} );
		await page.goto( build( fixture().three.page ) );
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await flat( page );
	} );

	test( 'a lost context goes back to the board', async ( { page } ) => {
		await open( page );
		await page.evaluate( () =>
			document
				.querySelector< HTMLCanvasElement >( '.cbuild-3d__canvas' )
				?.getContext( 'webgl2' )
				?.getExtension( 'WEBGL_lose_context' )
				?.loseContext()
		);
		await flat( page );
	} );

	test( 'an error thrown in the view goes back to the board', async ( {
		page,
	} ) => {
		await page.route( '**/build/builder/three.js*', ( route ) =>
			route.fulfill( {
				contentType: 'text/javascript',
				body: 'throw new Error( "A test’s error." );',
			} )
		);
		await page.goto( build( fixture().three.page ) );
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await flat( page );
	} );

	test( 'with 3D on, the page before the press is the page with it off', async ( {
		page,
		context,
	} ) => {
		test.skip(
			'chromium' !== page.context().browser()?.browserType().name(),
			'Layout shift and LCP entries are Chromium’s.'
		);
		/**
		 * Load a page and say what it fetched, its LCP's element and its layout shift.
		 *
		 * @param address The page.
		 */
		const measure = async ( address: string ) => {
			const fetched: { url: string; size: Promise< number > }[] = [];
			const heard = ( response: import( '@playwright/test' ).Response ) =>
				fetched.push( {
					url: response.url(),
					size: response
						.request()
						.sizes()
						.then( ( sizes ) => sizes.responseBodySize )
						.catch( () => 0 ),
				} );
			page.on( 'response', heard );
			await page.goto( address );
			await page.waitForLoadState( 'networkidle' );
			const seen = await page.evaluate(
				() =>
					new Promise< { lcp: string; cls: number } >( ( done ) => {
						let lcp = '';
						let cls = 0;
						new PerformanceObserver( ( list ) => {
							for ( const entry of list.getEntries() as ( PerformanceEntry & {
								element?: Element | null;
							} )[] ) {
								const element = entry.element;
								lcp = element
									? `${ element.tagName } ${ element.className }`
									: '';
							}
						} ).observe( {
							type: 'largest-contentful-paint',
							buffered: true,
						} );
						new PerformanceObserver( ( list ) => {
							for ( const entry of list.getEntries() as ( PerformanceEntry & {
								value: number;
								hadRecentInput: boolean;
							} )[] ) {
								cls += entry.hadRecentInput ? 0 : entry.value;
							}
						} ).observe( { type: 'layout-shift', buffered: true } );
						setTimeout( () => done( { lcp, cls } ), 500 );
					} )
			);
			page.off( 'response', heard );
			const sizes = await Promise.all(
				fetched.map( async ( { url, size } ) => ( {
					url: new URL( url ).pathname,
					size: await size,
				} ) )
			);
			return { ...seen, sizes };
		};
		const off = await measure( build( fixture().page ) );
		await scene( context );
		const on = await measure( build( fixture().three.page ) );
		const page_ = ( address: string ) => new URL( address ).pathname;
		const others = ( measured: typeof on, address: string ) =>
			measured.sizes
				.filter(
					( { url } ) =>
						url !== page_( address ) &&
						! url.includes( '/wp-json/' )
				)
				.sort( ( a, b ) => a.url.localeCompare( b.url ) );
		// The same files at the same sizes, three.js not among them; the page itself larger by its scene and the
		// view's few elements alone.
		expect( others( on, fixture().three.page ) ).toEqual(
			others( off, fixture().page )
		);
		const document = ( measured: typeof on, address: string ) =>
			measured.sizes.find( ( { url } ) => url === page_( address ) )
				?.size ?? 0;
		const grew =
			document( on, fixture().three.page ) -
			document( off, fixture().page );
		expect( grew ).toBeLessThan( 6 * 1024 );
		expect( on.lcp ).toBe( off.lcp );
		expect( on.cls ).toBeLessThanOrEqual( off.cls + 0.001 );
	} );
} );

test.describe( '3D view without JavaScript (milestone 14)', () => {
	test.skip(
		( { javaScriptEnabled } ) => false !== javaScriptEnabled,
		'Only without JavaScript.'
	);

	test( 'the board is drawn and View in 3D is not offered', async ( {
		page,
		context,
	} ) => {
		await scene( context );
		await page.goto( build( fixture().three.page ) );
		await expect(
			page.locator( '.cbuild__stage .cbuild-board' ).first()
		).toBeVisible();
		await expect(
			page.getByRole( 'button', { name: 'View in 3D' } )
		).toBeHidden();
		await expect( page.locator( '.cbuild-3d__stage' ) ).toBeHidden();
	} );
} );
