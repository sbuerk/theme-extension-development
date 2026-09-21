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
| Avatar                  | `.theme-avatar`           | `components/_avatar.scss`           |
| Avatar group            | `.theme-avatar-group`     | `components/_avatar.scss`           |
| Badge                   | `.theme-badge`            | `components/_badge.scss`            |
| Breadcrumb              | `.theme-breadcrumb`       | `components/_breadcrumb.scss`       |
| Button                  | `.theme-button`           | `components/_button.scss`           |
| Byline                  | `.theme-byline`           | `components/_byline.scss`           |
| Card                    | `.theme-card`             | `components/_card.scss`             |
| Card scroller           | `.theme-card-scroller`    | `components/_card.scss`             |
| Card wall               | `.theme-card-grid--wall`  | `components/_card.scss`             |
| Carousel                | `.theme-carousel`         | `components/_carousel.scss`         |
| Close button            | `.theme-close`            | `components/_close.scss`            |
| Code block              | `.theme-code`             | `components/_code.scss`             |
| Content element wrapper | `.theme-content-element`  | `components/_content-element.scss`  |
| Content menu            | `.theme-content-menu`     | `components/_content-menu.scss`     |
| Call to action          | `.theme-cta`              | `components/_cta.scss`              |
| Description list        | `.theme-dl`               | `components/_description-list.scss` |
| Display settings        | `.theme-settings`         | `components/_settings.scss`         |
| Dialog                  | `.theme-dialog`           | `components/_dialog.scss`           |
| Divider                 | `.theme-divider`          | `components/_divider.scss`          |
| Dropdown                | `.theme-dropdown`         | `components/_dropdown.scss`         |
| Embed                   | `.theme-embed`            | `components/_embed.scss`            |
| Feature                 | `.theme-feature`          | `components/_feature.scss`          |
| Feature grid            | `.theme-feature-grid`     | `components/_feature.scss`          |
| Feature introduction    | `.theme-feature-intro`    | `components/_feature.scss`          |
| Figure                  | `.theme-figure`           | `components/_figure.scss`           |
| File list               | `.theme-file-list`        | `components/_file-list.scss`        |
| Footnote reference      | `.theme-footnote-ref`     | `components/_footnotes.scss`        |
| Footnotes               | `.theme-footnotes`        | `components/_footnotes.scss`        |
| Gallery                 | `.theme-gallery`          | `components/_gallery.scss`          |
| Hero                    | `.theme-hero`             | `components/_hero.scss`             |
| Icon                    | `.theme-icon`             | `components/_icon.scss`             |
| Language menu           | `.theme-language-menu`    | `components/_language-menu.scss`    |
| Lightbox                | `.theme-lightbox`         | `components/_lightbox.scss`         |
| Link decoration         | `.theme-link`             | `components/_link.scss`             |
| List                    | `.theme-list`             | `components/_list.scss`             |
| List group              | `.theme-list-group`       | `components/_list-group.scss`       |
| Media                   | `.theme-media`            | `components/_media.scss`            |
| Media object            | `.theme-media-object`     | `components/_media-object.scss`     |
| Main navigation         | `.theme-nav-main`         | `components/_nav-main.scss`         |
| Meter                   | `.theme-meter`            | `components/_meter.scss`            |
| Sub navigation          | `.theme-nav-sub`          | `components/_nav-sub.scss`          |
| Pagination              | `.theme-pagination__list` | `components/_pagination.scss`       |
| Panel                   | `.theme-panel`            | `components/_panel.scss`            |
| Pricing                 | `.theme-pricing`          | `components/_pricing.scss`          |
| Progress                | `.theme-progress`         | `components/_progress.scss`         |
| Quote                   | `.theme-quote`            | `components/_quote.scss`            |
| Segmented control       | `.theme-segmented`        | `components/_settings.scss`         |
| Palette swatch          | `.theme-swatch`           | `components/_settings.scss`         |
| Skip link               | `.theme-skip-link`        | `components/_skip-link.scss`        |
| Social links            | `.theme-social-links`     | `components/_social-links.scss`     |
| Split tiles             | `.theme-split-tiles`      | `components/_split-tiles.scss`      |
| Stat                    | `.theme-stat`             | `components/_stat.scss`             |
| Stats                   | `.theme-stats`            | `components/_stat.scss`             |
| Steps                   | `.theme-steps`            | `components/_steps.scss`            |
| Table                   | `.theme-table-wrapper`    | `components/_table.scss`            |
| Tabs                    | `.theme-tabs`             | `components/_tabs.scss`             |
| Tag list                | `.theme-tag-list`         | `components/_tag.scss`              |
| Teaser                  | `.theme-teaser`           | `components/_teaser.scss`           |
| Text: display           | `.theme-display`          | `components/_text.scss`             |
| Text: eyebrow           | `.theme-eyebrow`          | `components/_text.scss`             |
| Text: lead              | `.theme-lead`             | `components/_text.scss`             |
| Timeline                | `.theme-timeline`         | `components/_timeline.scss`         |
| Toggletip               | `.theme-toggletip`        | `components/_toggletip.scss`        |
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
bare class besides. It matches on a **token boundary**: `.theme-card` is not
counted as found because `.theme-card-scroller` is in the file, so a selector
of that table has to be declared under exactly the name the table gives it.
That is also why the table names `.theme-pagination__list` and not the nav
around it.

The **page column grid is not in that table** and not in that provider, which
is shared with
`StyleguideRenderingTest::everyComponentOfTheLibraryIsShownOnTheStyleguide` —
every entry there has to appear on the styleguide page. `.theme-page__columns`,
`.theme-page__cover` and `.theme-page__bands` are page structure written by
`Templates/Page/*.html` and chosen by the backend layout of the page; the
styleguide renders through the `styleguide` layout and has none of them, and a
specimen faking a page layout inside a page would demonstrate nothing. That
they reach the bundle is asserted by
`ComponentLibraryTest::thePageLayoutStructuresArePartOfTheBundle` instead, and
that the markup carries them by
`Tests/Functional/BackendLayoutRenderingTest`. The palette swatch is covered twice over,
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

| Component            | Icon                                                                                        | Size                                                      |
|----------------------|---------------------------------------------------------------------------------------------|-----------------------------------------------------------|
| Display settings     | `gear`; `desktop`, `sun`, `moon` for the appearance options; `check` on the chosen palette  | `.theme-settings__icon`, `.theme-segmented__icon`; one em |
| Accordion            | `chevron-down`, the marker, turned when an item opens                                       | `--theme-accordion-marker-size`, through the token        |
| Main navigation      | `bars`, before the label of the toggle                                                      | one em                                                    |
| Close button         | `xmark`                                                                                     | `--theme-close-glyph-size`, through the token             |
| Alert, notice, login | one per kind, picked by `Partials/ContentElement/AlertIcon.html`                            | `--theme-alert-icon-size`, through the token              |
| Field messages       | `circle-exclamation` in an error, `circle-check` in a success                               | one em                                                    |
| Icon button          | whatever the button stands for                                                              | `--theme-button-icon-size`, through the token             |
| Theme links          | the icon an editor picked, before the label of a button, a content menu link or a card link | one em, spaced by the `gap` of the link                   |
| Link decoration      | `arrow-up-right-from-square`, `download`, `envelope`, `phone`, as a CSS mask after the text | `--theme-link-marker-size`, three quarters of an em       |
| Tag                  | optional, whatever a template puts before the label                                         | one em, spaced by the `gap` of the tag                    |

### Link decoration

A link TYPO3 builds from a link reference — rich text, and the link fields of
the content elements — marked by what it leads to. Written by
`Classes/EventListener/LinkDecoration.php`, never by hand:

