# Development environment

All tests and quality tools run in containers through the
[`Build/Scripts/runTests.sh`](../../Build/Scripts/runTests.sh) wrapper. The only
requirement on the host is a container runtime — **podman** (preferred) or
**docker**. The wrapper pulls the required TYPO3 testing images on first use;
neither PHP nor Composer needs to be installed on the host.

Dependencies are installed into the git-ignored `.Build/` directory. The
wrapper installs them for a specific TYPO3 core and PHP version:

```bash
# Install dependencies for TYPO3 v13 on PHP 8.2 (default matrix).
Build/Scripts/runTests.sh -t 13 -p 8.2 -s composerUpdate

# Switch the working copy to the TYPO3 v14 dependency set.
Build/Scripts/runTests.sh -t 14 -p 8.2 -s composerUpdate
```

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

| Option         | Meaning                                                                   |
|----------------|---------------------------------------------------------------------------|
| `-s <suite>`   | Suite to run (`unit`, `functional`, `cgl`, `phpstan`, …).                 |
| `-t <13\|14>`  | TYPO3 core major version to run against. Default `13`.                    |
| `-p <version>` | PHP version (`8.2` … `8.5`). Default `8.2`.                               |
| `-d <dbms>`    | Database for functional tests (`sqlite`, `mariadb`, `mysql`, `postgres`). |
| `-i <version>` | Database image version, together with `-d`. `-h` lists the accepted ones. |
| `-b <bin>`     | Container binary, `podman` or `docker`. Auto-detected, podman preferred.  |
| `-n`           | Check only, do not modify files (used by `cgl` in CI).                    |
| `-o <seed>`    | Replay a specific random order seed with `unitRandom`.                    |
| `-h`           | Full help with every suite and option.                                    |

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
needs dependencies runs: about 160 MB below `.Build/` and 50 MB of composer
cache for one core version, measured on TYPO3 v13.

## See also

- [Dual core setup](dual-core-setup.md)
- [Quality gates](quality-gates.md)
- [Testing](../testing/Index.md)
