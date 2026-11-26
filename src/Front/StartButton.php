<?php
/**
 * "Build a complete with this deck" on a deck's own page.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slot;
use CreativeBranding\SkateboardBuilder\Hookable;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The way in from a deck's own product page (spec: Ways in that skip the hunt, D-253): a button after its add-to-cart
 * form, `woocommerce_after_add_to_cart_form` (WooCommerce 11.3.0, templates/single-product/add-to-cart/simple.php:54,
 * variable.php:125, external.php:32, grouped.php:145), that opens a kit's builder with the deck picked. The deck brings
 * its width, so the builder skips the question.
 *
 * - **Which kit.** One whose first slot offers the product, or one of its variations, in stock, and whose Build tab
 *   leaves Show "Build a complete with this deck" on its parts' pages on (1.2.0, #115); where more than one does, the
 *   first by menu order, then by ID.
 * - **Which deck.** The product, or its first variation the kit offers in stock; on a variable product's page, the
 *   variation the shopper chooses in its form, where the kit offers it in stock (the spec reviewer's M10 minor M3).
 * - **What a shop changes.** `cbuild_start_button`.
 * - **How it is drawn** (#133, D-377). As the theme's own button, `button` for a classic theme and `wp-element-button`
 *   for a block theme, and never Add to cart's `single_add_to_cart_button`, which WooCommerce's scripts bind to, nor
 *   `alt`. A script after it gives it Add to cart's font and height as the page draws them, and where the theme draws
 *   it in Add to cart's fill it takes Add to cart's colour as an outline, so it reads as the page's second action.
 *   Words that do not read on its own fill take Add to cart's, or black or white with the outline (#149, D-416).
 *
 * @since 1.0.0
 */
final class StartButton implements Hookable {

	/**
	 * Where the button goes unless a shop moves it.
	 */
	private const string HOOK = 'woocommerce_after_add_to_cart_form';

	/**
	 * Follows the variation chosen in WooCommerce's form: its `found_variation` and `reset_data` events
	 * (assets/js/frontend/add-to-cart-variation.js:318, :320 in 11.3.0) carry no DOM event, so jQuery's are read. The
	 * links are the server's, one a variation, in the button's `data-cbuild-starts`; a variation not among them keeps
	 * the button's own.
	 */
	private const string FOLLOW = 'jQuery(function($){$(".variations_form").on("found_variation reset_data",function(e,v){$(".cbuild-start__link[data-cbuild-starts]").each(function(){var a=$(this),s=a.data("cbuildStarts")||{},d=a.data("cbuildStart");a.attr("href",v&&s[v.variation_id]?s[v.variation_id]:d);});});});';

	/**
	 * The handle the button's few rules are printed under: a handle with no file, as the carts' (D-377).
	 */
	private const string HANDLE = 'cbuild-start';

	/**
	 * The words the button takes where the theme's own do not read on its fill, in the colour the script below sets as
	 * `--cbuild-start-words`, at three classes' weight, over a page builder's row that colours its links (#149, D-416).
	 * The outline the button takes where the theme draws it as Add to cart's twin, in the colour the script below sets
	 * as `--cbuild-start-ink`. Its weight outranks a theme's plain `.button:hover`. An inset ring, not a border, so its
	 * height stays Add to cart's. Focused, it keeps the outline's ground and words, as a theme's focus fill would put
	 * the twin's words on the theme's own (F34, D-514), and loses only its ring, so the theme's focus mark shows whether
	 * it is an outline or a shadow (the auditor's third pass on M12). The paragraph keeps no inline padding: WooCommerce's add-to-cart form
	 * block pads every child after its first by 4 px (`:where(.wp-block-woocommerce-add-to-cart-form>*+*)`, in
	 * assets/client/blocks/wc-block-library-style.css, 11.3.1), which set the button 4 px in from the quantity and
	 * Add to cart on a block theme.
	 */
	private const string STYLES = '.cbuild-start{padding-inline:0}:where(.cbuild-start__link){display:inline-block}.cbuild-start .cbuild-start__link.cbuild-start__link--words{color:var(--cbuild-start-words)}.cbuild-start .cbuild-start__link.cbuild-start__link--outline{background:transparent;color:var(--cbuild-start-ink);border-color:var(--cbuild-start-ink)}.cbuild-start .cbuild-start__link.cbuild-start__link--outline:not(:focus){box-shadow:inset 0 0 0 2px var(--cbuild-start-ink)}.cbuild-start .cbuild-start__link.cbuild-start__link--outline:not(:focus):hover{background:color-mix(in srgb,var(--cbuild-start-ink) 12%,transparent)}';

