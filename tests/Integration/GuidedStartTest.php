<?php
/**
 * The guided start, against a real catalogue.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuilderView;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\StateAnswer;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Product_Simple;

/**
 * #134: "Help me choose" before the first step. The answers give a deck width from the kit's width guide, wheel sizes
 * from its wheel guide, and up to three ready builds from parts in stock that pass the fit engine, each priced as the
 * cart prices it, with why in plain words. The answers live in the link alone, and `cbuild_guided_start` answers after
 * the settings.
 */
final class GuidedStartTest extends IntegrationTestCase {

	private const string BASE = 'https://example.org/build/';

	/**
	 * The kit.
	 *
	 * @var int
	 */
	private int $kit;

	/**
	 * The parts, by a name of the test's own.
	 *
	 * @var array<string, int>
	 */
	private array $parts = array();

	/**
	 * The kit's slots, each with its category.
	 *
	 * @var array<string, mixed>
	 */
	private array $slots = array();

	/**
	 * Each slot's category.
	 *
	 * @var array<string, int>
	 */
	private array $categories = array();

	public function set_up(): void {
		parent::set_up();
		$slots = KitDocuments::slots();
		foreach ( $slots['slots'] as $at => $slot ) {
			$this->categories[ $slot['key'] ] = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
			$slots['slots'][ $at ]['sources'] = array( 'categories' => array( $this->categories[ $slot['key'] ] ) );
		}
		$this->slots = $slots;
		$deck        = array(
			'deck_length'  => '32.0000',
			'hole_pattern' => 'new_school',
		);
		$truck = array(
			'baseplate'     => 'new_school',
			'max_wheel'     => '56.0000',
			'axle_diameter' => '8.0000',
			'set_size'      => '2',
		);
		$this->part( 'deck 8.0', 'deck', '60', array( 'deck_width' => '8.0000' ) + $deck );
		$this->part( 'deck 8.25', 'deck', '65', array( 'deck_width' => '8.2500' ) + $deck );
		$this->part( 'deck 8.25 dear', 'deck', '85', array( 'deck_width' => '8.2500' ) + $deck );
		$this->part( 'deck 8.25 sold out', 'deck', '40', array( 'deck_width' => '8.2500' ) + $deck, false );
		$this->part( 'deck 8.5', 'deck', '70', array( 'deck_width' => '8.5000' ) + $deck );
		$this->part( 'truck 8.25', 'trucks', '50', array( 'axle_width' => '8.2500', 'deck_min' => '8.0000', 'deck_max' => '8.3750' ) + $truck );
		$this->part( 'truck 8.25 dear', 'trucks', '90', array( 'axle_width' => '8.2500', 'deck_min' => '8.0000', 'deck_max' => '8.3750' ) + $truck );
		$this->part( 'truck 8.5', 'trucks', '55', array( 'axle_width' => '8.5000', 'deck_min' => '8.3750', 'deck_max' => '8.6000' ) + $truck );
		// The cheapest wheels in stock, smaller than any riding style's: a build at a style's sizes never takes them.
		$this->part( 'wheels 48', 'wheels', '25', array( 'wheel_diameter' => '48.0000', 'set_size' => '4' ) );
		$this->part( 'wheels 52 sold out', 'wheels', '20', array( 'wheel_diameter' => '52.0000', 'set_size' => '4' ), false );
		$this->part( 'wheels 52', 'wheels', '30', array( 'wheel_diameter' => '52.0000', 'set_size' => '4' ) );
		$this->part( 'wheels 53', 'wheels', '40', array( 'wheel_diameter' => '53.0000', 'set_size' => '4' ) );
		$this->part( 'wheels 54', 'wheels', '50', array( 'wheel_diameter' => '54.0000', 'set_size' => '4' ) );
		$this->part( 'wheels 60', 'wheels', '45', array( 'wheel_diameter' => '60.0000', 'set_size' => '4' ) );
		$this->part( 'riser 1/4', 'risers', '6', array( 'riser_thickness' => '0.2500', 'set_size' => '2' ) );
		$this->part( 'riser 1/2', 'risers', '8', array( 'riser_thickness' => '0.5000', 'set_size' => '2' ) );
		$this->part( 'bearings', 'bearings', '15', array( 'bearing_bore' => '8.0000', 'set_size' => '8' ) );
		$this->part( 'bolts 1', 'hardware', '4', array( 'bolt_length' => '1.0000', 'set_size' => '8' ) );
		$this->part( 'bolts 1.25', 'hardware', '5', array( 'bolt_length' => '1.2500', 'set_size' => '8' ) );
		$this->part( 'bolts 1.5', 'hardware', '5', array( 'bolt_length' => '1.5000', 'set_size' => '8' ) );
		$this->part( 'grip', 'grip', '8', array( 'grip_width' => '9.0000', 'grip_length' => '33.0000' ) );
		$this->part( 'grip dear', 'grip', '14', array( 'grip_width' => '9.0000', 'grip_length' => '33.0000' ) );
		$this->kit = KitFactory::kit(
			'Complete',
			array(
				Options::META_KIT_SLOTS   => $this->slots,
				Options::META_KIT_PRESETS => array(
					'schema_version' => 1,
					'presets'        => array(),
				),
			)
		)->get_id();
	}

