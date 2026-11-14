/**
 * How the builder goes from one state to the next with JavaScript (D-148). A step's form, a link or the shopper's
 * Back each name a build link; the state route is asked for it, with the page and the language, and its answer
 * carries the view the server would have rendered, which the store shows. Nothing here decides fit, price or words.
 *
 * - **Picks wait.** The route is asked 150 ms after the last pick (spec: Performance), and a step change asks at
 *   once, taking the place of a pick still waiting.
 * - **The last request wins.** A request a later one overtakes is aborted, and its answer, should it arrive, dropped.
 * - **Anything but a view is a failure,** and the form or link does what it does without JavaScript.
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
	 * history treats the view as shown, which is the move's unless the view is a step of its own.
	 */
	show: ( view: View, history: Move[ 'history' ] ) => Move[ 'history' ];
	pending: ( on: boolean ) => void;
	push: ( url: string ) => void;
	replace: ( url: string ) => void;
}

/** How long a pick waits for the next before the route is asked. */
export const WAIT = 150;

/** How long the shopper waits for an answer before the builder says it is on its way: a quick one never flickers. */
export const SLOW = 300;

const PARAM = 'cbuild';

/**
 * The state route's address for a move: the build link's fields alone, then the page and the language.
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

	constructor( private readonly deps: Deps ) {}

	/**
	 * A pick: the route is asked once the shopper has stopped picking for a moment.
	 *
	 * @param move Where the pick leaves the build.
	 */
	pick( move: Move ): void {
		clearTimeout( this.timer );
		this.waiting();
		// The fields are read now: the form may change before the wait is over.
		const fields = [ ...move.fields ];
		this.timer = setTimeout( () => this.go( { ...move, fields } ), WAIT );
	}

	/**
	 * Any other move: the route is asked at once.
	 *
	 * @param move Where to go.
	 */
	go( move: Move ): void {
		clearTimeout( this.timer );
		this.asking?.abort();
		const asking = new AbortController();
		this.asking = asking;
		this.waiting();
		void this.ask( move, asking.signal ).then( ( view ) => {
			if ( asking !== this.asking ) {
				return;
			}
			clearTimeout( this.slow );
			this.slow = undefined;
			this.deps.pending( false );
			if ( null === view ) {
				move.fallback();
				return;
			}
			const history = this.deps.show( view, move.history );
			if ( 'push' === history ) {
				this.deps.push( view.url );
			} else if ( 'replace' === history ) {
				this.deps.replace( view.url );
			}
		} );
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
	 */
	private async ask(
		move: Move,
		signal: AbortSignal
	): Promise< View | null > {
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
			if ( ! response.ok ) {
				return null;
			}
			body = await response.json();
		} catch {
			return null;
		}
		return viewOf( body );
	}
}
