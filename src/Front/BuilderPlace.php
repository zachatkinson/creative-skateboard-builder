<?php
/**
 * Where a builder is.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Port\BuilderPosts;
use CreativeBranding\SkateboardBuilder\Support\Cast;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * One answer to which kit a page shows, which the page's title, its robots and canonical, and the state route's links
 * each ask (audit 14 and 16, milestone 6): a kit's own page shows its kit, and any other post the kit of its first
 * Builder block, at any depth and inside a synced pattern. A product that is no kit shows none, whatever its
 * description holds.
 *
 * @since 0.6.0
 */
final class BuilderPlace {

	/**
	 * The synced pattern's block, whose `ref` names the pattern's post.
	 */
	private const SYNCED = 'core/block';

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param BuilderPosts $posts The posts and their blocks.
	 */
	public function __construct( private BuilderPosts $posts ) {}

	/**
	 * The kit a post shows, or 0.
	 *
	 * @since 0.6.0
	 *
	 * @param int    $post The post's ID.
	 * @param string $type The post's type.
	 */
	public function kit( int $post, string $type ): int {
		if ( $post < 1 ) {
			return 0;
		}
		if ( 'product' === $type ) {
			return $this->posts->isKit( $post ) ? $post : 0;
		}
		$found = 0;
		$this->walk(
			$this->posts->blocks( $post ),
			static function ( int $kit ) use ( &$found ): bool {
				$found = $kit;
				return true;
			},
			array( $post )
		);
		return $found;
	}

	/**
	 * Whether a post holds a kit's builder: the kit's own page, or any of its Builder blocks.
	 *
	 * @since 0.6.0
	 *
	 * @param int $post The post's ID.
	 * @param int $kit  The kit's ID.
	 */
	public function holds( int $post, int $kit ): bool {
		if ( $kit < 1 || $post < 1 ) {
			return false;
		}
		if ( $post === $kit ) {
			return $this->posts->isKit( $post );
		}
		return $this->walk(
			$this->posts->blocks( $post ),
			static fn ( int $found ): bool => $found === $kit,
			array( $post )
		);
	}

	/**
	 * Whether a kit's builder may be sent to a page: a published page a visitor may see, other than the kit's own, that
	 * holds the kit's Builder block (#113, D-348). Asked each time the page is used, so one unpublished, trashed or
	 * deleted, or one that has lost the block, is one no longer.
	 *
	 * @since 1.2.0
	 *
	 * @param int $page The page's ID.
	 * @param int $kit  The kit's ID.
	 */
	public function opens( int $page, int $kit ): bool {
		return $page > 0 && $page !== $kit && $this->posts->isPage( $page ) && $this->holds( $page, $kit );
	}

	/**
	 * The pages a kit's builder may be sent to, in the order the site lists them: what the kit's Build tab offers.
	 *
	 * @since 1.2.0
	 *
	 * @param int $kit The kit's ID.
	 * @return list<int>
	 */
	public function pages( int $kit ): array {
		return array_values( array_filter( $this->posts->pages(), fn( int $page ): bool => $this->opens( $page, $kit ) ) );
	}

	/**
	 * Walk blocks in order, inner blocks and synced patterns included, telling each Builder block's kit until told
	 * to stop. A pattern already being walked is not walked again, so one that holds itself ends.
	 *
	 * @param array<mixed>        $blocks Parsed blocks.
	 * @param callable(int): bool $each   Told each Builder block's kit, 0 for one that names none; true stops.
	 * @param list<int>           $seen   The posts being walked.
	 * @return bool Whether it was told to stop.
	 */
	private function walk( array $blocks, callable $each, array $seen ): bool {
		foreach ( $blocks as $block ) {
			$block = Cast::map( $block );
			$name  = $block['blockName'] ?? null;
			$attrs = Cast::map( $block['attrs'] ?? null );
			if ( PageHooks::BLOCK === $name ) {
				if ( $each( Cast::int( $attrs['kit'] ?? null ) ) ) {
					return true;
				}
				continue;
			}
			if ( self::SYNCED === $name ) {
				$ref = Cast::int( $attrs['ref'] ?? null );
				if ( $ref > 0 && ! in_array( $ref, $seen, true ) && $this->walk( $this->posts->blocks( $ref ), $each, array( ...$seen, $ref ) ) ) {
					return true;
				}
				continue;
			}
			if ( $this->walk( Cast::items( $block['innerBlocks'] ?? null ), $each, $seen ) ) {
				return true;
			}
		}
		return false;
	}
}
