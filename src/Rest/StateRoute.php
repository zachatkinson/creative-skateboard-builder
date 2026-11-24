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
use CreativeBranding\SkateboardBuilder\Domain\Look\Chips;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Look\ShelfAsk;
use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuilderAddress;
use CreativeBranding\SkateboardBuilder\Front\BuilderPlace;
use CreativeBranding\SkateboardBuilder\Front\BuilderUrl;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Cart;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\RateLimit;
use CreativeBranding\SkateboardBuilder\Service\ShelfQuery;
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
	 * A deck width as the shelf names it.
	 */
	private const string WIDTH = '^[0-9]{1,3}(\\.[0-9]{1,4})?(in|mm)(?!\n)$';

	/**
	 * A chip's value: a slug, or a size as the shelf names it.
	 */
	private const string CHIP = '^[a-z0-9_%.-]{1,200}(?!\n)$';


	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Kits                               $kits   The shop's kits.
	 * @param Closure(): BuildState              $state  Works a build's state out.
	 * @param Closure(): StateAnswer             $writer Writes it down.
	 * @param Closure(): RateLimit               $limit  The rate limit.
	 * @param Closure(): Cart                    $cart   The cart, for the shopper it knows.
	 * @param Closure(): Builder                 $builder What the builder shows, for a build link.
	 * @param BuilderPlace                       $place   Which page holds which kit's builder.
	 * @param (Closure(): ComposedPictures)|null $pictures Asks for a review's picture, where its link is offered; none in tests that leave it out.
	 * @param Clock|null                         $clock    Times an answer for a shop manager's Server-Timing header (1.1.0); none in tests that leave it out.
	 */
	public function __construct( private Kits $kits, private Closure $state, private Closure $writer, private Closure $limit, private Closure $cart, private Closure $builder, private BuilderPlace $place, private ?Closure $pictures = null, private ?Clock $clock = null ) {}

	/**
	 * Where each kit's builder is, as its Build tab chose (1.2.0, #113): none in a copy an earlier release's container
	 * built, which asks `BuilderUrl::of()` as 1.1.0 did (D-208).
	 *
	 * @var BuilderAddress|null
	 */
	private ?BuilderAddress $address = null;

	/**
	 * The same, sending shoppers to the Builder page each kit's Build tab chose (#113, D-348). Only this release's
	 * container calls it (D-208).
	 *
	 * @since 1.2.0
	 *
	 * @param BuilderAddress $address Where each kit's builder is.
	 */
	public function withAddress( BuilderAddress $address ): self {
		$copy          = clone $this;
		$copy->address = $address;
		return $copy;
	}

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
			'cbuild'   => array(
				'description'   => __( 'A build link, as a page\'s address holds it. It takes the place of picks, previous, step and assembly, and the answer gains the view the page would show.', 'creative-skateboard-builder' ),
				'type'          => 'object',
				'maxProperties' => 64,
			),
			'page'     => array(
				'description' => __( 'The ID of the post the builder is on, for the view\'s links. A post that holds no builder for the kit is not used.', 'creative-skateboard-builder' ),
				'type'        => 'integer',
				'minimum'     => 1,
			),
			'width'    => array(
				'description' => __( 'The deck width the shelf lists, as its widths name one: 8.25in.', 'creative-skateboard-builder' ),
				'type'        => 'string',
				'pattern'     => self::WIDTH,
			),
			'chips'    => array(
				'description'          => __( 'The chips chosen: each group\'s name to the values chosen in it.', 'creative-skateboard-builder' ),
				'type'                 => 'object',
				'maxProperties'        => Chips::MOST,
				'patternProperties'    => array(
					self::KEY => array(
						'type'     => 'array',
						'maxItems' => 20,
						'items'    => array(
							'type'    => 'string',
							'pattern' => self::CHIP,
						),
					),
				),
				'additionalProperties' => false,
			),
			'q'        => array(
				'description' => __( 'What the shopper searched the step for.', 'creative-skateboard-builder' ),
				'type'        => 'string',
				'maxLength'   => ShelfQuery::QUERY,
			),
			'sort'     => array(
				'description' => __( 'The order the shelf lists the step in.', 'creative-skateboard-builder' ),
				'type'        => 'string',
				'enum'        => ShelfAsk::SORTS,
			),
			'from'     => array(
				'description' => __( 'Where the shelf\'s page starts, as the last answer\'s more said.', 'creative-skateboard-builder' ),
				'type'        => 'integer',
				'minimum'     => 0,
				'maximum'     => Shelf::MOST,
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
		$start   = $this->clock?->now();
		$queries = get_num_queries();
		$answer  = $this->limited( $request );
		// The route's own time and queries, to a shop manager alone, so the priming work can be measured on a real
		// host (#90): `cbuild-route` in milliseconds, from the callback's start, and `cbuild-queries`.
		if ( null !== $this->clock && null !== $start && $answer instanceof WP_REST_Response && current_user_can( 'manage_woocommerce' ) ) {
			$end  = $this->clock->now();
			$took = ( (int) $end->format( 'U' ) - (int) $start->format( 'U' ) ) * 1000 + ( (int) $end->format( 'u' ) - (int) $start->format( 'u' ) ) / 1000;
			$answer->header( 'Server-Timing', sprintf( 'cbuild-route;dur=%.1F, cbuild-queries;desc="%d"', $took, get_num_queries() - $queries ) );
		}
		return $answer;
	}

	/**
	 * The route's answer, or its refusal past the rate limit, before it is timed.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function limited( WP_REST_Request $request ) {
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
					/* translators: %s: how many seconds until the shopper can try again. */
					'message' => sprintf( _n( 'Too many requests. Try again in %s second.', 'Too many requests. Try again in %s seconds.', $wait, 'creative-skateboard-builder' ), number_format_i18n( $wait ) ),
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
		// The prices are for the shopper the cart will charge, where tax goes by where they are (issue #27).
		( $this->cart )()->shopper();
		if ( null !== $request['cbuild'] ) {
			return $this->link( $id, $request );
		}
		$picks    = Cast::map( $request['picks'] );
		$previous = null === $request['previous'] ? null : Cast::map( $request['previous'] );
		$step     = null === $request['step'] ? null : Cast::str( $request['step'] );
		// A slot the kit does not have is refused before anything is worked out: the kit alone is read for it. A kit
		// that does not read is left to the state service, which logs it.
		$kit = $this->kits->kit( $id );
		foreach ( array_merge( array_keys( $picks ), array_keys( $previous ?? array() ), null === $step ? array() : array( $step ) ) as $key ) {
			if ( null !== $kit && null === $kit->slots->get( (string) $key ) ) {
				return new WP_Error(
					'rest_invalid_param',
					Words::problem( Problem::SlotUnknown, (string) $key ),
					array( 'status' => 400 )
				);
			}
		}
		try {
			$state = ( $this->state )()->of( $id, $picks, $previous, $step, null === $request['assembly'] ? null : Cast::bool( $request['assembly'] ) );
		} catch ( InvalidMoney ) {
			return new WP_Error( 'cbuild_no_price', __( 'This build cannot be priced in the shop\'s currency settings.', 'creative-skateboard-builder' ), array( 'status' => 500 ) );
		}
		if ( null === $state ) {
			return $missing;
		}
		return new WP_REST_Response( ( $this->writer )()->of( $state, null, self::shelf( $request ) ?? new ShelfQuery() ) );
	}

	/**
	 * The answer to a build link: what the page would show for it, the view among it (D-148).
	 *
	 * @param int                                   $id      The kit, which a visitor may see.
	 * @param WP_REST_Request<array<string, mixed>> $request The request.
	 * @return WP_REST_Response|WP_Error
	 */
	private function link( int $id, WP_REST_Request $request ) {
		foreach ( array( 'picks', 'previous', 'step', 'assembly' ) as $argument ) {
			if ( null !== $request[ $argument ] ) {
				return new WP_Error(
					'rest_invalid_param',
					/* translators: %s: the argument's name, such as picks. */
					sprintf( __( 'A build link takes the place of %s: send one or the other.', 'creative-skateboard-builder' ), $argument ),
					array( 'status' => 400 )
				);
			}
		}
		try {
			$link  = BuildLink::read( $request['cbuild'] );
			$shelf = self::shelf( $request );
			$shown = ( $this->builder )()->show( $id, null === $shelf ? $link : $link->shelved( $shelf ), $this->base( $id, Cast::int( $request['page'] ) ) );
		} catch ( InvalidMoney ) {
			return new WP_Error( 'cbuild_no_price', __( 'This build cannot be priced in the shop\'s currency settings.', 'creative-skateboard-builder' ), array( 'status' => 500 ) );
		}
		if ( null === $shown ) {
			return new WP_Error( 'cbuild_kit_not_found', __( 'There is no such kit.', 'creative-skateboard-builder' ), array( 'status' => 404 ) );
		}
		if ( null !== $this->pictures ) {
			( $this->pictures )()->offered( $shown['state'] );
		}
		return new WP_REST_Response( $shown['answer'] + array( 'view' => $shown['view'] ) );
	}

	/**
	 * What the request asks of the step's shelf, or null where it sends none of the shelf's arguments. The schema
	 * has checked each; a search is read as a link's is.
	 *
	 * @param WP_REST_Request<array<string, mixed>> $request The request.
	 */
	private static function shelf( WP_REST_Request $request ): ?ShelfQuery {
		if ( null === $request['width'] && null === $request['chips'] && null === $request['q'] && null === $request['sort'] && null === $request['from'] ) {
			return null;
		}
		$read = BuildLink::read(
			array(
				'_q'     => $request['q'],
				'_chips' => $request['chips'],
			)
		)->shelf;
		return new ShelfQuery(
			null === $request['width'] ? '' : Cast::str( $request['width'] ),
			$read->chips,
			$read->query,
			null === $request['sort'] ? ShelfAsk::SORTS[0] : Cast::str( $request['sort'] ),
			Cast::int( $request['from'] )
		);
	}

	/**
	 * The address of the page the builder is on, with no build in it: the post named, where a visitor may see it and
	 * it holds the kit's Builder block, and otherwise where the kit's builder is.
	 *
	 * @param int $kit  The kit.
	 * @param int $page The post the request names, or 0.
	 */
	private function base( int $kit, int $page ): string {
		$post = $page > 0 && $kit !== $page ? get_post( $page ) : null;
		if ( null !== $post && is_post_publicly_viewable( $post ) && ! post_password_required( $post ) && $this->place->holds( $post->ID, $kit ) ) {
			return (string) get_permalink( $post );
		}
		return $this->address?->of( $kit ) ?? BuilderUrl::of( $kit );
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
