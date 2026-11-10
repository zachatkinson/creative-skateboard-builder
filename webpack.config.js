/**
 * @wordpress/scripts' own configuration, with the admin's scripts added to its classic-script build (D-086).
 * wp-scripts finds a block's scripts from its block.json; a script for a wp-admin screen belongs to no block, so it
 * is named here. With `--experimental-modules` the default export is two configurations: scripts, then script
 * modules. The builder's store is a script module and arrives through its block.json in milestone 6.
 */
const fs = require( 'node:fs' );
const path = require( 'node:path' );
const [
	scripts,
	modules,
] = require( '@wordpress/scripts/config/webpack.config' );

const ADMIN = {
	'admin/part-specs': path.resolve(
		__dirname,
		'assets/src/admin/part-specs.ts'
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
	modules,
];
