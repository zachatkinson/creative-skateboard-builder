<?php
/**
 * The state route.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Rest;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\RateLimit;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `GET cbuild/v1/kits/<id>/state`: the state of a build of a kit, for the builder's script and for anything else
 * that asks (spec: How it fits; Security). Public and read-only. It holds no rule of its own: the service works the
 * state out and `Front\StateAnswer` writes it down.
 *
 * - **What it takes** is checked twice: WordPress holds the arguments against their schema before this class runs,
 *   and the callback then holds the slot keys against the kit's own, which no schema written ahead can know.
 * - **A kit a visitor may not see** (a draft, a private one, one behind a password, or no kit at all) is answered as
 *   one that is not there, in the same words, so the route says nothing of what the shop has not published.
 * - **The limit comes first** in the callback, so a request over it costs a count and nothing else.
 * - **The language asked for** is switched to once the limit is passed and switched back whatever follows, so a
 *   refusal is in it too. WordPress switches only to a language the site has installed.
 * - **Never kept by a cache:** every answer of the route, a refusal included, says `no-store`.
 *
 * @since 0.4.0
 */
final class StateRoute implements Hookable {

	/**
	 * The namespace.
	 */
	private const NAMESPACE = 'cbuild/v1';

	/**
	 * What a slot's key is made of: `schema/kit-slots.json`'s own pattern.
	 */
	private const string KEY = '^[a-z][a-z0-9_]{0,31}(?!\n)$';

	/**
	 * What a locale's name is made of: `fr`, `fr_CA`, `de_DE_formal`.
	 */
	private const string LOCALE = '^[a-z]{2,3}(_[A-Z]{2})?(_[a-z0-9]+)?(?!\n)$';

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Kits                   $kits   The shop's kits.
	 * @param Closure(): BuildState  $state  Works a build's state out.
	 * @param Closure(): StateAnswer $writer Writes it down.
	 * @param Closure(): RateLimit   $limit  The rate limit.
	 */
	public function __construct( private Kits $kits, private Closure $state, private Closure $writer, private Closure $limit ) {}

