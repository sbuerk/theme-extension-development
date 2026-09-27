# Quality gates

The same gates run locally and in the GitHub Actions workflows for TYPO3 v13
and v14. Every one of them must pass for both core versions, each after the
matching `composerUpdate` — see [Dual core setup](dual-core-setup.md).

## The gates

```bash
# Coding guidelines: fix in place ...
Build/Scripts/runTests.sh -s cgl

# ... or check only, without changing files, as CI does.
Build/Scripts/runTests.sh -s cgl -n

# Static analysis (PHPStan, level 8).
Build/Scripts/runTests.sh -s phpstan

# PHP linting.
Build/Scripts/runTests.sh -s lintPhp

# Validate the root composer.json.
Build/Scripts/runTests.sh -s composerValidate

# Ensure UTF-8 files do not contain a BOM.
Build/Scripts/runTests.sh -s checkBom

# Find duplicate or missing exception codes.
Build/Scripts/runTests.sh -s checkExceptionCodes

# Ensure markdown tables are formatted ("-- --fix" formats them).
Build/Scripts/runTests.sh -s checkMarkdownTables

# Ensure test methods do not start with "test".
Build/Scripts/runTests.sh -s checkTestMethodsPrefix

# Ensure the committed CSS matches its SCSS sources.
Build/Scripts/runTests.sh -s checkCssBuild

# Ensure the committed icons equal the pinned Font Awesome Free package.
Build/Scripts/runTests.sh -s checkIconsBuild
```

| Gate                     | Configuration                                                                                        | Core version dependent |
|--------------------------|------------------------------------------------------------------------------------------------------|------------------------|
| `cgl`                    | [`Build/php-cs-fixer/config.php`](../../Build/php-cs-fixer/config.php)                               | no                     |
| `phpstan`                | `Build/phpstan/Core13/`, `Build/phpstan/Core14/`                                                     | **yes**                |
| `lintPhp`                | —                                                                                                    | no                     |
| `composerValidate`       | `composer.json`                                                                                      | no                     |
| `checkBom`               | [`Build/Scripts/checkUtf8Bom.sh`](../../Build/Scripts/checkUtf8Bom.sh)                               | no                     |
| `checkExceptionCodes`    | [`Build/Scripts/duplicateExceptionCodeCheck.sh`](../../Build/Scripts/duplicateExceptionCodeCheck.sh) | no                     |
| `checkMarkdownTables`    | [`Build/Scripts/checkMarkdownTables.php`](../../Build/Scripts/checkMarkdownTables.php)               | no                     |
| `checkTestMethodsPrefix` | [`Build/Scripts/testMethodPrefixChecker.php`](../../Build/Scripts/testMethodPrefixChecker.php)       | no                     |
| `checkCssBuild`          | [`package.json`](../../package.json), see [Frontend assets](frontend-assets.md)                      | no                     |
| `checkIconsBuild`        | [`package.json`](../../package.json), see [Icons](icons.md)                                          | no                     |

## PHPStan

PHPStan runs at **level 8** and is configured **per core version**. Each
configuration analyses only its own core version aware sources —
`Build/phpstan/Core13/phpstan.neon` lists `Classes`, `Configuration`, `Core13`
and `Tests`, and excludes `Tests/*/Core14/*`. Analysing the sources of the other
core version would report false positives about API that does not exist there.

Both PHPStan suites pass arguments after `--` through to the tool and do not
force an output format, so `-- --error-format=json` or `-- --no-progress` is the
caller's choice.

When PHPStan reports pre-existing findings that cannot be fixed right away, the
baseline can be regenerated per core version — but **prefer fixing the finding**:

```bash
Build/Scripts/runTests.sh -t 13 -s phpstanGenerateBaseline
Build/Scripts/runTests.sh -t 14 -s phpstanGenerateBaseline
```

