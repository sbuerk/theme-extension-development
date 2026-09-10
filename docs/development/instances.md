# Development instances

Two TYPO3 instances live in the repository, one per supported core version:

```
instance-core-13/    TYPO3 v13
instance-core-14/    TYPO3 v14
```

They exist so the theme can be looked at and rendered against — the thing the
extension is for. They are **development instances**: disposable, not deployed,
and not part of the distributed package.

Each is an independent composer project and an independent DDEV project. Both
resolve the extension out of the repository root, and both run on SQLite with no
database container. **Nothing of an instance's state is committed**: a fresh
clone builds it from the seed set on the first start.

```bash
# With DDEV. The first start installs TYPO3 and imports the seed set.
cd instance-core-13
ddev start
ddev launch /typo3/                  # john-doe / John-Doe-1701D.

# Without DDEV, on a host stack.
cd instance-core-13
composer install
composer system:setup
# then point a vhost at instance-core-13/public
```

> [!IMPORTANT]
> **Do not mix the two.** An instance installed with DDEV and an instance
> installed on the host produce different, mutually incompatible `vendor/`
> directories — see [why](#the-theme-symlink) below. `vendor/` is git-ignored,
> so the fix is simply to re-run `composer install` in whichever world you
> switched to.

## Setup, reseed, refresh

Three composer scripts, the same in both instances:

| Script                    | Does                                                                                             |
|---------------------------|--------------------------------------------------------------------------------------------------|
| `composer system:setup`   | Installs TYPO3 and imports the seed set — whatever of the two is missing. Idempotent.            |
| `composer system:reseed`  | Removes the database, `settings.php`, the seeded files and the caches, then runs `system:setup`. |
| `composer system:refresh` | Flushes and warms the caches, updates the language packs, runs `extension:setup`.                |

Prefix them with `ddev` inside DDEV. `ddev start` runs `system:setup` itself, as
a post-start hook after `composer install`, so a started instance is always a
set up and seeded one — and on every start after the first, the script finds
both done and does nothing.

Both scripts run [`Build/Scripts/instance.php`](../../Build/Scripts/instance.php),
which calls `vendor/bin/typo3` as a separate process for every step:

1. **Is TYPO3 installed?** Yes when `config/system/settings.php` exists and the
   database holds a `be_users` table. The database file alone says nothing:
   SQLite creates a missing file on the first connection, so one request to an
   instance that was never set up leaves an empty database behind. If not,
   `typo3 setup --force` runs with the admin account below and the SQLite
   connection type, and the database it creates is moved to where the instance
   looks for it — `typo3 setup` names a SQLite file `cms-<hash>.sqlite` itself.
2. **Is it seeded?** Yes when `vendor/bin/typo3 dev-site:seed-state` exits `0`.
   If not, `extension:setup`, `data-factory:import theme-instance`,
   `dev-site:seed-state --mark=theme-instance` and `cache:flush` run, in that
   order.

The seed state is a `sys_registry` entry, not a marker file and not "does page 1
exist". It is written last, so it exists exactly when every step before it
succeeded: an import that stopped halfway — data-factory writes files, then
records, then file references — leaves a page tree behind and no entry. And it
lives in the database, so removing the database is all it takes to make the
next start rebuild the instance. A failed import says so and leaves the instance
unmarked; run `system:reseed` then, because a second import collides with the
uids the first one already wrote.

`config/system/additional.php` does nothing at request time besides setting
configuration: a request is the wrong place to build an instance.

`Build/Scripts/runTests.sh -s acceptance` builds an instance exactly this way,
below `.Build/acceptance/`, and tests it in a browser — which makes it the
automated test of this tooling as well. See
[Acceptance tests](../testing/acceptance-tests.md).

## What is committed

Only what describes an instance, never what an install produces:

| Path                               | Is                                                                      |
|------------------------------------|-------------------------------------------------------------------------|
| `.ddev/config.yaml`                | The DDEV project, `core13-theme-v2` / `core14-theme-v2`, and its hooks. |
| `.ddev/docker-compose.mounts.yaml` | The mounts that make the relative paths resolve, see below.             |
| `composer.json`                    | Dependencies, path repositories, the `system:*` scripts.                |
| `config/system/additional.php`     | **All** instance configuration: database path, debug, image processing. |
| `config/sites/demo/`               | The site of the `/` tree, delivered through the site set.               |
| `config/sites/demo-legacy/`        | The site of the `/legacy/` tree, delivered through `sys_template`.      |

Generated and git-ignored: `vendor/`, `public/`, `var/`, `.cache/`,
`composer.lock`, `config/system/settings.php` and
`config/system/additional/*.php`.

The lock file is deliberately **not** committed, unlike in some reference
setups. The root `composer.lock` of this repository is ignored for the same
reason: a development instance should install the current patch level of the
core rather than a pinned one.

### Why `settings.php` is not committed

`typo3 setup` writes `config/system/settings.php`, and writes it complete: the
**encryption key**, the **install tool password hash**, the system maintainers,
and later the extension configuration `extension:setup` adds. A committed file
would either carry an encryption key every clone shares, or be rewritten into a
dirty working copy by every setup. So it is generated per checkout, and every
value the repository wants an instance to have is set in
`config/system/additional.php`, which TYPO3 applies after it and which
therefore wins. Change instance configuration there, never in `settings.php`.

One setting is applied conditionally, and it is the reason the file checks for
`settings.php` at all: `SYS/exceptionalErrors` turns warnings into exceptions
only once TYPO3 is installed. Before that, `typo3 setup` boots against the
`DefaultConfiguration` of the core, which has no `SYS/encryptionKey`, and the
first cache write warns about the missing key — as an exception, that aborted
the very setup that would have created it.

## Accounts

Created by the setup and the seed, identical in both instances, and deliberately
documented — they exist on development instances only:

| Area     | Username       | Password               | What                                                           |
|----------|----------------|------------------------|----------------------------------------------------------------|
| Backend  | `john-doe`     | `John-Doe-1701D.`      | Administrator and system maintainer, created by `typo3 setup`. |
| Backend  | `erika-editor` | `Erika-Editor-1701D.`  | Editor, group *Theme editors*.                                 |
| Frontend | `jane.doe`     | `Frontend-User-1701D.` | Group *Members*: may open `/members`.                          |
| Frontend | `liam.rhodes`  | `Frontend-User-1701D.` | No group: logs in, and still gets a 403 on `/members`.         |

The administrator is the account of the TYPO3 contribution guide, spelled
exactly like that. `typo3 setup` sets the install tool password to the same
`John-Doe-1701D.`, and writes its hash into the git-ignored `settings.php`.

The **editor group** is what makes the editor account worth having. It mounts
both trees, grants the page, list and file list modules and the file mount of
the seeded files, and grants every content type the theme renders — `CType`
carries `authMode = explicitAllow`, so a non-admin gets exactly what a group
grants — except *Plain HTML*, which an editor should not get by default.
Every page of the seed belongs to the group with full rights.

The **frontend users** live on the folder *Frontend users* (uid 20). The login
form is on `/login` (uid 21), rendered by EXT:felogin through the theme, and
`/members` (uid 22) is restricted to the group *Members*. The `demo` site
depends on the set `typo3/felogin` for it: felogin registers its TypoScript with
`addTypoScriptSetup(…, false)`, for `sys_template` sites only, so on a site set
site the plugin otherwise runs with no configuration at all — and TYPO3 v13's
login controller then fails on the logout form of a logged in user. All three
pages carry `nav_hide`, because the `/legacy/` tree mirrors the showcase and not these
pages, and a login link in one tree's navigation only would make every page of
the two trees differ.

All of it is declared in
`packages-dev/dev-site/Configuration/DataFactory/theme-instance/Accounts.yaml`,
with plain text passwords that DataHandler hashes on save — see
[Seeding](seeding.md).

## The `theme` symlink

The repository root **is** the extension, so an instance has to reference it as
`..`. That breaks under DDEV, which mounts the instance at `/var/www/html` —
where `..` is `/var/www`, not the repository root, and holds DDEV's own
`phpstatus.php`.

The repository therefore contains a symlink at its root:

```
theme -> .
```

and each instance references the extension as **`../theme`**:

```json
"repositories": {
    "theme": { "type": "path", "url": "../theme" },
    "packages-dev": { "type": "path", "url": "../packages-dev/*" }
}
```

- On the host, `instance-core-13/../theme` is that symlink, which points at the
  repository root.
- Inside the container, `../theme` is `/var/www/theme`, which
  `.ddev/docker-compose.mounts.yaml` binds to the repository root.

Both resolve to the extension, so one `composer.json` serves both worlds.

### Why an install does not travel between the two

Composer resolves the path repository before recording it. On the host `theme`
*is* a symlink, so it collapses and composer writes
`vendor/sbuerk/theme-extension-development -> ../../..`. Inside the container
`/var/www/theme` is a real directory, so the name survives and composer writes
`-> ../../../theme`.

Each is correct where it was produced, and the host form points at `/var/www`
inside a container. That is the whole reason for the warning above.

### Keep the symlink out of everything that walks the tree

`theme -> .` is self referencing: a tool that follows symlinks while descending
would recurse forever. `find` does not follow symlinks, and neither does the
Symfony Finder the other gates use, but `lintPhp` and `checkUtf8Bom.sh` exclude
`./theme/*` explicitly rather than relying on that default. They exclude the
generated instance trees as well, while still linting `config/system/*.php` —
the committed `additional.php`, and the `settings.php` a setup generated there.

The symlink also carries `export-ignore`, so it can never end up inside a
composer dist archive or a TER artifact.

## Database, and why there is no database container

`.ddev/config.yaml` sets `omit_containers: [db]`. The instance runs on SQLite,
and `config/system/additional.php` recomputes the path from `__DIR__` on every
request, so the same checkout resolves its database identically under DDEV and
on a host stack — and `typo3 setup`, which names the file itself, is overruled.
The path is `var/sqlite/core-13.sqlite` (`core-14.sqlite`), and
`Build/Scripts/instance.php` reads the name from that same line of
`additional.php`, so the two cannot disagree.

`config/system/additional/` is git-ignored and included automatically — the
place for anything belonging to one machine rather than the repository, such as
a different ImageMagick path or mail transport on a host stack.

There is no backup and restore of the database, and no database template is
committed: the seed set is the only source of an instance's content, reviewed
as text, and `system:reseed` produces an instance from it.

## Switching branches in the same checkout

The DDEV project name carries **two** dimensions, the core version and the
extension's own version line — `core13-theme-v2` and `core14-theme-v2` on this
branch, `core12-theme-v1` and `core13-theme-v1` on branch `1`. The second half
is what matters here: `instance-core-13/` exists on both branches, the instance
directory is the same path on every branch, DDEV keys a project on its root
directory, and it refuses a second name for a path it already knows.

```
Failed to start app core13-theme-v2: this project root '…/instance-core-13'
already contains a project named 'core13-theme-v1'.
```

`ddev stop --unlist <other-name> && ddev start` fixes it. `--unlist` removes only
the registration; the database in the git-ignored `var/` survives and still holds
the other branch's state, and it is still marked as seeded, so `ddev start`
leaves it alone. `ddev composer system:reseed` rebuilds it from this branch's
seed set. The same applies after pulling a change to the seed set: an instance
is seeded once, and it is not reconciled with a changed set.

The error is the good outcome, and it is why the names differ. Two branches
naming the project *identically* would not produce it at all — they would
silently share one registration and one database, and content seeded on one
branch would show up on the other. `instance-core-13/` is exactly that case:
both branches ship it, and only the `-v1`/`-v2` suffix keeps the two apart. So
check `.ddev/config.yaml` whenever a branch is cut from another.

## See also

- [Development environment](environment.md)
- [Dual core setup](dual-core-setup.md)
- [Frontend assets](frontend-assets.md)
