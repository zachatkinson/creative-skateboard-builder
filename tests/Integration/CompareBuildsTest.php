<?php
/**
 * My builds' compare: up to three of a customer's saved builds side by side.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\CompareView;
use CreativeBranding\SkateboardBuilder\Front\SavedBuildHooks;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Support\Tables;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use DOMElement;
use DOMXPath;

/**
 * Issue #136 (docs/reviews/M13.md, D-411): under My builds, up to three saved builds side by side in a real table, the
 * parts that differ and the price gap said in words, at today's prices, with D-254's note where a part changed since
 * the save. A query string on My builds' own endpoint; only the signed-in customer's own builds are read.
 */
final class CompareBuildsTest extends CartTestCase {

	private int $ana;

	private int $ben;

	/**
	 * A second set of wheels that fits, dearer than the first.
	 */
	private int $wheels;

	public function set_up(): void {
		parent::set_up();
		global $wpdb;
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i', Tables::builds() ) );
		$this->ana = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => 'shopper+1@example.com' ) );
		$this->ben = self::factory()->user->create( array( 'role' => 'customer', 'user_email' => 'shopper+2@example.com' ) );
		$this->service( SavedBuildHooks::class )->register();

		$first   = wc_get_product( $this->parts['wheels'] );
		$product = new \WC_Product_Simple();
		$product->set_name( 'Other wheels' );
		$product->set_status( 'publish' );
		$product->set_regular_price( '40' );
		$product->update_meta_data( Options::META_SPECS, $first->get_meta( Options::META_SPECS ) );
		$product->save();
		wp_set_object_terms( $product->get_id(), wp_get_object_terms( $this->parts['wheels'], 'product_cat', array( 'fields' => 'ids' ) ), 'product_cat' );
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		$this->wheels = $product->get_id();
		$this->service( Kits::class )->forget();
	}

	public function tear_down(): void {
		$_GET = array();
		wp_set_current_user( 0 );
		parent::tear_down();
	}

	/**
	 * Save a build for a customer, and give its UUID.
	 *
	 * @param int                $user     The customer.
	 * @param string             $name     Its name.
	 * @param array<string, int> $picks    Its picks.
	 * @param bool               $assembly Assembly.
	 */
	private function saved( int $user, string $name, array $picks, bool $assembly = true ): string {
		$saved = $this->service( SavedBuilds::class )->save( $user, $this->kit, $picks, $assembly, $name );
		self::assertSame( SavedBuilds::SAVED, $saved['code'] );
		return $saved['uuid'];
	}

	/**
	 * My builds as the signed-in customer sees it, asked to compare these builds.
	 *
	 * @param list<string> $uuids The builds asked for.
	 */
	private function compare( array $uuids ): string {
		$_GET[ CompareView::PARAM ] = $uuids;
		ob_start();
		$this->service( SavedBuildHooks::class )->page();
		return (string) ob_get_clean();
	}

	/**
	 * A fragment as a document's paths.
	 *
	 * @param string $html The fragment.
	 */
	private static function dom( string $html ): DOMXPath {
		$document = new \DOMDocument();
		libxml_use_internal_errors( true );
		$document->loadHTML( '<?xml encoding="utf-8"?><div>' . $html . '</div>' );
		libxml_clear_errors();
		return new DOMXPath( $document );
	}

	/**
	 * A node's text, its spaces run together.
	 *
	 * @param \DOMNode|null $node The node.
	 */
	private static function text( ?\DOMNode $node ): string {
		return trim( (string) preg_replace( '/\s+/u', ' ', (string) $node?->textContent ) );
	}

	/**
	 * The row whose header starts with this label.
	 *
	 * @param DOMXPath $dom   The page.
	 * @param string   $label The row's label.
	 * @return list<string> The header's text, then each cell's.
	 */
	private static function row( DOMXPath $dom, string $label ): array {
		$rows = $dom->query( '//table[contains(@class,"cbuild-compare__table")]/tbody/tr[th[@scope="row"][starts-with(normalize-space(.), "' . $label . '")]]' );
		self::assertSame( 1, $rows->length, 'one row for ' . $label );
		$out = array();
		foreach ( $rows->item( 0 )?->childNodes ?? array() as $cell ) {
			if ( $cell instanceof DOMElement ) {
				$out[] = self::text( $cell );
			}
		}
		return $out;
	}

	/**
	 * A build's total today, as the shop writes it.
	 *
	 * @param array<string, int> $picks    Its picks.
	 * @param bool               $assembly Assembly.
	 */
	private function total( array $picks, bool $assembly = true ): int {
		$state = $this->service( BuildState::class )->of( $this->kit, $picks, null, State::REVIEW, $assembly );
		self::assertNotNull( $state );
		return $state->quote->total;
	}

	private function money( int $amount ): string {
		return $this->service( Money::class )->text( $amount, $this->service( Kits::class )->decimals() );
	}

	public function test_two_builds_side_by_side_in_a_table_with_the_parts_that_differ_and_the_price_gap_in_words(): void {
		wp_set_current_user( $this->ana );
		$street = $this->picks();
		$park   = array( 'wheels' => $this->wheels ) + $this->picks();
		$a      = $this->saved( $this->ana, 'Street', $street );
		$b      = $this->saved( $this->ana, 'Park', $park );

		$dom = self::dom( $this->compare( array( $a, $b ) ) );
		self::assertSame( 1, $dom->query( '//table[contains(@class,"cbuild-compare__table")]/caption' )->length, 'a table with a caption' );
		$heads = array_map( static fn( \DOMNode $th ): string => self::text( $th ), iterator_to_array( $dom->query( '//table[contains(@class,"cbuild-compare__table")]/thead/tr/th[@scope="col"]' ) ) );
		self::assertSame( array( 'Part', 'Street Open', 'Park Open' ), $heads, 'a column a build, each with its Open' );
		self::assertSame( array( 'Deck', 'Deck $70.00', 'Deck $70.00' ), self::row( $dom, 'Deck' ), 'the same deck, not marked' );
		$wheels = self::row( $dom, 'Wheels' );
		self::assertSame( 'Wheels Differs', $wheels[0], 'said in words in its header' );
		self::assertStringStartsWith( 'Wheels ', $wheels[1] );
		self::assertStringStartsWith( 'Other wheels ', $wheels[2] );
		self::assertSame( 2, $dom->query( '//tbody/tr[th[starts-with(normalize-space(.), "Wheels")]]/td[contains(@class,"cbuild-compare__differs")]' )->length, 'and in colour' );
		self::assertSame( array( 'Risers', 'None', 'None' ), self::row( $dom, 'Risers' ), 'a slot neither picked' );

		$low  = $this->total( $street );
		$high = $this->total( $park );
		self::assertGreaterThan( $low, $high );
		self::assertSame( array( 'Total today', $this->money( $low ), $this->money( $high ) ), self::row( $dom, 'Total today' ) );
		self::assertSame( array( 'Price difference', 'Lowest total', $this->money( $high - $low ) . ' more than the lowest' ), self::row( $dom, 'Price difference' ) );
		self::assertSame( 1, $dom->query( '//tbody/tr[th[normalize-space(.)="Price difference"]]/td[contains(@class,"cbuild-compare__lowest")]' )->length );
		$back = $dom->query( '//a[normalize-space(.)="Back to My builds"]/@href' )->item( 0 );
		self::assertSame( SavedBuildHooks::url(), html_entity_decode( (string) $back?->nodeValue ) );
	}

	/**
	 * D-254: a price that changed since the save is the price shown, with then and now; a part gone shows its closest
	 * fit in its place, with why, and the totals are today's.
	 */
	public function test_prices_are_todays_and_a_part_changed_since_the_save_is_said_as_reopening_says_it(): void {
		wp_set_current_user( $this->ana );
		$a = $this->saved( $this->ana, 'Street', $this->picks() );
		$b = $this->saved( $this->ana, 'Park', array( 'wheels' => $this->wheels ) + $this->picks() );

		$deck = wc_get_product( $this->parts['deck'] );
		$deck->set_regular_price( '99' );
		$deck->save();
		wp_trash_post( $this->wheels );
		$this->service( Kits::class )->forget();

		$dom = self::dom( $this->compare( array( $a, $b ) ) );
		self::assertSame(
			array( 'Deck', 'Deck $99.00 Deck: $70.00 when you saved, $99.00 now', 'Deck $99.00 Deck: $70.00 when you saved, $99.00 now' ),
			self::row( $dom, 'Deck' )
		);
		$wheels = self::row( $dom, 'Wheels' );
		self::assertSame( 'Wheels', $wheels[0], 'the closest fit is the same wheels: no longer differs' );
		self::assertStringContainsString( 'Wheels: Other wheels is no longer in the shop.', $wheels[2] );
		self::assertStringContainsString( 'Closest fit: Wheels', $wheels[2] );
		$total = $this->total( $this->picks() );
		self::assertSame( array( 'Total today', $this->money( $total ), $this->money( $total ) ), array_map( static fn( string $cell ): string => explode( ' Total: ', $cell )[0], self::row( $dom, 'Total today' ) ) );
		self::assertStringContainsString( ' when you saved, ' . $this->money( $total ) . ' now', self::row( $dom, 'Total today' )[1], 'the total then and now' );
		self::assertSame( array( 'Price difference', 'Same total', 'Same total' ), self::row( $dom, 'Price difference' ) );
	}

	/**
	 * Security (D-217): only the signed-in customer's own builds are read. Another's UUID, alone or beside their own,
	 * shows nothing of it.
	 */
	public function test_another_customers_builds_are_never_compared(): void {
		$a = $this->saved( $this->ana, 'Ana street', $this->picks() );
		$b = $this->saved( $this->ana, 'Ana park', array( 'wheels' => $this->wheels ) + $this->picks() );
		$c = $this->saved( $this->ben, 'Ben cruiser', $this->picks() );

		wp_set_current_user( $this->ben );
		foreach ( array( array( $a, $b ), array( $c, $a ), array( $a, $b, $c ) ) as $asked ) {
			$html = $this->compare( $asked );
			self::assertStringNotContainsString( 'Ana ', $html, 'nothing of another customer\'s' );
			self::assertStringNotContainsString( 'cbuild-compare__table', $html );
			self::assertStringContainsString( 'Choose two or three of your saved builds to compare.', $html );
		}
		wp_set_current_user( 0 );
		self::assertStringNotContainsString( 'Ana ', $this->compare( array( $a, $b ) ), 'nor to a visitor' );
	}

	public function test_up_to_three_builds_are_compared_and_more_are_said_to_be_left_out(): void {
		wp_set_current_user( $this->ana );
		$uuids = array();
		foreach ( array( 'One', 'Two', 'Three', 'Four' ) as $name ) {
			$uuids[] = $this->saved( $this->ana, $name, $this->picks() );
		}
		$dom   = self::dom( $this->compare( $uuids ) );
		$heads = array_map( static fn( \DOMNode $th ): string => self::text( $th ), iterator_to_array( $dom->query( '//thead/tr/th[@scope="col"]' ) ) );
		self::assertSame( array( 'Part', 'One Open', 'Two Open', 'Three Open' ), $heads );
		self::assertStringContainsString( 'Up to three builds are compared at once. These are the first three you chose.', self::text( $dom->query( '//*[contains(@class,"cbuild-compare__said")]' )->item( 0 ) ) );
		// Written with the page, so no live region (the auditor's 1.3.0 minor): a status on load is read out of turn by
		// some screen readers and by none in others, and the paragraph is read in its place with the rest.
		self::assertSame( 0, $dom->query( '//*[contains(@class,"cbuild-compare__said")][@role]' )->length, 'the compare\'s note has no role' );
		$asked = self::dom( $this->compare( array( $uuids[0] ) ) );
		self::assertSame( 1, $asked->query( '//*[contains(@class,"cbuild-compare__said")]' )->length, 'one build asked for: My builds says why' );
		self::assertSame( 0, $asked->query( '//*[contains(@class,"cbuild-compare__said")][@role]' )->length, 'My builds\' note has no role' );
		// One asked twice, or a UUID that is no UUID, is not a build.
		$dom = self::dom( $this->compare( array( $uuids[0], $uuids[0], 'not-a-uuid', $uuids[1] ) ) );
		self::assertSame( 3, $dom->query( '//thead/tr/th[@scope="col"]' )->length );
	}

	/**
	 * My builds lists a box a build and one Compare button, a form sent by GET to the page itself, so it works without
	 * JavaScript. A customer with one build has nothing to compare it with.
	 */
	public function test_my_builds_offers_compare_as_a_form_without_javascript(): void {
		wp_set_current_user( $this->ana );
		$a = $this->saved( $this->ana, 'Street', $this->picks() );
		ob_start();
		$this->service( SavedBuildHooks::class )->page();
		self::assertStringNotContainsString( CompareView::PARAM, (string) ob_get_clean(), 'one build: nothing to compare' );

		$b = $this->saved( $this->ana, 'Park', $this->picks() );
		ob_start();
		$this->service( SavedBuildHooks::class )->page();
		$dom  = self::dom( (string) ob_get_clean() );
		$form = $dom->query( '//form[@method="get"][contains(@class,"cbuild-my-builds__compare")]' )->item( 0 );
		self::assertInstanceOf( DOMElement::class, $form );
		self::assertSame( strtok( SavedBuildHooks::url(), '?' ), $form->getAttribute( 'action' ) );
		$boxes = $dom->query( '//input[@type="checkbox"][@name="' . CompareView::PARAM . '[]"][@form="' . $form->getAttribute( 'id' ) . '"]' );
		self::assertSame( 2, $boxes->length );
		$named = array();
		foreach ( $boxes as $box ) {
			self::assertInstanceOf( DOMElement::class, $box );
			$named[ $box->getAttribute( 'value' ) ] = $box->getAttribute( 'aria-label' );
		}
		self::assertSame(
			array(
				$b => 'Compare Park',
				$a => 'Compare Street',
			),
			$named,
			'a box a build, named for it, the last saved first as the list is'
		);
	}

	/**
	 * Performance (docs/reviews/M13.md): the compare reads the customer's builds once, and its queries do not grow
	 * with the builds compared.
	 */
	public function test_three_builds_ask_no_more_queries_than_two(): void {
		update_option( 'woocommerce_placeholder_image', 0 );
		wp_set_current_user( $this->ana );
		$uuids = array(
			$this->saved( $this->ana, 'One', $this->picks() ),
			$this->saved( $this->ana, 'Two', array( 'wheels' => $this->wheels ) + $this->picks() ),
			$this->saved( $this->ana, 'Three', $this->picks() + array( 'risers' => $this->parts['risers'] ) ),
		);
		$count = function ( array $asked ): int {
			$this->compare( $asked );
			wp_cache_flush();
			$this->service( Kits::class )->forget();
			$before = get_num_queries();
			$this->compare( $asked );
			return get_num_queries() - $before;
		};
		$two   = $count( array_slice( $uuids, 0, 2 ) );
		$three = $count( $uuids );
		self::assertLessThanOrEqual( $two + 2, $three, sprintf( '%d queries for two builds, %d for three', $two, $three ) );
	}
}
