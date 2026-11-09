import { expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * Fails on serious and critical WCAG 2.2 AA violations inside a region; shared by every spec.
 *
 * @param page    The page.
 * @param name    What is being checked, for the message.
 * @param include The CSS selector of the region.
 * @param exempt  Rules not run here, each with its reason in docs/DECISIONS.md at the call.
 * @param outside Selectors inside the region that are not ours to check (core's markup), each with its reason at the call.
 */
export async function axe(
	page: Page,
	name: string,
	include = 'body',
	exempt: string[] = [],
	outside: string[] = []
) {
	// Colours read mid-transition are blended with the page, so let every running animation finish before axe
	// measures contrast.
	await page.evaluate( () =>
		Promise.all(
			document
				.getAnimations()
				.filter(
					( a ) => a.effect?.getTiming().iterations !== Infinity
				)
				.map( ( a ) => a.finished.catch( () => undefined ) )
		)
	);
	const builder = new AxeBuilder( { page } )
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
		.include( include )
		.disableRules( exempt );
	outside.forEach( ( selector ) => builder.exclude( selector ) );
	const results = await builder.analyze();
	const serious = results.violations.filter(
		( v ) => v.impact === 'serious' || v.impact === 'critical'
	);
	expect(
		serious,
		`${ name }: ${ JSON.stringify(
			serious.map( ( v ) => ( {
				id: v.id,
				nodes: v.nodes.map( ( n ) => n.html ),
			} ) ),
			null,
			2
		) }`
	).toEqual( [] );
}
