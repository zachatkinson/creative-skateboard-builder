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
use CreativeBranding\SkateboardBuilder\Domain\Look\ShelfAsk;
use CreativeBranding\SkateboardBuilder\Service\ShelfQuery;
use CreativeBranding\SkateboardBuilder\Service\State;
use CreativeBranding\SkateboardBuilder\Support\Cast;

/**
 * A build as the query parameters of a page: `cbuild[<slot key>]=<part ID>`, with `cbuild[step]`,
 * `cbuild[assembly]` and `cbuild[preset]` (spec: Upgrades and the public API). It is how a build is shared, how the
 * builder's forms carry it without JavaScript, and what the browser's history holds with it.
 *
 * What the builder's own forms need beside those four travels under keys that start with an underscore, which no
 * slot's key can (D-142): `_was`, the picks before a change, slot key to part ID; `_brand` and `_size`, the step's
 * filters; `_shown`, how many options the step lists; `_on`, the step whose form was sent, so that a filter or Show
 * pressed in it stays on it and no other step takes them (D-150); `_replace`, the build in the cart an add takes the
 * place of. From 1.0.0 the step's shelf too (D-264): `_width`, the deck width asked, which every step keeps for the
 * visit (D-242); `_chips`, group to the values chosen; `_q`, the search; and `_sort`.
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
	 * @param list<string>            $none     The slots the shopper chose to leave empty, by key: "No risers".
	 * @param ShelfQuery              $shelf    The step's width, chips, search and sort (from 1.0.0).
	 * @param bool                    $asked    Whether a link asked for the step by name, as Add risers does, not a
	 *                                          step's form, whose Next may name a step the form's own pick undid
	 *                                          (from 1.0.0). Only a request read says so; a link made here does not.
	 * @param string                  $saved    The UUID of the saved build My builds opened, so it is checked against
	 *                                          the shop once, or empty (from 1.0.0, D-254). No link made from this one
	 *                                          carries it.
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
		public string $replace = '',
		public array $none = array(),
		public ShelfQuery $shelf = new ShelfQuery(),
		public bool $asked = false,
		public string $saved = ''
	) {}

	/**
	 * The most chip groups a link carries, and values a group.
	 */
	private const array CHIPS = array( 4, 20 );

	/**
	 * Read what a request sent under `cbuild`.
	 *
	 * @since 0.6.0
	 *
	 * @param mixed $sent The parameter's value, unslashed.
	 */
	public static function read( mixed $sent ): self {
		$sent = is_array( $sent ) ? $sent : array();
		$on   = $sent['_on'] ?? null;
		$on   = is_string( $on ) && Fields::isKey( $on ) ? $on : null;
		$step = $sent['step'] ?? $on;
		$step = is_string( $step ) && Fields::isKey( $step ) ? $step : null;
		// A step's filters and Show more are that step's: Next, sent from the same form, leaves them behind.
		$own      = null === $on || $on === $step;
		$assembly = $sent['assembly'] ?? null;
		$preset   = self::number( $sent['preset'] ?? null );
		$was      = self::ids( $sent['_was'] ?? null );
		$shown    = self::number( $sent['_shown'] ?? null );
		return new self(
			self::ids( $sent ),
			array() === $was ? null : $was,
			$step,
			'1' === $assembly || '0' === $assembly ? '1' === $assembly : null,
			null !== $preset && $preset < 6 ? $preset : null,
			$own ? self::matching( $sent['_brand'] ?? null, '/\A[a-z0-9_%-]{1,200}\z/' ) : '',
			$own ? self::matching( $sent['_size'] ?? null, '/\A[a-z0-9._-]{1,32}\z/' ) : '',
			! $own || null === $shown || $shown <= self::PAGE ? 0 : min( self::MOST, $shown ),
			self::matching( $sent['_replace'] ?? null, '/\A[a-f0-9-]{1,64}\z/' ),
			self::left( $sent ),
			new ShelfQuery(
				self::matching( $sent['_width'] ?? null, '/\A[0-9]{1,3}(?:\.[0-9]{1,4})?(?:in|mm)\z/' ),
				$own ? self::chips( $sent['_chips'] ?? null ) : array(),
				$own ? self::text( $sent['_q'] ?? null ) : '',
				$own && in_array( $sent['_sort'] ?? null, ShelfAsk::SORTS, true ) ? Cast::str( $sent['_sort'] ) : ShelfAsk::SORTS[0]
			),
			null !== $step && null === $on,
			self::matching( $sent['_saved'] ?? null, '/\A[a-f0-9-]{36}\z/' )
		);
	}

	/**
	 * Whether the link says nothing: the page with no build in its address.
	 *
	 * @since 0.6.0
	 */
	public function isClean(): bool {
		return array() === $this->picks && null === $this->was && null === $this->step && null === $this->assembly && null === $this->preset
			&& '' === $this->brand && '' === $this->size && 0 === $this->shown && '' === $this->replace && array() === $this->none
			&& '' === $this->shelf->width && array() === $this->shelf->chips && '' === $this->shelf->query && ShelfAsk::SORTS[0] === $this->shelf->sort;
	}

	/**
	 * The link as query parameters under `cbuild`, in one order whatever order it was sent in: the kit's slots in
	 * step order, then the step, assembly and the preset, then the builder's own. A slot the kit does not have is
	 * left out, as is a step that is neither one of its slots nor the review.
	 *
	 * @since 0.6.0
	 *
	 * @param Slots $slots The kit's slots.
	 * @return array<string, string|array<string, string>|array<string, list<string>>>
	 */
	public function query( Slots $slots ): array {
		$query = array();
		$was   = array();
		foreach ( $slots->keys() as $key ) {
			if ( isset( $this->picks[ $key ] ) ) {
				$query[ $key ] = (string) $this->picks[ $key ];
			} elseif ( in_array( $key, $this->none, true ) ) {
				$query[ $key ] = '0';
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
		if ( '' !== $this->saved ) {
			$query['_saved'] = $this->saved;
		}
		if ( '' !== $this->shelf->width ) {
			$query['_width'] = $this->shelf->width;
		}
		if ( '' !== $this->shelf->query ) {
			$query['_q'] = $this->shelf->query;
		}
		if ( ShelfAsk::SORTS[0] !== $this->shelf->sort ) {
			$query['_sort'] = $this->shelf->sort;
		}
		if ( array() !== $this->shelf->chips ) {
			$query['_chips'] = $this->shelf->chips;
		}
		return $query;
	}

	/**
	 * The link with a slot's pick set, or taken out. Either way the slot is no longer one the shopper left empty.
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
		return new self( $picks, $this->was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $this->shown, $this->replace, array_values( array_diff( $this->none, array( $slot ) ) ), $this->shelf );
	}

	/**
	 * The link with a slot left empty by choice: its pick taken out, and no part started on in its place.
	 *
	 * @since 0.6.0
	 *
	 * @param string $slot The slot's key.
	 */
	public function leave( string $slot ): self {
		$left = $this->pick( $slot, null );
		return new self( $left->picks, $left->was, $left->step, $left->assembly, $left->preset, $left->brand, $left->size, $left->shown, $left->replace, array_merge( $left->none, array( $slot ) ), $left->shelf );
	}

	/**
	 * The link at another step. A step starts with no filter, its first options and no change to explain.
	 *
	 * @since 0.6.0
	 *
	 * @param string $step The step: a slot's key or the review.
	 */
	public function at( string $step ): self {
		return new self( $this->picks, null, $step, $this->assembly, $this->preset, '', '', 0, $this->replace, $this->none, new ShelfQuery( $this->shelf->width ) );
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
		return new self( $this->picks, $this->was, $this->step, $this->assembly, $this->preset, $brand, $size, 0, $this->replace, $this->none, $this->shelf );
	}

	/**
	 * The link with more of the step's options listed.
	 *
	 * @since 0.6.0
	 *
	 * @param int $shown How many.
	 */
	public function showing( int $shown ): self {
		return new self( $this->picks, $this->was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $shown, $this->replace, $this->none, $this->shelf );
	}

	/**
	 * The link with assembly on or off.
	 *
	 * @since 0.6.0
	 *
	 * @param bool $assembly Whether the shop assembles the build.
	 */
	public function assembled( bool $assembly ): self {
		return new self( $this->picks, $this->was, $this->step, $assembly, $this->preset, $this->brand, $this->size, $this->shown, $this->replace, $this->none, $this->shelf );
	}

	/**
	 * The link with the picks it follows, for the change they make to be explained.
	 *
	 * @since 0.6.0
	 *
	 * @param array<string, int> $was The picks before the change.
	 */
	public function after( array $was ): self {
		return new self( $this->picks, $was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $this->shown, $this->replace, $this->none, $this->shelf );
	}

	/**
	 * The link for a build that takes the place of one in the cart.
	 *
	 * @since 0.6.0
	 *
	 * @param string $build The build's identifier.
	 */
	public function replacing( string $build ): self {
		return new self( $this->picks, $this->was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, $this->shown, $build, $this->none, $this->shelf );
	}

	/**
	 * The link with the step's shelf set. What is listed starts again from the first page.
	 *
	 * @since 1.0.0
	 *
	 * @param ShelfQuery $shelf The width, chips, search and sort.
	 */
	public function shelved( ShelfQuery $shelf ): self {
		return new self( $this->picks, $this->was, $this->step, $this->assembly, $this->preset, $this->brand, $this->size, 0, $this->replace, $this->none, $shelf );
	}

	/**
	 * The link's address on a page: the page's own address with the link's query, or the page's alone for a link
	 * that says nothing.
	 *
	 * @since 0.7.0
	 *
	 * @param string $base  The address of the page the builder is on, with no build in it.
	 * @param Slots  $slots The kit's slots, for the order of the picks.
	 */
	public function address( string $base, Slots $slots ): string {
		$query = $this->query( $slots );
		return array() === $query ? $base : $base . ( str_contains( $base, '?' ) ? '&' : '?' ) . http_build_query( array( self::PARAM => $query ) );
	}

	/**
	 * The link as it is shared: part IDs, the step and assembly on or off (spec: Privacy), and nothing of the visit
	 * it was made in.
	 *
	 * @since 0.6.0
	 */
	public function shared(): self {
		// Nothing of the shelf: its width comes with the deck.
		return new self( $this->picks, null, $this->step, $this->assembly, none: $this->none );
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
	 * The slots sent as `0`: left empty by choice. No more than a kit can have slots.
	 *
	 * @param array<mixed> $sent What was sent.
	 * @return list<string>
	 */
	private static function left( array $sent ): array {
		$left = array();
		foreach ( $sent as $key => $value ) {
			if ( 0 === self::number( $value ) && is_string( $key ) && Fields::isKey( $key ) && ! isset( Slots::KEPT[ $key ] ) && count( $left ) < Slots::MAX ) {
				$left[] = $key;
			}
		}
		return $left;
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
	 * The chips as sent: four groups at most, each `brand`, a spec's key or an attribute's taxonomy, with twenty
	 * values at most, each a slug or a size as the chips name it. A group with none is left out.
	 *
	 * @param mixed $sent What was sent.
	 * @return array<string, list<string>>
	 */
	private static function chips( mixed $sent ): array {
		$chips = array();
		foreach ( is_array( $sent ) ? $sent : array() as $group => $values ) {
			if ( count( $chips ) === self::CHIPS[0] || ! is_string( $group ) || 1 !== preg_match( '/\A[a-z][a-z0-9_]{0,31}\z/', $group ) || ! is_array( $values ) ) {
				continue;
			}
			$kept = array();
			foreach ( $values as $value ) {
				$value = self::matching( $value, '/\A[a-z0-9_%.-]{1,200}\z/' );
				if ( '' !== $value && count( $kept ) < self::CHIPS[1] ) {
					$kept[] = $value;
				}
			}
			if ( array() !== $kept ) {
				$chips[ $group ] = $kept;
			}
		}
		return $chips;
	}

	/**
	 * A search as sent, as plain text: UTF-8 alone, each control character a space, trimmed, and 100 characters at
	 * most. It is not stored and never logged (SPEC-CONFIGURATOR, Search).
	 *
	 * @param mixed $sent What was sent.
	 */
	private static function text( mixed $sent ): string {
		if ( ! is_string( $sent ) || 1 !== preg_match( '//u', $sent ) ) {
			return '';
		}
		$plain = trim( (string) preg_replace( '/\p{Cc}/u', ' ', $sent ) );
		return (string) preg_replace( '/\A(.{0,' . ShelfQuery::QUERY . '}).*\z/us', '$1', $plain );
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