	public function tear_down(): void {
		remove_all_filters( 'cbuild_guided_start' );
		remove_all_filters( 'cbuild_help_me_choose' );
		parent::tear_down();
	}

	/**
	 * A published part in its slot's category, indexed.
	 *
	 * @param string                $name     The test's name for it, and its own.
	 * @param string                $slot     Its slot.
	 * @param string                $price    Its price.
	 * @param array<string, string> $specs    Its specs.
	 * @param bool                  $in_stock Whether it is in stock.
	 */
	private function part( string $name, string $slot, string $price, array $specs, bool $in_stock = true ): void {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->set_stock_status( $in_stock ? 'instock' : 'outofstock' );
		$product->update_meta_data( Options::META_SPECS, $specs );
		$product->save();
		wp_set_object_terms( $product->get_id(), array( $this->categories[ $slot ] ), 'product_cat' );
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		$this->parts[ $name ] = $product->get_id();
	}

	/**
	 * What the builder shows at an address.
	 *
	 * @param array<string, mixed> $sent     The build in the address.
	 * @param Builder|null         $builder  The builder, this release's unless given.
	 * @return array{state: State, answer: array<string, mixed>, view: array<string, mixed>}
	 */
	private function show( array $sent, ?Builder $builder = null ): array {
		$shown = ( $builder ?? $this->service( Builder::class ) )->show( $this->kit, BuildLink::read( $sent ), self::BASE );
		self::assertNotNull( $shown );
		return $shown;
	}

	/**
	 * The guide's view for some answers.
	 *
	 * @param array<string, string> $answers The answers.
	 * @return array<string, mixed>
	 */
	private function guide( array $answers ): array {
		$guide = $this->show( array( '_guide' => $answers + array( 'asked' => '1' ) ) )['view']['guide'];
		self::assertIsArray( $guide );
		return $guide;
	}

	/**
	 * Each tile's picks, by slot, with the test's names for the parts.
	 *
	 * @param array<string, mixed> $guide The guide's view.
	 * @return array<string, array<string, string>>
	 */
	private function builds( array $guide ): array {
		$names  = array_flip( $this->parts );
		$builds = array();
		foreach ( (array) $guide['tiles'] as $tile ) {
			$picks = array();
			foreach ( (array) $tile['fields'] as $field ) {
				if ( 1 === preg_match( '/\Acbuild\[([a-z]+)\]\z/', (string) $field['name'], $slot ) && 'step' !== $slot[1] ) {
					$picks[ $slot[1] ] = $names[ (int) $field['value'] ] ?? (string) $field['value'];
				}
			}
			$builds[ (string) $tile['name'] ] = $picks;
		}
		return $builds;
	}

	/**
	 * The reasons a guide gives.
	 *
	 * @param array<string, mixed> $guide The guide's view.
	 * @return list<string>
	 */
	private static function reasons( array $guide ): array {
		return array_map( static fn( array $reason ): string => (string) $reason['text'], array_values( (array) $guide['reasons'] ) );
	}

