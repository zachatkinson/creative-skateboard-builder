<?php
/**
 * A build's state, as the state route answers it.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildProblem;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Option;
use CreativeBranding\SkateboardBuilder\Domain\Rule\Finding;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Service\StateStep;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the state the service worked out into the document `schema/state.json` describes: the codes with their
 * sentences beside them, the amounts with the shop's way of writing them, and a card for each part on show. It
 * decides nothing of fit or price. What it does decide is what is on show:
 *
 * - **One step's options.** Every step says how many parts it offers and hides and why; only the step the shopper is
 *   on lists them.
 * - **24 at a time** (spec: Performance), of those offered and of those hidden. Parts the shop can supply come before
 *   those it cannot, each in the engine's own order, and the part the step counts is always among them.
 * - **The owner's own words through their filters:** `cbuild_slot_label`, `cbuild_preset_label` and `cbuild_kit_text`.
 * - **Cards for those and no others,** asked for in one call: the options listed, the parts the total counts, and the
 *   parts a change list names.
 *
 * @since 0.4.0
 */
final class StateAnswer {

	/**
	 * How many options a step lists.
	 *
	 * @since 0.4.0
	 */
	public const int SHOWN = 24;

	/**
	 * Constructor.
	 *
	 * @since 0.4.0
	 *
	 * @param PartCards $cards What a card shows of a part.
	 * @param Money     $money Writes an amount as the shop does.
	 * @param Kits      $kits  The shop's kits, for the currency's decimals.
	 */
	public function __construct( private PartCards $cards, private Money $money, private Kits $kits ) {}

