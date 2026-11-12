/**
 * The few WordPress packages the block's editor uses. WordPress loads each on the editor's page; the build names
 * them as dependencies and bundles none (`@wordpress/dependency-extraction-webpack-plugin`), so no package is
 * installed for them and these declarations stand in for their types.
 */
declare module '@wordpress/blocks' {
	/**
	 * Register a block type with its editor.
	 *
	 * @param name          The block's name.
	 * @param settings      What the editor needs of it.
	 * @param settings.edit The editor's view.
	 * @param settings.save What is saved: nothing, for a dynamic block.
	 */
	export function registerBlockType(
		name: string,
		settings: { edit: ( props: EditProps ) => unknown; save: () => null }
	): unknown;
	export interface EditProps {
		attributes: { kit: number };
		setAttributes: ( next: { kit: number } ) => void;
	}
}
declare module '@wordpress/element' {
	export function createElement(
		type: unknown,
		props?: Record< string, unknown > | null,
		...children: unknown[]
	): unknown;
	export function useState< T >( initial: T ): [ T, ( next: T ) => void ];
	export function useEffect(
		effect: () => void | ( () => void ),
		deps: unknown[]
	): void;
	export const Fragment: unknown;
}
declare module '@wordpress/block-editor' {
	export const InspectorControls: unknown;
	export function useBlockProps(
		props?: Record< string, unknown >
	): Record< string, unknown >;
}
declare module '@wordpress/components' {
	export const PanelBody: unknown;
	export const ComboboxControl: unknown;
	export const Placeholder: unknown;
}
declare module '@wordpress/server-side-render' {
	const ServerSideRender: unknown;
	export default ServerSideRender;
}
declare module '@wordpress/api-fetch' {
	export default function apiFetch< T >( options: {
		path: string;
	} ): Promise< T >;
}
declare module '@wordpress/url' {
	export function addQueryArgs(
		url: string,
		args: Record< string, string | number >
	): string;
}
declare module '@wordpress/i18n' {
	export function __( text: string, domain: string ): string;
}
