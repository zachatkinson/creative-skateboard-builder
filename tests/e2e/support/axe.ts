import { createRequire } from 'node:module';
import { expect, Page } from '@playwright/test';
import AxeBuilder from '@axe-core/playwright';

/**
 * axe for Firefox, told to read `aria-labelledby` from the attribute alone (D-421). Playwright 1.65's Firefox (159,
 * build 1572) crashes its content process, on signal 11, once an element's `ariaLabelledByElements` has been read in
 * both Playwright's utility world and the page's: Playwright reads it there to name roles, since 1.65.0, and axe reads
 * it in the page wherever an element has `aria-labelledby` (microsoft/playwright#43967, Mozilla's bug 2090055). With
 * the property's name taken off axe's standard for the attribute, axe resolves the attribute's IDs itself: the same
 * elements, since the builder sets every reference in its markup and none from script. The source is the one
 * AxeBuilder injects, from its own copy of axe-core.
 */
const FIREFOX_AXE = `${
	(
		createRequire( require.resolve( '@axe-core/playwright' ) )(
			'axe-core'
		) as { source: string }
	 ).source
}
axe.configure( { standards: { ariaAttrs: { 'aria-labelledby': { prop: '' } } } } );`;

/**
 * Fails on any violation of the WCAG 2.0, 2.1 and 2.2 A and AA rules inside a region; shared by every spec.
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
	// measures contrast. Not one on an element that is not drawn, which axe leaves out: Firefox never moves a
	// transition on the widths folded inside a shut `details` on, so waiting for it would wait out the test.
	await page.evaluate( () =>
		Promise.all(
			document
				.getAnimations()
				.filter(
					( a ) =>
						a.effect?.getTiming().iterations !== Infinity &&
						false !==
							(
								( a.effect as KeyframeEffect | null )
									?.target as Element | null | undefined
							 )?.checkVisibility?.()
				)
				.map( ( a ) => a.finished.catch( () => undefined ) )
		)
	);
	const firefox =
		'firefox' === page.context().browser()?.browserType().name();
	const builder = new AxeBuilder(
		firefox ? { page, axeSource: FIREFOX_AXE } : { page }
	)
		.withTags( [ 'wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa' ] )
		.include( include )
		.disableRules( exempt );
	outside.forEach( ( selector ) => builder.exclude( selector ) );
	const results = await builder.analyze();
	// Every violation of the tagged rules, whatever impact axe gives it (docs/AUDIT.md: the A and AA rules).
	const violations = results.violations;
	expect(
		violations,
		`${ name }: ${ JSON.stringify(
			violations.map( ( v ) => ( {
				id: v.id,
				impact: v.impact,
				nodes: v.nodes.map( ( n ) => n.html ),
			} ) ),
			null,
			2
		) }`
	).toEqual( [] );
}