	public function test_the_answers_give_three_builds_at_the_guides_width_and_wheel_sizes_from_parts_in_stock(): void {
		$guide = $this->guide(
			array(
				'shoe'  => '3',
				'style' => 'street',
			)
		);
		self::assertTrue( $guide['shown'] );
		self::assertTrue( $guide['asked'] );
		self::assertSame(
			array(
				'Budget build'    => array( 'deck' => 'deck 8.25', 'trucks' => 'truck 8.25', 'wheels' => 'wheels 52', 'bearings' => 'bearings', 'hardware' => 'bolts 1', 'grip' => 'grip' ),
				'Mid-range build' => array( 'deck' => 'deck 8.25', 'trucks' => 'truck 8.25', 'wheels' => 'wheels 53', 'bearings' => 'bearings', 'hardware' => 'bolts 1', 'grip' => 'grip' ),
				'High-end build'   => array( 'deck' => 'deck 8.25 dear', 'trucks' => 'truck 8.25 dear', 'wheels' => 'wheels 54', 'bearings' => 'bearings', 'hardware' => 'bolts 1', 'grip' => 'grip dear' ),
			),
			$this->builds( $guide ),
			'9.5 and larger rides 8.25 in and wider: the 8.25 in decks in stock, never the sold-out one; street\'s 50 to 54 mm wheels, never the sold-out set'
		);
		self::assertSame(
			array(
				'Deck width 8.25″, for shoe size 9.5 and larger.',
				'Wheels of 50 mm to 54 mm suit street skating.',
				'3 builds fit and are in stock.',
			),
			array_map( static fn( string $text ): string => str_replace( "\u{00A0}", ' ', $text ), self::reasons( $guide ) )
		);
		self::assertSame( 'Help me choose', $this->show( array() )['view']['guide']['summary'] );
		self::assertSame( 'Change your answers', $guide['summary'] );
		self::assertFalse( $guide['hasNone'] );
		self::assertTrue( $guide['width']['shown'] );
		self::assertSame( self::BASE . '?' . http_build_query( array( 'cbuild' => array( '_width' => '8.25in' ) ) ), $guide['width']['url'], 'build your own at that width' );
		self::assertSame( self::BASE, $guide['skip']['url'] );
	}

	public function test_each_build_opens_at_the_review_whole_fitting_and_at_the_total_its_tile_says(): void {
		// A fee for assembly, which builds start with, and the shop's build discount: the tile's total is the cart's, not
		// the sum of its parts' prices.
		update_option( Options::SETTINGS, array( Options::SETTING_BUILD_DISCOUNT => '10' ) );
		$this->kit = KitFactory::kit(
			'Assembled',
			array(
				Options::META_KIT_SLOTS    => $this->slots,
				Options::META_KIT_ASSEMBLY => array( 'fee' => '20' ) + KitDocuments::assembly(),
			)
		)->get_id();
		$guide = $this->guide(
			array(
				'shoe'  => '3',
				'style' => 'street',
			)
		);
		self::assertCount( 3, $guide['tiles'] );
		foreach ( (array) $guide['tiles'] as $tile ) {
			$sent = array();
			foreach ( (array) $tile['fields'] as $field ) {
				parse_str( (string) $field['name'] . '=' . rawurlencode( (string) $field['value'] ), $one );
				$sent = array_replace_recursive( $sent, (array) ( $one['cbuild'] ?? array() ) );
			}
			$shown = $this->show( $sent );
			self::assertSame( State::REVIEW, $shown['state']->step, (string) $tile['name'] );
			self::assertTrue( $shown['state']->validation->isValid(), (string) $tile['name'] );
			self::assertTrue( $shown['state']->ready(), (string) $tile['name'] );
			self::assertSame( $tile['total'], $shown['answer']['total']['total']['text'], (string) $tile['name'] . ': the cart\'s price' );
			self::assertTrue( $tile['hasTotal'] );
		}
	}

	public function test_every_answer_left_blank_gives_builds_of_any_width_and_any_wheel(): void {
		$guide = $this->guide( array() );
		self::assertSame( array( 'Budget build', 'Mid-range build', 'High-end build' ), array_keys( $this->builds( $guide ) ) );
		self::assertSame( 'deck 8.0', $this->builds( $guide )['Budget build']['deck'], 'the cheapest deck in stock, whatever its width' );
		self::assertSame( array( '3 builds fit and are in stock.' ), self::reasons( $guide ), 'no width or wheel to explain' );
		self::assertFalse( $guide['width']['shown'] );
	}

	public function test_wheels_over_the_trucks_limit_bring_the_riser_the_kit_asks_for(): void {
		$builds = $this->builds( $this->guide( array( 'style' => 'cruising' ) ) );
		self::assertNotSame( array(), $builds );
		foreach ( $builds as $name => $picks ) {
			self::assertSame( 'wheels 60', $picks['wheels'], $name );
			self::assertSame( 'riser 1/2', $picks['risers'] ?? null, $name . ': 60 mm is 4 mm over a 56 mm limit, the kit\'s half inch' );
			self::assertSame( 'bolts 1.5', $picks['hardware'], $name . ': the usual inch and the riser' );
		}
	}

