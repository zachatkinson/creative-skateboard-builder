<?php
/**
 * The sentences the admin and the command line share.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Admin;

use CreativeBranding\SkateboardBuilder\Admin\Sentences;
use CreativeBranding\SkateboardBuilder\Service\CheckFinding;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin: the services return codes, and each code has one sentence that says what is wrong and what to do.
 * A code with no sentence is shown as it is, so nothing is ever blank.
 */
final class SentencesTest extends TestCase {

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_decimal'] );
	}

	/**
	 * A finding of the Catalogue check, and its sentence.
	 *
	 * @return array<string, array{0: CheckFinding, 1: string}>
	 */
	public static function findings(): array {
		$finding = static fn( string $code, string $slot = '', string $spec = '', array $values = array() ): CheckFinding => new CheckFinding( $code, 12, 3, $slot, $spec, $values );
		return array(
			'two sources disagree'                   => array(
				$finding(
					'sources_disagree',
					'',
					'deck_width',
					array(
						'kept_from'  => 'table',
						'kept'       => '8.25',
						'other_from' => 'attribute',
						'other'      => '8.5',
					)
				),
				'Deck width: two sources disagree. The maker\'s chart says 8.25 in, which is used. A mapped attribute says 8.5 in.',
			),
			'they disagree, the sources not said'    => array(
				$finding( 'sources_disagree', '', 'deck_width', array( 'kept' => '8.25' ) ),
				'Deck width: two sources disagree. The part\'s own field says 8.25 in, which is used. The part\'s own field says .',
			),
			'they disagree over a spec not known'    => array(
				$finding(
					'sources_disagree',
					'',
					'concave',
					array(
						'kept_from'  => 'field',
						'kept'       => 'deep',
						'other_from' => 'nowhere',
						'other'      => 'mellow',
					)
				),
				'concave: two sources disagree. The part\'s own field says deep, which is used. The part\'s own field says mellow.',
			),
			'a chart that is gone'                   => array(
				$finding( 'value_unread', '', '', array( 'reason' => 'table_missing' ) ),
				'It names a maker\'s chart that is no longer there. Choose another on its Part specs.',
			),
			'a size its chart does not have'         => array(
				$finding(
					'value_unread',
					'',
					'',
					array(
						'reason' => 'size_missing',
						'text'   => '149',
					)
				),
				'It names the size "149", which its maker\'s chart does not have.',
			),
			'a size missing, the size not said'      => array(
				$finding( 'value_unread', '', '', array( 'reason' => 'size_missing' ) ),
				'It names the size "", which its maker\'s chart does not have.',
			),
			'a value that cannot be read'            => array(
				$finding(
					'value_unread',
					'',
					'wheel_diameter',
					array(
						'reason' => 'measure_malformed',
						'from'   => 'attribute',
						'text'   => 'big',
					)
				),
				'Wheel diameter: A mapped attribute holds "big", which cannot be read as a value.',
			),
			'unread, with nothing said'              => array(
				$finding( 'value_unread', '', 'wheel_diameter' ),
				'Wheel diameter: The part\'s own field holds "", which cannot be read as a value.',
			),
			'the reindex failed'                     => array(
				$finding( 'reindex_failed' ),
				'Reading every part again stopped after five failures. Start it again with "Read every part again" on this page; WooCommerce > Status > Logs has the reason.',
			),
			'the import failed'                      => array(
				$finding( 'import_failed' ),
				'The last specs import stopped before its end. Import the file again; WooCommerce > Status > Logs has the reason.',
			),
			'one job failed'                         => array(
				$finding( 'jobs_failed', '', '', array( 'count' => '1' ) ),
				'1 background job of the builder has failed.',
			),
			'many jobs failed'                       => array(
				$finding( 'jobs_failed', '', '', array( 'count' => '1200' ) ),
				'1,200 background jobs of the builder have failed.',
			),
			'jobs failed, the number not said'       => array(
				$finding( 'jobs_failed' ),
				'0 background jobs of the builder have failed.',
			),
			'a slot with no parts'                   => array( $finding( 'slot_empty', 'trucks' ), 'The slot "trucks" has no parts.' ),
			'a slot with nothing in stock'           => array( $finding( 'slot_out_of_stock', 'wheels' ), 'The slot "wheels" has nothing in stock.' ),
			'a spec missing'                         => array(
				$finding( 'spec_missing', 'trucks', 'axle_width' ),
				'Axle width is missing, so the part is not offered in the slot "trucks".',
			),
			'a spec assumed'                         => array(
				$finding( 'spec_assumed', 'decks', 'hole_pattern' ),
				'Deck hole pattern is not filled in, so the builder assumes the usual one.',
			),
			'a preset names a part not in the slot'  => array(
				$finding(
					'preset_broken',
					'decks',
					'',
					array(
						'preset'  => 'Street',
						'problem' => 'part_not_in_slot',
					)
				),
				'The preset "Street" names it for the slot "decks", and it is not one of that slot\'s parts now.',
			),
			'a preset names a part that is sold out' => array(
				$finding(
					'preset_broken',
					'decks',
					'',
					array(
						'preset'  => 'Street',
						'problem' => 'part_sold_out',
					)
				),
				'The preset "Street" names it for the slot "decks", and it is sold out.',
			),
			'a preset whose parts no longer fit'     => array(
				$finding(
					'preset_broken',
					'decks',
					'',
					array(
						'preset'  => 'Street',
						'problem' => 'fit',
					)
				),
				'The preset "Street" names it for the slot "decks", and it no longer fits the preset\'s other parts.',
			),
			'a preset, with nothing said'            => array(
				$finding( 'preset_broken', 'decks' ),
				'The preset "" names it for the slot "decks", and it no longer fits the preset\'s other parts.',
			),
			'a code with no sentence'                => array( $finding( 'something_new', 'decks', 'deck_width' ), 'something_new' ),
		);
	}

	#[DataProvider( 'findings' )]
	public function test_each_finding_of_the_check_has_its_sentence( CheckFinding $finding, string $sentence ): void {
		self::assertSame( $sentence, Sentences::finding( $finding ) );
	}

	public function test_a_findings_values_are_written_as_the_shops_locale_writes_numbers(): void {
		$GLOBALS['cbuild_test_decimal'] = ',';
		self::assertSame(
			'Deck width: two sources disagree. The maker\'s chart says 8,25 in, which is used. The part\'s own field says 8,5 in.',
			Sentences::finding(
				new CheckFinding(
					'sources_disagree',
					12,
					0,
					'',
					'deck_width',
					array(
						'kept_from'  => 'table',
						'kept'       => '8.25',
						'other_from' => 'field',
						'other'      => '8.5',
					)
				)
			)
		);
	}

	public function test_each_reason_a_file_is_refused_has_its_sentence(): void {
		$sentences = array(
			'upload_missing'     => 'Choose a CSV file first.',
			'upload_failed'      => 'The file did not arrive whole. Choose it again.',
			'upload_not_csv'     => 'That is not a .csv file. Save the sheet as CSV and choose it again.',
			'file_too_big'       => 'The file is over 2 MB. Split it into smaller files.',
			'file_not_utf8'      => 'The file is not saved as UTF-8. Save it as CSV UTF-8 and choose it again.',
			'file_empty'         => 'The file has no rows under its headings.',
			'rows_too_many'      => 'The file has more than 5,000 rows. Split it into smaller files.',
			'key_column_missing' => 'The file has no column for what parts are matched by. Add an id or a sku column, or change the choice below.',
			'column_twice'       => 'A column is in the file twice. Keep one of each.',
			'no_spec_columns'    => 'The file has no spec columns, so there is nothing to import.',
			'import_running'     => 'An import is running. Wait for it to finish.',
			'something_new'      => 'something_new',
			''                   => '',
		);
		foreach ( $sentences as $code => $sentence ) {
			self::assertSame( $sentence, Sentences::fileRefused( (string) $code ), (string) $code );
		}
	}

	public function test_each_problem_with_a_row_has_its_sentence_and_names_what_the_row_held(): void {
		$sentences = array(
			'key_missing'              => 'The row does not say which part it is for.',
			'key_twice'                => '"YEA-DK" is in the file more than once. Keep one row for it.',
			'part_missing'             => 'No product is "YEA-DK".',
			'sku_mismatch'             => 'The product with this ID does not have the SKU "YEA-DK". Check the row is for this shop, or match parts by SKU.',
			'table_unknown'            => 'There is no chart named "YEA-DK". Add it under Spec tables first.',
			'table_ambiguous'          => 'More than one chart is named "YEA-DK". Rename one under Spec tables.',
			'size_missing'             => 'The chart has no size "YEA-DK".',
			'size_long'                => 'The size is too long. Use 40 characters or fewer.',
			'table_missing'            => 'That chart is no longer there.',
			'measure_malformed'        => '"YEA-DK" is not a measurement. Use a number, or a fraction such as 8 1/4.',
			'measure_zero_denominator' => 'The fraction in "YEA-DK" divides by zero.',
			'measure_negative'         => '"YEA-DK" is below zero.',
			'something_new'            => '"YEA-DK" is not a value this column takes.',
		);
		foreach ( $sentences as $code => $sentence ) {
			self::assertSame( $sentence, Sentences::rowProblem( $code, 'YEA-DK' ), $code );
		}
	}
}
