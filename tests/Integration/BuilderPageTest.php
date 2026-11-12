<?php
/**
 * The builder, rendered on the server.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuilderPage;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\PageHooks;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use DOMDocument;
use DOMXPath;
use RuntimeException;
use WC_Product_Simple;
use WP_Block_Type_Registry;
use WP_Interactivity_API;

/**
 * Spec, The builder: the kit's page and the Builder block render one builder, on the server, with the first step
 * and the picture in the HTML (Speed); every step is a GET form whose picks travel in the address (Without
 * JavaScript); a kit a visitor may not see renders nothing; and a render that fails says one sentence and is logged
 * (Resilience).
 */
final class BuilderPageTest extends IntegrationTestCase {

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

	public function set_up(): void {
		parent::set_up();
		$this->service( \CreativeBranding\SkateboardBuilder\Hooks\CardHooks::class )->register();
		// The page's store state is the request's: each test is a request of its own.
		$GLOBALS['wp_interactivity'] = new WP_Interactivity_API();
		unset( $_GET[ BuildLink::PARAM ] );

		$decks  = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$trucks = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$truck  = array(
			'baseplate'     => 'new_school',
			'max_wheel'     => '56.0000',
			'axle_diameter' => '8.0000',
			'set_size'      => '2',
		);

		$this->parts['deck 8.0']  = $this->part( 'Deck 8.0 & <b>co</b>', '60', array( 'deck_width' => '8.0000' ), $decks );
		$this->parts['deck 8.5']  = $this->part( 'Deck 8.5', '70', array( 'deck_width' => '8.5000' ), $decks );
		$this->parts['truck 139'] = $this->part(
			'Truck 139',
			'25',
			array(
				'axle_width' => '8.0000',
				'deck_min'   => '7.8000',
				'deck_max'   => '8.2000',
			) + $truck,
			$trucks
		);
		$this->parts['truck 149'] = $this->part(
			'Truck 149',
			'27',
			array(
				'axle_width' => '8.5000',
				'deck_min'   => '8.3750',
				'deck_max'   => '8.6000',
			) + $truck,
			$trucks
		);

		$slots                        = KitDocuments::slots();
		$slots['slots'][0]['sources'] = array( 'categories' => array( $decks ) );
		$slots['slots'][1]['sources'] = array( 'categories' => array( $trucks ) );
		$this->kit                    = KitFactory::kit( 'Complete', array( Options::META_KIT_SLOTS => $slots ) )->get_id();
	}

	public function tear_down(): void {
		unset( $_GET[ BuildLink::PARAM ], $GLOBALS['product'] );
		remove_all_filters( 'cbuild_part_classes' );
		parent::tear_down();
	}

	/**
	 * A published part in a category, indexed.
	 *
	 * @param array<string, string> $specs Its specs.
	 */
	private function part( string $name, string $price, array $specs, int $category ): int {
		$product = new WC_Product_Simple();
		$product->set_name( $name );
		$product->set_status( 'publish' );
		$product->set_regular_price( $price );
		$product->update_meta_data( Options::META_SPECS, $specs );
		$product->save();
		wp_set_object_terms( $product->get_id(), array( $category ), 'product_cat' );
		$this->service( SpecIndexer::class )->index( $product->get_id() );
		return $product->get_id();
	}

	/**
	 * A renderer of its own, as a request has.
	 */
	private function page( ?Logger $logger = null ): BuilderPage {
		return new BuilderPage( $this->service( Builder::class ), $this->service( Kits::class ), $logger ?? $this->service( Logger::class ) );
	}

	/**
	 * The kit's page, as WooCommerce asks the kit's type for its add-to-cart area.
	 *
	 * @param array<string, mixed>|null $sent The build in the address.
	 */
	private function kitPage( ?array $sent = null ): string {
		if ( null !== $sent ) {
			$_GET[ BuildLink::PARAM ] = $sent;
		}
		$GLOBALS['product'] = wc_get_product( $this->kit );
		$hooks              = new PageHooks( fn(): BuilderPage => $this->page() );
		ob_start();
		$hooks->kitPage();
		return (string) ob_get_clean();
	}

	/**
	 * Markup as a visitor sees it, to be asked about: a list's template is kept for the store and shows nothing.
	 */
	private static function dom( string $html ): DOMXPath {
		$document = new DOMDocument();
		$shown    = (string) preg_replace( '#<template\b.*?</template>#s', '', $html );
		self::assertTrue( $document->loadHTML( '<?xml encoding="utf-8"?><body>' . $shown . '</body>', LIBXML_NOERROR ) );
		return new DOMXPath( $document );
	}

