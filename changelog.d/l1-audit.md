### Added

- **`replace` in the delivery filters' context** (#168, D-488): `cbuild_arrival_estimate` and `cbuild_delivery_line` are given the cart key of the kit line an Edit build would replace, or empty, so a plugin counting the cart, such as Creative Checkout 1.7.1, leaves that build out rather than counting it beside the new one.
- **The 3D view's root and the picture's 3D state as theme classes** (M14's audit, S4): `cbuild-3d`, and `cbuild-picture--3d` on the picture while the 3D view is open, join the classes the API promises a theme.
- **`view.scene` and `view.three` are public** (M14's audit, S3, D-487): the state route's `view` stays the builder's own, with these two keys as its one stated exception.

### Changed

- **The rule for what a kit's page carries to the Builder page is a service of its own, `Front\CarriedArgs`** (M14's audit, F10), tested without WordPress; the redirect is unchanged.
- **The review's visual shot compares "Show the parts" again** (#110, D-486), quarantined since 1.2.0.

### Fixed

- **Site Health no longer counts a size only the 3D view assumes as a thing to fix** (M14's audit, F4, D-485). After the update, every new kit has the 3D view on, and each deck or wheel without the new optional specs was counted. The Catalogue check still names them.
- **readme.txt names the 3D view among the features, and says the 3D view bundles three.js under the MIT License,** with its license in `licenses/three.js.txt` (M14's audit, F11). A gate now holds that the Features list names the release's minor version.
