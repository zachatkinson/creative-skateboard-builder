### Added

- **The 3D view's scene** (#139, D-470): `Domain\Scene\Scene` measures every part of a build in whole micrometres from its specs, on the same frame as the 2D picture (`Domain\Picture\Board`), and names each usual value it assumed. The builder's view carries it as `view.scene` (`schema/state.json`).
- **Spec keys `nose_length`, `tail_length`, `concave_depth`, `kick_angle` and `wheel_width`** (D-472, D-473): optional, through the import's columns, Part specs and the specs index. A deck or wheel without one gets a usual value.

### Changed

- **A deck drilled for both patterns** takes trucks whose baseplates fit only old school holes on its old school holes, in the picture as in the 3D view (D-470).
- **A wheel's width** comes from `wheel_width` where it gives one, in the picture as in the 3D view; without one it is still drawn three fifths as wide as its diameter (D-471).
