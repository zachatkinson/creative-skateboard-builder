<?php
/**
 * What the builder's template shows.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Look\Shelf;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns a build's answer into the document the builder's one template is bound to (spec: The builder; Rules,
 * Markup): every sentence written out, every address made, and every yes or no the template shows or hides by.
 * The server renders the template from it, and the script, given the same document by the state route, renders the
 * same template again. So the template holds no rule, and the script holds no word and builds no address.
 *
 * It decides nothing of fit, price or which step a build is on. It reads the answer and words it.
 *
 * The document is the builder's own: the template and the script are its only readers, and its fields may change in
 * any release. What other code may rely on is the rest of the state route's answer.
 *
 * @since 0.6.0
 */
final class BuilderView {

	/**
	 * The most looks a step offers with its cards alone; from 13 it gets Sort and the chips (spec: Tools that grow).
	 */
	private const int CARDS_ALONE = 12;

	/**
	 * The most looks a step offers with no search; from 41 it gets one.
	 */
	private const int SEARCHED = 40;

	/**
	 * The board's height in the picture: at most this many CSS pixels (36rem), from a window this tall, and below
	 * that this share of the window's height, in vh. The stylesheet's `--cbuild-board-size` is the same.
	 */
	private const int BOARD_MOST = 576;

	private const int BOARD_TALL = 960;

	private const int BOARD_SHARE = 60;

	/**
	 * The board's height in the phone bar, in CSS pixels: the bar's target, `--cbuild-target`'s 2.75rem.
	 */
	private const int BAR_SIZE = 44;

	/**
	 * The step's form's ID, by which the phone bar's button sends it. A page holds one builder.
	 *
	 * @since 0.6.0
	 */
	public const string STEP_FORM = 'cbuild-step-form';

	/**
	 * The review's form's ID, as the step's.
	 *
	 * @since 0.6.0
	 */
	public const string REVIEW_FORM = 'cbuild-review-form';

	/**
	 * The document.
	 *
	 * @since 0.6.0
	 *
	 * @param State                $state   The build's state, on the step shown: one of `$steps`, or the review.
	 * @param array<string, mixed> $answer  The state's answer.
	 * @param BuildLink            $link    The address of what is shown.
	 * @param string               $base    The address of the page the builder is on, with no build in it.
	 * @param list<string>         $steps   The slots that are steps, in step order.
	 * @param BuildProblem|null    $stopped What kept the shopper from the step asked for, if anything did.
	 * @param string               $moved   What the build moved to on its own, said first, such as "Hard Hat is now 8.5″." (#81).
	 * @return array<string, mixed>
	 */
	public function of( State $state, array $answer, BuildLink $link, string $base, array $steps, ?BuildProblem $stopped, string $moved = '' ): array {
		$slots = $state->kit->slots;
		$url   = static fn ( BuildLink $to ): string => $to->address( $base, $slots );
		// The picks as the address has them, and as the total counts them: a slot that starts on a part counts it.
		$counted = array();
		$lines   = array();
		foreach ( Cast::items( Cast::map( $answer['total'] ?? null )['lines'] ?? null ) as $line ) {
			$line                     = Cast::map( $line );
			$slot                     = Cast::str( $line['slot'] ?? null );
			$counted[ $slot ]         = Cast::int( $line['part'] ?? null );
			$lines[ $slot ]           = $line;
			$lines[ $slot ]['amount'] = Cast::str( Cast::map( $line['total'] ?? null )['text'] ?? null );
		}
		$about = array();
		foreach ( Cast::items( $answer['steps'] ?? null ) as $step ) {
			$step                                      = Cast::map( $step );
			$about[ Cast::str( $step['slot'] ?? '' ) ] = $step;
		}
		$label   = static fn( string $slot ): string => Cast::str( $about[ $slot ]['label'] ?? $slot );
		$review  = State::REVIEW === $state->step;
		$changes = Cast::map( $answer['changes'] ?? null );
		$panel   = array() !== $changes;
		$all     = array_merge( $steps, array( State::REVIEW ) );
		$at      = array_flip( $all )[ $state->step ];
		$name    = $review ? __( 'Review', 'creative-skateboard-builder' ) : $label( $state->step );
		/* translators: 1: the step's number. 2: how many steps there are. 3: the step's name, such as Trucks. */
		$heading = sprintf( __( 'Step %1$s of %2$s: %3$s', 'creative-skateboard-builder' ), number_format_i18n( $at + 1 ), number_format_i18n( count( $all ) ), $name );
		// The heading shows the step's question and is read with its count; the title keeps the count and the name (D-269).
		/* translators: 1: the step's number. 2: how many steps there are. Read before the step's question. */
		$count = sprintf( __( 'Step %1$s of %2$s:', 'creative-skateboard-builder' ), number_format_i18n( $at + 1 ), number_format_i18n( count( $all ) ) );

		// What keeps the build from where the shopper asked to go, or from the cart.
		$errors = array();
		// A part a ready build holds that the kit no longer offers is named as its tile names it; an ID a link brought
		// that no preset holds is named by nothing (#85).
		$named = array();
		foreach ( Cast::items( $answer['presets'] ?? null ) as $preset ) {
			foreach ( Cast::items( Cast::map( $preset )['problems'] ?? null ) as $kept ) {
				$kept                                        = Cast::map( $kept );
				$named[ Cast::int( $kept['part'] ?? null ) ] = Cast::str( $kept['name'] ?? null );
			}
		}
		$refused = array();
		foreach ( Cast::items( $answer['refused'] ?? null ) as $problem ) {
			$problem          = Cast::map( $problem );
			$slot             = Cast::str( $problem['slot'] ?? null );
			$code             = Problem::tryFrom( Cast::str( $problem['code'] ?? null ) );
			$name             = $named[ Cast::int( $problem['part'] ?? null ) ] ?? '';
			$refused[ $slot ] = true;
			$errors[]         = array(
				'text' => Problem::PartNotInSlot === $code && '' !== $name ? Words::named( $code, $label( $slot ), $name ) : Cast::str( $problem['message'] ?? null ),
				'url'  => $url( $link ),
			);
		}
		// The slot a refused part left empty is said once, by that part's line, not again as "nothing is chosen yet".
		if ( null !== $stopped && ! ( Problem::SlotEmpty === $stopped->code && isset( $refused[ $stopped->slot ] ) ) ) {
			array_unshift(
				$errors,
				array(
					'text' => Words::problem( $stopped->code, $label( $stopped->slot ) ),
					'url'  => $url( $link->at( $stopped->slot ) ),
				)
			);
		}
		if ( $review ) {
			foreach ( Cast::items( $answer['problems'] ?? null ) as $problem ) {
				$problem  = Cast::map( $problem );
				$slot     = Cast::str( $problem['slot'] ?? null );
				$code     = Problem::tryFrom( Cast::str( $problem['code'] ?? null ) );
				$errors[] = array(
					// A part the build holds is named: one a preset or a link brought may be one the shopper never saw.
					'text' => null === $code ? Cast::str( $problem['message'] ?? null ) : Words::named( $code, $label( $slot ), Cast::str( $lines[ $slot ]['name'] ?? null ) ),
					'url'  => $url( $link->at( in_array( $slot, $steps, true ) ? $slot : $state->step ) ),
				);
			}
		}

		$progress = array();
		// The step after the current one is a link too once the current one has a pick: one tap away (spec: The step
		// rail).
		$picked = isset( $counted[ $state->step ] );
		foreach ( $all as $number => $key ) {
			$done       = $number < $at;
			$linked     = $done || ( $picked && $number === $at + 1 );
			$named      = State::REVIEW === $key ? __( 'Review', 'creative-skateboard-builder' ) : $label( $key );
			$progress[] = array(
				'key'     => $key,
				'label'   => $named,
				'state'   => self::place( $number, $at, $named, $done ? Cast::str( $lines[ $key ]['name'] ?? null ) : '' ),
				'done'    => $done,
				'linked'  => $linked,
				'current' => $number === $at,
				'url'     => $linked ? $url( $link->at( $key ) ) : '',
				'aria'    => $number === $at ? 'step' : false,
			);
		}

		$total   = Cast::map( $answer['total'] ?? null );
		$sum     = Cast::str( Cast::map( $total['total'] ?? null )['text'] ?? null );
		$picture = Cast::map( $answer['picture'] ?? null );
		$status  = $panel
			/* translators: %s: a number of parts. */
			? sprintf( _n( '%s part no longer fits.', '%s parts no longer fit.', count( Cast::items( $changes['changes'] ?? null ) ), 'creative-skateboard-builder' ), number_format_i18n( count( Cast::items( $changes['changes'] ?? null ) ) ) )
			: self::said( Cast::str( $picture['description'] ?? null ), $sum, isset( $total['estimate'] ) ? Cast::str( Cast::map( $total['estimate'] )['text'] ?? null ) : null );
		if ( '' !== $moved ) {
			/* translators: 1: what the build moved to, such as "Hard Hat is now 8.5″.". 2: what the status says after it. */
			$status = sprintf( __( '%1$s %2$s', 'creative-skateboard-builder' ), $moved, $status );
		}

		$board              = self::board( $picture, $state->id, null );
		$top                = self::top( $picture, $state->id );
		[ $action, $carry ] = self::form( $base );
		$step               = $review ? self::none( $state->id ) : $this->step( $state, $answer, $link, $url, $steps, $counted, $lines, $about );
		$summary            = $this->summary( $state, $answer, $link, $url, $lines, $label );
		$shown              = $this->review( $state, $answer, $link, $url, $steps, $counted, $lines, $label, $summary );
		return array(
			'kit'       => $state->id,
			'url'       => $url( $link ),
			'clean'     => $base,
			'action'    => $action,
			'carry'     => $carry,
			'heading'   => $review ? __( 'Check your build', 'creative-skateboard-builder' ) : self::question( $state->step, $name ),
			'count'     => $count,
			/* translators: %s: the page's heading, such as "Step 2 of 7: Trucks". */
			'title'     => array() === $errors ? $heading : sprintf( __( 'Error: %s', 'creative-skateboard-builder' ), $heading ),
			'isStep'    => ! $panel && ! $review,
			'isChanges' => $panel,
			'isReview'  => ! $panel && $review,
			// The kit's layout on a desktop (D-255): the stage on the left or the right, the board 60 % or 50 % wide.
			'layout'    => array(
				'stageRight' => 'right' === $state->kit->layout->stageSide,
				'balanced'   => 'balanced' === $state->kit->layout->boardSize,
			),
			'status'    => $status,
			'errors'    => array(
				'shown'   => array() !== $errors,
				'heading' => __( 'Fix these before going on', 'creative-skateboard-builder' ),
				'items'   => $errors,
			),
			'progress'  => $progress,
			'presets'   => self::presets( $answer, $link, $state, $label ),
			'step'      => $step,
			'changes'   => $this->changes( $state, $changes, $link, $lines, $label ),
			'summary'   => $summary,
			'bar'       => self::bar( $summary, $step, $shown, $review, $panel, Cast::map( Cast::items( $picture['items'] ?? null )[0] ?? null ), $state->id, self::board( $picture, $state->id, self::BAR_SIZE ), self::back( $review, $step, $steps, $link, $url, $label ) ),
			'picture'   => array(
				'heading'     => __( 'Your board', 'creative-skateboard-builder' ),
				'description' => Cast::str( $picture['description'] ?? null ),
				// With nothing picked, the stage says what to do where the board will be; the caption waits for a part.
				'captioned'   => array() !== Cast::items( $picture['items'] ?? null ),
				'empty'       => __( 'Pick a deck to start', 'creative-skateboard-builder' ),
				'board'       => $board,
				// The board's top (#46): shown on the grip step, where the shopper sees the grip go on, and turned to
				// with the button by the board, which the store offers and keeps to once pressed. A deck with no layer
				// image has no outline to draw the top from.
				'top'         => $top,
				'turnable'    => $top['shown'],
				'turned'      => $top['shown'] && SlotKey::GRIP === $state->step,
				'turn'        => __( 'Show the top', 'creative-skateboard-builder' ),
				'sided'       => $board['shown'],
				'seen'        => array(
					'under' => __( 'Seen from underneath.', 'creative-skateboard-builder' ),
					'top'   => __( 'Seen from the top.', 'creative-skateboard-builder' ),
				),
				// The same, as a line under the stage's heading.
				'side'        => array(
					'under' => __( 'Seen from underneath', 'creative-skateboard-builder' ),
					'top'   => __( 'Seen from the top', 'creative-skateboard-builder' ),
				),
				'items'       => array_map(
					static function ( mixed $item, int $at ) use ( $state ): array {
						$item  = Cast::map( $item );
						$drawn = Cast::bool( $item['drawn'] ?? null );
						return array(
							'key'      => Cast::str( $item['slot'] ?? null ),
							'classes'  => self::flagged( array( Options::CLASS_PICTURE_ITEM ), $state->id, Cast::str( $item['slot'] ?? null ), Cast::int( $item['part'] ?? null ) ),
							'label'    => Cast::str( $item['label'] ?? null ),
							'name'     => Cast::str( $item['name'] ?? null ),
							// A part the board draws has no labelled photo beside it (spec: The picture).
							'drawn'    => $drawn,
							// The first, the deck, is the page's largest paint (spec: The picture); the rest wait. A
							// drawn deck's photo is hidden, and a hidden eager image is still fetched, so it waits too.
							'loading'  => 0 === $at && ! $drawn ? 'eager' : 'lazy',
							'priority' => 0 === $at && ! $drawn ? 'high' : 'auto',
						) + self::image( $item['image'] ?? null );
					},
					Cast::items( $picture['items'] ?? null ),
					array_keys( Cast::items( $picture['items'] ?? null ) )
				),
			),
			'review'    => $shown,
		);
	}

