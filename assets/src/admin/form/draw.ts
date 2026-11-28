/**
 * The admin kit's drawing of DataForm (docs/ADMIN-KIT.md), and the start every screen's script makes: each form the
 * server described on the page is mounted (mount.ts). React's root is made once per form and drawn again on each edit;
 * `unmount()` takes it away, as the variations' panels need before WooCommerce empties them.
 */
import { DataForm } from '@wordpress/dataviews';
import { createElement, createRoot } from '@wordpress/element';
import { mount, type Draw } from './mount';
// DataForm's styles (style.css): wp-scripts writes them beside each screen's script, with a right-to-left copy.
import './style.css';

/**
 * Draws DataForm into a box.
 *
 * @param where The box.
 * @param props What DataForm is drawn with.
 */
export const draw: Draw = ( where, props ) => {
	const root = createRoot( where );
	root.render( createElement( DataForm, props ) );
	reveal( where, refusals( props.validity ) );
	return {
		update: ( next ) => root.render( createElement( DataForm, next ) ),
		unmount: () => root.unmount(),
	};
};

/**
 * How many fields a validity refuses, those in cards counted inside them.
 *
 * @param validity DataForm's validity, as validityOf() makes it.
 */
export function refusals( validity: unknown ): number {
	if ( null === validity || 'object' !== typeof validity ) {
		return 0;
	}
	return Object.values( validity ).reduce(
		( count: number, one: unknown ) =>
			count +
			( null !== one && 'object' === typeof one && 'children' in one
				? refusals( one.children )
				: 1 ),
		0
	);
}

/**
 * Shows the server's refusals as the page loads. DataForm shows a field's message once the field is left, or on an
 * `invalid` event, so a refusal the page came with would wait unseen. Once each refused control holds its message
 * (DataForm sets it in an effect after drawing), each gets an `invalid` of the script's own, which shows it; DataForm
 * moves focus only for the browser's own, so the page stays where it is (docs/ADMIN-KIT.md).
 *
 * @param where   The box DataForm is drawn in.
 * @param refused How many fields the server refused.
 * @param frames  How many frames to wait for them, at most.
 */
export function reveal(
	where: HTMLElement,
	refused: number,
	frames = 60
): void {
	if ( 0 === refused ) {
		return;
	}
	const look = ( left: number ) => {
		const invalid = where.querySelectorAll( ':invalid' );
		if ( invalid.length >= refused || 0 === left ) {
			invalid.forEach( ( control ) =>
				control.dispatchEvent( new Event( 'invalid' ) )
			);
			return;
		}
		requestAnimationFrame( () => look( left - 1 ) );
	};
	requestAnimationFrame( () => look( frames ) );
}

/**
 * Mounts every described form inside a part of the page.
 *
 * @param within Where to look: the page, or a panel loaded later.
 * @return What gives each back.
 */
export function mountAll( within: ParentNode = document ): Array< () => void > {
	return Array.from(
		within.querySelectorAll< HTMLElement >( '.cbuild-form' )
	).map( ( kit ) => mount( kit, draw ) );
}
