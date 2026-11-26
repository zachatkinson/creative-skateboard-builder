# Milestone 13's walkthrough

The screens milestone 13 built, taken on 26 November 2026 for the review (`docs/reviews/M13.md`), before the merge. Each name says the width, the screen, the colours and the language: `<desktop|phone>-<screen>-<light|dark>-<en|fr>.avif`. AVIF at quality 50 (D-088).

## How they were taken

- **The first pass,** 90 pictures: lane 3 (:8902) on `ecb30b9` (the combined tree `800d07e` and one tooling commit), built with `npm run build`, Kadence unless a name says another theme, by Playwright's Chromium. Desktop is 1440 by 900; phone is 390 by 844 at three device pixels. Dark is the e2e theme fixture's dark scheme; French is fr_CA with the plugin's translation. Motion is off but for the outline.
- **The first pass took only the screens the pending audit fixes left as they were.** Help me choose and the notice after a swap are in the second pass, 46 pictures, below.
- **The kits:** the e2e "Builder test kit" on the page that holds its block, "Build a complete" (`/builder-test-page/`, the block's full width), its picks in the address; the Build tab on "Build tab test kit"; a part's rank on "Builder test trucks 139".
- **A `*-full-*` picture** is the whole page in one screen, the stage held at the height it has on the phone or desktop screen, as the visual snapshots take it; the fixed bar is then drawn at the page's foot.
- **The pictures still loading** were made by holding every file from the uploads folder until the shot.
- **The outline** (#138) was caught by pausing the trucks' outline 300 ms into its 600 ms, every other animation at its end.
- **My builds' compare** was made with three builds saved for a test customer (`shopper+40@example.com`) and taken away after. The Storefront and Twenty Twenty-Five copies show the compare with and without its stylesheet (see the review's findings).
- **The delivery lines** in the `*-bar-review-*` pictures are the e2e delivery fixture's, which another plugin says on the demo; `*-row-*` adds the hostile theme fixture (a dark row with cream links, every button yellow, a paragraph's margin), as Cowork found on the demo (F32, F33).

## The builder

