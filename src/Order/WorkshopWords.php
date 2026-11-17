<?php
/**
 * What the workshop reads on an order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use CreativeBranding\SkateboardBuilder\Admin\Labels;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Service\Mismatch;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The words of the Build box, the assembly ticket and the orders list (spec: Admin, On orders; Pricing, stock,
 * assembly and returns). The services answer codes; each has one sentence here, through the text domain, and the
 * two filters a workshop answers to change the ticket.
 *
 * @since 0.8.0
 */
final class WorkshopWords {

	/**
	 * The most steps a ticket lists, so a filter's answer cannot run it to a second page.
	 *
	 * @since 0.8.0
	 */
	public const int STEPS_MOST = 20;

	/**
	 * The specs that matter at the bench, by slot (spec: Pricing, stock, assembly and returns, Assembly): deck width,
	 * truck size, wheel size, riser and bolt length. A truck's size is its axle's width, which is what the snapshot
	 * holds (D-219).
	 */
	private const array TICKET_SPECS = array(
		SlotKey::DECK     => array( SpecKey::DeckWidth ),
		SlotKey::TRUCKS   => array( SpecKey::AxleWidth ),
		SlotKey::WHEELS   => array( SpecKey::WheelDiameter ),
		SlotKey::RISERS   => array( SpecKey::RiserThickness ),
		SlotKey::HARDWARE => array( SpecKey::BoltLength ),
	);

