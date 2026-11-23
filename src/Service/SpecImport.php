<?php
/**
 * Reads the specs sheet, shows what it would change, and writes it once confirmed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

use CreativeBranding\SkateboardBuilder\Port\Clock;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Port\PartLayers;
use CreativeBranding\SkateboardBuilder\Port\PartSpecs;
use CreativeBranding\SkateboardBuilder\Port\Queue;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * An import writes nothing until it is confirmed (spec: Admin, Import and export). The file is read once into rows,
 * which wait; `plan()` says what each row would change now, for the screen and again for each batch that writes, so
 * what is written is what the shop holds when the batch runs, not when the file was read. A cell is checked by
 * `SpecWriter`, as the Part specs panel's fields are: a column the file leaves out is left as it is, a blank cell is
 * removed, and a row with anything wrong in it is not written at all.
 *
 * @since 0.3.0
 */
final class SpecImport {

	/**
	 * The job's action. `Support\Options` lists the same name, and a test holds the two together.
	 */
	public const JOB = 'cbuild_import';

	/**
	 * Rows in one batch, the most a file may hold, its most bytes, and how long one waits to be confirmed.
	 */
	public const BATCH     = 100;
	public const MAX_ROWS  = 5000;
	public const MAX_BYTES = 2097152;
	public const KEEP      = 3600;

	/**
	 * Seconds before a failed batch's first retry; each later one waits twice as long.
	 */
	public const RETRY_AFTER = 60;

	/**
	 * Why a file is not read.
	 */
	public const FILE_BIG     = 'file_too_big';
	public const FILE_NOT_UTF = 'file_not_utf8';
	public const FILE_EMPTY   = 'file_empty';
	public const ROWS_MANY    = 'rows_too_many';
	public const KEY_COLUMN   = 'key_column_missing';
	public const COLUMN_TWICE = 'column_twice';
	public const NO_SPECS     = 'no_spec_columns';
	public const BUSY         = 'import_running';

	/**
	 * Why a row is not written. A cell's problem carries `SpecWriter`'s code or the domain's.
	 */
	public const KEY_MISSING   = 'key_missing';
	public const KEY_TWICE     = 'key_twice';
	public const PART_MISSING  = 'part_missing';
	public const SKU_MISMATCH  = 'sku_mismatch';
	public const TABLE_UNKNOWN = 'table_unknown';
	public const TABLE_TWICE   = 'table_ambiguous';

	/**
	 * A row's `layer` that leaves the part's Layer image as it is.
	 */
	public const KEEP_LAYER = -1;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param PartSpecs   $parts  What each product holds.
	 * @param SpecTables  $tables The charts.
	 * @param SpecWriter  $writer Checks each row.
	 * @param ImportStore $store  Where the import waits.
	 * @param Queue       $queue  The background queue.
	 * @param Clock       $clock  The time.
	 * @param Logger      $logger Where failures go.
	 */
	public function __construct(
		private PartSpecs $parts,
		private SpecTables $tables,
		private SpecWriter $writer,
		private ImportStore $store,
		private Queue $queue,
		private Clock $clock,
		private Logger $logger
	) {}

	/**
	 * The Layer image check and the parts' own layer images, for the `layer_image` column. Without them the column is
	 * passed over, as a 0.9.0 container builds this class (D-208).
	 *
	 * @var LayerCheck|null
	 */
	private ?LayerCheck $check = null;

	/**
	 * The parts' own layer images.
	 *
	 * @var PartLayers|null
	 */
	private ?PartLayers $layers = null;

	/**
	 * A copy with the `layer_image` column read and written (Cowork's 1.0.0 request; D-287).
	 *
	 * @since 1.0.0
	 *
	 * @param LayerCheck $check  Checks an image as the Layer image field does.
	 * @param PartLayers $layers The parts' own layer images, and the Media Library's by name.
	 */
	public function withLayers( LayerCheck $check, PartLayers $layers ): self {
		$copy         = clone $this;
		$copy->check  = $check;
		$copy->layers = $layers;
		return $copy;
	}

