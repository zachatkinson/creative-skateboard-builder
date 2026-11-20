# Security policy

## Reporting a problem

Report a security problem privately, through this repository's **Security** tab: **Report a vulnerability**. Please don't open a public issue for it, or post it anywhere else, until a fix is released.

Say what you found, the version, and the steps that show it. A proof of concept against your own copy in a local WordPress is welcome. Please don't test against any shop that runs the plugin, or against the demo.

We answer within three working days, tell you what we will do, and credit you in the release notes unless you would rather not be named.

## Supported versions

| Version | Fixes |
| --- | --- |
| 1.x, the newest release | Yes |
| 0.x | No: update to 1.x |

A fix ships as a new release in [zachatkinson/creative-releases](https://github.com/zachatkinson/creative-releases), and WordPress offers it on the Plugins screen.

## What the plugin does about it

[docs/SECURITY.md](docs/SECURITY.md) walks the OWASP Top 10 against the code for each release: who can do what, what is stored, how every request is checked, and the one outbound request it makes (the update manifest).
