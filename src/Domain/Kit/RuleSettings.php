<?php
/**
 * Which rules a kit runs, and their settings.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Kit;

use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Unit;
use CreativeBranding\SkateboardBuilder\Domain\Rule\RuleId;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;

/**
 * A kit's rule switches and tolerances (spec: Data model, `_cbuild_rules`; Compatibility rules). They apply when fit
 * is checked, never when parts are indexed (D-026).
 */
final readonly class RuleSettings {

	/**
	 * The most rows a riser table holds.
	 */
	public const int MAX_RISER_STEPS = 6;

	/**
	 * The document a new kit starts from: every rule on, R1's tolerances, R3's class limits and riser table as the
	 * spec seeds them, and R6's length check on.
	 */
	public const array DEFAULTS = array(
		'schema_version' => 1,
		'r1'             => array(
			'enabled'         => true,
			'range_tolerance' => '0',
			'axle_fit'        => '0.25',
			'axle_warn'       => '0.5',
		),
		'r2'             => array( 'enabled' => true ),
		'r3'             => array(
			'enabled' => true,
			'limits'  => array(
				'low'  => '53',
				'mid'  => '56',
				'high' => '56',
			),
			'risers'  => array(
				array(
					'over'  => '2',
					'riser' => '0.25',
				),
				array(
					'over'  => '6',
					'riser' => '0.5',
				),
			),
		),
		'r4'             => array( 'enabled' => true ),
		'r5'             => array( 'enabled' => true ),
		'r6'             => array(
			'enabled'      => true,
			'check_length' => true,
		),
	);

	/**
	 * Constructor.
	 *
	 * @param array<string, bool>    $enabled        Whether each rule runs, by the rule's key.
	 * @param Measure                $rangeTolerance R1: how far outside a maker's deck range still fits, in inches.
	 * @param Measure                $axleFit        R1: for a truck with no range, how far the axle may be from the deck and fit.
	 * @param Measure                $axleWarn       R1: and how far it may be and warn; beyond it blocks.
	 * @param array<string, Measure> $classLimits    R3: the largest wheel without risers by height class, in millimetres.
	 * @param list<RiserStep>        $riserSteps     R3: the riser table, shortest reach first.
	 * @param bool                   $checkLength    R6: whether the grip must be as long as the deck.
	 */
	private function __construct(
		private array $enabled,
		public Measure $rangeTolerance,
		public Measure $axleFit,
		public Measure $axleWarn,
		private array $classLimits,
		public array $riserSteps,
		public bool $checkLength
	) {}

	/**
	 * The settings a new kit starts from.
	 *
	 * @since 0.2.0
	 */
	public static function defaults(): self {
		return self::fromDocument( self::DEFAULTS );
	}

	/**
	 * From the rules document.
	 *
	 * @since 0.2.0
	 *
	 * @param mixed $document The decoded `_cbuild_rules` document.
	 *
	 * @throws InvalidDocument When the document or a setting is not valid.
	 */
	public static function fromDocument( mixed $document ): self {
		$root    = Fields::document( $document );
		$enabled = array();
		foreach ( RuleId::cases() as $rule ) {
			$enabled[ $rule->value ] = $root->object( $rule->value )->flag( 'enabled' );
		}
		$r1       = $root->object( RuleId::R1->value );
		$axleFit  = $r1->measure( 'axle_fit', Unit::Inch );
		$axleWarn = $r1->measure( 'axle_warn', Unit::Inch );
		if ( $axleWarn->lessThan( $axleFit ) ) {
			$refused = InvalidDocument::because( DocumentError::OutOfOrder, $r1->path( 'axle_warn' ) );
			throw $refused;
		}
		$r3     = $root->object( RuleId::R3->value );
		$limits = array();
		foreach ( SpecKey::CLASSES as $class ) {
			$limits[ $class ] = $r3->object( 'limits' )->measure( $class, Unit::Millimetre );
		}
		$steps = array();
		$reach = null;
		foreach ( $r3->rowsFilled( 'risers', self::MAX_RISER_STEPS ) as $row ) {
			$over = $row->measure( 'over', Unit::Millimetre );
			if ( null !== $reach && $over->atMost( $reach ) ) {
				$refused = InvalidDocument::because( DocumentError::OutOfOrder, $row->path( 'over' ) );
				throw $refused;
			}
			$reach   = $over;
			$steps[] = new RiserStep( $over, $row->measure( 'riser', Unit::Inch ) );
		}
		return new self(
			$enabled,
			$r1->measure( 'range_tolerance', Unit::Inch ),
			$axleFit,
			$axleWarn,
			$limits,
			$steps,
			$root->object( RuleId::R6->value )->flag( 'check_length' )
		);
	}

	/**
	 * Whether the kit runs a rule.
	 *
	 * @since 0.2.0
	 *
	 * @param RuleId $rule The rule.
	 */
	public function enabled( RuleId $rule ): bool {
		return $this->enabled[ $rule->value ];
	}

	/**
	 * R3: the largest wheel a truck of a height class takes without risers. A class nobody knows counts as high, as a
	 * truck with no class does.
	 *
	 * @since 0.2.0
	 *
	 * @param string|null $heightClass The truck's `height_class`, or null when it has none.
	 */
	public function classLimit( ?string $heightClass ): Measure {
		return $this->classLimits[ $heightClass ?? 'high' ] ?? $this->classLimits['high'];
	}
}
