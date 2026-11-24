<?php
/**
 * Where a kit's builder is: the Builder page its Build tab chose, re-checked each time it is used.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\KitProduct;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Front\BuilderAddress;
use CreativeBranding\SkateboardBuilder\Front\BuilderPage;
use CreativeBranding\SkateboardBuilder\Front\BuilderPlace;
use CreativeBranding\SkateboardBuilder\Front\BuilderUrl;
use CreativeBranding\SkateboardBuilder\Front\MyBuildsView;
use CreativeBranding\SkateboardBuilder\Front\PageHooks;
use CreativeBranding\SkateboardBuilder\Rest\StateRoute;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use DateTimeImmutable;

/**
 * #113 (D-348): a kit's Build tab chooses the published page its Builder block is on. The kit's page sends a visitor
 * there, and My builds' Open, the state route's links, a deck's "Build a complete with this deck" (StartButtonTest) and
 * the cart's Edit build (CartShowTest) start from it. A page unpublished, trashed, deleted, made private or locked, or
 * one that has lost the block, falls back to the kit's own page, as an answer to `cbuild_builder_url` that is no
 * address on the site is ignored. The filter answers last.
 */
final class BuilderAddressTest extends IntegrationTestCase {

	private int $kit;

	public function set_up(): void {
		parent::set_up();
		$this->kit = KitFactory::kit()->get_id();
	}

	public function tear_down(): void {
		remove_all_filters( 'cbuild_builder_url' );
		parent::tear_down();
	}

	/**
	 * A published page holding a kit's Builder block, or the content given.
	 *
	 * @param array<string, mixed> $more The page's other fields.
	 */
	private function page( ?string $content = null, array $more = array() ): int {
		return self::factory()->post->create(
			$more + array(
				'post_type'    => 'page',
				'post_status'  => 'publish',
				'post_content' => $content ?? '<!-- wp:paragraph --><p>Build yours.</p><!-- /wp:paragraph -->' . "\n" . '<!-- wp:creative-skateboard-builder/builder {"kit":' . $this->kit . '} /-->',
			)
		);
	}

	/**
	 * The kit's layout, as the Build tab saves it.
	 *
	 * @param array<string, mixed> $layout The document's settings.
	 */
	private function layout( array $layout ): void {
		clean_post_cache( $this->kit );
		$kit = wc_get_product( $this->kit );
		self::assertInstanceOf( KitProduct::class, $kit );
		$kit->setDocument( Options::META_KIT_LAYOUT, array( 'schema_version' => 1 ) + $layout );
		$kit->save();
	}

	private function address(): BuilderAddress {
		return $this->service( BuilderAddress::class );
	}

	/**
	 * Where a visitor on the kit's own page is sent, or null where they stay.
	 */
	private function sent(): ?string {
		$this->go_to( (string) get_permalink( $this->kit ) );
		return $this->service( PageHooks::class )->elsewhere();
	}

	private function saved(): SavedBuild {
		$at = new DateTimeImmutable( '2026-11-24 12:00:00' );
		return new SavedBuild( 'b6d0f5a2-3c1e-4a8b-9d2f-0e1c2b3a4d5e', 1, $this->kit, array(), false, 'Mine', $at, $at );
	}

	private function route(): string {
		$base = new \ReflectionMethod( StateRoute::class, 'base' );
		return (string) $base->invoke( $this->service( StateRoute::class ), $this->kit, 0 );
	}

	public function test_a_kit_saved_before_1_2_0_is_built_on_its_own_page_and_the_filter_starts_from_it(): void {
		$own = (string) get_permalink( $this->kit );
		self::assertSame( $own, $this->address()->of( $this->kit ) );
		self::assertNull( $this->sent(), 'the kit\'s page keeps its visitor' );
		self::assertStringStartsWith( $own, (string) $this->service( MyBuildsView::class )->open( $this->saved() ) );
		self::assertSame( $own, $this->route() );
		$told = null;
		add_filter(
			'cbuild_builder_url',
			static function ( mixed $url ) use ( &$told ): mixed {
				$told = $url;
				return $url;
			}
		);
		$this->address()->of( $this->kit );
		self::assertSame( $own, $told, 'the filter is told the kit\'s own page, as in 1.1.0' );
	}

	public function test_the_chosen_page_is_where_the_kits_page_my_builds_and_the_route_send_a_visitor(): void {
		$page = $this->page();
		$this->layout( array( 'builder_page' => $page ) );
		$there = (string) get_permalink( $page );
		self::assertSame( $there, $this->address()->of( $this->kit ) );
		self::assertSame( $page, $this->address()->page( $this->kit ) );
		self::assertSame( $there, $this->sent(), 'the kit\'s page sends its visitor there' );
		self::assertStringStartsWith( $there, (string) $this->service( MyBuildsView::class )->open( $this->saved() ), 'My builds\' Open' );
		self::assertSame( $there, $this->route(), 'the state route\'s links' );

		// The builder on that page is the kit's, so the page keeps its visitor.
		$this->go_to( $there );
		self::assertSame( $this->kit, $this->service( BuilderPlace::class )->kit( $page, 'page' ) );
	}

