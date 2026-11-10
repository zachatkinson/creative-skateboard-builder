# Snippets the grep gates must reject

Each file is one thing `tools/gates.sh` forbids. `tools/gates-selftest.sh` (part of `composer gates`) puts each into an empty copy of the plugin's layout and fails unless the gate named by the file's leading number rejects it. `0-clean.fixture` must pass. A file ending `.pot.fixture` becomes the POT; one ending `.yml.fixture` becomes `.github/workflows/release.yml` (`0-clean-release.yml.fixture`, a release workflow of the right shape, must pass); one starting `6-` goes into `src/Domain`; the rest go into `src/Support`.

Add a snippet here in the same commit as any new rule, or any new way round an old one.
