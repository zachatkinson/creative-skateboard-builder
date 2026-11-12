<?php
/**
 * The builder, rendered on the server.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use Throwable;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The one renderer the kit's page and the Builder block both call (spec: The builder, ways in; Speed: the first step
 * and the picture are rendered on the server). It reads the build from the address, asks the flow what to show,
 * hands the view to the Interactivity API as the store's state, and renders the template with its directives.
 *
 * - **One builder a page.** The store's state is the page's, so a second builder would show the first's build. Only
 *   the first renders; the block says so in its `supports` too.
 * - **A boundary** (spec: Resilience). Whatever the render throws is logged with the kit and nothing of the request,
 *   and the shopper reads one sentence in place of the builder. The rest of the page is the theme's and stands.
 * - **Nothing for a kit a visitor may not see:** a draft, a private or a password-protected kit renders nothing, as
 *   the state route answers it as not there.
 *
 * @since 0.6.0
 */
final class BuilderPage {

	/**
	 * The store's namespace.
	 *
	 * @since 0.6.0
	 */
	public const string STORE = 'creative-skateboard-builder';

	/**
	 * Whether a builder has rendered on this page.
	 *
	 * @var bool
	 */
	private bool $rendered = false;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param Builder $builder The flow.
	 * @param Kits    $kits    The shop's kits, for whether a visitor may see one.
	 * @param Logger  $logger  Where a render that failed is written.
	 */
	public function __construct( private Builder $builder, private Kits $kits, private Logger $logger ) {}

	/**
	 * The builder's markup for a kit, or an empty string where there is none to show.
	 *
	 * @since 0.6.0
	 *
	 * @param int       $kit     The kit product's ID.
	 * @param BuildLink $link    The build in the address.
	 * @param string    $base    The address of the page the builder is on, with no build in it.
	 * @param bool      $process Whether to process the directives here. A block's are processed by WordPress
	 *                           once the block has rendered, as for any interactive block.
	 */
	public function render( int $kit, BuildLink $link, string $base, bool $process = true ): string {
		if ( $this->rendered || ! $this->kits->isPublic( $kit ) ) {
			return '';
		}
		$this->rendered = true;
		$level          = ob_get_level();
		try {
			$shown = $this->builder->show( $kit, $link, $base );
			if ( null === $shown ) {
				return '';
			}
			wp_interactivity_state(
				self::STORE,
				array(
					'view'   => $shown['view'],
					// Said by the store after a pick, never on load: the page has just been read out.
					'status' => '',
				)
			);
			ob_start();
			require CBUILD_PATH . 'templates/builder/builder.php';
			$html = (string) ob_get_clean();
			return $process ? wp_interactivity_process_directives( $html ) : $html;
		} catch ( Throwable $failure ) {
			// A template that threw leaves its buffer open, holding half a builder.
			while ( ob_get_level() > $level ) {
				ob_end_clean();
			}
			$this->logger->log(
				Logger::ERROR,
				'The builder could not be shown.',
				array(
					'kit'   => $kit,
					'error' => $failure->getMessage(),
				)
			);
			return '<p class="cbuild-failed">' . esc_html__( 'The builder could not be shown. Reload the page, or try again later.', 'creative-skateboard-builder' ) . '</p>';
		}
	}

	/**
	 * The build in the address of this request. A GET's query is read and nothing is changed by it, so no nonce is
	 * asked for: a build link is shared, and each ID in it is checked by the state before anything is shown.
	 *
	 * @since 0.6.0
	 */
	public static function requested(): BuildLink {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as above.
		$sent = isset( $_GET[ BuildLink::PARAM ] ) ? map_deep( wp_unslash( $_GET[ BuildLink::PARAM ] ), 'sanitize_text_field' ) : null;
		return BuildLink::read( $sent );
	}
}
