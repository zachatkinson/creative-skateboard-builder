<?php
/**
 * The Catalogue check.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Domain\Fit\BuildValidator;
use CreativeBranding\SkateboardBuilder\Domain\Fit\FitEngine;
use CreativeBranding\SkateboardBuilder\Domain\Fit\Problem;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\ReindexStore;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What stands between the shop's catalogue and a builder that offers every part it should (spec: Admin, Catalogue
 * check). It decides nothing about fit: a part's findings are the engine's, asked of the part alone, and a preset's
 * problems are the build validator's. It runs when asked, reads the sources as the indexer does, and writes
 * nothing.
 *
 * @since 0.3.0
 */
final class CatalogueCheck {

	/**
	 * Parts read at a time.
	 */
	public const BATCH = 100;

	/**
	 * What it finds without a kit: two sources that say different things of a part, a value a source holds that
	 * cannot be read, and background work that failed.
	 */
	public const DISAGREE       = 'sources_disagree';
	public const UNREAD         = 'value_unread';
	public const REINDEX_FAILED = 'reindex_failed';
	public const IMPORT_FAILED  = 'import_failed';
	public const JOBS_FAILED    = 'jobs_failed';

	/**
	 * What it finds in a kit: a slot with no parts or none in stock, a part that lacks a spec a rule needs, a part
	 * a rule had to assume something of, and a preset that can no longer be bought as it is.
	 */
	public const SLOT_EMPTY    = 'slot_empty';
	public const SLOT_NO_STOCK = 'slot_out_of_stock';
	public const SPEC_MISSING  = 'spec_missing';
	public const ASSUMED       = 'spec_assumed';
	public const PRESET        = 'preset_broken';

	/**
	 * The engine's notes that mean a rule went without a spec and assumed the usual, each with the spec to fill in.
	 */
	private const NOTES = array(
		'pattern_assumed'  => 'hole_pattern',
		'axle_assumed'     => 'axle_diameter',
		'length_unchecked' => 'deck_length',
		'limit_from_class' => 'max_wheel',
	);

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Kits          $kits      The shop's kits.
	 * @param PartCatalogue $catalogue The shop's parts.
	 * @param SpecIndexer   $indexer   Reads the sources.
	 * @param ReindexStore  $reindex   Where the reindex is.
	 * @param ImportStore   $import    The import in hand.
	 * @param Queue         $queue     The background queue.
	 */
	public function __construct(
		private Kits $kits,
		private PartCatalogue $catalogue,
		private SpecIndexer $indexer,
		private ReindexStore $reindex,
		private ImportStore $import,
		private Queue $queue
	) {}

	/**
	 * Run the check.
	 *
	 * @since 0.3.0
	 *
	 * @return list<CheckFinding> Background work first, then each kit, then each part by ID. None when all is well.
	 */
	public function run(): array {
		$found = $this->work();
		foreach ( $this->kits->all() as $id => $kit ) {
			$found = array( ...$found, ...$this->kit( $id, $kit['kit'] ) );
		}
		$page = 0;
		do {
			$ids = $this->catalogue->page( ++$page, self::BATCH );
			if ( array() !== $ids ) {
				$found = array( ...$found, ...$this->parts( ...$ids ) );
			}
			$full = count( $ids ) >= self::BATCH;
		} while ( $full );
		return $found;
	}

	/**
	 * Background work that failed.
	 *
	 * @return list<CheckFinding>
	 */
	private function work(): array {
		$found   = array();
		$reindex = ReindexState::fromArray( $this->reindex->load() );
		if ( ReindexState::FAILED === $reindex?->phase ) {
			$found[] = new CheckFinding( self::REINDEX_FAILED );
		}
		if ( ImportState::FAILED === ImportState::fromArray( $this->import->load() )?->status ) {
			$found[] = new CheckFinding( self::IMPORT_FAILED );
		}
		// A reindex that finished read every part again, so a job that failed before it has nothing left to say.
		$failed = $this->queue->failed( Health::since( $reindex ) );
		if ( $failed > 0 ) {
			$found[] = new CheckFinding( self::JOBS_FAILED, values: array( 'count' => (string) $failed ) );
		}
		return $found;
	}