	/**
	 * The footer's Back: the step's own Back on a step, and the last step at the review. It shows "Back" and is read
	 * with the step it goes to, as the step's own link is (spec: The layout, Footer).
	 *
	 * @param bool                        $review Whether the view is the review.
	 * @param array<string, mixed>        $step   The step's view.
	 * @param list<string>                $steps  The slots that are steps.
	 * @param BuildLink                   $link   The address of what is shown.
	 * @param callable(BuildLink): string $url    Makes an address.
	 * @param callable(string): string    $label  A slot's label.
	 * @return array{shown: bool, url: string, text: string, label: string}
	 */
	private static function back( bool $review, array $step, array $steps, BuildLink $link, callable $url, callable $label ): array {
		$own  = Cast::map( $step['back'] ?? null );
		$last = $review && array() !== $steps ? $steps[ count( $steps ) - 1 ] : null;
		return array(
			'shown' => null !== $last || Cast::bool( $own['shown'] ?? null ),
			'url'   => null === $last ? Cast::str( $own['url'] ?? null ) : $url( $link->at( $last ) ),
			'text'  => __( 'Back', 'creative-skateboard-builder' ),
			/* translators: %s: a step's name, such as Deck. */
			'label' => null === $last ? Cast::str( $own['text'] ?? null ) : sprintf( __( 'Back: %s', 'creative-skateboard-builder' ), $label( $last ) ),
		);
	}

	/**
	 * The phone bar (spec: The total, W7): the total, which opens the parts list, and the button that moves the build
	 * on, the step's Next or the review's Add to cart. The button belongs to its form by the form's ID, so it sends that
	 * form with JavaScript or without. The change panel asks for Use these or Undo in its own forms, so the bar has no
	 * button there.
	 *
	 * @param array<string, mixed>                                         $summary The summary.
	 * @param array<string, mixed>                                         $step    The step.
	 * @param array<string, mixed>                                         $shown   The review.
	 * @param bool                                                         $review  Whether the review is shown.
	 * @param bool                                                         $panel   Whether the change panel is shown.
	 * @param array<string, mixed>                                         $first   The picture's first part, if it has one: its slot, its ID and its photo.
	 * @param int                                                          $kit     The kit product's ID.
	 * @param array<string, mixed>                                         $board   The board, drawn at the bar's size.
	 * @param array{shown: bool, url: string, text: string, label: string} $back The footer's Back.
	 * @return array{shown: bool, seen: bool, board: array<string, mixed>, photo: array{hasImage: bool, src: string, srcset: string, sizes: string, width: int, height: int, classes: string}, picture: string, total: string, amount: string, parts: string, hide: string, back: array{shown: bool, url: string, text: string, label: string}, next: array{shown: bool, onStep: bool, onReview: bool, text: string, form: string, name: string, value: string, disabled: bool}}
	 */
	private static function bar( array $summary, array $step, array $shown, bool $review, bool $panel, array $first, int $kit, array $board, array $back ): array {
		$next  = Cast::map( $step['next'] ?? null );
		$photo = self::image( $first['image'] ?? null );
		return array(
			'shown'       => true,
			// A small picture of the board, which links to the picture: the board itself once it draws a layer, and
			// until then the picture's first photo, the deck's.
			'seen'        => Cast::bool( $board['shown'] ?? null ) || $photo['hasImage'],
			'board'       => $board,
			'photo'       => $photo + array(
				'classes' => self::flagged( array( Options::CLASS_BAR_PHOTO ), $kit, Cast::str( $first['slot'] ?? null ), Cast::int( $first['part'] ?? null ) ),
			),
			'picture'     => __( 'See the picture', 'creative-skateboard-builder' ),
			/* translators: %s: the build's total, such as $184.00. */
			'total'       => sprintf( __( 'Total: %s', 'creative-skateboard-builder' ), Cast::str( $summary['total'] ?? null ) ),
			// The amount alone is shown and the sentence read: beside the board's picture "Total:" broke the line at
			// 412 pixels, and the bar grew under the thumb (D-207).
			'amount'      => Cast::str( $summary['total'] ?? null ),
			// The disclosure says what it does, open or shut (Cowork minor 18); the stylesheet shows the one that applies.
			// On a phone the summary is in the shut parts sheet, so the bar gives the estimate itself, on a line of its
			// own under the amount, while one is given (Cowork's 0.8.0 review, F2; D-230). The status region says it
			// already, so the line is not read again.
			'hasEstimate' => Cast::bool( $summary['hasEstimate'] ?? null ),
			/* translators: %s: an amount of money, such as $184.00. Short: it sits under the total in the phone's bar. */
			'estimate'    => sprintf( __( 'Complete from about %s', 'creative-skateboard-builder' ), Cast::str( $summary['estimateAmount'] ?? null ) ),
			'parts'       => __( 'Show the parts', 'creative-skateboard-builder' ),
			'hide'        => __( 'Hide the parts', 'creative-skateboard-builder' ),
			'back'        => array( 'shown' => $back['shown'] && ! $panel ) + $back,
			'next'        => array(
				'shown'    => ! $panel,
				// Which of the bar's two buttons: a button's form is its own, set once, so each form has one.
				'onStep'   => ! $panel && ! $review,
				'onReview' => ! $panel && $review,
				'text'     => $review ? Cast::str( $shown['add'] ?? null ) : Cast::str( $next['text'] ?? null ),
				'form'     => $review ? self::REVIEW_FORM : self::STEP_FORM,
				'name'     => $review ? '' : Cast::str( $step['stepName'] ?? null ),
				'value'    => $review ? '' : Cast::str( $next['value'] ?? null ),
				'disabled' => $review && ! Cast::bool( $shown['ready'] ?? null ),
			),
		);
	}

	/**
	 * The preset tiles (spec: Presets): on a build not yet begun, one for each of the kit's presets, with its total,
	 * a line on who it is for, and any part that keeps it from the cart named. Each is a GET form whose button starts
	 * the build at the review (spec: Search, "Picks, filters, Show more and presets are buttons in GET forms"), its
	 * fields the preset's part IDs as a build link writes them, each checked again when it arrives.
	 *
	 * @param array<string, mixed>    $answer The state's answer.
	 * @param BuildLink               $link   The address.
	 * @param State                   $state  The build's state.
	 * @param callable(string):string $label  A slot's label.
	 * @return array{shown: bool, heading: string, tiles: list<array<string, mixed>>}
	 */
	private static function presets( array $answer, BuildLink $link, State $state, callable $label ): array {
		$tiles = array();
		// At the start alone: on the first step, with nothing picked. A build begun is the shopper's own.
		$first = $state->kit->slots->keys()[0] ?? '';
		if ( array() === $link->picks && null === $link->preset && $first === $state->step ) {
			foreach ( Cast::items( $answer['presets'] ?? null ) as $at => $preset ) {
				$preset = Cast::map( $preset );
				$picks  = array();
				foreach ( Cast::items( $preset['picks'] ?? null ) as $pick ) {
					$pick                                        = Cast::map( $pick );
					$picks[ Cast::str( $pick['slot'] ?? null ) ] = Cast::int( $pick['part'] ?? null );
				}
				$fields = array();
				foreach ( ( new BuildLink( $picks, null, State::REVIEW, null, $at ) )->query( $state->kit->slots ) as $key => $value ) {
					$fields[] = array(
						'name'  => BuildLink::PARAM . '[' . $key . ']',
						'value' => Cast::str( $value ),
					);
				}
				// One line a slot: a part refused comes first and leaves its slot empty, which says the same again.
				$notes = array();
				$whole = true;
				foreach ( Cast::items( $preset['problems'] ?? null ) as $problem ) {
					$problem = Cast::map( $problem );
					$code    = Problem::tryFrom( Cast::str( $problem['code'] ?? null ) );
					$slot    = Cast::str( $problem['slot'] ?? null );
					// A slot left without its part leaves the build short of it, and its total short with it.
					$whole = $whole && Problem::SlotEmpty !== $code && Problem::PartNotInSlot !== $code;
					if ( isset( $notes[ $slot ] ) ) {
						continue;
					}
					$notes[ $slot ] = array( 'text' => null === $code ? Cast::str( $problem['message'] ?? null ) : Words::named( $code, $label( $slot ), Cast::str( $problem['name'] ?? null ) ) );
				}
				$notes       = array_values( $notes );
				$name        = Cast::str( $preset['name'] ?? null );
				$description = Cast::str( $preset['description'] ?? null );
				$tiles[]     = self::image( $preset['image'] ?? null ) + array(
					'name'           => $name,
					'description'    => $description,
					'hasDescription' => '' !== $description,
					'total'          => Cast::str( Cast::map( $preset['total'] ?? null )['text'] ?? null ),
					'hasTotal'       => $whole,
					'notes'          => $notes,
					'fields'         => $fields,
					/* translators: %s: a preset's name, such as Street. */
					'button'         => sprintf( __( 'Start from %s', 'creative-skateboard-builder' ), $name ),
				);
			}
		}
		return array(
			'shown'   => array() !== $tiles,
			'heading' => __( 'Start from a ready build', 'creative-skateboard-builder' ),
			'tiles'   => $tiles,
		);
	}

	/**
	 * Where a step stands against the one shown, as its class and as a reader hears it.
	 *
	 * @param int    $number The step's place.
	 * @param int    $at     The place of the step shown.
	 * @param string $label  The step's label.
	 * @param string $pick   The part picked in a step done, or empty.
	 */
	private static function place( int $number, int $at, string $label, string $pick ): string {
		if ( $number < $at ) {
			return '' === $pick
				/* translators: %s: a step's label, such as Risers, done with nothing picked. */
				? sprintf( __( '%s, done', 'creative-skateboard-builder' ), $label )
				/* translators: 1: a step's label, such as Deck. 2: the part picked in it. */
				: sprintf( __( '%1$s, done: %2$s', 'creative-skateboard-builder' ), $label, $pick );
		}
		return $number === $at
			/* translators: %s: a step's label, such as Trucks. */
			? sprintf( __( '%s, current step', 'creative-skateboard-builder' ), $label )
			/* translators: %s: a step's label, such as Wheels. */
			: sprintf( __( '%s, to come', 'creative-skateboard-builder' ), $label );
	}

	/**
	 * A GET form's address and the fields that carry the page's own query: a page at `?page_id=5` is still that
	 * page after the form is sent, which replaces the address's query with its fields.
	 *
	 * @param string $base The page's address.
	 * @return array{string, list<array{name: string, value: string}>}
	 */
	private static function form( string $base ): array {
		$parts = explode( '?', $base, 2 );
		$carry = array();
		parse_str( $parts[1] ?? '', $query );
		foreach ( $query as $name => $value ) {
			if ( is_string( $value ) ) {
				$carry[] = array(
					'name'  => (string) $name,
					'value' => $value,
				);
			}
		}
		return array( $parts[0], $carry );
	}

	/**
	 * A photo as the template binds it: every field there, and whether there is one.
	 *
	 * @param mixed $image The photo, as a card holds it, or null.
	 * @return array{hasImage: bool, src: string, srcset: string, sizes: string, width: int, height: int}
	 */
	private static function image( mixed $image ): array {
		$image = Cast::map( $image );
		return array(
			'hasImage' => array() !== $image,
			'src'      => Cast::str( $image['src'] ?? null ),
			'srcset'   => Cast::str( $image['srcset'] ?? null ),
			'sizes'    => Cast::str( $image['sizes'] ?? null ),
			'width'    => Cast::int( $image['width'] ?? null ),
			'height'   => Cast::int( $image['height'] ?? null ),
		);
	}

