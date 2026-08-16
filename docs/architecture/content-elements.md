# Content elements

How the classic core content types render without `fluid_styled_content`,
which TypoScript branch and template belong to which `CType`, and the two
elements — `table` and `shortcut` — that needed more than a template. This
page is the detail behind the content-element paragraph in
[Page rendering](page-rendering.md); read that page first for how a content
element reaches `lib.contentElement` at all — this page does not repeat the
`FLUIDTEMPLATE` vs. `PAGEVIEW` reasoning or the Fluid file layout.

## TCA without rendering

`EXT:frontend` registers the whole classic set of content types itself, in
`Configuration/TCA/Overrides/2xx-tt_content-content_type-*.php` — image,
textmedia, bullets, table, uploads, the eleven `menu_*` types, shortcut, div
and html — on TYPO3 v13.4 **and** v14 alike. On v14, `fluid_styled_content` is
not even installed; it was never a dependency of this theme on either version.
What that extension supplies, when present, is the *rendering* — a
`lib.contentElement` TypoScript object and one template per `CType` — never
the TCA.

The consequence: every one of those types can be created in the backend of an
installation using this theme, whether or not anything renders it. A `CType`
with no branch in
[`Configuration/TypoScript/ContentElements.typoscript`](../../Configuration/TypoScript/ContentElements.typoscript)
falls through to the core's own "no rendering definition" notice — the yellow
box. To an editor that looks like the element is broken, not merely unstyled,
because nothing distinguishes "not covered yet" from "misconfigured" in that
notice.

`Tests/Functional/CoreContentElementRenderingTest.php` guards exactly this: a
sweep assertion (`noRenderedElementFallsBackToTheCoreNotice`) renders a page
carrying one of every covered type and asserts the notice string does not
appear anywhere in the output. It is the test that fails the moment a type
becomes creatable — which every classic type already is — without anyone
having added a branch for it. Per-type assertions then check the actual
markup: a `<ul>`/`<li>` for bullets, a `<table>` with split cells, an `<hr>`
for div, unescaped `bodytext` for html, and a duplicated fragment for the
shortcut's target. A shared assertion,
`everyCoveredTypeIsRenderedThroughTheContentElementWrapper`, checks
`data-ctype="<CType>"` on the outer element for each type — the wrapper
`Layouts/ContentElement.html` renders unconditionally
(`<div id="c{data.uid}" class="theme-content-element theme-content-element--{data.CType}" data-ctype="{data.CType}">`)
— so this also proves each element actually went through
`lib.contentElement` rather than being emitted some other way.

## Coverage table

Taken from the TypoScript branches, not summarised from memory:

| `CType`                    | Template                                 | Processing                                                                      |
|----------------------------|------------------------------------------|---------------------------------------------------------------------------------|
| `header`                   | `ContentElements/Header`                 | —                                                                               |
| `text`                     | `ContentElements/Text`                   | —                                                                               |
| `image`                    | `ContentElements/Image`                  | `FilesProcessor` (`image`) → `GalleryProcessor`                                 |
| `textpic`                  | `ContentElements/TextPic`                | `FilesProcessor` (`image`) → `GalleryProcessor`                                 |
| `textmedia`                | `ContentElements/TextMedia`              | `FilesProcessor` (`assets`) → `GalleryProcessor`                                |
| `bullets`                  | `ContentElements/Bullets`                | `SplitProcessor` (core)                                                         |
| `table`                    | `ContentElements/Table`                  | `TableProcessor` (this extension)                                               |
| `uploads`                  | `ContentElements/Uploads`                | `FilesProcessor` (`media` + `file_collections`)                                 |
| `div`                      | `ContentElements/Div`                    | —                                                                               |
| `html`                     | `ContentElements/Html`                   | —                                                                               |
| `shortcut`                 | `ContentElements/Shortcut`               | `RECORDS` cObject, wired as a TypoScript variable, not a `dataProcessing` entry |
| `menu_pages`               | `ContentElements/MenuPages`              | `MenuProcessor` (`special = list`)                                              |
| `menu_subpages`            | `ContentElements/MenuSubpages`           | `MenuProcessor` (`special = directory`)                                         |
| `menu_section`             | `ContentElements/MenuSection`            | `MenuProcessor` (`special = list`, 2 levels)                                    |
| `menu_section_pages`       | `ContentElements/MenuSectionPages`       | `MenuProcessor` (`special = directory`, 2 levels)                               |
| `menu_sitemap`             | `ContentElements/MenuSitemap`            | `MenuProcessor` (no `special`, 7 levels)                                        |
| `menu_sitemap_pages`       | `ContentElements/MenuSitemapPages`       | `MenuProcessor` (`special = directory`, 7 levels)                               |
| `menu_abstract`            | `ContentElements/MenuAbstract`           | `MenuProcessor` (`special = directory`)                                         |
| `menu_recently_updated`    | `ContentElements/MenuRecentlyUpdated`    | `MenuProcessor` (`special = updated`)                                           |
| `menu_related_pages`       | `ContentElements/MenuRelatedPages`       | `MenuProcessor` (`special = keywords`)                                          |
| `menu_categorized_pages`   | `ContentElements/MenuCategorizedPages`   | `RECORDS` cObject with `categories`/`categories.relation`                       |
| `menu_categorized_content` | `ContentElements/MenuCategorizedContent` | `DatabaseQueryProcessor` with a `sys_category_record_mm` subquery               |

