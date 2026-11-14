/**
 * The Cart and Checkout blocks' line filters (spec: Cart, checkout and orders, Showing it). The server says what
 * each line is of a build, under the plugin's namespace in the Store API's `extensions`; these give WooCommerce's
 * filters that answer and decide nothing. A part's row gets a class and its slot's label before its name, and
 * loses its remove link: the kit's line removes the build, and the server removes all of it whichever line goes.
 * A build removed is said once, by its kit's name, in the server's words, in place of a notice for each part.
 */
export const SPACE = 'creative-skateboard-builder';

interface Told {
	role?: unknown;
	name?: unknown;
	build?: unknown;
	removed?: unknown;
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

export interface Notices {
	createInfoNotice: (
		content: string,
		options: { context: string; speak: boolean; type: string; id: string }
	) => unknown;
}

type Removed = (
	show: boolean,
	item: { extensions?: Extensions } | null | undefined
) => boolean;

/**
 * The answer to WooCommerce's `woocommerce_show_cart_item_removed_notice`, which the Cart block asks of each line
 * that has gone from the cart but the one the shopper removed (Cowork's recheck, N5; D-182). A part's is no: once
 * for the build, the block says what the server wrote, by its kit's name, under one notice ID that each part asks
 * again and so replaces.
 *
 * @param notices WordPress's notices store, where there is one.
 */
export function removed( notices: Notices | undefined ): Removed {
	return ( show, item ) => {
		const { role, build, removed: words } = told( item?.extensions );
		if ( role !== 'part' ) {
			return show;
		}
		if ( typeof words === 'string' && words !== '' ) {
			notices?.createInfoNotice( words, {
				context: 'wc/cart',
				speak: true,
				type: 'snackbar',
				id: `cbuild-${ String( build ) }-removed`,
			} );
		}
		return false;
	};
}

interface View {
	wc?: {
		blocksCheckout?: {
			registerCheckoutFilters?: (
				space: string,
				answers: Filters
			) => void;
		};
	};
	wp?: {
		hooks?: {
			addFilter?: (
				hook: string,
				space: string,
				answer: Removed
			) => void;
		};
		data?: {
			dispatch?: ( store: string ) => Notices | undefined;
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
	// The notices store is asked for when a build goes, by which time the block's scripts have all run.
	view.wp?.hooks?.addFilter?.(
		'woocommerce_show_cart_item_removed_notice',
		SPACE,
		( show, item ) =>
			removed( view.wp?.data?.dispatch?.( 'core/notices' ) )( show, item )
	);
}

register( window as View );
