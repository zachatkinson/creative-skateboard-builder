<?php
/**
 * The 3D view's scene, for the builder's view.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Unit\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\SlotKey;
use CreativeBranding\SkateboardBuilder\Domain\Picture\ImageFacts;
use CreativeBranding\SkateboardBuilder\Domain\Picture\Renditions;
use CreativeBranding\SkateboardBuilder\Front\SceneView;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\LayerArtwork;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\Shop;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeComposedLayers;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\FakeLogger;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryKits;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerImages;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryLayerRenditions;
use CreativeBranding\SkateboardBuilder\Tests\Support\Fakes\InMemoryShopSettings;
use PHPUnit\Framework\TestCase;

/**
 * #139: the scene the view carries for the 3D view, only where the kit's 3D view is on: the domain's sizes, each drawn
 * part's texture after `cbuild_composed_layer` with the use `3d`, and `cbuild_scene` last (D-470, D-475).
 */
final class SceneViewTest extends TestCase {

	private const int KIT = 77;

	private const array PICKS = array(
		SlotKey::DECK     => 102,
		SlotKey::TRUCKS   => 144,
		SlotKey::WHEELS   => 302,
		SlotKey::HARDWARE => 601,
		SlotKey::GRIP     => 701,
	);

	private InMemoryKits $kits;

	private InMemoryLayerImages $images;

	private InMemoryLayerRenditions $renditions;

	private FakeComposedLayers $layers;

	protected function setUp(): void {
		$this->kits       = new InMemoryKits();
		$this->images     = new InMemoryLayerImages();
		$this->renditions = new InMemoryLayerRenditions();
		$this->layers     = new FakeComposedLayers();
		$this->images->attachments = array( 102 => 10, 302 => 30, 701 => 70 );
		$this->images->images      = array(
			10 => new ImageFacts( 'image/png', 420, 1626, true ),
			30 => new ImageFacts( 'image/png', 200, 330, true ),
			70 => new ImageFacts( 'image/png', 230, 840, true ),
		);
		// The deck's renditions are made; the wheel's and the grip's are not yet.
		$deck = Shop::parts()[ SlotKey::DECK ][1];
		$this->renditions->make( 10, Renditions::drawn( SlotKey::DECK, $deck->specs, 420, 1626 ) );
	}

	protected function tearDown(): void {
		unset( $GLOBALS['cbuild_test_filters'] );
	}

	/**
	 * The build's state on a kit with these settings.
	 *
	 * @param array<string, mixed>|null $settings The kit's settings document.
	 * @param array<string, int>        $picks    The picks.
	 */
	private function state( ?array $settings, array $picks = self::PICKS ): State {
		$this->kits->add( self::KIT, Kit::fromDocuments( KitDocuments::slots(), KitDocuments::rules(), KitDocuments::presets(), KitDocuments::assembly(), null, null, $settings ), Shop::parts() );
		$state = ( new BuildState( $this->kits, new InMemoryShopSettings(), new FakeLogger() ) )->of( self::KIT, $picks, null, State::REVIEW );
		self::assertNotNull( $state );
		return $state;
	}

	/**
	 * The scene, which there is.
	 *
	 * @param State $state The build's state.
	 * @return array<string, mixed>
	 */
	private function scene( State $state ): array {
		$scene = ( new SceneView( new LayerArtwork( $this->images, $this->renditions ), $this->layers ) )->of( $state );
		self::assertIsArray( $scene );
		return $scene;
	}

	public function test_there_is_no_scene_where_the_kits_3d_view_is_off_or_no_deck_is_picked(): void {
		$view = new SceneView( new LayerArtwork( $this->images, $this->renditions ), $this->layers );
		self::assertNull( $view->of( $this->state( null ) ), 'a kit saved before 1.4.0' );
		self::assertNull( $view->of( $this->state( array( 'schema_version' => 1, 'view_3d' => false ) ) ) );
		self::assertSame( array(), $this->layers->asked, 'nothing asked of another plugin' );
		self::assertNull( $view->of( $this->state( array( 'schema_version' => 1, 'view_3d' => true ), array() ) ), 'no deck' );
	}

