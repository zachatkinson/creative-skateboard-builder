/**
 * The brand chips in one row (#98): a "priority+" row, as a navigation bar that keeps what fits and puts the rest
 * behind "More". The server renders every chip, the brands with the most parts first, so without the script all of
 * them show; the store measures which fit beside "N more" and hides the rest, a chosen chip never among them.
 */

/**
 * Which chips show in one row, in their order: every chip, where all fit; else those chosen, then each of the rest
 * in turn while it fits beside the others and the button. Once one doesn't fit, none after it shows, so the row is
 * the first in the order. Each width counts as whole pixels, so a row the browser would wrap is never taken for one
 * that fits.
 *
 * @param widths The chips' widths, in CSS pixels.
 * @param kept   Whether each is chosen, and so always shown.
 * @param room   The row's width.
 * @param gap    The gap between two chips.
 * @param button The button's width.
 */
export function fitting(
	widths: number[],
	kept: boolean[],
	room: number,
	gap: number,
	button: number
): boolean[] {
	const whole = widths.map( ( width ) => Math.ceil( width ) );
	const all = whole.reduce( ( sum, width ) => sum + width + gap, -gap );
	if ( all <= room ) {
		return whole.map( () => true );
	}
	const shown = whole.map( ( _, at ) => true === kept[ at ] );
	let line = whole.reduce(
		( sum, width, at ) => ( shown[ at ] ? sum + width + gap : sum ),
		button
	);
	for ( let at = 0; at < whole.length; at++ ) {
		if ( shown[ at ] ) {
			continue;
		}
		const width = whole[ at ]! + gap;
		if ( line + width > room ) {
			break;
		}
		shown[ at ] = true;
		line += width;
	}
	return shown;
}

/**
 * The values of the chips in a row that don't fit, measured as the page lays it out: a chip behind the button is out
 * of the flow and out of sight, but keeps its width.
 *
 * @param options The group's row of chips.
 * @param least   The button's width at least, so a wider button measured before is not taken back.
 * @return The values shut away, and the button's width as measured.
 */
export function shut(
	options: HTMLElement,
	least: number
): { values: string[]; button: number } {
	const chips = [
		...options.querySelectorAll< HTMLElement >( '.cbuild-chips__chip' ),
	];
	const inputs = chips.map( ( chip ) =>
		chip.querySelector< HTMLInputElement >( '.cbuild-chips__control' )
	);
	const button = Math.max(
		least,
		options.querySelector( '.cbuild-chips__more' )?.getBoundingClientRect()
			.width ?? 0
	);
	const style =
		options.ownerDocument.defaultView?.getComputedStyle( options );
	const shown = fitting(
		chips.map( ( chip ) => chip.getBoundingClientRect().width ),
		inputs.map( ( input ) => true === input?.checked ),
		options.clientWidth,
		parseFloat( style?.columnGap ?? '' ) || 0,
		button
	);
	return {
		values: inputs
			.filter( ( _, at ) => ! shown[ at ] )
			.map( ( input ) => input?.value ?? '' ),
		button,
	};
}
