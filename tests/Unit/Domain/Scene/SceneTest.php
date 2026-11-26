<?php
/**
 * Scene.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Domain\Scene;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Part;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Picks;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Layout;
use CreativeBranding\SkateboardBuilder\Domain\Picture\PictureSettings;
use CreativeBranding\SkateboardBuilder\Domain\Scene\Scene;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Parts;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * The 3D view's scene from the picks' specs (#139): every part's size and place in whole micrometres from the
 * trucks' midpoint on the deck's top face, x toward the nose, y across and z up, with the usual values it assumed. The
 * cases are the spec's accuracy tests: the deck's sizes within 0.5 mm of its specs, the trucks' holes the deck's holes
 * for every pattern, the wheels on the axles' ends, the outline at the shape's extremes, and every usual value applied
 * and named.
 */
final class SceneTest extends TestCase {

	/**
	 * How far a size may be from its spec: 0.5 mm.
	 */
	private const int HALF_MM = 500;

	/**
	 * An 8.25 in deck, 32 in long, with a 14.25 in wheelbase and the 3D view's sizes, and other specs if wanted.
	 *
	 * @param array<string, string|int> $specs Specs to add or replace.
	 */
	private static function deck( array $specs = array() ): Part {
		return Parts::of(
			$specs + array(
				'deck_width'    => '8.25',
				'deck_length'   => '32',
				'wheelbase'     => '14.25',
				'nose_length'   => '7',
				'tail_length'   => '6.5',
				'concave_depth' => '0.375',
				'kick_angle'    => 18,
			),
			101
		);
	}

	/**
	 * A whole board whose parts give every size.
	 *
	 * @param array<string, Part> $parts Parts to add or replace, by slot.
	 */
	private static function whole( array $parts = array() ): Picks {
		return Parts::picks(
			$parts + array(
				SlotKey::DECK     => self::deck(),
				SlotKey::TRUCKS   => Parts::indy( '144', array( 'truck_height' => '52' ) ),
				SlotKey::WHEELS   => Parts::of( array( 'wheel_diameter' => '54', 'wheel_width' => '32' ), 303 ),
				SlotKey::RISERS   => Parts::of( array( 'riser_thickness' => '0.125' ), 404 ),
				SlotKey::HARDWARE => Parts::of( array( 'bolt_length' => '1' ), 505 ),
				SlotKey::GRIP     => Parts::of( array( 'grip_width' => '9', 'grip_length' => '33' ), 606 ),
			)
		);
	}

	/**
	 * The scene of the picks, which has one.
	 *
	 * @param Picks                $picks    The picks.
	 * @param PictureSettings|null $settings The kit's picture settings.
	 * @return array<string, mixed>
	 */
	private static function scene( Picks $picks, ?PictureSettings $settings = null ): array {
		$scene = Scene::of( $picks, $settings );
		self::assertNotNull( $scene );
		return $scene;
	}

	/**
	 * One of the scene's parts, which it has.
	 *
	 * @param array<string, mixed> $scene The scene.
	 * @param string               $key   The part's key.
	 * @return array<string, mixed>
	 */
	private static function part( array $scene, string $key ): array {
		$part = $scene[ $key ];
		self::assertIsArray( $part );
		return $part;
	}

	/**
	 * The deck's holes.
	 *
	 * @param array<string, mixed> $scene The scene.
	 * @return array<string, mixed>
	 */
	private static function holes( array $scene ): array {
		$holes = self::part( $scene, 'deck' )['holes'];
		self::assertIsArray( $holes );
		return $holes;
	}

	public function test_there_is_no_scene_before_a_deck_with_a_width_is_picked(): void {
		self::assertNull( Scene::of( Parts::picks( array( SlotKey::TRUCKS => Parts::indy( '144' ) ) ) ) );
		self::assertNull( Scene::of( Parts::picks( array( SlotKey::DECK => Parts::of( array( 'deck_length' => '32' ), 101 ) ) ) ) );
	}

