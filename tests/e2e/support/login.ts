import { readFileSync } from 'node:fs';
import path from 'node:path';
import type { Page } from '@playwright/test';

/**
 * Where the administrator's session is kept for the run: the cookies of one login, made by the global setup.
 */
export const SESSION = path.join( process.cwd(), 'tmp', 'e2e-session.json' );

/**
 * Makes the page's browser the administrator's, with the cookies of the run's one login; shared by every spec that
 * opens the admin. A test that never calls this is a visitor.
 *
 * @param page The page.
 */
export async function login( page: Page ) {
	const { cookies } = JSON.parse( readFileSync( SESSION, 'utf8' ) );
	await page.context().addCookies( cookies );
}