	public function test_the_scene_is_the_domains_with_each_drawn_parts_texture(): void {
		$scene = $this->scene( $this->state( array( 'schema_version' => 1, 'view_3d' => true ) ) );

		self::assertSame( 102, $scene['deck']['part'] );
		self::assertSame( 209550, $scene['deck']['width'], '8.25 in' );
		$made = $this->renditions->sources( 10, Renditions::drawn( SlotKey::DECK, Shop::parts()[ SlotKey::DECK ][1]->specs, 420, 1626 ) )['image/webp'];
		self::assertSame(
			array(
				'top'    => 'https://example.com/layer-70.png',
				'bottom' => $made[ count( $made ) - 1 ]['url'],
			),
			$scene['deck']['texture'],
			'the grip\'s own file, its renditions not made; the deck\'s largest rendition'
		);
		self::assertStringEndsWith( '.webp', $made[ count( $made ) - 1 ]['url'] );
		self::assertSame( 'https://example.com/layer-30.png', $scene['wheels']['texture'] );
		self::assertSame( 'https://example.com/layer-70.png', $scene['grip']['texture'] );
		self::assertSame( array( 601, 25400 ), array( $scene['bolts']['part'], $scene['bolts']['length'] ) );
		self::assertNull( $scene['risers'] );
		self::assertSame(
			array(
				array( 102, SlotKey::DECK, self::KIT, '3d' ),
				array( 302, SlotKey::WHEELS, self::KIT, '3d' ),
				array( 701, SlotKey::GRIP, self::KIT, '3d' ),
			),
			$this->sorted( $this->layers->asked ),
			'each drawn part with a layer image asked, for the 3D view'
		);
	}

	public function test_a_part_answered_other_than_as_it_is_has_no_texture(): void {
		$this->layers->answers = array(
			102 => array( '3d' => 'blurred' ),
			302 => array( '3d' => 'left_out' ),
			701 => array( 'cart' => 'left_out' ),
		);
		$scene = $this->scene( $this->state( array( 'schema_version' => 1, 'view_3d' => true ) ) );

		self::assertNull( $scene['deck']['texture']['bottom'], 'blurred: no sharp texture shows what the answer hid' );
		self::assertNull( $scene['wheels']['texture'], 'left out' );
		self::assertSame( 'https://example.com/layer-70.png', $scene['grip']['texture'], 'another use\'s answer is not the 3D view\'s' );
		self::assertSame( 'https://example.com/layer-70.png', $scene['deck']['texture']['top'] );
	}

	public function test_a_part_with_no_layer_image_and_a_slot_with_no_part_have_no_texture(): void {
		$this->images->attachments = array();
		$scene                     = $this->scene( $this->state( array( 'schema_version' => 1, 'view_3d' => true ), array( SlotKey::DECK => 102 ) ) );

		self::assertSame(
			array(
				'top'    => null,
				'bottom' => null,
			),
			$scene['deck']['texture']
		);
		self::assertSame( array( null, null, null ), array( $scene['trucks'], $scene['wheels'], $scene['grip'] ) );
		self::assertSame( array(), $this->layers->asked );
	}

	public function test_a_plugin_answers_last_and_an_answer_that_is_no_scene_is_passed_over(): void {
		$asked = array();
		$GLOBALS['cbuild_test_filters']['cbuild_scene'] = static function ( array $scene, int $kit, array $parts ) use ( &$asked ): array {
			$asked                    = array( $kit, $parts );
			$scene['trucks']['color'] = '#b8bcc2';
			return $scene;
		};
		$scene = $this->scene( $this->state( array( 'schema_version' => 1, 'view_3d' => true ) ) );
		self::assertSame( '#b8bcc2', $scene['trucks']['color'] );
		self::assertSame( array( self::KIT, self::PICKS ), $asked, 'the kit, and the parts it was measured from' );

		foreach ( array( 'not a scene', array( 'deck' => 'none' ), array() ) as $answer ) {
			$GLOBALS['cbuild_test_filters']['cbuild_scene'] = static fn(): mixed => $answer;
			$scene = $this->scene( $this->state( array( 'schema_version' => 1, 'view_3d' => true ) ) );
			self::assertSame( 102, $scene['deck']['part'], 'the domain\'s scene kept' );
		}
	}

	/**
	 * What was asked, by part.
	 *
	 * @param list<array{int, string, int, string}> $asked What was asked.
	 * @return list<array{int, string, int, string}>
	 */
	private function sorted( array $asked ): array {
		usort( $asked, static fn( array $a, array $b ): int => $a[0] <=> $b[0] );
		return $asked;
	}
}
