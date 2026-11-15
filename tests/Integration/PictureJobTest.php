<?php
/**
 * The composed picture's job, asked for by an add to the cart and run by Action Scheduler.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\WpPictureFiles;
use CreativeBranding\SkateboardBuilder\Hooks\PictureHooks;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\CartTestCase;
use CreativeBranding\SkateboardBuilder\Tests\Support\LayerFiles;

/**
 * Spec, Options and jobs: "`cbuild_compose_picture` (one build's composed image)". Plan, milestone 7: "A composed
 * picture is asked for, never waited on. These ask for one: an add to the cart; an Edit build that replaces." Risk 2:
 * "Composition only in Action Scheduler, a test asserting no page request composes" (D-197).
 */
final class PictureJobTest extends CartTestCase {

	public function set_up(): void {
		parent::set_up();
		$this->service( PictureHooks::class )->register();
		$deck = wc_get_product( $this->parts['deck'] );
		$deck->update_meta_data( Options::META_LAYER, (string) LayerFiles::attachment( 420, 1626 ) );
		$deck->save();
	}

	public function tear_down(): void {
		as_unschedule_all_actions( Options::JOB_COMPOSE_PICTURE, array(), Options::QUEUE_GROUP );
		foreach ( (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) as $file ) {
			wp_delete_file( (string) $file );
		}
		parent::tear_down();
	}

	/**
	 * The files in the pictures' folder.
	 *
	 * @return list<string>
	 */
	private static function files(): array {
		return array_map( 'basename', array_map( 'strval', (array) glob( wp_get_upload_dir()['basedir'] . '/' . WpPictureFiles::FOLDER . '/*' ) ) );
	}

	public function test_an_add_to_the_cart_asks_for_the_builds_pictures_once_and_the_job_draws_them(): void {
		$body = array(
			'id'     => $this->kit,
			'cbuild' => array( 'picks' => $this->picks() ),
		);
		self::assertSame( 201, $this->store( $body )->get_status() );
		self::assertSame( 201, $this->store( $body )->get_status(), 'the same build again' );
		$this->form( array( 'picks' => array_map( 'strval', $this->picks() ) ) );
		self::assertSame(
			array(
				array(
					'kit'   => $this->kit,
					'picks' => $this->picks(),
				),
			),
			self::waiting( Options::JOB_COMPOSE_PICTURE ),
			'one job for the build, with its IDs alone, whichever route added it'
		);
		self::assertSame( array(), self::files(), 'no request draws a picture' );

		self::work();
		self::assertCount( 3, self::files(), 'one for the cart, one for a share card and one for the emails' );
		$pictures = $this->service( ComposedPictures::class );
		foreach ( array_keys( ComposedPictures::CANVASES ) as $use ) {
			$url = (string) $pictures->url( $this->kit, $this->picks(), $use );
			self::assertContains( basename( $url ), self::files(), $use );
		}
		self::assertSame( array(), self::waiting( Options::JOB_COMPOSE_PICTURE ) );
	}

	public function test_edit_builds_add_asks_for_the_edited_build(): void {
		self::assertSame( 201, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		$first = self::builds()[0]['build'];
		$risen = $this->picks() + array( 'risers' => $this->parts['risers'] );
		self::assertSame(
			201,
			$this->store(
				array(
					'id'     => $this->kit,
					'cbuild' => array(
						'picks'   => $risen,
						'replace' => $first->id,
					),
				)
			)->get_status()
		);
		self::assertCount( 2, self::waiting( Options::JOB_COMPOSE_PICTURE ), 'the build as it was, and as it was edited' );
		self::assertContains( self::builds()[0]['build']->picks, array_column( self::waiting( Options::JOB_COMPOSE_PICTURE ), 'picks' ) );
	}

	public function test_a_refused_add_asks_for_nothing(): void {
		$sold = wc_get_product( $this->parts['wheels'] );
		$sold->set_stock_status( 'outofstock' );
		$sold->save();
		self::assertSame( 400, $this->store( array( 'id' => $this->kit, 'cbuild' => array( 'picks' => $this->picks() ) ) )->get_status() );
		$this->form( array( 'picks' => $this->picks() ) );
		self::assertSame( array(), self::waiting( Options::JOB_COMPOSE_PICTURE ) );
	}
}
