<?php
/**
 * WooCommerce > Builder, Import and export, on real WordPress.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Integration;

use CreativeBranding\SkateboardBuilder\Adapter\RequestUploads;
use CreativeBranding\SkateboardBuilder\Admin\BuilderHooks;
use CreativeBranding\SkateboardBuilder\Admin\ImportScreen;
use CreativeBranding\SkateboardBuilder\Domain\Spec\SpecTable;
use CreativeBranding\SkateboardBuilder\Hooks\ImportHooks;
use CreativeBranding\SkateboardBuilder\Install\Deactivator;
use CreativeBranding\SkateboardBuilder\Port\ImportStore;
use CreativeBranding\SkateboardBuilder\Port\PartSpecs;
use CreativeBranding\SkateboardBuilder\Port\SpecTables;
use CreativeBranding\SkateboardBuilder\Port\UploadRefused;
use CreativeBranding\SkateboardBuilder\Port\Uploads;
use CreativeBranding\SkateboardBuilder\Scope;
use CreativeBranding\SkateboardBuilder\Service\Csv;
use CreativeBranding\SkateboardBuilder\Service\ImportState;
use CreativeBranding\SkateboardBuilder\Service\IndexJobs;
use CreativeBranding\SkateboardBuilder\Service\SpecExport;
use CreativeBranding\SkateboardBuilder\Service\SpecImport;
use CreativeBranding\SkateboardBuilder\Service\SpecSheet;
use CreativeBranding\SkateboardBuilder\Support\Options;
use CreativeBranding\SkateboardBuilder\Tests\Support\IntegrationTestCase;

/**
 * Spec, Admin, Import and export: specs as CSV, a row per part, keyed on the ID or the SKU; an import first shows
 * what it would change and any rows it can't read, and writes nothing until confirmed. Audit, Security: a capability
 * and a nonce on every action, an exported cell a spreadsheet would run is neutralised, and the file is never moved
 * into the site.
 */
final class ImportScreenTest extends IntegrationTestCase {

	private const FIELDS = array( 'cbuild_action', 'cbuild_token', 'cbuild_key', '_wpnonce' );

	public function tear_down(): void {
		foreach ( self::FIELDS as $field ) {
			unset( $_POST[ $field ], $_REQUEST[ $field ] );
		}
		unset( $_GET['tab'], $_GET['notice'], $_FILES[ ImportScreen::FILE ] );
		delete_option( 'woocommerce_price_decimal_sep' );
		delete_option( Options::IMPORT );
		parent::tear_down();
	}

	private function as( string $role ): void {
		wp_set_current_user( self::factory()->user->create( array( 'role' => $role ) ) );
	}

	/**
	 * The screen, with this text as the file sent, or this reason for there being none.
	 *
	 * @param string $text   The file's text.
	 * @param string $refuse Why the upload is refused, '' when it is not.
	 */
	private function screen( string $text = '', string $refuse = '' ): ImportScreen {
		$uploads = new class( $text, $refuse ) implements Uploads {
			public function __construct( private string $text, private string $refuse ) {}
			public function csv( string $field, int $most ): string {
				if ( '' !== $this->refuse || ImportScreen::FILE !== $field || SpecImport::MAX_BYTES !== $most ) {
					throw new UploadRefused( $this->refuse );
				}
				return $this->text;
			}
		};
		return new ImportScreen( $this->service( SpecImport::class ), $this->service( SpecExport::class ), $uploads );
	}

	/**
	 * Set a form's fields, slashed as PHP's superglobals are in WordPress.
	 *
	 * @param array<string, string> $fields The form's fields.
	 * @param bool                  $nonce  Whether the form's nonce is sent.
	 */
	private static function post( array $fields, bool $nonce = true ): void {
		foreach ( self::FIELDS as $field ) {
			unset( $_POST[ $field ], $_REQUEST[ $field ] );
		}
		foreach ( wp_slash( $fields ) as $name => $value ) {
			$_POST[ $name ] = $value;
		}
		if ( $nonce ) {
			$_REQUEST['_wpnonce'] = wp_create_nonce( ImportScreen::NONCE );
		}
	}

	/**
	 * Send a file to be checked.
	 *
	 * @param string $text The file's text.
	 * @param string $key  What parts are matched by.
	 * @return array{0: ImportScreen, 1: string|null}
	 */
	private function read( string $text, string $key = 'id' ): array {
		self::post(
			array(
				'cbuild_action' => 'read',
				'cbuild_key'    => $key,
			)
		);
		$screen = $this->screen( $text );
		return array( $screen, $screen->handle() );
	}

	/**
	 * What the tab draws.
	 *
	 * @param ImportScreen $screen The screen.
	 * @param string       $notice The notice the address asks for.
	 */
	private static function drawn( ImportScreen $screen, string $notice = '' ): string {
		unset( $_GET['notice'] );
		if ( '' !== $notice ) {
			$_GET['notice'] = $notice;
		}
		ob_start();
		$screen->render();
		return (string) ob_get_clean();
	}

