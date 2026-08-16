# Agent instructions

This file is the entry point for AI coding agents working in this repository.
`CLAUDE.md`, `GEMINI.md` and `.github/copilot-instructions.md` are symlinks to
it — there is one set of instructions, not four.

It does **not** repeat the developer documentation. Everything about how this
repository is built lives in [`docs/`](docs/Index.md) and
[`CONTRIBUTING.md`](CONTRIBUTING.md), and is linked from here. What this file
adds are the rules that apply *specifically* to working as an agent, and the
handful of things that are easy to get wrong and expensive to discover later.

## Local additions and overrides

A machine-local `AGENTS.local.md` may sit next to this file. It is git-ignored
and never committed, so a fresh clone starts without it. `CLAUDE.local.md` is a
symlink to it.

**Read it if it is present.** It belongs to whoever works on this checkout, it
may add to or override anything in this file, and where the two differ it takes
precedence.

## Who you are working for

The maintainer of this repository is an **experienced senior developer with a
maintainer background** — TYPO3 Core Team, and maintainer of a number of public
extensions and packages. Work is held to TYPO3 Core review standards.

What follows from that:

- **Explain decisions, not concepts.** No introductions to what dependency
  injection or a functional test is. Say what you chose and why you rejected the
  alternative.
- **Correctness before volume.** A small change that is verified beats a large
  one that is plausible.
- **Say what you did not do.** A gap that is named is a decision; a gap that is
  hidden is a defect.
- **Disagreement is useful.** If the requested approach has a real problem, say
  so in a sentence or two, then deliver what was asked under stated assumptions.

## AI-assisted contributions

This repository follows the **TYPO3 Association policy on AI-assisted code**.

> [!NOTE]
> The policy is a **draft under community review** at
> <https://github.com/TYPO3-Documentation/Policy/pull/47>. The summary below
> reflects the draft of 20 July 2026. Re-check it against the final document
> once the pull request is merged, and update this section.

The policy neither prohibits nor stigmatizes AI-assisted development. What it
asks for is accountability, transparency and unchanged quality.

**1. Responsibility is indivisible, and AI is not an author.** The contributor
is accountable for the quality, security, functionality and license compliance
of every line submitted, no matter how it was produced — exactly as for code
adapted from documentation or a forum answer. A model is a tool. It is therefore
never credited as an author: no `Co-authored-by:` trailer for a model or tool,
and no "Generated with …" notice.

**2. Disclose substantial AI involvement.** When AI generated the structural
logic, roughly more than half the lines of a commit, or structural
configuration such as TCA or a database schema — or when an agent produced the
commits autonomously — add a trailer naming the tool:

```
[FEATURE] Add core version aware example service

Explain what the change does and why it is needed.

AI-assisted: <tool name>
```

Disclosure is **not** expected for incidental use: editor autocompletion,
explaining or exploring existing code, formatting, linting fixes and mechanical
refactoring, or drafting a commit message or documentation that was then
reviewed and edited. The tag signals provenance; it does not document the
process, so prompts and iteration counts do not belong in it.

