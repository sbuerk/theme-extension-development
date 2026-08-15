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

**Only two content types exist to render.** EXT:frontend registers `header` and
`text` and nothing else — verified by reading the `CType` items of its
`tt_content` TCA on both versions:

| Version | CTypes registered by EXT:frontend |
|---------|-----------------------------------|
| v13     | `header`, `text`, `list`          |
| v14     | `header`, `text`                  |

Everything a TYPO3 installation normally offers — textmedia, image, bullets,
table, uploads, menus, html — belongs to `fluid_styled_content`. Those are not
elements this theme has chosen not to render; they do not exist here at all, and
bringing them means registering content types **and their TCA** in this
extension.

`list` is the legacy plugin type, deprecated in v13 (#105076) and removed in v14
(#105377), so it is deliberately not rendered.

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

The first two cover the two branches of the guard condition. Both were shown to
fail: renaming the set breaks the first, inverting the condition breaks the
second.

## See also

- [Core version aware code](core-version-aware-code.md)
- [Frontend assets](../development/frontend-assets.md)
- [Site based tests](../testing/site-based-tests.md)
