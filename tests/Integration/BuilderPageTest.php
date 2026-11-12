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
use CreativeBranding\SkateboardBuilder\Front\PageTitle;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Front\BuilderView;
use CreativeBranding\SkateboardBuilder\Front\BuilderUrl;
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
		remove_all_filters( 'cbuild_builder_url' );
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
		// The innermost template first, until none is left: a template can hold another (a list in a list).
		$shown = $html;
		do {
			$before = $shown;
			$shown  = (string) preg_replace( '#<template\b(?:(?!<template\b).)*?</template>#s', '', $shown );
		} while ( $shown !== $before );
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

	public function test_the_kits_page_gives_the_store_its_route_page_and_language_and_loads_it(): void {
		$this->go_to( (string) get_permalink( $this->kit ) );
		$this->kitPage();
		self::assertSame(
			array(
				'cart'     => rest_url( 'wc/store/v1/cart' ),
				'endpoint' => rest_url( 'cbuild/v1/kits/' . $this->kit . '/state' ),
				'page'     => $this->kit,
				'locale'   => determine_locale(),
				'title'    => PageTitle::around( PageTitle::base() ),
			),
			wp_interactivity_config( BuilderPage::STORE )
		);
		self::assertFalse( wp_interactivity_state( BuilderPage::STORE )['pending'] );
		self::assertFalse( wp_interactivity_state( BuilderPage::STORE )['added'], 'Nothing is added on load.' );
		self::assertFalse( wp_interactivity_state( BuilderPage::STORE )['canCopy'], 'The store says whether the browser can copy.' );
		self::assertContains( BuilderPage::MODULE, wp_script_modules()->get_queue(), 'the kit\'s page has no block to load it' );
		self::assertNotNull( wp_script_modules()->get_registered( BuilderPage::MODULE ), 'the block registers it, from build/builder/view.js' );
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

	public function test_parts_that_do_not_fit_are_listed_under_a_disclosure_with_their_reasons(): void {
		$dom = self::dom( $this->kitPage( array( 'deck' => (string) $this->parts['deck 8.5'] ) ) );

		$details = $dom->query( '//details[contains(@class,"cbuild-hidden")]' )->item( 0 );
		self::assertNotNull( $details );
		self::assertFalse( $details->hasAttribute( 'hidden' ) );
		self::assertSame( '1 part hidden: the trucks are made for another width of deck.', self::text( $dom, '//details[contains(@class,"cbuild-hidden")]/summary' ) );
		self::assertSame( 'Truck 139', self::text( $dom, '//li[contains(@class,"cbuild-hidden__part")]/a' ) );
		self::assertSame( get_permalink( $this->parts['truck 139'] ), $dom->query( '//li[contains(@class,"cbuild-hidden__part")]/a' )->item( 0 )->getAttribute( 'href' ) );
		self::assertStringContainsString( 'The deck is 8.5 in.', self::text( $dom, '//ul[contains(@class,"cbuild-hidden__reasons")]/li' ) );
	}

	public function test_the_step_and_the_review_have_the_forms_the_phone_bar_sends_by_their_ids(): void {
		$dom = self::dom( $this->kitPage( array() ) );
		self::assertSame( 1, $dom->query( '//form[@id="' . BuilderView::STEP_FORM . '"][contains(@class,"cbuild-step")]' )->length );
		self::assertSame( 1, $dom->query( '//form[@id="' . BuilderView::REVIEW_FORM . '"][contains(@class,"cbuild-review__form")]' )->length );
	}

	public function test_the_review_holds_the_builds_link_to_copy_with_or_without_javascript(): void {
		$dom  = self::dom( $this->kitPage( array() ) );
		$link = $dom->query( '//input[contains(@class,"cbuild-share__link")]' )->item( 0 );
		self::assertNotNull( $link );
		self::assertTrue( $link->hasAttribute( 'readonly' ) );
		self::assertSame( 'url', $link->getAttribute( 'type' ) );
		$url = wp_interactivity_state( BuilderPage::STORE )['view']['url'];
		self::assertStringContainsString( 'cbuild%5Bstep%5D=', $url );
		self::assertSame( $url, $link->getAttribute( 'value' ), 'The view\'s own address, the build in it.' );
		$label = $dom->query( '//label[@for="' . $link->getAttribute( 'id' ) . '"]' )->item( 0 );
		self::assertSame( 'Link to this build', trim( (string) $label?->textContent ) );
		$copy = $dom->query( '//button[contains(@class,"cbuild-share__copy")]' )->item( 0 );
		self::assertSame( 'Copy link', trim( (string) $copy->textContent ) );
		self::assertSame( 'button', $copy->getAttribute( 'type' ) );
		self::assertSame( 'actions.copy', $copy->getAttribute( 'data-wp-on--click' ) );
		self::assertTrue( $copy->hasAttribute( 'hidden' ), 'Without JavaScript the button would do nothing.' );
	}

	public function test_the_review_adds_through_the_store_and_says_so_with_a_link_to_the_cart(): void {
		$dom  = self::dom( $this->kitPage( array() ) );
		$form = $dom->query( '//form[@id="' . BuilderView::REVIEW_FORM . '"]' )->item( 0 );
		self::assertSame( 'actions.add', $form->getAttribute( 'data-wp-on--submit' ), 'With JavaScript, the Store API adds the build (D-155).' );
		self::assertSame( 'post', $form->getAttribute( 'method' ), 'Without it, WooCommerce\'s own handler does.' );
		$added = $dom->query( '//p[contains(@class,"cbuild-review__added")]' )->item( 0 );
		self::assertNotNull( $added );
		self::assertTrue( $added->hasAttribute( 'hidden' ), 'Nothing is added on load.' );
		self::assertSame( '-1', $added->getAttribute( 'tabindex' ), 'Focus goes to it once the build is added.' );
		self::assertSame( 'The build is in your cart.', self::text( $dom, '//p[contains(@class,"cbuild-review__added")]/span' ) );
		self::assertSame( wc_get_cart_url(), $dom->query( '//p[contains(@class,"cbuild-review__added")]/a' )->item( 0 )->getAttribute( 'href' ) );
		self::assertSame( 'View cart', self::text( $dom, '//p[contains(@class,"cbuild-review__added")]/a' ) );
	}

	public function test_the_deck_step_has_a_button_for_each_width_in_its_form(): void {
		$dom  = self::dom( $this->kitPage( array() ) );
		$path = '//details[contains(@class,"cbuild-filters")]';
		self::assertFalse( $dom->query( $path )->item( 0 )->hasAttribute( 'hidden' ) );
		self::assertSame( 'Width', self::text( $dom, $path . '//legend' ) );
		$buttons = $dom->query( $path . '//button[contains(@class,"cbuild-filters__option")]' );
		self::assertSame( array( '8in', '8.5in' ), array_map( static fn( $button ) => $button->getAttribute( 'value' ), iterator_to_array( $buttons ) ) );
		self::assertSame( 'cbuild[_size]', $buttons->item( 1 )->getAttribute( 'name' ) );
		self::assertSame( 'false', $buttons->item( 1 )->getAttribute( 'aria-pressed' ) );
		self::assertTrue( $dom->query( $path . '//button[contains(@class,"cbuild-filters__clear")]' )->item( 0 )->hasAttribute( 'hidden' ) );
	}

	public function test_a_width_pressed_in_the_step_lists_only_its_decks_and_keeps_the_step(): void {
		$path   = '//details[contains(@class,"cbuild-filters")]';
		$dom    = self::dom( $this->kitPage( array( '_on' => 'deck', '_size' => '8.5in' ) ) );
		$radios = $dom->query( '//input[@name="cbuild[deck]"][not(@value="0")]' );
		self::assertSame( array( (string) $this->parts['deck 8.5'] ), array_map( static fn( $radio ) => $radio->getAttribute( 'value' ), iterator_to_array( $radios ) ) );
		self::assertSame( 'true', $dom->query( $path . '//button[@value="8.5in"]' )->item( 0 )->getAttribute( 'aria-pressed' ) );
		self::assertFalse( $dom->query( $path . '//button[contains(@class,"cbuild-filters__clear")]' )->item( 0 )->hasAttribute( 'hidden' ) );
		self::assertNotNull( $dom->query( '//input[@type="hidden"][@name="cbuild[_size]"][@value="8.5in"]' )->item( 0 ), 'the filter stays on through a pick' );
		self::assertNotNull( $dom->query( '//input[@type="hidden"][@name="cbuild[_on]"][@value="deck"]' )->item( 0 ), 'the step its form is on' );
		self::assertSame( '1 of 1 part shown', self::text( $dom, '//span[contains(@class,"cbuild-step__count")]' ) );
		self::assertTrue( $dom->query( '//button[contains(@class,"cbuild-step__show")]' )->item( 0 )->hasAttribute( 'hidden' ) );
	}

	public function test_a_step_past_one_the_build_leaves_empty_stays_there_and_says_why(): void {
		$dom = self::dom( $this->kitPage( array( 'step' => 'review' ) ) );

		self::assertSame( 'Step 1 of 7: Deck', self::text( $dom, '//h2[contains(@class,"cbuild__heading")]' ) );
		$errors = $dom->query( '//div[contains(@class,"cbuild-errors")]' )->item( 0 );
		self::assertNotNull( $errors );
		self::assertFalse( $errors->hasAttribute( 'hidden' ) );
		self::assertSame( 'Deck: nothing is chosen yet.', self::text( $dom, '//div[contains(@class,"cbuild-errors")]//a' ) );
	}

	public function test_a_page_with_a_build_says_its_step_first_in_its_title_and_the_clean_page_keeps_its_own(): void {
		$page = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Build a complete',
				'post_content' => '<!-- wp:group --><div><!-- wp:creative-skateboard-builder/builder {"kit":' . $this->kit . '} /--></div><!-- /wp:group -->',
			)
		);
		$this->go_to( (string) get_permalink( $page ) );
		$clean = wp_get_document_title();
		self::assertStringStartsWith( 'Build a complete', $clean, 'the clean page is the one search engines index' );

		$_GET[ BuildLink::PARAM ] = array( 'deck' => (string) $this->parts['deck 8.5'] );
		self::assertSame( 'Build a complete – Test Blog', PageTitle::base(), 'the page\'s own title, as text' );
		self::assertSame( 'Step 2 of 7: Trucks - Build a complete – Test Blog', wp_get_document_title() );

		$_GET[ BuildLink::PARAM ] = array( 'step' => 'review' );
		self::assertSame( 'Error: Step 1 of 7: Deck - Build a complete – Test Blog', wp_get_document_title(), 'an error summary is shown' );
	}

	public function test_a_kits_page_with_a_build_says_its_step_and_a_page_with_no_builder_or_a_hidden_kit_does_not(): void {
		$this->go_to( (string) get_permalink( $this->kit ) );
		$_GET[ BuildLink::PARAM ] = array( 'deck' => (string) $this->parts['deck 8.5'] );
		self::assertStringStartsWith( 'Step 2 of 7: Trucks - ', wp_get_document_title() );

		$plain = self::factory()->post->create(
			array(
				'post_type'   => 'page',
				'post_status' => 'publish',
				'post_title'  => 'About',
			)
		);
		$this->go_to( (string) get_permalink( $plain ) );
		$_GET[ BuildLink::PARAM ] = array( 'deck' => (string) $this->parts['deck 8.5'] );
		self::assertSame( 'About &#8211; Test Blog', wp_get_document_title() );

		$hidden = self::factory()->post->create(
			array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_title'   => 'Hidden kit',
				'post_content' => '<!-- wp:creative-skateboard-builder/builder {"kit":' . $this->kit . '} /-->',
			)
		);
		wp_update_post(
			array(
				'ID'          => $this->kit,
				'post_status' => 'draft',
			)
		);
		$this->go_to( (string) get_permalink( $hidden ) );
		$_GET[ BuildLink::PARAM ] = array( 'deck' => (string) $this->parts['deck 8.5'] );
		self::assertSame( 'Hidden kit &#8211; Test Blog', wp_get_document_title() );
	}

	public function test_the_store_is_told_what_goes_around_each_step_in_the_title(): void {
		self::assertSame(
			array(
				'before' => '',
				'after'  => ' - Build a complete – Test Blog',
			),
			PageTitle::around( 'Build a complete – Test Blog' )
		);
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

	public function test_a_render_that_fails_says_the_builder_is_not_available_closes_its_buffer_and_is_logged_with_the_kit(): void {
		add_filter(
			'cbuild_part_classes',
			static function (): array {
				throw new RuntimeException( 'A plugin broke.' );
			}
		);
		$logger = new FakeLogger();
		$level  = ob_get_level();
		$html   = $this->page( $logger )->render( $this->kit, new BuildLink(), get_permalink( $this->kit ) );

		self::assertSame( '<p class="cbuild-failed">This builder isn&#039;t available right now.</p>', $html, 'Spec, Resilience: the shopper reads one sentence, and no reason.' );
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

	public function test_a_shop_manager_reads_why_a_render_failed_escaped(): void {
		add_filter(
			'cbuild_part_classes',
			static function (): array {
				throw new RuntimeException( 'A plugin broke <b>here</b>.' );
			}
		);
		wp_set_current_user( self::factory()->user->create( array( 'role' => 'shop_manager' ) ) );
		$html = $this->page( new FakeLogger() )->render( $this->kit, new BuildLink(), get_permalink( $this->kit ) );

		self::assertSame(
			'<p class="cbuild-failed">This builder isn&#039;t available right now.</p>'
			. '<p class="cbuild-failed__why">Shown to shop managers only: A plugin broke &lt;b&gt;here&lt;/b&gt;.</p>',
			$html
		);
	}

	public function test_a_kits_builder_is_on_its_own_page_unless_the_shop_names_another_on_the_site(): void {
		self::assertSame( get_permalink( $this->kit ), BuilderUrl::of( $this->kit ) );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		add_filter( 'cbuild_builder_url', static fn( mixed $url, int $kit ): mixed => $kit > 0 ? get_permalink( $page ) : $url, 10, 2 );
		self::assertSame( get_permalink( $page ), BuilderUrl::of( $this->kit ) );

		remove_all_filters( 'cbuild_builder_url' );
		add_filter( 'cbuild_builder_url', static fn(): string => 'https://elsewhere.example/build/' );
		self::assertSame( get_permalink( $this->kit ), BuilderUrl::of( $this->kit ), 'Another site is no answer: the link would send a shopper off the shop.' );

		remove_all_filters( 'cbuild_builder_url' );
		add_filter( 'cbuild_builder_url', static fn(): int => 5 );
		self::assertSame( get_permalink( $this->kit ), BuilderUrl::of( $this->kit ), 'An answer that is no address is none.' );
	}

	public function test_the_kits_page_sends_a_visitor_to_the_builders_page_with_the_build(): void {
		$hooks = new PageHooks( fn(): BuilderPage => $this->page() );
		$this->go_to( get_permalink( $this->kit ) );
		self::assertNull( $hooks->elsewhere(), 'The builder is on the kit\'s page.' );

		$page = self::factory()->post->create( array( 'post_type' => 'page' ) );
		add_filter( 'cbuild_builder_url', static fn(): string => (string) get_permalink( $page ) );
		$_GET[ BuildLink::PARAM ] = array(
			'deck' => (string) $this->parts['deck 8.5'],
			'step' => 'trucks',
		);
		self::assertSame(
			add_query_arg( array( 'cbuild' => array( 'deck' => (string) $this->parts['deck 8.5'], 'step' => 'trucks' ) ), (string) get_permalink( $page ) ),
			$hooks->elsewhere()
		);

		$this->go_to( get_permalink( $this->parts['deck 8.0'] ) );
		self::assertNull( $hooks->elsewhere(), 'A product that is no kit stays where it is.' );
		remove_all_filters( 'cbuild_builder_url' );
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
