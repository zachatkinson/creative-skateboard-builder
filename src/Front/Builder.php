<?php
/**
 * A request for a kit's builder, answered.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Service\StateStep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one road from an address to what the builder shows (spec: The builder; Quality bar, DRY and SOLID). The page
 * that renders the builder and the state route that re-renders it both ask here with the same address, so a step
 * reached by a form with JavaScript off and the same step reached by the script cannot differ.
 *
 * It decides which step an address lands on, and nothing of fit or price:
 *
 * - **The step asked for,** or the first with nothing picked where the address names none.
 * - **Risers only when needed** (spec: A step, P2). A risers slot the kit does not require is a step only while the
 *   build asks for risers or holds some. Asked for when it is no step, it gives way to the step after it.
 * - **No step past a slot the kit requires and the build leaves empty.** Next, or a link to a later step, stays on
 *   that slot's step and says why, which the page shows as its error summary.
 *
 * @since 0.6.0
 */
final class Builder {

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param BuildState  $builds Works a build's state out.
	 * @param StateAnswer $writer Writes it down.
	 * @param BuilderView $view   Says it as the template shows it.
	 */
	public function __construct( private BuildState $builds, private StateAnswer $writer, private BuilderView $view ) {}

	/**
	 * What the builder shows at an address, or null when the shop has no such kit.
	 *
	 * @since 0.6.0
	 *
	 * @param int       $kit  The kit product's ID. The caller has checked that a visitor may see it.
	 * @param BuildLink $link The address, as it was sent.
	 * @param string    $base The address of the page the builder is on, with no build in it.
	 * @return array{state: State, answer: array<string, mixed>, view: array<string, mixed>}|null
	 *
	 * @throws InvalidMoney When the build cannot be priced in the shop's currency settings.
	 */
	public function show( int $kit, BuildLink $link, string $base ): ?array {
		$state = $this->builds->of( $kit, $link->picks, $link->was, $link->step, $link->assembly, $link->none );
		if ( null === $state ) {
			return null;
		}
		$steps   = self::steps( $state );
		$stopped = null;
		$step    = $state->step;
		if ( State::REVIEW !== $step && ! in_array( $step, $steps, true ) ) {
			// A slot that is no step now gives way to the one after it, and the last to the review.
			// Risers are the one slot that can be no step, so the slot after it is one.
			$step = $state->kit->slots->after( $step )[0] ?? State::REVIEW;
		}
		$unseen = self::risersNeeded( $state ) && ! isset( $link->picks[ SlotKey::RISERS ] ) && ! in_array( SlotKey::RISERS, $link->none, true );
		foreach ( $state->steps as $about ) {
			if ( $about->slot->key === $step ) {
				break;
			}
			if ( SlotKey::RISERS === $about->slot->key && $unseen ) {
				// The wheels just made risers a step, after the page's Next had named the step past it: the shopper sees
				// the riser the build now counts before going on. Its form sends the pick, or No risers.
				$step = SlotKey::RISERS;
				break;
			}
			if ( $about->slot->required && null === $about->counted() ) {
				$stopped = new BuildProblem( Problem::SlotEmpty, $about->slot->key, null );
				$step    = $about->slot->key;
				break;
			}
		}
		$state = $state->at( $step );
		// The address of what is shown: the step landed on, with the asked step's filters only where it is that step,
		// and the picks before only while their change is being explained.
		$shown = $link->at( $step );
		if ( $step === $link->step ) {
			$shown = $shown->filtered( $link->brand, $link->size )->showing( $link->shown );
		}
		if ( null !== $state->changes && null !== $link->was ) {
			$shown = $shown->after( $link->was );
		}
		$link   = $shown;
		$answer = $this->writer->of( $state, $link );
		return array(
			'state'  => $state,
			'answer' => $answer,
			'view'   => $this->view->of( $state, $answer, $link, $base, $steps, $stopped ),
		);
	}

	/**
	 * The slots that are steps, in step order: every slot but a risers slot the kit does not require, while the build
	 * holds no risers and its wheels need none (spec: A step, risers only when needed). Wheels that need risers keep
	 * the step with "No risers" chosen, so a shopper who left them out can still see why and change it.
	 *
	 * @since 0.6.0
	 *
	 * @param State $state The build's state.
	 * @return list<string>
	 */
	public static function steps( State $state ): array {
		$steps  = array();
		$needed = self::risersNeeded( $state );
		foreach ( $state->steps as $step ) {
			if ( $needed || ! self::skipped( $step ) ) {
				$steps[] = $step->slot->key;
			}
		}
		return $steps;
	}

	/**
	 * Whether a part the build counts says the wheels need a riser under its trucks.
	 *
	 * @param State $state The build's state.
	 */
	private static function risersNeeded( State $state ): bool {
		foreach ( $state->steps as $step ) {
			foreach ( $step->counted()->findings ?? array() as $finding ) {
				if ( MessageCode::WheelNeedsRiser === $finding->code ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether a slot is no step for this build.
	 *
	 * @param StateStep $step The slot, as the state holds it.
	 */
	private static function skipped( StateStep $step ): bool {
		return SlotKey::RISERS === $step->slot->key && ! $step->slot->required && null === $step->counted();
	}
}
