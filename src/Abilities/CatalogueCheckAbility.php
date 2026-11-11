<?php
/**
 * The Catalogue check as an ability.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Abilities;

use Closure;
use CreativeBranding\SkateboardBuilder\Admin\Sentences;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * `creative-skateboard-builder/catalogue-check` (WordPress's Abilities API): the check of WooCommerce > Builder for
 * the shop's own tools and assistants, under the screen's capability, with the screen's service and sentences. It
 * reads and changes nothing.
 *
 * @since 0.3.0
 */
final class CatalogueCheckAbility implements Hookable {

	/**
	 * The category the plugin's abilities sit in, and the most findings one answer lists.
	 */
	public const CATEGORY = 'creative-skateboard-builder';
	public const NAME     = 'creative-skateboard-builder/catalogue-check';
	public const MOST     = 500;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): CatalogueCheck $check Builds the check, when the ability is run.
	 */
	public function __construct( private Closure $check ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		add_action( 'wp_abilities_api_categories_init', array( $this, 'category' ), 10, 0 );
		add_action( 'wp_abilities_api_init', array( $this, 'ability' ), 10, 0 );
	}

	/**
	 * Register the category.
	 *
	 * @since 0.3.0
	 */
	public function category(): void {
		wp_register_ability_category(
			self::CATEGORY,
			array(
				'label'       => __( 'Creative Skateboard Builder', 'creative-skateboard-builder' ),
				'description' => __( 'The skateboard builder: what keeps a part out of it, and what a build fits and costs.', 'creative-skateboard-builder' ),
			)
		);
	}

	/**
	 * Register the ability.
	 *
	 * @since 0.3.0
	 */
	public function ability(): void {
		wp_register_ability(
			'creative-skateboard-builder/catalogue-check',
			array(
				'label'               => __( 'Run the Catalogue check', 'creative-skateboard-builder' ),
				'description'         => __( 'Lists what keeps a part out of the builder: sources that disagree about a part, values that cannot be read, background work that failed and, for each kit, parts that lack a spec, presets that no longer fit and slots with nothing in stock. It reads every part and changes nothing.', 'creative-skateboard-builder' ),
				'category'            => self::CATEGORY,
				'permission_callback' => array( self::class, 'allowed' ),
				'execute_callback'    => array( $this, 'run' ),
				'output_schema'       => self::outputSchema(),
				'meta'                => array(
					'annotations'  => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
					'show_in_rest' => true,
				),
			)
		);
	}

	/**
	 * What the ability answers with, its descriptions in the language of whoever asks.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	public static function outputSchema(): array {
		$text = array( 'type' => 'string' );
		$id   = array(
			'type'    => 'integer',
			'minimum' => 0,
		);
		return array(
			'type'                 => 'object',
			'properties'           => array(
				'count'    => array(
					'type'        => 'integer',
					'minimum'     => 0,
					'description' => __( 'How many things the check found.', 'creative-skateboard-builder' ),
				),
				'findings' => array(
					'type'        => 'array',
					'maxItems'    => self::MOST,
					'description' => __( 'The findings, the first 500 where there are more.', 'creative-skateboard-builder' ),
					'items'       => array(
						'type'                 => 'object',
						'properties'           => array(
							'code'    => $text,
							'part'    => $id,
							'kit'     => $id,
							'slot'    => $text,
							'spec'    => $text,
							'values'  => array(
								'type'                 => 'object',
								'additionalProperties' => $text,
							),
							'message' => $text,
						),
						'required'             => array( 'code', 'part', 'kit', 'slot', 'spec', 'values', 'message' ),
						'additionalProperties' => false,
					),
				),
			),
			'required'             => array( 'count', 'findings' ),
			'additionalProperties' => false,
		);
	}

	/**
	 * Whether the current user may run the check: who may open its screen.
	 *
	 * @since 0.3.0
	 */
	public static function allowed(): bool {
		return current_user_can( 'manage_woocommerce' );
	}

	/**
	 * Run the check.
	 *
	 * @since 0.3.0
	 *
	 * @return array{count: int, findings: list<array{code: string, part: int, kit: int, slot: string, spec: string, values: object, message: string}>}
	 */
	public function run(): array {
		$found    = ( $this->check )()->run();
		$findings = array();
		foreach ( array_slice( $found, 0, self::MOST ) as $finding ) {
			$findings[] = array(
				'code'    => $finding->code,
				'part'    => $finding->part,
				'kit'     => $finding->kit,
				'slot'    => $finding->slot,
				'spec'    => $finding->spec,
				// An object though empty, so it is `{}` as JSON and never `[]`.
				'values'  => (object) $finding->values,
				'message' => Sentences::finding( $finding ),
			);
		}
		return array(
			'count'    => count( $found ),
			'findings' => $findings,
		);
	}
}
