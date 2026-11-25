/**
 * The page's first wait, as the store starts, before anything has reset it (D-308): only a pick's wait says the
 * picture is being drawn.
 */
import { afterEach, expect, it, vi } from 'vitest';
import { setConfig, stores } from './support/interactivity';
import { NAME } from '../../assets/src/builder/view';
import { SLOW } from '../../assets/src/builder/navigate';

interface Defined {
	state: {
		view: Record< string, unknown >;
		pending: boolean;
		drawing: boolean;
	};
	actions: { back: () => void };
}

afterEach( () => {
	vi.useRealTimers();
	vi.unstubAllGlobals();
} );

it( 'never says the picture is drawn for the first wait on the page when it is no pick', () => {
	vi.useFakeTimers();
	const { state, actions } = stores.get( NAME )!.definition as Defined;
	setConfig( NAME, {
		endpoint: window.location.origin + '/wp-json/cbuild/v1/kits/7/state',
		page: 25,
	} );
	state.view = {
		url: window.location.origin + '/?page_id=25',
		step: { slot: 'deck' },
		title: 'Step 1 of 7: Deck',
		status: '',
		isChanges: false,
		errors: { shown: false },
	};
	vi.stubGlobal( 'fetch', () => new Promise< Response >( () => undefined ) );
	actions.back();
	vi.advanceTimersByTime( SLOW );
	expect( state.pending ).toBe( true );
	expect( state.drawing ).toBe( false );
} );
