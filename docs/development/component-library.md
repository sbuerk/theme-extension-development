# Component library

The stylesheet under `Resources/Private/Scss/` is a component library, not a
framework: every rule serves one of the markup contracts documented in this
page, there is no grid system, no utility classes and no JavaScript this
library depends on to render correctly. [Frontend assets](frontend-assets.md)
covers the source tree, the token layers and the build; this page covers what
the library actually contains — the components, the markup each one expects,
and the two switches that change behaviour rather than appearance.

Every class is prefixed `theme-`. This theme is the ground other extensions
under development get built and demonstrated against, and those extensions
bring their own CSS. An unprefixed `.button` or `.card` is exactly the kind of
name a third-party stylesheet also reaches for, and a collision between the two
would be silent — whichever rule loads last wins, with no error to point at it.
The prefix is namespacing, nothing more elaborate.

There are no exceptions. The image gallery predates the library and shipped as
`.gallery`; it was renamed to `.theme-gallery` rather than left alone, because
`.gallery` is exactly the kind of generic name the prefix exists to protect
against, and the rename was cheap while only one template depended on it.

## Component reference

| Component               | Root class                | File                                |
|-------------------------|---------------------------|-------------------------------------|
| Accordion               | `.theme-accordion`        | `components/_accordion.scss`        |
| Alert                   | `.theme-alert`            | `components/_alert.scss`            |
| Author                  | `.theme-author`           | `components/_author.scss`           |
| Badge                   | `.theme-badge`            | `components/_badge.scss`            |
| Breadcrumb              | `.theme-breadcrumb`       | `components/_breadcrumb.scss`       |
| Button                  | `.theme-button`           | `components/_button.scss`           |
| Card                    | `.theme-card`             | `components/_card.scss`             |
| Close button            | `.theme-close`            | `components/_close.scss`            |
| Code block              | `.theme-code`             | `components/_code.scss`             |
| Content element wrapper | `.theme-content-element`  | `components/_content-element.scss`  |
| Content menu            | `.theme-content-menu`     | `components/_content-menu.scss`     |
| Description list        | `.theme-dl`               | `components/_description-list.scss` |
| Display settings        | `.theme-settings`         | `components/_settings.scss`         |
| Dialog                  | `.theme-dialog`           | `components/_dialog.scss`           |
| Divider                 | `.theme-divider`          | `components/_divider.scss`          |
| Figure                  | `.theme-figure`           | `components/_figure.scss`           |
| Gallery                 | `.theme-gallery`          | `components/_gallery.scss`          |
| Hero                    | `.theme-hero`             | `components/_hero.scss`             |
| Icon                    | `.theme-icon`             | `components/_icon.scss`             |
| List                    | `.theme-list`             | `components/_list.scss`             |
| Main navigation         | `.theme-nav-main`         | `components/_nav-main.scss`         |
| Sub navigation          | `.theme-nav-sub`          | `components/_nav-sub.scss`          |
| Pagination              | `.theme-pagination__list` | `components/_pagination.scss`       |
| Panel                   | `.theme-panel`            | `components/_panel.scss`            |
| Quote                   | `.theme-quote`            | `components/_quote.scss`            |
| Segmented control       | `.theme-segmented`        | `components/_settings.scss`         |
| Palette swatch          | `.theme-swatch`           | `components/_settings.scss`         |
| Skip link               | `.theme-skip-link`        | `components/_skip-link.scss`        |
| Table                   | `.theme-table-wrapper`    | `components/_table.scss`            |
| Tabs                    | `.theme-tabs`             | `components/_tabs.scss`             |
| Teaser                  | `.theme-teaser`           | `components/_teaser.scss`           |
| Text: display           | `.theme-display`          | `components/_text.scss`             |
| Text: eyebrow           | `.theme-eyebrow`          | `components/_text.scss`             |
| Text: lead              | `.theme-lead`             | `components/_text.scss`             |
| Tooltip                 | `.theme-tooltip`          | `components/_tooltip.scss`          |
| Form controls           | `.theme-input`            | `forms/_controls.scss`              |
| Form switch             | `.theme-switch`           | `forms/_controls.scss`              |
| Form field wrapper      | `.theme-field`            | `forms/_field.scss`                 |
| Form choice group       | `.theme-choice-group`     | `forms/_choice-group.scss`          |
| Form input group        | `.theme-input-group`      | `forms/_input-group.scss`           |
| Form validation         | `.theme-field--invalid`   | `forms/_validation.scss`            |
| Page frame              | `.theme-page`             | `layout/_page.scss`                 |
| Site header             | `.theme-site-header`      | `layout/_site-header.scss`          |
| Site footer             | `.theme-site-footer`      | `layout/_site-footer.scss`          |
| Styleguide page         | `.theme-styleguide`       | `layout/_styleguide.scss`           |

