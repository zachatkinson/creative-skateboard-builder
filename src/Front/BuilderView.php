<?php
/**
 * What the builder's template shows.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Cast;

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
	 * @return array<string, mixed>
	 */
	public function of( State $state, array $answer, BuildLink $link, string $base, array $steps, ?BuildProblem $stopped ): array {
		$slots = $state->kit->slots;
		$url   = static function ( BuildLink $to ) use ( $base, $slots ): string {
			$query = $to->query( $slots );
			return array() === $query ? $base : $base . ( str_contains( $base, '?' ) ? '&' : '?' ) . http_build_query( array( BuildLink::PARAM => $query ) );
		};
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

		// What keeps the build from where the shopper asked to go, or from the cart.
		$errors = array();
		if ( null !== $stopped ) {
			$errors[] = array(
				'text' => Words::problem( $stopped->code, $label( $stopped->slot ) ),
				'url'  => $url( $link->at( $stopped->slot ) ),
			);
		}
		foreach ( Cast::items( $answer['refused'] ?? null ) as $problem ) {
			$errors[] = array(
				'text' => Cast::str( Cast::map( $problem )['message'] ?? null ),
				'url'  => $url( $link ),
			);
		}
		if ( $review ) {
			foreach ( Cast::items( $answer['problems'] ?? null ) as $problem ) {
				$problem  = Cast::map( $problem );
				$slot     = Cast::str( $problem['slot'] ?? null );
				$errors[] = array(
					'text' => Cast::str( $problem['message'] ?? null ),
					'url'  => $url( $link->at( in_array( $slot, $steps, true ) ? $slot : $state->step ) ),
				);
			}
		}

		$progress = array();
		foreach ( $all as $number => $key ) {
			$done       = $number < $at;
			$progress[] = array(
				'key'     => $key,
				'label'   => State::REVIEW === $key ? __( 'Review', 'creative-skateboard-builder' ) : $label( $key ),
				'state'   => self::place( $number, $at ),
				'done'    => $done,
				'current' => $number === $at,
				'url'     => $done ? $url( $link->at( $key ) ) : '',
				'aria'    => $number === $at ? 'step' : false,
			);
		}

		$total   = Cast::map( $answer['total'] ?? null );
		$sum     = Cast::str( Cast::map( $total['total'] ?? null )['text'] ?? null );
		$picture = Cast::map( $answer['picture'] ?? null );
		$status  = $panel
			/* translators: %s: a number of parts. */
			? sprintf( _n( '%s part no longer fits.', '%s parts no longer fit.', count( Cast::items( $changes['changes'] ?? null ) ), 'creative-skateboard-builder' ), number_format_i18n( count( Cast::items( $changes['changes'] ?? null ) ) ) )
			/* translators: 1: the build in a sentence. 2: the build's total, such as $184.00. */
			: sprintf( __( '%1$s Total: %2$s.', 'creative-skateboard-builder' ), Cast::str( $picture['description'] ?? null ), $sum );

		[ $action, $carry ] = self::form( $base );
		return array(
			'kit'       => $state->id,
			'url'       => $url( $link ),
			'clean'     => $base,
			'action'    => $action,
			'carry'     => $carry,
			'heading'   => $heading,
			/* translators: %s: the page's heading, such as "Step 2 of 7: Trucks". */
			'title'     => array() === $errors ? $heading : sprintf( __( 'Error: %s', 'creative-skateboard-builder' ), $heading ),
			'isStep'    => ! $panel && ! $review,
			'isChanges' => $panel,
			'isReview'  => ! $panel && $review,
			'status'    => $status,
			'errors'    => array(
				'shown'   => array() !== $errors,
				'heading' => __( 'This build needs a look before it can go on', 'creative-skateboard-builder' ),
				'items'   => $errors,
			),
			'progress'  => $progress,
			'step'      => $review ? self::none() : $this->step( $state, $answer, $link, $url, $steps, $counted, $lines, $about ),
			'changes'   => $this->changes( $state, $changes, $link, $lines, $label ),
			'summary'   => $this->summary( $state, $answer, $link, $url, $lines, $label ),
			'picture'   => array(
				'heading'     => __( 'What is in the box', 'creative-skateboard-builder' ),
				'description' => Cast::str( $picture['description'] ?? null ),
				'items'       => array_map(
					static function ( mixed $item ): array {
						$item = Cast::map( $item );
						return array(
							'key'   => Cast::str( $item['slot'] ?? null ),
							'label' => Cast::str( $item['label'] ?? null ),
							'name'  => Cast::str( $item['name'] ?? null ),
						) + self::image( $item['image'] ?? null );
					},
					Cast::items( $picture['items'] ?? null )
				),
			),
			'review'    => $this->review( $state, $answer, $link, $url, $steps, $counted, $lines, $label ),
		);
	}

	/**
	 * Where a step stands against the one shown, as its class and as a reader hears it.
	 *
	 * @param int $number The step's place.
	 * @param int $at     The place of the step shown.
	 */
	private static function place( int $number, int $at ): string {
		return match ( $number <=> $at ) {
			-1      => __( 'Done', 'creative-skateboard-builder' ),
			0       => __( 'Current step', 'creative-skateboard-builder' ),
			default => __( 'To come', 'creative-skateboard-builder' ),
		};
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
	 * @return array<string, mixed>
	 */
	private static function none(): array {
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
			'empty'     => false,
			'emptyText' => '',
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
	 * @return array{shown: bool, heading: string, groups: list<array<string, mixed>>}
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
			'legend' => match ( $slot ) {
				SlotKey::DECK     => __( 'Width', 'creative-skateboard-builder' ),
				SlotKey::TRUCKS   => __( 'Axle width', 'creative-skateboard-builder' ),
				SlotKey::WHEELS   => __( 'Diameter', 'creative-skateboard-builder' ),
				SlotKey::RISERS   => __( 'Thickness', 'creative-skateboard-builder' ),
				SlotKey::HARDWARE => __( 'Length', 'creative-skateboard-builder' ),
				default           => __( 'Size', 'creative-skateboard-builder' ),
			},
			'name'   => BuildLink::PARAM . '[_size]',
			'any'    => match ( $slot ) {
				SlotKey::DECK => __( 'Any width', 'creative-skateboard-builder' ),
				default       => __( 'Any size', 'creative-skateboard-builder' ),
			},
			'values' => Cast::items( $filters['sizes'] ?? null ),
		);
		$groups = array();
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
	 * @return array{shown: bool, name: string, value: string, text: string, count: string}
	 */
	private static function more( array $answer, int $listed ): array {
		$matching = Cast::int( $answer['matching'] ?? null );
		$more     = $answer['more'] ?? null;
		// The answer asks for the next page whole; the button says, and asks for, only what is left.
		$more = null === $more ? null : min( Cast::int( $more ), $matching );
		$next = null === $more ? 0 : $more - $listed;
		return array(
			'shown' => null !== $more,
			'name'  => BuildLink::PARAM . '[_shown]',
			'value' => null === $more ? '' : (string) $more,
			/* translators: %s: how many more parts the button lists. */
			'text'  => null === $more ? '' : sprintf( __( 'Show %s more', 'creative-skateboard-builder' ), number_format_i18n( $next ) ),
			/* translators: 1: how many parts are listed. 2: how many match. */
			'count' => sprintf( _n( '%1$s of %2$s part shown', '%1$s of %2$s parts shown', $matching, 'creative-skateboard-builder' ), number_format_i18n( $listed ), number_format_i18n( $matching ) ),
		);
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

		$options = array();
		foreach ( Cast::items( $answer['options'] ?? null ) as $option ) {
			$option    = Cast::map( $option );
			$id        = Cast::int( $option['id'] ?? null );
			$card      = Cast::map( $option['card'] ?? null );
			$title     = Cast::str( $card['name'] ?? null );
			$checked   = ( $counted[ $slot ] ?? null ) === $id;
			$available = Cast::bool( $option['available'] ?? null );
			$notes     = array();
			foreach ( Cast::items( $option['messages'] ?? null ) as $message ) {
				$notes[] = array( 'text' => Cast::str( Cast::map( $message )['text'] ?? null ) );
			}
			$fit       = Cast::str( $option['fit'] ?? null );
			$options[] = array(
				'id'            => $id,
				'input'         => 'cbuild-' . $state->id . '-' . $id,
				'value'         => (string) $id,
				'name'          => $title,
				/* translators: %s: a part's name. */
				'choose'        => sprintf( __( 'Choose %s', 'creative-skateboard-builder' ), $title ),
				'checked'       => $checked,
				// A part the shop cannot supply cannot be picked. One a link or a preset brought stays in the form, so
				// that the build still names it until the shopper swaps it.
				'disabled'      => ! $available && ! $checked,
				'soldOut'       => ! $available,
				'url'           => Cast::str( $card['url'] ?? null ),
				/* translators: %s: a part's name. */
				'page'          => sprintf( __( 'See the page for %s', 'creative-skateboard-builder' ), $title ),
				'delta'         => Cast::str( Cast::map( $option['delta'] ?? null )['text'] ?? null ),
				'stock'         => Cast::str( $card['stock'] ?? null ),
				'stockClass'    => 'cbuild-card__stock cbuild-card__stock--' . sanitize_html_class( Cast::str( $card['stock_class'] ?? null ) ),
				'fit'           => self::fit( $fit, $width ),
				'fitClass'      => 'cbuild-card__fit cbuild-card__fit--' . sanitize_html_class( $fit ),
				'notes'         => $notes,
				'recommended'   => Cast::bool( $option['recommended'] ?? null ),
				'recommendedAs' => self::recommended( $width ),
				'classes'       => self::classes( $state->id, $slot, $id, $fit, $available, $checked ),
			) + self::image( $card['image'] ?? null );
		}

		$required = Cast::bool( $step['required'] ?? null );
		$line     = Cast::map( $lines[ $slot ] ?? null );
		$before   = $state->kit->slots->before( $slot );
		$after    = $state->kit->slots->after( $slot );
		// Where the step has filters or Show more, the step the form is on, so that they keep the shopper on it and Next,
		// which names the next, leaves them behind (D-150).
		$tail = array();
		if ( self::filters( $answer, $slot )['shown'] || null !== ( $answer['more'] ?? null ) ) {
			$tail[] = array(
				'name'  => BuildLink::PARAM . '[_on]',
				'value' => $slot,
			);
		}
		foreach ( array(
			'_brand' => $link->brand,
			'_size'  => $link->size,
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
		return array(
			'slot'      => $slot,
			'legend'    => Cast::str( $step['label'] ?? $slot ),
			'help'      => Cast::str( $step['help'] ?? null ),
			'hasHelp'   => '' !== Cast::str( $step['help'] ?? null ),
			'lead'      => __( 'Start with a width. Every later step offers only what fits the deck you choose.', 'creative-skateboard-builder' ),
			'hasLead'   => SlotKey::DECK === $slot && ! isset( $counted[ $slot ] ),
			'name'      => BuildLink::PARAM . '[' . $slot . ']',
			'before'    => self::fields( BuildLink::PARAM, $link->picks, $link->none, $before ),
			'after'     => self::fields( BuildLink::PARAM, $link->picks, $link->none, $after ),
			'tail'      => $tail,
			'options'   => $options,
			'hidden'    => self::hidden( $answer, $step ),
			'filters'   => self::filters( $answer, $slot ),
			'more'      => self::more( $answer, count( $options ) ),
			'empty'     => array() === $options,
			'emptyText' => '' === $link->brand && '' === $link->size
				? __( 'Nothing the shop has fits the parts before this step.', 'creative-skateboard-builder' )
				: __( 'No part matches these filters.', 'creative-skateboard-builder' ),
			'leave'     => array(
				'shown'   => ! $required,
				'id'      => 'cbuild-' . $state->id . '-' . $slot . '-none',
				'label'   => SlotKey::RISERS === $slot ? __( 'No risers', 'creative-skateboard-builder' ) : __( 'None', 'creative-skateboard-builder' ),
				'checked' => ! isset( $counted[ $slot ] ),
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
	 * A card's fit label (spec: A step). A part that fits says so against the deck; one that warns or is blocked
	 * says so shortly, and its notes say why.
	 *
	 * @param string $fit   The option's fit: `fits`, `warn` or `block`.
	 * @param string $width The deck's width, or empty.
	 */
	private static function fit( string $fit, string $width ): string {
		if ( 'warn' === $fit ) {
			return __( 'Fits, with a note', 'creative-skateboard-builder' );
		}
		if ( 'block' === $fit ) {
			return __( 'Does not fit', 'creative-skateboard-builder' );
		}
		if ( '' === $width ) {
			return '';
		}
		/* translators: %s: a deck's width, such as 8.25 in. */
		return sprintf( __( 'Fits your %s deck', 'creative-skateboard-builder' ), $width );
	}

	/**
	 * The label of the part a step starts on (spec: A step, P9).
	 *
	 * @param string $width The deck's width, or empty.
	 */
	private static function recommended( string $width ): string {
		if ( '' === $width ) {
			return __( 'Recommended', 'creative-skateboard-builder' );
		}
		/* translators: %s: a deck's width, such as 8.25 in. */
		return sprintf( __( 'Recommended for your %s deck', 'creative-skateboard-builder' ), $width );
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
		/**
		 * Filter the classes of a part's card in the builder. The builder's own come first and are kept whatever
		 * the answer: what is added is made safe for a class attribute and put after them.
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
			'heading'  => __( 'This change affects parts you chose', 'creative-skateboard-builder' ),
			'keptHead' => __( 'Kept', 'creative-skateboard-builder' ),
			'hasKept'  => array() !== $kept,
			'kept'     => $kept,
			'goneHead' => __( 'No longer fits', 'creative-skateboard-builder' ),
			'items'    => $items,
			// Buttons in GET forms, not links (spec: Without JavaScript; SEO and sharing): a crawler never enters a state.
			'use'      => array(
				'fields' => $none ? array() : self::flat( $use->query( $state->kit->slots ) ),
				'text'   => __( 'Use these', 'creative-skateboard-builder' ),
			),
			'undo'     => array(
				'fields' => $none ? array() : self::flat( $undo->query( $state->kit->slots ) ),
				'text'   => __( 'Undo', 'creative-skateboard-builder' ),
			),
		);
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
			'heading'    => __( 'Your build', 'creative-skateboard-builder' ),
			'rows'       => $rows,
			'empty'      => array() === $rows,
			'emptyText'  => __( 'Nothing is chosen yet.', 'creative-skateboard-builder' ),
			'assembly'   => $assembly,
			'toggle'     => array(
				'url'  => $url( $link->assembled( ! $on ) ),
				'text' => $on ? __( 'Remove assembly', 'creative-skateboard-builder' ) : __( 'Add assembly', 'creative-skateboard-builder' ),
			),
			'hasSaved'   => Cast::int( $saved['amount'] ?? null ) > 0,
			/* translators: %s: an amount of money, such as $12.00. */
			'saved'      => sprintf( __( 'Build discount: %s', 'creative-skateboard-builder' ), Words::delta( 'less', Cast::str( $saved['text'] ?? null ) ) ),
			'totalLabel' => __( 'Total', 'creative-skateboard-builder' ),
			'total'      => Cast::str( Cast::map( $total['total'] ?? null )['text'] ?? null ),
			'hasArrival' => '' !== $arrival,
			'arrival'    => $arrival,
			'hasShips'   => '' !== $ships,
			'ships'      => $ships,
			'notes'      => $notes,
			'kit'        => $state->id,
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
	 * @return array<string, mixed>
	 */
	private function review( State $state, array $answer, BuildLink $link, callable $url, array $steps, array $counted, array $lines, callable $label ): array {
		$wrong = array();
		foreach ( Cast::items( $answer['problems'] ?? null ) as $problem ) {
			$problem                                      = Cast::map( $problem );
			$wrong[ Cast::str( $problem['slot'] ?? '' ) ] = Cast::str( $problem['message'] ?? null );
		}
		$items = array();
		foreach ( $steps as $slot ) {
			$items[] = array(
				'key'    => $slot,
				'label'  => $label( $slot ),
				'name'   => isset( $counted[ $slot ] ) ? Cast::str( $lines[ $slot ]['name'] ?? null ) : __( 'None', 'creative-skateboard-builder' ),
				'fits'   => ! isset( $wrong[ $slot ] ),
				'fit'    => $wrong[ $slot ] ?? ( isset( $counted[ $slot ] ) ? __( 'Fits', 'creative-skateboard-builder' ) : '' ),
				'url'    => $url( $link->at( $slot ) ),
				/* translators: %s: a slot's label, such as Trucks. */
				'change' => sprintf( __( 'Change %s', 'creative-skateboard-builder' ), $label( $slot ) ),
			);
		}
		$texts  = Cast::map( $answer['texts'] ?? null );
		$on     = Cast::bool( Cast::map( $answer['total'] ?? null )['assembly'] ?? null );
		$lead   = $on ? Cast::str( $texts['lead_time'] ?? null ) : '';
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
		return array(
			'heading'   => __( 'Check your build', 'creative-skateboard-builder' ),
			'items'     => $items,
			'hasLead'   => '' !== $lead,
			'lead'      => $lead,
			'hasNotice' => '' !== $notice,
			'notice'    => $notice,
			'ready'     => Cast::bool( $answer['ready'] ?? null ),
			'action'    => $url( $link ),
			'fields'    => $fields,
			'add'       => __( 'Add to cart', 'creative-skateboard-builder' ),
		);
	}
}