| Screen | Desktop | Phone |
| --- | --- | --- |
| The deck step's compact cards, a width picked (#131) | `desktop-cards-deck-light-en` | `phone-cards-deck-light-en` |
| Cards side by side on each later step (#131): trucks, wheels, bearings, hardware, grip | `desktop-cards-<step>-light-en` | `phone-cards-<step>-light-en` |
| The deck picked | `desktop-deck-step-light-en` | `phone-deck-step-light-en` |
| A card picked for Street, with its rating (#137), and the card alone | `desktop-card-picked-rated-light-en`, `desktop-card-picked-rated-crop-light-en` | `phone-card-picked-rated-light-en`, `phone-card-picked-rated-crop-light-en` |
| The review with thumbnails (#132), and the whole page | `desktop-review-thumbnails-light-en`, `desktop-review-full-light-en` | `phone-review-thumbnails-light-en`, `phone-review-full-light-en` |
| The review with its pictures still loading (#132) | `desktop-review-thumbnails-loading-light-en` | `phone-review-thumbnails-loading-light-en` |
| The pick outline, mid-outline (#138), and the picture alone | `desktop-outline-light-en`, `desktop-outline-crop-light-en` | `phone-outline-light-en`, `phone-outline-crop-light-en` |
| The phone bar on the trucks step | | `phone-bar-light-en` |
| The phone bar at the review on Kadence (F33, #150), and under the hostile theme | | `phone-bar-review-light-en`, `phone-bar-review-row-light-en` |

## Smart swaps (#135)

| Screen | Desktop | Phone |
| --- | --- | --- |
| The chosen card's "For less" (the 54 mm wheels picked) | `desktop-card-swap-less-light-en` | `phone-card-swap-less-light-en` |
| The chosen card's "Step up" (the 52 mm wheels picked) | `desktop-card-swap-step-up-light-en` | `phone-card-swap-step-up-light-en` |
| The review's "Same board for $3.00 less", and "For less" under its rows | `desktop-review-swaps-less-light-en` | `phone-review-swaps-less-light-en` |
| The review's "Step up" under its rows | `desktop-review-swaps-step-up-light-en` | `phone-review-swaps-step-up-light-en` |

## My builds' compare (#136)

| Screen | Desktop | Phone |
| --- | --- | --- |
| Three builds ticked, Compare the ticked builds | `desktop-my-builds-compare-ticked-light-en` | `phone-my-builds-compare-ticked-light-en` |
| The compare, and the whole page | `desktop-my-builds-compare-light-en`, `desktop-my-builds-compare-full-light-en` | `phone-my-builds-compare-light-en`, `phone-my-builds-compare-full-light-en` |
| The phone's table after an attempt to scroll it sideways | | `phone-my-builds-compare-scrolled-light-en` |
| The compare on Storefront and Twenty Twenty-Five | `desktop-my-builds-compare-<theme>-light-en` | `phone-my-builds-compare-<theme>-light-en` |

## Dark and French

| Screen | Dark | French |
| --- | --- | --- |
| The deck step's cards, a width picked | `desktop-cards-deck-dark-en`, `phone-cards-deck-dark-en` | `desktop-cards-deck-light-fr`, `phone-cards-deck-light-fr` |
| The deck picked | `desktop-deck-step-dark-en`, `phone-deck-step-dark-en` | `desktop-deck-step-light-fr`, `phone-deck-step-light-fr` |
| The trucks step with its cards | `desktop-cards-trucks-dark-en`, `phone-cards-trucks-dark-en` | `desktop-cards-trucks-light-fr`, `phone-cards-trucks-light-fr` |
| The review | `desktop-review-thumbnails-dark-en`, `phone-review-thumbnails-dark-en`, `desktop-review-full-dark-en`, `phone-review-full-dark-en` | `desktop-review-thumbnails-light-fr`, `phone-review-thumbnails-light-fr`, `desktop-review-full-light-fr`, `phone-review-full-light-fr` |
| The phone bar, on the trucks step and at the review | `phone-bar-dark-en`, `phone-bar-review-dark-en` | `phone-bar-light-fr`, `phone-bar-review-light-fr` |

## The ways in

| Way in | Desktop | Phone |
| --- | --- | --- |
| A deck's page, "Build a complete with this deck" beside Add to cart, on Kadence, Storefront and Twenty Twenty-Five | `desktop-deck-page-<theme>-light-en` | `phone-deck-page-<theme>-light-en` |
| The same on Kadence under a row whose links are cream (F32, #149) | `desktop-deck-page-kadence-row-light-en` | `phone-deck-page-kadence-row-light-en` |

## The admin

| Screen | Desktop | Phone |
| --- | --- | --- |
| The Build tab: Where shoppers find it, with Offer smart swaps (#135) | `desktop-admin-build-swaps-light-en` | `phone-admin-build-swaps-light-en` |
| The Build tab: Shown in the builder (D-409) | `desktop-admin-build-shown-light-en` | `phone-admin-build-shown-light-en` |
| The Build tab: Wheel guide (#134) | `desktop-admin-build-wheel-guide-light-en` | `phone-admin-build-wheel-guide-light-en` |
| A part's Part specs, Rank in its slot (`_cbuild_rank`) | `desktop-admin-part-rank-light-en` | `phone-admin-part-rank-light-en` |

## Help me choose and the swap notice (second pass)

Taken on 26 November 2026 on lane 3 on `m13-walk2`: the audit-fixed tree `2cd0cc6` with the first pass on top, built with `npm run build`, by the same script and in the same way as the first pass. The answers are pressed and typed in the form, as a shopper does. The fixture's three builds show only when no shoe size is given: a shoe size picks one deck width, and the shop has two wheels in street's sizes at it, so that answer gives two builds (`*-guide-*-shoe-*`). Help me choose was turned off on the kit for `*-guide-off-*` and turned back on after.

### Help me choose (#134)

| Screen | Desktop | Phone |
| --- | --- | --- |
| At the builder's start, closed | `desktop-guide-closed-light-en` | `phone-guide-closed-light-en` |
| Open, with its questions, and the whole page | `desktop-guide-open-light-en`, `desktop-guide-open-full-light-en` | `phone-guide-open-light-en`, `phone-guide-open-full-light-en` |
| Answered: Street, a budget of 250, no shoe size | `desktop-guide-answers-light-en` | `phone-guide-answers-light-en` |
| The three builds, each with why, and the whole page | `desktop-guide-builds-light-en`, `desktop-guide-builds-full-light-en` | `phone-guide-builds-light-en`, `phone-guide-builds-full-light-en` |
| Answered with a shoe size of 9.5 and larger, Street, a budget of 250: two builds | `desktop-guide-answers-shoe-light-en`, `desktop-guide-builds-shoe-light-en`, `desktop-guide-builds-shoe-full-light-en` | `phone-guide-answers-shoe-light-en`, `phone-guide-builds-shoe-light-en`, `phone-guide-builds-shoe-full-light-en` |
| A budget typed as "$150", read as 150 (D-433) | `desktop-guide-budget-dollar-light-en` | `phone-guide-budget-dollar-light-en` |
| A budget typed as "abc", left out with a line that says so (D-433) | `desktop-guide-budget-unread-light-en` | `phone-guide-budget-unread-light-en` |
| Turned off in the Build tab: the start without it (D-409) | `desktop-guide-off-light-en` | `phone-guide-off-light-en` |

### The swap notice (#135, D-432)

| Screen | Desktop | Phone |
| --- | --- | --- |
| "Same board for $3.00 less" pressed: the notice with Undo swap, and the whole page | `desktop-swap-notice-light-en`, `desktop-swap-notice-full-light-en` | `phone-swap-notice-light-en`, `phone-swap-notice-full-light-en` |
| After Undo swap: the build and the offer back | `desktop-swap-undone-light-en` | `phone-swap-undone-light-en` |

### Dark and French

| Screen | Dark | French |
| --- | --- | --- |
| Help me choose answered | `desktop-guide-answers-dark-en`, `phone-guide-answers-dark-en` | `desktop-guide-answers-light-fr`, `phone-guide-answers-light-fr` |
| Its builds | `desktop-guide-builds-dark-en`, `phone-guide-builds-dark-en`, `desktop-guide-builds-full-dark-en`, `phone-guide-builds-full-dark-en` | `desktop-guide-builds-light-fr`, `phone-guide-builds-light-fr`, `desktop-guide-builds-full-light-fr`, `phone-guide-builds-full-light-fr` |
| The swap notice | `desktop-swap-notice-dark-en`, `phone-swap-notice-dark-en` | `desktop-swap-notice-light-fr`, `phone-swap-notice-light-fr` |

## Retaken after the walkthrough's fixes

The second pass found two defects, fixed by D-460 and D-461 (the review's walkthrough section). The pictures they changed were taken again on 26 November 2026 on lane 3, on the tree with both fixes (`4fc1794`), by the same script:

- **D-461, a ready build's tile cut to a square:** every `*-guide-builds-*` picture, the shoe size's too. The Mid-range build's 8.25″ deck now shows whole.
- **D-460, "et ." in a French list:** `desktop-swap-notice-light-fr` and `phone-swap-notice-light-fr`, which now read "Échangé : wheels et hardware."
