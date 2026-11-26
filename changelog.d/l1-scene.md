### Added

- **The 3D view's scene** (#139, D-470): `Domain\Scene\Scene` measures every part of a build in whole micrometres from its specs, on the same frame as the 2D picture (`Domain\Picture\Board`), and names each usual value it assumed. The builder's view carries it as `view.scene` (`schema/state.json`).
- **Spec keys `nose_length`, `tail_length`, `concave_depth`, `kick_angle` and `wheel_width`** (D-472, D-473): optional, through the import's columns, Part specs and the specs index. A deck or wheel without one gets a usual value.
- **Two Build tab switches under Shown in the builder** (D-474): **3D view** (`view_3d` in `_cbuild_settings`), off where a kit has none, so an update changes no builder; and **Trick buttons** (`tricks`), on where a kit has none, shown only once the kit's 3D view is saved on.

- **The filter `cbuild_scene`** (D-475): the 3D view's scene of a build, after the domain, with each drawn part's texture. `cbuild_composed_layer` gains the use `3d`; a part answered `blurred` or `left_out` has no texture in the 3D view.
- **The Catalogue check names a deck or wheel the 3D view draws at a usual size** (D-473), on a kit whose 3D view is on, with the size to fill in.

### Changed

- **A deck drilled for both patterns** takes trucks whose baseplates fit only old school holes on its old school holes, in the picture as in the 3D view (D-470).
- **A wheel's width** comes from `wheel_width` where it gives one, in the picture as in the 3D view; without one it is still drawn three fifths as wide as its diameter (D-471).
