/**
 * How the builder goes from one state to the next with JavaScript (D-148). A step's form, a link or the shopper's
 * Back each name a build link; the state route is asked for it, with the page and the language, and its answer
 * carries the view the server would have rendered, which the store shows. Nothing here decides fit, price or words.
 *
 * - **Picks wait.** The route is asked 150 ms after the last pick (spec: Performance), and a step change asks at
 *   once, taking the place of a pick still waiting. A pick made by a press, a tap or a click asks at once: it is one
 *   pick, not a run of arrows (D-308).
 * - **Pictures first.** An answer is shown once the store has its board's new image decoded, at most HOLD ms, so
 *   the old board stays whole until the new one can take its place (D-308).
 * - **The last request wins.** A request a later one overtakes is aborted, and its answer, should it arrive, dropped.
 * - **Asked ahead.** The store may ask ahead for a move the shopper is likely to make next, a step's lit Next (D-355):
 *   nothing is shown or said for it, and the move, made with the same fields within FRESH, takes its answer, or waits
 *   for it, rather than asking again. Any other move forgets it.
 * - **Anything but a view is a failure,** and the form or link does what it does without JavaScript, save the rate
 *   limit's refusal (spec: Search) and an answer past LIMIT (D-364), below.
 * - **An answer has LIMIT ms.** Past it the request is given up, and the step keeps what it shows and says so, as past
 *   the rate limit (D-364).
 * - **History.** A step change is a new entry, a pick replaces the current one, and going back makes none. The address
 *   is the one the answer says the view is at, which the server may have moved to another step. The store, shown the
 *   view, may say it is a step of its own though a pick brought it: the change panel (D-152).
 */

/**
 * What the store shows: the server's view, of which the navigator reads the address alone.
 */
export interface View {
	url: string;
	[ key: string ]: unknown;
}

/**
 * Where a move goes: the fields a form or link would send, and what to do with history.
 */
export interface Move {
	/** The fields, in the order the form or link has them. */
	fields: Iterable< [ string, string ] >;
	/** A new entry, or the current one replaced. Left out when going back, which makes neither. */
	history?: 'push' | 'replace';
	/** What happens without JavaScript: the form sent, the link followed, the page reloaded. */
	fallback: () => void;
	/**
	 * Help me choose's press that made the move, if one did: Show builds or Skip. Handed back with the move's own
	 * answer alone, so an answer to a later move, or none, never takes it (the auditor's M14 review, F3; D-479).
	 */
	press?: 'show' | 'skip';
}

/**
 * What the navigator works with, given by the store or a test.
 */
export interface Deps {
	/** The state route's address for the kit, as WordPress writes it. */
	endpoint: string;
	/** The post the builder is on. */
	page: number;
	/** The page's language. */
	locale: string;
	fetch: ( input: string, init?: RequestInit ) => Promise< Response >;
	/**
	 * Shows a view, told how the move that brought it treats history: a pick replaces, a step pushes. Answers how
	 * history treats the view as shown, which is the move's unless the view is a step of its own. The move's press
	 * comes with its answer.
	 */
	show: (
		view: View,
		history: Move[ 'history' ],
		press?: Move[ 'press' ]
	) => Move[ 'history' ];
	pending: ( on: boolean ) => void;
	/** Says the route's rate limit is reached, in its own words, which say when to try again. */
	limited: ( message: string ) => void;
	/** What the step says when the route has not answered within LIMIT, in the page's language (D-364). */
	late: string;
	push: ( url: string ) => void;
	replace: ( url: string ) => void;
	/**
	 * Readies what a view shows, its board's new image decoded, before it is shown: null where nothing needs readying,
	 * and the view shows at once. Left out, every view shows at once.
	 */
	settle?: ( view: View ) => Promise< void > | null;
}

/**
 * The route's refusal past its rate limit, in its own words.
 */
class Limited {
	/**
	 * @param message What the route says, which names when to try again.
	 */
	constructor( readonly message: string ) {}
}

/**
 * A refusal in the words given, or null, the form's fallback, where there are none to say.
 *
 * @param message The words, which may be none or no text.
 */
function limited( message: unknown ): Limited | null {
	return 'string' === typeof message && '' !== message
		? new Limited( message )
		: null;
}

/** How long a pick waits for the next before the route is asked. */
export const WAIT = 150;

