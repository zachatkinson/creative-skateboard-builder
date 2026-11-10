<?php
/**
 * The settings, checked and saved.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Service;

use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Domain\Spec\AttributeMap;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SourceOrder;
use CreativeBranding\SkateboardBuilder\Service\SettingsEditor;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Spec, Admin, Settings: the attribute mapping and which source wins. All of what is sent is saved, or none.
 */
final class SettingsEditorTest extends TestCase {

	private const ORDER = array( 'table', 'field', 'attribute' );

	private InMemoryShopSettings $settings;

	private SettingsEditor $editor;

	protected function setUp(): void {
		$this->settings = new InMemoryShopSettings();
		$this->editor   = new SettingsEditor( $this->settings );
	}

	/**
	 * A line as the form sends it.
	 */
	private static function line( string $attribute, string $spec, string $unit = '' ): array {
		return compact( 'attribute', 'spec', 'unit' );
	}

	/**
	 * The problems of a submission, as code@line.
	 *
	 * @param array<mixed> $lines The lines.
	 * @param array<mixed> $order The order.
	 * @return list<string>
	 */
	private function problems( array $lines, array $order = self::ORDER ): array {
		return array_map( static fn( array $problem ): string => $problem['code'] . '@' . $problem['line'], $this->editor->submit( $lines, $order )->problems );
	}

	public function test_a_mapping_and_an_order_with_nothing_wrong_are_saved(): void {
		$draft = $this->editor->submit(
			array( self::line( ' pa_width ', 'deck_width', 'in' ), self::line( 'Length', 'deck_length' ), self::line( 'pa_pattern', 'hole_pattern' ) ),
			array( 'field', 'attribute', 'table' )
		);
		self::assertTrue( $draft->saved() );
		self::assertSame( array(), $draft->problems );
		self::assertSame( 1, $this->settings->saves );
		self::assertSame(
			array(
				array( 'attribute' => 'pa_width', 'spec' => 'deck_width', 'unit' => 'in' ),
				array( 'attribute' => 'Length', 'spec' => 'deck_length', 'unit' => '' ),
				array( 'attribute' => 'pa_pattern', 'spec' => 'hole_pattern', 'unit' => '' ),
			),
			array_map( static fn( AttributeMap $map ): array => $map->toArray(), $this->settings->mapping )
		);
		self::assertSame( $draft->mapping, $this->settings->mapping );
		self::assertSame( array( 'field', 'attribute', 'table' ), $this->settings->order?->names() );
		self::assertSame( $draft->order, $this->settings->order );
	}

	public function test_a_blank_line_is_no_line_and_an_empty_mapping_is_saved(): void {
		$draft = $this->editor->submit(
			array( self::line( '', '' ), self::line( '  ', '', 'mm' ), 'not a line', array( 'attribute' => 7, 'spec' => null ), array() ),
			self::ORDER
		);
		self::assertTrue( $draft->saved() );
		self::assertSame( array(), $this->settings->mapping );
		self::assertSame( 1, $this->settings->saves );
		self::assertEquals( SourceOrder::standard(), $this->settings->order );
	}

	public function test_each_thing_wrong_with_a_line_is_named_with_its_line_as_the_screen_counts_them(): void {
		$long = str_repeat( 'a', 191 );
		self::assertSame(
			array(
				'mapping_attribute_missing@2',
				'mapping_spec_missing@4',
				'mapping_attribute_long@5',
				'mapping_spec_unknown@6',
				'mapping_unit_unknown@7',
				'mapping_unit_not_measure@8',
				'mapping_duplicate@9',
			),
			$this->problems(
				array(
					self::line( 'pa_width', 'deck_width' ),
					self::line( '', 'deck_width' ),
					self::line( '', '' ),
					self::line( 'pa_width', '' ),
					self::line( $long, 'deck_width' ),
					self::line( 'pa_width', 'deck_girth' ),
					self::line( 'pa_width', 'grip_width', 'cm' ),
					self::line( 'pa_pattern', 'hole_pattern', 'in' ),
					self::line( ' pa_width', 'deck_width', 'mm' ),
				)
			)
		);
		self::assertSame( 0, $this->settings->saves, 'one line wrong, and none of it is saved' );
		self::assertNull( $this->settings->order );
	}

