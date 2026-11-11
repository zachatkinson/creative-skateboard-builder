<?php
/**
 * The ability that checks a build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Abilities;

use WP_Error;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `creative-skateboard-builder/check-build`: whether a build of a kit may go to the cart, and what keeps it from it.
 * The state route's `ready`, `refused` and `problems`, from the route's own service.
 *
 * @since 0.4.0
 */
final class CheckBuildAbility extends BuildAbility {

	/**
	 * The ability's name.
	 *
	 * @since 0.4.0
	 */
	public const NAME = 'creative-skateboard-builder/check-build';

	/**
	 * Register the ability.
	 *
	 * @since 0.4.0
	 */
	public function ability(): void {
		wp_register_ability(
			'creative-skateboard-builder/check-build',
			array(
				'label'         => __( 'Check a build', 'creative-skateboard-builder' ),
				'description'   => __( 'Says whether a build of a kit may go to the cart: every required slot filled, each part one of its slot\'s, in stock, and fitting the others. What is wrong is listed with its code and a sentence. A slot with no pick is checked with the part the builder would start it on.', 'creative-skateboard-builder' ),
				'output_schema' => self::outputSchema(),
			) + $this->common()
		);
	}

	/**
	 * What the ability answers with.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	public static function outputSchema(): array {
		$text     = array( 'type' => 'string' );
		$problems = array(
			'type'  => 'array',
			'items' => array(
				'type'                 => 'object',
				'properties'           => array(
					'code'     => array(
						'type' => 'string',
						'enum' => array( 'slot_unknown', 'slot_empty', 'part_not_in_slot', 'part_sold_out', 'part_short', 'part_blocked' ),
					),
					'slot'     => $text,
					'part'     => array( 'type' => array( 'integer', 'null' ) ),
					'message'  => $text,
					'messages' => array(
						'type'  => 'array',
						'items' => self::message(),
					),
				),
				'required'             => array( 'code', 'slot', 'part', 'message', 'messages' ),
				'additionalProperties' => false,
			),
		);
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'kit'      => array(
					'type'    => 'integer',
					'minimum' => 1,
				),
				'ready'    => array(
					'type'        => 'boolean',
					'description' => __( 'Whether the build may go to the cart.', 'creative-skateboard-builder' ),
				),
				'refused'  => array( 'description' => __( 'The picks given that were not taken.', 'creative-skateboard-builder' ) ) + $problems,
				'problems' => array( 'description' => __( 'What keeps the build from the cart. Empty when it is ready.', 'creative-skateboard-builder' ) ) + $problems,
			),
			'required'             => array( 'kit', 'ready', 'refused', 'problems' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Check the build.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $input The kit and its picks.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run( mixed $input = null ): array|WP_Error {
		$answer = $this->answer( $input );
		return $answer instanceof WP_Error ? $answer : array_intersect_key( $answer, array_flip( array( 'kit', 'ready', 'refused', 'problems' ) ) );
	}
}
