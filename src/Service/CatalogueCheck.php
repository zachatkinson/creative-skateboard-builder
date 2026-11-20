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
use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\LayerImages;
use CreativeBranding\SkateboardBuilder\Port\PartCatalogue;
use CreativeBranding\SkateboardBuilder\Port\PartLooks;
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
	 * What it finds in a kit: settings that do not read (since 0.4.0), a slot with no parts or none in stock, a part that lacks a spec a rule needs, a part
	 * a rule had to assume something of, and a preset that can no longer be bought as it is.
	 */
	public const KIT_UNREAD    = 'kit_unread';
	public const SLOT_EMPTY    = 'slot_empty';
	public const SLOT_NO_STOCK = 'slot_out_of_stock';
	public const SPEC_MISSING  = 'spec_missing';
	public const ASSUMED       = 'spec_assumed';
	public const PRESET        = 'preset_broken';

	/**
	 * A part of a slot the picture draws has no layer image, so the picture shows its labelled photo (since 0.7.0).
	 */
	public const LAYER_MISSING = 'layer_missing';

	/**
	 * A product's variations share a photo and a layer image, so the builder shows them as one card and asks what
	 * tells them apart after the pick (since 1.0.0, D-281). Said, not a fix.
	 */
	public const LOOK_SHARED = 'look_shared';


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
	 * The parts' layer images, since 0.7.0, given by the container through `withLayers()`. A check that an earlier
	 * release's container builds, in the request that replaces that release's files with these, has none, and finds no
	 * missing layer image (D-208).
	 *
	 * @var LayerImages|null
	 */
	private ?LayerImages $layers = null;

	/**
	 * The same check, finding the drawn parts that have no layer image.
	 *
	 * @since 0.7.0
	 *
	 * @param LayerImages $layers The parts' layer images.
	 */
	public function withLayers( LayerImages $layers ): self {
		$check         = clone $this;
		$check->layers = $layers;
		return $check;
	}

	/**
	 * The parts' looks, since 1.0.0, given by the container through `withLooks()`. A check an earlier release's
	 * container builds has none, and says nothing of shared cards (D-208).
	 *
	 * @var PartLooks|null
	 */
	private ?PartLooks $looks = null;

	/**
	 * The same check, saying which variations share a card.
	 *
	 * @since 1.0.0
	 *
	 * @param PartLooks $looks The parts' looks.
	 */
	public function withLooks( PartLooks $looks ): self {
		$check        = clone $this;
		$check->looks = $looks;
		return $check;
	}

	/**
	 * The same check, saying nothing of shared cards: for Site Health, which counts none (D-281).
	 *
	 * @since 1.0.0
	 */
	public function withoutLooks(): self {
		$check        = clone $this;
		$check->looks = null;
		return $check;
	}

	/**
	 * Run the check.
	 *
	 * @since 0.3.0
	 *
	 * @return list<CheckFinding> Background work first, then each kit, then each part by ID, then the parts the
	 *                            picture lacks a layer image of, which a new shop has many of and which stop no sale
	 *                            (D-193). None when all is well.
	 */
	public function run(): array {
		$found  = $this->work();
		$layers = array();
		$shared = array();
		$kits   = $this->kits->all();
		// A kit product that is not among those that read: no shopper can open it, and it has no slot to check.
		foreach ( $this->kits->ids() as $id ) {
			if ( ! isset( $kits[ $id ] ) ) {
				$found[] = new CheckFinding( self::KIT_UNREAD, kit: $id );
			}
		}
		foreach ( $kits as $id => $kit ) {
			$found  = array( ...$found, ...$this->kit( $id, $kit['kit'] ) );
			$layers = array( ...$layers, ...$this->layers( $id, $kit['kit'] ) );
			$shared = array( ...$shared, ...$this->shared( $id, $kit['kit'] ) );
		}
		$page = 0;
		do {
			$ids = $this->catalogue->page( ++$page, self::BATCH );
			if ( array() !== $ids ) {
				$found = array( ...$found, ...$this->parts( ...$ids ) );
			}
			$full = count( $ids ) >= self::BATCH;
		} while ( $full );
		return array( ...$found, ...$layers, ...$shared );
	}

	/**
	 * Each product of a kit's slot whose parts share a photo and a layer image, and so a card, with those parts: the
	 * looks the step groups them into (D-240), in the slots' order.
	 *
	 * @param int $id  The kit product's ID.
	 * @param Kit $kit The kit.
	 * @return list<CheckFinding>
	 */
	private function shared( int $id, Kit $kit ): array {
		if ( null === $this->looks ) {
			return array();
		}
		$found = array();
		foreach ( $kit->slots->all() as $slot ) {
			$ids    = array_map( static fn( Part $part ): int => $part->id, $this->kits->candidates( $id, $slot->key ) );
			$groups = array();
			$owners = array();
			foreach ( $this->looks->looks( $ids ) as $part => $fact ) {
				$key              = $fact->product . ':' . $fact->photo . ':' . $fact->layer;
				$groups[ $key ][] = $part;
				$owners[ $key ]   = $fact->product;
			}
			foreach ( $groups as $key => $parts ) {
				if ( count( $parts ) > 1 ) {
					$found[] = new CheckFinding( self::LOOK_SHARED, $owners[ $key ], $id, $slot->key, values: array( 'parts' => implode( ', ', $parts ) ) );
				}
			}
		}
		return $found;
	}

	/**
	 * The parts of a kit's drawn slots that have no layer image, in the slots' order.
	 *
	 * @param int $id  The kit product's ID.
	 * @param Kit $kit The kit.
	 * @return list<CheckFinding>
	 */
	private function layers( int $id, Kit $kit ): array {
		if ( null === $this->layers ) {
			return array();
		}
		$found = array();
		foreach ( $kit->slots->all() as $slot ) {
			if ( ! in_array( $slot->key, Layout::DRAWN, true ) ) {
				continue;
			}
			$parts   = array_map( static fn( Part $part ): int => $part->id, $this->kits->candidates( $id, $slot->key ) );
			$layered = $this->layers->layered( ...$parts );
			foreach ( $parts as $part ) {
				if ( ! in_array( $part, $layered, true ) ) {
					$found[] = new CheckFinding( self::LAYER_MISSING, $part, $id, $slot->key );
				}
			}
		}
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
