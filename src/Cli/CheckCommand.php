<?php
/**
 * `wp cbuild check`.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Cli;

use CreativeBranding\SkateboardBuilder\Admin\Sentences;
use CreativeBranding\SkateboardBuilder\Service\CatalogueCheck;
use WP_CLI;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The Catalogue check of WooCommerce > Builder, from the command line: the same service and the same sentences.
 *
 * @since 0.3.0
 */
final class CheckCommand {

	/**
	 * Constructor.
	 *
	 * @since 0.3.0
	 *
	 * @param CatalogueCheck $check The check.
	 */
	public function __construct( private CatalogueCheck $check ) {}

	/**
	 * List what keeps a part out of the builder.
	 *
	 * Reads every part and changes nothing. Lists sources that disagree about a part, values that cannot be read,
	 * background work that failed and, for each kit, parts that lack a spec, presets that no longer fit and slots
	 * with nothing in stock.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : How the findings are listed.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 *   - count
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp cbuild check
	 *     wp cbuild check --format=json
	 *
	 * ## EXIT STATUS
	 *
	 * 0 when the check found nothing, 1 when it found anything.
	 *
	 * @since 0.3.0
	 *
	 * @param array<int, string>   $args  Positional arguments.
	 * @param array<string, mixed> $assoc Flags.
	 */
	public function __invoke( array $args, array $assoc = array() ): void {
		unset( $args );
		$format = is_string( $assoc['format'] ?? null ) ? $assoc['format'] : 'table';
		$items  = array();
		foreach ( $this->check->run() as $finding ) {
			$items[] = array(
				'code'    => $finding->code,
				'part'    => $finding->part,
				'kit'     => $finding->kit,
				'slot'    => $finding->slot,
				'spec'    => $finding->spec,
				'message' => Sentences::finding( $finding ),
			);
		}
		if ( array() === $items ) {
			if ( 'table' === $format ) {
				WP_CLI::success( __( 'The check found nothing to fix.', 'creative-skateboard-builder' ) );
				return;
			}
			WP_CLI\Utils\format_items( $format, $items, array( 'code', 'part', 'kit', 'slot', 'spec', 'message' ) );
			return;
		}
		WP_CLI\Utils\format_items( $format, $items, array( 'code', 'part', 'kit', 'slot', 'spec', 'message' ) );
		/* translators: %s: a number of findings. */
		WP_CLI::error( sprintf( _n( 'The check found %s thing to fix.', 'The check found %s things to fix.', count( $items ), 'creative-skateboard-builder' ), number_format_i18n( count( $items ) ) ) );
	}
}
