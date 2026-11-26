/**
 * The store's side of the 3D view (1.4.0, #139): View in 3D loads the chunk on its press alone and opens it on the
 * server's canvas; a second press, or one while it loads, closes it; a failure goes back to the board with the
 * server's message; each view's scene is drawn once it is open; and the camera's buttons send it by their values.
 */
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { setElement, stores } from './support/interactivity';
import { NAME, reset } from '../../assets/src/builder/view';
import { flat, placeOf, PLACES } from '../../assets/src/builder/picture';
import { scene } from './support/scene';

const drawn = {
	show: vi.fn(),
	look: vi.fn(),
	play: vi.fn( () => Promise.resolve( true ) ),
	close: vi.fn(),
};

const opened = vi.hoisted( () => ( {
	open: vi.fn(),
} ) );

vi.mock( '../../assets/src/builder/three/index', () => opened );

interface Defined {
	state: {
		view: Record< string, unknown >;
		status: string;
		three: { open: boolean; loading: boolean; failed: boolean };
	};
	actions: { three: () => Promise< void >; look: () => void };
	callbacks: { init: () => void; scene: () => void };
}

const { state, actions, callbacks } = stores.get( NAME )!.definition as Defined;

/**
 * The builder with its 3D view, as the server renders it, and the store started on it.
 *
 * @param canvas Whether the page has the canvas.
 */
function page( canvas = true ): HTMLCanvasElement | null {
	document.body.innerHTML = `
		<div class="cbuild">
			<p class="cbuild__status"></p>
			<div class="cbuild-3d">
				${ canvas ? '<canvas class="cbuild-3d__canvas"></canvas>' : '' }
				<button type="button" value="top">Top</button>
				<button type="button" value="nowhere">Nowhere</button>
				<p class="cbuild-3d__failed">
					The 3D view can’t be shown here, so the board is drawn flat.
				</p>
			</div>
		</div>`;
	setElement( document.querySelector< HTMLElement >( '.cbuild__status' ) );
	callbacks.init();
	return document.querySelector( 'canvas' );
}

beforeEach( () => {
	reset();
	vi.clearAllMocks();
	opened.open.mockImplementation( () => drawn );
	state.three = { open: false, loading: false, failed: false };
	state.status = '';
	state.view = {
		step: { slot: 'deck' },
		picture: { turnable: true, turned: false },
		scene: scene(),
	};
	window.matchMedia = vi.fn( () => ( { matches: false } ) ) as never;
} );

afterEach( () => {
	document.body.innerHTML = '';
} );

