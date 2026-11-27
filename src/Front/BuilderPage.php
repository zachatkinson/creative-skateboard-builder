<?php
/**
 * The builder, rendered on the server.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Cart\ShowHooks;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Logger;
use CreativeBranding\SkateboardBuilder\Support\Cast;
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
 *   and the shopper reads one sentence in place of the builder. A shop manager also reads why. The rest of the page is
 *   the theme's and stands.
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
	 * The store's script module, as WordPress names the Builder block's `viewScriptModule`.
	 *
	 * @since 0.6.0
	 */
	public const string MODULE = 'creative-skateboard-builder-builder-view-script-module';

	/**
	 * The builder's stylesheet, as WordPress names the Builder block's `style` (D-159).
	 *
	 * @since 0.6.0
	 */
	public const string STYLE = 'creative-skateboard-builder-builder-style';

	/**
	 * Whether a builder has rendered on this page.
	 *
	 * @var bool
	 */
	private bool $rendered = false;

	/**
	 * What the builder shows, by kit, address and base, worked out once a request: the page's title asks before the
	 * builder renders.
	 *
	 * @var array<string, array{state: \CreativeBranding\SkateboardBuilder\Service\State, answer: array<string, mixed>, view: array<string, mixed>}|null>
	 */
	private array $shown = array();

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
	 * The customer's saved builds, for Save build on the review; null in a container that predates them (D-208).
	 *
	 * @var SavedBuilds|null
	 */
	private ?SavedBuilds $saved = null;

	/**
	 * The same page, with Save build for a signed-in customer (D-217). Only this release's container calls it, so a
	 * page an earlier release's container builds mid-update has no Save build (D-208).
	 *
	 * @since 0.8.0
	 *
	 * @param SavedBuilds $saved The customer's saved builds.
	 */
	public function withSaved( SavedBuilds $saved ): self {
		$page        = clone $this;
		$page->saved = $saved;
		return $page;
	}

	/**
	 * Save build's part of the store's state: whether a customer is signed in, where the form posts, its nonce, and
	 * the saved build the page was opened from where it is theirs. Nothing of a customer's for a visitor signed out,
	 * whose page a cache may keep.
	 *
	 * @since 0.8.0
	 *
	 * @return array{signedIn: bool, action: string, nonce: string, saved: string}
	 */
	public function account(): array {
		$user = get_current_user_id();
		if ( null === $this->saved || $user < 1 ) {
			return array(
				'signedIn' => false,
				'action'   => '',
				'nonce'    => '',
				'saved'    => '',
			);
		}
		// The page now holds this customer's nonce. WordPress sends them no-cache headers (wp-includes/class-wp.php:443);
		// a page cache that reads only this constant is told too (spec: Security).
		if ( ! defined( 'DONOTCACHEPAGE' ) ) {
			define( 'DONOTCACHEPAGE', true ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedConstantFound -- The page caches' own constant, which they read by this name.
		}
		$sent = isset( $_GET[ MyBuildsView::PARAM ] ) && is_string( $_GET[ MyBuildsView::PARAM ] ) ? sanitize_text_field( wp_unslash( $_GET[ MyBuildsView::PARAM ] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- Read and checked against its owner, nothing changed.
		return array(
			'signedIn' => true,
			'action'   => SavedBuildHooks::url(),
			'nonce'    => wp_create_nonce( SavedBuildHooks::SAVE ),
			// Honoured only for its owner (spec: Security; D-217).
			'saved'    => null === $this->saved->find( $user, $sent ) ? '' : $sent,
		);
	}

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
	 * @param bool      $titled  Whether the builder prints the product's name as the page's h1: where it takes the
	 *                           place of the theme's title (spec: Where it renders, One h1).
	 */
	public function render( int $kit, BuildLink $link, string $base, bool $process = true, bool $titled = false ): string {
		if ( $this->rendered || ! $this->kits->isPublic( $kit ) ) {
			return '';
		}
		$this->rendered = true;
		$level          = ob_get_level();
		try {
			$shown = $this->shown( $kit, $link, $base );
			if ( null === $shown ) {
				return '';
			}
			wp_interactivity_state(
				self::STORE,
				array(
					'view'    => $shown['view'],
					// Said by the store after a pick, never on load: the page has just been read out.
					'status'  => '',
					'pending' => false,
					// Set by the store once Add to cart has put the build in the cart without a page load (D-155).
					'added'   => false,
					// Set by the store where the browser has a clipboard to write to (D-156).
					'canCopy' => false,
					// Save build, for a signed-in customer (D-217).
					'account' => $this->account(),
					// Set by the store, which turns the board over with the button by it; without the script the
					// step's own side is shown (#46).
					'canTurn' => false,
					// Whether the turn's two buttons show: once the store runs, and not while the 3D view is open, whose
					// own Top and Underside move its camera (D-522). The store works it out; the server's page has neither.
					'turns'   => false,
					// Set by the store once it runs: the shelf's chips and Sort then send themselves, and their Apply
					// buttons, for a page with no script, are hidden (D-266).
					'live'    => false,
					// Set by the store once a turn is seen, so the board turns where motion is allowed, never on load.
					'turning' => false,
					// What a press of the step's dimmed Next says is missing, set by the store (#100). The store dims
					// Next itself once it runs, so without the script nothing is dimmed and the form is sent as before.
					'missing' => '',
				)
			);
			// Where the store asks for the next view: the state route, for this page, in its language (D-148), and the
			// page's title around the step.
			wp_interactivity_config(
				self::STORE,
				array(
					// Where Add to cart sends the build with JavaScript: the Store API's cart (D-155).
					'cart'     => rest_url( 'wc/store/v1/cart' ),
					'endpoint' => rest_url( 'cbuild/v1/kits/' . $kit . '/state' ),
					'page'     => is_singular() ? get_queried_object_id() : 0,
					'locale'   => determine_locale(),
					// What goes around each step in the page's title (D-149).
					'title'    => PageTitle::around( PageTitle::base() ),
					// What the step says when the route has not answered within the store's limit (D-364).
					'late'     => __( 'The shop did not answer in time. Try again in a moment.', 'creative-skateboard-builder' ),
				)
			);
			// The block's own render enqueues it; the kit's page has no block.
			wp_enqueue_script_module( self::MODULE );
			wp_enqueue_style( self::STYLE );
			// A build added here without a page load shows in the page's Mini-Cart, grouped (D-158).
			ShowHooks::styles();
			// The panel's head: a way back to the shop for a theme whose header has none, and the h1 where the builder
			// takes the theme's title's place (spec: Where it renders).
			$head = array(
				'shop'  => (string) wc_get_page_permalink( 'shop' ),
				'title' => $titled ? get_the_title( $kit ) : '',
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
			// Once an hour a kit: a broken builder on a busy page would otherwise write a line on every view.
			$this->logger->hourly(
				'render_' . $kit,
				Logger::ERROR,
				'The builder could not be shown.',
				array(
					'kit'   => $kit,
					'error' => $failure->getMessage(),
				)
			);
			$html = '<p class="cbuild-failed">' . esc_html__( 'This builder isn\'t available right now.', 'creative-skateboard-builder' ) . '</p>';
			if ( current_user_can( 'manage_woocommerce' ) ) {
				// A signed-in page is not served from a page cache, so no visitor is shown this.
				/* translators: %s: why the builder could not be shown, as the error said it. */
				$html .= '<p class="cbuild-failed__why">' . esc_html( sprintf( __( 'Shown to shop managers only: %s', 'creative-skateboard-builder' ), $failure->getMessage() ) ) . '</p>';
			}
			return $html;
		}
	}

	/**
	 * The page's title for a builder's step, "Error:" first where one is shown, or null where the builder shows nothing
	 * (D-149). A failure is the render's to log and say.
	 *
	 * @since 0.6.0
	 *
	 * @param int       $kit  The kit product's ID.
	 * @param BuildLink $link The build in the address.
	 * @param string    $base The address of the page the builder is on, with no build in it.
	 */
	public function title( int $kit, BuildLink $link, string $base ): ?string {
		if ( ! $this->kits->isPublic( $kit ) ) {
			return null;
		}
		try {
			$shown = $this->shown( $kit, $link, $base );
		} catch ( Throwable ) {
			return null;
		}
		return null === $shown ? null : Cast::str( $shown['view']['title'] ?? null );
	}

	/**
	 * A build link's share card, from what the page shows, or null where the builder shows nothing (D-200).
	 *
	 * @since 0.7.0
	 *
	 * @param int       $kit   The kit product's ID.
	 * @param BuildLink $link  The build in the address.
	 * @param string    $base  The address of the page the builder is on, with no build in it.
	 * @param ShareCard $cards Works the card out.
	 * @return array{title: string, description: string, url: string, image: array{src: string, width: int, height: int, type: string}|null, waiting: bool}|null
	 */
	public function card( int $kit, BuildLink $link, string $base, ShareCard $cards ): ?array {
		if ( ! $this->kits->isPublic( $kit ) ) {
			return null;
		}
		try {
			$shown = $this->shown( $kit, $link, $base );
			return null === $shown ? null : $cards->of( $shown['state'], $shown['answer'], $link, $base );
		} catch ( Throwable ) {
			// The render logs and says what failed; a card is left out.
			return null;
		}
	}

	/**
	 * What the builder shows, once a request for each kit, address and base.
	 *
	 * @param int       $kit  The kit.
	 * @param BuildLink $link The address.
	 * @param string    $base The page.
	 * @return array{state: \CreativeBranding\SkateboardBuilder\Service\State, answer: array<string, mixed>, view: array<string, mixed>}|null
	 *
	 * @throws \CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney When the build cannot be priced.
	 */
	private function shown( int $kit, BuildLink $link, string $base ): ?array {
		$key = $kit . "\n" . $base . "\n" . wp_json_encode( get_object_vars( $link ) );
		if ( ! array_key_exists( $key, $this->shown ) ) {
			$this->shown[ $key ] = $this->builder->show( $kit, $link, $base );
		}
		return $this->shown[ $key ];
	}

	/**
	 * The build in the address of this request. A GET's query is read and nothing is changed by it, so no nonce is
	 * asked for: a build link is shared, and each ID in it is checked by the state before anything is shown.
	 *
	 * @since 0.6.0
	 */
	public static function requested(): BuildLink {
		return BuildLink::read( self::sent() );
	}

	/**
	 * What this request sent under `cbuild`, as text, unread: for a page that passes a build on to the builder's own,
	 * which reads it there.
	 *
	 * @since 0.6.0
	 */
	public static function sent(): mixed {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- as requested() says.
		return isset( $_GET[ BuildLink::PARAM ] ) ? map_deep( wp_unslash( $_GET[ BuildLink::PARAM ] ), 'sanitize_text_field' ) : null;
	}
}
