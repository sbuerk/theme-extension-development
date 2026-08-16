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

| `CType`     | Template                    | Processing                                                                      |
|-------------|-----------------------------|---------------------------------------------------------------------------------|
| `header`    | `ContentElements/Header`    | —                                                                               |
| `text`      | `ContentElements/Text`      | —                                                                               |
| `image`     | `ContentElements/Image`     | `FilesProcessor` (`image`) → `GalleryProcessor`                                 |
| `textpic`   | `ContentElements/TextPic`   | `FilesProcessor` (`image`) → `GalleryProcessor`                                 |
| `textmedia` | `ContentElements/TextMedia` | `FilesProcessor` (`assets`) → `GalleryProcessor`                                |
| `bullets`   | `ContentElements/Bullets`   | `SplitProcessor` (core)                                                         |
| `table`     | `ContentElements/Table`     | `TableProcessor` (this extension)                                               |
| `uploads`   | `ContentElements/Uploads`   | `FilesProcessor` (`media` + `file_collections`)                                 |
| `div`       | `ContentElements/Div`       | —                                                                               |
| `html`      | `ContentElements/Html`      | —                                                                               |
| `shortcut`  | `ContentElements/Shortcut`  | `RECORDS` cObject, wired as a TypoScript variable, not a `dataProcessing` entry |

`header`, `text` and `image` predate this page — see
[Page rendering](page-rendering.md) and the `Feature-ContentElementRendering`
/ `Feature-ImageContentElementRendering` changelog entries. Everything from
`textpic` down is what this page documents.

Out of scope: the eleven `menu_*` types. They are the last of the classic set
`EXT:frontend` registers that still renders the core notice, and they differ
from everything above in needing a `MenuProcessor` configured per menu type
rather than only a template — tracked as a `@todo` in
`ContentElements.typoscript`, not covered here.

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

## See also

- [Page rendering](page-rendering.md)
- [Component library](../development/component-library.md)
- [Dependency injection](dependency-injection.md)
- [Functional tests](../testing/functional-tests.md)
