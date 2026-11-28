### Changed

- **Development:** CI's integration and browser legs get 80 minutes, since milestone 14's 3D tests took the oldest leg past 60 on a slow runner with every test passing (D-544).
- **Development:** under CI, a start of wp-env that fails on a download from wordpress.org (a 429, a 5xx, a timeout) is tried again, up to three tries, 20 then 60 seconds apart; any other failure stops at once (#179, D-565).
- **Development:** the OpenSSF Scorecard runs weekly and on each push to `main`, its result in code scanning and as the README's badge. The SBOMs and CodeQL were already in place (#169, D-545).
- **Development:** every start of a wp-env site checks that it runs the pinned WordPress and WooCommerce, the oldest pair's after `npm run env:oldest`, and fails if not; `npm run env:oldest -- --start` starts both sites on the oldest pair (D-548).
- **Development:** the browser tests' lock around wp-env is taken only from a holder that has died, never on age alone, and a lock judged abandoned is cleared in place under a clearer's own lock, so two processes can no longer hold it at once (#172, D-550).
- **Development:** the upgrade runs follow a support floor in `tools/upgrade-releases.sh`. While none is set, only the demo runs the plugin, and CI's updates job runs from the previous release alone; `npm run upgrade:all -- --every` runs every release, before a major and when a migration changes (D-549).
- **Development:** CI's static and JavaScript jobs run on ARM runners, about 6 % faster than on x64 over two measured runs, every step passing (D-569).
- **Development:** the browser tests try a `wp-env run` again, twice, where wp-env lost its connection to wordpress.org before running the command, which cost CI one test in 718 (D-575).
