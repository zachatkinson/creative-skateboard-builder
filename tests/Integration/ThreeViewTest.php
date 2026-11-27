<?php
/**
 * Milestone 14, the 3D view (#139, D-495, D-497): the view's `three` says whether the kit offers it, and its template
 * renders only then, with every word the server's.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Front\Builder;
use CreativeBranding\SkateboardBuilder\Front\BuildLink;
use CreativeBranding\SkateboardBuilder\Service\SpecIndexer;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\Domain\KitDocuments;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\KitFactory;
use WC_Product_Simple;

/**
 * The 3D view's switch, its view and its template.
 */
final class ThreeViewTest extends IntegrationTestCase {

	/**
	 * The kit's slots, the deck's from a category of its own.
	 *
	 * @var array<string, mixed>
	 */
	private array $slots = array();

	/**
	 * The deck's ID.
	 */
	private int $deck = 0;

	public function set_up(): void {
		parent::set_up();
		$category    = self::factory()->term->create( array( 'taxonomy' => 'product_cat' ) );
		$this->slots = KitDocuments::slots();
		foreach ( $this->slots['slots'] as $at => $slot ) {
			if ( 'deck' === $slot['key'] ) {
				$this->slots['slots'][ $at ]['sources'] = array( 'categories' => array( $category ) );
			}
		}
		$deck = new WC_Product_Simple();
		$deck->set_name( 'deck 8.25' );
		$deck->set_status( 'publish' );
		$deck->set_regular_price( '65' );
		$deck->update_meta_data(
			Options::META_SPECS,
			array(
				'deck_width'   => '8.2500',
				'deck_length'  => '32.0000',
				'hole_pattern' => 'new_school',
			)
		);
		$deck->save();
		wp_set_object_terms( $deck->get_id(), array( $category ), 'product_cat' );
		$this->service( SpecIndexer::class )->index( $deck->get_id() );
		$this->deck = $deck->get_id();
	}

	public function tear_down(): void {
		remove_all_filters( 'cbuild_part_classes' );
		parent::tear_down();
	}

	/**
	 * The view of a kit with these settings.
	 *
	 * @param array<string, mixed>|null $settings The settings document's keys past its version, or null for none saved.
	 * @param array<string, string>     $sent     The build's link.
	 * @return array<string, mixed>
	 */
	private function view( ?array $settings, array $sent = array() ): array {
		$documents = array( Options::META_KIT_SLOTS => $this->slots );
		if ( null !== $settings ) {
			$documents[ Options::META_KIT_SETTINGS ] = array( 'schema_version' => 1 ) + $settings;
		}
		$kit   = KitFactory::kit( 'Kit', $documents )->get_id();
		$shown = $this->service( Builder::class )->show( $kit, BuildLink::read( $sent ), 'https://example.org/build/' );
		self::assertNotNull( $shown );
		return $shown['view'];
	}

	/**
	 * The template's output for a view.
	 *
	 * @param array<string, mixed> $view The view.
	 */
	private static function rendered( array $view ): string {
		$shown = array( 'view' => $view );
		ob_start();
		require CBUILD_PATH . 'templates/builder/three-open.php';
		require CBUILD_PATH . 'templates/builder/three.php';
		return (string) ob_get_clean();
	}

	public function test_the_view_is_offered_only_where_the_kit_turns_it_on(): void {
		foreach ( array(
			'no settings saved'     => array( null, false, false ),
			'saved without the key' => array( array(), false, false ),
			'off'                   => array( array( 'view_3d' => false ), false, false ),
			'on'                    => array( array( 'view_3d' => true ), true, true ),
			'on, tricks off'        => array(
				array(
					'view_3d' => true,
					'tricks'  => false,
				),
				true,
				false,
			),
		) as $case => [ $settings, $shown, $tricks ] ) {
			$three = $this->view( $settings )['three'];
			self::assertSame( $shown, $three['shown'], $case );
			self::assertSame( $tricks, $three['tricks'], $case );
			self::assertSame( 'cbuild-3d__canvas', $three['canvas'], $case );
		}
	}

	public function test_the_canvas_takes_the_classes_a_plugin_gives_the_deck(): void {
		$deck = $this->deck;
		add_filter(
			'cbuild_part_classes',
			static fn( array $classes, int $part, string $slot ): array => $deck === $part && 'deck' === $slot ? array_merge( $classes, array( 'mature' ) ) : $classes,
			10,
			3
		);
		self::assertSame( 'cbuild-3d__canvas mature', $this->view( array( 'view_3d' => true ), array( 'deck' => (string) $deck ) )['three']['canvas'] );
	}

