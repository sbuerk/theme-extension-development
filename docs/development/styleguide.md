# Styleguide page

One page that renders **every component of the library from its own Fluid**,
independently of any content element, any record and any TypoScript content
object. [Component library](component-library.md) is the written contract —
which classes exist, what markup each expects; this page is that contract
rendered, in the browser, in whatever appearance and palette the reader has
selected.

The implementation is
[`Resources/Private/Templates/Page/Styleguide.html`](../../Resources/Private/Templates/Page/Styleguide.html),
the seven partials below
[`Resources/Private/Partials/Styleguide/`](../../Resources/Private/Partials/Styleguide),
the page furniture in
[`Resources/Private/Scss/layout/_styleguide.scss`](../../Resources/Private/Scss/layout/_styleguide.scss)
and the guard in
[`Tests/Functional/StyleguideRenderingTest.php`](../../Tests/Functional/StyleguideRenderingTest.php).

## Where the page is

The seeded demo tree carries it as page uid 9,
`Configuration/Seeds/Demo.yaml`, slug `/styleguide`, backend layout
`styleguide`. After `vendor/bin/typo3 theme:seed` it is at
`https://<instance>/styleguide` — see [Seeding](seeding.md).

It is **`nav_hide`, not `hidden`**. A hidden page answers 404 in the frontend
and is reachable only through a backend preview link carrying a valid hash,
which defeats the point of seeding a page whose entire purpose is to be opened
and looked at. `nav_hide` keeps it reachable by URL and out of every menu the
theme renders, which is what "not part of the site" actually meant here.

The page is not privileged in any other way: it is a normal `doktype 1` page
with a backend layout, so it resolves its template through the same mechanism
as every other page — see
[Page rendering](../architecture/page-rendering.md).

## Why it renders no content elements

This is the part that looks like a shortcut and is not. The brief asked for the
styleguide to render Fluid directly *"without taking content elements on that
page into account"*, and two files implement that between them.

**The backend layout offers no usable column.**
`Configuration/PageTsConfig/BackendLayouts/Styleguide.tsconfig` declares a
single column with `colPos = 999`. The `colPos` values the theme's TypoScript
actually reads are `0`, `1`, `2` and `10`–`14`; nothing under `lib.content.*`
reads `999`, so an element an editor drops there is stored and never rendered —
inert rather than broken.

The obvious alternative, declaring no column at all, does not work, and the
reasoning is worth repeating because it is not visible from the outside:

- `PageTsBackendLayoutDataProvider::generateBackendLayoutFromTsConfig()`
  registers a layout only if `config.backend_layout.` parses to a **non-empty**
  array. An empty block would remove the layout from the backend selector
  entirely — worse than a layout with one unused column.
- A `rows` key that is present but empty yields a grid with zero rows.
  `Partials/PageLayout/Grid.fluid.html` then renders a `<table>` with no rows,
  which means no "new content element" affordance anywhere and a page module
  that cannot be used for this layout at all.

So the column exists because the page module needs *something* to draw, and it
is `999` because that is a value nothing renders. Its label is a distinct
`backendLayout.colPos.unused.name` rather than a reuse of `main`, so the page
module does not suggest it behaves like the main column of the other layouts.

