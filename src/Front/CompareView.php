<?php
/**
 * My builds' compare: up to three saved builds side by side.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Front;

use CreativeBranding\SkateboardBuilder\Domain\Kit\InvalidMoney;
use CreativeBranding\SkateboardBuilder\Domain\Saved\SavedBuild;
use CreativeBranding\SkateboardBuilder\Port\Kits;
use CreativeBranding\SkateboardBuilder\Port\Money;
use CreativeBranding\SkateboardBuilder\Port\PartCards;
use CreativeBranding\SkateboardBuilder\Port\ProductMeta;
use CreativeBranding\SkateboardBuilder\Service\BuildState;
use CreativeBranding\SkateboardBuilder\Service\SavedBuilds;
use CreativeBranding\SkateboardBuilder\Service\State;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Up to three of the signed-in customer's saved builds as one table, a column a build and a row a slot (issue #136,
 * D-411). Each build is checked and priced today, as My builds lists it; a build that changed since it was saved is
 * shown as it would reopen, its closest parts in place, with D-254's words for what changed. The rows whose parts
 * differ, and the gap between the totals, are said in words. Nothing is stored, and no build but the customer's own
 * is read: the builds come from their own list.
 *
 * @since 1.3.0
 */
final class CompareView {

	/**
	 * The query parameter on My builds that asks for the compare: the builds' UUIDs, `cbuild_compare[]`, in the order
	 * chosen.
	 *
	 * @since 1.3.0
	 */
	public const string PARAM = 'cbuild_compare';

	/**
	 * The most builds compared at once.
	 *
	 * @since 1.3.0
	 */
	public const int MOST = 3;

	/**
	 * Constructor.
	 *
	 * @since 1.3.0
	 *
	 * @param SavedBuilds  $saved   The customer's builds, and what changed in each.
	 * @param BuildState   $builds  Checks and prices each one today.
	 * @param Kits         $kits    The kits, for their slots and the shop's decimals.
	 * @param Money        $money   Writes an amount as the shop does.
	 * @param PartCards    $cards   The parts' names.
	 * @param StateAnswer  $answer  Says a changed build's changes as the builder does.
	 * @param MyBuildsView $view    Names a build and opens it, as My builds does.
	 * @param ProductMeta  $meta    Reads the builds' parts' meta in one query.
	 */
	public function __construct(
		private SavedBuilds $saved,
		private BuildState $builds,
		private Kits $kits,
		private Money $money,
		private PartCards $cards,
		private StateAnswer $answer,
		private MyBuildsView $view,
		private ProductMeta $meta
	) {}

	/**
	 * The UUIDs this request asks to compare, each once, in the order sent; any that is no UUID is left out.
	 *
	 * @since 1.3.0
	 *
	 * @return list<string>|null Null where no compare is asked.
	 */
	public static function asked(): ?array {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- A view of the customer's own builds, read by GET; it changes nothing.
		if ( ! isset( $_GET[ self::PARAM ] ) ) {
			return null;
		}
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- As above; each is checked against a UUID's form below.
		$sent  = is_array( $_GET[ self::PARAM ] ) ? wp_unslash( $_GET[ self::PARAM ] ) : array();
		$uuids = array();
		foreach ( $sent as $uuid ) {
			if ( is_string( $uuid ) && 1 === preg_match( '/\A[0-9a-f-]{36}\z/', $uuid ) && ! in_array( $uuid, $uuids, true ) ) {
				$uuids[] = $uuid;
			}
		}
		return $uuids;
	}

	/**
	 * What My builds says in place of a compare's table, or null where it shows the table: it shows one of two builds
	 * or more, and asks for them where fewer of those asked are the customer's own.
	 *
	 * @since 1.3.0
	 *
	 * @param array{builds: list<mixed>} $table The table, from table().
	 */
	public static function refused( array $table ): ?string {
		return count( $table['builds'] ) > 1 ? null : __( 'Choose two or three of your saved builds to compare.', 'creative-skateboard-builder' );
	}

