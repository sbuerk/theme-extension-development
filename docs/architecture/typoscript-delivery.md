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
in `Configuration/TCA/Overrides/`, among them
`225-tt_content-content_type-image.php` and
`230-tt_content-content_type-textmedia.php`, each calling
`ExtensionManagementUtility::addRecordType()`. Verified against `v13.4.0`,
`v13.4.34` and `v14.3.6`; the set is identical, the only difference being the
tab label short form v14 introduced (#107789).

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
(#105377), so it is deliberately not rendered.

Rendered so far: `header`, `text` and `image`. The rest carry a `@todo` in
`ContentElements.typoscript`; none of them needs TCA of this extension's own.

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
