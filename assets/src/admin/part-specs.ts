/**
 * The product screen opens the part's fields when its address names them, as the Catalogue check's links do (D-083,
 * D-086). WooCommerce's product screen opens no tab from the address, so a link would land on General with the
 * field out of sight.
 *
 * - `#cbuild_part_specs` opens the Part specs tab.
 * - `#cbuild-spec-<key>` opens the Part specs tab and the group the field is in, and moves to the field, as the
 *   notice for a value that was not saved does.
 * - `#cbuild_variation_<id>` opens the Variations tab, then the variation once WooCommerce has loaded it, and
 *   scrolls to its fields. A variation on a later page is left to WooCommerce's own paging.
 *
 * WooCommerce's tabs and rows open on a click (assets/js/admin/meta-boxes.js:149), which a native click reaches.
 * Two things are jQuery's alone: the Variations tab announces its rows with a jQuery event
 * (assets/js/admin/meta-boxes-product-variation.js:636), which the DOM never hears, and a row opens with a jQuery
 * animation, whose end only jQuery can promise. So jQuery is used for those two and nothing else. No data is read
 * and no markup is built. Focus follows the scroll, and a frame later what has focus is checked to be in view still.
 *
 * It also keeps the product's form small (issue #23). A group of fields that is closed is drawn with its fields
 * disabled, so a part with no specs sends none: 21 fields in each of 15 variations took a product's form past PHP's
 * max_input_vars on a host that leaves it at 1,000. A group's fields are enabled when it opens and stay so.
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
// A group of fields opens from its summary, so that comes first where the fields are grouped.
const CONTROLS = 'summary, input:not([type="hidden"]), select, textarea';

const FIELDS = '.cbuild-part-specs';

/**
 * Enables a group's fields, so the form sends them.
 *
 * @param group The group: a details element.
 */
export function enable( group: Element ): void {
	group
		.querySelectorAll< HTMLInputElement | HTMLSelectElement >(
			'input:disabled, select:disabled'
		)
		.forEach( ( control ) => {
			control.disabled = false;
		} );
}

/**
 * Enables each of the panel's groups as it opens, in the product's tab and in every variation, whenever WooCommerce
 * loads it. A group that closes again keeps its fields enabled: what was typed in it is still to be sent.
 *
 * @param doc The product screen.
 */
export function groups( doc: Document ): void {
	// A details element's toggle does not bubble, so it is heard on its way down.
	doc.addEventListener(
		'toggle',
		( event ) => {
			const group = event.target;
			if (
				group instanceof HTMLDetailsElement &&
				group.open &&
				group.closest( FIELDS )
			) {
				enable( group );
			}
		},
		true
	);
}

/**
 * A frame after a jump, brings what has focus back into view if the page has moved under it: a notice above that
 * WordPress moves or removes once the page is ready shifts everything below.
 *
 * @param view   The window, or as much of it as this needs.
 * @param target What focus moved to.
 */
export function settle(
	view: {
		innerHeight: number;
		requestAnimationFrame?: ( run: () => void ) => unknown;
	} | null,
	target: Element | null
): void {
	if ( ! view || ! target ) {
		return;
	}
	view.requestAnimationFrame?.( () => {
		const box = target.getBoundingClientRect();
		if ( box.top < 0 || box.bottom > view.innerHeight ) {
			target.scrollIntoView( { block: 'center' } );
		}
	} );
}

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
 * The field of the product's panel an address names, by its ID, or nothing.
 *
 * @param hash The address's fragment, with its #.
 */
export function field( hash: string ): string | null {
	return hash.match( /^#(cbuild-spec-[a-z]+(?:-[a-z]+)*)$/ )?.[ 1 ] ?? null;
}

/**
 * Opens what the address names.
 *
 * @param doc  The product screen.
 * @param hash The address's fragment, with its #.
 * @param $    jQuery.
 */
export function open( doc: Document, hash: string, $: Query ): void {
	// Scrolled to, and focus moved to its first control, so a keyboard or a screen reader lands where the eye does.
	const show = ( element: Element | null ) => {
		element?.scrollIntoView( { block: 'start' } );
		const control =
			element?.querySelector< HTMLElement >( CONTROLS ) ?? null;
		control?.focus( { preventScroll: true } );
		settle( doc.defaultView, control );
	};
	const tab = ( id: string ) =>
		doc
			.querySelector< HTMLElement >( `ul.wc-tabs a[href="#${ id }"]` )
			?.click();

	const own = doc.getElementById( field( hash ) ?? '' );
	if ( own ) {
		tab( PANEL );
		const group = own.closest( 'details' );
		if ( group ) {
			// Open, and its fields enabled before focus moves to one: a closed group's are disabled (issue #23).
			group.setAttribute( 'open', '' );
			enable( group );
		}
		own.scrollIntoView( { block: 'center' } );
		own.focus( { preventScroll: true } );
		settle( doc.defaultView, own );
		return;
	}
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
 * Waits for the page, then opens what its address names, and again each time the address changes, as it does when
 * a notice's link is followed. Does nothing where jQuery is not loaded.
 *
 * @param win The window.
 */
export function start( win: Window & { jQuery?: Query } ): void {
	groups( win.document );
	const $ = win.jQuery;
	if ( ! $ ) {
		return;
	}
	const now = () => open( win.document, win.location.hash, $ );
	$( now );
	win.addEventListener( 'hashchange', now );
}

start( window );
