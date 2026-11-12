<?php
/**
 * The build link.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\Fields;
use CreativeBranding\SkateboardBuilder\Domain\Kit\Slots;
use CreativeBranding\SkateboardBuilder\Service\State;

/**
 * A build as the query parameters of a page: `cbuild[<slot key>]=<part ID>`, with `cbuild[step]`,
 * `cbuild[assembly]` and `cbuild[preset]` (spec: Upgrades and the public API). It is how a build is shared, how the
 * builder's forms carry it without JavaScript, and what the browser's history holds with it.
 *
 * What the builder's own forms need beside those four travels under keys that start with an underscore, which no
 * slot's key can (D-142): `_was`, the picks before a change, slot key to part ID; `_brand` and `_size`, the step's
 * filters; `_shown`, how many options the step lists; `_replace`, the build in the cart an add takes the place of.
 *
 * Nothing sent is trusted. A link is read into IDs, keys and flags, and what is not one of those is left out. Which
 * parts a slot may hold, and whether an ID is a part at all, is the state's to say.
 *
 * @since 0.6.0
 */
final readonly class BuildLink {

	/**
	 * The query parameter.
	 *
	 * @since 0.6.0
	 */
	public const string PARAM = 'cbuild';

	/**
	 * The step that is no slot's: the review.
	 *
	 * @since 0.6.0
	 */
	public const string REVIEW = State::REVIEW;

	/**
	 * How many options a step lists to begin with, and how many more each Show more adds.
	 *
	 * @since 0.6.0
	 */
	public const int PAGE = 24;

	/**
	 * The most options a step lists: every part a slot can have.
	 *
	 * @since 0.6.0
	 */
	public const int MOST = 500;

	/**
	 * Constructor.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string, int>      $picks    Slot key to part ID, in the order sent.
	 * @param array<string, int>|null $was      The picks before the last change, or null where none was sent.
	 * @param string|null             $step     The step: a slot's key or the review.
	 * @param bool|null               $assembly Assembly on or off, or null where the link does not say.
	 * @param int|null                $preset   Which of the kit's presets the build began as, counted from 0.
	 * @param string                  $brand    The step's brand filter: a term's slug, or empty.
	 * @param string                  $size     The step's size filter, or empty.
	 * @param int                     $shown    How many options the step lists, or 0 for the first page.
	 * @param string                  $replace  The identifier of the build in the cart this one replaces, or empty.
	 */
	public function __construct(
		public array $picks = array(),
		public ?array $was = null,
		public ?string $step = null,
		public ?bool $assembly = null,
		public ?int $preset = null,
		public string $brand = '',
		public string $size = '',
		public int $shown = 0,
		public string $replace = ''
	) {}

	/**
	 * Read what a request sent under `cbuild`.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $sent The parameter's value, unslashed.
	 */
	public static function read( mixed $sent ): self {
		$sent     = is_array( $sent ) ? $sent : array();
		$step     = $sent['step'] ?? null;
		$assembly = $sent['assembly'] ?? null;
		$preset   = self::number( $sent['preset'] ?? null );
		$was      = self::ids( $sent['_was'] ?? null );
		$shown    = self::number( $sent['_shown'] ?? null );
		return new self(
			self::ids( $sent ),
			array() === $was ? null : $was,
			is_string( $step ) && Fields::isKey( $step ) ? $step : null,
			'1' === $assembly || '0' === $assembly ? '1' === $assembly : null,
			null !== $preset && $preset < 6 ? $preset : null,
			self::matching( $sent['_brand'] ?? null, '/\A[a-z0-9_%-]{1,200}\z/' ),
			self::matching( $sent['_size'] ?? null, '/\A[a-z0-9._-]{1,32}\z/' ),
			null === $shown || $shown <= self::PAGE ? 0 : min( self::MOST, $shown ),
			self::matching( $sent['_replace'] ?? null, '/\A[a-f0-9-]{1,64}\z/' )
		);
	}

	/**
	 * Whether the link says nothing: the page with no build in its address.
	 *
	 * @since 0.6.0
	 */
	public function isClean(): bool {
		return array() === $this->picks && null === $this->was && null === $this->step && null === $this->assembly && null === $this->preset
			&& '' === $this->brand && '' === $this->size && 0 === $this->shown && '' === $this->replace;
	}

	/**
	 * The link as query parameters under `cbuild`, in one order whatever order it was sent in: the kit's slots in
	 * step order, then the step, assembly and the preset, then the builder's own. A slot the kit does not have is
	 * left out, as is a step that is neither one of its slots nor the review.
	 *
	 * @since 0.6.0
	 *
	 * @param Slots $slots The kit's slots.
	 * @return array<string, string|array<string, string>>
	 */
	public function query( Slots $slots ): array {
		$query = array();
		$was   = array();
		foreach ( $slots->keys() as $key ) {
			if ( isset( $this->picks[ $key ] ) ) {
				$query[ $key ] = (string) $this->picks[ $key ];
			}
			if ( isset( $this->was[ $key ] ) ) {
				$was[ $key ] = (string) $this->was[ $key ];
			}
		}
		if ( null !== $this->step && ( self::REVIEW === $this->step || null !== $slots->get( $this->step ) ) ) {
			$query['step'] = $this->step;
		}
		if ( null !== $this->assembly ) {
			$query['assembly'] = $this->assembly ? '1' : '0';
		}
		if ( null !== $this->preset ) {
			$query['preset'] = (string) $this->preset;
		}
		if ( array() !== $was ) {
			$query['_was'] = $was;
		}
		if ( '' !== $this->brand ) {
			$query['_brand'] = $this->brand;
		}
		if ( '' !== $this->size ) {
			$query['_size'] = $this->size;
		}
		if ( $this->shown > 0 ) {
			$query['_shown'] = (string) $this->shown;
		}
		if ( '' !== $this->replace ) {
			$query['_replace'] = $this->replace;
		}
		return $query;
	}

	/**
	 * The link with a slot's pick set, or taken out.
	 *
	 * @since 0.6.0
	 *
	 * @param string   $slot The slot's key.
	 * @param int|null $part The part's ID, or null for none.
	 */
	public function pick( string $slot, ?int $part ): self {
		$picks = $this->picks;
		unset( $picks[ $slot ] );
		if ( null !== $part ) {
			$picks[ $slot ] = $part;
		}
		return new self( $picks, $this->was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $this->shown, $this->replace );
	}

	/**
	 * The link at another step. A step starts with no filter, its first options and no change to explain.
	 *
	 * @since 0.6.0
	 *
	 * @param string $step The step: a slot's key or the review.
	 */
	public function at( string $step ): self {
		return new self( $this->picks, null, $step, $this->assembly, $this->preset, '', '', 0, $this->replace );
	}

	/**
	 * The link with the step's filters set. What is listed starts again from the first options.
	 *
	 * @since 0.6.0
	 *
	 * @param string $brand The brand's slug, or empty.
	 * @param string $size  The size, or empty.
	 */
	public function filtered( string $brand, string $size ): self {
		return new self( $this->picks, $this->was, $this->step, $this->assembly, $this->preset, $brand, $size, 0, $this->replace );
	}

	/**
	 * The link with more of the step's options listed.
	 *
	 * @since 0.6.0
	 *
	 * @param int $shown How many.
	 */
	public function showing( int $shown ): self {
		return new self( $this->picks, $this->was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $shown, $this->replace );
	}

	/**
	 * The link with assembly on or off.
	 *
	 * @since 0.6.0
	 *
	 * @param bool $assembly Whether the shop assembles the build.
	 */
	public function assembled( bool $assembly ): self {
		return new self( $this->picks, $this->was, $this->step, $assembly, $this->preset, $this->brand, $this->size, $this->shown, $this->replace );
	}

	/**
	 * The link with the picks it follows, for the change they make to be explained.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string, int> $was The picks before the change.
	 */
	public function after( array $was ): self {
		return new self( $this->picks, $was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $this->shown, $this->replace );
	}

	/**
	 * The link for a build that takes the place of one in the cart.
	 *
	 * @since 0.6.0
	 *
	 * @param string $build The build's identifier.
	 */
	public function replacing( string $build ): self {
		return new self( $this->picks, $this->was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $this->shown, $build );
	}

	/**
	 * The link as it is shared: part IDs, the step and assembly on or off (spec: Privacy), and nothing of the visit
	 * it was made in.
	 *
	 * @since 0.6.0
	 */
	public function shared(): self {
		return new self( $this->picks, null, $this->step, $this->assembly );
	}

	/**
	 * The IDs among what was sent, by key: each a slot's key and a whole number above nothing, written as PHP
	 * writes one, so that `012` and `12` are not two addresses of one build. No more than a kit can have slots.
	 *
	 * @param mixed $sent What was sent.
	 * @return array<string, int>
	 */
	private static function ids( mixed $sent ): array {
		$ids = array();
		foreach ( is_array( $sent ) ? $sent : array() as $key => $value ) {
			$id = self::number( $value );
			if ( null !== $id && $id > 0 && is_string( $key ) && Fields::isKey( $key ) && ! isset( Slots::KEPT[ $key ] ) && count( $ids ) < Slots::MAX ) {
				$ids[ $key ] = $id;
			}
		}
		return $ids;
	}

	/**
	 * A whole number as sent, or null: digits alone with no leading zero, and small enough to be a post's ID.
	 *
	 * @param mixed $value What was sent.
	 */
	private static function number( mixed $value ): ?int {
		$text = is_int( $value ) ? (string) $value : $value;
		if ( ! is_string( $text ) || 1 !== preg_match( '/\A(?:0|[1-9][0-9]{0,17})\z/', $text ) ) {
			return null;
		}
		return (int) $text;
	}

	/**
	 * A string as sent where it matches, or an empty one.
	 *
	 * @param mixed  $value   What was sent.
	 * @param string $pattern The pattern.
	 */
	private static function matching( mixed $value, string $pattern ): string {
		return is_string( $value ) && 1 === preg_match( $pattern, $value ) ? $value : '';
	}
}
