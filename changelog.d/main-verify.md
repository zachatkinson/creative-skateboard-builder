### Changed

- /verify is `tools/verify.sh`: one run a lane, a stopped run stops the integration suite in the container too, and before a commit the browser step runs only the tests that cover the change, with `--full` before every push (D-567).
- `tools/verify.sh --full --split <lane> <checkout>` runs a push's chromium-phone in another lane's checkout at the same commit, beside chromium-desktop (D-570).
