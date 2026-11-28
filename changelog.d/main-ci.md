### Changed

- **Development:** CI's integration and browser legs get 80 minutes, since milestone 14's 3D tests took the oldest leg past 60 on a slow runner with every test passing (D-544).
- **Development:** under CI, a start of wp-env that fails on a download from wordpress.org (a 429, a 5xx, a timeout) is tried again, up to three tries, 20 then 60 seconds apart; any other failure stops at once (#179, D-565).
- **Development:** the browser tests' lock around wp-env is taken only from a holder that has died, never on age alone, and a lock judged abandoned is cleared in place under a clearer's own lock, so two processes can no longer hold it at once (#172, D-550).
