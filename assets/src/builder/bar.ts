/**
 * The phone bar's place among a theme's own layout (D-163). The bar is `position: sticky` at the foot of the screen
 * (D-160), which two things a theme does can undo:
 *
 * - **An ancestor with `overflow: hidden`**, as Storefront gives `body` and `.site` to stop sideways scrolling. A box
 *   with `hidden` is a scroll container even when it never scrolls, and a sticky box sticks to its nearest one, so
 *   the bar never moves from the builder's end. `clip` clips the same and is no scroll container, so each such
 *   ancestor gets `clip` in its place; a plain block also gets `flow-root`, the block formatting context `hidden`
 *   gave it, so floats it held stay held.
 * - **A bar of the theme's own, fixed to the foot of the screen**, as Storefront's on phones. The builder's bar goes
 *   above it: its height is `--cbuild-bar-offset` on the page's root, which the bar's place and the page's scroll
 *   padding read.
 */

/**
 * Give each ancestor of the builder that is a scroll container only by `overflow: hidden` the clip it meant.
 *
 * @param root The builder's element.
 */
export function unclip( root: Element ): void {
	const view = root.ownerDocument.defaultView;
	if ( ! view ) {
		return;
	}
	for (
		let node = root.parentElement;
		node && node !== root.ownerDocument.documentElement;
		node = node.parentElement
	) {
		const style = view.getComputedStyle( node );
		const x = 'hidden' === style.overflowX;
		const y = 'hidden' === style.overflowY;
		if ( ! x && ! y ) {
			continue;
		}
		if ( 'block' === style.display ) {
			node.style.display = 'flow-root';
		}
		if ( x ) {
			node.style.overflowX = 'clip';
		}
		if ( y ) {
			node.style.overflowY = 'clip';
		}
	}
}

/**
 * How much of the foot of the screen the theme's own fixed bars take, in CSS pixels: the tallest fixed box, outside
 * the builder, that reaches the screen's bottom edge and takes less than half the screen.
 *
 * @param root The builder's element.
 */
export function taken( root: Element ): number {
	const doc = root.ownerDocument;
	const view = doc.defaultView;
	if ( ! view ) {
		return 0;
	}
	const height = view.innerHeight;
	const width = view.innerWidth;
	let most = 0;
	for ( const x of [ 0.1, 0.5, 0.9 ] ) {
		for ( const hit of doc.elementsFromPoint( width * x, height - 1 ) ) {
			if ( root.contains( hit ) ) {
				continue;
			}
			const fixed = pinned( hit, view );
			if ( ! fixed ) {
				continue;
			}
			const box = fixed.getBoundingClientRect();
			if ( box.bottom >= height - 1 && box.height < height / 2 ) {
				most = Math.max( most, Math.ceil( height - box.top ) );
			}
		}
	}
	return most;
}

/**
 * The box that keeps an element fixed to the screen, the element itself or an ancestor, if any.
 *
 * @param node An element.
 * @param view The window.
 */
function pinned( node: Element, view: Window ): Element | null {
	for (
		let at: Element | null = node;
		at && at !== node.ownerDocument.documentElement;
		at = at.parentElement
	) {
		if ( 'fixed' === view.getComputedStyle( at ).position ) {
			return at;
		}
	}
	return null;
}

/**
 * Fit the bar into the theme while it is shown: called once the builder starts, and again as the screen changes.
 *
 * @param root The builder's element.
 */
export function fit( root: Element ): void {
	const bar = root.querySelector( '.cbuild-bar' );
	const page = root.ownerDocument.documentElement;
	if ( ! bar || 0 === bar.getClientRects().length ) {
		page.style.removeProperty( '--cbuild-bar-offset' );
		return;
	}
	unclip( root );
	const offset = taken( root );
	if ( offset > 0 ) {
		page.style.setProperty( '--cbuild-bar-offset', `${ offset }px` );
	} else {
		page.style.removeProperty( '--cbuild-bar-offset' );
	}
}

/**
 * Set the bar's own height on the page while it sticks over the page, for the page's scroll padding to keep focus
 * clear of it: it is as tall as its lines make it, the total, the estimate, and Back and Next on a row of their own
 * where they do not fit beside the total (D-272). Pinned under the panel on a desktop, it covers nothing.
 *
 * @param root The builder's element.
 */
export function size( root: Element ): void {
	const bar = root.querySelector( '.cbuild-bar' );
	const page = root.ownerDocument.documentElement;
	const view = root.ownerDocument.defaultView;
	if (
		! bar ||
		! view ||
		0 === bar.getClientRects().length ||
		'sticky' !== view.getComputedStyle( bar ).position
	) {
		page.style.removeProperty( '--cbuild-bar-size' );
		return;
	}
	page.style.setProperty(
		'--cbuild-bar-size',
		`${ Math.ceil( bar.getBoundingClientRect().height ) }px`
	);
}

/**
 * Keep where the stage's foot is on the page while it sticks at the top of the screen, narrower than 64rem, for the
 * scroll padding that keeps focus clear of it (WCAG 2.4.11; the auditor's M10 review, 1). Nothing to clear, 0, where
 * it sits beside the panel or scrolls with the page.
 *
 * @param root The builder's element.
 */
export function stage( root: Element ): void {
	const shown = root.querySelector( '.cbuild__stage' );
	const page = root.ownerDocument.documentElement;
	const view = root.ownerDocument.defaultView;
	const sticks =
		!! shown &&
		!! view &&
		0 !== shown.getClientRects().length &&
		'sticky' === view.getComputedStyle( shown ).position;
	// Where its foot is once it sticks: its sticky top and its height, not where it stands now, which before it
	// sticks is further down the page.
	page.style.setProperty(
		'--cbuild-stage-size',
		sticks && shown && view
			? `${ Math.ceil(
					( parseFloat( view.getComputedStyle( shown ).top ) || 0 ) +
						shown.getBoundingClientRect().height
				) }px`
			: '0px'
	);
}
