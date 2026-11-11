<?php
/**
 * What a shop says of a build's delivery.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\Delivery;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;

/**
 * Spec, The builder, The total: the arrival date and the shipping words each come from a filter the shop answers.
 */
final class DeliveryTest extends CartTestCase {

	public function tear_down(): void {
		remove_all_filters( 'cbuild_arrival_estimate' );
		remove_all_filters( 'cbuild_delivery_line' );
		parent::tear_down();
	}

	public function test_with_nobody_answering_there_is_nothing_to_show(): void {
		self::assertSame(
			array(
				'arrival' => '',
				'line'    => '',
			),
			$this->service( Delivery::class )->said( $this->kit, true, 19800, 2 )
		);
	}

	public function test_each_filter_is_given_the_build_and_its_answer_is_shown_as_plain_text(): void {
		$kit = wc_get_product( $this->kit );
		$kit->setDocument(
			Options::META_KIT_ASSEMBLY,
			array(
				'schema_version' => 1,
				'default_on'     => false,
				'fee'            => '15',
				'lead_time'      => 'Two business days',
				'returns_notice' => '',
			)
		);
		$kit->save();
		$given = array();
		add_filter(
			'cbuild_arrival_estimate',
			static function ( string $estimate, array $context ) use ( &$given ): string {
				$given[] = array( $estimate, $context );
				return ' Arrives by <strong>Thu, Nov 19</strong>, assembled ';
			},
			10,
			2
		);
		add_filter(
			'cbuild_delivery_line',
			static function ( string $line, array $context ) use ( &$given ): string {
				$given[] = array( $line, $context );
				return 'Spend $41.00 more for free shipping to Ontario.';
			},
			10,
			2
		);

		$context = array(
			'kit'       => $this->kit,
			'assembly'  => true,
			'lead_time' => 'Two business days',
			'total'     => 19800,
			'decimals'  => 2,
		);
		self::assertSame(
			array(
				'arrival' => 'Arrives by Thu, Nov 19, assembled',
				'line'    => 'Spend $41.00 more for free shipping to Ontario.',
			),
			$this->service( Delivery::class )->said( $this->kit, true, 19800, 2 )
		);
		self::assertSame( array( array( '', $context ), array( '', $context ) ), $given );

		$given = array();
		$this->service( Delivery::class )->said( $this->kit, false, 18300, 2 );
		self::assertSame( '', $given[0][1]['lead_time'], 'a build the shop does not assemble has no lead time' );
		self::assertFalse( $given[0][1]['assembly'] );
		self::assertSame( 18300, $given[1][1]['total'] );
	}

	public function test_an_answer_that_is_not_words_is_no_answer(): void {
		add_filter( 'cbuild_arrival_estimate', '__return_null' );
		add_filter( 'cbuild_delivery_line', static fn(): array => array( 'not words' ) );
		self::assertSame(
			array(
				'arrival' => '',
				'line'    => '',
			),
			$this->service( Delivery::class )->said( $this->kit, true, 19800, 2 )
		);
		self::assertSame( '', $this->service( Delivery::class )->said( 0, true, 0, 2 )['arrival'], 'a kit that does not read' );
	}
}
