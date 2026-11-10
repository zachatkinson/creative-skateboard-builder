/**
 * The asset budgets (docs/AUDIT.md: Performance): the builder's script under 15 kB and its styles under 10 kB,
 * compressed with Brotli as the shop's server would send them. Every .js and .css under build/ is measured; the
 * builder's own bundle is build/builder/view.js and its styles build/builder/style-view.css (milestone 6). Until then
 * build/ is empty and the budgets measure nothing, which passes.
 */
import { readdirSync, readFileSync, statSync } from 'node:fs';
import { join, extname } from 'node:path';
import { brotliCompressSync, constants } from 'node:zlib';

const BUDGETS = { '.js': 15 * 1024, '.css': 10 * 1024 };

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
	const size = brotliCompressSync( readFileSync( file ), {
		params: { [ constants.BROTLI_PARAM_QUALITY ]: 11 },
	} ).length;
	const budget = BUDGETS[ extname( file ) ];
	const ok = size <= budget;
	failed ||= ! ok;
	console.log(
		`${ ok ? 'ok  ' : 'OVER' } ${ file }: ${ size } B compressed (budget ${ budget } B)`
	);
}
process.exit( failed ? 1 : 0 );