	/**
	 * Decks across the range sold, by width, length and wheelbase in inches.
	 *
	 * @return iterable<string, array{string, string, string}>
	 */
	public static function decks(): iterable {
		yield 'a mini' => array( '7', '28', '13' );
		yield 'a narrow street deck' => array( '7.5', '31', '14' );
		yield 'the usual street deck' => array( '8.25', '32', '14.25' );
		yield 'a sixteenth over' => array( '8.0625', '31.875', '14.125' );
		yield 'a wide deck' => array( '9', '32.5', '14.5' );
		yield 'an old school shape' => array( '10.5', '33', '15' );
	}

	#[DataProvider( 'decks' )]
	public function test_the_decks_width_length_and_wheelbase_are_within_half_a_millimetre_of_its_specs( string $width, string $length, string $wheelbase ): void {
		$deck = self::part( self::scene( Parts::picks( array( SlotKey::DECK => self::deck( array( 'deck_width' => $width, 'deck_length' => $length, 'wheelbase' => $wheelbase ) ) ) ) ), 'deck' );

		self::assertEqualsWithDelta( (float) $width * 25400, $deck['width'], self::HALF_MM, 'width' );
		self::assertEqualsWithDelta( (float) $length * 25400, $deck['length'], self::HALF_MM, 'length' );
		self::assertEqualsWithDelta( (float) $wheelbase * 25400, $deck['wheelbase'], self::HALF_MM, 'wheelbase' );
		$along = array_column( self::holes( self::scene( Parts::picks( array( SlotKey::DECK => self::deck( array( 'deck_width' => $width, 'deck_length' => $length, 'wheelbase' => $wheelbase ) ) ) ) ) )['points'], 0 );
		// The wheelbase is measured between the trucks' inner holes, as decks are.
		self::assertEqualsWithDelta( (float) $wheelbase * 25400, $along[2] - $along[4], self::HALF_MM, 'the inner rows are the wheelbase apart' );
	}

	public function test_the_usual_deck_is_measured_to_the_micrometre(): void {
		$scene = self::scene( self::whole() );

		self::assertSame( array(), $scene['assumed'] );
		self::assertSame(
			array(
				'part'      => 101,
				'width'     => 209550,
				'length'    => 812800,
				'wheelbase' => 361950,
				'nose'      => 177800,
				'tail'      => 165100,
				'concave'   => 9525,
				'kick'      => 180,
				'thickness' => 11000,
				'plies'     => 7,
				'holes'     => array(
					'pattern' => 'new_school',
					'spacing' => 53975,
					'columns' => 41275,
					// From the nose: its outer row, its inner row, then the tail's inner row and its outer row, each row's
					// left first. The inner rows are 14.25 in apart, and each row 2.125 in from its pair.
					'points'  => array(
						array( 234950, -20638 ),
						array( 234950, 20638 ),
						array( 180975, -20638 ),
						array( 180975, 20638 ),
						array( -180975, -20638 ),
						array( -180975, 20638 ),
						array( -234950, -20638 ),
						array( -234950, 20638 ),
					),
				),
				'texture'   => array(
					'top'    => null,
					'bottom' => null,
				),
			),
			$scene['deck']
		);
	}

	/**
	 * The hole patterns: the deck's, the trucks' baseplate, and the pattern and spacing they meet on.
	 *
	 * @return iterable<string, array{string, string|null, string, int}>
	 */
	public static function patterns(): iterable {
		yield 'new school' => array( 'new_school', 'new_school', 'new_school', 53975 );
		yield 'old school' => array( 'old_school', 'old_school', 'old_school', 63500 );
		yield 'both, with trucks for both' => array( 'both', 'both', 'new_school', 53975 );
		yield 'both, with new school trucks' => array( 'both', 'new_school', 'new_school', 53975 );
		yield 'both, with old school trucks' => array( 'both', 'old_school', 'old_school', 63500 );
		yield 'both, with trucks that say none' => array( 'both', null, 'new_school', 53975 );
	}

