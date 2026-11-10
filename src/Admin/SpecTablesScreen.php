<?php
/**
 * The makers' charts, at WooCommerce > Builder.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKind;
use CreativeBranding\SkateboardBuilder\Domain\Spec\TableRow;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Service\SpecTableEditor;
use CreativeBranding\SkateboardBuilder\Service\TableDraft;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * A list of the charts, one chart's rows to edit, and a page that asks before a chart is deleted (spec: Admin,
 * WooCommerce > Builder, Spec tables). Every form is a plain post with a nonce, checked with the capability before
 * anything is read. A chart saves whole or not at all; one that is refused comes back as it was typed, with each
 * problem in the row it is about. Nothing here needs a script.
 *
 * @since 0.3.0
 */
final class SpecTablesScreen {

	/**
	 * The forms' nonce action.
	 */
	public const NONCE = 'cbuild_spec_tables';

	/**
	 * Blank rows after a chart's own, for new sizes.
	 */
	private const BLANK_ROWS = 3;

	/**
	 * The screen's only styles: a row's fields sit side by side where there is room and wrap where there is not.
	 */
	private const STYLES = '.cbuild-field{display:inline-block;margin:0 16px 12px 0;vertical-align:top}.cbuild-chart-row{margin:0 0 16px;padding:0}.cbuild-chart-row legend{font-weight:600;padding:0 0 8px}';

	/**
	 * A save that was refused, kept to draw again as it was typed.
	 *
	 * @var array{name: string, rows: list<array{size: string, source: string, read_on: string, specs: array<string, string>}>, draft: TableDraft}|null
	 */
	private ?array $refused = null;

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param SpecTables      $tables The charts.
	 * @param SpecTableEditor $editor Checks and saves them.
	 */
	public function __construct( private SpecTables $tables, private SpecTableEditor $editor ) {}

	/**
	 * The figures a row shows, in the order the spec names them.
	 *
	 * @return list<SpecKey>
	 */
	private static function columns(): array {
		return array( SpecKey::AxleWidth, SpecKey::DeckMin, SpecKey::DeckMax, SpecKey::TruckHeight, SpecKey::MaxWheel, SpecKey::Baseplate );
	}

	/**
	 * The page's address, with these arguments.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, int|string> $args Query arguments.
	 */
	public static function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => BuilderHooks::PAGE ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Style the rows.
	 *
	 * @since 0.3.0
	 */
	public function styles(): void {
		wp_add_inline_style( 'common', self::STYLES );
	}

