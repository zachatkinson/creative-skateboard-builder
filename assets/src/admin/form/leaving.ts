/**
 * The admin kit's leaving warning (docs/ADMIN-KIT.md): the browser's own prompt, and only once something differs from
 * what the page loaded with, so a page looked at and left asks nothing (M15, "a leaving warning only after a change").
 * A Save leaves without it. A Save that something stopped, so the page stays, still warns after.
 *
 * WooCommerce's Settings warn on their own once a `change` bubbles from a control, which the kit's writes fire, so a
 * form there is described with `leave: false` (`Form::leftToHost()`) and the kit adds no second prompt.
 */

/**
 * Warns before the page is left while the form differs from what it loaded with.
 *
 * @param form    The server's form, whose submit is the Save; none for a form outside one.
 * @param changed Whether something differs now.
 * @return What takes the warning away.
 */
export function guard(
	form: HTMLFormElement | null,
	changed: () => boolean
): () => void {
	let saving: Event | null = null;
	const onSubmit = ( event: Event ): void => {
		saving = event;
	};
	const onLeave = ( event: BeforeUnloadEvent ): void => {
		// Read here, after every listener: a Save that one stopped didn't leave the page.
		const leavingBySave = null !== saving && ! saving.defaultPrevented;
		if ( leavingBySave || ! changed() ) {
			return;
		}
		event.preventDefault();
		// Chrome before 119 and Safari show the prompt only for a returnValue set.
		event.returnValue = '';
	};
	form?.addEventListener( 'submit', onSubmit );
	window.addEventListener( 'beforeunload', onLeave );
	return () => {
		form?.removeEventListener( 'submit', onSubmit );
		window.removeEventListener( 'beforeunload', onLeave );
	};
}
