<?php
/**
 * The ability that prices a build.
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
 * `creative-skateboard-builder/price-build`: what a build of a kit costs. The state route's `total`, from the
 * route's own service: a line per part, the build discount, the assembly fee and the sum.
 *
 * @since 0.4.0
 */
final class PriceBuildAbility extends BuildAbility {

	/**
	 * The ability's name.
	 *
	 * @since 0.4.0
	 */
	public const NAME = 'creative-skateboard-builder/price-build';

	/**
	 * Register the ability.
	 *
	 * @since 0.4.0
	 */
	public function ability(): void {
		wp_register_ability(
			'creative-skateboard-builder/price-build',
			array(
				'label'         => __( 'Price a build', 'creative-skateboard-builder' ),
				'description'   => __( 'Gives what a build of a kit costs: a line for each part with its quantity and price, what the build discount takes off, the assembly fee and the total. Each amount comes in whole minor units and as the shop writes its prices. A slot with no pick is priced with the part the builder would start it on.', 'creative-skateboard-builder' ),
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
		$text  = array( 'type' => 'string' );
		$money = array(
			'type'                 => 'object',
			'properties'           => array(
				'amount' => array( 'type' => 'integer' ),
				'text'   => $text,
			),
			'required'             => array( 'amount', 'text' ),
			'additionalProperties' => false,
		);
		$id    = array(
			'type'    => 'integer',
			'minimum' => 1,
		);
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'kit'   => $id,
				'total' => array(
					'type'                 => 'object',
					'description'          => __( 'What the build costs.', 'creative-skateboard-builder' ),
					'properties'           => array(
						'lines'    => array(
							'type'        => 'array',
							'description' => __( 'A line for each part, in step order.', 'creative-skateboard-builder' ),
							'items'       => array(
								'type'                 => 'object',
								'properties'           => array(
									'slot'     => $text,
									'part'     => $id,
									'name'     => $text,
									'quantity' => $id,
									'price'    => $money,
									'charged'  => $money,
									'total'    => $money,
								),
								'required'             => array( 'slot', 'part', 'name', 'quantity', 'price', 'charged', 'total' ),
								'additionalProperties' => false,
							),
						),
						'parts'    => array( 'description' => __( 'The parts together, after the build discount.', 'creative-skateboard-builder' ) ) + $money,
						'saved'    => array( 'description' => __( 'What the build discount takes off.', 'creative-skateboard-builder' ) ) + $money,
						'assembly' => array(
							'type'        => 'boolean',
							'description' => __( 'Whether the shop assembles the build.', 'creative-skateboard-builder' ),
						),
						'fee'      => array( 'description' => __( 'The assembly fee.', 'creative-skateboard-builder' ) ) + $money,
						'total'    => array( 'description' => __( 'The parts and the fee.', 'creative-skateboard-builder' ) ) + $money,
					),
					'required'             => array( 'lines', 'parts', 'saved', 'assembly', 'fee', 'total' ),
					'additionalProperties' => false,
				),
			),
			'required'             => array( 'kit', 'total' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Price the build.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $input The kit and its picks.
	 * @return array<string, mixed>|WP_Error
	 */
	public function run( mixed $input = null ): array|WP_Error {
		$answer = $this->answer( $input );
		return $answer instanceof WP_Error ? $answer : array_intersect_key( $answer, array_flip( array( 'kit', 'total' ) ) );
	}
}
