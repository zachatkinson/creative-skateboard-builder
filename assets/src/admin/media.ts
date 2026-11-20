/**
 * The media library's modal for a field that takes an attachment's ID: the Layer image field on a part (milestone 7)
 * and a preset's Photo on the kit's Build tab (D-252).
 */

/**
 * What WordPress's media modal hands back for the image chosen.
 */
export interface Chosen {
	id: number;
	url: string;
}

/**
 * As much of a media modal as this script uses.
 */
export interface MediaFrame {
	on: ( event: 'select', heard: () => void ) => unknown;
	open: () => unknown;
	state: () => {
		get: ( name: 'selection' ) => {
			first: () => { toJSON: () => Chosen } | undefined;
		};
	};
}

/**
 * WordPress's `wp.media`, which makes a modal (wp-includes/js/media-models.js).
 */
export type Media = ( options: {
	title: string;
	multiple: false;
	library: { type: string[] };
} ) => MediaFrame;

const CHOOSE = '.cbuild-layer__choose';

/**
 * A field's Choose button (spec: Admin, Milestone 7; D-252). Without this script the field takes an attachment's ID
 * and the button stays hidden; with WordPress's media modal on the page, the button shows and opens it, offering PNG
 * and WebP alone, and the image chosen fills the field and the preview. A variation's fields arrive after the page,
 * so the buttons are shown as they arrive and a click is heard on the document. The server checks what is sent.
 *
 * @param doc   The product screen.
 * @param media WordPress's `wp.media`, when the page has it.
 */
export function layers( doc: Document, media: Media | undefined ): void {
	if ( ! media ) {
		return;
	}
	const reveal = () =>
		doc
			.querySelectorAll< HTMLButtonElement >( `${ CHOOSE }[hidden]` )
			.forEach( ( button ) => {
				button.hidden = false;
			} );
	reveal();
	new MutationObserver( reveal ).observe( doc.body, {
		childList: true,
		subtree: true,
	} );
	doc.addEventListener( 'click', ( event ) => {
		const button =
			event.target instanceof Element
				? event.target.closest( CHOOSE )
				: null;
		if ( ! ( button instanceof HTMLButtonElement ) ) {
			return;
		}
		event.preventDefault();
		// Stryker disable next-line StringLiteral: no element has an empty ID or Stryker's placeholder for one, so a button that names no field finds none either way.
		const input = doc.getElementById( button.dataset.cbuildLayer ?? '' );
		if ( ! ( input instanceof HTMLInputElement ) ) {
			return;
		}
		const frame = media( {
			title: button.dataset.cbuildTitle ?? '',
			multiple: false,
			// The layer types the server accepts, from the one list it keeps (D-210).
			library: {
				type: ( button.dataset.cbuildTypes ?? '' )
					.split( ',' )
					.filter( Boolean ),
			},
		} );
		frame.on( 'select', () => {
			const chosen = frame.state().get( 'selection' ).first()?.toJSON();
			if ( ! chosen ) {
				return;
			}
			input.disabled = false;
			input.value = String( chosen.id );
			// WooCommerce marks a variation as changed when one of its fields is (meta-boxes-product-variation.js).
			input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			const image = doc
				.getElementById( `${ input.id }-preview` )
				?.querySelector( 'img' );
			if ( image ) {
				image.src = chosen.url;
				image.hidden = false;
			}
		} );
		frame.open();
	} );
}
