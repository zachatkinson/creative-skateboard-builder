### Added

- **Development:** the admin kit, which M15's screens are built on. Each screen's form is described once in PHP, and DataForm 22 stands in front of the server's own controls in the browser. It posts those controls, and shows the server's refusals on their fields as the page loads (docs/ADMIN-KIT.md, D-571).
- **Development:** `npm run check` fails if a bundled WordPress package reaches a private API beyond `@wordpress/ui`'s one fallback (D-553).
- **Development:** the admin kit warns before a page is left only once something differs from what it loaded with, never for the Save, and leaves the warning to WooCommerce's Settings, which give their own (D-576).
- **Development:** a refused save on a kit screen opens with one notice that names each field to change and links to it, and keyboard focus starts there (D-577).
- **Development:** the admin kit draws a list of rows as a table: Add and Edit in a modal, Move up and Move down where order matters, and Remove with an Undo, all posted as the server's form names them (D-578).
