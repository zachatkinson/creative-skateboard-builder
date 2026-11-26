/**
 * @wordpress/scripts' own configuration, with the admin's scripts added to its classic-script build (D-086).
 * wp-scripts finds a block's scripts from its block.json; a script for a wp-admin screen belongs to no block, so it
 * is named here. With `--experimental-modules` the default export is two configurations: scripts, then script
 * modules. The builder's store is a script module and arrives through its block.json in milestone 6; from 1.4.0 it
 * imports the 3D view's chunk when the shopper asks for it (#139).
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const [
	scripts,
	modules,
] = require( '@wordpress/scripts/config/webpack.config' );

const ADMIN = {
	// The Cart and Checkout blocks' line filters and the carts' styles (milestone 5): a classic script too, since
	// WooCommerce's `wc.blocksCheckout` is one.
	'cart/lines': path.resolve( __dirname, 'assets/src/cart/lines.ts' ),
	'admin/part-specs': path.resolve(
		__dirname,
		'assets/src/admin/part-specs.ts'
	),
	'admin/build-tab': path.resolve(
		__dirname,
		'assets/src/admin/build-tab.ts'
	),
};

// Asked for the blocks' scripts only once there is a block: with none, wp-scripts warns that it found no entry.
const blocks =
	fs.globSync( 'assets/src/**/block.json', { cwd: __dirname } ).length > 0;

module.exports = [
	{
		...scripts,
		entry: async () => ( {
			...( blocks ? await scripts.entry() : {} ),
			...ADMIN,
		} ),
	},
	{
		...modules,
		optimization: {
			...modules.optimization,
			splitChunks: {
				...modules.optimization.splitChunks,
				cacheGroups: {
					...modules.optimization.splitChunks.cacheGroups,
					// The 3D view's chunk, build/builder/three.js, holds three.js too (1.4.0, #139): one file loaded on
					// the press of View in 3D, with a budget of its own, and nothing split out of it to load besides.
					defaultVendors: false,
				},
			},
		},
	},
];