	#[DataProvider( 'patterns' )]
	public function test_the_trucks_holes_are_the_decks_holes( string $deck, ?string $baseplate, string $pattern, int $spacing ): void {
		$trucks = Parts::indy( '144', null === $baseplate ? array() : array( 'baseplate' => $baseplate ) );
		$scene  = self::scene( self::whole( array( SlotKey::DECK => self::deck( array( 'hole_pattern' => $deck ) ), SlotKey::TRUCKS => $trucks ) ) );
		$holes  = self::holes( $scene );

		self::assertSame( $holes['points'], self::part( $scene, 'trucks' )['holes'], 'holes meet holes' );
		self::assertSame( $holes['points'], self::part( $scene, 'bolts' )['points'], 'a bolt in every hole' );
		self::assertSame( $pattern, $holes['pattern'] );
		self::assertSame( $spacing, $holes['spacing'] );
		self::assertCount( 8, $holes['points'] );
		$along = array_column( $holes['points'], 0 );
		self::assertSame( $spacing, $along[0] - $along[2], 'the nose truck\'s rows are a spacing apart' );
		self::assertSame( $spacing, $along[4] - $along[6], 'the tail truck\'s rows are a spacing apart' );
		self::assertSame( 361950, $along[2] - $along[4], 'the inner rows are the wheelbase apart' );
		self::assertSame( array( -20638, 20638, -20638, 20638, -20638, 20638, -20638, 20638 ), array_column( $holes['points'], 1 ), 'the columns are 1.625 in apart' );
	}

	public function test_the_trucks_sit_on_the_holes_by_their_axle_and_height(): void {
		self::assertSame(
			array(
				'part'      => 144,
				'axle'      => 209550,
				'height'    => 52000,
				// Two thirds of the axle, as an Independent 144's hanger is of its 8.25 in axle.
				'hanger'    => 139700,
				// The 2.125 in between its rows and 0.875 in past them; the 1.625 in between its columns and the same.
				'baseplate' => array(
					'length' => 76200,
					'width'  => 63500,
				),
				'holes'     => self::holes( self::scene( self::whole() ) )['points'],
				'color'     => null,
			),
			self::part( self::scene( self::whole() ), 'trucks' )
		);
	}

	/**
	 * Wheels by their widths in millimetres, and trucks by their size number.
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function wheels(): iterable {
		yield 'a narrow wheel' => array( '30', '139' );
		yield 'the usual wheel' => array( '32', '144' );
		yield 'a wide wheel' => array( '36.5', '149' );
	}

	#[DataProvider( 'wheels' )]
	public function test_the_wheels_are_on_the_axles_ends_each_by_its_own_width( string $width, string $size ): void {
		$scene  = self::scene( self::whole( array( SlotKey::TRUCKS => Parts::indy( $size, array( 'truck_height' => '52' ) ), SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54', 'wheel_width' => $width ), 303 ) ) ) );
		$wheels = self::part( $scene, 'wheels' );
		$axle   = self::part( $scene, 'trucks' )['axle'];
		$wide   = (int) round( (float) $width * 1000 );

		self::assertSame( $wide, $wheels['width'] );
		self::assertSame( intdiv( $wide, 2 ), $wheels['contact'], 'the contact band is half the width' );
		self::assertSame( 54000, $wheels['diameter'] );
		self::assertIsArray( $wheels['places'] );
		self::assertCount( 4, $wheels['places'] );
		// Half the wheelbase and half a spacing out: the truck's middle, between its rows.
		$out = array( 207963, 207963, -207963, -207963 );
		// Under the deck, the 1/8 in riser and the trucks' 52 mm from baseplate to axle.
		$z = -( 11000 + 3175 + 52000 );
		foreach ( $wheels['places'] as $at => $place ) {
			self::assertIsArray( $place );
			self::assertSame( $out[ $at ], $place[0], 'wheel ' . $at . ' is on its axle' );
			self::assertEqualsWithDelta( $axle / 2, abs( $place[1] ) + $wide / 2, 1, 'wheel ' . $at . '\'s outer face is at the axle\'s end' );
			self::assertSame( $z, $place[2], 'wheel ' . $at . ' is on the axle\'s centre' );
		}
		self::assertSame( array( -1, 1, -1, 1 ), array_map( static fn( array $place ): int => $place[1] <=> 0, $wheels['places'] ), 'each pair\'s left first' );
	}

	public function test_without_risers_the_axle_is_the_trucks_height_under_the_deck(): void {
		$picks = self::whole();
		$scene = self::scene( $picks->without( SlotKey::RISERS ) );

		self::assertNull( $scene['risers'] );
		self::assertSame( -63000, self::part( $scene, 'wheels' )['places'][0][2] );
	}

	/**
	 * Shapes at the extremes: width, length, wheelbase, nose and tail in inches; the nose and tail the scene gives in
	 * micrometres; and the specs it assumed.
	 *
	 * @return iterable<string, array{string, string, string, string|null, string|null, int, int, list<string>}>
	 */
	public static function outlines(): iterable {
		// 26 in less 11.5 in and two 2.125 in spacings leaves 10.25 in.
		yield 'the narrowest and shortest, both kicks given' => array( '6.5', '26', '11.5', '5.25', '5', 133350, 127000, array() );
		// 36 in less 16 in and two spacings leaves 15.75 in.
		yield 'the widest and longest, both kicks given' => array( '10.5', '36', '16', '8.25', '7.5', 209550, 190500, array() );
		// 32 in less 14.25 in and two spacings leaves 13.5 in: the nose has a quarter inch more.
		yield 'neither given, the nose a little longer' => array( '8.25', '32', '14.25', null, null, 174625, 168275, array( 'nose_length', 'tail_length' ) );
		yield 'the nose given, the tail is the rest' => array( '8.25', '32', '14.25', '6.875', null, 174625, 168275, array( 'tail_length' ) );
		yield 'the tail given, the nose is the rest' => array( '8.25', '32', '14.25', null, '6.25', 184150, 158750, array( 'nose_length' ) );
		yield 'both given and more than the deck has, shared out in their proportion' => array( '8.25', '32', '14.25', '7', '7', 171450, 171450, array() );
		yield 'a nose longer than the deck has' => array( '8.25', '32', '14.25', '20', null, 342900, 0, array( 'tail_length' ) );
		yield 'a tail longer than the deck has' => array( '8.25', '32', '14.25', null, '20', 0, 342900, array( 'nose_length' ) );
		yield 'a wheelbase longer than the deck' => array( '8.25', '20', '19', '6', '6', 0, 0, array() );
		yield 'a wheelbase longer than the deck, neither given' => array( '8.25', '20', '19', null, null, 0, 0, array( 'nose_length', 'tail_length' ) );
	}