	/**
	 * The board in the page (spec: The picture; D-195): its layers from the bottom, each placed as a share of the
	 * board, so the stylesheet sets one size and the layers keep to scale. The layout's numbers are millimetres, so a
	 * layer's share of the board's height says how wide it shows, which is its `sizes`. The board is drawn at most
	 * 36rem tall, and 60 % of the window's height below that (the stylesheet's `--cbuild-board-size`); in the bar, at
	 * the bar's own size. The deck, the board's largest paint, is fetched first.
	 *
	 * @param array<string, mixed> $picture The answer's picture.
	 * @param int                  $kit     The kit product's ID.
	 * @param int|null             $fixed   The board's height in CSS pixels, or null for the picture's own.
	 * @return array{shown: bool, style: string, layers: list<array<string, mixed>>}
	 */
	private static function board( array $picture, int $kit, ?int $fixed ): array {
		$width  = Cast::int( $picture['width'] ?? null );
		$height = Cast::int( $picture['height'] ?? null );
		$layers = array();
		$seen   = array();
		foreach ( $height > 0 && $width > 0 ? Cast::items( $picture['layers'] ?? null ) : array() as $layer ) {
			$layer         = Cast::map( $layer );
			$slot          = Cast::str( $layer['slot'] ?? null );
			$image         = Cast::map( $layer['image'] ?? null );
			$share         = Cast::int( $layer['width'] ?? null ) / $height;
			$sizes         = null === $fixed
				? sprintf( '(min-height: %1$dpx) %2$dpx, %3$svh', self::BOARD_TALL, (int) ceil( self::BOARD_MOST * $share ), self::decimal( self::BOARD_SHARE * $share ) )
				: ceil( $fixed * $share ) . 'px';
			$seen[ $slot ] = ( $seen[ $slot ] ?? 0 ) + 1;
			$layers[]      = array(
				'key'      => $slot . '-' . $seen[ $slot ],
				'classes'  => self::flagged( array( Options::CLASS_LAYER, Options::CLASS_LAYER . '--' . $slot ), $kit, $slot, Cast::int( $layer['part'] ?? null ) ),
				'style'    => self::placed( $layer, $width, $height ),
				'src'      => Cast::str( $image['src'] ?? null ),
				'width'    => Cast::int( $image['width'] ?? null ),
				'height'   => Cast::int( $image['height'] ?? null ),
				'sizes'    => $sizes,
				'sources'  => array_map(
					static fn( mixed $source ): array => array(
						'type'   => Cast::str( Cast::map( $source )['type'] ?? null ),
						'srcset' => Cast::str( Cast::map( $source )['srcset'] ?? null ),
						'sizes'  => $sizes,
					),
					Cast::items( $image['sources'] ?? null )
				),
				'priority' => SlotKey::DECK === $slot && null === $fixed ? 'high' : 'auto',
			);
		}
		return array(
			'shown'  => array() !== $layers,
			'style'  => array() === $layers ? '' : '--cbuild-board-ratio:' . $width . ' / ' . $height,
			'layers' => $layers,
		);
	}

	/**
	 * The board's top (#46), placed as the underside is, each layer an element the stylesheet paints from its style:
	 * the deck maple in its own outline, the deck's image as a mask; the grip's image repeated across the same
	 * outline, nose to tail; and each wheel and bolt head its own image. Each is one file, not a `srcset`: a style takes
	 * one address. It is the largest WebP rendition made, or else AVIF's, or else the image's own file, so the outline
	 * is as sharp as the underside's deck. The bar draws the same top at its size, from the same files.
	 *
	 * @param array<string, mixed> $picture The answer's picture.
	 * @param int                  $kit     The kit product's ID.
	 * @return array{shown: bool, style: string, layers: list<array{key: string, classes: string, style: string}>}
	 */
	private static function top( array $picture, int $kit ): array {
		$width  = Cast::int( $picture['width'] ?? null );
		$height = Cast::int( $picture['height'] ?? null );
		$top    = $height > 0 && $width > 0 ? Cast::items( $picture['top'] ?? null ) : array();
		$mask   = '';
		foreach ( $top as $layer ) {
			$layer = Cast::map( $layer );
			if ( SlotKey::DECK === Cast::str( $layer['slot'] ?? null ) ) {
				$mask = self::url( self::address( Cast::map( $layer['image'] ?? null ) ) );
				break;
			}
		}
		$layers = array();
		$seen   = array();
		foreach ( '' === $mask ? array() : $top as $layer ) {
			$layer         = Cast::map( $layer );
			$slot          = Cast::str( $layer['slot'] ?? null );
			$style         = array( self::placed( $layer, $width, $height ) );
			$style[]       = in_array( $slot, array( SlotKey::DECK, SlotKey::GRIP ), true ) ? '--cbuild-mask:' . $mask : '';
			$style[]       = SlotKey::DECK === $slot ? '' : '--cbuild-image:' . self::url( self::address( Cast::map( $layer['image'] ?? null ) ) );
			$seen[ $slot ] = ( $seen[ $slot ] ?? 0 ) + 1;
			$layers[]      = array(
				'key'     => $slot . '-' . $seen[ $slot ],
				'classes' => self::flagged( array( Options::CLASS_LAYER, Options::CLASS_LAYER . '--' . $slot ), $kit, $slot, Cast::int( $layer['part'] ?? null ) ),
				'style'   => implode( ';', array_filter( $style, static fn( string $part ): bool => '' !== $part ) ),
			);
		}
		return array(
			'shown'  => array() !== $layers,
			'style'  => array() === $layers ? '' : '--cbuild-board-ratio:' . $width . ' / ' . $height,
			'layers' => $layers,
		);
	}

	/**
	 * A layer's place on the board, as shares of it.
	 *
	 * @param array<string, mixed> $layer  The layer.
	 * @param int                  $width  The board across.
	 * @param int                  $height The board along.
	 */
	private static function placed( array $layer, int $width, int $height ): string {
		return sprintf(
			'--cbuild-layer-x:%1$s%%;--cbuild-layer-y:%2$s%%;--cbuild-layer-width:%3$s%%;--cbuild-layer-height:%4$s%%',
			self::decimal( 100 * Cast::int( $layer['x'] ?? null ) / $width ),
			self::decimal( 100 * Cast::int( $layer['y'] ?? null ) / $height ),
			self::decimal( 100 * Cast::int( $layer['width'] ?? null ) / $width ),
			self::decimal( 100 * Cast::int( $layer['height'] ?? null ) / $height )
		);
	}

	/**
	 * One file of a layer image for a style: the largest WebP rendition made, or else AVIF's, or else its own file.
	 * A `srcset` lists its files smallest first.
	 *
	 * @param array<string, mixed> $image The layer's image.
	 */
	private static function address( array $image ): string {
		$sets = array_column( array_map( static fn( mixed $source ): array => Cast::map( $source ), Cast::items( $image['sources'] ?? null ) ), 'srcset', 'type' );
		foreach ( array( 'image/webp', 'image/avif' ) as $type ) {
			$files = explode( ', ', Cast::str( $sets[ $type ] ?? '' ) );
			$last  = explode( ' ', (string) end( $files ) )[0];
			if ( '' !== $last ) {
				return $last;
			}
		}
		return Cast::str( $image['src'] ?? null );
	}

	/**
	 * An address as a style's `url()`: quoted, with every character a URL does not need written as its escape, so
	 * no quote, backslash, space or line in it can end the string or the declaration.
	 *
	 * @param string $address The address.
	 */
	private static function url( string $address ): string {
		return 'url("' . preg_replace_callback( '/[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/', static fn( array $found ): string => rawurlencode( $found[0] ), $address ) . '")';
	}

	/**
	 * A number with at most four decimals, a point whatever the locale, for a style or a size.
	 *
	 * @param float $number The number.
	 */
	private static function decimal( float $number ): string {
		return rtrim( rtrim( sprintf( '%.4F', $number ), '0' ), '.' );
	}

	/**
	 * The hidden fields that carry picks in a form, under a name.
	 *
	 * @param string             $under The fields' name, before the slot's key.
	 * @param array<string, int> $picks Slot key to part ID.
	 * @param list<string>       $none  The slots left empty by choice.
	 * @param list<string>       $keys  The slots to write, in order.
	 * @return list<array{name: string, value: string}>
	 */
	private static function fields( string $under, array $picks, array $none, array $keys ): array {
		$fields = array();
		foreach ( $keys as $key ) {
			if ( isset( $picks[ $key ] ) || in_array( $key, $none, true ) ) {
				$fields[] = array(
					'name'  => $under . '[' . $key . ']',
					'value' => (string) ( $picks[ $key ] ?? 0 ),
				);
			}
		}
		return $fields;
	}

	/**
	 * A build's query as a form's hidden fields. Use these and Undo carry no picks before a change, so every value is
	 * text.
	 *
	 * @param array<string, mixed> $query The build's query.
	 * @return list<array{name: string, value: string}>
	 */
	private static function flat( array $query ): array {
		$fields = array();
		foreach ( $query as $key => $value ) {
			$fields[] = array(
				'name'  => BuildLink::PARAM . '[' . $key . ']',
				'value' => Cast::str( $value ),
			);
		}
		return $fields;
	}

	/**
	 * The step's part of the document on the review, where there is no step: every field there, and nothing shown.
	 *
	 * @param int $kit The kit product's ID.
	 * @return array<string, mixed>
	 */
	private static function none( int $kit ): array {
		return array(
			'slot'      => '',
			'legend'    => '',
			'help'      => '',
			'hasHelp'   => false,
			'lead'      => '',
			'hasLead'   => false,
			'name'      => '',
			'before'    => array(),
			'after'     => array(),
			'tail'      => array(),
			'options'   => array(),
			'hidden'    => self::hidden( array(), array() ),
			'filters'   => self::filters( array(), '' ),
			'more'      => self::more( array(), 0 ),
			'shelf'     => self::shelf( array(), '', array(), $kit ),
			'empty'     => false,
			'emptyText' => '',
			'found'     => self::found( array(), '', array() ),
			'leave'     => array(
				'shown'   => false,
				'id'      => '',
				'label'   => '',
				'checked' => false,
				'delta'   => '',
			),
			'back'      => array(
				'shown' => false,
				'url'   => '',
				'text'  => '',
			),
			'next'      => array(
				'value' => '',
				'text'  => '',
			),
			'stepName'  => '',
		);
	}

	/**
	 * The parts a step keeps out of its list, out of the list but not out of sight (spec: A step, "Nothing vanishes
	 * silently"): how many and why, said in one line, and each part with its reasons.
	 *
	 * @param array<string, mixed> $answer The state's answer.
	 * @param array<string, mixed> $step   The answer's step.
	 * @return array{shown: bool, summary: string, groups: list<array{text: string}>, items: list<array<string, mixed>>}
	 */
	private static function hidden( array $answer, array $step ): array {
		$groups = array();
		foreach ( Cast::items( $step['groups'] ?? null ) as $group ) {
			$groups[] = array( 'text' => Cast::str( Cast::map( $group )['message'] ?? null ) );
		}
		$count = Cast::int( $step['hidden'] ?? null );
		$items = array();
		foreach ( Cast::items( $answer['hidden'] ?? null ) as $part ) {
			$part    = Cast::map( $part );
			$card    = Cast::map( $part['card'] ?? null );
			$reasons = array();
			foreach ( Cast::items( $part['messages'] ?? null ) as $message ) {
				$reasons[] = array( 'text' => Cast::str( Cast::map( $message )['text'] ?? null ) );
			}
			$items[] = array(
				'id'      => Cast::int( $part['id'] ?? null ),
				'name'    => Cast::str( $card['name'] ?? null ),
				'url'     => Cast::str( $card['url'] ?? null ),
				'reasons' => $reasons,
			);
		}
		if ( 1 === count( $groups ) ) {
			// One reason is said once: its own sentence is the summary.
			return array(
				'shown'   => true,
				'summary' => $groups[0]['text'],
				'groups'  => array(),
				'items'   => $items,
			);
		}
		return array(
			'shown'   => $count > 0,
			'summary' => $count > 0
				/* translators: %s: a number of parts. */
				? sprintf( _n( '%s part hidden: it does not fit the parts before this step.', '%s parts hidden: they do not fit the parts before this step.', $count, 'creative-skateboard-builder' ), number_format_i18n( $count ) )
				: '',
			'groups'  => $groups,
			'items'   => $items,
		);
	}