	/**
	 * Gives the button just printed Add to cart's font, weight and height as the page draws them (#133, D-377): a theme
	 * may style Add to cart through its own class or a setting aimed at it alone, which no rule of the plugin's can
	 * name without naming the theme. The padding makes up any difference in the borders. Its colours (#149, D-416):
	 * - **Its words.** Where the theme's own words read below 4.5:1 on the button's fill (or, with no fill, on the first
	 *   background behind it), as a page builder's row that colours its links makes them, the button takes Add to
	 *   cart's words where they read on that fill, else black or white, whichever reads more, with the outline.
	 * - **The outline.** Where the theme draws the button in Add to cart's fill, whatever its words, or its words read
	 *   on its fill in neither its own colour nor Add to cart's, it takes the outline above, in Add to cart's fill where
	 *   that reads at 4.5:1 or more against the first background behind the button that is not transparent (white
	 *   where none is), else in Add to cart's words where they do, else not at all (WCAG 1.4.3, 1.4.11). The ratio is
	 * WCAG's, from relative luminance; a part-transparent background is read as solid, and a background image not at
	 * all. The script holds no angle bracket: a block theme passes the template's markup through WordPress's
	 * texturizer, which takes a "<" and a later ">" in a script for a tag and turns the script's "&&" into entities. It runs as the button is parsed, before the first paint,
	 * and finds every start button where a page has moved the script away from it. A theme's button may change size at
	 * a breakpoint, so a ResizeObserver on Add to cart copies them again whenever its size changes, and lets go once it
	 * leaves the page. The copy is made with the theme's transitions off, so a theme that eases every change of its
	 * buttons (Kadence's) shows no growing or shrinking button. Without it the button is the theme's as it stands.
	 */
	private const string MATCH = '(function(s){var w=s&&s.previousElementSibling,u=function(x){var m=String(x).match(/^rgba?\\(([^)]*)\\)$/);return m?m[1].split(/[\\s,\\/]+/).filter(Boolean).map(parseFloat):null},l=function(v){return[.2126,.7152,.0722].reduce(function(t,k,i){var j=v[i]/255;return t+k*(j===Math.min(j,.04045)?j/12.92:Math.pow((j+.055)/1.055,2.4));},0)},q=function(x,y){var i=u(x),j=u(y),h,m;if(!i||!j){return 0;}h=l(i);m=l(j);return(Math.max(h,m)+.05)/(Math.min(h,m)+.05)};[].forEach.call(w&&w.matches(".cbuild-start")?[w]:document.querySelectorAll(".cbuild-start"),function(p){var b=p.querySelector(".cbuild-start__link"),a=(p.closest(".product")||document).querySelector(".single_add_to_cart_button");if(!b||!a){return;}var c=getComputedStyle(a),o=getComputedStyle(b),n=function(x,k){return parseFloat(x[k])||0},r=window.ResizeObserver?new ResizeObserver(function(){f();}):null,f=function(){if(!a.isConnected){if(r){r.disconnect();}return;}b.style.transition="none";["fontFamily","fontSize","fontWeight","letterSpacing","lineHeight"].forEach(function(k){b.style[k]=c[k];});b.style.paddingTop=Math.max(0,n(c,"paddingTop")+n(c,"borderTopWidth")-n(o,"borderTopWidth"))+"px";b.style.paddingBottom=Math.max(0,n(c,"paddingBottom")+n(c,"borderBottomWidth")-n(o,"borderBottomWidth"))+"px";void b.offsetHeight;b.style.transition="";};var W="rgb(255, 255, 255)",K="rgb(0, 0, 0)",g=W,e,t,v,k,i,y,z,h=function(x){return/^(transparent|rgba\\(.*, 0\\))$/.test(x)},m=function(x,y){return 4.5===Math.min(q(x,y),4.5)};for(e=p;e;e=e.parentElement){t=getComputedStyle(e).backgroundColor;v=u(t);if(v&&0!==v[3]){g=t;break;}}y=h(o.backgroundColor)?g:o.backgroundColor;z=m(o.color,y);if(!z&&u(y)){t=m(c.color,y)?c.color:q(K,y)===Math.max(q(K,y),q(W,y))?K:W;b.style.setProperty("--cbuild-start-words",t);b.classList.add("cbuild-start__link--words");z=t===c.color;}k=h(c.backgroundColor)?c.color:c.backgroundColor;i=m(k,g)?k:m(c.color,g)?c.color:"";if(i&&(o.backgroundColor===c.backgroundColor||!z)){b.style.setProperty("--cbuild-start-ink",i);b.classList.add("cbuild-start__link--outline");}if("inline"===o.display){b.style.display="inline-block";}f();if(r){r.observe(a);}});})(document.currentScript);';

