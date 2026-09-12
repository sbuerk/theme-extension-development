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

| Component               | Root class                | File                               |
|-------------------------|---------------------------|------------------------------------|
| Accordion               | `.theme-accordion`        | `components/_accordion.scss`       |
| Alert                   | `.theme-alert`            | `components/_alert.scss`           |
| Author                  | `.theme-author`           | `components/_author.scss`          |
| Badge                   | `.theme-badge`            | `components/_badge.scss`           |
| Breadcrumb              | `.theme-breadcrumb`       | `components/_breadcrumb.scss`      |
| Button                  | `.theme-button`           | `components/_button.scss`          |
| Card                    | `.theme-card`             | `components/_card.scss`            |
| Close button            | `.theme-close`            | `components/_close.scss`           |
| Content element wrapper | `.theme-content-element`  | `components/_content-element.scss` |
| Content menu            | `.theme-content-menu`     | `components/_content-menu.scss`    |
| Display settings        | `.theme-settings`         | `components/_settings.scss`        |
| Dialog                  | `.theme-dialog`           | `components/_dialog.scss`          |
| Gallery                 | `.theme-gallery`          | `components/_gallery.scss`         |
| Hero                    | `.theme-hero`             | `components/_hero.scss`            |
| Main navigation         | `.theme-nav-main`         | `components/_nav-main.scss`        |
| Sub navigation          | `.theme-nav-sub`          | `components/_nav-sub.scss`         |
| Pagination              | `.theme-pagination__list` | `components/_pagination.scss`      |
| Panel                   | `.theme-panel`            | `components/_panel.scss`           |
| Quote                   | `.theme-quote`            | `components/_quote.scss`           |
| Segmented control       | `.theme-segmented`        | `components/_settings.scss`        |
| Palette swatch          | `.theme-swatch`           | `components/_settings.scss`        |
| Skip link               | `.theme-skip-link`        | `components/_skip-link.scss`       |
| Table                   | `.theme-table-wrapper`    | `components/_table.scss`           |
| Tabs                    | `.theme-tabs`             | `components/_tabs.scss`            |
| Teaser                  | `.theme-teaser`           | `components/_teaser.scss`          |
| Text: display           | `.theme-display`          | `components/_text.scss`            |
| Text: eyebrow           | `.theme-eyebrow`          | `components/_text.scss`            |
| Text: lead              | `.theme-lead`             | `components/_text.scss`            |
| Tooltip                 | `.theme-tooltip`          | `components/_tooltip.scss`         |
| Form controls           | `.theme-input`            | `forms/_controls.scss`             |
| Form switch             | `.theme-switch`           | `forms/_controls.scss`             |
| Form field wrapper      | `.theme-field`            | `forms/_field.scss`                |
| Form choice group       | `.theme-choice-group`     | `forms/_choice-group.scss`         |
| Form input group        | `.theme-input-group`      | `forms/_input-group.scss`          |
| Form validation         | `.theme-field--invalid`   | `forms/_validation.scss`           |
| Page frame              | `.theme-page`             | `layout/_page.scss`                |
| Site header             | `.theme-site-header`      | `layout/_site-header.scss`         |
| Site footer             | `.theme-site-footer`      | `layout/_site-footer.scss`         |
| Styleguide page         | `.theme-styleguide`       | `layout/_styleguide.scss`          |

`.theme-pagination` has no rule of its own — only `__list`, `__link` and
`__ellipsis` are styled, current-page state comes from `[aria-current="page"]`
rather than a modifier class. `theme.scss` is the authoritative list and the
cascade order; `Tests/Unit/ComponentLibraryTest::everyComponentIsPartOfTheBundle`
asserts every one of the thirty-nine selectors above is actually compiled into
`Resources/Public/Css/theme.css`. The palette swatch is covered twice over,
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

### Navigation