	/**
	 * Read a file's text into rows that wait to be confirmed, in place of any that waited before. Nothing is
	 * written to a product.
	 *
	 * @since 0.3.0
	 *
	 * @param string $text      The file's text.
	 * @param string $key       The column rows are matched by: `id` or `sku`.
	 * @param string $separator The shop's decimal separator.
	 * @param int    $by        The user who sent it, who alone may confirm it.
	 * @return list<string> Why the file was not read; none when it now waits.
	 */
	public function read( string $text, string $key = SpecSheet::ID, string $separator = '.', int $by = 0 ): array {
		$this->resume();
		if ( ImportState::RUNNING === $this->state()?->status ) {
			return array( self::BUSY );
		}
		if ( strlen( $text ) > self::MAX_BYTES ) {
			return array( self::FILE_BIG );
		}
		if ( 1 !== preg_match( '//u', $text ) ) {
			return array( self::FILE_NOT_UTF );
		}
		if ( str_starts_with( $text, "\xEF\xBB\xBF" ) ) {
			$text = substr( $text, 3 );
		}
		// The heading, the most rows a file may have, and one more to know it has too many.
		$rows   = Csv::parse( $text, Csv::delimiter( $text ), self::MAX_ROWS + 2 );
		$header = array_shift( $rows );
		if ( null === $header || array() === $rows ) {
			return array( self::FILE_EMPTY );
		}
		// Read to that limit, a file has too many rows when it has the one more.
		if ( self::MAX_ROWS + 1 === count( $rows ) ) {
			return array( self::ROWS_MANY );
		}
		$key      = SpecSheet::SKU === $key ? SpecSheet::SKU : SpecSheet::ID;
		$map      = SpecSheet::map( $header );
		$problems = array();
		if ( ! isset( $map['columns'][ $key ] ) ) {
			$problems[] = self::KEY_COLUMN;
		}
		if ( array() !== $map['twice'] ) {
			$problems[] = self::COLUMN_TWICE;
		}
		if ( array() === array_diff( array_keys( $map['columns'] ), array( SpecSheet::ID, SpecSheet::SKU, SpecSheet::NAME ) ) ) {
			$problems[] = self::NO_SPECS;
		}
		if ( array() !== $problems ) {
			return $problems;
		}
		// Which product each SKU names is asked once, here: a look-up a row on every draw of the page was 5,000
		// queries for the largest file. Whether the product still has that SKU is checked on each use.
		$found = array();
		if ( SpecSheet::SKU === $key ) {
			$skus = array();
			foreach ( $rows as $row ) {
				$skus[] = self::key( $row[ $map['columns'][ $key ] ] ?? '', $key );
			}
			$found = $this->parts->bySku( ...array_unique( array_filter( $skus, static fn( string $sku ): bool => '' !== $sku ) ) );
		}
		$now   = $this->clock->now()->getTimestamp();
		$state = new ImportState( ImportState::WAITING, hash( 'sha256', $now . '|' . $text ), $key, $separator, $now, $map['columns'], $rows, count( $rows ), by: $by, found: $found );
		$this->store->save( $state->toArray() );
		return array();
	}

	/**
	 * The import in hand, or null when there is none. One that waited too long to be confirmed is forgotten.
	 *
	 * @since 0.3.0
	 */
	public function state(): ?ImportState {
		$state = ImportState::fromArray( $this->store->load() );
		if ( null !== $state && ImportState::WAITING === $state->status && $this->clock->now()->getTimestamp() - $state->at > self::KEEP ) {
			$this->store->clear();
			return null;
		}
		return $state;
	}

	/**
	 * Give a running import its job back when it has none. A batch that died with its request, or a deactivation
	 * that cancelled the queue, leaves the import running with nothing to run it; without this it would refuse
	 * every new file for good. The job starts from the saved place, and a batch run twice writes the same values.
	 *
	 * @since 0.3.0
	 *
	 * @return bool Whether it had stopped and was queued again.
	 */
	public function resume(): bool {
		if ( ImportState::RUNNING !== $this->state()?->status || $this->queue->isPending( self::JOB ) ) {
			return false;
		}
		$this->queue->enqueueUnique( self::JOB );
		return true;
	}

