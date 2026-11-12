/**
 * The Cart and Checkout blocks' line filters (spec: Cart, checkout and orders, Showing it). The server says what
 * each line is of a build, under the plugin's namespace in the Store API's `extensions`; these give WooCommerce's
 * filters that answer and decide nothing. A part's row gets a class and its slot's label before its name, and
 * loses its remove link: the kit's line removes the build, and the server removes all of it whichever line goes.
 */
export const SPACE = 'creative-skateboard-builder';

interface Told {
	role?: unknown;
	name?: unknown;
}

type Extensions = Record< string, Told | undefined > | null | undefined;

export interface Filters {
	cartItemClass: ( value: string, extensions: Extensions ) => string;
	itemName: ( value: string, extensions: Extensions ) => string;
	showRemoveItemLink: ( value: boolean, extensions: Extensions ) => boolean;
}

function told( extensions: Extensions ): Told {
	return extensions?.[ SPACE ] ?? {};
}

export const filters: Filters = {
	cartItemClass( value, extensions ) {
		const { role } = told( extensions );
		return role === 'kit' || role === 'part'
			? `${ value } cbuild-${ role }`.trim()
			: value;
	},
	itemName( value, extensions ) {
		const { role, name } = told( extensions );
		return role === 'part' && typeof name === 'string' && name !== ''
			? name
			: value;
	},
	showRemoveItemLink( value, extensions ) {
		return told( extensions ).role === 'part' ? false : value;
	},
};

interface View {
	wc?: {
		blocksCheckout?: {
			registerCheckoutFilters?: (
				space: string,
				answers: Filters
			) => void;
		};
	};
}

/**
 * Give WooCommerce the filters, where its checkout script is on the page.
 *
 * @param view The window, or as much of it as this needs.
 */
export function register( view: View ): void {
	view.wc?.blocksCheckout?.registerCheckoutFilters?.( SPACE, filters );
}

register( window as View );
