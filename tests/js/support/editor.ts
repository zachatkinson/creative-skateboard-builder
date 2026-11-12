/**
 * Doubles of the WordPress packages the Builder block's editor uses (`@wordpress/blocks`, `element`, `block-editor`,
 * `components`, `url` and `i18n`): the page loads WordPress's own, never bundled. Elements are plain objects, and
 * `mount()` runs a function component with just enough of the hooks for the block's `Edit`.
 */

export interface Element {
	type: unknown;
	props: Record< string, unknown >;
	children: unknown[];
}

export const registered = new Map< string, Record< string, unknown > >();

export function registerBlockType(
	name: string,
	settings: Record< string, unknown >
): unknown {
	registered.set( name, settings );
	return settings;
}

export const Fragment = 'Fragment';
export const InspectorControls = 'InspectorControls';
export const PanelBody = 'PanelBody';
export const ComboboxControl = 'ComboboxControl';
export const Placeholder = 'Placeholder';

export function createElement(
	type: unknown,
	props?: Record< string, unknown > | null,
	...children: unknown[]
): Element {
	return { type, props: props ?? {}, children };
}

export function useBlockProps(
	props: Record< string, unknown > = {}
): Record< string, unknown > {
	return {
		className: 'wp-block-creative-skateboard-builder-builder',
		...props,
	};
}

export function addQueryArgs(
	url: string,
	args: Record< string, string | number >
): string {
	const query = new URLSearchParams();
	for ( const [ key, value ] of Object.entries( args ) ) {
		query.append( key, String( value ) );
	}
	return url + '?' + query.toString();
}

export function __( text: string, domain: string ): string {
	return domain === 'creative-skateboard-builder' ? text : 'wrong domain';
}

interface Effect {
	deps: unknown[];
	cleanup: void | ( () => void );
}

let slots: unknown[] = [];
let at = 0;
let waiting: Array< () => void > = [];

export function useState< T >( initial: T ): [ T, ( next: T ) => void ] {
	const i = at++;
	if ( ! ( i in slots ) ) {
		slots[ i ] = initial;
	}
	return [
		slots[ i ] as T,
		( next: T ) => {
			slots[ i ] = next;
		},
	];
}

export function useEffect(
	effect: () => void | ( () => void ),
	deps: unknown[]
): void {
	const i = at++;
	const before = slots[ i ] as Effect | undefined;
	if (
		undefined === before ||
		deps.some( ( dep, k ) => ! Object.is( dep, before.deps[ k ] ) )
	) {
		waiting.push( () => {
			if ( typeof before?.cleanup === 'function' ) {
				before.cleanup();
			}
			slots[ i ] = { deps, cleanup: effect() };
		} );
	}
}

/**
 * Runs a function component, its effects after it, as React would; `render()` runs it again with what the hooks now
 * hold, and `unmount()` runs each effect's cleanup.
 *
 * @param component The component.
 * @param props     Its props.
 */
export function mount< P >( component: ( props: P ) => unknown, props: P ) {
	slots = [];
	const render = (): Element => {
		at = 0;
		waiting = [];
		const tree = component( props ) as Element;
		for ( const run of waiting ) {
			run();
		}
		return tree;
	};
	return {
		render,
		unmount: () => {
			for ( const slot of slots ) {
				const cleanup = ( slot as Partial< Effect > | undefined )
					?.cleanup;
				if ( typeof cleanup === 'function' ) {
					cleanup();
				}
			}
		},
	};
}
