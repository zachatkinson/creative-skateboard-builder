# Contributing

Thank you for looking. This plugin is built by Creative Branding and held to a written audit, so the way in is a little narrower than usual.

## Contents

- [Issues: welcome](#issues-welcome)
- [Pull requests: by invitation](#pull-requests-by-invitation)
- [Conduct](#conduct)

## Issues: welcome

Open an issue for a bug, a question or an idea, with the template that fits. For a bug, these are what we need most:

- the plugin's version, WordPress's and WooCommerce's;
- the theme;
- the steps that show it.

Site Health's Info tab has a "Skateboard builder" section with the versions and the index's state, which can be copied in whole.

A security problem goes through [SECURITY.md](SECURITY.md), not an issue.

## Pull requests: by invitation

**Every change is held to the twelve dimensions** in [docs/AUDIT.md](docs/AUDIT.md), with tests first and every gate green, so a pull request we haven't talked about first is likely to be closed unread. If you would like to work on something, say so on its issue, and we'll say whether we can take it and how.

Once invited:

- Read [CLAUDE.md](CLAUDE.md) for the commands and the rules, and [docs/STANDARDS.md](docs/STANDARDS.md) for how the code is written.
- Write the failing test first, and put the test and the change that passes it in one commit.
- Run `composer check`, `npm run check` and the integration and browser suites in wp-env before you push.
- Fill in the pull request template.

By contributing, you agree that your work is licensed under the GPL-2.0-or-later, as the plugin is.

## Conduct

Everyone taking part follows the [code of conduct](CODE_OF_CONDUCT.md).
