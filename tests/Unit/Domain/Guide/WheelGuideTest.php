<?php
/**
 * WheelGuide.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Guide;

use CreativeBranding\SkateboardBuilder\Domain\Guide\RidingStyle;
use CreativeBranding\SkateboardBuilder\Domain\Guide\WheelGuide;
use CreativeBranding\SkateboardBuilder\Domain\Kit\DocumentError;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Fields;
use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidDocument;
use CreativeBranding\SkateboardBuilder\Domain\Kit\KitSettings;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * #134: the kit's wheel guide, riding style to a wheel size range in millimetres, which the guided start reads. A kit
 * with none, as every kit saved before 1.3.0, has the plugin's sizes, from published wheel guides (D-400).
 */
final class WheelGuideTest extends TestCase {

	/**
	 * The guide from its object in the settings.
	 *
	 * @param array<string, mixed> $guide The `wheel_guide` object.
	 */
	private static function guide( array $guide ): WheelGuide {
		return WheelGuide::fromFields(
			Fields::document(
				array(
					'schema_version' => 1,
					'wheel_guide'    => $guide,
				)
			)->object( 'wheel_guide' )
		);
	}

	/**
	 * A style's range as two decimals of millimetres.
	 *
	 * @param WheelGuide  $guide The guide.
	 * @param RidingStyle $style The style.
	 * @return array{0: string, 1: string|null}
	 */
	private static function range( WheelGuide $guide, RidingStyle $style ): array {
		$span = $guide->span( $style );
		return array( $span->from->decimal(), $span->to?->decimal() );
	}

	public function test_a_kit_with_no_wheel_guide_has_the_plugins_sizes_from_the_published_guides(): void {
		foreach ( array(
			'no settings'            => KitSettings::fromDocument( null )->wheels,
			'settings with no guide' => KitSettings::fromDocument( array( 'schema_version' => 1 ) )->wheels,
			'a guide with no rows'   => self::guide( array() ),
			'a guide with none'      => self::guide( array( 'rows' => array() ) ),
		) as $case => $guide ) {
			self::assertSame( array( '50.0000', '54.0000' ), self::range( $guide, RidingStyle::Street ), $case );
			self::assertSame( array( '53.0000', '56.0000' ), self::range( $guide, RidingStyle::Park ), $case );
			self::assertSame( array( '55.0000', '58.0000' ), self::range( $guide, RidingStyle::Bowl ), $case );
			self::assertSame( array( '59.0000', '65.0000' ), self::range( $guide, RidingStyle::Cruising ), $case );
			foreach ( RidingStyle::cases() as $style ) {
				self::assertFalse( $guide->isOwn( $style ), $case . ': ' . $style->value );
			}
		}
	}

	public function test_the_shops_row_for_a_style_takes_the_place_of_the_plugins_and_the_others_keep_theirs(): void {
		$guide = self::guide(
			array(
				'rows' => array(
					array(
						'style' => 'park',
						'from'  => '54',
						'to'    => '58.5',
					),
				),
			)
		);
		self::assertSame( array( '54.0000', '58.5000' ), self::range( $guide, RidingStyle::Park ) );
		self::assertTrue( $guide->isOwn( RidingStyle::Park ) );
		self::assertSame( array( '50.0000', '54.0000' ), self::range( $guide, RidingStyle::Street ), 'a style the shop left has the plugin\'s' );
		self::assertFalse( $guide->isOwn( RidingStyle::Street ) );
		self::assertEquals( Measure::millimetres( 540000 ), $guide->span( RidingStyle::Park )->from );
	}

	public function test_a_row_with_one_size_is_that_size_alone(): void {
		$guide = self::guide( array( 'rows' => array( array( 'style' => 'street', 'from' => '52' ) ) ) );
		self::assertSame( array( '52.0000', '52.0000' ), self::range( $guide, RidingStyle::Street ) );
		$empty = self::guide( array( 'rows' => array( array( 'style' => 'street', 'from' => '52', 'to' => '' ) ) ) );
		self::assertSame( array( '52.0000', '52.0000' ), self::range( $empty, RidingStyle::Street ), 'an empty to is none' );
	}

	/**
	 * @return iterable<string, array{0: string, 1: string|null}>
	 */
	public static function sizesInRange(): iterable {
		yield 'the smallest wheel' => array( '40', null );
		yield 'the largest wheel' => array( '90', null );
		yield 'the smallest to the largest' => array( '40', '90' );
		yield 'to the same size' => array( '54', '54' );
		yield 'with four places' => array( '53.5555', '53.5556' );
	}