	/**
	 * A simple product.
	 *
	 * @param string                $sku    Its SKU.
	 * @param array<string, string> $fields Its own specs.
	 * @param string                $name   Its name.
	 */
	private static function part( string $sku, array $fields = array(), string $name = 'Katie 7.75' ): \WC_Product_Simple {
		$product = new \WC_Product_Simple();
		$product->set_name( $name );
		$product->set_sku( $sku );
		$product->set_regular_price( '10' );
		if ( array() !== $fields ) {
			$product->update_meta_data( Options::META_SPECS, $fields );
		}
		$product->save();
		return $product;
	}

	/**
	 * A part's own specs, read from the database.
	 *
	 * @param int $id The part.
	 * @return mixed
	 */
	private static function stored( int $id ) {
		return wc_get_product( $id )->get_meta( Options::META_SPECS, true );
	}

	private function chart(): int {
		return $this->service( SpecTables::class )->create(
			'Independent',
			SpecTable::read(
				array(
					array(
						'size'  => '149',
						'specs' => array( 'axle_width' => '8.5000' ),
					),
				)
			)
		);
	}

	public function test_the_page_has_a_tab_for_the_charts_and_one_for_the_import_and_the_job_runs_anywhere(): void {
		$built = static fn( array $scopes ): array => array_map( 'get_class', ( new \CreativeBranding\SkateboardBuilder\Container() )->hookablesFor( ...$scopes ) );
		self::assertContains( ImportHooks::class, $built( Scope::atBoot( false, false, false ) ) );
		self::assertSame( 10, has_action( Options::JOB_IMPORT, array( $this->service( ImportHooks::class ), 'run' ) ) );

		$this->as( 'shop_manager' );
		$hooks = $this->service( BuilderHooks::class );
		ob_start();
		$hooks->render();
		$charts = (string) ob_get_clean();
		self::assertStringContainsString( '<nav class="nav-tab-wrapper" aria-label="Builder">', $charts );
		self::assertMatchesRegularExpression( '#<a\s+class="nav-tab nav-tab-active"\s+href="[^"]*admin\.php\?page=cbuild"\s+aria-current="page"\s*>Spec tables</a>#', $charts );
		self::assertMatchesRegularExpression( '#<a\s+class="nav-tab"\s+href="[^"]*admin\.php\?page=cbuild&\#038;tab=import"\s*>Import and export</a>#', $charts );
		self::assertStringContainsString( 'Add a chart', $charts );

		$_GET['tab'] = 'import';
		ob_start();
		$hooks->render();
		$import = (string) ob_get_clean();
		self::assertMatchesRegularExpression( '#<a\s+class="nav-tab nav-tab-active"\s+href="[^"]*tab=import"\s+aria-current="page"\s*>Import and export</a>#', $import );
		self::assertStringNotContainsString( 'Add a chart', $import );
		self::assertStringContainsString( '<h2>Export</h2>', $import );
		self::assertSame( 1, substr_count( $import, 'aria-current="page"' ) );
	}

