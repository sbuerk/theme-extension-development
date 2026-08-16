# Seeding

A development instance is worth nothing empty. `theme:seed` writes a page tree
and its content from a definition that lives in the repository, so an instance
can be rebuilt from nothing instead of being clicked together by hand.

```bash
cd instance-core-13
ddev exec vendor/bin/typo3 theme:seed          # or, on a host stack:
vendor/bin/typo3 theme:seed
```

The shipped definition is
[`Configuration/Seeds/Demo.yaml`](../../Configuration/Seeds/Demo.yaml). Another
one is written by passing its path, and `EXT:` is resolved:

```bash
vendor/bin/typo3 theme:seed EXT:my_package/Configuration/Seeds/Other.yaml
vendor/bin/typo3 theme:seed --root-page=12 --force
```

## The format

Structural keys are `identifier`, `uid`, `children` and `content`. Everything
else is a field of the record and is written as it stands:

```yaml
identifier: demo

pages:
  - identifier: home
    uid: 1
    title: 'Theme demo'
    slug: '/'
    is_siteroot: 1
    content:
      - identifier: home-heading
        CType: header
        header: 'A frontend to look at'
    children:
      - identifier: about
        title: 'About'
        slug: '/about'
```

- `identifier` is symbolic and has to be unique across the whole definition. It
  becomes the DataHandler placeholder and is what the command reports the
  written uids under.
- `uid` is **optional**, and where it is given it is passed to DataHandler as a
  *suggested* uid.
- `children` nests pages, `content` nests `tt_content` records below the page
  carrying them.
- `files` on a record creates file references, as a map of field name to file
  identifiers.

## Files

Files are copied into a file storage before any record is written, so a record
can reference them:

```yaml
files:
  - identifier: placeholder
    source: 'EXT:my_package/Configuration/Seeds/Files/placeholder.svg'
    folder: 'theme-demo'     # optional, storage root by default
    # name: 'other-name.svg' # optional, the source name by default
    # storage: 2             # optional, the default storage otherwise

pages:
  - identifier: home
    title: 'Home'
    files:
      media:                 # any FAL field of the record
        - placeholder
```

The FAL fields available are `pages.media` and `tt_content.image`, `assets` and
`media` — all of them from EXT:frontend, so none of them depends on
`fluid_styled_content`. Of those, `tt_content.image` is the one the theme
renders, through the `image` content element.

The copy goes through the storage API, not through the filesystem: a file copied
into `fileadmin/` with `cp` exists on disk and does not exist for TYPO3, so
nothing can reference it. Three details of that API are handled here and are
each easy to get wrong:

- **`addFile()` moves by default.** Its `removeOriginal` argument defaults to
  `true`, which would delete the source out of the repository. It is passed as
  `false`.
- **The conflict mode is the native enum.** TYPO3 v13 still carries the older
  `Resource\DuplicationBehavior` class alongside `Resource\Enum\DuplicationBehavior`,
  and passing the old one triggers a deprecation (#101151) that this test suite
  turns into a failure. The enum exists in v13.4 and v14 alike, so this needs no
  version split.
- **A storage evaluates backend user file mounts.** Seeding runs on the command
  line into a folder no user has a mount for, so the check is suspended for the
  duration of the copy and restored afterwards.

### Why references need a second pass

A `sys_file_reference` carries the uid of the record it belongs to in
`uid_foreign`, and that is a plain integer column rather than a relation
DataHandler resolves. A `NEW...` placeholder written there stays unresolved and
the reference silently points at record 0.

So the records are written first, their real uids are read back from
`substNEWwithIDs`, and the references are attached in a second DataHandler pass.
That is also why a file reference cannot simply be another entry in the same
data map.

## Why it goes through DataHandler

Because the alternative is reimplementing TYPO3. Writing rows directly means
owning slug generation, TCA defaults and evaluations, `sorting`, the reference
index and the cache flush — and getting them subtly wrong. Through DataHandler
the core does all of it, and what comes out is a page tree rather than rows that
merely resemble one.

Three consequences are worth knowing, because each one bit during
implementation:

**An admin backend user is required.** DataHandler honours suggested uids only
for an admin, and *silently ignores them otherwise* — a seed declaring uid 1
would quietly get whatever was free, and a site configuration pointing at it
would be wrong. The seeder refuses rather than allowing that.

**Declaration order needs negative pids.** A new record is placed at the *top*
of its parent, so records created in declared order come out reversed. The
convention DataHandler offers is a negative `pid`, meaning "directly after this
record", so only the first sibling addresses its parent. That predecessor is
tracked **per table**, because a negative pid names a record of the same table
and a page's children are a mix of sub pages and content elements.

**Records are seeded visible.** DataHandler creates them hidden, which is right
for an editor and wrong for a seed: the tree would exist, the frontend would
render nothing, and nothing would say why. A definition can still ask for a
hidden record by declaring `hidden: 1` itself.

## Why a seed may declare uids

So a site configuration can be committed. `instance-core-13/config/sites/demo/`
and its v14 counterpart reference `rootPageId: 1`, which only works because the
definition declares that uid. Without it the root page would get whatever the
database assigned and the site configuration could not be written in advance.

That is also why the command refuses to run into a non-empty page tree: a
definition declaring uids collides rather than adding. `--force` overrides that
for a definition known not to overlap.

## What it does not do

- **No file metadata.** A file is copied and referenced; its `sys_file_metadata`
  (alternative text, title) is not written, and neither are the `title` and
  `alternative` fields of the reference itself.
- **No site configuration.** Sites are committed files under
  `instance-core-*/config/sites/`, which is why the seed declares uids rather
  than the seeder writing sites.
- **No update.** Seeding writes; it does not reconcile an existing tree against
  a definition.

## Where the code lives

`Classes/Seeding/`, and every class in it is `@internal`. It deliberately
depends on nothing else in this extension, so it can be extracted into a package
of its own once it earns that.

| Class                                      | Does                                                             |
|--------------------------------------------|------------------------------------------------------------------|
| `YamlSeedParser`                           | Reads a definition into value objects, and validates it.         |
| `FileSeeder`                               | Copies the files into a storage and returns their sys_file uids. |
| `DataMapFactory`                           | Turns a definition into the DataHandler data map.                |
| `Seeder`                                   | Runs DataHandler, attaches the references, reports the uids.     |
| `SeedDefinition`, `SeedRecord`, `SeedFile` | The value objects. Data, not services.                           |

`Classes/Command/SeedCommand.php` is the CLI surface and stays behind when the
engine is extracted.

## See also

- [Development instances](instances.md)
- [Functional tests](../testing/functional-tests.md)
