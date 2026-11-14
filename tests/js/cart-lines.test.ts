import { describe, expect, it, vi } from 'vitest';
import { SPACE, filters, register, removed } from '../../assets/src/cart/lines';

/**
 * The Cart and Checkout blocks' line filters: what the server says of a line, given to WooCommerce as it asks. The
 * browser tests run them in the real Cart block (tests/e2e/cart.spec.ts).
 */

const line = ( told?: Record< string, unknown > ) =>
	told ? { [ SPACE ]: told } : {};
const part = line( { role: 'part', name: 'Deck: Plain 8.5' } );
const kit = line( { role: 'kit', name: '' } );

describe( 'the cart line filters', () => {
	it( 'gives a row of a build its class, and any other row none', () => {
		expect( filters.cartItemClass( 'row', part ) ).toBe(
			'row cbuild-part'
		);
		expect( filters.cartItemClass( '', kit ) ).toBe( 'cbuild-kit' );
		expect( filters.cartItemClass( 'row', line( { role: '' } ) ) ).toBe(
			'row'
		);
		expect( filters.cartItemClass( 'row', line( { role: 'x' } ) ) ).toBe(
			'row'
		);
		expect( filters.cartItemClass( 'row', line() ) ).toBe( 'row' );
		expect( filters.cartItemClass( 'row', undefined ) ).toBe( 'row' );
		expect( filters.cartItemClass( 'row', null ) ).toBe( 'row' );
	} );

	it( "names a part with its slot's label, as the server wrote it", () => {
		expect( filters.itemName( 'Plain 8.5', part ) ).toBe(
			'Deck: Plain 8.5'
		);
		expect( filters.itemName( 'Complete', kit ) ).toBe( 'Complete' );
		expect(
			filters.itemName( 'Plain', line( { role: 'part', name: '' } ) )
		).toBe( 'Plain' );
		expect(
			filters.itemName( 'Plain', line( { role: 'part', name: 7 } ) )
		).toBe( 'Plain' );
		expect(
			filters.itemName( 'Plain', line( { role: 'kit', name: 'Other' } ) )
		).toBe( 'Plain' );
		expect( filters.itemName( 'Plain', undefined ) ).toBe( 'Plain' );
	} );

	it( "hides a part's remove link and leaves every other as it was", () => {
		expect( filters.showRemoveItemLink( true, part ) ).toBe( false );
		expect( filters.showRemoveItemLink( true, kit ) ).toBe( true );
		expect( filters.showRemoveItemLink( false, kit ) ).toBe( false );
		expect( filters.showRemoveItemLink( true, line() ) ).toBe( true );
	} );

	it( "says a build was removed once, by its kit's name, in place of a notice for each part", () => {
		// Claude in Cowork's recheck, N5.
		const createInfoNotice = vi.fn();
		const notices = { createInfoNotice };
		const gone = {
			extensions: line( {
				role: 'part',
				build: 'b1',
				removed: 'Complete was removed from your cart.',
			} ),
		};
		expect( removed( notices )( true, gone ) ).toBe( false );
		expect( removed( notices )( true, gone ) ).toBe( false );
		expect( createInfoNotice ).toHaveBeenCalledTimes( 2 );
		expect( createInfoNotice ).toHaveBeenCalledWith(
			'Complete was removed from your cart.',
			{
				context: 'wc/cart',
				speak: true,
				type: 'snackbar',
				id: 'cbuild-b1-removed',
			}
		);
		createInfoNotice.mockClear();
		expect( removed( notices )( true, { extensions: kit } ) ).toBe( true );
		expect( removed( notices )( false, { extensions: line() } ) ).toBe(
			false
		);
		expect( removed( notices )( true, null ) ).toBe( true );
		expect(
			removed( notices )( true, {
				extensions: line( { role: 'part', build: 'b1', removed: '' } ),
			} ),
			'a part with no words for it is still not named'
		).toBe( false );
		expect( createInfoNotice ).not.toHaveBeenCalled();
		expect(
			removed( undefined )( true, gone ),
			'with no notices store, still no notice for the part'
		).toBe( false );
	} );

	it( "registers under the plugin's namespace where WooCommerce's script is", () => {
		const registerCheckoutFilters = vi.fn();
		register( { wc: { blocksCheckout: { registerCheckoutFilters } } } );
		expect( registerCheckoutFilters ).toHaveBeenCalledWith(
			'creative-skateboard-builder',
			filters
		);
		const addFilter = vi.fn();
		register( { wp: { hooks: { addFilter } } } );
		expect( addFilter ).toHaveBeenCalledWith(
			'woocommerce_show_cart_item_removed_notice',
			'creative-skateboard-builder',
			expect.any( Function )
		);
		expect( () => register( {} ) ).not.toThrow();
		expect( () => register( { wc: {} } ) ).not.toThrow();
		expect( () =>
			register( { wc: { blocksCheckout: {} } } )
		).not.toThrow();
	} );
} );
