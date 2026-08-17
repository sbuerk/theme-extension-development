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

Structural keys are `identifier`, `uid`, `children`, `content`, `files` and
`inline` — plus two that are structure on one level and an ordinary field
everywhere else: `table`, on an inline or `records` child, and `records`, on a
page. Everything else is a field of the record and is written as it stands:

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

- `identifier` is symbolic and has to be unique across the whole definition,
  inline children included. It becomes the DataHandler placeholder and is what
  the command reports the written uids under. **Letters, digits and dashes
  only, starting with a letter or a digit** — an underscore is rejected with a
  message, for the reason in [Placeholders carry no underscore](#placeholders-carry-no-underscore).
- `uid` is **optional**, and where it is given it is passed to DataHandler as a
  *suggested* uid.
- `children` nests pages, `content` nests `tt_content` records below the page
  carrying them.
- `records` nests records of **any** table below the page carrying them, each
  declaring its own `table`. See [Records of any table](#records-of-any-table).
- `files` on a record creates file references, as a map of field name to the
  references declared for it.
- `inline` nests records into a **relation** rather than below a page, as a map
  of field name to the children declared for it.

Everything that is not one of those keys reaches the record untouched, which is
the answer to the question the format invites: **a field needs no support in the
seeder to be seedable.** `backend_layout`, `nav_hide`, `abstract`, `keywords`
and the `table_*` fields of the `table` element are all ordinary columns and are
all written by declaring them. `DataMapFactory::write()` sets `pid` and defaults
`hidden`, and copies the rest of `SeedRecord::$values` verbatim. A seeder that
special-cases a field it does not have to is a seeder that will special-case the
next one too, so the absence of that branch is deliberate and
`SeedingTest::fieldsTheSeederKnowsNothingAboutAreWrittenAsDeclared()` is what
keeps it true.

## Inline children

`children` and `content` express the page tree, where nesting becomes a `pid`.
A relation is a different shape: the child is not *below* the parent, it is
*pointed at* by one of the parent's fields. That is what `inline` expresses — a
map of the parent field carrying the relation to the records declared for it:

```yaml
content:
  - identifier: showcase-linklist
    CType: theme_linklist
    header: 'Where to read more'
    inline:
      tx_theme_list_items:            # the field on the parent record
        - identifier: showcase-docs
          table: tx_theme_list_item   # required, never inferred
          link: 't3://page?uid=2'
          link_label: 'Typography'
        - identifier: showcase-media
          table: tx_theme_list_item
          link: 't3://page?uid=3'
          link_label: 'Media'
```

Four rules, each of which is a decision rather than a detail:

**A child declares its own `table`.** It would be derivable — the parent's field
has a `config.foreign_table` in the TCA — and deriving it is wrong twice over: a
seed definition would then only be parseable with the TCA loaded, and a field
name that does not exist would produce a null dereference somewhere in the
factory rather than a message naming the child. `table` is therefore structural
on an inline child exactly as `identifier` is, and it is structural *only*
there: `tt_content` and `pages` both have real fields whose name begins with
`table`, so the key is decided per level, where the context is known
(`YamlSeedParser::STRUCTURAL_KEYS` and the `$table === null` branch in
`parseRecords()`).

**The parent's field is written as the comma-joined list of the children's
placeholders**, in declaration order, and nothing else. Which columns the
relation actually uses is a property of that relation and comes from the TCA of
the parent field — for `tt_content.tx_theme_list_items` they are `uid_foreign`,
`tablename` and `sorting_foreign`, and the singular `tablename` there is not the
`tablenames` of `sys_file_reference`. DataHandler reads the list, resolves the
placeholders and writes those columns itself. A seeder filling them in would
produce identical rows for this relation and the wrong rows for the first one
whose TCA names them differently, so it names none of them.

**Order comes from that list.** Not from `sorting`, and not from the negative
`pid` trick that orders pages and content elements — that convention names a
record of the same table and is a sorting instruction, which a relation does not
need. `DataHandler` numbers `sorting_foreign` by walking the value of the
parent's field.

**A child's `pid` is the page its parent sits on.** A relation is not a
containment: the child is an ordinary record on an ordinary page, and only the
relation columns tie it to the parent. Writing the parent's placeholder there
would put content records on a content record.

A child is a record like any other otherwise — it may declare a `uid`, and it
may carry `files`, which the four cards of `theme_media_teaser_grid` in the demo
definition do.

## Records of any table

`content` puts `tt_content` records on a page. `records` does the same for every
other table, and the child names the table itself:

```yaml
pages:
  - identifier: persons-storage
    title: 'Persons'
    doktype: 254
    records:
      - identifier: profile-doe
        table: tx_academicpersons_domain_model_profile
        first_name: 'Jane'
        last_name: 'Doe'
        inline:
          contracts:
            - identifier: contract-doe
              table: tx_academicpersons_domain_model_contract
              position: 'Professor'
```

That is what lets a definition describe **the data a plugin reads**, and not
only the pages and content elements around it. A plugin page whose records have
to be clicked together by hand afterwards is a page tree, not a development
instance.

Four things follow from the design rather than needing their own machinery:

**A record under `records` is a record like any other.** It may declare a `uid`,
carry `files`, and carry `inline` children — the profile above nests its
contracts through a relation exactly as a content element nests its list items.
Nothing about those keys knows which table it is applied to.

**Its `pid` is the page that declares it**, the same rule `content` follows.
Nesting expresses the page tree; a relation is what `inline` expresses.

**Declaration order is kept per table.** `DataMapFactory` tracks the predecessor
of the negative-`pid` chain per table, so pages, content elements and three
other tables can sit on one page without disturbing each other's sorting.

**A relation to a seeded record is written by declaring its uid.** The record
declares `uid: 4711`, the field pointing at it is written with `4711`, and
DataHandler does the rest — including an **MM** relation, whose rows it writes
into a table the seeder never names. `SeedingTest::aRelationToASeededRecordIsWrittenFromTheDeclaredUid()`
asserts that on `pages.categories`.

The one restriction is where the key may appear: **`records` is structure on a
page and an ordinary field everywhere else.** `tt_content` has a column of that
name — the one the *Insert records* element writes `tt_content_<uid>` into, and
the demo tree uses it — so the key is decided per level, exactly as `table` is
(`YamlSeedParser::STRUCTURAL_KEYS` and the `$table === self::PAGES` branch in
`parseRecords()`). Declaring `records` on a content element therefore does not
nest anything; it writes a field.

## Placeholders carry no underscore

The placeholder of a record is `NEW<identifier>`, and the identifier may carry
no underscore. That restriction exists to work around one line of DataHandler.

The placeholder used to carry the table name as well —
`NEW<table without underscores>-<identifier>`. It never contributed uniqueness:
`YamlSeedParser` tracks identifiers in a single set across every level and every
table, so two records cannot share one. It only contributed length, and length
turned out to matter — see [the identifier length limit](#identifiers-are-at-most-27-characters).

`processRemapStack()` resolves the `NEW…` placeholders in a relation field. It
first asks whether the value contains an underscore
(`.Build/vendor/typo3/cms-core/Classes/DataHandling/DataHandler.php:7165-7189`,
the *Replace relations to NEW...-IDs* block). If it does not, the value is a
plain placeholder and the table comes from `config.foreign_table`. If it does,
the value is read as the `<table>_<uid>` form the backend writes for a group
field: it is split on every underscore, the **last** segment is taken as the id
and everything before it as the table name.

A placeholder like `NEWtt_content_home` therefore does not resolve. It is taken
apart into a table `NEWtt_content` and an id `home`, `substNEWwithIDs['home']`
does not exist, and the `?? ''` puts an empty string in its place. The relation
is written **empty, with an empty error log** — nothing about that path is an
error condition.

That is the worst kind of failure, and it cost twice here:

- **Every inline relation would have been empty**, which renders as a correct,
  empty wrapper — indistinguishable from an editor who added no entries. That is
  precisely the failure mode `ThemeContentElementRenderingTest` exists to catch.
- **Every seeded file reference had kept `sorting_foreign = 0`** since file
  seeding was added. That one was invisible:
  `FileRepository::findByRelation()` selects by
  `uid_foreign`/`tablenames`/`fieldname` and never reads the parent's counter
  column (`.Build/vendor/typo3/cms-core/Classes/Resource/FileRepository.php:86-113`),
  so the images appeared and looked right. It orders by `sorting_foreign`
  though, and that column is only written by
  `RelationHandler::writeForeignField()`, which runs after the placeholders in
  the parent's field resolve. The order of a multi-file gallery was left to the
  database.

`YamlSeedParser` therefore rejects an identifier carrying an underscore.
Restricting the identifier is what makes the guarantee hold: a definition that
would seed an empty relation is rejected with a message instead of seeding one.

## Identifiers are at most 27 characters

`YamlSeedParser` rejects an identifier longer than 27 characters, by name and
with the definition it came from, rather than letting it fail later.

The limit comes from TYPO3 v12. `sys_log` has a `NEWid varchar(30)` column
there (`.Build/vendor/typo3/cms-core/ext_tables.sql`), and
`BackendUserAuthentication::writelog()` writes the raw data map key into it on
every record DataHandler creates — so `NEW` plus the identifier has to fit into
thirty characters. TYPO3 v13 has no `NEWid` column at all and its `writelog()`
ignores that parameter position entirely, which is why the `main` line never
saw this.

Exceeding it is not a soft failure. SQLite does not enforce a declared
`varchar` length, so a too-long identifier passes there and throws a
`DriverException` from inside `process_datamap()` on PostgreSQL, MySQL and
MariaDB — the kind of defect that only a four database run finds. The parser
checks it up front instead.

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

A reference is either the bare identifier of a declared file, as above, or a map
naming that identifier alongside the fields of the `sys_file_reference` record —
the alternative text, title, description and link an editor fills in on a file
relation:

```yaml
    files:
      image:
        - placeholder                            # short form, no fields
        - identifier: placeholder-portrait       # long form
          alternative: 'A placeholder graphic'
          title: 'Placeholder'
          description: 'Rendered as the caption'
```

Those fields live on the **reference**, not on the file, which is what lets the
same image carry a different alternative text in two places. `identifier` is the
only structural key; everything else is written to the reference as it stands,
and a field the TCA of `sys_file_reference` does not know is dropped by
DataHandler without a word.

The columns the seeder owns — `uid_local`, `uid_foreign`, `tablenames`,
`fieldname` and `pid` — always win over a declared value, so a definition cannot
detach a reference from the record carrying it. This is the same rule a record's
own `pid` follows.

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
- **The conflict mode changed type between the supported versions**, and it is
  the one thing in the seeder that needed a core version split. v13 introduced
  the native enum `Resource\Enum\DuplicationBehavior` (#101151) and triggers a
  deprecation for the older `Resource\DuplicationBehavior` class, which this
  test suite turns into a failure; v12 has only the older one, and the enum does
  not exist there at all. `FileSeeder` therefore type hints
  `Classes/Seeding/FileImporterInterface` and receives
  `Core12/Seeding/FileImporter` or `Core13/Seeding/FileImporter` from the
  container.
  → [Core version aware code](../architecture/core-version-aware-code.md#the-worked-example-duplicationbehavior)
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
and its v12 counterpart both reference `rootPageId: 1`, which only works because
the definition declares that uid. Without it the root page would get whatever the
database assigned and the site configuration could not be written in advance.

What a seed definition still **cannot** declare is a record outside the page
tree: `YamlSeedParser` has exactly two top-level containers, `files` and
`pages`, and [`records`](#records-of-any-table) hangs off a page like everything
else. A row that belongs on a page is a different matter — the `sys_template`
record that enables the theme on TYPO3 v12 sits on page 1 and is therefore
expressible now. It is not declared in the demo definition, so that step is
still performed in the backend; making it part of the seed is a change to the
definition and is not made here.
→ [Development instances](instances.md#enabling-the-theme-on-typo3-v12)

That is also why the command refuses to run into a non-empty page tree: a
definition declaring uids collides rather than adding. `--force` overrides that
for a definition known not to overlap.

**Handing DataHandler a suggested uid takes two things, and neither is
obvious.** `insertDB()` reads the suggestion from `$fieldArray['uid']` — the
data map row — and looks it up in `suggestedInsertUids` under the key
`"<table>:<uid>"`, not under the placeholder
(`.Build/vendor/typo3/cms-core/Classes/DataHandling/DataHandler.php:7793-7811`).
It then unsets the column again — *"Do NOT insert the UID field, ever!"* — so
writing it into the data map cannot force a uid by itself, and populating only
`suggestedInsertUids` with a placeholder key finds nothing.

Getting either half wrong fails silently: DataHandler assigns the next free uid,
the seeder reports whatever it got, and the result is correct exactly as long as
declaration order happens to equal insertion order. It did, for the whole first
version of the demo definition, which is why nothing noticed. The regression
test therefore declares a single page with uid `4711` — a number that cannot be
reached by counting.

## The demo tree

[`Configuration/Seeds/Demo.yaml`](../../Configuration/Seeds/Demo.yaml) is not a
sample of the format, it is the frontend this extension is developed against.
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

Uids 1 to 4 do not move: the committed site configurations of both development
instances point at root page 1, and the tests assert against the others.

Four properties of that tree are deliberate, and are asserted by
`Tests/Functional/ShowcaseTreeTest.php` and `Tests/Functional/SeedingTest.php`
rather than left to a reader to preserve:

- **Page 4 declares no `backend_layout` at all**, and that is the point of it.
  It is the only page in the tree that reaches the hard-coded `default` in
  `PageLayoutResolver::getLayoutIdentifierForPage()`
  (`.Build/vendor/typo3/cms-core/Classes/Page/PageLayoutResolver.php:118-120`),
  after the `backend_layout_next_level` walk up the rootline found nothing. That
  path has no other coverage in a seeded tree.
- **Pages 6 and 7 use `content_sidebar` and their sibling 8 does not.** Two
  pages under one parent rendering with and without the sub navigation is what
  proves the layout is resolved per page rather than inherited down the branch.
- **The styleguide page uses `nav_hide`, never `hidden`.** A hidden page returns
  404 in the frontend and is only reachable through a backend preview link
  carrying a valid hash, which defeats the point of seeding a page that exists
  to be opened. `nav_hide` keeps it reachable by URL and out of every menu,
  which is what "not published" meant here.
- **The two lists that hold the tree complete are read from the repository, not
  from the test.** `ShowcaseTreeTest` derives the backend layouts from
  `Configuration/PageTsConfig/BackendLayouts/` and the content types from the
  TypoScript. A layout or an element added without a demo page then fails there,
  instead of shipping undemonstrated — a list maintained in a test goes stale
  silently, because a demo page nobody seeded is a page nobody misses.

## What it does not do

- **No categorized menus in the demo tree**, although the format can express
  them since [`records`](#records-of-any-table). `menu_categorized_pages` and
  `menu_categorized_content` are still seeded with `selected_categories: 0` — an
  empty selection, which renders an empty menu, and an empty menu is the correct
  rendering of "nothing chosen". Seeding categories and relating pages to them
  would demonstrate the two elements better; it is a change to the demo
  definition, and it is not made here. The `0` is written out rather than the
  field left off because it is the value the column carries once anything real
  has touched it, and because it keeps the two elements identical so neither
  reads as the special case; the empty value itself is handled in
  `Configuration/TypoScript/ContentElements.typoscript`, where
  `selected_categories` reaches the subquery through an `ifEmpty = 0`.
- **No file metadata.** The fields of a *reference* are written; the
  `sys_file_metadata` of the file itself — the alternative text and title that
  apply wherever the file is used — is not. That is deliberate rather than
  missing: an alternative text describes what the image means *in this place*,
  which is a property of the reference.
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
- [Page rendering](../architecture/page-rendering.md) — the backend layouts the
  demo tree uses, and how a layout resolves to a template.
- [Content elements](../architecture/content-elements.md) — the `CType` set the
  showcase pages have to cover.
- [Functional tests](../testing/functional-tests.md)