	/**
	 * The outline's extremes.
	 *
	 * @param list<string> $assumed The specs assumed.
	 */
	#[DataProvider( 'outlines' )]
	public function test_the_outline_runs_from_the_outer_holes_by_the_nose_and_tail_to_the_decks_length( string $width, string $length, string $wheelbase, ?string $nose, ?string $tail, int $noseWant, int $tailWant, array $assumed ): void {
		$specs = array(
			'deck_width'    => $width,
			'deck_length'   => $length,
			'wheelbase'     => $wheelbase,
			'concave_depth' => '0.375',
			'kick_angle'    => 18,
		);
		$specs += null === $nose ? array() : array( 'nose_length' => $nose );
		$specs += null === $tail ? array() : array( 'tail_length' => $tail );
		$scene = self::scene( Parts::picks( array( SlotKey::DECK => Parts::of( $specs, 101 ) ) ) );
		$deck  = self::part( $scene, 'deck' );
		$along = array_column( self::holes( $scene )['points'], 0 );
		$span  = $along[0] - $along[6];

		self::assertSame( $noseWant, $deck['nose'], 'nose' );
		self::assertSame( $tailWant, $deck['tail'], 'tail' );
		if ( $span <= $deck['length'] ) {
			self::assertSame( $deck['length'], $deck['nose'] + $deck['tail'] + $span, 'nose, tail and the outer holes\' span are the length' );
		}
		self::assertEqualsWithDelta( (float) $length * 25400, $deck['length'], self::HALF_MM, 'nose tip to tail tip' );
		self::assertEqualsWithDelta( (float) $width * 25400, $deck['width'], self::HALF_MM, 'the widest the outline goes' );
		self::assertSame( $assumed, $scene['assumed'] );
	}

