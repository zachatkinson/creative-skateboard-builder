/**
 * The few WordPress packages the block's editor and the builder's store use. WordPress loads each on the page; the
 * build names them as dependencies and bundles none (`@wordpress/dependency-extraction-webpack-plugin`), so no package
 * is installed for them and these declarations stand in for their types. In Vitest, `@wordpress/interactivity` is the
 * double in tests/js/support.
 */
declare module '@wordpress/interactivity' {
	/**
	 * Define a store, or add to one.
	 *
	 * @param namespace    The store's name.
	 * @param definition   Its state, actions and callbacks.
	 * @param options      How it is defined.
	 * @param options.lock True for a private store that no other code can read.
	 */
	export function store< T extends object >(
		namespace: string,
		definition: T,
		options?: { lock?: boolean | string }
	): T;
	/**
	 * What the server gave a store through `wp_interactivity_config()`.
	 *
	 * @param namespace The store's name.
	 */
	export function getConfig( namespace?: string ): Record< string, unknown >;
	/**
	 * The element whose directive is running.
	 */
	export function getElement(): {
		ref: HTMLElement | null;
		attributes: Record< string, unknown >;
	};
	/**
	 * The context the running directive is in, such as a `data-wp-each` item's.
	 */
	export function getContext< T extends object >(): T;
}
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
	/**
	 * A React root in an element, for the admin kit's DataForm (M15, assets/src/admin/form/draw.ts).
	 *
	 * @param container The element.
	 */
	export function createRoot( container: Element ): {
		render: ( element: unknown ) => void;
		unmount: () => void;
	};
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

/**
 * A stylesheet imported for its side effect: the build extracts it to its own file (block.json's `style`).
 */
declare module '*.scss';
