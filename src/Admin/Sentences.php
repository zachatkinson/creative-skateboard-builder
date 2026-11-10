<?php
/**
 * Codes as sentences.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Port\UploadRefused;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Service\SpecWriter;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * What the services answer with codes, said in the reader's language. The screens and `wp cbuild` both read from
 * here, so a finding or a refused row is the same sentence wherever it is met.
 *
 * @since 0.3.0
 */
final class Sentences {

	/**
	 * A finding of the Catalogue check as a sentence.
	 *
	 * @since 0.3.0
	 *
	 * @param CheckFinding $finding The finding.
	 */
	public static function finding( CheckFinding $finding ): string {
		$key    = SpecKey::tryFrom( $finding->spec );
		$spec   = null === $key ? $finding->spec : Labels::spec( $key );
		$values = $finding->values;
		$value  = static fn( string $name ): string => null === $key || '' === ( $values[ $name ] ?? '' ) ? ( $values[ $name ] ?? '' ) : Labels::value( $key, $values[ $name ] );
		$source = static fn( string $name ): string => Labels::source( Origin::tryFrom( $values[ $name ] ?? '' ) ?? Origin::Field );
		switch ( $finding->code ) {
			case CatalogueCheck::DISAGREE:
				/* translators: 1: a spec's name. 2: a source, such as "The maker's chart". 3: a value. 4: another source. 5: its value. */
				return sprintf( __( '%1$s: two sources disagree. %2$s says %3$s, which is used. %4$s says %5$s.', 'creative-skateboard-builder' ), $spec, $source( 'kept_from' ), $value( 'kept' ), $source( 'other_from' ), $value( 'other' ) );
			case CatalogueCheck::UNREAD:
				if ( 'table_missing' === ( $values['reason'] ?? '' ) ) {
					return __( 'It names a maker\'s chart that is no longer there. Choose another on its Part specs.', 'creative-skateboard-builder' );
				}
				if ( 'size_missing' === ( $values['reason'] ?? '' ) ) {
					/* translators: %s: a size in a maker's chart. */
					return sprintf( __( 'It names the size "%s", which its maker\'s chart does not have.', 'creative-skateboard-builder' ), $values['text'] ?? '' );
				}
				/* translators: 1: a spec's name. 2: a source. 3: what the source holds. */
				return sprintf( __( '%1$s: %2$s holds "%3$s", which cannot be read as a value.', 'creative-skateboard-builder' ), $spec, $source( 'from' ), $values['text'] ?? '' );
			case CatalogueCheck::REINDEX_FAILED:
				return __( 'Reading every part again stopped after five failures. Save the Builder settings to start it again; WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' );
			case CatalogueCheck::IMPORT_FAILED:
				return __( 'The last specs import stopped before its end. Import the file again; WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' );
			case CatalogueCheck::JOBS_FAILED:
				/* translators: %s: a number of jobs. */
				return sprintf( _n( '%s background job of the builder has failed.', '%s background jobs of the builder have failed.', (int) ( $values['count'] ?? '0' ), 'creative-skateboard-builder' ), number_format_i18n( (int) ( $values['count'] ?? '0' ) ) );
			case CatalogueCheck::SLOT_EMPTY:
				/* translators: %s: a slot's key, such as trucks. */
				return sprintf( __( 'The slot "%s" has no parts.', 'creative-skateboard-builder' ), $finding->slot );
			case CatalogueCheck::SLOT_NO_STOCK:
				/* translators: %s: a slot's key, such as trucks. */
				return sprintf( __( 'The slot "%s" has nothing in stock.', 'creative-skateboard-builder' ), $finding->slot );
			case CatalogueCheck::SPEC_MISSING:
				/* translators: 1: a spec's name. 2: a slot's key. */
				return sprintf( __( '%1$s is missing, so the part is not offered in the slot "%2$s".', 'creative-skateboard-builder' ), $spec, $finding->slot );
			case CatalogueCheck::ASSUMED:
				/* translators: %s: a spec's name. */
				return sprintf( __( '%s is not filled in, so the builder assumes the usual one.', 'creative-skateboard-builder' ), $spec );
			case CatalogueCheck::PRESET:
				$problems = array(
					/* translators: 1: a preset's name. 2: a slot's key. */
					'part_not_in_slot' => __( 'The preset "%1$s" names it for the slot "%2$s", and it is not one of that slot\'s parts now.', 'creative-skateboard-builder' ),
					/* translators: 1: a preset's name. 2: a slot's key. */
					'part_sold_out'    => __( 'The preset "%1$s" names it for the slot "%2$s", and it is sold out.', 'creative-skateboard-builder' ),
				);
				/* translators: 1: a preset's name. 2: a slot's key. */
				return sprintf( $problems[ $values['problem'] ?? '' ] ?? __( 'The preset "%1$s" names it for the slot "%2$s", and it no longer fits the preset\'s other parts.', 'creative-skateboard-builder' ), $values['preset'] ?? '', $finding->slot );
		}
		return $finding->code;
	}

