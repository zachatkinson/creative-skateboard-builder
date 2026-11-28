# The admin kit

M15's screens are built on one kit: a server form, described once in PHP, that DataForm 22 stands in front of in the browser. This page is how a screen uses it. The reasons are in D-553 (the pin) and D-571 (the mount), and the research in `docs/research/admin-handbooks-2026.md`.

## The contract

1. **The server's form is the page.** A screen prints its controls, nonce and Save button as it would with no script. With no script, that is what the shop owner uses, and it must work alone.
2. **The script stands in front of it.** DataForm is drawn before the server's controls, which are hidden but still posted. Each edit is written into the server's control of the same input name, with a bubbling `change`.
3. **The server decides.** A field's limits are hints. While the kit is mounted the browser's own validation is off, and what the server refuses comes back on its field, shown as the page loads, until that field is edited.

4. **Leaving warns only after a change.** The browser's own prompt shows when something differs from what the page loaded with, so a value changed and changed back asks nothing. A Save leaves without it; a Save that something stopped warns after.

## A screen, in PHP

```php
use CreativeBranding\SkateboardBuilder\Admin\Form\{Assets, Card, Errors, Field, Form, Mount};

$form = Form::of(
    'cbuild-settings',
    array(
        Field::of( 'lead_days', 'cbuild[lead_days]', __( 'Assembly time', 'creative-skateboard-builder' ), 'integer' )
            ->help( __( 'Business days to assemble a board.', 'creative-skateboard-builder' ) )
            ->rules( required: true, min: 0, max: 30 )
            ->value( $saved['lead_days'] ),
        Field::of( 'unit', 'cbuild[unit]', __( 'Unit', 'creative-skateboard-builder' ), 'text' )
            ->control( 'select' )
            ->choices( array( 'mm' => __( 'Millimetres', 'creative-skateboard-builder' ), 'in' => __( 'Inches', 'creative-skateboard-builder' ) ) )
            ->value( $saved['unit'] ),
    ),
    array( Card::of( 'builds', __( 'Builds', 'creative-skateboard-builder' ), array( 'lead_days', 'unit' ) ) )
)->failed( $errors ); // Errors::none()->with( 'lead_days', $message ), from the last refused save.

echo Mount::open( $form );
// The server's own controls, named as the fields' input names, each with the id Mount::controlId( $form, 'lead_days' ).
echo Mount::close();
```

Where the server refused anything, `Mount::open()` prints one summary first: an inline error notice that says how many fields need a change and links to each, with its label and message. With a script, the summary takes focus as the page loads and each link moves focus to DataForm's control for the field. With none, each link goes to the server's control by `Mount::controlId()`, so give each control that id. A screen prints no other notice for its refusals.

On the screen's own `admin_enqueue_scripts`, `Assets::enqueue( '<screen>', CBUILD_PATH, CBUILD_URL )` loads `build/admin/<screen>.js` with the dependencies the build wrote, and the kit's shared stylesheet with its right-to-left copy.

- **Field:**
  - `of( id, name, label, type )` takes DataForm's types;
  - `help()` is at most 140 characters;
  - `control()` names one of DataForm's controls;
  - `choices()`, `rules()`, `suffix()`, `placeholder()`;
  - `readOnly( source )` for a value set elsewhere, with where;
  - `visibleWhen( field, values )` shows a field only while another holds one of the values.
- **Card:** `of( id, label, fields )`, then `closed( summaryFieldId )` to start it shut with a field's value in its header.
- **Form:** refuses a duplicate id, and a condition, card or summary that names an unknown field. `failed( $errors )` opens the cards that hold an error. `leftToHost()` is for a page that warns before it is left by itself, as WooCommerce's Settings do: the kit adds no second warning.

## A screen, in the browser

Each screen has an entry, `assets/src/admin/<screen>.ts`, that calls `mountAll()`. A panel that WooCommerce loads later, such as a variation's, calls `mountAll( panel )`, and gives each back before WooCommerce empties it.

The kit, in `assets/src/admin/form/`, imports only its own modules and WordPress's packages. ESLint refuses anything else, so the agency's other plugins can copy it.

## What DataForm does that a screen should know

- **Number fields clamp to their limits.** A field that says 30 at most posts 30 when 99 is typed. A rule only the server knows, such as one field against another, still comes back as a refusal.
- **Validity nests under cards.** `validityOf()` handles it. A screen passes errors by field id.
- **A message shows once a field is left.** The kit shows the server's refusals as the page loads (`reveal()`); the summary names them too, and takes focus.
- **A required field's label gains "(Required)",** translated. The summary's links find a control by its label, the same words first, then the label and more after a space.

## Budgets and checks

- **Budgets:** each screen's script is held to 170 kB with Brotli (`tools/budget.mjs`), and the kit's stylesheet to the stylesheets' 10 kB.
- **Private APIs:** `npm run private-apis` (in `npm run check`) fails if a bundled package reaches a private WordPress API beyond `@wordpress/ui`'s one fallback.
- **The kit's own page:** `tests/e2e/admin-kit.spec.ts` runs the whole contract on a fixture page of its own.
