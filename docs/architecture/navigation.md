# Navigation

Main menu, left-hand sub navigation, breadcrumb. All three are the same core
processor, configured three different ways, in
[`Configuration/TypoScript/Navigation.typoscript`](../../Configuration/TypoScript/Navigation.typoscript):

| Variable     | Source                                    | Levels |
|--------------|-------------------------------------------|--------|
| `mainMenu`   | `special = directory`, from the site root | 2      |
| `subMenu`    | the current page's **section**, see below | 2      |
| `breadcrumb` | `special = rootline`                      | —      |

All three are `TYPO3\CMS\Frontend\DataProcessing\MenuProcessor`, stock core,
unchanged on v13.4 and v14.3 — there is no changelog entry for it in either
series. No custom PHP. This page is the detail behind the navigation partials;
[Page rendering](page-rendering.md) covers the Fluid structure they are
rendered into, and [Component library](../development/component-library.md)
covers the markup contract and CSS each one has to satisfy — read that page
first if the question is "what class does this need", not "why does this
TypoScript say what it says".

## The sub navigation resolves a fixed rootline position, not the current page

`subMenu` uses `special = directory` with
`special.value.data = leveluid:1` rather than `entryLevel`. The two answer
different questions, and only one of them is the one the sidebar needs:

- **`entryLevel`** is relative to the current page's *depth*. The same value
  resolves to a *different* ancestor depending on how deep the current page
  is — it answers "how far up from here", not "which page".
- **`leveluid:1`** indexes the current page's local rootline, root first
  (`RootlineUtility::generateRootlineCache()`,
  `.Build/vendor/typo3/cms-core/Classes/Utility/RootlineUtility.php:444-454`).
  Index `0` is the site root, index `1` is the first page below it — on
  **every** page, whatever its depth, because the rootline grows underneath
  that entry rather than shifting it.

`special = directory` without a value defaults to the *current* page's own
uid (`AbstractMenuContentObject::start()`,
`.Build/vendor/typo3/cms-frontend/Classes/ContentObject/Menu/AbstractMenuContentObject.php:296-299`).
`leveluid:1` overrides that default with the section root instead:

| Page depth                     | Local rootline                         | `leveluid:1` resolves to        |
|--------------------------------|----------------------------------------|---------------------------------|
| First level (the section root) | `[0 => root, 1 => this page]`          | this page's own uid             |
| Second level                   | `[0 => root, 1 => section, 2 => this]` | the section root, two levels up |
| Third level                    | one entry deeper again                 | the section root, unchanged     |

The section root does not move as the current page gets deeper — only the
rootline grows underneath it. That is the entire point: a sidebar built from
the current page's own children looks perfectly correct on a first-level
section landing page, where the section root *is* the current page, and empties
out on every page below it, exactly where a reader needs the navigation most.
This failure is invisible at level one, which is why
`Tests/Functional/NavigationRenderingTest.php` and its fixture
(`Tests/Functional/Fixtures/Database/NavigationPageTree.csv`) go three levels
deep and assert the same section content at `/first`, `/first/a` and
`/first/a/deep` — a fixture that stopped at two levels could pass against
either the `entryLevel` version or the current-page-children version and never
notice.

On the site root itself the local rootline has only index `0`, so `leveluid:1`
resolves to nothing and `special = directory` falls back to the current page's
own children — the site root's top-level pages. Harmless in practice: the sub
navigation is rendered only on the `content_sidebar` backend layout, and
nothing requires the site root to use it.

## A branch of the sub navigation folds, and no script is involved

An item of `subMenu` that has children is rendered as a native
`<details>`/`<summary>` pair beside its link, so a reader can fold that branch
away. The element carries the state, the keyboard handling and the announced
expanded/collapsed semantics; the theme adds a chevron and a hidden name and
nothing else. Nothing is gated behind `data-js`, because nothing about folding
a branch waits for a script — the tree works on a page whose JavaScript never
arrived, which is the reason it is built on `details` rather than on a button
and a class.

**The branch holding the current page is `open`, every other one starts
folded.** `MenuProcessor` marks it `active` (an ancestor) or `current` (the
page itself). The attribute is assembled as a string and interpolated rather
than written as `open="{…}"`, because `open` is true whatever its value —
`open=""` included — so a conditional *value* would leave every branch open and
a test looking only for the open one would pass.

