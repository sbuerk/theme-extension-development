# Dual core setup

The extension serves **TYPO3 v12.4 and v13.4** from one code base, and the
tooling installs **one** dependency set into `.Build/` at a time. Everything
below follows from that: the two versions are developed against alternately,
never simultaneously, and the whole development workflow rests on knowing which
one is currently installed.

## The rule

> **The dependency set installed in `.Build/` must match the core version the
> tool is run for.**

`-t <version>` selects the core version for a suite, but it does **not** install
anything. Only `composerUpdate` changes what is in `.Build/`:

```bash
# 1. Install the dependency set ...
Build/Scripts/runTests.sh -t 12 -s composerUpdate

# 2. ... then run gates with the SAME -t value.
Build/Scripts/runTests.sh -t 12 -s phpstan
Build/Scripts/runTests.sh -t 12 -s unit
Build/Scripts/runTests.sh -t 12 -s functional -d sqlite
```

Running a suite with a `-t` value other than the installed set produces **false
positives and false negatives**, not an error message. PHPStan reports missing
classes that exist in the other core version, and tests pass or fail for reasons
unrelated to the change under test. `runTests.sh` rejects an unsupported `-t`
value outright, but it cannot see what is installed — that part is on the
person running it.

**Do one version completely, then switch.** Interleaving `-t 12` and `-t 13`
commands is the reliable way to produce a green run that proves nothing.

`composerInstall` does **not** honour `-t`; it only replays the current
`composer.lock`. `composerUpdate` removes and reinstalls `.Build/` and
`composer.lock` — both are git-ignored, so nothing of value is lost.