	public function test_the_export_is_a_row_for_every_product_and_variation_with_what_each_holds(): void {
		$chart = $this->chart();
		$deck  = self::part( 'DK-7750', array( 'deck_width' => '7.7500' ) );
		$evil  = self::part( '=1+1', array(), '@SUM(A1)' );
		$trucks = new \WC_Product_Variable();
		$trucks->set_name( 'Stage 11' );
		$trucks->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => $chart,
				'size'  => '',
			)
		);
		$trucks->save();
		$size = new \WC_Product_Variation();
		$size->set_parent_id( $trucks->get_id() );
		$size->set_sku( 'TR-149' );
		$size->update_meta_data(
			Options::META_SPEC_TABLE,
			array(
				'table' => 0,
				'size'  => '149',
			)
		);
		$size->save();

		$this->as( 'shop_manager' );
		self::post( array( 'cbuild_action' => 'export' ) );
		$file = $this->screen()->download();
		self::assertIsString( $file );
		self::assertStringStartsWith( "\xEF\xBB\xBFid,sku,name,spec_table,spec_size,layer_image,deck_width,", $file );
		$rows = array();
		foreach ( array_slice( Csv::parse( substr( $file, 3 ) ), 1 ) as $row ) {
			$rows[ (int) $row[0] ] = array_combine( SpecSheet::columns(), $row );
		}
		self::assertSame( array( $deck->get_id(), $evil->get_id(), $trucks->get_id(), $size->get_id() ), array_keys( $rows ) );
		self::assertSame( 'DK-7750', $rows[ $deck->get_id() ]['sku'] );
		self::assertSame( 'Katie 7.75', $rows[ $deck->get_id() ]['name'] );
		self::assertSame( '7.7500', $rows[ $deck->get_id() ]['deck_width'] );
		self::assertSame( 'Independent', $rows[ $trucks->get_id() ]['spec_table'] );
		self::assertSame( '', $rows[ $trucks->get_id() ]['spec_size'] );
		self::assertSame( '', $rows[ $size->get_id() ]['spec_table'] );
		self::assertSame( '149', $rows[ $size->get_id() ]['spec_size'] );
		self::assertSame( 'TR-149', $rows[ $size->get_id() ]['sku'] );
		// Spec, Security: a cell that starts with =, +, - or @ is neutralised, so a spreadsheet can't run it.
		self::assertStringContainsString( $evil->get_id() . ",'=1+1,'@SUM(A1),", $file );

		// Read back, it changes nothing.
		[ $screen ] = $this->read( $file );
		self::assertStringContainsString( '0 parts would change.', self::drawn( $screen ) );
		self::assertStringContainsString( '4 rows are the same as the shop has now.', self::drawn( $screen ) );
		self::assertStringContainsString( '0 rows cannot be read', self::drawn( $screen ) );
		self::assertStringNotContainsString( 'value="confirm"', self::drawn( $screen ) );
	}

	public function test_a_file_is_shown_before_anything_is_written_and_written_once_confirmed(): void {
		$deck  = self::part( 'DK-7750', array( 'deck_width' => '7.7500' ) );
		$other = self::part( 'DK-8000', array(), 'Indy & "Co" <b>' );
		$this->as( 'shop_manager' );
		update_option( 'woocommerce_price_decimal_sep', ',' );

		[ $screen, $next ] = $this->read( "id;deck_width;wheelbase\n{$deck->get_id()};8,25;14 1/4\n{$other->get_id()};wide;\n999999;8;\n" );
		self::assertSame( ImportScreen::url(), $next );
		self::assertStringContainsString( 'tab=import', (string) $next );
		self::assertSame( array( 'deck_width' => '7.7500' ), self::stored( $deck->get_id() ) );
		self::assertSame( array(), self::waiting( Options::JOB_IMPORT ) );

		$page = self::drawn( $this->screen() );
		self::assertStringContainsString( '<h2>Check the import</h2>', $page );
		self::assertStringContainsString( 'Nothing has been changed yet.', $page );
		self::assertStringContainsString( '1 part would change.', $page );
		self::assertStringContainsString( '0 rows are the same as the shop has now.', $page );
		self::assertStringContainsString( '2 rows cannot be read, and would be left out.', $page );
		self::assertMatchesRegularExpression( '#<td>2</td>\s*<td>Katie 7\.75</td>\s*<td>Deck width</td>\s*<td>7\.75 in</td>\s*<td>8\.25 in</td>#', $page );
		self::assertMatchesRegularExpression( '#<td>2</td>\s*<td>Katie 7\.75</td>\s*<td>Wheelbase</td>\s*<td>Nothing</td>\s*<td>14\.25 in</td>#', $page );
		self::assertMatchesRegularExpression( '#<td>3</td>\s*<td>Deck width</td>\s*<td>&quot;wide&quot; is not a measurement\. Use a number, or a fraction such as 8 1/4\.</td>#', $page );
		self::assertMatchesRegularExpression( '#<td>4</td>\s*<td>ID</td>\s*<td>No product is &quot;999999&quot;\.</td>#', $page );
		self::assertStringContainsString( 'role="region" aria-labelledby="cbuild-wrong" tabindex="0"', $page );
		self::assertStringContainsString( 'role="region" aria-labelledby="cbuild-changes" tabindex="0"', $page );
		self::assertSame( 3, substr_count( $page, 'name="_wpnonce"' ) );
		$state = $this->service( SpecImport::class )->state();
		self::assertNotNull( $state );
		self::assertStringContainsString( 'name="cbuild_token" value="' . $state->token . '"', $page );

		// Confirmed by a token that is not this import's: nothing starts.
		self::post(
			array(
				'cbuild_action' => 'confirm',
				'cbuild_token'  => str_repeat( 'a', 64 ),
			)
		);
		self::assertSame( ImportScreen::url( array( 'notice' => 'stale' ) ), $this->screen()->handle() );
		self::assertSame( array(), self::waiting( Options::JOB_IMPORT ) );
		self::assertStringContainsString( 'That import is no longer waiting. Choose the file again.', self::drawn( $this->screen(), 'stale' ) );

		self::post(
			array(
				'cbuild_action' => 'confirm',
				'cbuild_token'  => $state->token,
			)
		);
		self::assertSame( ImportScreen::url( array( 'notice' => 'started' ) ), $this->screen()->handle() );
		self::assertCount( 1, self::waiting( Options::JOB_IMPORT ) );
		self::assertSame( array( 'deck_width' => '7.7500' ), self::stored( $deck->get_id() ) );
		$page = self::drawn( $this->screen(), 'started' );
		self::assertStringContainsString( 'The import has started.', $page );
		self::assertStringContainsString( '0 of 3 rows done.', $page );
		self::assertStringNotContainsString( 'type="file"', $page );
		self::assertStringContainsString( '<h2>Export</h2>', $page );

		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own job.
		do_action( Options::JOB_IMPORT );
		self::assertSame(
			array(
				'deck_width' => '8.2500',
				'wheelbase'  => '14.2500',
			),
			self::stored( $deck->get_id() )
		);
		self::assertSame( '', self::stored( $other->get_id() ) );
		// The part that was written is indexed again, as any saved product is.
		self::assertContains( array( 'part' => $deck->get_id() ), self::waiting( IndexJobs::JOB_PART ) );

		$page = self::drawn( $this->screen() );
		self::assertStringContainsString( '<h2>The last import</h2>', $page );
		self::assertStringContainsString( '1 part was changed.', $page );
		self::assertStringContainsString( '2 rows could not be read, and were left out.', $page );
		self::assertStringContainsString( 'No product is &quot;999999&quot;.', $page );
		self::assertStringContainsString( 'type="file"', $page );
		self::assertSame( array(), ImportState::fromArray( $this->service( ImportStore::class )->load() )?->rows );
	}

	public function test_what_a_file_says_is_escaped_where_it_is_shown(): void {
		$this->chart();
		$part = self::part( 'A', array(), 'Indy & <b>Co</b>' );
		$this->as( 'administrator' );
		[ $screen ] = $this->read( "id,spec_table,wheelbase\n{$part->get_id()},\"<script>alert(1)</script>\",14\n\"<img src=x>\",,1\n" );
		$page       = self::drawn( $screen );
		self::assertStringNotContainsString( '<script>alert', $page );
		self::assertStringNotContainsString( '<img', $page );
		self::assertStringContainsString( 'There is no chart named &quot;&lt;script&gt;alert(1)&lt;/script&gt;&quot;.', $page );

		[ $screen ] = $this->read( "id,wheelbase\n{$part->get_id()},14\n" );
		self::assertStringContainsString( '<td>Indy &amp; &lt;b&gt;Co&lt;/b&gt;</td>', self::drawn( $screen ) );
	}

	public function test_rows_are_matched_by_sku_and_a_chart_is_named_by_its_name(): void {
		$chart = $this->chart();
		$part  = self::part( 'TR-149' );
		$this->as( 'shop_manager' );
		[ $screen ] = $this->read( "sku,spec_table,spec_size\nTR-149,Independent,149\nTR-000,Independent,149\n", 'sku' );
		$page       = self::drawn( $screen );
		self::assertMatchesRegularExpression( '#<td>Maker&\#039;s chart</td>\s*<td>Nothing</td>\s*<td>Independent</td>#', $page );
		self::assertMatchesRegularExpression( '#<td>Size in the chart</td>\s*<td>Nothing</td>\s*<td>149</td>#', $page );
		self::assertMatchesRegularExpression( '#<td>3</td>\s*<td>SKU</td>\s*<td>No product is &quot;TR-000&quot;\.</td>#', $page );

		$import = $this->service( SpecImport::class );
		self::assertTrue( $import->confirm( (string) $import->state()?->token, get_current_user_id() ) );
		$import->run();
		self::assertSame(
			array(
				'table' => $chart,
				'size'  => '149',
			),
			wc_get_product( $part->get_id() )->get_meta( Options::META_SPEC_TABLE, true )
		);
	}


	// M14's audit, S9: no test imported the 3D view's specs. A sheet with `nose_length`, `kick_angle` and `wheel_width`
	// columns is imported, and each part's row in the specs index holds them, from the part's own fields.
	public function test_the_3d_views_specs_are_imported_and_read_back_from_the_index(): void {
		$deck  = self::part( 'DK-8250', array( 'deck_width' => '8.2500' ) );
		$wheel = self::part( 'WH-54', array( 'wheel_diameter' => '54.0000' ), 'Spitfire 54' );
		$this->as( 'shop_manager' );
		[ $screen ] = $this->read( "id,nose_length,kick_angle,wheel_width\n{$deck->get_id()},7 1/4,21,\n{$wheel->get_id()},,,32\n" );
		self::assertStringContainsString( '2 parts would change.', self::drawn( $screen ) );
		$import = $this->service( SpecImport::class );
		self::assertTrue( $import->confirm( (string) $import->state()?->token, get_current_user_id() ) );
		$import->run();
		self::assertSame(
			array(
				'deck_width'  => '8.2500',
				'nose_length' => '7.2500',
				'kick_angle'  => '21',
			),
			self::stored( $deck->get_id() )
		);

		$indexer = $this->service( \CreativeBranding\SkateboardBuilder\Service\SpecIndexer::class );
		$indexer->index( $deck->get_id() );
		$indexer->index( $wheel->get_id() );
		$index = $this->service( \CreativeBranding\SkateboardBuilder\Repository\SpecIndexRepository::class );
		$rows  = static fn( int $part ): array => array_combine(
			array_map( static fn( $row ): string => $row->key->value, $index->rowsOf( $part ) ),
			array_map( static fn( $row ): array => array( $row->text, $row->origin->value ), $index->rowsOf( $part ) )
		);
		$deckRows = $rows( $deck->get_id() );
		self::assertSame( array( '7.2500', 'field' ), $deckRows['nose_length'] );
		self::assertSame( array( '21', 'field' ), $deckRows['kick_angle'] );
		self::assertArrayNotHasKey( 'wheel_width', $deckRows, 'an empty cell writes nothing' );
		self::assertSame( array( '32.0000', 'field' ), $rows( $wheel->get_id() )['wheel_width'] );
		// And the index reads them as the specs the 3D view is drawn from.
		$specs = $index->specsOf( $deck->get_id(), $wheel->get_id() );
		self::assertTrue( $specs[ $deck->get_id() ]->has( \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey::NoseLength ) );
		self::assertSame( 21, $specs[ $deck->get_id() ]->count( \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey::KickAngle ) );
		self::assertTrue( $specs[ $wheel->get_id() ]->has( \CreativeBranding\SkateboardBuilder\Domain\Spec\SpecKey::WheelWidth ) );
	}

	public function test_a_file_that_is_refused_says_why_beside_the_field_and_nothing_waits(): void {
		$this->as( 'shop_manager' );
		[ $screen, $next ] = $this->read( "sku,name\nA,B\n" );
		self::assertNull( $next );
		self::assertNull( $this->service( ImportStore::class )->load() );
		$page = self::drawn( $screen );
		self::assertStringContainsString( '<div class="notice notice-error"><p>The file was not read. Nothing has changed.</p></div>', $page );
		self::assertStringContainsString( 'id="cbuild-file-problem"', $page );
		self::assertStringContainsString( 'The file has no column for what parts are matched by.', $page );
		self::assertStringContainsString( 'The file has no spec columns, so there is nothing to import.', $page );
		self::assertMatchesRegularExpression( '#<input\s+type="file"\s+id="cbuild-file"\s+name="cbuild_file"\s+accept="\.csv,text/csv"\s+aria-invalid="true" aria-describedby="cbuild-file-problem"#', $page );
		self::assertStringContainsString( '<label for="cbuild-file">', $page );
		self::assertStringContainsString( '<label for="cbuild-key">', $page );
		self::assertStringContainsString( 'enctype="multipart/form-data"', $page );

		$sentences = array(
			UploadRefused::MISSING => 'Choose a CSV file first.',
			UploadRefused::FAILED  => 'The file did not arrive whole.',
			UploadRefused::NOT_CSV => 'That is not a .csv file.',
			UploadRefused::BIG     => 'The file is over 2 MB.',
		);
		foreach ( $sentences as $code => $sentence ) {
			self::post( array( 'cbuild_action' => 'read' ) );
			$screen = $this->screen( '', $code );
			self::assertNull( $screen->handle() );
			self::assertStringContainsString( $sentence, self::drawn( $screen ), $code );
		}
		foreach ( array( "id,deck_width\n" => 'The file has no rows under its headings.', "id,deck_width\n1,\xC3\x28\n" => 'The file is not saved as UTF-8.', "id,deck_width,deck_width\n1,2,3\n" => 'A column is in the file twice.', "id,deck_width\n" . str_repeat( "1,8\n", SpecImport::MAX_ROWS + 1 ) => 'The file has more than 5,000 rows.' ) as $text => $sentence ) {
			[ $screen ] = $this->read( $text );
			self::assertStringContainsString( $sentence, self::drawn( $screen ) );
		}
		self::assertNull( $this->service( ImportStore::class )->load() );

		// A clean page has no problem on the field.
		$page = self::drawn( $this->screen() );
		self::assertStringNotContainsString( 'aria-invalid', $page );
		self::assertStringNotContainsString( 'notice-error', $page );
	}

	public function test_a_waiting_import_is_put_away_and_one_that_runs_refuses_another_file(): void {
		$part = self::part( 'A' );
		$this->as( 'shop_manager' );
		$this->read( "id,wheelbase\n{$part->get_id()},14\n" );
		self::post( array( 'cbuild_action' => 'discard' ) );
		self::assertSame( ImportScreen::url( array( 'notice' => 'discarded' ) ), $this->screen()->handle() );
		self::assertNull( $this->service( ImportStore::class )->load() );
		self::assertStringContainsString( 'The import was put away. Nothing was changed.', self::drawn( $this->screen(), 'discarded' ) );
		self::assertSame( '', self::stored( $part->get_id() ) );

		$this->read( "id,wheelbase\n{$part->get_id()},14\n" );
		$import = $this->service( SpecImport::class );
		self::assertTrue( $import->confirm( (string) $import->state()?->token, get_current_user_id() ) );
		[ $screen, $next ] = $this->read( "id,wheelbase\n{$part->get_id()},15\n" );
		self::assertNull( $next );
		self::assertStringContainsString( 'An import is running. Wait for it to finish.', self::drawn( $screen ) );

		// An unknown action and an unknown notice do nothing.
		self::post( array( 'cbuild_action' => 'erase' ) );
		self::assertNull( $this->screen()->handle() );
		self::assertNull( $this->screen()->download() );
		self::assertStringNotContainsString( 'class="notice', self::drawn( $this->screen(), 'erase' ) );
	}

	public function test_an_import_whose_job_was_lost_goes_on_when_the_page_is_opened_and_ends_as_one_never_stopped(): void {
		$first  = self::part( 'A' );
		$second = self::part( 'B' );
		$this->as( 'shop_manager' );
		$this->read( "id,wheelbase\n{$first->get_id()},14\n{$second->get_id()},14.25\n" );
		$import = $this->service( SpecImport::class );
		self::assertTrue( $import->confirm( (string) $import->state()?->token, get_current_user_id() ) );

		// The plugin is switched off while the import runs: its job is cancelled, and the state still says running.
		Deactivator::deactivate();
		self::assertSame( array(), self::waiting( Options::JOB_IMPORT ) );
		self::assertSame( ImportState::RUNNING, $import->state()?->status );
		self::assertSame( '', self::stored( $first->get_id() ) );

		$page = self::drawn( $this->screen() );
		self::assertStringContainsString( 'The import had stopped part way and has been started again from where it was.', $page );
		self::assertCount( 1, self::waiting( Options::JOB_IMPORT ) );
		self::assertStringNotContainsString( 'has been started again', self::drawn( $this->screen() ), 'once: its job waits now' );
		self::assertCount( 1, self::waiting( Options::JOB_IMPORT ) );

		self::work();
		self::assertSame( ImportState::DONE, $import->state()?->status );
		self::assertSame( array( 'wheelbase' => '14.0000' ), self::stored( $first->get_id() ) );
		self::assertSame( array( 'wheelbase' => '14.2500' ), self::stored( $second->get_id() ) );
	}

	public function test_an_import_that_stopped_says_so(): void {
		$this->as( 'shop_manager' );
		$state = new ImportState( ImportState::FAILED, 't', 'id', '.', 1, array( 'id' => 0 ), array(), 300, 100, 90, 10 );
		$this->service( ImportStore::class )->save( $state->toArray() );
		$page = self::drawn( $this->screen() );
		self::assertStringContainsString( '<div class="notice notice-error"><p>The import stopped: a batch failed four times.', $page );
		self::assertStringContainsString( '90 parts were changed.', $page );
		self::assertStringContainsString( '10 rows were the same as the shop had.', $page );
		self::assertStringContainsString( 'type="file"', $page );
	}

	public function test_long_lists_are_cut_and_say_how_many_more_there_are(): void {
		$this->as( 'shop_manager' );
		$part = self::part( 'A' );
		$this->read( "id,wheelbase,deck_width,deck_length\n{$part->get_id()},14,8,32\n" . str_repeat( "x,1,1,1\n", ImportScreen::SHOWN + 5 ) );
		$page = self::drawn( $this->screen() );
		self::assertSame( ImportScreen::SHOWN + 3, substr_count( $page, '<tr>' ) - 2 );
		self::assertStringContainsString( 'And 5 more.', $page );
		self::assertSame( 1, substr_count( $page, 'more.' ) );
		self::assertStringContainsString( number_format_i18n( ImportScreen::SHOWN + 5 ) . ' rows cannot be read', $page );
	}

	public function test_who_cannot_manage_the_shop_does_nothing_and_sees_nothing_and_a_form_needs_its_nonce(): void {
		$part = self::part( 'A' );
		$this->as( 'shop_manager' );
		$this->read( "id,wheelbase\n{$part->get_id()},14\n" );
		$token = (string) $this->service( SpecImport::class )->state()?->token;
		$owner = get_current_user_id();
		self::assertStringContainsString( 'value="confirm"', self::drawn( $this->screen() ) );
		self::assertStringNotContainsString( 'Another person', self::drawn( $this->screen() ) );

		// Another manager sees the file and may put it away, and may not confirm what they did not send.
		$this->as( 'shop_manager' );
		$page = self::drawn( $this->screen() );
		self::assertStringContainsString( 'Another person sent this file, and only they can confirm it.', $page );
		self::assertStringContainsString( '1 part would change.', $page );
		self::assertStringNotContainsString( 'value="confirm"', $page );
		self::assertStringContainsString( 'value="discard"', $page );
		self::post(
			array(
				'cbuild_action' => 'confirm',
				'cbuild_token'  => $token,
			)
		);
		self::assertSame( ImportScreen::url( array( 'notice' => 'stale' ) ), $this->screen()->handle() );
		self::assertSame( array(), self::waiting( Options::JOB_IMPORT ) );

		// An editor can edit products, and still may not import or export.
		$this->as( 'editor' );
		foreach ( array( 'export', 'read', 'discard', 'confirm' ) as $action ) {
			self::post(
				array(
					'cbuild_action' => $action,
					'cbuild_token'  => $token,
				)
			);
			self::assertNull( $this->screen( "id,wheelbase\n{$part->get_id()},15\n" )->handle(), $action );
			self::assertNull( $this->screen()->download(), $action );
		}
		self::assertSame( '', self::drawn( $this->screen() ) );
		self::assertSame( ImportState::WAITING, $this->service( SpecImport::class )->state()?->status );
		self::assertSame( $token, $this->service( SpecImport::class )->state()?->token );

		wp_set_current_user( $owner );
		foreach ( array( 'export', 'read', 'discard', 'confirm' ) as $action ) {
			foreach ( array( 'handle', 'download' ) as $method ) {
				self::post(
					array(
						'cbuild_action' => $action,
						'cbuild_token'  => $token,
					),
					false
				);
				try {
					$this->screen()->$method();
					self::fail( "$action went through $method() without its nonce." );
				} catch ( \WPDieException $stopped ) {
					self::assertStringContainsString( 'expired', $stopped->getMessage() );
				}
			}
		}
		self::assertSame( ImportState::WAITING, $this->service( SpecImport::class )->state()?->status );
		self::assertSame( array(), self::waiting( Options::JOB_IMPORT ) );
	}

	public function test_an_upload_is_refused_unless_it_is_a_csv_file_php_itself_received(): void {
		$uploads = new RequestUploads();
		$refused = static function ( ?array $file ) use ( $uploads ): string {
			unset( $_FILES[ ImportScreen::FILE ] );
			if ( null !== $file ) {
				$_FILES[ ImportScreen::FILE ] = $file;
			}
			try {
				$uploads->csv( ImportScreen::FILE, 100 );
			} catch ( UploadRefused $why ) {
				return $why->getMessage();
			}
			return '';
		};
		$path = (string) wp_tempnam( 'specs.csv' );
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents -- A file of the test's own.
		file_put_contents( $path, "id,wheelbase\n1,14\n" );
		$file = array(
			'name'     => 'specs.csv',
			'type'     => 'text/csv',
			'tmp_name' => $path,
			'error'    => UPLOAD_ERR_OK,
			'size'     => 18,
		);

		self::assertSame( UploadRefused::MISSING, $refused( null ) );
		self::assertSame( UploadRefused::MISSING, $refused( array( 'error' => UPLOAD_ERR_NO_FILE ) + $file ) );
		self::assertSame( UploadRefused::BIG, $refused( array( 'error' => UPLOAD_ERR_INI_SIZE ) + $file ) );
		self::assertSame( UploadRefused::BIG, $refused( array( 'error' => UPLOAD_ERR_FORM_SIZE ) + $file ) );
		self::assertSame( UploadRefused::BIG, $refused( array( 'size' => 101 ) + $file ) );
		self::assertSame( UploadRefused::FAILED, $refused( array( 'error' => UPLOAD_ERR_PARTIAL ) + $file ) );
		self::assertSame( UploadRefused::NOT_CSV, $refused( array( 'name' => 'specs.php' ) + $file ) );
		self::assertSame( UploadRefused::NOT_CSV, $refused( array( 'name' => 'specs.csv.php' ) + $file ) );
		// A path a request names, which PHP did not itself receive as an upload, is not read.
		self::assertSame( UploadRefused::FAILED, $refused( array( 'size' => 100 ) + $file ) );
		self::assertSame( UploadRefused::FAILED, $refused( array( 'tmp_name' => ABSPATH . 'wp-config.php' ) + $file ) );
		wp_delete_file( $path );
	}

	public function test_what_each_product_holds_is_read_and_written_through_woocommerce(): void {
		$parts = $this->service( PartSpecs::class );
		$deck  = self::part( 'DK-1', array( 'deck_width' => '8.0000' ) );
		$draft = self::part( 'DK-2' );
		wp_update_post(
			array(
				'ID'          => $draft->get_id(),
				'post_status' => 'draft',
			)
		);
		$variable = new \WC_Product_Variable();
		$variable->save();

		self::assertSame( array( $deck->get_id(), $draft->get_id(), $variable->get_id() ), $parts->page( 1, 10 ) );
		self::assertSame( array( $draft->get_id() ), $parts->page( 2, 1 ) );
		self::assertSame( array(), $parts->page( 2, 10 ) );
		self::assertSame(
			array(
				'DK-1' => $deck->get_id(),
				'DK-2' => $draft->get_id(),
			),
			$parts->bySku( 'DK-1', 'NOPE', 'DK-2' )
		);
		self::assertSame(
			array(
				$deck->get_id() => array(
					'sku'    => 'DK-1',
					'name'   => 'Katie 7.75',
					'fields' => array( 'deck_width' => '8.0000' ),
					'table'  => 0,
					'size'   => '',
				),
			),
			$parts->read( $deck->get_id(), 999999 )
		);

		$parts->write( $deck->get_id(), array( 'wheelbase' => '14.0000' ), 5, '149' );
		$stored = wc_get_product( $deck->get_id() );
		self::assertSame( array( 'wheelbase' => '14.0000' ), $stored->get_meta( Options::META_SPECS, true ) );
		self::assertSame(
			array(
				'table' => 5,
				'size'  => '149',
			),
			$stored->get_meta( Options::META_SPEC_TABLE, true )
		);
		$parts->write( $deck->get_id(), array(), 0, '149' );
		self::assertFalse( metadata_exists( 'post', $deck->get_id(), Options::META_SPECS ) );
		self::assertSame( '149', wc_get_product( $deck->get_id() )->get_meta( Options::META_SPEC_TABLE, true )['size'] );
		$parts->write( $deck->get_id(), array(), 0, '' );
		self::assertFalse( metadata_exists( 'post', $deck->get_id(), Options::META_SPEC_TABLE ) );
		$parts->write( 999999, array( 'wheelbase' => '1' ), 0, '' );
		self::assertFalse( get_post( 999999 ) instanceof \WP_Post );
	}

	public function test_the_import_in_hand_is_an_option_that_is_not_autoloaded(): void {
		$store = $this->service( ImportStore::class );
		self::assertNull( $store->load() );
		$store->save( array( 'status' => 'waiting' ) );
		self::assertSame( array( 'status' => 'waiting' ), $store->load() );
		self::assertContains( $GLOBALS['wpdb']->get_var( $GLOBALS['wpdb']->prepare( "SELECT autoload FROM {$GLOBALS['wpdb']->options} WHERE option_name = %s", Options::IMPORT ) ), array( 'no', 'off' ) );
		update_option( Options::IMPORT, 'not an import' );
		self::assertNull( $store->load() );
		$store->clear();
		self::assertFalse( get_option( Options::IMPORT ) );
	}

	public function test_the_rows_are_stored_once_and_a_batch_saves_only_its_place(): void {
		$text = "id,deck_width\n";
		for ( $at = 0; $at < 250; $at++ ) {
			$text .= ( 90000000 + $at ) . ",8.5\n";
		}
		$import = $this->service( SpecImport::class );
		$writes = array();
		$count  = static function ( string $option ) use ( &$writes ): void {
			$writes[ $option ] = ( $writes[ $option ] ?? 0 ) + 1;
		};
		$reads = 0;
		$read  = static function ( mixed $pre ) use ( &$reads ): mixed {
			++$reads;
			return $pre;
		};
		add_action( 'added_option', $count );
		add_action( 'updated_option', $count );
		$import->read( $text );
		$token = (string) $import->state()?->token;
		self::assertTrue( $import->confirm( $token ) );
		for ( $batch = 0; $batch < 3; $batch++ ) {
			$held = get_option( Options::IMPORT_ROWS );
			self::assertIsArray( $held );
			self::assertCount( 250, $held['rows'], 'while it runs' );
			add_filter( 'pre_option_' . Options::IMPORT_ROWS, $read );
			// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- The plugin's own job.
			do_action( Options::JOB_IMPORT );
			remove_filter( 'pre_option_' . Options::IMPORT_ROWS, $read );
			self::assertArrayNotHasKey( 'rows', (array) get_option( Options::IMPORT ), 'the place and the counts, without the rows' );
		}
		remove_action( 'added_option', $count );
		remove_action( 'updated_option', $count );

		$state = $import->state();
		self::assertSame( ImportState::DONE, $state?->status );
		self::assertCount( 250, $state->refused, 'every row was reached' );
		self::assertSame( 1, $writes[ Options::IMPORT_ROWS ] ?? 0, 'the rows are written when the file is read, and not again' );
		self::assertSame( 5, $writes[ Options::IMPORT ] ?? 0, 'read, confirmed and three batches' );
		self::assertSame( 3, $reads, 'a batch reads the rows once, and saving its place does not read or compare them again' );
		self::assertFalse( get_option( Options::IMPORT_ROWS ), 'the rows go when the import is done' );
		self::assertContains( Options::IMPORT_ROWS, Options::names(), 'so uninstall removes them' );
	}

	public function test_an_import_whose_rows_are_gone_or_are_another_imports_is_none(): void {
		$store  = $this->service( ImportStore::class );
		$import = $this->service( SpecImport::class );
		$import->read( "id,deck_width\n90000000,8.5\n" );
		self::assertCount( 1, (array) $import->state()?->rows );
		delete_option( Options::IMPORT_ROWS );
		self::assertNull( $store->load() );
		self::assertNull( $import->state() );

		$import->read( "id,deck_width\n90000000,8.5\n" );
		update_option( Options::IMPORT_ROWS, array( 'token' => 'another', 'rows' => array( array( '1', '2' ) ), 'found' => array() ), false );
		self::assertNull( $store->load() );
		$store->clear();
		self::assertFalse( get_option( Options::IMPORT_ROWS ) );
	}
}
