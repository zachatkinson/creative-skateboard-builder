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
	 * The card of a build.
	 *
	 * @since 0.7.0
	 *
	 * @param State                $state  The build's state, as the page shows it.
	 * @param array<string, mixed> $answer The state's answer.
	 * @param BuildLink            $link   The address it was asked at.
	 * @param string               $base   The address of the page the builder is on, with no build in it.
	 * @return array{title: string, description: string, url: string, image: array{src: string, width: int, height: int, type: string}|null}
	 */
	public function of( State $state, array $answer, BuildLink $link, string $base ): array {
		$counted = $state->counted();
		$picks   = array_map( static fn( $part ): int => $part->id, $counted );
		return array(
			'title'       => $this->title( $counted ),
			'description' => self::description( Cast::str( Cast::map( Cast::map( $answer['total'] ?? null )['total'] ?? null )['text'] ?? null ) ),
			'url'         => $link->shared()->at( State::REVIEW )->address( $base, $state->kit->slots ),
			'image'       => $this->image( $state, $picks, Cast::map( $answer['picture'] ?? null ) ),
		);
	}

	/**
	 * The build's name, as "8.25 in complete: Rascals, Independent 8.5 in, Retna 54 mm".
	 *
	 * @param array<string, \CreativeBranding\SkateboardBuilder\Domain\Kit\Part> $counted The parts, by slot.
	 */
	private function title( array $counted ): string {
		$slugs  = array_values( array_unique( array_filter( array_map( static fn( $part ): string => $part->brand, $counted ), static fn( string $slug ): bool => '' !== $slug ) ) );
		$brands = array() === $slugs ? array() : $this->cards->brands( $slugs );
		$named  = array();
		foreach ( self::NAMED as $slot ) {
			$part = $counted[ $slot ] ?? null;
			if ( null === $part ) {
				continue;
			}
			$brand = $brands[ $part->brand ] ?? '';
			// The deck's width heads the name, so the deck is named by its brand alone.
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
	 * The card's image: the composed picture, else the deck's photo, else its layer image, else none. The photo comes
	 * before the layer: a layer is a narrow cut-out on transparency, which platforms draw on black (the audit of
	 * milestone 7).
	 *
	 * @param State                $state   The build's state.
	 * @param array<string, int>   $picks   The part counted in each slot.
	 * @param array<string, mixed> $picture The answer's picture.
	 * @return array{src: string, width: int, height: int, type: string}|null
	 */
	private function image( State $state, array $picks, array $picture ): ?array {
		if ( $state->ready() ) {
			$composed = $this->pictures->url( $state->id, $picks, ComposedPictures::SHARE );
			if ( null !== $composed ) {
				[ $width, $height, $type ] = ComposedPictures::CANVASES[ ComposedPictures::SHARE ];
				return array(
					'src'    => $composed,
					'width'  => $width,
					'height' => $height,
					'type'   => $type,
				);
			}
			$this->pictures->ask( $state->id, $picks, ComposedPictures::FROM_PAGE );
		}
		foreach ( array( 'items', 'layers' ) as $list ) {
			foreach ( Cast::items( $picture[ $list ] ?? null ) as $entry ) {
				$entry = Cast::map( $entry );
				$image = SlotKey::DECK === Cast::str( $entry['slot'] ?? null ) ? self::shown( Cast::map( $entry['image'] ?? null ) ) : null;
				if ( null !== $image ) {
					return $image;
				}
			}
		}
		return null;
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
