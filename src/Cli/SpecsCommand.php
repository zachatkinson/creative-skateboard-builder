<?php
/**
 * `wp cbuild specs`.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cli;

use CreativeBranding\SkateboardBuilder\Admin\Sentences;
use CreativeBranding\SkateboardBuilder\Port\Files;
use CreativeBranding\SkateboardBuilder\Port\UploadRefused;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\SpecExport;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Service\SpecSheet;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Export and import part specs as CSV: the sheet of WooCommerce > Builder > Import and export, through the same
 * services, so a file reads the same from either.
 *
 * @since 0.3.0
 */
final class SpecsCommand {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecImport $import The import.
	 * @param SpecExport $export The export.
	 * @param Files      $files  The disk.
	 */
	public function __construct( private SpecImport $import, private SpecExport $export, private Files $files ) {}

	/**
	 * Export every part's own specs as CSV.
	 *
	 * ## OPTIONS
	 *
	 * [<file>]
	 * : Where to write the sheet, a path ending in .csv. A file already there is replaced. Without it the sheet is
	 * printed.
	 *
	 * ## EXAMPLES
	 *
	 *     wp cbuild specs export specs.csv
	 *     wp cbuild specs export > specs.csv
	 *
	 * ## EXIT STATUS
	 *
	 * 0 when the sheet was written or printed, 1 when the file could not be written.
	 *
	 * @since 0.3.0
	 *
	 * @param array<int, string>   $args  Positional arguments.
	 * @param array<string, mixed> $assoc Flags.
	 */
	public function export( array $args, array $assoc = array() ): void {
		unset( $assoc );
		$text = $this->export->text();
		$path = $args[0] ?? '';
		if ( '' === $path ) {
			WP_CLI::line( rtrim( $text, "\r\n" ) );
			return;
		}
		try {
			// A byte-order mark first, as the screen's download has: without it a spreadsheet misreads accents.
			$this->files->write( $path, "\xEF\xBB\xBF" . $text );
		} catch ( UploadRefused $refused ) {
			WP_CLI::error( self::whyNot( $refused->getMessage(), $path ) );
		}
		$parts = substr_count( $text, "\n" ) - 1;
		/* translators: 1: a number of rows. 2: a file's path. */
		WP_CLI::success( sprintf( _n( '%1$s row written to %2$s.', '%1$s rows written to %2$s.', $parts, 'creative-skateboard-builder' ), number_format_i18n( $parts ), $path ) );
	}

	/**
	 * Import part specs from a CSV file.
	 *
	 * The file is checked whole first. A row with anything wrong in it is not written, and is listed; the rest are
	 * written. With --dry-run nothing is written.
	 *
	 * ## OPTIONS
	 *
	 * <file>
	 * : The sheet, a path ending in .csv, 2 MB and 5,000 rows at most.
	 *
	 * [--dry-run]
	 * : Say what the file would change and which rows cannot be read, and write nothing.
	 *
	 * [--key=<key>]
	 * : The column parts are matched by.
	 * ---
	 * default: id
	 * options:
	 *   - id
	 *   - sku
	 * ---
	 *
	 * [--format=<format>]
	 * : How the rows that cannot be read are listed.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cbuild specs import specs.csv --dry-run
	 *     wp cbuild specs import specs.csv --key=sku
	 *
	 * ## EXIT STATUS
	 *
	 * 0 when every row was read (and, without --dry-run, written). 1 when the file was not read, a row could not be
	 * read, or a batch failed.
	 *
	 * @since 0.3.0
	 *
	 * @param array<int, string>   $args  Positional arguments.
	 * @param array<string, mixed> $assoc Flags.
	 */
	public function import( array $args, array $assoc = array() ): void {
		$path   = $args[0] ?? '';
		$key    = SpecSheet::SKU === ( $assoc['key'] ?? '' ) ? SpecSheet::SKU : SpecSheet::ID;
		$format = is_string( $assoc['format'] ?? null ) ? $assoc['format'] : 'table';
		$dry    = isset( $assoc['dry-run'] ) && false !== $assoc['dry-run'];
		$by     = get_current_user_id();
		try {
			$why = $this->import->read( $this->files->csv( $path, SpecImport::MAX_BYTES ), $key, wc_get_price_decimal_separator(), $by );
		} catch ( UploadRefused $refused ) {
			$why = array( $refused->getMessage() );
		}
		$state = $this->import->state();
		if ( array() !== $why || null === $state ) {
			WP_CLI::error( implode( ' ', array_map( static fn( string $code ): string => self::whyNot( $code, $path, $key ), $why ) ) );
		}

		if ( $dry ) {
			$changed = 0;
			$same    = 0;
			$refused = array();
			foreach ( $this->import->plan( $state, 0, $state->total ) as $row ) {
				if ( array() !== $row['problems'] ) {
					foreach ( $row['problems'] as $problem ) {
						$refused[] = array( 'row' => $row['row'] ) + $problem;
					}
				} elseif ( array() === $row['changes'] ) {
					++$same;
				} else {
					++$changed;
				}
			}
			$this->import->discard();
			self::listRefused( $refused, $format );
			/* translators: 1: parts the file would change. 2: parts it would leave as they are. 3: rows that cannot be read. */
			$said = sprintf( __( 'Dry run: %1$s would change, %2$s would stay as they are, %3$s cannot be read. Nothing was written.', 'creative-skateboard-builder' ), number_format_i18n( $changed ), number_format_i18n( $same ), number_format_i18n( self::rows( $refused ) ) );
			self::finish( $said, array() === $refused );
			return;
		}

		$this->import->confirm( $state->token, $by );
		do {
			$this->import->run();
			$state = $this->import->state();
		} while ( null !== $state && ImportState::RUNNING === $state->status && 0 === $state->failures );
		if ( null === $state || ImportState::DONE !== $state->status ) {
			WP_CLI::error( __( 'A batch of the import failed. The rows before it are written, and the rest are tried again in the background. WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' ) );
		}
		self::listRefused( $state->refused, $format );
		/* translators: 1: parts changed. 2: parts left as they were. 3: rows not written. */
		$said = sprintf( __( 'Imported: %1$s changed, %2$s already as the file has them, %3$s not written.', 'creative-skateboard-builder' ), number_format_i18n( $state->changed ), number_format_i18n( $state->same ), number_format_i18n( self::rows( $state->refused ) ) );
		self::finish( $said, array() === $state->refused );
	}