`.theme-pagination` has no rule of its own — only `__list`, `__link` and
`__ellipsis` are styled, current-page state comes from `[aria-current="page"]`
rather than a modifier class. `theme.scss` is the authoritative list and the
cascade order; `Tests/Unit/ComponentLibraryTest::everyComponentIsPartOfTheBundle`
asserts every selector above is actually compiled into
`Resources/Public/Css/theme.css`, and the two display sizes that are not the
bare class besides. The palette swatch is covered twice over,
because it duplicates colour that lives in `abstracts/_palettes.scss` and
`abstracts/_tokens.scss` — see
[Appearance switching](appearance-switching.md#palette-swatches-carry-literal-colours).
The segmented control and the swatch are blocks of their own in
`components/_settings.scss`: nothing about them depends on sitting in the
settings panel, but that panel is their only consumer so far.

## Markup contracts

Every fragment below is copied verbatim from the header comment of the file
that implements it. Where a component reads a token another file declares
(`--theme-space-*`, `--theme-color-*`, …) that is covered by
[Frontend assets § Component tokens](frontend-assets.md#component-tokens), not
repeated here.

### Icon

One icon of the vendored Font Awesome Free solid set, inline. It is never
written by hand: `<theme:icon name="…" />` renders it — see
[Icons](icons.md) for the ViewHelper, the set and the rule:

```html
<svg class="theme-icon" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Free … --><path fill="currentColor" d="…"/></svg>
<svg class="theme-icon" role="img" aria-label="…" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">…</svg>
```

The attribution comment of the file stays in every rendered icon, see
[Icons § Licence and attribution](icons.md#licence-and-attribution).

A square one em wide, filled with `currentColor`, `overflow: visible` and set
`-0.125em` below the baseline in a line of text — the two values Font Awesome's
own stylesheet uses. `--theme-icon-size` is **read with a fallback and never
declared on the icon**: a component that gives icons a slot sets it on the slot,
and every icon inside follows. Declared on `.theme-icon`, it would beat the value
the slot passes down. Icons wider than tall (576 or 640 units against 512) keep
the square box and come out lower; Font Awesome's own 1.25em wide box would not
fit the square slots below.

| Component            | Icon                                                                                       | Size                                                      |
|----------------------|--------------------------------------------------------------------------------------------|-----------------------------------------------------------|
| Display settings     | `gear`; `desktop`, `sun`, `moon` for the appearance options; `check` on the chosen palette | `.theme-settings__icon`, `.theme-segmented__icon`; one em |
| Accordion            | `chevron-down`, the marker, turned when an item opens                                      | `--theme-accordion-marker-size`, through the token        |
| Main navigation      | `bars`, before the label of the toggle                                                     | one em                                                    |
| Close button         | `xmark`                                                                                    | `--theme-close-glyph-size`, through the token             |
| Alert, notice, login | one per kind, picked by `Partials/ContentElement/AlertIcon.html`                           | `--theme-alert-icon-size`, through the token              |
| Field messages       | `circle-exclamation` in an error, `circle-check` in a success                              | one em                                                    |
| Icon button          | whatever the button stands for                                                             | `--theme-button-icon-size`, through the token             |

### Navigation

Main navigation, two levels deep from `MenuProcessor`. `--active` marks the
ancestor of the current page, `[aria-current="page"]` marks the current page
itself — independently, because a top-level item can carry one without the
other:

```html
<nav class="theme-nav-main" aria-label="Main">
    <button class="theme-nav-main__toggle" aria-expanded="false" aria-controls="nav-main"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg> …</button>
    <ul class="theme-nav-main__list" id="nav-main">
        <li class="theme-nav-main__item theme-nav-main__item--active">
            <a class="theme-nav-main__link" href="…" aria-current="page">…</a>
            <ul class="theme-nav-main__list theme-nav-main__list--sub">…</ul>
        </li>
    </ul>
</nav>
```

Sub navigation — the current section, not the whole site. No `--active`
modifier: the nav is scoped to one section, so `[aria-current="page"]` alone
is enough wherever it sits:

```html
<nav class="theme-nav-sub" aria-label="Section">
    <p class="theme-nav-sub__heading">…</p>
    <ul class="theme-nav-sub__list">
        <li class="theme-nav-sub__item"><a class="theme-nav-sub__link" href="…" aria-current="page">…</a>
            <ul class="theme-nav-sub__list theme-nav-sub__list--level-2">…</ul>
        </li>
    </ul>
</nav>
```

Breadcrumb, an `<ol>` because the trail's order — root first, current page
last — is part of its meaning:

```html
<nav class="theme-breadcrumb" aria-label="Breadcrumb">
    <ol class="theme-breadcrumb__list">
        <li class="theme-breadcrumb__item"><a class="theme-breadcrumb__link" href="…">…</a></li>
        <li class="theme-breadcrumb__item" aria-current="page">…</li>
    </ol>
</nav>
```

Pagination. No modifier classes anywhere — the current page is
`aria-current="page"` on the link, so the visual state and the accessible
state cannot disagree:

```html
<nav class="theme-pagination" aria-label="Pagination">
    <ul class="theme-pagination__list">
        <li><a class="theme-pagination__link" href="…" aria-label="Previous">…</a></li>
        <li><a class="theme-pagination__link" href="…" aria-current="page">2</a></li>
        <li><span class="theme-pagination__ellipsis">…</span></li>
    </ul>
</nav>
```

Skip link, the first focusable element on the page:

```html
<a class="theme-skip-link" href="#content">Skip to content</a>
```

### Content

Accordion, built on native `<details>`/`<summary>` — a shared `name` on every
`<details>` in the group is what makes them mutually exclusive. The marker is
the `chevron-down` icon at the end of the summary, which the stylesheet turns
half a turn on `[open]`; it replaced a chevron drawn from two borders on
`::after`, so a summary written to the old contract shows no marker:

```html
<div class="theme-accordion">
    <details class="theme-accordion__item" name="faq">
        <summary class="theme-accordion__summary">… <svg class="theme-icon theme-accordion__marker" aria-hidden="true" focusable="false" …>…</svg></summary>
        <div class="theme-accordion__panel">…</div>
    </details>
</div>
```

Alert. Modifiers `--info` (default), `--success`, `--warning`, `--danger` — the
four severities — and `--note` and `--tip`, the two asides. `role` is a markup
concern the stylesheet does not touch, and it is **not the same for all six**:

| Modifier    | Is                                                      | `role`                                |
|-------------|---------------------------------------------------------|---------------------------------------|
| `--info`    | information; also the look of the bare class            | `status` — polite, waits for a pause  |
| `--success` | something finished                                      | `status`                              |
| `--warning` | needs attention before it becomes a problem             | `alert` — assertive, interrupts       |
| `--danger`  | something failed                                        | `alert`                               |
| `--note`    | an aside to the text around it                          | `note`, or none — never a live region |
| `--tip`     | a recommendation; the one kind that follows the palette | `note`, or none                       |

A note or a tip is not a message about the page and never changes, so there is
nothing a live region could announce:

```html
<div class="theme-alert theme-alert--warning" role="alert">
    <span class="theme-alert__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></span>
    <div class="theme-alert__body">
        <p class="theme-alert__title">…</p>
        <p class="theme-alert__text">…</p>
    </div>
</div>
```

`.theme-alert__text` is selected by class, so it may just as well be a `div` of
paragraphs — the notice content element renders its rich text there — and its
last child drops its bottom margin so the padding stays even.
`.theme-accordion__panel` does the same, as `.theme-tabs__panel` already did.

`--tip` takes the palette's secondary accent, and its tint is mixed from that
accent in the stylesheet — `color-mix(in oklab, secondary 12%, background)` —
rather than read from a surface token: a palette varies accents only, and a
surface token would be one more colour for every palette to author, and a copy
that could drift from the accent. Its contrast in every palette and both
appearances is recorded in [`DESIGN.md`](../../DESIGN.md#alert-asides).

Author — a person: portrait, name, role and links. The name is not part of
this markup at all: it is the content element's own heading, rendered through
the shared header partial every content element uses, so `.theme-author`
sits below it and only covers the portrait, the role line and the bio. The
links reuse `.theme-content-menu` above rather than a list of this
component's own:

```html
<div class="theme-author">
    <div class="theme-author__portrait"><img …></div>
    <div class="theme-author__body">
        <p class="theme-author__role">…</p>
        <div class="theme-author__bio">…</div>
        <nav class="theme-content-menu" aria-label="…">…</nav>
    </div>
</div>
```

Badge. Two independent axes — severity (`--info`, `--success`, `--warning`,
`--danger`) and fill (soft by default, `--solid` combined with a severity):

```html
<span class="theme-badge theme-badge--success">…</span>
<span class="theme-badge theme-badge--solid theme-badge--danger">…</span>
```

Button. Variants `--secondary`, `--ghost`, `--danger` and `--link`; `--icon`
for a square button whose label is a glyph; sizes `--small` and `--large`. It is
used both as an `<a>` and as a `<button>`, and the component normalises the
difference between them, so either is correct wherever the other is. `--ghost`
is the strict case — with no fill and no border of its own, anything a browser
supplies is the only thing visible:

```html
<a class="theme-button" href="…">…</a>
<button class="theme-button theme-button--secondary" type="button">…</button>
<button class="theme-button theme-button--danger theme-button--small" type="button">…</button>
<a class="theme-button theme-button--link" href="…">…</a>
<button class="theme-button theme-button--icon" type="button" aria-label="…"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></button>
<button class="theme-button theme-button--secondary" type="button" aria-pressed="false">…</button>
<button class="theme-button" type="button" aria-busy="true" aria-disabled="true">…</button>
```

States are read off attributes, never off a class — `:disabled` and
`[aria-disabled='true']`, `[aria-pressed='true']`, `[aria-busy='true']` — so the
state a screen reader announces and the state the button is painted in cannot
disagree. An icon button's glyph is decoration, and its name is `aria-label`.
A pressed toggle takes the filled default, which shows on `--secondary` and
`--ghost`, the variants a toggle is drawn in. `aria-busy` adds a spinner but
does not stop a click, so a busy button that must not be pressed twice carries
`aria-disabled` as well; under `prefers-reduced-motion` the spinner stands
still, because `base/_reset.scss` collapses every animation to one 1ms
iteration.

`:focus-visible` is deliberately **not** a rule of this component. The ring is
global, in `base/_reset.scss`, so every focusable thing on the page carries the
same one and a new component cannot forget it.

`.theme-button-group` lays out a row of them with the standard gap and wraps
rather than overflowing. `--attached` makes the row one control in several
parts — edges joined, only the outer corners round, no wrapping — and lifts the
button whose border means something right now (hovered, focused, pressed) over
the shared edge with `position: relative` alone, not with a `z-index` outside
the named stacking layers:

```html
<div class="theme-button-group">…</div>
<div class="theme-button-group theme-button-group--attached" role="group" aria-label="…">…</div>
```

Close button — a component of its own rather than a button modifier: no fill,
no border, no label, and a negative margin that lines up the glyph, not the
44px target around it, with the content edge. The cross is the `xmark` icon in
the markup — `<theme:icon name="xmark" />` — which replaced two borders drawn
on pseudo elements; a close button written to the old contract, empty, now
shows nothing. Its name is `aria-label`, and inside a `<form method="dialog">`
it carries `value="cancel"` instead of `type="button"`:

```html
<button class="theme-close" type="button" aria-label="Close"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></button>
```

Card. Modifier `--linked` for a card whose whole surface is the link target;
the grid takes `--wide`, which lowers the column count so the same items get
more room each:

```html
<article class="theme-card">
    <div class="theme-card__media"><img …></div>
    <div class="theme-card__body">
        <h3 class="theme-card__title">…</h3>
        <p class="theme-card__text">…</p>
        <a class="theme-card__link" href="…">…</a>
    </div>
</article>
```

```html
<div class="theme-card-grid">…</div>
```

Content element wrapper — every rendered content element, `--{CType}` and
`data-ctype` both carry the CType, one for styling hooks and one for the
diagnostic label. See [the content-element outline](#the-content-element-outline)
below for the two switches this component owns:

```html
<div class="theme-content-element theme-content-element--text" id="c123" data-ctype="text">
    <div class="theme-content-element__inner">…</div>
</div>
```

Content menu — the shared component for all eleven `menu_*` content
elements, a flat list of links unless nested for `menu_sitemap`'s tree.
`__date` and `__abstract` are only present when the underlying menu type has
one (`menu_recently_updated`, `menu_abstract`); omitted rather than rendered
empty, the same rule the navigation components use for `aria-current`. Not
`.theme-nav-sub`: a content element is authored content in the content
column, not section-scoped site chrome, and reusing the navigation component
would drag navigation styling into content rendering for two components that
only coincidentally both draw a list of links:

```html
<nav class="theme-content-menu" aria-label="…">
    <ul class="theme-content-menu__list">
        <li class="theme-content-menu__item">
            <a class="theme-content-menu__link" href="…" aria-current="page">…</a>
            <time class="theme-content-menu__date" datetime="…">…</time>
            <p class="theme-content-menu__abstract">…</p>
            <ul class="theme-content-menu__list theme-content-menu__list--sub">…</ul>
        </li>
    </ul>
</nav>
```

Code block — a `pre` of the element baseline with a file name above it, in
one frame. The `pre` is the box that scrolls, so it carries the tab stop and
the name of the scrollable-region pattern the table wrapper uses; the name
repeats the file name, because the caption labels the figure and not the
region inside it. `__language` is optional, the caption is not — a code block
without a name is a bare `pre`:

```html
<figure class="theme-code">
    <figcaption class="theme-code__caption">
        <span class="theme-code__filename">Configuration/Services.php</span>
        <span class="theme-code__language">PHP</span>
    </figcaption>
    <pre class="theme-code__block" role="region" tabindex="0" aria-label="Configuration/Services.php"><code>…</code></pre>
</figure>
```

Description list. `--horizontal` sets terms and descriptions side by side from
`bp.$md` up; `--truncate`, with it, keeps every term on one line and cuts it
with an ellipsis. Truncation is opt-in because a cut term is text a sighted
reader cannot read. `dt`/`dd` are bare direct children, and the bare class
differs from the element baseline only in not indenting the description:

```html
<dl class="theme-dl theme-dl--horizontal">
    <dt>…</dt>
    <dd>…</dd>
</dl>
```

Divider — a separator with a label, and a section break. `<hr>` is void and
cannot hold a label, so it is a `div` with `role="separator"`; a separator's
children are presentational, so the name is an `aria-label` repeating the
label. `--start` moves the label to the inline start, `--break` is an
asterism between two passages of one section, without a label. The asterism is
text in the markup, `aria-hidden`, not generated content — the stylesheet draws
no glyph:

```html
<div class="theme-divider" role="separator" aria-label="Part two">
    <span class="theme-divider__label">Part two</span>
</div>
<div class="theme-divider theme-divider--break" role="separator">
    <span class="theme-divider__glyph" aria-hidden="true">⁂</span>
</div>
```

Figure. `--caption-end` aligns the caption to the end; `--float-start` and
`--float-end` float the figure beside the running text from `bp.$md` up,
capped at half the column — logical, so the start is the right side of a
right-to-left line. Any element with a floated figure as a direct child becomes
a block formatting context (`:where(:has(> …))`, no specificity), so an image
never hangs out of a box whose text is shorter than it; a list item gets
`flow-root list-item` instead, so it keeps its marker:

```html
<figure class="theme-figure theme-figure--float-start">
    <img class="theme-figure__media" src="…" alt="…" width="…" height="…">
    <figcaption class="theme-figure__caption">
        …
        <small class="theme-figure__credit">…</small>
    </figcaption>
</figure>
```

Gallery of the image content element. The wrapper always carries **two**
position modifiers, not one: `GalleryProcessor` produces a vertical position
(`above`, `intext`, `below`) and a horizontal one (`left`, `center`, `right`),
and `Partials/ContentElement/Gallery.html` emits both. The horizontal three
align the row; `above` and `below` are the order of the markup. `intext` is
translated by the partial into `--float-start` (in text, left) or `--float-end`
(in text, right), plus `--nowrap` for the two "no wrap" variants, which keeps
the text beside the gallery from flowing back under it. The whole gallery
floats, not one item of it. Every item is a `.theme-figure` with a
`.theme-figure__caption`; the gallery's own rules follow the figure's in the
cascade and win where they meet:

```html
<div class="theme-gallery theme-gallery--intext theme-gallery--left theme-gallery--float-start" data-theme-gallery-columns="2">
    <div class="theme-gallery__row">
        <figure class="theme-gallery__item theme-figure">
            <a class="theme-gallery__zoom" href="…"><img class="theme-gallery__image" …></a>
            <figcaption class="theme-gallery__caption theme-figure__caption">…</figcaption>
        </figure>
    </div>
</div>
```

Hero. Modifiers: default (text only), `--media` (adds `theme-hero__media`),
`--compact`:

```html
<section class="theme-hero theme-hero--media">
    <div class="theme-hero__media"><img …></div>
    <div class="theme-hero__body">
        <p class="theme-hero__eyebrow">…</p>
        <h1 class="theme-hero__title">…</h1>
        <p class="theme-hero__lead">…</p>
        <div class="theme-hero__actions"><a class="theme-button" …>…</a></div>
    </div>
</section>
```

List. Modifiers `--unstyled`, `--inline` (one row, a hairline between the
items), `--check` (a check mark per item), `--icon` (an icon per item, from
the `__icon` slot), `--columns-2`/`--columns-3` (CSS columns, at least 12rem
each, so a phone gets one) and `--divided` (a hairline between rows). Works on
`ul` and `ol`; the items are bare `li` selected as direct children, so a list
nested in an item keeps the element baseline. The check mark is the set's
`check`, referenced as a CSS `mask` and painted in the success accent — one
marker for every item without markup per item, the same shape in every font,
and `CanvasText` under forced colours, see [Icons](icons.md#the-rule).
`__icon` holds an icon rendered by `<theme:icon>` and sets `--theme-icon-size`
to one em, on the first line, `aria-hidden` because the item's text is its
content:

```html
<ul class="theme-list theme-list--check"><li>…</li></ul>
<ul class="theme-list theme-list--icon">
    <li><span class="theme-list__icon" aria-hidden="true"><theme:icon name="…" /></span>…</li>
</ul>
```

Quote:

```html
<figure class="theme-quote">
    <blockquote class="theme-quote__text"><p>…</p></blockquote>
    <figcaption class="theme-quote__attribution">
        <span class="theme-quote__author">…</span>
        <cite class="theme-quote__source">…</cite>
    </figcaption>
</figure>
```

Table. `tabindex="0"` plus `role="region"` plus `aria-label` on the wrapper is
the W3C APG scrollable-region-focusable pattern — a container that scrolls but
carries no tab stop is unreachable by keyboard:

```html
<div class="theme-table-wrapper" tabindex="0" role="region" aria-label="…">
    <table class="theme-table theme-table--striped">
        <caption class="theme-table__caption">…</caption>
        <thead>…</thead>
        <tbody>…</tbody>
        <tbody>…</tbody>
        <tfoot>…</tfoot>
    </table>
</div>
```

`<thead>`/`<tbody>`/`<tfoot>`/`<th>`/`<td>` are styled through plain element
selectors scoped under `.theme-table`, not a BEM class each; the one helper
class is `theme-table__cell--numeric` on a cell holding a number. A
`<th scope="row">` in the body is set at the medium weight, a second `<tbody>`
opens with the strong rule the header closes on, and a `<tfoot>` is a totals
row on the header's tint.

Modifiers: `--striped`, `--striped-columns`, `--hover`, `--bordered`,
`--borderless`, `--compact`, `--sticky-header` and `--caption-bottom`. Every
one re-points a token of the table's own layer — rule widths, cell padding,
fills — rather than restating a rule, so `--borderless` removes the header and
group rules as well without a selector for each. The default rows are no
longer striped; that is `--striped`, so that the core's "Striped" table class
changes something. `--hover` mixes the primary accent 12% into the background
and is the one state here, so it is drawn in `Highlight` under forced colours;
its contrast is in [`DESIGN.md`](../../DESIGN.md#table-rows).
`--sticky-header` switches to separated borders — a collapsed border belongs to
the table and stays behind when the cell sticks — draws every rule two cells
share only once, since separated borders no longer merge (a column rule from
the end edge only, no hairline above a group or totals rule), and gives the wrapper a
maximum height through `:has()`, so the wrapper stays the one scrolling,
focusable region. The table content element maps `table_class` onto these one
to one; see [Content elements](../architecture/content-elements.md#table_class-the-modifier-of-the-same-name).

Teaser. Modifier `--reversed` swaps media and body once the row layout kicks
in, stacking below `bp.$md` the same as `theme-hero--media`:

```html
<article class="theme-teaser theme-teaser--reversed">
    <div class="theme-teaser__media"><img …></div>
    <div class="theme-teaser__body">
        <h2 class="theme-teaser__title">…</h2>
        <p class="theme-teaser__text">…</p>
        <a class="theme-teaser__link" href="…">…</a>
    </div>
</article>
```

Panel. Header, aside and footer are optional; the body is the panel. A
`section` with an accessible name is a region landmark, so a panel that is not
a destination of its own takes a `div` instead. Not a card: a card is a teaser
for content that lives elsewhere — an optional image, a title, a summary and one
link, with `--linked` making the whole surface that link — while a panel is
content that lives here, grouped under a heading. It has no media slot, it is
never a link as a whole, and its footer holds controls rather than a "read
more":

```html
<section class="theme-panel" aria-labelledby="p-instance-title">
    <header class="theme-panel__header">
        <h3 class="theme-panel__title" id="p-instance-title">…</h3>
        <div class="theme-panel__aside">…</div>
    </header>
    <div class="theme-panel__body">…</div>
    <footer class="theme-panel__footer">…</footer>
</section>
```

Text roles — the three roles of the type table in
[`DESIGN.md`](../../DESIGN.md#roles) that no element carries by itself: a
heading level says where something sits in the outline, not how loud it is.
Each is a class on whatever element the outline asks for, and they live in a
component file rather than in `base/`, because a class — unlike a bare element
— has something to re-theme and so carries a token layer:

```html
<p class="theme-eyebrow">…</p>
<h1 class="theme-display">…</h1>
<h1 class="theme-display theme-display--1">…</h1>
<p class="theme-lead">…</p>
```

The display role has three sizes: `--1` grows from 34 to 68px, `--2` from 34
to 54px — the measured one, and the size of the bare class — and `--3` from 34
to 43px. All three start at the first-level heading size on a narrow viewport
and differ only in how far they grow; the two that were not measured are
derived from the one that was, see [`DESIGN.md`](../../DESIGN.md#display-sizes).
A modifier re-points `--theme-display-font-size` rather than restating
`font-size`, and `--2` exists although it changes nothing, so a template that
maps an editor's choice onto a class never special-cases the middle one.

The element baseline carries the rest of the typography, without a class,
because rich text is markup the theme cannot add one to: a `small` inside a
heading as a secondary line at 0.7 of the heading; `text-wrap: balance` on
headings and `pretty` on paragraphs; `dfn`; a key combination as a `kbd` of
`kbd`s, only the keys framed; quotation marks per `:lang()` — English by
default, German and French pairs; `hyphens: auto` for German only; and
`overflow-wrap: anywhere` on links in prose blocks, so a written-out URL stops
widening its container. Captions and cells align to `start`, not `left`, like
every other declaration of the file.

A title that a component selects by class, on whatever level the editor picked
— `.theme-hero__title` and `.theme-teaser__title` follow `header_layout` from
h1 to h5 — states `text-transform: none`. The element baseline sets `h5` and
`h6` in capitals, and a title class that does not mention the property would
pick that up on level five.
`Tests/Unit/ComponentLibraryTest::aTitleOnAnyHeadingLevelKeepsItsOwnCase`
holds both to it.

### Forms

The `forms/` partials style one contract between them — `_controls.scss` the
native controls, `_field.scss` the label/hint/required chrome and vertical
rhythm, `_choice-group.scss` and `_input-group.scss` two arrangements of
controls, `_validation.scss` the error/success repaint of all of it:

```html
<form class="theme-form">
    <div class="theme-form-summary theme-form-summary--error" role="alert" tabindex="-1">
        <p class="theme-form-summary__title">…</p>
        <ul class="theme-form-summary__list"><li><a href="#f-mail">…</a></li></ul>
    </div>

    <div class="theme-field theme-field--invalid">
        <label class="theme-field__label" for="f-mail">Email <span class="theme-field__required" aria-hidden="true">*</span></label>
        <input class="theme-input" id="f-mail" type="email" aria-invalid="true" aria-describedby="f-mail-error f-mail-hint">
        <p class="theme-field__error" id="f-mail-error"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg> …</p>
        <p class="theme-field__hint" id="f-mail-hint">…</p>
    </div>

    <fieldset class="theme-fieldset">
        <legend class="theme-fieldset__legend">…</legend>
        <label class="theme-check"><input type="checkbox"> …</label>
        <label class="theme-switch"><input type="checkbox" role="switch"> <span>…</span></label>
    </fieldset>
</form>
```

`.theme-switch` is the native checkbox drawn as a track and a thumb
(`appearance: none`, the thumb a radial gradient on the input, since
`::before` is not rendered on an input in every engine). The thumb's position
is the state — the start of the track when off, the end when on — and its
width is given explicitly as the track's inner height: a gradient has no size
of its own, so with `background-size: auto` it spans the whole track, left
and right are the same place, and the thumb sits in the middle in both
states. The visual suite measures where it is painted, see
[Visual tests](../testing/visual-tests.md#the-matrix). It is for a setting
that takes effect the moment it is flipped — the element outlines in the
[display settings](#display-settings) — and carries `role="switch"` in the
markup, so a screen reader announces "on"/"off" rather than "checked"; the
rule does not depend on the role. A choice that is only submitted with the
rest of a form stays a `.theme-check`. Under `forced-colors: active` the
browser would drop the thumb and paint the track `Canvas` in both states, so
the input opts out with `forced-color-adjust: none` and paints itself with
system colours — `CanvasText` on `Canvas` when off, `HighlightText` on
`Highlight` when on.

`.theme-input` is the one class for every native input type that resolves to
the same box (text, email, url, tel, number, password, search, and the
date/time family); `color`, `range` and `file` render a fundamentally
different UA widget and get an explicit `[type='…']` override in
`_controls.scss`. `.theme-field--invalid`/`--valid` are the explicit
counterparts of `:user-invalid` — deliberately not `:invalid`, which would
paint every empty required field red before the reader has typed anything.
An error or success message starts with its icon in the markup,
`circle-exclamation` or `circle-check`. It used to be a `⚠` or `✓` in the
`content` of a `::before`, which is drawn by whichever installed font covers the
character — possibly a colour emoji font that ignores `color` — and an icon
cannot be generated content.

**A control boundary uses `--theme-color-border-strong`; `--theme-color-border`
is decorative only.** The edge of an empty text field is the only thing that
identifies it, and WCAG 1.4.11 holds it to 3:1 — which the decorative border,
at about 1.4:1, does not reach. So `--theme-input-border-color`, the addon
border of `.theme-input-group` and the switch track default to the strong
border; hover moves the input's border to `--theme-color-text-secondary`, and
the validation states re-point the same tokens over both. Tabs, pagination,
the segmented control and the fieldset keep the decorative border: their
labels identify them, and their borders frame. The reasoning per control, and
the contrast of the token against every background, are in
[`DESIGN.md`](../../DESIGN.md#control-boundaries);
`ComponentLibraryTest::aControlDrawsItsBoundaryInTheStrongBorderColour` and
`StylesheetTest::theControlBorderReachesThreeToOneOnEveryBackground` hold both.

### Layout

Page frame — `.theme-skip-link` precedes `.theme-page` as a sibling, not a
descendant:

```html
<body>
    <a class="theme-skip-link" href="#content">…</a>
    <div class="theme-page">
        <header class="theme-site-header">…</header>
        <div class="theme-page__body">
            <aside class="theme-page__aside"><nav class="theme-nav-sub">…</nav></aside>
            <main class="theme-page__main" id="content">…</main>
        </div>
        <footer class="theme-site-footer">…</footer>
    </div>
</body>
```

The two-column split is `:has(.theme-page__aside)` on `.theme-page__body`, not
a page-level modifier class — whether the grid applies follows the markup
rather than a class kept in sync with it.

Site header. `--sticky` is opt-in, because a sticky header steals viewport
height on every scroll position:

```html
<header class="theme-site-header">
    <div class="theme-site-header__inner">
        <a class="theme-site-header__brand" href="{siteRootUrl}">…</a>
        <nav class="theme-nav-main">…</nav>
        <div class="theme-site-header__actions">…</div>
    </div>
</header>
```

`__actions` holds the [display settings](#display-settings). Brand,
navigation and actions share one row at every width: above `bp.$md` the
navigation does not shrink, so the brand wraps first; below it the expanded
navigation drops down under the header as a full-width band, behind
`data-js` like the collapse itself.

### Display settings

The cog at the end of the site header and the panel it discloses —
appearance, palette, the content-element outline and a reset. See
[Appearance switching](appearance-switching.md) for the behaviour and the
server defaults the `data-theme-default-*` attributes carry:

```html
<div class="theme-settings" data-theme-default-appearance="auto" data-theme-default-palette="neutral" data-theme-default-content-outline="on">
    <button class="theme-settings__trigger" type="button" aria-expanded="false" aria-controls="theme-settings-panel">
        <svg class="theme-icon theme-settings__icon" aria-hidden="true" focusable="false" …>…</svg>
        <span class="theme-settings__label">…</span>
    </button>
    <div class="theme-settings__panel" id="theme-settings-panel" hidden>
        <fieldset class="theme-settings__group">
            <legend class="theme-settings__legend">…</legend>
            <div class="theme-segmented">
                <label class="theme-segmented__option">
                    <input type="radio" name="theme-settings-appearance" value="auto" data-theme-setting="appearance" checked>
                    <span class="theme-segmented__label"><svg class="theme-icon theme-segmented__icon" aria-hidden="true" focusable="false" …>…</svg>…</span>
                </label>
            </div>
        </fieldset>
        <fieldset class="theme-settings__group">
            <legend class="theme-settings__legend">…</legend>
            <div class="theme-swatch-list">
                <label class="theme-swatch-option">
                    <input type="radio" name="theme-settings-palette" value="neutral" data-theme-setting="palette" checked>
                    <span class="theme-swatch theme-swatch--neutral" aria-hidden="true"></span>… <svg class="theme-icon theme-swatch-option__check" aria-hidden="true" focusable="false" …>…</svg>
                </label>
            </div>
        </fieldset>
        <div class="theme-settings__footer">
            <label class="theme-switch"><input type="checkbox" role="switch" name="theme-settings-content-outline" value="on" data-theme-setting="content-outline" checked> <span>…</span></label>
            <button class="theme-button theme-button--link theme-button--small" type="button" data-theme-settings-reset>…</button>
        </div>
    </div>
</div>
```

The whole control is hidden until `data-js`, like the navigation toggle. It is
a disclosure holding native radios and a switch, not an ARIA menu: the radios
stay in the document, stretched over their labels at zero opacity, and the
labels draw their state off the input with `:has()`. The panel is flat —
`--theme-color-surface-raised` inside a `--theme-color-border-strong` border,
stacked with `--theme-z-overlay` — and never wider than the viewport less a
gutter on either side. Under `forced-colors: active` the segment labels and
the swatches opt out of forced colours, so the checked segment stays
distinguishable (`Highlight`) and the swatches keep their colours — see
[Appearance switching § Forced colours](appearance-switching.md#forced-colours).

The id and the radio names above are the defaults. The partial derives both
from its optional `idPrefix` argument (`theme-settings` when not given), so a
page can carry a second instance with a prefix of its own; the script binds
every `.theme-settings` and keeps them all in step.

Site footer:

```html
<footer class="theme-site-footer">
    <div class="theme-site-footer__inner">
        <div class="theme-site-footer__columns">
            <div class="theme-site-footer__column">…</div>
        </div>
        <div class="theme-site-footer__meta">…</div>
    </div>
</footer>
```

## The two switches

### The content-element outline

`components/_content-element.scss` draws a dashed outline and a `CType` chip
around every rendered content element — the affordance that made Frame the
chosen structural variant (see [`DESIGN.md`](../../DESIGN.md#what-this-file-does-not-decide)).
It is a development/staging aid, meant to be switched off wholesale for a
production site package.

Globally, on the root element:

```html
<html data-theme-content-outline="off">
```

```scss
[data-theme-content-outline='off'] .theme-content-element {
    --theme-content-element-outline-style: none;

    &::before {
        content: none;
    }
}
```

Per element, for the page that wants the frame everywhere except one embedded
element (a hero, a plugin with its own visible chrome):

```html
<div class="theme-content-element theme-content-element--plain">…</div>
```

**This is an attribute selector, not a custom property**, and that is a
deliberate trade rather than the more obvious choice. The label is generated
content (`content: attr(data-ctype)` on a `::before`), and hiding it has to
depend on the *value* of a property, not on the DOM shape — `:has()` cannot
see a property value at all. A CSS container **style** query can: a
pseudo-element genuinely can query its own originating element, the documented
exception to "elements cannot query themselves". But Firefox only shipped
style queries in version 151, on 19 May 2026. On anything older the query is
dropped as unparseable — the outline disappears and the label does not,
leaving a stray CType chip floating on a production page. That is a visible
defect, not a graceful degradation, and it is not worth buying with a
three-month-old feature when an attribute selector does the same job in every
browser this theme runs on.

The custom property (`--theme-content-element-outline-style`) still exists,
still works, and is what `--plain` re-points for a single element — it simply
is not the documented global switch, because on its own it cannot take the
label with it.

### Content element appearance

The appearance fields of a record become modifiers of the wrapper and of its
header. `Layouts/ContentElement.html` and `Partials/ContentElement/Header.html`
write them, and [Content elements](../architecture/content-elements.md#appearance-fields)
has the mapping from field to class:

```html
<div class="theme-content-element theme-content-element--text theme-content-element--frame-inverse theme-content-element--space-after-large" id="c123" data-ctype="text">
    <div class="theme-content-element__inner">
        <header class="theme-content-element__header theme-content-element__header--center">
            <h2 class="theme-content-element__heading theme-content-element__heading--h4">…</h2>
        </header>
        …
    </div>
</div>
```

**Bands** are the Frame language applied to one element: a fill, the hairline
and the 5px radius. A band belongs to the element, not to the column around it,
so no element has to know which band it sits in. That was the architectural
cost DESIGN.md held against the full-bleed "Band" variant, and it does not
arise here. The development outline sits on top of the hairline. The global
switch and `--plain` still remove outline and chip, and the band stays,
because it is content.

| Modifier          | Fill                                                 |
|-------------------|------------------------------------------------------|
| `--frame-surface` | `--theme-color-surface`                              |
| `--frame-raised`  | `--theme-color-surface-raised`                       |
| `--frame-accent`  | the primary accent at 5% in the background, in Oklab |
| `--frame-inverse` | the background of the other appearance               |
| `--frame-none`    | none; the inner padding is `0`, the outline stays    |

**The inverse band re-points no token.** Every colour token is a
`light-dark()` held by an unregistered custom property. It inherits as written
and resolves against the `color-scheme` of the element that reads it. The band
sets the opposite scheme, so its whole subtree takes the other appearance:
text, surfaces, the accents of the palette, the semantic colours and the
controls the browser draws. Any component in it is then exactly as readable as
on a page in that appearance, and nothing has to be declared twice. CSS cannot
read back which scheme the page resolved to, so the three ways it can get one
are answered separately: `data-theme="dark"`, the operating system when no
`data-theme` is set, and dark as the inverse of the default light.

**The accent band is a tint** for the reason recorded in
[`DESIGN.md`](../../DESIGN.md#content-element-bands): a solid fill would need
every component inside it re-pointed to a second palette.

**Spacing** re-points `--theme-content-element-spacing`, the token the element
reads for the gap before itself, as `--spaced-loose` does. It also states the
top margin, so the first element of a column gets the space an editor asked
for. Space after is a bottom margin. Neighbouring margins collapse, so the
space after one element and before the next is the larger of the two, not the
sum.

**Header looks** restate the metrics of `base/_elements.scss` per level,
including `text-transform` and the tracking. An `h5` in the look of heading 1
therefore loses its capitals. The display look is the text role
`.theme-display`.

### The `data-js` marker

`components/_nav-main.scss` collapses the main navigation behind a toggle
below `bp.$md`, but only once a script has announced itself:

```html
<html data-js>
```

The default layout — with no marker present — is the *open* one: the list is
always laid out, stacked below the breakpoint and in a row above it, needing
no script at all. A plain `<button>` carries no native disclosure behaviour
the way `<details>` does, so the toggle only does anything once a script flips
`aria-expanded`; making the collapsed state the default would hide the entire
menu on a narrow viewport whenever that script has not run yet — a broken
page, not a degraded one. The collapse rule is therefore written as a
negation ("not disclosed" hides) rather than a positive ("disclosed shows"),
so the undecorated state is the usable one:

```scss
@media (max-width: bp.$md-max) {
    [data-js] .theme-nav-main:not(:has(.theme-nav-main__toggle[aria-expanded='true'])) > .theme-nav-main__list {
        display: none;
    }
}
```

The open state read off `[aria-expanded='true']` via `:has()`, not a
JS-authored class, so the attribute a screen reader announces and the
attribute CSS renders from cannot drift apart.

`Tests/Unit/ComponentLibraryTest::collapsingTheMainNavigationRequiresTheScriptMarker`
asserts the compiled rule stays gated behind `[data-js]` — inverting it is a
one-character change with no visible symptom on a desktop check.

## Components that need the script

Three components are the only ones in the library that depend on
`Resources/Public/JavaScript/theme.js`, and each is written so that the page is
still usable without it — with JavaScript switched off, and with JavaScript on
but `theme.js` failing to load, which are two different pages. The dialog
opener follows the [`data-js` marker](#the-data-js-marker) like the navigation
toggle does; the tabs follow a marker of their own that only `theme.js` sets,
because what they hide has to stay reachable until the script that switches
them has actually run.

| Component | With the script                                                                                                                          | Without it                                                                                         | Gate in the stylesheet                                  |
|-----------|------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------|---------------------------------------------------------|
| Tabs      | WAI-ARIA tabs with automatic activation: arrow keys (mirrored right-to-left), Home, End, a roving tab stop; the panels become tab panels | No tab list; every panel shown, stacked, in its own frame under its heading, with no tab semantics | `.theme-tabs[data-theme-tabs-bound]`, set by `theme.js` |
| Dialog    | `data-theme-dialog-open` calls `showModal()`; a click on the backdrop closes it; focus returns to the opener                             | The opener is hidden and the dialog stays closed                                                   | `:root:not([data-js]) [data-theme-dialog-open]`         |
| Tooltip   | Escape sets `data-theme-tooltip-dismissed` until pointer and focus have both left                                                        | Hover and focus still show it; Escape does not hide it                                             | none — the CSS behaviour is the fallback                |

**Tabs.** The markup keeps three rules that the stylesheet and the script both
depend on: the first tab is the selected one and every other tab carries
`tabindex="-1"`; a panel carries nothing but its class and its id — no
`hidden`, because a panel hidden in the markup is a panel nobody can read
without the script, and no `role`, `aria-labelledby` or `tabindex`, which the
script adds when it binds the group, so a page without it has no tab panels
labelled by tabs nobody can see and no extra tab stop per panel; and every
panel repeats its tab's label as a `.theme-tabs__heading`, which labels it
while there are no tabs:

```html
<div class="theme-tabs">
    <div class="theme-tabs__list" role="tablist" aria-label="…">
        <button class="theme-tabs__tab" type="button" role="tab" id="t-set" aria-selected="true" aria-controls="t-set-panel">…</button>
        <button class="theme-tabs__tab" type="button" role="tab" id="t-static" aria-selected="false" aria-controls="t-static-panel" tabindex="-1">…</button>
    </div>
    <div class="theme-tabs__panel" id="t-set-panel">
        <h3 class="theme-tabs__heading">…</h3>
        …
    </div>
    <div class="theme-tabs__panel" id="t-static-panel">
        <h3 class="theme-tabs__heading">…</h3>
        …
    </div>
</div>
```

The gate is the group's own `data-theme-tabs-bound`, which `theme.js` sets once
it has bound that group, and not `data-js`, which only says that the inline
head script ran. Gated on `data-js`, a page whose `theme.js` failed to load
would show tab buttons that do nothing and hide every panel but the first —
content out of reach, where the undecorated state has to be the usable one.
The price is one reflow: the footer module binds the group after first paint,
and the panels that are not selected disappear then. The script hides a panel
with the `hidden` attribute, which is why `.theme-tabs__panel` has no `display`
of its own — any author `display` on the class would beat the user agent's
`[hidden] { display: none }`.

Folder tabs, per the Frame variant: the selected tab takes the panel's surface
and border and opens into it. The rule under the list is an inset `box-shadow`,
not elevation — it is the one way to draw a line the selected tab's own
background can cover.

**Dialog.** A native `<dialog>` opened with `showModal()`: focus kept inside,
the inert page, Escape and the `::backdrop` are the browser's, and a
`<form method="dialog">` closes it with the pressed button's `value` as its
`returnValue`. Only opening needs the script. Invoker commands would do that
declaratively, but they arrived long after the browser floor
[`DESIGN.md`](../../DESIGN.md#one-declaration-both-appearances) writes the
stylesheet against:

```html
<button class="theme-button" type="button" aria-haspopup="dialog" data-theme-dialog-open="d-reseed">…</button>

<dialog class="theme-dialog" id="d-reseed" aria-labelledby="d-reseed-title">
    <form method="dialog">
        <div class="theme-dialog__header">
            <h2 class="theme-dialog__title" id="d-reseed-title">…</h2>
            <button class="theme-close" value="cancel" aria-label="Close"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></button>
        </div>
        <div class="theme-dialog__body">…</div>
        <div class="theme-dialog__footer">
            <button class="theme-button theme-button--ghost" value="cancel" autofocus>…</button>
            <button class="theme-button theme-button--danger" value="confirm">…</button>
        </div>
    </form>
</dialog>
```

It is flat, on `--theme-color-border-strong` over the scrim, with no elevation
token: on top of a scrim the border is the only edge the box has. The opener's
rule is a negation, because an opener is any element and a rule showing it
again would have to know its `display`. Content a page cannot do without does
not belong in a dialog.

`autofocus` sits on the least destructive action: without it `showModal()`
focuses the first focusable element, which is the close button, and the
WAI-ARIA dialog pattern starts a confirmation on the action that does no harm
when Enter is pressed by reflex. A click closes the dialog only when the press
started on the backdrop as well: a click goes to the nearest element the press
and the release share, so a press on the text dragged out over the scrim
arrives as a click on the dialog outside its box — a text selection, not a
dismissal.

**Tooltip.** Shown on `:hover` and `:focus-within` with CSS alone; the trigger
names the bubble in `aria-describedby`, so a screen reader announces the text
whether it is on screen or not. Of WCAG 1.4.13, *hoverable* is CSS — the bubble
takes the pointer while visible, and a transparent strip bridges the gap to the
trigger — and *dismissible* is the script. The bubble sits above the trigger,
anchored at its inline start edge and opening towards the inline end: centred
on a 44px trigger, a 15rem bubble stuck out past the edge of a 400px viewport
(WCAG 1.4.10), and opening one way it stays on screen for any trigger in the
first two thirds of a phone's line. There is no collision handling beyond
that — keeping the bubble inside the viewport whatever the trigger is what CSS
anchor positioning is for, and that is outside the browser floor — so a trigger
at the inline end of a narrow viewport is a placement to avoid:

```html
<span class="theme-tooltip">
    <button class="theme-button theme-button--ghost theme-button--icon" type="button" aria-label="…" aria-describedby="tt-outline">…</button>
    <span class="theme-tooltip__bubble" role="tooltip" id="tt-outline">…</span>
</span>
```

Escape acts on a tooltip only while the pointer or focus is inside it, and it
never moves focus. Nothing is needed to close it when focus leaves: the bubble
is shown by `:focus-within`, so it goes with the focus, and it never sits over
whatever takes the focus next (WCAG 2.4.11).

`Tests/Unit/ComponentLibraryTest` holds the two gates on the compiled file;
`Tests/Acceptance/styleguide.spec.ts` drives all three components in a browser
on `/styleguide`, and loads the page once with JavaScript switched off and once
with `theme.js` blocked — see [Acceptance tests](../testing/acceptance-tests.md).

## Forced colours

A convention for every component: **a state carried by colour alone gets a
rule under `@media (forced-colors: active)`**, in the component's own file.
Forced colours — Windows contrast themes, and Chromium's emulation of them —
repaint every background to the canvas colour and every text and border to
the system text colour, drop every `box-shadow` and every gradient, and paint
a transparent border side solid. A state that is only a fill therefore looks
exactly like its neighbours, and a shape made of one coloured border side
turns into a block.

The rule uses system colours, never palette tokens: `Highlight` with
`HighlightText` for a selected or pressed state — with
`forced-color-adjust: none` on that element, so the pair is painted as written
instead of being repainted in turn — and `CanvasText`, `ButtonText` and
`ButtonBorder` for edges. Measured in Chromium with forced colours emulated:

| Component   | Lost under forced colours                                 | Rule                                         |
|-------------|-----------------------------------------------------------|----------------------------------------------|
| Tabs        | the selected tab's fill; the inset rule under the list    | highlight pair; a real bottom border         |
| Button      | the fill of `[aria-pressed='true']`; the spinner's gap    | highlight pair; the spinner's colours stated |
| Tooltip     | the inverted bubble's edge; the arrow turns into a square | a `CanvasText` border; the arrow is dropped  |
| Alert       | the tint, so only the leading rule is left                | an edge all round, the leading side strong   |
| Dialog      | —                                                         | the strong border width                      |
| Input group | nothing — the lifted edge is position, not colour         | none                                         |

The `::backdrop` needs no rule: forced colours keep the alpha of its
background, so the scrim stays a translucent canvas over the page. The alert
kinds are told apart by their glyph once the accents are gone, which is what
the alert contract already relies on.
`Tests/Acceptance/styleguide.spec.ts` loads the styleguide with
`forcedColors: 'active'` and asserts the selected tab and the pressed toggle
against the browser's own `Highlight`, the spinner's gap, and the tooltip,
alert and dialog edges.

## Breakpoints

There is exactly one: `bp.$md`, `48rem` (768px), declared in
`abstracts/_breakpoints.scss`. It is the point at which the main navigation's
two levels stop fitting a single row — every other component that stacks
(`theme-hero--media`, `theme-teaser`, `theme-page__body`) was checked against
it and none wanted a different one.

It is a **Sass variable, not a custom property**, and that is forced rather
than preferred: a media query condition is evaluated before the cascade runs,
so `@media (min-width: var(--theme-breakpoint-md))` has no value to read at
the point the condition is tested, in any browser. This is the one place the
"re-theme without rebuilding" property documented in
[Frontend assets](frontend-assets.md#no-framework-and-no-import) does not
hold: an integrator can move every colour, size and spacing from their own
CSS, but **moving a breakpoint means recompiling the SCSS**.

## What the tests guard

`Tests/Unit/ComponentLibraryTest` covers the structural promises this page
documents — `Tests/Unit/StylesheetTest` covers the appearance contract
(colour, light/dark) separately:

| Test                                                 | Guards                                                                                                                                                                                                                             |
|------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `everyComponentIsPartOfTheBundle`                    | Every selector in the [component reference](#component-reference) is actually compiled into `theme.css` — dropping a `@use` from `theme.scss` is otherwise invisible until someone looks at a page.                                |
| `collapsingTheMainNavigationRequiresTheScriptMarker` | The `[data-js]` gate on the navigation collapse still holds — see [the `data-js` marker](#the-data-js-marker).                                                                                                                     |
| `theContentElementOutlineSwitchesOffCompletely`      | `[data-theme-content-outline='off']` still removes the label together with the outline — see [the content-element outline](#the-content-element-outline).                                                                          |
| `everyPaletteHasASwatchWithItsOwnColours`            | Every palette has a `.theme-swatch--*` modifier, and its two literals equal the palette's primary and secondary pair — see [Appearance switching](appearance-switching.md#palette-swatches-carry-literal-colours).                 |
| `tabsShowEveryPanelUntilTheScriptHasBoundThem`       | The tab list is hidden and the panel headings shown until the group carries `data-theme-tabs-bound`, and nothing about the tabs is gated on `[data-js]` — see [Components that need the script](#components-that-need-the-script). |
| `aDialogOpenerIsHiddenWithoutTheScriptMarker`        | `:root:not([data-js]) [data-theme-dialog-open]` still hides every opener nothing could operate.                                                                                                                                    |
| `textIsAlignedToTheStartOrTheEndOfTheLine`           | No `text-align: left` or `right` is compiled — a physical alignment puts the text of a right-to-left page against the wrong edge, and the element baseline carried two until they were found.                                      |
| `aTitleOnAnyHeadingLevelKeepsItsOwnCase`             | `.theme-hero__title` and `.theme-teaser__title` state `text-transform: none`, so a title rendered as `h5` does not turn into capitals.                                                                                             |
| `noComponentReferencesAnUndeclaredToken`             | Every `var(--theme-…)` referenced anywhere under `Resources/Private/Scss/` is declared somewhere in the same tree — walked on the sources, not the compiled file, so the offending name is still readable.                         |
| `aListItemHoldingAFloatKeepsItsMarker`               | A list item holding a floated figure or gallery is `flow-root list-item`, not `flow-root`, which would drop its marker.                                                                                                            |
| `aControlDrawsItsBoundaryInTheStrongBorderColour`    | The text input, the input group addon and the switch track default to `--theme-color-border-strong`, with its light value as the fallback literal — see [Forms](#forms).                                                           |
| `aHoveredTextInputChangesItsBorder`                  | The hover border of `.theme-input` differs from its resting one, now that the resting one is the strong border.                                                                                                                    |
| `everyTableClassAnEditorCanPickIsStyled`             | Every `table_class` an editor can pick — the core's `striped` and `bordered` and the `addItems` of `Configuration/PageTsConfig/TCEFORM/TableClass.tsconfig` — has a compiled `.theme-table--<value>` rule.                         |

The last one strips comments before scanning, which matters here specifically:
the comment documenting why a breakpoint cannot be a custom property spells
out `var(--theme-breakpoint-md)` in prose, and scanning it as code would flag
the one token this codebase deliberately does not declare.

## Extending it

A site package adding a component writes its own partial with its own token
layer — the pattern every file in `components/` already follows, see
[Frontend assets § Component tokens](frontend-assets.md#component-tokens) —
and its own entry point rather than editing this theme's `theme.scss`:

```scss
@use 'path/to/theme/Resources/Private/Scss/abstracts/tokens';
@use 'my-component';
```

Overriding an existing component does not require touching its SCSS at all in
the common case: every component reads its values through `var()` with a
literal fallback, so a site package's own CSS can re-point a single component
token (`--theme-card-background`) or a global one
(`--theme-color-surface`) without rebuilding this theme. That also makes a
component portable into a shadow root, since custom properties inherit
through the boundary even though inherited CSS does not — see
[Frontend assets § Building a subset](frontend-assets.md#components-are-self-contained).

Reaching for the SCSS itself is only needed to change a rule's *shape* rather
than its values — a modifier this library does not ship, or the one
compile-time exception, [the breakpoint](#breakpoints).

## See also

- [Frontend assets](frontend-assets.md)
- [`DESIGN.md`](../../DESIGN.md)
- [Quality gates](quality-gates.md)
- [Unit tests](../testing/unit-tests.md)
