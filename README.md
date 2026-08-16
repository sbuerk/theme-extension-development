# TYPO3 extension `theme_extension_development`

A TYPO3 frontend theme for **development purposes**. Its job is to give a TYPO3
installation a reasonable frontend to look at and to render against, without
building a site package for it first.

- **Package name**: `sbuerk/theme-extension-development`
- **Extension key**: `theme_extension_development`
- **Repository**: https://github.com/sbuerk/theme-extension-development
- **License**: GPL-2.0-or-later

> [!IMPORTANT]
> **The theme is not finished** — the section below describes what this package
> is for; see [Status](#status) for what it already does.
>
> It is a development tool, not a production theme.

## What it is for

The situations it is built for, where an extension has to be seen or exercised
in a frontend rather than only in a test assertion:

- **Extension development**, to click through what an extension actually
  outputs instead of reading the rendered HTML in a test failure.
- **DDEV based test instances** of an extension repository, where a throwaway
  TYPO3 installation needs a frontend rendering pages, navigation and content
  elements.
- **Acceptance tests**, which need a stable and predictable frontend to drive a
  browser against.
- **Reproducing an issue** in a minimal installation before debugging it.

→ [Introduction](Documentation/Introduction/Index.rst) states the same for
users and integrators, with the full scope warning.

## Status

**The theme renders a page, and not much more yet.** A site enables it by
depending on the site set `sbuerk/theme-extension-development`, which brings the
TypoScript, a Fluid page rendering and the compiled stylesheet.

It renders **Header**, **Text** and **Images** without depending on
`fluid_styled_content`. The remaining classic elements — Text & Media, Bullet
List, Table, File Links, the menus — **can be created**: their TCA comes from
EXT:frontend, and what `fluid_styled_content` contributes is only the rendering.
They render the TYPO3 "no rendering definition" notice until the theme gives
them a template, which needs no TCA of its own.

Backend layouts are missing too, so every page renders the same template.

The stylesheet is built from a documented set of design tokens with a light
and a dark appearance — see [`DESIGN.md`](DESIGN.md). They are CSS custom
properties, so a site package re-themes the extension by overriding a handful
of them, without rebuilding the SCSS.

Underneath that sits the foundation: TYPO3 v13 and v14 support from one code
base, the core version aware wiring, the SCSS build, two development instances
and the container based test and quality gate harness. The public API is not
stable yet and may change without a deprecation phase until the first stable
release.

## Compatibility

| Branch | Extension | TYPO3     | PHP       |
|--------|-----------|-----------|-----------|
| main   | 1.x       | v13 / v14 | 8.2 - 8.5 |

## Installation

Being a development tool, it usually belongs in `require-dev` — of the extension
repository whose frontend you want to look at, or of the test instance you set
up for it:

```bash
composer require --dev sbuerk/theme-extension-development
```

As long as no stable version has been released, require the development version
of the main branch explicitly:

```bash
composer require --dev sbuerk/theme-extension-development:^1.0@dev
```

This additionally requires `minimum-stability: "dev"` together with
`prefer-stable: true` in the root `composer.json` file.

## Documentation

| For                        | Where                                                         |
|----------------------------|---------------------------------------------------------------|
| Users and integrators      | [`Documentation/`](Documentation), rendered to docs.typo3.org |
| Developers and maintainers | [`docs/`](docs/Index.md)                                      |
| Contributors, entry point  | [`CONTRIBUTING.md`](CONTRIBUTING.md)                          |
| AI coding agents           | [`AGENTS.md`](AGENTS.md)                                      |

```bash
# Render once, as CI does. Must pass without errors.
Build/Scripts/runTests.sh -s renderDocumentation

# Serve it while writing, re-rendering on every save, on port 1337.
Build/Scripts/runTests.sh -s watchDocumentation
```

The rendered output is written to the git-ignored `Documentation-GENERATED-temp/`
directory.

## Development

All tests and quality tools run in containers through the
[`Build/Scripts/runTests.sh`](Build/Scripts/runTests.sh) wrapper. The only
requirement on the host is a container runtime — **podman** (preferred) or
**docker**.

```bash
# Install dependencies for TYPO3 v13 on PHP 8.2 (default matrix).
Build/Scripts/runTests.sh -t 13 -p 8.2 -s composerUpdate

# Quality gates.
Build/Scripts/runTests.sh -s cgl -n
Build/Scripts/runTests.sh -s phpstan
Build/Scripts/runTests.sh -s lintPhp

# Tests.
Build/Scripts/runTests.sh -s unit
Build/Scripts/runTests.sh -s functional -d sqlite

# All available options.
Build/Scripts/runTests.sh -h
```

Everything has to pass for **both** TYPO3 v13 and v14, each after the matching
`composerUpdate` — see
[Dual core setup](docs/development/dual-core-setup.md).

→ [`CONTRIBUTING.md`](CONTRIBUTING.md) for the contribution workflow ·
[`docs/`](docs/Index.md) for the full developer documentation

## License

This extension is published under the [GPL-2.0-or-later](LICENSE) license.