	/**
	 * @param string      $from The smallest size.
	 * @param string|null $to   The largest, or none.
	 */
	#[DataProvider( 'sizesInRange' )]
	public function test_a_size_from_40_to_90_mm_is_read( string $from, ?string $to ): void {
		$row = array(
			'style' => 'bowl',
			'from'  => $from,
		);
		if ( null !== $to ) {
			$row['to'] = $to;
		}
		self::assertTrue( self::guide( array( 'rows' => array( $row ) ) )->isOwn( RidingStyle::Bowl ) );
	}

	/**
	 * @return iterable<string, array{0: array<string, mixed>, 1: DocumentError, 2: string}>
	 */
	public static function refused(): iterable {
		$row = static fn( array $with ): array => array( 'rows' => array( $with + array( 'style' => 'street', 'from' => '52', 'to' => '54' ) ) );
		yield 'under 40 mm' => array( $row( array( 'from' => '39.9999' ) ), DocumentError::OutOfRange, 'wheel_guide.rows[0].from' );
		yield 'over 90 mm' => array( $row( array( 'to' => '90.0001' ) ), DocumentError::OutOfRange, 'wheel_guide.rows[0].to' );
		yield 'a from over 90 mm' => array( $row( array( 'from' => '91', 'to' => '' ) ), DocumentError::OutOfRange, 'wheel_guide.rows[0].from' );
		yield 'to under from' => array( $row( array( 'from' => '54', 'to' => '53.9999' ) ), DocumentError::OutOfOrder, 'wheel_guide.rows[0].to' );
		yield 'no size' => array( array( 'rows' => array( array( 'style' => 'street' ) ) ), DocumentError::Missing, 'wheel_guide.rows[0].from' );
		yield 'a size that is no measure' => array( $row( array( 'from' => '52mm' ) ), DocumentError::BadMeasure, 'wheel_guide.rows[0].from' );
		yield 'a style the question does not ask' => array( $row( array( 'style' => 'vert' ) ), DocumentError::OutOfRange, 'wheel_guide.rows[0].style' );
		yield 'no style' => array( array( 'rows' => array( array( 'from' => '52' ) ) ), DocumentError::Missing, 'wheel_guide.rows[0].style' );
		yield 'a style twice' => array(
			array(
				'rows' => array(
					array(
						'style' => 'park',
						'from'  => '53',
					),
					array(
						'style' => 'park',
						'from'  => '56',
					),
				),
			),
			DocumentError::Duplicate,
			'wheel_guide.rows[1].style',
		);
		yield 'more rows than styles' => array(
			array( 'rows' => array_fill( 0, 5, array( 'style' => 'park', 'from' => '53' ) ) ),
			DocumentError::OutOfRange,
			'wheel_guide.rows',
		);
		yield 'rows that are no list' => array( array( 'rows' => 'street' ), DocumentError::WrongType, 'wheel_guide.rows' );
	}

	/**
	 * @param array<string, mixed> $guide  The guide.
	 * @param DocumentError        $reason Why it is refused.
	 * @param string               $path   Where.
	 */
	#[DataProvider( 'refused' )]
	public function test_a_guide_that_breaks_its_shape_is_refused_at_its_field( array $guide, DocumentError $reason, string $path ): void {
		try {
			self::guide( $guide );
			self::fail( 'read: ' . $path );
		} catch ( InvalidDocument $refused ) {
			self::assertSame( $reason, $refused->reason );
			self::assertSame( $path, $refused->path );
		}
	}

	public function test_the_settings_read_the_kits_wheel_guide(): void {
		$settings = KitSettings::fromDocument(
			array(
				'schema_version' => 1,
				'wheel_guide'    => array( 'rows' => array( array( 'style' => 'cruising', 'from' => '60', 'to' => '70' ) ) ),
			)
		);
		self::assertSame( array( '60.0000', '70.0000' ), self::range( $settings->wheels, RidingStyle::Cruising ) );
		self::assertTrue( $settings->startButton, 'and nothing else changes' );
		$this->expectExceptionObject( InvalidDocument::because( DocumentError::OutOfRange, 'wheel_guide.rows[0].from' ) );
		KitSettings::fromDocument( array( 'schema_version' => 1, 'wheel_guide' => array( 'rows' => array( array( 'style' => 'cruising', 'from' => '10' ) ) ) ) );
	}

	public function test_each_style_is_one_the_question_asks_in_its_order(): void {
		self::assertSame( array( 'street', 'park', 'bowl', 'cruising' ), array_column( RidingStyle::cases(), 'value' ) );
	}
}
