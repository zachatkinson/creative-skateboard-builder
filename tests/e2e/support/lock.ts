import { randomUUID } from 'node:crypto';
import {
	mkdirSync,
	readFileSync,
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
 * process ID and a token of its own into it. A lock whose holder has died (a worker Playwright stopped mid-command)
 * is cleared and taken. A holder still running keeps it however long it takes (#172, D-550): age alone counts only for
 * a lock with no holder written, whose maker died between its `mkdir` and its write.
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
 * moment between another process's `mkdir` and its write, unless it is older than `staleAfter`; one whose holder still
 * runs is held, whatever its age.
 *
 * @param dir        The lock's directory.
 * @param staleAfter How old a lock may be before it is taken, in milliseconds.
 * @param now        The time it is judged at: the same reading the caller gives up by (#155).
 */
function abandoned(
	dir: string,
	staleAfter: number,
	now: number
): string | undefined {
	let age: number;
	try {
		age = now - statSync( dir ).mtimeMs;
	} catch {
		return undefined;
	}
	const seen = holder( dir );
	const pid = Number( seen?.split( ' ' )[ 0 ] );
	if ( pid > 0 ) {
		return alive( pid ) ? undefined : ( seen ?? '' );
	}
	return age > staleAfter ? ( seen ?? '' ) : undefined;
}

/**
 * How old a clearer's own lock may be before another takes it over: clearing is two calls to the file system.
 */
const CLEARING_STALE = 10_000;

/**
 * Removes a lock judged abandoned, if it still is the one judged. Clearers take a lock of their own first
 * (`<dir>.breaking`), so between the look and the removal nothing but the lock's holder can change it, and a holder
 * judged abandoned no longer runs. A lock let go and taken by another since it was judged has another holder, and is
 * left where it is (#172): set aside and put back, it was missing for a moment, and a third process could take it.
 * Another clearer at work is left to it; one that died at it, its lock older than CLEARING_STALE, is taken over.
 *
 * @param dir        The lock's directory.
 * @param seen       The holder the lock was judged abandoned with: '' for one with no holder written.
 * @param staleAfter How old a lock with no holder written may be before it is taken, in milliseconds. A fresh lock,
 *                   between its maker's `mkdir` and its write, has no holder either: it is judged again here.
 */
export function clear( dir: string, seen: string, staleAfter = 0 ): void {
	const clearing = `${ dir }.breaking`;
	try {
		mkdirSync( clearing );
	} catch {
		try {
			if ( Date.now() - statSync( clearing ).mtimeMs > CLEARING_STALE ) {
				rmSync( clearing, { recursive: true, force: true } );
			}
		} catch {}
		return;
	}
	try {
		if ( abandoned( dir, staleAfter, Date.now() ) === seen ) {
			rmSync( dir, { recursive: true, force: true } );
		}
	} finally {
		rmSync( clearing, { recursive: true, force: true } );
	}
}

/**
 * Runs a function while this process holds the lock, and lets it go after, whether the function returned or threw.
 *
 * @param dir        The lock's directory; its parent is made if it is missing.
 * @param run        What to run while holding it.
 * @param staleAfter How old a lock with no holder written may be before it is taken, in milliseconds.
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
		// One reading of the clock for both: judged at one millisecond and given up at the next, a lock made in the
		// millisecond the wait began was never old enough, yet the wait was over (#155, D-511).
		const now = Date.now();
		const seen = abandoned( dir, staleAfter, now );
		if ( undefined !== seen ) {
			clear( dir, seen, staleAfter );
			continue;
		}
		if ( now > giveUp ) {
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
