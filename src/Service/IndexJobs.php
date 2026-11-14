<?php
/**
 * The jobs that keep the specs index.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\Reindex;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Port\SpecIndex;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The index is rebuilt in the background, never in the request that saves a product (spec: Performance). A change
 * to a product queues one job for it; a change to what every part is read with (the settings, a mapped attribute's
 * terms) queues a reindex, which runs 100 parts at a time from a saved place. Every job can run twice and leave the
 * index as running once would.
 *
 * @since 0.3.0
 */
final class IndexJobs implements Reindex {

	/**
	 * The jobs' actions. `Support\Options` lists the same names for deactivation and uninstall, and a test holds the
	 * two together: a service may not reach the support layer (deptrac).
	 */
	public const JOB_PART    = 'cbuild_index_part';
	public const JOB_REINDEX = 'cbuild_reindex';

	/**
	 * Parts in one batch of a reindex.
	 */
	public const BATCH = 100;

	/**
	 * How many times one part's job is tried again after it fails.
	 */
	public const RETRIES = 3;

	/**
	 * Seconds before a failed job's first retry; each later one waits twice as long.
	 */
	public const RETRY_AFTER = 60;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecIndexer   $indexer   Reads the sources and writes the index.
	 * @param SpecIndex     $index     The index.
	 * @param PartCatalogue $catalogue The shop's parts.
	 * @param Queue         $queue     The background queue.
	 * @param ReindexStore  $store     The reindex's place.
	 * @param ShopSettings  $settings  The mapping.
	 * @param Clock         $clock     The time.
	 * @param Logger        $logger    Where failures go.
	 * @param Kits          $kits      Told of the parts a batch indexed.
	 * @param SavedSources  $saved     What each save changed.
	 */
	public function __construct(
		private SpecIndexer $indexer,
		private SpecIndex $index,
		private PartCatalogue $catalogue,
		private Queue $queue,
		private ReindexStore $store,
		private ShopSettings $settings,
		private Clock $clock,
		private Logger $logger,
		private Kits $kits,
		private SavedSources $saved
	) {}

	/**
	 * A product or variation was saved over what was stored: queue its job, unless the save moved no source of the
	 * index. An order's stock change is such a save, one for every line (issue #21).
	 *
	 * @since 0.5.0
	 *
	 * @param int $id The product's or variation's ID.
	 */
	public function updated( int $id ): void {
		if ( $this->saved->moved( $id ) ) {
			$this->changed( $id );
		}
	}

	/**
	 * A product or variation was saved: queue its job. Nothing is read here, so a save costs one queue check. A
	 * running reindex is not told, as it is of a delete: a save moves no page under it, and this job indexes the
	 * part whichever of the two runs last (D-096).
	 *
	 * @since 0.3.0
	 *
	 * @param int $id The product's or variation's ID.
	 */
	public function changed( int $id ): void {
		$this->queue->enqueueDistinct( self::JOB_PART, array( 'part' => $id ) );
	}

	/**
	 * A post was deleted: when it was a product or a variation, queue its job, which will find nothing and remove
	 * its rows. A reindex walking the shop's pages is told, since a page just moved under it.
	 *
	 * @since 0.3.0
	 *
	 * @param int    $id       The post's ID.
	 * @param string $postType Its type.
	 */
	public function removed( int $id, string $postType ): void {
		if ( ! $this->catalogue->holds( $postType ) ) {
			return;
		}
		$this->changed( $id );
		$state = $this->state();
		if ( null !== $state && $state->isRunning() && ! $state->again ) {
			$this->store->save( $state->disturbed()->toArray() );
		}
	}

	/**
	 * A term was edited or deleted: when its taxonomy is one the shop mapped, every part with it may read differently.
	 * The mapping may name the attribute as the shop does, `Width` for `pa_width`, so the catalogue says whether a
	 * name is read from the taxonomy.
	 *
	 * @since 0.3.0
	 *
	 * @param string $taxonomy The term's taxonomy.
	 */
	public function termChanged( string $taxonomy ): void {
		foreach ( $this->settings->attributeMapping() as $map ) {
			if ( $this->catalogue->reads( $map->attribute, $taxonomy ) ) {
				$this->requestReindex();
				return;
			}
		}
	}

	/**
	 * The settings were saved: ask for a reindex when what the index reads from them changed, which is the mapping
	 * and the order of the sources. The option's other keys change nothing a part's row holds. Each side is read as
	 * the settings are read, so a default left out and a default written are the same.
	 *
	 * @since 0.4.0
	 *
	 * @param mixed $before The option as it was, or anything else when it was not there.
	 * @param mixed $after  The option as it is.
	 */
	public function settingsChanged( mixed $before, mixed $after ): void {
		if ( $this->settings->indexedFrom( $before ) !== $this->settings->indexedFrom( $after ) ) {
			$this->requestReindex();
		}
	}