	/**
	 * A mismatch as a sentence. A code this version does not know is shown as it is, so nothing is blank.
	 *
	 * @since 0.8.0
	 *
	 * @param Mismatch $mismatch The mismatch.
	 * @param string   $label    The label of the slot it is about, as the screen shows it.
	 * @param string   $sold     The part's name as it was sold, for PART_CHANGED.
	 */
	public static function mismatch( Mismatch $mismatch, string $label, string $sold = '' ): string {
		switch ( $mismatch->code ) {
			case Mismatch::PART_REMOVED:
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( 'The %s line is no longer on the order.', 'creative-skateboard-builder' ), $label );
			case Mismatch::QUANTITY_CHANGED:
				/* translators: 1: a slot's label, such as Wheels; 2: how many the line holds; 3: how many the build needs. */
				return sprintf( __( 'The %1$s line has %2$s, and the build needs %3$s.', 'creative-skateboard-builder' ), $label, number_format_i18n( $mismatch->found ), number_format_i18n( $mismatch->expected ) );
			case Mismatch::COPIED_WITHOUT_KEYS:
				/* translators: %s: a slot's label, such as Trucks. */
				return sprintf( __( 'The %s line was added again without the build\'s details, so it is no longer tied to the build.', 'creative-skateboard-builder' ), $label );
			case Mismatch::KIT_WITHOUT_BUILD:
				/* translators: %s: the kit's name on the order. */
				return sprintf( __( '%s is a kit\'s line with no build behind it. It was added outside the builder, so there is no ticket to print for it.', 'creative-skateboard-builder' ), $mismatch->name );
			case Mismatch::KIT_REMOVED:
				return __( 'The build\'s kit line is no longer on the order. Its parts are listed from their own lines.', 'creative-skateboard-builder' );
			case Mismatch::SNAPSHOT_UNREAD:
				return __( 'The record of this build as it was sold cannot be read. Its parts are listed from their own lines.', 'creative-skateboard-builder' );
			case Mismatch::PART_TWICE:
				return '' === $mismatch->slot
					? __( 'The build\'s kit line is on the order twice.', 'creative-skateboard-builder' )
					/* translators: %s: a slot's label, such as Trucks. */
					: sprintf( __( 'The %s line is on the order twice.', 'creative-skateboard-builder' ), $label );
			case Mismatch::PART_CHANGED:
				/* translators: 1: a slot's label, such as Trucks; 2: the product the line now holds; 3: the product the build was sold with. */
				return sprintf( __( 'The %1$s line is now %2$s. The build was sold with %3$s.', 'creative-skateboard-builder' ), $label, $mismatch->name, $sold );
			case Mismatch::PART_NOT_IN_BUILD:
				/* translators: %s: a line's name on the order. */
				return sprintf( __( '%s is tied to this build, but the build was sold without it.', 'creative-skateboard-builder' ), $mismatch->name );
		}
		return $mismatch->code;
	}

	/**
	 * The specs a ticket shows for a slot's part, as spec keys.
	 *
	 * @since 0.8.0
	 *
	 * @param string $slot The slot's key.
	 * @param int    $kit  The kit product's ID.
	 * @return list<string>
	 */
	public static function ticketSpecs( string $slot, int $kit ): array {
		$specs = array_map( static fn( SpecKey $key ): string => $key->value, self::TICKET_SPECS[ $slot ] ?? array() );
		/**
		 * Filter which specs the assembly ticket shows for a slot's part. The ticket shows those the part was sold with,
		 * in this order, each with its name and unit; a key that is not a spec key is left out.
		 *
		 * @since 0.8.0
		 *
		 * @param list<string> $specs The spec keys, such as `deck_width`: by default deck width, the trucks' axle
		 *                            width, wheel diameter, riser thickness and bolt length, each for its own slot.
		 * @param string       $slot  The slot's key, such as `trucks`.
		 * @param int          $kit   The kit product's ID.
		 */
		$said = apply_filters( 'cbuild_ticket_specs', $specs, $slot, $kit );
		return array_values( array_unique( array_filter( is_array( $said ) ? $said : array(), 'is_string' ) ) );
	}

	/**
	 * The steps a build's ticket lists, each with a tick box: for an assembled build, a step for what it has.
	 *
	 * @since 0.8.0
	 *
	 * @param array<string, int> $parts    The build's parts, slot key to part ID.
	 * @param bool               $assembly Whether the shop assembles it.
	 * @param int                $kit      The kit product's ID.
	 * @param int                $order    The order's ID.
	 * @return list<string>
	 */
	public static function steps( array $parts, bool $assembly, int $kit, int $order ): array {
		$steps = array();
		if ( $assembly ) {
			$has = static fn( string $slot ): bool => isset( $parts[ $slot ] );
			if ( $has( SlotKey::GRIP ) ) {
				$steps[] = __( 'Lay the grip tape on the deck, press it down and trim it to the edge.', 'creative-skateboard-builder' );
			}
			if ( $has( SlotKey::TRUCKS ) ) {
				$steps[] = $has( SlotKey::RISERS )
					? __( 'Bolt the trucks to the deck, with the risers between them.', 'creative-skateboard-builder' )
					: __( 'Bolt the trucks to the deck.', 'creative-skateboard-builder' );
			}
			if ( $has( SlotKey::WHEELS ) && $has( SlotKey::BEARINGS ) ) {
				$steps[] = __( 'Press the bearings into the wheels.', 'creative-skateboard-builder' );
			}
			if ( $has( SlotKey::WHEELS ) ) {
				$steps[] = __( 'Put the wheels on the trucks and tighten the axle nuts until each wheel spins without wobbling.', 'creative-skateboard-builder' );
			}
			$steps[] = __( 'Check every bolt and nut, and spin each wheel.', 'creative-skateboard-builder' );
		}
		/**
		 * Filter the steps the assembly ticket lists for a build, each with a tick box, so a workshop can add, change
		 * or remove a step without a template. Each is a sentence, shown as plain text; the ticket lists the first 20.
		 *
		 * @since 0.8.0
		 *
		 * @param list<string>                                                          $steps   The steps: for an assembled build, one for each thing it has (grip, trucks, bearings, wheels) and a last check; none for a build the shop does not assemble.
		 * @param array{kit: int, order: int, parts: array<string, int>, assembly: bool} $context The kit product's ID, the order's ID, the build's parts as slot key to part ID, and whether the shop assembles it.
		 */
		$said   = apply_filters(
			'cbuild_ticket_steps',
			$steps,
			array(
				'kit'      => $kit,
				'order'    => $order,
				'parts'    => $parts,
				'assembly' => $assembly,
			)
		);
		$listed = array();
		foreach ( is_array( $said ) ? $said : array() as $step ) {
			$step = is_string( $step ) ? wp_strip_all_tags( $step ) : '';
			if ( '' !== $step ) {
				$listed[] = $step;
			}
		}
		return array_slice( array_values( array_unique( $listed ) ), 0, self::STEPS_MOST );
	}

	/**
	 * A spec as sold, with its name and its value as a person reads it: a length the way skaters write it, a choice,
	 * a count or a flag in words. Null for a key this version does not know; a value that does not read is shown as
	 * it was kept.
	 *
	 * @since 0.8.0
	 *
	 * @param string $key   The spec's key.
	 * @param string $value Its value, as the index held it.
	 * @return array{label: string, value: string}|null
	 */
	public static function spec( string $key, string $value ): ?array {
		$spec = SpecKey::tryFrom( $key );
		if ( null === $spec ) {
			return null;
		}
		if ( null !== $spec->unit() ) {
			$measure = Specs::fromIndex( array( $key => $value ) )->measure( $spec );
			$said    = null === $measure ? $value : Words::measure( $measure, SpecKey::RiserThickness === $spec || SpecKey::BoltLength === $spec );
		} else {
			$said = Labels::value( $spec, $value );
		}
		return array(
			'label' => Labels::spec( $spec ),
			'value' => $said,
		);
	}

	/**
	 * What the orders list's Assembly column says of an order: how many boards to assemble, that its builds are not
	 * assembled, or nothing for an order with no build.
	 *
	 * @since 0.8.0
	 *
	 * @param int $builds How many builds the order holds.
	 * @param int $boards How many boards of them the shop assembles.
	 */
	public static function column( int $builds, int $boards ): string {
		if ( $boards > 0 ) {
			/* translators: %s: a number of boards. */
			return sprintf( _n( '%s board to assemble', '%s boards to assemble', $boards, 'creative-skateboard-builder' ), number_format_i18n( $boards ) );
		}
		return $builds > 0 ? __( 'Not assembled', 'creative-skateboard-builder' ) : '';
	}

	/**
	 * The refund help (spec: Pricing, stock, assembly and returns, Returns): WooCommerce's Restock refunded items
	 * covers a whole refund, so once the grip is applied the unused parts are restocked in a refund of their own.
	 *
	 * @since 0.8.0
	 */
	public static function refund(): string {
		return __( 'Restock refunded items covers a whole refund. Where the grip is already applied, refund the unused parts first with it ticked, then the grip and the rest in a second refund with it cleared.', 'creative-skateboard-builder' );
	}

	/**
	 * The build in one sentence, for whoever cannot see its picture: each part by its slot, as the builder says it.
	 * Empty for a build with no part.
	 *
	 * @since 0.8.0
	 *
	 * @param list<array{0: string, 1: string}> $parts Each part's slot label and name, in step order.
	 */
	public static function described( array $parts ): string {
		if ( array() === $parts ) {
			return '';
		}
		$said = array();
		foreach ( $parts as [ $label, $name ] ) {
			/* translators: 1: a slot's label, such as Deck. 2: the part's name. */
			$said[] = sprintf( __( '%1$s: %2$s', 'creative-skateboard-builder' ), $label, $name );
		}
		/* translators: Between two parts of the build, as in "Deck: …; Trucks: …". */
		$between = _x( '; ', 'between the parts of a build', 'creative-skateboard-builder' );
		/* translators: %s: each part of the build, by its slot, as "Deck: …; Trucks: …". */
		return sprintf( __( 'The build: %s.', 'creative-skateboard-builder' ), implode( $between, $said ) );
	}

	/**
	 * How many boards of a build the order holds, and whether the shop assembles them.
	 *
	 * @since 0.8.0
	 *
	 * @param bool $assembly Whether the shop assembles the build.
	 * @param int  $boards   How many boards.
	 */
	public static function assembled( bool $assembly, int $boards ): string {
		$count = number_format_i18n( $boards );
		return $assembly
			/* translators: %s: a number of boards. */
			? sprintf( _n( '%s board, assembled by the shop.', '%s boards, assembled by the shop.', $boards, 'creative-skateboard-builder' ), $count )
			/* translators: %s: a number of boards. */
			: sprintf( _n( '%s board, not assembled. The parts ship as they are.', '%s boards, not assembled. The parts ship as they are.', $boards, 'creative-skateboard-builder' ), $count );
	}

	/**
	 * A build's heading: its kit's name on the order, counted where the order holds more than one build.
	 *
	 * @since 0.8.0
	 *
	 * @param int    $at   Which build, from 1.
	 * @param int    $of   How many builds the order holds.
	 * @param string $name The kit's name on the order, empty where its line is gone.
	 */
	public static function title( int $at, int $of, string $name ): string {
		$name = '' === $name ? __( 'Build', 'creative-skateboard-builder' ) : $name;
		if ( $of < 2 ) {
			return $name;
		}
		/* translators: 1: which build, from 1; 2: how many builds the order holds; 3: the kit's name. */
		return sprintf( __( 'Build %1$s of %2$s: %3$s', 'creative-skateboard-builder' ), number_format_i18n( $at ), number_format_i18n( $of ), $name );
	}

	/**
	 * Which board a ticket is for, where a build has more than one; empty where it has one.
	 *
	 * @since 0.8.0
	 *
	 * @param int $at Which board, from 1.
	 * @param int $of How many boards of the build.
	 */
	public static function board( int $at, int $of ): string {
		/* translators: 1: which board, from 1; 2: how many boards of the build. */
		return $of < 2 ? '' : sprintf( __( 'Board %1$s of %2$s', 'creative-skateboard-builder' ), number_format_i18n( $at ), number_format_i18n( $of ) );
	}
}
