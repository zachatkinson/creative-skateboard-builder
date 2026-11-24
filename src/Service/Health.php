<?php
/**
 * What Site Health says of the plugin.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Whether the specs index is up to date, and whether background work has failed (spec: Resilience, failures are
 * found). It reads the reindex's saved place and asks the queue two questions; it reads no part, so Site Health's
 * page and its weekly check cost next to nothing.
 *
 * @since 0.3.0
 */
final class Health {

	/**
	 * How bad: Site Health's own three words.
	 */
	public const GOOD        = 'good';
	public const RECOMMENDED = 'recommended';
	public const CRITICAL    = 'critical';

	/**
	 * What the index's test finds.
	 */
	public const INDEX_CURRENT  = 'index_current';
	public const INDEX_BUILDING = 'index_building';
	public const INDEX_STALLED  = 'index_stalled';
	public const INDEX_FAILED   = 'index_failed';

	/**
	 * What the jobs' test finds.
	 */
	public const JOBS_FINE      = 'jobs_fine';
	public const JOBS_FAILED    = 'jobs_failed';
	public const IMPORT_FAILED  = 'import_failed';
	public const IMPORT_STALLED = 'import_stalled';

	/**
	 * What the Catalogue check's verdict can say.
	 */
	public const CHECK_CLEAR  = 'check_clear';
	public const CHECK_FOUND  = 'check_found';
	public const CHECK_LAYERS = 'check_layers';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ReindexStore $reindex The reindex's place.
	 * @param Queue        $queue   The background queue.
	 * @param ImportStore  $import  The import in hand.
	 */
	public function __construct( private ReindexStore $reindex, private Queue $queue, private ImportStore $import ) {}

	/**
	 * Whether the index is up to date.
	 *
	 * A reindex that stopped after its failures leaves parts with what they held before: critical, since the
	 * builder then offers by old figures. One that is under way is fine. One that is under way with no action of
	 * its own pending or running has lost its job, and waits to be asked for again.
	 *
	 * @since 0.3.0
	 *
	 * @return array{status: string, code: string, parts: int}
	 */
	public function index(): array {
		$state = ReindexState::fromArray( $this->reindex->load() );
		$parts = $state->parts ?? 0;
		if ( null === $state || ReindexState::DONE === $state->phase ) {
			return self::verdict( self::GOOD, self::INDEX_CURRENT, $parts );
		}
		if ( ReindexState::FAILED === $state->phase ) {
			return self::verdict( self::CRITICAL, self::INDEX_FAILED, $parts );
		}
		if ( $this->queue->isPending( IndexJobs::JOB_REINDEX ) ) {
			return self::verdict( self::GOOD, self::INDEX_BUILDING, $parts );
		}
		return self::verdict( self::RECOMMENDED, self::INDEX_STALLED, $parts );
	}

	/**
	 * Whether any of the plugin's background work has failed or stopped.
	 *
	 * A job that failed before the last reindex finished is not counted: that reindex read every part again. An
	 * import catches its own failures, so its actions end well and its state says what happened: stopped after its
	 * failures, or still marked running with no action left to run it.
	 *
	 * @since 0.3.0
	 *
	 * @return array{status: string, code: string, failed: int}
	 */
	public function jobs(): array {
		$failed = $this->queue->failed( self::since( ReindexState::fromArray( $this->reindex->load() ) ) );
		$import = ImportState::fromArray( $this->import->load() );
		$code   = self::JOBS_FINE;
		if ( $failed > 0 ) {
			$code = self::JOBS_FAILED;
		} elseif ( ImportState::FAILED === $import?->status ) {
			$code = self::IMPORT_FAILED;
		} elseif ( ImportState::RUNNING === $import?->status && ! $this->queue->isPending( SpecImport::JOB ) ) {
			$code = self::IMPORT_STALLED;
		}
		return array(
			'status' => self::JOBS_FINE === $code ? self::GOOD : self::RECOMMENDED,
			'code'   => $code,
			'failed' => $failed,
		);
	}

	/**
	 * What the Catalogue check found, for Site Health. Background work that failed is left out: the index's and the
	 * jobs' tests report it, and one failure is not shown three times. Parts with no layer image are counted apart, by
	 * part: they stay in the builder with their photo drawn, so they are not things that keep a part out (D-211).
	 *
	 * @since 0.3.0
	 *
	 * @param list<CheckFinding> $found The check's findings.
	 * @return array{status: string, code: string, findings: int, parts: int, layers: int}
	 */
	public static function catalogue( array $found ): array {
		$findings = 0;
		$parts    = array();
		$layers   = array();
		foreach ( $found as $finding ) {
			// Background work has its own test, variations that share a card are what the builder does (D-281), and a
			// part kept out is what the shop chose (#116).
			if ( in_array( $finding->code, array( CatalogueCheck::REINDEX_FAILED, CatalogueCheck::IMPORT_FAILED, CatalogueCheck::JOBS_FAILED, CatalogueCheck::LOOK_SHARED, CatalogueCheck::NOT_OFFERED ), true ) ) {
				continue;
			}
			if ( CatalogueCheck::LAYER_MISSING === $finding->code ) {
				$layers[ $finding->part ] = $finding->part;
				continue;
			}
			++$findings;
			if ( $finding->part > 0 ) {
				$parts[ $finding->part ] = $finding->part;
			}
		}
		$code = self::CHECK_CLEAR;
		if ( $findings > 0 ) {
			$code = self::CHECK_FOUND;
		} elseif ( array() !== $layers ) {
			$code = self::CHECK_LAYERS;
		}
		return array(
			'status'   => self::CHECK_CLEAR === $code ? self::GOOD : self::RECOMMENDED,
			'code'     => $code,
			'findings' => $findings,
			'parts'    => count( $parts ),
			'layers'   => count( $layers ),
		);
	}

	/**
	 * The moment failed jobs count from: when the last reindex finished, or the beginning when none has.
	 *
	 * @since 0.3.0
	 *
	 * @param ReindexState|null $reindex The reindex's state.
	 * @return int A UTC timestamp, 0 for the beginning.
	 */
	public static function since( ?ReindexState $reindex ): int {
		return ReindexState::DONE === $reindex?->phase ? ( $reindex->finishedAt ?? 0 ) : 0;
	}

	/**
	 * The index's verdict.
	 *
	 * @param string $status How bad.
	 * @param string $code   What was found.
	 * @param int    $parts  The parts the last reindex has read.
	 * @return array{status: string, code: string, parts: int}
	 */
	private static function verdict( string $status, string $code, int $parts ): array {
		return array(
			'status' => $status,
			'code'   => $code,
			'parts'  => $parts,
		);
	}
}
