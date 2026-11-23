<?php
/**
 * What a build link's share card says and shows.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Spec, SEO: "A build link's page carries Open Graph and X card tags with the build's composed picture, its name
 * ("An 8.25″ complete: Rascals, Independent 149, Retna 54 mm") and its total. `og:url` is the build link, so the card
 * shows that build, while `rel=canonical` stays the clean page." (D-200.) The card is worked out from the state the
 * page already shows, so it costs no second fit. A build that can go to the cart asks for its composed picture;
 * until it is made the card shows the deck's layer image, or else the deck's photo.
 *
 * @since 0.7.0
 */
final class ShareCard {

	/**
	 * The slots a card names, in the order it names them: the parts a complete is known by.
	 */
	private const NAMED = array( SlotKey::DECK, SlotKey::TRUCKS, SlotKey::WHEELS );

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param ComposedPictures $pictures The composed pictures.
	 * @param PartCards        $cards    The parts' brands' names.
	 */
	public function __construct( private ComposedPictures $pictures, private PartCards $cards ) {}

	/**
	 * The card of a build, or null for one that is not ready to go to the cart: a part sold out, or no longer
	 * offered, would leave a card that names the build complete and totals what remains. Such a link keeps the kit's
	 * own card, as its page without a build has (Cowork's M7 review, M2; D-212).
	 *
	 * `waiting` says the card falls back from its composed picture, which is not made yet: the page is then not to be
	 * cached, or the first unfurl's photo would stay for the cache's lifetime (Cowork's M7 review, F5; D-212).
	 *
	 * @since 0.7.0
	 *
	 * @param State                $state  The build's state, as the page shows it.
	 * @param array<string, mixed> $answer The state's answer.
	 * @param BuildLink            $link   The address it was asked at.
	 * @param string               $base   The address of the page the builder is on, with no build in it.
	 * @return array{title: string, description: string, url: string, image: array{src: string, width: int, height: int, type: string}|null, waiting: bool}|null
	 */
	public function of( State $state, array $answer, BuildLink $link, string $base ): ?array {
		if ( ! $state->ready() ) {
			return null;
		}
		$counted             = $state->counted();
		$picks               = array_map( static fn( $part ): int => $part->id, $counted );
		[ $image, $waiting ] = $this->image( $state, $picks, Cast::map( $answer['picture'] ?? null ) );
		return array(
			'title'       => $this->title( $counted ),
			'description' => self::description( Cast::str( Cast::map( Cast::map( $answer['total'] ?? null )['total'] ?? null )['text'] ?? null ) ),
			// RFC 3986 reserves the brackets of `cbuild[deck]`: encoded, a strict parser reads the same link (M6).
			'url'         => str_replace( array( '[', ']' ), array( '%5B', '%5D' ), $link->shared()->at( State::REVIEW )->address( $base, $state->kit->slots ) ),
			'image'       => $image,
			'waiting'     => $waiting,
		);
	}

	/**
	 * The build's name, as "8.25 in complete: Baker, Independent 8.5 in, and Spitfire 54 mm": the deck by its brand,
	 * since its width heads the name, the trucks and wheels by brand and size. A part with no brand is named by its
	 * product's name, which says more than a bare size (Cowork's M7 review, M1; D-212).
	 *
	 * @since 0.8.0 Public, for My builds' name of a build its customer did not name.
	 *
	 * @param array<string, \CreativeBranding\SkateboardBuilder\Domain\Kit\Part> $counted The parts, by slot.
	 */
	public function title( array $counted ): string {
		$slugs    = array_values( array_unique( array_filter( array_map( static fn( $part ): string => $part->brand, $counted ), static fn( string $slug ): bool => '' !== $slug ) ) );
		$brands   = array() === $slugs ? array() : $this->cards->brands( $slugs );
		$nameless = array();
		foreach ( self::NAMED as $slot ) {
			$part = $counted[ $slot ] ?? null;
			if ( null !== $part && '' === ( $brands[ $part->brand ] ?? '' ) ) {
				$nameless[] = $part->id;
			}
		}
		$cards = array() === $nameless ? array() : $this->cards->cards( $nameless );
		$named = array();
		foreach ( self::NAMED as $slot ) {
			$part = $counted[ $slot ] ?? null;
			if ( null === $part ) {
				continue;
			}
			$brand = $brands[ $part->brand ] ?? '';
			if ( '' === $brand ) {
				$named[] = wp_strip_all_tags( $cards[ $part->id ]['name'] ?? '' );
				continue;
			}
			$size    = SlotKey::DECK === $slot ? '' : ( StateAnswer::size( $slot, $part )['text'] ?? '' );
			$named[] = Words::branded( $brand, $size );
		}
		$named = array_values( array_filter( $named, static fn( string $said ): bool => '' !== $said ) );
		$deck  = $counted[ SlotKey::DECK ] ?? null;
		$width = null === $deck ? '' : ( StateAnswer::size( SlotKey::DECK, $deck )['text'] ?? '' );
		return Words::complete( $width, $named );
	}