	/**
	 * The answer.
	 *
	 * @since 0.4.0
	 *
	 * @param State $state The build's state.
	 * @return array<string, mixed>
	 */
	public function of( State $state ): array {
		$labels  = array();
		$current = null;
		foreach ( $state->steps as $step ) {
			$labels[ $step->slot->key ] = self::label( $step->slot->label, $step->slot->key, $state->id );
			if ( $step->slot->key === $state->step ) {
				$current = $step;
			}
		}
		$offered = null === $current ? array() : self::shown( $current->options->offered, $current->counted() );
		$hidden  = null === $current ? array() : self::shown( $current->options->hidden, $current->picked );

		// Every part the answer names, for one call to the cards.
		$ids = array();
		foreach ( array( $offered, $hidden ) as $list ) {
			foreach ( $list as $option ) {
				$ids[] = $option->part->id;
			}
		}
		foreach ( $state->quote->lines as $line ) {
			$ids[] = $line->part;
		}
		foreach ( $state->changes->changes ?? array() as $change ) {
			$ids[] = $change->was->id;
			if ( null !== $change->closest ) {
				$ids[] = $change->closest->part->id;
			}
		}
		$cards    = $this->cards->cards( array_values( array_unique( $ids ) ) );
		$decimals = $this->kits->decimals();
		$money    = fn( int $amount ): array => array(
			'amount' => $amount,
			'text'   => $this->money->text( $amount, $decimals ),
		);
		$option   = static fn( Option $option, ?StateStep $step ): array => array(
			'id'          => $option->part->id,
			'brand'       => $option->part->brand,
			'fit'         => $option->severity->value,
			'in_stock'    => $option->part->inStock,
			'available'   => $option->available(),
			'quantity'    => $option->quantity,
			'picked'      => $option->part->id === $step?->picked?->part->id,
			'recommended' => $option->part->id === $step?->recommended?->part->id,
			'messages'    => self::messages( $option->findings ),
			'card'        => $cards[ $option->part->id ] ?? null,
		);
		$problem  = static fn( BuildProblem $problem ): array => array(
			'code'     => $problem->code->value,
			'slot'     => $problem->slot,
			'part'     => $problem->part,
			'message'  => Words::problem( $problem->code, $labels[ $problem->slot ] ?? $problem->slot ),
			'messages' => self::messages( $problem->findings ),
		);

		$steps = array();
		foreach ( $state->steps as $step ) {
			$groups = array();
			foreach ( $step->options->hiddenGroups() as $group ) {
				$groups[] = array(
					'rule'    => $group->rule->value,
					'code'    => $group->code->value,
					'count'   => $group->count,
					'message' => Words::hidden( $group->code, $group->count ),
				);
			}
			$steps[] = array(
				'slot'        => $step->slot->key,
				'label'       => $labels[ $step->slot->key ],
				'help'        => self::text( $step->slot->help, 'slot_help', $state->id, $step->slot->key ),
				'required'    => $step->slot->required,
				'units'       => $step->slot->units,
				'picked'      => $step->picked?->part->id,
				'recommended' => $step->recommended?->part->id,
				'offered'     => count( $step->options->offered ),
				'hidden'      => count( $step->options->hidden ),
				'groups'      => $groups,
			);
		}

		$lines = array();
		foreach ( $state->quote->lines as $line ) {
			$lines[] = array(
				'slot'     => $line->slot,
				'part'     => $line->part,
				'name'     => $cards[ $line->part ]['name'] ?? '',
				'quantity' => $line->quantity,
				'price'    => $money( $line->price ),
				'charged'  => $money( $line->charged ),
				'total'    => $money( $line->total ),
			);
		}

		$changes = null;
		if ( null !== $state->changes ) {
			$changes = array(
				'kept'     => $state->changes->kept,
				'proposed' => array(),
				'changes'  => array(),
			);
			foreach ( $state->steps as $step ) {
				$part = $state->changes->proposed->part( $step->slot->key );
				if ( null !== $part ) {
					$changes['proposed'][] = array(
						'slot' => $step->slot->key,
						'part' => $part->id,
					);
				}
			}
			foreach ( $state->changes->changes as $change ) {
				$changes['changes'][] = array(
					'slot'     => $change->slot,
					'label'    => $labels[ $change->slot ] ?? $change->slot,
					'was'      => $change->was->id,
					'name'     => $cards[ $change->was->id ]['name'] ?? '',
					'messages' => self::messages( $change->findings ),
					'closest'  => null === $change->closest ? null : $option( $change->closest, null ),
				);
			}
		}

		$presets = array();
		foreach ( $state->kit->presets as $at => $preset ) {
			$picks = array();
			foreach ( $preset->picks as $slot => $part ) {
				$picks[] = compact( 'slot', 'part' );
			}
			$presets[] = array(
				'name'        => self::preset( $preset->name, $at, $state->id ),
				'description' => self::text( $preset->description, 'preset_description', $state->id, (string) $at ),
				'picks'       => $picks,
			);
		}

		return array(
			'kit'      => $state->id,
			'step'     => $state->step,
			'ready'    => $state->ready(),
			'texts'    => array(
				'lead_time'      => self::text( $state->kit->assembly->leadTime, 'lead_time', $state->id ),
				'returns_notice' => self::text( $state->kit->assembly->returnsNotice, 'returns_notice', $state->id ),
			),
			'presets'  => $presets,
			'steps'    => $steps,
			'options'  => array_map( static fn( Option $shown ): array => $option( $shown, $current ), $offered ),
			'hidden'   => array_map( static fn( Option $shown ): array => $option( $shown, $current ), $hidden ),
			'refused'  => array_map( $problem, $state->refused ),
			'problems' => array_map( $problem, $state->validation->problems ),
			'changes'  => $changes,
			'total'    => array(
				'lines'    => $lines,
				'parts'    => $money( $state->quote->parts ),
				'saved'    => $money( $state->quote->saved ),
				'assembly' => $state->quote->assembly,
				'fee'      => $money( $state->quote->fee ),
				'total'    => $money( $state->quote->total ),
			),
		);
	}