```html
<a class="theme-link theme-link--external" href="…" target="_blank">…<span class="theme-link__marker" aria-hidden="true"></span><span class="theme-link__hint">(opens in a new window)</span></a>
<a class="theme-link theme-link--download" href="…">…<span class="theme-link__marker" aria-hidden="true"></span><span class="theme-link__hint">(download)</span></a>
<a class="theme-link theme-link--mail" href="mailto:…">…<span class="theme-link__marker" aria-hidden="true"></span></a>
<a class="theme-link theme-link--tel" href="tel:…">…<span class="theme-link__marker" aria-hidden="true"></span></a>
```

The kind comes from the type TYPO3 resolved the link to — `url` on a host that
is no host of the installation, `file`, `email`, `telephone` — and not from an
attribute selector on the `href`, which cannot tell an absolute link to the
site itself from one elsewhere, nor a `t3://file` link from a page. The hosts of
the installation are the host of the request and every base, base variant and
language base any site configures, compared in their ASCII form
(`idn_to_ascii()`) and read with `parse_url()`, so userinfo in front of a host
does not pass for it. A `www.` variant counts only where a site configures it.

The marker is decoration. A link that opens a new window says so in the
visually hidden `__hint` whatever it leads to — a page of the site too, which
gets the hint and neither class nor marker — and a link to a file says it is a
download; a screen reader reads the hint out with the link. The download hint
is added to every file link and may repeat a label that already says
"download" — "Download the report (download)". It is left that way rather than
guessed from the label, which may be in any language; an editor who wants to
avoid it writes the label without the word. The listener acts only where the
theme's TypoScript sets `config.tx_theme.linkDecoration`, from the constant
`theme.linkDecoration`.