	/**
	 * A step's filters (spec: A step, "Long lists get filters (brand, size) above them"): a button in the step's form
	 * for each brand and each size its parts have, with how many, and one to clear each. The deck's sizes come first:
	 * a shopper with no deck in mind starts with a width.
	 *
	 * @param array<string, mixed> $answer The state's answer.
	 * @param string               $slot   The step's slot.
	 * @return array{shown: bool, open: bool, heading: string, groups: list<array<string, mixed>>}
	 */
	private static function filters( array $answer, string $slot ): array {
		$filters = Cast::map( $answer['filters'] ?? null );
		$brand   = array(
			'key'    => 'brand',
			'legend' => __( 'Brand', 'creative-skateboard-builder' ),
			'name'   => BuildLink::PARAM . '[_brand]',
			'any'    => __( 'Any brand', 'creative-skateboard-builder' ),
			'values' => Cast::items( $filters['brands'] ?? null ),
		);
		$size    = array(
			'key'    => 'size',
			'legend' => StateAnswer::sizeName( $slot ),
			'name'   => BuildLink::PARAM . '[_size]',
			'any'    => match ( $slot ) {
				SlotKey::DECK => __( 'Any width', 'creative-skateboard-builder' ),
				default       => __( 'Any size', 'creative-skateboard-builder' ),
			},
			'values' => Cast::items( $filters['sizes'] ?? null ),
		);
		$groups = array();
		$open   = false;
		foreach ( SlotKey::DECK === $slot ? array( $size, $brand ) : array( $brand, $size ) as $group ) {
			if ( array() === $group['values'] ) {
				continue;
			}
			$options  = array();
			$selected = false;
			foreach ( $group['values'] as $value ) {
				$value     = Cast::map( $value );
				$pressed   = Cast::bool( $value['selected'] ?? null );
				$selected  = $selected || $pressed;
				$options[] = array(
					'value'   => Cast::str( $value['value'] ?? null ),
					/* translators: 1: a brand or a size, such as Independent or 8.25 in. 2: how many parts have it. */
					'text'    => sprintf( __( '%1$s (%2$s)', 'creative-skateboard-builder' ), Cast::str( $value['label'] ?? null ), number_format_i18n( Cast::int( $value['count'] ?? null ) ) ),
					'pressed' => $pressed ? 'true' : 'false',
				);
			}
			$open     = $open || $selected;
			$groups[] = array(
				'key'     => $group['key'],
				'legend'  => $group['legend'],
				'name'    => $group['name'],
				'options' => $options,
				'clear'   => array(
					'shown' => $selected,
					'text'  => $group['any'],
				),
			);
		}
		return array(
			'shown'   => array() !== $groups,
			// A drawer on phones (spec: A step), closed until a filter is pressed, so the shopper sees which; wider,
			// the styles show its content whether it is open or not (audit 21).
			'open'    => $open,
			'heading' => __( 'Filter the parts', 'creative-skateboard-builder' ),
			'groups'  => $groups,
		);
	}

	/**
	 * Show more (spec: Performance, "A slot shows 24 options at a time, with Show more"): a button in the step's form
	 * that asks for the next 24, and how many are shown of how many match.
	 *
	 * @param array<string, mixed> $answer The state's answer.
	 * @param int                  $listed How many options the step lists.
	 * @return array{shown: bool, name: string, value: string, text: string, count: string, added: string}
	 */
	private static function more( array $answer, int $listed ): array {
		$matching = Cast::int( $answer['matching'] ?? null );
		$more     = $answer['more'] ?? null;
		// The answer asks for the next page whole; the button says, and asks for, only what is left.
		$more = null === $more ? null : min( Cast::int( $more ), $matching );
		return array(
			'shown' => null !== $more,
			'name'  => BuildLink::PARAM . '[_shown]',
			'value' => null === $more ? '' : (string) $more,
			/* translators: %s: how many more the button lists: parts, or looks on the shelf. */
			'text'  => null === $more ? '' : sprintf( __( 'Show %s more', 'creative-skateboard-builder' ), number_format_i18n( $more - $listed ) ),
			/* translators: 1: how many parts are listed. 2: how many match. */
			'count' => sprintf( _n( '%1$s of %2$s part shown', '%1$s of %2$s parts shown', $matching, 'creative-skateboard-builder' ), number_format_i18n( $listed ), number_format_i18n( $matching ) ),
			'added' => '',
		);
	}

	/**
	 * Show 30 more from the shelf (D-244): a button that asks the link to list 30 more, and how many are shown of
	 * how many in stock match. After a Show more, what it added, for the status region (spec: Accessibility).
	 *
	 * @param array<string, mixed> $shelf  The answer's shelf.
	 * @param int                  $listed How many looks the step lists.
	 * @param BuildLink            $link   The address of what is shown.
	 * @return array{shown: bool, name: string, value: string, text: string, count: string, added: string}
	 */
	private static function page( array $shelf, int $listed, BuildLink $link ): array {
		$matching = Cast::int( $shelf['matching'] ?? null );
		$more     = null === ( $shelf['more'] ?? null ) ? null : Cast::int( $shelf['more'] );
		$shown    = max( Shelf::SHOWN, $link->shown );
		// What the last Show more added: those listed past the page before it.
		$added = $listed - min( $shown - Shelf::SHOWN, $matching );
		return array(
			'shown' => null !== $more,
			'name'  => BuildLink::PARAM . '[_shown]',
			'value' => null === $more ? '' : (string) ( $shown + Shelf::SHOWN ),
			/* translators: %s: how many more the button lists: parts, or looks on the shelf. */
			'text'  => null === $more ? '' : sprintf( __( 'Show %s more', 'creative-skateboard-builder' ), number_format_i18n( min( Shelf::SHOWN, $matching - $listed ) ) ),
			/* translators: 1: how many looks are listed. 2: how many match. */
			'count' => sprintf( _n( 'Showing %1$s of %2$s', 'Showing %1$s of %2$s', $matching, 'creative-skateboard-builder' ), number_format_i18n( $listed ), number_format_i18n( $matching ) ),
			/* translators: 1: how many Show more added. 2: how many are listed. 3: how many match. No noun: decks, wheels and so on (#84). */
			'added' => $shown > Shelf::SHOWN && $added > 0 ? sprintf( _n( '%1$s more shown, %2$s of %3$s.', '%1$s more shown, %2$s of %3$s.', $added, 'creative-skateboard-builder' ), number_format_i18n( $added ), number_format_i18n( $listed ), number_format_i18n( $matching ) ) : '',
		);
	}

	/**
	 * The search box's hint, in the step's own words: its first brand as the example, where it has brands (spec:
	 * Search, "Brand or graphic, like Rascals").
	 *
	 * @param string                     $slot   The step's slot.
	 * @param list<array<string, mixed>> $groups The step's chip groups, as the view has them.
	 */
	private static function hint( string $slot, array $groups ): string {
		$example = '';
		foreach ( $groups as $group ) {
			if ( Shelf::BRAND === ( $group['key'] ?? null ) ) {
				$example = Cast::str( Cast::map( Cast::items( $group['inputs'] ?? null )[0] ?? null )['text'] ?? null );
			}
		}
		if ( '' === $example ) {
			return SlotKey::DECK === $slot ? __( 'Brand or graphic', 'creative-skateboard-builder' ) : __( 'Brand or name', 'creative-skateboard-builder' );
		}
		return SlotKey::DECK === $slot
			/* translators: %s: a brand the step sells, such as Rascals. */
			? sprintf( __( 'Brand or graphic, like %s', 'creative-skateboard-builder' ), $example )
			/* translators: %s: a brand the step sells, such as Bones. */
			: sprintf( __( 'Brand or name, like %s', 'creative-skateboard-builder' ), $example );
	}

	/**
	 * The step's shelf tools (SPEC-CONFIGURATOR, Finding a part): the deck's width question, then search, the chips
	 * and Sort as the step's offered looks grow, and the sold-out fold. Each is a field of the step's GET form.
	 *
	 * @param array<string, mixed>       $shelf  The answer's shelf, or nothing where there is none.
	 * @param string                     $slot   The step's slot.
	 * @param list<array<string, mixed>> $folded The sold-out looks' cards.
	 * @param int                        $kit    The kit product's ID, for the width guide's filter.
	 * @return array<string, mixed>
	 */
	private static function shelf( array $shelf, string $slot, array $folded, int $kit ): array {
		$offered = Cast::int( $shelf['offered'] ?? null );
		$widths  = array();
		$none    = array();
		$asking  = true;
		$chosen  = '';
		foreach ( Cast::items( $shelf['widths'] ?? null ) as $width ) {
			$width    = Cast::map( $width );
			$stock    = Cast::int( $width['in_stock'] ?? null );
			$pressed  = Cast::bool( $width['selected'] ?? null );
			$asking   = $asking && ! $pressed;
			$chosen   = $pressed ? Cast::str( $width['text'] ?? null ) : $chosen;
			$widths[] = array(
				'value'   => Cast::str( $width['value'] ?? null ),
				'text'    => Cast::str( $width['text'] ?? null ),
				'count'   => number_format_i18n( $stock ),
				// The button's name, as words: "8.25″, 12 in stock" (the auditor's M10 review, 13).
				/* translators: 1: a deck width, such as 8.25″. 2: how many decks of that width are in stock. */
				'said'    => sprintf( _n( '%1$s, %2$s in stock', '%1$s, %2$s in stock', $stock, 'creative-skateboard-builder' ), Cast::str( $width['text'] ?? null ), number_format_i18n( $stock ) ),
				'pressed' => $pressed ? 'true' : 'false',
				'dimmed'  => 0 === $stock,
			);
			if ( 0 === $stock ) {
				$none[] = Cast::str( $width['text'] ?? null );
			}
		}
		$groups = array();
		foreach ( Cast::items( $shelf['chips'] ?? null ) as $group ) {
			$group  = Cast::map( $group );
			$name   = Cast::str( $group['name'] ?? null );
			$inputs = array();
			foreach ( Cast::items( $group['chips'] ?? null ) as $at => $chip ) {
				$chip     = Cast::map( $chip );
				$count    = Cast::int( $chip['count'] ?? null );
				$inputs[] = array(
					/* translators: 1: a chip, such as a brand. 2: how many it leaves: decks, wheels and so on (#84). */
					'said'    => sprintf( _x( '%1$s, %2$s', 'a chip and how many it lists', 'creative-skateboard-builder' ), Cast::str( $chip['label'] ?? null ), number_format_i18n( $count ) ),
					'id'      => 'cbuild-chip-' . $slot . '-' . $name . '-' . $at,
					'name'    => BuildLink::PARAM . '[_chips][' . $name . '][]',
					'value'   => Cast::str( $chip['value'] ?? null ),
					'text'    => Cast::str( $chip['label'] ?? null ),
					'count'   => number_format_i18n( Cast::int( $chip['count'] ?? null ) ),
					'checked' => Cast::bool( $chip['selected'] ?? null ),
				);
			}
			$groups[] = array(
				'key'    => $name,
				'legend' => Cast::str( $group['label'] ?? null ),
				'inputs' => $inputs,
			);
		}
		$sort  = Cast::str( $shelf['sort'] ?? null );
		$sorts = array(
			'recommended' => __( 'Recommended', 'creative-skateboard-builder' ),
			'price-asc'   => __( 'Price low to high', 'creative-skateboard-builder' ),
			'price-desc'  => __( 'Price high to low', 'creative-skateboard-builder' ),
			'newest'      => __( 'Newest', 'creative-skateboard-builder' ),
		);
		// Asking only where there is a question: a step with no widths lists its cards.
		$asking = $asking && array() !== $widths;
		$tools  = $offered > self::CARDS_ALONE;
		// "Not sure of your width?" under the question, asked only where there is a question (spec: The deck's width).
		$guide = array() === $widths ? array() : WidthGuide::rows( $kit );
		return array(
			'width'   => array(
				'shown'   => array() !== $widths,
				'asking'  => $asking,
				'legend'  => $asking ? __( 'What width do you ride?', 'creative-skateboard-builder' ) : __( 'Width', 'creative-skateboard-builder' ),
				// Once chosen, the width is the step's first line, with Change, which opens the widths again (spec: The
				// deck's width first; the spec reviewer's M10 minor M1).
				'chosen'  => $chosen,
				'change'  => __( 'Change', 'creative-skateboard-builder' ),
				'name'    => BuildLink::PARAM . '[_width]',
				'options' => $widths,
				'hasNone' => array() !== $none,
				/* translators: %s: widths, such as 7.75″, 8.125″ or 9″. */
				'none'    => array() === $none ? '' : sprintf( __( 'Nothing in %s is in stock right now.', 'creative-skateboard-builder' ), Words::either( $none ) ),
				'guide'   => array(
					'shown'   => array() !== $guide,
					'summary' => __( 'Not sure of your width?', 'creative-skateboard-builder' ),
					'caption' => __( 'Deck width by shoe size', 'creative-skateboard-builder' ),
					'shoe'    => __( 'Shoe size (US)', 'creative-skateboard-builder' ),
					'width'   => __( 'Deck width', 'creative-skateboard-builder' ),
					'rows'    => $guide,
				),
			),
			'search'  => array(
				'shown'  => $offered > self::SEARCHED,
				'label'  => self::searchLabel( $slot ),
				'hint'   => self::hint( $slot, $groups ),
				'name'   => BuildLink::PARAM . '[_q]',
				'value'  => Cast::str( $shelf['query'] ?? null ),
				'button' => __( 'Search', 'creative-skateboard-builder' ),
			),
			'chips'   => array(
				'shown'  => $tools && array() !== $groups,
				'groups' => $groups,
				'apply'  => __( 'Apply', 'creative-skateboard-builder' ),
				// A phone folds the chips behind this button (spec: Phone); it says how many narrow the cards.
				'toggle' => self::toggle( $groups ),
			),
			'sort'    => array(
				'shown'   => $tools,
				'label'   => __( 'Sort', 'creative-skateboard-builder' ),
				'name'    => BuildLink::PARAM . '[_sort]',
				'options' => array_map(
					static fn( string $value, string $text ): array => array(
						'value'    => $value,
						'text'     => $text,
						'selected' => $value === $sort || ( '' === $sort && 'recommended' === $value ),
					),
					array_keys( $sorts ),
					$sorts
				),
			),
			'soldOut' => array(
				'shown'   => array() !== $folded,
				/* translators: %s: how many sold-out looks the fold holds. */
				'summary' => sprintf( _n( 'Show %s sold out', 'Show %s sold out', count( $folded ), 'creative-skateboard-builder' ), number_format_i18n( count( $folded ) ) ),
				'options' => $folded,
			),
		);
	}

