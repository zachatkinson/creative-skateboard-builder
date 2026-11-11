<?php
/**
 * What the two build abilities share.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Abilities;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An ability that answers about a build of a kit: it takes a kit and its picks, asks the service the state route
 * asks, and gives a part of the same answer, written by the same class. So an ability and the route cannot
 * disagree, which `BuildAbilitiesTest` holds them to.
 *
 * Each is read-only and open to whoever may run an ability, as the route is open to whoever asks: it says of a kit
 * only what the kit's page says, and a kit a visitor may not see is answered as one that is not there.
 *
 * @since 0.4.0
 */
abstract class BuildAbility implements Hookable {

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param Closure(): Kits        $kits   The shop's kits.
	 * @param Closure(): BuildState  $state  Works a build's state out.
	 * @param Closure(): StateAnswer $writer Writes it down.
	 */
	final public function __construct( private Closure $kits, private Closure $state, private Closure $writer ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.4.0
	 */
	final public function register(): void {
		add_action( 'wp_abilities_api_init', array( $this, 'ability' ), 10, 0 );
	}

	/**
	 * Register the ability.
	 *
	 * @since 0.4.0
	 */
	abstract public function ability(): void;

	/**
	 * What every ability of the kind is registered with, beside its name, its words and its output.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	final protected function common(): array {
		return array(
			'category'            => CatalogueCheckAbility::CATEGORY,
			// Whoever is signed in. WordPress's run route asks nothing but this (WordPress 7.1.4,
			// wp-includes/rest-api/endpoints/class-wp-rest-abilities-v1-run-controller.php:143), and the answer
			// is the state route's, which is counted for each address: open, this would be that route uncounted.
			'permission_callback' => 'is_user_logged_in',
			'execute_callback'    => array( $this, 'run' ),
			'input_schema'        => self::inputSchema(),
			'meta'                => array(
				'annotations'  => array(
					'readonly'    => true,
					'destructive' => false,
					'idempotent'  => true,
				),
				'show_in_rest' => true,
			),
		);
	}

	/**
	 * What an ability takes: the kit, its picks, and whether the shop assembles the build.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	final public static function inputSchema(): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'kit'      => array(
					'type'        => 'integer',
					'minimum'     => 1,
					'description' => __( 'The kit product\'s ID.', 'creative-skateboard-builder' ),
				),
				'picks'    => array(
					'type'                 => 'object',
					'description'          => __( 'The picks: each of the kit\'s slot keys to a part\'s ID.', 'creative-skateboard-builder' ),
					'maxProperties'        => Slots::MAX,
					'additionalProperties' => array(
						'type'    => 'integer',
						'minimum' => 1,
					),
				),
				'assembly' => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the shop assembles the build. Left out, the kit\'s own default.', 'creative-skateboard-builder' ),
				),
			),
			'required'             => array( 'kit' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * The state route's answer for what was asked, or why there is none.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $input What the ability was given.
	 * @return array<string, mixed>|WP_Error
	 */
	final protected function answer( mixed $input ): array|WP_Error {
		$input   = Cast::map( $input );
		$id      = Cast::int( $input['kit'] ?? 0 );
		$missing = new WP_Error( 'cbuild_kit_not_found', __( 'There is no such kit.', 'creative-skateboard-builder' ), array( 'status' => 404 ) );
		if ( ! ( $this->kits )()->isPublic( $id ) ) {
			return $missing;
		}
		try {
			$state = ( $this->state )()->of( $id, Cast::map( $input['picks'] ?? array() ), null, null, isset( $input['assembly'] ) ? Cast::bool( $input['assembly'] ) : null );
		} catch ( InvalidMoney ) {
			return new WP_Error( 'cbuild_no_price', __( 'This build cannot be priced in the shop\'s currency settings.', 'creative-skateboard-builder' ), array( 'status' => 500 ) );
		}
		return null === $state ? $missing : ( $this->writer )()->of( $state );
	}

	/**
	 * Run the ability.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $input What the ability was given.
	 * @return array<string, mixed>|WP_Error
	 */
	abstract public function run( mixed $input = null ): array|WP_Error;

	/**
	 * A rule's finding, as the state route's answer carries one.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	final protected static function message(): array {
		$text = array( 'type' => 'string' );
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'rule'     => $text,
				'code'     => $text,
				'severity' => array(
					'type' => 'string',
					'enum' => array( 'fits', 'warn', 'block' ),
				),
				'text'     => $text,
			),
			'required'             => array( 'rule', 'code', 'severity', 'text' ),
			'additionalProperties' => false,
		);
	}
}
