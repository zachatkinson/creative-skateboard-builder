<?php
/**
 * Looks.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Look;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Look\Look;
use CreativeBranding\SkateboardBuilder\Domain\Look\Looks;
use CreativeBranding\SkateboardBuilder\Domain\Look\Variant;
use CreativeBranding\SkateboardBuilder\Domain\Measure\Measure;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\TestCase;

/**
 * One card per look: a product, or the variations of one product that share a photo and a layer image
 * (SPEC-CONFIGURATOR, One card per look; D-240).
 */
final class LooksTest extends TestCase {

	/**
	 * A deck variation: its width, photo and layer image, and its other attributes.
	 *
	 * @param int                   $id      The variation's id.
	 * @param int                   $product The parent product's id.
	 * @param string                $width   Its width in inches.
	 * @param int                   $photo   Its photo's attachment id.
	 * @param int                   $layer   Its layer image's attachment id.
	 * @param array<string, string> $choices Its other attributes.
	 */
	private static function deck( int $id, int $product, string $width, int $photo, int $layer = 0, array $choices = array() ): Variant {
		return new Variant( Parts::of( array( 'deck_width' => $width ), $id ), $product, $photo, $layer, $choices );
	}

	/**
	 * The parts' ids, look by look.
	 *
	 * @param list<Look> $looks The looks.
	 * @return list<list<int>>
	 */
	private static function ids( array $looks ): array {
		return array_map( static fn( Look $look ): array => array_map( static fn( Part $part ): int => $part->id, $look->parts() ), $looks );
	}

	public function test_sizes_that_share_a_photo_and_a_layer_image_are_one_look(): void {
		$looks = Looks::of( array( self::deck( 11, 10, '8', 1, 5 ), self::deck( 12, 10, '8.25', 1, 5 ), self::deck( 13, 10, '8.5', 1, 5 ) ) );
		self::assertSame( array( array( 11, 12, 13 ) ), self::ids( $looks ) );
		self::assertSame( 10, $looks[0]->product );
		self::assertSame( '10:1:5', $looks[0]->key, 'product, photo and layer image' );
	}

	public function test_a_colour_with_its_own_photo_is_its_own_look(): void {
		$looks = Looks::of( array( self::deck( 11, 10, '8', 1 ), self::deck( 12, 10, '8', 2 ), self::deck( 13, 10, '8.5', 1 ), self::deck( 14, 10, '8.5', 2 ) ) );
		self::assertSame( array( array( 11, 13 ), array( 12, 14 ) ), self::ids( $looks ) );
		self::assertNotSame( $looks[0]->key, $looks[1]->key );
	}

	public function test_the_same_photo_with_another_layer_image_is_another_look(): void {
		$looks = Looks::of( array( self::deck( 11, 10, '8', 1, 5 ), self::deck( 12, 10, '8', 1, 6 ) ) );
		self::assertSame( array( array( 11 ), array( 12 ) ), self::ids( $looks ) );
	}

	public function test_another_products_variations_are_never_the_same_look(): void {
		$looks = Looks::of( array( self::deck( 11, 10, '8', 1, 5 ), self::deck( 21, 20, '8', 1, 5 ) ) );
		self::assertSame( array( array( 11 ), array( 21 ) ), self::ids( $looks ) );
	}

	public function test_a_products_looks_sit_side_by_side_in_the_order_first_given(): void {
		$looks = Looks::of( array( self::deck( 11, 10, '8', 1 ), self::deck( 21, 20, '8', 3 ), self::deck( 12, 10, '8', 2 ), self::deck( 22, 20, '8.5', 3 ), self::deck( 13, 10, '8.5', 1 ) ) );
		self::assertSame( array( array( 11, 13 ), array( 12 ), array( 21, 22 ) ), self::ids( $looks ) );
	}

	public function test_nothing_given_is_no_looks(): void {
		self::assertSame( array(), Looks::of( array() ) );
	}

	public function test_the_choices_you_cant_see_are_the_attributes_that_differ_between_a_looks_variations(): void {
		$bolt  = static fn( int $id, string $length, string $head, string $colour ): Variant => new Variant( Parts::of( array( 'bolt_length' => $length ), $id ), 60, 7, 0, array( 'pa_head' => $head, 'pa_colour' => $colour ) );
		$looks = Looks::of( array( $bolt( 61, '1', 'allen', 'black' ), $bolt( 62, '1', 'phillips', 'black' ), $bolt( 63, '1.25', 'allen', 'black' ) ) );
		self::assertSame( array( 'pa_head' => array( 'allen', 'phillips' ) ), $looks[0]->choices(), 'the colour is the same on all three, so it is no choice' );
	}

	public function test_an_attribute_only_some_variations_carry_is_a_choice_with_the_ones_they_have(): void {
		$wheel = static fn( int $id, array $choices ): Variant => new Variant( Parts::of( array( 'wheel_diameter' => '54' ), $id ), 30, 4, 0, $choices );
		$looks = Looks::of( array( $wheel( 31, array( 'pa_hardness' => '99a' ) ), $wheel( 32, array() ), $wheel( 33, array( 'pa_hardness' => '101a' ) ) ) );
		self::assertSame( array( 'pa_hardness' => array( '99a', '101a' ) ), $looks[0]->choices() );
	}