	/**
	 * Why a file was not read, as a sentence.
	 *
	 * @since 0.3.0
	 *
	 * @param string $code The code.
	 */
	public static function fileRefused( string $code ): string {
		$problems = array(
			UploadRefused::MISSING   => __( 'Choose a CSV file first.', 'creative-skateboard-builder' ),
			UploadRefused::FAILED    => __( 'The file did not arrive whole. Choose it again.', 'creative-skateboard-builder' ),
			UploadRefused::NOT_CSV   => __( 'That is not a .csv file. Save the sheet as CSV and choose it again.', 'creative-skateboard-builder' ),
			SpecImport::FILE_BIG     => __( 'The file is over 2 MB. Split it into smaller files.', 'creative-skateboard-builder' ),
			SpecImport::FILE_NOT_UTF => __( 'The file is not saved as UTF-8. Save it as CSV UTF-8 and choose it again.', 'creative-skateboard-builder' ),
			SpecImport::FILE_EMPTY   => __( 'The file has no rows under its headings.', 'creative-skateboard-builder' ),
			SpecImport::ROWS_MANY    => __( 'The file has more than 5,000 rows. Split it into smaller files.', 'creative-skateboard-builder' ),
			SpecImport::KEY_COLUMN   => __( 'The file has no column for what parts are matched by. Add an id or a sku column, or change the choice below.', 'creative-skateboard-builder' ),
			SpecImport::COLUMN_TWICE => __( 'A column is in the file twice. Keep one of each.', 'creative-skateboard-builder' ),
			SpecImport::NO_SPECS     => __( 'The file has no spec columns, so there is nothing to import.', 'creative-skateboard-builder' ),
			SpecImport::BUSY         => __( 'An import is running. Wait for it to finish.', 'creative-skateboard-builder' ),
		);
		return $problems[ $code ] ?? $code;
	}

	/**
	 * What is wrong with a row, as a sentence.
	 *
	 * @since 0.3.0
	 *
	 * @param string $code The code.
	 * @param string $text What the cell held.
	 */
	public static function rowProblem( string $code, string $text ): string {
		return match ( $code ) {
			SpecImport::KEY_MISSING => __( 'The row does not say which part it is for.', 'creative-skateboard-builder' ),
			/* translators: %s: an ID or a SKU. */
			SpecImport::KEY_TWICE => sprintf( __( '"%s" is in the file more than once. Keep one row for it.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: an ID or a SKU. */
			SpecImport::PART_MISSING => sprintf( __( 'No product is "%s".', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: a SKU. */
			SpecImport::SKU_MISMATCH => sprintf( __( 'The product with this ID does not have the SKU "%s". Check the row is for this shop, or match parts by SKU.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: a chart's name. */
			SpecImport::TABLE_UNKNOWN => sprintf( __( 'There is no chart named "%s". Add it under Spec tables first.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: a chart's name. */
			SpecImport::TABLE_TWICE => sprintf( __( 'More than one chart is named "%s". Rename one under Spec tables.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: a size in a chart. */
			SpecWriter::SIZE_MISSING => sprintf( __( 'The chart has no size "%s".', 'creative-skateboard-builder' ), $text ),
			SpecWriter::SIZE_LONG => __( 'The size is too long. Use 40 characters or fewer.', 'creative-skateboard-builder' ),
			SpecWriter::TABLE_MISSING => __( 'That chart is no longer there.', 'creative-skateboard-builder' ),
			/* translators: %s: what a cell held. */
			MeasureError::Malformed->value => sprintf( __( '"%s" is not a measurement. Use a number, or a fraction such as 8 1/4.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: what a cell held. */
			MeasureError::ZeroDenominator->value => sprintf( __( 'The fraction in "%s" divides by zero.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: what a cell held. */
			MeasureError::Negative->value => sprintf( __( '"%s" is below zero.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: what a cell held. */
			default => sprintf( __( '"%s" is not a value this column takes.', 'creative-skateboard-builder' ), $text ),
		};
	}
}