	public function test_the_template_renders_the_view_only_where_it_is_offered(): void {
		self::assertSame( '', self::rendered( $this->view( array() ) ) );
		$html = self::rendered( $this->view( array( 'view_3d' => true ) ) );
		self::assertStringContainsString( '>View in 3D</button>', $html );
		self::assertStringContainsString( 'class="cbuild-3d__canvas" tabindex="0" role="img"', $html );
		self::assertStringContainsString( 'aria-describedby="cbuild-3d-keys cbuild-3d-scale"', $html );
		self::assertStringContainsString( 'Sizes true to scale, shapes simplified.', $html );
		foreach ( array( 'reset', 'top', 'under', 'graphic' ) as $place ) {
			self::assertStringContainsString( 'value="' . $place . '"', $html );
		}
		self::assertStringContainsString( 'The 3D view can’t be shown here, so the board is drawn flat.', $html );
	}

	public function test_the_template_renders_the_trick_buttons_only_with_the_kits_trick_buttons_on(): void {
		$html = self::rendered( $this->view( array( 'view_3d' => true ) ) );
		self::assertStringContainsString( 'class="cbuild-3d__tricks" role="group" aria-label="Tricks"', $html );
		$names = array(
			'ollie'        => 'Ollie',
			'kickflip'     => 'Kickflip',
			'heelflip'     => 'Heelflip',
			'pop-shove-it' => 'Pop shove-it',
			'360-flip'     => '360 flip',
		);
		foreach ( $names as $value => $name ) {
			self::assertMatchesRegularExpression( '~<button class="cbuild-button" type="button" value="' . $value . '" data-wp-bind--aria-disabled="state\.three\.playing" data-wp-on--click="actions\.trick">' . preg_quote( $name, '~' ) . '</button>~', $html );
		}
		// In the server's order, the order the view's tricks are in.
		self::assertSame( 5, preg_match_all( '~value="(' . implode( '|', array_keys( $names ) ) . ')"~', $html, $found ) );
		self::assertSame( array_keys( $names ), $found[1] );
		$off = self::rendered(
			$this->view(
				array(
					'view_3d' => true,
					'tricks'  => false,
				)
			)
		);
		self::assertStringContainsString( '>View in 3D</button>', $off );
		self::assertStringNotContainsString( 'cbuild-3d__tricks', $off );
		self::assertStringNotContainsString( 'actions.trick', $off );
	}

	/**
	 * The auditor's M14 review, F13: the note said "Sizes true to scale" where a usual value stood in for a size. The
	 * server picks the words by whether anything was assumed, and the store's path for them reads the same in PHP as
	 * in the browser (D-484).
	 */
	public function test_the_note_says_usual_sizes_stood_in_where_any_was_assumed(): void {
		$true    = 'Sizes true to scale, shapes simplified.';
		$usual   = 'Sizes to scale, with usual sizes where a part lists none. Shapes simplified.';
		$view    = $this->view( array( 'view_3d' => true ), array( 'deck' => (string) $this->deck ) );
		$assumed = $view['scene']['assumed'] ?? array();
		self::assertIsArray( $assumed );
		self::assertNotSame( array(), $assumed, 'The deck has no wheelbase, so its usual one stands in.' );
		$exact                     = $view;
		$exact['scene']['assumed'] = array();
		foreach ( array(
			'assumed' => array( $view, $usual, $true ),
			'exact'   => array( $exact, $true, $usual ),
		) as $case => [ $shown_view, $said, $unsaid ] ) {
			$html = self::rendered( $shown_view );
			self::assertMatchesRegularExpression( '~<span data-wp-bind--hidden="!?state\.view\.scene\.assumed\.0">' . preg_quote( $said, '~' ) . '</span>~', $html, $case );
			self::assertMatchesRegularExpression( '~<span hidden data-wp-bind--hidden="!?state\.view\.scene\.assumed\.0">' . preg_quote( $unsaid, '~' ) . '</span>~', $html, $case );
			// The directives, as WordPress processes them on the page, keep the server's choice.
			// A store of its own a case: WordPress merges each state given into the one before.
			wp_interactivity_state( 'cbuild-note-' . $case, array( 'view' => $shown_view ) );
			$processed = wp_interactivity_process_directives( '<div data-wp-interactive="cbuild-note-' . $case . '">' . $html . '</div>' );
			self::assertMatchesRegularExpression( '~<span\s+data-wp-bind--hidden="[^"]*">' . preg_quote( $said, '~' ) . '</span>~', $processed, $case );
			self::assertMatchesRegularExpression( '~<span hidden[^>]*>' . preg_quote( $unsaid, '~' ) . '</span>~', $processed, $case );
		}
	}
}
