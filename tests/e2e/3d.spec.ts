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
	camera: number[];
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
 * The state route, which answers each pick and each Next.
 *
 * @param url A request's address.
 */
const isRoute = ( url: string ): boolean =>
	/cbuild(%2F|\/)v1(%2F|\/)kits(%2F|\/)\d+(%2F|\/)state/.test( url );

/**
 * Wait until the builder has done with a press: no answer awaited, so the next press lands on the step the shopper
 * sees, as theirs would (D-529). A press while a step still crosses over is taken since #178 (D-566).
 *
 * @param page The page.
 */
async function settled( page: Page ) {
	await expect( page.locator( '.cbuild' ) ).not.toHaveAttribute(
		'aria-busy',
		'true'
	);
}

/**
 * Press Next and wait for what the press promises: the step after it and its answer drawn (D-529). Pressed sooner, a
 * pick or a Next could land on a step the answer was about to redraw.
 *
 * @param page The page.
 * @param step The next step's heading.
 */
async function next( page: Page, step: RegExp ) {
	await page.getByRole( 'button', { name: /^Next/ } ).click();
	await expect( page.locator( '#cbuild-heading' ) ).toHaveText( step );
	await settled( page );
}

/**
 * Pick a part on the step shown, and send the step with Next, waiting for the step after. The pick waits for what it
 * promises before Next is pressed: the radio checked, Next lit, and the route's answer to it in (D-529).
 *
 * @param page The page.
 * @param name The part's product name.
 * @param step The next step's heading.
 */
async function onward( page: Page, name: string, step: RegExp ) {
	const radio = page.getByRole( 'radio', {
		name: `Choose ${ name }`,
		exact: true,
	} );
	await Promise.all( [
		page.waitForResponse( ( answer ) => isRoute( answer.url() ) ),
		radio.check(),
	] );
	await expect( radio ).toBeChecked();
	await expect(
		page.getByRole( 'button', { name: /^Next/ } )
	).not.toHaveAttribute( 'aria-disabled', 'true' );
	await settled( page );
	await next( page, step );
}

/**
 * The 2D board is back, the message under it, and the build goes on: Next takes the shopper to the review, and Add to
 * cart puts the build in the cart (the spec reviewer's M14 review, S6).
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
	await settled( page );
	await next( page, /bearings/i );
	await onward( page, 'Builder test bearings', /hardware/i );
	await onward( page, 'Builder test bolts 1', /grip/i );
	await onward( page, 'Builder test grip', /check your build/i );
	await page
		.locator( '.cbuild' )
		.getByRole( 'button', { name: 'Add to cart' } )
		.click();
	await expect
		.poll( async () => {
			const cart = await (
				await page.request.get( '/wp-json/wc/store/v1/cart' )
			).json();
			return cart.items.map( ( item: { name: string } ) => item.name );
		} )
		.toEqual(
			expect.arrayContaining( [
				'Builder test deck 8.0',
				'Builder test bearings',
				'Builder test grip',
			] )
		);
}

/**
 * Hear the builder's `cbuild:3d-failed` events, from before the page's scripts run.
 *
 * @param page The page.
 */
async function hearFailures( page: Page ) {
	await page.addInitScript( () => {
		const heard: unknown[] = [];
		( window as Window & { cbuildFailed?: unknown[] } ).cbuildFailed =
			heard;
		document.addEventListener( 'cbuild:3d-failed', ( event ) =>
			heard.push( ( event as CustomEvent ).detail )
		);
	} );
}