	/**
	 * The text of the first element a query finds.
	 */
	private static function text( DOMXPath $dom, string $query ): string {
		$found = $dom->query( $query );
		self::assertNotFalse( $found );
		$node = $found->item( 0 );
		self::assertNotNull( $node, $query );
		return trim( $node->textContent );
	}

	public function test_the_kits_page_renders_the_first_step_as_a_form_with_a_radio_for_each_deck(): void {
		$html = $this->kitPage();
		$dom  = self::dom( $html );

		self::assertStringContainsString( 'data-wp-interactive="creative-skateboard-builder"', $html );
		self::assertSame( 'Step 1 of 7: Deck', self::text( $dom, '//h2[contains(@class,"cbuild__heading")]' ) );
		$form = $dom->query( '//form[contains(@class,"cbuild-step")]' )->item( 0 );
		self::assertNotNull( $form );
		self::assertSame( 'get', $form->getAttribute( 'method' ) );
		// A GET form drops its action's query, so the page's own query travels as fields.
		self::assertSame( home_url( '/' ), $form->getAttribute( 'action' ) );
		$carried = $dom->query( '//form[contains(@class,"cbuild-step")]//input[@type="hidden" and @name="product"]' )->item( 0 );
		self::assertNotNull( $carried );
		self::assertSame( 'complete', $carried->getAttribute( 'value' ) );
		self::assertFalse( $form->hasAttribute( 'hidden' ) );

		$radios = $dom->query( '//input[@type="radio" and @name="cbuild[deck]"]' );
		self::assertSame( array( (string) $this->parts['deck 8.0'], (string) $this->parts['deck 8.5'], '0' ), array_map( static fn( $radio ) => $radio->getAttribute( 'value' ), iterator_to_array( $radios ) ), 'Each deck, then None, which the deck step hides.' );
		self::assertSame( 'Choose Deck 8.0 & co', $radios->item( 0 )->getAttribute( 'aria-label' ), 'A name is plain text, escaped as an attribute and read back.' );
		self::assertStringNotContainsString( '<b>co</b>', $html, 'A part\'s name is text, never markup.' );
		self::assertSame( '+$60.00', self::text( $dom, '//li[contains(@class,"cbuild-card")][1]//span[@class="cbuild-card__delta"]' ) );
		self::assertSame( 'Next: Trucks', self::text( $dom, '//button[@name="cbuild[step]"]' ) );
		self::assertSame( 'trucks', $dom->query( '//button[@name="cbuild[step]"]' )->item( 0 )->getAttribute( 'value' ) );
		self::assertSame( 1, $dom->query( '//*[@role="status"]' )->length, 'The status region is in the page from the start.' );
		self::assertNotNull( $dom->query( '//section[contains(@class,"cbuild-review")][@hidden]' )->item( 0 ), 'The review is not this step.' );
		self::assertNotNull( $dom->query( '//section[contains(@class,"cbuild-changes")][@hidden]' )->item( 0 ) );
	}

	public function test_a_build_link_opens_on_its_step_with_its_picks_carried_and_the_recommendation_checked(): void {
		$dom = self::dom( $this->kitPage( array( 'deck' => (string) $this->parts['deck 8.5'] ) ) );

		self::assertSame( 'Step 2 of 7: Trucks', self::text( $dom, '//h2[contains(@class,"cbuild__heading")]' ) );
		$deck = $dom->query( '//form[contains(@class,"cbuild-step")]//input[@type="hidden" and @name="cbuild[deck]"]' )->item( 0 );
		self::assertNotNull( $deck, 'The deck travels with the form.' );
		self::assertSame( (string) $this->parts['deck 8.5'], $deck->getAttribute( 'value' ) );
		$checked = $dom->query( '//input[@type="radio" and @name="cbuild[trucks]" and @checked]' );
		self::assertSame( 1, $checked->length );
		self::assertSame( (string) $this->parts['truck 149'], $checked->item( 0 )->getAttribute( 'value' ), 'The step starts on what fits, and the form sends it.' );
		self::assertSame( 'Recommended for your 8.5 in deck', self::text( $dom, '//li[contains(@class,"cbuild-card--chosen")]//span[@class="cbuild-card__recommended"]' ) );
		self::assertSame( 'Fits your 8.5 in deck', self::text( $dom, '//li[contains(@class,"cbuild-card--chosen")]//span[contains(@class,"cbuild-card__fit")]' ) );
		$back = $dom->query( '//a[contains(@class,"cbuild-step__back")]' )->item( 0 );
		self::assertNotNull( $back );
		self::assertSame( 'Back: Deck', trim( $back->textContent ) );
		self::assertSame( add_query_arg( array( 'cbuild' => array( 'deck' => $this->parts['deck 8.5'], 'step' => 'deck' ) ), get_permalink( $this->kit ) ), html_entity_decode( $back->getAttribute( 'href' ) ) );
		self::assertSame( 'nofollow', $back->getAttribute( 'rel' ) );
	}

