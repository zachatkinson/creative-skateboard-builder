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
