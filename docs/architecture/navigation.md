# Navigation

Main menu, left-hand sub navigation, breadcrumb. All three are the same core
processor, configured three different ways, in
[`Configuration/TypoScript/Navigation.typoscript`](../../Configuration/TypoScript/Navigation.typoscript):

| Variable     | Source                                    | Levels |
|--------------|-------------------------------------------|--------|
| `mainMenu`   | `special = directory`, from the site root | 2      |
| `subMenu`    | the current page's **section**, see below | 2      |
| `breadcrumb` | `special = rootline`                      | —      |

All three are `TYPO3\CMS\Frontend\DataProcessing\MenuProcessor`, stock core and
unchanged across both supported versions — no changelog entry in `12.*/` or
`13.*/` changes what it returns. No custom PHP, and no `Core<major>/` split. This page is the detail behind the navigation partials;
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

**Those three flags are not computed in PHP on either supported core**, which
is worth knowing before reading the processor as if it computed them:

| What                 | Where it comes from                                                                                                                     |
|----------------------|-----------------------------------------------------------------------------------------------------------------------------------------|
| The item             | A JSON string assembled by TypoScript, decoded by `LanguageMenuProcessor::process()`. The link is a placeholder substituted afterwards. |
| `active`             | `cObject` slot `91`. The `ACT` and `CUR` state configurations write `1` into it, `USERDEF2` keeps it because it is derived from `ACT`.  |
| `current`            | `cObject` slot `92`. Only the `CUR` state configuration writes `1` into it, and a language menu never reaches that state.               |
| `available`          | `cObject` slot `93`. `USERDEF1` and `USERDEF2` write `0` into it; every other state leaves the default `1`.                             |
| The state of an item | `AbstractMenuContentObject`, which picks `USERDEF1`/`USERDEF2` when the overlay is empty and `NO`/`ACT` when it is not.                 |

`menuLevelConfig` and `buildConfiguration()` — the slot table and the state
configurations above — are **unchanged between 12.4.45 and 13.4.35**, read in
both rather than assumed from one. The class around them is not identical:
v13.4 declares property types, takes its two collaborators by constructor
injection, drops the `$menuTargetVariableName` property, and reaches the
request and the site through the request attributes where v12.4 goes through
`$GLOBALS['TSFE']`. None of that reaches the three flags. Neither version sets
`special.normalWhenNoLanguage`. The template therefore depends on the contract
rather than on the mechanism, which is why one template serves both;
`LanguageMenuRenderingTest` is green on v12.4 and v13.4 alike.

It also uses only the keys `LanguageMenuProcessor` has documented since TYPO3
9.3 — `languageId`, `navigationTitle`, `hreflang`, `link`, `active`,
`available`. The item carries `locale`, `title`, `twoLetterIsoCode`,
`direction`, `flag` and `current` as well, on both cores; a menu of links needs
none of them.

### An untranslated language is shown, not hidden

A language the current page has no translation for comes back with
`available = 0`, and the partial renders it as plain text carrying
`aria-disabled="true"` rather than as a link. Hiding it would tell a reader the
site has fewer languages than it has; linking it would promise a translation
that does not exist.

**`fallbackType` cannot argue that flag away.** The state is decided by whether
`getPageOverlay()` returned a record of its own — recognised by `_PAGES_OVERLAY`
on v12.4 and by `_LOCALIZED_UID` on v13.4, the same test under two names — and
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

### The header dropdown is a popover

`Partials/Page/Dropdown.html` is a trigger carrying `popovertarget` over a panel
carrying `popover`. The browser opens and closes it, puts it in the top layer
and gives it light dismiss — Escape and a click outside — with no script of the
theme's own. That is why the dropdown, unlike the display settings, is rendered
unconditionally rather than hidden until `data-js`: nothing about opening it
depends on a script having run.

`aria-expanded` is still mirrored onto the trigger, because the Popover API
tells assistive technology nothing about it. `theme.js` does that from the
panel's own `toggle` event — it reports the state and never changes it, so a
page whose script failed to load still has a working dropdown with a stale
attribute, rather than a dead button.

The Popover API is what moved the [browser floor](../../DESIGN.md#the-browser-floor)
to Firefox 125.

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
