# Site settings

A **site setting** is a value an integrator changes per site, in the site's
own `settings.yaml` and, on TYPO3 v13, in *Site Management > Sites* in the
backend. Settings that a set declares are a v13.1 feature, like the sets
themselves; TYPO3 v12.4 reads a site's `settings.yaml` but no
`settings.definitions.yaml`, and has no settings editor. The theme declares its
settings in
[`Configuration/Sets/ThemeExtensionDevelopment/settings.definitions.yaml`](../../Configuration/Sets/ThemeExtensionDevelopment/settings.definitions.yaml)
and **declares each one a second time, with the same default, as a TypoScript
constant** in
[`Configuration/TypoScript/constants.typoscript`](../../Configuration/TypoScript/constants.typoscript).

That duplication is the whole design, so it is worth being precise about why —
and on this branch it is more than a convenience: on TYPO3 v12 the constant is
the only half that exists. `settings.definitions.yaml` is inert there, exactly
like the `config.yaml` beside it.

## Two delivery paths, one template

The theme reaches a site two ways — the site set on TYPO3 v13, and the classic
static include, which is the only way on v12 and supported on v13 (see
[TypoScript delivery](typoscript-delivery.md)). Only the first declares
settings. The core resolves the collision in exactly the direction that makes
one template serve both:

> Set constants will always be overruled by site settings. Since site settings
> always provide a default value, a constant will always be overruled by a
> defined setting.
>
> — *Feature: #103439 — TypoScript provider for sites and sets*, which
> names this as the way an extension stays compatible with a core that has no
> settings.

So:

| The site takes the theme through      | What `{$theme.header.variant}` is                                 |
|---------------------------------------|-------------------------------------------------------------------|
| the **site set** (v13)                | the site setting — its default, or the edited value               |
| the **static include** (v12, and v13) | the constant of `constants.typoscript`, or the value set after it |

Read in `SysTemplateTreeBuilder` on both cores rather than taken from the
changelog alone: on v13.4.35 `createSiteTemplateInclude()` adds the TypoScript
of the sets first and the site settings as constants after it, so the setting
wins; on v12.4.45 and on the `sys_template` path of v13.4.35,
`handleSysTemplateRecordInclude()` adds the site settings of a clearing record
*before* its static templates, so the default this theme's
`constants.typoscript` declares overrules a value of the same key in
`settings.yaml`. **On the static include the constant is set as a constant** —
in the `sys_template` record or in a site package included after the theme —
never in `settings.yaml`.

A template never asks which. `Configuration/TypoScript/Page.typoscript` reads
the constant into a FLUIDTEMPLATE variable, and the partial reads the
variable.

**Not `settings.` of the FLUIDTEMPLATE.** That key is a TypoScript array
assigned per cObject and has nothing to do with site settings — the display
settings already occupy `settings.appearance`. Reading the constant is what
makes the two paths meet.

## Declaring a setting

```yaml
settings:
  theme.header.variant:
    label: 'Header layout'
    description: 'How the site header arranges the title, the navigation and the controls.'
    type: string
    default: simple
    enum:
      simple: 'Simple - title, navigation and controls in one row'
      centred: 'Centred - the title on its own row above a centred navigation'
```

with the same key and the same default in `constants.typoscript`:

```typoscript
theme.header.variant = simple
```

Three rules this extension holds itself to:

- **The key is the constant's path.** `theme.header.variant` is the setting
  and `{$theme.header.variant}` is the constant. Anything else means the two
  paths disagree, and the disagreement shows only on the path nobody tested.
- **The default is written twice and is the same twice.** There is no
  mechanism that checks it;
  `Tests/Unit/SiteSettingsTest` is what checks it.
- **Labels are English literals, not `LLL:` references.** See below.

### Why the labels are not translated

A set *can* ship a `labels.xlf`, and half of that mechanism works on TYPO3
v13.4. The half that does not is the half this extension would need.

Read in `YamlSetDefinitionProvider::createDefinition()` on v13.4.35, the only
core of this branch that reads a set at all, and on v14.3.7 for the version
that changes it:

| What `labels.xlf` gives                                                          | v13.4.35 | v14.3.7 |
|----------------------------------------------------------------------------------|----------|---------|
| The set's own `label`, from the key `label`                                      | yes      | yes     |
| Each setting's `label` and `description`, from keys derived from the setting key | yes      | yes     |
| The **options of an `enum`**, from keys `settings.<key>.enum.<value>`            | no       | yes     |
| A `LLL:` reference written inside an `enum` map, resolved                        | no       | yes     |

The last two arrived with *Feature: #106640 — Localize enum labels in site
settings definitions* in **TYPO3 v14.2**, which this branch does not support.
On v13.4 `enum` is handed to `SettingDefinition` exactly as the YAML wrote it:
a map's values are the labels, verbatim, so a `LLL:` reference reaches the
settings editor spelled out, and a list has integer keys.

The settings that matter here are enums — a header layout and a footer layout
— and their *options* are the text an integrator reads. Translating the label
of a setting while its four options stay English would split one form's
strings across two files and translate the half nobody picks from. So all of
it stays an English literal, and
`SiteSettingsTest::noLabelIsATranslationReference` refuses a `LLL:` one.

This is a version difference the theme *accepts* rather than splits, because
it is configuration — see
[Core version aware code § Configuration is the exception](core-version-aware-code.md#configuration-is-the-exception).
There is no `@todo`, because nothing here breaks when the floor moves; the
`@todo` belongs on the day somebody wants the labels translated.

### Types

`Classes/Settings/Type/` on v13.4.35 ships `bool`, `color`, `int`, `number`,
`page`, `stringlist`, `string` and `text`. This extension uses `string` and
`int`. v12.4 has no setting types — the constant is untyped there.

**A setting that names a link is an `int` page uid here, not a string.** The
`actions` header's call to action is the example: its destination goes into an
`href`, and a string a form accepts would accept `javascript:` with it. `int`
is validated by the core's `IntType`, so the *settings editor* cannot deliver
a scheme.

It narrows the form, not every path: the TypoScript constant of the same name
is free text like every constant, so a site package can still write anything
into it — and on TYPO3 v12 the constant is the only path there is. That is
integrator-level trust, the same every constant already carries; what the type
buys is the one editor-facing path that would otherwise not have it. The cost
— the call to action cannot leave the site — is the deliberate half of the
trade.

## A value nobody planned for

A setting is edited in a form, and a form is where a value nobody looks at
twice comes from. Two rules follow:

- **A setting never becomes part of a path.**
  `f:render partial="Page/Header/{headerVariant}"` is the short way to write
  a variant switch and turns an editable value into a file system lookup.
  `Partials/Page/Header.html` names its four partials as literals in an
  `f:switch`, so a value nothing matches renders the default.
  `SiteHeaderVariantRenderingTest::aValueTheSwitchDoesNotKnowRendersTheDefaultHeader`
  is that assertion.
- **A half-configured feature renders nothing, not half of itself.** The call
  to action needs both of its settings; with one it is a button with no
  destination or a button with no name, and either is worse than no button.

## Testing a setting

A functional test hands the settings to `ThemeSiteTrait::setUpThemeSite()`
and lets the delivery of the running core decide where they go — the same
seam that decides between the set and the static include
(`Tests/Functional/ThemeDeliveryInterface.php`):

- On **TYPO3 v13** `Core13\ThemeDelivery` writes them into the site
  configuration as a **map** of full setting keys,
  `$site['settings'] = ['theme.header.variant' => 'centred']`, which is the
  format the core stores and advises since TYPO3 v13.4 (*Important: #106894 —
  Site settings.yaml is now stored as a map*). The older tree form is still
  read and is deliberately not used.
- On **TYPO3 v12** `Core12\ThemeDelivery` writes them as constants into the
  `sys_template` record that selects the static include,
  `theme.header.variant = centred` — where an integrator on v12 writes them.

So `SiteHeaderVariantRenderingTest` proves the setting on v13 and the constant
alone on v12, each on the path that version actually has.

**A test class that writes the site more than once has to flush the caches
between the writes.** Both the resolved site and the TypoScript built from its
settings are cached, and without the flush every test after the first renders
the first one's configuration — green, and measuring nothing.
`SiteHeaderVariantRenderingTest` does it in the helper that writes the site.

## See also

- [TypoScript delivery](typoscript-delivery.md) — the site set, the static
  include fallback and how the two share their files.
- [Core version aware code](core-version-aware-code.md)
- [Component library § Header variants](../development/component-library.md#header-variants)