	public function test_what_is_checked_first(): void {
		$long = str_repeat( 'a', 191 );
		self::assertSame( array( 'mapping_spec_missing@1' ), $this->problems( array( self::line( $long, '', 'cm' ) ) ) );
		self::assertSame( array( 'mapping_attribute_long@1' ), $this->problems( array( self::line( $long, 'deck_girth', 'cm' ) ) ) );
		self::assertSame( array( 'mapping_spec_unknown@1' ), $this->problems( array( self::line( 'a', 'deck_girth', 'cm' ) ) ) );
		self::assertSame( array( 'mapping_unit_unknown@1' ), $this->problems( array( self::line( 'a', 'hole_pattern', 'cm' ) ) ) );
		self::assertSame( array(), $this->problems( array( self::line( str_repeat( 'a', 190 ), 'deck_width', 'mm' ) ) ), 'a name of 190 characters is kept' );
	}

	public function test_two_attributes_may_feed_one_spec_and_one_attribute_two_specs(): void {
		$lines = array( self::line( 'pa_width', 'deck_width' ), self::line( 'Width', 'deck_width' ), self::line( 'pa_width', 'grip_width' ) );
		self::assertSame( array(), $this->problems( $lines ), 'the Catalogue check shows where they disagree (D-069)' );
		self::assertCount( 3, $this->settings->mapping );
		self::assertSame( array( 'mapping_duplicate@2', 'mapping_duplicate@4' ), $this->problems( array( $lines[0], $lines[0], $lines[1], $lines[1] ) ) );
	}

	public function test_a_line_that_was_refused_does_not_make_a_later_one_a_duplicate(): void {
		self::assertSame(
			array( 'mapping_unit_unknown@1' ),
			$this->problems( array( self::line( 'pa_width', 'deck_width', 'cm' ), self::line( 'pa_width', 'deck_width' ) ) )
		);
	}

	public function test_a_mapping_holds_fifty_lines_at_most(): void {
		$lines = array();
		foreach ( range( 1, 50 ) as $number ) {
			$lines[] = self::line( 'attribute ' . $number, 'deck_width' );
		}
		self::assertSame( 50, SettingsEditor::MAX_LINES );
		self::assertSame( array(), $this->problems( $lines ) );
		self::assertCount( 50, $this->settings->mapping );
		$lines[] = self::line( 'attribute 51', 'deck_width' );
		self::assertSame( array( 'mapping_too_many@0' ), $this->problems( $lines ) );
		self::assertSame( 1, $this->settings->saves );
	}

	public function test_an_order_that_is_not_each_source_once_is_refused_with_the_mapping(): void {
		$lines = array( self::line( 'pa_width', 'deck_width' ) );
		foreach ( array( array(), array( 'table', 'field' ), array( 'table', 'table', 'field' ), array( 'table', 'field', 'feed' ), array( 'table', 'field', 'attribute', 'table' ) ) as $order ) {
			$draft = $this->editor->submit( $lines, $order );
			self::assertFalse( $draft->saved() );
			self::assertNull( $draft->order );
			self::assertCount( 1, $draft->mapping, 'the lines that read are kept for the screen' );
			self::assertSame( array( array( 'code' => 'order_invalid', 'line' => 0 ) ), $draft->problems );
		}
		self::assertSame( 0, $this->settings->saves );
		self::assertSame(
			array( 'mapping_spec_missing@1', 'order_invalid@0' ),
			$this->problems( array( self::line( 'pa_width', '' ) ), array() ),
			'both are told at once'
		);
	}

