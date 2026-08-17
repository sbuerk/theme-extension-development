# Development instances

One TYPO3 instance lives in the repository per supported core version:

```
instance-core-12/    TYPO3 v12
instance-core-13/    TYPO3 v13
```

They exist so the theme can be looked at and rendered against — the thing the
extension is for. They are **development instances**: disposable, not deployed,
and not part of the distributed package.

Each is an independent composer project and an independent DDEV project, and
each resolves the extension out of the repository root and runs on SQLite with
no database container.

```bash
# With DDEV.
cd instance-core-13
ddev start                       # installs on first start, see "post-start" below

# Without DDEV, on a host stack.
cd instance-core-13
composer install
# then point a vhost at instance-core-13/public
```

Substitute `instance-core-12` throughout for the v12 instance. The two differ in
their `typo3/minimal` constraint, their DDEV project name, their SQLite file
name, their website title — and in **how the theme is enabled**, which is the
one difference that is not cosmetic; see
[below](#enabling-the-theme-on-typo3-v12).

Both instances pin `php_version: "8.2"`. PHP 8.1 is supported for TYPO3 v12, but
covering it belongs in [CI](quality-gates.md#continuous-integration), not in an
instance whose purpose is to look at the theme.

> [!IMPORTANT]
> **Do not mix the two worlds.** An instance installed with DDEV and the same
> instance installed on the host produce different, mutually incompatible
> `vendor/` directories — see [why](#the-theme-symlink) below. `vendor/` is
> git-ignored, so the fix is simply to re-run `composer install` in whichever
> world you switched to.

## What is committed

Only what describes an instance, never what an install produces:

| Path                                 | Is                                                                    |
|--------------------------------------|-----------------------------------------------------------------------|
| `.ddev/config.yaml`                  | The DDEV project, `core12-theme-v1` / `core13-theme-v1`.              |
| `.ddev/docker-compose.mounts.yaml`   | The mounts that make the relative paths resolve, see below.           |
| `composer.json`                      | Dependencies, path repositories, the snapshot scripts.                |
| `config/system/settings.php`         | Instance configuration. The database path there is advisory.          |
| `config/system/additional.php`       | Resolves the database and includes local overrides.                   |
| `config/sites/demo/config.yaml`      | The site of the seeded demo tree — **not identical between the two**. |
| `../sqlite-databases/core-1*.sqlite` | The committed database template, once one exists.                     |

Generated and git-ignored: `vendor/`, `public/`, `var/`, `.cache/`,
`composer.lock` and `config/system/additional/*.php`.

The lock file is deliberately **not** committed, unlike in some reference
setups. The root `composer.lock` of this repository is ignored for the same
reason: a development instance should install the current patch level of the
core rather than a pinned one.

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
generated instance trees as well, while still linting the committed
`config/system/*.php`.

The symlink also carries `export-ignore`, so it can never end up inside a
composer dist archive or a TER artifact.

## Database, and why there is no database container

`.ddev/config.yaml` sets `omit_containers: [db]`. The instance runs on SQLite,
and `config/system/additional.php` recomputes the path from `__DIR__` on every
request rather than trusting `settings.php`, so the same checkout resolves its
database identically under DDEV and on a host stack.

When a template exists at `sqlite-databases/core-<major>.sqlite` it is copied
into `var/sqlite/` on first start. Until one has been committed, the instance
starts empty: set it up with `vendor/bin/typo3 setup` and then fill it with
`vendor/bin/typo3 theme:seed` — see [Seeding](seeding.md). The site
configuration below `config/sites/demo/` is committed and points at the root
page uid the seed definition declares.

`config/system/additional/` is git-ignored and included automatically — the
place for anything belonging to one machine rather than the repository, such as
a different ImageMagick path or mail transport on a host stack.

## Enabling the theme on TYPO3 v12

`instance-core-13/config/sites/demo/config.yaml` carries

```yaml
dependencies:
  - sbuerk/theme-extension-development
```

and that is all it takes there. `instance-core-12/`'s site configuration
deliberately carries **no** `dependencies` key: site sets arrived in TYPO3 v13.1
(#103437), so on v12 the key is read by nothing. Writing it down would claim
this file enables the theme when it does not.
→ [TypoScript delivery](../architecture/typoscript-delivery.md)

The replacement is a `sys_template` record — a database row, and one no
configuration file can carry. A [seed definition](seeding.md) can express it
since the [`records`](seeding.md#records-of-any-table) key, because the row sits
on page 1 like any other record; the demo definition does not declare one, so
this is still a **manual step, between `typo3 setup` and `theme:seed`**:

1. **Web > List**, page `1` — the site root.
2. *Create new record* → **System records** → **"TypoScript record"**
   (`sys_template`).
3. Set a *Title*.
4. Check **Rootlevel**.
5. Check **Clear** for both *Constants* and *Setup*.
6. Under **"Include TypoScript sets"** (`include_static_file`), select
   **"Theme Extension Development"**.

> [!NOTE]
> There is no **Web > Template** module in these instances. `typo3/minimal` does
> not ship `typo3/cms-tstemplate`, which is why the record is created through
> Web > List rather than the module a TYPO3 developer would reach for first.

Committing a prepared `sqlite-databases/core-12.sqlite` would remove the step,
and declaring the record under `records:` in the demo definition would remove it
for good. Neither is done here: the first commits a binary nobody can review,
and the second changes the shipped demo tree for the sake of one core version —
which is a decision about the demo, not about the seeder.

## Snapshot and restore

```bash
cd instance-core-13
ddev composer sqlite:backup    # instance -> ../sqlite-databases/core-13.sqlite
ddev composer sqlite:apply     # template  -> instance, discarding its database
ddev composer system:refresh   # flush and warm caches, update languages, extension:setup
```

Both directions go through
[`Build/Scripts/sqliteSnapshot.php`](../../Build/Scripts/sqliteSnapshot.php)
rather than `cp`, and that is not a refinement — a plain copy is **wrong** here.

SQLite in write ahead logging mode keeps the newest transactions in a `-wal`
sidecar until a checkpoint folds them back in. While anything holds the database
open — a running web server, which is precisely the situation when a backup is
taken — the main file can be almost empty. Measured on a database of 500 rows
with a connection open: the main file was 4 kB, the `-wal` sidecar 2 MB, and a
plain `cp` of the main file produced a template that could not be opened at all.

The script therefore checkpoints before copying, removes the sidecars of the
file it replaces, and verifies the copy by opening it and counting its tables.
The checkpoint is harmless in any other journal mode.

> [!NOTE]
> `sqlite:backup` rewrites a binary that git cannot delta compress. Commit it
> when the demo content genuinely changed, not on every run.

## Switching branches in the same checkout

The DDEV project name carries **two** dimensions, the core version and the
extension's own version line — `core12-theme-v1` and `core13-theme-v1` on this
branch. The second half is what matters here: `instance-core-13/` exists on this
branch *and* on `main`, the instance directory is the same path on every branch,
DDEV keys a project on its root directory, and it refuses a second name for a
path it already knows.

```
Failed to start app core13-theme-v2: this project root '…/instance-core-13'
already contains a project named 'core13-theme-v1'.
```

`ddev stop --unlist <other-name> && ddev start` fixes it. `--unlist` removes only
the registration; the database in the git-ignored `var/` survives and still holds
the other branch's state, so `ddev composer sqlite:apply` resets it.

Two branches naming the project *identically* would not produce that error at
all — they would silently share one registration and one database, and content
seeded on one branch would show up on the other. That is why the version line is
part of the name and not only the core version, and it is the reason to check
`.ddev/config.yaml` when a branch is cut from another.

## See also

- [Development environment](environment.md)
- [Dual core setup](dual-core-setup.md)
- [Frontend assets](frontend-assets.md)
