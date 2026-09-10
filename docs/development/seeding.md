# Seeding

A development instance is worth nothing empty. The showcase page tree of the
theme is a **seed set** of [`sbuerk/data-factory`](https://github.com/sbuerk/data-factory),
so an instance is rebuilt from a definition in the repository instead of being
clicked together by hand:

```bash
cd instance-core-13
ddev exec vendor/bin/typo3 data-factory:import theme-demo   # or, on a host stack:
vendor/bin/typo3 data-factory:import theme-demo
```

`data-factory:list` shows every set an installation provides, and
`data-factory:import --help` every option and exit code. The format, the
command and what happens between them are documented by that extension —
[seed definitions](https://github.com/sbuerk/data-factory/blob/1/docs/development/seed-definitions.md),
[seed sets and the CLI](https://github.com/sbuerk/data-factory/blob/1/docs/development/seed-sets.md).
This page documents the set, not the tool.

## Where the set lives, and why there

```
Configuration/DataFactory/theme-demo/
├── config.yml      identifier, scenario files, files, file references
├── Scenario.yaml   the records, in the scenario format
└── Files/          placeholder.svg, placeholder-portrait.svg
```

The set ships **with the extension**, not with the development instances. That
is what the built-in `theme:seed` command offered before the seeder was
extracted, and it stays true: any installation that has `sbuerk/data-factory`
installed can import the showcase with one command, a test instance of another
extension included.

This branch uses the 1.x line of `sbuerk/data-factory`, which supports TYPO3
v12.4 and v13.4; the 2.x line on `main` is v13.4 and v14.3.

`sbuerk/data-factory` is **suggested**, not required — in `composer.json` and in
`ext_emconf.php`. Discovery finds a set in `Configuration/DataFactory/` of every
active package, so the set costs an installation without that extension
nothing: it is a directory of YAML that nothing reads. The root `composer.json`
requires it for development, because the functional tests import the set, and
both instances require it, because that is how they get their content.

Every path in `config.yml` is an `EXT:` path, although a path relative to the
set directory would do for the set on its own. data-factory resolves a relative
path against the directory of the **entry file**, and a set composed from this
one — through `imports` — has an entry file somewhere else. `EXT:` resolves the
same from both.

## Two files, two formats

| File            | Format                                                        | Owner                             |
|-----------------|---------------------------------------------------------------|-----------------------------------|
| `config.yml`    | the set descriptor: identity, scenarios, files, references    | data-factory, closed key set      |
| `Scenario.yaml` | the scenario format of `typo3/testing-framework`, key for key | upstream, the core's own fixtures |

A record is a field map below `self`, nested under its page through `entities`
(content, list items) or `children` (sub pages). Every key that is not
structural is written to the record as it stands, which is why
`backend_layout`, `nav_hide`, `abstract`, `keywords` and the `table_*` fields of
the `table` element need nothing from the tool.

## Uids are declared, and they are a rule

| Table                | Uids                                                             |
|----------------------|------------------------------------------------------------------|
| `pages`              | 1 to 9                                                           |
| `tt_content`         | its page times 100 plus its position: the third of page 6 is 603 |
| `tx_theme_list_item` | 1 to 12 on page 8, in declaration order                          |

Every record declares one, because the records point at each other by uid and
a scenario record has no other handle:

- the committed site configurations of both instances name root page `1`;
- `config.yml` names the record a file reference hangs on by its uid;
- the *Insert records* element writes `records: 'tt_content_601'`;
- the link fields hold `t3://page?uid=2`;
- an inline parent lists its children as `tx_theme_list_items: '5,3,6,4'`.

A declared uid is a *suggestion* DataHandler honours for an admin backend user
only, which is why `data-factory:import` refuses to run as anybody else. A
record without one would not get an auto increment uid either, but one from a
counter at 10000 — so nothing here relies on that.

The import refuses an installation that already uses one of those uids. It does
not reconcile, merge or overwrite, and `--force` is no way around it for this
set. It gives up the suggestions of every table something collides in and
writes those records under free uids. The file references of `config.yml`
follow — data-factory resolves each to the uid the run actually wrote — but
nothing else does: a link, a menu page list, `tt_content_601` and an inline list
are literal uids in a field, and would then name whatever record of the
installation carries them.

## Relations

An **inline relation** needs no construct of its own. The parent writes the
comma separated list of the declared ids of its children into its relation
field, the children are records of their own on the same page, and DataHandler
resolves the list like a backend form submit: `uid_foreign`, `tablename` and
`sorting_foreign` come from the TCA of the parent field
(`tt_content.tx_theme_list_items`), never from the scenario. The order of the
list is the order of the children — `ShowcaseTreeTest::inlineChildrenKeepTheirDeclarationOrderInTheFrontend()`
reads the list from the scenario and holds the rendering to it. The link list
names its children out of uid order (`5,3,6,4`) for that test's sake: uid order
is also creation order, and a list in that order could not tell a rendering
that follows the relation from one that sorts by uid.

A **file reference** is the one relation the scenario cannot express: a
`sys_file_reference` points at its file through `uid_local`, a uid the FAL
indexer hands out while the file is placed. So files and references are declared
in `config.yml` — `files:` places a file into `fileadmin/theme-demo/` through the
storage API, `references:` attaches it to a field of a record named by table and
uid, with the fields of the reference itself (alternative text, caption, title)
under `values`. References to one field are ordered as declared.

## Two traps the scenario format sets

**`hidden: 0` sits on the wildcard entity `'*'` and nowhere else.** The `pages`
TCA defaults `hidden` to `1`, so a page written without it exists and renders
nothing. Repeating the key on a declared entity is worse than leaving it out:
the wildcard is merged into each entity with `array_merge_recursive()`, so a key
on both sides becomes the list `[0, 0]` and reaches the database as the string
`Array`.

**`entities:` is only read on an entity with `isNode: true`.** On any other
entity it is ignored without a word, which is the most likely way to write a
scenario that seeds less than it says. Only `page` is a node here.

## The demo tree

Not a sample of the format — the frontend this extension is developed against.
Nine pages, and between them every backend layout the extension registers and
every `CType` it renders:

| uid | Title          | Slug              | `backend_layout`  | What it is for                              |
|-----|----------------|-------------------|-------------------|---------------------------------------------|
| 1   | Theme demo     | `/`               | `start`           | The site root, and the footer columns.      |
| 2   | Typography     | `/typography`     | `content`         | Headings, running text, the inline cases.   |
| 3   | Media          | `/media`          | `content`         | One image, and a two column gallery.        |
| 4   | Empty page     | `/empty`          | *(none)*          | The `default` layout fallback.              |
| 5   | Elements       | `/elements`       | `content`         | The showcase branch, parent of 6 to 8.      |
| 6   | Core elements  | `/elements/core`  | `content_sidebar` | Every classic `CType` the theme renders.    |
| 7   | Menu elements  | `/elements/menu`  | `content_sidebar` | The eleven `menu_*` elements.               |
| 8   | Theme elements | `/elements/theme` | `content`         | The ten `theme_*` elements.                 |
| 9   | Styleguide     | `/styleguide`     | `styleguide`      | The component library, straight from Fluid. |

Four properties of that tree are deliberate, and are asserted by
`Tests/Functional/ShowcaseTreeTest.php` rather than left to a reader to
preserve:

- **Page 4 declares no `backend_layout` at all**, and that is the point of it.
  It is the only page in the tree that reaches the hard-coded `default` in
  `PageLayoutResolver::getLayoutIdentifierForPage()`, after the
  `backend_layout_next_level` walk up the rootline found nothing.
- **Pages 6 and 7 use `content_sidebar` and their sibling 8 does not.** Two
  pages under one parent rendering with and without the sub navigation is what
  proves the layout is resolved per page rather than inherited down the branch.
- **The styleguide page uses `nav_hide`, never `hidden`.** A hidden page returns
  404 in the frontend and is only reachable through a backend preview link
  carrying a valid hash, which defeats the point of seeding a page that exists
  to be opened.
- **The two lists that hold the tree complete are read from the repository, not
  from the test.** `ShowcaseTreeTest` derives the backend layouts from
  `Configuration/PageTsConfig/BackendLayouts/` and the content types from the
  TypoScript. A layout or an element added without a demo page then fails there,
  instead of shipping undemonstrated.

## How the tests import it

Through the command. `Tests/Functional/DataFactoryImportTrait.php` runs
`data-factory:import <identifier>` with a `CommandTester` and asserts exit code
`0`, printing the command output when it is not. The command, its options and
its exit codes are the supported interface of data-factory; the parser, the
composer and the seeder behind it are `@internal` there. A test that assembled
those services itself would be pinned to the one part of the dependency that
promises nothing.

A test importing a set loads `sbuerk/data-factory` and the extension shipping
the set, imports `Fixtures/Database/AdminBackendUser.csv` — the import runs as
an admin or not at all — and calls `createDefaultFileStorage()`, because a
functional test instance has a `fileadmin/` folder but no `sys_file_storage`
record.

`ImageElementRenderingTest` imports a set of its own, `tests-image-element`,
from the fixture extension
[`data-factory-fixture`](../../Tests/Functional/Fixtures/Extensions/data-factory-fixture).
It asserts how many figures one page renders and at which sizes, and a page of
the showcase is free to change what it shows.

## What it does not do

- **No categorized menus.** `menu_categorized_pages` and
  `menu_categorized_content` are seeded with `selected_categories: 0` and render
  an empty menu, which is the correct rendering of "nothing chosen". The format
  could express categories and their MM relation — a relation field listing
  declared uids — and the set does not seed any yet.
- **No site configuration.** A site names its root page by uid and carries
  values of the installation, its base and title. The instances commit theirs
  below `instance-core-*/config/sites/`.
- **No TypoScript record.** On TYPO3 v13 the site of an instance enables the
  theme through its site set. TYPO3 v12 has no site sets, and a `sys_template`
  record on page 1 would enable it there — the set could declare one, but it
  would then carry it on v13 as well, which changes the shipped showcase for one
  core version. See [Enabling the theme on TYPO3 v12](instances.md#enabling-the-theme-on-typo3-v12).
- **No file metadata.** The fields of a *reference* are written; the
  `sys_file_metadata` of the file itself is not. An alternative text describes
  what an image means *in this place*, which is a property of the reference.
- **No update.** An import writes. It does not reconcile an existing tree
  against the set, and importing twice is a uid collision.

## See also

- [Development instances](instances.md)
- [Page rendering](../architecture/page-rendering.md) — the backend layouts the
  demo tree uses, and how a layout resolves to a template.
- [Content elements](../architecture/content-elements.md) — the `CType` set the
  showcase pages have to cover.
- [Fixture extensions](../testing/fixture-extensions.md)
- [Functional tests](../testing/functional-tests.md)