describe( 'View in 3D', () => {
	it( 'loads the 3D view on its press, and draws the view’s scene on its step’s side', async () => {
		const canvas = page();
		const pressed = actions.three();
		expect( state.three.loading ).toBe( true );
		expect( state.three.open ).toBe( false );
		await pressed;
		expect( state.three ).toEqual( {
			open: true,
			loading: false,
			failed: false,
		} );
		expect( opened.open ).toHaveBeenCalledTimes( 1 );
		const [ opening ] = opened.open.mock.calls[ 0 ]!;
		expect( opening.canvas ).toBe( canvas );
		expect( opening.reduced() ).toBe( false );
		expect( drawn.show ).toHaveBeenCalledWith( state.view.scene, false );
	} );

	it( 'opens on the top where the step shows it', async () => {
		page();
		state.view = { ...state.view, picture: { turned: true } };
		await actions.three();
		expect( drawn.show ).toHaveBeenCalledWith( state.view.scene, true );
	} );

	it( 'reads reduced motion as the board’s moments do', async () => {
		page();
		await actions.three();
		window.matchMedia = vi.fn( () => ( { matches: true } ) ) as never;
		expect( opened.open.mock.calls[ 0 ]![ 0 ].reduced() ).toBe( true );
	} );

	it( 'closes on a second press, giving everything back', async () => {
		page();
		await actions.three();
		await actions.three();
		expect( drawn.close ).toHaveBeenCalledTimes( 1 );
		expect( state.three.open ).toBe( false );
		// The board's renderer draws nothing.
		callbacks.scene();
		expect( drawn.show ).toHaveBeenCalledTimes( 1 );
		await actions.three();
		expect( state.three.open ).toBe( true );
		expect( opened.open ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'stays closed where it was pressed again while it loaded', async () => {
		page();
		const first = actions.three();
		const second = actions.three();
		expect( state.three.loading ).toBe( false );
		await second;
		await first;
		expect( opened.open ).not.toHaveBeenCalled();
		expect( state.three ).toEqual( {
			open: false,
			loading: false,
			failed: false,
		} );
	} );

	it( 'does nothing on a page with no canvas', async () => {
		page( false );
		await actions.three();
		expect( state.three.loading ).toBe( false );
		expect( opened.open ).not.toHaveBeenCalled();
	} );

	it( 'goes back to the board with the server’s message where it cannot open', async () => {
		page();
		opened.open.mockImplementation( () => {
			throw new Error( 'Error creating WebGL context.' );
		} );
		await actions.three();
		expect( state.three ).toEqual( {
			open: false,
			loading: false,
			failed: true,
		} );
		expect( state.status ).toBe(
			'The 3D view can’t be shown here, so the board is drawn flat.'
		);
		await actions.three();
		expect( opened.open ).toHaveBeenCalledTimes( 2 );
	} );

	it( 'says nothing where the page has no message', async () => {
		page();
		document.querySelector( '.cbuild-3d__failed' )!.remove();
		opened.open.mockImplementation( () => {
			throw new Error( 'no WebGL' );
		} );
		await actions.three();
		expect( state.three.failed ).toBe( true );
		expect( state.status ).toBe( '' );
	} );

	it( 'goes back to the board where the open view fails', async () => {
		page();
		await actions.three();
		opened.open.mock.calls[ 0 ]![ 0 ].failed( new Error( 'lost' ) );
		expect( drawn.close ).toHaveBeenCalledTimes( 1 );
		expect( state.three ).toEqual( {
			open: false,
			loading: false,
			failed: true,
		} );
		expect( state.status ).toContain( 'drawn flat' );
		// A failure clears once it opens again.
		await actions.three();
		expect( state.three.failed ).toBe( false );
	} );
} );

describe( 'each view in 3D', () => {
	it( 'draws the view’s scene once open, on its step’s side', async () => {
		page();
		await actions.three();
		const next = scene();
		next.wheels!.part = 304;
		state.view = {
			...state.view,
			picture: { turned: true },
			scene: next,
		};
		callbacks.scene();
		expect( drawn.show ).toHaveBeenLastCalledWith( next, true );
		state.view = { step: { slot: 'deck' } };
		callbacks.scene();
		expect( drawn.show ).toHaveBeenLastCalledWith( null, false );
	} );
} );

describe( 'the camera’s buttons', () => {
	it( 'send the camera where their values name', async () => {
		page();
		await actions.three();
		setElement( document.querySelector< HTMLElement >( '[value=top]' ) );
		actions.look();
		expect( drawn.look ).toHaveBeenCalledWith( 'top' );
		setElement(
			document.querySelector< HTMLElement >( '[value=nowhere]' )
		);
		actions.look();
		setElement( null );
		actions.look();
		expect( drawn.look ).toHaveBeenCalledTimes( 1 );
	} );
} );

describe( 'the picture’s renderers', () => {
	it( 'has the board’s draw nothing and play nothing', async () => {
		expect( flat.show( scene(), true ) ).toBeUndefined();
		expect( flat.look( 'top' ) ).toBeUndefined();
		expect( flat.close() ).toBeUndefined();
		await expect(
			flat.play( { duration: 1, pose: () => undefined } )
		).resolves.toBe( false );
	} );

	it( 'reads a place from a button’s value', () => {
		expect( PLACES ).toEqual( [ 'reset', 'top', 'under', 'graphic' ] );
		for ( const one of PLACES ) {
			expect( placeOf( one ) ).toBe( one );
		}
		expect( placeOf( 'side' ) ).toBeNull();
		expect( placeOf( undefined ) ).toBeNull();
	} );
} );