	/**
	 * The job for one product: index the parts it stands for, and drop its own rows when it is not one of them (a
	 * variable product, or a product that is gone). A failure is logged with the product and tried again, a few
	 * times, each after a longer wait; then it is thrown, so the queue records the action as failed.
	 *
	 * @since 0.3.0
	 *
	 * @param int $id      The product's or variation's ID.
	 * @param int $attempt How many times it has failed before.
	 *
	 * @throws Throwable The failure, once it has been tried again as often as allowed.
	 */
	public function indexPart( int $id, int $attempt = 0 ): void {
		try {
			$parts = $this->catalogue->partsOf( $id );
			if ( ! in_array( $id, $parts, true ) ) {
				$this->index->forget( $id );
			}
			if ( array() !== $parts ) {
				$this->indexer->index( ...$parts );
				// What a build is told of these parts holds their specs as the index had them. Theirs alone: the
				// rest of the shop's parts stay as they are while the reindex runs.
				$this->kits->forget( ...$parts );
				// And written again here, in the background, so that no visitor's request reads the shop for them.
				$this->kits->warm( ...$parts );
			}
		} catch ( Throwable $failure ) {
			$this->logger->log(
				'error',
				'A part could not be indexed.',
				array(
					'part'    => $id,
					'attempt' => $attempt,
					'error'   => $failure->getMessage(),
				)
			);
			if ( $attempt >= self::RETRIES ) {
				throw $failure;
			}
			$this->queue->schedule(
				$this->clock->now()->getTimestamp() + self::RETRY_AFTER * 2 ** $attempt,
				self::JOB_PART,
				array(
					'part'    => $id,
					'attempt' => $attempt + 1,
				)
			);
		}
	}

	/**
	 * Ask for everything to be indexed again, from the start. Asked twice, it starts once: a reindex that is running
	 * goes back to its beginning, and one job is queued.
	 *
	 * @since 0.3.0
	 */
	public function requestReindex(): void {
		$this->begin();
		$this->queue->enqueueUnique( self::JOB_REINDEX );
	}

	/**
	 * Read every part again in this process, a batch after another, for the command line. Nothing is queued: a
	 * queue runner would take a queued batch and run it beside this one. A job an earlier request left waiting is
	 * cancelled, since this run does its work. A batch that fails stops the run, and its retry is the queue's.
	 *
	 * @since 0.3.0
	 *
	 * @return ReindexState|null Where it ended.
	 */
	public function reindexNow(): ?ReindexState {
		$this->queue->cancelAll( self::JOB_REINDEX );
		$this->begin();
		do {
			$this->runReindex( false );
			$state = $this->state();
		} while ( null !== $state && $state->isRunning() && 0 === $state->failures );
		return $state;
	}

	/**
	 * Save a reindex that starts from the beginning, as a run of its own.
	 */
	private function begin(): void {
		$run = ( $this->state()->run ?? 0 ) + 1;
		$this->store->save( ReindexState::begin( $this->clock->now()->getTimestamp(), $run )->toArray() );
	}

	/**
	 * The reindex's job: one batch from the saved place, the place saved, and the next batch queued. Stopped at any
	 * point, the next run starts from the last place saved, and a batch run twice writes the same rows.
	 *
	 * @since 0.3.0
	 *
	 * @param bool $queued Whether the next batch is queued. The command line runs its batches itself.
	 */
	public function runReindex( bool $queued = true ): void {
		$state = $this->state();
		if ( null === $state || ! $state->isRunning() ) {
			return;
		}
		try {
			if ( ReindexState::ROWS === $state->phase ) {
				$held     = $this->index->partsAfter( $state->position, self::BATCH );
				$position = array() === $held ? $state->position : $held[ count( $held ) - 1 ];
				// A row's product may no longer be a part: made variable, or deleted, while the plugin was off.
				// It is read as the parts it stands for now, as a save of it would be.
				$parts = array();
				foreach ( $held as $id ) {
					$stands = $this->catalogue->partsOf( $id );
					if ( ! in_array( $id, $stands, true ) ) {
						$this->index->forget( $id );
					}
					array_push( $parts, ...$stands );
				}
				$parts = array_unique( $parts );
			} else {
				$held     = $this->catalogue->page( $state->position + 1, self::BATCH );
				$parts    = $held;
				$position = $state->position + 1;
			}
			if ( array() !== $parts ) {
				$this->indexer->index( ...$parts );
				// What a build is told of these parts holds their specs as the index had them. Theirs alone: the
				// rest of the shop's parts stay as they are while the reindex runs.
				$this->kits->forget( ...$parts );
				// And written again here, in the background, so that no visitor's request reads the shop for them.
				$this->kits->warm( ...$parts );
			}
		} catch ( Throwable $failure ) {
			$failed = ( $this->state() ?? $state )->failed( $failure->getMessage() );
			$this->store->save( $failed->toArray() );
			$this->logger->log(
				'error',
				'A batch of the reindex failed.',
				array(
					'phase'    => $state->phase,
					'position' => $state->position,
					'failures' => $failed->failures,
					'error'    => $failure->getMessage(),
				)
			);
			if ( $failed->isRunning() ) {
				$this->queue->schedule( $this->clock->now()->getTimestamp() + self::RETRY_AFTER * 2 ** ( $failed->failures - 1 ), self::JOB_REINDEX );
			}
			return;
		}
		// Read again: a delete or a new request may have changed the state while the batch ran.
		$current = $this->state() ?? $state;
		if ( $current->run !== $state->run ) {
			// Asked for again from the start while this batch ran. That request's unique job was refused, since this
			// action was running, so the next batch is queued here and starts from the new beginning.
			if ( $queued ) {
				$this->queue->enqueue( self::JOB_REINDEX );
			}
			return;
		}
		$next = $current->advanced( $position, count( $held ), count( $held ) < self::BATCH, $this->clock->now()->getTimestamp() );
		$this->store->save( $next->toArray() );
		if ( $queued && $next->isRunning() ) {
			// Plain, not unique: this action is still running, and the unique check counts it.
			$this->queue->enqueue( self::JOB_REINDEX );
		}
	}

	/**
	 * Where the reindex is, or null when none has been asked for.
	 *
	 * @since 0.3.0
	 */
	public function state(): ?ReindexState {
		return ReindexState::fromArray( $this->store->load() );
	}
}