	/**
	 * The table of the customer's builds among those asked, the first MOST of them, in the order asked.
	 *
	 * @since 1.3.0
	 *
	 * @param int          $user  The customer's user ID.
	 * @param list<string> $uuids The builds asked for.
	 * @return array{builds: list<array{uuid: string, name: string, open: string|null}>, rows: list<array{label: string, differs: bool, cells: list<array{name: string, price: string, notes: list<string>}>}>, totals: list<array{total: string, notes: list<string>}>, gaps: list<array{text: string, lowest: bool}>, more: bool}
	 */
	public function table( int $user, array $uuids ): array {
		return $this->kits->together(
			function () use ( $user, $uuids ): array {
				$own = array();
				foreach ( $this->saved->of( $user ) as $build ) {
					$own[ $build->uuid ] = $build;
				}
				$builds = array();
				foreach ( $uuids as $uuid ) {
					if ( isset( $own[ $uuid ] ) ) {
						$builds[] = $own[ $uuid ];
					}
				}
				$more   = count( $builds ) > self::MOST;
				$builds = array_slice( $builds, 0, self::MOST );
				$this->prime( $builds );
				return $this->of( $builds ) + array( 'more' => $more );
			}
		);
	}

	/**
	 * Read the builds' parts' meta, with their products, in one query, as My builds does (#116, D-351).
	 *
	 * @param list<SavedBuild> $builds The builds.
	 */
	private function prime( array $builds ): void {
		$products = array();
		foreach ( $builds as $build ) {
			$products = array( ...$products, $build->kit, ...array_values( $build->picks ) );
		}
		$this->meta->primeWithParents( ...$products );
	}

	/**
	 * The table of these builds.
	 *
	 * @param list<SavedBuild> $builds The builds.
	 * @return array{builds: list<array{uuid: string, name: string, open: string|null}>, rows: list<array{label: string, differs: bool, cells: list<array{name: string, price: string, notes: list<string>}>}>, totals: list<array{total: string, notes: list<string>}>, gaps: list<array{text: string, lowest: bool}>}
	 */
	private function of( array $builds ): array {
		$decimals = $this->kits->decimals();
		$columns  = array();
		$labels   = array();
		$ids      = array();
		foreach ( $builds as $build ) {
			$column    = $this->column( $build );
			$columns[] = $column;
			foreach ( $column['labels'] as $slot => $label ) {
				$labels[ $slot ] ??= $label;
			}
			$ids = array( ...$ids, ...array_values( $column['parts'] ) );
		}
		$names = $this->cards->cards( array_values( array_unique( $ids ) ) );

		$rows = array();
		foreach ( $labels as $slot => $label ) {
			$cells = array();
			$shown = array();
			foreach ( $columns as $column ) {
				$part    = $column['parts'][ $slot ] ?? null;
				$shown[] = $part;
				$cells[] = array(
					'name'  => null === $part ? '' : ( $names[ $part ]['name'] ?? '' ),
					'price' => isset( $column['lines'][ $slot ] ) ? $this->money->text( $column['lines'][ $slot ], $decimals ) : '',
					'notes' => $column['notes'][ $slot ] ?? array(),
				);
			}
			$rows[] = array(
				'label'   => $label,
				'differs' => count( array_unique( array_map( 'strval', $shown ) ) ) > 1,
				'cells'   => $cells,
			);
		}
		$rows[] = $this->assembly( $columns, $decimals );

		$ready  = array_filter( array_map( static fn( array $column ): ?int => $column['total'], $columns ), static fn( ?int $total ): bool => null !== $total );
		$lowest = array() === $ready ? 0 : min( $ready );
		$same   = count( $ready ) > 1 && max( $ready ) === $lowest;
		$totals = array();
		$gaps   = array();
		foreach ( $columns as $column ) {
			$total    = $column['total'];
			$totals[] = array(
				'total' => null === $total ? '' : $this->money->text( $total, $decimals ),
				'notes' => $column['totals'],
			);
			$gaps[]   = match ( true ) {
				null === $total, count( $ready ) < 2 => array(
					'text'   => __( 'No total to compare', 'creative-skateboard-builder' ),
					'lowest' => false,
				),
				$same => array(
					'text'   => __( 'Same total', 'creative-skateboard-builder' ),
					'lowest' => false,
				),
				$total === $lowest => array(
					'text'   => __( 'Lowest total', 'creative-skateboard-builder' ),
					'lowest' => true,
				),
				default => array(
					/* translators: %s: how much more this build costs than the cheapest of those compared, such as $12.00. */
					'text'   => sprintf( __( '%s more than the lowest', 'creative-skateboard-builder' ), $this->money->text( $total - $lowest, $decimals ) ),
					'lowest' => false,
				),
			};
		}
		return array(
			'builds' => array_map(
				static fn( array $column ): array => array(
					'uuid' => $column['uuid'],
					'name' => $column['name'],
					'open' => $column['open'],
				),
				$columns
			),
			'rows'   => $rows,
			'totals' => $totals,
			'gaps'   => $gaps,
		);
	}