	/**
	 * Constructor.
	 *
	 * @since 1.0.0
	 *
	 * @param Closure(): Kits $kits The kits and their parts, built on a product's page alone.
	 */
	public function __construct( private Closure $kits ) {}

	/**
	 * Where each kit's builder is, as its Build tab chose (1.2.0, #113): none in a copy an earlier release's container
	 * built, which asks `BuilderUrl::of()` as 1.1.0 did (D-208).
	 *
	 * @var BuilderAddress|null
	 */
	private ?BuilderAddress $address = null;

	/**
	 * The same, sending shoppers to the Builder page each kit's Build tab chose (#113, D-348). Only this release's
	 * container calls it (D-208).
	 *
	 * @since 1.2.0
	 *
	 * @param BuilderAddress $address Where each kit's builder is.
	 */
	public function withAddress( BuilderAddress $address ): self {
		$copy          = clone $this;
		$copy->address = $address;
		return $copy;
	}

	/**
	 * Attach hooks: the button is placed once the request knows its product, so a shop's filter can move it.
	 *
	 * @since 1.0.0
	 */
	public function register(): void {
		add_action( 'wp', array( $this, 'place' ) );
	}

	/**
	 * On a product's page, the button where the shop wants it, if any kit offers the product first.
	 *
	 * @since 1.0.0
	 */
	public function place(): void {
		if ( ! is_singular( 'product' ) ) {
			return;
		}
		$product = get_queried_object_id();
		$start   = $this->start( $product );
		$button  = null === $start ? false : $start + array(
			'text'     => __( 'Build a complete with this deck', 'creative-skateboard-builder' ),
			'hook'     => self::HOOK,
			'priority' => 10,
		);
		/**
		 * Filters "Build a complete with this deck" on a product's page. The button is an array with `kit` (the kit
		 * product's ID), `part` (the product or variation it opens picked), `slot` (the kit's slot it is picked in: the first, or another of the kit's slots), `text`, `hook` (the action it prints on,
		 * `woocommerce_after_add_to_cart_form` by default) and `priority`; or false where no kit offers the product in
		 * its first slot. Return false to leave the button out, or an array to choose the kit, move it or word it. A
		 * kit a visitor may not see shows no button, and the builder checks the part as it checks any link.
		 *
		 * @since 1.0.0
		 *
		 * @param array{kit: int, part: int, slot: string, text: string, hook: string, priority: int}|false $button  The button, or false.
		 * @param int                                                                                       $product The product's ID.
		 */
		$said = apply_filters( 'cbuild_start_button', $button, $product );
		if ( ! is_array( $said ) ) {
			return;
		}
		$kit  = Cast::int( $said['kit'] ?? null );
		$part = Cast::int( $said['part'] ?? null );
		$text = trim( wp_strip_all_tags( Cast::str( $said['text'] ?? null ) ) );
		$hook = Cast::str( $said['hook'] ?? null );
		$kits = ( $this->kits )();
		$read = $kit > 0 && $kits->isPublic( $kit ) ? $kits->kit( $kit ) : null;
		$slot = null === $read ? null : ( $read->slots->get( Cast::str( $said['slot'] ?? null ) ) ?? $read->slots->all()[0] ?? null );
		if ( null === $read || null === $slot || $part < 1 || '' === $text || '' === $hook ) {
			return;
		}
		$base   = $this->address?->of( $kit, $read ) ?? BuilderUrl::of( $kit );
		$url    = ( new BuildLink() )->pick( $slot->key, $part )->address( $base, $read->slots );
		$starts = $this->starts( $product, $kit, $slot, $read, $base );
		add_action(
			$hook,
			static function () use ( $url, $text, $starts ): void {
				if ( array() === $starts ) {
					printf( '<p class="cbuild-start"><a class="button wp-element-button cbuild-start__link" href="%1$s" rel="nofollow">%2$s</a></p>', esc_url( $url ), esc_html( $text ) );
				} else {
					printf(
						'<p class="cbuild-start"><a class="button wp-element-button cbuild-start__link" href="%1$s" rel="nofollow" data-cbuild-start="%1$s" data-cbuild-starts="%2$s">%3$s</a></p>',
						esc_url( $url ),
						esc_attr( (string) wp_json_encode( array_map( 'esc_url_raw', $starts ) ) ),
						esc_html( $text )
					);
				}
				wp_print_inline_script_tag( self::MATCH );
			},
			Cast::int( $said['priority'] ?? 10 )
		);
		add_action(
			'wp_enqueue_scripts',
			static function (): void {
				// A handle with no file: WordPress prints what is added to it and asks for nothing.
				wp_register_style( self::HANDLE, false, array(), CBUILD_VERSION );
				wp_add_inline_style( self::HANDLE, self::STYLES );
				wp_enqueue_style( self::HANDLE );
			}
		);
		if ( array() !== $starts ) {
			// After WooCommerce registers its variation script (class-wc-frontend-scripts.php:56, :238 in 11.3.0).
			add_action(
				'wp_enqueue_scripts',
				static function (): void {
					wp_add_inline_script( 'wc-add-to-cart-variation', self::FOLLOW );
				},
				20
			);
		}
	}

