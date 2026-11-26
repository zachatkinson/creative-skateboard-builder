import { execSync } from 'node:child_process';
import path from 'node:path';
import { withLock } from './lock';

/**
 * The one way the browser tests reach the browser site's command line (D-422). Every `wp-env run` reads wp-env's cache
 * file, changes a key and writes the file back, not atomically (@wordpress/env 12, lib/cache.js, setCache()), and a
 * read that meets a file mid-write gives an empty cache. Two at once, from two of Playwright's workers, can write back
 * a cache with only the WordPress version left in it, and every `wp-env run` after says the site was never started.
 * So one runs at a time, in every worker: the lock is a directory in this checkout's tmp/, which is the lane's own.
 */
export const LOCK = path.join( process.cwd(), 'tmp', 'wp-env-run.lock' );

/**
 * Runs a shell command on the host under the lock, and gives back what it printed, trimmed. One that fails throws with
 * what it said on stderr and stdout, which wp-env and WP-CLI print their reasons to: the tests' helpers used to throw
 * stderr away.
 *
 * @param command The command.
 */
export function locked( command: string ): string {
	return withLock( LOCK, () => {
		try {
			return execSync( command, {
				stdio: [ 'ignore', 'pipe', 'pipe' ],
				encoding: 'utf8',
			} ).trim();
		} catch ( error ) {
			const failed = error as {
				status?: number | null;
				stderr?: string;
				stdout?: string;
			};
			throw new Error(
				[
					`${ command } failed with exit code ${ failed.status }.`,
					failed.stderr?.trim(),
					failed.stdout?.trim(),
				]
					.filter( Boolean )
					.join( '\n' )
			);
		}
	} );
}

/**
 * Runs a command in the browser site's cli container.
 *
 * @param command The command, after `wp-env run cli --`.
 */
export function cli( command: string ): string {
	return locked( `npx wp-env run cli -- ${ command }` );
}

/**
 * Runs WP-CLI in the browser site.
 *
 * @param command The command, after `wp`.
 */
export function wp( command: string ): string {
	return cli( `wp ${ command }` );
}
