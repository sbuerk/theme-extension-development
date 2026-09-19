# Content elements

How the classic core content types render without `fluid_styled_content`,
which TypoScript branch and template belong to which `CType`, and the two
elements — `table` and `shortcut` — that needed more than a template. This
page is the detail behind the content-element paragraph in
[Page rendering](page-rendering.md); read that page first for how a content
element reaches `lib.contentElement` at all — this page does not repeat the
`FLUIDTEMPLATE` vs. `PAGEVIEW` reasoning or the Fluid file layout.

## TCA without rendering

`EXT:frontend` registers the whole classic set of content types itself — image,
textmedia, bullets, table, uploads, the eleven `menu_*` types, shortcut, div and
html. **Where** it writes them down differs between the supported versions and
nothing else does: v13 ships one
`Configuration/TCA/Overrides/2xx-tt_content-content_type-*.php` per type, each
calling `ExtensionManagementUtility::addRecordType()`, while v12 declares all of
them inline in the `types` array of `Configuration/TCA/tt_content.php`. Verified
in both installed cores. The set of `CType` values a theme has to cover is
identical.
`fluid_styled_content` was never a dependency of this theme and is not
installed here at all. What that extension supplies, when present, is the
*rendering* — a `lib.contentElement` TypoScript object and one template per
`CType` — never the TCA.

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
put the gallery first, the latter so the text after it can flow around it.
`Partials/ContentElement/Gallery.html` translates `intext` into a float of the
whole gallery — `theme-gallery--float-start` for "In text, left",
`--float-end` for "In text, right", and `--nowrap` on top for the two "no
wrap" variants, which keeps the text beside the gallery instead of letting it
flow back under it. Left and right become start and end on purpose: in a
right-to-left page an editor's "left" is the start of the line, as it already
is for the horizontal alignment of the gallery row. Every gallery item is a
`.theme-figure` as well — see the gallery and figure contracts in
[Component library](../development/component-library.md).
`Tests/Functional/TextPicElementRenderingTest.php` holds the translation for
`textpic` and `textmedia` against seeded records with real file references.
`uploads` is a file list, not a gallery: `FilesProcessor` merges `media` and
`file_collections` and applies `filelink_sorting`/`filelink_sorting_direction`
natively, so the template only formats what it is handed, as a
`.theme-file-list` — see [below](#uploads-the-display-type-picks-the-file-list).
`div` renders nothing but an `<hr>`; its
only field, `header`, has its label overridden by `EXT:frontend`'s own
language file to "Name (not visible in frontend)", which is also why neither
`div`, `html` nor `shortcut` render the shared header partial.

## `bullets`: core processors, and the list components

`bodytext` for `bullets` is one item per line. No processor of this
extension's own was needed: the TypoScript branch wires the two core
processors the way `fluid_styled_content` wires them for this CType
(`Configuration/TypoScript/ContentElement/Bullets.typoscript` there), condition
for condition, so both split a record into the same items — which matters for
the optional bridge to `fluid_styled_content` that points its paths at these
templates. What the templates then render of a definition list differs in one
point, below.

| `bullets_type` | Processor                      | Split                        | Element                                      |
|----------------|--------------------------------|------------------------------|----------------------------------------------|
| `0`            | `SplitProcessor`               | one item per line            | `<ul class="theme-list …">`                  |
| `1`            | `SplitProcessor`               | one item per line            | `<ol class="theme-list …">`                  |
| `2`            | `CommaSeparatedValueProcessor` | `term\|description` per line | `<dl class="theme-dl theme-dl--horizontal">` |

`SplitProcessor` runs with `removeEmptyEntries`, so an empty line — the
trailing line break of a textarea included — is no item. The CSV processor has
no such option: an empty line is a row with an empty first cell, and the
template skips it. The first cell of a row is the `<dt>`, every further
non-empty cell a `<dd>` of it, and a line without `|` is a term with no
description — the reading of `fluid_styled_content`'s `Bullets/Type-2`
partial, with one deliberate difference: that partial renders an empty `<dt>`
for a line such as `|description` and an empty `<dd>` for an empty cell, and
this template skips both, the description of an empty term with it. A
description without its term is not a definition. `BulletListRenderingTest`
pins that.

The enclosure stays the processor's default `"`, as there, so a line is read as
CSV (`CsvUtility::csvToArray()`, `fgetcsv()`): a line starting with `"` loses
the quote, and a `"` that is never closed swallows the lines after it into one
cell. That is the same in both, and it is why the changelog does not promise
that every line without `|` renders as before.

Until this change every line of a definition list was a `<dt>` of its own:
there was no reference rendering installed to take the convention from, and
inventing a delimiter nobody told the editor about was worse than leaving
`<dd>` out. `fluid_styled_content` is that reference, and a line of the old
shape — no `|` — still renders as a term.

### `layout` picks the modifier of `.theme-list`

`layout` is disabled for every CType in
`Configuration/PageTsConfig/ContentElementAppearance.tsconfig` and re-enabled
for `bullets` alone through `layout.types.bullets.disabled = 0`:
`PageTsConfigMerged` merges `types.<CType>` over the field configuration for
the record type of the form, so the `0` wins for this type and no other. The
four core values keep their numbers and get labels that describe a list:

| `layout` | Label              | Modifier                                                |
|----------|--------------------|---------------------------------------------------------|
| `0`      | Bullets or numbers | none — `.theme-list` alone, the markers of the baseline |
| `1`      | Check marks        | `.theme-list--check`                                    |
| `2`      | Icons              | `.theme-list--icon`, one icon of the set for every item |
| `3`      | Inline             | `.theme-list--inline`                                   |

An `<ol>` takes the same modifiers — the component is written for both, and an
ordered list stays ordered for assistive technology when a check mark replaces
the number. A value outside the four renders the bare `.theme-list`, the rule
of the frame modifiers; `ContentElementContractTest` reads the modifiers out
of `Bullets.html` and holds each to a rule of the compiled stylesheet. The
definition list ignores `layout`: it is always `--horizontal`, stacked below
`bp.$md` by the component itself.

**Layout 2 shows the icon the editor picked**, one for the whole list, in
`tt_content.tx_theme_icon`. The column is the theme's icon picker —
`IconItems::selectConfig()`, the icon grid below the select, the curated
`keepItems` of `Configuration/PageTsConfig/IconPicker.tsconfig` copied from the
link icon — see [Icons](../development/icons.md#picking-an-icon-in-the-backend).
It is a column of its own rather than `tx_theme_link_icon`, which belongs to a
link and is shown with it, and it is added to `bullets` alone, after the list
type; a later core layout that shows one icon for the whole element adds the
same column to its CType. The template renders the name with `optional`: an
icon a later version of the set no longer has costs the icon, not the page.
With no icon picked the layout has nothing to put in the slot, so the list
renders as layout 0 rather than as a column of empty slots.

`BulletListRenderingTest` holds the four layouts, the picked icon, the layout
without one, the unknown value, the ordered list and the definition list
against fixture records; `ContentElementAppearanceFormEngineTest` holds the
form to offering `layout` and the icon on this CType, under these labels, and
on no other, and `IconPickerFormEngineTest` holds the icon field to the
curated list and to the whole set without it.

## `text`: `layout` sets the text in columns

The text element has one look besides running text, and `layout` picks it.
`layout.types.text` re-enables the field for this CType the way the bullet list
does, relabels the two values it renders and takes the other two out of the
select with `removeItems = 2, 3` — offered, they would be layouts that look like
the default.

| `layout` | Label        | Modifier on `.theme-content-element__body`       |
|----------|--------------|--------------------------------------------------|
| `0`      | Running text | none                                             |
| `1`      | Columns      | `.theme-text--columns` (`components/_text.scss`) |

`Text.html` matches `1` alone. `2` and `3` — removed from the select, but still
on a record saved before — and any value nothing offered render running text.
The modifier goes on the block holding the rich text, not on the wrapper: the
header stays one line across the columns, and a rich text block elsewhere can
take the same class.

The columns are CSS `columns: 2 30ch` — at most two, each at least 30
characters wide, so a phone and a narrow sidebar get one. The minimum is wider
than the 12rem of `.theme-list--columns-2` because a column of prose needs more
room than a list item. It is 30ch rather than 20ch because two columns of 20
characters still fit the column of a phone, and prose broken every three words
is harder to read than a single column. A heading does not end a column
(`break-after: avoid`), and a figure, a table, a quotation, a code block and a
list item move to the next column whole. The rule between the columns is the
decorative border colour, like every hairline of the Frame language.

`TextLayoutRenderingTest` holds the four cases through the set and the static
include, `ContentElementAppearanceFormEngineTest` the form to offering `0` and
`1` under these labels on this CType, and `ContentElementContractTest` the
modifier to a rule of the stylesheet.

## `uploads`: the display type picks the file list

The core gives the file links element a display type of its own,
`uploads_type`, in the palette `uploadslayout` next to `filelink_size` and
`uploads_description` — the same three values on v12.4 and v13.4
(`Configuration/TCA/tt_content.php` of EXT:frontend on v12.4,
`Overrides/245-tt_content-content_type-uploads.php` on v13.4). They are
the three layouts of a file list, so `Uploads.html` maps them onto
`.theme-file-list` rather than the theme adding a second field for the same
choice:

| `uploads_type` | Core label                            | Renders                                                                              |
|----------------|---------------------------------------|--------------------------------------------------------------------------------------|
| `0`            | Only file name                        | `.theme-file-list`                                                                   |
| `1`            | File name and file extension icon     | `.theme-file-list--icon`, the icon of the file type before each name                 |
| `2`            | File name and thumbnail (if possible) | `.theme-file-list--preview`, a thumbnail, or the icon of the file type in its square |

**`layout` stays disabled for `uploads`.** It is the other field a display
choice could live in, and it would be the same choice twice: an editor would
find a "Layout" next to a "Display file/icon/thumbnail" that both change the
list, and a site switching to fluid_styled_content would lose the one the
theme had picked. `uploads_type` keeps its core meaning, which is also what
fluid_styled_content renders from it.

`filelink_size` adds the size in every display type, `f:format.bytes`, and
`uploads_description` the description of the file reference; neither is tied
to a display type.

The icon of a file type comes from
`Partials/ContentElement/FileIcon.html`: one `f:case` per lower case
extension — FAL lower-cases it — with the icon written out, the `file-*`
icons of the set (`file-pdf`, `file-image`, `file-zipper`, `file-lines`,
`file-csv`, `file-word`, `file-excel`, `file-powerpoint`, `file-audio`,
`file-video`) and `file` for any other. By extension rather than by MIME type,
because the extension is what the name in the link shows. Written out rather
than computed, so `IconUsageTest` holds every name to the shipped set and the
icon table of the styleguide to the partial.

A thumbnail is made only for a file FAL classifies as an image
(`file.type == 2`): `f:image` cannot process a PDF or a text file. A file of
another type gets its icon in the square the thumbnail would take, so every row
starts at the same place. Icon and thumbnail are decoration beside the link,
not inside it: the link names the file, the icon slot is `aria-hidden`, and the
thumbnail carries `alt=""` — explicitly, because `f:image` otherwise writes the
alternative text of the reference (`ImageViewHelper`, read on v12.4 and v13.4). A value
of `uploads_type` outside the three renders the bare list.

**The size stays outside the link.** The link text is the file name, which
carries its extension and is usually unique within a list, so the entries of a
screen reader's list of links name their files. WCAG 2.4.4 (Link Purpose, In Context)
asks that the purpose of a link can be determined from its text together with
its context, and the size is context: the sibling `.theme-file-list__size` in
the same list item. Inside the link, every entry of that list would end in its
size - "release-notes.pdf 1 KB" - and the names that tell the entries apart
would be followed by a number that tells none of them apart.

The classes `theme-content-element__file-*` the template used to write are
gone: they had no rule, and `.theme-file-list` replaces them.

`UploadsRenderingTest` renders real files of a storage — a PDF, a zip archive,
a text file, an extension without an icon and an SVG — and holds the three
display types and the unknown value through both delivery paths, the icon of
each type, the thumbnail and its fallback, the size and the description;
`ContentElementContractTest` holds both modifiers to the stylesheet. The
showcase page `/elements/core/uploads` shows the icons of four types and the
thumbnail next to its fallback with files of its own, see
[Seeding](../development/seeding.md).

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

`Classes/DataProcessing/TableProcessor.php` (`final`, wired with a
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

### `table_class`: the modifier of the same name

`Table.html` renders `table_class` as `.theme-table--{table_class}`, one to
one, and without a list of allowed values:

| `table_class`     | Offered by                      | Renders                         |
|-------------------|---------------------------------|---------------------------------|
| *(empty)*         | core, the default               | `.theme-table`, rows ruled only |
| `striped`         | core TCA                        | `.theme-table--striped`         |
| `bordered`        | core TCA                        | `.theme-table--bordered`        |
| `striped-columns` | theme page TSconfig, `addItems` | `.theme-table--striped-columns` |
| `hover`           | theme page TSconfig, `addItems` | `.theme-table--hover`           |
| `borderless`      | theme page TSconfig, `addItems` | `.theme-table--borderless`      |
| `compact`         | theme page TSconfig, `addItems` | `.theme-table--compact`         |
| `sticky-header`   | theme page TSconfig, `addItems` | `.theme-table--sticky-header`   |

The core items are identical on both core versions: EXT:frontend declares
them in `Configuration/TCA/tt_content.php` on v12.4 and in
`Configuration/TCA/Overrides/240-tt_content-content_type-table.php` on v13.4
(read in `.Build/vendor` after the `composerUpdate` of each). The theme adds its
own through page TSconfig,
[`Configuration/PageTsConfig/TCEFORM/TableClass.tsconfig`](../../Configuration/PageTsConfig/TCEFORM/TableClass.tsconfig),
imported from `Configuration/page.tsconfig` like the backend layouts, so it
applies through the site set and the static include alike. That is the route
the core documents for this very field — "Edit predefined options" in
Feature #79622, "Introducing Table Class for Fluid Styled Content" — rather
than a TCA override, and it keeps a site package able to `removeItems` in the
same place. DataHandler stores a static select value without checking it
against the items on either version (`checkValueForGroupFolderSelect()` in
`DataHandler.php`, read on v12.4 and v13.4), so a value added in TSconfig saves
like a TCA one.

The value is passed through rather than checked against the list, as the
core's own rendering did (`ce-table-{table_class}`): a site package that adds
an item of its own gets the class for it and styles it. It is escaped like any
attribute value. The field is a single select, so an element carries one
modifier; the styleguide combines them, an editor cannot.

A table without a class is **no longer striped**: the stripes used to be the
default of `.theme-table`, which made the core's "Striped" item change nothing.
They are `--striped` now, and the default rows are separated by rules only.

`Tests/Functional/TableClassRenderingTest.php` reads the offered values the way
the backend does — the TCA items plus the `addItems` of the page TSconfig
loaded for the page — asserts the list, and renders one element per value;
`Tests/Unit/ComponentLibraryTest::everyTableClassAnEditorCanPickIsStyled` holds
every value to a compiled `.theme-table--<value>` rule.

## `shortcut`: recursion, broken structurally rather than by a register

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

            # The cycle break: inside a shortcut, the shortcut branch
            # renders nothing at all.
            conf.tt_content.shortcut = TEXT
            conf.tt_content.shortcut.value =
        }
    }
}
```

`conf.tt_content =< tt_content` is what makes a referenced record render
**exactly as it would on its own**: it copies the whole `tt_content` `CASE`
object this file builds (registered by `EXT:frontend` in its
`ext_localconf.php`, keyed on `CType`) as the render definition for each
fetched record, so a referenced `shortcut` would go through this same branch
again — recursion is possible by construction, not an edge case bolted on
afterwards.

The last two lines are what stops it. Inside a shortcut's own `RECORDS` call,
the copied `CASE` object's `shortcut` branch is replaced by an empty `TEXT`
object, so a shortcut reached *through* a shortcut renders nothing. One level
of indirection is followed — which is all the element is for — and a second is
refused. Both the direct cycle (A references A) and the indirect one (A
references B references A) are impossible by construction, with no state kept
anywhere. `conf.tt_content` is copied fresh per invocation, so the override is
local to this element's own rendering and never leaks into how another
element's `RECORDS` call resolves the same branch.

### Why not leave it to the core

The core has a guard of its own, and it works. Verified in source against the
installed v13.4.34 core
(`.Build/vendor/typo3/cms-frontend/Classes/ContentObject/RecordsContentObject.php`):

1. Before fetching anything, `render()` reads the record currently being
   rendered off `TypoScriptFrontendController::$currentRecord` and registers
   it in `TypoScriptFrontendController::$recordRegister`, keyed `table:uid`,
   incrementing a counter (lines 59–67). The outer `CONTENT` cObject that
   dispatches a page's content columns (`ContentContentObject::render()`,
   lines 47–55) registers the same way, so a shortcut's own record is already
   registered by the time its own `RECORDS` call runs at all.
2. Before rendering each fetched item, the loop checks whether that item's
   own `table:uid` key is already registered (lines 111–114); if it is, the
   item is skipped — no `cObjGetSingle()` call, nothing appended to the
   output for it. The counter is decremented again once rendering finishes
   (lines 138–141).
3. `$recordRegister` lives on the frontend controller, not on the `RECORDS`
   call, so it catches an **indirect** cycle too: rendering A registers
   `tt_content:A`, sets `$currentRecord` to `tt_content:B` before registering
   it in turn (line 120), and by the time B's own reference back to A is
   reached, `tt_content:A` is still registered — A has not finished rendering
   yet — so that reference is skipped the same way a direct self-reference is.

That is a guarantee of one core version's frontend controller, held in
request-scoped state on a class this theme has no contract with. The
structural break above is a property of **this theme**: it holds wherever the
theme runs, it is visible in the TypoScript that causes it rather than in
someone else's `protected` array, and it needs no state at all. Where the core
guard is present the two agree — a self-reference produces no output for that
one reference while every other reference in the same `records` field still
renders — so nothing is lost by not depending on it.

The two differ in one respect worth naming: the core guard skips a record that
is *anywhere* up the current render stack, while the break here refuses the
`shortcut` branch specifically. A cycle that leaves the branch — a shortcut
reached through some other content type that references the shortcut back — is
not closed by this theme; it is closed by the core guard where that exists.
`Tests/Functional/CoreContentElementRenderingTest.php::aCircularShortcutDoesNotTakeTheRequestDown`
covers the theme's own break, not the core's.

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
`fluid_styled_content` TypoScript that rendered these same nine CTypes,
reproduced close to verbatim for seven of the nine.

Their **TCA** is `EXT:frontend`'s on both supported versions, so this is not a
v13 change one has to work around here: v12's
`EXT:frontend/Configuration/TCA/tt_content.php` declares the eleven `menu_*`
types in its `types` array, and v13 registers the same types from
`Configuration/TCA/Overrides/`. Only the file layout moved.

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
updated` sorts by `SYS_LASTCHANGED` descending
(`prepareMenuItemsForUpdatedMenu()`), so the field the template reads is the
very field the query itself is keyed on.