	public function test_the_codes_are_the_ones_the_screen_has_words_for(): void {
		self::assertSame(
			array( 'mapping_attribute_missing', 'mapping_spec_missing', 'mapping_attribute_long', 'mapping_spec_unknown', 'mapping_unit_unknown', 'mapping_unit_not_measure', 'mapping_duplicate', 'mapping_too_many', 'order_invalid' ),
			array( SettingsEditor::ATTRIBUTE_MISSING, SettingsEditor::SPEC_MISSING, SettingsEditor::ATTRIBUTE_LONG, SettingsEditor::SPEC_UNKNOWN, SettingsEditor::UNIT_UNKNOWN, SettingsEditor::UNIT_NOT_MEASURE, SettingsEditor::DUPLICATE, SettingsEditor::TOO_MANY, SettingsEditor::ORDER_INVALID )
		);
	}

	/**
	 * The terms as the form sends them, with nothing wrong.
	 */
	private const array TERMS = array(
		'discount'       => '7,5',
		'coupons'        => 'parts',
		'rate'           => '60',
		'header'         => ' CF-Connecting-IP ',
		'default_on'     => '1',
		'fee'            => '15,50',
		'lead_time'      => ' Two working days ',
		'returns_notice' => 'An assembled board cannot be returned.',
	);

	/**
	 * The problems of the terms, as code@line.
	 *
	 * @param array<string, mixed> $terms The fields that differ from the ones with nothing wrong.
	 * @return list<string>
	 */
	private function wrong( array $terms ): array {
		return array_map( static fn( array $problem ): string => $problem['code'] . '@' . $problem['line'], $this->editor->submit( array(), self::ORDER, $terms + self::TERMS, ',' )->problems );
	}

	public function test_the_terms_are_saved_with_the_mapping_as_the_settings_write_them(): void {
		$draft = $this->editor->submit( array( self::line( 'pa_width', 'deck_width' ) ), self::ORDER, self::TERMS, ',' );
		self::assertTrue( $draft->saved() );
		self::assertSame( 1, $this->settings->saves );
		self::assertNotNull( $draft->terms );
		self::assertSame( $draft->terms, $this->settings->terms() );
		self::assertSame(
			array(
				'build_discount'       => '7.5',
				'fixed_coupons'        => 'parts',
				'rate_limit'           => 60,
				'trusted_proxy_header' => 'CF-Connecting-IP',
				'kit_defaults'         => array(
					'assembly' => array(
						'schema_version' => 1,
						'default_on'     => true,
						'fee'            => '15.5',
						'lead_time'      => 'Two working days',
						'returns_notice' => 'An assembled board cannot be returned.',
					),
				),
			),
			$this->settings->terms()->toArray()
		);
	}

	public function test_fields_left_empty_are_no_discount_no_header_and_no_fee(): void {
		$draft = $this->editor->submit(
			array(),
			self::ORDER,
			array(
				'discount' => '',
				'coupons'  => 'kit',
				'rate'     => '120',
			)
		);
		self::assertTrue( $draft->saved() );
		self::assertSame( BuildTerms::read( null )->toArray(), $this->settings->terms()->toArray() );
	}

	public function test_a_submission_without_terms_leaves_the_terms_as_they_are(): void {
		$this->editor->submit( array(), self::ORDER, self::TERMS, ',' );
		$draft = $this->editor->submit( array( self::line( 'pa_width', 'deck_width' ) ), self::ORDER );
		self::assertTrue( $draft->saved() );
		self::assertNull( $draft->terms );
		self::assertSame( 750, $this->settings->terms()->discount );
		self::assertCount( 1, $this->settings->mapping );
	}

	public function test_a_point_is_read_whatever_the_shops_separator(): void {
		self::assertSame(
			array(),
			$this->wrong(
				array(
					'discount' => '7.5',
					'fee'      => '15.50',
				)
			)
		);
		self::assertSame( 750, $this->settings->terms()->discount );
		self::assertSame( '15.5', $this->settings->terms()->assembly['fee'] );
	}