The marker is an empty span painted in the text colour and masked with the
icon file, the way the check list masks `check` — see
[Icons § The rule](icons.md#the-rule). It is a real element rather than
`::after` of the link, which `.theme-card--linked` already uses for its hit
area. Under forced colours it opts out with `forced-color-adjust: none`, so
the glyph is drawn in the link colour the system forces, not repainted to the
canvas colour. It keeps a quarter em from the text; a link that spaces its
content with a `gap` — `.theme-button`, `.theme-content-menu__link`,
`.theme-card__link` — sets `--theme-link-marker-gap: 0` on itself, read with a
fallback like `--theme-icon-size`.

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

In the row layout, from `bp.$md` up, the top level items drop the bottom
margin every `li` takes from the element baseline — all but the last one. An
entry is centred in its row by its margin box, so the last entry used to sit
half a margin lower than the others; stacked, the margin still adds to the
gap.

Sub navigation — the current section, not the whole site. No `--active`
modifier: the nav is scoped to one section, so `[aria-current="page"]` alone
is enough wherever it sits:

```html
<nav class="theme-nav-sub" aria-label="Section">
    <p class="theme-nav-sub__heading">…</p>
    <ul class="theme-nav-sub__list">
        <li class="theme-nav-sub__item"><a class="theme-nav-sub__link" href="…" aria-current="page">…</a></li>
        <li class="theme-nav-sub__item theme-nav-sub__item--branch">
            <a class="theme-nav-sub__link" href="…">…</a>
            <details class="theme-nav-sub__branch" open>
                <summary class="theme-nav-sub__toggle">
                    <span class="theme-nav-sub__toggle-label">Pages below …</span>
                    <svg class="theme-icon theme-nav-sub__marker" aria-hidden="true" focusable="false" …>…</svg>
                </summary>
                <ul class="theme-nav-sub__list theme-nav-sub__list--level-2">…</ul>
            </details>
        </li>
    </ul>
</nav>
```

An item with children is a **branch** and folds. It is a native
`<details>`/`<summary>` and nothing else — no script, no `data-js` gate, no
`aria-expanded` for a script to keep in step: the element carries the state,
the keyboard handling and the announced semantics, so the tree works on a page
whose JavaScript never arrived. The branch holding the current page is `open`,
every other one starts folded.

Three decisions in that markup, each of which was the second attempt:

- **The branch link is not inside the summary.** It was, and a summary names
  itself by its subtree text, so the branch title would have named the
  disclosure control for nothing. axe refuses it — `nested-interactive`,
  serious, in every appearance and palette of the styleguide fixture. So the
  link and the toggle are siblings.
- **The toggle is named by hidden text, not by `aria-label`.** Subtree text is
  the naming method a `<summary>` has, and the name says which branch:
  "Pages below Analytics".
- **The toggle is positioned, not laid out.** A grid on the `<details>` does
  not place its summary and its list, because a browser wraps the content of a
  `<details>` in slots of its own shadow tree and those slots are the grid
  items. The item is the positioning context instead, and the toggle sits in
  its corner over the list it discloses.

`NavigationRenderingTest` holds the markup and which branch is open;
`Tests/Acceptance/frontend.spec.ts` opens and closes one **with JavaScript
disabled**, and follows the branch link, because both are browser behaviour
rather than markup.

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

#### Social links

The row `theme_sociallinks` renders: one link per platform, carrying the
platform's logo from the vendored [brand set](icons.md#brand-logos) and the
platform's name as text.

```html
<nav class="theme-social-links" aria-label="Social links">
    <ul class="theme-social-links__list">
        <li class="theme-social-links__item">
            <a class="theme-social-links__link" href="…">
                <svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg>
                <span class="theme-social-links__label">Mastodon</span>
            </a>
        </li>
    </ul>
</nav>
```

**The name is always in the markup.** A logo is decoration — `aria-hidden`,
like every other icon of the theme — so `__label` is what names the link.
Where a logo was rendered the stylesheet hides that label *visually* and the
link keeps its accessible name; where none was, the label stays visible and
the entry is an ordinary text link.

The rule that hides it is `:has(.theme-icon)` on the link, **not** a modifier
class the template writes. The logo comes from a record and is rendered
`optional`, so a platform name a later Font Awesome version dropped renders
nothing at all — and a class decided at template time would then hide the
label of an entry with nothing left in it. `:has()` asks the question at the
one moment it can be answered: an icon actually came out. The marker the
[link decoration](#link-decoration) adds is hidden by the same rule, for the
reason it exists at all — "this leaves for another site" is what a platform
logo already says, and next to a hidden label the marker would be the only
other visible thing. Its hidden hint stays.

An entry with a logo is a square of `--theme-tap-target-min` in both
directions: it is a link, and WCAG 2.2 target size applies to it exactly as
it does to the settings button.

`ComponentLibraryTest::aSocialLinkHidesItsLabelOnlyWhereALogoWasRendered`
holds the conditional to the compiled stylesheet.

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

Byline — the credit line of a long text: who wrote it, when, and whatever
else fits in one short phrase. A `p` rather than a list, because a reader
hears one line and not three items; the date is a `time` with a machine
readable `datetime`, the rest are spans. Items are separated by the
`border-inline-start` of the item that follows, not by a character in
generated content — see [Icons](icons.md#the-rule). The row wraps, and a rule
at the start of a wrapped line is accepted rather than worked around: the
alternative puts a meaningless separator element into every byline. It is not
`.theme-author`, which is a person with a portrait and a bio, rendered by the
`theme_author` element:

```html
<p class="theme-byline">
    <span class="theme-byline__item">By <span class="theme-byline__author">…</span></span>
    <time class="theme-byline__item" datetime="2026-09-20">20 September 2026</time>
    <span class="theme-byline__item">…</span>
</p>
```

Avatar — a portrait, or the initials where there is none. `--square` takes
the system radius instead of the circle, `--small` and `--large` are 30 and
60 pixels against the default 40, all steps of the spacing scale. The
initials are text, not an icon, set at 40% of the size; the name is the
`aria-label` of the avatar with `role="img"`, and the letters are
`aria-hidden`, since "A E" read letter by letter is not a name. Next to the
person's visible name — an author line, a comment — the avatar is decoration:
the image takes `alt=""` and the initials drop `role` and `aria-label`, so the
name is not read twice. A group is a list whose avatars overlap, each ringed in
`--theme-avatar-group-ring-color` — the page background by default, re-pointed
by a group that sits on a card or a band — and whose last item may count the
people left out:

```html
<span class="theme-avatar"><img class="theme-avatar__image" src="…" alt="…" width="40" height="40"></span>
<span class="theme-avatar theme-avatar--square theme-avatar--large"><img class="theme-avatar__image" …></span>
<span class="theme-avatar" role="img" aria-label="Ada Example"><span class="theme-avatar__initials" aria-hidden="true">AE</span></span>

<!-- Next to the visible name: decoration -->
<span class="theme-avatar"><img class="theme-avatar__image" src="…" alt="" width="40" height="40"></span> Ada Example
<span class="theme-avatar"><span class="theme-avatar__initials" aria-hidden="true">AE</span></span> Ada Example

<ul class="theme-avatar-group" aria-label="…">
    <li><span class="theme-avatar">…</span></li>
    <li><span class="theme-avatar" role="img" aria-label="3 more"><span class="theme-avatar__initials" aria-hidden="true">+3</span></span></li>
</ul>
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
<a class="theme-button" href="…"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg> …</a>
<button class="theme-button theme-button--icon" type="button" aria-label="…"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></button>
<button class="theme-button theme-button--secondary" type="button" aria-pressed="false">…</button>
<button class="theme-button" type="button" aria-busy="true" aria-disabled="true">…</button>
```

An icon before the label is decoration, spaced by the button's `gap` in
reading order; it is how the link of a theme content element renders the icon
an editor picked, in each of the four styles the element offers — the plain
button, `--secondary`, `--ghost` and `--link`.

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
rather than overflowing. It is a block like a list or a figure, and ends on
the same bottom margin, `var(--theme-space-4)`, so the component after it does
not touch the buttons. `--attached` makes the row one control in several
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

Every part of a card but the body is optional. `__subtitle` follows the title;
`__actions` holds a `.theme-button` instead of the card link and is pushed to
the foot of the body the same way. Where the title itself is the link - a page
in a menu - it holds `.theme-card__title-link`, which `--linked` stretches over
the card like `__link`. `--compact` tightens the padding and the title for a
card that is mostly its image:

```html
<li class="theme-card theme-card--linked theme-card--compact">
    <div class="theme-card__media"><img …></div>
    <div class="theme-card__body">
        <h3 class="theme-card__title"><a class="theme-card__title-link" href="…">…</a></h3>
        <p class="theme-card__subtitle">…</p>
        <div class="theme-card__actions"><a class="theme-button theme-button--secondary" href="…">…</a></div>
    </div>
</li>
```

A grid of cards is a `div` of `article`s, or a `ul` of `li.theme-card` where the
cards are items of a list; the grid resets the indent, the markers and the
margin a list gets from the element baseline, and a card the margin of a list
item. `--columns-2`, `--columns-3` and `--columns-4` cap the number of columns
and keep the minimum width of one, so a row still breaks into fewer where there
is no room - no breakpoint, the minimum decides. Four columns lower the minimum
to 11rem, since four of 14rem need more than a content column has. `--narrow`
lowers it to 9rem and, unlike every other grid of the file, keeps empty tracks
(`auto-fill`), so thumbnails stay small where there are few of them.

`--scroller` turns the grid into one row that scrolls sideways, in a
`.theme-card-scroller` region:

```html
<div class="theme-card-scroller" role="region" tabindex="0" aria-label="…">
    <ul class="theme-card-grid theme-card-grid--scroller theme-card-grid--columns-3">
        <li class="theme-card">…</li>
    </ul>
</div>
```

No script: the browser scrolls, `scroll-snap-type` snaps each card to the start
edge, and the keyboard reaches the strip the way it reaches a table - the
wrapper is the one region that scrolls, and it carries the tab stop and a name,
so the arrow keys scroll it once it has focus. A link inside a card is reached
by Tab, and the browser scrolls it into view. The wrapper and not the list is
the region, because a `role` on the list would take its list semantics away. A
card is the share of the column count less a quarter of a card, so the next one
always shows at the end edge; on a phone it takes the minimum width, at most
85% of the strip. Nothing animates, so `prefers-reduced-motion` has nothing to
stop.

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
reader cannot read. `--divided` draws a hairline in the decorative border
colour above every term but the first — the key and value look of the details
of a record; with `--horizontal` the line runs across both columns, and a
second description of the same term follows without one. Key and value is
therefore this component with a modifier, not a component of its own.
`dt`/`dd` are bare direct children, and the bare class differs from the
element baseline only in not indenting the description:

```html
<dl class="theme-dl theme-dl--horizontal">
    <dt>…</dt>
    <dd>…</dd>
</dl>
<dl class="theme-dl theme-dl--horizontal theme-dl--divided">…</dl>
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

Feature — an icon, a title, a sentence or two and an optional link; a group of
them is a `.theme-feature-grid`. Three item layouts: `--column` (the default,
the icon on a tile of the primary accent above the title), `--hanging` (a
smaller framed tile at the start of the line, the text beside it) and `--tile`
(the whole feature framed, the icon above). `--columns-2`, `--columns-3` and
`--columns-4` set the most columns the grid takes; each column is at least
12rem wide, so a narrow column gets fewer, down to one, without a breakpoint.
Four columns take 53.625rem: the full-width page layout gives a content
element that much, the main column beside a sub navigation does not, and
there the grid shows three.
`.theme-feature-intro` sets a block of text beside a grid from `bp.$md` up.
`.theme-feature__title` is selected by class on any heading level and states
`text-transform: none`, like the hero title:

```html
<div class="theme-feature-intro">
    <div class="theme-feature-intro__text">…</div>
    <div class="theme-feature-grid theme-feature-grid--columns-3">
        <div class="theme-feature theme-feature--column">
            <span class="theme-feature__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></span>
            <div class="theme-feature__body">
                <h3 class="theme-feature__title">…</h3>
                <p class="theme-feature__text">…</p>
                <a class="theme-feature__link" href="…">…</a>
            </div>
        </div>
    </div>
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

Call to action — a heading, a short text, an optional large icon and up to two
links, centred on one axis, the text held to the measure. Width modifiers
`--boxed` (narrower than the column, centred in it) and `--band` (across the
column: no radius, no frame at the sides, more air above and below); tone
modifiers `--accent` (the 5% tint of the accent band), `--inverse` (the colour
scheme of the subtree turned, in the three rules of the inverse band) and
`--placeholder` (no fill, a dashed frame in `--theme-color-border-strong`).
The bare class is a box on the surface filling its column. The tones reuse the
band tokens rather than defining their own, so the band contrast in
[`DESIGN.md`](../../DESIGN.md#call-to-action) holds; `ContentElementContractTest`
holds the tint and the three scheme rules to the stylesheet:

```html
<section class="theme-cta theme-cta--band theme-cta--accent">
    <span class="theme-cta__icon" aria-hidden="true"><svg class="theme-icon" …>…</svg></span>
    <h2 class="theme-cta__title">…</h2>
    <div class="theme-cta__text"><p>…</p></div>
    <div class="theme-cta__actions">
        <a class="theme-button" href="…">…</a>
        <a class="theme-button theme-button--secondary" href="…">…</a>
    </div>
</section>
```

File list — the three display types of the "File Links" element. Modifiers:
the bare class (the names alone), `--icon` (the icon of the file type before
each name) and `--preview` (a square thumbnail, or the icon of the file type
in a square of the same size where no thumbnail can be made). Icon and
thumbnail sit beside the link, never inside it: the link is the file name,
the icon slot is `aria-hidden` and the thumbnail has `alt=""`. The size and
the description are optional parts of the body. Rows are separated by the
hairline, like `.theme-list--divided`:

```html
<ul class="theme-file-list theme-file-list--icon">
    <li class="theme-file-list__item">
        <span class="theme-file-list__icon" aria-hidden="true"><svg class="theme-icon" …>…</svg></span>
        <div class="theme-file-list__body">
            <a class="theme-file-list__link" href="…">report.pdf</a>
            <span class="theme-file-list__size">12 KB</span>
            <p class="theme-file-list__description">…</p>
        </div>
    </li>
</ul>
```

Footnotes, and the reference that points at one — two blocks of one file,
because neither is any use without the other and the reference never sits
inside the list. The numbers are in the markup rather than in a counter or an
`ol` marker: the number is what the reference and the note have in common, and
a reader copying the sentence out copies it with them. `.theme-footnotes__item`
hangs its wrapped lines two characters past the number. The back link's only
content is the vendored `arrow-turn-up`, masked by the stylesheet for the
reason the markers of a decorated link are — no template of the theme writes
this markup, it comes out of a rich text or an `html` element — so the link is
named by `aria-label`, and the forced-colours rule keeps it `currentColor`
rather than `CanvasText`:

```html
<p>… a sentence<sup class="theme-footnote-ref" id="fnref1"><a href="#fn1">1</a></sup>.</p>

<aside class="theme-footnotes" aria-labelledby="…">
    <h2 class="theme-footnotes__heading" id="…">Notes</h2>
    <ol class="theme-footnotes__list">
        <li class="theme-footnotes__item" id="fn1">
            1. The note. <a class="theme-footnotes__backlink" href="#fnref1" aria-label="…"></a>
        </li>
    </ol>
</aside>
```

Hero. Modifiers: default (text only), `--media` (adds `theme-hero__media`),
`--compact`, and the layouts of the hero elements, which combine with both:
`--image-end` (the image at the end of the row, still first in the markup),
`--centred` (text, actions and image on one axis, the image no wider than the
measure), `--screenshot` (centred, the image after the text in the markup, in
a 2:1 box resting on the bottom edge, which cuts it off) and `--bordered` (the
image at the end, running out of the end and bottom edges, a hairline on its
two inner edges). The layouts come after `--media` in the file because two of
them undo its row. `__eyebrow` is filled by the field `tx_theme_eyebrow`:

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

Media object — an icon of the set beside a block of content. Three
independent axes, one modifier each, the first value the default: position
`--start`, `--end`, `--top`; shape `--plain`, `--square`, `--circle`; size
`--md`, `--lg`, `--xl`. `--start` and `--end` are the edges of the line;
`--end` reverses the row, not the source, so the icon stays first in the
markup. `--plain` is the icon in the primary accent; `--square` and `--circle`
put it on a tile twice its size, filled with the primary accent, the icon in
`on-primary`. The tile follows the icon size through a token computed on the
same element, so the modifiers combine in any order:

```html
<div class="theme-media-object theme-media-object--start theme-media-object--square theme-media-object--lg">
    <span class="theme-media-object__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></span>
    <div class="theme-media-object__body">…</div>
</div>
```

Progress and meter — the native `<progress>` and `<meter>`, each labelled
by a `label` in a `.theme-field` and followed by its value in words as a
`.theme-field__hint`: a bar is read at a glance, a number exactly. A meter is
a measurement within a known range — storage used — and a progress bar the
progress of a task. The browser sorts a meter's value into a region from
`low`, `high` and `optimum`, drawn in the success, warning and danger colour;
colour alone carries no information (WCAG 1.4.1), so the hint
says what the region means — "nearly full" — and the meter references it with
`aria-describedby`. A progress bar without `value` is indeterminate and shows
the empty track:

```html
<div class="theme-field">
    <label class="theme-field__label" for="upload-progress">…</label>
    <progress class="theme-progress" id="upload-progress" max="100" value="40" aria-describedby="upload-progress-value">40 %</progress>
    <p class="theme-field__hint" id="upload-progress-value">40 % of 12 MB</p>
</div>
<div class="theme-field">
    <label class="theme-field__label" for="storage-meter">…</label>
    <meter class="theme-meter" id="storage-meter" min="0" max="100" low="60" high="85" optimum="0" value="92" aria-describedby="storage-meter-value">92 GB</meter>
    <p class="theme-field__hint" id="storage-meter-value">92 of 100 GB used - nearly full</p>
</div>
```

The track is the element's own box after `appearance: none`, and its edge is
a graphical object held to 3:1 (WCAG 1.4.11): it is drawn in
`--theme-color-border-strong`, like the edge of a text field, not in the
decorative border. Chromium and WebKit draw the fill as pseudo-elements of
their own — `::-webkit-progress-value`, and one per region of a meter — and
Firefox as `::-moz-progress-bar` and `::-moz-meter-bar`, each in a rule of its
own, since a selector list naming a pseudo-element an engine does not know is
dropped by that engine as a whole. Chromium stretches a meter's fill to the
height of the track only as the one flex item of its inner element; a height
on the bar or the value does not reach it. Only Chromium is tested, by the
visual suite; the Firefox rules were looked at once in the Firefox of the
pinned Playwright image. The contrast of the fills and the edge is in
[`DESIGN.md`](../../DESIGN.md#indicators).

List group — a framed list of rows, each an avatar, a title, a line of text
and meta data at the end. Every part but the title is optional:

```html
<ul class="theme-list-group">
    <li class="theme-list-group__item">
        <span class="theme-avatar"><img class="theme-avatar__image" src="…" alt="" width="40" height="40"></span>
        <div class="theme-list-group__body">
            <h3 class="theme-list-group__title"><a class="theme-list-group__link" href="…">…</a></h3>
            <p class="theme-list-group__text">…</p>
        </div>
        <p class="theme-list-group__meta"><time datetime="…">…</time> <span>…</span></p>
    </li>
</ul>
```

A row has one link, the title, and the whole row is its target - the
technique of `.theme-card--linked`: the link's `::after` is stretched over the
row, so no interactive element is nested in another and the name of the link is
the title alone. Hovering the row fills it with the surface colour. The focus
ring moves from the title to the row, because the row is what a press
activates: the ring of `base/_reset.scss`, drawn on the link's `::after` - the
hit area that already covers the row - and inset by its own width so the frame
of the list does not cut it off. It depends on nothing but the link having
`:focus-visible`, not on `:has()` on the row. That began as a browser floor
decision — the floor was Firefox 120 and `:has()` arrived in 121 — and the
[floor](../../DESIGN.md#the-browser-floor) has since moved to 125, so the
original reason no longer holds; the rule is kept because depending on the
link's own `:focus-visible` is the simpler dependency for an indicator that
has to be right. The hover fill does use `:has()`; without it, hover shows
the underline of the title only. A row without a link has a title of plain
text and is no target. The meta data wraps onto a line of its own, at its end,
where the row is too narrow for it. The image is an [avatar](#content) in its
decorative form, `alt=""`: it sits beside the visible title, which names the
row, and the list group styles nothing of it.

Timeline — an ordered list on an axis at the inline start: a ring on the first
line of every entry, and a hairline down to the ring of the next. An entry may
carry an icon instead of the ring, in `__icon`:

```html
<ol class="theme-timeline">
    <li class="theme-timeline__item">
        <span class="theme-timeline__icon" aria-hidden="true"><theme:icon name="…" /></span>
        <time class="theme-timeline__date" datetime="…">…</time>
        <h3 class="theme-timeline__title">…</h3>
        <div class="theme-timeline__media"><img …></div>
        <p class="theme-timeline__text">…</p>
    </li>
</ol>
```

The ring and the rail are the item's `::before` and `::after`, component
geometry rather than icons - see [Icons § The rule](icons.md#the-rule). The
date sets its line box to `--theme-timeline-first-line`, and the ring and the
icon are placed against the same length, so they stay centred on the date when
a font size changes. The icon sits on the background colour, so the rail stops
at its edge; the ring is dropped for an item holding the slot. Under forced
colours the rail, a background, is painted `CanvasText`.

Quote. Modifiers `--pull` (larger and bolder, between a rule above and one
below in the accent, instead of the rule at the start) and `--centred` (on one
axis, no rule, the text held to the measure). Both open with the quotation
mark in `__mark`: the `quote-left` icon of the set, rendered by `<theme:icon>`
in the accent of the rule, `aria-hidden` because the `blockquote` is what makes
it a quotation. The bare class has no mark:

```html
<figure class="theme-quote">
    <blockquote class="theme-quote__text"><p>…</p></blockquote>
    <figcaption class="theme-quote__attribution">
        <span class="theme-quote__author">…</span>
        <cite class="theme-quote__source">…</cite>
    </figcaption>
</figure>

<figure class="theme-quote theme-quote--pull">
    <span class="theme-quote__mark" aria-hidden="true"><svg class="theme-icon" …>…</svg></span>
    <blockquote class="theme-quote__text"><p>…</p></blockquote>
    <figcaption class="theme-quote__attribution">…</figcaption>
</figure>
```

The media slot `__portrait` holds a `.theme-avatar` in its large size, first
in the attribution. It only centres the avatar on the line of the name; next
to that visible name the picture is decoration, with an empty `alt` - see
[Content elements](../architecture/content-elements.md#the-portrait-of-the-testimonial):

```html
<figcaption class="theme-quote__attribution">
    <span class="theme-avatar theme-avatar--large theme-quote__portrait"><img class="theme-avatar__image" src="…" alt=""></span>
    <span class="theme-quote__author">…</span>
    <cite class="theme-quote__source">…</cite>
</figcaption>
```

Stat — a figure and what it counts, as a description list: the label is the
term, the figure its description, a sentence about it a second one. Each pair
is grouped in a `div`, which a `dl` allows, and is one box of the grid. The
source reads label then figure; `order` shows the figure first and larger.
The figure is set in `tabular-nums`, so a row of figures lines up, and an
optional icon sits before it, three quarters of its size:

```html
<dl class="theme-stats">
    <div class="theme-stat">
        <dt class="theme-stat__label">…</dt>
        <dd class="theme-stat__value"><span class="theme-stat__icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></span>2001</dd>
        <dd class="theme-stat__text">…</dd>
    </div>
</dl>
```

Steps — a process as an ordered list. The marker is empty in the markup and
numbered by a CSS counter, so the source carries no number that could
disagree with the order; `__marker--icon` holds an icon of the set instead.
The markers are `aria-hidden`: the list already says which item of how many a
step is. Below `bp.$md` the steps run down, from `bp.$md` up across - a list of
up to five steps; one with a sixth stays down, through `:has()`, since a
sixth of the column is narrower than a title - and a
hairline rail - a border of an empty pseudo element - joins each marker to
the next:

```html
<ol class="theme-steps">
    <li class="theme-steps__step">
        <span class="theme-steps__marker" aria-hidden="true"></span>
        <div class="theme-steps__body">
            <h3 class="theme-steps__title">…</h3>
            <p class="theme-steps__text">…</p>
        </div>
    </li>
    <li class="theme-steps__step">
        <span class="theme-steps__marker theme-steps__marker--icon" aria-hidden="true"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg></span>
        …
    </li>
</ol>
```

Table. `tabindex="0"` plus `role="region"` plus `aria-label` on the wrapper is
the W3C APG scrollable-region-focusable pattern — a container that scrolls but
carries no tab stop is unreachable by keyboard. The wrapper is the block, and
ends on the bottom margin a bare `table` of the element baseline ends on:

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

Tag — a keyword attached to something, in a list that wraps. Not a badge: a
badge is a status with a severity, a tag a label that may lead to everything
else carrying it. A tag is text or a link, told apart by the `href` rather
than by a modifier; a linked tag is underlined as well as drawn in the primary
accent, so it differs from a plain one by more than colour (WCAG 1.4.1), and
turns its hairline to the accent on hover. It is at least 24 pixels high, the
target size WCAG 2.5.8 asks for. An icon before the label is optional
decoration, spaced by the `gap`, which is why a decorated link inside a tag
drops its own marker gap, as a button does:

```html
<ul class="theme-tag-list" aria-label="…">
    <li><span class="theme-tag">…</span></li>
    <li><a class="theme-tag" href="…">…</a></li>
    <li><a class="theme-tag" href="…"><svg class="theme-icon" aria-hidden="true" focusable="false" …>…</svg> …</a></li>
</ul>
```

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

Text in columns — the layout "Columns" of the text element — is a modifier
that stands on its own, like the alignments, on the block holding the rich
text:

```html
<div class="theme-content-element__body theme-text--columns">…rich text…</div>
```

At most two columns, each at least `30ch` (`columns`, so one on a phone), a
hairline in `--theme-color-border` between them, no heading ending a column
and no figure, table, quotation, code block or list item split across two.
The count, the width, the gap and the rule colour are tokens of its own
(`--theme-text-columns-*`).

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

Media — a video or an audio file, played in place. The modifier is not
decoration: the two elements need opposite sizing rules, and a video is
letterboxed into `--theme-media-ratio` until its own dimensions are known,
because FAL records none for a video file and `GalleryProcessor` would
otherwise compute a box of no height for it. Inside a gallery the item is a
`.theme-media` **as well as** a `.theme-figure`, so the caption stays the
gallery's own:

```html
<figure class="theme-media theme-media--video">
    <video class="theme-media__player" controls preload="metadata" playsinline>
        <source src="…" type="video/mp4">
        <track kind="captions" src="…" label="…">
    </video>
    <figcaption class="theme-media__caption">…</figcaption>
</figure>
```

The tag is written in the template rather than left to `f:media`, and that is
the point of the component: the core's `AudioTagRenderer` and `VideoTagRenderer`
both emit `<video controls><source …></video>` and **neither has any notion of
a text track** — there is no `track` in either class, on v13.4 or v14.3. A
caption track is the one part of a media element that is not decoration (WCAG
1.2.2), so it cannot be left to a renderer that cannot produce one.

Embed — a video of another site, which is not requested until the reader asks
for it. The markup carries no `iframe`, no preconnect and no image of the other
site; `theme.js` builds the frame on the press and replaces the placeholder
with it:

```html
<figure class="theme-embed theme-embed--16-9">
    <div class="theme-embed__frame">
        <img class="theme-embed__poster" src="…" alt="" width="…" height="…">
        <button class="theme-embed__button" type="button" data-theme-embed-play
                data-theme-embed-src="https://www.youtube-nocookie.com/embed/…"
                data-theme-embed-title="…">
            <span class="theme-embed__play" aria-hidden="true"><svg class="theme-icon" …>…</svg></span>
            <span class="theme-embed__label">…</span>
        </button>
    </div>
    <figcaption class="theme-embed__note">… <a class="theme-embed__source" href="…">…</a></figcaption>
</figure>
```

`--16-9` and `--4-3` re-point `--theme-embed-ratio`, which the frame keeps
whether it holds the placeholder or the iframe, so nothing below it moves when
the one replaces the other. The placeholder is a **button**, not a link: a link
would have to point somewhere, and the only place it could point is the host
this is avoiding.

Lightbox — the enlarged image of a gallery, in a dialog. A `.theme-dialog`
first, widened for a picture; everything the dialog contract documents applies:

```html
<dialog class="theme-dialog theme-lightbox" id="c1-lightbox" aria-label="…">
    <form method="dialog">
        <div class="theme-dialog__header theme-lightbox__header">
            <button class="theme-close" aria-label="Close">…</button>
        </div>
        <div class="theme-dialog__body theme-lightbox__body">
            <figure class="theme-lightbox__item" id="c1-lightbox-7" data-theme-lightbox-item>
                <img class="theme-lightbox__image" src="…" alt="…">
                <figcaption class="theme-lightbox__caption">…</figcaption>
            </figure>
        </div>
        <div class="theme-dialog__footer theme-lightbox__controls">
            <button class="theme-button theme-button--secondary theme-button--icon" type="button" data-theme-lightbox-previous aria-label="Previous image">…</button>
            <button class="theme-button theme-button--secondary theme-button--icon" type="button" data-theme-lightbox-next aria-label="Next image">…</button>
        </div>
    </form>
</dialog>
```

Three departures from the dialog's own contract, each forced by what is in this
one: the header carries **no title** (an image has a caption, which is inside
the picture and changes with it, and a heading reading "Image" would be the
same word on every gallery of every page); the close button carries **no
`value`**, because nothing here reads `returnValue`; and the two arrows are
**`type="button"`**, because inside a `<form method="dialog">` a button without
a type is a submit button and the first arrow press would close the lightbox
instead of moving it.

Items are addressed **by the id of their file reference**, never by a position.
A `textmedia` gallery may hold a video between two images, and an index would
have to agree with a list the template does not have.

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

Page column grid — the multi column page layouts, inside `.theme-page__main`
rather than on it, because the breadcrumb and the stage render into `__main`
first and would otherwise become columns:

```html
<main class="theme-page__main" id="content">
    <nav class="theme-breadcrumb">…</nav>
    <div class="theme-page__columns theme-page__columns--halves">
        <div class="theme-page__column">…</div>
        <div class="theme-page__column">…</div>
    </div>
</main>
```

`--halves`, `--wide-start`, `--thirds` and `--article` size the tracks; the
number of columns is the number of children the template wrote. Every track is
`minmax(0, …)` so a wide child — a table, a code block, an unbroken URL —
shrinks its track rather than pushing the row past the page. Stacked below
`bp.$md`. Why the grid is there and not on `__main`:
[page rendering](../architecture/page-rendering.md#the-column-grid-sits-inside-theme-page__main-not-on-it).

`--article` pairs a column capped at the reading measure with a fixed aside.
The aside is an `<aside class="theme-page__column">` of that grid and **not**
`.theme-page__aside`, which is the navigation column of the shell:

```html
<div class="theme-page__columns theme-page__columns--article">
    <div class="theme-page__column theme-page__column--measure">…</div>
    <aside class="theme-page__column">…</aside>
</div>
```

Cover — one slot, centred in what the shell leaves between header and footer.
`.theme-page__main:has(.theme-page__cover)` becomes the grid that centres it;
no height is stated anywhere, because `.theme-page` is already at least
`100dvh` tall:

```html
<main class="theme-page__main" id="content">
    <div class="theme-page__cover">…</div>
</main>
```

Band stack — the one structure that leaves the content container.
`.theme-page__body:has(.theme-page__bands)` drops `max-width` and the inline
padding, so each band spans the viewport while the header and the footer keep
theirs. Bands are flush; `.theme-page__bands` is a grid so the last margin of
one band cannot collapse into the next:

```html
<div class="theme-page__bands">
    <div class="theme-page__band">…</div>
    <div class="theme-page__band">…</div>
</div>
```

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
navigation and actions share one row at every width. Above `bp.$md` the top
level of the menu may wrap onto a second row inside its frame, aligned to the
end, and an entry never breaks inside itself; which of title and menu gives
way when the row gets tight depends on its width:

- From `bp.$lg` up the title does not give way: the brand keeps its line up to
  half the row, and the navigation shrinks down to its widest entry and wraps.
  That holds seven top level entries beside the title of the showcase at 1280
  pixels, where a sixth used to push the title onto a second line.
- Between `bp.$md` and `bp.$lg` the menu keeps its row, up to 50% of the row,
  and the brand wraps beside it — held to one line there, the title left a
  menu of three entries on two rows next to a title on two lines anyway. A
  menu wider than 50% wraps inside that width, so nothing spills sideways. It
  was 60% while the actions slot held one control, the settings cog; the
  language dropdown is a second one, and 60% then left the brand less than
  its longest word with seven entries at 768 pixels and the row spilled
  sideways — `layout/_site-header.scss` says so at the rule.

The showcase itself has **six** top level entries since the page layouts were
added, and its own menu therefore takes two rows at 1280 pixels beside a title
that keeps its line — five entries on the first row and the sixth on the
second, measured in the browser, not deduced. That is the first of those two
rules working, not a defect.

`Tests/Acceptance/frontend.spec.ts` asserts both, in both trees: seven entries
beside a one line title at 1280 pixels, three in one row at 768 pixels, and
seven without spilling sideways at 768 and 900 pixels. It also holds the seven
entries of the showcase to **at most two rows** at 1280: without a bound on the
rows, the checks that no two entries overlap and that the entries of one row
share their top are all satisfied by seven entries on seven rows. Below `bp.$md` the
expanded navigation drops down under the header as a full-width band, behind
`data-js` like the collapse itself.

#### Header variants

The arrangement above is one of four, and the one a site gets unless it says
otherwise. The site setting `theme.header.variant` selects the others; each is
a partial below `Partials/Page/Header/` and a modifier on the header, and
`Partials/Page/Header.html` is a switch over literal partial names — the value
of the setting never becomes part of a path.

| Value      | Rows                                                                  | Modifier     |
|------------|-----------------------------------------------------------------------|--------------|
| `simple`   | Title, navigation and controls in one row. **The default.**           | none         |
| `centred`  | Title centred with the controls at the end; navigation centred below. | `--centred`  |
| `actions`  | Title, call to action and controls; navigation below.                 | `--actions`  |
| `two-tier` | A tinted meta row of controls above title and navigation.             | `--two-tier` |

```html
<header class="theme-site-header theme-site-header--two-tier">
    <div class="theme-site-header__meta">
        <div class="theme-site-header__inner theme-site-header__inner--meta">…controls…</div>
    </div>
    <div class="theme-site-header__inner theme-site-header__inner--main">…brand, nav…</div>
</header>
```

**None of the three re-opens the width budget above.** That budget is measured
in a browser and holds for the single row; a variant that put a third thing in
that row would have to be measured again. Instead each of them takes something
*out* of it — `centred` gives the title a row, `actions` gives the navigation
one and puts the call to action in the space it leaves, `two-tier` moves both
controls into a meta row. `ComponentLibraryTest::aHeaderVariantDoesNotChangeTheMeasuredWidthBudget`
fails on a variant rule that sets a width on the navigation or the brand, which
is the way that promise breaks silently: the acceptance tests render the
default and would stay green.

The call to action of `actions` takes two more settings, `theme.header.actionPage`
and `theme.header.actionLabel`, and renders nothing unless both are set. The
destination is a **page uid, not a link** — nothing an integrator writes into
the setting can become the scheme of that `href`, which a link setting would
allow. The cost is that the call to action cannot leave the site.

The styleguide section `chrome` shows all four; `ChromeVariantRenderingTest`
renders each one from the setting, and asserts that a value the switch does not
know falls back to the default.

#### Footer variants

The footer has two arrangements, selected by `theme.footer.variant` the same
way the header's are, with the same rules — a partial each below
`Partials/Page/Footer/`, a switch over literal names in
`Partials/Page/Footer.html`, and a default that carries no modifier.

| Value        | The footer is                                                 | Modifier       |
|--------------|---------------------------------------------------------------|----------------|
| `columns`    | The four content columns above the meta row. **The default.** | none           |
| `newsletter` | A newsletter band above those.                                | `--newsletter` |

```html
<footer class="theme-site-footer theme-site-footer--newsletter">
    <div class="theme-site-footer__newsletter">
        <div class="theme-site-footer__inner theme-site-footer__inner--newsletter">
            <div class="theme-site-footer__newsletter-text">
                <h2 class="theme-site-footer__newsletter-heading">…</h2>
                <p>…</p>
            </div>
            <a class="theme-button theme-button--primary theme-site-footer__newsletter-action">…</a>
        </div>
    </div>
    <div class="theme-site-footer__inner">…columns, meta…</div>
</footer>
```

**The band is a link, not a form.** A subscription form needs somewhere to
post to, and the theme has nowhere — no controller, no storage, no double opt
in — so the band leads to the page that carries the real form. A field that
looks like a subscription and drops what is typed into it is worse than no
field. The heading, that page and the button's label are all required; the
one line of text under the heading is optional, and the band renders nothing
at all if any of the three is missing.

**Social icons are content, not a slot.** A row of platform logos in the
footer is the `theme_sociallinks` element placed in one of the four columns —
see [Social links](#social-links). The theme adds no footer slot for it,
because a column already is one.

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

| Modifier          | Fill                                                               |
|-------------------|--------------------------------------------------------------------|
| `--frame-surface` | `--theme-color-surface`                                            |
| `--frame-raised`  | `--theme-color-surface-raised`                                     |
| `--frame-accent`  | the primary accent at 5% in the background, in Oklab               |
| `--frame-inverse` | the background of the other appearance                             |
| `--frame-none`    | none; the inner padding is `0` but for the chip, the outline stays |

`--frame-none` keeps its content below the CType chip, which straddles the top
edge: by half the line box of the chip, `--theme-content-element-chip-clearance`.
The global switch and `--plain` remove the chip and set the clearance to `0`,
so an element without outline is flush with its box.

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

`theme.js` binds nine groups in all. Seven of them are the components in the
table below, each written so that the page is still usable without the script —
with JavaScript switched off, and with JavaScript on but `theme.js` failing to
load, which are two different pages. The other two are just as script-driven and
have sections of their own rather than a row here: the
[display settings](#display-settings), and the main navigation toggle behind
[the `data-js` marker](#the-data-js-marker).

Three gates decide what a page without the script shows, and the table names the
one each component uses:

- The dialog opener follows the [`data-js` marker](#the-data-js-marker), like
  the navigation toggle does. That marker only says the inline head script ran.
- The tabs, the carousel and the embed follow a marker of their own that only
  `theme.js` sets, once it has bound that group — `data-js` would show a
  control that does nothing on a page whose module never loaded.
- The lightbox, the tooltip and the dropdown are gated by nothing at all.
  Each degrades to behaviour the platform already provides — a link to the
  file, a bubble shown on hover and focus, a popover the browser opens — so
  there is nothing to hide.

| Component | With the script                                                                                                                          | Without it                                                                                         | Gate in the stylesheet                                                |
|-----------|------------------------------------------------------------------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------|-----------------------------------------------------------------------|
| Tabs      | WAI-ARIA tabs with automatic activation: arrow keys (mirrored right-to-left), Home, End, a roving tab stop; the panels become tab panels | No tab list; every panel shown, stacked, in its own frame under its heading, with no tab semantics | `.theme-tabs[data-theme-tabs-bound]`, set by `theme.js`               |
| Dialog    | `data-theme-dialog-open` calls `showModal()`; a click on the backdrop closes it; focus returns to the opener                             | The opener is hidden and the dialog stays closed                                                   | `:root:not([data-js]) [data-theme-dialog-open]`                       |
| Lightbox  | The gallery's zoom link opens the dialog on the image it names; the arrows and the arrow keys move within it, wrapping                   | The zoom link leads to the file, as it always did; the dialog is closed and renders nothing        | none — the link is the fallback, so nothing is hidden                 |
| Embed     | The play button builds the `iframe` from `data-theme-embed-src` and replaces the placeholder with it                                     | No play button; the poster, the note and the link to the source                                    | `.theme-embed[data-theme-embed-bound] .theme-embed__button`           |
| Carousel  | The previous and next buttons scroll the track by one slide, and `aria-current` marks the indicator of the slide in view                 | The track still scrolls and snaps, and the indicators are still links to the slides; no buttons    | `.theme-carousel[data-theme-carousel-bound] .theme-carousel__control` |
| Tooltip   | Escape sets `data-theme-tooltip-dismissed` until pointer and focus have both left                                                        | Hover and focus still show it; Escape does not hide it                                             | none — the CSS behaviour is the fallback                              |
| Dropdown  | `aria-expanded` on the trigger is mirrored from the panel's own `toggle` event                                                           | The panel still opens, closes and light-dismisses; `aria-expanded` stays stale                     | none — the Popover API is the behaviour, the script reports it        |

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

The selected tab is told apart by more than its colour (WCAG 1.4.1): its frame
is the decorative border, which hardly shows, so it carries a bar along its top
edge, `--theme-tabs-indicator-width` thick — the strong border width, two
pixels — in `--theme-tabs-indicator-color`, the primary accent. Every tab
carries that edge, transparent until it is selected, so selecting a tab paints
it and moves nothing. `Tests/Acceptance/styleguide.spec.ts` holds the bar to
the selected tab. Under forced colours the tab keeps the highlight pair of the
[forced colours](#forced-colours) rule.

**Dialog.** A native `<dialog>` opened with `showModal()`: focus kept inside,
the inert page, Escape and the `::backdrop` are the browser's, and a
`<form method="dialog">` closes it with the pressed button's `value` as its
`returnValue`. Only opening needs the script. Invoker commands would do that
declaratively, and they are still above the
[browser floor](../../DESIGN.md#the-browser-floor) even after it moved to
Firefox 125 for the Popover API — adopting them is a change of its own:

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
anchor positioning is for, and that is still above the
[browser floor](../../DESIGN.md#the-browser-floor) — so a trigger at the inline
end of a narrow viewport is a placement to avoid:

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

## Reading direction

Every box of the library is spaced and bordered with logical properties, and
every alignment is `start` or `end`, so `dir="rtl"` on an element, on a wrapper
or on the document is nearly all that is required and the boxes turn around by
themselves. Two unit tests hold that:
`ComponentLibraryTest::textIsAlignedToTheStartOrTheEndOfTheLine` and
`::noBoxIsPlacedByAPhysicalEdge`, both read off the compiled stylesheet.

**One box is not, and it is known.** `.theme-dropdown__panel` is a popover in
the top layer, so it is positioned against the viewport rather than against its
trigger — without anchor positioning, which is above the
[browser floor](../../DESIGN.md#the-browser-floor), `position: absolute` inside
the component is ignored. It is pinned with
`inset: auto var(--theme-dropdown-panel-gutter) auto auto`, and that second
value is the **right** edge: in a right-to-left document the trigger moves to
the left of the header row and the panel does not follow it. Neither test above
catches it, because both match property names and this one hides in the value
of an `inset` shorthand — see the docblock of `::noBoxIsPlacedByAPhysicalEdge`
for why that is not gated. `.theme-toggletip__bubble` uses the same shorthand
and is fine: `left: 50%` with `translate: -50% 0` centres a box, which is the
same box in either direction.

What does **not** turn around by itself is a glyph. An icon that points
somewhere means something by where it points, and in a right-to-left text that
direction is the other way round. Those are mirrored with `scale: -1 1` under
`:dir(rtl)`, in the file of the component that uses them:

| Icon                             | Where                                       |
|----------------------------------|---------------------------------------------|
| `arrow-up-right-from-square`     | `.theme-link--external .theme-link__marker` |
| `arrow-turn-up`                  | `.theme-footnotes__backlink`                |
| `chevron-left` / `chevron-right` | `.theme-carousel__control .theme-icon`      |
| `chevron-left` / `chevron-right` | `.theme-lightbox__controls .theme-icon`     |
| `arrow-up-right-from-square`     | `.theme-embed__source .theme-icon`          |

Mirroring rather than a second file keeps the set the vendored one — the shape
is still Font Awesome's, drawn the other way round, and `checkIconsBuild`
covers it with the rest. `download`, `envelope`, `phone` and `chevron-down`
point nowhere horizontal and stay as they are, and an `arrow-right` an editor
puts in a button label is the site's content rather than the theme's
furniture.

`:dir()` and not `[dir='rtl']`: the direction of an element is inherited — from
the document, from a wrapper, or resolved from `auto` — and an attribute
selector only matches the element that carries the attribute. `:dir()` is below
the [browser floor](../../DESIGN.md#the-browser-floor) (Firefox 49, Chrome 120,
Safari 16.4), and the `direction` section of the styleguide is screenshotted by
the visual suite, so the claim that it resolves rests on a rendered page rather
than on a version number alone.

The **page chrome is not part of this**. A right-to-left site sets the
direction on the `html` element through its site language, and the header, the
navigation, the breadcrumb and the footer follow it there. The showcase page
`/typography/right-to-left` uses wrappers instead, because a seed set carries
no site configuration; it says so on itself, and `RightToLeftRenderingTest`
holds the document to staying left to right.

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
| Progress    | the fill and the tint of the track                        | opt out: `Canvas`, `CanvasText`, `Highlight` |
| Meter       | the fill and the tint of the track; the three regions     | as progress, every region `Highlight`        |
| Icon tiles  | the fill of the media object and feature tiles            | none: a transparent border is painted solid  |
| Timeline    | the rail, a background                                    | painted `CanvasText`                         |

The `::backdrop` needs no rule: forced colours keep the alpha of its
background, so the scrim stays a translucent canvas over the page. The alert
kinds are told apart by their glyph once the accents are gone, which is what
the alert contract already relies on.
`Tests/Acceptance/styleguide.spec.ts` loads the styleguide with
`forcedColors: 'active'` and asserts the selected tab and the pressed toggle
against the browser's own `Highlight`, the spinner's gap, and the tooltip,
alert and dialog edges.

## Breakpoints

There are two, declared in `abstracts/_breakpoints.scss`. `bp.$md`, `48rem`
(768px), is the breakpoint of the library: the point at which the main
navigation's two levels stop fitting a single row — every other component that
stacks (`theme-hero--media`, `theme-teaser`, `theme-page__body`) was checked
against it and none wanted a different one. `bp.$lg`, `64rem` (1024px), is used
by the [site header](#layout) alone, where it decides whether the title or the
menu gives way when the row gets tight; flex layout cannot make that choice by
itself.

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

| Test                                                   | Guards                                                                                                                                                                                                                             |
|--------------------------------------------------------|------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| `everyComponentIsPartOfTheBundle`                      | Every selector in the [component reference](#component-reference) is actually compiled into `theme.css` — dropping a `@use` from `theme.scss` is otherwise invisible until someone looks at a page.                                |
| `collapsingTheMainNavigationRequiresTheScriptMarker`   | The `[data-js]` gate on the navigation collapse still holds — see [the `data-js` marker](#the-data-js-marker).                                                                                                                     |
| `theContentElementOutlineSwitchesOffCompletely`        | `[data-theme-content-outline='off']` still removes the label together with the outline — see [the content-element outline](#the-content-element-outline).                                                                          |
| `anElementWithoutPaddingKeepsItsContentClearOfTheChip` | `--frame-none` starts its content below the CType chip by `--theme-content-element-chip-clearance`, and the global switch and `--plain`, which remove the chip, set the clearance to `0`.                                          |
| `everyPaletteHasASwatchWithItsOwnColours`              | Every palette has a `.theme-swatch--*` modifier, and its two literals equal the palette's primary and secondary pair — see [Appearance switching](appearance-switching.md#palette-swatches-carry-literal-colours).                 |
| `tabsShowEveryPanelUntilTheScriptHasBoundThem`         | The tab list is hidden and the panel headings shown until the group carries `data-theme-tabs-bound`, and nothing about the tabs is gated on `[data-js]` — see [Components that need the script](#components-that-need-the-script). |
| `aDialogOpenerIsHiddenWithoutTheScriptMarker`          | `:root:not([data-js]) [data-theme-dialog-open]` still hides every opener nothing could operate.                                                                                                                                    |
| `textIsAlignedToTheStartOrTheEndOfTheLine`             | No `text-align: left` or `right` is compiled — a physical alignment puts the text of a right-to-left page against the wrong edge, and the element baseline carried two until they were found.                                      |
| `noBoxIsPlacedByAPhysicalEdge`                         | No `margin-`, `padding-`, `border-`, `left`/`right` offset, `float` or `clear` names a physical edge — the sibling of the rule above, for where a box sits rather than where its text sits.                                        |
| `aDirectionAwareIconIsMirroredInARightToLeftText`      | Every glyph that points somewhere carries a `:dir(rtl)` rule mirroring it — see [Reading direction](#reading-direction).                                                                                                           |
| `aTitleOnAnyHeadingLevelKeepsItsOwnCase`               | `.theme-hero__title`, `.theme-teaser__title`, `.theme-feature__title` and `.theme-steps__title` state `text-transform: none`, so a title rendered as `h5` does not turn into capitals.                                             |
| `noComponentReferencesAnUndeclaredToken`               | Every `var(--theme-…)` referenced anywhere under `Resources/Private/Scss/` is declared somewhere in the same tree — walked on the sources, not the compiled file, so the offending name is still readable.                         |
| `aListItemHoldingAFloatKeepsItsMarker`                 | A list item holding a floated figure or gallery is `flow-root list-item`, not `flow-root`, which would drop its marker.                                                                                                            |
| `aControlDrawsItsBoundaryInTheStrongBorderColour`      | The text input, the input group addon, the switch track and the tracks of progress and meter default to `--theme-color-border-strong`, with its light value as the fallback literal — see [Forms](#forms).                         |
| `aHoveredTextInputChangesItsBorder`                    | The hover border of `.theme-input` differs from its resting one, now that the resting one is the strong border.                                                                                                                    |
| `everyTableClassAnEditorCanPickIsStyled`               | Every `table_class` an editor can pick — the core's `striped` and `bordered` and the `addItems` of `Configuration/PageTsConfig/TCEFORM/TableClass.tsconfig` — has a compiled `.theme-table--<value>` rule.                         |
| `aBlockKeepsItsDistanceToWhatFollows`                  | The list, the description list, the code block, the figure, the button group and the table wrapper each end on `margin: 0 0 var(--theme-space-4)` in their base rule, so the next component never touches them.                    |

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
