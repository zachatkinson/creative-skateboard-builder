### Added

- **Development:** the admin kit, which M15's screens are built on. Each screen's form is described once in PHP, and DataForm 22 stands in front of the server's own controls in the browser. It posts those controls, and shows the server's refusals on their fields as the page loads (docs/ADMIN-KIT.md, D-571).
- **Development:** `npm run check` fails if a bundled WordPress package reaches a private API beyond `@wordpress/ui`'s one fallback (D-553).
- **Development:** the admin kit warns before a page is left only once something differs from what it loaded with, never for the Save, and leaves the warning to WooCommerce's Settings, which give their own (D-576).