	/**
	 * Add the hooks.
	 *
	 * @since 0.4.0
	 */
	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'headers' ), 10, 3 );
	}

	/**
	 * Register the route.
	 *
	 * @since 0.4.0
	 */
	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/kits/(?P<id>[1-9][0-9]{0,17})/state',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'state' ),
				// Public on purpose: it shows what the kit's page shows, to whoever can see that page.
				'permission_callback' => '__return_true',
				'args'                => self::arguments(),
			)
		);
	}

	/**
	 * The route's arguments, each with its schema. Public API: `composer api:check` holds their names and schemas.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public static function arguments(): array {
		$picks = array(
			'type'                 => 'object',
			'maxProperties'        => Slots::MAX,
			'patternProperties'    => array(
				self::KEY => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
			),
			'additionalProperties' => false,
		);
		return array(
			'id'       => array(
				'description' => __( 'The kit product\'s ID.', 'creative-skateboard-builder' ),
				'type'        => 'integer',
				'minimum'     => 1,
				'required'    => true,
			),
			'picks'    => array( 'description' => __( 'The picks: each of the kit\'s slot keys to a part\'s ID.', 'creative-skateboard-builder' ) ) + $picks,
			'previous' => array( 'description' => __( 'The picks as they were before the last change, for the change list.', 'creative-skateboard-builder' ) ) + $picks,
			'step'     => array(
				'description' => __( 'The key of the slot whose options are listed.', 'creative-skateboard-builder' ),
				'type'        => 'string',
				'pattern'     => self::KEY,
			),
			'assembly' => array(
				'description' => __( 'Whether the shop assembles the build. Left out, the kit\'s own default.', 'creative-skateboard-builder' ),
				'type'        => 'boolean',
			),
			'locale'   => array(
				'description' => __( 'The language to answer in, when the site has it installed.', 'creative-skateboard-builder' ),
				'type'        => 'string',
				'pattern'     => self::LOCALE,
			),
		);
	}

	/**
	 * Answer a request.
	 *
	 * @since 0.4.0
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	public function state( WP_REST_Request $request ) {
		$limit  = ( $this->limit )();
		$header = $limit->header();
		// The connection's own address, which counts requests and is never kept (Service\RateLimit). The route's answers
		// say no-store, so no cache stands between the address and the count.
		// phpcs:ignore WordPressVIPMinimum.Variables.ServerVariables.UserControlledHeaders, WordPressVIPMinimum.Variables.RestrictedVariables.cache_constraints___SERVER__REMOTE_ADDR__ -- read as an address or not at all, and only to count.
		$remote = filter_var( wp_unslash( $_SERVER['REMOTE_ADDR'] ?? '' ), FILTER_VALIDATE_IP );
		$wait   = $limit->wait(
			false === $remote ? '' : $remote,
			'' === $header ? '' : sanitize_text_field( Cast::str( $request->get_header( $header ) ) )
		);
		if ( $wait > 0 ) {
			$refusal = new WP_REST_Response(
				array(
					'code'    => 'cbuild_rate_limited',
					'message' => __( 'Too many requests. Try again in a minute.', 'creative-skateboard-builder' ),
					'data'    => array( 'status' => 429 ),
				),
				429
			);
			$refusal->header( 'Retry-After', (string) $wait );
			return $refusal;
		}

		// Everything said from here on is in the language asked for, a refusal included.
		$switched = null !== $request['locale'] && switch_to_locale( Cast::str( $request['locale'] ) );
		try {
			return $this->answer( $request );
		} finally {
			if ( $switched ) {
				restore_previous_locale();
			}
		}
	}

	/**
	 * The answer to a request within the limit.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function answer( WP_REST_Request $request ) {
		$id      = Cast::int( $request['id'] );
		$missing = new WP_Error( 'cbuild_kit_not_found', __( 'There is no such kit.', 'creative-skateboard-builder' ), array( 'status' => 404 ) );
		if ( ! $this->kits->isPublic( $id ) ) {
			return $missing;
		}
		$picks    = Cast::map( $request['picks'] );
		$previous = null === $request['previous'] ? null : Cast::map( $request['previous'] );
		$step     = null === $request['step'] ? null : Cast::str( $request['step'] );
		try {
			$state = ( $this->state )()->of( $id, $picks, $previous, $step, null === $request['assembly'] ? null : Cast::bool( $request['assembly'] ) );
		} catch ( InvalidMoney ) {
			return new WP_Error( 'cbuild_no_price', __( 'This build cannot be priced in the shop\'s currency settings.', 'creative-skateboard-builder' ), array( 'status' => 500 ) );
		}
		if ( null === $state ) {
			return $missing;
		}
		foreach ( array_merge( array_keys( $picks ), array_keys( $previous ?? array() ), null === $step ? array() : array( $step ) ) as $key ) {
			if ( null === $state->kit->slots->get( (string) $key ) ) {
				return new WP_Error(
					'rest_invalid_param',
					Words::problem( Problem::SlotUnknown, (string) $key ),
					array( 'status' => 400 )
				);
			}
		}
		return new WP_REST_Response( ( $this->writer )()->of( $state ) );
	}

	/**
	 * Keep every answer of the route out of caches and out of search: stock and prices move.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $response The response.
	 * @param mixed $server   The server.
	 * @param mixed $request  The request.
	 * @return mixed
	 */
	public function headers( mixed $response, mixed $server, mixed $request ): mixed {
		if ( $response instanceof WP_REST_Response && $request instanceof WP_REST_Request && 1 === preg_match( '#^/' . self::NAMESPACE . '/kits/[^/]+/state$#', $request->get_route() ) ) {
			$response->header( 'Cache-Control', 'no-store' );
			$response->header( 'X-Robots-Tag', 'noindex' );
		}
		return $response;
	}
}
