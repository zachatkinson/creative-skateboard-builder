/**
 * The builder's add with JavaScript (spec: How a build travels; D-155): the review's form, posted as JSON to the
 * Store API's `cart/add-item`, the same build WooCommerce's own handler takes without JavaScript. The server judges
 * the whole build again, as it does for any add.
 *
 * - **The nonce is asked for when the shopper adds,** from the cart's own answer, so that no page a cache keeps can
 *   hold a stale one.
 * - **Anything but a line added is a failure,** which the store answers by sending the form as it would be sent
 *   without JavaScript, where WooCommerce says why as a notice.
 */

/**
 * What the add works with, given by the store or a test.
 */
export interface CartDeps {
	/** The Store API's cart, as WordPress writes its address, or empty where the page names none. */
	cart: string;
	fetch: ( input: string, init?: RequestInit ) => Promise< Response >;
}

/**
 * The add-item request's body, as the plugin's cart takes it (docs/HOOKS.md, The cart).
 */
export interface AddItem {
	id: number;
	quantity: number;
	cbuild: {
		picks: Record< string, number >;
		assembly?: boolean;
		replace?: string;
	};
}

const PICK = /^cbuild\[picks\]\[([a-z][a-z0-9_]*)\]$/;

/**
 * The review form's fields as the Store API takes them: the kit, and under `cbuild` the picks, assembly where the
 * form says, and the build it replaces where Edit build sent one.
 *
 * @param fields The form's fields.
 */
export function body( fields: Iterable< [ string, string ] > ): AddItem {
	const item: AddItem = { id: 0, quantity: 1, cbuild: { picks: {} } };
	for ( const [ name, value ] of fields ) {
		const pick = PICK.exec( name );
		if ( null !== pick ) {
			item.cbuild.picks[ pick[ 1 ]! ] = Number( value );
		} else if ( 'add-to-cart' === name ) {
			item.id = Number( value );
		} else if ( 'cbuild[assembly]' === name ) {
			item.cbuild.assembly = '1' === value;
		} else if ( 'cbuild[replace]' === name ) {
			item.cbuild.replace = value;
		}
	}
	return item;
}

/**
 * The address of a route under the cart's.
 *
 * @param cart The cart's address.
 * @param path The route's path under it.
 */
function under( cart: string, path: string ): string {
	const url = new URL( cart );
	const route = url.searchParams.get( 'rest_route' );
	if ( null !== route ) {
		// Plain permalinks: the route is a query parameter.
		url.searchParams.set( 'rest_route', route + path );
		return url.toString();
	}
	return cart + path;
}

/**
 * Adds a build to the cart, and says whether it was added.
 *
 * @param deps   The cart and fetch.
 * @param fields The review form's fields.
 */
export async function add(
	deps: CartDeps,
	fields: Iterable< [ string, string ] >
): Promise< boolean > {
	if ( '' === deps.cart ) {
		return false;
	}
	try {
		const cart = await deps.fetch( deps.cart, {
			credentials: 'same-origin',
			headers: { Accept: 'application/json' },
		} );
		const nonce = cart.headers.get( 'Nonce' );
		if ( ! cart.ok || null === nonce ) {
			return false;
		}
		const added = await deps.fetch( under( deps.cart, '/add-item' ), {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				Accept: 'application/json',
				'Content-Type': 'application/json',
				Nonce: nonce,
			},
			body: JSON.stringify( body( fields ) ),
		} );
		return added.ok;
	} catch {
		return false;
	}
}