	/**
	 * The width the deck step's shelf lists, as the `width` argument names it, or empty: the one asked, or the one
	 * of the deck the build holds.
	 *
	 * @param array<string, mixed> $shelf The answer's shelf.
	 */
	private static function shown( array $shelf ): string {
		foreach ( Cast::items( $shelf['widths'] ?? null ) as $width ) {
			$width = Cast::map( $width );
			if ( Cast::bool( $width['selected'] ?? null ) ) {
				return Cast::str( $width['value'] ?? null );
			}
		}
		return '';
	}

	/**
	 * A step's question, as its heading shows it (D-269). A slot of a kind the plugin does not name asks with its own
	 * name.
	 *
	 * @param string $slot The step's slot.
	 * @param string $name The step's name, as the kit gives it.
	 */
	private static function question( string $slot, string $name ): string {
		return match ( $slot ) {
			SlotKey::DECK     => __( 'Choose your deck', 'creative-skateboard-builder' ),
			SlotKey::TRUCKS   => __( 'Choose your trucks', 'creative-skateboard-builder' ),
			SlotKey::WHEELS   => __( 'Choose your wheels', 'creative-skateboard-builder' ),
			SlotKey::BEARINGS => __( 'Choose your bearings', 'creative-skateboard-builder' ),
			SlotKey::RISERS   => __( 'Choose your risers', 'creative-skateboard-builder' ),
			SlotKey::HARDWARE => __( 'Choose your hardware', 'creative-skateboard-builder' ),
			SlotKey::GRIP     => __( 'Choose your grip tape', 'creative-skateboard-builder' ),
			default           => $name,
		};
	}

	/**
	 * What a search that lists nothing says, and the way on (spec: Search, "When nothing is found"): the widths its
	 * matches come in, with a button to each; or how many parts that don't fit match, with a link to why; or that
	 * nothing matches, with Clear search. Shown only where a search lists nothing.
	 *
	 * @param array<string, mixed> $shelf   The answer's shelf, or nothing where there is none.
	 * @param string               $slot    The step's slot.
	 * @param list<mixed>          $options The cards listed.
	 * @return array<string, mixed>
	 */
	private static function found( array $shelf, string $slot, array $options ): array {
		$query     = Cast::str( $shelf['query'] ?? null );
		$shown     = '' !== $query && array() === $options && array() === Cast::items( $shelf['sold_out'] ?? null );
		$elsewhere = array();
		foreach ( $shown ? Cast::items( $shelf['elsewhere'] ?? null ) : array() as $width ) {
			$width       = Cast::map( $width );
			$elsewhere[] = array(
				'value' => Cast::str( $width['value'] ?? null ),
				'text'  => Cast::str( $width['text'] ?? null ),
				/* translators: %s: a deck width, such as 8.5″. */
				'label' => sprintf( __( 'Show %s decks', 'creative-skateboard-builder' ), Cast::str( $width['text'] ?? null ) ),
			);
		}
		$asked = '';
		foreach ( Cast::items( $shelf['widths'] ?? null ) as $width ) {
			$width = Cast::map( $width );
			if ( true === ( $width['selected'] ?? null ) ) {
				$asked = Cast::str( $width['text'] ?? null );
			}
		}
		$hidden = Cast::int( $shelf['hidden_matching'] ?? null );
		// Parts that match but don't fit are said only where no other width has the search's matches.
		$why = $shown && array() === $elsewhere && $hidden > 0;
		if ( ! $shown ) {
			$text = '';
		} elseif ( array() !== $elsewhere ) {
			/* translators: 1: a deck width, such as 8.25″. 2: what the shopper searched for. */
			$text = sprintf( __( 'No %1$s decks match %2$s.', 'creative-skateboard-builder' ), $asked, $query ) . ' '
				/* translators: 1: what the shopper searched for. 2: deck widths, such as 8″ or 8.5″. */
				. sprintf( __( '%1$s comes in %2$s.', 'creative-skateboard-builder' ), $query, Words::either( array_column( $elsewhere, 'text' ) ) );
		} elseif ( $why ) {
			/* translators: %s: how many parts match the search. */
			$text = sprintf( _n( '%s part matches, but it doesn’t fit your build.', '%s parts match, but they don’t fit your build.', $hidden, 'creative-skateboard-builder' ), number_format_i18n( $hidden ) );
		} elseif ( SlotKey::DECK === $slot && '' !== $asked ) {
			/* translators: 1: a deck width, such as 8.25″. 2: what the shopper searched for. */
			$text = sprintf( __( 'No %1$s decks match %2$s.', 'creative-skateboard-builder' ), $asked, $query );
		} else {
			$text = self::noMatch( $slot, $query );
		}
		return array(
			'shown'     => $shown,
			'text'      => $text,
			'widths'    => $elsewhere,
			'hasWidths' => array() !== $elsewhere,
			'why'       => array(
				'shown' => $why,
				'text'  => __( 'See why', 'creative-skateboard-builder' ),
			),
			'clear'     => array(
				'shown' => $shown && array() === $elsewhere && 0 === $hidden,
				'text'  => __( 'Clear search', 'creative-skateboard-builder' ),
				'name'  => BuildLink::PARAM . '[_q]',
			),
		);
	}

	/**
	 * "No decks match Rascals.", in the step's own words.
	 *
	 * @param string $slot  The step's slot.
	 * @param string $query What the shopper searched for.
	 */
	private static function noMatch( string $slot, string $query ): string {
		return sprintf(
			match ( $slot ) {
				/* translators: %s: what the shopper searched for. */
				SlotKey::DECK     => __( 'No decks match %s.', 'creative-skateboard-builder' ),
				/* translators: %s: what the shopper searched for. */
				SlotKey::TRUCKS   => __( 'No trucks match %s.', 'creative-skateboard-builder' ),
				/* translators: %s: what the shopper searched for. */
				SlotKey::WHEELS   => __( 'No wheels match %s.', 'creative-skateboard-builder' ),
				/* translators: %s: what the shopper searched for. */
				SlotKey::BEARINGS => __( 'No bearings match %s.', 'creative-skateboard-builder' ),
				/* translators: %s: what the shopper searched for. */
				SlotKey::RISERS   => __( 'No risers match %s.', 'creative-skateboard-builder' ),
				/* translators: %s: what the shopper searched for. */
				SlotKey::HARDWARE => __( 'No hardware matches %s.', 'creative-skateboard-builder' ),
				/* translators: %s: what the shopper searched for. */
				SlotKey::GRIP     => __( 'No grip tape matches %s.', 'creative-skateboard-builder' ),
				/* translators: %s: what the shopper searched for. */
				default           => __( 'No parts match %s.', 'creative-skateboard-builder' ),
			},
			$query
		);
	}

	/**
	 * What a step's search box is called.
	 *
	 * @param string $slot The step's slot.
	 */
	private static function searchLabel( string $slot ): string {
		return match ( $slot ) {
			SlotKey::DECK     => __( 'Search decks', 'creative-skateboard-builder' ),
			SlotKey::TRUCKS   => __( 'Search trucks', 'creative-skateboard-builder' ),
			SlotKey::WHEELS   => __( 'Search wheels', 'creative-skateboard-builder' ),
			SlotKey::BEARINGS => __( 'Search bearings', 'creative-skateboard-builder' ),
			SlotKey::RISERS   => __( 'Search risers', 'creative-skateboard-builder' ),
			SlotKey::HARDWARE => __( 'Search hardware', 'creative-skateboard-builder' ),
			SlotKey::GRIP     => __( 'Search grip tape', 'creative-skateboard-builder' ),
			default           => __( 'Search parts', 'creative-skateboard-builder' ),
		};
	}