	/**
	 * A slot's label, as another plugin would have it said (spec: French and other languages). An answer that is no
	 * text leaves the owner's words as they are, here and in the two methods below.
	 *
	 * @since 0.5.0 Public, for the cart's sentences.
	 *
	 * @param string $label The owner's label.
	 * @param string $slot  The slot's key.
	 * @param int    $kit   The kit product's ID.
	 */
	public static function label( string $label, string $slot, int $kit ): string {
		/**
		 * Filter a slot's label as a shopper reads it. The label is the shop owner's own words, so no translation file
		 * holds it: a translation plugin answers here.
		 *
		 * @since 0.4.0
		 *
		 * @param string $label The label the owner typed.
		 * @param string $slot  The slot's key, such as trucks.
		 * @param int    $kit   The kit product's ID.
		 */
		$said = apply_filters( 'cbuild_slot_label', $label, $slot, $kit );
		return is_string( $said ) ? $said : $label;
	}

	/**
	 * A preset's name, as another plugin would have it said.
	 *
	 * @param string $name     The owner's name for it.
	 * @param int    $position Where the preset stands among the kit's, from 0.
	 * @param int    $kit      The kit product's ID.
	 */
	private static function preset( string $name, int $position, int $kit ): string {
		/**
		 * Filter a preset's name as a shopper reads it.
		 *
		 * @since 0.4.0
		 *
		 * @param string $name     The name the owner typed.
		 * @param int    $position Where the preset stands among the kit's, from 0.
		 * @param int    $kit      The kit product's ID.
		 */
		$said = apply_filters( 'cbuild_preset_label', $name, $position, $kit );
		return is_string( $said ) ? $said : $name;
	}

	/**
	 * Another of the owner's texts, as another plugin would have it said.
	 *
	 * @param string $text  The owner's words.
	 * @param string $field Which text it is.
	 * @param int    $kit   The kit product's ID.
	 * @param string $of    The slot's key or the preset's position, for a text that belongs to one.
	 */
	public static function text( string $text, string $field, int $kit, string $of = '' ): string {
		/**
		 * Filter one of a kit's texts as a shopper reads it: a slot's help, a preset's description, the lead time or
		 * the returns notice.
		 *
		 * @since 0.4.0
		 *
		 * @param string $text  The text the owner typed.
		 * @param string $field Which text: `slot_help`, `preset_description`, `lead_time` or `returns_notice`.
		 * @param int    $kit   The kit product's ID.
		 * @param string $of    A slot's key for `slot_help`, a preset's position from 0 for `preset_description`, and
		 *                      an empty string otherwise.
		 */
		$said = apply_filters( 'cbuild_kit_text', $text, $field, $kit, $of );
		return is_string( $said ) ? $said : $text;
	}

	/**
	 * The options a list shows: those the shop can supply before those it cannot, the first 24, and the one the step
	 * counts whether or not it is among them.
	 *
	 * @param list<Option> $options The list, in the engine's order.
	 * @param Option|null  $counted The option the step counts.
	 * @return list<Option>
	 */
	private static function shown( array $options, ?Option $counted ): array {
		$here = array();
		$gone = array();
		foreach ( $options as $option ) {
			if ( $option->available() ) {
				$here[] = $option;
			} else {
				$gone[] = $option;
			}
		}
		$all   = array_merge( $here, $gone );
		$shown = array_slice( $all, 0, self::SHOWN );
		foreach ( array_slice( $all, self::SHOWN ) as $option ) {
			if ( $option->part->id === $counted?->part->id ) {
				$shown[] = $option;
			}
		}
		return $shown;
	}

	/**
	 * Findings as the answer carries them: the rule, the code, how bad, and the sentence.
	 *
	 * @param list<Finding> $findings The findings.
	 * @return list<array{rule: string, code: string, severity: string, text: string}>
	 */
	private static function messages( array $findings ): array {
		$messages = array();
		foreach ( $findings as $finding ) {
			$messages[] = array(
				'rule'     => $finding->rule->value,
				'code'     => $finding->code->value,
				'severity' => $finding->severity->value,
				'text'     => Words::finding( $finding ),
			);
		}
		return $messages;
	}
}
