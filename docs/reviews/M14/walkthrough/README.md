# Milestone 14's walkthrough

The screens milestone 14 built, taken on 27 November 2026 for the review (`docs/reviews/M14.md`), before the audit. Each name says the width, the screen, the colours and the language: `<desktop|phone>-<screen>-<light|dark>-<en|fr>.avif`. AVIF at quality 50 (D-088).

## How they were taken

- **The tree:** lane 2 (:8900) on `m14-l2-walk`, made from `m14-3d` at `a03e349`, built with `npm run build`, by Playwright's Chromium from a throwaway script. Desktop is 1440 by 900; phone is 390 by 844 at three device pixels; the phone held sideways is 844 by 390. Kadence unless a name says another theme. Dark is the e2e theme fixture's dark scheme; French is fr_CA with the plugin's translation.
- **The kit:** the e2e "3D test kit" on `/three-test-page/`, its picks in the address, with the server's own scene (no e2e scene cookie). Its decks and wheels list none of the five new sizes, so every open view shows the "usual sizes" note. Help me choose is on the "Builder test kit" (`/builder-test-page/`).
- **The view is opened by the button's own click,** and the page then scrolled to the builder, so each picture frames the view. `phone-3d-open-tapped-light-en` is the one exception: a real tap, and the page where the tap leaves it (finding 1).
- **The trick** was caught when the probe read the board over 150 mm off the ground, a kickflip mid-air.
- **The fallback** was forced as the browser test forces it: `WEBGL_lose_context` on the open canvas.
- **#152's held answer** was made by holding the state route's answer until the shot, then letting it through.
- **The new kit** on the Build tab is a draft kit saved for this walkthrough ("Walkthrough new kit", post 4991 on lane 2's site), which the Catalogue check pictures also list.
- **A `*-full-*` picture** is the whole page, the fixed bar drawn where the page's foot puts it.

## Reused from the lanes' walkthroughs

The admin screens and the code under F34 and F37 have not changed since the lanes took them, so they are not taken again:

| Screen | Where |
| --- | --- |
| The Build tab's 3D view and Trick buttons on an old kit (off) and on a new kit (on), desktop | `../lanes/l1/desktop-build-tab-3d-off-light-en`, `../lanes/l1/desktop-build-tab-3d-on-light-en`, `../lanes/l1/desktop-build-tab-new-kit-light-en` |
| Part specs with the new deck and wheel fields, desktop | `../lanes/l1/desktop-part-specs-deck-light-en`, `../lanes/l1/desktop-part-specs-wheels-light-en` |
| The Catalogue check listing what the 3D view assumed, desktop | `../lanes/l1/desktop-catalogue-check-light-en` |
| F34: the deck page's start button focused on Kadence | `../lanes/l3/kadence-desktop-5-start-focused.png`, `../lanes/l3/kadence-phone-5-start-focused.png` |
| F37: My builds' compare on a phone, its row headers kept | `../lanes/l3/kadence-phone-7-compare.png`, `../lanes/l3/twentytwentyfive-phone-7-compare.png` |

Every builder screen is taken again here: View in 3D's row, the note, the side line and the turn's buttons all changed after the lanes' pictures (F7, F13, D-483, D-484, D-521, #171).

## The 3D view (#139)

| Screen | Desktop | Phone |
| --- | --- | --- |
| The deck step, View in 3D offered beside the board | `desktop-3d-closed-deck-light-en` | `phone-3d-closed-deck-light-en` |
| The view open on the deck step | `desktop-3d-open-deck-light-en` | `phone-3d-open-deck-light-en` |
| The view open on the wheels step | `desktop-3d-open-wheels-light-en` | `phone-3d-open-wheels-light-en` |
| The view open at the review, and the whole page | `desktop-3d-open-review-light-en`, `desktop-3d-open-review-full-light-en` | `phone-3d-open-review-light-en`, `phone-3d-open-review-full-light-en` |
| The phone's press by a real tap, where the page is left | | `phone-3d-open-tapped-light-en` |
| The "usual sizes" note, the view's stage alone | `desktop-3d-usual-note-light-en` | `phone-3d-usual-note-light-en` |
| Reset view, on the whole build at the grip step | `desktop-3d-place-reset-light-en` | `phone-3d-place-reset-light-en` |
| Top | `desktop-3d-place-top-light-en` | `phone-3d-place-top-light-en` |
| Underside | `desktop-3d-place-underside-light-en` | `phone-3d-place-underside-light-en` |
| Side (#171) | `desktop-3d-place-side-light-en` | `phone-3d-place-side-light-en` |
| Graphic close-up | `desktop-3d-place-graphic-light-en` | `phone-3d-place-graphic-light-en` |
| A kickflip mid-air, the trick buttons disabled | `desktop-3d-trick-kickflip-light-en` | `phone-3d-trick-kickflip-light-en` |
| The fallback after a lost context: the board flat, with the message | `desktop-3d-fallback-light-en` | `phone-3d-fallback-light-en` |
| Reduced motion: the view open, no trick buttons | `desktop-3d-reduced-motion-light-en` | `phone-3d-reduced-motion-light-en` |
| The phone held sideways, the view open, and the whole page | | `phone-sideways-3d-open-light-en`, `phone-sideways-3d-open-full-light-en` |

## The bar and the steps

| Screen | Desktop | Phone |
| --- | --- | --- |
| #153: the phone bar on Twenty Twenty-Five, on the trucks step and at the review | | `phone-bar-twentytwentyfive-light-en`, `phone-bar-review-twentytwentyfive-light-en` |
| #152: Next pressed on the first step, its answer held back | `desktop-next-held-deck-light-en` | `phone-next-held-deck-light-en` |
| #152: the same once the answer is in | `desktop-next-answered-trucks-light-en` | `phone-next-answered-trucks-light-en` |
| F35: Help me choose after Show builds, focus on Builds for you | `desktop-guide-builds-focused-light-en` | `phone-guide-builds-focused-light-en` |

## Dark and French

| Screen | Dark | French |
| --- | --- | --- |
| The review, the view closed | `desktop-3d-closed-review-dark-en`, `phone-3d-closed-review-dark-en` | `desktop-3d-closed-review-light-fr`, `phone-3d-closed-review-light-fr` |
| The 3D view open at the review, and the whole page | `desktop-3d-open-review-dark-en`, `phone-3d-open-review-dark-en`, `desktop-3d-open-review-full-dark-en`, `phone-3d-open-review-full-dark-en` | `desktop-3d-open-review-light-fr`, `phone-3d-open-review-light-fr`, `desktop-3d-open-review-full-light-fr`, `phone-3d-open-review-full-light-fr` |
| The phone bar on Twenty Twenty-Five, on the trucks step and at the review | `phone-bar-twentytwentyfive-dark-en`, `phone-bar-review-twentytwentyfive-dark-en` | `phone-bar-twentytwentyfive-light-fr`, `phone-bar-review-twentytwentyfive-light-fr` |

## The admin, at phone width and in Site Health

| Screen | Desktop | Phone |
| --- | --- | --- |
| The Build tab's Shown in the builder on an old kit: 3D view off, Trick buttons not shown | lane 1's | `phone-build-tab-old-kit-light-en` |
| The same on a new kit: 3D view ticked, Trick buttons on | lane 1's | `phone-build-tab-new-kit-light-en` |
| Part specs, Deck: nose length, tail length, concave depth and kick angle, and the group's foot | lane 1's | `phone-part-specs-deck-light-en`, `phone-part-specs-deck-kick-light-en` |
| Part specs, Wheels: the wheel's width | lane 1's | `phone-part-specs-wheels-light-en` |
| The Catalogue check, and what it found | lane 1's | `phone-catalogue-check-light-en`, `phone-catalogue-check-found-light-en` |
| Site Health's Catalogue check, with decks and wheels whose sizes are assumed | `desktop-site-health-catalogue-light-en` | `phone-site-health-catalogue-light-en` |

## What the pictures show

1. **Should-fix:** on a phone, a tap on View in 3D leaves the page scrolled 404 px down, the height the view adds above the cards, so the canvas is above the screen and the shopper sees the camera's buttons and the deck step. Scroll anchoring keeps the cards in place. `phone-3d-open-tapped-light-en`
2. **Should-fix:** on a phone held sideways with the view open, the 609 px frame covers the row under the heading: View in 3D, which closes the view, and the turn's Underside and Top can't be pressed, and only their tops show. The canvas is taller than the 390 px screen. `phone-sideways-3d-open-light-en`, `phone-sideways-3d-open-full-light-en`
3. **Should-fix:** with the view open, the 2D turn's Underside and Top stay beside the view's own Top and Underside, so the screen has two buttons of each name. The turn's buttons move the 3D camera too, and the turn keeps "✓ Top" pressed while the camera is at Underside, Side or the close-up. `desktop-3d-place-underside-light-en`, `phone-3d-open-deck-light-en`
4. **Minor:** in the phone's fallback, the message runs into the board's "Bearings" label, beside the wrapped "so the board". `phone-3d-fallback-light-en`
5. **Minor:** Side puts the board near the canvas's foot, with most of the frame empty above it. `desktop-3d-place-side-light-en`
6. **Minor:** the phone's Graphic close-up cuts the tail off at the canvas's left edge and the nose at its top. `phone-3d-place-graphic-light-en`
7. **Minor:** Site Health says the Catalogue check found 11 things while the check's screen says 43. The difference is the usual-size notes, which Site Health neither counts nor names. `desktop-site-health-catalogue-light-en`, `phone-catalogue-check-light-en`
8. **Minor:** "Kick angle in degrees" is the only new spec label without its unit in brackets, as "Concave depth (in)" has it. `phone-part-specs-deck-kick-light-en`
