import { test, expect, Page, inLocale } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * The Part specs panel (milestone 3): a tab on a product and a group of fields in each variation. Filled in by
 * keyboard, a wrong value named in WooCommerce's notice while the rest saves, a value from a maker's chart shown
 * beneath its field, and axe-clean in each state.
 */

// The product is found by its SKU, which the fixture sets; its ID differs from site to site.
async function edit( page: Page, sku: string, name: string ) {
	await page.goto( `/wp-admin/edit.php?post_type=product&s=${ sku }` );
	await page
		.locator( '#the-list' )
		.getByRole( 'link', { name, exact: true } )
		.first()
		.click();
	await expect( page.locator( '#woocommerce-product-data' ) ).toBeVisible();
}

async function update( page: Page ) {
	await Promise.all( [
		page.waitForURL( /message=/ ),
		page.locator( '#publish' ).click(),
	] );
}

// WooCommerce binds its tabs when its script has run, which at phone width can be after the first click.
async function tab( page: Page, target: string ) {
	await expect( async () => {
		await page.locator( `a[href="${ target }"]` ).click();
		await expect( page.locator( target ) ).toBeVisible( {
			timeout: 2000,
		} );
	} ).toPass();
}

// A group of the panel is a details element; its summary opens it from the keyboard.
async function open( page: Page, region: string, group: string ) {
	const details = page.locator( `${ region } details` ).filter( {
		has: page.locator( 'summary', { hasText: group } ),
	} );
	const summary = details.locator( 'summary' );
	if ( ( await details.getAttribute( 'open' ) ) === null ) {
		await summary.focus();
		await page.keyboard.press( 'Enter' );
	}
	await expect( details ).toHaveAttribute( 'open', '' );
}