`header`, `text` and `image` predate this page — see
[Page rendering](page-rendering.md) and the `Feature-ContentElementRendering`
/ `Feature-ImageContentElementRendering` changelog entries. `textpic` through
`shortcut` are covered directly below; the eleven `menu_*` types get their own
section, [The eleven `menu_*` types](#the-eleven-menu_-types), further down —
they needed a `MenuProcessor` (or, for the two categorized types, a
category-aware query) configured per type rather than only a template, which
is enough of a difference to keep them out of the prose that follows and give
them their own section instead.

`textpic` and `textmedia` reuse the same `FilesProcessor` →
`GalleryProcessor` pair `image` already uses (see
[Page rendering](page-rendering.md#the-fluid-structure) and the gallery
markup contract in [Component library](../development/component-library.md)),
pointed at a different source field — `image` for `textpic`, the
media-type-unrestricted `assets` for `textmedia` — and add a `bodytext` block
the gallery partial itself does not carry. `imageorient`'s vertical component
decides the DOM order: `below` puts the text first, `above` and `intext` both
put the gallery first, the latter relying on a stylesheet to float it beside
the following text.
`uploads` is a file list, not a gallery: `FilesProcessor` merges `media` and
`file_collections` and applies `filelink_sorting`/`filelink_sorting_direction`
natively, so the template only formats what it is handed —
`f:format.bytes` for the optional size, `f:image` for a thumbnail, and only
when FAL classifies the file as an image (`file.type == 2`), since `f:image`
cannot process a PDF or a text file. `div` renders nothing but an `<hr>`; its
only field, `header`, has its label overridden by `EXT:frontend`'s own
language file to "Name (not visible in frontend)", which is also why neither
`div`, `html` nor `shortcut` render the shared header partial.

## `bullets`: the core's own `SplitProcessor`, no PHP of this extension's

`bodytext` for `bullets` is one item per line — exactly what
`TYPO3\CMS\Frontend\DataProcessing\SplitProcessor` is for: one field, one
delimiter, no nesting. Its default delimiter is `LF`, which is already how
`bodytext` is stored, so the TypoScript branch configures nothing beyond
`fieldName` and `as`. No processor of this extension's own was needed.

`bullets_type` (core TCA,
`Configuration/TCA/Overrides/235-tt_content-content_type-bullets.php`)
selects the list shape:

| `bullets_type` | Element |
|----------------|---------|
| `0`            | `<ul>`  |
| `1`            | `<ol>`  |
| `2`            | `<dl>`  |

For the definition list, **every line becomes a standalone `<dt>`**, never a
`<dt>`/`<dd>` pair. `bodytext` for `bullets` carries no second field and no
documented delimiter convention for splitting a term from its description,
and no `fluid_styled_content` reference rendering ships with either core
version installed here to defer to. Inventing an ad-hoc in-line delimiter the
editor was never told about would be worse than leaving `<dd>` out entirely,
so the template does exactly that.

The component library has no dedicated list component —
[Component library](../development/component-library.md) documents no `.theme-list` or
similar — so `Bullets.html` renders plain semantic `<ul>`/`<ol>`/`<dl>` inside
the same `.theme-content-element__body` wrapper `Text.html` uses, styled only
by whatever base typography already applies to those elements.

## `table`: why a real `DataProcessor` was necessary

`bodytext` for `table` is delimited/enclosed text, shaped by five fields:
`table_caption`, `table_delimiter`, `table_enclosure`, `table_header_position`
and `table_tfoot`. Two core processors were checked first, and neither is
enough on its own:

- `TYPO3\CMS\Frontend\DataProcessing\SplitProcessor` splits on **one**
  delimiter into a flat list — right for `bullets`, but `table` needs two
  nested levels (rows, then cells), delimiter-aware quoting so the enclosure
  character or the delimiter itself can appear inside a cell, and multi-line
  cells, none of which it attempts.
- `TYPO3\CMS\Frontend\DataProcessing\CommaSeparatedValueProcessor` is
  genuinely built for this field — its own docblock names `table`'s
  `bodytext` as the example — and does the row/column split with proper
  quoting via `TYPO3\CMS\Core\Utility\CsvUtility::csvToArray()`. It falls
  short on two points specific to this `CType`: `table_delimiter` and
  `table_enclosure` are stored as TCA **character codes** (verified against
  `Configuration/TCA/Overrides/240-tt_content-content_type-table.php`: `124`
  / `59` / `44` / `58` / `9` for the delimiter, `0` / `39` / `34` for the
  enclosure), and TypoScript's stdWrap has no property that turns a numeric
  code into the character it stands for — only PHP can decode that. It also
  has no concept of `table_header_position` or `table_tfoot`: shaping a
  `<thead>`/`<tbody>`/`<tfoot>` split still needs slicing a row off the
  result afterwards, which is the same "not reasonable in Fluid" territory as
  the two-level split itself.

`Classes/DataProcessing/TableProcessor.php` (`final readonly`, wired with a
Symfony `#[Autoconfigure]` attribute tagging it `data.processor`, per
[Dependency injection](dependency-injection.md)) does both: decodes the two
character codes with `chr()`, splits with `CsvUtility::csvToArray()`, then
extracts a header row (`table_header_position == 1`) or marks the header as a
column (`== 2`) and pops a footer row (`table_tfoot`) off what remains. Rows
of differing cell counts are padded to the widest row by `csvToArray()`
itself, so `Table.html` can iterate every row with a uniform column count.
`Tests/Unit/DataProcessing/TableProcessorTest.php` covers the split, the
quoting, all three header positions, the footer interaction with a
single-row table (the row becomes the header or the footer, never both, so
nothing is duplicated), and the character-code decode.

### The `fgetcsv()` edge that made `chr(0)` deliberate, not accidental

`table_enclosure` has a `0 = "None"` option, and it is the field's **default**
— an editor who never touches the enclosure dropdown gets `0`. The character
codes are decoded the same way the backend's own table wizard preview does it
(`TYPO3\CMS\Backend\Form\Element\TextTableElement::getTableWizard()`):
`chr()` on a non-zero code, a fallback otherwise. The backend's own fallback
for "0 = None" is an **empty string** — and that is exactly what
`TableProcessor` does *not* reuse, because PHP's `fgetcsv()` (which
`CsvUtility::csvToArray()` calls internally) throws
`ValueError: Argument #4 ($enclosure) must be a single character` when handed
an empty string, on every PHP version this extension supports. Reusing the
backend's fallback verbatim would mean the field's own *default*
configuration throws on every table content element created without
touching the enclosure dropdown. `TableProcessor` uses `chr(0)` (NUL)
instead: a single byte `fgetcsv()` accepts without complaint, and one an
editor typing into the backend's textarea will not produce, so it behaves as
"no enclosure" in practice while never throwing.

## `shortcut`: recursion, and a guard that is version-dependent

`records` holds one or more `tt_content_<uid>` references (the TCA `group`
field allows only `tt_content`). The TypoScript branch resolves them through
the core's `RECORDS` cObject rather than anything of this extension's own,
assigned as a TypoScript **variable**, not a `dataProcessing` entry:

```typoscript
tt_content.shortcut {
    templateName = ContentElements/Shortcut

    variables {
        records = RECORDS
        records {
            tables = tt_content
            source.field = records
            conf.tt_content =< tt_content
        }
    }
}
```

`conf.tt_content =< tt_content` is what makes a referenced record render
**exactly as it would on its own**: it copies the whole `tt_content` `CASE`
object this file builds (registered by `EXT:frontend` in its
`ext_localconf.php`, keyed on `CType`) as the render definition for each
fetched record, so a referenced `shortcut` goes through this same branch
again — recursion is possible by construction, not an edge case bolted on
afterwards.

**On TYPO3 v13.4**, that recursion is guarded by the core, and the guard was
verified in source rather than assumed, against
`RecordsContentObject::render()` while v13.4.34 was the installed core
(`instance-core-13/vendor/typo3/cms-frontend/Classes/ContentObject/RecordsContentObject.php`):

1. Before fetching anything, `render()` reads the record currently being
   rendered off `TypoScriptFrontendController::$currentRecord` and registers
   it in `TypoScriptFrontendController::$recordRegister`, keyed `table:uid`,
   incrementing a counter (lines 59–67). The outer `CONTENT` cObject that
   dispatches a page's content columns (`ContentContentObject::render()`)
   registers the same way, so a shortcut's own record is already registered
   by the time its own `RECORDS` call runs at all.
2. Before rendering each fetched item, the loop checks whether that item's
   own `table:uid` key is already registered (lines 111–114); if it is, the
   item is skipped — no `cObjGetSingle()` call, nothing appended to the
   output for it. A shortcut referencing itself therefore does not loop and
   is not silently dropped as "no output" for the whole element: that one
   reference among others produces nothing, while every other reference in
   the same `records` field still renders normally. The counter is
   decremented again once rendering finishes (lines 138–141).
3. `$recordRegister` lives on the frontend controller, not on the `RECORDS`
   call, so it survives an **indirect** cycle too: shortcut A referencing
   shortcut B referencing A back registers `tt_content:A` while rendering A,
   sets `$currentRecord` to `tt_content:B` before registering it in turn
   (line 120), and by the time B's own reference back to A is reached,
   `tt_content:A` is still registered — A has not finished rendering yet —
   so that reference is skipped the same way a direct self-reference is.

No guard of this extension's own is added on that basis: the core's already
covers both the direct and the indirect case — **on v13.4**.

> [!IMPORTANT]
> **Discrepancy found against the contract this page was written from.**
> The guard above does not exist on TYPO3 v14. `TypoScriptFrontendController`
> — and `$recordRegister` with it — was fully removed in v14.0
> (`Breaking-107831-RemovedTypoScriptFrontendController.rst`: "All remaining
> properties have been removed … making the class a readonly internal
> service used by the TYPO3 Core only"). Verified directly: with v14.3.6
> installed (`.Build/vendor/typo3/cms-frontend/Classes/ContentObject/RecordsContentObject.php`
> and `ContentContentObject.php`), neither class references
> `recordRegister`, `currentRecord`, or any replacement recursion tracking —
> a `grep -r recordRegister` across the entire installed v14 core and
> frontend package tree returns nothing. There is also no fallback: the
> older `TypoScriptFrontendController->cObjectDepthCounter` guard against
> content-object recursion was itself removed back in v11.4
> (`Deprecation-94957`), on the stated basis that "PHP will now stop with a
> fatal PHP nesting level error at some point, instead [of] TYPO3 frontend
> rendering silently stopping" — which is the actual behaviour a
> self-referencing or cyclic `shortcut` should be expected to hit on v14: an
> uncontrolled recursion ending in a PHP fatal error, not a silently skipped
> reference.
>
> `Tests/Functional/CoreContentElementRenderingTest.php` does not exercise
> this: its one shortcut fixture (`tt_content` uid 80) references a
> non-recursive record (uid 10, the bullet list), so the gap is untested on
> both core versions. No guard of this extension's own has been added here
> either — that would be new behaviour beyond what step 5a asked for — but
> anyone relying on "the core already guards recursive shortcuts" should
> read that as **v13.4 only** until this is re-verified or a test is added
> that would catch it on v14.

## The eleven `menu_*` types

The last of the classic set `EXT:frontend` registers without shipping a
rendering for it. Nine are the core's `MenuProcessor` with a different
`special`; the remaining two — `menu_categorized_pages` and
`menu_categorized_content` — select by category rather than rootline
position, which `MenuProcessor` cannot express at all, and are built on
`RECORDS` and `DatabaseQueryProcessor` instead. All eleven share one Fluid
partial, `Partials/ContentElement/Menu.html`, and one new SCSS component,
`.theme-content-menu`.

### The nine `MenuProcessor` types

One full definition, `tt_content.menu_pages`; every other type in
`ContentElements.typoscript` is `=< tt_content.menu_X` plus only the lines
that actually differ, so the difference between the nine is what is visible
in the TypoScript, not nine near-identical blocks. Each `special` was read
against its own `prepareMenuItemsFor*Menu()` method in
`AbstractMenuContentObject.php` and cross-checked against the historical
`fluid_styled_content` TypoScript that rendered these same nine CTypes before
TYPO3 v13 made the TCA an `EXT:frontend`-only concern, reproduced close to
verbatim for seven of the nine:

| `CType`                 | `special`   | Levels | Falls back to (no `pages` selected)               |
|-------------------------|-------------|--------|---------------------------------------------------|
| `menu_pages`            | `list`      | 1      | site root (`entryLevel` default)                  |
| `menu_subpages`         | `directory` | 1      | current page                                      |
| `menu_section`          | `list`      | 2      | current page (`special.value.override`)           |
| `menu_section_pages`    | `directory` | 2      | current page                                      |
| `menu_sitemap`          | *(none)*    | 7      | site root — the CType has no `pages` field at all |
| `menu_sitemap_pages`    | `directory` | 7      | current page                                      |
| `menu_abstract`         | `directory` | 1      | current page                                      |
| `menu_recently_updated` | `updated`   | 1      | current page                                      |
| `menu_related_pages`    | `keywords`  | 1      | current page                                      |

`menu_pages` and `menu_recently_updated`/`menu_related_pages` fall back to the
site root only through `special = list`'s own default when no override
applies; `menu_section` is the one `list`-based type that overrides that
default explicitly (`special.value.override.data = page:uid`,
`special.value.override.if.isFalse.field = pages`), because its whole purpose
— "page content marked for section menus" per the CType's own description —
is about the page(s) the element itself sits among, not an arbitrary
site-wide list. Every `directory`-based type already defaults to the current
page through `MenuProcessor`'s own request-attribute fallback, so none of
them needs the same override.

`menu_section` and `menu_section_pages` are the two whose `levels = 2` is
this theme's stand-in for what historical `fluid_styled_content` did instead
— see [the known gap](#known-gap-no-sectionindex-embedding) below.

### `menu_abstract` and `menu_recently_updated` needed no extra processor

Both templates render more than a link — an abstract line, a last-changed
date — and neither needed a `dataProcessing` entry to reach it.
`MenuProcessor::getDataAsJson()` selects each menu page with `'*'` and
JSON-encodes the whole row onto `item.data` for every item it produces, so
`item.data.abstract` and `item.data.SYS_LASTCHANGED` are simply there on the
same page row every other menu type already carries at `item.data` — nothing
had to be added to go and fetch them. `menu_recently_updated`'s `special =
updated` already sorts by `SYS_LASTCHANGED` descending
(`prepareMenuItemsForUpdatedMenu()`), so the field the template reads is the
very field the query itself is keyed on.

Both are read through the same shared partial, `Partials/ContentElement/Menu.html`
— its `Item` section takes `showAbstract`/`showDate` arguments that gate
whether `item.data.abstract`/`item.data.SYS_LASTCHANGED` are rendered at all;
every other menu template renders the partial with neither argument set, and
both default to unset/false.

The **date format is hardcoded**, not configurable through TypoScript: the
visible text is `Y-m-d` (`f:format.date`, `format="Y-m-d"`, e.g.
`2026-08-16`), and the `<time>` element's `datetime` attribute is always the
full ISO 8601 form (`format="c"`) regardless of what the visible text shows.
A site package that wants a different visible format has to override the
template, not a constant.

### The two categorized types are built differently — deliberately

`menu_categorized_pages` and `menu_categorized_content` both select records
by category membership (`selected_categories`/`category_field`), not by
rootline position, which `MenuProcessor` cannot express — `HMENU`'s own
`special = categories` was read and ruled out too, because
`CategoryMenuUtility::collectPages()` behind it hard-codes `'pages'` as the
table it queries, so it could only ever serve the pages variant, never the
content one.

`menu_categorized_pages` uses the core's `RECORDS` cObject, with its
`categories`/`categories.relation` properties — the same mechanism
`CategoryCollection::load()` backs on both sides, `RECORDS` just calls it
with whichever table the surrounding loop is currently on. Each matched page
renders through its own small `FLUIDTEMPLATE`,
`Templates/ContentElements/Partials/CategorizedMenuPageItem.html`, invoked
once per match by `RECORDS` itself, since `RECORDS` only ever concatenates
whatever its `conf.<table>` cObject returns for each row.

`menu_categorized_content` selects the identical way against `tt_content`
instead, but does **not** reuse `RECORDS` to do it — this is deliberate, not
an inconsistency between two elements that otherwise look alike.
`DatabaseQueryProcessor` with a subquery against `sys_category_record_mm` is
used instead, because `RECORDS` would render each match as a **whole content
element nested inside this one**: the wrong shape for a menu — a menu of
content should be a list of links, not a stack of embedded content elements —
and it would inherit the exact recursion exposure documented above for
`shortcut`, which TYPO3 v14 no longer guards at all. A categorized-content
element can match itself by category, or match another one that matches it
back, and rendering rows as links rather than whole elements means nothing
can nest and the cycle cannot form in the first place; no structural break
like the one added for `shortcut` was needed here because the rendering
shape itself rules the cycle out.

`fluid_styled_content` expressed the same selection as a **join** plus a
`GROUP BY uid` to collapse the duplicate rows a join produces when a record
matches more than one selected category. That group is invalid under
`ONLY_FULL_GROUP_BY`, because `SELECT tt_content.*` names columns the group does
not. PostgreSQL and MySQL accept it regardless — both infer the functional
dependency from `uid` being the primary key — and **MariaDB does not implement
that inference**, so it was the only one of the four supported databases that
rejected the query. It failed there and passed everywhere else, which is exactly
the shape of defect the four-DBMS matrix exists to catch.

`IN (subquery)` cannot multiply rows in the first place, so there is nothing to
group and the query is identical on every DBMS.

**`DatabaseQueryProcessor` wraps every row as `['data' => $row]`**
(`DatabaseQueryProcessor.php`, `$processedRecordVariables[$key] = ['data' =>
$record]`), the same shape `FluidTemplateContentObject` gives every content
element its own record in. `MenuCategorizedContent.html` therefore reads
`item.data.header`, never `item.header` — the latter resolves to nothing at
all and renders an empty link rather than failing, which cost a debugging
session in this repository before the wrapping was understood. An element
with no `header` falls back to `#<uid>` so a link is never silently empty.

### Markup: `.theme-content-menu`, shared by all eleven

One new component, `.theme-content-menu`
(`Resources/Private/Scss/components/_content-menu.scss`), is the shared
list rendering for all eleven types — a flat list of links, optionally
carrying a date (`menu_recently_updated`) or an abstract line
(`menu_abstract`), nested one level for `menu_sitemap`'s tree. It is
**deliberately not `.theme-nav-sub`**: that component is section-scoped site
chrome rendered once per page from the backend layout, while a `menu_*`
content element is authored content an editor drops into the content column
like any other element, zero, one or several times, at any depth the column
allows. Reusing the navigation component would drag navigation styling — and
navigation's separate position in `theme.scss`'s cascade order — into
content rendering for two components that only coincidentally both draw a
list of links. See [Component library](../development/component-library.md#markup-contracts)
for the full markup contract.

`menu_sitemap` is the one type that is a tree rather than a flat list, and
`Partials/ContentElement/Menu.html` renders it the same way
`Partials/Navigation/Main.html` renders the site's own main navigation: one
`Item` section calling itself for `item.children`, rather than the list
markup written out once per level. `children` is only ever populated when
`levels` is more than 1 (`menu_section`, `menu_section_pages`,
`menu_sitemap`, `menu_sitemap_pages`), so the same section serves a
single-level menu and a whole seven-level sitemap with no level argument
needed to gate it.

### Known gap: no `sectionIndex` embedding

Historical `fluid_styled_content` gave `menu_section` and `menu_section_pages`
one thing this theme does not reproduce: it additionally queried each listed
page's own `tt_content` rows flagged `sectionIndex` and linked into them by
anchor, so a section menu could jump straight to a heading inside a page, not
only to the page itself. **That is not implemented here.** `levels = 2` — a
second menu level, the listed pages' own children — stands in for it instead,
the same way `menu_categorized_content` is the only element in this file that
renders more than a link and `menu_abstract`/`menu_recently_updated` are the
only two among the nine that render more than a title. A fourth type quietly
doing the same would contradict that boundary, so this is stated here as a
gap, not folded in as a feature: a site package that needs anchor-level
section navigation has to add it itself.

### What the tests guard

`Tests/Functional/CoreContentElementRenderingTest.php` covers the eleven
`menu_*` types the same way it covers every other classic type, plus two
assertions specific to menus:

- The sweep, `noRenderedElementFallsBackToTheCoreNotice`, and the per-type
  `everyCoveredTypeIsRenderedThroughTheContentElementWrapper` (driven by
  `coveredContentTypes()`, nineteen types including all eleven menus) fail
  the moment any covered `CType` regresses to the core's "no rendering
  definition" notice or stops going through `lib.contentElement` — the same
  guard every other type in this page relies on.
- `aPageMenuListsTheSubPages` renders past the wrapper into the actual
  markup: a menu configured with the wrong `special` produces a perfectly
  valid, perfectly empty wrapper, which looks like "no pages match" rather
  than like a defect, so the sweep alone cannot catch it.
- `aCategorisedMenuSelectsWhatShareItsCategory` is the one that actually
  exercises the category selection: the fixture
  (`Tests/Functional/Fixtures/Database/PageWithCoreContentElements.csv`) puts
  one page and one content element in the same category and points both
  categorized elements at it, then asserts `menu_categorized_pages` lists
  the categorized page and not the uncategorized one, and
  `menu_categorized_content` links the categorized content element by its
  header and its `c<uid>` anchor. Without that fixture data both elements
  render a correct empty wrapper and nothing proves the selection was ever
  wired up — which is exactly the state this test was added in, and it
  caught two real defects before it passed.

## `html`: unescaped by design, restricted by convention

`Html.html` renders `bodytext` through `f:format.raw`
(`TYPO3Fluid\Fluid\ViewHelpers\Format\RawViewHelper`), which emits the value
completely untouched — no escaping, no parsing. This is deliberately **not**
`f:format.html`: that ViewHelper runs its value through
`lib.parseFunc_RTE`, `EXT:frontend`'s own ParseFunc setup for rich text, which
rewrites links and reinterprets tags — exactly what an editor who pasted raw
markup or a script tag does not want done to it.

"`html` is admin-only" is a **convention, not a hardcoded check**. The
`CType` column carries `authMode = explicitAllow`
(`EXT:frontend/Configuration/TCA/tt_content.php`, verified), and TYPO3
applies `authMode` identically to **every** value of that field, not
specially to `html`. A non-admin editor may only save a record whose `CType`
is explicitly present in their backend group's `explicit_allowdeny` list
(`BackendUserAuthentication::checkAuthMode()`); admins bypass the check
entirely. A freshly created backend group starts with an empty list, so
*every* `CType` needs explicit grants before a non-admin editor can use it —
`html` being the one site administrators are expected to leave off that
grant is a deployment decision this theme cannot see or enforce from the
frontend side. Raw output here is exactly as trusted as whoever was granted
the `html` `CType`.

## Escaping: three categories, not one

Most of what a content element renders is plain interpolation, which Fluid
HTML-escapes by default: table cells and header/footer cells in `Table.html`,
bullet items in `Bullets.html`, the table caption, file names and
descriptions in `Uploads.html`. Nothing in those templates uses
`f:format.raw` or `f:format.html`.

`textpic` and `textmedia` sit in the middle: their `bodytext` goes through
`f:format.html`, which **does** run rich text through
`lib.parseFunc_RTE` — the RTE-authored content is trusted and expected to
carry markup, but it is still parsed rather than emitted verbatim, unlike
`html`.

`html` (and, transitively, whatever a `shortcut` resolves to — its own
`f:format.raw` wraps already-rendered markup, not user input, so the two are
not the same kind of "raw") is the one genuine exception: unescaped,
unparsed, exactly as entered.

## A Fluid gotcha: `&amp;&amp;` is not `&&`

A boolean condition written with an HTML/XML-escaped `&amp;&amp;` instead of
a literal `&&` is valid XML, reads as if someone was simply being careful
about entities, and **silently changes what the condition evaluates to** —
not by throwing, not by warning, by quietly dropping the right-hand operand.

Fluid's `BooleanParser`
(`typo3fluid/fluid`, `Core/Parser/BooleanParser.php`) tokenizes a condition
with a regex whose `&&` alternative matches the literal two-character
sequence `&&` and nothing else — there is no XML entity decoding step in
that tokenizer. Feed it `{a} == 1 &amp;&amp; {b}` and the first two tokens
parse as an ordinary comparison (`{a} == 1`); the tokenizer then reaches `&`,
which matches none of the recognised token alternatives except the
catch-all "any single character", so `parseAndToken()`'s lookahead for `&&`
or `and` never matches and the loop simply stops — the entire
`&amp;&amp; {b}` tail is never consumed or evaluated. The condition's result
is whatever the left-hand comparison alone evaluates to.

This is not a hypothetical: it shipped in this repository's main navigation.
A condition meant to require both "this is a top-level item" **and** "this
item is active" was written with `&amp;&amp;`, evaluated to just the
top-level check, and every top-level item was marked active regardless of
whether it actually was. `Resources/Private/Partials/Navigation/Main.html`
now spells it `&&` — the literal characters, inside an attribute value that
is otherwise perfectly ordinary XML:

```html
<li class="theme-nav-main__item{f:if(condition: '{isTopLevel} == 1 && {item.active}', then: ' theme-nav-main__item--active')}">
```

**Fluid templates are XML-shaped, but a `condition` attribute's value is not
XML content — it is a Fluid boolean expression that happens to sit inside an
XML attribute.** Treating it as XML that ought to be "cleaned up" by
escaping its ampersands introduces exactly this bug. The installed TYPO3
core itself never does this: across every `.html` template shipped in the
core packages installed here, `&&` inside a `condition="…"` attribute
appears raw in 24 places and escaped in none.

## The theme's own content elements

Everything above registers no TCA of its own — `EXT:frontend` already made
every classic type and every `menu_*` type creatable, and this theme only
supplied a rendering. The ten types below are different: their TCA is this
extension's own, in
[`Configuration/TCA/Overrides/tt_content_theme_*.php`](../../Configuration/TCA/Overrides/)
and [`Configuration/TCA/tx_theme_list_item.php`](../../Configuration/TCA/tx_theme_list_item.php),
registered in a separate wizard group ("Theme") so an editor can tell them
apart from the core set at a glance. `Tests/Functional/ThemeContentElementRenderingTest.php`
guards them the same way `CoreContentElementRenderingTest.php` guards the
classic set, plus assertions specific to what only this half of the file has
to get right: an inline relation that resolves to nothing renders a correct,
empty wrapper — indistinguishable from "the editor added no entries" — and a
`link` field read as a plain URL still looks correct until someone clicks it.

| `CType`                   | Is                                       | Renders through                                         |
|---------------------------|------------------------------------------|---------------------------------------------------------|
| `theme_hero`              | Full hero: heading, text, media, actions | `.theme-hero` (`Partials/ContentElement/Hero.html`)     |
| `theme_hero_small`        | The same, reduced                        | `.theme-hero--compact`, same partial                    |
| `theme_hero_text_only`    | The same, no media                       | `.theme-hero` with no `--media`, same partial           |
| `theme_teaser`            | Text teaser, no media                    | `.theme-teaser` (`Partials/ContentElement/Teaser.html`) |
| `theme_media_teaser`      | Text beside a single image               | `.theme-teaser` with media, same partial                |
| `theme_media_teaser_grid` | Several media teasers in a grid          | `.theme-card-grid` of `.theme-card` items               |
| `theme_testimonial`       | A quotation with an attribution          | `.theme-quote`                                          |
| `theme_author`            | A person: portrait, name, role, links    | `.theme-author` + `.theme-content-menu`                 |
| `theme_linklist`          | A list of links                          | `.theme-content-menu`                                   |
| `theme_sociallinks`       | The same, labelled instead of iconed     | `.theme-content-menu`                                   |

`theme_hero`, `theme_hero_small` and `theme_hero_text_only` share one Fluid
partial and differ only in a `compact` argument and in whether an `image`
field exists on the CType at all; `theme_teaser` and `theme_media_teaser`
share the sibling partial the same way. Both are documented in full above the
markup in `Partials/ContentElement/Hero.html` and `Teaser.html` — this table
only records which component backs which `CType`, not the shared-partial
reasoning already written there.

### Naming: `theme_*`, `tx_theme_*`, `tx_theme_list_item`

CTypes are prefixed `theme_`, columns `tx_theme_`, and the shared inline child
table is `tx_theme_list_item` — short rather than the full extension key
(`themeextensiondevelopment_hero` is unusable in a `showitem` string and in
TypoScript), following [camino](https://github.com/TYPO3-CMS/theme_camino)'s
own `camino_` precedent for the identical reason. The collision risk this
accepts is real — another extension is free to also prefix its own fields
`theme_` — and it is accepted deliberately, the same way camino accepts it for
its own prefix, rather than overlooked.

### No `ext_tables.sql`: the schema derives from TCA

This extension ships no `ext_tables.sql` anywhere below `Configuration/` — the
whole schema for `tx_theme_list_item` and the four `tx_theme_*` columns added
to `tt_content` comes from `TYPO3\CMS\Core\Database\Schema\DefaultTcaSchema::enrich()`
reading the TCA at compare-schema time, on both v13.4 and v14.3.

One column needed more than "add a `type=input` field and let it happen",
because `DefaultTcaSchema` does not treat every part of an inline relation the
same way. Reading `enrichSingleTableFieldsFromTcaColumns()`
(`.Build/vendor/typo3/cms-core/Classes/Database/Schema/DefaultTcaSchema.php`):
a `type=inline` parent column with `foreign_field` and `foreign_table_field`
set — `tx_theme_list_items` in
[`Configuration/TCA/Overrides/tt_content.php`](../../Configuration/TCA/Overrides/tt_content.php) —
gets both of those child columns auto-created if the child TCA does not
already declare them (lines 835–860 there: an explicit
"add definition … if it is not defined" step for exactly those two keys).
`foreign_match_fields` is not part of that special case at all — a search of
the same method turns up nothing for it — so a field used *only* as a
`foreign_match_fields` target does not get a column for free. `fieldname` on
`tx_theme_list_item` is therefore declared as a real, persisted `type=input`
column
([`Configuration/TCA/tx_theme_list_item.php`](../../Configuration/TCA/tx_theme_list_item.php)),
the same way core's own `sys_file_reference` declares its own `fieldname`
column with the identical reasoning in that file's own comment — copied here
because it is the evidence for what a `foreign_match_fields`-only field needs,
not a convention assumed from the field's name.

### `type=link` fields are not URLs

`tx_theme_link` (the call-to-action link shared by the hero and teaser
variants) and the child table's own `link` column are both TCA `type=link`.
Their stored value is a `stdWrap.typolink` parameter string — the page, file,
URL, email or record syntax `TYPO3\CMS\Core\LinkHandling\LinkService` writes,
never a bare URL — so every template that reads one renders it through
`f:link.typolink` or `f:uri.typolink`
(`Partials/ContentElement/LinkButton.html`, `LinkList.html`), never as a plain
`href`. `Tests/Functional/ThemeContentElementRenderingTest.php::aLinkFieldIsResolvedToARealUrl`
exists specifically because getting this wrong still renders a page that looks
correct: the anchor carries `t3://page?uid=1` verbatim and nothing about the
markup looks broken until the link is followed.

TYPO3 v14's Fluid 5 change to null-handling on tag-based ViewHelpers
(`Breaking-108148-StrictTypesInFluidViewHelpers.rst`) names `f:link.typolink`
as an explicit exception — it renders through `ContentObjectRenderer::typoLink()`
rather than building a tag itself — so no version split was needed for either
partial to keep working on both v13.4 and v14.3.

### Inline children: `DatabaseQueryProcessor`, and its `item.data.*` trap

No core data processor resolves a generic database relation the way
`FilesProcessor` resolves FAL: that class is FAL-specific by construction, it
only ever wraps `FileCollector`, which only ever resolves `sys_file_reference`
rows. `tx_theme_list_items` is an ordinary inline relation, not FAL, so
`theme_author`, `theme_linklist`, `theme_sociallinks` and
`theme_media_teaser_grid` all resolve it with
`TYPO3\CMS\Frontend\DataProcessing\DatabaseQueryProcessor` instead, selecting
on the same `uid_foreign`/`tablename`/`fieldname` triple DataHandler wrote on
the way in (see the section comment above `tt_content.theme_author` in
[`Configuration/TypoScript/ContentElements.typoscript`](../../Configuration/TypoScript/ContentElements.typoscript)).

`DatabaseQueryProcessor` wraps every row as `['data' => $row]`, the same shape
it already uses for `tt_content.menu_categorized_content` above. Every
template that reads `listItems` therefore reads `item.data.link`,
`item.data.header`, `item.data.text` — never `item.link`, which resolves to
nothing through Fluid's ordinary missing-variable handling and renders an
**empty list with no error**, not a broken one. That is not a hypothetical
here either: it is the same class of defect `menu_categorized_content` warns
about above, and it happened during this element set's own development before
`ThemeContentElementRenderingTest.php` caught it.

### `GalleryProcessor` is deliberately not used

`theme_hero`, `theme_hero_small`, `theme_media_teaser` and `theme_author` all
resolve their `image` field with `FilesProcessor` alone — no
`GalleryProcessor` afterwards, unlike `tt_content.image`/`textpic`/`textmedia`
further up this page. Two independent reasons, either sufficient on its own:

1. None of these components lay out a grid of images. Each shows exactly one
   image in a fixed-shape box (`.theme-hero__media`, `.theme-teaser__media`,
   `.theme-author__portrait`, all `object-fit: cover`) — there is nothing for
   the row/column/width/height computation `GalleryProcessor` exists for to
   arrange.
2. Every showitem that carries `image` puts it alone on its own "Images" tab —
   `imageorient`, `imagecols`, `imageheight`, `imagewidth` and `imageborder`
   are not part of the form (compare
   [`tt_content_theme_hero.php`](../../Configuration/TCA/Overrides/tt_content_theme_hero.php)
   with the core's own `tt_content.image` TCA). `GalleryProcessor` reads every
   one of those through a `.field` binding, so wiring it here would bind to
   columns an editor can never set, deciding the layout from a plain database
   default rather than editor intent.

The templates read `files.0` directly instead — the first, and in practice
only, file reference.

### Link lists reuse `.theme-content-menu`; only the author needed a new component

`theme_linklist` and `theme_sociallinks` render their resolved `listItems`
through the shared `Partials/ContentElement/LinkList.html`, wrapped in
`.theme-content-menu` — the same component every `menu_*` content element
already uses, not a list component of its own. Structurally the two shapes are
identical: authored content in the content column, a flat list of links, no
navigation-chrome semantics to drag in. A purpose-built list component here
would only duplicate the list/link styling `.theme-content-menu` already
provides. `theme_author`'s own profile/contact links reuse the identical pair
for the same reason — see the header comment of
[`ThemeAuthor.html`](../../Resources/Private/Templates/ContentElements/ThemeAuthor.html).

`.theme-author` is the one genuinely new component this element set needed:
an author/person block — portrait, role line, bio — has no existing
equivalent. It deliberately does not render the person's own name: `header`
goes through the shared header partial like every other content element, so
the name is the content element's heading, sitting above `.theme-author`
rather than inside it (`Resources/Private/Scss/components/_author.scss`).

### Gaps, stated as gaps

- **No icons at all.** This theme ships no icon assets and no icon component
  — unlike camino, which ships both an icon set and a `link_icon` field.
  `theme_sociallinks` therefore renders the same text-label list as
  `theme_linklist`; `link_label` stands in for what would otherwise be a
  platform icon ("Mastodon", "LinkedIn", …), not a Unicode glyph or any other
  approximation of one.
- **`.theme-hero__eyebrow` has CSS but no TCA field behind it.** The class is
  part of `_hero.scss`'s own markup contract (and the reference markup in
  [Component library](../development/component-library.md#content)), but none
  of the three hero CTypes' TCA offers an "eyebrow" field — only `header`,
  `bodytext` and the `theme_link` palette. Omitted rather than invented.

### A field was removed: `theme_testimonial` lost its `image`

`theme_testimonial`'s TCA
([`tt_content_theme_testimonial.php`](../../Configuration/TCA/Overrides/tt_content_theme_testimonial.php))
originally exposed the core `image` field on its own "Images" tab, the same
way `theme_hero` and `theme_author` do. `.theme-quote` has no media slot in
its markup contract, though, so a filled-in `image` would never have appeared
— an editor attaches a portrait, the page looks finished, and the work is
silently gone. The field was dropped from the showitem rather than left
inert: the current showitem is `bodytext, --palette--;;headers` only, with no
`image` and no "Images" `--div--`, and `ContentElements.typoscript` wires no
`FilesProcessor` for `theme_testimonial` either. (The header comments in
`ThemeTestimonial.html` and above `tt_content.theme_testimonial` in the
TypoScript still describe the field as present-but-unrendered — that prose
predates the removal and is stale; the TCA itself is the current state and no
longer offers the field to an editor at all.)

### The wizard group, and what an unresolved icon identifier does

Every one of the ten types carries a `label`, a `description` and an `icon` on
its `addRecordType()`/`addTcaSelectItemGroup()` call, all under one wizard
group ("Theme",
`tt_content.group.theme` in `locallang_tca.xlf`, inserted `before:default`).
No page TSconfig registers any of this: since TYPO3 v13
(`Feature-102834-Auto-registrationOfNewContentElementWizardViaTCA.rst`, on
disk for both installed core versions), the "new content element" wizard is
generated from exactly those TCA keys, which replaced the former
`mod.wizards.newContentElement.wizardItems.<group>` TSconfig step this theme
therefore never needed to write.

The core requires an icon identifier, and none of this theme's own is
invented — all eight reused identifiers (`content-header`,
`content-text-teaser`, `content-beside-text-img-left`, `content-card-group`,
`content-quote`, `content-user`, `content-bullets`, `content-listgroup`) are
verified present in the core's own icon registry
(`.Build/vendor/typo3/cms-core/Resources/Public/Icons/T3Icons/icons.json`),
not shipped as image files of this extension's own. An identifier that is
*not* registered does not fail quietly: `IconRegistry::getIconConfigurationByIdentifier()`
throws (`Exception`, code `1437425804`, "Icon with identifier … is not
registered") the moment something tries to resolve it — there is no silent
fallback icon to lean on if a future addition typos one.

## See also

- [Page rendering](page-rendering.md)
- [Component library](../development/component-library.md)
- [Dependency injection](dependency-injection.md)
- [Functional tests](../testing/functional-tests.md)
