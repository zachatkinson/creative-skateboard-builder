<?php
/**
 * A build's composed pictures: asked for, drawn in the background, and found by their names.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Canvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\PictureFiles;
use CreativeBranding\SkateboardBuilder\Port\Queue;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one place a build's composed picture is named, asked for and drawn (spec: Options and jobs; D-197). A
 * picture is asked for, never waited on: an add to the cart, Edit build's among them, queues the build's job, and the
 * job draws a picture for each use. The job takes the build's IDs only and works the build out again; a build that
 * does not pass validation now, or whose picks are not all its own, gets no picture. The queue is capped: while it
 * holds QUEUED_MOST builds, nothing more is asked for and the deck's photo stands in, so a stream of made-up builds
 * costs one count each. A part with no layer image is left out of the drawing, as it is in the page's.
 *
 * @since 0.7.0
 */
final class ComposedPictures {

	/**
	 * The job that draws a build's pictures. `Options::JOB_COMPOSE_PICTURE` names the same action for the hooks.
	 *
	 * @since 0.7.0
	 */
	public const string JOB = 'cbuild_compose_picture';

	/**
	 * The cart's thumbnail.
	 *
	 * @since 0.7.0
	 */
	public const string CART = 'cart';

	/**
	 * A build link's share card, in Open Graph's proportions.
	 *
	 * @since 0.7.0
	 */
	public const string SHARE = 'share';

	/**
	 * The emails', as a JPEG, which every mail client shows.
	 *
	 * @since 0.7.0
	 */
	public const string EMAIL = 'email';

	/**
	 * Each use's canvas: pixels across, pixels down, and the type.
	 *
	 * @since 0.7.0
	 */
	public const array CANVASES = array(
		self::CART  => array( 480, 480, 'image/webp' ),
		self::SHARE => array( 1200, 630, 'image/webp' ),
		self::EMAIL => array( 600, 600, 'image/jpeg' ),
	);

	/**
	 * The most builds waiting for their pictures. A build's three took 0.46 s and 7 MB with GD in wp-env, so a full
	 * queue is about one of Action Scheduler's 30-second runs (D-197).
	 *
	 * @since 0.7.0
	 */
	public const int QUEUED_MOST = 50;

	/**
	 * Constructor.
	 *
	 * @since 0.7.0
	 *
	 * @param Kits         $kits    The kits.
	 * @param BuildState   $states  Works a build out.
	 * @param LayerArtwork $artwork The parts' layer images.
	 * @param PictureFiles $files   The pictures' files.
	 * @param Queue        $queue   Where the job waits.
	 */
	public function __construct(
		private Kits $kits,
		private BuildState $states,
		private LayerArtwork $artwork,
		private PictureFiles $files,
		private Queue $queue
	) {}

	/**
	 * Ask for a build's pictures. Nothing is asked while the queue is full, or for a build that names no part.
	 *
	 * @since 0.7.0
	 *
	 * @param int                $kit   The kit product's ID.
	 * @param array<string, int> $picks The part's ID for each slot filled, in step order.
	 * @return bool Whether it was asked for.
	 */
	public function ask( int $kit, array $picks ): bool {
		if ( $kit < 1 || array() === $picks || $this->queue->pending( self::JOB, self::QUEUED_MOST ) >= self::QUEUED_MOST ) {
			return false;
		}
		$this->queue->enqueueDistinct(
			self::JOB,
			array(
				'kit'   => $kit,
				'picks' => $picks,
			) 
		);
		return true;
	}

	/**
	 * The job: draw a build's pictures, each use's once.
	 *
	 * @since 0.7.0
	 *
	 * @param int          $kit   The kit product's ID.
	 * @param array<mixed> $picks The part's ID for each slot filled, as the job was queued with them.
	 * @return int How many of its pictures are there now.
	 *
	 * @throws \CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney When the build cannot be priced in the
	 *                                                                         shop's currency settings.
	 */
	public function compose( int $kit, array $picks ): int {
		$there = 0;
		foreach ( $this->compositions( $kit, $picks ) as $composition ) {
			$there += $this->files->draw( $composition ) ? 1 : 0;
		}
		return $there;
	}

	/**
	 * The address of a build's picture for one use, or null while it is not made, or where nothing of it is drawn.
	 *
	 * @since 0.7.0
	 *
	 * @param int          $kit   The kit product's ID.
	 * @param array<mixed> $picks The part's ID for each slot filled.
	 * @param string       $purpose One of CART, SHARE and EMAIL.
	 *
	 * @throws \CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney When the build cannot be priced in the
	 *                                                                         shop's currency settings.
	 */
	public function url( int $kit, array $picks, string $purpose ): ?string {
		$composition = $this->compositions( $kit, $picks )[ $purpose ] ?? null;
		return null === $composition ? null : $this->files->url( $composition->name() );
	}

	/**
	 * A build's drawings, by use: none for a build that does not pass validation, one with a pick that is not its
	 * slot's, or one with nothing to draw.
	 *
	 * @param int          $kit   The kit product's ID.
	 * @param array<mixed> $picks The part's ID for each slot filled.
	 * @return array<string, Composition>
	 *
	 * @throws \CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney When the build cannot be priced.
	 */
	private function compositions( int $kit, array $picks ): array {
		$found = $this->kits->kit( $kit );
		if ( null === $found ) {
			return array();
		}
		$state = $this->states->of( $kit, $picks, null, State::REVIEW, null, array_values( array_diff( $found->slots->keys(), array_keys( $picks ) ) ) );
		if ( null === $state || ! $state->ready() || array() !== $state->refused ) {
			return array();
		}
		$counted = $state->counted();
		$ids     = array();
		foreach ( $counted as $slot => $part ) {
			$ids[ $slot ] = $part->id;
		}
		if ( $ids !== $picks ) {
			// A recommendation filled a slot the build left: the picture would show a part the shopper did not choose.
			return array();
		}
		$built = Picks::none();
		foreach ( $counted as $slot => $part ) {
			$built = $built->with( $slot, $part );
		}
		$layout = Layout::of( $built, $state->kit->picture );
		$art    = $this->artwork->of( $layout, $counted );
		$drawn  = array();
		foreach ( self::CANVASES as $use => [ $width, $height, $type ] ) {
			$composition = ( new ComposedRenderer( new Canvas( $width, $height, $type ) ) )->render( $layout, $art );
			if ( ! $composition->isEmpty() ) {
				$drawn[ $use ] = $composition;
			}
		}
		return $drawn;
	}
}