test.describe( 'The Part specs panel', () => {
	test.skip(
		( { javaScriptEnabled } ) => ! javaScriptEnabled,
		"WooCommerce's product data box shows its tabs, and loads and saves variations, with a script."
	);

	test( 'a part is filled in by keyboard, and a wrong value is named while the rest saves', async ( {
		page,
	} ) => {
		const PANEL = '#cbuild_part_specs';
		await login( page );
		await edit( page, 'smoke-test-deck', 'Smoke test deck' );
		await tab( page, PANEL );
		await expect( page.locator( PANEL ) ).toBeVisible();

		await open( page, PANEL, 'Deck' );
		await axe( page, 'the Part specs tab', PANEL );

		// By keyboard alone: the width as a fraction, Tab to the length, and a length that is not one.
		const width = page.locator( PANEL ).getByLabel( 'Deck width (in)' );
		const length = page.locator( PANEL ).getByLabel( 'Deck length (in)' );
		await width.focus();
		await page.keyboard.press( 'ControlOrMeta+a' );
		await page.keyboard.type( '8 1/4' );
		await page.keyboard.press( 'Tab' );
		await expect( length ).toBeFocused();
		await page.keyboard.press( 'ControlOrMeta+a' );
		await page.keyboard.type( 'long' );
		await update( page );

		await expect(
			page.getByText(
				'Deck length was not saved: "long" is not a measurement. Type a number, or a fraction such as 8 1/4.'
			)
		).toBeVisible();
		// The notice is a link to its field (#19): followed by keyboard, it opens the tab and lands on the field.
		const notice = page.getByRole( 'link', {
			name: /^Deck length was not saved/,
		} );
		await notice.focus();
		await page.keyboard.press( 'Enter' );
		await expect( page.locator( PANEL ) ).toBeVisible();
		await expect( length ).toBeFocused();
		await expect(
			page
				.locator( `${ PANEL } details` )
				.filter( { hasText: 'Deck width' } )
		).toHaveAttribute( 'open', '' );
		await expect( width ).toHaveValue( '8.25' );
		await expect( length ).toHaveValue( '' );
		await axe( page, 'the Part specs tab, with a value', PANEL );

		// As the fixture left it.
		await width.fill( '' );
		await update( page );
		await tab( page, PANEL );
		await expect( width ).toHaveValue( '' );
	} );

	test( "a variation names its size in a maker's chart, and each figure says where it comes from", async ( {
		page,
	}, testInfo ) => {
		const FIELDS = '.woocommerce_variation:first-child .cbuild-part-specs';
		const variations = async () => {
			await tab( page, '#variable_product_options' );
			await page
				.locator(
					'.woocommerce_variation:first-child h3 .edit_variation'
				)
				.click();
			await expect( page.locator( FIELDS ) ).toBeVisible();
		};
		// Issue #22, D-117: twice under load axe's target-size has said a group's heading was partly covered by
		// nodes that are nowhere near it in the settled page, and no run since has shown it again. Until that is
		// found, the rule is quarantined in this region alone and the headings are measured here instead: each at
		// least 24 px by 24 px, and none overlapping the next.
		const QUARANTINED = [ 'target-size' ];
		const targets = async () => {
			const boxes = await page
				.locator( `${ FIELDS } summary` )
				.evaluateAll( ( summaries ) =>
					summaries.map( ( summary ) => {
						const box = summary.getBoundingClientRect();
						return {
							top: box.top,
							bottom: box.bottom,
							width: box.width,
							height: box.height,
						};
					} )
				);
			expect( boxes.length ).toBeGreaterThan( 1 );
			boxes.forEach( ( box, at ) => {
				expect( box.width ).toBeGreaterThanOrEqual( 24 );
				expect( box.height ).toBeGreaterThanOrEqual( 24 );
				const before = boxes[ at - 1 ];
				if ( before ) {
					expect( box.top ).toBeGreaterThanOrEqual(
						before.bottom - 0.5
					);
				}
			} );
		};
		const save = async () => {
			await page.getByRole( 'button', { name: 'Save changes' } ).click();
			await expect(
				page.getByRole( 'button', { name: 'Save changes' } )
			).toBeDisabled();
		};

		await login( page );
		await edit( page, 'panel-test-trucks', 'Panel test trucks' );
		await variations();
		await expect(
			page.getByRole( 'group', { name: 'Part specs' } ).first()
		).toBeVisible();

		await open( page, FIELDS, 'Trucks' );
		const chart = page
			.locator( FIELDS )
			.getByLabel( "Maker's chart and size" );
		const size = await page
			.locator( `${ FIELDS } optgroup[label="Independent"] option` )
			.filter( { hasText: /^149$/ } )
			.getAttribute( 'value' );
		await chart.selectOption( size );
		await axe( page, "a variation's Part specs", FIELDS, QUARANTINED );
		await targets();
		await save();

		await page.reload();
		await variations();
		const axle = page.locator( FIELDS ).getByLabel( 'Axle width (in)' );
		await expect( axle ).toBeVisible();
		await expect( axle ).toHaveValue( '' );
		await expect( axle ).toHaveAccessibleDescription(
			inLocale(
				testInfo.project.name,
				"In use: 8.5 in, from Independent's chart, size 149."
			)
		);
		await axe(
			page,
			"a variation's Part specs, with a chart",
			FIELDS,
			QUARANTINED
		);
		await targets();

		// As the fixture left it.
		await chart.selectOption( '' );
		await save();
		await page.reload();
		await variations();
		await open( page, FIELDS, 'Trucks' );
		await expect( axle ).toHaveAccessibleDescription( '' );
	} );

	test( "an address that names a variation opens it, as the Catalogue check's links do", async ( {
		page,
	} ) => {
		await login( page );
		await edit( page, 'panel-test-trucks', 'Panel test trucks' );
		await tab( page, '#variable_product_options' );
		const first = page.locator( '.woocommerce_variation' ).first();
		await expect( first ).toBeAttached();
		const id = await first.locator( 'input.variable_post_id' ).inputValue();
		const address = page.url().replace( /#.*$/, '' );

		// The variation: its tab, its row opened, and its fields in view.
		await page.goto( 'about:blank' );
		await page.goto( `${ address }#cbuild_variation_${ id }` );
		const fields = page
			.locator( '.woocommerce_variation' )
			.filter( {
				has: page.locator( `input.variable_post_id[value="${ id }"]` ),
			} )
			.locator( '.cbuild-part-specs' );
		await expect( fields ).toBeVisible();
		await expect( fields ).toBeInViewport();
		// And focus is in them, for a keyboard or a screen reader.
		await expect( fields.locator( ':focus' ) ).toHaveCount( 1 );

		// The product's own tab.
		await page.goto( 'about:blank' );
		await page.goto( `${ address }#cbuild_part_specs` );
		await expect( page.locator( '#cbuild_part_specs' ) ).toBeVisible();
		await expect(
			page.locator( '#variable_product_options' )
		).toBeHidden();
	} );

	// Issue #23: on a host that leaves PHP's max_input_vars at 1,000, 21 fields in each of a page's 15 variations
	// took a product's form past it, and PHP drops what is over without a word.
	test( 'a part with no specs adds nothing to what the product form sends, and an open group adds its own', async ( {
		page,
	} ) => {
		const sent = async () => {
			const [ request ] = await Promise.all( [
				page.waitForRequest(
					( asked ) =>
						asked.method() === 'POST' &&
						asked.url().includes( '/wp-admin/post.php' )
				),
				update( page ),
			] );
			return Array.from(
				new URLSearchParams( request.postData() ?? '' ).keys()
			).filter( ( name ) => name.startsWith( 'cbuild_' ) );
		};

		await login( page );
		await edit( page, 'panel-test-trucks', 'Panel test trucks' );
		// With its variations loaded and one open, as a shop manager leaves them.
		await tab( page, '#variable_product_options' );
		await page
			.locator( '.woocommerce_variation:first-child h3 .edit_variation' )
			.click();
		await expect(
			page.locator(
				'.woocommerce_variation:first-child .cbuild-part-specs'
			)
		).toBeVisible();
		expect( await sent() ).toEqual( [] );

		// A group that is opened sends its fields, and no other group does.
		const PANEL = '#cbuild_part_specs';
		await tab( page, PANEL );
		await open( page, PANEL, 'Wheels' );
		await expect(
			page.locator( PANEL ).getByLabel( 'Wheel diameter (mm)' )
		).toBeEnabled();
		expect( await sent() ).toEqual( [ 'cbuild_specs[wheel_diameter]' ] );
	} );
} );
