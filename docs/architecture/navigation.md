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
`_nav-main.scss` gates collapsing behind both come with the appearance
switcher, not with navigation. Until then the button is inert, and
`_nav-main.scss` hides it entirely in the absence of `data-js` — the menu is
simply always expanded, which is the intended, working state rather than a
degraded one. See [Component library § The `data-js`
marker](../development/component-library.md#the-data-js-marker) for the CSS
side of that gate and the test that pins it down.

## Language navigation is deliberately absent

Nothing in the navigation contract asks for one, and the theme has no
multi-language story yet — `NavigationRenderingTest` runs a single-language
site configuration. Adding a language menu ahead of that would be building
against a contract that does not exist yet rather than the one that does.

## What the tests cover

`Tests/Functional/NavigationRenderingTest.php`, against the three-level
fixture in `NavigationPageTree.csv`:

| Test                                                     | Guards                                                                    |
|----------------------------------------------------------|---------------------------------------------------------------------------|
| `theMainMenuListsTheTopLevelOfTheSite`                   | The top level renders, from the site root regardless of the current page. |
| `theMainMenuLeavesOutAPageHiddenFromNavigation`          | `nav_hide` is honoured.                                                   |
| `theMainMenuCarriesASecondLevel`                         | `expandAll = 1` puts the second level in the markup unconditionally.      |
| `theSubNavigationShowsTheSectionOnEveryLevelOfIt`        | The `leveluid:1` fix, asserted at all three page depths at once.          |
| `theCurrentPageIsMarkedForAssistiveTechnology`           | `aria-current="page"` is present on the current page's link.              |
| `theBreadcrumbShowsTheTrailAndDoesNotLinkTheCurrentPage` | The trail order, and that the last item is not an anchor.                 |
| `everyNavigationLandmarkIsLabelled`                      | No `<nav>` without an `aria-label`.                                       |
| `theMenuToggleIsWiredToTheListItControls`                | The button's `aria-controls` names an `id` that actually exists.          |
| `onlyTheSidebarLayoutCarriesTheSubNavigation`            | The sub navigation appears on `content_sidebar` and nowhere else.         |

The accessible state is asserted throughout, not the visual one — asserting a
modifier class instead of `[aria-current='page']` would let the two drift
apart without any test noticing, since the stylesheet reads the attribute, not
a class.

## See also

- [Page rendering](page-rendering.md)
- [Component library](../development/component-library.md)
- [`DESIGN.md`](../../DESIGN.md)
- [Functional tests](../testing/functional-tests.md)