	public function test_a_step_past_one_the_build_leaves_empty_stays_there_and_says_why(): void {
		$dom = self::dom( $this->kitPage( array( 'step' => 'review' ) ) );

		self::assertSame( 'Step 1 of 7: Deck', self::text( $dom, '//h2[contains(@class,"cbuild__heading")]' ) );
		$errors = $dom->query( '//div[contains(@class,"cbuild-errors")]' )->item( 0 );
		self::assertNotNull( $errors );
		self::assertFalse( $errors->hasAttribute( 'hidden' ) );
		self::assertSame( 'Deck: nothing is chosen yet.', self::text( $dom, '//div[contains(@class,"cbuild-errors")]//a' ) );
	}

	public function test_a_kit_a_visitor_may_not_see_renders_nothing_and_a_page_renders_one_builder(): void {
		$page = $this->page();
		self::assertNotSame( '', $page->render( $this->kit, new BuildLink(), get_permalink( $this->kit ) ) );
		self::assertSame( '', $page->render( $this->kit, new BuildLink(), get_permalink( $this->kit ) ), 'The second builder on a page.' );

		$kit = wc_get_product( $this->kit );
		$kit->set_status( 'draft' );
		$kit->save();
		self::assertSame( '', $this->page()->render( $this->kit, new BuildLink(), '' ) );
		self::assertSame( '', $this->page()->render( $this->parts['deck 8.0'], new BuildLink(), '' ), 'A product that is no kit.' );
	}

	public function test_a_render_that_fails_says_one_sentence_closes_its_buffer_and_is_logged_with_the_kit(): void {
		add_filter(
			'cbuild_part_classes',
			static function (): array {
				throw new RuntimeException( 'A plugin broke.' );
			}
		);
		$logger = new FakeLogger();
		$level  = ob_get_level();
		$html   = $this->page( $logger )->render( $this->kit, new BuildLink(), get_permalink( $this->kit ) );

		self::assertSame( '<p class="cbuild-failed">The builder could not be shown. Reload the page, or try again later.</p>', $html );
		self::assertSame( $level, ob_get_level() );
		self::assertSame(
			array(
				array(
					'level'   => Logger::ERROR,
					'message' => 'The builder could not be shown.',
					'context' => array(
						'kit'   => $this->kit,
						'error' => 'A plugin broke.',
					),
				),
			),
			$logger->lines,
			'The kit and the error, and nothing of the request.'
		);
	}

	public function test_the_block_renders_the_same_builder_through_wordpress_and_nothing_without_a_kit(): void {
		self::assertTrue( WP_Block_Type_Registry::get_instance()->is_registered( PageHooks::BLOCK ), 'The build wrote build/builder/block.json.' );
		$type = WP_Block_Type_Registry::get_instance()->get_registered( PageHooks::BLOCK );
		self::assertNotNull( $type );
		self::assertSame( 3, $type->api_version );
		self::assertTrue( $type->supports['interactivity'] );
		self::assertFalse( $type->supports['multiple'] );

		// A block of its own, with a renderer of its own, as a request has.
		unregister_block_type( PageHooks::BLOCK );
		( new PageHooks( fn(): BuilderPage => $this->page() ) )->block();
		$html = do_blocks( '<!-- wp:creative-skateboard-builder/builder {"kit":' . $this->kit . '} /-->' );
		self::assertSame( 'Step 1 of 7: Deck', self::text( self::dom( $html ), '//h2[contains(@class,"cbuild__heading")]' ), 'Its directives processed by WordPress.' );
		self::assertSame( 3, self::dom( $html )->query( '//input[@type="radio" and @name="cbuild[deck]"]' )->length, 'Each deck and None, once: the directives were processed once.' );

		unregister_block_type( PageHooks::BLOCK );
		( new PageHooks( fn(): BuilderPage => $this->page() ) )->block();
		self::assertSame( '', trim( do_blocks( '<!-- wp:creative-skateboard-builder/builder /-->' ) ) );
	}
}