	/**
	 * The step: its form's fields, its options as cards, and where Back and Next go.
	 *
	 * @param State                               $state   The build's state.
	 * @param array<string, mixed>                $answer  The state's answer.
	 * @param BuildLink                           $link    The address of what is shown.
	 * @param callable(BuildLink): string         $url     Makes an address.
	 * @param list<string>                        $steps   The slots that are steps.
	 * @param array<string, int>                  $counted The part the total counts in each slot.
	 * @param array<string, array<string, mixed>> $lines   The total's lines, by slot.
	 * @param array<string, array<string, mixed>> $about   The answer's steps, by slot.
	 * @return array<string, mixed>
	 */
	private function step( State $state, array $answer, BuildLink $link, callable $url, array $steps, array $counted, array $lines, array $about ): array {
		$slot  = $state->step;
		$step  = $about[ $slot ] ?? array();
		$keys  = $state->kit->slots->keys();
		$place = array_flip( $steps )[ $slot ];
		$next  = $steps[ $place + 1 ] ?? State::REVIEW;
		$width = SlotKey::DECK === $slot ? '' : self::deckWidth( $answer );
		// What the step's parts were checked against, as a fitting part's two labels.
		$against = self::against( Cast::strings( $step['against'] ?? null ), $width, static fn( string $key ): string => Cast::str( Cast::map( $about[ $key ] ?? null )['label'] ?? $key ) );

		// The width asked, for a picked deck its look is not made in, whose card says so (#81).
		$asked = null;
		foreach ( Cast::items( Cast::map( $answer['shelf'] ?? null )['widths'] ?? null ) as $offered ) {
			$asked = Cast::bool( Cast::map( $offered )['selected'] ?? null ) ? Cast::map( $offered ) : $asked;
		}
		$card    = function ( array $option, array $look = array() ) use ( $state, $slot, $counted, $against, $asked ): array {
			$id   = Cast::int( $option['id'] ?? null );
			$card = Cast::map( $option['card'] ?? null );
			// A look's card names its product, not a size of it, and the look by what tells it from the product's
			// others (D-273); a part listed alone keeps its own name.
			$product = Cast::str( $look['name'] ?? null );
			$name    = '' !== $product ? $product : Cast::str( $card['name'] ?? null );
			$seen    = implode( _x( ', ', 'between values in a list', 'creative-skateboard-builder' ), Cast::strings( $look['look'] ?? null ) );
			/* translators: 1: a product's name, such as Katie. 2: what tells one look of it from another, such as Mint. */
			$title  = '' === $seen ? $name : sprintf( __( '%1$s, %2$s', 'creative-skateboard-builder' ), $name, $seen );
			$brand  = Cast::str( $look['brand'] ?? null );
			$size   = Cast::str( Cast::map( $option['size'] ?? null )['text'] ?? null );
			$others = array();
			foreach ( Cast::items( $look['sizes'] ?? null ) as $other ) {
				$text = Cast::str( Cast::map( $other )['text'] ?? null );
				if ( '' !== $text && $text !== $size ) {
					$others[] = $text;
				}
			}
			$also      = SlotKey::DECK === $slot ? Words::also( $others ) : '';
			$change    = Cast::str( Cast::map( $option['delta'] ?? null )['change'] ?? null );
			$checked   = ( $counted[ $slot ] ?? null ) === $id;
			$available = Cast::bool( $option['available'] ?? null );
			$fit       = Cast::str( $option['fit'] ?? null );
			$notes     = array();
			$short     = '';
			foreach ( Cast::items( $option['messages'] ?? null ) as $message ) {
				$message = Cast::map( $message );
				$short   = '' === $short ? Cast::str( $message['short'] ?? null ) : $short;
				// A spec the owner left out and the rule assumed is the owner's to fix, and the catalogue check says so. A
				// card that fits keeps quiet about it; one that warns or is blocked says it, since it may be the reason.
				if ( 'fits' === $fit && 'fits' === Cast::str( $message['severity'] ?? null ) ) {
					continue;
				}
				$notes[] = array( 'text' => Cast::str( $message['text'] ?? null ) );
			}
			if ( $checked && null !== $asked && SlotKey::DECK === $slot && Cast::str( Cast::map( $option['size'] ?? null )['value'] ?? null ) !== Cast::str( $asked['value'] ?? null ) ) {
				/* translators: 1: the picked deck's width, such as 8.25″. 2: the width asked, such as 8.5″. */
				$notes[] = array( 'text' => sprintf( __( 'Picked in %1$s. Not made in %2$s.', 'creative-skateboard-builder' ), $size, Cast::str( $asked['text'] ?? null ) ) );
			}
			$input = 'cbuild-' . $state->id . '-' . $id;
			// The picked card's sizes, where the shopper chooses one: radios of the step's own field, after the card. The
			// card's own radio then stands aside, so that one radio of the step is checked (D-279).
			$field  = BuildLink::PARAM . '[' . $slot . ']';
			$inputs = array();
			foreach ( Cast::items( $look['sized'] ?? null ) as $sized ) {
				$sized    = Cast::map( $sized );
				$part     = Cast::int( $sized['part'] ?? null );
				$inStock  = Cast::bool( $sized['in_stock'] ?? null );
				$picked   = Cast::bool( $sized['picked'] ?? null );
				$inputs[] = array(
					'id'       => $input . '-size-' . $part,
					'name'     => $field,
					'value'    => (string) $part,
					'text'     => Cast::str( $sized['text'] ?? null ),
					'checked'  => $picked,
					'disabled' => ! $inStock && ! $picked,
					/* translators: %s: a size, such as 54 mm. */
					'label'    => $inStock ? Cast::str( $sized['text'] ?? null ) : sprintf( __( '%s, sold out', 'creative-skateboard-builder' ), Cast::str( $sized['text'] ?? null ) ),
				);
			}
			$grouped = $checked && array() !== $inputs;
			return array(
				'id'            => $id,
				'input'         => $input,
				// The radio is named by its part alone; what else the card says describes it, so that a screen reader
				// says the price, stock, fit and notes after the name (spec: Accessibility).
				'about'         => $input . '-about',
				'notesId'       => $input . '-notes',
				// The brand, shown above the name, is said first of what describes it (D-273).
				'brandId'       => $input . '-brand',
				'describedby'   => ( '' === $brand ? '' : $input . '-brand ' ) . $input . '-about ' . $input . '-notes',
				'value'         => (string) $id,
				'brand'         => $brand,
				'hasBrand'      => '' !== $brand,
				'name'          => $name,
				'look'          => $seen,
				'hasLook'       => '' !== $seen,
				'price'         => Cast::str( $card['price'] ?? null ),
				/* translators: %s: a part's name. */
				'choose'        => sprintf( __( 'Choose %s', 'creative-skateboard-builder' ), $title ),
				'checked'       => $checked,
				'field'         => $grouped ? '' : $field,
				'hasSizes'      => $grouped,
				'sizeGroup'     => array(
					/* translators: %s: a look's name, such as Retna Cruiser, Mint. */
					'legend' => sprintf( __( 'Size for %s', 'creative-skateboard-builder' ), $title ),
					'inputs' => $grouped ? $inputs : array(),
				),
				// A part the shop cannot supply cannot be picked. One a link or a preset brought stays in the form, so
				// that the build still names it until the shopper swaps it.
				'disabled'      => ! $available && ! $checked,
				'soldOut'       => ! $available,
				'url'           => Cast::str( $card['url'] ?? null ),
				/* translators: %s: a part's name. */
				'page'          => sprintf( __( 'See the page for %s', 'creative-skateboard-builder' ), $title ),
				// Under the full price, once the step has a pick (D-249).
				'delta'         => $change,
				'hasDelta'      => '' !== $change,
				'also'          => $also,
				'hasAlso'       => '' !== $also,
				'stock'         => Cast::str( $card['stock'] ?? null ),
				'stockClass'    => 'cbuild-card__stock cbuild-card__stock--' . sanitize_html_class( Cast::str( $card['stock_class'] ?? null ) ),
				// A warning with a short label of its own says it on the card, as "Needs ⅛″ risers with your trucks".
				'fit'           => 'warn' === $fit && '' !== $short ? $short : self::fit( $fit, $against['fits'] ),
				'fitClass'      => 'cbuild-card__fit cbuild-card__fit--' . sanitize_html_class( $fit ),
				'notes'         => $notes,
				'recommended'   => Cast::bool( $option['recommended'] ?? null ),
				'recommendedAs' => $against['recommended'],
				'classes'       => self::classes( $state->id, $slot, $id, $fit, $available, $checked ),
			) + self::image( $card['image'] ?? null );
		};
		$shelf   = isset( $answer['shelf'] ) ? Cast::map( $answer['shelf'] ) : null;
		$options = array();
		$folded  = array();
		if ( null === $shelf ) {
			foreach ( Cast::items( $answer['options'] ?? null ) as $option ) {
				$options[] = $card( Cast::map( $option ) );
			}
		} else {
			foreach ( Cast::items( $shelf['looks'] ?? null ) as $look ) {
				$look      = Cast::map( $look );
				$options[] = $card( Cast::map( $look['part'] ?? null ), $look );
			}
			foreach ( Cast::items( $shelf['sold_out'] ?? null ) as $look ) {
				$look     = Cast::map( $look );
				$folded[] = $card( Cast::map( $look['part'] ?? null ), $look );
			}
		}

		$required = Cast::bool( $step['required'] ?? null );
		$found    = self::found( $shelf ?? array(), $slot, $options );
		$line     = Cast::map( $lines[ $slot ] ?? null );
		$before   = $state->kit->slots->before( $slot );
		$after    = $state->kit->slots->after( $slot );
		// Where the step has filters or Show more, the step the form is on, so that they keep the shopper on it and Next,
		// which names the next, leaves them behind (D-150).
		$tail = array();
		if ( null !== $shelf || self::filters( $answer, $slot )['shown'] || null !== ( $answer['more'] ?? null ) ) {
			$tail[] = array(
				'name'  => BuildLink::PARAM . '[_on]',
				'value' => $slot,
			);
		}
		foreach ( array(
			'_brand' => $link->brand,
			'_size'  => $link->size,
			// The width every step keeps (D-242), before the deck step's width buttons, so one pressed comes after it.
			'_width' => '' !== $link->shelf->width ? $link->shelf->width : self::shown( $shelf ?? array() ),
		) as $key => $value ) {
			if ( '' !== $value ) {
				$tail[] = array(
					'name'  => BuildLink::PARAM . '[' . $key . ']',
					'value' => $value,
				);
			}
		}
		if ( null !== $link->assembly ) {
			$tail[] = array(
				'name'  => BuildLink::PARAM . '[assembly]',
				'value' => $link->assembly ? '1' : '0',
			);
		}
		if ( null !== $link->preset ) {
			$tail[] = array(
				'name'  => BuildLink::PARAM . '[preset]',
				'value' => (string) $link->preset,
			);
		}
		// The picks as they are, for the server to say what the step's pick does to the ones after it.
		$tail = array_merge( $tail, self::fields( BuildLink::PARAM . '[_was]', $link->picks, array(), $keys ) );
		if ( '' !== $link->replace ) {
			$tail[] = array(
				'name'  => BuildLink::PARAM . '[_replace]',
				'value' => $link->replace,
			);
		}
		$back = $place > 0 ? $steps[ $place - 1 ] : null;
		// The risers step says why it appeared: the wheels picked are over what the trucks clear (R3).
		$lead = '';
		foreach ( SlotKey::RISERS === $slot ? $state->steps : array() as $held ) {
			foreach ( $held->counted()->findings ?? array() as $finding ) {
				$lead = '' === $lead ? Words::riserLead( $finding ) : $lead;
			}
		}
		return array(
			'slot'      => $slot,
			'legend'    => Cast::str( $step['label'] ?? $slot ),
			'help'      => Cast::str( $step['help'] ?? null ),
			'hasHelp'   => '' !== Cast::str( $step['help'] ?? null ),
			'lead'      => '' !== $lead ? $lead : __( 'Start with a width. Every later step offers only what fits the deck you choose.', 'creative-skateboard-builder' ),
			'hasLead'   => '' !== $lead || ( SlotKey::DECK === $slot && ! isset( $counted[ $slot ] ) ),
			'name'      => BuildLink::PARAM . '[' . $slot . ']',
			'before'    => self::fields( BuildLink::PARAM, $link->picks, $link->none, $before ),
			'after'     => self::fields( BuildLink::PARAM, $link->picks, $link->none, $after ),
			'tail'      => $tail,
			'options'   => $options,
			'hidden'    => self::hidden( $answer, $step ),
			'filters'   => null === $shelf ? self::filters( $answer, $slot ) : self::filters( array(), '' ),
			'more'      => null === $shelf ? self::more( $answer, count( $options ) ) : self::page( $shelf, count( $options ), $link ),
			'shelf'     => self::shelf( $shelf ?? array(), $slot, $folded, $state->id ),
			// The deck step asking its width lists nothing yet, and says nothing is missing; a search that lists nothing
			// says so itself, so the fit rule is not given as the reason (#83).
			'empty'     => array() === $options && ! ( null !== $shelf && SlotKey::DECK === $slot && '' === $link->shelf->width ) && true !== $found['shown'],
			'emptyText' => '' === $link->brand && '' === $link->size && array() === $link->shelf->chips
				? __( 'Nothing the shop has fits the parts before this step.', 'creative-skateboard-builder' )
				: __( 'No part matches these filters.', 'creative-skateboard-builder' ),
			'found'     => $found,
			'leave'     => array(
				'shown'   => ! $required,
				'id'      => 'cbuild-' . $state->id . '-' . $slot . '-none',
				'label'   => SlotKey::RISERS === $slot ? __( 'No risers', 'creative-skateboard-builder' ) : __( 'None', 'creative-skateboard-builder' ),
				// Checked only where it is offered: a hidden one would be the group's checked radio, and Safari tabs to that alone.
				'checked' => ! $required && ! isset( $counted[ $slot ] ),
				'delta'   => Words::delta( isset( $counted[ $slot ] ) ? 'less' : 'included', Cast::str( $line['amount'] ?? null ) ),
			),
			'back'      => array(
				'shown' => null !== $back,
				'url'   => null === $back ? '' : $url( $link->at( $back ) ),
				/* translators: %s: a step's name, such as Deck. */
				'text'  => null === $back ? '' : sprintf( __( 'Back: %s', 'creative-skateboard-builder' ), Cast::str( $about[ $back ]['label'] ?? $back ) ),
			),
			'next'      => array(
				'value' => $next,
				/* translators: %s: the next step's name, such as Wheels. */
				'text'  => sprintf( __( 'Next: %s', 'creative-skateboard-builder' ), State::REVIEW === $next ? __( 'Review', 'creative-skateboard-builder' ) : Cast::str( $about[ $next ]['label'] ?? $next ) ),
			),
			'stepName'  => BuildLink::PARAM . '[step]',
		);
	}

	/**
	 * The width of the deck the build counts, as a person reads it, or an empty string. The picture holds each part
	 * the total counts, with its size.
	 *
	 * @param array<string, mixed> $answer The state's answer.
	 */
	private static function deckWidth( array $answer ): string {
		$sizes = array();
		foreach ( Cast::items( Cast::map( $answer['picture'] ?? null )['items'] ?? null ) as $item ) {
			$item                                        = Cast::map( $item );
			$sizes[ Cast::str( $item['slot'] ?? null ) ] = Cast::str( $item['size'] ?? null );
		}
		return $sizes[ SlotKey::DECK ] ?? '';
	}

	/**
	 * What a step's parts were checked against, as a fitting part's labels: the deck by its width, "Fits your 8.25″
	 * deck", and other slots by their labels inside a sentence, "Fits your trucks and risers". Each case is a sentence
	 * of its own, so a translation can give each its own determiner (Cowork's review of 0.9.0, #65). Where the parts
	 * were checked against nothing picked before them, no fit label and plain "Recommended" (Cowork's review,
	 * should-fix 5; spec: A step, P9).
	 *
	 * @param list<string>             $slots The slots' keys.
	 * @param string                   $width The deck's width, or empty.
	 * @param callable(string): string $label A slot's label.
	 * @return array{fits: string, recommended: string}
	 */
	private static function against( array $slots, string $width, callable $label ): array {
		if ( array( SlotKey::DECK ) === $slots && '' !== $width ) {
			return array(
				/* translators: %s: a deck's width, such as 8.25″. */
				'fits'        => sprintf( __( 'Fits your %s deck', 'creative-skateboard-builder' ), $width ),
				/* translators: %s: a deck's width, such as 8.25″. */
				'recommended' => sprintf( __( 'Recommended for your %s deck', 'creative-skateboard-builder' ), $width ),
			);
		}
		if ( array() === $slots ) {
			return array(
				'fits'        => '',
				'recommended' => __( 'Recommended', 'creative-skateboard-builder' ),
			);
		}
		$parts = wp_sprintf( '%l', array_map( static fn( string $slot ): string => Words::inSentence( $label( $slot ) ), $slots ) );
		return array(
			/* translators: %s: the parts picked before, such as "trucks and risers". */
			'fits'        => sprintf( __( 'Fits your %s', 'creative-skateboard-builder' ), $parts ),
			/* translators: %s: the parts picked before, such as "trucks and risers". */
			'recommended' => sprintf( __( 'Recommended for your %s', 'creative-skateboard-builder' ), $parts ),
		);
	}

	/**
	 * A card's fit label (spec: A step). A part that fits says what it was checked against, and nothing where it was
	 * checked against nothing; one that warns or is blocked says so shortly, and its notes say why.
	 *
	 * @param string $fit     The option's fit: `fits`, `warn` or `block`.
	 * @param string $against A fitting part's label ("Fits your 8.25″ deck"), or empty.
	 */
	private static function fit( string $fit, string $against ): string {
		if ( 'warn' === $fit ) {
			return __( 'Fits, with a note', 'creative-skateboard-builder' );
		}
		if ( 'block' === $fit ) {
			return __( 'Does not fit', 'creative-skateboard-builder' );
		}
		return $against;
	}

