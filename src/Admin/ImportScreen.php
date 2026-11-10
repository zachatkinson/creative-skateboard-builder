<?php
/**
 * Import and export, at WooCommerce > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\UploadRefused;
use CreativeBranding\SkateboardBuilder\Port\Uploads;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\SpecExport;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Service\SpecSheet;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Every part's specs as a CSV file, out and in (spec: Admin, Import and export). A file that is chosen is read and
 * shown: what it would change, and each row it cannot read. Nothing is written until the import is confirmed, and
 * then it runs in the background. Every form is a plain post with a nonce, checked with the capability before
 * anything is read. Nothing here needs a script.
 *
 * @since 0.3.0
 */
final class ImportScreen {

	/**
	 * The forms' nonce action, and the file field's name.
	 */
	public const NONCE = 'cbuild_import';
	public const FILE  = 'cbuild_file';

	/**
	 * The most rows of changes, and of problems, the page lists.
	 */
	public const SHOWN = 200;

	/**
	 * The screen's only style: a wide table scrolls inside its own box, not the page.
	 */
	private const STYLES = '.cbuild-scroll{overflow-x:auto;margin:0 0 16px}';

	/**
	 * Why the file sent with this request was not read.
	 *
	 * @var list<string>
	 */
	private array $refused = array();

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecImport $import  Reads, shows and writes a file.
	 * @param SpecExport $export  Writes the sheet.
	 * @param Uploads    $uploads The file a form sent.
	 */
	public function __construct( private SpecImport $import, private SpecExport $export, private Uploads $uploads ) {}

	/**
	 * The tab's address, with these arguments.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, int|string> $args Query arguments.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg(
			array_merge(
				array(
					'page' => BuilderHooks::PAGE,
					'tab'  => BuilderHooks::TAB_IMPORT,
				),
				$args
			),
			admin_url( 'admin.php' )
		);
	}

	/**
	 * Style the tables.
	 *
	 * @since 0.3.0
	 */
	public function styles(): void {
		wp_add_inline_style( 'common', self::STYLES );
	}

