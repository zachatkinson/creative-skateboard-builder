### Fixed

- On a phone the bar's Back and Next, or Add to cart, stay beside the total: Next's words break inside the button where the bar is short of room, never inside a word, and the button keeps its 44 px target. In the theme's content width they had taken a row of their own under the total, on a step in English on Twenty Twenty-Five and Storefront and always in French, up to 199 px of bar; it is now 90 to 147 px (#153, D-510).
- Next pressed on the first step no longer moves the step or the bar when its answer comes a second late, as on a real host: the deck step's width line and its count go at the press, as the answer takes them, and the bar's Back comes at the press too. On a phone the step had moved by up to 0.046 of layout shift and the bar by 0.004 (#152, D-512).

### Changed

- **Development:** the browser tests' lock around wp-env reads the clock once to judge a lock and to give up on it. Read twice, a lock made within the millisecond the wait began could be given up on as still held, and its test failed now and then under load (#155, D-511).
