/**
 * A double of `@wordpress/interactivity` for Vitest: WordPress's own module is loaded by the page, never bundled, so
 * a test gives the store its config and the element a directive runs on, and reads back what the store defined.
 */
interface Defined {
	definition: object;
	options: { lock?: boolean | string } | undefined;
}

export const stores = new Map< string, Defined >();
const configs = new Map< string, Record< string, unknown > >();
let element: HTMLElement | null = null;

export function store< T extends object >(
	namespace: string,
	definition: T,
	options?: { lock?: boolean | string }
): T {
	stores.set( namespace, { definition, options } );
	return definition;
}

export function getConfig( namespace = '' ): Record< string, unknown > {
	return configs.get( namespace ) ?? {};
}

export function getElement(): {
	ref: HTMLElement | null;
	attributes: Record< string, unknown >;
} {
	return { ref: element, attributes: {} };
}

/**
 * What the server would have said through `wp_interactivity_config()`.
 *
 * @param namespace The store's name.
 * @param config    The config.
 */
export function setConfig(
	namespace: string,
	config: Record< string, unknown >
): void {
	configs.set( namespace, config );
}

/**
 * The element the next directive runs on.
 *
 * @param ref The element.
 */
export function setElement( ref: HTMLElement | null ): void {
	element = ref;
}