	/**
	 * Forget an import that waits, or one that has finished. One that is running is left to finish.
	 *
	 * @since 0.3.0
	 */
	public function discard(): void {
		if ( ImportState::RUNNING !== $this->state()?->status ) {
			$this->store->clear();
		}
	}

	/**
	 * Confirm the import that waits: it runs in the background, a batch at a time.
	 *
	 * @since 0.3.0
	 *
	 * @param string $token The token of the import that was shown.
	 * @param int    $by    The user who confirms it.
	 * @return bool Whether that import was waiting, and was this user's.
	 */
	public function confirm( string $token, int $by = 0 ): bool {
		if ( ! $this->start( $token, $by ) ) {
			return false;
		}
		$this->queue->enqueueUnique( self::JOB );
		return true;
	}

	/**
	 * Confirm the import that waits and write it in this process, a batch after another, for the command line.
	 * Nothing is queued: a queue runner would take a queued batch and run it beside this one, and count its rows
	 * twice. A batch that fails stops the run, and its retry is the queue's.
	 *
	 * @since 0.3.0
	 *
	 * @param string $token The token of the import that was shown.
	 * @param int    $by    The user who confirms it.
	 * @return ImportState|null Where it ended, or null when that import was not waiting or not this user's.
	 */
	public function runNow( string $token, int $by = 0 ): ?ImportState {
		if ( ! $this->start( $token, $by ) ) {
			return null;
		}
		do {
			$this->run( false );
			$state = $this->state();
		} while ( null !== $state && ImportState::RUNNING === $state->status && 0 === $state->failures );
		return $state;
	}

	/**
	 * Mark the waiting import as started, when it is the one shown and this user's.
	 *
	 * @param string $token The token of the import that was shown.
	 * @param int    $by    The user who confirms it.
	 */
	private function start( string $token, int $by ): bool {
		$state = $this->state();
		if ( null === $state || ImportState::WAITING !== $state->status || $token !== $state->token || $by !== $state->by ) {
			return false;
		}
		$this->store->save( $state->started()->toArray() );
		return true;
	}