A growing baseline is a defect, not a configuration. Regenerating it to make a
new finding disappear hides the very problem the gate exists for. The two
`@phpstan-ignore` annotations this repository does accept are documented, scoped
and justified in
[Class design](../architecture/class-design.md#the-two-phpstan-ignores-on-injected-readonly-properties);
nothing else may be silenced.

## Byte order marks

`checkBom` compares the first three bytes of every file against `EF BB BF`
instead of grepping the output of `file`. That wording is not stable: `file`
5.47, which the PHP images ship, prints `Unicode text, UTF-8 (with BOM) text`,
and the grep for the older `UTF-8 Unicode (with BOM)` passed on every file with
a BOM. The compiled `Resources/Public/Css/theme.css` is in scope, see
[Frontend assets](frontend-assets.md#no-byte-order-mark).

## Exception codes

TYPO3 exception codes are unix timestamps taken at the moment the exception is
written, and must be unique across the code base. `checkExceptionCodes` finds
both duplicates and exceptions thrown without a code.

## Test method naming

Test methods must **not** be prefixed with `test`; use the PHPUnit `#[Test]`
attribute and a descriptive method name instead. `checkTestMethodsPrefix`
enforces this:

```php
#[Test]
public function getExtensionKeyReturnsExtensionKey(): void
{
    // ...
}
```

## Markdown table formatting

`checkMarkdownTables` verifies that every table in `./*.md` and `docs/` is
formatted — cells padded so the pipes line up, separator row as wide as its
column.

It is the second gate that can fix what it finds, and the two are inverted
towards each other: `cgl` **fixes** by default and only checks with `-n`, while
`checkMarkdownTables` **checks** by default and only fixes when asked:

```bash
Build/Scripts/runTests.sh -s checkMarkdownTables
Build/Scripts/runTests.sh -s checkMarkdownTables -- --fix
```

The gate exists because the defect is invisible: an unformatted table renders
exactly like a formatted one, so it survives review and only shows up as noise
in the diff of the *next* change to that table. Alignment markers (`:---`,
`---:`, `:---:`) are preserved, and tables inside fenced code blocks are left
alone so a page can show an unformatted one as an example.

Git-ignored files are skipped, and so are the symlinked agent instruction files,
which are checked through their target. What is git-ignored is decided by the
`.gitignore` of the checkout the script belongs to, applied by the script
itself. The Finder option `ignoreVCSIgnored()` it used before takes as its root
the nearest directory upwards with a `.git` *directory*, and a worktree has a
`.git` *file*: run on the host from a worktree nested below an ignored path of
another checkout, it applied that checkout's `.gitignore`, found every file
ignored and passed with "Checked 0 markdown files". A run that finds no file in
`./*.md` or in `docs/` now fails, as the root always has `README.md` and
`docs/` always has `Index.md`.
→ [Documentation conventions](../Index.md#conventions-of-this-documentation)

## Functional tests in parallel chunks

The functional suite is the slowest gate, and `-j <number>` runs it in that many
chunks at once, each with its own containers:

```bash
Build/Scripts/runTests.sh -s functional -d sqlite -j 4

# Locally: as many chunks as the machine carries and the durations make worth it.
Build/Scripts/runTests.sh -s functional -d sqlite -j auto
```

A chunked run carries a check of its own: it fails unless the chunks together
executed exactly as many tests as PHPUnit listed for the run, so a class lost on
the way through the split cannot pass unnoticed. A class is never split, which
makes the slowest class the floor of every chunked run.
→ [Functional tests in parallel chunks](environment.md#functional-tests-in-parallel-chunks)

## Continuous integration

[`.github/workflows/ci.yml`](../../.github/workflows/ci.yml) runs everything for
a pull request, with the core version as a **matrix dimension** rather than one
workflow per core version. Every step calls `Build/Scripts/runTests.sh`, so a
gate behaves identically in CI and on a developer machine.

No job waits for another — every job starts with the run:

```
quality, phpstan, lint, unit, assets, documentation   the gates, ~1 minute each
visual                                                screenshots and axe
acceptance                                            a built instance
functional (MySQL, MariaDB, Postgres)                 16 jobs
functional (SQLite)
```

| Job                 | Matrix                                   | Runs                                                                                      |
|---------------------|------------------------------------------|-------------------------------------------------------------------------------------------|
| `quality`           | lowest PHP, one core version             | The gates that inspect source files                                                       |
| `phpstan`           | lowest PHP × both core versions          | The one gate configured per core version                                                  |
| `lint`              | all PHP versions × both core versions    | `lintPhp`                                                                                 |
| `unit`              | edge PHP versions × both core versions   | `unit`, `unitRandom`                                                                      |
| `functional-sqlite` | edge PHP versions × both core versions   | `functional -d sqlite -j 4`, uploads the JUnit logs                                       |
| `functional-dbms`   | edge PHP × both cores × 4 DBMS — 16 jobs | `functional -j 4` against each database, uploads the JUnit logs                           |
| `acceptance`        | lowest PHP × both core versions          | `acceptance`: a built instance, in Chromium and in Firefox; uploads the report on failure |
| `visual`            | lowest PHP, one core version             | `visual`: screenshots and axe of the styleguide partials; uploads the diffs on failure    |
| `assets`            | —                                        | `checkCssBuild` and `checkIconsBuild`: the committed build output equals the build        |
| `documentation`     | —                                        | `renderDocumentation`, uploads the artifact                                               |

The decisions worth knowing:

- **Nothing is staged.** The jobs used to wait for each other, cheapest first:
  the gates, then `unit`, then the SQLite jobs, then the sixteen database jobs.
  A defect a gate finds was then reported without starting the expensive jobs,
  but every run that went on to pass — nearly every pull request run — waited
  for each stage in turn: in the run of pull request #91 the first database job
  started 24 minutes after the run did, of 101 minutes in all. Starting
  everything at once costs runner time when a gate fails, which a public
  repository does not pay for, and delays no report, because the gates still
  finish within about a minute. A defect that is not DBMS specific is now
  reported by the twenty functional jobs rather than four.
- **No aggregating job.** Nothing requires a named check on `main` — there is no
  branch protection and no ruleset — and every job reports on the pull request
  by itself, so an `all checks` job that needs every other one would add a job
  and tell nobody anything new. It is the job to add once a check becomes
  required: require that one, not the matrix job names, which change whenever
  the matrix does.
- **The job order in `ci.yml` is deliberate.** The account runs at most twenty
  jobs at a time (GitHub Free) and the workflow has forty, so half of them start
  queued. The quick jobs are listed first, then the long ones longest first —
  the database jobs before the shorter SQLite jobs, which absorb a late start.
  GitHub does not document in which order the queued jobs of a run get a runner;
  the order helps if it follows the file, and costs nothing if it does not.
- **The version independent gates run once, not per core version and PHP
  version.** They inspect source files rather than the installed core, so
  repeating them tests the same files again. Only `phpstan` is genuinely per
  core version. `visual` is one job for the same reason: the partials render
  identically with the Fluid of both dependency sets, and the stylesheet and
  the browser image are the same — see [Visual tests](../testing/visual-tests.md#in-ci).

### Job timeouts

Every job carries a `timeout-minutes`, so a job that stalls fails instead of
holding a runner for GitHub's default of 360 minutes. The values come from the
slowest successful job of the fifteen newest pull request runs of `ci.yml`
against `main` started before 2026-09-26 12:00 UTC (#68 to #91), all attempts,
a job carried unchanged into a re-run counted once, with room for a slow image
pull or a busy runner:

| Job                               | Slowest seen, minutes | Timeout, minutes |
|-----------------------------------|-----------------------|------------------|
| `quality`, `phpstan`, `lint`      | 1.3                   | 10               |
| `unit`, `assets`, `documentation` | 0.7                   | 10               |
| `visual`                          | 3.4                   | 15               |
| `acceptance`                      | 8.7                   | 20               |
| `functional-sqlite`               | 23.4                  | 35               |
| `functional-dbms`                 | 54.7                  | 65               |

For the database jobs, 54.7 minutes is the slowest of 227 out of the 240 that
succeeded; the other thirteen took 58.8 to 158 minutes, and three more were
cancelled after 74.7 to 84.4 minutes. That tail is what the timeout cuts off:
it would have ended ten of the 240. The functional values were measured with the
suite unchunked, and are retuned after the first CI run of the chunked suite.

The functional test step has a `timeout-minutes` of its own, five minutes below
that of its job — 30 for SQLite, 60 for the databases. A step that times out
fails like any other failing step, so the upload of the logs after it still
runs; a job that times out is cancelled as a whole, and the upload, which is
guarded by `!cancelled()`, would not. The workflow syntax reference leaves the
result of a step timeout open; the runner sets it to failed
([`StepsRunner.cs`](https://github.com/actions/runner/blob/15231bede4aacecb6686f4b7de25c62398607993/src/Runner.Worker/StepsRunner.cs#L322-L330)),
and `cancelled()` is true only for a job whose status is cancelled
([`CancelledFunction.cs`](https://github.com/actions/runner/blob/15231bede4aacecb6686f4b7de25c62398607993/src/Runner.Worker/Expressions/CancelledFunction.cs#L27-L28)).
On a step timeout the runner signals the shell of the step alone — SIGINT,
SIGTERM 7.5 seconds later, then a kill
([`ProcessInvoker.cs`](https://github.com/actions/runner/blob/15231bede4aacecb6686f4b7de25c62398607993/src/Runner.Sdk/ProcessInvoker.cs#L443-L465)) —
and not the `runTests.sh` it started, so the chunks may still run while their
logs are uploaded: an event log then ends where the chunk was at that moment.

### Functional jobs in four chunks

Both functional jobs run the suite with `runTests.sh -j 4`: four chunks in
parallel. With the showcase seed imported once per class, no class outweighs the
rest — the heaviest takes 18 to 28 % of the suite, 84 of 478 s on SQLite and
297 of 1055 s on MySQL 8.0 — so a fourth chunk still shortens the run. Measured
locally on a shared machine, one pair of runs each: SQLite took 159 s with
`-j 3` and 126 s with `-j 4`, MySQL 8.0 322 s and 269 s. Four is also the number
of vCPU of a hosted runner, where neither count was measured yet.
→ [The floor, and the recorded durations](environment.md#the-floor-and-the-recorded-durations)

Each chunk writes a JUnit log and a PHPUnit event log below
`.Build/functional-runs/<suffix>/`, and the job uploads both as the artifact
`functional-junit-<dbms>[-<version>]-v<core>-php<version>`, for seven days and
also when the tests failed or their step timed out. The JUnit logs are what the
committed test durations that balance the chunks are refreshed from, and in a
failed run the chunk without a JUnit log is the one that did not finish; its
event log, appended to event by event, names the test it was in.

For the time being the functional jobs also record the CPU model and hypervisor
of their runner, `host-lscpu.txt`, and `vmstat` every ten seconds,
`host-vmstat.txt`, uploaded in the same artifact. On some hosts in the
`centralus` and `westus3` regions the MySQL and MariaDB jobs ran two to five
times slower than their siblings from the first test on, while SQLite and
PostgreSQL did not; the steal time `st` tells a starved CPU from slow InnoDB
DDL. The step goes once that is answered.

### Why CI passes `-b docker`

Every `runTests.sh` invocation in the workflows passes `-b docker`. The script
itself prefers **podman** and only falls back to docker, and that default is
right and stays: podman-only machines are exactly what it is built for.
GitHub hosted runners happen to ship both, and that is the single place this
repository meets a broken combination — their podman/crun pairing has been
observed to abort the *first* container start of a job with

```
Error: OCI runtime error: crun: unknown version specified
```

and exit code 126. It is intermittent, hits any job, and is caused by neither
the runner image nor the testing image changing; a rerun onto another host
clears it. Selecting docker in the workflow avoids crun entirely and leaves the
script default and every local run untouched. **Drop the flag once GitHub stops
producing the mismatch** — it is a workaround for their fleet, not a property of
this repository.

Picking docker there has a consequence worth knowing, and it is why the SQLite
functional mount carries `mode=1777` in addition to `uid`/`gid`: docker runs the
container as `--user $(id -u)` with group 0, and on a runner the tmpfs comes up
`root:root` mode `0755`, so neither the owner nor the group bits apply and every
test fails with `unable to open database file`. Rootless podman is root inside
its user namespace and never saw it.

### The runner image

Every job of every workflow runs on `ubuntu-26.04`, named rather than
`ubuntu-latest`. That label moves from Ubuntu 24.04 to 26.04 in a rollout from
October 19 to November 19, 2026, job by job
([actions/runner-images#14748](https://github.com/actions/runner-images/issues/14748));
a named version moves every job at once, and when this repository decides to.
Change the label in `ci.yml`, `nightly.yml`, `pr-comment.yml` and `publish.yml`
together.

The two functional jobs had been pinned to `ubuntu-22.04` since the initial
commit, without a recorded reason, and moved with the rest. The image ships
Docker 29.4 and Podman 5.7.0; `-b docker` stays, for the reason above.
`publish.yml` runs `jq` on the runner itself, which the image ships as well, and
installs PHP 8.2 with `shivammathur/setup-php`, which lists Ubuntu 26.04 as
supported. It runs for a tag only, so the next release is its first run there.

### The composer cache

The composer **download cache** is shared per PHP and core version, so the
repeated `composerUpdate` resolves against a warm cache instead of downloading
the dependency set again in every job. The `acceptance` job keeps its own key,
because it installs the development instance rather than the root dependency
set, and falls back to the key of its PHP and core version.

It lives in `.cache/` at the repository root, and that location is load-bearing:
`runTests.sh -s composerUpdate` starts with `rm -rf .Build`, so a cache kept
under `.Build/` would be deleted before composer ever reads it. The job would
still save it on the way out, so the cache step looks healthy in the run log
while never once being used — locally the same applies, and every dependency
install re-downloads the whole set. The phpstan result cache sits next to it for
the same reason. **Do not move either back under `.Build/`.**

What is deliberately *not* symmetric is who keeps it: `composerUpdate` deletes
`.cache/` **locally** and keeps it in CI, guarded by the same `IS_CORE_CI` the
rest of the script uses. The two contexts differ in what the cache can collide
with. A CI job starts from an empty checkout, installs once and ends; a working
copy switches between the core versions for months, and that switch changes the
major version of `typo3/class-alias-loader` — v13 resolves `^1.2`, v14 resolves
`^2.0.1`.

The local clear is a **precaution rather than a fix for a reproduced defect**.
Switching back and forth four times does not fail today; what it buys is that an
install never resolves against a cache belonging to the other major, which is a
class of failure that costs far more to recognize than the one download it
costs to avoid — of a dependency set that was going to be replaced anyway.

CI is the safety net, not the first run — the gates are cheap enough to run
locally before pushing.

### The nightly run

[`.github/workflows/nightly.yml`](../../.github/workflows/nightly.yml) runs the
complete `ci.yml` every night at 02:37 UTC, for `main` and for `1`, and can be
started by hand from the Actions tab.

- **Why at all.** `ci.yml` runs for pull requests and by hand, so a merged state
  is never checked again. And there is no `composer.lock`: a TYPO3 patch release
  or any other dependency release changes what a run installs without a commit
  here. The nightly run is where that shows up first, rather than in the next
  unrelated pull request.
- **Why it dispatches `ci.yml` instead of scheduling it.** A `schedule` only fires
  on the default branch, with the workflow file of the default branch, and
  branch `1` has a `ci.yml` of its own. `workflow_dispatch` runs `ci.yml` as it
  is on the branch it is given; both branches carry that trigger. A dispatch
  made with the job's own token does start a run — `workflow_dispatch` is one of
  the two events exempt from the rule that events caused by `GITHUB_TOKEN`
  start none.
- **Why it waits.** A dispatched run belongs to `github-actions`, and its failure
  notifies nobody. Each nightly job follows the run it started with
  `gh run watch --exit-status` and fails unless that run succeeded. A failed
  scheduled run is reported to whoever last changed its `cron` line.
- **Concurrency.** The dispatched run is grouped by branch,
  `CI-refs/heads/<branch>`, and a pull request run by its number, so the two
  never cancel each other, and the runs for the two branches do not either. A
  `ci.yml` run started by hand on the same branch meanwhile does cancel the
  nightly one, and the nightly job then fails. `nightly.yml` has a group of its
  own without `cancel-in-progress`, which holds one running and at most one
  pending run: a second nightly run waits rather than cancelling the first
  one's CI runs through its dispatch, and a third replaces the second while that
  is still pending
  ([Concurrency](https://docs.github.com/en/actions/concepts/workflows-and-actions/concurrency)).
- **Finding the run it started.** The dispatch is asked to return the id of the
  run it creates. When it answers without one, the job looks for the dispatched
  `ci.yml` run on the branch created since a minute before the dispatch, and
  fails when there is more than one. A run dispatched by hand on the same branch
  in that minute, before the nightly one shows up, is taken for it when it is
  the only one listed.
- **Cost.** Each nightly job holds a runner while it waits, two of the twenty
  the account runs at a time; the two dispatched runs together queue eighty
  jobs. Its timeout is 180 minutes for that reason.
- **Permissions.** `actions: write` to dispatch and follow the run, `contents:
  read`, nothing else.

GitHub disables a schedule in a public repository after 60 days without
repository activity; it is enabled again from the Actions tab. `nightly.yml` only
takes effect on the default branch.

### Commenting on a pull request from a fork

[`.github/workflows/pr-comment.yml`](../../.github/workflows/pr-comment.yml)
posts the link to the rendered documentation as a single comment, updated in
place on every push.

It is a **separate workflow on the `workflow_run` event**, and it has to be. A
pull request from a fork gets a read-only `GITHUB_TOKEN` and no secrets, so a
comment step inside `ci.yml` would work for branches in this repository and
silently fail for exactly the external contributors it is meant to serve.
`workflow_run` fires when `ci.yml` finishes, runs in the context of the default
branch of this repository rather than the fork, and its token may write even
though the token of the run that triggered it could not. No pull request code is
checked out or executed there, which is what makes the write permission safe —
and it is why `pull_request_target` is *not* used.

Two consequences:

1. The file only takes effect once it is **on the default branch**. Changing it
   in a pull request does not change the behaviour of that pull request.
2. `github.event.workflow_run.pull_requests` is empty for a fork, so the pull
   request number travels in the `pull-request-context` artifact written by
   `ci.yml`.

## See also

- [Development environment](environment.md)
- [Dual core setup](dual-core-setup.md)
- [Testing](../testing/Index.md)
- [Pull requests](../workflow/pull-requests.md)