	/**
	 * The build's total in a sentence.
	 *
	 * @param string $total The total, as the shop writes an amount.
	 */
	private static function description( string $total ): string {
		/* translators: %s: the build's total, such as $184.00. */
		return '' === $total ? '' : sprintf( __( 'Total: %s', 'creative-skateboard-builder' ), $total );
	}

	/**
	 * The card's image: the composed picture, else the deck's photo, else its layer image, else none, and whether it
	 * falls back while the composed picture, which this host can draw, is not made yet. From 1.1.0 none in place of a
	 * deck another plugin blurs or leaves out of the share card (D-309). The photo comes before the layer: a layer is a narrow cut-out on
	 * transparency, which platforms draw on black (the audit of milestone 7).
	 *
	 * @param State                $state   The build's state, ready.
	 * @param array<string, int>   $picks   The part counted in each slot.
	 * @param array<string, mixed> $picture The answer's picture.
	 * @return array{0: array{src: string, width: int, height: int, type: string}|null, 1: bool}
	 */
	private function image( State $state, array $picks, array $picture ): array {
		$composed = $this->pictures->url( $state->id, $picks, ComposedPictures::SHARE );
		if ( null !== $composed ) {
			[ $width, $height, $type ] = ComposedPictures::CANVASES[ ComposedPictures::SHARE ];
			return array(
				array(
					'src'    => $composed,
					'width'  => $width,
					'height' => $height,
					'type'   => $type,
				),
				false,
			);
		}
		// A picture this host can draw is asked for, and the page waits for it; one that cannot be, with no layer image
		// to draw or no library, is never made, and the fallback is the card for good (the audit of the fixes; D-212).
		$waiting = $this->pictures->drawable( $state->id, $picks, ComposedPictures::SHARE );
		if ( $waiting ) {
			$this->pictures->ask( $state->id, $picks, ComposedPictures::FROM_PAGE );
		}
		$deck = $picks[ SlotKey::DECK ] ?? null;
		if ( null !== $deck && ! $this->pictures->shown( $state->id, SlotKey::DECK, $deck, ComposedPictures::SHARE ) ) {
			// A deck another plugin blurs or leaves out of the share card has no stand-in: its photo and its layer are
			// the artwork the card keeps from a link preview (#106; D-309).
			return array( null, $waiting );
		}
		foreach ( array( 'items', 'layers' ) as $list ) {
			foreach ( Cast::items( $picture[ $list ] ?? null ) as $entry ) {
				$entry = Cast::map( $entry );
				// An item's image is the deck's photo only where the card says so: without one, its card shows its layer
				// image small (D-282), and the layer's own file, from the layers, is the card's.
				$photo = 'layers' === $list || true === ( $entry['photo'] ?? null );
				$image = $photo && SlotKey::DECK === Cast::str( $entry['slot'] ?? null ) ? self::shown( Cast::map( $entry['image'] ?? null ) ) : null;
				if ( null !== $image ) {
					return array( $image, $waiting );
				}
			}
		}
		return array( null, $waiting );
	}

	/**
	 * An image as the card gives it, or null for none.
	 *
	 * @param array<string, mixed> $image An image as the answer carries it: `src`, `width` and `height`.
	 * @return array{src: string, width: int, height: int, type: string}|null
	 */
	private static function shown( array $image ): ?array {
		$src = Cast::str( $image['src'] ?? null );
		if ( '' === $src ) {
			return null;
		}
		return array(
			'src'    => $src,
			'width'  => Cast::int( $image['width'] ?? null ),
			'height' => Cast::int( $image['height'] ?? null ),
			'type'   => (string) ( wp_check_filetype( (string) wp_parse_url( $src, PHP_URL_PATH ) )['type'] ?? '' ),
		);
	}
}
