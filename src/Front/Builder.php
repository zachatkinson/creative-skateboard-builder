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
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\BearingRule;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Service\ShelfQuery;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Service\StateStep;
use CreativeBranding\SkateboardBuilder\Service\StepShelf;

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
	 * A signed-in customer's width, from their saved builds (from 1.0.0).
	 *
	 * @var RememberedWidth|null
	 */
	private ?RememberedWidth $remembered = null;

	/**
	 * A saved build opened from My builds, checked against the shop (from 1.0.0).
	 *
	 * @var SavedBuilds|null
	 */
	private ?SavedBuilds $saved = null;

	/**
	 * The step's shelf, for the deck a new width moves the build to.
	 *
	 * @var StepShelf|null
	 */
	private ?StepShelf $shelf = null;

	/**
	 * The guided start's recommendations (from 1.3.0, #134).
	 *
	 * @var GuidedStart|null
	 */
	private ?GuidedStart $guided = null;

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
	 * The same builder, which asks a signed-in customer's width of their saved builds and checks a saved build opened
	 * from My builds against the shop. Only this release's container calls it, so a builder an earlier release's
	 * container makes mid-update does neither (D-208).
	 *
	 * @since 1.0.0
	 *
	 * @param RememberedWidth|null $remembered A signed-in customer's width.
	 * @param SavedBuilds|null     $saved      The customers' saved builds.
	 */
	public function withSaved( ?RememberedWidth $remembered, ?SavedBuilds $saved ): self {
		$builder             = clone $this;
		$builder->remembered = $remembered;
		$builder->saved      = $saved;
		return $builder;
	}

	/**
	 * The builder with the step's shelf, which a new width asks for the picked deck's look in it (#81).
	 *
	 * @since 1.1.0
	 *
	 * @param StepShelf $shelf The step's shelf.
	 */
	public function withShelf( StepShelf $shelf ): self {
		$builder        = clone $this;
		$builder->shelf = $shelf;
		return $builder;
	}

	/**
	 * The builder with the guided start, which recommends builds from a shopper's answers at its start (#134). Only
	 * this release's container calls it, so a builder an earlier release's container makes mid-update asks the
	 * questions and shows no builds (D-208).
	 *
	 * @since 1.3.0
	 *
	 * @param GuidedStart $guided The guided start.
	 */
	public function withGuide( GuidedStart $guided ): self {
		$builder         = clone $this;
		$builder->guided = $guided;
		return $builder;
	}

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
		if ( '' !== $link->saved && null !== $this->saved ) {
			// Opened from My builds: a build that changed since it was saved says so before its review, with the
			// closest parts in place (spec: A saved build that has changed, D-254). One that has not opens as it was.
			$reopened = $this->saved->reopened( get_current_user_id(), $link->saved );
			if ( null === $reopened || $kit !== $reopened->build->kit ) {
				return $this->show( $kit, new BuildLink( $link->picks, null, $link->step, $link->assembly ), $base );
			}
			$shown = $this->show( $kit, new BuildLink( $reopened->check->picks, null, State::REVIEW, $reopened->build->assembly ), $base );
			if ( null === $shown ) {
				return null;
			}
			$panel           = $this->writer->reopened( $reopened );
			$shown['view']   = $this->view->reopened( $shown['view'], $panel, $shown['state'], $reopened->build->assembly );
			$shown['answer'] = $shown['answer'] + array( 'reopened' => $panel );
			return $shown;
		}
		if ( null !== $this->remembered && '' === $link->shelf->width && ! isset( $link->picks[ SlotKey::DECK ] ) ) {
			// A width asked for, or the deck's, comes first; otherwise a signed-in customer's last saved build brings
			// theirs, and skips the question (spec: The deck's width first, "Remembered").
			$width = $this->remembered->of( $kit );
			if ( '' !== $width ) {
				$shelf = $link->shelf;
				$link  = $link->shelved( new ShelfQuery( $width, $shelf->chips, $shelf->query, $shelf->sort, $shelf->from, $shelf->shown, $shelf->ranked ) );
			}
		}
		$state = $this->builds->of( $kit, $link->picks, $link->was, $link->step, $link->assembly, $link->none );
		if ( null === $state ) {
			return null;
		}
		$moved = $this->moved( $state, $link );
		if ( null !== $moved ) {
			// A new width moves the picked deck to its look in that width, and what no longer fits it is explained (#81).
			$link  = $link->pick( SlotKey::DECK, $moved['part'] )->after( $link->was ?? $link->picks );
			$state = $this->builds->of( $kit, $link->picks, $link->was, $link->step, $link->assembly, $link->none );
			if ( null === $state ) {
				return null;
			}
		}
		$steps = self::steps( $state );
		if ( $link->asked && SlotKey::RISERS === $link->step && ! in_array( SlotKey::RISERS, $steps, true ) && in_array( SlotKey::RISERS, $state->kit->slots->keys(), true ) ) {
			// Asked for by a link, as Add risers at the review asks, the risers slot is a step though nothing needs it
			// (spec: Risers, "Anyone can add them"). A step's form naming it gives way as before: its Next was made
			// before the form's own pick of smaller wheels.
			$steps = array_values( array_intersect( $state->kit->slots->keys(), array_merge( $steps, array( SlotKey::RISERS ) ) ) );
		}
		$stopped = null;
		$step    = $state->step;
		if ( State::REVIEW !== $step && ! in_array( $step, $steps, true ) ) {
			// A slot that is no step now gives way to the first step after it, and the last to the review.
			$step = array_values( array_intersect( $state->kit->slots->after( $step ), $steps ) )[0] ?? State::REVIEW;
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
			if ( $about->slot->required && null === $about->counted() && in_array( $about->slot->key, $steps, true ) ) {
				$stopped = new BuildProblem( Problem::SlotEmpty, $about->slot->key, null );
				$step    = $about->slot->key;
				break;
			}
		}
		$state = $state->at( $step );
		// The address of what is shown: the step landed on, with the asked step's filters and shelf only where it is that step,
		// and the picks before only while their change is being explained.
		$shown = $link->at( $step );
		if ( $step === $link->step ) {
			$shown = $shown->filtered( $link->brand, $link->size )->shelved( $link->shelf )->showing( $link->shown );
		}
		if ( null !== $state->changes && null !== $link->was ) {
			$shown = $shown->after( $link->was );
		}
		// The guided start's answers stay in the address of the start they were given at, and in no other (#134).
		if ( BuilderView::atStart( $state, $shown ) ) {
			$shown = $shown->guided( $link->guide );
		}
		if ( null === $state->changes && array() !== $link->undo ) {
			// A smart swap's Undo stays with the build it made until the next move (D-397).
			$shown = $shown->undoing( $link->undo );
		}
		$link   = $shown;
		$answer = $this->writer->of( $state, $link );
		$view   = $this->view->of( $state, $answer, $link, $base, $steps, $stopped, $moved['said'] ?? '' );
		if ( null !== $this->guided && $link->guide->asked && BuilderView::atStart( $state, $link ) ) {
			$view['guide'] = BuilderView::guide( $state, $answer, $link, $base, $this->guided->of( $state, $link->guide ) );
		}
		return array(
			'state'  => $state,
			'answer' => $answer,
			'view'   => $view,
		);
	}

	/**
	 * The deck a width asked for moves the build to, with what to say of it: the picked deck's look in that width. Null
	 * where nothing is picked, the deck is in that width already, or its look is not made in it (#81).
	 *
	 * @param State     $state The build's state.
	 * @param BuildLink $link  The address.
	 * @return array{part: int, said: string}|null
	 */
	private function moved( State $state, BuildLink $link ): ?array {
		$picked = null;
		foreach ( $state->steps as $step ) {
			if ( SlotKey::DECK === $step->slot->key ) {
				$picked = $step->picked;
			}
		}
		$width = $picked?->part->specs->measure( SpecKey::DeckWidth );
		if ( null === $this->shelf || null === $picked || null === $width || '' === $link->shelf->width || Shelf::named( $width ) === $link->shelf->width ) {
			return null;
		}
		$view = $this->shelf->of( $state->at( SlotKey::DECK ), new ShelfQuery( $link->shelf->width, shown: Shelf::MOST ) );
		foreach ( null === $view ? array() : array_merge( $view->listing->looks, $view->listing->soldOut ) as $card ) {
			$size = $card->option->part->specs->measure( SpecKey::DeckWidth );
			if ( null !== $size && $card->option->part->id !== $picked->part->id && in_array( $picked->part, $card->look->parts(), true ) ) {
				// A deck with no name moves all the same, and is not said with none.
				$name = $view->names[ $card->look->product ] ?? '';
				return array(
					'part' => $card->option->part->id,
					/* translators: 1: a deck's name, such as Hard Hat. 2: the width it is now, such as 8.5″. */
					'said' => '' === $name ? '' : sprintf( __( '%1$s is now %2$s.', 'creative-skateboard-builder' ), $name, Words::measure( $size ) ),
				);
			}
		}
		return null;
	}

	/**
	 * The slots that are steps, in step order: every slot but a risers slot the kit does not require, while the build
	 * holds no risers and its wheels need none (spec: A step, risers only when needed), and but bearings the wheels
	 * come with (spec: Bearings). Wheels that need risers keep
	 * the step with "No risers" chosen, so a shopper who left them out can still see why and change it.
	 *
	 * @since 0.6.0
	 *
	 * @param State $state The build's state.
	 * @return list<string>
	 */
	public static function steps( State $state ): array {
		$steps    = array();
		$needed   = self::risersNeeded( $state );
		$included = self::bearingsIncluded( $state );
		foreach ( $state->steps as $step ) {
			if ( ( $needed || ! self::skipped( $step ) ) && ! ( $included && SlotKey::BEARINGS === $step->slot->key && null === $step->counted() ) ) {
				$steps[] = $step->slot->key;
			}
		}
		return $steps;
	}

	/**
	 * Whether the wheels the build counts come with their bearings, so the bearings slot is no step and counts as
	 * filled (spec: Bearings, D-246). A bearing picked before such wheels stays a step until the change list takes it.
	 *
	 * @since 1.0.0
	 *
	 * @param State $state The build's state.
	 */
	public static function bearingsIncluded( State $state ): bool {
		foreach ( $state->steps as $step ) {
			if ( SlotKey::WHEELS === $step->slot->key ) {
				return BearingRule::comesWith( $step->counted()?->part );
			}
		}
		return false;
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
