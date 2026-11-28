/**
 * The summary of the server's refusals (src/Admin/Form/Mount.php): one notice that names each refused field and links
 * to it, printed first in the mount. As the page loads it takes focus, so a keyboard starts from what went wrong, as
 * GOV.UK's error summary does after a submit. Each link moves focus to DataForm's own control for the field, found by
 * its label, since DataForm names its controls with ids of its own (docs/research/admin-handbooks-2026.md, finding
 * 4.2). With no script the links go to the server's controls by their ids (`Mount::controlId()`).
 */

/**
 * Whether a label's words are a field's label: the same, or the label and more after a space, as DataForm writes a
 * required field's "Assembly time (Required)", translated.
 *
 * @param words The label element's words.
 * @param label The field's label.
 * @param exact Whether only the same words will do.
 */
function names( words: string, label: string, exact: boolean ): boolean {
	return words === label || ( ! exact && words.startsWith( `${ label } ` ) );
}

/**
 * The control DataForm drew for a label: the one its label points to, a list's group (list.ts), or the first inside a
 * fieldset named by it. A label
 * of the same words is taken before one that only starts with them, so "Unit" never lands on "Unit price".
 *
 * @param app   DataForm's box.
 * @param label The field's label.
 */
function control( app: HTMLElement, label: string ): HTMLElement | null {
	const labels = Array.from( app.querySelectorAll( 'label' ) );
	for ( const exact of [ true, false ] ) {
		const named = labels.find( ( one ) =>
			names( one.textContent?.trim() ?? '', label, exact )
		);
		if ( named ) {
			const pointed = named.htmlFor
				? app.ownerDocument.getElementById( named.htmlFor )
				: null;
			return (
				pointed ??
				named.querySelector< HTMLElement >( 'input, select, textarea' )
			);
		}
		// A list: a group named by its label (list.ts), which takes focus itself.
		const group = Array.from(
			app.querySelectorAll< HTMLElement >(
				'[role="group"][aria-labelledby]'
			)
		).find( ( one ) =>
			names(
				app.ownerDocument
					.getElementById(
						one.getAttribute( 'aria-labelledby' ) ?? ''
					)
					?.textContent?.trim() ?? '',
				label,
				exact
			)
		);
		if ( group ) {
			return group;
		}
		const legend = Array.from( app.querySelectorAll( 'legend' ) ).find(
			( one ) => names( one.textContent?.trim() ?? '', label, exact )
		);
		if ( legend ) {
			return (
				legend.parentElement?.querySelector< HTMLElement >(
					'input, select, textarea'
				) ?? null
			);
		}
	}
	return null;
}

/**
 * Gives the summary focus and its links their way to DataForm's controls.
 *
 * @param kit    The mount.
 * @param app    DataForm's box.
 * @param labels Each field's label, by its id.
 * @return What takes the links' handling away.
 */
export function summarise(
	kit: HTMLElement,
	app: HTMLElement,
	labels: ReadonlyMap< string, string >
): () => void {
	const summary = kit.querySelector< HTMLElement >(
		':scope > .cbuild-form__summary'
	);
	if ( null === summary ) {
		return () => {};
	}
	const onClick = ( event: MouseEvent ): void => {
		const link = ( event.target as Element | null )?.closest< HTMLElement >(
			'a[data-cbuild-field]'
		);
		const label = labels.get( link?.dataset.cbuildField ?? '' );
		const found = undefined === label ? null : control( app, label );
		if ( null === found ) {
			// A field DataForm isn't showing: the link goes where the page would take it.
			return;
		}
		event.preventDefault();
		found.focus();
	};
	summary.addEventListener( 'click', onClick );
	summary.focus();
	return () => summary.removeEventListener( 'click', onClick );
}