The core sorts by that field and nothing else, and pages changed in the same
second — every page of a tree imported or published at once — then come back
in whatever order the database returns them: ascending uid on SQLite, an order
that differed between two identical page trees on PostgreSQL. The theme sets
`alternativeSortingField = SYS_LASTCHANGED DESC, uid DESC`, which the core
hands to `getMenuForPages()` as the complete `ORDER BY`, so it repeats the
primary sort and adds the newer uid as tie-breaker.
`RecentlyUpdatedMenuRenderingTest` asserts the complete order over three tied
pages. `special.mode` still decides which column `maxAge` filters on, but no
longer the order: a site package setting `mode = tstamp` sets
`alternativeSortingField = tstamp DESC, uid DESC` with it.

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
`shortcut`. A categorized-content element can match itself by category, or
match another one that matches it back, and rendering rows as links rather
than whole elements means nothing can nest and the cycle cannot form in the
first place; no structural break like the one applied to `shortcut` was
needed here because the rendering shape itself rules the cycle out.

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
supplied a rendering. The types below are different: their TCA is this
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

| `CType`                   | Is                                                       | Renders through                                         |
|---------------------------|----------------------------------------------------------|---------------------------------------------------------|
| `theme_hero`              | Full hero: heading, text, media, actions                 | `.theme-hero` (`Partials/ContentElement/Hero.html`)     |
| `theme_hero_small`        | The same, reduced                                        | `.theme-hero--compact`, same partial                    |
| `theme_hero_text_only`    | The same, no media                                       | `.theme-hero` with no `--media`, same partial           |
| `theme_teaser`            | Text teaser, no media                                    | `.theme-teaser` (`Partials/ContentElement/Teaser.html`) |
| `theme_media_teaser`      | Text beside a single image                               | `.theme-teaser` with media, same partial                |
| `theme_media_teaser_grid` | Several media teasers in a grid                          | `.theme-card-grid` of `.theme-card` items               |
| `theme_testimonial`       | A quotation with an attribution                          | `.theme-quote`                                          |
| `theme_author`            | A person: portrait, name, role, links                    | `.theme-author` + `.theme-content-menu`                 |
| `theme_linklist`          | A list of links                                          | `.theme-content-menu`                                   |
| `theme_sociallinks`       | The same, labelled instead of iconed                     | `.theme-content-menu`                                   |
| `theme_notice`            | A note, tip, information, success, warning or danger box | `.theme-alert`, the modifier and `role` of its kind     |
| `theme_tabs`              | Items in tabs, one panel at a time                       | `.theme-tabs`                                           |
| `theme_accordion`         | Collapsible items, one open at a time                    | `.theme-accordion`                                      |
| `theme_text_icon`         | A heading, rich text and a link beside one icon          | `.theme-media-object`                                   |
| `theme_features`          | A group of features with an icon each                    | `.theme-feature` in `.theme-feature-grid`               |
| `theme_stats`             | Figures and what they count                              | `.theme-stat` in `.theme-stats`                         |
| `theme_steps`             | The numbered steps of a process                          | `.theme-steps`                                          |