	/**
	 * On a variable product's page, the builder's link for each of its variations the slot offers in stock, by ID.
	 *
	 * @param int    $product The product's ID.
	 * @param int    $kit     The kit product's ID.
	 * @param Slot   $slot    The slot the deck is picked in.
	 * @param Kit    $read    The kit.
	 * @param string $base    The builder's address for the kit.
	 * @return array<int, string>
	 */
	private function starts( int $product, int $kit, Slot $slot, Kit $read, string $base ): array {
		$found = wc_get_product( $product );
		if ( ! $found instanceof \WC_Product_Variable ) {
			return array();
		}
		$children = array_map( Cast::int( ... ), $found->get_children() );
		$starts   = array();
		foreach ( ( $this->kits )()->offered( $kit, $slot ) as $part ) {
			if ( $part->inStock && in_array( $part->id, $children, true ) ) {
				$starts[ $part->id ] = ( new BuildLink() )->pick( $slot->key, $part->id )->address( $base, $read->slots );
			}
		}
		return $starts;
	}

	/**
	 * The kit and the part the button opens, or null where no kit offers the product first.
	 *
	 * @param int $product The product's ID.
	 * @return array{kit: int, part: int, slot: string}|null
	 */
	private function start( int $product ): ?array {
		$found = wc_get_product( $product );
		if ( ! $found instanceof \WC_Product ) {
			return null;
		}
		$ids   = array( $product, ...array_map( Cast::int( ... ), $found->get_children() ) );
		$every = ( $this->kits )();
		// Asked once, of the product: its variations are in its categories, brands and attributes. A kit that takes it
		// in its first slot then finds its parts among what is kept of the slot's, with no product read (the auditor's
		// M10 review, 4).
		$kits = array_keys( $every->all() );
		$rank = array();
		foreach ( $kits as $kit ) {
			$rank[ $kit ] = (int) get_post_field( 'menu_order', $kit );
		}
		usort( $kits, static fn( int $a, int $b ): int => array( $rank[ $a ], $a ) <=> array( $rank[ $b ], $b ) );
		foreach ( $kits as $kit ) {
			$read = $every->isPublic( $kit ) ? $every->kit( $kit ) : null;
			$slot = null === $read ? null : ( $read->slots->all()[0] ?? null );
			// A kit whose Show "Build a complete with this deck" on its parts' pages is off is passed over, and the next
			// that offers the part places it (#115, D-349).
			if ( null === $read || null === $slot || ! $read->settings->startButton ) {
				continue;
			}
			if ( ! in_array( $slot->key, $every->slotsOf( $product, $read ), true ) ) {
				continue;
			}
			foreach ( $every->offered( $kit, $slot ) as $part ) {
				if ( $part->inStock && in_array( $part->id, $ids, true ) ) {
					return array(
						'kit'  => $kit,
						'part' => $part->id,
						'slot' => $slot->key,
					);
				}
			}
		}
		return null;
	}
}