Main navigation, two levels deep from `MenuProcessor`. `--active` marks the
ancestor of the current page, `[aria-current="page"]` marks the current page
itself — independently, because a top-level item can carry one without the
other:

```html
<nav class="theme-nav-main" aria-label="Main">
    <button class="theme-nav-main__toggle" aria-expanded="false" aria-controls="nav-main">…</button>
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
`<details>` in the group is what makes them mutually exclusive:

```html
<div class="theme-accordion">
    <details class="theme-accordion__item" name="faq">
        <summary class="theme-accordion__summary">…</summary>
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
    <span class="theme-alert__icon" aria-hidden="true">…</span>
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
<button class="theme-button theme-button--icon" type="button" aria-label="…"><svg aria-hidden="true" focusable="false" …>…</svg></button>
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
44px target around it, with the content edge. The cross is two borders, which
forced colours mode keeps where it would drop a background. Its name is
`aria-label`, and inside a `<form method="dialog">` it carries
`value="cancel"` instead of `type="button"`:

```html
<button class="theme-close" type="button" aria-label="Close"></button>
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

Gallery of the image content element. The wrapper always carries **two**
position modifiers, not one: `GalleryProcessor` produces a vertical position
(`above`, `intext`, `below`) and a horizontal one (`left`, `center`, `right`),
and `Partials/ContentElement/Gallery.html` emits both. Only the horizontal
three carry rules today — the vertical three are structural, and the `@todo` in
`components/_gallery.scss` says so:

```html
<div class="theme-gallery theme-gallery--below theme-gallery--center" data-theme-gallery-columns="2">
    <div class="theme-gallery__row">
        <figure class="theme-gallery__item">
            <a class="theme-gallery__zoom" href="…"><img class="theme-gallery__image" …></a>
            <figcaption class="theme-gallery__caption">…</figcaption>
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
    <table class="theme-table">
        <caption class="theme-table__caption">…</caption>
        <thead>…</thead><tbody>…</tbody>
    </table>
</div>
```

`<thead>`/`<tbody>`/`<th>`/`<td>` are styled through plain element selectors
scoped under `.theme-table`, not a BEM class each; the one helper class is
`theme-table__cell--numeric` on a cell holding a number.

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
<p class="theme-lead">…</p>
```

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
        <p class="theme-field__error" id="f-mail-error">…</p>
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
`::before` is not rendered on an input in every engine). It is for a setting
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
        <svg class="theme-settings__icon" aria-hidden="true">…</svg>
        <span class="theme-settings__label">…</span>
    </button>
    <div class="theme-settings__panel" id="theme-settings-panel" hidden>
        <fieldset class="theme-settings__group">
            <legend class="theme-settings__legend">…</legend>
            <div class="theme-segmented">
                <label class="theme-segmented__option">
                    <input type="radio" name="theme-settings-appearance" value="auto" data-theme-setting="appearance" checked>
                    <span class="theme-segmented__label"><svg class="theme-segmented__icon" aria-hidden="true">…</svg>…</span>
                </label>
            </div>
        </fieldset>
        <fieldset class="theme-settings__group">
            <legend class="theme-settings__legend">…</legend>
            <div class="theme-swatch-list">
                <label class="theme-swatch-option">
                    <input type="radio" name="theme-settings-palette" value="neutral" data-theme-setting="palette" checked>
                    <span class="theme-swatch theme-swatch--neutral" aria-hidden="true"></span>…
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
            <button class="theme-close" value="cancel" aria-label="Close"></button>
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
| `aTitleOnAnyHeadingLevelKeepsItsOwnCase`             | `.theme-hero__title` and `.theme-teaser__title` state `text-transform: none`, so a title rendered as `h5` does not turn into capitals.                                                                                             |
| `noComponentReferencesAnUndeclaredToken`             | Every `var(--theme-…)` referenced anywhere under `Resources/Private/Scss/` is declared somewhere in the same tree — walked on the sources, not the compiled file, so the offending name is still readable.                         |

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
