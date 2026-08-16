# TypoScript delivery

The theme ships its TypoScript twice over: as a **site set**, which is the way
it is meant to be enabled, and as a **classic static include**, for
installations that do not use site sets. Both read the same files.

| Path                                            | Is                                                             |
|-------------------------------------------------|----------------------------------------------------------------|
| `Configuration/Sets/ThemeExtensionDevelopment/` | The site set. `config.yaml` is the whole definition.           |
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

A site enables the theme by depending on it:

```yaml
dependencies:
  - sbuerk/theme-extension-development
```

Both `typoscript` and `optionalDependencies` are properties of `SetDefinition`
itself (`.Build/vendor/typo3/cms-core/Classes/Site/Set/SetDefinition.php`, lines
34 and 37) — verified there rather than taken from the documentation — so the
set is plain configuration with no version aware code behind it.

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

`site('sets')` resolves through `Site::getSets()`, so the guard is a TypoScript
condition with no version aware code behind it.

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
`contentAs`, `f:render.contentArea`, `f:render.record` and `f:page.headerData`
**do not exist on v13.4**, verified against the installed
`.Build/vendor/typo3/cms-fluid`. A split in the template layer would also be the
most awkward kind: Fluid files live in `Resources/` and cannot be selected by
the container the way classes are.

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
`1`, `header`, `text` and `list`, which reads like the complete list of content
types. It is not. The same extension ships **22 files** in
`Configuration/TCA/Overrides/`, among them
`225-tt_content-content_type-image.php` and
`230-tt_content-content_type-textmedia.php`, each calling
`ExtensionManagementUtility::addRecordType()`. Verified against `v13.4.0` and
`v13.4.34`; the set is identical across the patch levels.

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

`list` is the legacy plugin type, deprecated in v13 (#105076) but still present
in the v13.4 TCA. It is rendered too, not skipped: any third-party Extbase
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

| Test                                    | Proves                                                                 |
|-----------------------------------------|------------------------------------------------------------------------|
| `SiteSetRenderingTest`                  | A page renders through the set, with **no** `sys_template`.            |
| `StaticTypoScriptFallbackRenderingTest` | A page renders through the static include, with no set.                |
| `StaticTypoScriptIncludeTest`           | The static include is registered in the TCA at all.                    |
| `ContentElementRenderingTest`           | `header` and `text` render, and the core error notice does not appear. |
| `ImageElementRenderingTest`             | The `image` element renders, and its backend fields reach the output.  |

The first two cover the two branches of the guard condition. Both were shown to
fail: renaming the set breaks the first, inverting the condition breaks the
second.

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
