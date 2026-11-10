<?php
/**
 * Site Health.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Health;

use Closure;
use CreativeBranding\SkateboardBuilder\Admin\CheckScreen;
use CreativeBranding\SkateboardBuilder\Admin\Labels;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\MigrationStore;
use CreativeBranding\SkateboardBuilder\Port\ShopSettings;
use CreativeBranding\SkateboardBuilder\Service\Health;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Two tests on Tools > Site Health (the specs index up to date, and no failed background job) and a section on its
 * Info tab with the plugin's versions and settings. The tests read a saved place and count failed actions; neither
 * reads a part. Nothing on the Info tab is about a person.
 *
 * @since 0.3.0
 */
final class SiteHealth implements Hookable {

	/**
	 * The tests' identifiers, and the Info tab's section.
	 */
	public const TEST_INDEX = 'cbuild_index';
	public const TEST_JOBS  = 'cbuild_jobs';
	public const SECTION    = 'creative-skateboard-builder';

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param Closure(): Health         $health     Builds the verdicts, when a test runs.
	 * @param Closure(): ShopSettings   $settings   Builds the settings, when the Info tab is drawn.
	 * @param Closure(): MigrationStore $migrations Builds the schema's version, when the Info tab is drawn.
	 */
	public function __construct( private Closure $health, private Closure $settings, private Closure $migrations ) {}

	/**
	 * Attach hooks.
	 *
	 * @since 0.3.0
	 */
	public function register(): void {
		add_filter( 'site_status_tests', array( $this, 'tests' ) );
		add_filter( 'debug_information', array( $this, 'info' ) );
	}

	/**
	 * Add the two tests. Both are direct: each is an option read and one indexed query.
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
		$direct                     = is_array( $tests['direct'] ?? null ) ? $tests['direct'] : array();
		$direct[ self::TEST_INDEX ] = array(
			'label' => __( 'The skateboard builder\'s specs index', 'creative-skateboard-builder' ),
			'test'  => array( $this, 'index' ),
		);
		$direct[ self::TEST_JOBS ]  = array(
			'label' => __( 'The skateboard builder\'s background jobs', 'creative-skateboard-builder' ),
			'test'  => array( $this, 'jobs' ),
		);
		$tests['direct']            = $direct;
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
		$settings = sprintf( '<a href="%s">%s</a>', esc_url( admin_url( 'admin.php?page=wc-settings&tab=cbuild' ) ), esc_html__( 'Open the Builder settings', 'creative-skateboard-builder' ) );
		switch ( $verdict['code'] ) {
			case Health::INDEX_FAILED:
				$label = __( 'The skateboard builder\'s specs index was not rebuilt', 'creative-skateboard-builder' );
				$says  = __( 'Reading every part again stopped after five failures, so some parts are still indexed with what they held before. Save the Builder settings to start it again. WooCommerce > Status > Logs has the reason, under creative-skateboard-builder.', 'creative-skateboard-builder' );
				break;
			case Health::INDEX_STALLED:
				$label = __( 'The skateboard builder\'s specs index stopped part way', 'creative-skateboard-builder' );
				/* translators: %s: a number of parts. */
				$says = sprintf( __( 'Reading every part again got as far as %s parts and has no job waiting to go on. Save the Builder settings to start it again.', 'creative-skateboard-builder' ), $parts );
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
				'index'      => array(
					'label' => __( 'Specs index', 'creative-skateboard-builder' ),
					'value' => $index['code'],
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
