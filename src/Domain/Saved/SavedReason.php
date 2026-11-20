<?php
/**
 * Why a saved build's pick changed.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Saved;

/**
 * Why a pick a build was saved with is not in it as it reopens (spec: A saved build that has changed, D-254).
 *
 * @since 1.0.0
 */
enum SavedReason: string {

	/** The kit no longer offers the part. */
	case Gone = 'gone';

	/** The part is sold out, or the shop holds fewer than the build takes. */
	case SoldOut = 'sold_out';

	/** The part no longer fits the build before it. */
	case NoLongerFits = 'no_fit';
}
