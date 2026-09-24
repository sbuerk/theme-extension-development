# Icons

Every icon the theme draws comes from **Font Awesome Free**. The full solid
set of one pinned version of the npm package `@fortawesome/fontawesome-free`
is copied into the extension and committed, together with **fifteen named
logos of its brands style**, and a page gets the icons it uses as **inline
SVG**. There is no webfont, no CDN, no sprite and no request for an icon of
any kind.

The solid set is the theme's icons. The brand logos are not: they exist for
one job — [naming the platform a social link leads to](#brand-logos) — and
they are a trademark of somebody else, which is why they are an allowlist
rather than a style copied whole.

```bash
# Copy the solid icons, the allowlisted brand logos, the licence and the
# categories of the pinned version into the extension.
Build/Scripts/runTests.sh -s buildIcons

# Check the committed copies still equal the pinned package, as CI does.
Build/Scripts/runTests.sh -s checkIconsBuild
```

| Path                                                 | Is                                                                                         |
|------------------------------------------------------|--------------------------------------------------------------------------------------------|
| `Resources/Public/Icons/FontAwesome/Solid/`          | The 2001 files of `svgs/solid/` of 7.3.1, 1 648 470 bytes, unchanged.                      |
| `Resources/Public/Icons/FontAwesome/Brands/`         | The fifteen files of `svgs/brands/` that `fontAwesomeBrands` names, unchanged.             |
| `Resources/Public/Icons/FontAwesome/LICENSE.txt`     | The licence of the package, copied from the same version.                                  |
| `Resources/Public/Icons/FontAwesome/categories.yml`  | `metadata/categories.yml` of the same version: the backend groups.                         |
| `Resources/Public/Icons/FontAwesome/ATTRIBUTION.txt` | The attribution, written by hand: both sets, version, author, licence, the trademark note. |
| `package.json`, `package-lock.json`                  | The exact pin — `"7.3.1"`, no range — its integrity hash, and the brand allowlist.         |

## Why the whole set, and why inline

The whole solid set ships, not the handful of icons the templates use today,
because the next step is letting an editor pick an icon by name. A set cut to
what the theme needs would turn every icon an editor wants into a build change.

Inline SVG is the one way to use an icon that needs nothing from the network
and nothing from a font:

- **Colour follows the text.** Every file paints its path with
  `fill="currentColor"`, so an icon takes the colour of the text it sits in —
  the accent of an alert, the hover colour of a button, and the system colours
  of forced colours mode, with no rule of its own for any of them.
- **Accessibility is decided per use.** A decorative icon is `aria-hidden`, an
  icon that is the only content of its context gets `role="img"` and a name.
  A webfont glyph is a private use character that assistive technology may
  read out, and it is gone when a reader blocks web fonts.
- **No request.** A sprite referenced with `<use href="…">` is one more
  request, has to be same origin, and puts the icon into a shadow tree the page
  stylesheet reaches only through inherited properties. A file of the set is
  824 bytes on average; the few a page uses cost less inlined than a request.

## Brand logos

The brands style of Font Awesome Free is 609 files, and fifteen of them ship:

```json
"fontAwesomeBrands": [
    "bluesky", "discord", "facebook", "github", "gitlab", "instagram",
    "linkedin", "mastodon", "pinterest", "threads", "tiktok", "whatsapp",
    "x-twitter", "xing", "youtube"
]
```

That list is in the root `package.json`, next to the version pin, and it is
the whole mechanism: `build:icons:brands` copies exactly those files, and
`build:icons:brands:verify` fails when the directory and the list disagree.
Adding a platform is one line plus a rebuild plus a commit, and it is visible
in a diff as a decision somebody made.

**Why an allowlist and not the style.** Three reasons, in the order they
matter:

- **A brand logo is a trademark of its owner**, and Font Awesome's licence
  asks in so many words that it is used *only to represent the company,
  product or service it refers to*. Shipping 609 of them into every
  installation of the theme invites exactly the use the licence asks against —
  a logo as decoration, next to a feature, in a card. Fifteen, reachable from
  one field on one content element, is a set whose only use is the one that is
  allowed.
- **The solid set is the theme's icon language**, and it stays that. A brand
  logo is never an alternative to `circle-info` or `chevron-down`; there is no
  overlap to choose from.
- **Size.** The whole brands style of 7.3.1 is 765 101 bytes in 609 files,
  against the 1 648 470 bytes of solid icons that already ship. The fifteen
  taken from it are 13 194 bytes. All of it would travel into the composer
  dist archive and the TER artifact.

They are rendered exactly like any other icon, through the same ViewHelper,
with `set="brands"`:

```html
<theme:icon set="brands" name="mastodon" />
<theme:icon set="brands" name="{item.brand_icon}" optional="1" />
```

`set` takes `solid` (the default) or `brands`, and nothing else — a third
value throws `\InvalidArgumentException` `1789218004` rather than falling back
to the default, because `set="brand"` would otherwise look for a platform logo
among the solid icons and report *the name* as missing.

An icon-only social link is **never** a link without a name. A template that
renders a brand logo keeps the platform name in the markup and hides it
visually; the logo itself stays `aria-hidden`, like every other decorative
icon.

`Tests/Unit/IconUsageTest::theBrandLogosAreTheAllowlistOfThePackageManifest`
holds the list to being sorted, free of duplicates, at most fifteen entries
long and equal to the committed directory;
`theAttributionNamesTheTrademarkRestrictionOfTheBrandLogos` holds
`ATTRIBUTION.txt` to naming the restriction.

## Licence and attribution

`LICENSE.txt` of the package, checked for 7.3.1: the **icons** are
**CC BY 4.0**, the fonts SIL OFL 1.1 and the code MIT. Only icons are shipped
here — no font file and no line of Font Awesome code — so CC BY 4.0 is the
licence that applies, and it requires attribution.

Two things meet that requirement, and both ship:

- **Every file keeps the comment it comes with**, naming Font Awesome Free, the
  version, the licence and Fonticons, Inc. The files are copied byte for byte.
  `LICENSE.txt` of the package says why that comment is there and asks for it
  to stay:

  > Attribution is required by MIT, SIL OFL, and CC BY licenses. Downloaded
  > Font Awesome Free files already contain embedded comments with sufficient
  > attribution, so you shouldn't need to do anything additional when using
  > these files normally.
  >
  > We've kept attribution comments terse, so we ask that you do not actively
  > work to remove them from files, especially code.

  Keeping every file identical to the package is also what makes the gate below
  a plain `diff`.
- **`LICENSE.txt` and `ATTRIBUTION.txt`** sit next to the set, below
  `Resources/Public/`, which is not `export-ignore`d: they reach the composer
  dist archive and the TER artifact together with the icons.

The same file adds a condition that CC BY 4.0 does not, for the brands style
alone:

> All brand icons are trademarks of their respective owners. The use of these
> trademarks does not indicate endorsement of the trademark holder by Font
> Awesome, nor vice versa. **Please do not use brand logos for any purpose
> except to represent the company, product, or service to which they refer.**

`ATTRIBUTION.txt` repeats it beside the files, because that is the copy that
travels with the extension.

## The build and the gate

The build is npm scripts in the root `package.json`, next to the CSS build,
run in the same node image through `runTests.sh`. Two of them are the entry
points the suites call; the rest are the steps they chain:

| Script                      | Suite             | Does                                                                                                  |
|-----------------------------|-------------------|-------------------------------------------------------------------------------------------------------|
| `build:icons`               | `buildIcons`      | The three below, in order.                                                                            |
| `build:icons:solid`         | —                 | Empties `Solid/` and copies `svgs/solid/*.svg` of the installed package into it, nothing else.        |
| `build:icons:brands`        | —                 | Empties `Brands/` and copies the files `fontAwesomeBrands` names out of `svgs/brands/`, nothing else. |
| `build:icons:meta`          | —                 | Copies `LICENSE.txt` and `metadata/categories.yml` of the installed package into place.               |
| `build:icons:verify`        | `checkIconsBuild` | The three below, in order.                                                                            |
| `build:icons:solid:verify`  | —                 | `diff -r` of the installed `svgs/solid/` against `Solid/`.                                            |
| `build:icons:brands:verify` | —                 | Copies the allowlist into `.Build/icons-verify/Brands` and `diff -r`s that against `Brands/`.         |
| `build:icons:meta:verify`   | —                 | `diff -u` of the two licence files and of the two category files.                                     |

Both start with `npm ci`, which installs exactly the version of
`package-lock.json` and refuses a tarball whose integrity hash does not match.
The comparison fails in every direction: an edited file, a file missing from
the committed set and a file in the committed set that the package does not
have — a hand-drawn icon dropped next to the vendored ones — each make it exit
non-zero. `Brands/` is compared against the **allowlist applied to the
package** rather than against the whole brands style, so it fails on the same
three plus a fourth: a name added to or dropped from `fontAwesomeBrands`
without running the build. It is the icon counterpart of
[`checkCssBuild`](frontend-assets.md#the-checkcssbuild-gate), and like it
compares files instead of asking `git`, for the same reason.

The scripts are plain shell in `package.json` rather than a script below
`Build/`, because `Build/` is `export-ignore`d and `package.json` ships: the
rebuild path travels with the sources, as it does for the stylesheet. The
brand step is the one that cannot be shell alone — it reads a JSON array out
of `package.json`, and the container images ship no `jq` — so it is a `node
-e` expression, in the image that is already running `npm`.
`ATTRIBUTION.txt` is not written by the build; it names the version and has to
be changed by hand when the pin moves.

In CI the gate is a step of the `assets` job, next to `checkCssBuild`: it
needs neither PHP nor a core version, and it uses the node image and the
lockfile that job already uses.

### Why it is committed

For the reason the stylesheet is: both distribution paths are exports of
committed content and run no build — see
[Frontend assets § Why the compiled CSS is committed](frontend-assets.md#why-the-compiled-css-is-committed).
The package is a `devDependency`: nothing that installs the extension ever runs
`npm`, and `node_modules/` is git-ignored and `export-ignore`d.

## Rendering an icon

`<theme:icon>` —
[`Classes/ViewHelpers/IconViewHelper.php`](../../Classes/ViewHelpers/IconViewHelper.php)
— is the one way an icon reaches a page. A template declares the namespace
with the URL form and names the icon by its file name:

```html
<html xmlns:f="http://typo3.org/ns/TYPO3/CMS/Fluid/ViewHelpers"
      xmlns:theme="http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers"
      data-namespace-typo3-fluid="true">

<theme:icon name="circle-info" />
<theme:icon name="gear" class="theme-settings__icon" />
<theme:icon name="circle-info" label="Information" />
```

| Argument   | Required | Does                                                                                                   |
|------------|----------|--------------------------------------------------------------------------------------------------------|
| `name`     | yes      | The icon: a file name below the set's directory without `.svg`. `a-z`, `0-9` and `-` only.             |
| `set`      | no       | `solid` (the default) or `brands`. Anything else throws — see [Brand logos](#brand-logos).             |
| `label`    | no       | An accessible name. With one the icon is `role="img"` with `aria-label`; without, it is `aria-hidden`. |
| `class`    | no       | Classes added after `theme-icon`, for the slot a component gives it.                                   |
| `optional` | no       | Render nothing for an empty name or a name the set does not have. For a name read from a record.       |

It renders the file as shipped, attribution comment included, with three
attributes added to the root element and nothing else changed:

```html
<svg class="theme-icon" aria-hidden="true" focusable="false" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512"><!--! Font Awesome Free 7.3.1 by @fontawesome - https://fontawesome.com License - … Copyright 2026 Fonticons, Inc. --><path fill="currentColor" d="…"/></svg>
```

The comment goes into the page with every icon. The icons are CC BY 4.0, and
whoever shares them carries the attribution — a public page shows them — and
the licence asks that the comment is not removed from files, "especially code"
(see [Licence and attribution](#licence-and-attribution)). The cost is about
210 bytes per icon on the page, accepted as the price of that attribution.
`Tests/Functional/AppearanceRenderingTest` requires the comment on a rendered
page.

Almost every icon is decoration — next to text, or inside a button named by its
`aria-label` or its own text — and takes no `label`. A label is for the rare
icon that is the only thing telling a reader something.

`label` and `class` are text, and are escaped once more on the way out, the way
Fluid's own `TagBuilder::addAttribute()` escapes an attribute value: an entity
written into the template, `label="Tom &amp; Jerry"`, arrives in the page as
`aria-label="Tom &amp;amp; Jerry"` and is read out with the entity spelled.
Write the character itself, `label="Tom & Jerry"`, or pass a variable.

A name an editor picked is the exception, and is rendered with `optional`:
the field offered only names of the set when the record was saved, but a later
Font Awesome version may rename or drop one, and that has to cost the icon,
not the page. With `optional` an empty name, a name the set does not have and a
malformed name render nothing: a record holds whatever was written into its
column, by an import as well as by the picker, and a malformed name is refused
before it becomes part of a path either way.
`IconUsageTest::aNameAnEditorPickedIsRenderedAsOptional` requires `optional`
on every `name` that is or contains a variable — a `{` anywhere in it — and
leaves those names out of the check against the set, which is for names written
into a template.

Otherwise a name that is malformed or not in the set throws an `\InvalidArgumentException`
— code `1789218001` and `1789218002` — rather than rendering nothing, and a file
that does not start and end as an `<svg>` element throws
`\UnexpectedValueException` `1789218003`: the attributes go in right after
`<svg`, so an XML declaration or a comment in front of the root element would
break the markup. The name is checked against `\A[a-z0-9-]+\z` before it
becomes part of a path, so no name reaches a file outside `Solid/`.

`IconSet` takes the directory it reads as a constructor argument that defaults
to the shipped set. Nothing but `IconSetTest` passes one, to reach that last
exception with a fixture; the object holds the path and nothing else.

What an icon looks like is [`components/_icon.scss`](../../Resources/Private/Scss/components/_icon.scss):
a square one em wide, filled with the text colour. A component that gives icons
a slot sets `--theme-icon-size` on it — see
[Component library § Icon](component-library.md#icon).

### Plain Fluid, for the standalone renderer

The styleguide partials use the ViewHelper, and
[the visual suite](../testing/visual-tests.md) renders them with standalone
`typo3fluid/fluid` and no TYPO3 bootstrap. So the ViewHelper is a plain Fluid
ViewHelper and has to stay one:

- It uses no TYPO3 API. The set it reads, `Classes/Icon/IconSet.php`, finds
  `Solid/` relative to its own class file, not through
  `ExtensionManagementUtility` or a resource path.
- Its namespace is declared per template with
  `http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers`. Both Fluid
  2 (TYPO3 v12) and Fluid 4 (TYPO3 v13) turn that URL into the PHP namespace by
  themselves, so neither TYPO3 nor `renderStyleguideFixtures.php` registers it,
  and no global namespace is added to every template of an installation.
- One class works on both Fluid versions. Neither Fluid 2 nor Fluid 4 types
  `initializeArguments()` or `render()`, and `$escapeOutput` is untyped in
  both, so `initializeArguments(): void`, `render(): string` and an untyped
  `$escapeOutput` satisfy both — no split into `Core12/` and `Core13/` is
  needed.
- It is `final`, not `readonly`: the parent keeps the arguments of the current
  call in mutable properties. It keeps nothing of its own between calls. TYPO3
  creates a new instance per use (EXT:fluid tags every ViewHelper
  `fluid.viewhelper` and marks it non-shared), and the constructor defaults the
  `IconSet` for the standalone renderer, which has no container.

`Tests/Unit/ViewHelpers/IconViewHelperTest` renders it with standalone Fluid;
the functional tests render it through TYPO3.

## Picking an icon in the backend

An editor picks an icon from a select field with the core's `selectIcons`
field wizard: the list, grouped by Font Awesome's categories, and under it a
grid of the icons to click on. A column that stores an icon name — of the theme
or of another extension — takes its `config` from
[`Classes/Tca/IconItems.php`](../../Classes/Tca/IconItems.php):

```php
use SBUERK\ThemeExtensionDevelopment\Tca\IconItems;

'tx_myextension_icon' => [
    'label' => '…',
    'config' => IconItems::selectConfig(),
],
```

and a template renders the stored name with
`<theme:icon name="{…}" optional="1" />`. The theme adds such a column where an
element renders the icon, and not before: a field whose value nothing renders
is work an editor does for nothing.

| Key             | Is                                                                                                                |
|-----------------|-------------------------------------------------------------------------------------------------------------------|
| `renderType`    | `selectSingle`, `default` `''`.                                                                                   |
| `items`         | "No icon" with the value `''` — the only item in the TCA.                                                         |
| `itemsProcFunc` | `IconItems->addItems`: every icon of the catalogue, label and value the name, sorted.                             |
| `items.icon`    | `EXT:theme_extension_development/Resources/Public/Icons/FontAwesome/Solid/<name>.svg`.                            |
| `items.group`   | The first category of `categories.yml` that lists the name.                                                       |
| `itemGroups`    | Every category, in the order of the file, with Font Awesome's English label; empty ones are left out by the core. |
| `fieldWizard`   | `selectIcons` switched on.                                                                                        |

The icon fields of the theme:

| Column                          | Shown in                                                                                                                | Rendered by                                                |
|---------------------------------|-------------------------------------------------------------------------------------------------------------------------|------------------------------------------------------------|
| `tx_theme_list_item.link_icon`  | The `theme_link` palette of the child, so every link relation.                                                          | `LinkList.html`, `ThemeMediaTeaserGrid.html`               |
| `tt_content.tx_theme_link_icon` | The `theme_link` palette of the hero and teaser elements.                                                               | `LinkButton.html`                                          |
| `tt_content.tx_theme_icon`      | The icon of an element as a whole: the bullet list in its layout "Icons".                                               | `Bullets.html`                                             |
| `tx_theme_list_item.icon`       | The icon of an item, in the relations of the features, the figures and the steps; and in the default type of the child. | `ThemeFeatures.html`, `ThemeStats.html`, `ThemeSteps.html` |

The stored value is the name, and a template renders it with
`<theme:icon name="{…}" optional="1" />` — see
[Rendering an icon](#rendering-an-icon) for why a name from a record is
optional.

Five decisions, each read in the core rather than assumed:

- **The icon of an item is the file.** `FormEngineUtility::getIconHtml()`
  renders an item's `icon` as an `<img>` when it resolves to a file and asks
  the icon registry only otherwise — through `getFileAbsFileName()` on v12.4
  and on v13.4. So the set needs no registration in `Configuration/Icons.php`:
  2001 backend icons would be the wrong tool, since the registry is for the
  backend's own interface.
  The image draws the icon in black, whatever the backend scheme: an `<img>`
  inherits no `currentColor`.
- **The icons are not in the TCA.** Written into it as static items they cost
  several hundred kilobytes of cached TCA per column, and every request of an
  installation loads the TCA. The TCA carries the field, "No icon", the groups
  and the wizard; `IconItems::addItems()`, the `itemsProcFunc`, adds the icons
  when a form is built. Page TSconfig still narrows them:
  `TcaSelectItems::addData()` resolves the `itemsProcFunc` before `keepItems`,
  `addItems` and `removeItems` — lines 59 against 72 to 74 on v12.4.45, 65
  against 78 to 80 on v13.4.35.
- **The list is built once and cached.** `IconCatalogue` reads every file of
  the set to find the aliases below, which is not something to do per form and
  per inline child. `IconItems` keeps the result in the `core` cache as a PHP
  file, under a fingerprint of the names of the set and of `categories.yml`, so
  a different set is a different entry. It keeps nothing in the object: the
  service is stateless, the cache is the state, and it is flushed with every
  other cache. After changing the set by hand, flush the caches.
- **One group per icon, the first category that lists it.** Font Awesome files
  many icons under several categories, and a select item has one group. Any
  rule is a choice; this one needs nothing but the file. `circle-info` comes
  out under *Accessibility*, `envelope` under *Business*.
- **The aliases of renamed icons are left out.** 579 names of 7.3.1 are in no
  category, and every one of them is an alias Font Awesome keeps for a renamed
  icon — `arrow-circle-right` for `circle-arrow-right` — whose file is byte for
  byte the file of the icon it stands for. A name no category lists is dropped
  when its glyph is the glyph of one that is listed, which needs no metadata
  beyond what ships; the alias list of `metadata/icons.yml` is 912 kB. A name no
  category lists that draws a glyph of its own would be kept, last, under
  "Without a category" — 7.3.1 has none.

`categories.yml` is copied by `buildIcons` and compared by `checkIconsBuild`,
like the icons: it is data of the pinned version and changes with it.

Page TSconfig narrows a field to the icons it names:

```typoscript
TCEFORM.tx_myextension_domain_model_thing.tx_myextension_icon.keepItems = ,envelope,phone,globe
```

The value starts with a comma. `keepItems` keeps the items whose value it
lists, and "No icon" has the empty value: without the empty entry the leading
comma makes, the form drops the item, preselects the first icon and stores it
with every record saved.

`Tests/Unit/Icon/IconCatalogueTest` holds the groups and the aliases,
`Tests/Unit/Tca/IconItemsTest` the configuration and the cache.
`Tests/Functional/IconPickerFormEngineTest` compiles the form the way the
backend does, on each core, for the column of the fixture extension
`tests/icon-picker-fixture`, and holds what reaches the editor: a list page
TSconfig narrowed, with "No icon" still first; the whole catalogue otherwise,
without the aliases; the category headings; and an `<img>` of the right file for
every item.

## The rule

Icons come **only** from the vendored Font Awesome Free sets — the solid set
for everything the theme means, the fifteen brand logos for naming a platform
— and **only** through `<theme:icon>`. No SVG drawn into a template by hand,
no icon as CSS generated content, no webfont, no sprite, no CDN.

An icon is a glyph that stands for something — a kind of message, an action,
a state. The stylesheet still draws a few shapes of its own, and they are
**component geometry, not icons**. These, and only these, stay CSS:

| Shape                             | Where                         | Why it is not an icon                                                          |
|-----------------------------------|-------------------------------|--------------------------------------------------------------------------------|
| The arrow of the tooltip bubble   | `components/_tooltip.scss`    | Part of the bubble's outline, pointing at its trigger; it means nothing alone. |
| The spinner of a busy button      | `components/_button.scss`     | An animated ring over `[aria-busy='true']`; the label says what happens.       |
| The track and thumb of the switch | `forms/_controls.scss`        | The control itself, drawn on the native checkbox.                              |
| The ring and rail of a timeline   | `components/_timeline.scss`   | The position of an entry in a sequence; the date says what the ring marks.     |
| The dot of a carousel indicator   | `components/_carousel.scss`   | The position of a slide in the set; the link around it carries the name.       |
| The `/` between breadcrumb items  | `components/_breadcrumb.scss` | A text separator in `content`, not a glyph.                                    |
| The rule between byline items     | `components/_byline.scss`     | The `border-inline-start` of the item after it — a separator, not a glyph.     |
| The arrow of a native select      | the browser                   | The user agent's own indicator, left in place on purpose.                      |

The accordion chevron and the check mark of the chosen palette were drawn from
borders as well; they are icons of the set now — `chevron-down` and `check`.
The other pseudo elements of the stylesheet draw no shape at all: the hit area
of a linked card and the hover bridge of the tooltip are invisible, and the
`CType` label of the content-element outline and the quotation marks are text.
A new shape drawn in CSS is either one of the kinds above or an icon.

An icon of the set may still reach the page through the stylesheet where a
component shows the same one on every item and has no markup per item to put
`<theme:icon>` into — the marker of `.theme-list--check`. The stylesheet then
references the vendored file as a CSS `mask`, relative to the compiled
`theme.css` (`url('../Icons/FontAwesome/Solid/check.svg')`), and paints it
with `background-color`. That draws nothing: the shape is the file's, the one
`<theme:icon name="check" />` renders, and `checkIconsBuild` covers it with the
rest of the set. It needs a forced colours rule of its own, because the fill is
a background — see `components/_list.scss`.

The markers of a decorated link are the second case, for the other reason: no
template of the theme writes that markup at all.
`Classes/EventListener/LinkDecoration.php` adds an empty
`<span class="theme-link__marker">` to a link TYPO3 builds — rich text
included — and `components/_link.scss` masks it with the file of its kind:
`arrow-up-right-from-square`, `download`, `envelope`, `phone`. The stylesheet
decides the glyph, so a site package changes it in CSS. Its forced colours rule
differs from the check list's on purpose: the marker opts out and keeps
`currentColor`, which is then the link colour the system forces, where the
check mark paints `CanvasText` — a marker has to stay the colour of its link.
See [Component library § Link decoration](component-library.md#link-decoration).

The back link of a footnote is the second case again, and takes both rules
from it: `.theme-footnotes__backlink` masks `arrow-turn-up`, and the markup it
sits in comes out of a rich text or an `html` content element, where
`<theme:icon>` cannot be called at all. It opts out of forced colours and
keeps `currentColor` rather than painting `CanvasText`, because it is the
marker of a link — see `components/_footnotes.scss`.

The rule is narrow on purpose: a stylesheet references **only files of
`Resources/Public/Icons/FontAwesome/Solid/`**, by their relative path from the
compiled stylesheet. Never a `data:` URI, never an image of its own, never a
file the set does not ship. `IconUsageTest::aStylesheetReferencesOnlyFilesOfTheIconSet`
fails on any other `url()`.

`Tests/Unit/IconUsageTest` holds the templates and the stylesheets to it:

| Test                                                          | Guards                                                                                                                                     |
|---------------------------------------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------|
| `everyIconATemplateNamesIsShipped`                            | Every `name` a template passes is a file of the set its `set` names — an icon a version bump renamed fails here, not on a page.            |
| `noTemplateDrawsAnSvgOfItsOwn`                                | No template below `Resources/Private/` contains an `<svg>` element.                                                                        |
| `noStylesheetDrawsAGlyphAsGeneratedContent`                   | Every quoted `content` in the SCSS sources is empty or the breadcrumb's `/` — no `⚠`, no `✓`.                                              |
| `aNameAnEditorPickedIsRenderedAsOptional`                     | Every `name` that is a variable carries `optional`, so a name a later version dropped costs the icon, not the page.                        |
| `aStylesheetReferencesOnlyFilesOfTheIconSet`                  | Every `url()` of the SCSS sources is a file of `Solid/` by its path relative to the compiled stylesheet — no `data:` URI, no own image.    |
| `theStyleguideListsTheIconsTheTemplatesUseAndTheSizeOfTheSet` | The icon table of the styleguide lists exactly the icons the templates use and the stylesheets mask, and the count it states is the set's. |
| `theShippedSetIsThePinnedVersion`                             | The pin is exact, the lockfile agrees, and `ATTRIBUTION.txt` and every shipped file of both sets name that version.                        |
| `theBrandLogosAreTheAllowlistOfThePackageManifest`            | `fontAwesomeBrands` is sorted, unique, at most fifteen long, and equal to the committed `Brands/`.                                         |
| `theAttributionNamesTheTrademarkRestrictionOfTheBrandLogos`   | `ATTRIBUTION.txt` names `Brands/` and the trademark restriction that travels with those files.                                             |

`checkIconsBuild` proves the files equal the package; these prove that what
refers to the files is still right.

## Updating the pinned version

1. Pick the version and read its `LICENSE.txt` — the licence of the icons has to
   still be CC BY 4.0.
2. Pin it exactly:
   `Build/Scripts/runTests.sh -s npm -- install --save-dev --save-exact --no-audit --no-fund @fortawesome/fontawesome-free@<version>`.
3. `Build/Scripts/runTests.sh -s buildIcons`, and change the version in
   `ATTRIBUTION.txt`.
4. `Build/Scripts/runTests.sh -s checkIconsBuild`.
5. Look at the diff of `Solid/` and of `Brands/`. Font Awesome renames and
   removes icons between versions, a major one in particular, so a name the
   theme uses may be gone — and a name of `fontAwesomeBrands` that the new
   version no longer has makes `buildIcons` itself fail, on the copy, naming
   the file. `-s unit` names a template's icon that went missing, see
   [the rule](#the-rule). Change the count the styleguide states, and run
   `-s visual` — a redrawn icon changes pixels.
6. Commit the lockfile, `package.json`, both sets and both text files together.

## See also

- [Component library § Icon](component-library.md#icon) — the markup contract,
  and which components use icons.
- [Styleguide page](styleguide.md) — the `icons` section shows every icon the
  theme uses, by name.
- [Frontend assets](frontend-assets.md)
- [Quality gates](quality-gates.md)
- [`DESIGN.md`](../../DESIGN.md#icons)
