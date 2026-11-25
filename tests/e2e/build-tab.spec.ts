import { test, expect, inLocale, Locator, Page } from './support/test';
import { axe } from './support/axe';
import { login } from './support/login';

/**
 * The kit's Build tab (milestone 4): its slots, rules, presets and assembly settings on a tab of the kit's product
 * screen. Set up by keyboard, a wrong value named in WooCommerce's notice with a link that leads to its field while
 * the rest saves, a slot's row moved by its buttons (WCAG 2.5.7), and axe-clean in each state.
 */

const PANEL = '#cbuild_build';

// WooCommerce's own search fields (selectWoo), which the spec asks for: their markup is WooCommerce's to put right
// (D-107). The select each one stands for is ours and is checked.
const THEIRS = [ '.select2-container' ];

// The kit is found by its name, which the fixture sets; a kit has no SKU, and its ID differs from site to site.
async function edit( page: Page ) {
	await page.goto(
		'/wp-admin/edit.php?post_type=product&s=Build+tab+test+kit'
	);
	await page
		.locator( '#the-list' )
		.getByRole( 'link', { name: 'Build tab test kit', exact: true } )
		.first()
		.click();
	// The product editor is the heaviest admin page; Firefox on CI's runner took longer than five seconds to show it
	// once (run 43832149023), with the page whole by the failure's snapshot. A page load's wait, not the check's.
	await expect( page.locator( '#woocommerce-product-data' ) ).toBeVisible( {
		timeout: 15_000,
	} );
}

async function update( page: Page ) {
	await Promise.all( [
		page.waitForURL( /message=/ ),
		page.locator( '#publish' ).click(),
	] );
}

// WooCommerce binds its tabs when its script has run, which at phone width can be after the first click.
async function tab( page: Page ) {
	await expect( async () => {
		await page.locator( `a[href="${ PANEL }"]` ).click();
		await expect( page.locator( PANEL ) ).toBeVisible( { timeout: 2000 } );
	} ).toPass();
}

async function type( page: Page, id: string, text: string ) {
	await page.locator( id ).focus();
	await page.keyboard.press( 'ControlOrMeta+a' );
	await page.keyboard.type( text );
}

// The slots' labels in the order their rows stand in, the new slot's empty one left out.
async function slots( page: Page ) {
	return (
		await page
			.locator( `${ PANEL } .cbuild-kit-row input[id$="-label"]` )
			.evaluateAll( ( inputs ) =>
				inputs.map( ( input ) => ( input as HTMLInputElement ).value )
			)
	).filter( ( label ) => label !== '' );
}

