# TypoScript delivery

The theme ships its TypoScript twice over: as a **site set**, which is the way
it is meant to be enabled, and as a **classic static include**, for
installations that do not use site sets. Both read the same files.

| Path                                            | Is                                                                                                     |
|-------------------------------------------------|--------------------------------------------------------------------------------------------------------|
| `Configuration/Sets/ThemeExtensionDevelopment/` | The site set: `config.yaml`, and the [site settings](site-settings.md) in `settings.definitions.yaml`. |
| `Configuration/TypoScript/`                     | The actual `setup.typoscript` and `constants.typoscript`.                                              |
| `Configuration/TypoScript/Static/`              | The static include: two guarded files importing the two above.                                         |
| `Configuration/TCA/Overrides/sys_template.php`  | Registers the static include for `sys_template` records.                                               |

## The site set

```yaml
name: sbuerk/theme-extension-development
label: 'Theme Extension Development'
typoscript: 'EXT:theme_extension_development/Configuration/TypoScript/'
```

Only `name` and `label` are required; the set schema is **closed**, so any key
the core does not know throws rather than being ignored.

`typoscript` deliberately points at the shared directory instead of defaulting
to the set directory, which is what lets one set of files serve both delivery
mechanisms. The **trailing slash is required**: the core appends
`setup.typoscript` to this path directly (`SysTemplateTreeBuilder::handleSetInclude()`).

A site enables the theme by depending on it:

```yaml
dependencies:
  - sbuerk/theme-extension-development
```

Both `typoscript` and `optionalDependencies` exist in TYPO3 v13.4 and v14 alike —
verified against `SetDefinition` in both — so the set needs no version split.

## Why the static include is guarded

Site sets and `sys_template` records are processed by **separate code paths with
no cross check**, and the static include is appended **after** the set. A site
using both would therefore parse the theme twice, and the second pass would
re-assign the shipped defaults over every customisation made on top of them.

That failure does not look like duplication. It looks like "my site setting does
nothing".

Plain `=` assignments are idempotent, so the damage is limited to overriding —
but `:=addToList()`, `:=appendString()` and `<` copies are not idempotent at
all, and would visibly corrupt the result.

So the static files import their real counterparts only when the set is not
active:

```typoscript
[not ('sbuerk/theme-extension-development' in site('sets'))]
    @import 'EXT:theme_extension_development/Configuration/TypoScript/setup.typoscript'
[END]
```

`site('sets')` resolves through `Site::getSets()` and behaves identically on v13
and v14, so this is common code rather than a version split.

One limit worth knowing: `site('sets')` lists the sets a site declares
**itself**. A theme pulled in as a transitive dependency of another set is not
listed, and the guard would not suppress the static include in that case.
Enabling the theme through its own set — the documented way — is covered.

## Why `addStaticFile()` lives in a TCA override

`ExtensionManagementUtility::addStaticFile()` appends an item to
`$GLOBALS['TCA']['sys_template']['columns']['include_static_file']` and is
guarded by `is_array()` on that column. Called from `ext_localconf.php`, where
the TCA does not exist yet, it therefore does **nothing at all** — silently.