	/**
	 * What is wrong in one kit.
	 *
	 * @param int $id  The kit product's ID.
	 * @param Kit $kit The kit.
	 * @return list<CheckFinding>
	 */
	private function kit( int $id, Kit $kit ): array {
		$engine = FitEngine::standard();
		$found  = array();
		$parts  = array();
		$ids    = array();
		foreach ( $kit->slots->all() as $slot ) {
			$candidates        = $this->kits->candidates( $id, $slot->key );
			$ids[ $slot->key ] = array();
			$stocked           = false;
			foreach ( $candidates as $part ) {
				$ids[ $slot->key ][] = $part->id;
				$parts[ $part->id ]  = $part;
				$stocked             = $stocked || $part->inStock;
				// Two rules may need the same spec of a part; it is said once, by the first.
				$lacks = array();
				foreach ( $engine->judge( $kit, $slot->key, $part, Picks::none() ) as $finding ) {
					$named  = $finding->values['spec'] ?? '';
					$spec   = MessageCode::SpecMissing === $finding->code && is_string( $named ) ? $named : ( self::NOTES[ $finding->code->value ] ?? '' );
					$lacks += array( $spec => $finding );
				}
				unset( $lacks[''] );
				foreach ( $lacks as $spec => $finding ) {
					$found[] = MessageCode::SpecMissing === $finding->code
						? new CheckFinding( self::SPEC_MISSING, $part->id, $id, $slot->key, $spec, array( 'rule' => $finding->rule->value ) )
						: new CheckFinding( self::ASSUMED, $part->id, $id, $slot->key, $spec, array( 'note' => $finding->code->value ) );
				}
			}
			if ( array() === $candidates ) {
				$found[] = new CheckFinding( self::SLOT_EMPTY, 0, $id, $slot->key );
			} elseif ( ! $stocked ) {
				$found[] = new CheckFinding( self::SLOT_NO_STOCK, 0, $id, $slot->key );
			}
		}

		$validator = new BuildValidator( $engine );
		foreach ( $kit->presets as $preset ) {
			$picks = Picks::none();
			foreach ( $preset->picks as $slot => $part ) {
				if ( in_array( $part, $ids[ $slot ], true ) ) {
					$picks = $picks->with( $slot, $parts[ $part ] );
					continue;
				}
				$found[] = new CheckFinding(
					self::PRESET,
					$part,
					$id,
					$slot,
					'',
					array(
						'preset'  => $preset->name,
						'problem' => Problem::PartNotInSlot->value,
					)
				);
			}
			foreach ( $validator->validate( $kit, $picks, $ids )->problems as $problem ) {
				if ( null === $problem->part ) {
					// An empty slot: its part is no longer the slot's, which is said above.
					continue;
				}
				$found[] = new CheckFinding(
					self::PRESET,
					$problem->part,
					$id,
					$problem->slot,
					'',
					array(
						'preset'  => $preset->name,
						'problem' => $problem->code->value,
					)
				);
			}
		}
		return $found;
	}

	/**
	 * What is wrong with what the sources say of these parts.
	 *
	 * @param int ...$ids Part IDs.
	 * @return list<CheckFinding>
	 */
	private function parts( int ...$ids ): array {
		$found = array();
		foreach ( $this->indexer->resolve( ...$ids ) as $id => $sourced ) {
			foreach ( $sourced->resolution->disagreements as $disagreement ) {
				foreach ( $disagreement->others as $other ) {
					$found[] = new CheckFinding(
						self::DISAGREE,
						$id,
						0,
						'',
						$disagreement->kept->key->value,
						array(
							'kept'       => $disagreement->kept->text,
							'kept_from'  => $disagreement->kept->origin->value,
							'kept_ref'   => $disagreement->kept->ref,
							'other'      => $other->text,
							'other_from' => $other->origin->value,
							'other_ref'  => $other->ref,
						)
					);
				}
			}
			foreach ( $sourced->unread as $unread ) {
				$found[] = new CheckFinding(
					self::UNREAD,
					$id,
					0,
					'',
					$unread->key,
					array(
						'text'   => $unread->text,
						'from'   => $unread->origin->value,
						'ref'    => $unread->ref,
						'reason' => $unread->reason,
					)
				);
			}
		}
		return $found;
	}
}