const failures = ( page: Page ): Promise< unknown[] > =>
	page.evaluate(
		() =>
			( window as Window & { cbuildFailed?: unknown[] } ).cbuildFailed ??
			[]
	);

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
		// Without WebGL 2 the chunk still loads on the press, then the view goes back to the board, so the stage never
		// shows: CI's Linux Firefox has none (#173, D-538). The fallback tests cover that path.
		test.skip(
			! ( await drawsWebGL( page ) ),
			'This browser has no WebGL 2 here; the fallback tests cover it.'
		);
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
			'Side',
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

	// #171: Side puts the camera level with the deck's edge, turned a little toward the nose, so both wheels on the
	// near side show with the truck and the riser between them. With reduced motion it goes there at once.
	test( 'Side sends the camera level with the deck’s edge, turned a little (#171)', async ( {
		page,
		browserName,
	} ) => {
		// WebKit tabs to buttons only with Option held, as Safari does without Full Keyboard Access.
		const tab = 'webkit' === browserName ? 'Alt+Tab' : 'Tab';
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page );
		const places = page.getByRole( 'group', {
			name: 'Views of the board',
		} );
		await expect( places.getByRole( 'button' ) ).toHaveText( [
			'Reset view',
			'Top',
			'Underside',
			'Side',
			'Graphic close-up',
		] );
		// The places are in the page's order, one Tab each, and the group's last hands on to what follows it.
		await places.getByRole( 'button', { name: 'Reset view' } ).focus();
		for ( const name of [
			'Top',
			'Underside',
			'Side',
			'Graphic close-up',
		] ) {
			await page.keyboard.press( tab );
			await expect(
				places.getByRole( 'button', { name, exact: true } )
			).toBeFocused();
		}
		await page.keyboard.press( tab );
		await expect( places.locator( ':focus' ) ).toHaveCount( 0 );
		await places
			.getByRole( 'button', { name: 'Side', exact: true } )
			.press( 'Enter' );
		await expect
			.poll( async () => {
				const [ x, y, z, tx, ty, tz ] = ( await probe( page ) )!.camera;
				return [
					Math.round( ( y! - ty! ) * 10 ) / 10,
					Math.round( ( ( x! - tx! ) / ( z! - tz! ) ) * 100 ) / 100,
					z! - tz! > 0,
				];
			} )
			.toEqual( [ 0, 0.3, true ] );
		await axe(
			page,
			'the 3D view’s places, side on',
			'.cbuild-3d__places'
		);
	} );

	// Milestone 14's walkthrough, #5 and #6 (D-530): Side was seen with the board at the canvas's foot, and the phone's
	// Graphic close-up with the nose cut. Read from the pixels a shopper sees: Side puts the board in the canvas's
	// middle, clear of every edge, and the close-up holds the whole nose, its tail going past the edge as a close-up's
	// does.
	test( 'Side puts the board in the canvas’s middle, and the close-up holds the whole nose (walkthrough #5, #6)', async ( {
		page,
	} ) => {
		await page.emulateMedia( { reducedMotion: 'reduce' } );
		await open( page );
		const canvas = page.locator( '.cbuild-3d__canvas' );
		// The board's box in the canvas's picture: every pixel that is not the frame's ground, read at its top right
		// corner, which no place draws on.
		const drawn = async () => {
			await still( page );
			await canvas.evaluate( ( shown ) =>
				shown.scrollIntoView( { block: 'center' } )
			);
			const shot = ( await canvas.screenshot() ).toString( 'base64' );
			return page.evaluate( async ( png ) => {
				const image = await createImageBitmap(
					await (
						await fetch( `data:image/png;base64,${ png }` )
					).blob()
				);
				const paper = new OffscreenCanvas( image.width, image.height );
				const pen = paper.getContext( '2d' )!;
				pen.drawImage( image, 0, 0 );
				const { data } = pen.getImageData(
					0,
					0,
					image.width,
					image.height
				);
				const at = ( image.width - 1 ) * 4;
				const ground = [
					data[ at ]!,
					data[ at + 1 ]!,
					data[ at + 2 ]!,
				];
				let [ left, top, right, bottom ] = [
					image.width,
					image.height,
					-1,
					-1,
				];
				for ( let y = 0; y < image.height; y++ ) {
					for ( let x = 0; x < image.width; x++ ) {
						const i = ( y * image.width + x ) * 4;
						if (
							Math.abs( data[ i ]! - ground[ 0 ]! ) +
								Math.abs( data[ i + 1 ]! - ground[ 1 ]! ) +
								Math.abs( data[ i + 2 ]! - ground[ 2 ]! ) >
							24
						) {
							left = Math.min( left, x );
							right = Math.max( right, x );
							top = Math.min( top, y );
							bottom = Math.max( bottom, y );
						}
					}
				}
				return {
					height: image.height,
					above: top,
					below: image.height - 1 - bottom,
					before: left,
					after: image.width - 1 - right,
				};
			}, shot );
		};
		const places = page.getByRole( 'group', {
			name: 'Views of the board',
		} );

		await places
			.getByRole( 'button', { name: 'Side', exact: true } )
			.click();
		const side = await drawn();
		expect( side.above, 'Side: clear of the top' ).toBeGreaterThan( 0 );
		expect( side.below, 'Side: clear of the foot' ).toBeGreaterThan( 0 );
		expect( side.before, 'Side: the tail inside' ).toBeGreaterThan( 0 );
		expect( side.after, 'Side: the nose inside' ).toBeGreaterThan( 0 );
		expect(
			Math.abs( side.above - side.below ),
			`Side: in the middle, ${ side.above } px above and ${ side.below } px below`
		).toBeLessThanOrEqual( Math.max( 4, side.height * 0.05 ) );

		await places
			.getByRole( 'button', { name: 'Graphic close-up', exact: true } )
			.click();
		const close = await drawn();
		expect(
			close.above,
			'the close-up: the nose clear of the top'
		).toBeGreaterThan( 0 );
		expect(
			close.after,
			'the close-up: the nose clear of the side'
		).toBeGreaterThan( 0 );
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

	// The spec reviewer's M14 review, S7: a real swipe, where a phone's touch can be synthesised (Chromium's). It found
	// the camera turning by the swipe's first moves, before the browser takes the pan (F14).
	test( 'on a touch screen a vertical swipe over the canvas scrolls the page and leaves the camera', async ( {
		page,
	} ) => {
		await open( page );
		const canvas = page.locator( '.cbuild-3d__canvas' );
		test.skip(
			! test.info().project.use.hasTouch ||
				'chromium' !== page.context().browser()?.browserType().name(),
			'A touch swipe is synthesised through Chromium’s protocol, on a touch screen.'
		);
		// A control first: the same swipe over the page's heading. Linux's headless Chromium, CI's, scrolls no page with a
		// synthesised touch swipe, over the canvas or not, so there the test has nothing to show (#173, D-540).
		const swipes = await page.context().newCDPSession( page );
		await page.evaluate( () => window.scrollTo( 0, 0 ) );
		const heading = ( await page.locator( 'h1' ).first().boundingBox() )!;
		await swipes.send( 'Input.synthesizeScrollGesture', {
			x: Math.round( heading.x + 20 ),
			y: Math.round( heading.y + heading.height / 2 ),
			xDistance: 0,
			yDistance: -120,
			gestureSourceType: 'touch',
			speed: 600,
		} );
		const scrolled = await page
			.waitForFunction( () => window.scrollY >= 40, undefined, {
				timeout: 2000,
			} )
			.then( () => true )
			.catch( () => false );
		test.skip(
			! scrolled,
			'This browser’s synthesised touch swipe scrolls no page, over the canvas or not.'
		);
		await canvas.evaluate( ( shown ) =>
			shown.scrollIntoView( { block: 'center' } )
		);
		await still( page );
		const box = ( await canvas.boundingBox() )!;
		const shown = ( await probe( page ) )!;
		const from = await page.evaluate( () => window.scrollY );
		const session = await page.context().newCDPSession( page );
		await session.send( 'Input.synthesizeScrollGesture', {
			x: Math.round( box.x + box.width / 2 ),
			y: Math.round( box.y + box.height / 2 ),
			xDistance: 0,
			yDistance: -120,
			gestureSourceType: 'touch',
			speed: 600,
		} );
		await expect
			.poll( () => page.evaluate( () => window.scrollY ) )
			.toBeGreaterThan( from + 40 );
		// The camera did not turn: its place and the point it turns about are where they were, to a hundredth of a
		// millimetre, and the board has not moved. Read from the probe, not the pixels, which a late frame changes.
		await still( page );
		const now = ( await probe( page ) )!;
		expect( now.camera ).toHaveLength( 6 );
		now.camera.forEach( ( at, axis ) =>
			expect( at, `the camera's ${ axis }` ).toBeCloseTo(
				shown.camera[ axis ]!,
				2
			)
		);
		expect( now.board ).toEqual( shown.board );
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
		await hearFailures( page );
		await open( page );
		// The auditor's M14 review, F2: focus in the view, which hides, goes to View in 3D, not to the page.
		await page.locator( '.cbuild-3d__canvas' ).focus();
		await page.evaluate( () =>
			document
				.querySelector< HTMLCanvasElement >( '.cbuild-3d__canvas' )
				?.getContext( 'webgl2' )
				?.getExtension( 'WEBGL_lose_context' )
				?.loseContext()
		);
		await expect(
			page.getByRole( 'button', { name: 'View in 3D' } )
		).toBeFocused();
		// F8: the reason is told, for a shop's analytics.
		expect( await failures( page ) ).toEqual( [
			{ kit: fixture().three.kit, reason: 'lost' },
		] );
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
		await hearFailures( page );
		const warned: string[] = [];
		page.on( 'console', ( message ) => {
			if ( 'warning' === message.type() ) {
				warned.push( message.text() );
			}
		} );
		await page.goto( build( fixture().three.page ) );
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await expect
			.poll( () => failures( page ) )
			.toEqual( [ { kit: fixture().three.kit, reason: 'load' } ] );
		expect( warned ).toContainEqual(
			expect.stringContaining( 'The 3D view stopped (load).' )
		);
		await flat( page );
	} );

	// The auditor's M14 review, F13: the side line names a side the 3D view's camera does not keep.
	test( 'hides the side line while the view is open, and shows it again once it closes', async ( {
		page,
	} ) => {
		await open( page );
		await expect( page.locator( '.cbuild-picture__side' ) ).toBeHidden();
		await page.getByRole( 'button', { name: 'View in 3D' } ).click();
		await expect( page.locator( '.cbuild-picture__side' ) ).toBeVisible();
	} );

	// M14's walkthrough, finding 3: the flat board's Underside and Top stayed beside the view's own Top and Underside,
	// two buttons of each name, and "Top" stayed pressed whatever the camera showed (D-522).
	test( 'while open, one Top and one Underside show, the view’s, and the board’s come back on close as they were', async ( {
		page,
	} ) => {
		const named = ( name: string ) =>
			page
				.locator( '.cbuild' )
				.getByRole( 'button', { name, exact: true } );
		const turn = page.locator( '.cbuild-picture__sides' );
		const pressed = () =>
			turn
				.getByRole( 'button' )
				.evaluateAll( ( buttons ) =>
					buttons.map( ( button ) =>
						button.getAttribute( 'aria-pressed' )
					)
				);
		await page.goto( build( fixture().three.page ) );
		const before = await pressed();
		expect( before ).toHaveLength( 2 );
		await open( page );
		for ( const name of [ 'Top', 'Underside' ] ) {
			await expect( named( name ), name ).toHaveCount( 1 );
			await expect(
				page
					.getByRole( 'group', { name: 'Views of the board' } )
					.getByRole( 'button', { name, exact: true } )
			).toBeVisible();
		}
		await expect( turn ).toHaveAttribute( 'hidden', '' );
		const toggle = page.getByRole( 'button', { name: 'View in 3D' } );
		await expect( toggle ).toHaveAttribute( 'aria-pressed', 'true' );
		await toggle.click();
		await expect( toggle ).toHaveAttribute( 'aria-pressed', 'false' );
		await expect( turn ).toBeVisible();
		for ( const name of [ 'Top', 'Underside' ] ) {
			await expect( named( name ), name ).toHaveCount( 1 );
		}
		expect( await pressed() ).toEqual( before );
	} );

	// M14's walkthrough, finding 1: on a phone a tap on View in 3D left the page about 404 px further down, the canvas
	// above the top of the screen. The stage stops sticking as the view opens and grows by the view's height, and the
	// browser's scroll anchoring kept the cards under it in place (D-523). From the top of the page, from where the
	// stage starts to stick and from where it has stuck, the canvas is on the screen after the tap, its top on it.
	for ( const from of [ 0, 150, 500 ] ) {
		test( `on a phone a tap on View in 3D, ${ from } px down the page, leaves the canvas on the screen`, async ( {
			page,
		} ) => {
			test.skip(
				! test.info().project.use.hasTouch,
				'A tap needs a touch screen: chromium-phone and webkit-phone.'
			);
			await page.setViewportSize( { width: 390, height: 844 } );
			await page.goto( build( fixture().three.page ) );
			test.skip(
				! ( await drawsWebGL( page ) ),
				'This browser has no WebGL 2 here; the fallback tests cover it.'
			);
			await page.evaluate( ( down ) => window.scrollTo( 0, down ), from );
			await expect
				.poll( () => page.evaluate( () => window.scrollY ) )
				.toBe( from );
			const toggle = page.locator( '.cbuild-3d__open' );
			await toggle.tap();
			await expect( toggle ).toHaveAttribute( 'aria-pressed', 'true' );
			await still( page );
			const [ top, bottom, height ] = await page
				.locator( '.cbuild-3d__canvas' )
				.evaluate( ( canvas ) => {
					const box = canvas.getBoundingClientRect();
					return [ box.top, box.bottom, window.innerHeight ];
				} );
			expect( top, 'the canvas’s top' ).toBeGreaterThanOrEqual( -0.5 );
			expect( top, 'the canvas’s top' ).toBeLessThan( height! );
			expect( bottom, 'the canvas’s foot' ).toBeGreaterThan( 0 );
		} );
	}

	// M14's walkthrough, finding 2: on a phone held sideways the open view's 609 px frame lay over the words' column,
	// View in 3D, which closes it, and the turn's buttons with it (D-524).
	for ( const language of [ 'English', 'French' ] as const ) {
		test( `on a phone held sideways in ${ language }, the open view covers none of the controls, and View in 3D closes it`, async ( {
			page,
		} ) => {
			await page.setViewportSize( { width: 844, height: 390 } );
			if ( 'French' === language ) {
				await page.setExtraHTTPHeaders( {
					'x-cbuild-e2e-locale': 'fr_CA',
					'x-cbuild-e2e-translated': '1',
				} );
			}
			await page.goto( build( fixture().three.page ) );
			test.skip(
				! ( await drawsWebGL( page ) ),
				'This browser has no WebGL 2 here; the fallback tests cover it.'
			);
			const toggle = page.locator( '.cbuild-3d__open' );
			await toggle.click();
			await expect( toggle ).toHaveAttribute( 'aria-pressed', 'true' );
			await expect( page.locator( '.cbuild-3d__canvas' ) ).toBeVisible();
			await still( page );
			await toggle.evaluate( ( shown ) =>
				shown.scrollIntoView( { block: 'center' } )
			);
			const found = await toggle.evaluate( ( shown ) => {
				const box = ( element: Element ) => {
					const r = element.getBoundingClientRect();
					return [ r.left, r.top, r.right, r.bottom ];
				};
				const [ left, top, right, bottom ] = box( shown );
				const others = [
					...shown
						.closest( '.cbuild__stage' )!
						.querySelectorAll(
							'button, a, canvas, .cbuild-3d__frame'
						),
				]
					.filter(
						( other ) =>
							other !== shown &&
							0 !== other.getClientRects().length &&
							'hidden' !== getComputedStyle( other ).visibility
					)
					.filter( ( other ) => {
						const [ l, t, r, b ] = box( other );
						return (
							l! < right! &&
							r! > left! &&
							t! < bottom! &&
							b! > top!
						);
					} )
					.map(
						( other ) =>
							`${ other.tagName } ${ String( other.className ) } ${ other.textContent?.trim() }`
					);
				const hit = document.elementFromPoint(
					( left! + right! ) / 2,
					( top! + bottom! ) / 2
				);
				return { others, hit: hit === shown || shown.contains( hit ) };
			} );
			expect( found.others, 'controls under View in 3D' ).toEqual( [] );
			expect( found.hit, 'View in 3D at its own centre' ).toBe( true );
			await toggle.click( { timeout: 5000 } );
			await expect( toggle ).toHaveAttribute( 'aria-pressed', 'false' );
			await expect( page.locator( '.cbuild-3d__stage' ) ).toBeHidden();
		} );
	}

	// M14's walkthrough, finding 4: on a phone the fallback's message shared the parts' labels' row, squeezed into a
	// column beside them that ended where "Bearings" began (D-525). Upright on a phone and on a desktop, it has a row of
	// its own: no label, part or button shares its lines, or lies over it.
	for ( const [ name, size ] of [
		[ 'a phone', { width: 390, height: 844 } ],
		[ 'a desktop', { width: 1440, height: 900 } ],
	] as const ) {
		test( `on ${ name } the fallback’s message has a place of its own`, async ( {
			page,
		} ) => {
			await page.setViewportSize( size );
			await page.addInitScript( () => {
				HTMLCanvasElement.prototype.getContext = ( () =>
					null ) as typeof HTMLCanvasElement.prototype.getContext;
			} );
			// The whole build, on the grip step, as the walkthrough had it: bearings and hardware have no layer image, so
			// their labels are under the board.
			const url = new URL( build( fixture().three.page ) );
			for ( const [ slot, sku ] of [
				[ 'bearings', 'builder-test-bearings' ],
				[ 'hardware', 'builder-test-bolts-1' ],
				[ 'grip', 'builder-test-grip' ],
			] as const ) {
				url.searchParams.set(
					`cbuild[${ slot }]`,
					String( fixture().parts[ sku ] )
				);
			}
			url.searchParams.set( 'cbuild[step]', 'grip' );
			await page.goto( url.toString() );
			await page.locator( '.cbuild-3d__open' ).click();
			const message = page.locator( '.cbuild-3d__failed' );
			await expect( message ).toBeVisible();
			const over = await message.evaluate( ( shown ) => {
				const a = shown.getBoundingClientRect();
				return [
					...shown
						.closest( '.cbuild__stage' )!
						.querySelectorAll(
							'.cbuild-picture__item, .cbuild-picture__label, button, .cbuild-board'
						),
				]
					.filter(
						( other ) =>
							0 !== other.getClientRects().length &&
							'hidden' !== getComputedStyle( other ).visibility
					)
					.filter( ( other ) => {
						const b = other.getBoundingClientRect();
						return (
							b.width > 0 && a.top < b.bottom && a.bottom > b.top
						);
					} )
					.map(
						( other ) =>
							`${ String( other.className ) } ${ other.textContent?.trim() }`
					);
			} );
			expect( over, 'what shares the message’s lines' ).toEqual( [] );
		} );
	}

	// The auditor's M14 review, F7: View in 3D shared the turn's cell, and covered its buttons at 320 px in French; on
	// a phone held sideways it took a row of its own, so the board was smaller before the press. Upright it now goes
	// beside the heading, the board's box as with no 3D view; sideways the strip grows to hold it, the board its size
	// kept, from the first paint (D-483).
	for ( const [ name, size ] of [
		[ '320 px wide', { width: 320, height: 640 } ],
		[ 'a phone held sideways', { width: 844, height: 390 } ],
	] as const ) {
		test( `in French at ${ name }, View in 3D covers nothing, the board keeps its size and the strip holds it all`, async ( {
			page,
		} ) => {
			test.skip(
				'chromium' !== page.context().browser()?.browserType().name(),
				'Layout shift entries are Chromium’s; the other engines lay the strip out as their own projects show.'
			);
			await page.setViewportSize( size );
			await page.setExtraHTTPHeaders( {
				'x-cbuild-e2e-locale': 'fr_CA',
				'x-cbuild-e2e-translated': '1',
			} );
			const measured = async ( address: string ) => {
				await page.goto( build( address ) );
				await expect(
					page.locator( '.cbuild[data-ready]' )
				).toBeVisible();
				return page.evaluate(
					() =>
						new Promise< {
							boxes: Record< string, number[] >;
							turns: number[][];
							inside: string[];
							shifts: number;
						} >( ( done ) => {
							const box = ( element: Element | null ) => {
								const r = element?.getBoundingClientRect();
								return r ? [ r.x, r.y, r.width, r.height ] : [];
							};
							// Text kept for screen readers alone, clipped to a pixel, as the stage's caption is.
							const forReaders = ( element: Element ) => {
								for (
									let at: Element | null = element;
									at;
									at = at.parentElement
								) {
									if (
										'none' !==
										getComputedStyle( at ).clipPath
									) {
										return true;
									}
								}
								return false;
							};
							const stage =
								document.querySelector( '.cbuild__stage' )!;
							const edge = stage.getBoundingClientRect();
							// Every child the stage shows, each whole inside it.
							const inside = [ ...stage.querySelectorAll( '*' ) ]
								.filter( ( element ) => {
									const r = element.getBoundingClientRect();
									return (
										r.width > 0 &&
										r.height > 0 &&
										'hidden' !==
											getComputedStyle( element )
												.visibility &&
										null ===
											element.closest(
												'[hidden], .cbuild-3d__stage'
											) &&
										! forReaders( element )
									);
								} )
								.filter( ( element ) => {
									const r = element.getBoundingClientRect();
									return (
										r.top < edge.top - 0.5 ||
										r.bottom > edge.bottom + 0.5 ||
										r.left < edge.left - 0.5 ||
										r.right > edge.right + 0.5
									);
								} )
								.map( ( element ) =>
									String( element.className )
								);
							// The builder's own layout shifts from the first paint to ready.
							let shifts = 0;
							new PerformanceObserver( ( list ) => {
								for ( const entry of list.getEntries() as ( PerformanceEntry & {
									value: number;
									hadRecentInput: boolean;
									sources?: { node?: Node | null }[];
								} )[] ) {
									const ours = ( entry.sources ?? [] ).some(
										( source ) => {
											const node = source.node;
											const element =
												node instanceof Element
													? node
													: ( node?.parentElement ??
														null );
											return (
												null !==
												element?.closest( '.cbuild' )
											);
										}
									);
									shifts +=
										entry.hadRecentInput || ! ours
											? 0
											: entry.value;
								}
							} ).observe( {
								type: 'layout-shift',
								buffered: true,
							} );
							setTimeout(
								() =>
									done( {
										boxes: {
											board: box(
												document.querySelector(
													'.cbuild__stage .cbuild-board'
												)
											),
											open: box(
												document.querySelector(
													'.cbuild-3d__open'
												)
											),
										},
										turns: [
											...document.querySelectorAll(
												'.cbuild-picture__sides .cbuild-button'
											),
										].map( box ),
										inside,
										shifts,
									} ),
								500
							);
						} )
				);
			};
			const off = await measured( fixture().page );
			const on = await measured( fixture().three.page );
			const [ , , width, height ] = off.boxes.board!;
			if ( size.width < 700 ) {
				// Upright, the board's box is the one with no 3D view.
				expect( on.boxes.board ).toEqual( off.boxes.board );
			} else {
				expect( on.boxes.board!.slice( 2 ) ).toEqual( [
					width,
					height,
				] );
			}
			const apart = ( one: number[], other: number[] ) =>
				one[ 0 ]! >= other[ 0 ]! + other[ 2 ]! ||
				other[ 0 ]! >= one[ 0 ]! + one[ 2 ]! ||
				one[ 1 ]! >= other[ 1 ]! + other[ 3 ]! ||
				other[ 1 ]! >= one[ 1 ]! + one[ 3 ]!;
			expect( on.turns ).toHaveLength( 2 );
			for ( const turn of on.turns ) {
				expect(
					apart( on.boxes.open!, turn ),
					'View in 3D and a turn button'
				).toBe( true );
				expect(
					apart( on.boxes.board!, turn ),
					'the board and a turn button'
				).toBe( true );
			}
			expect(
				apart( on.boxes.open!, on.boxes.board! ),
				'View in 3D and the board'
			).toBe( true );
			expect( on.boxes.open![ 3 ] ).toBeGreaterThanOrEqual( 44 );
			expect( on.inside, 'outside the stage' ).toEqual( [] );
			// No shift from the first paint to ready past the page's with no 3D view, as the CLS check below measures.
			expect( on.shifts ).toBeLessThanOrEqual( off.shifts + 0.001 );
		} );
	}

	// On a phone held sideways the board grew from 122.6 to 130.0 px as the store took over (D-521): the turn's two
	// buttons, which only the store shows, came into the strip and resized the board's row. The page a shopper sees
	// first is the page with scripting on and the store not yet run, which the store's own file held back gives.
	for ( const language of [ 'English', 'French' ] as const ) {
		test( `on a phone held sideways in ${ language }, the board is the size before the store runs that it is after, with 3D off and on`, async ( {
			page,
		} ) => {
			await page.setViewportSize( { width: 844, height: 390 } );
			if ( 'French' === language ) {
				await page.setExtraHTTPHeaders( {
					'x-cbuild-e2e-locale': 'fr_CA',
					'x-cbuild-e2e-translated': '1',
				} );
			}
			const board = () =>
				// The side shown: the other board is in the page, and not drawn.
				page
					.locator( '.cbuild__stage .cbuild-board' )
					.evaluateAll( ( boards ) =>
						boards
							.map( ( element ) =>
								element.getBoundingClientRect()
							)
							.filter( ( r ) => r.width > 0 )
							.map( ( r ) => [ r.x, r.y, r.width, r.height ] )
					);
			for ( const address of [ fixture().page, fixture().three.page ] ) {
				const store = /\/build\/builder\/view\.js/;
				await page.route( store, ( route ) => route.abort() );
				// Not page.goto(), which waits for the ready mark the held-back store never sets.
				const url = build( address );
				await page.evaluate(
					( to ) => window.location.assign( to ),
					url
				);
				await page.waitForURL( url, { waitUntil: 'load' } );
				const before = await board();
				await expect( page.locator( '.cbuild' ) ).not.toHaveAttribute(
					'data-ready'
				);
				await page.unroute( store );
				await page.goto( build( address ) );
				await expect(
					page.locator( '.cbuild[data-ready]' )
				).toBeVisible();
				const after = await board();
				expect( after ).toHaveLength( 1 );
				expect( before ).toHaveLength( 1 );
				for ( const [ at, value ] of after[ 0 ]!.entries() ) {
					expect(
						Math.abs( value - before[ 0 ]![ at ]! ),
						`${ address }: ${ JSON.stringify( before ) } before, ${ JSON.stringify( after ) } after`
					).toBeLessThanOrEqual( 0.5 );
				}
			}
		} );
	}

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
