<?php
/**
 * The release manifest over https.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Updates;

use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Fetches the manifest with WordPress's HTTP API, at most once every 12 hours (once an hour after a failure), and
 * sends nothing about the site beyond what any https request carries: the user agent names only the plugin
 * (docs/PRIVACY.md). The one outbound request the plugin makes (CLAUDE.md; tools/gates.sh, gate 5).
 */
final class HttpManifestSource implements ManifestSource {

	private const TTL     = 12 * HOUR_IN_SECONDS;
	private const RETRY   = HOUR_IN_SECONDS;
	private const TIMEOUT = 10;

	/**
	 * Constructor.
	 *
	 * @param string $url The manifest's https address.
	 */
	public function __construct( private string $url ) {}

	/**
	 * The latest release, cached for the network.
	 */
	public function latest(): ?Manifest {
		$cached = get_site_transient( Options::UPDATE_MANIFEST );
		if ( is_array( $cached ) && array_key_exists( 'data', $cached ) ) {
			return Manifest::fromArray( $cached['data'] );
		}
		$data = $this->fetch();
		set_site_transient( Options::UPDATE_MANIFEST, array( 'data' => $data ), null !== Manifest::fromArray( $data ) ? self::TTL : self::RETRY );
		return Manifest::fromArray( $data );
	}

	/**
	 * The latest release read again now; the answer replaces the cached one.
	 */
	public function fresh(): ?Manifest {
		self::flush();
		return $this->latest();
	}

	/**
	 * Forget the cached manifest (Dashboard > Updates > Check again).
	 */
	public static function flush(): void {
		delete_site_transient( Options::UPDATE_MANIFEST );
	}

	/**
	 * The decoded manifest, or null.
	 */
	private function fetch(): mixed {
		// Only over https: a manifest read in the clear could be rewritten on the way to name another release.
		if ( 'https' !== wp_parse_url( $this->url, PHP_URL_SCHEME ) ) {
			return null;
		}
		$response = wp_safe_remote_get(
			$this->url,
			array(
				'timeout'             => self::TIMEOUT,
				// The address answers itself. A redirect could lead off https, and a manifest is a few hundred bytes.
				'redirection'         => 0,
				'limit_response_size' => 65536,
				// WordPress's default user agent names the site; this one names only the plugin.
				'user-agent'          => 'Creative Skateboard Builder/' . CBUILD_VERSION,
				'headers'             => array( 'Accept' => 'application/json' ),
			)
		);
		if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
			return null;
		}
		return json_decode( wp_remote_retrieve_body( $response ), true );
	}
}
