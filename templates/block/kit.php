<?php
/**
 * The block template a kit's page takes in a block theme (spec: Where it renders): the theme's header, the builder at
 * the page's width with the product's name as its h1, the product's description, specs and reviews, and the theme's
 * footer. The shop can edit it in the Site Editor.
 *
 * @package CreativeBranding\SkateboardBuilder
 */

defined( 'ABSPATH' ) || exit;
?>
<!-- wp:template-part {"slug":"header","tagName":"header"} /-->

<!-- wp:group {"tagName":"main","align":"full","layout":{"type":"default"}} -->
<main class="wp-block-group alignfull">
<!-- wp:creative-skateboard-builder/builder {"title":true,"align":"full"} /-->

<!-- wp:group {"layout":{"type":"constrained"}} -->
<div class="wp-block-group">
<!-- wp:woocommerce/store-notices /-->

<!-- wp:woocommerce/product-details {"align":"wide"} /-->
</div>
<!-- /wp:group -->
</main>
<!-- /wp:group -->

<!-- wp:template-part {"slug":"footer","tagName":"footer"} /-->