	/**
	 * @return iterable<string, array{array<string, mixed>, list<string>}>
	 */
	public static function wrongTerms(): iterable {
		yield 'a discount of everything' => array( array( 'discount' => '100' ), array( 'discount_invalid@0' ) );
		yield 'a discount in words' => array( array( 'discount' => 'five' ), array( 'discount_invalid@0' ) );
		yield 'a discount with three places' => array( array( 'discount' => '5,125' ), array( 'discount_invalid@0' ) );
		yield 'a discount that is no text' => array( array( 'discount' => array( '5' ) ), array() );
		yield 'coupons somewhere else' => array( array( 'coupons' => 'cart' ), array( 'coupons_invalid@0' ) );
		yield 'no coupons choice' => array( array( 'coupons' => '' ), array( 'coupons_invalid@0' ) );
		yield 'a rate below the least' => array( array( 'rate' => '9' ), array( 'rate_invalid@0' ) );
		yield 'a rate above the most' => array( array( 'rate' => '6001' ), array( 'rate_invalid@0' ) );
		yield 'a rate with a point' => array( array( 'rate' => '120.5' ), array( 'rate_invalid@0' ) );
		yield 'a rate with a sign' => array( array( 'rate' => '+120' ), array( 'rate_invalid@0' ) );
		yield 'a rate too long to be a number' => array( array( 'rate' => '00000000000000000000120' ), array( 'rate_invalid@0' ) );
		yield 'no rate' => array( array( 'rate' => '' ), array( 'rate_invalid@0' ) );
		yield 'a header with a space' => array( array( 'header' => 'X Real IP' ), array( 'header_invalid@0' ) );
		yield 'a header as PHP names it' => array( array( 'header' => 'HTTP_X_REAL_IP' ), array( 'header_invalid@0' ) );
		yield 'a fee in words' => array( array( 'fee' => 'free' ), array( 'fee_invalid@0' ) );
		yield 'a fee with five places' => array( array( 'fee' => '1,00001' ), array( 'fee_invalid@0' ) );
		yield 'a long lead time' => array( array( 'lead_time' => str_repeat( 'a', 201 ) ), array( 'lead_time_long@0' ) );
		yield 'a long returns notice' => array( array( 'returns_notice' => str_repeat( 'a', 1001 ) ), array( 'returns_notice_long@0' ) );
		yield 'every field wrong' => array(
			array(
				'discount' => '-1',
				'coupons'  => 'both',
				'rate'     => 'often',
				'header'   => 'X:Y',
				'fee'      => '-1',
			),
			array( 'discount_invalid@0', 'coupons_invalid@0', 'rate_invalid@0', 'header_invalid@0', 'fee_invalid@0' ),
		);
	}

	/**
	 * @param array<string, mixed> $terms    The fields that are wrong.
	 * @param list<string>         $problems What is said of them.
	 */
	#[DataProvider( 'wrongTerms' )]
	public function test_a_term_that_is_wrong_is_named_and_nothing_is_saved( array $terms, array $problems ): void {
		self::assertSame( $problems, $this->wrong( $terms ) );
		self::assertSame( array() === $problems ? 1 : 0, $this->settings->saves );
	}

	public function test_a_wrong_term_keeps_a_good_mapping_from_saving_and_a_wrong_line_keeps_good_terms(): void {
		$draft = $this->editor->submit( array( self::line( 'pa_width', 'deck_width' ) ), self::ORDER, array( 'rate' => '1' ) + self::TERMS, ',' );
		self::assertFalse( $draft->saved() );
		self::assertNull( $draft->terms );
		self::assertCount( 1, $draft->mapping );

		$draft = $this->editor->submit( array( self::line( 'pa_width', 'nothing' ) ), self::ORDER, self::TERMS, ',' );
		self::assertFalse( $draft->saved() );
		self::assertNotNull( $draft->terms );
		self::assertSame( 0, $this->settings->saves );
		self::assertSame( 0, $this->settings->terms()->discount );
	}

	public function test_the_most_and_the_least_a_field_takes_are_taken(): void {
		self::assertSame(
			array(),
			$this->wrong(
				array(
					'discount'       => '99,99',
					'rate'           => '6000',
					'lead_time'      => str_repeat( 'é', 200 ),
					'returns_notice' => str_repeat( 'é', 1000 ),
				)
			)
		);
		self::assertSame( array(), $this->wrong( array( 'rate' => '10' ) ) );
		self::assertSame( 10, $this->settings->terms()->rateLimit );
	}
}
