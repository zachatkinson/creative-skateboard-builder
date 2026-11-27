### Fixed

- My builds stays in the account's column beside a theme's sidebar: where the column is narrower than 32rem, as Storefront's is at a tablet's width, each build's cells stack under their column's names. The table had run out under Storefront's sidebar, which covered Open (D-528).

### Changed

- **Development:** the 3D view's fallback tests walk to the cart pressing each Next and each pick only once the last press's answer is drawn, as a shopper does; a press under load had come before it (D-529).
- **Development:** a browser test holds the 3D view's Side place to the canvas's middle and the Graphic close-up to the whole nose, read from the canvas's pixels (D-530).
- **Development:** the browser test that runs axe on each of the builder's states first brings a control cut by the stacked bar or stage into view, as a shopper scrolls to it, so a tablet's shorter header no longer leaves "Not sure of your width?" under the bar (D-527).
