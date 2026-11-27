import { test, expect, Page, SITE } from './support/test';
import { cli, wp } from './support/cli';

/**
 * The test themes' headers and sidebars hold still (D-519). Each of the three lists the site's pages in its header when it is given
 * no menu, so a page a later fixture published moved every builder below it by a part of a pixel, and the visual
 * snapshots with it. The shop's fixture gives each a fixed menu: publishing a page leaves each header's links as they
 * were, and the page is in none of them. And it sets the widget areas a fresh site has, which a real switch of theme
 * remaps and a switch back leaves remapped.
 */

const THEMES = [ 'twentytwentyfive', 'storefront', 'kadence' ] as const;
const TITLE = 'A page published after the fixture';

/**
 * Every link in the theme's header, hidden ones (a phone's menu) included.
 *
 * @param page  The page.
 * @param url   The site's address.
 * @param theme The theme, asked for by the e2e theme mu-plugin's cookie.
 */
async function headerLinks(
	page: Page,
	url: string,
	theme: string
): Promise< string[] > {
	await page
		.context()
		.addCookies( [ { name: 'cbuild_e2e_theme', value: theme, url } ] );
	await page.goto( '/' );
	return page
		.locator( 'header' )
		.first()
		.locator( 'a' )
		.evaluateAll( ( links ) =>
			links.map( ( link ) => ( link.textContent ?? '' ).trim() )
		);
}

test( 'publishing a page leaves each test theme’s header as it was', async ( {
	page,
	baseURL,
}, testInfo ) => {
	test.skip(
		testInfo.project.name !== 'chromium-desktop',
		'The header is the server’s; one browser reads it.'
	);
	const url = baseURL || SITE;
	const before: Record< string, string[] > = {};
	for ( const theme of THEMES ) {
		before[ theme ] = await headerLinks( page, url, theme );
		expect( before[ theme ].length, theme ).toBeGreaterThan( 0 );
	}
	const id = wp(
		`post create --post_type=page --post_status=publish --post_title="${ TITLE }" --porcelain`
	);
	try {
		for ( const theme of THEMES ) {
			const after = await headerLinks( page, url, theme );
			expect( after, theme ).not.toContain( TITLE );
			expect( after, theme ).toEqual( before[ theme ] );
		}
	} finally {
		wp( `post delete ${ id } --force` );
	}
} );

test( 'switching the site to Kadence and back leaves Storefront’s sidebar as it was, once the fixture has run', async ( {
	page,
	baseURL,
}, testInfo ) => {
	test.skip(
		testInfo.project.name !== 'chromium-desktop',
		'The sidebar is the server’s; one browser reads it.'
	);
	const url = baseURL || SITE;
	const sidebar = async () => {
		await page
			.context()
			.addCookies( [
				{ name: 'cbuild_e2e_theme', value: 'storefront', url },
			] );
		await page.goto( '/' );
		return page
			.locator( '#secondary' )
			.evaluateAll( ( areas ) =>
				areas.map( ( area ) =>
					area.textContent?.replace( /\s+/g, ' ' ).trim()
				)
			);
	};
	const before = await sidebar();
	expect( before.join( '' ) ).not.toBe( '' );
	try {
		// A real switch, as a walkthrough or a person makes it: core remaps the widgets to Kadence's areas.
		wp( 'theme activate kadence' );
		wp( 'theme activate twentytwentyfive' );
		// What Playwright's setup and tools/visual.sh run before every suite.
		cli(
			'wp eval-file wp-content/plugins/creative-skateboard-builder/tests/e2e/fixtures/shop.php'
		);
		expect( await sidebar() ).toEqual( before );
	} finally {
		wp( 'theme activate twentytwentyfive' );
		cli(
			'wp eval-file wp-content/plugins/creative-skateboard-builder/tests/e2e/fixtures/shop.php'
		);
	}
} );