	public function test_a_budget_keeps_the_builds_within_it_or_the_closest_said_to_be_over(): void {
		$all    = $this->guide( array( 'shoe' => '3', 'style' => 'street' ) );
		$totals = array_map( static fn( array $tile ): string => (string) $tile['total'], array_values( (array) $all['tiles'] ) );
		// The budget build is $65 + $50 + $30 + $15 + $4 + $8 = $172.
		self::assertSame( '$172.00', html_entity_decode( wp_strip_all_tags( $totals[0] ) ) );
		// The mid-range build is $65 + $50 + $40 + $15 + $4 + $8 = $182; a budget there keeps it beside the cheaper one.
		self::assertSame( '$182.00', html_entity_decode( wp_strip_all_tags( $totals[1] ) ) );
		$within = $this->guide( array( 'shoe' => '3', 'style' => 'street', 'budget' => '182' ) );
		self::assertSame( array( 'Budget build', 'Mid-range build' ), array_keys( $this->builds( $within ) ), 'a total at the budget is within it' );
		self::assertContains( 'Every build here is within your budget.', self::reasons( $within ) );
		self::assertSame( array( array(), array() ), array_column( $within['tiles'], 'notes' ) );
		$over = $this->guide( array( 'shoe' => '3', 'style' => 'street', 'budget' => '150' ) );
		self::assertSame( array( 'Budget build' ), array_keys( $this->builds( $over ) ), 'none within: the closest alone' );
		self::assertContains( 'No build comes within your budget. The closest is ' . $totals[0] . '.', self::reasons( $over ) );
		// F40 (#163): the count is of every build that fits, not of the closest one shown.
		self::assertContains( sprintf( '%d builds fit and are in stock.', count( $totals ) ), self::reasons( $over ) );
		self::assertSame( 'Over your budget by $22.00.', html_entity_decode( wp_strip_all_tags( (string) $over['tiles'][0]['notes'][0]['text'] ) ) );
		self::assertSame( '150', $over['budget']['value'], 'the answer stays in its field' );
	}

	// The auditor's 1.3.0 should-fix 4 (D-433): a budget typed as shoppers type amounts was dropped with no word.
	public function test_a_budget_typed_with_a_sign_separators_or_cents_is_read_and_one_that_is_no_amount_says_so(): void {
		$answers = array(
			'shoe'  => '3',
			'style' => 'street',
		);
		$plain   = $this->guide( $answers + array( 'budget' => '150' ) );
		foreach ( array( '$150', '150.00', '149.50', ' 150 ' ) as $typed ) {
			$guide = $this->guide( $answers + array( 'budget' => $typed ) );
			self::assertSame( self::reasons( $plain ), self::reasons( $guide ), $typed . ' reads as 150' );
			self::assertSame( '150', $guide['budget']['value'], $typed . ': the field shows the amount read' );
		}
		$thousand = $this->guide( $answers + array( 'budget' => '$1,000' ) );
		self::assertContains( 'Every build here is within your budget.', self::reasons( $thousand ), 'a thousand, with its comma' );
		self::assertSame( '1000', $thousand['budget']['value'] );
		self::assertCount( 3, $thousand['tiles'] );

		$all = $this->guide( $answers );
		foreach ( array( 'about 150', '150-200', '1.2.3' ) as $typed ) {
			$guide = $this->guide( $answers + array( 'budget' => $typed ) );
			self::assertContains( 'Your budget was left out: type an amount, such as 150.', self::reasons( $guide ), $typed . ': said, with the fix' );
			self::assertSame( array_column( $all['tiles'], 'name' ), array_column( $guide['tiles'], 'name' ), $typed . ': the builds of any budget' );
			self::assertSame( '', $guide['budget']['value'], $typed );
		}
		self::assertNotContains( 'Your budget was left out: type an amount, such as 150.', self::reasons( $all ), 'none typed: nothing said' );
	}

	public function test_a_shoe_size_with_no_deck_in_stock_says_so_and_builds_at_any_width(): void {
		$guide = $this->guide( array( 'shoe' => '0' ) );
		self::assertSame( 'No deck for shoe size 6.5 or smaller is in stock right now, so the builds take any width.', self::reasons( $guide )[0] );
		self::assertNotSame( array(), $guide['tiles'] );
		self::assertFalse( $guide['width']['shown'] );
	}

