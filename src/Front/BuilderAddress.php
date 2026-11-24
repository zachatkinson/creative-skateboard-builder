<?php
/**
 * Where a kit's builder is, as its Build tab and the shop's filter say.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use Closure;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Kit;
use CreativeBranding\SkateboardBuilder\Port\Kits;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * The address of the page a kit's builder is on, with no build in it, from 1.2.0 (#113, D-348): the Builder page the
 * kit's Build tab chose, where it is a published page a visitor may see and still holds the kit's Builder block, and
 * otherwise the kit's own page, as 1.1.0 had it. That is the starting value of `cbuild_builder_url`, which answers last
 * (`BuilderUrl::from()`). The page is checked each time the address is asked for, so one that has been unpublished,
 * trashed or deleted, or has lost the block, sends no one there.
 *
 * The kit's page sends a visitor there, and "Build a complete with this deck", the cart's Edit build, My builds' Open
 * and the state route's links start from it. Each of those classes was built by an earlier release's container too, so
 * each takes this through a with-er that only this release's container calls (D-208); a copy without it asks
 * `BuilderUrl::of()`, as 1.1.0 did.
 *
 * @since 1.2.0
 */
final class BuilderAddress {

	/**
	 * Constructor.
	 *
	 * @since 1.2.0
	 *
	 * @param Closure(): Kits $kits  The shop's kits, read when a kit the caller has not read is asked for.
	 * @param BuilderPlace    $place Whether a page holds a kit's builder.
	 */
	public function __construct( private Closure $kits, private BuilderPlace $place ) {}

	/**
	 * The builder's address for a kit.
	 *
	 * @since 1.2.0
	 *
	 * @param int      $kit  The kit product's ID.
	 * @param Kit|null $read The kit, where the caller has read it already.
	 */
	public function of( int $kit, ?Kit $read = null ): string {
		$page = $this->page( $kit, $read );
		return BuilderUrl::from( $kit, (string) get_permalink( $page > 0 ? $page : $kit ) );
	}

	/**
	 * The Builder page the kit's Build tab chose, where it may be used now, or 0 for the kit's own page.
	 *
	 * @since 1.2.0
	 *
	 * @param int      $kit  The kit product's ID.
	 * @param Kit|null $read The kit, where the caller has read it already.
	 */
	public function page( int $kit, ?Kit $read = null ): int {
		$chosen = ( $read ?? ( $this->kits )()->kit( $kit ) )?->settings->builderPage;
		return null !== $chosen && $this->place->opens( $chosen, $kit ) ? $chosen : 0;
	}
}
