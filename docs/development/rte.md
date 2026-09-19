# Rich text preset

The theme ships a rich text preset of its own,
[`Configuration/RTE/Theme.yaml`](../../Configuration/RTE/Theme.yaml), registered
as `theme` and selected for every rich text field. Every class it lets an
editor write is a class of the theme, and a test holds each one to the
compiled stylesheet.

| File                                      | Does                                                                     |
|-------------------------------------------|--------------------------------------------------------------------------|
| `Configuration/RTE/Theme.yaml`            | The preset: toolbar, headings, styles, alignments, table toolbar.        |
| `ext_localconf.php`                       | Registers it as `theme`, only while `rte_ckeditor` is loaded.            |
| `Configuration/PageTsConfig/Rte.tsconfig` | `RTE.default.preset = theme`, imported by `Configuration/page.tsconfig`. |
| `components/_text.scss`                   | The alignment classes the preset writes.                                 |

## Why a preset of its own

The core `Default.yaml` of rte_ckeditor offers Bootstrap's classes: the styles
`p.lead`, `small` and `span.text-muted`, and the alignments `text-start`,
`text-center`, `text-end` and `text-justify`. This theme styles none of those
classes. An editor would choose "Lead" or "centre", the stored markup would
carry the class, and the page would not change — a class in a contract that
matches nothing, the defect [the component library](component-library.md)
exists to rule out. Nothing reports it: the save succeeds, and the frontend
renders exactly what was stored.

So the preset maps each of them to a class the theme styles:

| Offered as     | Writes                       | Styled by                                     |
|----------------|------------------------------|-----------------------------------------------|
| Lead           | `<p class="theme-lead">`     | the text role, `components/_text.scss`        |
| Eyebrow        | `<p class="theme-eyebrow">`  | the text role, `components/_text.scss`        |
| Small print    | `<small>`                    | the element baseline, `base/_elements.scss`   |
| Marked         | `<mark>`                     | the element baseline                          |
| Keyboard input | `<kbd>`                      | the element baseline                          |
| Code           | `<code>`                     | the element baseline                          |
| Left, right    | `theme-text--start`, `--end` | `components/_text.scss`, logical properties   |
| Centre         | `theme-text--center`         | `components/_text.scss`                       |
| Justify        | `theme-text--justify`        | `components/_text.scss`, with `hyphens: auto` |

Left out on purpose:

- **Muted text.** The theme has no muted text role; `--theme-color-text-muted`
  is for dates, hints and the like, set by components, not by an editor.
- **`abbr`.** The baseline styles `abbr[title]`, and a style can set no title, so
  it would write an abbreviation no rule matches. Source editing still
  reaches it.
- **Table and cell properties.** They write inline styles, and `style` is not
  an allowed attribute of the core `Processing.yaml`: the save would strip what
  the editor had just set.

## Justify cannot be left out

The first version of the preset offered three alignments and no justify.
The core does not allow that. `CKEditor5Migrator` runs over every resolved
preset. On v12.4 and v13.4 it is part of EXT:core, and
`Richtext::getConfiguration()` calls it as its last step. While the alignment
plugin is loaded, `handleAlignmentPlugin()` writes all four alignments. An
alignment the preset does not name gets the core's class — `text-justify` for
this one (read on v12.4.45 and v13.4.35). The
functional test found it. The raw YAML never contained it.

The two ways out were to remove the alignment plugin, and with it every
alignment, or to name justify. The preset names it, and
`.theme-text--justify` hyphenates, because a justified column in the reading
measure opens rivers without it. `hyphens: auto` works in the language of the
element, and TYPO3 writes the site language onto `<html lang>`.

The same migrator spells a style without a class as `classes: ['']`, because
CKEditor rejects an empty list. The styles of the preset that write a bare
element — Small print, Marked, Keyboard input, Code — follow the core
`Default.yaml`, whose "Small" is written the same way.

## The imports

```yaml
imports:
  - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Processing.yaml' }
  - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Editor/Base.yaml' }
  - { resource: 'EXT:rte_ckeditor/Configuration/RTE/Editor/Plugins.yaml' }
```

The three core files that carry no style and no alignment: the processing
rules that decide what a save keeps, the editor basics, and the TYPO3 plugins
(soft hyphen, link browser). `Default.yaml` is not imported. The YAML loader
appends a list of an imported file to the list of the importing one, so its
Bootstrap styles would come back next to the theme's.

