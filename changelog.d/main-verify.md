### Changed

- /verify is `tools/verify.sh`: one run a lane, a stopped run stops the integration suite in the container too, and before a commit the browser step runs only the tests that cover the change, with `--full` before every push (D-567).