**The template contains no `f:cObject`.** Not in
`Templates/Page/Styleguide.html`, not in any of the seven partials — not even
for `main`. A single one would quietly make this a content page again, and the
difference would surface only the first time somebody happened to place an
element on it. The absence is asserted, not reviewed
([below](#what-the-tests-guard)).

## Seven sections, seven partials

`Templates/Page/Styleguide.html` renders a heading, an intro, a section index
and then one `f:render partial` per section, in this order:

| Partial           | Section `id` | Demonstrates                                                                                                                                      |
|-------------------|--------------|---------------------------------------------------------------------------------------------------------------------------------------------------|
| `Tokens.html`     | `tokens`     | All 27 colour tokens as swatches, the type scale, weight and family, spacing, radius, shadow, focus ring.                                         |
| `Typography.html` | `typography` | The element baseline of `base/_elements.scss` — headings, running text, inline elements, lists, quotes, `pre`/`code`, `hr` — plus `.theme-table`. |
| `Buttons.html`    | `buttons`    | `.theme-button` with every modifier and state its SCSS defines, `.theme-button-group`, `.theme-badge` on both axes.                               |
| `Boxes.html`      | `boxes`      | `.theme-card`, `.theme-teaser`, `.theme-hero`, `.theme-quote`, `.theme-alert`, `.theme-accordion`, `.theme-author`.                               |
| `Forms.html`      | `forms`      | The whole `forms/` contract, selector by selector, including the validation states.                                                               |
| `Navigation.html` | `navigation` | `.theme-nav-main`, `.theme-nav-sub`, `.theme-breadcrumb`, `.theme-pagination`, `.theme-content-menu`.                                             |
| `Media.html`      | `media`      | `.theme-gallery` in one, two and three columns, and `.theme-content-element` with its outline switch.                                             |

Each partial is a single `<section class="theme-styleguide__section" id="…">`
and nothing else — no `f:layout`, no `f:section`, no wrapper. The page template
is what places them, and the index at the top is built from the same seven ids.

The split follows the same rule as the rest of the theme: a site package that
wants its own forms section overrides **one file**,
`Partials/Styleguide/Forms.html`, and keeps the other six. Overriding the page
template instead would mean re-stating all seven renders and the index to
change one section.

The index itself reuses `.theme-content-menu` rather than introducing an index
component. It is exactly what that component is — a flat list of links — so an
own component would have been a second stylesheet rule doing the same job, and
rendering it here demonstrates the component as a side effect.

Every specimen is copied from the markup contract in the component's own SCSS
header comment, which is the authoritative source; where a header comment and
the prose in [Component library](component-library.md) disagreed, the SCSS won
and the disagreement was fixed. Three specimens depart from their header
comment deliberately, each with the reason at the specimen: heading levels are
lowered so the page keeps one document outline instead of restarting it per
component, the alert's `role` differs per severity, and images are inline SVG
data URIs because a specimen has no FAL record to reference and the theme has
to render with no network.

### The page furniture is not a card

`layout/_styleguide.scss` adds what no component provides: section rhythm, a
frame that makes one example legibly one example, and the swatch grid.
`.theme-card` was the obvious candidate for the frame and was rejected — a card
is a content component with its own visual decisions, and borrowing it here
would mean every change to the card reflows the styleguide.

It is registered like any other file: the `@use` in `theme.scss`, a row in the
component reference table of [Component library](component-library.md), and
`-s buildCss` with the compiled `Resources/Public/Css/theme.css` committed. See
[Frontend assets](frontend-assets.md).

## Specimen copy is not translated

Section headings and specimen text are **literal English in the partials**. The
only translated string on the page is its `<h1>`, which keeps the existing
`theme.styleguideHeading` label.

That is a decision, not an omission. Specimen copy is not product text: it
exists to be *set in a typeface*, and a paragraph demonstrating running text
has nothing in it to localise. Routing the roughly 150 strings on this page
through `locallang.xlf` would fill that file with entries no integrator will
ever translate, and — the part that actually matters — would make the specimens
unreadable in the source, which is the one place they have to be readable. A
`<f:translate key="…" />` where a sentence should be tells a developer nothing
about what the component does with it.

Section headings name CSS components ("Buttons", "Forms"). Those are developer
terms and are not translated anywhere else in this repository either.

The now-unused `theme.styleguideNotImplemented` label was removed together with
the placeholder it belonged to.

## The page is a test, not only a reference

The token section is the reason this page earns its place: the tokens are the
theme's public API for a site package, and there is nowhere else to see them
*resolved*. `DESIGN.md` lists their values; this page shows what they actually
render as, under the palette and appearance currently selected.

That makes the page a **live test of the two switchers**. Flip the appearance
control or the palette control in the header, and every swatch, every specimen
and every border on the page has to move together. One that does not is a
colour declared outside `light-dark()` or outside the palette selector — see
[Appearance switching](appearance-switching.md) and
[`DESIGN.md`](../../DESIGN.md).

Swatches carry their colour as `style="background-color: var(--theme-color-…)"`.
That is the one place an inline style is right here: the value on show *is* the
token, and a class per token would mean a stylesheet rule per token carrying
nothing the attribute does not already carry. Every swatch is drawn with a
`--theme-color-border-strong` border, because a swatch for
`--theme-color-background` is exactly the colour of the page behind it and
would otherwise render as nothing.

Two token groups are deliberately absent: the motion tokens (a duration and an
easing curve) and the stacking tokens, neither of which has a visual form on a
static page.

## What the tests guard

`Tests/Functional/StyleguideRenderingTest` renders `/styleguide` through a
frontend sub-request against `Fixtures/Database/StyleguidePage.csv`:

| Test                                                        | Guards                                                                                        |
|-------------------------------------------------------------|-----------------------------------------------------------------------------------------------|
| `everyComponentOfTheLibraryIsShownOnTheStyleguide`          | Every component of the library appears on the page. Data provider, one case per component.    |
| `everySectionOfTheStyleguideRendersAndIsLinkedFromTheIndex` | Each of the seven ids renders **and** is linked from the index — no dead anchor.              |
| `contentPlacedOnTheStyleguidePageIsNotRendered`             | Neither the element in `colPos 999` nor the one in `colPos 0` reaches the frontend.           |
| `theFormsSectionShowsTheInvalidState`                       | `.theme-field--invalid`, `aria-invalid="true"`, `.theme-field__error`, `.theme-form-summary`. |
| `everyColourTokenHasASwatch`                                | Every `--theme-color-*` token declared in `abstracts/_tokens.scss` has a swatch.              |
| `noSpecimenLandmarkSharesItsNameWithThePageChrome`          | No two `<nav>` landmarks on the page share an `aria-label`.                                   |
| `everyIdOnThePageIsUnique`                                  | No `id` appears twice.                                                                        |

Two of them derive their expectations from the repository rather than from a
list written next to the assertion, and that is the point of both:

- **The component sweep reuses
  `ComponentLibraryTest::shippedComponents()`.** Restating the component list in
  the functional test would produce two lists of the same thing, and they drift
  silently — a component missing from the styleguide looks exactly like a
  styleguide that is simply shorter than it used to be. Reusing the provider is
  the link between the two, so a component added to the library without a
  specimen fails here. Five entries are satisfied by the page frame rather than
  by a specimen — `.theme-page`, `.theme-site-header`, `.theme-site-footer`,
  `.theme-skip-link`, `.theme-appearance-switcher` — which is correct: they
  *are* demonstrated on the page, as its own chrome.
- **The swatch check reads the tokens out of `abstracts/_tokens.scss`**, strips
  comments (the file names tokens while explaining its decisions) and asserts a
  swatch for each. A token added without a swatch is otherwise invisible, and
  the token section is the only place the palette can be looked at at all.

The last two tests exist because the specimens **duplicate real page chrome**.
`.theme-nav-main`, `.theme-nav-sub` and `.theme-breadcrumb` are on this page
twice: once as the site's own navigation, once as a specimen. Two navigation
landmarks with the same accessible name is a defect that no visual check can
see, so every specimen `<nav>` says "specimen" in its `aria-label` and the test
asserts no label repeats. Ids are the same class of problem in a worse form:
`Partials/Navigation/Main.html` puts `id="nav-main"` on the real top-level list
because the header toggle's `aria-controls` points at it, so a specimen reusing
that id would silently re-target the header's toggle at the specimen list. The
navigation specimen uses `theme-styleguide-nav-main`, the form specimens prefix
every id `sg-form-`, and the uniqueness test is what keeps it that way — a
repeated id breaks every `for`, `aria-describedby` and `aria-controls`
reference pointing at it while the page still looks entirely correct.

## Relationship to the seeded `/elements` pages

The demo tree also seeds `/elements/core`, `/elements/menu` and
`/elements/theme`, which show the same components rendered from **real content
records** through the real content element rendering. The two are complementary
and neither replaces the other:

| Path                              | Renders from                                      | Proves                                                                        |
|-----------------------------------|---------------------------------------------------|-------------------------------------------------------------------------------|
| `/styleguide`                     | Fluid partials, literal markup                    | The **library** — that the component's contract produces the intended result. |
| `/elements/core` and its siblings | Content records, `lib.content.*`, data processors | The **wiring** — that a `CType` reaches that markup with its data in place.   |

A component can break in one without the other noticing. A `CType` template
that stops emitting `.theme-teaser__title` renders wrongly on `/elements` while
the styleguide stays green, because the styleguide never asks the template
anything. Conversely a stylesheet change that breaks a modifier no content
element happens to use — most of `forms/`, half the button modifiers — is
invisible on `/elements` and obvious here. The forms section is the clearest
case: no content element renders a form at all, so without this page
`forms/_validation.scss` would ship never having been rendered.

## Three defects the page turned up

Building the page surfaced three small defects in code it only had to *use*,
all fixed in the same change. They are recorded where they belong; noted here
because "a second instance of a component on one page" is the condition that
exposed all three, and the styleguide is now the place that condition exists.

- **Only the first navigation toggle was bound.**
  `Resources/Public/JavaScript/theme.js` looked the toggle up with
  `querySelector`, while the collapse rule in `_nav-main.scss` matches *every*
  `.theme-nav-main` that has no expanded toggle. A second navigation on a page
  — this page's specimen, or a site package repeating the menu in its footer —
  would have been folded shut below the breakpoint by a control that was never
  wired up, with nothing able to open it again. Every toggle is now bound, each
  scoped to its own nav. → [Appearance switching](appearance-switching.md)
- **`.theme-form-summary--error` matched no rule.** It was in the markup
  contract and in the documentation but had never been declared; the base rule
  already carries the danger palette, so it *looked* right. A class in a
  published contract that matches nothing is indistinguishable from one dropped
  by accident, so it is now declared explicitly and symmetric with `--success`.
  → [Component library](component-library.md)
- **The alert contract showed the wrong `role`.** `_alert.scss` illustrated
  `role="status"` on a `--warning` alert, which invites copying an assertive
  severity as a polite one. `--info` and `--success` take `status`; `--warning`
  and `--danger` take `alert`. → [Component library](component-library.md)

## See also

- [Component library](component-library.md) — the markup contract each specimen
  is copied from.
- [Frontend assets](frontend-assets.md) — the SCSS build and the
  `checkCssBuild` gate that holds the compiled stylesheet to it.
- [Appearance switching](appearance-switching.md) — the two switchers this page
  is a live test of.
- [Seeding](seeding.md) — the demo tree the page is part of.
- [Page rendering](../architecture/page-rendering.md) — backend layouts and
  template resolution.
- [`DESIGN.md`](../../DESIGN.md) — the token specification the token section
  renders.
