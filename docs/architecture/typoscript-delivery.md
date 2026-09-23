# TypoScript delivery

The theme ships its TypoScript twice over: as a **site set** and as a **classic
static include**. Both read the same files.

On a v12/v13 branch those two are not a preference and a fallback. **Site sets
are a TYPO3 v13.1 feature** (#103437, "Introduce site sets", with #103439 for
the TypoScript provider that makes a set deliver `setup.typoscript`), so:

| Core version | Site set            | Static include |
|--------------|---------------------|----------------|
| v12.4        | —                   | the only way   |
| v13.4        | the recommended way | supported      |

Half the supported installations reach the theme exclusively through the static
include. It is a first class delivery path here, tested as such, and the way
`instance-core-12/` and every functional test on v12 enable the theme.

| Path                                            | Is                                                                                                                                  |
|-------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------|
| `Configuration/Sets/ThemeExtensionDevelopment/` | The site set: `config.yaml`, and the [site settings](site-settings.md) in `settings.definitions.yaml`. v13 only; both inert on v12. |
| `Configuration/TypoScript/`                     | The actual `setup.typoscript` and `constants.typoscript`.                                                                           |
| `Configuration/TypoScript/Static/`              | The static include: two guarded files importing the two above.                                                                      |
| `Configuration/TCA/Overrides/sys_template.php`  | Registers the static include for `sys_template` records.                                                                            |

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

A site on TYPO3 v13 enables the theme by depending on it:

```yaml
dependencies:
  - sbuerk/theme-extension-development
```

Both `typoscript` and `optionalDependencies` are properties of `SetDefinition`
itself (`Classes/Site/Set/SetDefinition.php` of `typo3/cms-core`) — verified
there rather than taken from the documentation — so the set is plain
configuration with no version aware code behind it.

