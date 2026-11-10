<?php
/**
 * The domain's test bootstrap: the autoloader and nothing else. No WordPress, no shim and no constant of the plugin's,
 * so a domain class that reaches for any of them fails here (docs/ROADMAP.md, milestone 2: Done when).
 *
 * @package CreativeBranding\SkateboardBuilder\Tests
 */

declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';