	/**
	 * What some of an import's rows would do now.
	 *
	 * @since 0.3.0
	 *
	 * @param ImportState $state The import.
	 * @param int         $from  The first row, from 0.
	 * @param int         $count How many rows.
	 * @return list<array{row: int, part: int, name: string, sku: string, fields: array<string, string>, table: int, size: string, layer: int, changes: list<array{key: string, from: string, to: string}>, problems: list<array{key: string, code: string, text: string}>}>
	 *         A row is counted from 2: the heading is row 1.
	 */
	public function plan( ImportState $state, int $from, int $count ): array {
		$cell = static fn( array $row, string $column ): ?string => isset( $state->columns[ $column ] ) ? ( $row[ $state->columns[ $column ] ] ?? '' ) : null;
		$keys = array();
		foreach ( $state->rows as $row ) {
			$keys[] = self::key( (string) $cell( $row, $state->key ), $state->key );
		}
		$often = array_count_values( $keys );
		$rows  = array_slice( $state->rows, $from, $count );
		$asked = array_filter( array_slice( $keys, $from, $count ), static fn( string $key ): bool => '' !== $key );
		$ids   = SpecSheet::SKU === $state->key ? array_intersect_key( $state->found, array_flip( $asked ) ) : array_combine( $asked, array_map( 'intval', $asked ) );
		$held  = $this->parts->read( ...array_values( $ids ) );
		if ( SpecSheet::SKU === $state->key ) {
			// A product that has since lost the SKU is no longer the row's.
			$ids = array_filter( $ids, static fn( int $id, string $sku ): bool => ( $held[ $id ]['sku'] ?? null ) === $sku, ARRAY_FILTER_USE_BOTH );
		}

		$owned  = null !== $this->layers && isset( $state->columns[ SpecSheet::LAYER ] ) ? $this->layers->owned( ...array_values( $ids ) ) : array();
		$charts = $this->tables->all();
		$named  = array();
		foreach ( $charts as $id => $chart ) {
			$named[ $chart['name'] ][] = $id;
		}

		$plan = array();
		foreach ( $rows as $at => $row ) {
			$key      = $keys[ $from + $at ];
			$part     = $ids[ $key ] ?? 0;
			$now      = $held[ $part ] ?? null;
			$problems = array();
			if ( '' === $key ) {
				$problems[] = self::problem( $state->key, self::KEY_MISSING, (string) $cell( $row, $state->key ) );
			} elseif ( $often[ $key ] > 1 ) {
				$problems[] = self::problem( $state->key, self::KEY_TWICE, $key );
			} elseif ( null === $now ) {
				$problems[] = self::problem( $state->key, self::PART_MISSING, $key );
			}
			$sku = $cell( $row, SpecSheet::SKU ) ?? '';
			if ( null !== $now && SpecSheet::ID === $state->key && '' !== $sku && $sku !== $now['sku'] ) {
				$problems[] = self::problem( SpecSheet::SKU, self::SKU_MISMATCH, $sku );
			}
			if ( null === $now || array() !== $problems ) {
				$plan[] = self::outcome( $from + $at, $part, $now, $problems );
				continue;
			}

			$sent = array();
			foreach ( SpecSheet::specs() as $spec ) {
				$typed = $cell( $row, $spec );
				if ( null !== $typed ) {
					$sent[ $spec ] = $typed;
				}
			}
			$chart = $cell( $row, SpecSheet::TABLE );
			$size  = $cell( $row, SpecSheet::SIZE );
			$table = $now['table'];
			if ( null !== $chart && '' !== $chart ) {
				$found = $named[ $chart ] ?? array();
				$table = 1 === count( $found ) ? $found[0] : $now['table'];
				if ( 1 !== count( $found ) ) {
					$problems[] = self::problem( SpecSheet::TABLE, array() === $found ? self::TABLE_UNKNOWN : self::TABLE_TWICE, $chart );
				}
			} elseif ( '' === $chart ) {
				$table = 0;
			}
			$naming = array(
				'table' => $now['table'],
				'size'  => $now['size'],
			);
			$asking = null === $chart && null === $size ? null : array(
				'table' => $table,
				'size'  => $size ?? $now['size'],
			);
			$draft  = $this->writer->draft( $sent, $now['fields'], $state->separator, $asking, $naming );

			$changes = array();
			foreach ( array_unique( array( ...array_keys( $now['fields'] ), ...array_keys( $draft->fields ) ) ) as $spec ) {
				$was = $now['fields'][ $spec ] ?? '';
				$is  = $draft->fields[ $spec ] ?? '';
				if ( $was !== $is ) {
					$changes[] = self::change( $spec, $was, $is );
				}
			}
			if ( $draft->table !== $now['table'] ) {
				$changes[] = self::change( SpecSheet::TABLE, $charts[ $now['table'] ]['name'] ?? '', $charts[ $draft->table ]['name'] ?? '' );
			}
			if ( $draft->size !== $now['size'] ) {
				$changes[] = self::change( SpecSheet::SIZE, $now['size'], $draft->size );
			}
			// Without the layer images, as a 0.9.0 container builds this class, the column is passed over (D-208).
			$own               = $owned[ $part ] ?? null;
			[ $layer, $wrong ] = null === $this->layers ? array( self::KEEP_LAYER, array() ) : $this->layer( (string) $cell( $row, SpecSheet::LAYER ), $part, $own, $this->layers );
			if ( self::KEEP_LAYER !== $layer && null !== $this->layers && null !== $own ) {
				$changes[] = self::change( SpecSheet::LAYER, $this->layers->fileName( $own['own'] ), $this->layers->fileName( $layer ) );
			}
			$outcome            = self::outcome( $from + $at, $part, $now, array( ...$problems, ...$draft->problems, ...$wrong ) );
			$outcome['layer']   = $layer;
			$outcome['fields']  = $draft->fields;
			$outcome['table']   = $draft->table;
			$outcome['size']    = $draft->size;
			$outcome['changes'] = $changes;
			$plan[]             = $outcome;
		}
		return $plan;
	}