`theme_hero`, `theme_hero_small` and `theme_hero_text_only` share one Fluid
partial and differ only in a `compact` argument and in whether an `image`
field exists on the CType at all; `theme_teaser` and `theme_media_teaser`
share the sibling partial the same way. Both are documented in full above the
markup in `Partials/ContentElement/Hero.html` and `Teaser.html` — this table
only records which component backs which `CType`, not the shared-partial
reasoning already written there.

### `lib.themeContentElement`: their own frame, not `lib.contentElement`

All of them are `=< lib.themeContentElement`, a `FLUIDTEMPLATE` with the same
three root paths `lib.contentElement` has — the `theme.*RootPath` constants at
index `10` — and nothing else. The classic set above stays on
`lib.contentElement`.

The reason is `fluid_styled_content`. Its
`Configuration/TypoScript/Helper/ContentElement.typoscript` starts with
`lib.contentElement >` and builds the object again from its own root paths
(verified at `v12.4.45` and `v13.4.34`). An installation that loads it
after the theme therefore loses every root path the theme set on that object,
and an element of the theme's own would look for `ContentElements/Theme…` in
fluid_styled_content's templates and fail. Nothing clears an object of the
theme's own name, so these elements render the same whether that extension is
installed or not, and in whichever order the two are loaded.

