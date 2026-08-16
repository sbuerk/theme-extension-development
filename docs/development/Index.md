# Development

Setting up a working copy, running the tooling and keeping every supported TYPO3
version green.

| Page                                            | Contents                                                                                                                               |
|-------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------------------|
| [Development environment](environment.md)       | `runTests.sh`, container runtimes, the full suite and option list, passing arguments to PHPUnit.                                       |
| [Dual core setup](dual-core-setup.md)           | TYPO3 v12 and v13 from one code base: why the installed set must match `-t`, the PHP asymmetry, how to verify a change, test grouping. |
| [Quality gates](quality-gates.md)               | Every gate and its configuration, PHPStan per core version, the CI staging and why it runs the containers with docker.                 |
| [Frontend assets](frontend-assets.md)           | The SCSS build, the node image, why the compiled CSS is committed, the `checkCssBuild` gate.                                           |
| [Component library](component-library.md)       | Every component's markup contract, the two switches that change behaviour, what the tests guard.                                       |
| [Styleguide page](styleguide.md)                | The page rendering the library straight from Fluid, why it ignores content elements, and what it is a live test of.                    |
| [Development instances](instances.md)           | The TYPO3 instance per core version, the `theme` symlink, SQLite, snapshot and restore, enabling the theme on v12.                     |
| [Seeding](seeding.md)                           | `theme:seed`, the definition format, inline relations, the demo tree, and why it goes through DataHandler.                             |
| [Appearance switching](appearance-switching.md) | The three constants, server-rendered attributes, the no-flash script, the switcher, and why there is no cookie.                        |

## Quick start

```bash
# Install dependencies for TYPO3 v12 on PHP 8.2 (the defaults).
Build/Scripts/runTests.sh -t 12 -p 8.2 -s composerUpdate

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

Repeat the whole block with `-t 13`, starting again at `composerUpdate` — and
never interleave the two. See [Dual core setup](dual-core-setup.md).

## See also

- [Documentation index](../Index.md)
- [Testing](../testing/Index.md)
- [Pull requests](../workflow/pull-requests.md)