	public function test_a_look_of_one_part_has_no_choices(): void {
		self::assertSame( array(), Looks::of( array( self::deck( 11, 11, '8', 1, 0, array( 'pa_colour' => 'red' ) ) ) )[0]->choices() );
	}

	public function test_narrowing_keeps_the_variations_with_one_of_the_values_chosen_in_each_choice(): void {
		$bolt = static fn( int $id, string $head, string $colour ): Variant => new Variant( Parts::of( array( 'bolt_length' => '1' ), $id ), 60, 7, 0, array( 'pa_head' => $head, 'pa_colour' => $colour ) );
		$look = Looks::of( array( $bolt( 61, 'allen', 'black' ), $bolt( 62, 'phillips', 'black' ), $bolt( 63, 'allen', 'silver' ), $bolt( 64, 'hex', 'silver' ) ) )[0];
		$ids  = static fn( array $chosen ): array => array_map( static fn( Part $part ): int => $part->id, $look->narrowed( $chosen ) );
		self::assertSame( array( 61, 62, 63, 64 ), $ids( array() ) );
		self::assertSame( array( 61, 63 ), $ids( array( 'pa_head' => array( 'allen' ) ) ) );
		self::assertSame( array( 61, 62, 63 ), $ids( array( 'pa_head' => array( 'allen', 'phillips' ) ) ), 'values in one choice widen' );
		self::assertSame( array( 63 ), $ids( array( 'pa_head' => array( 'allen' ), 'pa_colour' => array( 'silver' ) ) ), 'choices narrow each other' );
		self::assertSame( array( 61, 62, 63, 64 ), $ids( array( 'pa_grade' => array( 'eight' ) ) ), 'a choice the look does not have narrows nothing' );
		self::assertSame( array( 61, 62, 63, 64 ), $ids( array( 'pa_head' => array() ) ), 'a choice with nothing chosen narrows nothing' );
		self::assertSame( array(), $ids( array( 'pa_head' => array( 'torx' ) ) ), 'a value the look does not come in leaves nothing' );
	}

	public function test_the_sizes_a_look_comes_in_are_its_distinct_figures_smallest_first(): void {
		$look = Looks::of( array( self::deck( 13, 10, '8.5', 1 ), new Variant( Parts::of( array(), 15 ), 10, 1, 0, array() ), self::deck( 11, 10, '8', 1 ), self::deck( 14, 10, '8.5', 1 ), self::deck( 12, 10, '203.2mm', 1 ) ) )[0];
		self::assertSame( array( '8.0000', '8.5000' ), array_map( static fn( Measure $size ): string => $size->decimal(), $look->sizes( SpecKey::DeckWidth ) ), '203.2 mm is 8 in, and a variation with no width adds none' );
	}

	// The card names a look by what tells it from its product's other looks: "Mint" beside "Black" (D-273).
	public function test_a_look_is_named_by_the_attributes_that_tell_it_from_its_products_other_looks(): void {
		$looks = Looks::of(
			array(
				self::deck( 11, 10, '8', 1, 0, array( 'pa_colour' => 'mint', 'pa_shape' => 'popsicle' ) ),
				self::deck( 12, 10, '8.5', 1, 0, array( 'pa_colour' => 'mint', 'pa_shape' => 'popsicle' ) ),
				self::deck( 13, 10, '8', 2, 0, array( 'pa_colour' => 'black', 'pa_shape' => 'popsicle' ) ),
				self::deck( 21, 20, '8', 3, 0, array( 'pa_colour' => 'red' ) ),
			)
		);
		self::assertSame(
			array(
				'10:1:0' => array( 'pa_colour' => 'mint' ),
				'10:2:0' => array( 'pa_colour' => 'black' ),
				'20:3:0' => array(),
			),
			Looks::names( $looks ),
			'the shape is the product\'s, and a product with one look needs no name'
		);
	}

	public function test_a_look_told_apart_by_two_attributes_is_named_by_both(): void {
		$looks = Looks::of(
			array(
				self::deck( 11, 10, '8', 1, 0, array( 'pa_colour' => 'mint', 'pa_finish' => 'matte' ) ),
				self::deck( 12, 10, '8', 2, 0, array( 'pa_colour' => 'black', 'pa_finish' => 'gloss' ) ),
			)
		);
		self::assertSame(
			array(
				'10:1:0' => array( 'pa_colour' => 'mint', 'pa_finish' => 'matte' ),
				'10:2:0' => array( 'pa_colour' => 'black', 'pa_finish' => 'gloss' ),
			),
			Looks::names( $looks )
		);
	}

	public function test_a_value_that_differs_within_a_look_does_not_name_it(): void {
		$looks = Looks::of(
			array(
				self::deck( 11, 10, '8', 1, 0, array( 'pa_head' => 'allen', 'pa_colour' => 'mint' ) ),
				self::deck( 12, 10, '8', 1, 0, array( 'pa_head' => 'phillips', 'pa_colour' => 'mint' ) ),
				self::deck( 13, 10, '8', 2, 0, array( 'pa_head' => 'allen' ) ),
			)
		);
		self::assertSame(
			array(
				'10:1:0' => array( 'pa_colour' => 'mint' ),
				'10:2:0' => array( 'pa_head' => 'allen' ),
			),
			Looks::names( $looks ),
			'a choice you can\'t see is chosen after the pick, and a look missing an attribute another has is told by its own'
		);
	}
}
