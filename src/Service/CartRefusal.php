<?php
/**
 * Why the cart refuses.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Service;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Why a build is not added to the cart, or a change to one is not made. The code is what the Store API answers
 * with; the sentence beside it is made where it is shown.
 *
 * @since 0.5.0
 */
enum CartRefusal: string {

	/** A kit arrived with no build: it is bought through the builder alone. */
	case NoBuild = 'cbuild_no_build';

	/** The shop has no such kit, or none a visitor may see, or its settings do not read. */
	case KitNotFound = 'cbuild_kit_not_found';

	/** The build has problems: an empty slot, a part that is not its slot's, does not fit or cannot be had. */
	case BuildRefused = 'cbuild_build_refused';

	/** The build cannot be priced in the shop's currency settings. */
	case NoPrice = 'cbuild_no_price';

	/** WooCommerce would not add one of the build's parts, so none of the build was added. */
	case NotAdded = 'cbuild_not_added';
}
