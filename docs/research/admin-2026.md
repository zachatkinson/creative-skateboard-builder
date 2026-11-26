# The admin in 2026: what DataForm and DataViews cost (for milestone 15)

Measured on 26 November 2026, during milestone 14, for #156. Zach asked for an admin as modern as the builder, with rules a shop can add and remove rather than fixed blank lines.

## What the leading plugins use

- **DataForm** for settings: fields described in code, and the form drawn from them. WordPress's Developer Blog walked through it in January 2026 ("How to use DataForm to create plugin settings pages").
- **DataViews** for lists: sorting, filters, search, saved views and bulk actions. WooCommerce is moving its admin lists to it ("What's the value of DataViews and who is it for?", developer.woocommerce.com, 5 August 2026).

Both come from `@wordpress/dataviews` (22.1.0 on 26 November).

## What WordPress 7.1.5 already ships

As script handles that a plugin's script can use for nothing, through wp-scripts' dependency extraction: `wp-element` (React), `wp-components`, `wp-data`, `wp-i18n`, `wp-a11y`, `wp-compose`, `wp-hooks`, `wp-api-fetch` and others (`wp-includes/js/dist/`).

It does not ship DataViews or DataForm as a handle of its own. Core bundles them inside the editors (`edit-site.min.js`, `editor.min.js` and `block-editor.min.js`), so a plugin bundles its own copy.

## What a plugin's copy weighs

Bundled with esbuild, minified, with every package core ships left out, as wp-scripts would leave it out:

| Bundle | Brotli 11 | gzip |
| --- | --- | --- |
| DataForm alone | 160 kB | 204 kB |
| DataViews alone | 227 kB | 293 kB |
| Both | 238 kB | 308 kB |
| Its stylesheet | 8.8 kB | |

Most of the weight is its own new component library, which core doesn't ship yet:

| Package | Minified |
| --- | --- |
| `@wordpress/ui` | 342 kB |
| `@base-ui/react` | 163 kB |
| DataViews itself | 83 kB |
| `date-fns` | 55 kB |
| `colorjs.io` | 48 kB |
| `react-day-picker` | 44 kB |

The admin's script budget is 15 kB (D-086), so DataForm alone is about ten times it.

## The options for milestone 15

1. **Core's own components, no new library.** Each screen is rebuilt with `@wordpress/components`, which core ships, so it costs the plugin's script nothing.
   - The repeating groups get "Add a rule", a remove on each row, reordering by drag and by keyboard, and inline errors.
   - The screens look like WooCommerce's and WordPress's own settings.
   - The script stays near the 15 kB budget.
   - It needs no new dependency, since `@wordpress/components` comes with core and `@wordpress/scripts` is already the build.
2. **DataViews for the lists only:** the Catalogue check and the spec tables, where sorting, filters and bulk actions pay. That's about 227 kB Brotli on those two screens alone, and the settings use option 1. It needs `@wordpress/dataviews` approved, and a budget line for those two screens.
3. **DataForm and DataViews everywhere.** That's about 238 kB on every screen of the plugin's, until core ships `@wordpress/ui` as a handle and the cost falls to about DataViews' own 83 kB.

**My recommendation** is option 1, with option 2 for the Catalogue check if its lists grow past what a table with filters handles. The look and the add, remove and reorder Zach asked for come from core's components at no cost. DataViews' weight is mostly a library core will ship later, which is the point to revisit.

Zach decides at milestone 15's plan.