	public function test_a_concave_and_a_kick_past_any_deck_are_held_to_the_most_a_deck_could_have(): void {
		$deck = self::part( self::scene( Parts::picks( array( SlotKey::DECK => self::deck( array( 'concave_depth' => '5', 'kick_angle' => 1000 ) ) ) ) ), 'deck' );

		self::assertSame( 52388, $deck['concave'], 'a quarter of the width' );
		self::assertSame( 450, $deck['kick'], '45 degrees' );
	}

	public function test_a_concave_and_a_kick_inside_the_limits_are_kept(): void {
		$deck = self::part( self::scene( Parts::picks( array( SlotKey::DECK => self::deck( array( 'concave_depth' => '2', 'kick_angle' => 45 ) ) ) ) ), 'deck' );

		self::assertSame( 50800, $deck['concave'], 'just under a quarter of the width' );
		self::assertSame( 450, $deck['kick'] );
	}

	public function test_every_usual_value_is_applied_and_named(): void {
		$scene = self::scene(
			Parts::picks(
				array(
					SlotKey::DECK     => Parts::of( array( 'deck_width' => '8.25' ), 101 ),
					SlotKey::TRUCKS   => Parts::of( array(), 202 ),
					SlotKey::WHEELS   => Parts::of( array(), 303 ),
					SlotKey::RISERS   => Parts::of( array(), 404 ),
					SlotKey::HARDWARE => Parts::of( array(), 505 ),
					SlotKey::GRIP     => Parts::of( array(), 606 ),
				)
			)
		);

		self::assertSame( array( 'wheelbase', 'deck_length', 'axle_width', 'wheel_diameter', 'nose_length', 'tail_length', 'concave_depth', 'kick_angle', 'truck_height', 'wheel_width', 'riser_thickness', 'bolt_length' ), $scene['assumed'] );
		$deck = self::part( $scene, 'deck' );
		// 32 in long, the 14.25 in wheelbase of a deck from 8 in, 0.45 in of concave and a 20 degree kick.
		self::assertSame( array( 812800, 361950, 174625, 168275, 11430, 200 ), array( $deck['length'], $deck['wheelbase'], $deck['nose'], $deck['tail'], $deck['concave'], $deck['kick'] ) );
		$trucks = self::part( $scene, 'trucks' );
		// The deck's width, and a high truck's 55 mm.
		self::assertSame( array( 202, 209550, 55000 ), array( $trucks['part'], $trucks['axle'], $trucks['height'] ) );
		$wheels = self::part( $scene, 'wheels' );
		// 54 mm, three fifths as wide, as the picture draws it.
		self::assertSame( array( 303, 54000, 32400, 16200 ), array( $wheels['part'], $wheels['diameter'], $wheels['width'], $wheels['contact'] ) );
		self::assertSame(
			array(
				'part'      => 404,
				'thickness' => 3175,
			),
			$scene['risers'],
			'a 1/8 in riser'
		);
		$bolts = self::part( $scene, 'bolts' );
		self::assertSame( array( 505, 25400 ), array( $bolts['part'], $bolts['length'] ), 'a 1 in bolt' );
		self::assertSame(
			array(
				'part'    => 606,
				'texture' => null,
			),
			$scene['grip']
		);
	}

	/**
	 * Truck height classes and the height each stands for in micrometres.
	 *
	 * @return iterable<string, array{string, int}>
	 */
	public static function heights(): iterable {
		yield 'low' => array( 'low', 49000 );
		yield 'mid' => array( 'mid', 52000 );
		yield 'high' => array( 'high', 55000 );
	}

	#[DataProvider( 'heights' )]
	public function test_a_truck_that_gives_only_its_class_stands_at_its_classs_usual_height( string $class, int $height ): void {
		$scene = self::scene( self::whole( array( SlotKey::TRUCKS => Parts::indy( '144', array( 'height_class' => $class ) ) ) ) );

		self::assertSame( $height, self::part( $scene, 'trucks' )['height'] );
		self::assertSame( array( 'truck_height' ), $scene['assumed'] );
	}