	public function test_answers_the_shop_cannot_build_say_so_with_skip_and_never_a_build_that_fails(): void {
		foreach ( $this->parts as $name => $id ) {
			if ( str_starts_with( $name, 'bearings' ) ) {
				$product = wc_get_product( $id );
				self::assertInstanceOf( \WC_Product::class, $product );
				$product->set_stock_status( 'outofstock' );
				$product->save();
				$this->service( SpecIndexer::class )->index( $id );
			}
		}
		$guide = $this->guide( array( 'style' => 'street' ) );
		self::assertSame( array(), $guide['tiles'] );
		self::assertTrue( $guide['hasNone'] );
		self::assertSame( 'The shop cannot make a build from these answers right now. Leave one blank, or skip to the builder.', $guide['none'] );
	}

	public function test_a_plugin_answers_after_the_settings_with_the_width_and_the_wheel_sizes(): void {
		$seen = array();
		add_filter(
			'cbuild_guided_start',
			static function ( array $recommended, array $answers, int $kit ) use ( &$seen ): array {
				$seen = array( $recommended, $answers, $kit );
				return array(
					'width'  => '8.5in',
					'wheels' => array(
						'from' => '58mm',
						'to'   => '',
					),
				);
			},
			10,
			3
		);
		$builds = $this->builds( $this->guide( array( 'shoe' => '3', 'style' => 'street', 'budget' => '400' ) ) );
		self::assertSame(
			array(
				array(
					'width'  => '8.25in',
					'wheels' => array(
						'from' => '50mm',
						'to'   => '54mm',
					),
				),
				array(
					'shoe'   => 3,
					'style'  => 'street',
					'budget' => 400,
				),
				$this->kit,
			),
			$seen,
			'the settings\' answer first'
		);
		self::assertSame( array( 'Budget build', 'High-end build' ), array_keys( $builds ), 'one deck at 8.5 in and one wheel from 58 mm, so two grips make two builds' );
		foreach ( $builds as $name => $picks ) {
			self::assertSame( array( 'deck 8.5', 'truck 8.5', 'wheels 60', 'riser 1/2' ), array( $picks['deck'], $picks['trucks'], $picks['wheels'], $picks['risers'] ?? '' ), $name );
		}
		remove_all_filters( 'cbuild_guided_start' );
		add_filter( 'cbuild_guided_start', static fn(): array => array( 'width' => '8.25 inches', 'wheels' => 'none' ) );
		self::assertSame( 'deck 8.0', $this->builds( $this->guide( array( 'shoe' => '3' ) ) )['Budget build']['deck'], 'an answer that does not read is any width' );
	}

	public function test_the_answers_live_in_the_link_of_the_start_alone(): void {
		$shown = $this->show(
			array(
				'_guide' => array(
					'style'  => 'bowl',
					'budget' => '200',
					'asked'  => '1',
				),
			)
		);
		self::assertSame( self::BASE . '?' . http_build_query( array( 'cbuild' => array( 'step' => 'deck', '_guide' => array( 'style' => 'bowl', 'budget' => '200', 'asked' => '1' ) ) ) ), $shown['view']['url'], 'the start\'s address, the answers with it' );
		$picked = $this->show(
			array(
				'deck'   => (string) $this->parts['deck 8.0'],
				'_guide' => array(
					'style' => 'bowl',
					'asked' => '1',
				),
			)
		);
		self::assertStringNotContainsString( '_guide', rawurldecode( (string) $picked['view']['url'] ), 'a build begun is the shopper\'s own' );
		self::assertFalse( $picked['view']['guide']['shown'] );
		self::assertFalse( $picked['view']['guide']['asked'] );
	}

	// A width pressed is the shopper's own answer to the deck step's question, so "Help me choose" steps aside and the
	// decks keep the first view (F14's counts, D-404). Its builds, once asked, stay with the width they bring.
	public function test_a_width_pressed_puts_the_questions_away_and_builds_asked_for_stay(): void {
		self::assertTrue( $this->show( array() )['view']['guide']['shown'], 'the start' );
		self::assertFalse( $this->show( array( '_width' => '8in' ) )['view']['guide']['shown'], 'a width pressed' );
		$asked = $this->show(
			array(
				'_width' => '8.25in',
				'_guide' => array(
					'shoe'  => '10',
					'asked' => '1',
				),
			)
		)['view']['guide'];
		self::assertTrue( $asked['shown'], 'the builds asked for' );
		self::assertTrue( $asked['asked'] );
	}

