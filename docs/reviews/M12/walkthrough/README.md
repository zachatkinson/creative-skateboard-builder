# Milestone 12's walkthrough

The screens milestone 12 built, taken on 24 November 2026 for the review (`docs/reviews/M12.md`), before the merge. Each name says the width, the screen, the colours and the language: `<desktop|phone>-<screen>-<light|dark>-<en|fr>.avif`. AVIF at quality 50 (D-088).

## How they were taken

- **The second pass,** 90 pictures: lane 3 (:8902) on `bccb5cc`, built with `npm run build`, Kadence unless a name says another theme, by Playwright's Chromium. Desktop is 1440 by 900; phone is 390 by 844 at three device pixels, and sideways 844 by 390. Dark is the e2e theme fixture's dark scheme; French is fr_CA with the plugin's translation. They show the tree before the fixes they led to: the `*-limit-*` pictures have the limit's line at the builder's top (moved over the bar by D-383), the phone pictures on "Build a complete" have the bar cut off and the 7 px edge (D-382, D-384), `desktop-admin-assembly-time-error-notice-light-en` and `desktop-admin-assembly-time-error-light-en` have the notice naming four settings and the unmarked field (D-385, D-386), `*-admin-builder-page-*` the cut option (D-387), and `desktop-deck-page-twentytwentyfive-light-en` the start button 4 px out of line (74df3d2).
- **The kits:** the e2e "Builder test kit" on its own page (`/product/builder-test-kit/`) and on the page that holds its block, "Build a complete" (`/builder-test-page/`, the block's full width); the Build tab on "Build tab test kit".
- **The limit message** (D-364) was made in the browser: the state route answered 429, or held past 10 s.
- **Site Health's test** (#129) was made by moving one part's resized copies out of the uploads folder for the shot, and back after.
- **The first pass,** the 16 pictures listed last, was taken earlier on 24 November on lane 2's sample shop, at 1280 by 900.

## The builder

| Screen | Desktop | Phone |
| --- | --- | --- |
| The width question, kit page (F24) | `desktop-width-question-light-en` | `phone-width-question-light-en` |
| A width pressed, the deck step folded (F20, F24) | `desktop-width-pressed-light-en` | `phone-width-pressed-light-en`, `phone-width-pressed-full-light-en` |
| The deck picked, the started build | `desktop-deck-step-light-en` | `phone-deck-step-light-en` |
| The bar on a blank build and a started one (D-374) | in the two rows above | `phone-bar-blank-light-en`, `phone-bar-light-en` |
| The shelf kit's deck step, with search and brands | `desktop-deck-cards-pictures-light-en` | `phone-deck-cards-pictures-light-en` |
| Each step on its side (F27): deck, trucks, wheels, bearings underneath; hardware and grip on top | `desktop-side-<step>-light-en` | `phone-side-<step>-light-en` |
| Top pressed, then Underside (F28) | `desktop-turn-top-pressed-light-en`, `desktop-turn-underside-pressed-light-en` | `phone-turn-top-pressed-light-en`, `phone-turn-underside-pressed-light-en` |
| Held sideways, the board beside the heading (F21) | | `phone-sideways-light-en`, `phone-sideways-start-light-en` |
| The phone rail (F25), at the start and on Hardware | | `phone-rail-deck-light-en`, `phone-rail-hardware-light-en`, `phone-hardware-step-light-en` |
| The limit message (D-364): 429 on a width, and no answer in 10 s on a deck | `desktop-limit-429-light-en`, `desktop-limit-late-light-en` | `phone-limit-429-light-en`, `phone-limit-late-light-en` |
| The review | `desktop-review-light-en`, `desktop-review-full-light-en` | `phone-review-light-en`, `phone-review-full-light-en` |

## Dark and French

| Screen | Dark | French |
| --- | --- | --- |
| The width question | `desktop-width-question-dark-en`, `phone-width-question-dark-en` | `desktop-width-question-light-fr`, `phone-width-question-light-fr` |
| The deck step, a deck picked | `desktop-deck-step-dark-en`, `phone-deck-step-dark-en` | `desktop-deck-step-light-fr`, `phone-deck-step-light-fr` |
| The trucks step | `desktop-trucks-dark-en`, `phone-trucks-dark-en` | `desktop-trucks-light-fr`, `phone-trucks-light-fr` |
| The review | `desktop-review-dark-en`, `phone-review-dark-en`, `phone-review-full-dark-en` | `desktop-review-light-fr`, `phone-review-light-fr`, `phone-review-full-light-fr` |
| The phone bar | `phone-bar-dark-en` | `phone-bar-light-fr` |

## The ways in

| Way in | Desktop | Phone |
| --- | --- | --- |
| A deck's page, "Build a complete with this deck" (#133) on Kadence, Storefront and Twenty Twenty-Five | `desktop-deck-page-<theme>-light-en` | `phone-deck-page-<theme>-light-en` |
| The start button pressed, into the builder | `desktop-way-in-deck-page-light-en` | `phone-way-in-deck-page-light-en` |
| A shared build link, opened by a visitor | `desktop-way-in-shared-link-light-en` | `phone-way-in-shared-link-light-en` |
| A saved build: My builds, then Open | `desktop-my-builds-light-en`, `desktop-way-in-saved-build-light-en` | `phone-my-builds-light-en`, `phone-way-in-saved-build-light-en` |
| A wider deck that breaks the trucks | `desktop-changes-light-en` | `phone-changes-light-en` |
| A preset with a sold-out part, by its link | `desktop-preset-sold-out-light-en` | `phone-preset-sold-out-light-en` |

## The admin

| Screen | Desktop | Phone |
| --- | --- | --- |
| The Build tab: Builder page and the start button's box (#113, #115) | `desktop-admin-builder-page-light-en` | `phone-admin-builder-page-light-en` |
| The Build tab: Width guide (#114) | `desktop-admin-kit-width-guide-light-en` | `phone-admin-kit-width-guide-light-en` |
| The Build tab: Assembly time in business days (#142) | `desktop-admin-assembly-time-light-en` | `phone-admin-assembly-time-light-en` |
| Assembly time typed as 61: the notice, then the field it leads to | `desktop-admin-assembly-time-error-notice-light-en`, `desktop-admin-assembly-time-error-light-en` | |
| A deck's Part specs, "Not offered in the builder" (#116) | `desktop-admin-part-specs-not-offered-light-en` | `phone-admin-part-specs-not-offered-light-en` |
| Site Health's composed pictures test naming the part (#129) | `desktop-admin-site-health-pictures-light-en` | `phone-admin-site-health-pictures-light-en` |

## The first pass

On lane 2's sample shop: `desktop-admin-where-shoppers-light-en`, `phone-admin-where-shoppers-light-en`, `desktop-admin-width-guide-light-en`, `phone-admin-width-guide-light-en`, `desktop-admin-not-offered-light-en`, `phone-admin-not-offered-light-en`, `desktop-admin-variation-not-offered-light-en`, `desktop-admin-check-kept-out-light-en`, `desktop-builder-page-light-en`, `desktop-builder-page-dark-en`, `phone-builder-page-light-en`, `phone-builder-page-light-fr`, `desktop-width-guide-light-en`, `desktop-width-guide-dark-en`, `phone-width-guide-light-en` and `phone-width-guide-light-fr`.
