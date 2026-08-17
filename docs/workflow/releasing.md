# Releasing

Two scripts in [`Build/Scripts/`](../../Build/Scripts) drive the release. Both
always operate on the repository root, no matter from where they are called, and
both show all options with `--help`.

## Release branches

One branch per core version tuple, one major version line each:

| Branch | Extension | TYPO3         | PHP       |
|--------|-----------|---------------|-----------|
| `main` | 2.x       | v13.4 / v14.3 | 8.2 - 8.5 |
| `1`    | 1.x       | v12.4 / v13.4 | 8.1 - 8.4 |

`main` is where development happens and where a pull request is opened. Branch
`1` is released from its own checkout with `--source-branch=1`, which is already
its default there — see [below](#--source-branch-and-the-key-the-alias-is-stored-under).
A change that belongs on both is made on `main` first and then backported.

## `setVersion.sh` — apply a version

Applies a version and its derived variants to every file carrying one: the
`COMPOSER_ROOT_VERSION` in `Build/Scripts/runTests.sh`, `extra.typo3/cms.version`
and `extra.branch-alias` in `composer.json`, the `version` in `ext_emconf.php`,
the `VERSION` file and — discovered dynamically, none has to exist — the
functional test [fixture extensions](../testing/fixture-extensions.md) below
`Tests/Functional/Fixtures/Extensions/`.

```bash
# Release version 1.2.0 (X.Y.Z, no branch-alias update).
Build/Scripts/setVersion.sh 1.2.0 release

# Next development version after it (X.Y.W-dev, branch-alias X.Y.x-dev).
Build/Scripts/setVersion.sh 1.2.1 post-release

# Force a plain development version, for example when branching.
Build/Scripts/setVersion.sh 1.3.0 dev

# Show every change without touching a file.
Build/Scripts/setVersion.sh 1.2.0 release --dry-run
```

The script only edits working-tree files; it performs no git or network
operations.

It reads and writes `composer.json` with **php**, not with `jq`, so it can also
be run through the container wrapper on a host that has neither:

```bash
Build/Scripts/runTests.sh -s setVersion -- 1.2.0 release
```

Everything after `--` is passed to the script unchanged, `--dry-run` included.
Both ways produce the same result — the wrapper only adds the container.

### `--source-branch`, and the key the alias is stored under

Both scripts default `--source-branch` to **`main`** on this branch:

```bash
SOURCE_BRANCH="main"
```

so nothing has to be passed here. Branch `1` — the maintained line for the
previous core version tuple — defaults to `1` for the same reason. Pass it
explicitly only when driving one branch's release from a checkout of another.

The key `extra.branch-alias` is stored under is **not** `dev-<source-branch>`.
Composer derives a version from the branch name before it consults the alias
map, and a branch whose name looks like a version is normalised to
`<name>.x-dev`:

| Branch | Derived by composer | Alias key  |
|--------|---------------------|------------|
| `main` | `dev-main`          | `dev-main` |
| `1`    | `1.x-dev`           | `1.x-dev`  |

`setVersion.sh` derives it, so this is not something to get right by hand — but
it is worth knowing, because getting it wrong is **silent**. An alias keyed
`dev-1` on a branch named `1` matches no reference composer ever produces, so it
is ignored: the branch does not provide the version it claims to, and nothing
reports it. That is exactly what a branch cut from `main` inherits, since it
takes this file along with `main` in it.

`release.sh` uses it for more than the alias: it is the branch it branches off,
refreshes, targets pull requests at with `gh pr create --base`, and tags. Run
with the default from a maintenance branch it would open the release pull
request against `main`.

## `release.sh` — orchestrate the release

Drives the full two-phase workflow for one release version: branch, apply the
release version, commit `[RELEASE] X.Y.Z`, push, open a pull request, wait for
the checks, merge, tag and push the tag — and afterwards the same for the next
development version with `[TASK] Set version X.Y.W`.

It has two independent safety gates:

```bash
# Print the whole plan, change nothing at all.
Build/Scripts/release.sh 1.2.0 --dry-run

# Run the local steps for real, but only PRINT every remote operation.
Build/Scripts/release.sh 1.2.0

# Actually publish: push, pull request, merge, tag.
Build/Scripts/release.sh 1.2.0 --execute
```

Without `--execute` no push, no pull request, no merge and no tag ever happens,
so a release can safely be rehearsed. `git` and the GitHub CLI (`gh`) have to be
available and authenticated for `--execute`.

Pushing the tag triggers the [`publish`](../../.github/workflows/publish.yml)
workflow, which builds the TER artifact, creates the GitHub release and
publishes the artifact to the TYPO3 Extension Repository.

The TER step authenticates with the `TYPO3_API_TOKEN` repository secret and
needs the extension key registered in the TER and owned by that token. It runs
**after** the GitHub release, so an upload that fails — an expired token, a
version already published — leaves the release and its artifact in place to
retry against instead of losing both.

The tag has to match the version in `ext_emconf.php`, which is what
`setVersion.sh` keeps in sync: `tailor create-artefact` fails otherwise, on
purpose, so a release cannot disagree with the extension metadata.

## Before releasing

- Both core versions green across the full [gate matrix](../development/quality-gates.md).
- Changelog entries for the version in place, see
  [Changelog and documentation](changelog-and-documentation.md).
- `Build/Scripts/runTests.sh -s renderDocumentation` passing.

## See also

- [Pull requests](pull-requests.md)
- [Commit messages](commit-messages.md)