	/**
	 * A card's classes, as another plugin would have them.
	 *
	 * @param int    $kit       The kit product's ID.
	 * @param string $slot      The slot's key.
	 * @param int    $part      The part's ID.
	 * @param string $fit       The option's fit.
	 * @param bool   $available Whether the shop can supply it.
	 * @param bool   $checked   Whether the build counts it.
	 */
	private static function classes( int $kit, string $slot, int $part, string $fit, bool $available, bool $checked ): string {
		$classes = array( 'cbuild-card', 'cbuild-card--' . sanitize_html_class( $fit ) );
		if ( ! $available ) {
			$classes[] = 'cbuild-card--sold-out';
		}
		if ( $checked ) {
			$classes[] = 'cbuild-card--chosen';
		}
		return self::flagged( $classes, $kit, $slot, $part );
	}

	/**
	 * A part's own classes with what another plugin adds after them (spec: "A flagged part blurs on its card in a step,
	 * in the parts picture and in the review"): the same answer wherever the part is shown.
	 *
	 * @param list<string> $classes The builder's own classes, kept whatever the answer.
	 * @param int          $kit     The kit product's ID.
	 * @param string       $slot    The slot's key.
	 * @param int          $part    The part's ID: a product's or a variation's.
	 */
	private static function flagged( array $classes, int $kit, string $slot, int $part ): string {
		/**
		 * Filter the classes of a part in the builder: its card in a step, its place in the picture and the bar's
		 * photo, and its line in the review. The builder's own come first and are kept whatever the answer: what is
		 * added is made safe for a class attribute and put after them.
		 *
		 * @since 0.6.0
		 *
		 * @param list<string> $classes The classes to add: none.
		 * @param int          $part    The part's ID: a product's or a variation's.
		 * @param string       $slot    The slot's key, such as trucks.
		 * @param int          $kit     The kit product's ID.
		 */
		$added = apply_filters( 'cbuild_part_classes', array(), $part, $slot, $kit );
		foreach ( is_array( $added ) ? $added : array() as $class ) {
			$class = is_string( $class ) ? sanitize_html_class( $class ) : '';
			if ( '' !== $class ) {
				$classes[] = $class;
			}
		}
		return implode( ' ', array_unique( $classes ) );
	}

