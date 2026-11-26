### Added

- View in 3D: where a kit turns it on, the picture shows the build in 3D, drawn from the parts' own sizes, to scale with its shapes simplified. Drag or use the arrow keys to turn it, + and − to zoom, and four buttons for the three-quarter view, the top, the underside and the graphic close-up. Its script loads only when the button is pressed, and it draws nothing while still, off the screen or in a hidden tab (#139, D-490 to D-498).
- Where the browser can't draw it, or it stops, the board comes back with a short message, and the build goes on as before (D-494).
- The state route's view says whether the 3D view is offered: `view.three`, with `shown`, `tricks` and `canvas`; and the classes `cbuild-3d__open`, `cbuild-3d__stage`, `cbuild-3d__frame`, `cbuild-3d__canvas`, `cbuild-3d__places` and `cbuild-3d__failed` (D-495, D-497).
- three.js 0.187.0, the 3D view's only new dependency, in a chunk of its own budgeted at 200 kB gzipped (D-490).
