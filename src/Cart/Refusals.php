<?php
/**
 * Why the cart refuses a build, in words.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cart;

use Closure;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\CartRefusal;
use CreativeBranding\SkateboardBuilder\Service\Verdict;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Turns the cart's service's verdict into what a shopper reads and what the Store API sends: a sentence for each
 * thing wrong with the build, naming its slot as the builder does, or the one reason.
 *
 * @since 0.5.0
 */
final class Refusals {

	/**
	 * Constructor.
	 *
	 * @since 0.5.0
	 *
	 * @param Closure(): Kits $kits The shop's kits, for a slot's label.
	 */
	public function __construct( private Closure $kits ) {}

	/**
	 * Why a build is refused, as sentences.
	 *
	 * @since 0.5.0
	 *
	 * @param int     $kit     The kit product's ID.
	 * @param Verdict $verdict What was said of the build.
	 * @return non-empty-list<string>
	 */
	public function sentences( int $kit, Verdict $verdict ): array {
		$sentences = array();
		$slots     = array() === $verdict->problems ? null : ( $this->kits )()->kit( $kit )?->slots;
		foreach ( $verdict->problems as $problem ) {
			$slot        = $slots?->get( $problem->slot );
			$sentences[] = Words::problem( $problem->code, null === $slot ? $problem->slot : StateAnswer::label( $slot->label, $slot->key, $kit ) );
		}
		return array() === $sentences ? array( Words::refusal( $verdict->refusal ?? CartRefusal::NoBuild ) ) : $sentences;
	}

	/**
	 * Why a build that is in the cart stops the order, as sentences: the first names the build and says what to
	 * do, and the rest say what is wrong with it. Nothing is said of adding: the build is in the cart and stays
	 * there until the shopper removes it.
	 *
	 * @since 0.5.0
	 *
	 * @param int     $kit     The kit product's ID.
	 * @param string  $name    The kit's name, as the cart's line shows it.
	 * @param Verdict $verdict What was said of the build.
	 * @return non-empty-list<string>
	 */
	public function held( int $kit, string $name, Verdict $verdict ): array {
		if ( array() === $verdict->problems ) {
			return array(
				CartRefusal::KitNotFound === $verdict->refusal
					/* translators: %s: a kit's name, such as Build a complete. */
					? sprintf( __( '%s is no longer sold. Remove it from your cart to check out.', 'creative-skateboard-builder' ), $name )
					/* translators: %s: a kit's name, such as Build a complete. */
					: sprintf( __( '%s cannot be bought as it is now. Remove it from your cart to check out.', 'creative-skateboard-builder' ), $name ),
			);
		}
		/* translators: %s: a kit's name, such as Build a complete. */
		$lead = sprintf( __( '%s cannot be bought as it is now. Remove it from your cart to check out, then build it again.', 'creative-skateboard-builder' ), $name );
		return array( $lead, ...$this->sentences( $kit, $verdict ) );
	}

	/**
	 * A refusal's problems as the Store API sends them: each one's code, slot and part.
	 *
	 * @since 0.5.0
	 *
	 * @param Verdict $verdict What was said of the build.
	 * @return list<array{code: string, slot: string, part: int|null}>
	 */
	public static function problems( Verdict $verdict ): array {
		$problems = array();
		foreach ( $verdict->problems as $problem ) {
			$problems[] = array(
				'code' => $problem->code->value,
				'slot' => $problem->slot,
				'part' => $problem->part,
			);
		}
		return $problems;
	}
}
