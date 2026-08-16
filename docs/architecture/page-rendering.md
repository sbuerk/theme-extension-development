# Page rendering

How a page's backend layout becomes a rendered page: backend layout
registration, template name resolution, content slots, and the Fluid
structure that assembles them. This page is the detail behind the "page
rendering" paragraph in
[TypoScript delivery](typoscript-delivery.md#page-rendering) — read that page
first for why `page.10` is a `FLUIDTEMPLATE` object at all and how content
elements render without `fluid_styled_content`; this page does not repeat
either.

## Why `FLUIDTEMPLATE` and not `PAGEVIEW`

`PAGEVIEW` exists since TYPO3 v13.1
(`Feature-103504-NewContentObjectPageView.rst`), so age is not the reason.
The reason is the layer normally used with it: `ContentAreaCollection`,
`{content.main.records}` and the `f:render.contentArea` /
`f:render.record` ViewHelpers landed only in **v14.2**
(`Feature-104974-ContentAreaRelatedInformationInTheFrontend.rst`). A template
written against that API does not compile on v13.4 — the ViewHelpers do not
exist there. `PAGEVIEW` itself would run, but nothing sane to render content
areas with is available at v13.4.

`FLUIDTEMPLATE` is not deprecated on either version, and the v14.2 changelog
entry does not present `PAGEVIEW` as its replacement — it describes it as "a
powerful alternative to the `FLUIDTEMPLATE` cObject, allowing a full page to
be rendered with less configuration." Less configuration is not on offer here:
a v12/v13 downgrade branch of this extension is planned, and `PAGEVIEW`
would have to be abandoned for it anyway. Building on `FLUIDTEMPLATE` now
means the page rendering layer needs no `Core13/`/`Core14/` split and nothing
has to be unwound later — see
[Core version aware code](core-version-aware-code.md) for why a split in
`Resources/` specifically would be the most awkward kind, Fluid files not
being selectable by the container the way classes are.

## Backend layout registration

Backend layouts are registered as **PageTsConfig**, in
[`Configuration/page.tsconfig`](../../Configuration/page.tsconfig), which
imports one file per layout from
[`Configuration/PageTsConfig/BackendLayouts/`](../../Configuration/PageTsConfig/BackendLayouts).
TYPO3 auto-loads `Configuration/page.tsconfig` from every package since v12.0
(`Feature-96614-AutomaticInclusionOfPageTsConfigOfExtensions.rst`) — no
registration call, no DB seeding, and (verified against the v14.3 loader,
`TsConfigTreeBuilder::184`) still true on the newer version.

That beats both alternatives available for registering a backend layout:

- A `backend_layout` **database record** exists only if something inserts
  it — which needs its own registration step, and does not survive a fresh
  installation without seeding.
- A custom **`BackendLayoutDataProvider`** needs a registration call of its
  own (`ExtensionManagementUtility::addBackendLayoutDataProvider()` or a
  service tag, depending on core version), which is exactly the kind of
  version aware wiring this file avoids.

Neither alternative is what actually decides this, though. This extension is
delivered two ways — as a **site set** and as a classic `sys_template`
**static include** — and PageTsConfig applies identically under both, because
TYPO3 loads it before either code path runs. A `backend_layout` database
record and a custom data provider are each wired into exactly one of those
two delivery mechanisms, never both, which is precisely the split
[TypoScript delivery](typoscript-delivery.md) exists to avoid for the
TypoScript itself.

## The layout table

| Identifier        | Title                     | Template resolved          |
|-------------------|---------------------------|----------------------------|
| `default`         | Default                   | `Page/Default.html`        |
| `content`         | Content page              | `Page/Content.html`        |
| `content_sidebar` | Content page with sidebar | `Page/ContentSidebar.html` |
| `start`           | Start page                | `Page/Start.html`          |
| `styleguide`      | Styleguide                | `Page/Styleguide.html`     |

`default` is also the literal fallback identifier
`PageLayoutResolver::getLayoutIdentifierForPage()` returns when neither a
page nor any ancestor has a `backend_layout` set — every page not otherwise
configured reaches it, so it has to exist as a real layout, not merely be
selectable.

### Column layout

Column numbers mirror `EXT:theme_camino`, so content is portable between the
two themes:

| Slot         | colPos | In layouts                            |
|--------------|--------|---------------------------------------|
| `main`       | 0      | all except `styleguide`               |
| `sidebar`    | 1      | `content_sidebar`                     |
| `stage`      | 2      | `content`, `content_sidebar`, `start` |
| `footermeta` | 10     | `content`, `content_sidebar`, `start` |
| `footer1`    | 11     | `content`, `content_sidebar`, `start` |
| `footer2`    | 12     | `content`, `content_sidebar`, `start` |
| `footer3`    | 13     | `content`, `content_sidebar`, `start` |
| `footer4`    | 14     | `content`, `content_sidebar`, `start` |

`default` has **only** `main`. `styleguide` declares one column at `colPos
999` — deliberately outside the range any `lib.content.*` object reads —
rather than none at all: an empty `config.backend_layout` block is dropped
from the backend layout selector entirely by
`PageTsBackendLayoutDataProvider::generateBackendLayoutFromTsConfig()`, and a
`rows` key present but empty renders a page module grid with zero rows and no
"new content element" affordance anywhere. Both are worse than a column
nothing ever renders; the `styleguide` layout's own tsconfig comment spells
this out. `identifier = unused` rather than reusing `main`, because reusing
`main` would suggest the column behaves like every other layout's.

## Template name resolution

The template name is resolved once, verbatim from
[`Configuration/TypoScript/Page.typoscript`](../../Configuration/TypoScript/Page.typoscript):

```typoscript
templateName = TEXT
templateName {
    data = pagelayout
    ifEmpty = default
    replacement.10 {
        search = #^.*__#
        replace =
        useRegExp = 1
    }
    replacement.20 {
        search = #^none$#
        replace = default
        useRegExp = 1
    }
    case = uppercamelcase
}
templateName.wrap = Page/|
```

Four things about it are load-bearing:

- **`data = pagelayout`, never `field = backend_layout`.** The `pagelayout`
  getter resolves through `PageLayoutResolver`, which falls back to the first
  ancestor's `backend_layout_next_level` when the page's own field is empty.
  Reading `backend_layout` directly ignores that inheritance entirely, and
  every sub-page of a configured parent would silently render the wrong
  template — silently, because nothing about a missing field looks like an
  error. The getter itself is nothing new: it has existed since 7.5 (#69602),
  and the only change since is v13.0 (#102715), which moved where it reads
  the page record from, not what it returns. Verified present and unchanged
  in that respect on both v13.4 and v14.3.
- **The `pagets__` prefix is stripped.** `replacement.10` removes it with a
  regular expression. The PageTsConfig provider prefixes every identifier it
  returns with `pagets__` — it also has to distinguish layouts that come from
  database records, which are prefixed `0_` instead — so the raw value is
  `pagets__content`, not `content`, and has to be stripped before it is
  usable as a file name.
- **`replacement.20` maps the literal string `none` to `default`.**
  `PageLayoutResolver::getLayoutIdentifierForPage()` returns exactly that
  string — not empty — when an editor picks TYPO3's own built-in "[None]"
  option in the page properties, an option every page offers regardless of
  which layouts this theme registers. Because it is not empty, `ifEmpty`
  above never sees it; left alone it would resolve to a template called
  `Page/None.html`, which this theme does not ship, and the request would end
  in an exception rather than degrade. Falling back to `default` here is the
  same choice `ifEmpty` already makes for a page with no layout at all —
  `BackendLayoutRenderingTest` pins both fallbacks down explicitly.
- **`case = uppercamelcase` and `wrap = Page/|`, in that order — not because
  they were written that way, but because `wrap` runs last regardless.**
  `case` maps the identifier to the file name — `content_sidebar` becomes
  `ContentSidebar`, matching `Templates/Page/ContentSidebar.html`. `wrap`
  reliably applies after every other stdWrap property, including `case`,
  because `ContentObjectRenderer::STD_WRAP_ORDER` fixes the sequence stdWrap
  properties execute in and `wrap` is one of the last entries in it; the
  order in the TypoScript file itself has no effect on this.

## Two inheritance edges

`BackendLayoutRenderingTest` pins down two edges of `PageLayoutResolver`'s
inheritance that are easy to get backwards:

- **A page's own `backend_layout_next_level` never applies to itself.** The
  resolver `array_shift()`s the rootline before searching it for
  `backend_layout_next_level`, so that setting only ever reaches a page's
  children. The root page in the test fixture sets `next_level`, and the root
  itself still resolves to `default` — `'a page does not inherit its own next
  level setting'` in `resolvedLayouts()`.
- **Inheritance reaches further than one level up.** A page two levels below
  the one that sets `backend_layout_next_level` still resolves it —
  `'inheritance reaches further than one level'`, asserted against
  `/inherits/deeper` in addition to the direct child `/inherits`. A test that
  only checked the immediate child would pass against an implementation that
  resolved exactly one level and stopped, which is a plausible enough bug to
  be worth ruling out explicitly.

Both are why `templateName` reads `pagelayout` and not `backend_layout` in
the first place: reading the field directly only reproduces the *first* of
these two behaviours by accident (an empty field falling through to
`ifEmpty`), never the second.

## Content slots

Defined in
[`Configuration/TypoScript/ContentSlots.typoscript`](../../Configuration/TypoScript/ContentSlots.typoscript),
one `lib.content.<slot>` `CONTENT` object per slot, rather than one object
switched by a variable, so a site package can override exactly one column —
say `sidebar` — without repeating or touching the other five. Every object
selects through `where = {#colPos}=<n>`, quoted so it runs through the
QueryBuilder's field quoting rather than a bare `colPos=0` string
concatenation.

The five footer slots — `footermeta`, `footer1`–`footer4` — additionally
carry `select.slide = -1`:

```typoscript
lib.content.footermeta {
    table = tt_content
    select {
        orderBy = sorting
        where = {#colPos}=10
        slide = -1
    }
}
```

A `CONTENT` object normally reads only the current page's `colPos`. `slide`
makes it walk the rootline instead — a positive number is a level count, `-1`
walks upward without a limit, stopping the moment a page has content in that
column (`ContentContentObject::render()`, the loop continues only `while ...
$slide && $tmpValue === ''`). With nothing placed in these columns below the
site root, every page finds the root page's content, one level up or fifty.
This reproduces what `EXT:theme_camino` gets for the same columns from
`slideMode = slide` on `lib.contentElement`, expressed the way a bare
`CONTENT` object does it — this theme does not depend on
`fluid_styled_content`, so there is no `lib.contentElement` to configure a
slide mode on.

## Column identifiers are mandatory

Every column in every `*.tsconfig` file under
`Configuration/PageTsConfig/BackendLayouts/` carries an `identifier` in
addition to `name` and `colPos`. On TYPO3 v14,
`ContentAreaResolver::collectContentAreasRecursive()` raises a deprecation
for a column without one, and the deprecation is scheduled to become a thrown
exception in v15. On v13 the same column configuration is accepted without
comment — the check does not exist yet. One spelling with `identifier`
present therefore serves both core versions; there is nothing to split.

This was not designed in from a changelog reading — it was found by the
strict test suite doing what [the strictness
policy](../testing/phpunit-configuration.md#strictness-policy) is for: a
deprecation notice fails the functional suite outright rather than being
logged and ignored, which is what surfaced the missing identifiers here.

## The Fluid structure

One file per unit, so each is overridable on its own without pulling in the
rest:

```
Layouts/Default.html                  the page shell
Templates/Page/Default.html
Templates/Page/Content.html
Templates/Page/ContentSidebar.html
Templates/Page/Start.html
Templates/Page/Styleguide.html
Partials/Page/Header.html
Partials/Page/Footer.html
Partials/Page/Stage.html
Partials/Page/Sidebar.html
```

`Layouts/Default.html` renders the header and footer partials
unconditionally, then:

```html
<div class="theme-page__body">
    <f:render section="Aside" optional="1" />
    <main class="theme-page__main" id="content">
        <f:render section="Main" />
    </main>
</div>
```

Only `Templates/Page/ContentSidebar.html` defines an `Aside` section — it is
the only backend layout with a `sidebar` column. Every other template leaves
the section undefined, and `f:render ... optional="1"` then renders nothing
at all rather than an empty element. That distinction is not cosmetic:
`layout/_page.scss` switches from a single-column to a two-column grid with
`:has(.theme-page__aside)`. An `<aside>` emitted unconditionally and merely
left empty by CSS would still satisfy that selector and produce a
permanently empty second column on every layout without a sidebar — a
section left undefined is the only way to keep the element out of the markup
entirely, which is what the selector actually needs.
`BackendLayoutRenderingTest::onlyTheSidebarLayoutEmitsAnAside()` asserts
exactly this: an aside on `/own` (`content_sidebar`), and none on any of the
other layouts.

## `data-theme-page-layout` on `.theme-page`

`Page.typoscript` resolves the backend layout identifier a second time, in
its own `pageLayout` variable, and the page shell renders it as an attribute:

```html
<div class="theme-page" data-theme-page-layout="{pageLayout}">
```

This duplicates the resolution `templateName` already does — deliberately,
because the two want different shapes by the time they are needed:
`templateName` has already been upper-camel-cased and prefix-stripped, and
undoing that would need a second stdWrap chain rather than avoiding one. The
two must be changed together.

It exists as a development affordance: which backend layout a page actually
resolved to is otherwise invisible in the rendered frontend, and
`backend_layout_next_level` inheritance is exactly the kind of thing that
goes wrong quietly — a sub-page silently rendering its parent's layout looks
identical to it rendering its own, right up until a column that only exists
in one of them is missing. `BackendLayoutRenderingTest` reads this attribute
rather than any markup difference between templates, for the same reason: it
is the one place the resolved identifier is asserted directly, independent
of what each template happens to render.

## Only `Page/Default.html` renders the page title

```html
<h1 class="theme-page__title">{pageTitle}</h1>
```

appears in `Templates/Page/Default.html` and nowhere else. `default` is the
one layout with no `stage` slot, so nothing editorial is guaranteed to carry
a heading — a page on that layout with no content yet would otherwise render
an empty `<main>` with no indication of which page it is, which is common
enough in a theme meant for extension development that it needs handling
rather than being an edge case. The layouts that do have a stage —
`content`, `content_sidebar`, `start` — deliberately leave the heading to
whatever is placed in it: emitting one in the template as well would give
those pages two first-level headings, one from the shell and one from the
content.

## See also

- [TypoScript delivery](typoscript-delivery.md)
- [Core version aware code](core-version-aware-code.md)
- [Component library](../development/component-library.md)
- [Functional tests](../testing/functional-tests.md)
