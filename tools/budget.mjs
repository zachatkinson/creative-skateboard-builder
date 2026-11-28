/**
 * The asset budgets (docs/AUDIT.md: Performance): the builder's script under 15 kB and its styles under 10 kB,
 * compressed with Brotli as the shop's server would send them. Every .js and .css under build/ is measured; the
 * builder's own bundle is build/builder/view.js and its styles build/builder/style-index.css (D-159). The
 * admin's script, build/admin/part-specs.js, is held to the same budget (D-086).
 *
 * From 1.4.0 the 3D view's chunk, build/builder/three.js, which the builder loads only when the shopper presses View
 * in 3D, has a line of its own (#139): 200 kB, compressed with gzip at level 9, as three.js's own size is measured
 * and as Zach approved it. Its Brotli size is printed beside it. Every other budget is as it was.
 *
 * From 1.5.0 M15's admin screens, each its own entry, have a line of their own (Zach approved it on 26 November,
 * D-553): 170 kB with Brotli at quality 11. A screen holds the admin kit with DataForm, about 104 kB, and DataViews'
 * table where it lists rows, 161.7 kB in the spike, rounded up. They load on the plugin's own admin screens only, never
 * on the shop's pages. The Build tab's and Part specs' scripts keep the 15 kB line until their screens are rebuilt on
 * the kit, and DataViews' stylesheet, 8.5 kB, fits the stylesheets' 10 kB.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';
import { brotliCompressSync, constants, gzipSync } from 'node:zlib';

const BUDGETS = { '.js': 15 * 1024, '.css': 10 * 1024 };

/** M15's admin screens, each with a Brotli budget of its own, by path. */
const SCREENS = Object.fromEntries(
	[ 'settings', 'import', 'check', 'spec-tables' ].map( ( screen ) => [
		join( 'build', 'admin', `${ screen }.js` ),
		170 * 1024,
	] )
);

/** The chunks with a gzip budget of their own, by path. */
const CHUNKS = { [ join( 'build', 'builder', 'three.js' ) ]: 200 * 1024 };

function walk( dir, out = [] ) {
	let entries = [];
	try {
		entries = readdirSync( dir );
	} catch {
		return out;
	}
	for ( const entry of entries ) {
		const path = join( dir, entry );
		if ( statSync( path ).isDirectory() ) {
			walk( path, out );
		} else if (
			BUDGETS[ extname( path ) ] &&
			! /\.(map|asset\.php)$/.test( path )
		) {
			out.push( path );
		}
	}
	return out;
}

const files = walk( 'build' );
if ( files.length === 0 ) {
	console.log(
		'Budgets: build/ has no script or stylesheet yet; nothing to measure.'
	);
	process.exit( 0 );
}
let failed = false;
for ( const file of files ) {
	const brotli = brotliCompressSync( readFileSync( file ), {
		params: { [ constants.BROTLI_PARAM_QUALITY ]: 11 },
	} ).length;
	if ( undefined !== CHUNKS[ file ] ) {
		const gzip = gzipSync( readFileSync( file ), { level: 9 } ).length;
		const chunk = CHUNKS[ file ];
		const fits = gzip <= chunk;
		failed ||= ! fits;
		console.log(
			`${ fits ? 'ok  ' : 'OVER' } ${ file }: ${ gzip } B gzipped (budget ${ chunk } B), ${ brotli } B with Brotli`
		);
		continue;
	}
	const size = brotli;
	const budget = SCREENS[ file ] ?? BUDGETS[ extname( file ) ];
	const ok = size <= budget;
	failed ||= ! ok;
	console.log(
		`${ ok ? 'ok  ' : 'OVER' } ${ file }: ${ size } B compressed (budget ${ budget } B)`
	);
}
process.exit( failed ? 1 : 0 );
