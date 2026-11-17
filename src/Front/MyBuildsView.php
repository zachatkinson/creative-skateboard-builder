<?php
/**
 * What My builds shows of each saved build.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Service\State;
use WC_DateTime;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Each of a customer's saved builds as My builds lists it (roadmap, milestone 8): its picture, its name, its total
 * today and the date it was saved, with Open, Rename and Delete. The build is checked again as it is listed, so a part
 * that is gone or no longer fits says so here, and Open takes the customer to the builder, which names it (D-217).
 *
 * @since 0.8.0
 */
final class MyBuildsView {

	/**
	 * The query parameter a build opened from My builds carries: its UUID. Only its owner's page reads it, and no build
	 * link, Copy link or `og:url` carries it (spec: Share links).
	 *
	 * @since 0.8.0
	 */
	public const string PARAM = 'cbuild_saved';

	/**
	 * Constructor.
	 *
	 * @since 0.8.0
	 *
	 * @param SavedBuilds      $saved    The customer's builds.
	 * @param BuildState       $builds   Checks and prices each one today.
	 * @param Kits             $kits     The kits, for their slots and the shop's decimals.
	 * @param Money            $money    Writes an amount as the shop does.
	 * @param ShareCard        $cards    Names a build its customer did not name.
	 * @param ComposedPictures $pictures The builds' pictures.
	 */
	public function __construct(
		private SavedBuilds $saved,
		private BuildState $builds,
		private Kits $kits,
		private Money $money,
		private ShareCard $cards,
		private ComposedPictures $pictures
	) {}

	/**
	 * The customer's builds, the last saved first, as the template prints them.
	 *
	 * @since 0.8.0
	 *
	 * @param int $user The customer's user ID.
	 * @return list<array{uuid: string, name: string, picture: string|null, deck: int, total: string, ready: bool, saved: string, open: string|null}>
	 */
	public function rows( int $user ): array {
		$rows = array();
		foreach ( $this->saved->of( $user ) as $build ) {
			$rows[] = $this->row( $build );
		}
		return $rows;
	}

	/**
	 * The address that opens a saved build in its builder, at the review, with its UUID for its owner's Save build to
	 * update it. Null where the shop no longer has the kit.
	 *
	 * @since 0.8.0
	 *
	 * @param SavedBuild $build The build.
	 */
	public function open( SavedBuild $build ): ?string {
		$kit = $this->kits->kit( $build->kit );
		if ( null === $kit || ! $this->kits->isPublic( $build->kit ) ) {
			return null;
		}
		$query = ( new BuildLink( $build->picks, null, State::REVIEW, $build->assembly ) )->query( $kit->slots );
		return add_query_arg(
			array(
				BuildLink::PARAM => urlencode_deep( $query ),
				self::PARAM      => $build->uuid,
			),
			BuilderUrl::of( $build->kit )
		);
	}

	/**
	 * When a build was last saved, as the shop writes dates, in its own time zone: WooCommerce's formatting, as its
	 * Orders list in My account writes an order's date.
	 *
	 * @param SavedBuild $build The build.
	 */
	private static function when( SavedBuild $build ): string {
		$date = new WC_DateTime( '@' . $build->updated->getTimestamp() );
		$date->setTimezone( wp_timezone() );
		return wc_format_datetime( $date );
	}

	/**
	 * One build's row.
	 *
	 * @param SavedBuild $build The build.
	 * @return array{uuid: string, name: string, picture: string|null, deck: int, total: string, ready: bool, saved: string, open: string|null}
	 */
	private function row( SavedBuild $build ): array {
		try {
			$state = $this->builds->of( $build->kit, $build->picks, null, State::REVIEW, $build->assembly );
		} catch ( InvalidMoney ) {
			$state = null;
		}
		$ready = null !== $state && $state->ready();
		$name  = $build->name;
		if ( '' === $name ) {
			$name = null === $state ? __( 'A saved build', 'creative-skateboard-builder' ) : $this->cards->title( $state->counted() );
		}
		$picture = null;
		if ( $ready ) {
			$picture = $this->pictures->url( $build->kit, $build->picks, ComposedPictures::CART );
			if ( null === $picture ) {
				// Made in the background for the next visit; the deck's photo stands in until then.
				$this->pictures->ask( $build->kit, $build->picks );
			}
		}
		return array(
			'uuid'    => $build->uuid,
			'name'    => $name,
			'picture' => $picture,
			'deck'    => $build->picks[ SlotKey::DECK ] ?? 0,
			'total'   => $ready ? $this->money->text( $state->quote->total, $this->kits->decimals() ) : '',
			'ready'   => $ready,
			'saved'   => self::when( $build ),
			'open'    => $this->open( $build ),
		);
	}
}