Locally it drops the composer download cache in `.cache/` as well, which is why
an install downloads the dependency set rather than unpacking it. That is a
deliberate precaution: a working copy accumulates installs across core versions,
switching between them exchanges the **major version** of four packages at once
(see the table below), and an install is never left to resolve against a cache
belonging to a different one.
→ [The composer cache](quality-gates.md#the-composer-cache)

## The PHP dimension is not square

The two core versions do not accept the same PHP versions, so `-t` and `-p` are
not independent:

| `-p`  | TYPO3 v12.4 | TYPO3 v13.4 |
|-------|-------------|-------------|
| `8.1` | yes         | **no**      |
| `8.2` | yes         | yes         |
| `8.3` | yes         | yes         |
| `8.4` | yes         | yes         |

`typo3/cms-core` 13.4 requires PHP `^8.2`, so `-t 13 -p 8.1` cannot resolve at
all. `runTests.sh` does not reject the combination — `composerUpdate` does, by
failing to resolve, which is the honest place for it. The `-p` help text says
so.

The default is `-p 8.2`, the lowest version valid for **both** core versions,
and the default of `-t` is **12**, the lowest supported core version: gates that
do not depend on a core version are run against the lowest set, which is where
an accidentally used newer API shows up.

PHP 8.1 is therefore only ever exercised together with `-t 12`. That combination
is not optional decoration — it is what catches PHP 8.2-only syntax such as a
`readonly` class or a constant in a trait, both of which parse on every other
supported version.
→ [Class design](../architecture/class-design.md#backports-from-main-readonly-moves-off-the-class)

## The dependency sets differ by more than the core

Switching `-t` does not only exchange `typo3/cms-core`. The v12 set resolves an
older toolchain, because the newer one does not support v12 at all:

| Package                              | With `-t 12` | With `-t 13` |
|--------------------------------------|--------------|--------------|
| `typo3/cms-core`                     | 12.4         | 13.4         |
| `typo3/testing-framework`            | 8.x          | 8.x          |
| `phpunit/phpunit`                    | 10.5         | 10.5         |
| `phpstan/phpstan`                    | 1.x          | 2.x          |
| `saschaegerer/phpstan-typo3`         | 1.x          | 2.x          |
| `nikic/php-parser`                   | 4.x          | 5.x          |
| `sbuerk/typo3-site-based-test-trait` | 1.x          | 2.x          |
| `fgtclb/environment-state-manager`   | 1.x          | 1.x          |

Three of those are worth knowing about:

- **`saschaegerer/phpstan-typo3` is what forces PHPStan 1.x onto v12.** No 2.x
  or 3.x release of it supports TYPO3 v12 — they all require
  `typo3/cms-core ^13.4.3` — and 1.10.2, the newest v12-capable release,
  requires PHPStan 1.x. The v13 leg keeps the 2.x toolchain regardless, because
  1.10.2 predates 13.4 and using it there would be a downgrade.
- **PHPUnit is pinned to 10.5 for the whole branch.** `typo3/testing-framework`
  8.x permits PHPUnit 10 *or* 11, and PHPUnit 11 requires PHP ≥ 8.2, so left
  alone composer would resolve 11.5 everywhere except the PHP 8.1 job and this
  branch would run two PHPUnit majors at once. It does not: `composer.json`
  requires `^10.5.64`, so every job on every PHP version and both core versions
  runs the same one. That is a deliberate narrowing — 10.5 is the only major
  reachable on PHP 8.1, and one major means one set of runner semantics rather
  than two that disagree (`--exclude-group` is spelled differently in each).
  → [PHPUnit configuration](../testing/phpunit-configuration.md)
- **`nikic/php-parser` spans two majors**, because `typo3/cms-install` requires
  `^4.15.4` on v12. `Build/Scripts/testMethodPrefixChecker.php` is its only
  consumer here and picks its factory spelling at runtime —
  `ParserFactory::createForVersion()` and `PhpParser\PhpVersion` are 5-only. A
  build script is not extension code, so a conditional is the right tool there,
  and it carries a `@todo`.

`fgtclb/environment-state-manager` 1.0.0 is the v12-capable major and is what
raises the core floor to **12.4.22**; the constraint states that floor rather
than hiding it behind `^12.4`.

## The changelogs come with the dependency set

The TYPO3 changelogs live inside the core package, so what is readable below
`.Build/vendor/typo3/cms-core/Documentation/Changelog/` is decided by which set
is installed. A package carries the changelogs of its own and all **earlier**
versions:

| Installed set | On disk                     | Missing        |
|---------------|-----------------------------|----------------|
| `-t 12`       | `7.0/` … `12.4/`, `12.4.x/` | everything v13 |
| `-t 13`       | `7.0/` … `13.4/`, `13.4.x/` | —              |

Installing the **highest** supported version therefore puts both sets on disk at
once and saves switching back and forth to look something up. Reading a
changelog is not running a gate — look it up with the v13 set installed, then
`composerUpdate` back to the version being worked on before running anything.
→ [Referencing TYPO3 behaviour changes](../workflow/commit-messages.md#referencing-typo3-behaviour-changes)

## Verifying a change

A change is only verified when the full sequence has run for **both** core
versions, each after its own `composerUpdate`:

```bash
for core in 12 13; do
    Build/Scripts/runTests.sh -t ${core} -s composerUpdate
    Build/Scripts/runTests.sh -t ${core} -s cgl -n
    Build/Scripts/runTests.sh -t ${core} -s phpstan
    Build/Scripts/runTests.sh -t ${core} -s lintPhp
    Build/Scripts/runTests.sh -t ${core} -s unit
    Build/Scripts/runTests.sh -t ${core} -s unitRandom
    Build/Scripts/runTests.sh -t ${core} -s functional -d sqlite
    Build/Scripts/runTests.sh -t ${core} -s composerValidate
    Build/Scripts/runTests.sh -t ${core} -s checkBom
    Build/Scripts/runTests.sh -t ${core} -s checkExceptionCodes
    Build/Scripts/runTests.sh -t ${core} -s checkMarkdownTables
    Build/Scripts/runTests.sh -t ${core} -s checkTestMethodsPrefix
    Build/Scripts/runTests.sh -t ${core} -s checkCssBuild
done
```

Add `-t 12 -p 8.1 -s lintPhp` when the change adds or moves a class — that is
the only run in which PHP 8.1 syntax is checked at all.

Add `-s functional -d mariadb -i 10.6` (or `mysql`, `postgres`) when the change
touches queries, schema or TCA — the schema is derived from TCA, so a TCA change
is a schema change, and on v12 it is also an
[`ext_tables.sql`](../architecture/core-version-aware-code.md#configuration-is-the-exception)
change.

Running the gates in CI only is not enough: CI reports the failure *after* the
pull request is open, and the core version aware parts of the tree are exactly
where mistakes happen.

## What is core version dependent

| Artefact                        | Per core version?                                                      |
|---------------------------------|------------------------------------------------------------------------|
| `Classes/`                      | No — must work on every supported version.                             |
| `Core<major>/`                  | Yes — `Core12/` and `Core13/`.                                         |
| `Build/phpstan/Core<major>/`    | Yes — separate config and baseline each, `Core12`, `Core13`.           |
| `Tests/Unit/Core<major>/`       | Yes — grouped, see below.                                              |
| `Tests/Functional/Core<major>/` | Yes — same grouping.                                                   |
| `Build/phpunit/*.xml`           | No — one configuration for all of them.                                |
| `Configuration/`                | No — loaded from a fixed path, so a difference is applied in the file. |
| `ext_localconf.php`             | No — one file, with a single v12 block that is inert on v13.           |
| `ext_tables.sql`                | No — one file, needed by v12 and redundant on v13.                     |
| `.github/workflows/ci.yml`      | No — the core version is a matrix dimension.                           |

The mechanism is described in full in
[Core version aware code](../architecture/core-version-aware-code.md).

## Test grouping

`Build/Scripts/runTests.sh` passes `--exclude-group not-core-<version>` to
PHPUnit for whichever core version `-t` selected. A test that must **not** run on
a given core version therefore declares the group naming that version:

```php
#[Group('not-core-12')]
final class SiteSetRenderingTest extends AbstractFunctionalTestCase
{
}
```

Note the inverted logic: the group names the core version the test must **not**
run on, so a test without any group runs everywhere.

The group is used sparingly and deliberately. It belongs on a test whose
**subject** is version specific — `SiteSetRenderingTest`, because site sets do
not exist on v12, and the two version specific assertions of
[`ExtensionCoreVersionCompatTestsTrait`](../../Tests/ExtensionCoreVersionCompatTestsTrait.php).
It does **not** belong on a test that merely needs the theme set up, because
that would delete the test on the other version. Those go through
[`ThemeSiteTrait`](../testing/site-based-tests.md#arranging-the-theme-themesitetrait)
instead, which arranges the delivery of whichever version is running and leaves
the test itself version neutral.

## See also

- [Development environment](environment.md)
- [Quality gates](quality-gates.md)
- [Core version aware code](../architecture/core-version-aware-code.md)
- [Site based tests](../testing/site-based-tests.md)