	/**
	 * What the form sent asked for, once who sent it may ask and the nonce is theirs.
	 */
	private static function asked(): string {
		if ( ! isset( $_POST['cbuild_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return '';
		}
		check_admin_referer( self::NONCE );
		return is_string( $_POST['cbuild_action'] ) ? sanitize_key( wp_unslash( $_POST['cbuild_action'] ) ) : '';
	}

	/**
	 * The sheet, when the export's form was sent.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null The file's text, or null when it was not asked for.
	 */
	public function download(): ?string {
		// A byte-order mark first: without it a spreadsheet reads the names' accents as another alphabet.
		return 'export' === self::asked() ? "\xEF\xBB\xBF" . $this->export->text() : null;
	}

	/**
	 * Act on a form that was sent.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null Where to go next, or null to draw the page: nothing was sent, or the file was refused.
	 */
	public function handle(): ?string {
		$action = self::asked();
		if ( 'discard' === $action ) {
			$this->import->discard();
			return self::url( array( 'notice' => 'discarded' ) );
		}
		if ( 'confirm' === $action ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in asked().
			$token = isset( $_POST['cbuild_token'] ) && is_string( $_POST['cbuild_token'] ) ? sanitize_key( wp_unslash( $_POST['cbuild_token'] ) ) : '';
			return self::url( array( 'notice' => $this->import->confirm( $token, get_current_user_id() ) ? 'started' : 'stale' ) );
		}
		if ( 'read' !== $action ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Missing -- Checked in asked().
		$key = isset( $_POST['cbuild_key'] ) && is_string( $_POST['cbuild_key'] ) ? sanitize_key( wp_unslash( $_POST['cbuild_key'] ) ) : '';
		try {
			$this->refused = $this->import->read( $this->uploads->csv( self::FILE, SpecImport::MAX_BYTES ), $key, wc_get_price_decimal_separator(), get_current_user_id() );
		} catch ( UploadRefused $refused ) {
			$this->refused = array( $refused->getMessage() );
		}
		return array() === $this->refused ? self::url() : null;
	}

	/**
	 * Draw the tab: the export, the form that reads a file, and the import in hand.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Chooses what is shown and changes nothing.
		$notice = self::notice( isset( $_GET['notice'] ) && is_string( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '' );
		$failed = false;
		$state  = $this->import->state();
		$status = $state->status ?? '';
		if ( ImportState::FAILED === $status ) {
			$failed = true;
			$notice = __( 'The import stopped: a batch failed four times. The parts before it were written. WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' );
		}
		$problems = array_map( Sentences::fileRefused( ... ), $this->refused );
		if ( array() !== $problems ) {
			$failed = true;
			$notice = __( 'The file was not read. Nothing has changed.', 'creative-skateboard-builder' );
		}

		$counts  = array(
			'rows'    => $state->total ?? 0,
			'done'    => min( $state->cursor ?? 0, $state->total ?? 0 ),
			'changed' => $state->changed ?? 0,
			'same'    => $state->same ?? 0,
			'refused' => 0,
		);
		$wrong   = array();
		$changes = array();
		$rows    = array();
		if ( null !== $state && ImportState::WAITING === $status ) {
			$counts['changed'] = 0;
			foreach ( $this->import->plan( $state, 0, $state->total ) as $row ) {
				$part = '' === $row['name'] ? $row['sku'] : $row['name'];
				foreach ( $row['problems'] as $problem ) {
					$wrong[]             = array( 'row' => $row['row'] ) + $problem;
					$rows[ $row['row'] ] = true;
				}
				if ( array() !== $row['problems'] ) {
					continue;
				}
				if ( array() === $row['changes'] ) {
					++$counts['same'];
					continue;
				}
				++$counts['changed'];
				foreach ( $row['changes'] as $change ) {
					$changes[] = array(
						'row'    => $row['row'],
						'part'   => $part,
						'column' => self::column( $change['key'] ),
						'from'   => self::shown( $change['key'], $change['from'] ),
						'to'     => self::shown( $change['key'], $change['to'] ),
					);
				}
			}
		} else {
			$wrong = $state->refused ?? array();
			foreach ( $wrong as $left ) {
				$rows[ $left['row'] ] = true;
			}
		}
		$counts['refused'] = count( $rows );
		$listed            = array();
		foreach ( array_slice( $wrong, 0, self::SHOWN ) as $entry ) {
			$listed[] = array(
				'row'     => $entry['row'],
				'column'  => self::column( $entry['key'] ),
				'problem' => Sentences::rowProblem( $entry['code'], $entry['text'] ),
			);
		}
		$more    = array(
			'wrong'   => count( $wrong ) - count( $listed ),
			'changes' => max( 0, count( $changes ) - self::SHOWN ),
		);
		$wrong   = $listed;
		$changes = array_slice( $changes, 0, self::SHOWN );
		$token   = $state->token ?? '';
		$mine    = get_current_user_id() === ( $state->by ?? 0 );
		$tabs    = BuilderHooks::tabs( BuilderHooks::TAB_IMPORT );
		$nonce   = self::NONCE;
		$file    = self::FILE;
		$back    = self::url();
		include CBUILD_PATH . 'templates/admin/import.php';
	}

	/**
	 * A column's name as a person reads it.
	 *
	 * @param string $name The column.
	 */
	private static function column( string $name ): string {
		$key = SpecKey::tryFrom( $name );
		if ( null !== $key ) {
			return Labels::spec( $key );
		}
		$columns = array(
			SpecSheet::ID    => __( 'ID', 'creative-skateboard-builder' ),
			SpecSheet::SKU   => __( 'SKU', 'creative-skateboard-builder' ),
			SpecSheet::TABLE => __( 'Maker\'s chart', 'creative-skateboard-builder' ),
			SpecSheet::SIZE  => __( 'Size in the chart', 'creative-skateboard-builder' ),
		);
		return $columns[ $name ] ?? $name;
	}

	/**
	 * A value as a person reads it, and a word for none.
	 *
	 * @param string $name The column.
	 * @param string $text The value.
	 */
	private static function shown( string $name, string $text ): string {
		if ( '' === $text ) {
			return __( 'Nothing', 'creative-skateboard-builder' );
		}
		$key = SpecKey::tryFrom( $name );
		return null === $key ? $text : Labels::value( $key, $text );
	}

	/**
	 * What a notice says.
	 *
	 * @param string $code The notice asked for.
	 */
	private static function notice( string $code ): string {
		$notices = array(
			'started'   => __( 'The import has started. It runs in the background, 100 rows at a time.', 'creative-skateboard-builder' ),
			'discarded' => __( 'The import was put away. Nothing was changed.', 'creative-skateboard-builder' ),
			'stale'     => __( 'That import is no longer waiting. Choose the file again.', 'creative-skateboard-builder' ),
		);
		return $notices[ $code ] ?? '';
	}
}