	/**
	 * The change panel (spec: A change that breaks later picks): what is kept, what no longer fits and the closest
	 * part that does, with where "Use these" and "Undo" go.
	 *
	 * @param State                               $state   The build's state.
	 * @param array<string, mixed>                $changes The answer's change list, or none.
	 * @param BuildLink                           $link    The address of what is shown.
	 * @param array<string, array<string, mixed>> $lines   The total's lines, by slot.
	 * @param callable(string): string            $label   A slot's label.
	 * @return array<string, mixed>
	 */
	private function changes( State $state, array $changes, BuildLink $link, array $lines, callable $label ): array {
		$kept  = array();
		$items = array();
		foreach ( Cast::strings( $changes['kept'] ?? null ) as $slot ) {
			/* translators: 1: a slot's label, such as Bearings. 2: a part's name. */
			$kept[] = array( 'text' => sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $label( $slot ), Cast::str( $lines[ $slot ]['name'] ?? null ) ) );
		}
		foreach ( Cast::items( $changes['changes'] ?? null ) as $change ) {
			$change  = Cast::map( $change );
			$closest = Cast::map( $change['closest'] ?? null );
			$reasons = array();
			foreach ( Cast::items( $change['messages'] ?? null ) as $message ) {
				$reasons[] = array( 'text' => Cast::str( Cast::map( $message )['text'] ?? null ) );
			}
			$items[] = array(
				'key'     => Cast::str( $change['slot'] ?? null ),
				/* translators: 1: a slot's label, such as Trucks. 2: a part's name. */
				'text'    => sprintf( __( '%1$s: %2$s no longer fits.', 'creative-skateboard-builder' ), Cast::str( $change['label'] ?? null ), Cast::str( $change['name'] ?? null ) ),
				'reasons' => $reasons,
				'closest' => array() === $closest
					? __( 'Nothing the shop has fits here, so this slot would be left for you to choose again.', 'creative-skateboard-builder' )
					/* translators: %s: a part's name. */
					: sprintf( __( 'Closest fit: %s', 'creative-skateboard-builder' ), Cast::str( Cast::map( $closest['card'] ?? null )['name'] ?? null ) ),
			);
		}
		// Use these: the proposed build, on the step asked for. Undo: the picks as they were, on the step that changed.
		$proposed = array();
		foreach ( Cast::items( $changes['proposed'] ?? null ) as $pick ) {
			$pick = Cast::map( $pick );
			$proposed[ Cast::str( $pick['slot'] ?? '' ) ] = Cast::int( $pick['part'] ?? null );
		}
		$was     = $link->was ?? array();
		$changed = $state->step;
		foreach ( $state->kit->slots->keys() as $key ) {
			if ( ( $link->picks[ $key ] ?? null ) !== ( $was[ $key ] ?? null ) ) {
				$changed = $key;
				break;
			}
		}
		$use  = new BuildLink( $proposed, null, $link->step, $link->assembly, $link->preset, replace: $link->replace, none: $link->none );
		$undo = new BuildLink( $was, null, $changed, $link->assembly, $link->preset, replace: $link->replace, none: $link->none );
		$none = array() === $changes;
		return array(
			'heading'    => __( 'This change affects parts you chose', 'creative-skateboard-builder' ),
			'keptHead'   => __( 'Kept', 'creative-skateboard-builder' ),
			'hasKept'    => array() !== $kept,
			'kept'       => $kept,
			'goneHead'   => __( 'No longer fits', 'creative-skateboard-builder' ),
			'hasItems'   => true,
			'items'      => $items,
			// A saved build reopened says its new prices and totals here (D-254); a change made now has none.
			'pricesHead' => __( 'New prices', 'creative-skateboard-builder' ),
			'hasPrices'  => false,
			'prices'     => array(),
			'hasTotals'  => false,
			'totals'     => '',
			// Buttons in GET forms, not links (spec: Without JavaScript; SEO and sharing): a crawler never enters a state.
			'use'        => array(
				'fields' => $none ? array() : self::flat( $use->query( $state->kit->slots ) ),
				'text'   => __( 'Use these', 'creative-skateboard-builder' ),
			),
			'undo'       => array(
				'fields' => $none ? array() : self::flat( $undo->query( $state->kit->slots ) ),
				'text'   => __( 'Undo', 'creative-skateboard-builder' ),
			),
		);
	}

	/**
	 * The Filters button's words: "Filters", and how many chips are chosen where any are.
	 *
	 * @param list<array<string, mixed>> $groups The chip groups, as the view gives them.
	 */
	private static function toggle( array $groups ): string {
		$chosen = 0;
		foreach ( $groups as $group ) {
			foreach ( Cast::items( $group['inputs'] ?? null ) as $chip ) {
				$chosen += true === ( Cast::map( $chip )['checked'] ?? null ) ? 1 : 0;
			}
		}
		if ( 0 === $chosen ) {
			return __( 'Filters', 'creative-skateboard-builder' );
		}
		/* translators: %s: how many filter choices are chosen, such as 2. */
		return sprintf( _n( 'Filters, %s chosen', 'Filters, %s chosen', $chosen, 'creative-skateboard-builder' ), number_format_i18n( $chosen ) );
	}

	/**
	 * The view of a saved build that changed since it was saved, as "Your build has changed" before the review (spec: A
	 * saved build that has changed, D-254): the change panel, each changed part named with why and its closest fit,
	 * each new price then and now, the totals, then Use these, which takes the closest parts to the review, and Choose
	 * myself, which opens the first changed step.
	 *
	 * @since 1.0.0
	 *
	 * @param array<string, mixed>                                                                                                                                                                                                                                                                                                         $view     The view of the build with the closest parts in place, at the review.
	 * @param array{changes: list<array{slot: string, label: string, was: int, name: string, reason: string, closest: array{id: int, name: string}|null}>, prices: list<array{slot: string, label: string, part: int, name: string, then: string, now: string}>, then: string|null, now: string, picks: array<string, int>, first: string} $reopened What changed, as the answer says it.
	 * @param State                                                                                                                                                                                                                                                                                                                        $state    The build's state.
	 * @param bool|null                                                                                                                                                                                                                                                                                                                    $assembly Assembly, as saved.
	 * @return array<string, mixed>
	 */
	public function reopened( array $view, array $reopened, State $state, ?bool $assembly ): array {
		$items = array();
		foreach ( $reopened['changes'] as $change ) {
			$named   = '' === $change['name'] ? __( 'a part', 'creative-skateboard-builder' ) : $change['name'];
			$items[] = array(
				'key'     => $change['slot'],
				'text'    => match ( $change['reason'] ) {
					/* translators: 1: a slot's label, such as Wheels. 2: a part's name. */
					'gone'     => sprintf( __( '%1$s: %2$s is no longer in the shop.', 'creative-skateboard-builder' ), $change['label'], $named ),
					/* translators: 1: a slot's label, such as Wheels. 2: a part's name. */
					'sold_out' => sprintf( __( '%1$s: %2$s is sold out.', 'creative-skateboard-builder' ), $change['label'], $named ),
					/* translators: 1: a slot's label, such as Trucks. 2: a part's name. */
					default    => sprintf( __( '%1$s: %2$s no longer fits.', 'creative-skateboard-builder' ), $change['label'], $named ),
				},
				'reasons' => array(),
				'closest' => null === $change['closest']
					? __( 'Nothing the shop has fits here, so this slot would be left for you to choose again.', 'creative-skateboard-builder' )
					/* translators: %s: a part's name. */
					: sprintf( __( 'Closest fit: %s', 'creative-skateboard-builder' ), $change['closest']['name'] ),
			);
		}
		$prices = array();
		foreach ( $reopened['prices'] as $price ) {
			/* translators: 1: a part's name, such as Independent 149 trucks. 2: its price when the build was saved. 3: its price now. */
			$prices[] = array( 'text' => sprintf( __( '%1$s: %2$s when you saved, %3$s now', 'creative-skateboard-builder' ), $price['name'], $price['then'], $price['now'] ) );
		}
		$totals = null === $reopened['then']
			/* translators: %s: the build's total now. */
			? sprintf( __( 'Total now: %s', 'creative-skateboard-builder' ), $reopened['now'] )
			/* translators: 1: the build's total when it was saved. 2: its total now. */
			: sprintf( __( 'Total: %1$s when you saved, %2$s now', 'creative-skateboard-builder' ), $reopened['then'], $reopened['now'] );
		$slots             = $state->kit->slots;
		$kept              = array_diff_key( $reopened['picks'], array_flip( array_column( $reopened['changes'], 'slot' ) ) );
		$use               = new BuildLink( $reopened['picks'], null, State::REVIEW, $assembly );
		$self              = new BuildLink( $kept, null, $reopened['first'], $assembly );
		$heading           = __( 'Your build has changed', 'creative-skateboard-builder' );
		$view['changes']   = array(
			'heading'    => $heading,
			'keptHead'   => '',
			'hasKept'    => false,
			'kept'       => array(),
			'goneHead'   => __( 'Changed since you saved it', 'creative-skateboard-builder' ),
			'hasItems'   => array() !== $items,
			'items'      => $items,
			'pricesHead' => __( 'New prices', 'creative-skateboard-builder' ),
			'hasPrices'  => array() !== $prices,
			'prices'     => $prices,
			'hasTotals'  => true,
			'totals'     => $totals,
			'use'        => array(
				'fields' => self::flat( $use->query( $slots ) ),
				'text'   => __( 'Use these', 'creative-skateboard-builder' ),
			),
			'undo'       => array(
				'fields' => self::flat( $self->query( $slots ) ),
				'text'   => __( 'Choose myself', 'creative-skateboard-builder' ),
			),
		);
		$view['isStep']    = false;
		$view['isReview']  = false;
		$view['isChanges'] = true;
		$view['status']    = $heading;
		return $view;
	}

	/**
	 * What the status region says after a pick: the build, its total, and the estimate while there is one (D-213).
	 *
	 * @param string      $description The build in a sentence.
	 * @param string      $total       The total, as the shop writes money.
	 * @param string|null $estimate    The estimate, or null.
	 */
	private static function said( string $description, string $total, ?string $estimate ): string {
		if ( null === $estimate ) {
			/* translators: 1: the build in a sentence. 2: the build's total, such as $184.00. */
			return sprintf( __( '%1$s Total: %2$s.', 'creative-skateboard-builder' ), $description, $total );
		}
		/* translators: 1: the build in a sentence. 2: the build's total, such as $70.00. 3: the estimate for a complete board, such as $184.00. */
		return sprintf( __( '%1$s Total: %2$s. A complete board from about %3$s.', 'creative-skateboard-builder' ), $description, $total, $estimate );
	}

	/**
	 * The summary (spec: The total): the parts so far, assembly from the first step, any build discount, the
	 * total, the delivery line and what other plugins add.
	 *
	 * @param State                               $state  The build's state.
	 * @param array<string, mixed>                $answer The state's answer.
	 * @param BuildLink                           $link   The address of what is shown.
	 * @param callable(BuildLink): string         $url    Makes an address.
	 * @param array<string, array<string, mixed>> $lines  The total's lines, by slot.
	 * @param callable(string): string            $label  A slot's label.
	 * @return array<string, mixed>
	 */
	private function summary( State $state, array $answer, BuildLink $link, callable $url, array $lines, callable $label ): array {
		$total    = Cast::map( $answer['total'] ?? null );
		$fee      = Cast::map( $total['fee'] ?? null );
		$saved    = Cast::map( $total['saved'] ?? null );
		$delivery = Cast::map( $answer['delivery'] ?? null );
		$on       = Cast::bool( $total['assembly'] ?? null );
		$rows     = array();
		foreach ( $lines as $slot => $line ) {
			$quantity = Cast::int( $line['quantity'] ?? null );
			$rows[]   = array(
				'key'    => $slot,
				'label'  => $label( $slot ),
				'name'   => Cast::str( $line['name'] ?? null ),
				/* translators: %s: how many of a part a build takes. */
				'count'  => $quantity > 1 ? sprintf( __( '× %s', 'creative-skateboard-builder' ), number_format_i18n( $quantity ) ) : '',
				'amount' => Cast::str( $line['amount'] ?? null ),
			);
		}
		$notes = array();
		foreach ( Cast::strings( $answer['notes'] ?? null ) as $note ) {
			$notes[] = array( 'text' => $note );
		}
		$assembly = __( 'Assembly: not added', 'creative-skateboard-builder' );
		if ( $on ) {
			$assembly = 0 === Cast::int( $fee['amount'] ?? null )
				? __( 'Assembly: free', 'creative-skateboard-builder' )
				/* translators: %s: the assembly fee, such as $15.00. */
				: sprintf( __( 'Assembly: %s', 'creative-skateboard-builder' ), Cast::str( $fee['text'] ?? null ) );
		}
		$arrival = Cast::str( $delivery['arrival'] ?? null );
		$ships   = Cast::str( $delivery['line'] ?? null );
		return array(
			'heading'        => __( 'Your build', 'creative-skateboard-builder' ),
			'rows'           => $rows,
			'empty'          => array() === $rows,
			'emptyText'      => __( 'Nothing is chosen yet.', 'creative-skateboard-builder' ),
			'assembly'       => $assembly,
			// A button in a GET form, not a link (spec: SEO and sharing): a crawler never enters a state.
			'toggle'         => array(
				'fields' => self::flat( $link->assembled( ! $on )->query( $state->kit->slots ) ),
				'text'   => $on ? __( 'Remove assembly', 'creative-skateboard-builder' ) : __( 'Add assembly', 'creative-skateboard-builder' ),
			),
			'hasSaved'       => Cast::int( $saved['amount'] ?? null ) > 0,
			/* translators: %s: an amount of money, such as $12.00. */
			'saved'          => sprintf( __( 'Build discount: %s', 'creative-skateboard-builder' ), Words::delta( 'less', Cast::str( $saved['text'] ?? null ) ) ),
			'totalLabel'     => __( 'Total', 'creative-skateboard-builder' ),
			'total'          => Cast::str( Cast::map( $total['total'] ?? null )['text'] ?? null ),
			// The full price is no surprise at the review (P9): the board with each recommendation, said as an
			// estimate under the total, which counts what was picked (D-213).
			'hasEstimate'    => null !== ( $total['estimate'] ?? null ),
			/* translators: %s: an amount of money, such as $184.00. */
			'estimate'       => sprintf( __( 'A complete board from about %s', 'creative-skateboard-builder' ), Cast::str( Cast::map( $total['estimate'] ?? null )['text'] ?? null ) ),
			'estimateAmount' => Cast::str( Cast::map( $total['estimate'] ?? null )['text'] ?? null ),
			'hasArrival'     => '' !== $arrival,
			'arrival'        => $arrival,
			'hasShips'       => '' !== $ships,
			'ships'          => $ships,
			'notes'          => $notes,
			'kit'            => $state->id,
		);
	}

	/**
	 * The review (spec: Review and add to cart): the build by slot with a Change link each, assembly with its lead
	 * time, the returns notice, and the form that posts the build to WooCommerce's own add-to-cart handler.
	 *
	 * @param State                               $state   The build's state.
	 * @param array<string, mixed>                $answer  The state's answer.
	 * @param BuildLink                           $link    The address of what is shown.
	 * @param callable(BuildLink): string         $url     Makes an address.
	 * @param list<string>                        $steps   The slots that are steps.
	 * @param array<string, int>                  $counted The part the total counts in each slot.
	 * @param array<string, array<string, mixed>> $lines   The total's lines, by slot.
	 * @param callable(string): string            $label   A slot's label.
	 * @param array<string, mixed>                $summary The summary, whose assembly, arrival and total the review says
	 *                                                     again before Add to cart.
	 * @return array<string, mixed>
	 */
	private function review( State $state, array $answer, BuildLink $link, callable $url, array $steps, array $counted, array $lines, callable $label, array $summary ): array {
		$wrong = array();
		foreach ( Cast::items( $answer['problems'] ?? null ) as $problem ) {
			$problem                                      = Cast::map( $problem );
			$wrong[ Cast::str( $problem['slot'] ?? '' ) ] = Cast::str( $problem['message'] ?? null );
		}
		$items    = array();
		$included = Builder::bearingsIncluded( $state );
		foreach ( $state->kit->slots->keys() as $slot ) {
			if ( ! in_array( $slot, $steps, true ) ) {
				if ( SlotKey::BEARINGS === $slot && $included ) {
					// Wheels sold with their bearings fill the slot (spec: Bearings, D-246): said, and changed with the wheels.
					$items[] = array(
						'key'     => $slot,
						'classes' => Options::CLASS_REVIEW_ITEM,
						'label'   => $label( $slot ),
						'name'    => __( 'Included with your wheels', 'creative-skateboard-builder' ),
						'fits'    => true,
						'fit'     => '',
						'amount'  => '',
						'url'     => $url( $link->at( SlotKey::WHEELS ) ),
						'text'    => __( 'Change', 'creative-skateboard-builder' ),
						/* translators: %s: a slot's label, such as Trucks. */
						'change'  => sprintf( __( 'Change %s', 'creative-skateboard-builder' ), Words::inSentence( $label( SlotKey::WHEELS ) ) ),
					);
				}
				continue;
			}
			$own     = isset( $wrong[ $slot ] ) ? array( Options::CLASS_REVIEW_ITEM, Options::CLASS_REVIEW_ITEM . '--wrong' ) : array( Options::CLASS_REVIEW_ITEM );
			$items[] = array(
				'key'     => $slot,
				'classes' => isset( $counted[ $slot ] ) ? self::flagged( $own, $state->id, $slot, $counted[ $slot ] ) : implode( ' ', $own ),
				'label'   => $label( $slot ),
				'name'    => isset( $counted[ $slot ] ) ? Cast::str( $lines[ $slot ]['name'] ?? null ) : __( 'None', 'creative-skateboard-builder' ),
				'fits'    => ! isset( $wrong[ $slot ] ),
				'fit'     => $wrong[ $slot ] ?? ( isset( $counted[ $slot ] ) ? __( 'Fits', 'creative-skateboard-builder' ) : '' ),
				'amount'  => isset( $counted[ $slot ] ) ? Cast::str( $lines[ $slot ]['amount'] ?? null ) : '',
				'url'     => $url( $link->at( $slot ) ),
				// "Change" to the eye, as the prototype has it, and read with the slot it changes.
				'text'    => __( 'Change', 'creative-skateboard-builder' ),
				/* translators: %s: a slot's label, such as Trucks. */
				'change'  => sprintf( __( 'Change %s', 'creative-skateboard-builder' ), Words::inSentence( $label( $slot ) ) ),
			);
		}
		$texts = Cast::map( $answer['texts'] ?? null );
		$on    = Cast::bool( Cast::map( $answer['total'] ?? null )['assembly'] ?? null );
		$lead  = $on ? Cast::str( $texts['lead_time'] ?? null ) : '';
		/* translators: %s: how long the shop takes to put a build together, in the owner's words, such as 2 business days. */
		$lead   = '' === $lead ? '' : sprintf( __( 'Assembly time: %s', 'creative-skateboard-builder' ), $lead );
		$notice = Cast::str( $texts['returns_notice'] ?? null );
		$fields = array(
			array(
				'name'  => 'add-to-cart',
				'value' => (string) $state->id,
			),
		);
		$fields = array_merge( $fields, self::fields( BuildLink::PARAM . '[picks]', $counted, array(), $state->kit->slots->keys() ) );
		$fields = array_merge(
			$fields,
			array(
				array(
					'name'  => BuildLink::PARAM . '[assembly]',
					'value' => $on ? '1' : '0',
				),
			)
		);
		// Save build's fields: the build alone, with nothing of the cart's (D-217). The kit goes under the plugin's own name:
		// WooCommerce adds `add-to-cart` to the cart on any request it comes with (includes/class-wc-form-handler.php).
		$saving    = $fields;
		$saving[0] = array(
			'name'  => SavedBuildHooks::KIT,
			'value' => (string) $state->id,
		);
		$replacing = '' !== $link->replace;
		if ( $replacing ) {
			// From Edit build: the add takes the place of the build in the cart it came from (D-157).
			$fields[] = array(
				'name'  => BuildLink::PARAM . '[replace]',
				'value' => $link->replace,
			);
		}
		return array(
			'heading'    => __( 'Check your build', 'creative-skateboard-builder' ),
			'items'      => $items,
			// Under the parts, for a rider who wants risers when nothing asks for them (spec: The review, D-246).
			'addRisers'  => array(
				'shown' => in_array( SlotKey::RISERS, $state->kit->slots->keys(), true ) && ! in_array( SlotKey::RISERS, $steps, true ),
				'text'  => __( 'Add risers', 'creative-skateboard-builder' ),
				'url'   => $url( $link->at( SlotKey::RISERS ) ),
			),
			// All before Add to cart (spec: Review and add to cart), in the summary's own words.
			'assembly'   => Cast::str( $summary['assembly'] ?? null ),
			'hasArrival' => Cast::bool( $summary['hasArrival'] ?? null ),
			'arrival'    => Cast::str( $summary['arrival'] ?? null ),
			/* translators: %s: the build's total, such as $184.00. */
			'total'      => sprintf( __( 'Total: %s', 'creative-skateboard-builder' ), Cast::str( $summary['total'] ?? null ) ),
			'hasLead'    => '' !== $lead,
			'lead'       => $lead,
			'hasNotice'  => '' !== $notice,
			'notice'     => $notice,
			'ready'      => Cast::bool( $answer['ready'] ?? null ),
			'action'     => $url( $link ),
			'fields'     => $fields,
			'saveFields' => $saving,
			// From Edit build the add takes the place of the build in the cart, and says so (spec: Review and add to
			// cart, "Saving there replaces the build"; Cowork's review, should-fix 7).
			'add'        => $replacing ? __( 'Save changes', 'creative-skateboard-builder' ) : __( 'Add to cart', 'creative-skateboard-builder' ),
			'replacing'  => $replacing,
			'replaces'   => __( 'Saving replaces the build in your cart.', 'creative-skateboard-builder' ),
			// Said, and focused, once an add without a page load is done (D-155).
			'added'      => $replacing ? __( 'Your cart has the changed build.', 'creative-skateboard-builder' ) : __( 'The build is in your cart.', 'creative-skateboard-builder' ),
			// The build's link needs no account to open (P12, D-156). It is BuildLink::shared(): the parts, the step
			// and assembly, and nothing of the shopper's cart or the builder's own keys (spec: Share links; D-157).
			'link'       => $url( $link->shared()->at( State::REVIEW ) ),
			'share'      => __( 'Link to this build', 'creative-skateboard-builder' ),
			'copy'       => __( 'Copy link', 'creative-skateboard-builder' ),
			'copied'     => __( 'Link copied.', 'creative-skateboard-builder' ),
			// Save build (D-217): for a signed-in customer, a form to My builds; for anyone else, the way to sign in
			// that brings them back to this build.
			'save'       => __( 'Save build', 'creative-skateboard-builder' ),
			'saveName'   => __( 'Name it (optional)', 'creative-skateboard-builder' ),
			'signIn'     => wp_login_url( $url( $link->shared()->at( State::REVIEW ) ) ),
			'signInText' => __( 'Sign in to save this build', 'creative-skateboard-builder' ),
		);
	}
}
