<?php
/**
 * Posts and their blocks, as told.
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Tests\Support\Fakes;

use CreativeBranding\SkateboardBuilder\Port\BuilderPosts;

/**
 * Each post's parsed blocks, and the kits among them.
 */
final class FakeBuilderPosts implements BuilderPosts {

	/**
	 * How many times each post's blocks were asked for.
	 *
	 * @var array<int, int>
	 */
	public array $asked = array();

	/**
	 * Constructor.
	 *
	 * @param array<int, array<mixed>> $blocks Each post's parsed blocks.
	 * @param list<int>                $kits   The posts that are kits.
	 */
	public function __construct( private array $blocks = array(), private array $kits = array() ) {}

	public function blocks( int $post ): array {
		$this->asked[ $post ] = ( $this->asked[ $post ] ?? 0 ) + 1;
		return $this->blocks[ $post ] ?? array();
	}

	public function isKit( int $post ): bool {
		return in_array( $post, $this->kits, true );
	}
}
