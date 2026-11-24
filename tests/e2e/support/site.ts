/**
 * The browser site's address: WP_BASE_URL when it is set, else wp-env's browser site on CBUILD_PORT, 8898 by default.
 * A second checkout runs its own pair of sites on other ports through `bash tools/lane.sh 2 …`, which sets CBUILD_PORT
 * (tools/ports.sh), so its browser tests never reach the first checkout's site.
 */
export const SITE =
	process.env.WP_BASE_URL ||
	`http://localhost:${ process.env.CBUILD_PORT || '8898' }`;
