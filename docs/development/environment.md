# Development environment

All tests and quality tools run in containers through the
[`Build/Scripts/runTests.sh`](../../Build/Scripts/runTests.sh) wrapper. The only
requirement on the host is a container runtime — **podman** (preferred) or
**docker**. The wrapper pulls the required TYPO3 testing images on first use;
neither PHP nor Composer needs to be installed on the host.

Dependencies are installed into the git-ignored `.Build/` directory. The
wrapper installs them for a specific TYPO3 core and PHP version:

```bash
# Install dependencies for TYPO3 v12 on PHP 8.2 (the defaults).
Build/Scripts/runTests.sh -t 12 -p 8.2 -s composerUpdate
```

`-t` takes `12` or `13`, and re-running `composerUpdate` with a different one
replaces the whole set in `.Build/`. `-p 8.1` is only usable together with
`-t 12`: `typo3/cms-core` 13.4 requires PHP `^8.2`, so the combination cannot
resolve.

> [!IMPORTANT]
> The installed dependency set must match the core version a gate is run for.
> See [Dual core setup](dual-core-setup.md) — this is the single most common
> source of false positives in this repository.

Run `Build/Scripts/runTests.sh -h` to see all suites and options.

The wrapper detects whether it is attached to a terminal. Interactively it runs
the containers with `-it`; from a pipe, a wrapper script, an IDE run
configuration or a git hook it drops those flags — podman would only warn, but
docker fails outright, and redirected output would carry TTY control characters.
`--init` is kept either way, so ctrl-c still reaches the process in the
container.

