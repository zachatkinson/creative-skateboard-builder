### Added

- `cbuild:3d-failed`, a DOM event the builder sends when the 3D view can't be shown or stops, with the kit's ID and why: `load`, `webgl`, `lost` or `error`, for a shop's analytics. The reason is also warned in the browser's console (F8, D-481).

### Fixed

- After Show builds or Skip in Help me choose was overtaken by a pick, or answered with the route's limit, a later pick that opened the change panel no longer goes unsaid, and Back no longer takes focus to the builds (F3, D-479).
- Focus in the 3D view goes to View in 3D where the view stops, as when its context is lost, rather than to the page (F2, D-480).
- The 3D view closes where the build's scene goes, as when the deck sells out or Back returns to before one, rather than leaving a blank canvas with View in 3D hidden (F6, D-482).
- **Development:** the 3D view's fallback tests finish the build and add it to the cart (S6).
