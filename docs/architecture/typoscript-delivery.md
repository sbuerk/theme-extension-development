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

| Path                                            | Is                                                             |
|-------------------------------------------------|----------------------------------------------------------------|
| `Configuration/Sets/ThemeExtensionDevelopment/` | The site set. `config.yaml` is the whole definition. v13 only. |
| `Configuration/TypoScript/`                     | The actual `setup.typoscript` and `constants.typoscript`.      |
| `Configuration/TypoScript/Static/`              | The static include: two guarded files importing the two above. |
| `Configuration/TCA/Overrides/sys_template.php`  | Registers the static include for `sys_template` records.       |

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

Two of the things the theme relies on came into `EXT:frontend` after v12, and
before that they came from `fluid_styled_content` — which this theme
deliberately does not depend on. [`ext_localconf.php`](../../ext_localconf.php)
supplies both, inside one `if ((new Typo3Version())->getMajorVersion() < 13)`
block, and does nothing at all on v13.

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

v13 never reaches that gate: a site set goes through
`SysTemplateTreeBuilder::createSiteTemplateInclude()`, which calls
`addContentRenderingFromGlobals()` unconditionally with no lookup anywhere near
it. The difference has no changelog of its own — neither #103437 nor #103439
mentions `defaultContentRendering` — the two source positions are the whole
evidence.

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

| Test                                    | Proves                                                                                                | Runs on  |
|-----------------------------------------|-------------------------------------------------------------------------------------------------------|----------|
| `SiteSetRenderingTest`                  | A page renders through the set, with **no** `sys_template`.                                           | v13 only |
| `StaticFileIncludeRenderingTest`        | A page renders through the `include_static_file` field — the production path of the classic delivery. | both     |
| `StaticTypoScriptFallbackRenderingTest` | A page renders through the static directory imported into `sys_template.config`.                      | both     |
| `StaticIncludeGuardTest`                | With a set **and** a `sys_template` record, the theme is applied exactly once.                        | v13 only |
| `StaticTypoScriptIncludeTest`           | The static include is registered in the TCA at all.                                                   | both     |
| `ContentElementRenderingTest`           | `header` and `text` render, and the core error notice does not appear.                                | both     |
| `ImageElementRenderingTest`             | The `image` element renders, and its backend fields reach the output.                                 | both     |

`SiteSetRenderingTest` and `StaticFileIncludeRenderingTest` are deliberately the
same three assertions on the two delivery paths, so the paths are held to
delivering the same thing rather than to each working somehow. The static one
also closes a gap that predates this branch: `include_static_file` was
*registered* by a test and *rendered* by none — `StaticTypoScriptFallbackRenderingTest`
takes a different code path, writing `@import` lines into `sys_template.config`
rather than letting `SysTemplateTreeBuilder::handleSingleIncludeStaticFile()`
resolve the registered directory.

The two version specific tests are the only ones in the rendering suite carrying
`#[Group('not-core-12')]`, and both because their **subject** is the site set.
Everything else arranges the theme through
[`ThemeSiteTrait`](../testing/site-based-tests.md#arranging-the-theme-themesitetrait)
and runs on both versions.

Each covers a break that is easy to produce on purpose: renaming the set breaks
`SiteSetRenderingTest`, inverting the guard condition breaks the static ones and
`StaticIncludeGuardTest` in opposite directions, and removing the `?: []`
fallback breaks `StaticFileIncludeRenderingTest` on v12 with the `TypeError`.

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