test.describe( 'The Build tab', () => {
	test.skip(
		( { javaScriptEnabled } ) => ! javaScriptEnabled,
		"WooCommerce's product data box shows its tabs with a script."
	);

	test( 'a kit is set up by keyboard, a wrong value is named with a link to its field, and the rest saves', async ( {
		page,
	} ) => {
		await login( page );
		await edit( page );
		await tab( page );
		await expect(
			page.locator( `${ PANEL } legend`, { hasText: 'Slot: Trucks' } )
		).toBeVisible();
		await axe( page, 'the Build tab', PANEL, [], THEIRS );
		// The start button's box says what it shows (M12's audit).
		await expect(
			page.locator( '#cbuild-kit-settings-start_button' )
		).toHaveAccessibleName(
			'Show "Build a complete with this deck" on its parts\' pages'
		);

		// By keyboard alone: the Trucks slot's settings opened, units that are no number, and a fee.
		const fold = page.locator(
			'#cbuild-kit-slots-1 details.cbuild-kit-fold'
		);
		await fold.locator( '> summary' ).focus();
		await page.keyboard.press( 'Enter' );
		await expect( fold ).toHaveAttribute( 'open', '' );
		await axe( page, 'the Build tab with a slot open', PANEL, [], THEIRS );
		await type( page, '#cbuild-kit-slots-1-units', 'two' );
		await type( page, '#cbuild-kit-assembly-fee', '12' );
		await update( page );

		const notices = page.locator( '#woocommerce_errors' );
		await expect( notices ).toContainText(
			"The kit's slots were not saved."
		);
		const link = notices.getByRole( 'link', {
			name: 'The Trucks slot, Units per build: this is not a value the field takes.',
		} );
		await expect( link ).toBeVisible();

		// The link opens the tab and lands on the field, which is marked and tied to the sentence.
		await link.focus();
		await page.keyboard.press( 'Enter' );
		const units = page.locator( '#cbuild-kit-slots-1-units' );
		await expect( page.locator( PANEL ) ).toBeVisible();
		await expect( units ).toBeFocused();
		await expect( units ).toBeInViewport();
		await expect( units ).toHaveValue( 'two' );
		await expect( units ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( units ).toHaveAccessibleDescription(
			/this is not a value the field takes/
		);
		// The fee saved all the same.
		await expect( page.locator( '#cbuild-kit-assembly-fee' ) ).toHaveValue(
			'12'
		);
		await axe(
			page,
			'the Build tab with a section not saved',
			PANEL,
			[],
			THEIRS
		);

		await type( page, '#cbuild-kit-slots-1-units', '2' );
		await update( page );
		await expect( page.locator( '#woocommerce_errors' ) ).toHaveCount( 0 );
		await tab( page );
		await expect( units ).toHaveValue( '2' );
		await expect( page.locator( `${ PANEL } [aria-invalid]` ) ).toHaveCount(
			0
		);
	} );

	test( "a slot's row moves by its buttons from the keyboard, and the kit keeps the order", async ( {
		page,
	} ) => {
		await login( page );
		await edit( page );
		await tab( page );
		const before = await slots( page );
		const last = before.length - 1;
		expect( before[ 0 ] ).toBe( 'Deck' );

		// The row is held by what it is, not by where it stands, since it is about to move.
		const row = page.locator( `${ PANEL } .cbuild-kit-row` ).filter( {
			has: page.locator(
				`input[id$="-label"][value="${ before[ last ] }"]`
			),
		} );
		// Each row's buttons carry the row's name, so a list of the tab's buttons tells them apart.
		await expect(
			page.getByRole( 'button', { name: /^Move up Slot: / } )
		).toHaveCount( before.length );
		const up = row.getByRole( 'button', { name: 'Move up' } );
		const down = row.getByRole( 'button', { name: 'Move down' } );

		// Up one: focus stays on the button, so it can be pressed again.
		await up.focus();
		await page.keyboard.press( 'Enter' );
		const moved = [ ...before ];
		moved.splice( last - 1, 0, ...moved.splice( last, 1 ) );
		expect( await slots( page ) ).toEqual( moved );
		await expect( up ).toBeFocused();
		// A screen reader hears where it went.
		await expect( page.locator( '#a11y-speak-polite' ) ).toHaveText(
			`${ before[ last ] } is now slot ${ last } of ${
				before.length + 1
			}.`
		);
		// The deck's row has nowhere to go up to.
		await expect(
			page
				.locator( `${ PANEL } .cbuild-kit-row` )
				.first()
				.getByRole( 'button', { name: 'Move up' } )
		).toBeDisabled();
		await axe( page, 'the Build tab with a row moved', PANEL, [], THEIRS );

		await update( page );
		await expect( page.locator( '#woocommerce_errors' ) ).toHaveCount( 0 );
		await tab( page );
		expect( await slots( page ) ).toEqual( moved );

		// And back down, with Space this time, so the kit is left as it was found.
		const again = down;
		await again.focus();
		await page.keyboard.press( 'Space' );
		expect( await slots( page ) ).toEqual( before );
		await expect( again ).toBeFocused();
		await update( page );
		await tab( page );
		expect( await slots( page ) ).toEqual( before );
	} );

	// #114: the Width guide section. Rows removed and added by their buttons from the keyboard, the shop's rows saved,
	// the guide hidden, and the builder's rows put back, which leaves the kit as it was found.
	test( "the width guide takes rows of its own by keyboard, hides, and goes back to the builder's rows", async ( {
		page,
	}, info ) => {
		// The builder's own first row, as the site's locale writes its number: 6,5 in French.
		const smallest = inLocale( info.project.name, '6.5 or smaller' );
		const GUIDE = '#cbuild-kit-settings-width_guide';
		const row = ( at: number ) => `${ GUIDE }-rows-${ at }`;
		const shoe = ( at: number ) => page.locator( `${ row( at ) }-shoe` );
		const shown = page.locator( `${ GUIDE }-shown` );
		const plugins = page.locator( `${ GUIDE }-plugins` );
		await login( page );
		await edit( page );
		await tab( page );
		const add = page.locator( `${ GUIDE } button[data-cbuild-add]` );
		await expect( add ).toBeVisible();
		await expect( shown ).toBeChecked();
		await expect( plugins ).toBeChecked();
		// The builder's rows, to start from, and one empty row; the rest wait.
		await expect( shoe( 0 ) ).toHaveValue( smallest );
		await expect( shoe( 4 ) ).toHaveValue( '' );
		await expect( page.locator( row( 4 ) ) ).toBeVisible();
		await expect( page.locator( row( 5 ) ) ).toBeHidden();
		await axe( page, 'the Build tab, Width guide', PANEL, [], THEIRS );

		// Remove the first row, named with it: the rows after it move up, and focus stays in its place.
		const remove = page.getByRole( 'button', { name: 'Remove Row 1' } );
		await remove.focus();
		await page.keyboard.press( 'Enter' );
		await expect( shoe( 0 ) ).toHaveValue( '7 to 8' );
		await expect( shoe( 0 ) ).toBeFocused();
		await expect( page.locator( row( 4 ) ) ).toBeHidden();
		await expect( page.locator( '#a11y-speak-polite' ) ).toHaveText(
			'Row 1 removed. The rows after it moved up.'
		);
		await expect( plugins ).not.toBeChecked();

		// Add a row, with Space: it is shown and takes focus.
		await add.focus();
		await page.keyboard.press( 'Space' );
		await expect( page.locator( row( 4 ) ) ).toBeVisible();
		await expect( shoe( 4 ) ).toBeFocused();
		await page.keyboard.type( 'EU 46' );
		await page.keyboard.press( 'Tab' );
		await page.keyboard.type( '9' );
		await axe(
			page,
			'the Build tab, Width guide with a row added',
			PANEL,
			[],
			THEIRS
		);
		await update( page );
		await expect( page.locator( '#woocommerce_errors' ) ).toHaveCount( 0 );
		await tab( page );
		await expect( plugins ).not.toBeChecked();
		await expect( shoe( 0 ) ).toHaveValue( '7 to 8' );
		await expect( shoe( 3 ) ).toHaveValue( 'EU 46' );
		await expect( page.locator( `${ row( 3 ) }-from` ) ).toHaveValue( '9' );

		// Hidden, by keyboard: its rows are kept.
		await shown.focus();
		await page.keyboard.press( 'Space' );
		await update( page );
		await tab( page );
		await expect( shown ).not.toBeChecked();
		await expect( shoe( 3 ) ).toHaveValue( 'EU 46' );

		// The builder's rows again, and shown: the kit as it was found.
		await shown.check();
		await plugins.check();
		await update( page );
		await tab( page );
		await expect( shown ).toBeChecked();
		await expect( plugins ).toBeChecked();
		await expect( shoe( 0 ) ).toHaveValue( smallest );
		await expect( shoe( 4 ) ).toHaveValue( '' );
	} );

	// The walkthrough of M12, finding 9 (D-387): a list cut its chosen option short, "the builder's own otherw", at
	// 1440 px. Each list is as wide as its longest option, up to the field's width.
	test( 'each list shows its options whole on a desktop', async ( {
		page,
	}, info ) => {
		test.skip(
			info.project.name.includes( 'phone' ),
			'A phone is narrower than the longest option; its own picker shows each whole.'
		);
		await page.setViewportSize( { width: 1440, height: 900 } );
		await login( page );
		await edit( page );
		await tab( page );
		const cut = await page
			.locator( `${ PANEL } p.form-field select.short:not([multiple])` )
			.evaluateAll( ( lists ) =>
				lists
					.filter(
						( list ) =>
							( list as HTMLElement ).offsetParent !== null
					)
					.flatMap( ( list ) => {
						const pen = document
							.createElement( 'canvas' )
							.getContext( '2d' );
						if ( ! pen ) {
							return [];
						}
						const select = list as HTMLSelectElement;
						const style = getComputedStyle( select );
						pen.font = `${ style.fontWeight } ${ style.fontSize } ${ style.fontFamily }`;
						const room =
							select.clientWidth -
							parseFloat( style.paddingInlineStart ) -
							parseFloat( style.paddingInlineEnd );
						return [ ...select.options ]
							.filter(
								( option ) =>
									// A pixel for the canvas's measure against layout's: Firefox sizes the list to
									// the text to within it.
									pen.measureText( option.text ).width >
									room + 1
							)
							.map(
								( option ) => `${ select.id }: ${ option.text }`
							);
					} )
			);
		expect( cut, 'options cut short' ).toEqual( [] );
	} );

	// #142: the assembly time in business days, beside the lead time. Typed as "2 - 4", it is kept as "2-4"; past 60
	// it is not saved, and the notice leads to the field in Assembly, which shows it as typed.
	test( 'the assembly time in business days is typed beside the lead time, kept as 2-4, and refused past 60', async ( {
		page,
	} ) => {
		const DAYS = '#cbuild-kit-settings-lead_days';
		const days = page.locator( DAYS );
		await login( page );
		await edit( page );
		await tab( page );
		await expect( days ).toHaveAccessibleName(
			'Assembly time in business days'
		);
		await expect( days ).toHaveAccessibleDescription( /from 0 to 60/ );
		// In the Assembly section, after the lead time.
		const fields = await page
			.locator( '#cbuild-kit-assembly :is(input, textarea)' )
			.evaluateAll( ( controls ) =>
				controls.map( ( control ) => control.id )
			);
		expect(
			fields.slice(
				fields.indexOf( 'cbuild-kit-assembly-lead_time' ),
				fields.indexOf( 'cbuild-kit-assembly-lead_time' ) + 2
			)
		).toEqual( [
			'cbuild-kit-assembly-lead_time',
			'cbuild-kit-settings-lead_days',
		] );

		await type( page, DAYS, '2 - 4' );
		await update( page );
		await expect( page.locator( '#woocommerce_errors' ) ).toHaveCount( 0 );
		await tab( page );
		await expect( days ).toHaveValue( '2-4' );

		await type( page, DAYS, '61' );
		await type( page, '#cbuild-kit-assembly-fee', '14' );
		await update( page );
		// The walkthrough of M12, finding 6 (D-385): the notice names the assembly time alone, and leads to its field.
		const refused = page
			.locator( '#woocommerce_errors' )
			.getByRole( 'link', {
				name: "The kit's assembly time in business days was not saved. The Build tab shows it as you typed it, with what to put right.",
			} );
		await expect( refused ).toBeVisible();
		await refused.focus();
		await page.keyboard.press( 'Enter' );
		await expect( days ).toBeFocused();
		// Finding 7 (D-386): the field refused is marked beside the section's list, by a thicker border in WordPress's
		// error red, so it is seen apart from the fields that saved, and not by colour alone.
		const edge = ( locator: Locator ) =>
			locator.evaluate( ( field ) => {
				const style = getComputedStyle( field );
				return {
					colour: style.borderTopColor,
					ring: style.boxShadow,
				};
			} );
		await page.locator( '#cbuild-kit-assembly-fee' ).focus();
		expect( await edge( days ), 'the field refused' ).toEqual( {
			colour: 'rgb(214, 54, 56)',
			ring: 'rgb(214, 54, 56) 0px 0px 0px 1px',
		} );
		expect(
			( await edge( page.locator( '#cbuild-kit-assembly-lead_time' ) ) )
				.colour,
			'a field that saved'
		).not.toBe( 'rgb(214, 54, 56)' );
		const link = page.locator( '#woocommerce_errors' ).getByRole( 'link', {
			name: 'Assembly time in business days: type business days from 0 to 60, such as 2 or 2-4.',
		} );
		await expect( link ).toBeVisible();
		await link.focus();
		await page.keyboard.press( 'Enter' );
		await expect( days ).toBeFocused();
		await expect( days ).toBeInViewport();
		await expect( days ).toHaveValue( '61' );
		await expect( days ).toHaveAttribute( 'aria-invalid', 'true' );
		await expect( days ).toHaveAccessibleDescription(
			/from 0 to 60, such as 2 or 2-4/
		);
		// The fee saved all the same.
		await expect( page.locator( '#cbuild-kit-assembly-fee' ) ).toHaveValue(
			'14'
		);
		await axe(
			page,
			'the Build tab with an assembly time not saved',
			PANEL,
			[],
			THEIRS
		);

		// Emptied, the kit has none.
		await days.fill( '' );
		await update( page );
		await expect( page.locator( '#woocommerce_errors' ) ).toHaveCount( 0 );
		await tab( page );
		await expect( days ).toHaveValue( '' );
		await expect( days ).not.toHaveAttribute( 'aria-invalid', 'true' );
	} );

	// #142 in French: the field's label, and its error, in the plugin's French.
	test( 'the assembly time and its error are said in French', async ( {
		page,
	}, info ) => {
		test.skip( 'fr-ca' !== info.project.name, 'The fr-ca project alone' );
		await page.setExtraHTTPHeaders( {
			'x-cbuild-e2e-locale': 'fr_CA',
			'x-cbuild-e2e-translated': '1',
		} );
		const DAYS = '#cbuild-kit-settings-lead_days';
		const days = page.locator( DAYS );
		await login( page );
		await edit( page );
		await tab( page );
		await expect( days ).toHaveAccessibleName(
			'Délai d’assemblage en jours ouvrables'
		);
		await type( page, DAYS, 'deux jours' );
		await update( page );
		await expect( page.locator( '#woocommerce_errors' ) ).toContainText(
			'Délai d’assemblage en jours ouvrables : tapez un nombre de jours ouvrables, par exemple 2, ou un intervalle, le plus petit nombre d’abord, par exemple 2-4.'
		);
		await tab( page );
		await expect( days ).toHaveValue( 'deux jours' );
		await expect( days ).toHaveAttribute( 'aria-invalid', 'true' );
		// The auditor's M12 minor 7: axe on the field with its error showing, in French as in English.
		await axe(
			page,
			'the Build tab with an assembly time not saved, in French',
			PANEL,
			[],
			THEIRS
		);

		await days.fill( '' );
		await update( page );
		await expect( page.locator( '#woocommerce_errors' ) ).toHaveCount( 0 );
	} );

	test( 'a product that is not a kit has no Build fields in its form', async ( {
		page,
	} ) => {
		await login( page );
		await page.goto(
			'/wp-admin/edit.php?post_type=product&s=smoke-test-deck'
		);
		await page
			.locator( '#the-list' )
			.getByRole( 'link', { name: 'Smoke test deck', exact: true } )
			.first()
			.click();
		await expect(
			page.locator( '#woocommerce-product-data' )
		).toBeVisible();
		await expect( page.locator( `a[href="${ PANEL }"]` ) ).toBeHidden();
		await expect(
			page.locator( '#post [name^="cbuild_kit"]' )
		).toHaveCount( 0 );
	} );
} );
