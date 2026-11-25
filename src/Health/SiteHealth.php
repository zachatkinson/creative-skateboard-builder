<?php
/**
 * Site Health.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Health;

use CreativeBranding\SkateboardBuilder\Install\NetworkFile;
use Closure;
use CreativeBranding\SkateboardBuilder\Admin\CheckScreen;
use CreativeBranding\SkateboardBuilder\Admin\ImportScreen;
use CreativeBranding\SkateboardBuilder\Admin\Labels;
use CreativeBranding\SkateboardBuilder\Domain\Kit\BuildTerms;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use CreativeBranding\SkateboardBuilder\Service\ComposedPictures;
use CreativeBranding\SkateboardBuilder\Service\Health;
use CreativeBranding\SkateboardBuilder\Service\PartRenditions;
use CreativeBranding\SkateboardBuilder\Support\Cast;
use CreativeBranding\SkateboardBuilder\Support\Options;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Four tests on Tools > Site Health and a section on its Info tab with the plugin's versions and settings. Three are
 * direct: the specs index up to date and no failed background job, each a saved place read and failed actions
 * counted; and whether composed pictures can be drawn and saved, from the host's libraries and the uploads folder
 * (D-198). The fourth runs the Catalogue check, which reads every part, so it is asynchronous: the page asks for it
 * over REST once it has drawn, and WordPress's weekly check calls it (D-094). Nothing here is about a person.
 *
 * @since 0.3.0
 */
final class SiteHealth implements Hookable {

	/**
	 * The tests' identifiers, and the Info tab's section.
	 */
	public const TEST_INDEX     = 'cbuild_index';
	public const TEST_JOBS      = 'cbuild_jobs';
	public const TEST_CATALOGUE = 'cbuild_catalogue';
	public const TEST_PICTURES  = 'cbuild_pictures';
	public const TEST_NETWORK   = 'cbuild_network';
	public const SECTION        = 'creative-skateboard-builder';

	/**
	 * How many of the parts with no resized copies the pictures' test names.
	 */
	private const NAMED = 5;