The split also decides what a later bridge to fluid_styled_content has to do:
add root paths to `lib.contentElement` at an index between its own `0` and the
`10` of its `styles.templates.*` constants, for the classic set only. It never
has to touch a `theme_*` element.

Two objects rather than `lib.contentElement =< lib.themeContentElement`: the
root paths are the whole definition, and a reference would make one object
depend on the other for exactly the case where one of them is cleared.

For an integrator this is one change: root paths added to `lib.contentElement`
reach the classic set only. The `theme.*RootPath` constants set both objects,
as before.

`Tests/Functional/ThemeContentElementObjectTest.php` renders a page of all of
them with `lib.contentElement >` loaded after the theme — through the set
and through the static include — and requires the markup to be identical to
the page without it. A core element on a second page, rendered with the same
TypoScript, has to lose its rendering, which is what shows that the clearing
took effect. Pointing `theme_notice` back at `lib.contentElement` fails the
comparison on both paths.

### Naming: `theme_*`, `tx_theme_*`, `tx_theme_list_item`

CTypes are prefixed `theme_`, columns `tx_theme_`, and the shared inline child
table is `tx_theme_list_item` — short rather than the full extension key
(`themeextensiondevelopment_hero` is unusable in a `showitem` string and in
TypoScript), following [camino](https://github.com/TYPO3-CMS/theme_camino)'s
own `camino_` precedent for the identical reason. The collision risk this
accepts is real — another extension is free to also prefix its own fields
`theme_` — and it is accepted deliberately, the same way camino accepts it for
its own prefix, rather than overlooked.

### `ext_tables.sql`, and why it exists although the schema derives from TCA

On TYPO3 v13 the whole schema for `tx_theme_list_item` and the fifteen
`tx_theme_*` columns added to `tt_content` comes from
`TYPO3\CMS\Core\Database\Schema\DefaultTcaSchema::enrich()` reading the TCA at
compare-schema time (#101553, extended by #104311 in 13.3).

**TYPO3 v12 does none of that.** Its `DefaultTcaSchema` derives the management
columns from `ctrl`, the `category|datetime|slug|json|uuid` types and MM tables,
and has no branch for `input`, `text`, `link`, `file`, `inline` or a `select`
without an MM table — so on v12 the table and the fifteen columns are simply
never created and every theme element using them fails.
[`ext_tables.sql`](../../ext_tables.sql) therefore ships the
definitions v13 would generate, reproduced column for column from what v13's own
analyzer derives, so the analyzer stays quiet on both versions. #101553 states
that an explicit definition takes precedence, which is why one non-version-aware
file serves both. `Tests/Functional/DatabaseSchemaTest` is the gate.

The practical consequence: **adding a column to a theme element means adding it
to `ext_tables.sql` as well**, or the element works on v13 and fails on v12.
→ [The other configuration exception: `ext_tables.sql`](core-version-aware-code.md#the-other-configuration-exception-ext_tablessql)

The TCA still has to be right, and one column needed more than "add a `type=input` field and let it happen",
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

### The link of a theme element: a style and an icon

The `theme_link` palette carries two choices besides the link and its label,
and so does the link of an inline list item, as far as it applies there:

| Column                             | On                            | Renders as                                                        |
|------------------------------------|-------------------------------|-------------------------------------------------------------------|
| `tt_content.tx_theme_link_variant` | hero and teaser elements      | `''` `.theme-button`, `secondary`, `ghost`, `link` its modifier   |
| `tt_content.tx_theme_link_icon`    | hero and teaser elements      | `<theme:icon>` before the label, in `LinkButton.html`             |
| `tx_theme_list_item.link_icon`     | every relation showing a link | `<theme:icon>` before the label, in `LinkList.html` and the cards |

The style is `tx_theme_link_variant`, labelled "Link style" in the form, and
keeps its column name: installations already store the style in this column,
with the first three values before `link`, so renaming it would lose it.
`--danger` is not offered — a call to action is not a destructive action.
`ThemeLinkRenderingTest` holds the items of the column and the cases of
`LinkButton.html` to each other, in both directions.

A list item has no style. Its link is a row of `.theme-content-menu` in three
of the four relations, where a button would break the list, and the card link
of the fourth is the card's own affordance.

The icon goes **before** the label, in every place: it says what the link leads
to, and leaves the end of the link to what says how it opens. It is
decoration; the label names the link. Each link spaces it with a `gap` — the
button already had one, `.theme-content-menu__link` and `.theme-card__link`
got one — so the icon is a flex item in reading order, first in a
right-to-left page as well, and no margin names a physical side. Rendered with
`optional`, because the stored name may be one a later Font Awesome version no
longer has — see [Icons](../development/icons.md#rendering-an-icon).

The picker and the icons it offers by default are documented in
[Icons § Picking an icon in the backend](../development/icons.md#picking-an-icon-in-the-backend).

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

### Notice, tabs and accordion

`theme_notice`, `theme_tabs` and `theme_accordion` put the three content
components of the library into an editor's hands that had no content element:
`.theme-alert`, `.theme-tabs` and `.theme-accordion`. bootstrap_package ships
the same three as `panel`, `tab` and `accordion`; camino has none of them.

**The notice** has a kind, `tx_theme_notice_kind`, one of the six
`.theme-alert` modifiers. `ThemeNotice.html` derives the modifier and the
`role` from it in one `f:switch`, so the two cannot disagree — the role is a
property of the kind, not a second field an editor could set against it:

| Kind                | `role`   |
|---------------------|----------|
| `info`, `success`   | `status` |
| `warning`, `danger` | `alert`  |
| `note`, `tip`       | `note`   |

The TCA default is `note`, not `info` — `info` is the look of the bare class,
but it is a live region, and a notice whose kind nobody chose should be the one
kind that never is. A value outside the six renders as a note, and a notice
with neither title nor text renders nothing inside its wrapper — an empty
live region announces nothing, and an empty alert would interrupt a reader for
it. `header` is the
title inside the component, `.theme-alert__title`, not a content heading.
`bodytext` is rich text through `columnsOverrides` of the type, so DataHandler
runs the RTE transformation on save as it does for `text`; `.theme-alert__text`
is therefore a `div` of paragraphs, and `_alert.scss` drops the bottom margin
of its last child.

**Tabs and accordion** reuse `tx_theme_list_items`, narrowed by
`overrideChildTca` to the child's `header` — required: it is the name of the
tab, and the summary of the item — and `text`. Their TypoScript copies
`tt_content.theme_linklist`: the query is the same, the template is not.

The child's `text` is rich text for these two relations only, through
`overrideChildTca` too. Set on the column, it would make the plain text of the
four other relations rich text as well — `theme_media_teaser_grid` renders it
through `nl2br()` and would print markup as text.
`ThemeContentElementRenderingTest::theListItemTextIsRichTextForTabsAndAccordionOnly`
holds both halves in the TCA, and `ListItemRichTextFormEngineTest` holds them in
the form: it compiles a parent with the `tcaDatabaseRecord` group and reads
`richtextConfiguration` off the child's `text` - `TcaText` sees the override
only because it is registered after `InlineOverrideChildTca`, and nothing
declares that dependency. `overrideChildTca` is a FormEngine setting, though:
DataHandler resolves the configuration of a field from the TCA of the table and
the `columnsOverrides` of the record's own type
(`resolveFieldConfigurationAndRespectColumnsOverrides()` in `DataHandler.php`,
read on v12.4 and v13.4), and `tx_theme_list_item` has no type field. **No RTE
transformation runs on save for this column**; the value is stored as the
editor submitted it. The frontend does not depend on the transformation:
`f:format.html` renders through `lib.parseFunc_RTE`, and `parseFunc` sanitises
its result unless `htmlSanitize` is switched off
(`ContentObjectRenderer::_parseFunc()`, `$conf['htmlSanitize'] ?? true`). The
alternative, a type field on the child table so the relation could use
`columnsOverrides`, would change the child table of four existing relations for
one flag.

The ids of the tabs are derived from the element's anchor and the position of
the item — `c817-tab-1`, its panel `c817-tab-1-panel` — and the `name` of the
accordion's items from the anchor alone, `c818-accordion`: unique per element
because the uid is, and the browser groups `details` by name across the whole
document, so two accordions sharing one would close each other's items. The
uid of the child row was not used: the anchor is the one identifier of an
element the theme already treats as its name on the page, and the one
difference `DevelopmentInstance/LegacyDeliveryTest` normalises between the two
trees — its rule now covers a `c<uid>` followed by `-` in `id`,
`aria-controls` and `name` as well.

The panel of a tab carries its class and its id and nothing else, as the tabs
contract requires; its `.theme-tabs__heading` is one level below the element's
heading (`Partials/ContentElement/ItemHeading.html`), h2 when the element shows
none. The accordion's summary is the item's title as text: a `summary` is
exposed as a button, and a heading inside it is flattened into its name.

Not added: `theme_panel`. `.theme-panel` is content grouped under a heading
with a footer of controls; a content element has no controls to put there, and
without them it is a notice of kind `note` or a teaser without a link, both of
which exist.

### Elements with icons

The elements below show icons of the shipped set, picked by the editor with
the theme's icon picker (`IconItems::selectConfig()`, the curated `keepItems`
of `Configuration/PageTsConfig/IconPicker.tsconfig`) and rendered with
`<theme:icon … optional="1" />` - see
[Icons](../development/icons.md#picking-an-icon-in-the-backend). Each has a
page of its own below `/elements/theme`, showing every value of the fields
that change how it looks; `ShowcaseTreeTest` reads the values from the TCA and
holds the page to them.

**`theme_text_icon`** is the heading, the rich text and the link of the element
beside one icon, on `.theme-media-object` - bootstrap_package's `texticon`. The
icon is `tt_content.tx_theme_icon`, the column of the bullet list's icon, with
a description of its own on this type. Three columns say how it is drawn, one
per axis of the component:

| Column                   | Values                      | Modifier                          |
|--------------------------|-----------------------------|-----------------------------------|
| `tx_theme_icon_position` | `start`, `end`, `top`       | `--start`, `--end`, `--top`       |
| `tx_theme_icon_shape`    | `plain`, `square`, `circle` | `--plain`, `--square`, `--circle` |
| `tx_theme_icon_size`     | `md`, `lg`, `xl`            | `--md`, `--lg`, `--xl`            |

They are in the palette `theme_icon` with the icon, and are named for the icon,
not for the element: a later element that shows one icon the same way offers
the same palette. The template writes one modifier per axis, always, and maps
every value it does not know - an empty one included - to the first value of
the axis, which is also the TCA default; `ContentElementContractTest` holds the
modifiers to the stylesheet.

The icon is rendered into a variable first, and the slot only when that
produced markup: an element without an icon, or with a name the set no longer
has, is its text alone rather than an empty tile. The heading goes through the
shared header partial **inside** the body, beside the icon, so
`header_position` and `tx_theme_header_style` apply as for a text element.

**`theme_features`, `theme_stats` and `theme_steps`** take their items from
`tx_theme_list_items`, like the tabs and the accordion, and their TypoScript is
a copy of `tt_content.theme_linklist` with a template of its own. Each shows the
child's `icon` column - `tx_theme_list_item.icon`, the icon of an item, from the
same picker - through the `showitem` of its `overrideChildTca`; no other
relation shows it, so no editor is offered an icon nothing renders. The title of
an item is required in all three.

| `CType`          | Items                                                       | Renders through                           |
|------------------|-------------------------------------------------------------|-------------------------------------------|
| `theme_features` | title, text, icon, link                                     | `.theme-feature` in `.theme-feature-grid` |
| `theme_stats`    | figure (`header`), what it counts (`subheader`), text, icon | `.theme-stat` in the `dl` `.theme-stats`  |
| `theme_steps`    | title, text, icon                                           | `.theme-steps`, an `ol`                   |

The features have a layout, the core `layout` re-enabled for this CType alone
in `ContentElementAppearance.tsconfig` - as for the bullet list - with labels
of its own, and a column count, `tt_content.tx_theme_columns`. Both are the
palette `theme_grid`:

| `layout` | Label                       | Item modifier                          |
|----------|-----------------------------|----------------------------------------|
| `0`      | Columns, the icon above     | `--column`                             |
| `1`      | Hanging icons               | `--hanging`                            |
| `2`      | Tiles                       | `--tile`                               |
| `3`      | With an introduction beside | `--hanging`, in `.theme-feature-intro` |

`tx_theme_columns` offers 2, 3 and 4, the `--columns-*` modifiers of the grid,
each the most columns it takes. It is an integer select, and its database
default is 0 rather than the TCA default: v13.4 derives 0 for every integer
select (`DefaultTcaSchema`, read on v13.4.35), and `ext_tables.sql` declares
the same column for v12.4, which derives none. A record written through the
form carries 3; the template renders 0 and every value it does not know as
three columns, and any `layout` it does not know as 0, so a row written
without the column reaches the page as three columns. It is named for the grid,
not for the element, and is meant for the next element that lays out a grid
of items.

**Four columns need a page without a sidebar.** Four tracks of the grid's
12rem minimum and three gaps of 1.875rem take 53.625rem inside the element.
The backend layout `content` gives an element up to 70rem - the 75rem content
width less the page and element padding - and `content_sidebar` at most
53.125rem, its 15rem aside and the gap taken off as well. On a page with a sub
navigation the four-column grid therefore shows three columns at every
viewport; nothing breaks, the grid only takes what fits. The showcase puts the
features on `/elements/theme/features` with `content` for that reason, and
`ShowcaseTreeTest::aFourColumnFeatureGridIsOnAPageWithoutASidebar` fails when
a four-column features element lands on a page with an aside.

The figures read the child's `header` as the figure and a new column,
`tx_theme_list_item.subheader`, as what it counts. Both are required and
relabelled "Figure" and "What it counts" on this relation - a `label` in
`overrideChildTca.columns` reaches the form because
`InlineOverrideChildTca::overrideColumns()` merges the whole column
configuration with `array_replace_recursive()` (read on v12.4.45 and v13.4.35,
and held on both cores by the form test). `subheader` is a short second line of
any item; no other relation shows it yet. In the markup what it counts is the
`dt` and the figure the `dd`, which a screen reader reads as "Icons shipped,
2001"; the stylesheet shows the figure first.

The steps are an `ol`, and the marker of a step is empty: the stylesheet
numbers it with a counter, so no number in the markup can disagree with the
order. A step with an icon shows it in its marker instead, with
`theme-steps__marker--icon`.

Every item icon goes through the same "into a variable first" as the text and
icon element, so an item without a usable icon renders no empty tile or marker
icon.

| Test                                                    | Guards                                                                                                                        |
|---------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------|
| `Tests/Functional/IconContentElementRenderingTest.php`  | every value of every axis, and one nothing offers, writes its modifier, through the set and the static include; no empty slot |
| `Tests/Functional/IconContentElementFormEngineTest.php` | the form offers the values the component styles, the fields on this type only, and the description of the type                |
| `Tests/Unit/ContentElementContractTest.php`             | every modifier the template writes is a selector of the compiled stylesheet                                                   |

### Gaps, stated as gaps

- **No platform logos.** The theme ships the solid set of Font Awesome Free,
  and a link can carry an icon of it — see
  [above](#the-link-of-a-theme-element-a-style-and-an-icon). The logos of
  platforms are Font Awesome's *brands* set, which is not shipped: they are
  trademarks with rules of their own, not symbols. `theme_sociallinks`
  therefore renders the same text-label list as `theme_linklist`, and
  `link_label` carries the platform name ("Mastodon", "LinkedIn", …).

### The layouts of the heroes, and their eyebrow

The three heroes carry two columns of their own, above the header palette:
`tx_theme_eyebrow`, the short label rendered as `.theme-hero__eyebrow` — the
class the hero's markup contract always had and no field filled — and
`tx_theme_hero_layout`, which `Partials/ContentElement/Hero.html` maps onto a
modifier of `.theme-hero`:

| `tx_theme_hero_layout` | Renders                                                                 | Without an image | Offered on            |
|------------------------|-------------------------------------------------------------------------|------------------|-----------------------|
| *(empty)*, the default | the image beside the text, at the start — no modifier                   | the text alone   | all three             |
| `image-end`            | `--image-end`: the image beside the text, at the end                    | the default      | full and reduced hero |
| `centred`              | `--centred`: text, actions and image on one axis                        | `--centred`      | all three             |
| `screenshot`           | `--screenshot`: centred, the image below, cut off by the bottom edge    | `--centred`      | full hero             |
| `bordered`             | `--bordered`: the image at the end, cut off by the end and bottom edges | the default      | full hero             |

The default is the hero as it always rendered, so every hero saved before the
field existed renders unchanged, and there is no `image-start` value — it would
be a second name for the default. The layouts that arrange an image fall back
when there is none, rather than writing a modifier that positions nothing. A
value nothing offers renders no modifier.

`tx_theme_hero_layout.types.<CType>.removeItems` in
`ContentElementAppearance.tsconfig` narrows the select per hero: the reduced
hero has too little height for an image cut off at an edge, the hero without
media has no image to place. One column for the three rather than one per
type: the values mean the same wherever they are offered.

`screenshot` is the one layout that changes the markup order: its image
follows the text, so what a screen reader reads is what the page shows.
`image-end` and `bordered` keep the image first and move it with
`flex-direction: row-reverse`, the way `.theme-teaser--reversed` does. The
eyebrow is a paragraph before the heading inside `.theme-hero__body`, not part
of the heading, so the outline keeps the title alone.

`HeroLayoutRenderingTest` holds every layout with and without an image, the
reduced and the text-only hero, the unknown value, the order of the image and
the eyebrow through both delivery paths; `ContentElementAppearanceFormEngineTest`
holds the values each hero offers; `ShowcaseTreeTest` holds
`/elements/theme/hero` to showing all of them.

### The styles of the testimonial

`tx_theme_quote_style` sets the quotation of `theme_testimonial`, mapped by
`ThemeTestimonial.html`: the default, a rule at the start as before; `pull`,
`.theme-quote--pull`, larger and between two rules; `centred`,
`.theme-quote--centred`. Both styles open with the quotation mark of the set,
`quote-left`, in an `aria-hidden` `.theme-quote__mark`; the default has none,
so every testimonial saved before the field existed renders unchanged. A value
nothing offers renders the default.

A select of its own, not the core `layout`. `layout` holds 0 to 3 under the
labels "Layout 1" to "Layout 3", is disabled for every CType by the theme's page
TSconfig and is an `exclude` field an editor group has to be granted; the
theme's own elements carry their choices in columns whose values are the names
of the modifiers they select, like the kind of a notice and the layout of a
hero. `QuoteStyleRenderingTest` holds the three styles and the unknown value
through both delivery paths and the mark,
`ContentElementAppearanceFormEngineTest` the values the form offers and that
`layout` stays out of it, and `ShowcaseTreeTest` `/elements/theme/quote` to
showing all three.

The portrait the catalogue planned for this element is not added: it needs a
media slot in `.theme-quote`, and that slot is to be built on the avatar
component, which did not exist when the styles were added. The field stays off
the form until then, for the reason below.

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

Every one of the theme's own types carries a `label`, a `description` and an
`icon` on its registration call, all under one wizard group ("Theme",
`tt_content.group.theme` in `locallang_tca.xlf`, inserted `before:default`).

The registration call is
[`Compatibility\ContentTypeRegistration::addRecordType()`](../../Classes/Compatibility/ContentTypeRegistration.php),
not the core method of the same name: `ExtensionManagementUtility::addRecordType()`
is v13 only, and v12.4 has nothing that writes a `types` definition. The helper
reproduces what v13's method does using API both versions have, so the TCA files
of the theme's types each keep exactly one call and carry no version switch.
`Tests/Functional/ThemeContentTypeRegistrationTest` asserts on both versions
that every one of their `CType` items, their `types` entries and their
`typeicon_classes` entries is there.
→ [Core version aware code](core-version-aware-code.md#configuration-is-the-exception)

**On v13, no page TSconfig registers the wizard**: since
`Feature-102834-Auto-registrationOfNewContentElementWizardViaTCA.rst` (v13.0)
the "new content element" wizard is generated from exactly those TCA keys, which
replaced the former `mod.wizards.newContentElement.wizardItems.<group>` TSconfig
step.

**On v12 that step is still the only one there is.** Its
`NewContentElementController::getWizards()` reads
`mod.wizards.newContentElement.wizardItems` and nothing else, so without page
TSconfig the theme's types are selectable in the `CType` dropdown of an existing
element and cannot be created —
[`Configuration/PageTsConfig/NewContentElementWizard.tsconfig`](../../Configuration/PageTsConfig/NewContentElementWizard.tsconfig)
supplies them behind a `[typo3.branch == "12.4"]` condition, imported from
`Configuration/page.tsconfig`. The condition is not caution: leaving the block
on for v13 would let the page TSconfig title, description and icon shadow the
TCA ones and drop `types.<value>.creationOptions` with them.
→ [The worked example: the new content element wizard](core-version-aware-code.md#the-worked-example-the-new-content-element-wizard)

The core requires an icon identifier, and none of this theme's own is
invented — every identifier is one the core registers (`content-header`,
`content-text-teaser`, `content-beside-text-img-left`, `content-card-group`,
`content-quote`, `content-user`, `content-bullets`, `content-listgroup`, and
`content-message`, `content-tab`, `content-accordion` for the notice, the
tabs and the accordion, `content-idea` for the text and icon element, and
`content-widget-list`, `content-widget-number` and `content-target` for the
features, the figures and the steps), verified present in the core's own icon registry
(`.Build/vendor/typo3/cms-core/Resources/Public/Icons/T3Icons/icons.json`),
not shipped as image files of this extension's own. An identifier that is
*not* registered does not fail quietly: `IconRegistry::getIconConfigurationByIdentifier()`
throws (`Exception`, code `1437425804`, "Icon with identifier … is not
registered") the moment something tries to resolve it — there is no silent
fallback icon to lean on if a future addition typos one.

## Appearance fields

The core `Appearance` tab and the header palette carry fields an editor sets
per element. All of them used to be ignored: the layout rendered the wrapper and
nothing else. Each field is now either rendered or taken out of the form. None
is left in the form with no effect.

| Field                   | Rendered as                                                                              | Where                                 |
|-------------------------|------------------------------------------------------------------------------------------|---------------------------------------|
| `frame_class`           | `--frame-surface`, `--frame-raised`, `--frame-accent`, `--frame-inverse`, `--frame-none` | `Layouts/ContentElement.html`         |
| `space_before_class`    | `--space-before-{extra-small … extra-large}`                                             | `Layouts/ContentElement.html`         |
| `space_after_class`     | `--space-after-{extra-small … extra-large}`                                              | `Layouts/ContentElement.html`         |
| `header_position`       | `__header--center`, `--end` for `right`, `--start` for `left`                            | `Partials/ContentElement/Header.html` |
| `tx_theme_header_style` | `.theme-display`, or `__heading--h1` … `--h5`                                            | `Partials/ContentElement/Header.html` |
| `layout`                | per CType: `bullets` and `text` render it, every other type has it disabled              | page TSconfig, the element template   |
| `sectionIndex`          | disabled                                                                                 | page TSconfig                         |
| `linkToTop`             | disabled                                                                                 | page TSconfig                         |

Every value is matched by an `f:case`, and anything else renders no modifier:
the default, a value the page TSconfig removed that an older record still
carries, and a value nothing ever offered. A class written whatever the field
holds would be a class no rule matches.

**`frame_class`** keeps the core values `default` and `none`.
[`ContentElementAppearance.tsconfig`](../../Configuration/PageTsConfig/ContentElementAppearance.tsconfig)
adds four bands with `addItems` and removes the rulers and indents the theme
does not draw with `removeItems`. `none` keeps the development outline and
drops the inner padding, for an element with a box of its own. How the bands
are drawn, and why the inverse band turns the colour scheme instead of
re-pointing tokens, is in
[Component library](../development/component-library.md#content-element-appearance);
their contrast is in [`DESIGN.md`](../../DESIGN.md#content-element-bands).

**`space_*_class`** take the five values of the core select, which are the same
on v12.4 and v13.4 (`EXT:frontend/Configuration/TCA/tt_content.php`, read on
both). They map onto `--theme-space-2`, `-4`, `-6`, `-7` and `-8`.

**`header_position`** maps the core's `left` and `right` onto the logical
edges. The stylesheet is written in logical properties, and an end-aligned
header stays at the end of the line in a right-to-left language.

**`tx_theme_header_style`** is the one new column: a `select` in
`Configuration/TCA/Overrides/tt_content.php`, placed after `header_layout` in
the core palettes `headers` and `header`. Like every column of this extension
on this branch it is declared in [`ext_tables.sql`](../../ext_tables.sql) as
well: v13's `DefaultTcaSchema` derives a `varchar(255)` from a select with
string values, v12.4's derives nothing for a select, and the declaration is the
definition v13 derives. `DatabaseSchemaTest` holds it. The level stays the
level: an `h3` in the look of heading 1 is still an `h3` in the outline.
`display` reuses the text role `.theme-display` instead of repeating its
metrics.

**Disabled, not rendered.** `layout` is disabled for every CType and
re-enabled per type where a template renders it: the bullet list and the text
element, above. `sectionIndex` and `linkToTop` wait for a table of contents
and a link back to the top.
`sectionIndex` keeps its TCA default of `1`, so elements created meanwhile
are already part of that index. `header_position` and `tx_theme_header_style`
are disabled per type for the CTypes that render their title outside the
shared partial: the three heroes, `theme_media_teaser` and
`theme_testimonial`.

The page TSconfig lives in `Configuration/page.tsconfig`, the file the core
loads from every active package, next to the backend layouts. The page TSconfig
of the site set would reach set sites only, and the static include delivers no
page TSconfig at all. The price is that it applies to every page tree of the
installation, a site that does not use the theme included — the same
trade-off the backend layouts make.

Page TSconfig is assembled in a fixed order (`TsConfigTreeBuilder`, read on
v12.4 and v13.4): the `Configuration/page.tsconfig` of every package first, then
`$GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPageTSconfig']`, then on v13.4 that
of the site — its sets and `config/sites/<site>/page.tsconfig`, which v12.4 does
not have — then the included files and the `TSconfig` field of every page down
the rootline. Later wins, so a foreign site restores the core form in any of
the later places:

```typoscript
TCEFORM.tt_content {
    frame_class.removeItems >
    frame_class.addItems >
    layout.disabled = 0
    sectionIndex.disabled = 0
    linkToTop.disabled = 0
}
```

A site package using the theme adds a removed frame back the same way, and then
styles the class itself.

The theme's own `theme_*` types have no `Appearance` tab in their `showitem`,
so an editor sets these fields on the classic types only. The layout renders
the modifiers for any CType whose record carries them.

| Test                                                          | Guards                                                                                              |
|---------------------------------------------------------------|-----------------------------------------------------------------------------------------------------|
| `Tests/Functional/ContentElementAppearanceRenderingTest.php`  | every value renders its modifier, and an unknown value none, through the set and the static include |
| `Tests/Functional/ContentElementAppearanceFormEngineTest.php` | the form offers the bands and the looks, and disables the fields above, per type where it should    |
| `Tests/Unit/ContentElementContractTest.php`                   | every class the two templates can write is a selector of the compiled stylesheet                    |

Breaking the `inverse` case of the layout and the `right` case of the header
partial fails four cases of the rendering test, one for each field on each
path. Dropping the TSconfig import fails six of the seven form cases. Renaming
one written class fails the contract test.

## Extbase plugins, and `tt_content.list`

Everything above is a `CType` this theme's own TypoScript branch is written
for by name. An Extbase plugin registered through
`TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin()` is different:
the method generates its own TypoScript, and it does so whether or not
`fluid_styled_content` is installed. Read in the installed core
(`.Build/vendor/typo3/cms-extbase/Classes/Utility/ExtensionUtility.php`), it
emits, verbatim, for a plugin registered with the `CType` type — the only type
that does not log a deprecation on v13.4, and a spelling v12 accepts unchanged:

```typoscript
tt_content.<pluginSignature> =< lib.contentElement
tt_content.<pluginSignature> {
    templateName = Generic
    20 = EXTBASEPLUGIN
    20 {
        extensionName = <ExtensionName>
        pluginName = <PluginName>
    }
}
```

`=< lib.contentElement` is generated unconditionally, while nothing outside
this theme defines that object at all — grepping the whole installed v13.4.34
vendor tree for `lib.contentElement =` returns nothing. This
theme is therefore not an optional convenience for a third-party plugin, it is
the only thing that makes one render: without a `Generic` template the plugin
falls through to the same core "no rendering definition" notice as any
uncovered classic type.

`Resources/Private/Templates/Generic.html` is that template — at the *root*
of `templateRootPath`, not below `ContentElements/` the way every other
template in the coverage table above is. That is not a stray inconsistency:
`templateName = Generic` is a fixed string `configurePlugin()` writes itself,
never `ContentElements/Generic`, and `FluidTemplateContentObject` resolves it
literally. A copy placed under `ContentElements/` was tried first and fails
with `TYPO3Fluid\Fluid\View\Exception\InvalidTemplateResourceException` —
confirmed by actually running it, not inferred — which is why it sits where it
does.

The template itself cannot be per-plugin, so it does not try: it reads back
the `20` cObject `configurePlugin()` places beside `templateName` in the very
same branch, at a path built from the current record rather than fixed —
`tt_content.{data.CType}.20` — because `CType` *is* the plugin signature for a
plugin registered this way (`ExtensionUtility::registerPlugin()` adds that
exact string as the `CType` select item's value). Confirmed to actually
resolve with a throwaway functional test against a fixture plugin before being
deleted again — `f:cObject`'s own
`typoscriptObjectPath` resolves from the root of the merged setup array, not
relative to the branch it is rendered from, which is why the path spells out
`tt_content` rather than reading `20` alone.

The call passes `data="{data}" table="tt_content"`, and it has to. Without
them `CObjectViewHelper` starts its content object renderer with an empty
record, and the plugin runs without its content element: no FlexForm settings
— the `pi_flexform` of the record is what Extbase merges into `settings` — and
an empty record as `currentContentObject` on the Extbase request. The plugin
still renders, which is why nothing looks wrong. `ExtbasePluginRenderingTest`
holds it for a cached and for a non-cacheable plugin, which is rendered after
the page from a serialised content object renderer: the fixture plugin prints
a FlexForm setting and the uid of its content element. The `list_type` CASE
of `tt_content.list` depends on the record the same way and has no test of its
own — registering a `list_type` plugin raises a deprecation on v13.4, which
this suite turns into a failure.

`Tests/Functional/Fixtures/Extensions/plugin-fixture` is the fixture that
keeps this covered going forward: an Extbase plugin registered with **no**
TypoScript of its own — unlike `tests/example-fixture`, which deliberately
overrides what `configurePlugin()` generates (see that extension's own
`Configuration/TypoScript/setup.typoscript`) — so the only thing that can make
it render is this theme's `lib.contentElement` and `Generic.html`.

That holds for a site using the site set. On the static include path, the
TypoScript `configurePlugin()` generates reaches the page only because
`ext_localconf.php` registers the static include as a content rendering
template — without it every plugin rendered the core notice there, see
[TypoScript delivery](typoscript-delivery.md#plugins-and-the-static-include-as-a-content-rendering-template).

### `tt_content.list`

One CType still reaches `configurePlugin()`'s *other* branch: `list`, the
historical "General Plugin" registration, present in EXT:frontend's own TCA on
both supported versions (`Configuration/TCA/tt_content.php`, `types.list`, the
`CType` select's own `value => 'list'`) — deprecated on v13.4 (Deprecation
#105076) and not deprecated at all on v12.4. A plugin registered with
`configurePlugin()`'s fifth argument omitted or `list_type` writes into it
directly on either:

```typoscript
tt_content.list.20.<pluginSignature> = EXTBASEPLUGIN
```

`fluid_styled_content` supplied `tt_content.list` itself, as a `CASE` object
keyed on `list_type` — and it is not a dependency here. Without that `CASE`
object, the assignment above has nothing to add a branch to, and a `list`
element renders the core notice the same as every other uncovered type.

`ContentElements.typoscript` declares it, in this theme's own house style —
`=< lib.contentElement`, `templateName = Generic` — which lets `Generic.html`
render it too: a `list` record's own `CType` is `list`, so
`{data.CType}.20` resolves to `tt_content.list.20`, the `CASE` itself, in
place of a single plugin's `EXTBASEPLUGIN`.

It is declared **unconditionally**, with no `[...]` condition around it: every
supported core version still carries the `list` CType, so there is no
difference to resolve and nothing to guard. Verified rather than assumed —
`grep -n "'list'\|list_type" .Build/vendor/typo3/cms-frontend/Configuration/TCA/tt_content.php`
returns `types.list`, the `CType` select item and
`subtype_value_field = list_type` on the v12.4 dependency set as it did on
v13.4.

Deprecated is not the same as gone. #105076 asks plugin authors to register
with the `CType` type instead — which the fixture plugins in this repository
do — but records authored the old way still exist in real installations, and
this branch is what renders them instead of leaving the core notice on the
page. It costs one `=< lib.contentElement` and no version aware code at all.

## EXT:felogin

The login form is a plugin, `felogin_login`, and renders through `Generic.html`
like any other. Its markup is felogin's templates, and the theme replaces two
of them — `Login/Login` and `Login/Logout`, below
`Resources/Private/Extensions/Felogin/Templates/` — by adding its path above
felogin's own at `plugin.tx_felogin_login.view.templateRootPaths.20`
(`Configuration/TypoScript/Felogin.typoscript`). Fluid looks a template up path
by path from the highest key down (`TemplatePaths::resolveFileInPaths()`), so
the theme's `Login/Login.html` wins over felogin's own of the same name on v12
and v13 alike. The password recovery templates are not replaced.

The templates keep every field felogin sends and change the markup around
them: `.theme-form`, a `.theme-fieldset` with its legend, `.theme-field` with
the required marker, `.theme-input`, the permanent login as a `.theme-check`,
and a `.theme-button`. The status message becomes an alert of the kind it is:
a failed login is `--danger` with `role=alert`, a logout `--success` with
`role=status`; the welcome text stays a heading and a paragraph. The glyph comes
from the same `ContentElement/AlertIcon` partial the notice uses, which is why
the theme's partial root path is added to the plugin as well.

One template serves both core versions. The labels are full `LLL:` references,
and felogin's `RenderLabelOrMessage` partial is repeated as a section of each
template, so the templates read nothing of felogin's but the variables its
controller assigns. The logout form passes `actionUri` through, as felogin's
own template does: the logout action of v12 and of v13 assigns the logout
redirect target there, and the form posts to it.

`FeloginRenderingTest` renders the form through the delivery of the running
core version — the set on v13, the static include on v12 — and through the
static include, and asserts the theme's markup and the absence of felogin's own;
`DevelopmentInstance/LoginPageTest` and `Tests/Acceptance/frontend-login.spec.ts`
log in and out on the seeded instance.

## Not yet: EXT:form

EXT:form is installed in neither instance nor in the root dependency set, so
the theme has no form content element, and [the form showcase](../development/form-showcase.md)
is a page of literal markup. Theming EXT:form is a step of its own. What is
known about it for the two core versions of this branch:

- **Templates.** EXT:form renders its Bootstrap 5 compatible templates with
  `renderingOptions.templateVariant: version2` (Feature #94868, v11.5); the
  legacy templates are deprecated since v12.0 (Deprecation #95456, "Deprecate
  legacy form templates"), which covers both v12 and v13.
- **Registration.** How the form YAML is registered has to be read in the
  EXT:form of both dependency sets once it is added to them. Nothing of it can
  be verified from this checkout today, so nothing about it is stated here.
- **Class mapping or partials.** The class names are YAML properties of the
  form elements, so most of the contract can be mapped without a template; the
  checkbox, radio and summary structure need partial overrides.

## See also

- [Page rendering](page-rendering.md)
- [Component library](../development/component-library.md)
- [Dependency injection](dependency-injection.md)
- [Functional tests](../testing/functional-tests.md)
