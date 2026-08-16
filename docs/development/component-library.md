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

| Component               | Root class                   | File                                   |
|-------------------------|------------------------------|----------------------------------------|
| Accordion               | `.theme-accordion`           | `components/_accordion.scss`           |
| Alert                   | `.theme-alert`               | `components/_alert.scss`               |
| Appearance switcher     | `.theme-appearance-switcher` | `components/_appearance-switcher.scss` |
| Author                  | `.theme-author`              | `components/_author.scss`              |
| Badge                   | `.theme-badge`               | `components/_badge.scss`               |
| Breadcrumb              | `.theme-breadcrumb`          | `components/_breadcrumb.scss`          |
| Button                  | `.theme-button`              | `components/_button.scss`              |
| Card                    | `.theme-card`                | `components/_card.scss`                |
| Content element wrapper | `.theme-content-element`     | `components/_content-element.scss`     |
| Content menu            | `.theme-content-menu`        | `components/_content-menu.scss`        |
| Gallery                 | `.theme-gallery`             | `components/_gallery.scss`             |
| Hero                    | `.theme-hero`                | `components/_hero.scss`                |
| Main navigation         | `.theme-nav-main`            | `components/_nav-main.scss`            |
| Sub navigation          | `.theme-nav-sub`             | `components/_nav-sub.scss`             |
| Pagination              | `.theme-pagination__list`    | `components/_pagination.scss`          |
| Quote                   | `.theme-quote`               | `components/_quote.scss`               |
| Skip link               | `.theme-skip-link`           | `components/_skip-link.scss`           |
| Table                   | `.theme-table-wrapper`       | `components/_table.scss`               |
| Teaser                  | `.theme-teaser`              | `components/_teaser.scss`              |
| Form controls           | `.theme-input`               | `forms/_controls.scss`                 |
| Form field wrapper      | `.theme-field`               | `forms/_field.scss`                    |
| Form validation         | `.theme-field--invalid`      | `forms/_validation.scss`               |
| Page frame              | `.theme-page`                | `layout/_page.scss`                    |
| Site header             | `.theme-site-header`         | `layout/_site-header.scss`             |
| Site footer             | `.theme-site-footer`         | `layout/_site-footer.scss`             |

`.theme-pagination` has no rule of its own — only `__list`, `__link` and
`__ellipsis` are styled, current-page state comes from `[aria-current="page"]`
rather than a modifier class. `theme.scss` is the authoritative list and the
cascade order; `Tests/Unit/ComponentLibraryTest::everyComponentIsPartOfTheBundle`
asserts every one of the twenty-five selectors above is actually compiled into
`Resources/Public/Css/theme.css`. The appearance switcher is covered twice over,
because its swatches duplicate colour that lives in `abstracts/_palettes.scss` —
see [Appearance switching](appearance-switching.md#what-the-tests-guard).

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

Alert. Modifiers `--info` (default), `--success`, `--warning`, `--danger`.
`role` is fixed by the contract and is a markup concern this file does not
touch:

```html
<div class="theme-alert theme-alert--warning" role="status">
    <span class="theme-alert__icon" aria-hidden="true">…</span>
    <div class="theme-alert__body">
        <p class="theme-alert__title">…</p>
        <p class="theme-alert__text">…</p>
    </div>
</div>
```

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

Card, and a grid of them:

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

Gallery of the image content element — unprefixed, see
[the component reference](#component-reference):

```html
<div class="theme-gallery theme-gallery--center" data-theme-gallery-columns="2">
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

### Forms

All three `forms/` partials style one contract between them —
`_controls.scss` the native controls, `_field.scss` the label/hint/required
chrome and vertical rhythm, `_validation.scss` the error/success repaint of
both:

```html
<form class="theme-form">
    <div class="theme-form-summary theme-form-summary--error" role="alert" tabindex="-1">
        <p class="theme-form-summary__title">…</p>
        <ul class="theme-form-summary__list"><li><a href="#f-mail">…</a></li></ul>
    </div>

    <div class="theme-field theme-field--invalid">
        <label class="theme-field__label" for="f-mail">Email <span class="theme-field__required">*</span></label>
        <input class="theme-input" id="f-mail" type="email" aria-invalid="true" aria-describedby="f-mail-error f-mail-hint">
        <p class="theme-field__error" id="f-mail-error">…</p>
        <p class="theme-field__hint" id="f-mail-hint">…</p>
    </div>

    <fieldset class="theme-fieldset">
        <legend class="theme-fieldset__legend">…</legend>
        <label class="theme-check"><input type="checkbox"> …</label>
    </fieldset>
</form>
```

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
        <a class="theme-site-header__brand" href="/">…</a>
        <nav class="theme-nav-main">…</nav>
        <div class="theme-site-header__actions">…</div>
    </div>
</header>
```

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

| Test                                                 | Guards                                                                                                                                                                                                     |
|------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `everyComponentIsPartOfTheBundle`                    | Every selector in the [component reference](#component-reference) is actually compiled into `theme.css` — dropping a `@use` from `theme.scss` is otherwise invisible until someone looks at a page.        |
| `collapsingTheMainNavigationRequiresTheScriptMarker` | The `[data-js]` gate on the navigation collapse still holds — see [the `data-js` marker](#the-data-js-marker).                                                                                             |
| `theContentElementOutlineSwitchesOffCompletely`      | `[data-theme-content-outline='off']` still removes the label together with the outline — see [the content-element outline](#the-content-element-outline).                                                  |
| `noComponentReferencesAnUndeclaredToken`             | Every `var(--theme-…)` referenced anywhere under `Resources/Private/Scss/` is declared somewhere in the same tree — walked on the sources, not the compiled file, so the offending name is still readable. |

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