	public function test_wheels_without_trucks_hang_at_the_usual_height_on_an_axle_as_wide_as_the_deck(): void {
		$scene = self::scene( Parts::picks( array( SlotKey::DECK => self::deck(), SlotKey::WHEELS => Parts::of( array( 'wheel_diameter' => '54', 'wheel_width' => '32' ), 303 ) ) ) );

		self::assertNull( $scene['trucks'] );
		self::assertSame( array( 'axle_width', 'truck_height' ), $scene['assumed'] );
		$place = self::part( $scene, 'wheels' )['places'][1];
		self::assertSame( array( 207963, 88775, -66000 ), $place );
	}

	public function test_an_empty_slot_is_null(): void {
		$scene = self::scene( Parts::picks( array( SlotKey::DECK => self::deck() ) ) );

		self::assertSame( array( null, null, null, null, null ), array( $scene['trucks'], $scene['wheels'], $scene['risers'], $scene['bolts'], $scene['grip'] ) );
		self::assertSame( array(), $scene['assumed'] );
	}

	public function test_the_scene_and_the_picture_place_the_trucks_and_wheels_alike(): void {
		$picks  = self::whole();
		$scene  = self::scene( $picks );
		$layout = Layout::of( $picks );
		$trucks = array_values( array_filter( $layout->layers, static fn( $layer ): bool => SlotKey::TRUCKS === $layer->slot ) );
		$wheels = array_values( array_filter( $layout->layers, static fn( $layer ): bool => SlotKey::WHEELS === $layer->slot ) );
		$places = self::part( $scene, 'wheels' )['places'];
		self::assertIsArray( $places );

		// The picture is in ten-thousandths of a millimetre, the scene in micrometres.
		self::assertSame( 207963, (int) round( $trucks[0]->y / 10 ) );
		foreach ( $wheels as $at => $wheel ) {
			self::assertIsArray( $places[ $at ] );
			self::assertSame( array( (int) round( $wheel->y / 10 ), (int) round( $wheel->x / 10 ) ), array( $places[ $at ][0], $places[ $at ][1] ), 'wheel ' . $at );
			self::assertSame( self::part( $scene, 'wheels' )['width'], intdiv( $wheel->width, 10 ) );
		}
	}

	public function test_the_kits_anchor_places_the_trucks_on_a_deck_that_gives_no_wheelbase(): void {
		$settings = PictureSettings::fromDocument(
			array(
				'schema_version' => 1,
				'wheelbase'      => '15',
			)
		);
		$scene = self::scene( Parts::picks( array( SlotKey::DECK => self::deck( array( 'wheelbase' => '15' ) ) ) ) );
		$own   = self::scene( Parts::picks( array( SlotKey::DECK => Parts::of( array( 'deck_width' => '8.25', 'deck_length' => '32', 'nose_length' => '7', 'tail_length' => '6.5', 'concave_depth' => '0.375', 'kick_angle' => 18 ), 101 ) ) ), $settings );

		self::assertSame( self::holes( $scene )['points'], self::holes( $own )['points'] );
		self::assertSame( array( 'wheelbase' ), $own['assumed'] );
	}

	public function test_the_scene_names_the_sizes_a_deck_or_wheel_lacks_for_the_catalogue_check(): void {
		self::assertSame( array( SpecKey::NoseLength, SpecKey::TailLength, SpecKey::ConcaveDepth, SpecKey::KickAngle ), Scene::assumes( SlotKey::DECK, Parts::of( array( 'deck_width' => '8' ) )->specs ) );
		self::assertSame( array( SpecKey::TailLength ), Scene::assumes( SlotKey::DECK, Parts::of( array( 'nose_length' => '7', 'concave_depth' => '0.5', 'kick_angle' => 20 ) )->specs ) );
		self::assertSame( array(), Scene::assumes( SlotKey::DECK, self::deck()->specs ) );
		self::assertSame( array( SpecKey::WheelWidth ), Scene::assumes( SlotKey::WHEELS, Parts::of( array( 'wheel_diameter' => '54' ) )->specs ) );
		self::assertSame( array(), Scene::assumes( SlotKey::WHEELS, Parts::of( array( 'wheel_width' => '32' ) )->specs ) );
		self::assertSame( array(), Scene::assumes( SlotKey::TRUCKS, Parts::of( array() )->specs ), 'the rules name what a truck lacks' );
	}
}