	/**
	 * The assembly row: each build's fee, free, or not assembled.
	 *
	 * @param list<array{assembly: bool|null, fee: int}> $columns  The builds' columns.
	 * @param int                                        $decimals The shop's decimals.
	 * @return array{label: string, differs: bool, cells: list<array{name: string, price: string, notes: list<string>}>}
	 */
	private function assembly( array $columns, int $decimals ): array {
		$cells = array();
		$shown = array();
		foreach ( $columns as $column ) {
			$shown[] = null === $column['assembly'] ? '' : ( $column['assembly'] ? 'on:' . $column['fee'] : 'off' );
			$cells[] = array(
				'name'  => match ( true ) {
					null === $column['assembly'] => '',
					! $column['assembly']        => __( 'Not assembled', 'creative-skateboard-builder' ),
					0 === $column['fee']         => __( 'Free', 'creative-skateboard-builder' ),
					default                      => __( 'Assembled', 'creative-skateboard-builder' ),
				},
				'price' => null !== $column['assembly'] && $column['assembly'] && $column['fee'] > 0 ? $this->money->text( $column['fee'], $decimals ) : '',
				'notes' => array(),
			);
		}
		return array(
			'label'   => __( 'Assembly', 'creative-skateboard-builder' ),
			'differs' => count( array_unique( $shown ) ) > 1,
			'cells'   => $cells,
		);
	}

	/**
	 * One build's column: the parts it holds today by slot, each line's price, the notes for what changed, its total.
	 *
	 * @param SavedBuild $build The build.
	 * @return array{uuid: string, name: string, open: string|null, labels: array<string, string>, parts: array<string, int>, lines: array<string, int>, notes: array<string, list<string>>, total: int|null, totals: list<string>, assembly: bool|null, fee: int}
	 */
	private function column( SavedBuild $build ): array {
		$changed = $this->saved->changed( $build );
		$state   = $changed?->state;
		if ( null === $changed ) {
			try {
				$state = $this->builds->of( $build->kit, $build->picks, null, State::REVIEW, $build->assembly );
			} catch ( InvalidMoney ) {
				$state = null;
			}
		}
		$notes  = array();
		$totals = array();
		if ( null !== $changed ) {
			$words = BuilderView::changedWords( $this->answer->reopened( $changed ) );
			foreach ( $words['changes'] as $change ) {
				$notes[ $change['slot'] ] = array( $change['text'], $change['closest'] );
			}
			foreach ( $words['prices'] as $price ) {
				$notes[ $price['slot'] ][] = $price['text'];
			}
			if ( null !== $changed->then ) {
				$totals[] = $words['totals'];
			}
		}
		$labels = array();
		$parts  = array();
		$lines  = array();
		if ( null !== $state ) {
			foreach ( $state->kit->slots->all() as $slot ) {
				$labels[ $slot->key ] = StateAnswer::label( $slot->label, $slot->key, $state->id );
			}
			foreach ( $state->quote->lines as $line ) {
				$parts[ $line->slot ] = $line->part;
				$lines[ $line->slot ] = $line->total;
			}
		}
		$ready = null !== $state && $state->ready();
		if ( null === $state ) {
			$totals[] = __( 'The shop no longer sells this kit.', 'creative-skateboard-builder' );
		} elseif ( ! $ready ) {
			$totals[] = __( 'A part has changed since you saved it. Open the build to see which.', 'creative-skateboard-builder' );
		}
		return array(
			'uuid'     => $build->uuid,
			'name'     => $this->view->named( $build, $state ),
			'open'     => $this->view->open( $build ),
			'labels'   => $labels,
			'parts'    => $parts,
			'lines'    => $lines,
			'notes'    => $notes,
			'total'    => $ready ? $state->quote->total : null,
			'totals'   => $totals,
			'assembly' => $state?->quote->assembly,
			'fee'      => $state?->quote->fee ?? 0,
		);
	}
}