	/**
	 * End with the summary: success, or an error, which exits 1.
	 *
	 * @param string $said The summary.
	 * @param bool   $well Whether every row was read.
	 */
	private static function finish( string $said, bool $well ): void {
		if ( $well ) {
			WP_CLI::success( $said );
			return;
		}
		WP_CLI::error( $said );
	}

	/**
	 * How many rows a list of problems is about.
	 *
	 * @param list<array{row: int, key: string, code: string, text: string}> $refused The problems.
	 */
	private static function rows( array $refused ): int {
		return count( array_unique( array_column( $refused, 'row' ) ) );
	}

	/**
	 * List the rows that cannot be read, each problem with its sentence.
	 *
	 * @param list<array{row: int, key: string, code: string, text: string}> $refused The problems.
	 * @param string                                                         $format  The format asked for.
	 */
	private static function listRefused( array $refused, string $format ): void {
		if ( array() === $refused ) {
			return;
		}
		$items = array();
		foreach ( $refused as $problem ) {
			$items[] = array(
				'row'     => $problem['row'],
				'column'  => $problem['key'],
				'code'    => $problem['code'],
				'message' => Sentences::rowProblem( $problem['code'], $problem['text'] ),
			);
		}
		WP_CLI\Utils\format_items( $format, $items, array( 'row', 'column', 'code', 'message' ) );
	}

	/**
	 * Why a file was not read or written, as the command line says it.
	 *
	 * @param string $code The code.
	 * @param string $path The path given.
	 * @param string $key  The column parts are matched by.
	 */
	private static function whyNot( string $code, string $path, string $key = SpecSheet::ID ): string {
		switch ( $code ) {
			case UploadRefused::MISSING:
				/* translators: %s: a file's path. */
				return sprintf( __( 'There is no file at %s.', 'creative-skateboard-builder' ), $path );
			case UploadRefused::FAILED:
				/* translators: %s: a file's path. */
				return sprintf( __( '%s cannot be read or written. Check the folder is there and its permissions.', 'creative-skateboard-builder' ), $path );
			case UploadRefused::NOT_CSV:
				/* translators: %s: a file's path. */
				return sprintf( __( '%s is not a .csv file.', 'creative-skateboard-builder' ), $path );
			case SpecImport::KEY_COLUMN:
				/* translators: %s: a column's name, id or sku. */
				return sprintf( __( 'The file has no %s column. Add one, or match parts by the other with --key.', 'creative-skateboard-builder' ), $key );
		}
		return Sentences::fileRefused( $code );
	}
}