	/**
	 * Act on a form that was sent.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null Where to go next, or null to draw the page: nothing was sent, or what was sent was refused.
	 */
	public function handle(): ?string {
		if ( ! isset( $_POST['cbuild_action'] ) || ! current_user_can( 'manage_woocommerce' ) ) {
			return null;
		}
		check_admin_referer( self::NONCE );
		$action = is_string( $_POST['cbuild_action'] ) ? sanitize_key( wp_unslash( $_POST['cbuild_action'] ) ) : '';
		$id     = isset( $_POST['cbuild_table'] ) && is_string( $_POST['cbuild_table'] ) ? absint( wp_unslash( $_POST['cbuild_table'] ) ) : 0;
		$name   = isset( $_POST['cbuild_name'] ) && is_string( $_POST['cbuild_name'] ) ? sanitize_text_field( wp_unslash( $_POST['cbuild_name'] ) ) : '';

		if ( 'delete' === $action ) {
			return self::url( array( 'notice' => $this->editor->delete( $id ) ? 'deleted' : 'missing' ) );
		}
		if ( 'add' === $action ) {
			$draft = $this->editor->add( $name );
			if ( $draft->saved() ) {
				return self::url(
					array(
						'table'  => $draft->id,
						'notice' => 'added',
					)
				);
			}
			$this->refused = array(
				'name'  => $name,
				'rows'  => array(),
				'draft' => $draft,
			);
			return null;
		}
		if ( 'save' !== $action ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Each field is sanitised in row().
		$sent = isset( $_POST['cbuild_rows'] ) && is_array( $_POST['cbuild_rows'] ) ? wp_unslash( $_POST['cbuild_rows'] ) : array();
		$rows = array();
		foreach ( $sent as $row ) {
			$rows[] = self::row( $row );
		}
		$draft = $this->editor->save( $id, $name, $rows, wc_get_price_decimal_separator() );
		if ( $draft->saved() ) {
			return self::url(
				array(
					'table'  => $id,
					'notice' => 'saved',
				)
			);
		}
		$this->refused = compact( 'name', 'rows', 'draft' );
		return null;
	}

	/**
	 * A row as it was sent, each field text.
	 *
	 * @param mixed $row What was sent for the row.
	 * @return array{size: string, source: string, read_on: string, specs: array<string, string>}
	 */
	private static function row( mixed $row ): array {
		$row   = is_array( $row ) ? $row : array();
		$text  = static fn( mixed $value ): string => is_string( $value ) ? sanitize_text_field( $value ) : '';
		$specs = array();
		foreach ( is_array( $row['specs'] ?? null ) ? $row['specs'] : array() as $key => $value ) {
			if ( is_string( $key ) && null !== SpecKey::tryFrom( $key ) ) {
				$specs[ $key ] = $text( $value );
			}
		}
		return array(
			'size'    => $text( $row['size'] ?? null ),
			'source'  => $text( $row['source'] ?? null ),
			'read_on' => $text( $row['read_on'] ?? null ),
			'specs'   => $specs,
		);
	}

	/**
	 * Draw the page: the list, one chart, or the question before a chart is deleted.
	 *
	 * @since 0.3.0
	 */
	public function render(): void {
		if ( ! current_user_can( 'manage_woocommerce' ) ) {
			return;
		}
		$tables = $this->tables->all();
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- These choose what is shown and change nothing.
		$id     = isset( $_GET['table'] ) && is_string( $_GET['table'] ) ? absint( wp_unslash( $_GET['table'] ) ) : 0;
		$asking = isset( $_GET['delete'] );
		$notice = self::notice( isset( $_GET['notice'] ) && is_string( $_GET['notice'] ) ? sanitize_key( wp_unslash( $_GET['notice'] ) ) : '' );
		// phpcs:enable
		$failed = null !== $this->refused;
		if ( $failed ) {
			$notice = __( 'Nothing was saved. Each problem is shown with the field it is about.', 'creative-skateboard-builder' );
		}
		$nonce = self::NONCE;
		$back  = self::url();

		if ( 0 !== $id && ! isset( $tables[ $id ] ) ) {
			$id     = 0;
			$failed = true;
			$notice = self::notice( 'missing' );
		}
		if ( 0 === $id ) {
			$charts = array();
			foreach ( $tables as $table => $chart ) {
				$charts[] = array(
					'name'  => $chart['name'],
					'sizes' => count( $chart['table']->rows ),
					'url'   => self::url( array( 'table' => $table ) ),
				);
			}
			$name    = $this->refused['name'] ?? '';
			$problem = null === $this->refused ? '' : self::problem( $this->refused['draft']->problems[0]['code'] );
			include CBUILD_PATH . 'templates/admin/spec-tables.php';
			return;
		}

		$name = $this->refused['name'] ?? $tables[ $id ]['name'];
		if ( $asking ) {
			include CBUILD_PATH . 'templates/admin/spec-table-delete.php';
			return;
		}
		$action   = self::url( array( 'table' => $id ) );
		$delete   = self::url(
			array(
				'table'  => $id,
				'delete' => 1,
			)
		);
		$rows     = $this->rows( $tables[ $id ]['table']->rows );
		$problems = $this->problems();
		$wrong    = array();
		foreach ( $this->refused['draft']->problems ?? array() as $found ) {
			$wrong[ $found['row'] ][ $found['field'] ] = true;
		}
		$columns = array();
		foreach ( self::columns() as $key ) {
			$choices = array();
			foreach ( $key->choices() as $choice ) {
				$choices[ $choice ] = Labels::choice( $choice );
			}
			$columns[ $key->value ] = array(
				'label'   => Labels::field( $key ),
				'choices' => $choices,
			);
		}
		$limits = array(
			'name'   => SpecTableEditor::MAX_NAME,
			'size'   => TableRow::MAX_SIZE,
			'source' => TableRow::MAX_SOURCE,
		);
		include CBUILD_PATH . 'templates/admin/spec-table.php';
	}

	/**
	 * The rows the form shows: what was typed when a save was refused, or the chart's own with their figures as a
	 * person types them, and blank ones after.
	 *
	 * @param list<TableRow> $stored The chart's rows.
	 * @return list<array{size: string, source: string, read_on: string, specs: array<string, string>}>
	 */
	private function rows( array $stored ): array {
		if ( null !== $this->refused ) {
			return $this->refused['rows'];
		}
		$rows = array();
		foreach ( $stored as $row ) {
			$specs = array();
			foreach ( $row->values as $name => $value ) {
				$key            = SpecKey::tryFrom( $name );
				$specs[ $name ] = null !== $key && SpecKind::Measure === $key->kind() ? Labels::typed( $value ) : $value;
			}
			$rows[] = array(
				'size'    => $row->size,
				'source'  => $row->source,
				'read_on' => $row->readOn,
				'specs'   => $specs,
			);
		}
		for ( $blank = 0; $blank < self::BLANK_ROWS; $blank++ ) {
			$rows[] = array(
				'size'    => '',
				'source'  => '',
				'read_on' => '',
				'specs'   => array(),
			);
		}
		return $rows;
	}

	/**
	 * What is wrong with what was sent, as sentences, by row from 1; 0 is the chart itself.
	 *
	 * @return array<int, list<string>>
	 */
	private function problems(): array {
		$problems = array();
		foreach ( $this->refused['draft']->problems ?? array() as $problem ) {
			$key                           = SpecKey::tryFrom( $problem['field'] );
			$typed                         = $this->refused['rows'][ $problem['row'] - 1 ]['specs'][ $problem['field'] ] ?? '';
			$problems[ $problem['row'] ][] = null === $key
				? self::problem( $problem['code'] )
				: self::figure( Labels::spec( $key ), $typed, $problem['code'] );
		}
		return $problems;
	}

	/**
	 * What a notice says.
	 *
	 * @param string $code The notice asked for.
	 */
	private static function notice( string $code ): string {
		$notices = array(
			'added'   => __( 'The chart was added. Give it a row for each size.', 'creative-skateboard-builder' ),
			'saved'   => __( 'The chart was saved. Every part is being read again, in the background.', 'creative-skateboard-builder' ),
			'deleted' => __( 'The chart was deleted.', 'creative-skateboard-builder' ),
			'missing' => __( 'That chart is no longer there.', 'creative-skateboard-builder' ),
		);
		return $notices[ $code ] ?? '';
	}

	/**
	 * What is wrong with a chart, a size, a source or a day, as a sentence.
	 *
	 * @param string $code The problem's code.
	 */
	private static function problem( string $code ): string {
		$problems = array(
			SpecTableEditor::NAME_MISSING   => __( 'Type the maker\'s name.', 'creative-skateboard-builder' ),
			SpecTableEditor::NAME_LONG      => __( 'The maker\'s name is too long. Use 190 characters or fewer.', 'creative-skateboard-builder' ),
			SpecTableEditor::TABLE_MISSING  => __( 'That chart is no longer there.', 'creative-skateboard-builder' ),
			SpecTableEditor::SIZE_MISSING   => __( 'Type the size this row is for, or clear the row.', 'creative-skateboard-builder' ),
			SpecTableEditor::SIZE_LONG      => __( 'The size is too long. Use 40 characters or fewer.', 'creative-skateboard-builder' ),
			SpecTableEditor::SIZE_DUPLICATE => __( 'This size is on a row above. Clear one of the two.', 'creative-skateboard-builder' ),
			SpecTableEditor::SOURCE_LONG    => __( 'The source is too long. Use 200 characters or fewer.', 'creative-skateboard-builder' ),
			SpecTableEditor::DATE_INVALID   => __( 'The day it was read is not a date. Choose one, or clear it.', 'creative-skateboard-builder' ),
			SpecTableEditor::TOO_MANY       => __( 'A chart holds 100 sizes at most. Clear some rows.', 'creative-skateboard-builder' ),
		);
		return $problems[ $code ] ?? $code;
	}

	/**
	 * What is wrong with a figure, as a sentence.
	 *
	 * @param string $label The figure's name.
	 * @param string $typed What was typed.
	 * @param string $code  The domain's code.
	 */
	private static function figure( string $label, string $typed, string $code ): string {
		return match ( $code ) {
			/* translators: 1: a spec's name. 2: what was typed. */
			MeasureError::Malformed->value => sprintf( __( '%1$s: "%2$s" is not a measurement. Type a number, or a fraction such as 8 1/4.', 'creative-skateboard-builder' ), $label, $typed ),
			/* translators: 1: a spec's name. 2: what was typed. */
			MeasureError::ZeroDenominator->value => sprintf( __( '%1$s: the fraction in "%2$s" divides by zero.', 'creative-skateboard-builder' ), $label, $typed ),
			/* translators: 1: a spec's name. 2: what was typed. */
			MeasureError::Negative->value => sprintf( __( '%1$s: "%2$s" is below zero.', 'creative-skateboard-builder' ), $label, $typed ),
			/* translators: 1: a spec's name. 2: what was typed. */
			default => sprintf( __( '%1$s: "%2$s" is not a value it takes.', 'creative-skateboard-builder' ), $label, $typed ),
		};
	}
}