**The branch link is a sibling of the summary, not a child of it.** The first
version had the link inside the summary, which named the disclosure control by
the branch's own title and needed no extra text at all. axe refuses it:
`nested-interactive`, serious, on every appearance and palette of the
styleguide fixture, because a summary is a widget and a focusable descendant
inside one is a control a reader cannot predict. The toggle is therefore named
by a visually hidden span — subtree text is the naming method a `<summary>`
has — and it names the branch rather than the action: "Pages below Analytics".

The layout of those two controls is in
[Component library § Navigation](../development/component-library.md#navigation),
including why a grid on the `<details>` does not arrange them.

## Three independent processors, three independent overrides

Each navigation is its own numbered key under `page.10.dataProcessing`
(`10`/`20`/`30` for main/sub/breadcrumb), not one processor switched by a
variable. A site package can drop exactly one:

```typoscript
page.10.dataProcessing.20 >
```

removes only `subMenu`; `.10` and `.30` are untouched. A single shared
definition would not offer that — removing one navigation would mean editing a
block the other two also depend on.

## Placement follows the backend layout

| Navigation | Rendered from                   | In layouts                   |
|------------|---------------------------------|------------------------------|
| main       | `Partials/Page/Header.html`     | all                          |
| breadcrumb | `Partials/Page/Breadcrumb.html` | `content`, `content_sidebar` |
| sub        | `Partials/Page/Sidebar.html`    | `content_sidebar` only       |

The sub navigation is rendered by `Partials/Navigation/Sub.html`, placed in the
`aside` above the `sidebar` content slot. `content_sidebar` is the one backend
layout with a left column (see [Page rendering § Column
layout](page-rendering.md#column-layout)), so it is the layout that gets the
left navigation — choosing that backend layout in the page module is how an
editor asks for it, not a template flag or a TypoScript condition.

The breadcrumb is rendered from `Templates/Page/Content.html` and
`Templates/Page/ContentSidebar.html`, ahead of `Partials/Page/Stage.html` in
each. It is **not** rendered on `start` (no trail is worth showing on a start
page) or on `default` (the bare layout the contract excludes outright, and the
one template that already renders `{pageTitle}` itself — a trail ending in the
same title immediately above it would be redundant with no stage to separate
the two).

All three partials guard their own emptiness — no `mainMenu`/`subMenu`/`breadcrumb`
means no `<nav>` element at all, never an empty one — so every caller renders
them unconditionally and lets the partial decide.

## Accessibility

- **Every `<nav>` carries a translated `aria-label`** (`theme.navMainLabel`,
  `theme.navSubLabel`, `theme.breadcrumbLabel` in `locallang.xlf`). Three
  navigation landmarks on one page are indistinguishable in a screen reader's
  landmark list without one.
- **The current page carries `aria-current="page"`**, and
  `components/_nav-main.scss` / `_nav-sub.scss` style `[aria-current='page']`
  directly rather than a modifier class kept in sync with it by the template.
  The attribute a screen reader announces and the attribute the CSS paints
  from are the same attribute, so the visual and announced states cannot
  disagree. (The main navigation additionally carries a `--active` modifier on
  the top-level ancestor of the current page — see [Component library §
  Navigation](../development/component-library.md#navigation) — but that is
  independent of `aria-current`, not a replacement for it.)
- **The breadcrumb's last item is not a link.** It carries `aria-current="page"`
  on the `<li>` itself, not on an anchor: the destination of a breadcrumb trail
  is not somewhere it still points to.
- **The breadcrumb separator is generated content**, a `::before` in
  `_breadcrumb.scss`, never a character in the markup. Generated content is not
  part of an element's accessible name in either the ARIA or the HTML-AAM
  mapping, so it is not announced — and no `aria-hidden` is needed, because
  there is nothing in the accessibility tree to hide.

## The menu toggle ships without its script

`Partials/Navigation/Main.html` renders a real `<button aria-expanded="false"
aria-controls="nav-main">`, paired with `id="nav-main"` on the list it
controls. That pairing is everything this step ships for the toggle: the
script that flips `aria-expanded` and the `data-js` marker that
`_nav-main.scss` gates collapsing behind both come with the theme script of
[Appearance switching](../development/appearance-switching.md), not with
navigation. Without that script the button is inert, and
`_nav-main.scss` hides it entirely in the absence of `data-js` — the menu is
simply always expanded, which is the intended, working state rather than a
degraded one. See [Component library § The `data-js`
marker](../development/component-library.md#the-data-js-marker) for the CSS
side of that gate and the test that pins it down.

## The language menu, and the honest unavailable state

`page.10.dataProcessing.40` is core `LanguageMenuProcessor`, rendered by
`Partials/Navigation/Language.html` inside the header dropdown. The processor
takes four keys only — `if`, `languages`, `as` and `addQueryString` — and
anything else throws (`1522959188`); it sets `special = language` itself.

**A one-language site gets no dropdown, and that takes a count to arrange.**
The processor defaults `special.value` to `auto` and returns early only when
the site has no language at all, so one language yields a menu of exactly one
item — the language already being read. `Partials/Page/Header.html` therefore
guards on `{languageMenu -> f:count()} > 1` rather than on the menu being
non-empty: a truthiness test would render a dropdown whose single entry offers
the reader nothing. The partial itself keeps the ordinary empty guard, so the
decision sits with the caller that has the header row to spend.

**The template reads `active`, never `current`.** `current` comes from the item
states `CUR`/`CURIFSUB`, and a language menu never emits either — it marks the
language being viewed `ACT`. `current` is therefore always `0` in a language
menu, and a template built on it marks nothing at all. The entry carries
`aria-current="true"`, not `"page"`: it is this page in the language already
being read, not a different page.

**How that flag is produced is not the same on the two cores**, which is worth
knowing before reading either implementation as if it were the other:

| Core  | How `active`, `current` and `available` are produced                                                                                                                                        |
|-------|---------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| v14.3 | Computed in PHP from the item's `ITEM_STATE`: `ACT`/`ACTIFSUB`/`USERDEF2` → active, `CUR`/`CURIFSUB` → current, `USERDEF1`/`USERDEF2` → unavailable.                                        |
| v13.4 | Never computed in PHP. Each item is a JSON string assembled by TypoScript, with `cObject` slots `91`, `92` and `93` carrying the three flags and a link placeholder substituted afterwards. |

On v13 the state configuration sets the values: `ACT` writes `1` into slot
`91`, `CUR` writes `1` into slot `92`, and `USERDEF1`/`USERDEF2` write `0` into
slot `93` — with `USERDEF2` derived from `ACT`, so it keeps slot `91`. The
resulting key names and their meanings are identical on both cores, and neither
sets `special.normalWhenNoLanguage`. The template therefore depends on the
contract rather than on the mechanism, which is why one template serves both;
`LanguageMenuRenderingTest` is green on v13.4 and v14.3 alike.

It also uses only the keys `LanguageMenuProcessor` has documented since TYPO3
9.3 — `languageId`, `navigationTitle`, `hreflang`, `link`, `active`,
`available`. TYPO3 v14 additionally puts `data`, `target`, `spacer` and
`hasSubpages` on every item; those are not part of the contract on v13.4 and
are deliberately unused.

### An untranslated language is shown, not hidden

A language the current page has no translation for comes back with
`available = 0`, and the partial renders it as plain text carrying
`aria-disabled="true"` rather than as a link. Hiding it would tell a reader the
site has fewer languages than it has; linking it would promise a translation
that does not exist.

**`fallbackType` cannot argue that flag away.** The state is decided by whether
`getPageOverlay()` returned a record carrying `_LOCALIZED_UID`, and
`LanguageMenuProcessor` never sets `special.normalWhenNoLanguage`, so for any
language other than the default an empty overlay is `USERDEF1` — unavailable.
`fallbacks: [0]` does not help either: `PageRepository::getPageOverlaysForLanguage()`
filters the default language out of the overlay chain with `array_filter()`, so
the chain is empty and there is no overlay to find. Only a **non-zero** fallback
language that actually has the page translated makes an entry available.

That is exactly the state the demo instances are in. Both sites declare a second
language, German, in `instance-core-*/config/sites/*/config.yaml`, and the
showcase is seeded in English only — `sbuerk/data-factory` declares records
through `self`, `children` and `entities` and cannot express a translation at
all. So the menu shows German as unavailable on every page of the demo, which is
the truth about that tree rather than a translation faked for a screenshot.
Adding the language needs no database record: `sys_language` was removed in
TYPO3 v12, and a site language is the site configuration alone.

### The landmark and the trigger are named differently, on purpose

The dropdown's button is named "Language" and the `<nav>` inside it
"Languages" (`theme.languageMenuLabel` and `theme.languageMenuListLabel`).
Both once used the first key, so a screen reader announced the button as
"Language" and then the region it opened as "Language" again — the same word
twice for two different things. The trigger names the control a reader
operates; the landmark names the list it contains. The rule that every `<nav>`
carries a translated `aria-label` is unchanged; what changed is that the label
is no longer a copy of the name of whatever opens it.

The table of contents solves the same problem the other way: it has a visible
heading, so its landmark takes `aria-labelledby` pointing at that heading
rather than repeating the words in an `aria-label` — see
[Content elements](content-elements.md). Where a landmark has visible text of
its own, that text names it; where it has none, it gets a label that does not
duplicate its trigger.

### The header dropdown is a `details`, and was a popover

`Partials/Page/Dropdown.html` is a `<details>` whose `<summary>` is the trigger
and whose panel is the next element. The browser opens and closes it and
announces the expanded state, with no script of the theme's own. That is why
the dropdown, unlike the display settings, is rendered unconditionally rather
than hidden until `data-js`: nothing about opening it depends on a script having
run. There is no `aria-expanded` in the markup — a `summary` carries the state
of its `details`, and an authored copy would be a second one for a script to
keep in step.

It **was** a popover — a trigger carrying `popovertarget` over a panel carrying
`popover` — and that panel opened over the trigger that opened it, on every page
with a header. A popover is in the top layer, and the containing block of a top
layer element is the viewport, whatever it is nested in: `position: absolute`
inside the component does not reach the component. So the panel could only be
pinned to a viewport coordinate, and the one it wants — the bottom edge of the
header row — is not a coordinate CSS can name, because the header's height
depends on its content (the title wraps, the menu wraps onto a second row).
Pinning a box to another box is what CSS anchor positioning is for, and that is
above the [browser floor](../../DESIGN.md#the-browser-floor). Out of the top
layer the panel is an ordinary absolutely positioned child of the component,
in logical properties, at any header height and in either reading direction —
the way `.theme-settings__panel` always was. The reasoning is written out in
`components/_dropdown.scss`.

**In the header row neither panel is placed against its own component.** The
row is as tall as its tallest child and the main navigation wraps onto a second
line inside it from `bp.$md` up, so a panel that starts under its own 44 pixel
trigger starts inside the row and covers what wrapped. Both are anchored on
`.theme-site-header` instead and drop under the whole row, with both offsets
derived — `calc(100% + <the component's own gap>)` for the block axis, and one
shared expression for the inline axis that reads the end of the content
container off its own `max-width` and padding. `layout/_site-header.scss`
carries the rule and the measurements; `ComponentLibraryTest` holds the two to
the same edge, and the acceptance suite opens both and measures where they land.

What the change costs is the **light dismiss** the Popover API gave for free.
`theme.js` writes it back: Escape, a click outside, and focus leaving the
control — the same three rules the display settings carry, the last of them
WCAG 2.2 2.4.11. Without a script the dropdown still opens and closes from its
own summary; what a reader loses there is the dismissal, not the control.

The Popover API still holds the floor at Firefox 125 — the toggletip is built
on it, and a toggletip has no placement to get wrong.

The language menu inside it is a plain `<nav>` that renders anywhere; the
dropdown only decides where it sits in the header row.

## The table of contents

`page.10.variables.tableOfContents` is an `HMENU` with `sectionIndex = 1`,
rendered by `Partials/Navigation/TableOfContents.html` into the aside of the
`content_sidebar` layout — the one layout with a column for it, the same
placement rule the sub navigation follows.

**It cannot be a `MenuProcessor`.** `sectionIndex` is not one of that
processor's `allowedConfigurationKeys`, and passing it throws `1478806566`. The
section index is an HMENU option read off the configuration of the menu level in
`AbstractMenuContentObject::prepareMenuItems()`, so the menu has to be built as
one and assigned as a template variable — there is no processor to hand the
result over.

Two options decide what a reader gets:

| Option                   | Value    | Why                                                                                                                                     |
|--------------------------|----------|-----------------------------------------------------------------------------------------------------------------------------------------|
| `sectionIndex.useColPos` | `-1`     | Lifts the column restriction. The default is `0`, so an element in the sidebar or the stage would be missing from its own page's index. |
| `sectionIndex.type`      | `header` | Drops an element whose header is empty or whose `header_layout` is `100`. The default type keeps both, as entries with no text.         |

**The order is `sorting`, across every column at once.** `sectionIndex()` orders
by the menu's `alternativeSortingField`, which defaults to `sorting`, and the
column is a `WHERE` rather than part of the `ORDER BY` — so with `useColPos = -1`
the columns are *interleaved* by `sorting`, not listed one after the other. An
element of the sidebar can therefore sit between two elements of the main
column, which is worth knowing before someone reads it as a bug.

The anchor needs
nothing: `sectionIndex()` puts the content uid in `sectionIndex_uid`, `link()`
copies it into `conf.section`, and `PageLinkBuilder::calculateUrlFragment()`
prefixes a numeric fragment with `c` — so the href is `#c<uid>`, exactly the id
`Layouts/ContentElement.html` writes. **Changing that id breaks every entry.**

The list is `.theme-content-menu`, not a component of its own: a flat list of
links is what that component is, and `Templates/Page/Styleguide.html` already
records the same reuse for its own index.

## What the tests cover

`Tests/Functional/NavigationRenderingTest.php`, against the three-level
fixture in `NavigationPageTree.csv`:

| Test                                                     | Guards                                                                         |
|----------------------------------------------------------|--------------------------------------------------------------------------------|
| `theMainMenuListsTheTopLevelOfTheSite`                   | The top level renders, from the site root regardless of the current page.      |
| `theMainMenuLeavesOutAPageHiddenFromNavigation`          | `nav_hide` is honoured.                                                        |
| `theMainMenuCarriesASecondLevel`                         | `expandAll = 1` puts the second level in the markup unconditionally.           |
| `theSubNavigationShowsTheSectionOnEveryLevelOfIt`        | The `leveluid:1` fix, asserted at all three page depths at once.               |
| `aBranchOfTheSubNavigationIsADetailsElement`             | A branch is `details`/`summary` — no button, no `aria-expanded`, no `data-js`. |
| `onlyTheBranchHoldingTheCurrentPageIsOpen`               | Exactly one branch is `open`, and it is the one the reader is in.              |
| `aBranchLinksToItsOwnPageBesideItsToggle`                | The branch link and the toggle are siblings, neither inside the other.         |
| `theBranchToggleIsNamedAfterItsBranch`                   | The toggle's name is hidden text naming the branch, not an `aria-label`.       |
| `aLeafOfTheSubNavigationIsAPlainListItem`                | An item without children is wrapped in nothing.                                |
| `theCurrentPageIsMarkedForAssistiveTechnology`           | `aria-current="page"` is present on the current page's link.                   |
| `theBreadcrumbShowsTheTrailAndDoesNotLinkTheCurrentPage` | The trail order, and that the last item is not an anchor.                      |
| `everyNavigationLandmarkIsLabelled`                      | No `<nav>` without an `aria-label`.                                            |
| `theMenuToggleIsWiredToTheListItControls`                | The button's `aria-controls` names an `id` that actually exists.               |
| `onlyTheSidebarLayoutCarriesTheSubNavigation`            | The sub navigation appears on `content_sidebar` and nowhere else.              |

The accessible state is asserted throughout, not the visual one — asserting a
modifier class instead of `[aria-current='page']` would let the two drift
apart without any test noticing, since the stylesheet reads the attribute, not
a class.

## See also

- [Page rendering](page-rendering.md)
- [Component library](../development/component-library.md)
- [`DESIGN.md`](../../DESIGN.md)
- [Functional tests](../testing/functional-tests.md)