On TYPO3 v12 the key is read by nothing at all. It does not error, it is simply
inert, which is the reason `instance-core-12/config/sites/demo/config.yaml`
deliberately does **not** carry it: a `dependencies` key there would claim the
file enables the theme when it does not.
→ [Development instances](../development/instances.md#enabling-the-theme-on-typo3-v12)

## The classic static include

A `sys_template` record whose *Include static (from extensions)* field
(`include_static_file`) names the registered directory delivers the same
TypoScript:

```
EXT:theme_extension_development/Configuration/TypoScript/Static
```

The value is the **directory**, without a trailing slash and without a file
name. `SysTemplateTreeBuilder::handleSingleIncludeStaticFile()` appends
`constants.typoscript` and `setup.typoscript` itself, which is also why both
files have to live in the same directory.

This is the only delivery path on TYPO3 v12, and it is what
`Tests/Functional/Core12/ThemeDelivery` arranges for every rendering test there
— see [Site based tests](../testing/site-based-tests.md#arranging-the-theme-themesitetrait).

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
[not ('sbuerk/theme-extension-development' in (site('sets') ?: []))]
    @import 'EXT:theme_extension_development/Configuration/TypoScript/setup.typoscript'
[END]
```

`site('sets')` resolves through `Site::getSets()`, so the guard is a TypoScript
condition with no version aware code behind it.

### The `?: []` is not defensive noise

It is the whole reason the frontend works on TYPO3 v12, and it looks like
clutter to anyone who only knows v13. The chain is short and every link was
read in the installed core:

1. v12's `Site` entity has no `getSets()`, so `site('sets')` resolves to `NULL`.
2. Symfony's ExpressionLanguage compiles `in` to
   `in_array($left, $right, true)`, which raises a **`TypeError`** for `NULL`.
3. `IncludeTreeConditionMatcherVisitor` catches only `SyntaxError` and
   `\RuntimeException`, so the `TypeError` is not contained.

The result is not a condition that evaluates false — it is **every frontend
request that loads this file dying**. Falling back to an empty array makes the
condition answer "no set is active", which is the only truthful answer on a
version that has no sets. `?:` exists in the Symfony ExpressionLanguage of
v12's floor, so one spelling serves both versions and no split is needed.

The file carries a `@todo` to drop the fallback when v12 support is dropped.
`StaticFileIncludeRenderingTest` is the regression test: on v12 it fails with
the `TypeError` if the fallback is removed, and on v13 it exercises the fallback
delivery path.

One limit worth knowing: `site('sets')` lists the sets a site declares
**itself**. A theme pulled in as a transitive dependency of another set is not
listed, and the guard would not suppress the static include in that case.
Enabling the theme through its own set — the documented way — is covered.

## Why `addStaticFile()` lives in a TCA override

`ExtensionManagementUtility::addStaticFile()` appends an item to
`$GLOBALS['TCA']['sys_template']['columns']['include_static_file']` and is
guarded by `is_array()` on that column. Called from `ext_localconf.php`, where
the TCA does not exist yet, it therefore does **nothing at all** — silently.

It belongs in `Configuration/TCA/Overrides/sys_template.php`, which TYPO3 loads
once the TCA exists. `ext_tables.php` is the historical location and is not used
here: it is loaded on every request in both frontend and backend, and TCA
overrides are the file type TYPO3 caches and loads for exactly this purpose.

`Tests/Functional/StaticTypoScriptIncludeTest` asserts the registration, because
"registered" and "silently absent" are otherwise indistinguishable.

## Page rendering

`page.10` is a `FLUIDTEMPLATE`, not a `PAGEVIEW`. `FLUIDTEMPLATE` keeps full
control over `templateRootPaths`, `partialRootPaths` and `layoutRootPaths`, and
the page rendering layer therefore needs no `Core<major>/` split.

`PAGEVIEW` is the newer object and its content area API is genuinely nicer — but
`PAGEVIEW` itself arrived in v13.1 and does not exist on v12 at all, and
`contentAs`, `f:render.contentArea`, `f:render.record` and `f:page.headerData`
do not exist on v13.4 either, verified against the installed
`.Build/vendor/typo3/cms-fluid`. So there is nothing to gain and one version to
lose. A split in the template layer would also be the most awkward kind: Fluid
files live in `Resources/` and cannot be selected by the container the way
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

On **v13**, reading `EXT:frontend/Configuration/TCA/tt_content.php` shows a
`types` array of `1`, `header`, `text` and `list`, which reads like the complete
list of content types. It is not. The same extension ships **22 files** in
`Configuration/TCA/Overrides/`, among them
`225-tt_content-content_type-image.php` and
`230-tt_content-content_type-textmedia.php`, each calling
`ExtensionManagementUtility::addRecordType()`. Verified against `v13.4.0` and
`v13.4.34`; the set is identical across the patch levels.

On **v12** the same information is in one place: `types` in
`EXT:frontend/Configuration/TCA/tt_content.php` declares all of them inline —
`1`, `bullets`, `div`, `header`, `text`, `textpic`, `textmedia`, `image`,
`html`, `list`, the eleven `menu_*` types, `shortcut`, `table`, `uploads` — and
`Configuration/TCA/Overrides/` holds only `sys_reaction.php`. The set of CTypes
is the same; only where it is written down changed.

`fluid_styled_content`'s own `Configuration/TCA/Overrides/` holds exactly one
file, `sys_template.php`, which registers its static include. **It contributes no
content type TCA at all.** (Checked on v13; the extension is not a dependency
here, so it is not in this checkout on either version.)

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

`list` is the legacy plugin type, deprecated in v13 (#105076) and not deprecated
at all on v12, but present in the TCA of both. It is rendered too, not skipped:
any third-party Extbase
plugin still registered the old way needs a `tt_content.list` object to render
through, the same way `configurePlugin()`'s default `CType` registration needs
`Generic.html` — see
[Content elements](content-elements.md#extbase-plugins-and-tt_contentlist) for
both.

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

The bridge makes the combination **defined**, and it is delivered the same two
ways the theme itself is, both reading
`Configuration/TypoScript/Fsc/setup.typoscript`:

| Core version | Bridge set                               | Bridge static include                             |
|--------------|------------------------------------------|---------------------------------------------------|
| v12.4        | — (no site sets, and fsc 12.4 has none)  | the only way                                      |
| v13.4        | `sbuerk/theme-extension-development-fsc` | supported, and the one a `sys_template` site uses |

On v13 a site enables it in place of the theme's own set:

```yaml
dependencies:
  - sbuerk/theme-extension-development-fsc
```

On v12 it is the static template *Theme Extension Development
(fluid_styled_content)*, selected last in `include_static_file`. `fluid_styled_content`
12.4 ships no `Configuration/Sets/` at all, so there is no
`typo3/fluid-styled-content` set to depend on there even if the mechanism
existed; the set file beside the TypoScript is inert on v12, exactly as the
theme's own set is.

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
  same two indices in its **12.4 and 13.4** releases, read in both rather than
  assumed from one, and both ship those templates as `*.html`. Fluid tries root
  paths from the highest index down — the core sorts them by integer key and
  the engine walks them reversed — so at `5` the theme's templates beat that
  extension's while an integrator's documented override still beats both.
  Standalone this is the same single entry it always was.
- **`optionalDependencies`, not `dependencies`** — on v13, where the set is
  read at all. A missing entry under `dependencies` makes the whole set
  invalid: `SetRegistry::computeOrderedSets()` drops it and logs an error, so a
  hard dependency would turn this set into an error message in every
  installation of the theme that does not have that extension. An optional one
  is skipped when absent and, when present, both orders the theme after it and
  activates it for the site (`hasDependency()` counts optional dependencies).

`tt_content.list` is deliberately **not** cleared. It is the historical plugin
CType, and `configurePlugin()` writes straight into it through
`defaultContentRendering` on v12.4 and v13.4 alike, at a point this file cannot
see — clearing it could drop a third-party plugin's own registration, a worse
failure than the leftover it would prevent. Both versions still carry the CType
in `EXT:frontend`'s TCA; it is deprecated on 13.4 (#105076) and not deprecated
at all on 12.4.

On the static path the order is the integrator's, and the bridge has to be
**last**, after both `Fluid Content Elements` and `Theme Extension Development`.

`FluidStyledContentBridgeTest` holds the byte-for-byte promise on that path, on
both versions, with a control pair that drives the two broken load orders
through `include_static_file` — the one place the order is specified.
`Core13/FluidStyledContentBridgeSetTest` does the same on the set path and is
`#[Group('not-core-12')]` for the reason `SiteSetRenderingTest` is: its subject
is the set. It sits below `Core13/` because it names `SetRegistry`, which v12
does not have, and the v12 PHPStan configuration excludes that directory.

## Plugins, and the static include as a content rendering template

`ExtensionUtility::configurePlugin()` adds the rendering of every plugin
`CType` with `addTypoScript(…, 'defaultContentRendering')`. The two delivery
paths treat that key differently (`SysTemplateTreeBuilder`, read on v12.4 and
v13.4):

- a site using sets gets it unconditionally
  (`createSiteTemplateInclude()`) — v13 only, v12 has no sets;
- a `sys_template` site gets it only right after a static include listed in
  `$GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates']`
  (`addStaticMagicFromGlobals()`) — the slot `fluid_styled_content` fills for
  the installations that use it.

[`ext_localconf.php`](../../ext_localconf.php) registers the theme's static
include there on both core versions, as
`themeextensiondevelopment/Configuration/TypoScript/Static/`: the extension key
without underscores and the registered path with a trailing slash, the string
the tree builder derives from an `include_static_file` entry.

It used to do that for v12 only, where the static include is the only delivery
path, on the reasoning that v13 delivers through the set. A v13 site using the
static include — the `/legacy/` tree of the development instance is one — then
rendered every classic and every `theme_*` element and let every plugin,
EXT:felogin's login form among them, fall through to the "no rendering
definition" notice.

It only takes effect for an `include_static_file` entry. A test that imports the
two static files directly into a `sys_template` renders the theme without any
plugin rendering, which is why `ExtbasePluginStaticIncludeRenderingTest` and the
static case of `FeloginRenderingTest` set `include_static_file`. Both run on
both core versions; `ExtbasePluginRenderingTest` and the other case of
`FeloginRenderingTest` cover the set path on v13 and the static include on v12,
through
[`ThemeSiteTrait`](../testing/site-based-tests.md#arranging-the-theme-themesitetrait).

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

- `styles.content.get`, the `tt_content = CASE` skeleton, `FilesProcessor` and
  `GalleryProcessor` — on both supported versions
- `lib.parseFunc` and `lib.parseFunc_RTE`, so `<f:format.html>` parses rich text
  without FSC — **from v13.2 only** (#103485). See below.

`header_layout` is honoured, including the value **100**, which the core TCA
offers as "do not display". A theme ignoring it would render headings an editor
had deliberately hidden.

### What v12 does not give for free: `ext_localconf.php`

Two of the things the theme relies on are handled differently on v12, where
they came from `fluid_styled_content` — which this theme deliberately does not
depend on. [`ext_localconf.php`](../../ext_localconf.php) supplies the first
inside one `if ((new Typo3Version())->getMajorVersion() < 13)` block. The
second, the content rendering template, is registered on both core versions:
v12 needs it for every site, v13 for every site using the static include.

It is a **configuration exception**, not a rule violation: `ext_localconf.php`
is loaded by TYPO3 from a fixed path, long before a container exists, so the
`Core<major>/` split is not available to it. The version test is the same
expression `Configuration/Services.php` and `ThemeSiteTrait` use, and each block
carries its `@todo`.
→ [Configuration is the exception](core-version-aware-code.md#configuration-is-the-exception)

**1. `lib.parseFunc` / `lib.parseFunc_RTE`.** Without them `<f:format.html>` —
which every rich text field in this theme goes through — throws
`LogicException: Invoked ContentObjectRenderer::parseFunc without any
configuration`. Measured on the v12 leg before the file existed: 65 of 245
functional tests errored with exactly that. The TypoScript is **copied byte for
byte** from the block v13.4's own `EXT:frontend/ext_localconf.php` registers,
into `Configuration/TypoScript/Compatibility/Core12/ParseFunc.typoscript`, so
the two versions parse rich text identically rather than similarly. Only the
`lib.parseFunc*` half is taken — v12 registers `styles.content.get` and the
`tt_content = CASE` default itself, and re-registering those would overwrite a
`tt_content` other extensions may have contributed to.

**2. The theme registers itself in `FE.contentRenderingTemplates`.** This is the
non-obvious one. `ExtensionUtility::configurePlugin()` registers a plugin's
rendering TypoScript through `addTypoScript(…, 'defaultContentRendering')`, and
on v12 *every* path that includes that array first checks whether the static
include being processed is listed in
`$GLOBALS['TYPO3_CONF_VARS']['FE']['contentRenderingTemplates']`. That array is
empty by default and is normally filled by `fluid_styled_content`. Without the
registration, no Extbase plugin in the installation has a rendering definition
on v12 at all — `ExtbasePluginRenderingTest` failed all three of its tests.

A v13 site set never reaches that gate: it goes through
`SysTemplateTreeBuilder::createSiteTemplateInclude()`, which calls
`addContentRenderingFromGlobals()` unconditionally with no lookup anywhere near
it. The difference has no changelog of its own — neither #103437 nor #103439
mentions `defaultContentRendering` — the two source positions are the whole
evidence. A v13 `sys_template` site still goes through the gate, which is why
the registration is not inside the v12 block — see
[above](#plugins-and-the-static-include-as-a-content-rendering-template).

The registered identifier is not a free label. The core builds it from the
static include a `sys_template` selects, as
`str_replace('_', '', $extensionKey) . '/' . $path . '/'`, so the entry has to
be the string the core will build:

```
themeextensiondevelopment/Configuration/TypoScript/Static/
```

Worth naming, because it is user visible: this declares the theme to be *a*
content rendering definition of the installation — which it is, it defines
`tt_content` for every element it ships plus `lib.contentElement`. An
installation that also installs `fluid_styled_content` then has two, and the
later static include wins per object path. That trade is not v12 specific; every
site package makes it.

> [!NOTE]
> Developer notes in a Fluid template belong in `<f:comment>`, not in an HTML
> comment. Fluid strips the former and renders the latter into the response —
> this was found the hard way, when a test asserting the absence of the core
> error notice matched a template comment that merely *described* it.

## What the tests cover

| Test                                              | Proves                                                                                                | Runs on  |
|---------------------------------------------------|-------------------------------------------------------------------------------------------------------|----------|
| `SiteSetRenderingTest`                            | A page renders through the set, with **no** `sys_template`.                                           | v13 only |
| `StaticFileIncludeRenderingTest`                  | A page renders through the `include_static_file` field — the production path of the classic delivery. | both     |
| `StaticTypoScriptFallbackRenderingTest`           | A page renders through the static directory imported into `sys_template.config`.                      | both     |
| `StaticIncludeGuardTest`                          | With a set **and** a `sys_template` record, the theme is applied exactly once.                        | v13 only |
| `StaticTypoScriptIncludeTest`                     | The static include is registered in the TCA at all.                                                   | both     |
| `ContentElementRenderingTest`                     | `header` and `text` render, and the core error notice does not appear.                                | both     |
| `ThemeContentElementObjectTest`                   | Every `theme_*` element renders the same with `lib.contentElement` cleared, both ways.                | both     |
| `ImageElementRenderingTest`                       | The `image` element renders, and its backend fields reach the output.                                 | both     |
| `ExtbasePluginStaticIncludeRenderingTest`         | An Extbase plugin renders through the `include_static_file` field, not only through the set.          | both     |
| `FeloginRenderingTest`                            | The login form renders on the form contract, through the theme delivery and the static include.       | both     |
| `DevelopmentInstance/LegacyDeliveryTest`          | The seeded showcase renders the same markup in its two trees, page by page.                           | both     |
| `DevelopmentInstance/DeliveryRegistrationTest`    | Every static include of every seeded `sys_template` root resolves and is registered.                  | both     |
| `Core12/DevelopmentInstance/InstanceDeliveryTest` | Both tree roots of the v12 instance carry the `sys_template` record, and no site declares a set.      | v12 only |
| `FluidStyledContentBridgeTest`                    | The bridged page is byte for byte the page the theme renders alone, on the static include path.       | both     |
| `Core13/FluidStyledContentBridgeSetTest`          | The same on the site set path, and that the bridge set really activates that extension.               | v13 only |

`SiteSetRenderingTest` and `StaticFileIncludeRenderingTest` are deliberately the
same three assertions on the two delivery paths, so the paths are held to
delivering the same thing rather than to each working somehow. The static one
also closes a gap that predates this branch: `include_static_file` was
*registered* by a test and *rendered* by none — `StaticTypoScriptFallbackRenderingTest`
takes a different code path, writing `@import` lines into `sys_template.config`
rather than letting `SysTemplateTreeBuilder::handleSingleIncludeStaticFile()`
resolve the registered directory.

The three version specific tests are the only ones in the rendering suite
carrying `#[Group('not-core-12')]`, and all three because their **subject** is
the site set. Everything else arranges the theme through
[`ThemeSiteTrait`](../testing/site-based-tests.md#arranging-the-theme-themesitetrait)
and runs on both versions.

Each covers a break that is easy to produce on purpose: renaming the set breaks
`SiteSetRenderingTest`, inverting the guard condition breaks the static ones and
`StaticIncludeGuardTest` in opposite directions, removing the `?: []`
fallback breaks `StaticFileIncludeRenderingTest` on v12 with the `TypeError`,
and dropping `optionalDependencies` from the bridge set breaks
`Core13/FluidStyledContentBridgeSetTest` on the one assertion the rendered
markup cannot make.

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