	/**
	 * The job: one batch from where the import has got to, its place saved, and the next batch queued. A batch run
	 * twice writes the same values; rows it had already written count the second time as changing nothing.
	 *
	 * @since 0.3.0
	 *
	 * @param bool $queued Whether the next batch is queued. The command line runs its batches itself.
	 */
	public function run( bool $queued = true ): void {
		$state = $this->state();
		if ( null === $state || ImportState::RUNNING !== $state->status ) {
			return;
		}
		$now = $this->clock->now()->getTimestamp();
		try {
			$summary = self::summary( $this->plan( $state, $state->cursor, self::BATCH ) );
			foreach ( $summary['changed'] as $row ) {
				$this->parts->write( $row['part'], $row['fields'], $row['table'], $row['size'] );
				if ( self::KEEP_LAYER !== $row['layer'] && null !== $this->layers ) {
					$this->layers->assign( $row['part'], $row['layer'] );
				}
			}
			$changed = count( $summary['changed'] );
			$same    = $summary['same'];
			$refused = $summary['refused'];
		} catch ( Throwable $failure ) {
			$failed = $state->failed( $now );
			$this->store->save( $failed->toArray() );
			$this->logger->log(
				'error',
				'A batch of the specs import failed.',
				array(
					'row'      => $state->cursor + 2,
					'failures' => $failed->failures,
					'error'    => $failure->getMessage(),
				)
			);
			if ( ImportState::RUNNING === $failed->status ) {
				$this->queue->schedule( $now + self::RETRY_AFTER * 2 ** $state->failures, self::JOB );
			}
			return;
		}
		$next = $state->advanced( self::BATCH, $changed, $same, $refused, $now );
		$this->store->save( $next->toArray() );
		if ( $queued && ImportState::RUNNING === $next->status ) {
			// Plain, not unique: this action is still running, and the unique check counts it.
			$this->queue->enqueue( self::JOB );
		}
	}

	/**
	 * A plan's rows sorted once: the rows that change a part, how many change nothing, and each problem with its row.
	 * A row with a problem is refused whole, whatever else it would change.
	 *
	 * @since 0.4.0
	 *
	 * @param list<array{row: int, part: int, name: string, sku: string, fields: array<string, string>, table: int, size: string, layer: int, changes: list<array{key: string, from: string, to: string}>, problems: list<array{key: string, code: string, text: string}>}> $plan Rows, as `plan()` gives them.
	 * @return array{changed: list<array{row: int, part: int, name: string, sku: string, fields: array<string, string>, table: int, size: string, layer: int, changes: list<array{key: string, from: string, to: string}>, problems: list<array{key: string, code: string, text: string}>}>, same: int, refused: list<array{row: int, key: string, code: string, text: string}>, rows: int}
	 *         `rows` is how many rows the problems are about.
	 */
	public static function summary( array $plan ): array {
		$changed = array();
		$same    = 0;
		$refused = array();
		$rows    = 0;
		foreach ( $plan as $row ) {
			if ( array() !== $row['problems'] ) {
				++$rows;
				foreach ( $row['problems'] as $problem ) {
					$refused[] = array( 'row' => $row['row'] ) + $problem;
				}
			} elseif ( array() === $row['changes'] ) {
				++$same;
			} else {
				$changed[] = $row;
			}
		}
		return array(
			'changed' => $changed,
			'same'    => $same,
			'refused' => $refused,
			'rows'    => $rows,
		);
	}