/** How long the shopper waits for an answer before the builder says it is on its way: a quick one never flickers. */
export const SLOW = 300;

/** The longest an answer is held for its pictures: past it, the view shows and its image paints as it comes. */
export const HOLD = 1000;

/** How long the route has to answer, from the request to its whole answer read (D-364). */
export const LIMIT = 10000;

/** How long an answer asked ahead may stand for the move, in ms: past it, the move asks again (D-355). */
export const FRESH = 60_000;

const PARAM = 'cbuild';

/**
 * The state route's address for a move: the build link's fields alone, then the page, the language and the view.
 *
 * @param deps   The endpoint, page and language.
 * @param fields The move's fields.
 */
export function route(
	deps: Deps,
	fields: Iterable< [ string, string ] >
): string {
	const url = new URL( deps.endpoint );
	let any = false;
	for ( const [ name, value ] of fields ) {
		if ( name.startsWith( PARAM + '[' ) ) {
			url.searchParams.append( name, value );
			any = true;
		}
	}
	if ( ! any ) {
		// The clean page: a build link that says nothing, so that the answer still carries the view.
		url.searchParams.append( PARAM, '' );
	}
	// A builder on an archive or a template has no post: the route takes none then, and would refuse 0.
	if ( deps.page > 0 ) {
		url.searchParams.append( 'page', String( deps.page ) );
	}
	url.searchParams.append( 'locale', deps.locale );
	// The view alone, which is all the store draws: WordPress trims the answer to it (rest_filter_response_fields,
	// wp-includes/rest-api.php:953). The raw shelf and options stay in the route's answer for anyone else (#89).
	url.searchParams.append( '_fields', 'view' );
	return url.toString();
}

/**
 * The view an answer carries, or null: a view is whatever the server sent with an address in it.
 *
 * @param body The answer, parsed.
 */
function viewOf( body: unknown ): View | null {
	const view = ( body as { view?: { url?: unknown } } | null )?.view;
	return typeof view?.url === 'string' ? ( view as View ) : null;
}

export class Navigator {
	private timer: ReturnType< typeof setTimeout > | undefined;
	private slow: ReturnType< typeof setTimeout > | undefined;
	private asking: AbortController | undefined;
	private ahead:
		| { url: string; at: number; view: Promise< View | Limited | null > }
		| undefined;

	constructor( private readonly deps: Deps ) {}

	/**
	 * A pick: the route is asked once the shopper has stopped picking for a moment.
	 *
	 * @param move    Where the pick leaves the build.
	 * @param pressed Whether a press, a tap or a click made it, which asks at once.
	 */
	pick( move: Move, pressed = false ): void {
		clearTimeout( this.timer );
		// The fields are read now: the form may change before the wait is over.
		const fields = [ ...move.fields ];
		if ( pressed ) {
			this.go( { ...move, fields } );
			return;
		}
		this.waiting();
		this.timer = setTimeout( () => this.go( { ...move, fields } ), WAIT );
	}

	/**
	 * Drop the answer waited for, and any pick waiting to be asked: the shopper has gone on typing, and the answer would
	 * put the search box back to what it was asked with (the auditor's M10 review, 19). The next move asks again.
	 */
	drop(): void {
		clearTimeout( this.timer );
		clearTimeout( this.slow );
		this.slow = undefined;
		this.asking?.abort();
		this.asking = undefined;
		this.deps.pending( false );
	}

	/**
	 * Ask ahead for where a move would go, saying nothing and showing nothing (D-355). Asked again only once the last
	 * answer for the same fields is past FRESH.
	 *
	 * @param fields The fields of the move the shopper is likely to make next, as it would send them.
	 */
	prefetch( fields: Iterable< [ string, string ] > ): void {
		const list = [ ...fields ];
		const url = route( this.deps, list );
		if ( this.ahead?.url === url && Date.now() - this.ahead.at < FRESH ) {
			return;
		}
		// It has LIMIT ms too, so a move that waits for it is not left waiting on a request that hangs (D-364).
		const giving = new AbortController();
		const limit = setTimeout( () => giving.abort(), LIMIT );
		this.ahead = {
			url,
			at: Date.now(),
			// Its own request: no later move aborts it, and an answer no move takes is dropped. Its history and what
			// happens without JavaScript are the move's that takes it.
			view: this.ask(
				{ fields: list, fallback: () => undefined },
				giving.signal,
				limit
			),
		};
	}