	/**
	 * The Catalogue check's route, for Site Health's page.
	 */
	private const NAMESPACE = 'cbuild/v1';
	private const ROUTE     = '/site-health/catalogue-check';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): Health                $health     Builds the verdicts, when a test runs.
	 * @param Closure(): ShopSettings          $settings   Builds the settings, when the Info tab is drawn.
	 * @param Closure(): MigrationStore        $migrations Builds the schema's version, when the Info tab is drawn.
	 * @param Closure(): CatalogueCheck        $check      Builds the Catalogue check, when its test runs.
	 * @param Closure(): ComposedPictures      $pictures   Builds the composed pictures' service, when its test runs.
	 * @param (Closure(): PartRenditions)|null $renditions Builds the layer images' renditions, when the pictures' test
	 *                                                    runs; none leaves their count out.
	 */
	public function __construct( private Closure $health, private Closure $settings, private Closure $migrations, private Closure $check, private Closure $pictures, private ?Closure $renditions = null ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'tests' ) );
		add_filter( 'debug_information', array( $this, 'info' ) );
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	/**
	 * The route Site Health's page asks for the Catalogue check's test. It reads and changes nothing, and answers
	 * whoever may see Site Health, as WordPress's own asynchronous tests do
	 * (wp-includes/rest-api/endpoints/class-wp-rest-site-health-controller.php:189).
	 *
	 * @since 0.3.0
	 */
	public function routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/site-health/catalogue-check',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'catalogue' ),
				'permission_callback' => static fn(): bool => current_user_can( 'view_site_health_checks' ),
			)
		);
	}

	/**
	 * Add the tests. Three are direct: an option read and one indexed query each, or the host's libraries, a folder and
	 * the drawn parts' stored image details, with no image file opened. The Catalogue check is not.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $tests Site Health's tests.
	 * @return mixed
	 */
	public function tests( mixed $tests ): mixed {
		if ( ! is_array( $tests ) ) {
			return $tests;
		}
		$direct                        = is_array( $tests['direct'] ?? null ) ? $tests['direct'] : array();
		$direct[ self::TEST_INDEX ]    = array(
			'label' => __( 'The skateboard builder\'s specs index', 'creative-skateboard-builder' ),
			'test'  => array( $this, 'index' ),
		);
		$direct[ self::TEST_JOBS ]     = array(
			'label' => __( 'The skateboard builder\'s background jobs', 'creative-skateboard-builder' ),
			'test'  => array( $this, 'jobs' ),
		);
		$direct[ self::TEST_PICTURES ] = array(
			'label' => __( 'The skateboard builder\'s composed pictures', 'creative-skateboard-builder' ),
			'test'  => array( $this, 'pictures' ),
		);
		if ( is_multisite() ) {
			$direct[ self::TEST_NETWORK ] = array(
				'label' => __( 'The skateboard builder on a network', 'creative-skateboard-builder' ),
				'test'  => array( $this, 'network' ),
			);
		}
		$tests['direct']               = $direct;
		$async                         = is_array( $tests['async'] ?? null ) ? $tests['async'] : array();
		$async[ self::TEST_CATALOGUE ] = array(
			'label'             => __( 'The skateboard builder\'s Catalogue check', 'creative-skateboard-builder' ),
			'test'              => rest_url( self::NAMESPACE . self::ROUTE ),
			'has_rest'          => true,
			'async_direct_test' => array( $this, 'catalogue' ),
		);
		$tests['async']                = $async;
		return $tests;
	}

	/**
	 * The test of the specs index.
	 *
	 * @since 0.3.0
	 *
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	public function index(): array {
		$verdict  = ( $this->health )()->index();
		$parts    = number_format_i18n( $verdict['parts'] );
		$settings = sprintf( '<a href="%s">%s</a>', esc_url( CheckScreen::url() ), esc_html__( 'Open the Catalogue check', 'creative-skateboard-builder' ) );
		switch ( $verdict['code'] ) {
			case Health::INDEX_FAILED:
				$label = __( 'The skateboard builder\'s specs index was not rebuilt', 'creative-skateboard-builder' );
				$says  = __( 'Reading every part again stopped after five failures, so some parts are still indexed with what they held before. Start it again with "Read every part again" on the Catalogue check. WooCommerce > Status > Logs has the reason, under creative-skateboard-builder.', 'creative-skateboard-builder' );
				break;
			case Health::INDEX_STALLED:
				$label = __( 'The skateboard builder\'s specs index stopped part way', 'creative-skateboard-builder' );
				/* translators: %s: a number of parts. */
				$says = sprintf( _n( 'Reading every part again got as far as %s part and has no job waiting to go on. Start it again with "Read every part again" on the Catalogue check.', 'Reading every part again got as far as %s parts and has no job waiting to go on. Start it again with "Read every part again" on the Catalogue check.', $verdict['parts'], 'creative-skateboard-builder' ), $parts );
				break;
			case Health::INDEX_BUILDING:
				$label = __( 'The skateboard builder\'s specs index is being rebuilt', 'creative-skateboard-builder' );
				/* translators: %s: a number of parts. */
				$says     = sprintf( __( 'Every part is being read again in the background, 100 at a time: %s so far. Parts keep what they held until they are reached.', 'creative-skateboard-builder' ), $parts );
				$settings = '';
				break;
			default:
				$label    = __( 'The skateboard builder\'s specs index is up to date', 'creative-skateboard-builder' );
				$says     = __( 'The index holds each part\'s specs, and a part is read again in the background whenever it is saved.', 'creative-skateboard-builder' );
				$settings = '';
		}
		return self::result( self::TEST_INDEX, $verdict['status'], $label, $says, $settings );
	}

	/**
	 * The test of the background jobs.
	 *
	 * @since 0.3.0
	 *
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	public function jobs(): array {
		$verdict = ( $this->health )()->jobs();
		$import  = sprintf( '<a href="%s">%s</a>', esc_url( ImportScreen::url() ), esc_html__( 'Open Import and export', 'creative-skateboard-builder' ) );
		if ( Health::IMPORT_FAILED === $verdict['code'] ) {
			return self::result( self::TEST_JOBS, $verdict['status'], __( 'The skateboard builder\'s last specs import stopped before its end', 'creative-skateboard-builder' ), __( 'A batch of the import failed four times, so the parts after it were not written. Import the file again: a part already written takes the same values. WooCommerce > Status > Logs has the reason, under creative-skateboard-builder.', 'creative-skateboard-builder' ), $import );
		}
		if ( Health::IMPORT_STALLED === $verdict['code'] ) {
			return self::result( self::TEST_JOBS, $verdict['status'], __( 'The skateboard builder\'s specs import has no job left to run it', 'creative-skateboard-builder' ), __( 'An import is part way through and nothing is queued to go on, as happens when the plugin is switched off while one runs. Opening Import and export starts it again from where it stopped.', 'creative-skateboard-builder' ), $import );
		}
		if ( Health::JOBS_FAILED !== $verdict['code'] ) {
			return self::result( self::TEST_JOBS, $verdict['status'], __( 'No background job of the skateboard builder has failed', 'creative-skateboard-builder' ), __( 'The builder indexes parts and writes imports in the background. A job that fails is tried again, and shows here if it still fails.', 'creative-skateboard-builder' ), '' );
		}
		/* translators: %s: a number of jobs. */
		$label   = sprintf( _n( '%s background job of the skateboard builder has failed', '%s background jobs of the skateboard builder have failed', $verdict['failed'], 'creative-skateboard-builder' ), number_format_i18n( $verdict['failed'] ) );
		$says    = __( 'A part whose job failed may be indexed with what it held before. The Catalogue check lists what needs fixing, and WooCommerce > Status > Logs has each failure\'s reason, under creative-skateboard-builder.', 'creative-skateboard-builder' );
		$actions = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=wc-status&tab=action-scheduler&status=failed' ) ), esc_html__( 'See the failed jobs', 'creative-skateboard-builder' ) )
			. ' | ' . sprintf( '<a href="%s">%s</a>', esc_url( CheckScreen::url() ), esc_html__( 'Open the Catalogue check', 'creative-skateboard-builder' ) );
		return self::result( self::TEST_JOBS, $verdict['status'], $label, $says, $actions );
	}

	/**
	 * On a network: whether the must-use file is in place that drops a deleted site's tables and a deleted account's
	 * saved builds where the plugin is not loaded (#8, D-220).
	 *
	 * @since 0.8.0
	 *
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	public function network(): array {
		if ( NetworkFile::inPlace() ) {
			return self::result(
				self::TEST_NETWORK,
				Health::GOOD,
				__( 'Deleting a site or an account removes the skateboard builder\'s data', 'creative-skateboard-builder' ),
				__( 'A must-use file names the builder\'s tables, so deleting a site drops them and deleting an account deletes its saved builds, on every site of the network.', 'creative-skateboard-builder' ),
				''
			);
		}
		return self::result(
			self::TEST_NETWORK,
			Health::RECOMMENDED,
			__( 'Deleting a site or an account may leave the skateboard builder\'s data behind', 'creative-skateboard-builder' ),
			/* translators: 1: a file's name; 2: a folder's path. */
			sprintf( __( 'The builder could not write %1$s to %2$s. Without it, a site deleted where the builder is not active for the whole network keeps its tables, and an account deleted from the network keeps its saved builds on such a site. Make the folder writable by the web server, then open any wp-admin page and the builder writes it.', 'creative-skateboard-builder' ), NetworkFile::NAME, WPMU_PLUGIN_DIR ),
			''
		);
	}

	/**
	 * The test of the composed pictures: whether this host can draw them and keep them (D-198).
	 *
	 * @since 0.7.0
	 *
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	public function pictures(): array {
		$verdict = ( $this->pictures )()->health();
		if ( ComposedPictures::HEALTH_NO_LIBRARY === $verdict['code'] ) {
			return self::result( self::TEST_PICTURES, $verdict['status'], __( 'The skateboard builder cannot compose pictures on this server', 'creative-skateboard-builder' ), __( 'PHP has neither GD nor Imagick able to read PNG and WebP and write WebP and JPEG, so carts and share cards show the deck\'s photo instead of the built board. The page\'s own picture is not affected. Your host can turn on GD or Imagick with WebP.', 'creative-skateboard-builder' ), '' );
		}
		if ( ComposedPictures::HEALTH_UNWRITABLE === $verdict['code'] ) {
			return self::result( self::TEST_PICTURES, $verdict['status'], __( 'The skateboard builder cannot save composed pictures', 'creative-skateboard-builder' ), __( 'The uploads folder cannot be written, so carts and share cards show the deck\'s photo instead of the built board. Your host can make wp-content/uploads writable by the web server.', 'creative-skateboard-builder' ), '' );
		}
		// Cowork's M7 review, F2: a part whose layer image has no resized copy is drawn from the full-size file (D-209).
		$parts      = null === $this->renditions ? array() : ( $this->renditions )()->unrenderedParts();
		$unrendered = count( $parts );
		if ( $unrendered > 0 ) {
			/* translators: %s: a number of parts. */
			$label = sprintf( _n( '%s part\'s layer image has no resized copies', '%s parts\' layer images have no resized copies', $unrendered, 'creative-skateboard-builder' ), number_format_i18n( $unrendered ) );
			$says  = __( 'The builder draws each part from copies of its layer image in AVIF and WebP at the sizes it shows, made in the background when the part is saved. Without them the page loads the full-size image, which is slower.', 'creative-skateboard-builder' );
			// The fix named is the one for the cause, and each says only what is so (Cowork's 0.7.0 recheck, N1; D-214):
			// a host whose editors write neither type; a copy that failed in the last 30 days, whose reason is logged;
			// or copies not made yet, which "Read every part again" queues the parts' jobs to make.
			$writes = wp_image_editor_supports( array( 'mime_type' => 'image/avif' ) ) || wp_image_editor_supports( array( 'mime_type' => 'image/webp' ) );
			if ( ! $writes ) {
				$says .= ' ' . __( 'This server\'s image editors write neither AVIF nor WebP. Your host can turn on WebP in GD or Imagick, and then "Read every part again" on the Catalogue check makes them in the background.', 'creative-skateboard-builder' );
			} elseif ( false !== get_transient( Options::RENDITIONS_FAILED ) ) {
				$says .= ' ' . __( 'Making a copy failed in the last 30 days. WooCommerce > Status > Logs has the reason, under creative-skateboard-builder. Once it is fixed, "Read every part again" on the Catalogue check makes the missing copies in the background.', 'creative-skateboard-builder' );
			} else {
				$says .= ' ' . __( '"Read every part again" on the Catalogue check makes the missing copies in the background, a part at a time.', 'creative-skateboard-builder' );
			}
			$says = trim( $says . ' ' . self::named( $parts ) );
			return self::result( self::TEST_PICTURES, Health::RECOMMENDED, $label, $says, sprintf( '<a href="%s">%s</a>', esc_url( CheckScreen::url() ), esc_html__( 'Open the Catalogue check', 'creative-skateboard-builder' ) ) );
		}
		return self::result( self::TEST_PICTURES, $verdict['status'], __( 'The skateboard builder can compose pictures', 'creative-skateboard-builder' ), __( 'Each build added to a cart gets pictures of the built board for the cart and share cards, made in the background. Pictures not shown for 30 days are deleted.', 'creative-skateboard-builder' ), '' );
	}

	/**
	 * The parts whose layer images have no resized copies, by name, so the shop knows which they are (issue #129,
	 * D-372): all of them up to five, else the first five the kits offer. Nothing where none can be read.
	 *
	 * @param list<int> $parts The parts' IDs.
	 */
	private static function named( array $parts ): string {
		$names = array();
		foreach ( array_slice( $parts, 0, self::NAMED ) as $part ) {
			$product = wc_get_product( $part );
			if ( $product instanceof \WC_Product ) {
				$names[] = $product->get_name();
			}
		}
		if ( array() === $names ) {
			return '';
		}
		$list = wp_sprintf_l( '%l', $names );
		if ( count( $parts ) > self::NAMED ) {
			/* translators: %s: the names of five parts, as a list. */
			return sprintf( __( 'Among them: %s.', 'creative-skateboard-builder' ), $list );
		}
		/* translators: %s: the names of the parts, as a list. */
		return sprintf( _n( 'The part without them: %s.', 'The parts without them: %s.', count( $names ), 'creative-skateboard-builder' ), $list );
	}

	/**
	 * The test of the Catalogue check: what keeps a part out of the builder, counted. The findings themselves are on
	 * the check's own screen.
	 *
	 * @since 0.3.0
	 *
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	public function catalogue(): array {
		// It counts no shared cards, so it doesn't work them out (the auditor's 1.0.0 minor 11).
		$verdict = Health::catalogue( ( $this->check )()->withoutLooks()->run() );
		$open    = sprintf( '<a href="%s">%s</a>', esc_url( CheckScreen::url() ), esc_html__( 'Open the Catalogue check', 'creative-skateboard-builder' ) );
		// Parts with no layer image stay in the builder, so they are counted in a sentence of their own (D-211).
		$layers = '';
		if ( $verdict['layers'] > 0 ) {
			/* translators: %s: a number of parts. */
			$layers = sprintf( _n( '%s part the picture draws has no layer image. It is still offered, and the picture shows its labelled photo beside the board until one is chosen on its product page.', '%s parts the picture draws have no layer image. Each is still offered, and the picture shows its labelled photo beside the board until one is chosen on its product page.', $verdict['layers'], 'creative-skateboard-builder' ), number_format_i18n( $verdict['layers'] ) );
		}
		if ( Health::CHECK_LAYERS === $verdict['code'] ) {
			/* translators: %s: a number of parts. */
			$label = sprintf( _n( 'The skateboard builder\'s Catalogue check found %s part with no layer image', 'The skateboard builder\'s Catalogue check found %s parts with no layer image', $verdict['layers'], 'creative-skateboard-builder' ), number_format_i18n( $verdict['layers'] ) );
			return self::result( self::TEST_CATALOGUE, $verdict['status'], $label, $layers . ' ' . __( 'Nothing it found keeps a part out of the builder.', 'creative-skateboard-builder' ), $open );
		}
		if ( Health::CHECK_FOUND !== $verdict['code'] ) {
			return self::result( self::TEST_CATALOGUE, $verdict['status'], __( 'The skateboard builder\'s Catalogue check found nothing to fix', 'creative-skateboard-builder' ), __( 'The check reads every part and lists sources that disagree and values that cannot be read. It found none.', 'creative-skateboard-builder' ), '' );
		}
		/* translators: %s: a number of findings. */
		$label = sprintf( _n( 'The skateboard builder\'s Catalogue check found %s thing to fix', 'The skateboard builder\'s Catalogue check found %s things to fix', $verdict['findings'], 'creative-skateboard-builder' ), number_format_i18n( $verdict['findings'] ) );
		/* translators: %s: a number of parts. */
		$says = sprintf( _n( 'What it found is in %s part. Until it is fixed, a part may be left out of the builder or offered by the wrong figure. The check lists each finding with a link to where it is fixed.', 'What it found is in %s parts. Until it is fixed, a part may be left out of the builder or offered by the wrong figure. The check lists each finding with a link to where it is fixed.', $verdict['parts'], 'creative-skateboard-builder' ), number_format_i18n( $verdict['parts'] ) );
		return self::result( self::TEST_CATALOGUE, $verdict['status'], $label, '' === $layers ? $says : $says . ' ' . $layers, $open );
	}

	/**
	 * Add the plugin's section to the Info tab.
	 *
	 * @since 0.3.0
	 *
	 * @param mixed $info The sections.
	 * @return mixed
	 */
	public function info( mixed $info ): mixed {
		if ( ! is_array( $info ) ) {
			return $info;
		}
		$settings = ( $this->settings )();
		$order    = array();
		foreach ( $settings->sourceOrder()->origins as $origin ) {
			$order[] = Labels::source( $origin );
		}
		$terms                 = $settings->terms();
		$written               = $terms->toArray();
		$index                 = ( $this->health )()->index();
		$jobs                  = ( $this->health )()->jobs();
		$info[ self::SECTION ] = array(
			'label'  => __( 'Creative Skateboard Builder', 'creative-skateboard-builder' ),
			'fields' => array(
				'version'    => array(
					'label' => __( 'Version', 'creative-skateboard-builder' ),
					'value' => CBUILD_VERSION,
				),
				'schema'     => array(
					'label' => __( 'Database version', 'creative-skateboard-builder' ),
					'value' => ( $this->migrations )()->schemaVersion(),
				),
				'order'      => array(
					'label' => __( 'Which source wins', 'creative-skateboard-builder' ),
					'value' => implode( ', ', $order ),
					'debug' => implode( ', ', array_column( $settings->sourceOrder()->origins, 'value' ) ),
				),
				'mapping'    => array(
					'label' => __( 'Attributes mapped to specs', 'creative-skateboard-builder' ),
					'value' => count( $settings->attributeMapping() ),
				),
				'discount'   => array(
					'label' => __( 'Build discount (%)', 'creative-skateboard-builder' ),
					'value' => Cast::str( $written[ BuildTerms::DISCOUNT ] ),
				),
				'coupons'    => array(
					'label' => __( 'A fixed product discount coupon applies to', 'creative-skateboard-builder' ),
					'value' => BuildTerms::ON_PARTS === $terms->fixedCoupons ? __( 'The kit and every part', 'creative-skateboard-builder' ) : __( 'The kit alone', 'creative-skateboard-builder' ),
					'debug' => $terms->fixedCoupons,
				),
				'rate'       => array(
					'label' => __( 'Requests a minute from one address', 'creative-skateboard-builder' ),
					'value' => $terms->rateLimit,
				),
				// A header's name, never an address.
				'proxy'      => array(
					'label' => __( 'Trusted proxy header', 'creative-skateboard-builder' ),
					'value' => '' === $terms->proxyHeader ? __( 'None', 'creative-skateboard-builder' ) : $terms->proxyHeader,
					'debug' => $terms->proxyHeader,
				),
				'assembly'   => array(
					'label' => __( 'A new kit starts with assembly chosen', 'creative-skateboard-builder' ),
					'value' => true === ( $terms->assembly['default_on'] ?? false ) ? __( 'Yes', 'creative-skateboard-builder' ) : __( 'No', 'creative-skateboard-builder' ),
					'debug' => true === ( $terms->assembly['default_on'] ?? false ),
				),
				'index'      => array(
					'label' => __( 'Specs index', 'creative-skateboard-builder' ),
					'value' => Labels::index( $index['code'] ),
					'debug' => $index['code'],
				),
				'failedJobs' => array(
					'label' => __( 'Failed background jobs', 'creative-skateboard-builder' ),
					'value' => $jobs['failed'],
				),
			),
		);
		return $info;
	}

	/**
	 * A test's result as Site Health takes it.
	 *
	 * @param string $test    The test's identifier.
	 * @param string $status  good, recommended or critical.
	 * @param string $label   The heading.
	 * @param string $says    The paragraph under it.
	 * @param string $actions Links, already escaped.
	 * @return array{label: string, status: string, badge: array{label: string, color: string}, description: string, actions: string, test: string}
	 */
	private static function result( string $test, string $status, string $label, string $says, string $actions ): array {
		return array(
			'label'       => $label,
			'status'      => $status,
			'badge'       => array(
				'label' => __( 'Skateboard builder', 'creative-skateboard-builder' ),
				'color' => Health::GOOD === $status ? 'blue' : ( Health::CRITICAL === $status ? 'red' : 'orange' ),
			),
			'description' => '<p>' . esc_html( $says ) . '</p>',
			'actions'     => '' === $actions ? '' : '<p>' . $actions . '</p>',
			'test'        => $test,
		);
	}
}
