<?php
/**
 * A build's composed pictures: asked for, drawn in the background, and found by their names.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Canvas;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Composition;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
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
	 * The daily job that prunes the pictures. `Options::JOB_PRUNE_PICTURES` names the same action for the hooks.
	 *
	 * @since 0.7.0
	 */
	public const string PRUNE = 'cbuild_prune_pictures';

	/**
	 * A day, in seconds: how often the pruning runs.
	 *
	 * @since 0.7.0
	 */
	public const int DAY = 86400;

	/**
	 * How long a picture is kept after it was last shown, in days (spec: Options and jobs).
	 *
	 * @since 0.7.0
	 */
	public const int KEPT_DAYS = 30;

	/**
	 * The most the pictures' folder may hold, in bytes: 200 MB, about a thousand builds' three pictures (D-198).
	 *
	 * @since 0.7.0
	 */
	public const int MOST_BYTES = 200 * 1024 * 1024;

	/**
	 * What the pictures' test finds: pictures can be made, the host has no library, or the folder cannot be written.
	 *
	 * @since 0.7.0
	 */
	public const string HEALTH_FINE       = 'pictures_fine';
	public const string HEALTH_NO_LIBRARY = 'pictures_no_library';
	public const string HEALTH_UNWRITABLE = 'pictures_unwritable';

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
	 * The most of the queue a page's asks may take: a build link's page and the review's answer are asked by anyone,
	 * so they share these places, and an add to the cart, which a shopper makes, keeps the rest (the audit of
	 * milestone 7; D-205).
	 *
	 * @since 0.7.0
	 */
	public const int PAGE_MOST = 10;

	/**
	 * Who asks: an add to the cart, or a page.
	 *
	 * @since 0.7.0
	 */
	public const string FROM_CART = 'cart';
	public const string FROM_PAGE = 'page';

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
	 * @param Clock        $clock   The time.
	 * @param Logger       $logger  Where a picture that cannot be drawn is written, at most hourly for a kit.
	 */
	public function __construct(
		private Kits $kits,
		private BuildState $states,
		private LayerArtwork $artwork,
		private PictureFiles $files,
		private Queue $queue,
		private Clock $clock,
		private Logger $logger
	) {}

	/**
	 * Ask for a build's pictures. Nothing is asked while the queue is full, or a page's share of it for a page's
	 * ask, or for a build that names no part.
	 *
	 * @since 0.7.0
	 *
	 * @param int                $kit   The kit product's ID.
	 * @param array<string, int> $picks The part's ID for each slot filled, in step order.
	 * @param string             $from  FROM_CART or FROM_PAGE.
	 * @return bool Whether it was asked for.
	 */
	public function ask( int $kit, array $picks, string $from = self::FROM_CART ): bool {
		$most = self::FROM_PAGE === $from ? self::PAGE_MOST : self::QUEUED_MOST;
		if ( $kit < 1 || array() === $picks || $this->queue->pending( self::JOB, $most ) >= $most ) {
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
	 * A build's state answered: on a review that could go to the cart, where its link is offered to be shared, its
	 * picture is asked for, as a page asks, unless it is made. So the card's first unfurl is the picture, once the job
	 * has run (the audit of milestone 7).
	 *
	 * @since 0.7.0
	 *
	 * @param State $state The state answered.
	 */
	public function offered( State $state ): void {
		if ( State::REVIEW !== $state->step || ! $state->ready() || array() !== $state->refused ) {
			return;
		}
		$counted = $state->counted();
		$card    = $this->drawings( $state->kit, $counted, array( self::SHARE => self::CANVASES[ self::SHARE ] ) )[ self::SHARE ] ?? null;
		if ( null === $card || null !== $this->files->url( $card->name() ) ) {
			// Nothing of the build is drawn, or its picture is made.
			return;
		}
		$picks = array();
		foreach ( $counted as $slot => $part ) {
			$picks[ $slot ] = $part->id;
		}
		$this->ask( $state->id, $picks, self::FROM_PAGE );
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
		$compositions = $this->compositions( $kit, $picks );
		if ( array() !== $compositions ) {
			// The folder's cap holds before each job draws, not only once a day (the audit of milestone 7).
			$this->prune();
		}
		$there = 0;
		foreach ( $compositions as $composition ) {
			$failed = $this->files->draw( $composition );
			if ( null === $failed ) {
				++$there;
				continue;
			}
			$this->logger->hourly(
				'picture_failed_' . $kit,
				Logger::WARNING,
				'A build\'s picture could not be drawn, so the deck\'s photo stands in for it. Site Health says whether the host can draw pictures.',
				array(
					'kit'    => $kit,
					'reason' => $failed,
				) 
			);
		}
		if ( $there > 0 ) {
			// The pruning starts with the first picture, and runs daily from then; deactivation unschedules it.
			$now = $this->clock->now()->getTimestamp();
			$this->queue->recurringUnique( $now + self::DAY, self::DAY, self::PRUNE );
		}
		return $there;
	}

	/**
	 * The address of a build's picture for one use, or null while it is not made, or where nothing of it is drawn.
	 * It reads the build's parts from the shop and lays them out, and runs no fit: a file of the name is there only
	 * where the job drew it, for a build that passed validation, with these parts' images in these places. So the
	 * cart, which asks for each of its builds on every view, pays for the parts it already reads and a file's check.
	 *
	 * @since 0.7.0
	 *
	 * @param int                $kit     The kit product's ID.
	 * @param array<string, int> $picks   The part's ID for each slot filled.
	 * @param string             $purpose One of CART, SHARE and EMAIL.
	 */
	public function url( int $kit, array $picks, string $purpose ): ?string {
		$found = $this->kits->kit( $kit );
		if ( null === $found || array() === $picks || ! isset( self::CANVASES[ $purpose ] ) ) {
			return null;
		}
		$asked = array();
		foreach ( $picks as $slot => $part ) {
			$filled = $found->slots->get( $slot );
			if ( null === $filled ) {
				return null;
			}
			$asked[ $slot ] = array( $filled, $part );
		}
		$read  = $this->kits->picked( $asked );
		$parts = array();
		foreach ( $picks as $slot => $part ) {
			if ( ! isset( $read[ $slot ] ) ) {
				// A part the shop no longer sells: no picture of the build is found.
				return null;
			}
			$parts[ $slot ] = $read[ $slot ];
		}
		$composition = $this->drawings( $found, $parts, array( $purpose => self::CANVASES[ $purpose ] ) )[ $purpose ] ?? null;
		$url         = null === $composition ? null : $this->files->url( $composition->name() );
		if ( null !== $composition && null !== $url ) {
			$this->files->used( $composition->name(), $this->clock->now()->getTimestamp() );
		}
		return $url;
	}

	/**
	 * The daily job: delete the pictures not shown for KEPT_DAYS, then the oldest until the folder is under its cap.
	 *
	 * @since 0.7.0
	 *
	 * @return int How many files were deleted.
	 */
	public function prune(): int {
		return $this->files->prune( $this->clock->now()->getTimestamp() - self::KEPT_DAYS * self::DAY, self::MOST_BYTES );
	}

	/**
	 * Whether this host can make pictures, for Site Health.
	 *
	 * @since 0.7.0
	 *
	 * @return array{status: string, code: string}
	 */
	public function health(): array {
		$code = self::HEALTH_FINE;
		foreach ( self::CANVASES as [ , , $type ] ) {
			if ( null === $this->files->library( $type ) ) {
				$code = self::HEALTH_NO_LIBRARY;
			}
		}
		if ( self::HEALTH_FINE === $code && ! $this->files->writable() ) {
			$code = self::HEALTH_UNWRITABLE;
		}
		return array(
			'status' => self::HEALTH_FINE === $code ? Health::GOOD : Health::RECOMMENDED,
			'code'   => $code,
		);
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
		return $this->drawings( $state->kit, $counted, self::CANVASES );
	}

	/**
	 * A build's drawings on some canvases, leaving out an empty one.
	 *
	 * @param Kit                                    $kit      The kit.
	 * @param array<string, Part>                    $parts    The build's parts, by slot in step order.
	 * @param array<string, array{int, int, string}> $canvases By use, as CANVASES.
	 * @return array<string, Composition>
	 */
	private function drawings( Kit $kit, array $parts, array $canvases ): array {
		$built = Picks::none();
		foreach ( $parts as $slot => $part ) {
			$built = $built->with( $slot, $part );
		}
		$layout = Layout::of( $built, $kit->picture );
		$art    = $this->artwork->of( $layout, $parts );
		$drawn  = array();
		foreach ( $canvases as $use => [ $width, $height, $type ] ) {
			$composition = ( new ComposedRenderer( new Canvas( $width, $height, $type ) ) )->render( $layout, $art );
			if ( ! $composition->isEmpty() ) {
				$drawn[ $use ] = $composition;
			}
		}
		return $drawn;
	}
}