	public function test_the_questions_offer_the_kits_width_guide_and_none_where_it_is_hidden(): void {
		$guide = $this->show( array() )['view']['guide'];
		self::assertSame( 'Your shoe size (US)', $guide['shoe']['legend'] );
		self::assertSame( array( 'Not sure', '6.5 or smaller', '7 to 8', '8.5 to 9', '9.5 and larger' ), array_column( $guide['shoe']['choices'], 'label' ) );
		self::assertSame( array( 'Not sure', 'Street', 'Skatepark', 'Bowl and transition', 'Cruising' ), array_column( $guide['style']['choices'], 'label' ) );
		self::assertSame( 'Your budget ($)', $guide['budget']['label'] );
		$checked = $this->guide( array( 'shoe' => '2', 'style' => 'park' ) );
		self::assertSame( array( false, false, false, true, false ), array_column( $checked['shoe']['choices'], 'checked' ) );
		self::assertSame( array( false, false, true, false, false ), array_column( $checked['style']['choices'], 'checked' ) );
		$this->kit = KitFactory::kit(
			'Hidden guide',
			array(
				Options::META_KIT_SLOTS    => $this->slots,
				Options::META_KIT_SETTINGS => array(
					'schema_version' => 1,
					'width_guide'    => array( 'shown' => false ),
				),
			)
		)->get_id();
		self::assertFalse( $this->show( array() )['view']['guide']['shoe']['shown'] );
		self::assertSame( array( '3 builds fit and are in stock.' ), self::reasons( $this->guide( array( 'shoe' => '3' ) ) ), 'a shoe answer with no guide is no answer' );
	}

	/**
	 * The kit with these settings, its slots the fixture's.
	 *
	 * @param array<string, mixed> $settings The settings document's keys past its version.
	 */
	private function settled( array $settings ): void {
		$this->kit = KitFactory::kit(
			'Settled',
			array(
				Options::META_KIT_SLOTS    => $this->slots,
				Options::META_KIT_SETTINGS => array( 'schema_version' => 1 ) + $settings,
			)
		)->get_id();
	}

	// D-409: the Build tab's Help me choose. Off, the start offers no questions and a link's answers recommend no build;
	// a kit whose settings were saved before 1.3.0 has no key and offers it. `cbuild_help_me_choose` answers last.
	public function test_help_me_choose_off_offers_nothing_and_a_kit_saved_without_the_key_offers_it(): void {
		$asked = array( '_guide' => array( 'style' => 'street', 'asked' => '1' ) );
		$this->settled( array( 'start_button' => true ) );
		$guide = $this->show( array() )['view']['guide'];
		self::assertTrue( $guide['offered'], 'saved before 1.3.0' );
		self::assertTrue( $guide['shown'] );
		self::assertCount( 3, $this->show( $asked )['view']['guide']['tiles'], 'and its builds' );

		$this->settled( array( 'guide' => false ) );
		foreach ( array(
			'at the start'      => array(),
			'with answers sent' => $asked,
		) as $case => $sent ) {
			$shown = $this->show( $sent );
			$guide = $shown['view']['guide'];
			self::assertFalse( $guide['offered'], $case );
			self::assertFalse( $guide['shown'], $case );
			self::assertFalse( $guide['asked'], $case . ': no builds asked' );
			self::assertSame( array(), $guide['tiles'], $case );
			self::assertSame( 'deck', $shown['state']->step, $case . ': the first step' );
		}

		$own = $this->kit;
		add_filter( 'cbuild_help_me_choose', static fn( mixed $offered, int $kit ): bool => $kit === $own && false === $offered, 10, 2 );
		self::assertTrue( $this->show( array() )['view']['guide']['shown'], 'a plugin answers last, with the kit\'s setting and its ID' );
		self::assertCount( 3, $this->show( $asked )['view']['guide']['tiles'] );
		remove_all_filters( 'cbuild_help_me_choose' );
		$this->settled( array() );
		add_filter( 'cbuild_help_me_choose', static fn(): string => 'yes' );
		self::assertFalse( $this->show( array() )['view']['guide']['shown'], 'anything but true is off' );
	}

	public function test_a_builder_an_earlier_release_built_asks_the_questions_and_shows_no_builds(): void {
		$earlier = new Builder( $this->service( BuildState::class ), $this->service( StateAnswer::class ), $this->service( BuilderView::class ) );
		$guide   = $this->show( array( '_guide' => array( 'style' => 'street', 'asked' => '1' ) ), $earlier )['view']['guide'];
		self::assertTrue( $guide['shown'] );
		self::assertFalse( $guide['asked'] );
		self::assertSame( array(), $guide['tiles'] );
	}
}
