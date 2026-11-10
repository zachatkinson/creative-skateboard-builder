<?php
/**
 * Why a spec's value was refused.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

declare( strict_types=1 );

namespace CreativeBranding\SkateboardBuilder\Domain\Spec;

/**
 * The codes the edge turns into words.
 */
enum SpecError: string {

	case WrongKind     = 'spec_wrong_kind';
	case UnknownChoice = 'spec_unknown_choice';
	case NotPositive   = 'spec_not_positive';
	case TooMany       = 'spec_too_many';
}
