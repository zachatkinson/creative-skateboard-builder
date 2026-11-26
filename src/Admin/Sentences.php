<?php
/**
 * Codes as sentences.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Admin;

use CreativeBranding\SkateboardBuilder\Domain\Picture\LayerError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\LeadDays;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Domain\Spec\StoredSpecs;
use CreativeBranding\SkateboardBuilder\Domain\Measure\MeasureError;
use CreativeBranding\SkateboardBuilder\Domain\Rule\MessageCode;
use CreativeBranding\SkateboardBuilder\Domain\Spec\Origin;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Front\Words;
use CreativeBranding\SkateboardBuilder\Port\UploadRefused;
use CreativeBranding\SkateboardBuilder\Port\PartsKeptOut;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use CreativeBranding\SkateboardBuilder\Service\KitEditor;
use CreativeBranding\SkateboardBuilder\Service\LayerCheck;
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
				if ( StoredSpecs::TABLE_MISSING === ( $values['reason'] ?? '' ) ) {
					return __( 'It names a maker\'s chart that is no longer there. Choose another on its Part specs.', 'creative-skateboard-builder' );
				}
				if ( StoredSpecs::SIZE_MISSING === ( $values['reason'] ?? '' ) ) {
					/* translators: %s: a size in a maker's chart. */
					return sprintf( __( 'It names the size "%s", which its maker\'s chart does not have.', 'creative-skateboard-builder' ), $values['text'] ?? '' );
				}
				/* translators: 1: a spec's name. 2: a source. 3: what the source holds. */
				return sprintf( __( '%1$s: %2$s holds "%3$s", which cannot be read as a value.', 'creative-skateboard-builder' ), $spec, $source( 'from' ), $values['text'] ?? '' );
			case CatalogueCheck::REINDEX_FAILED:
				return __( 'Reading every part again stopped after five failures. Start it again with "Read every part again" on this page; WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' );
			case CatalogueCheck::IMPORT_FAILED:
				return __( 'The last specs import stopped before its end. Import the file again; WooCommerce > Status > Logs has the reason.', 'creative-skateboard-builder' );
			case CatalogueCheck::JOBS_FAILED:
				/* translators: %s: a number of jobs. */
				return sprintf( _n( '%s background job of the builder has failed.', '%s background jobs of the builder have failed.', (int) ( $values['count'] ?? '0' ), 'creative-skateboard-builder' ), number_format_i18n( (int) ( $values['count'] ?? '0' ) ) );
			case CatalogueCheck::KIT_UNREAD:
				return __( 'The builder cannot read this kit\'s settings, so no shopper can open it. Open its Build tab, put right what it names, and save.', 'creative-skateboard-builder' );
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
			case CatalogueCheck::LAYER_MISSING:
				/* translators: %s: a slot's key, such as trucks. */
				return sprintf( __( 'It has no layer image, so the picture shows its labelled photo in the slot "%s". Add one on its Part specs, under Picture.', 'creative-skateboard-builder' ), $finding->slot );
			case CatalogueCheck::LOOK_SHARED:
				/* translators: 1: a list of variations' IDs, such as 11, 12. 2: a slot's key, such as deck. */
				return sprintf( __( 'Its variations %1$s share a photo, so the builder shows them as one card in the slot "%2$s" and asks for the rest after the pick.', 'creative-skateboard-builder' ), self::ids( $finding->values['parts'] ?? '' ), $finding->slot );
			case CatalogueCheck::NOT_OFFERED:
				return PartsKeptOut::PRODUCT === ( $values['from'] ?? '' )
					? __( 'Not offered in the builder is ticked on its product\'s Part specs, so no kit offers it or the product\'s other variations.', 'creative-skateboard-builder' )
					: __( 'Not offered in the builder is ticked on its Part specs, so no kit offers it.', 'creative-skateboard-builder' );
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
	 * The two ends of a deck range a writer refused, each with its unit, as the shop's locale writes numbers.
	 *
	 * @since 0.3.0
	 *
	 * @param string $text The narrowest and the widest as the index writes them, with a tab between.
	 * @return array{0: string, 1: string}
	 */
	public static function ends( string $text ): array {
		$ends = explode( "\t", $text );
		return array( Labels::value( SpecKey::DeckMin, $ends[0] ), Labels::value( SpecKey::DeckMax, $ends[1] ?? '' ) );
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
			/* translators: 1: a deck's width with its unit, such as 8.6 in. 2: another, such as 8.375 in. */
			SpecWriter::RANGE => sprintf( __( 'The narrowest deck, %1$s, is wider than the widest deck, %2$s.', 'creative-skateboard-builder' ), ...self::ends( $text ) ),
			SpecWriter::TABLE_MISSING => __( 'That chart is no longer there.', 'creative-skateboard-builder' ),
			/* translators: %s: what a cell held. */
			MeasureError::Malformed->value => sprintf( __( '"%s" is not a measurement. Use a number, or a fraction such as 8 1/4.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: what a cell held. */
			MeasureError::ZeroDenominator->value => sprintf( __( 'The fraction in "%s" divides by zero.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: what a cell held. */
			MeasureError::Negative->value => sprintf( __( '"%s" is below zero.', 'creative-skateboard-builder' ), $text ),
			/* translators: %s: what a cell held. */
			default => self::layer( $code, $text ) ?? sprintf( __( '"%s" is not a value this column takes.', 'creative-skateboard-builder' ), $text ),
		};
	}

	/**
	 * Why a layer image was not saved, as the Layer image field and an import's results both say it, or null for a
	 * code that is not a layer image's (D-287).
	 *
	 * @since 1.0.0
	 *
	 * @param string $code A `LayerError`'s value, or one of `LayerCheck`'s.
	 * @param string $text What its sentence needs, as `LayerCheck` gives it: the image's type and the pixels it needs,
	 *                     each after a tab, or what the cell named.
	 */
	public static function layer( string $code, string $text ): ?string {
		$parts = explode( "\t", $text );
		return match ( $code ) {
			/* translators: %s: what was typed in the Layer image field. */
			LayerCheck::NOT_IMAGE => sprintf( __( 'Layer image was not saved: "%s" is not the ID of an image in the Media Library.', 'creative-skateboard-builder' ), $parts[0] ),
			/* translators: %s: a file's name. */
			LayerCheck::NOT_FOUND => sprintf( __( 'Layer image was not saved: no image in the Media Library has the file name "%s".', 'creative-skateboard-builder' ), $parts[0] ),
			/* translators: 1: a file's name. 2: the attachment IDs of the images with that name, such as 12, 40. */
			LayerCheck::AMBIGUOUS => sprintf( __( 'Layer image was not saved: more than one image in the Media Library has the file name "%1$s" (IDs %2$s). Use the ID of the one you mean.', 'creative-skateboard-builder' ), $parts[0], self::ids( $parts[1] ?? '' ) ),
			/* translators: %s: a file's media type, such as image/jpeg. */
			LayerError::WrongType->value => sprintf( __( 'Layer image was not saved: it is %s, and a layer must be a PNG, WebP or AVIF with a transparent background.', 'creative-skateboard-builder' ), $parts[0] ),
			/* translators: %s: a file's media type, such as image/avif. */
			LayerError::Unreadable->value => sprintf( __( 'Layer image was not saved: it is %s, and this server\'s image library cannot read that type. Upload the cut-out as a PNG or WebP, or ask your host to turn on AVIF in GD or Imagick.', 'creative-skateboard-builder' ), $parts[0] ),
			LayerError::Opaque->value => __( 'Layer image was not saved: its corners are not transparent. Export the cut-out with a transparent background.', 'creative-skateboard-builder' ),
			/* translators: 1: a number of pixels. 2: another. */
			LayerError::TooSmall->value => sprintf( __( 'Layer image was not saved: it is too small for the size the picture draws this part. It needs at least %1$s pixels across or %2$s along.', 'creative-skateboard-builder' ), number_format_i18n( (int) ( $parts[1] ?? 0 ) ), number_format_i18n( (int) ( $parts[2] ?? 0 ) ) ),
			default => null,
		};
	}

	/**
	 * What was wrong with a kit's setting, as a sentence that starts with the field's name.
	 *
	 * @since 0.4.0
	 *
	 * @param string                $code   The problem's code: a `DocumentError`, or one of `KitEditor`'s.
	 * @param string                $field  The field's name, such as "The Trucks slot, Units per build".
	 * @param array<string, string> $values What the words need: `preset`, `slot`, `part`, `rule` and `message`, where the code has them.
	 */
	public static function kit( string $code, string $field, array $values = array() ): string {
		$preset = $values['preset'] ?? '';
		$slot   = $values['slot'] ?? '';
		$part   = $values['part'] ?? '';
		return match ( $code ) {
			/* translators: %s: a field's name. */
			DocumentError::Missing->value => sprintf( __( '%s: fill this in.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name. */
			DocumentError::BadMeasure->value => sprintf( __( '%s: type a number, such as 0.25.', 'creative-skateboard-builder' ), $field ),
			/* translators: 1: a field's name. 2: the most units a slot takes. 3: the most slots a kit takes. 4: the most presets a kit takes. */
			DocumentError::OutOfRange->value => sprintf( __( '%1$s: this is more than the kit takes, or less. A slot takes 1 to %2$d units, a kit %3$d slots and %4$d presets, and text no more than its field allows.', 'creative-skateboard-builder' ), $field, Slot::MAX_UNITS, Slots::MAX, Kit::MAX_PRESETS ),
			/* translators: %s: a field's name. */
			DocumentError::Duplicate->value => sprintf( __( '%s: the kit already has a slot of this kind. A kit takes one of each.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name. */
			DocumentError::DeckNotFirst->value => sprintf( __( '%s: the deck\'s slot must come first, since every other part is checked against the deck. Move it up.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name. */
			DocumentError::RisersAfterHardware->value => sprintf( __( '%s: the risers\' slot must come before the hardware\'s, since the bolts\' length depends on the riser. Move it up.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name. */
			DocumentError::OutOfOrder->value => sprintf( __( '%s: this must not be less than the figure before it.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name. */
			DocumentError::UnknownSlot->value => sprintf( __( '%s: the kit has no such slot now. Clear the part, or keep the slot.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name. */
			DocumentError::SlotNotFilled->value => sprintf( __( '%s: choose a part. A build must have one.', 'creative-skateboard-builder' ), $field ),
			KitEditor::LEAVE_PRESETS => __( 'The slots were not saved. A preset the kit already has names a slot these leave out, or leaves empty one a build must now have. Put that preset right, or remove it, in the same save.', 'creative-skateboard-builder' ),
			/* translators: %s: a field's name. */
			KitEditor::WHEELBASE_RANGE => sprintf( __( '%s: type a wheelbase from 10 to 20 in, or leave it empty.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name, Builder page. */
			KitEditor::BUILDER_PAGE => sprintf( __( '%s: choose one of the pages listed, or the kit\'s own page. A page is listed when it is published and holds this kit\'s Builder block.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name. */
			KitEditor::GUIDE_WIDTH => sprintf( __( '%s: type a width from 5 to 12 in.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: the field, such as "Wheel guide, Street, Smallest wheel". */
			KitEditor::WHEEL_SIZE => sprintf( __( '%s: type a size from 40 to 90 mm.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a field's name, Assembly time in business days. */
			KitEditor::LEAD_DAYS_FORM => sprintf( __( '%s: type a number of business days, such as 2, or a range with the fewer first, such as 2-4.', 'creative-skateboard-builder' ), $field ),
			/* translators: 1: a field's name, Assembly time in business days. 2: the most business days it may be, 60. */
			KitEditor::LEAD_DAYS_RANGE => sprintf( __( '%1$s: type business days from 0 to %2$d, such as 2 or 2-4.', 'creative-skateboard-builder' ), $field, LeadDays::MAX ),
			/* translators: %s: a field's name. */
			KitEditor::GUIDE_SHOE_TWICE => sprintf( __( '%s: the guide already has a row for this shoe size. Give each row its own.', 'creative-skateboard-builder' ), $field ),
			KitEditor::NO_SLOTS => __( 'Presets need the kit\'s slots. Put the slots right and save, then add the presets.', 'creative-skateboard-builder' ),
			/* translators: 1: a field's name. 2: a product's name. 3: a slot's label. */
			KitEditor::NOT_IN_SLOT => sprintf( __( '%1$s: %2$s is not a part the %3$s slot takes, or it cannot be bought now.', 'creative-skateboard-builder' ), $field, $part, $slot ),
			KitEditor::BLOCKED => MessageCode::SpecMissing->value === ( $values['message'] ?? '' )
				/* translators: 1: a field's name. 2: a product's name. 3: a rule's number, such as R1. */
				? sprintf( __( '%1$s: %2$s lacks a spec that rule %3$s reads, so the builder does not offer it. Fill it in on its Part specs.', 'creative-skateboard-builder' ), $field, $part, $values['rule'] ?? '' )
				/* translators: 1: a field's name. 2: a product's name. 3: a rule's number, such as R1. 4: a preset's name. */
				: sprintf( __( '%1$s: %2$s does not fit the other parts of "%4$s". Rule %3$s does not let them go together.', 'creative-skateboard-builder' ), $field, $part, $values['rule'] ?? '', $preset ),
			/* translators: %s: a field's name. */
			DocumentError::WrongType->value => sprintf( __( '%s: this is not a value the field takes.', 'creative-skateboard-builder' ), $field ),
			/* translators: %s: a section's name. */
			default => sprintf( __( '%s: what was sent could not be read. Load the page again and try once more.', 'creative-skateboard-builder' ), $field ),
		};
	}

	/**
	 * IDs the check joined with commas, as the locale writes a list: "11, 12 and 13".
	 *
	 * @param string $ids IDs joined with `, `.
	 */
	private static function ids( string $ids ): string {
		return '' === $ids ? '' : Words::joined( explode( ', ', $ids ) );
	}
}
