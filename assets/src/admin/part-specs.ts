/**
 * The product screen opens the part's fields when its address names them, as the Catalogue check's links do (D-083,
 * D-086). WooCommerce's product screen opens no tab from the address, so a link would land on General with the
 * field out of sight.
 *
 * - `#cbuild_part_specs` opens the Part specs tab.
 * - `#cbuild_variation_<id>` opens the Variations tab, then the variation once WooCommerce has loaded it, and
 *   scrolls to its fields. A variation on a later page is left to WooCommerce's own paging.
 *
 * WooCommerce's tabs and rows open on a click (assets/js/admin/meta-boxes.js:149), which a native click reaches.
 * Two things are jQuery's alone: the Variations tab announces its rows with a jQuery event
 * (assets/js/admin/meta-boxes-product-variation.js:636), which the DOM never hears, and a row opens with a jQuery
 * animation, whose end only jQuery can promise. So jQuery is used for those two and nothing else. No data is read
 * and no markup is built.
 */

/**
 * As much of a jQuery collection as this script uses.
 */
export interface Collection {
	one: ( event: string, heard: () => void ) => unknown;
	promise: () => { done: ( finished: () => void ) => unknown };
}

/**
 * As much of jQuery as this script uses: a collection of one element, and a callback for when the page is ready.
 */
export interface Query {
	( element: Element ): Collection;
	( ready: () => void ): unknown;
}

const PANEL = 'cbuild_part_specs';
const VARIATIONS = 'variable_product_options';

/**
 * What an address names: the panel, a variation's ID, or nothing of ours.
 *
 * @param hash The address's fragment, with its #.
 */
export function named( hash: string ): 'panel' | string | null {
	if ( hash === `#${ PANEL }` ) {
		return 'panel';
	}
	return hash.match( /^#cbuild_variation_(\d+)$/ )?.[ 1 ] ?? null;
}

/**
 * Opens what the address names.
 *
 * @param doc  The product screen.
 * @param hash The address's fragment, with its #.
 * @param $    jQuery.
 */
export function open( doc: Document, hash: string, $: Query ): void {
	const show = ( element: Element | null ) =>
		element?.scrollIntoView( { block: 'start' } );
	const tab = ( id: string ) =>
		doc
			.querySelector< HTMLElement >( `ul.wc-tabs a[href="#${ id }"]` )
			?.click();

	const part = named( hash );
	if ( part === null ) {
		return;
	}
	if ( part === 'panel' ) {
		tab( PANEL );
		show( doc.getElementById( PANEL ) );
		return;
	}
	const box = doc.getElementById( 'woocommerce-product-data' );
	if ( ! box ) {
		return;
	}
	$( box ).one( 'woocommerce_variations_loaded', () => {
		const row = Array.from(
			doc.querySelectorAll< HTMLInputElement >( 'input.variable_post_id' )
		)
			.find( ( input ) => input.value === part )
			?.closest( '.woocommerce_variation' );
		if ( ! row ) {
			return;
		}
		if ( row.classList.contains( 'closed' ) ) {
			row.querySelector< HTMLElement >( 'h3' )?.click();
		}
		const fields = () =>
			show( row.querySelector( '.cbuild-part-specs' ) ?? row );
		const content = row.querySelector( '.wc-metabox-content' );
		if ( content ) {
			// The row opens with an animation; its fields have no place on the page until it ends.
			$( content ).promise().done( fields );
		} else {
			fields();
		}
	} );
	tab( VARIATIONS );
}

/**
 * Waits for the page, then opens what its address names. Does nothing where jQuery is not loaded.
 *
 * @param win The window.
 */
export function start( win: Window & { jQuery?: Query } ): void {
	const $ = win.jQuery;
	if ( ! $ ) {
		return;
	}
	$( () => open( win.document, win.location.hash, $ ) );
}

start( window );