**3. Provenance sign-off is available, not required.** The draft policy
recommends a `Signed-off-by:` trailer certifying the
[Developer Certificate of Origin](https://developercertificate.org/) — that you
have the right to submit the contribution under this extension's license. It is
a **human act**, distinct from the disclosure tag above, and applies whether or
not a tool was involved:

```
Signed-off-by: Firstname Lastname <mail@example.com>
```

`git commit -s` adds it. **This repository does not require it.** Add it if you
want the certification on record; leaving it off is not a review finding here.

**4. The quality bar does not move.** Not lower, not higher. The same gates, the
same coding guidelines, the same review. In particular, verify that every TYPO3
API used actually exists in the target version — models routinely suggest
deprecated methods, removed classes and outdated patterns, and the authoritative
answer is on disk (see [the changelogs section](#verify-against-the-typo3-changelogs)).
Never submit code that has only been reasoned about; run it.

**5. Be deliberate about security.** Generated code can look entirely plausible
and still contain SQL injection, missing input validation, or misuse of TYPO3's
security APIs. Use `QueryBuilder` rather than string concatenation, TYPO3
request handling rather than `$_GET`/`$_POST`, and never hardcode credentials.
Security-relevant code has to be understood, not just accepted.

**6. Write in the maintainer's voice.** Commits, pull requests, issues and
documentation are the maintainer's own words. The `AI-assisted:` trailer is the
place where tool involvement is recorded — not the prose.

→ [Commit messages: attribution and AI disclosure](docs/workflow/commit-messages.md#attribution-and-ai-disclosure) ·
[Appendix A of the draft policy](https://github.com/TYPO3-Documentation/Policy/pull/47)

## Working files belong in `.agent/`

Never write scratch files, notes, plans, downloads or drafts into the repository
tree, and **never into `/tmp/`** — it is a ramdisk, it is lost on restart and it
costs RAM. Use `.agent/` in the repository root, which is git-ignored:

| Path              | For                                                                                          |
|-------------------|----------------------------------------------------------------------------------------------|
| `.agent/plans/`   | Plans and step tracking, **one subdirectory per plan** so all files of a plan stay together. |
| `.agent/reports/` | Analyses, investigations and hand-off documents.                                             |
| `.agent/tmp/`     | Everything else: scripts, downloads, scratch checkouts, tool output.                         |

Nothing below `.agent/` is ever committed.

## Read this before changing code

| Topic                                               | Page                                                                    |
|-----------------------------------------------------|-------------------------------------------------------------------------|
| Development environment, container based tooling    | [Environment](docs/development/environment.md)                          |
| **The development instances, and `theme`**          | [Development instances](docs/development/instances.md)                  |
| **Dual core setup (v12 + v13) — read this first**   | [Dual core setup](docs/development/dual-core-setup.md)                  |
| The gates and what they check                       | [Quality gates](docs/development/quality-gates.md)                      |
| Version differences split classes, not conditionals | [Core version aware code](docs/architecture/core-version-aware-code.md) |
| Symfony DI attributes, stateless services           | [Dependency injection](docs/architecture/dependency-injection.md)       |
| `readonly` properties, injected abstracts, DTOs     | [Class design](docs/architecture/class-design.md)                       |
| Both test suites and their strictness               | [Testing](docs/testing/Index.md)                                        |
| Design tokens, and the light/dark contract          | [Design tokens](DESIGN.md)                                              |
| Commit message conventions                          | [Commit messages](docs/workflow/commit-messages.md)                     |

## The rules that are not negotiable

These are stated in the documentation as well. They are repeated here because a
violation of any of them is a rejected change, not a review comment.

1. **Version differences split classes, they never add conditionals.** Shared
   code in `Classes/`, one implementation per supported core version in its own
   `Core<major>/` directory — `Core12/` and `Core13/` — and only the directory
   matching the running core registered in the container. The worked example is
   `Classes/Seeding/FileImporterInterface` with its two implementations.
   → [Core version aware code](docs/architecture/core-version-aware-code.md)

   The exception is **configuration** — TCA, TypoScript, page TSconfig,
   `ext_localconf.php`, `ext_tables.sql` — which TYPO3 loads from a fixed path
   and which therefore cannot be split. Apply the difference to the finished
   array or wrap it in one condition, add a `@todo` naming the condition under
   which it goes away, and name the changelog issue. Look for a spelling valid on
   both versions first: `Classes/Compatibility/ContentTypeRegistration` replaces
   a v13-only core method with API both versions have, and needs no switch at all.
   → [Configuration is the exception](docs/architecture/core-version-aware-code.md#configuration-is-the-exception)

2. **Services are wired with Symfony DI attributes on the class**, never in
   `Services.yaml` and never with service definitions in
   `Configuration/Services.php`. Services are **stateless** — new ones must be,
   existing ones must not gain state — and private unless something genuinely
   has to fetch them from the container.
   → [Dependency injection](docs/architecture/dependency-injection.md#rules)

3. **Classes are `final`, and every property they carry is `readonly`** unless a
   framework constraint prevents it. Class level `readonly` is **not** used on
   this branch: it supports PHP 8.1 for TYPO3 v12, and `readonly` on a class is
   PHP 8.2. A class backported from `main` as `final readonly class` becomes
   `final class` here, with `readonly` written on each property — dropping the
   class keyword without adding the property ones makes the state mutable, and
   nothing reports it.
   Abstract classes never use constructor injection; they use `#[Required]`
   `inject*()` methods so the constructor stays free for extending classes.
   → [Class design](docs/architecture/class-design.md#prefer-final-with-readonly-properties) ·
   [Backports from `main`](docs/architecture/class-design.md#backports-from-main-readonly-moves-off-the-class)

4. **Models, entities, value objects and DTOs are data, not services.** They are
   never registered in the container and carry no dependencies, and they
   **always** carry `#[Exclude]` — Extbase models included. Omitting it breaks
   nothing until someone type hints the class, and the error then points at the
   data object rather than at the code that caused it.
   → [Data objects are not services](docs/architecture/class-design.md#data-objects-are-not-services)

5. **Test methods are not prefixed with `test`.** Use `#[Test]` and a name that
   describes the expected behaviour. Enforced by a gate.
   → [Test method naming](docs/development/quality-gates.md#test-method-naming)

   `Tests/Unit/VersionCompatTest` and `Tests/Functional/ExtensionLoadedTest`
   are **never removed**, however trivial their assertions look — they prove
   that a suite ran against the core version it was asked for, and that a full
   TYPO3 instance with this extension boots at all. Both say so in their
   docblock.
   → [The two tests that must never be dropped](docs/testing/Index.md#the-two-tests-that-must-never-be-dropped)

6. **Functional tests extend `AbstractFunctionalTestCase`**, never the testing
   framework `FunctionalTestCase` directly.
   → [Site based tests](docs/testing/site-based-tests.md#no-test-extends-the-framework-test-case-directly)

7. **Commit messages follow the TYPO3 Core conventions** — `[TAG] Short
   imperative summary` of ~52 characters, blank line, body wrapped at ~72
   explaining *what* and *why*. Issue references are verified, never assumed.
   → [Commit messages](docs/workflow/commit-messages.md)

## Verify against the TYPO3 changelogs

TYPO3 ships its changelogs **with the core package**:

```
.Build/vendor/typo3/cms-core/Documentation/Changelog/
```

They are the authoritative record of what changed between TYPO3 versions, and
they are on disk — there is no reason to work from memory.

- Before writing version aware code, **search them** for the API in question.
- When a change reacts to a core change, **cite the entry** in the commit message
  body, by issue number and title.
- **Read the entry**, do not infer it from its filename. A "Breaking" entry often
  documents the replacement API in the same file, and the file often says
  the option is still required on the older version.

```bash
grep -rl "DuplicationBehavior" .Build/vendor/typo3/cms-core/Documentation/Changelog/13*/
```

The rendered version is at
<https://docs.typo3.org/c/typo3/cms-core/main/en-us/Index.html>.
→ [Referencing TYPO3 behaviour changes](docs/workflow/commit-messages.md#referencing-typo3-behaviour-changes)

> [!IMPORTANT]
> The changelogs on disk reach only as far as the installed core version: with
> TYPO3 v12 installed the newest directory is `12.4.x` and there is no `13.0/`
> to read. A package does ship the changelogs of all **earlier** versions, so
> installing the **highest** supported version — v13 — puts both the v12 and the
> v13 changelogs on disk at once, everything from `7.0/` to `13.4.x/`, and saves
> switching back and forth to look something up. Anything newer than the
> installed version is not on disk and cannot be verified from this checkout;
> say so rather than asserting it.
>
> Reading a changelog is not running a gate. Look things up, then
> `composerUpdate` back to the version you are working on before running
> anything — see
> [the dual core hint](#quality-gates-and-the-dual-core-hint) below.

## Quality gates, and the dual core hint

Every gate runs in a container through
[`Build/Scripts/runTests.sh`](Build/Scripts/runTests.sh). Nothing needs to be
installed on the host except **podman** (preferred) or docker.

> [!CAUTION]
> **`-t` selects the core version but installs nothing.** Only `composerUpdate`
> installs a dependency set. Running a gate with a `-t` value other than the
> installed set produces results that look real and are worthless — tests
> failing on the wrong core version, PHPStan reporting API that does exist,
> changelogs missing from disk.
>
> **Always `composerUpdate` for a core version before running anything for it,
> and never interleave `-t` values.** Do one version completely, then switch.

```bash
# TYPO3 v12 — install first, then run everything for v12.
Build/Scripts/runTests.sh -t 12 -s composerUpdate
Build/Scripts/runTests.sh -t 12 -s cgl -n
Build/Scripts/runTests.sh -t 12 -s phpstan
Build/Scripts/runTests.sh -t 12 -s lintPhp
Build/Scripts/runTests.sh -t 12 -s unit
Build/Scripts/runTests.sh -t 12 -s unitRandom
Build/Scripts/runTests.sh -t 12 -s functional -d sqlite
Build/Scripts/runTests.sh -t 12 -s composerValidate
Build/Scripts/runTests.sh -t 12 -s checkBom
Build/Scripts/runTests.sh -t 12 -s checkExceptionCodes
Build/Scripts/runTests.sh -t 12 -s checkMarkdownTables
Build/Scripts/runTests.sh -t 12 -s checkTestMethodsPrefix
Build/Scripts/runTests.sh -t 12 -s checkCssBuild

# Then the same for TYPO3 v13, starting with composerUpdate again.
Build/Scripts/runTests.sh -t 13 -s composerUpdate
# ...
```

`-t` defaults to **12**, the lowest supported version, and `-p` to **8.2**, the
lowest PHP version both cores accept. `-p 8.1` is TYPO3 v12 only —
`typo3/cms-core` 13.4 requires PHP `^8.2`, so `-t 13 -p 8.1` fails to resolve in
`composerUpdate`. Run `-t 12 -p 8.1 -s lintPhp` after adding or moving a class:
it is the only run that catches PHP 8.2-only syntax such as a `readonly` class
or a constant in a trait.

Further:

- `-s cgl` fixes in place, `-s cgl -n` only checks, as CI does. Run the fixer
  after any change that reorders imports.
- `-s checkMarkdownTables` also fixes, but the other way round: it checks by
  default and pads the tables only with `-- --fix` — see
  [documentation conventions](docs/Index.md#conventions-of-this-documentation).
- `-s renderDocumentation` when anything below `Documentation/` changed. Its
  sibling `-s watchDocumentation` serves the rendered documentation and blocks
  until ctrl-c — a writing aid for a human, never something to run as a gate.
- `-s buildCss` after any change below `Resources/Private/Scss/`, and commit the
  result: the compiled `Resources/Public/Css/theme.css` is committed, because
  neither the composer dist archive nor the TER artifact runs a build.
  `-s checkCssBuild` is the gate that holds it to that, and `-s watchCss` is the
  writing aid that blocks until ctrl-c.
  → [Frontend assets](docs/development/frontend-assets.md)
- `-s functional -d mariadb -i 10.6` (also `mysql`, `postgres`) when a change
  touches queries, schema or TCA. SQLite alone is not enough there.
- Arguments for PHPUnit go after `--`:
  `-s functional -d sqlite -- --filter SomeTest`.
- A **growing PHPStan baseline is a defect.** Fix the finding.
- The development instances, one per supported core version —
  `instance-core-12/` and `instance-core-13/` — are **not** driven by
  `runTests.sh`. They are installed
  with `ddev composer` or from the host, and the two ways must not be mixed —
  the resulting `vendor/` differs and does not travel. `theme` at the repository root is a symlink to
  that root; never follow it when walking the tree, and never add it to a path a
  tool descends into.
  → [Development instances](docs/development/instances.md)
- The wrapper notices that it has no terminal and drops the interactive
  container flags by itself, so calling it from a tool, a pipe or a hook needs
  no special handling — it is the same command a human types.
- `-s setVersion` is release tooling, not a gate: it rewrites the version in
  every file carrying one. Run it only when asked to, and with `--dry-run`
  first.
- The composer download and PHPStan result caches live in `.cache/` at the
  repository root, **not** under `.Build/`. `composerUpdate` starts with
  `rm -rf .Build`, so a cache kept there is deleted before it is ever read —
  it would still be written on the way out, so nothing looks broken while every
  install re-downloads the full dependency set and the CI cache step is a silent
  no-op. **Do not move them back.**
- **Locally**, `composerUpdate` deletes `.cache/` before installing, and in CI
  it does not. Both branches are guarded by `IS_CORE_CI`. The local clear is a
  **precaution**: switching core versions also switches the major version of
  `typo3/class-alias-loader`, and a working copy accumulates months of such
  switches, while a CI job starts from an empty checkout with nothing to collide
  with. Do not remove it to save a download.

A shell script below `Build/Scripts/` that a gate or a `-s` suite executes runs
**inside the container images**, and those ship **no `jq`**. `setVersion.sh`
therefore reads and writes `composer.json` with `php`, decoding into objects so
an empty JSON object survives as `{}`, and encoding with
`JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` plus a
trailing newline — which is byte identical to `jq --indent 4`. Do not introduce
a `jq` dependency in a new script; mirror those helpers instead.

→ [Quality gates](docs/development/quality-gates.md) ·
[Dual core setup](docs/development/dual-core-setup.md)

## The test suites are deliberately hard breaking

Notices, warnings, deprecations, PHPUnit deprecations, risky and incomplete
tests, a test without an assertion, output written during a test and an empty
test suite **all fail the run**.

Never make a suite pass by silencing a diagnostic, loosening the PHPUnit
configuration, adding `@` suppression or marking a test skipped or incomplete.
Fix the cause. If a deprecation is genuinely a core version difference, handle it
as a version difference — see rule 1.
→ [Strictness policy](docs/testing/phpunit-configuration.md#strictness-policy)

## Verify, do not assume

The most common failure mode of an agent in this repository is a confident,
plausible, wrong statement. Two habits prevent it:

- **Read the source rather than recalling it.** The installed core, the testing
  framework and every dependency are on disk below `.Build/vendor/`. When
  behaviour matters, open the file.
- **Prove a new test can fail.** A test that passes may be passing for the wrong
  reason. Break the thing it covers on purpose, watch the test go red, restore.
  This is cheap and it is the difference between a test and a decoration.

When writing a comment or a documentation sentence that explains *why* something
is configured a way, that explanation must be something you established — not
something that sounds right.

## Definition of done

Before reporting a change as complete:

- [ ] Full gate matrix green for **both** core versions, each after its own
      `composerUpdate`.
- [ ] New behaviour has a test, and the test was shown to fail without the
      change.
- [ ] [`docs/`](docs/Index.md) updated in the same change — new concepts get a
      page or a section, and it is linked from the section index.
- [ ] [`Documentation/`](Documentation) updated when the change is user or
      integrator facing, with a `Documentation/Changelog/<version>/` entry.
- [ ] `README.md` and `CONTRIBUTING.md` still only summarize and link — no
      duplicated content.
- [ ] Commit message follows the TYPO3 Core conventions, carries the
      `AI-assisted:` trailer when the work falls under
      [disclosure](#ai-assisted-contributions), and credits no model as an
      author.
- [ ] Anything left out is stated explicitly in the report.
