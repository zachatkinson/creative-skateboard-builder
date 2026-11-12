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
 * - **After a step changes,** focus goes to what the shopper reads first: the error summary, the change panel's
 *   heading, or the step's heading; the page's title says the step (D-149). **After a pick,** the status region says
 *   the build and its total, once (spec: Keyboard and screen readers).
 * - **Add to cart** posts the build to the Store API, then tells the carts (`wc-blocks_added_to_cart`, on which the
 *   Mini-Cart refreshes) and says so; without the Store API, the form goes to WooCommerce's own handler (D-155).
 * - **Two events** on the builder's element, bubbling, for a shop's own analytics under its own consent (spec:
 *   Privacy): `cbuild:pick`, with the kit, the slot and the part, and `cbuild:added`, with the kit, its picks and
 *   assembly. They carry IDs alone.
 * - **The phone bar** never covers the control in focus: one the bar overlaps is scrolled clear of it (D-160).
 * - **Copy link** writes the build's shared link to the clipboard and says so. It is offered only where the browser
 *   has a clipboard to write to; the address is in the page as a field either way, and a refused copy selects it.
 */
import { getConfig, getElement, store } from '@wordpress/interactivity';
import { fit } from './bar';
import { Navigator, type Move, type View } from './navigate';
import { add, body } from './cart';

export const NAME = 'creative-skateboard-builder';

interface Config {
	cart: string;
	endpoint: string;
	page: number;
	locale: string;
	title: { before: string; after: string };
}

interface Shown extends View {
	kit: number;
	step: { slot: string };
	review: { added: string; copied: string; link: string };
	title: string;
	status: string;
	isChanges: boolean;
	errors: { shown: boolean };
}

interface State {
	view: Shown;
	pending: boolean;
	status: string;
	added: boolean;
	canCopy: boolean;
}

let navigator: Navigator | undefined;

/**
 * The builder's own element, as its init callback found it.
 */
let root: HTMLElement | null = null;

/**
 * What the shopper reads first after a step changes, by the template's classes.
 *
 * @param view The view now shown.
 */
function first( view: Shown ): string {
	if ( view.errors.shown ) {
		return '.cbuild-errors';
	}
	return view.isChanges ? '.cbuild-changes__heading' : '.cbuild__heading';
}

/**
 * Says a view: the status region after a pick; after a step, the page's title, and focus once the page shows it.
 *
 * @param state   The store's state.
 * @param view    The view now shown.
 * @param history How the move treats history.
 * @param config  The page's config.
 */
function say(
	state: State,
	view: Shown,
	history: Move[ 'history' ],
	config: Partial< Config >
): void {
	if ( 'replace' === history ) {
		state.status = view.status;
		return;
	}
	// The change panel says how many parts changed; a step is read from its heading, so the region says nothing.
	state.status = view.isChanges ? view.status : '';
	const around = config.title ?? { before: '', after: '' };
	document.title = around.before + view.title + around.after;
	const target = first( view );
	// The page shows the new view once the Interactivity API has rendered it, before the next frame.
	window.requestAnimationFrame( () => {
		root?.querySelector< HTMLElement >( target )?.focus();
	} );
}

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
 * Show a change of view: crossed over with a view transition where the browser has one and the shopper allows
 * motion, at once otherwise (spec: Motion; D-161). The transition waits for the Interactivity API's render, which
 * runs on a microtask, before it takes the new picture; the stylesheet sets its timing.
 *
 * @param change What changes the view.
 */
function motion( change: () => void ): void {
	if (
		'function' !== typeof document.startViewTransition ||
		window.matchMedia?.( '(prefers-reduced-motion: reduce)' ).matches
	) {
		change();
		return;
	}
	document.startViewTransition( () => {
		change();
		return new Promise< void >( ( done ) => window.setTimeout( done ) );
	} );
}

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
			show: ( view, history ) => {
				const shown = view as Shown;
				// A pick that opens the change panel is a step: an entry of its own, which Back leaves for the step
				// the pick was made on, with focus on the panel's heading, as Next would have it (D-152).
				const turned =
					'replace' === history &&
					shown.isChanges &&
					! state.view.isChanges
						? 'push'
						: history;
				motion( () => {
					state.view = shown;
					state.added = false;
					say( state, shown, turned, config );
				} );
				return turned;
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
	root = null;
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
		state: {
			pending: false,
			status: '',
			added: false,
			canCopy: false,
		} as State,
		callbacks: {
			init(): void {
				root = getElement().ref;
				state.canCopy =
					'function' === typeof window.navigator.clipboard?.writeText;
				if ( root ) {
					fit( root );
				}
			},
		},
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
				root?.dispatchEvent(
					new CustomEvent( 'cbuild:pick', {
						bubbles: true,
						detail: {
							kit: state.view.kit,
							slot: state.view.step.slot,
							part: Number( input.value ),
						},
					} )
				);
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
			async add( event: SubmitEvent ): Promise< void > {
				const form = getElement().ref;
				if ( ! ( form instanceof HTMLFormElement ) ) {
					return;
				}
				event.preventDefault();
				const sent = fields( form, event.submitter );
				const config = getConfig( NAME ) as Partial< Config >;
				state.pending = true;
				const added = await add(
					{
						cart: String( config.cart ?? '' ),
						fetch: ( input, init ) => window.fetch( input, init ),
					},
					sent
				);
				state.pending = false;
				if ( ! added ) {
					send( form, event.submitter );
					return;
				}
				const item = body( sent );
				// The Mini-Cart listens on the document, as for any product added (D-155).
				form.ownerDocument.body.dispatchEvent(
					new CustomEvent( 'wc-blocks_added_to_cart', {
						bubbles: true,
						cancelable: true,
					} )
				);
				root?.dispatchEvent(
					new CustomEvent( 'cbuild:added', {
						bubbles: true,
						detail: {
							kit: item.id,
							picks: item.cbuild.picks,
							assembly: item.cbuild.assembly ?? false,
						},
					} )
				);
				state.added = true;
				state.status = state.view.review.added;
				window.requestAnimationFrame( () => {
					root?.querySelector< HTMLElement >(
						'.cbuild-review__added'
					)?.focus();
				} );
			},
			/**
			 * The screen changed: fit the phone bar into the theme again (D-163).
			 */
			fit(): void {
				if ( root ) {
					fit( root );
				}
			},
			reveal( event: FocusEvent ): void {
				const target = event.target;
				const bar = root?.querySelector< HTMLElement >( '.cbuild-bar' );
				if (
					! ( target instanceof HTMLElement ) ||
					! bar ||
					0 === bar.getClientRects().length ||
					bar.contains( target )
				) {
					return;
				}
				// A browser that scrolls a focused control only when it leaves the screen (Firefox) leaves it under the
				// bar; scrolling it into view honours the page's scroll padding, which clears the bar (D-160).
				if (
					target.getBoundingClientRect().bottom >
					bar.getBoundingClientRect().top
				) {
					target.scrollIntoView( { block: 'nearest' } );
				}
			},
			async copy(): Promise< void > {
				try {
					await window.navigator.clipboard.writeText(
						state.view.review.link
					);
					state.status = state.view.review.copied;
				} catch {
					// Refused, as a browser may without a secure page or the shopper's leave: the shopper copies it.
					const link = root?.querySelector< HTMLInputElement >(
						'.cbuild-share__link'
					);
					link?.focus();
					link?.select();
				}
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