	/**
	 * The view for a move: the answer asked ahead where the move is the one asked for and still fresh, unless it was no
	 * view; otherwise the route, asked now.
	 *
	 * @param move   Where to go.
	 * @param signal Aborts the request asked now.
	 */
	private answer(
		move: Move,
		signal: AbortSignal
	): Promise< View | Limited | null > {
		const ahead = this.ahead;
		this.ahead = undefined;
		const fields = [ ...move.fields ];
		const now = { ...move, fields };
		if (
			undefined === ahead ||
			ahead.url !== route( this.deps, fields ) ||
			Date.now() - ahead.at >= FRESH
		) {
			return this.ask( now, signal );
		}
		return ahead.view.then( ( view ) =>
			view instanceof Limited || null === view
				? this.ask( now, signal )
				: view
		);
	}

	/**
	 * Any other move: the route is asked at once, or the answer asked ahead for it taken.
	 *
	 * @param move Where to go.
	 */
	go( move: Move ): void {
		clearTimeout( this.timer );
		this.asking?.abort();
		const asking = new AbortController();
		this.asking = asking;
		this.waiting();
		// A request that hangs would keep the builder busy until the browser gives up (D-364).
		const limit = setTimeout( () => asking.abort(), LIMIT );
		void this.answer( move, asking.signal ).then( async ( answer ) => {
			clearTimeout( limit );
			if ( asking !== this.asking ) {
				return;
			}
			// Only the limit aborts the request still asked: a later one or the shopper's typing takes its place first.
			// A page cached before its words were in the config has none, and sends the form, as a 429 with none does.
			const view = asking.signal.aborted
				? limited( this.deps.late )
				: answer;
			const readying =
				null === view || view instanceof Limited
					? null
					: ( this.deps.settle?.( view ) ?? null );
			if ( null !== readying ) {
				await this.held( readying );
				if ( asking !== this.asking ) {
					return;
				}
			}
			clearTimeout( this.slow );
			this.slow = undefined;
			this.deps.pending( false );
			if ( null === view ) {
				move.fallback();
				return;
			}
			// Past the rate limit, the step keeps what it shows and says when to try again (spec: Search).
			if ( view instanceof Limited ) {
				this.deps.limited( view.message );
				return;
			}
			const history = this.deps.show( view, move.history, move.press );
			if ( 'push' === history ) {
				this.deps.push( view.url );
			} else if ( 'replace' === history ) {
				this.deps.replace( view.url );
			}
		} );
	}

	/**
	 * The store's readying of a view, waited for at most HOLD ms.
	 *
	 * @param readying The readying.
	 */
	private async held( readying: Promise< void > ): Promise< void > {
		let cap: ReturnType< typeof setTimeout > | undefined;
		await Promise.race( [
			readying.catch( () => undefined ),
			new Promise< void >( ( resolve ) => {
				cap = setTimeout( resolve, HOLD );
			} ),
		] );
		clearTimeout( cap );
	}

	/**
	 * The shopper is waiting from now, unless already: the pending state shows once they have waited SLOW.
	 */
	private waiting(): void {
		if ( undefined === this.slow ) {
			this.slow = setTimeout( () => this.deps.pending( true ), SLOW );
		}
	}

	/**
	 * The route's view for a move, or null for any failure.
	 *
	 * @param move   Where to go.
	 * @param signal Aborts the request.
	 * @param limit  A timer that gives the request up, cleared once it is answered or has failed.
	 */
	private async ask(
		move: Move,
		signal: AbortSignal,
		limit?: ReturnType< typeof setTimeout >
	): Promise< View | Limited | null > {
		let body: unknown;
		try {
			const response = await this.deps.fetch(
				route( this.deps, move.fields ),
				{
					signal,
					headers: { Accept: 'application/json' },
					credentials: 'same-origin',
				}
			);
			if ( 429 === response.status ) {
				// Whatever the answer is, null or text included, as an object: its message, if it has one.
				const { message } = Object( await response.json() ) as {
					message?: unknown;
				};
				return limited( message );
			}
			if ( ! response.ok ) {
				return null;
			}
			body = await response.json();
		} catch {
			// Unread, the body stays undefined, which is no view.
		} finally {
			clearTimeout( limit );
		}
		return viewOf( body );
	}
}