	/**
	 * What a `layer_image` cell asks of a part's own Layer image, and what is wrong with it. An empty cell leaves a
	 * product's as it is, and takes a variation's own away, so it takes its parent's (D-287).
	 *
	 * @param string                                $cell   The cell.
	 * @param int                                   $part   The part's ID.
	 * @param array{own: int, variation: bool}|null $owned  Its own layer image, and whether it is a variation; null
	 *                                                      where the file has no such column.
	 * @param PartLayers                            $layers The parts' own layer images, which `withLayers()` gives
	 *                                                      with the check.
	 * @return array{0: int, 1: list<array{key: string, code: string, text: string}>} The attachment's ID to set, 0 to
	 *         take the part's own away, or KEEP_LAYER; and the problems.
	 */
	private function layer( string $cell, int $part, ?array $owned, PartLayers $layers ): array {
		if ( null === $owned || null === $this->check ) {
			return array( self::KEEP_LAYER, array() );
		}
		$cell = trim( $cell );
		if ( '' === $cell ) {
			return array( $owned['variation'] && $owned['own'] > 0 ? 0 : self::KEEP_LAYER, array() );
		}
		// The part's own image, by its ID or the name an export writes, is no change: it is not looked up, so another
		// image with that name doesn't refuse the row, nor checked again (the auditor's 1.0.0 should-fix P1).
		if ( $owned['own'] > 0 && ( (string) $owned['own'] === $cell || $layers->fileName( $owned['own'] ) === $cell ) ) {
			return array( self::KEEP_LAYER, array() );
		}
		[ $id, $wrong ] = LayerCheck::named( $cell, $layers );
		if ( array() === $wrong ) {
			$wrong = $this->check->problems( $id, $this->check->size( $part ), $this->check->cutOut( $part ) );
		}
		if ( array() !== $wrong ) {
			return array( self::KEEP_LAYER, array_map( static fn( array $problem ): array => self::problem( SpecSheet::LAYER, $problem['code'], $problem['text'] ), $wrong ) );
		}
		return array( $id === $owned['own'] ? self::KEEP_LAYER : $id, array() );
	}

	/**
	 * A key cell as rows are matched by it: an ID as its number, a SKU as it is, and '' for a cell that is neither.
	 *
	 * @param string $text   The cell.
	 * @param string $column The key column.
	 */
	private static function key( string $text, string $column ): string {
		if ( SpecSheet::SKU === $column ) {
			return $text;
		}
		return ctype_digit( $text ) ? ltrim( $text, '0' ) : '';
	}

	/**
	 * A row's outcome before its cells are read.
	 *
	 * @param int                                                                                            $at       The row, from 0.
	 * @param int                                                                                            $part     The part's ID, 0 for none.
	 * @param array{sku: string, name: string, fields: array<string, string>, table: int, size: string}|null $now      What the part holds.
	 * @param list<array{key: string, code: string, text: string}>                                           $problems What is wrong.
	 * @return array{row: int, part: int, name: string, sku: string, fields: array<string, string>, table: int, size: string, layer: int, changes: list<array{key: string, from: string, to: string}>, problems: list<array{key: string, code: string, text: string}>}
	 */
	private static function outcome( int $at, int $part, ?array $now, array $problems ): array {
		return array(
			'row'      => $at + 2,
			'part'     => $part,
			'name'     => $now['name'] ?? '',
			'sku'      => $now['sku'] ?? '',
			'fields'   => $now['fields'] ?? array(),
			'table'    => $now['table'] ?? 0,
			'size'     => $now['size'] ?? '',
			'layer'    => self::KEEP_LAYER,
			'changes'  => array(),
			'problems' => $problems,
		);
	}

	/**
	 * A problem.
	 *
	 * @param string $key  The column.
	 * @param string $code The code.
	 * @param string $text What the cell held.
	 * @return array{key: string, code: string, text: string}
	 */
	private static function problem( string $key, string $code, string $text ): array {
		return array(
			'key'  => $key,
			'code' => $code,
			'text' => $text,
		);
	}

	/**
	 * A change.
	 *
	 * @param string $key  The column.
	 * @param string $from What the part holds.
	 * @param string $to   What it would hold.
	 * @return array{key: string, from: string, to: string}
	 */
	private static function change( string $key, string $from, string $to ): array {
		return array(
			'key'  => $key,
			'from' => $from,
			'to'   => $to,
		);
	}
}