It picks **podman** whenever it is installed and falls back to docker; `-b`
overrides that. There is no reason to pass it locally — the workflows do, and
[why they do](quality-gates.md#why-ci-passes--b-docker) is a property of GitHub
hosted runners, not of this repository.

Before a suite starts its first container, the wrapper makes sure every image
the suite needs is present. An image that is present is never pulled again, so
once a suite has run, it runs without a registry; `-u` is what updates the
TYPO3 testing images. A missing image is pulled with up to three attempts, 10
and 20 seconds apart, and when all three fail the run ends before starting
anything, naming the image. A pull left to `docker run` is tried once, and a
registry that did not answer in time — `context deadline exceeded` for the
Playwright image, exit code 125 — failed an `acceptance` job in CI before a
single test ran, although a rerun of the job passed.

## Frequently used options

| Option         | Meaning                                                                    |
|----------------|----------------------------------------------------------------------------|
| `-s <suite>`   | Suite to run (`unit`, `functional`, `cgl`, `phpstan`, …).                  |
| `-t <12\|13>`  | TYPO3 core major version to run against. Default `12`, the lowest.         |
| `-p <version>` | PHP version (`8.1` … `8.4`). Default `8.2`. `8.1` is TYPO3 v12 only.       |
| `-d <dbms>`    | Database for functional tests (`sqlite`, `mariadb`, `mysql`, `postgres`).  |
| `-i <version>` | Database image version, together with `-d`. `-h` lists the accepted ones.  |
| `-j <n\|auto>` | Functional tests in that many parallel chunks, or as many as are worth it. |
| `-b <bin>`     | Container binary, `podman` or `docker`. Auto-detected, podman preferred.   |
| `-n`           | Check only, do not modify files (used by `cgl` in CI).                     |
| `-o <seed>`    | Replay a specific random order seed with `unitRandom`.                     |
| `-h`           | Full help with every suite and option.                                     |

## Suites

| Suite                        | Purpose                                                                                                                    |
|------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| `unit`                       | PHP unit tests (default suite).                                                                                            |
| `unitRandom`                 | Unit tests in random order.                                                                                                |
| `functional`                 | PHP functional tests.                                                                                                      |
| `acceptance`                 | Playwright against an instance built from nothing, see [Acceptance tests](../testing/acceptance-tests.md).                 |
| `visual`                     | Screenshots and axe of the styleguide partials, see [Visual tests](../testing/visual-tests.md).                            |
| `cgl`                        | Coding guidelines, fix in place or check with `-n`.                                                                        |
| `phpstan`                    | Static analysis.                                                                                                           |
| `phpstanGenerateBaseline`    | Regenerate the PHPStan baseline of the selected core version.                                                              |
| `recordFunctionalTestTimes`  | Write the durations `-j` balances by, from JUnit logs, see [below](#the-floor-and-the-recorded-durations).                 |
| `lintPhp`                    | PHP linting.                                                                                                               |
| `checkBom`                   | UTF-8 files must not contain a BOM.                                                                                        |
| `checkExceptionCodes`        | Duplicate or missing exception codes.                                                                                      |
| `checkMarkdownTables`        | Markdown tables must be formatted, `-- --fix` formats them.                                                                |
| `checkTestMethodsPrefix`     | Test methods must not start with `test`.                                                                                   |
| `buildCss`                   | Compile the SCSS into `Resources/Public/Css`.                                                                              |
| `checkCssBuild`              | The committed CSS must match its SCSS sources.                                                                             |
| `watchCss`                   | Compile the SCSS, re-compiling on every change.                                                                            |
| `buildIcons`                 | Copy the pinned Font Awesome Free: the solid set, the allowlisted brand logos, licence, categories, see [Icons](icons.md). |
| `checkIconsBuild`            | The committed icons, licence and categories must equal the pinned package and the brand allowlist.                         |
| `npm`                        | `npm` with all remaining arguments dispatched.                                                                             |
| `composer`                   | `composer` with all remaining arguments dispatched.                                                                        |
| `composerInstall`            | `composer install`.                                                                                                        |
| `composerUpdate`             | `composer update` for the core version given with `-t`.                                                                    |
| `composerValidate`           | `composer validate --strict` of the root `composer.json`.                                                                  |
| `renderDocumentation`        | Render `Documentation/` into `Documentation-GENERATED-temp/`.                                                              |
| `setVersion`                 | Apply a version, `-- <version> <type>`.                                                                                    |
| `watchDocumentation`         | Serve `Documentation/`, re-rendering on every change.                                                                      |
| `clean`                      | Remove build, cache, rendered documentation and test files.                                                                |
| `cleanCache`                 | Cache files and folders only.                                                                                              |
| `cleanRenderedDocumentation` | `Documentation-GENERATED-temp/` only.                                                                                      |
| `cleanTests`                 | Test related files and folders only.                                                                                       |

## Passing arguments to the underlying tool

The wrapper parses its own options with `getopts`, so arguments meant for
PHPUnit (or any other dispatched tool) must follow a `--` separator:

```bash
Build/Scripts/runTests.sh -s functional -d sqlite -- --filter DummyTest
```

## Interrupting a run

Every run creates a network of its own, `theme-extension-development-<suffix>`,
and attaches its containers to it; the suffix is printed at the end of the
run. The run removes that network and every container on it, in whatever
state, on every way out:

- its end and every early exit, with the exit code of the suite — 0 for a
  green one, non-zero for a failing one;
- SIGINT, SIGTERM and SIGHUP, exiting with 2 for SIGINT as before, 143 for
  SIGTERM and 129 for SIGHUP; locally and in CI alike;
- SIGKILL, which no trap sees, and a SIGKILL that follows a SIGTERM before
  the trap is through: a reaper process waits for the run to end and removes
  what is left, looking once a second. With `setsid`, which Linux has, it runs
  in a session of its own, which no signal to the process group of the run
  reaches. Without it, on macOS, it shares that process group and ignores
  SIGINT, SIGTERM and SIGHUP, so only a SIGKILL to the whole group escapes it.

The containers that need it are the detached ones — the database of
`functional`, the web server of `acceptance` and `visual`. No process of them
takes a signal: a `functional` run stopped by SIGTERM or SIGHUP left the
database container and the network running for hours, while the
`functional-<suffix>` container beside it went away — its client either
forwarded the signal to it or, when only the script was signalled, ran the
suite to the end and removed it.

A signal to the process group — ctrl-c, a closed terminal, a supervisor that
sends SIGTERM and SIGKILL shortly after, as `timeout -k` does — stops the
running container and the run at once. A signal to the `runTests.sh` process
alone takes effect when the container in the foreground has finished, because
bash runs a trap only between commands.

## Git worktrees

A `git worktree` is a supported checkout, including one kept below an ignored
directory of another checkout. Every suite runs there as it does in a clone.

`runTests.sh` mounts the checkout and nothing else, so a worktree's `.git` — a
file pointing into the git directory of the main checkout — leads nowhere
inside a container. That is deliberately left so: no suite runs git. The one
tool that asks is composer, for the version of the extension that the
development instance of `-s acceptance` installs through a path repository,
and without an answer it falls back to `dev-main`, which the instance's `@dev`
constraint accepts, as it would accept whatever git answered. The gate that
does need to know what is git-ignored, `checkMarkdownTables`, reads the
`.gitignore` of its checkout rather than asking git or searching for a `.git`
directory, see [Markdown table formatting](quality-gates.md#markdown-table-formatting).

What a worktree costs is a dependency set of its own: `.Build/`, `.cache/` and
`composer.lock` are git-ignored and therefore per checkout, so a new worktree
starts without any and needs its own `-s composerUpdate` before a suite that
needs dependencies runs: about 135 MB below `.Build/` and 40 MB of composer
cache for one core version, measured on TYPO3 v12.

## Functional tests in parallel chunks

`-j <number>` splits the functional suite into that many chunks and runs them at
the same time:

```bash
Build/Scripts/runTests.sh -s functional -d mariadb -i 10.6 -j 4

# As many chunks as are worth it on this machine - the one for a local run.
Build/Scripts/runTests.sh -s functional -d sqlite -j auto
```

Without `-j`, or with `-j 1`, the suite runs in one PHP container exactly as
before. With it, a run does this:

1. **List.** PHPUnit writes the tests of the run with `--list-tests-xml`, from
   the suite configuration and any test file or directory given after `--`.
   Every data set is one entry. PHPUnit 10.5, the one of this branch, writes
   the list before it applies any filter and ignores `--exclude-group` there,
   so the list holds the tests of both core versions and every DBMS, each with
   its groups. The split and the count drop the tests of `not-<dbms>` and
   `not-core-<version>`, as the run does, and what is left is exactly what the
   run will execute. For the same reason `-j` refuses `--filter`, `--group`,
   `--exclude-group`, `--covers` and `--uses` after `--`: the chunks would run
   another set of tests than the list holds.
2. **Split.** [`Build/Scripts/splitFunctionalTests.php`](../../Build/Scripts/splitFunctionalTests.php)
   writes one PHPUnit configuration per chunk — a copy of
   `Build/phpunit/FunctionalTests.xml` listing the files of that chunk. The
   list names no file, so the file of a class is derived from the PSR-4
   mapping of the root `composer.json`; PHPUnit refuses a configured file that
   does not exist, so a class kept elsewhere fails its chunk. A test class is
   never split. The heaviest classes go first, each into the chunk that is
   lightest so far, weighed by the recorded durations of
   `Build/phpunit/FunctionalTestTimes-<dbms>.json`, or by the number of tests
   when that file is missing. There are never more chunks than test classes.
3. **Run.** The script starts itself once per chunk, with the internal
   `-c <chunk>/<number>` and otherwise the same arguments. A chunk is a run of
   its own: its suffix is the one of the run with the chunk number appended,
   `<suffix>-<chunk>`, and with it come its own container network, database
   container and PHP container. Each chunk writes a JUnit log.
4. **Report.** The output of every chunk is streamed while it runs, each line
   prefixed with its chunk, `[chunk 2/4] …`. Whole lines only, so chunks
   interleave by line and never within one; PHPUnit completes a progress line
   every 63 tests. Streamed rather than printed once a chunk is done: collected
   at the end, every line of a chunk carries the same timestamp in a CI log, and
   a chunk killed by a job timeout shows nothing at all. At the end the output of
   every failed chunk is repeated as one unprefixed block, followed by one line
   per chunk with its exit code and its duration in seconds.
5. **Count.** [`Build/Scripts/checkFunctionalTestCount.php`](../../Build/Scripts/checkFunctionalTestCount.php)
   compares the number of tests in the list with the number the JUnit logs
   report, and fails the run on any difference. Green chunks do not prove that
   every test ran — a class the split had left out would simply be absent.

### The files of a run

Everything a chunked run writes lives in `.Build/functional-runs/<suffix>/`,
named after the suffix the run prints, so two runs in one checkout never
overwrite each other's files. They are kept after the run, for reading a failed
chunk and for recording durations. `-s cleanTests` removes them, and so does
`-s composerUpdate` with the rest of `.Build/`.

| File                          | Content                                                           |
|-------------------------------|-------------------------------------------------------------------|
| `tests.xml`                   | The list of step 1.                                               |
| `list.log`                    | The output of step 1, printed when the list could not be written. |
| `FunctionalTests-Job-<n>.xml` | The configuration of chunk `<n>`, written by the split.           |
| `chunk-<n>.log`               | The complete output of chunk `<n>`, unprefixed.                   |
| `chunk-<n>.seconds`           | The wall time of chunk `<n>`.                                     |
| `junit-<n>.xml`               | The JUnit log of chunk `<n>`, written when the chunk ends.        |
| `events-<n>.txt`              | The PHPUnit event log of chunk `<n>`, appended event by event.    |

The event log (`--log-events-verbose-text`) is the one to read when a chunk was
killed — by a timeout, by ctrl-c: the JUnit log is written only when PHPUnit
ends, while the event log names the last test that was prepared and each test
that finished, with the time since the start of the chunk.

### Why the chunks do not disturb each other

- **Names.** A chunk's suffix derives from the run, not from a `$RANDOM` of its
  own: the chunks start in the same instant, `$RANDOM` has 15 bits, and two
  chunks drawing the same value would collide on the network and the database
  container, each removing what the other one uses.
- **Instances.** The testing framework sets up a TYPO3 instance per test class
  below `.Build/Web/typo3temp/var/tests/functional-<identifier>`, with
  `<identifier>` the first seven characters of the SHA-1 of the class name
  (`FunctionalTestCase::getInstanceIdentifier()`). The database of a class is
  named after the same identifier. As a class is never split, two chunks never
  share an instance directory or a database.
- **SQLite.** Every chunk mounts a tmpfs of its own on
  `typo3temp/var/tests/functional-sqlite-dbs/`, so the databases are per
  container. The directory itself is prepared once by the run, never by a
  chunk: removing it on the host detaches the tmpfs of every container that has
  it mounted, and the databases of a chunk that started a moment earlier would
  vanish in the middle of its tests.
- **Interruption.** A chunk is a run of its own, with the traps and the reaper
  of [Interrupting a run](#interrupting-a-run) for its own network. The reaper
  of a chunk also acts when the run that started the chunk is gone: terminated
  or killed alone, that run takes none of its chunks with it, and they would
  run on to the end, each with its database container. Measured on this
  branch with a four chunk MariaDB 10.6 run on TYPO3 v12, signalled while
  PHPUnit ran in every chunk: SIGINT to the process group, as ctrl-c sends it,
  SIGTERM, SIGHUP or SIGKILL to the run alone, and SIGTERM to the group
  followed by SIGKILL 0.3 seconds later each left no container and no network
  of the run after 1.8 to 4.6 seconds with podman; SIGTERM to the run, with
  docker, after 2.0 seconds. On `main`, with a reaper that watched its chunk
  alone, SIGTERM to the run left all four chunks running, with their eight
  containers. The output of a chunk passes `tee` and the prefixing loop, which
  ignore SIGINT, SIGTERM and SIGHUP: without that, the SIGINT of a ctrl-c ended
  them first, the chunk's own trap then died of SIGPIPE on its first message,
  before it cleaned up, and the database containers stayed behind.

### The floor, and the recorded durations

A class is the unit of the split, so the slowest class is the floor of a
chunked run, however many chunks it has. Here that is `ShowcaseTreeTest`: each of
its 151 tests, data sets counted, imports the showcase seed in `setUp()`, and it
takes about half of the whole suite on every DBMS. Recorded locally with TYPO3
v13, the heavier of the two sets with 1075 tests in 72 classes, PHP 8.2 and
`-j 4`:

| DBMS          | All classes | `ShowcaseTreeTest` | Share |
|---------------|-------------|--------------------|-------|
| SQLite        | 1303 s      | 641 s              | 49 %  |
| MariaDB 10.6  | 1919 s      | 1046 s             | 55 %  |
| MySQL 8.0     | 2038 s      | 1108 s             | 54 %  |
| PostgreSQL 10 | 2576 s      | 1430 s             | 56 %  |

So two chunks already reach the floor — one holding `ShowcaseTreeTest`, the
other everything else — and more chunks make the other classes finish earlier
without making the run shorter. The lever beyond that is `ShowcaseTreeTest`
itself, not the split.

### `-j auto`

`-j auto` picks the number of chunks, in two steps. The machine sets the most:
half its CPU cores, because a chunk of a DBMS run keeps a database container
busy next to its PHP process, and no more chunks than GB of available memory
(`MemAvailable` of `/proc/meminfo`; elsewhere only the cores count). The
durations then set how many of those are worth it. The heaviest chunk of the
most is the floor, and the split writes the fewest chunks of which every chunk
but the heaviest weighs at most 80 % of it. Each chunk costs its containers, and
once one class outweighs an even share another chunk buys nothing. The 20 % are
headroom: the same chunk took up to 10 % more or less from one run to the next,
and with two chunks on SQLite the other chunk, predicted at 86 % of the floor,
came within 40 and 50 s of it in two runs — both measured on `main`, with
TYPO3 v14. The run prints both steps:

```
-j auto: at most 16 chunks (32 CPU cores, 80 GB memory available)
auto: 3 of at most 16 chunks, the heaviest weighs 641 s, the next 331 s, the floor is 641 s
```

With the durations recorded today that is three chunks on every DBMS, on
either core version. If `ShowcaseTreeTest` gets faster or is split into several
classes, the same option picks more — after the durations are recorded again.
An explicit `-j <number>` is taken as given, up to the number of test classes.

The durations files are committed per DBMS, because the same class costs very
different times on each. They are an input of the split, never a gate: a stale
file costs balance, not a test, and a class it does not know yet weighs its
number of tests times the average recorded duration of one test. Refresh them
when the chunk times printed at the end of a run drift apart:

```bash
Build/Scripts/runTests.sh -t 13 -s functional -d mysql -i 8.0 -j 4
Build/Scripts/runTests.sh -s recordFunctionalTestTimes -d mysql -- .Build/functional-runs/<suffix>/junit-*.xml
```

`-d` selects the file written. The JUnit logs of a green CI run serve as well,
once downloaded below the repository root — the container only sees the
checkout. Those of a failed run do not: the chunk that did not finish left its
`junit-<n>.xml` empty, and the recorder stops at it with "Cannot read".
The keys are cut at `Tests/Functional/`, so logs of another checkout record the
same keys.

## See also

- [Dual core setup](dual-core-setup.md)
- [Quality gates](quality-gates.md)
- [Testing](../testing/Index.md)
