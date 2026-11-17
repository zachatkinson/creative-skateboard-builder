<?php
/**
 * What the Build box and the assembly ticket show of an order.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Order;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\PictureSettings;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Specs;
use CreativeBranding\SkateboardBuilder\Front\PageRenderer;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Service\Mismatch;
use CreativeBranding\SkateboardBuilder\Service\OrderedBuild;
use CreativeBranding\SkateboardBuilder\Service\OrderReading;
use WC_Order;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An order's builds as the workshop reads them (spec: Admin, On orders; Pricing, stock, assembly and returns,
 * Assembly): each slot's label, the part and its SKU as sold, how many, its specs as sold from `_cbuild_snapshot`,
 * the picture's layers, the mismatch in sentences and the ticket's steps. The Build box and the ticket print this and
 * decide nothing.
 *
 * A slot's label is the kit's own, in the admin's language through `cbuild_slot_label`, where the kit still has the
 * slot; otherwise the one the shopper read, from the snapshot. The picture is laid out from the specs as sold, with
 * the kit's picture settings where the kit is still there and each part's layer image as it is now.
 *
 * @since 0.8.0
 *
 * @phpstan-type Spec array{label: string, value: string}
 * @phpstan-type Row array{slot: string, label: string, name: string, sku: string, quantity: int, specs: list<Spec>, ticket: list<Spec>}
 * @phpstan-type Board array{style: string, layers: list<array{slot: string, style: string, src: string, width: int, height: int, sources: list<array{type: string, srcset: string}>}>}
 * @phpstan-type Shown array{id: string, title: string, boards: int, assembly: bool, assembled: string, rows: list<Row>, problems: list<string>, board: Board|null, description: string, steps: list<string>}
 * @phpstan-type View array{order: int, number: string, customer: string, note: string, problems: list<string>, builds: list<Shown>, assembles: bool}
 */
final class WorkshopView {

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param Kits         $kits     The shop's kits, for a slot's label and the picture's settings.
	 * @param LayerArtwork $artwork  The parts' layer images.
	 * @param PageRenderer $renderer Draws the layout as layers.
	 */
	public function __construct( private Kits $kits, private LayerArtwork $artwork, private PageRenderer $renderer ) {}

	/**
	 * An order's builds, read from its lines as they are now.
	 *
	 * @since 0.8.0
	 *
	 * @param WC_Order $order The order.
	 * @return View
	 */
	public function of( WC_Order $order ): array {
		return $this->shown( $order, OrderLines::read( $order ) );
	}

	/**
	 * What the box and the ticket show, from a reading of the order.
	 *
	 * @since 0.8.0
	 *
	 * @param WC_Order     $order   The order.
	 * @param OrderReading $reading Its builds.
	 * @return View
	 */
	public function shown( WC_Order $order, OrderReading $reading ): array {
		$builds = array();
		$count  = count( $reading->builds );
		foreach ( $reading->builds as $at => $build ) {
			$builds[] = $this->build( $order, $reading, $build, $at + 1, $count );
		}
		$customer = $order->get_formatted_billing_full_name();
		return array(
			'order'     => $order->get_id(),
			'number'    => (string) $order->get_order_number(),
			'customer'  => '' === trim( $customer ) ? $order->get_formatted_shipping_full_name() : $customer,
			'note'      => $order->get_customer_note(),
			'problems'  => array_map( static fn( Mismatch $mismatch ): string => WorkshopWords::mismatch( $mismatch, '' ), $reading->about( '' ) ),
			'builds'    => $builds,
			'assembles' => $reading->toAssemble() > 0,
		);
	}

	/**
	 * One build.
	 *
	 * @param WC_Order     $order   The order.
	 * @param OrderReading $reading The order's builds.
	 * @param OrderedBuild $build   The build.
	 * @param int          $at      Which build, from 1.
	 * @param int          $of      How many builds the order holds.
	 * @return Shown
	 */
	private function build( WC_Order $order, OrderReading $reading, OrderedBuild $build, int $at, int $of ): array {
		$kit    = $build->kit > 0 ? $this->kits->kit( $build->kit ) : null;
		$labels = array();
		$rows   = array();
		$named  = array();
		$parts  = array();
		foreach ( $build->picks as $slot => $pick ) {
			$current         = $kit?->slots->get( $slot );
			$labels[ $slot ] = null === $current ? $pick->label : StateAnswer::label( $current->label, $slot, $build->kit );
			$specs           = array();
			$ticket          = array();
			foreach ( $pick->specs as $key => $value ) {
				$said = WorkshopWords::spec( $key, $value );
				if ( null !== $said ) {
					$specs[] = $said;
				}
			}
			foreach ( WorkshopWords::ticketSpecs( $slot, $build->kit ) as $key ) {
				$said = isset( $pick->specs[ $key ] ) ? WorkshopWords::spec( $key, $pick->specs[ $key ] ) : null;
				if ( null !== $said ) {
					$ticket[] = $said;
				}
			}
			$rows[]         = array(
				'slot'     => $slot,
				'label'    => $labels[ $slot ],
				'name'     => $pick->name,
				'sku'      => $pick->sku,
				'quantity' => $pick->quantity,
				'specs'    => $specs,
				'ticket'   => $ticket,
			);
			$parts[ $slot ] = $pick->part;
			if ( '' !== $pick->name ) {
				$named[] = array( $labels[ $slot ], $pick->name );
			}
		}
		$problems = array();
		foreach ( $reading->about( $build->id ) as $mismatch ) {
			$problems[] = WorkshopWords::mismatch( $mismatch, $labels[ $mismatch->slot ] ?? $mismatch->slot, $build->picks[ $mismatch->slot ]->name ?? '' );
		}
		$line = $build->item > 0 ? $order->get_item( $build->item ) : false;
		return array(
			'id'          => $build->id,
			'title'       => WorkshopWords::title( $at, $of, false === $line ? '' : $line->get_name() ),
			'boards'      => $build->boards,
			'assembly'    => $build->assembly,
			'assembled'   => WorkshopWords::assembled( $build->assembly, $build->boards ),
			'rows'        => $rows,
			'problems'    => $problems,
			'board'       => $build->read ? $this->board( $build, $kit?->picture ) : null,
			'description' => WorkshopWords::described( $named ),
			'steps'       => WorkshopWords::steps( $parts, $build->assembly, $build->kit, $order->get_id() ),
		);
	}

	/**
	 * The build's picture, laid out from its specs as sold and drawn from each part's layer image as it is now; null
	 * where nothing is drawn: no deck with a width, or no layer image.
	 *
	 * @param OrderedBuild         $build    The build, read from its snapshot.
	 * @param PictureSettings|null $settings The kit's picture settings, where the kit is still there.
	 * @return Board|null
	 */
	private function board( OrderedBuild $build, ?PictureSettings $settings ): ?array {
		$picks = Picks::none();
		$parts = array();
		foreach ( $build->picks as $slot => $pick ) {
			$parts[ $slot ] = new Part( $pick->part, '', Specs::fromIndex( $pick->specs ), 0, true );
			$picks          = $picks->with( $slot, $parts[ $slot ] );
		}
		$layout = Layout::of( $picks, $settings );
		return OrderBoard::placed( $this->renderer->render( $layout, $this->artwork->of( $layout, $parts ) ) );
	}
}
