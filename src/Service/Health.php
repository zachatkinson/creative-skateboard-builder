<?php
/**
 * What Site Health says of the plugin.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

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
	public const JOBS_FINE   = 'jobs_fine';
	public const JOBS_FAILED = 'jobs_failed';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param ReindexStore $reindex The reindex's place.
	 * @param Queue        $queue   The background queue.
	 */
	public function __construct( private ReindexStore $reindex, private Queue $queue ) {}

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
	 * Whether any of the plugin's background jobs has failed.
	 *
	 * @since 0.3.0
	 *
	 * @return array{status: string, code: string, failed: int}
	 */
	public function jobs(): array {
		$failed = $this->queue->failed();
		return array(
			'status' => $failed > 0 ? self::RECOMMENDED : self::GOOD,
			'code'   => $failed > 0 ? self::JOBS_FAILED : self::JOBS_FINE,
			'failed' => $failed,
		);
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
