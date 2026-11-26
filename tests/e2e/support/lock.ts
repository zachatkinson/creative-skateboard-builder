import { randomUUID } from 'node:crypto';
import {
	mkdirSync,
	readFileSync,
	renameSync,
	rmSync,
	statSync,
	writeFileSync,
} from 'node:fs';
import path from 'node:path';

/**
 * A lock across processes, for the browser tests' calls into wp-env (D-422): Playwright's workers are processes of
 * their own, and two `wp-env run` at once can each write back half of wp-env's cache file, which then says the site
 * was never started.
 *
 * The lock is a directory, since making one is atomic: the process whose `mkdir` succeeds holds it, and writes its
 * process ID and a token of its own into it. A lock whose holder has died (a worker Playwright stopped mid-command),
 * or that is older than `staleAfter`, is set aside with an atomic rename and taken.
 */

const OWNER = 'owner';
const pause = new Int32Array( new SharedArrayBuffer( 4 ) );

/**
 * Waits without a timer: the callers are synchronous, as `execSync` is.
 *
 * @param ms How long, in milliseconds.
 */
function sleep( ms: number ): void {
	Atomics.wait( pause, 0, 0, ms );
}

/**
 * The lock's holder, as it wrote itself: "<pid> <token>", or null while there is none to read.
 *
 * @param dir The lock's directory.
 */
function holder( dir: string ): string | null {
	try {
		return readFileSync( path.join( dir, OWNER ), 'utf8' );
	} catch {
		return null;
	}
}

/**
 * Whether a process is still running. EPERM is a process that runs as another user: alive.
 *
 * @param pid The process ID.
 */
function alive( pid: number ): boolean {
	try {
		process.kill( pid, 0 );
		return true;
	} catch ( error ) {
		return 'EPERM' === ( error as NodeJS.ErrnoException ).code;
	}
}

/**
 * The holder of a lock nobody holds any more, or undefined while it is held. A lock with no holder written is the
 * moment between another process's `mkdir` and its write, unless it is older than `staleAfter`.
 *
 * @param dir        The lock's directory.
 * @param staleAfter How old a lock may be before it is taken, in milliseconds.
 */
function abandoned( dir: string, staleAfter: number ): string | undefined {
	let age: number;
	try {
		age = Date.now() - statSync( dir ).mtimeMs;
	} catch {
		return undefined;
	}
	const seen = holder( dir );
	const pid = Number( seen?.split( ' ' )[ 0 ] );
	const dead = null !== seen && pid > 0 && ! alive( pid );
	return dead || age > staleAfter ? ( seen ?? '' ) : undefined;
}

/**
 * Sets an abandoned lock aside, by a rename only one process can win, and removes it. If what was renamed is not the
 * lock judged abandoned (its holder let it go and another took it in between), it is put back.
 *
 * @param dir  The lock's directory.
 * @param seen The holder the lock was judged abandoned with.
 */
function clear( dir: string, seen: string ): void {
	const aside = `${ dir }.${ randomUUID() }`;
	try {
		renameSync( dir, aside );
	} catch {
		return;
	}
	if ( ( holder( aside ) ?? '' ) === seen ) {
		rmSync( aside, { recursive: true, force: true } );
		return;
	}
	try {
		renameSync( aside, dir );
	} catch {
		rmSync( aside, { recursive: true, force: true } );
	}
}

/**
 * Runs a function while this process holds the lock, and lets it go after, whether the function returned or threw.
 *
 * @param dir        The lock's directory; its parent is made if it is missing.
 * @param run        What to run while holding it.
 * @param staleAfter How old a lock may be before it is taken from a holder that is still running, in milliseconds.
 * @return What the function returned.
 */
export function withLock< T >(
	dir: string,
	run: () => T,
	staleAfter = 300_000
): T {
	mkdirSync( path.dirname( dir ), { recursive: true } );
	const mine = `${ process.pid } ${ randomUUID() }`;
	const giveUp = Date.now() + 2 * staleAfter;
	for (;;) {
		try {
			mkdirSync( dir );
			writeFileSync( path.join( dir, OWNER ), mine );
			break;
		} catch ( error ) {
			if ( 'EEXIST' !== ( error as NodeJS.ErrnoException ).code ) {
				throw error;
			}
		}
		const seen = abandoned( dir, staleAfter );
		if ( undefined !== seen ) {
			clear( dir, seen );
			continue;
		}
		if ( Date.now() > giveUp ) {
			throw new Error(
				`The lock ${ dir } is still held by ${ holder( dir ) }.`
			);
		}
		sleep( 25 );
	}
	try {
		return run();
	} finally {
		if ( holder( dir ) === mine ) {
			rmSync( dir, { recursive: true, force: true } );
		}
	}
}