	public function test_a_page_that_cannot_be_used_now_sends_no_one_there(): void {
		$own   = (string) get_permalink( $this->kit );
		$other = KitFactory::kit( 'Cruiser complete' )->get_id();
		$cases = array(
			'a draft'                  => fn(): int => $this->page( null, array( 'post_status' => 'draft' ) ),
			'a page waiting to go up'  => fn(): int => $this->page( null, array( 'post_status' => 'future', 'post_date' => '2099-01-01 00:00:00' ) ),
			'a private page'           => fn(): int => $this->page( null, array( 'post_status' => 'private' ) ),
			'a page with a password'   => fn(): int => $this->page( null, array( 'post_password' => 'secret' ) ),
			'a page in the bin'        => function (): int {
				$page = $this->page();
				wp_trash_post( $page );
				return $page;
			},
			'a page deleted'           => function (): int {
				$page = $this->page();
				wp_delete_post( $page, true );
				return $page;
			},
			'a page that lost the block' => function (): int {
				$page = $this->page();
				wp_update_post(
					array(
						'ID'           => $page,
						'post_content' => '<!-- wp:paragraph --><p>Gone.</p><!-- /wp:paragraph -->',
					)
				);
				return $page;
			},
			'another kit\'s builder'   => fn(): int => $this->page( '<!-- wp:creative-skateboard-builder/builder {"kit":' . $other . '} /-->' ),
			'a post, not a page'       => fn(): int => $this->page( null, array( 'post_type' => 'post' ) ),
			'a product'                => fn(): int => $other,
		);
		foreach ( $cases as $why => $make ) {
			$this->layout( array( 'builder_page' => $make() ) );
			self::assertSame( $own, $this->address()->of( $this->kit ), $why );
			self::assertNull( $this->sent(), $why . ': the kit\'s page keeps its visitor' );
			self::assertSame( $own, $this->route(), $why . ': the route' );
		}

		// Published again, it is used again: the page is checked each time.
		$page = $this->page( null, array( 'post_status' => 'draft' ) );
		$this->layout( array( 'builder_page' => $page ) );
		self::assertSame( $own, $this->address()->of( $this->kit ) );
		wp_publish_post( $page );
		self::assertSame( (string) get_permalink( $page ), $this->address()->of( $this->kit ) );
	}

	public function test_the_filter_answers_last_over_the_chosen_page(): void {
		$page = $this->page();
		$this->layout( array( 'builder_page' => $page ) );
		$there = (string) get_permalink( $page );
		$told  = null;
		$other = $this->page( '<p>The shop\'s own.</p>' );
		add_filter(
			'cbuild_builder_url',
			static function ( mixed $url, int $kit ) use ( &$told, $other ): string {
				$told = array( $url, $kit );
				return (string) get_permalink( $other );
			},
			10,
			2
		);
		self::assertSame( (string) get_permalink( $other ), $this->address()->of( $this->kit ), 'the answer wins' );
		self::assertSame( array( $there, $this->kit ), $told, 'told the chosen page' );
		self::assertSame( (string) get_permalink( $other ), $this->sent() );

		remove_all_filters( 'cbuild_builder_url' );
		add_filter( 'cbuild_builder_url', static fn(): string => 'https://elsewhere.example/build/' );
		self::assertSame( $there, $this->address()->of( $this->kit ), 'an answer on another host is ignored, and the chosen page kept' );
		remove_all_filters( 'cbuild_builder_url' );
		add_filter( 'cbuild_builder_url', static fn(): int => 5 );
		self::assertSame( $there, $this->address()->of( $this->kit ), 'as is one that is no address' );
	}

	// D-208: a copy an earlier release's container built mid-update has no address, and goes where 1.1.0 did.
	public function test_a_copy_an_earlier_release_built_keeps_the_kits_own_page(): void {
		$page = $this->page();
		$this->layout( array( 'builder_page' => $page ) );
		$own   = (string) get_permalink( $this->kit );
		$hooks = new PageHooks( fn(): BuilderPage => $this->service( BuilderPage::class ), $this->service( BuilderPlace::class ) );
		$this->go_to( $own );
		self::assertNull( $hooks->elsewhere() );
		self::assertSame( $own, BuilderUrl::of( $this->kit ) );
		$hooks = $hooks->withAddress( $this->address() );
		self::assertSame( (string) get_permalink( $page ), $hooks->elsewhere(), 'with it, the chosen page' );
	}
}