`Processing.yaml` is the same file on v12.4.45 and v13.4.35 but for its header
comment. The preset inherits whichever the installed core ships.

## Registered only with rte_ckeditor

The theme does not require rte_ckeditor. It suggests it, and the development
instances require it. The registration in `ext_localconf.php` is therefore
guarded:

```php
if (ExtensionManagementUtility::isLoaded('rte_ckeditor')) {
    $GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']['theme'] ??= 'EXT:theme_extension_development/Configuration/RTE/Theme.yaml';
}
```

Unguarded, the preset would name imports that do not resolve.
`YamlFileLoader::processImports()` catches the `YamlFileLoadingException`
(`1485784246`) of such an import and logs it as an error, so the preset would
load without the core processing rules. The core resolves the preset of a rich
text field on **every save** through DataHandler, with or without an editor on
screen: every save of a text element would log an error and be processed by a
preset with no processing rules of its own. The save itself succeeds: with the
guard removed, `RichTextPresetWithoutRteCkeditorTest` fails on the registration
and its save still goes through. Unregistered, the page TSconfig names a preset
that does not exist, and `Richtext::loadConfigurationFromPreset()` returns no
configuration. That is the same as the core's own `default` without
rte_ckeditor.

The registration is the same on v12.4 and v13.4. `Richtext` reads
`$GLOBALS['TYPO3_CONF_VARS']['RTE']['Presets']` on both, and no changelog
entry of either version changes it.

## Selected for the whole installation

`RTE.default.preset = theme` is page TSconfig in `Configuration/page.tsconfig`,
the file the core loads from every active package. The site set's page TSconfig
would reach set sites only, and the static include carries no page TSconfig, so
this is the one place that reaches both delivery paths — the same reason the
backend layouts are registered there. The price is that it applies to every
page tree of the installation, a site that does not use the theme included.

Page TSconfig is assembled in a fixed order (`TsConfigTreeBuilder`, read on
v12.4 and v13.4): the `Configuration/page.tsconfig` of every package first, then
`$GLOBALS['TYPO3_CONF_VARS']['BE']['defaultPageTSconfig']`, then on v13.4 that
of the site — its sets and `config/sites/<site>/page.tsconfig`, which v12.4 does
not have — then the included files and the `TSconfig` field of every page down
the rootline. Later wins, so a foreign site takes its own preset back in any of
the later places:

```typoscript
RTE.default.preset = default
```

A preset named for one field or one type still wins
(`RTE.config.<table>.<field>.preset`, `RTE.config.<table>.<field>.types.<type>.preset`),
and so does a `richtextConfiguration` in the TCA of a field. The core `sys_news`
field keeps its own preset that way.

## Extending it

A site package that wants more writes a preset of its own that imports this one
and adds to its lists:

```yaml
imports:
  - { resource: 'EXT:theme_extension_development/Configuration/RTE/Theme.yaml' }

editor:
  config:
    style:
      definitions:
        - { name: 'Highlight', element: 'span', classes: ['my-highlight'] }
```

It registers that file under a name of its own and selects it with
`RTE.default.preset`. The loader appends lists, so the theme's styles stay and
the new one is added. The package ships the rule for `.my-highlight` itself.
A class added without a rule is the defect this preset was written against,
and the theme's tests do not see a package's preset.

## What the tests guard

| Test                                                    | Fails when                                                                                                          |
|---------------------------------------------------------|---------------------------------------------------------------------------------------------------------------------|
| `Tests/Unit/RichTextPresetTest`                         | a class the preset offers is not a selector of `theme.css`, or a bare element has no rule; an import carries styles |
| `Tests/Functional/RichTextPresetTest`                   | the preset is not registered or not selected, a class of the *resolved* preset is unstyled, a save drops a style    |
| `Tests/Functional/RichTextPresetWithoutRteCkeditorTest` | the preset is registered without rte_ckeditor, or a rich text save fails there                                      |

The unit test reads the YAML as written. The functional test asks the core:
`Richtext::getConfiguration()` for the `bodytext` of a text element, with the
imports merged and the migrator applied. Only the second could see the
`text-justify` the migrator added.

## Not covered

The preset was not opened in an editor. The tests cover registration,
selection, the resolved configuration and what a save keeps. They do not cover
how CKEditor draws the styles in the backend. The editor's content stylesheet
is still the core `contents.css`, not the theme's: `theme.css` declares its
tokens on `:root`, and the editor area is not a document of its own.
