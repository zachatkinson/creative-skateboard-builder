/**
 * The Builder block in the editor: a kit chosen in the sidebar, and the server's own render of it as the preview
 * (docs/STANDARDS.md: Blocks). The block is dynamic, so it saves nothing of its own markup.
 */
import { registerBlockType, type EditProps } from '@wordpress/blocks';
import {
	createElement as el,
	Fragment,
	useEffect,
	useState,
} from '@wordpress/element';
import { InspectorControls, useBlockProps } from '@wordpress/block-editor';
import { ComboboxControl, PanelBody, Placeholder } from '@wordpress/components';
import ServerSideRender from '@wordpress/server-side-render';
import apiFetch from '@wordpress/api-fetch';
import { addQueryArgs } from '@wordpress/url';
import { __ } from '@wordpress/i18n';
// The builder's styles, built to style-index.css: block.json's `style`, on the page and in the editor's preview.
import './style.scss';

const NAME = 'creative-skateboard-builder/builder';

interface Found {
	id: number;
	title: { rendered: string };
}

/**
 * A product's title as plain text: WordPress gives it with its entities written out.
 *
 * @param rendered The title as the REST API gives it.
 */
export function plain( rendered: string ): string {
	const text = new DOMParser().parseFromString( rendered, 'text/html' );
	return text.body.textContent ?? '';
}

function Edit( { attributes, setAttributes }: EditProps ) {
	const [ search, setSearch ] = useState( '' );
	const [ found, setFound ] = useState< Found[] >( [] );
	useEffect( () => {
		let current = true;
		apiFetch< Found[] >( {
			path: addQueryArgs( '/wp/v2/product', {
				search,
				per_page: 20,
				_fields: 'id,title',
				status: 'publish',
			} ),
		} )
			.then( ( products ) => {
				if ( current ) {
					setFound( products );
				}
			} )
			.catch( () => {
				if ( current ) {
					setFound( [] );
				}
			} );
		return () => {
			current = false;
		};
	}, [ search ] );
	const options = found.map( ( product ) => ( {
		value: String( product.id ),
		label: plain( product.title.rendered ),
	} ) );
	const chooser = el( ComboboxControl, {
		__next40pxDefaultSize: true,
		__nextHasNoMarginBottom: true,
		label: __( 'Kit', 'creative-skateboard-builder' ),
		help: __(
			'The builder kit this block sells. Type to search the shop.',
			'creative-skateboard-builder'
		),
		value: attributes.kit > 0 ? String( attributes.kit ) : null,
		options,
		onFilterValueChange: setSearch,
		onChange: ( value: string | null ) =>
			setAttributes( { kit: value ? Number( value ) : 0 } ),
	} );
	return el(
		Fragment,
		null,
		el(
			InspectorControls,
			null,
			el(
				PanelBody,
				{ title: __( 'Kit', 'creative-skateboard-builder' ) },
				chooser
			)
		),
		el(
			'div',
			useBlockProps(),
			attributes.kit > 0
				? el( ServerSideRender, { block: NAME, attributes } )
				: el(
						Placeholder,
						{
							label: __(
								'Skateboard builder',
								'creative-skateboard-builder'
							),
							instructions: __(
								'Choose the kit this block sells.',
								'creative-skateboard-builder'
							),
						},
						chooser
					)
		)
	);
}

registerBlockType( NAME, { edit: Edit, save: () => null } );
