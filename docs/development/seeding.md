# Seeding

A development instance is worth nothing empty. The showcase page tree of the
theme is a **seed set** of [`sbuerk/data-factory`](https://github.com/sbuerk/data-factory),
so an instance is rebuilt from a definition in the repository instead of being
clicked together by hand:

```bash
# The showcase, in any installation that has sbuerk/data-factory installed.
vendor/bin/typo3 data-factory:import theme-demo
```

`theme-demo` ships with the extension. The development instances are built from
`theme-instance`, the showcase plus a second tree and the accounts — see
[The instance set](#the-instance-set-the-showcase-delivered-twice) — and the v12
instance from `theme-instance-core12`, that set plus the one record TYPO3 v12
needs — see [On TYPO3 v12](#on-typo3-v12-both-trees-through-sys_template). They
do not import it by hand: `ddev start` runs `composer system:setup`, which
imports the set of the instance into one that is not seeded yet, and
`composer system:reseed` rebuilds one from nothing. See
[Development instances](instances.md).

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
└── Files/          two placeholder SVGs, a PDF, a text file, a zip archive,
                    a video, an audio file and a caption track
```

**The video does not play, and is not meant to.** `placeholder-clip.mp4` is an
`ftyp` and a `free` box — enough to be detected as `video/mp4` and nothing
more — so a browser draws the player, its controls and its caption menu, and
then fails to decode the film. A playable film needs an encoder, and committing
one would put a megabyte of video into an extension whose showcase is about
markup; what the page demonstrates is the markup, the controls and the caption
track, and all three are there. `placeholder-tone.wav` beside it **is** a real,
playable file — a second of a 440 Hz tone — so the showcase demonstrates
playback once, on the element that costs four kilobytes to ship.

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

| Table                | Uids                                                                                                                                                                                                                |
|----------------------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `pages`              | 1 to 10, then 30 to 39, 50 to 56, 70 to 73, 90 to 92, 110 to 112, 130, 150 to 152 and 170 to 177 — forty-nine pages; the odd decades are reserved, the ids used are fewer, see [below](#why-new-pages-skip-decades) |
| `tt_content`         | its page times 100 plus its position: the third of page 6 is 603, the second of page 35 is 3502                                                                                                                     |
| `tx_theme_list_item` | 1 to 19 on page 8, and 100 to 129, 150 to 177, 450 to 454, 460 to 465, 470 to 474 and 500 to 503 on the pages below it, in declaration order                                                                        |

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

### Why new pages skip decades

The development instances import the showcase together with a generated
mirror of it that moves **every uid by 1000**, in every table — see
[The instance set](#the-instance-set-the-showcase-delivered-twice). A content
element's uid is its page times 100 plus its position, so 1000 is ten pages:
the mirror of the content of page *p* takes the uids of the content of page
*p* + 10. With pages 1 to 10 alone that never mattered. For a page added after
them it decides which uids are free:

| Page uids | Taken by                                                                                      |
|-----------|-----------------------------------------------------------------------------------------------|
| 11–19     | nothing as pages, but their content uids 1101–1999 are the mirror of the content of pages 1–9 |
| 20–29     | the account pages of the instance set, `Accounts.yaml` — 20, 21, 22, content 2101 and 2201    |
| 30–39     | new pages; the mirror of their content is 4001–4999                                           |
| 50–59     | new pages; the mirror of their content is 6001–6999                                           |
| 70–79     | new pages; the mirror of their content is 8001–8999                                           |
| 90–99     | new pages; the mirror of their content is 10001–10999                                         |
| 110–119   | new pages; the mirror of their content is 12001–12999                                         |
| 130–139   | new pages; the mirror of their content is 14001–14999                                         |
| 150–159   | new pages; the mirror of their content is 16001–16999                                         |
| 170–179   | new pages; the mirror of their content is 18001–18999                                         |

So a new page takes a uid in an odd decade from 30 up — 30–39, 50–59, 70–79,
90–99, 110–119, 130–139, 150–159 and so on — and the even decade after it stays
free for its mirror. The rule is stated at the top of `Scenario.yaml` as well, and
`Tests/Unit/GeneratedLegacyScenarioTest::theInstanceSetDeclaresNoUidTwice()`
walks the composed set of the v12 instance, `theme-instance-core12` —
showcase, mirror, accounts and the root template, a superset of
`theme-instance` — and fails on any uid declared twice in one table, which is
what a page in the wrong decade produces. A page uid itself is safe up to 999:
the mirror's pages are 1001 and up.

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

## Plain text and rich text

Whether a text field is rich text is decided by the TCA of its type:
`enableRichtext` in the column or in the `columnsOverrides` of the `CType`, and
for the `text` of a list item in the `overrideChildTca` of the relation. The
heroes, the teasers, the testimonial, the author and the lead-in of the media
teaser grid have none of these, and neither do the list items of every relation
but the accordion and the tabs: their fields are plain textareas.

A plain text field is seeded with plain text. Markup is shown to an editor as
tags in the textarea, and the templates render these fields through
`f:format.html`, whose `lib.parseFunc_RTE` makes every line of the value a
paragraph, or through `f:format.nl2br`, where every line ends in a line break.
So a value wrapped in the source renders one paragraph per source line, and a
trailing newline an empty paragraph after the text. A long value is written as
a folded scalar with strip chomping, `>-`: the source wraps, the value is one
line. A blank line in it is a single newline in the value, and with it a new
paragraph.

`PlainTextSeedTest` reads which field is which from the TCA of the running
core - the `$GLOBALS['TCA']` array, as v12.4 has no schema API - and holds
every seeded plain text value to that: no markup, no white space around it, no
empty line, and no line that ends inside a sentence. The fields that hold lines
rather than paragraphs are the ones with `wrap` set to `off` - `bullets` and
`table` on both cores, `html` on v13.4 - and the `bodytext` of `html`, which
v12.4 marks that way only through EXT:t3editor, a system extension the test
instance does not load.

## The demo tree

Not a sample of the format — the frontend this extension is developed against.
The first ten pages carry between them every `CType` the extension renders and
six of the twelve backend layouts it registers — `start`, `content`,
`content_sidebar`, the `default` fallback, `styleguide` and `forms`. The other
six are the multi column, article, cover and band layouts, which have a demo
page each below `/layouts`. The pages below the first ten show every classic
`CType` in its variants, and every appearance value:

| uid       | Title                    | Slug                             | `backend_layout`                | What it is for                                                                                                                                |
|-----------|--------------------------|----------------------------------|---------------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------|
| 1         | Theme demo               | `/`                              | `start`                         | The site root, and the footer columns.                                                                                                        |
| 2         | Typography               | `/typography`                    | `content`                       | Running text, the four bands with header positions, looks and spacing; parent of 52 to 56.                                                    |
| 52–55     | Text … Quotes and code   | `/typography/<page>`             | `content`                       | Text, lists, tables, quotes and code — rich text and elements.                                                                                |
| 56        | Article                  | `/typography/article`            | `content_sidebar`               | A long article: a byline, footnotes, and the `sectionIndex` table of contents the sidebar layout is chosen for.                               |
| 152       | Right to left            | `/typography/right-to-left`      | `content`                       | The components in a `dir="rtl"` wrapper: logical spacing, mirrored markers, `bdi` in a bidirectional line.                                    |
| 3         | Media                    | `/media`                         | `content`                       | One image, and a two column gallery.                                                                                                          |
| 4         | Empty page               | `/empty`                         | *(none)*                        | The `default` layout fallback; `nav_hide`, reached by URL and from page 8.                                                                    |
| 5         | Elements                 | `/elements`                      | `content`                       | The showcase branch, parent of 6 to 8 and 51.                                                                                                 |
| 6         | Core elements            | `/elements/core`                 | `content_sidebar`               | Every classic `CType` the theme renders, once each; parent of 30 to 39 and 50.                                                                |
| 30–39, 50 | Header … Insert records  | `/elements/core/<CType>`         | `content_sidebar`               | One page per classic `CType`, every value of the fields that change how it looks.                                                             |
| 7         | Menu elements            | `/elements/menu`                 | `content_sidebar`               | The eleven `menu_*` elements, and cards and thumbnails of two of them.                                                                        |
| 8         | Theme elements           | `/elements/theme`                | `content`                       | The `theme_*` elements, one each; parent of 70 and up.                                                                                        |
| 70–73     | Text and icon … Steps    | `/elements/theme/<name>`         | `content_sidebar`, 71 `content` | One page per theme element with icons, every value of the fields that change its look; 71 without a sidebar, which four feature columns need. |
| 90–92     | Card group … Teaser list | `/elements/theme/<name>`         | `content_sidebar`               | One page per collection element, every variant; page media and an abstract for the menus.                                                     |
| 150–151   | Carousel, Split tiles    | `/elements/theme/<name>`         | `content_sidebar`               | The carousel in every caption position, and the split tiles in every tone and both rhythms.                                                   |
| 110       | Hero layouts             | `/elements/theme/hero`           | `content_sidebar`               | Every layout of the three heroes, with an eyebrow each.                                                                                       |
| 111       | Calls to action          | `/elements/theme/cta`            | `content_sidebar`               | The call to action in every tone and width.                                                                                                   |
| 112       | Quotation styles         | `/elements/theme/quote`          | `content_sidebar`               | The styles and the portrait of the testimonial.                                                                                               |
| 130       | External media           | `/elements/theme/external-media` | `content_sidebar`               | Both shapes of the embed, an element without a poster, and a host that is not embedded at all.                                                |
| 170       | Pricing                  | `/elements/theme/pricing`        | `content`                       | Plans side by side, each with a price, a period, a feature list and a link; no sidebar, which the plan columns need.                          |
| 51        | Frames                   | `/elements/frames`               | `content`                       | Every frame, spacing, header alignment and header look of the Appearance tab.                                                                 |
| 171       | Layouts                  | `/layouts`                       | `content`                       | The page layout branch: what a backend layout decides, and a menu of 172 to 177.                                                              |
| 172–177   | Two columns … Bands      | `/layouts/<layout>`              | `two_columns` … `bands`         | One page per multi column, article, cover and band layout, with a labelled box in every column that layout declares.                          |
| 9         | Styleguide               | `/styleguide`                    | `styleguide`                    | The component library, straight from Fluid.                                                                                                   |
| 10        | Forms                    | `/forms`                         | `forms`                         | The form showcase, straight from Fluid.                                                                                                       |

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
- **The five showcase sections are in the main navigation** — `/elements`,
  `/typography`, `/layouts`, and `/styleguide` with `/forms` — and no page is
  `hidden`, which `ShowcaseTreeTest::showcaseSections()` holds the tree to. A
  hidden page returns 404 in the frontend and is only reachable through a
  backend preview link carrying a valid hash, which defeats the point of
  seeding a page that exists to be opened.
- **The two lists that hold the tree complete are read from the repository, not
  from the test.** `ShowcaseTreeTest` derives the backend layouts from
  `Configuration/PageTsConfig/BackendLayouts/` and the content types from the
  TypoScript. A layout or an element added without a demo page then fails there,
  instead of shipping undemonstrated.

> [!NOTE]
> **An unexplained observation, recorded rather than diagnosed.** While the
> `/layouts` pages were being seeded, an element written into a `colPos` no
> layout of the page declares once made that page answer 404, *No site
> configuration found*, rather than merely leaving the element unrendered.
> It did not reproduce, and it cannot be caused by this extension: the theme
> reads its columns through `lib.content.*` `CONTENT` objects with a fixed
> `where`, so a `colPos` nothing selects is simply never queried; the
> frontend of TYPO3 v12.4 reads nothing of a layout but its identifier, and
> v13.4 collects the columns of the layout structure without checking any
> content against them; and `DataHandler` validates no `colPos` against the
> layout of its page, neither in 12.4.45 nor in 13.4.35.
> The likely cause is an import that aborted part way and left the page
> record without its site-resolvable state. Recorded here so the next person
> who sees it knows it has been looked at — not as a known defect.

## The instance set: the showcase, delivered twice

The development instances do not import `theme-demo`. They import
`theme-instance`, a set of the development-only package
[`packages-dev/dev-site`](../../packages-dev/dev-site) that contains the
showcase and adds what only a development instance needs:

```
packages-dev/dev-site/Configuration/DataFactory/theme-instance/
├── config.yml              imports the showcase descriptor, adds the rest
├── ScenarioLegacy.yaml     GENERATED - the "/legacy/" tree
├── ReferencesLegacy.yaml   GENERATED - its file references
└── Accounts.yaml           backend editor and group, frontend users, login pages
```

The accounts, their credentials and why each is shaped the way it is are on
[Development instances](instances.md#accounts). `Accounts.yaml` extends the
`page` entity of the showcase with the editor group's page permissions, so
every page of the composed scenario — showcase, mirror and account pages —
belongs to that group.

`config.yml` pulls the descriptor of the showcase in with `imports`. The core's
`YamlFileLoader` merges an import with
`ArrayUtility::replaceAndAppendScalarValuesRecursive()`: a **list** of the
imported file is appended to, and a **scalar** of the importing file wins. So
`scenarios`, `files` and `references` of `theme-instance` add to those of
`theme-demo` — the showcase scenario first — and its `identifier` and `title`
replace the showcase's. Nothing of the showcase is repeated, which is also why
the showcase names every path with `EXT:`: a relative path would be resolved
against the directory of `theme-instance/config.yml`.

The two sets are never imported into one installation. `theme-instance`
contains the showcase, and a second import of it is a uid collision the import
refuses.

### On TYPO3 v12: both trees through `sys_template`

```
packages-dev/dev-site/Configuration/DataFactory/theme-instance-core12/
├── config.yml         imports the descriptor of theme-instance
└── RootTemplate.yaml  a root "sys_template" record on page 1, uid 2
```

TYPO3 v12 has no site sets — they arrived in v13.1 (#103437) — so the `/` tree
cannot be delivered through one there. `theme-instance-core12` imports
`theme-instance` the same way that set imports the showcase, and adds a root
`sys_template` record on page 1 with the static include of the theme: what the
`/legacy/` root carries on both versions. On TYPO3 v12 both trees are therefore
delivered through `sys_template`, and the markup comparison below still holds
the mirror to the showcase, but no longer compares two delivery mechanisms.

The record sits in the development site package, not in the showcase, because
the showcase ships with the extension and the record would reach TYPO3 v13
installations as well. Which set a test imports for the running core version
is `ThemeDeliveryInterface::instanceSeedSet()`, one implementation per core
version below `Tests/Functional/Core12|Core13/`.

### Two trees, two delivery mechanisms

| Tree       | Root | Site          | Delivered through                                                                            |
|------------|------|---------------|----------------------------------------------------------------------------------------------|
| `/`        | 1    | `demo`        | the site set `sbuerk/theme-extension-development`; on TYPO3 v12 a root `sys_template` record |
| `/legacy/` | 1001 | `demo-legacy` | a root `sys_template` record including the static include of the theme                       |

The theme supports both, and they fail differently: a site set that is missing
is loud, an `include_static_file` entry that resolves to nothing is not — it is
a comma separated list read with `trimExplode`, and the page still answers 200.
The static include is also guarded by a condition on `site('sets')`
(`Configuration/TypoScript/Static/setup.typoscript`), so a site that names the
set is rendered by the set and never reaches the include. The second tree
exists so that both mechanisms are looked at, in the backend and the frontend,
every time an instance is.

`/legacy/` is a **mirror**, generated rather than written:

```bash
php Build/Scripts/generateLegacyScenario.php           # rewrite both files
php Build/Scripts/generateLegacyScenario.php --check   # exit 1 if they are stale
```

Every mirrored record carries the uid of its original plus 1000, in every
table — page 3 is 1003, content element 601 is 1601, list item 12 is 1012 — and
so do the pointers at one the generator knows: `t3://page` links and their
content anchors, the page lists of the menu elements, `tt_content_601` of
*Insert records*, the inline child lists. The root
page is mirrored like every other page and then given a title of its own and
the `sys_template` record, the one record without an original, at uid 1. A
column it lists as a pointer it cannot rewrite — `shortcut`, `mount_pid`,
`content_from_pid`, a translation parent, `categories` — stops it when it
carries a value, rather than reaching the mirror unrewritten. What it does not
list, it does not recognise, which is why the result is checked from the
rendered side as well.

The generator needs the root composer install for `symfony/yaml` and writes
committed files, so it is run by hand after changing the showcase — never edit
the generated files. `--check` compares the committed files with what it would
write as data under the same header, not byte for byte, because the bytes are
those of whichever `symfony/yaml` the installed dependency set brings. It writes
multi-line values as quoted strings and refuses to write a file that does not
parse back to the data it came from: `symfony/yaml` reads a literal block (`|`)
back without its final line break when the next line is dedented, and the plain
text of a card rendered through `nl2br()` lost its trailing `<br />` that way.
That is how the mirror was first found to differ.

### What holds the two trees together

| Test                                              | Fails when                                                                                 |
|---------------------------------------------------|--------------------------------------------------------------------------------------------|
| `Tests/Unit/GeneratedLegacyScenarioTest`          | the showcase changed and the generator was not run                                         |
| `DevelopmentInstance/LegacyDeliveryTest`          | a page of one tree renders different markup than its mirror, or not at all                 |
| `DevelopmentInstance/LegacyDeliveryTest`          | a link of the rendered mirror leads out of it                                              |
| `DevelopmentInstance/LegacyDeliveryTest`          | TYPO3 v13: the legacy site declares a set, or the `/` tree carries a `sys_template` record |
| `Core12/DevelopmentInstance/InstanceDeliveryTest` | TYPO3 v12: a tree root lacks its `sys_template` record, or a site declares a set           |
| `DevelopmentInstance/DeliveryRegistrationTest`    | an `include_static_file` entry does not resolve, or is not offered by `addStaticFile()`    |

`LegacyDeliveryTest` imports the instance set of the running core version and
**adopts the committed site configurations** of
`instance-core-<major>/config/sites/` rather than writing its own, so it
measures what an instance serves. The comparison normalises only
what a mirror differs in by being one — the `/legacy` path segment, the two site
titles, the two root page titles, the content element anchors `c<uid>` of
mirrored elements, a CSP nonce — and every rule says why in `normalise()`. An
image width or a column count is not normalised: a constant the static include
failed to deliver changes exactly those, and making the legacy tree's
`maxGalleryWidth` differ makes the comparison fail on `/media` and
`/elements/core`.

The comparison maps `/legacy/` to `/` before comparing, so it cannot tell a link
that stays in the mirror from one that leaves it. A separate test reads the raw
markup of the mirror and requires every page link to stay below `/legacy/` and
every content anchor to name an element of the mirror. It
found two leaks when it was added: the brand link of the site header was a
literal `/`, and a paragraph of the showcase linked to `href="/"` instead of to
`t3://page?uid=1`.

Breaking the static include name makes the comparison, the rendering check and
`DeliveryRegistrationTest` fail; adding a set dependency to the legacy site
makes the dependency test fail, the one case the markup comparison cannot see,
because the set then renders the very same markup. The number of compared
pages is held to the number of pages the showcase scenario declares: an empty
comparison would pass.

An instance that was seeded with `theme-demo` alone cannot import
`theme-instance` on top — it contains the showcase, and the uids collide. Rebuild
it, see [Development instances](instances.md).

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
- **No TypoScript record in the showcase.** On TYPO3 v13 a site enables the
  theme through its site set. TYPO3 v12 has no site sets, and a `sys_template`
  record on page 1 enables it there — the showcase could declare one, but it
  would then carry it on v13 as well, which changes the shipped showcase for one
  core version. The v12 instance gets it from `theme-instance-core12` instead;
  an installation importing `theme-demo` on TYPO3 v12 creates it by hand. See
  [Enabling the theme on TYPO3 v12](instances.md#enabling-the-theme-on-typo3-v12).
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
