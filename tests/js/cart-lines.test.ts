import { describe, expect, it, vi } from 'vitest';
import { SPACE, filters, register } from '../../assets/src/cart/lines';

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

	it( "registers under the plugin's namespace where WooCommerce's script is", () => {
		const registerCheckoutFilters = vi.fn();
		register( { wc: { blocksCheckout: { registerCheckoutFilters } } } );
		expect( registerCheckoutFilters ).toHaveBeenCalledWith(
			'creative-skateboard-builder',
			filters
		);
		expect( () => register( {} ) ).not.toThrow();
		expect( () => register( { wc: {} } ) ).not.toThrow();
		expect( () =>
			register( { wc: { blocksCheckout: {} } } )
		).not.toThrow();
	} );
} );