It belongs in `Configuration/TCA/Overrides/sys_template.php`. It is not
deprecated in v14, and `ext_tables.php` — the historical location — is
deprecated as of 14.3 (#109438) and must not be used.

`Tests/Functional/StaticTypoScriptIncludeTest` asserts the registration, because
"registered" and "silently absent" are otherwise indistinguishable.

## Page rendering

`page.10` is a `FLUIDTEMPLATE`, not a `PAGEVIEW`. `FLUIDTEMPLATE` behaves
identically on v13 and v14 and keeps full control over `templateRootPaths`,
`partialRootPaths` and `layoutRootPaths`, so the page rendering layer needs no
`Core13/`/`Core14/` split.

`PAGEVIEW` is the newer object and its content area API is genuinely nicer — but
`contentAs`, `f:render.contentArea`, `f:render.record` and `f:page.headerData`
are **v14 only**, and a split in the template layer is the most awkward kind:
Fluid files live in `Resources/` and cannot be selected by the container the way
classes are.

Content is rendered with `styles.content.get`, which comes from **EXT:frontend**,
not from `fluid_styled_content` — the theme deliberately does not depend on that
extension.

## Content elements without `fluid_styled_content`

Without FSC there is no `lib.contentElement` and no `tt_content.<CType>` branch,
so every element falls through to the core default, which renders a yellow box
saying the element has no rendering definition.
`Configuration/TypoScript/ContentElements.typoscript` provides both.

**What FSC supplies is the rendering, not the TCA.** This is the single most
misleading thing about developing a theme without that extension, and it is
worth stating precisely, because the obvious way of checking it gives the wrong
answer.

Reading `EXT:frontend/Configuration/TCA/tt_content.php` shows a `types` array of
`1`, `header`, `text` — and on v13 additionally `list` — which reads like the
complete list of content types. It is not. The same extension ships **22 files**
in `Configuration/TCA/Overrides/` on v13.4 and 24 on v14.3, and 20 of them —
among them `225-tt_content-content_type-image.php` and
`230-tt_content-content_type-textmedia.php` — each call
`ExtensionManagementUtility::addRecordType()` for one classic type. Verified
against `v13.4.0`, `v13.4.35` and `v14.3.7`: the 20 are the same files on all
three. v14.3 adds only `fe_groups.php` and `fe_users.php`, which relabel
columns of those tables. In the 20 it references the tab and palette labels in
the short form of #107789, and drops the field label overrides from `showitem`
(#107789 as well), moving two of them — `bodytext` of `table`, `media` of
`uploads` — into `columnsOverrides`, without changing which types they
register.

`fluid_styled_content`'s own `Configuration/TCA/Overrides/` holds exactly one
file, `sys_template.php`, which registers its static include. **It contributes no
content type TCA at all.**

|                                                       | Registered by          |
|-------------------------------------------------------|------------------------|
| The `CType` items, their fields, palettes and icons   | EXT:frontend           |
| The `tt_content.<CType>` TypoScript that renders them | `fluid_styled_content` |

The consequence is the opposite of what it looks like from the outside: every
classic content element — Text & Media, Images, Bullet List, Table, File Links,
the menus, `html`, `div`, `shortcut` — **can be created in the backend of an
installation using this theme**, and each one renders the core notice until this
extension gives it a branch. They are elements without rendering, not elements
that do not exist.

`list` is the legacy plugin type, deprecated in v13 (#105076) and removed in v14
(#105377). It is rendered too, not skipped: any third-party Extbase plugin still
registered the old way on v13.4 needs a `tt_content.list` object to render
through, the same way a plugin registered as its own `CType` — the only type
`configurePlugin()` accepts on v14, but not its default on v13.4 — needs
`Generic.html`. See
[Content elements](content-elements.md#extbase-plugins-and-tt_contentlist) for
both, and for why declaring `tt_content.list` needs no version condition even
though the CType it renders is gone on v14.

Every classic CType `EXT:frontend` registers is now covered — see
[Content elements](content-elements.md) for the full table.

The theme's own `theme_*` elements are not built on `lib.contentElement`, but on
`lib.themeContentElement`, an object with the same root paths. `fluid_styled_content`
clears `lib.contentElement` before defining it, so a name the theme shares with
that extension is the wrong place for elements only the theme can render — see
[Content elements](content-elements.md#libthemecontentelement-their-own-frame-not-libcontentelement).
Both delivery paths read the same `ContentElements.typoscript`, so neither needs
anything of its own for it.

## The `fluid_styled_content` bridge

The theme does not depend on `fluid_styled_content` and renders every classic
element itself. Installed **beside** it, the two are not merely redundant —
they are order dependent, and both orders are wrong:

| `fluid_styled_content` loads | What happens                                                                                                                                                                   |
|------------------------------|--------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| **after** the theme          | Its `lib.contentElement >` clears the object, taking the theme's Fluid paths with it. The theme's branches then look for `ContentElements/…` among that extension's templates. |
| **before** the theme         | Its per-element `dataProcessing` stays underneath the theme's branches: `=<` and a plain assignment keep every key the theme does not overwrite.                               |

The second is the quiet one, and it is not quiet in practice. Both extensions
wire `menu_categorized_content` through `DatabaseQueryProcessor` and configure
it differently — that extension with `join` and `where.wrap`, the theme with a
portable `where.cObject` subquery (see
[the two categorized types](content-elements.md#the-two-categorized-types-are-built-differently--deliberately)).
The surviving keys compose one query out of two, and the page dies on a SQL
syntax error rather than rendering something slightly wrong. It was found by
running it, not by reading it.

The bridge makes the combination **defined**: a site set and a static include,
both reading `Configuration/TypoScript/Fsc/setup.typoscript`.

```yaml
dependencies:
  - sbuerk/theme-extension-development-fsc
```

That file clears the twenty-two classic `tt_content` branches and then imports
the theme's own `ContentElements.typoscript` again, last. The result is the
rendering the theme produces on its own — **byte for byte**, whichever
extension was loaded first.

Three details carry the weight:

- **Clearing, not merging.** Only `>` removes children
  (`AbstractAstBuilder::handleIdentifierUnsetLine()`), so re-declaring a branch
  over the other extension's would inherit whatever the theme does not happen
  to overwrite, key by key — the border settings of `GalleryProcessor`, a
  `table` fed by `comma-separated-value` against the theme's own
  `TableProcessor`, a `menu` with a different `special`.
- **Root path index `5`.** `lib.contentElement` carries that extension's
  templates at `0` and the integrator's `{$styles.templates.*}` at `10` — the
  same two indices in its **13.4 and 14.3** releases, read in both rather than
  assumed from one. Fluid tries root paths from the highest index down — the
  core sorts them by integer key and the engine walks them reversed — so at `5`
  the theme's templates beat that extension's while an integrator's documented
  override still beats both. Standalone this is the same single entry it always
  was. (v13 ships those templates as `*.html` and v14 as `*.fluid.html`;
  `resolveFileInPaths()` tries both spellings per path, so the theme's `*.html`
  overrides win on either.)
- **`optionalDependencies`, not `dependencies`.** A missing entry under
  `dependencies` makes the whole set invalid: `SetRegistry::computeOrderedSets()`
  drops it and logs an error, so a hard dependency would turn this set into an
  error message in every installation of the theme that does not have that
  extension. An optional one is skipped when absent and, when present, both
  orders the theme after it and activates it for the site
  (`hasDependency()` counts optional dependencies).

`tt_content.list` is deliberately **not** cleared. It is the historical plugin
CType, and on v13 `configurePlugin()` writes straight into it through
`defaultContentRendering`, at a point this file cannot see — clearing it could
drop a third-party plugin's own registration, a worse failure than the leftover
it would prevent. On v14 the CType does not exist at all.

On the static path the order is the integrator's, and the bridge has to be
**last**, after both `Fluid Content Elements` and `Theme Extension Development`.

## Plugins, and the static include as a content rendering template

`ExtensionUtility::configurePlugin()` adds the rendering of every plugin
`CType` with `addTypoScript(…, 'defaultContentRendering')`. The two delivery
paths treat that key differently (`SysTemplateTreeBuilder`, read on v13.4 and
v14.3):

- a site using sets gets it unconditionally
  (`createSiteTemplateInclude()`);
- a `sys_template` site gets it only right after a static include listed in
  `$GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates']`
  (`addStaticMagicFromGlobals()`) — the slot `fluid_styled_content` fills for
  the installations that use it.

The theme's static include was not listed, so on the static path every classic
and every `theme_*` element rendered and every plugin — EXT:felogin's login
form among them — fell through to the "no rendering definition" notice.
`ext_localconf.php` now registers it, as
`themeextensiondevelopment/Configuration/TypoScript/Static/`: the extension key
without underscores and the registered path with a trailing slash, the string
the tree builder derives from an `include_static_file` entry.

It only takes effect for an `include_static_file` entry. A test that imports the
two static files directly into a `sys_template` renders the theme without any
plugin rendering, which is why `ExtbasePluginStaticIncludeRenderingTest` and the
static case of `FeloginRenderingTest` set `include_static_file`.
`ExtbasePluginRenderingTest` covers the set path.

`Felogin.typoscript` adds the theme's templates for the login form above
felogin's own — see [Content elements](content-elements.md#extfelogin).

The `image` element is rendered through two core data processors, both in
EXT:frontend: `FilesProcessor` resolves the references of the `image` field and
`GalleryProcessor` turns `imagecols`, `imageorient`, `imagewidth`, `imageheight`
and `imageborder` into rows, columns and a computed width and height per image.
Iterating the files in Fluid instead would render the images correctly and
ignore every one of those backend fields — which is why the functional test
asserts the column count rather than the presence of an `<img>`.

What core still gives for free, and what the templates therefore rely on:

- `lib.parseFunc` and `lib.parseFunc_RTE`, in EXT:frontend since v13.2 (#103485),
  so `<f:format.html>` parses rich text without FSC
- `styles.content.get`, the `tt_content = CASE` skeleton, `FilesProcessor` and
  `GalleryProcessor`

`header_layout` is honoured, including the value **100**, which the core TCA
offers as "do not display". A theme ignoring it would render headings an editor
had deliberately hidden.

> [!NOTE]
> Developer notes in a Fluid template belong in `<f:comment>`, not in an HTML
> comment. Fluid strips the former and renders the latter into the response —
> this was found the hard way, when a test asserting the absence of the core
> error notice matched a template comment that merely *described* it.

## What the tests cover

| Test                                           | Proves                                                                                 |
|------------------------------------------------|----------------------------------------------------------------------------------------|
| `SiteSetRenderingTest`                         | A page renders through the set, with **no** `sys_template`.                            |
| `StaticTypoScriptFallbackRenderingTest`        | A page renders through the static include, with no set.                                |
| `StaticTypoScriptIncludeTest`                  | The static include is registered in the TCA at all.                                    |
| `ContentElementRenderingTest`                  | `header` and `text` render, and the core error notice does not appear.                 |
| `ThemeContentElementObjectTest`                | Every `theme_*` element renders the same with `lib.contentElement` cleared, both ways. |
| `ImageElementRenderingTest`                    | The `image` element renders, and its backend fields reach the output.                  |
| `ExtbasePluginStaticIncludeRenderingTest`      | An Extbase plugin renders through the static include, not only through the set.        |
| `FeloginRenderingTest`                         | The login form renders on the form contract, through the set and the static include.   |
| `DevelopmentInstance/LegacyDeliveryTest`       | The seeded showcase renders the same markup through both mechanisms.                   |
| `DevelopmentInstance/DeliveryRegistrationTest` | Every static include of the seeded `sys_template` root resolves and is registered.     |
| `FluidStyledContentBridgeTest`                 | The bridged page is byte for byte the page the theme renders alone, on both paths.     |

The first two cover the two branches of the guard condition. Both were shown to
fail: renaming the set breaks the first, inverting the condition breaks the
second. `DevelopmentInstance/LegacyDeliveryTest` runs the same comparison over
the whole seeded showcase, page by page, and `DeliveryRegistrationTest` checks
the static include the seeded `sys_template` names — see
[Seeding](../development/seeding.md#two-trees-two-delivery-mechanisms).

`ImageElementRenderingTest` was shown to fail twice, in the two ways that
matter: removing the `tt_content.image` branch turns all eight tests red, and
replacing `numberOfColumns.field = imagecols` with a fixed `1` turns exactly the
two column assertions red while the images still render. The second break is the
point of the test — it is the failure a template iterating the files directly
would produce, and it is invisible in a screenshot.

## See also

- [Core version aware code](core-version-aware-code.md)
- [Frontend assets](../development/frontend-assets.md)
- [Site based tests](../testing/site-based-tests.md)
