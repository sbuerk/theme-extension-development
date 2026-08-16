# Core version setup

The extension is written to serve more than one TYPO3 major version from one
code base, and the tooling installs **one** dependency set into `.Build/` at a
time. Today exactly one core version is supported — TYPO3 v13 — so there is only
one set to install, but the rule below is what the whole development workflow
rests on, and it does not depend on how many versions there are.

## The rule

> **The dependency set installed in `.Build/` must match the core version the
> tool is run for.**

`-t <version>` selects the core version for a suite, but it does **not** install
anything. Only `composerUpdate` changes what is in `.Build/`:

```bash
# 1. Install the dependency set ...
Build/Scripts/runTests.sh -t 13 -s composerUpdate

# 2. ... then run gates with the SAME -t value.
Build/Scripts/runTests.sh -t 13 -s phpstan
Build/Scripts/runTests.sh -t 13 -s unit
Build/Scripts/runTests.sh -t 13 -s functional -d sqlite
```

Running a suite with a `-t` value other than the installed set produces **false
positives and false negatives**, not an error message. PHPStan reports missing
classes that exist in the other core version, and tests pass or fail for reasons
unrelated to the change under test. `runTests.sh` rejects an unsupported `-t`
value outright, but it cannot see what is installed — that part is on the
person running it.

`composerInstall` does **not** honour `-t`; it only replays the current
`composer.lock`. `composerUpdate` removes and reinstalls `.Build/` and
`composer.lock` — both are git-ignored, so nothing of value is lost.

Locally it drops the composer download cache in `.cache/` as well, which is why
an install downloads the dependency set rather than unpacking it. That is a
deliberate precaution: a working copy accumulates installs across core versions,
switching between them also switches the major version of
`typo3/class-alias-loader`, and an install is never left to resolve against a
cache belonging to a different major.
→ [The composer cache](quality-gates.md#continuous-integration)

## The changelogs come with the dependency set

The TYPO3 changelogs live inside the core package, so what is readable below
`.Build/vendor/typo3/cms-core/Documentation/Changelog/` is decided by which set
is installed. A package carries the changelogs of its own and all **earlier**
versions: with the v13 set installed everything from `7.0/` up to and including
`13.4.x/` is on disk.

Nothing newer than the installed set is there, so a claim about a TYPO3 version
this branch does not support cannot be verified from this checkout — say so
rather than asserting it. Reading a changelog is not running a gate.
→ [Referencing TYPO3 behaviour changes](../workflow/commit-messages.md#referencing-typo3-behaviour-changes)

## Verifying a change

A change is only verified when the full sequence has run for **every** supported
core version, each after its own `composerUpdate`. With one supported version
that is one pass:

```bash
Build/Scripts/runTests.sh -t 13 -s composerUpdate
Build/Scripts/runTests.sh -t 13 -s cgl -n
Build/Scripts/runTests.sh -t 13 -s phpstan
Build/Scripts/runTests.sh -t 13 -s lintPhp
Build/Scripts/runTests.sh -t 13 -s unit
Build/Scripts/runTests.sh -t 13 -s unitRandom
Build/Scripts/runTests.sh -t 13 -s functional -d sqlite
Build/Scripts/runTests.sh -t 13 -s composerValidate
Build/Scripts/runTests.sh -t 13 -s checkBom
Build/Scripts/runTests.sh -t 13 -s checkExceptionCodes
Build/Scripts/runTests.sh -t 13 -s checkMarkdownTables
Build/Scripts/runTests.sh -t 13 -s checkTestMethodsPrefix
Build/Scripts/runTests.sh -t 13 -s checkCssBuild
```

Add `-s functional -d mariadb -i 10.6` (or `mysql`, `postgres`) when the change
touches queries, schema or TCA — the schema is derived from TCA, so a TCA change
is a schema change.

Running the gates in CI only is not enough: CI reports the failure *after* the
pull request is open, and the core version aware parts of the tree are exactly
where mistakes happen.

## What is core version dependent

| Artefact                        | Per core version?                                              |
|---------------------------------|----------------------------------------------------------------|
| `Classes/`                      | No — must work on every supported version.                     |
| `Core<major>/`                  | Yes — one directory per core version, today only `Core13/`.    |
| `Build/phpstan/Core<major>/`    | Yes — separate config and baseline each, today only `Core13/`. |
| `Tests/Unit/Core<major>/`       | Yes — grouped, see below.                                      |
| `Tests/Functional/Core<major>/` | Yes — same grouping.                                           |
| `Build/phpunit/*.xml`           | No — one configuration for all of them.                        |
| `.github/workflows/ci.yml`      | No — the core version is a matrix dimension.                   |

The mechanism is described in full in
[Core version aware code](../architecture/core-version-aware-code.md).

## Test grouping

`Build/Scripts/runTests.sh` passes `--exclude-group not-core-<version>` to
PHPUnit for whichever core version `-t` selected. A test that must **not** run on
a given core version therefore declares the group naming that version:

```php
#[Group('not-core-<version>')]
final class ExampleTest extends UnitTestCase
{
}
```

Note the inverted logic: the group names the core version the test must **not**
run on, so a test without any group runs everywhere.

With a single supported core version there is nothing to exclude, and no test
carries such a group today. The mechanism stays wired up regardless —
`runTests.sh` passes the flag and `Build/phpunit/UnitTests.xml` and
`FunctionalTests.xml` document it — because it belongs to the version aware
layout rather than to any one version.

## See also

- [Development environment](environment.md)
- [Quality gates](quality-gates.md)
- [Core version aware code](../architecture/core-version-aware-code.md)
