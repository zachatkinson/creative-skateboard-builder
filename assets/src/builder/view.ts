/**
 * The builder's store, `creative-skateboard-builder`: private and locked, so nothing can come to depend on it (spec:
 * Upgrades and the public API). The server renders the page, and the same template re-renders from the view the
 * state route answers with (D-148). The store holds that view and whether a request is out; every word and address in
 * it is the server's, and the script carries none.
 *
 * - **A step's form** sends its fields to the route, the button pressed among them. Without the route, the form is
 *   sent as it would be without JavaScript.
 * - **A pick** is the shopper's own radio, painted by the browser at once; the route is asked for the same step with it
 *   once the shopper stops picking.
 * - **A link** on the builder (Back, a step done, Change, an error) asks the route for its address. A link opened in
 *   another tab or window is left to the browser.
 * - **Back and Forward** ask the route for the address the browser is now at.
 */
import { getConfig, getElement, store } from '@wordpress/interactivity';
import { Navigator, type Move, type View } from './navigate';

export const NAME = 'creative-skateboard-builder';

interface Config {
	endpoint: string;
	page: number;
	locale: string;
}

interface Shown extends View {
	step: { slot: string };
}

interface State {
	view: Shown;
	pending: boolean;
}

let navigator: Navigator | undefined;

/**
 * What the builder does without JavaScript's help, named so that a test can see it done.
 */
export const browser = {
	assign: ( url: string ): void => window.location.assign( url ),
	reload: (): void => window.location.reload(),
	submit: ( form: HTMLFormElement ): void =>
		HTMLFormElement.prototype.submit.call( form ),
};

/**
 * The navigator, made on first use from the page's config.
 *
 * @param state The store's state.
 */
function nav( state: State ): Navigator {
	if ( undefined === navigator ) {
		const config = getConfig( NAME ) as Partial< Config >;
		navigator = new Navigator( {
			endpoint: String( config.endpoint ),
			page: Number( config.page ?? 0 ),
			locale: String( config.locale ?? '' ),
			fetch: ( input, init ) => window.fetch( input, init ),
			show: ( view ) => {
				state.view = view as Shown;
			},
			pending: ( on ) => {
				state.pending = on;
			},
			push: ( url ) => window.history.pushState( null, '', url ),
			replace: ( url ) => window.history.replaceState( null, '', url ),
		} );
	}
	return navigator;
}

/**
 * Forget the navigator: for tests, which give each its own page.
 */
export function reset(): void {
	navigator = undefined;
}

/**
 * A form's fields as text, the button pressed among them.
 *
 * @param form      The form.
 * @param submitter The button pressed, if any.
 */
function fields(
	form: HTMLFormElement,
	submitter?: HTMLElement | null
): Array< [ string, string ] > {
	return [ ...new FormData( form, submitter ?? null ) ].filter(
		( field ): field is [ string, string ] => typeof field[ 1 ] === 'string'
	);
}

/**
 * Send a form as the browser would without JavaScript, the button pressed among its fields.
 *
 * @param form      The form.
 * @param submitter The button pressed, if any.
 */
function send( form: HTMLFormElement, submitter?: HTMLElement | null ): void {
	if ( submitter instanceof HTMLButtonElement && '' !== submitter.name ) {
		const pressed = form.ownerDocument.createElement( 'input' );
		pressed.type = 'hidden';
		pressed.name = submitter.name;
		pressed.value = submitter.value;
		form.appendChild( pressed );
	}
	browser.submit( form );
}

const { state } = store(
	NAME,
	{
		state: { pending: false } as State,
		actions: {
			submit( event: SubmitEvent ): void {
				const form = getElement().ref;
				if ( ! ( form instanceof HTMLFormElement ) ) {
					return;
				}
				event.preventDefault();
				const move: Move = {
					fields: fields( form, event.submitter ),
					history: 'push',
					fallback: () => send( form, event.submitter ),
				};
				nav( state ).go( move );
			},
			pick( event: Event ): void {
				const input = event.target;
				if (
					! ( input instanceof HTMLInputElement ) ||
					'radio' !== input.type ||
					null === input.form
				) {
					return;
				}
				nav( state ).pick( {
					fields: [
						...fields( input.form ),
						[ 'cbuild[step]', state.view.step.slot ],
					],
					history: 'replace',
					// The radio stays picked, and the form's Next sends it.
					fallback: () => undefined,
				} );
			},
			follow( event: MouseEvent ): void {
				const link = getElement().ref;
				if (
					! ( link instanceof HTMLAnchorElement ) ||
					0 !== event.button ||
					event.metaKey ||
					event.ctrlKey ||
					event.shiftKey ||
					event.altKey
				) {
					return;
				}
				const url = new URL( link.href );
				if ( url.origin !== window.location.origin ) {
					return;
				}
				event.preventDefault();
				nav( state ).go( {
					fields: [ ...url.searchParams ],
					history: 'push',
					fallback: () => browser.assign( url.href ),
				} );
			},
			back(): void {
				nav( state ).go( {
					fields: [
						...new URLSearchParams( window.location.search ),
					],
					fallback: () => browser.reload(),
				} );
			},
		},
	},
	{ lock: true }
);
