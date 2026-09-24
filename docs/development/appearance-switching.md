# Appearance switching

Light / dark, the five colour palettes and the content-element outline are all
CSS-only — [`DESIGN.md`](../../DESIGN.md) and
[Component library § The two switches](component-library.md#the-two-switches)
cover that contract. This page covers what sits on top of it: the TypoScript
that renders a server-side default for all three, the inline script that
applies a stored choice before first paint, and the display settings — a
Fluid partial and a module script — that let a visitor change any of it. The
implementation is
[`Configuration/TypoScript/Appearance.typoscript`](../../Configuration/TypoScript/Appearance.typoscript),
[`Resources/Public/JavaScript/theme.js`](../../Resources/Public/JavaScript/theme.js),
[`Resources/Private/Partials/Page/Settings.html`](../../Resources/Private/Partials/Page/Settings.html)
and
[`Resources/Private/Scss/components/_settings.scss`](../../Resources/Private/Scss/components/_settings.scss).

## Three constants, not site settings

```typoscript
theme.appearance.default        = auto
theme.appearance.palette        = neutral
theme.appearance.contentOutline = on
```

| Constant                          | Values                                        | Default   |
|-----------------------------------|-----------------------------------------------|-----------|
| `theme.appearance.default`        | `auto`, `light`, `dark`                       | `auto`    |
| `theme.appearance.palette`        | `neutral`, `ember`, `ocean`, `moss`, `violet` | `neutral` |
| `theme.appearance.contentOutline` | `on`, `off`                                   | `on`      |

These are TypoScript constants under the existing `theme { }` block in
[`Configuration/TypoScript/constants.typoscript`](../../Configuration/TypoScript/constants.typoscript),
not entries in a `settings.definitions.yaml`. That is deliberate: a constant is
read identically by the site set and by the classic `sys_template` static
include, which is the property this whole theme is built around — see
[TypoScript delivery](../architecture/typoscript-delivery.md). A site settings
definition would only serve the site set half of that pair and would become a
second source of truth for the same value the moment someone edited the
constant instead.

A site package that wants these editable in the Site Settings UI can still
declare its own `settings.definitions.yaml` in its own set — nothing here
prevents that, it is just not what this theme ships.

All three are **defaults**. A visitor can change each of them in the display
settings, and that choice wins in their browser until they reset it.

## Server-rendered attributes

`config.htmlTag.attributes.*` is the only mechanism that can carry these onto
the `<html>` tag from TypoScript, and it comes with a constraint that decides
how the constants above are used:

```typoscript
config.htmlTag.attributes.data-palette = {$theme.appearance.palette}
config.htmlTag.attributes.data-theme-content-outline = {$theme.appearance.contentOutline}

["{$theme.appearance.default}" != "auto"]
    config.htmlTag.attributes.data-theme = {$theme.appearance.default}
[END]
```

`RequestHandler::generateHtmlTag()` iterates `htmlTag.attributes.*` and writes
each value onto the tag **raw**: no `stdWrap` runs on an attribute at all, and
the one `stdWrap` of the method, `htmlTag_stdWrap`, runs over the finished tag.
Verified in source rather than from the TypoScript reference —
`.Build/vendor/typo3/cms-frontend/Classes/Http/RequestHandler.php`,
`generateHtmlTag()` at line 833 of v13.4.35 and 880 of v12.4.45, whose loop at
line 837 (884 on v12.4.45) concatenates `htmlspecialchars()` of the name and
the value and nothing else; `htmlTag_stdWrap` follows at lines 853–855
(900–902). A constant is therefore the only thing that can be assigned here; a
cObject would need the `stdWrap` this code path never runs.

`data-theme` is behind a condition rather than a plain assignment, for a
different reason: `auto` is the **absence** of the attribute, not a value for
it. No selector in `abstracts/_tokens.scss` matches `data-theme="auto"`, so
rendering it would look like a deliberate, working choice while doing
nothing — the operating system already decides via `color-scheme` once no
attribute is present. `theme.appearance.palette` needs no such condition:
unlike `auto`, `neutral` has no "let something else decide" case — it is
simply what `_tokens.scss` already declares — so it is rendered
unconditionally even though it is also the default.

Constants are substituted into setup conditions before they are evaluated
(`TYPO3\CMS\Core\TypoScript\IncludeTree\Visitor\IncludeTreeSetupConditionConstantSubstitutionVisitor`),
so the condition above reads `theme.appearance.default` the same way the
unconditional assignments do.

## The server defaults reach the template too

```typoscript
page.10.settings.appearance {
    default = {$theme.appearance.default}
    palette = {$theme.appearance.palette}
    contentOutline = {$theme.appearance.contentOutline}
}
```

The same three constants are handed to the page template as FLUIDTEMPLATE
`settings` (`FluidTemplateContentObject` assigns `settings.` to the view as
`settings`). `Partials/Page/Settings.html` uses them twice:

- for the **initially checked** controls — the truth for a visitor who has
  stored nothing;
- as **`data-theme-default-appearance`, `data-theme-default-palette` and
  `data-theme-default-content-outline`** on the root element of the control,
  which is what "Reset" restores.

The second use is why the defaults are rendered at all. The html tag cannot
answer the question: once the no-flash script has applied a stored choice,
the server's value is no longer on it, and a reset reading the tag would
restore the visitor's own choice. Re-deriving the default in the script would
mean a second copy of the constants in JavaScript. Rendering them next to the
control is the one place both the template and the script can read from the
same source.

The assignment lives in `Appearance.typoscript`, while the FLUIDTEMPLATE
itself is set up in `Page.typoscript`, which `setup.typoscript` imports
afterwards. That is harmless: `page.10 = FLUIDTEMPLATE` there assigns the
object type and leaves properties assigned earlier in place.
`Tests/Functional/ConfiguredAppearanceRenderingTest` renders with every
constant changed and asserts both uses, through the static include.

## The no-flash script

An inline script in the document head, emitted through `page.headerData`
rather than `f:asset.script`, because the asset collector is free to move a
script to the end of the body — and a stored dark appearance would then paint
light first, which is the exact flash this script exists to prevent.

It does two things, in this order, and the order is load-bearing:

1. sets `data-js` on the root, **unconditionally and first**;
2. reads the stored appearance, palette and content outline from
   `localStorage` and applies them, each wrapped in its own `try`/`catch`.

```js
var root = document.documentElement;

// First act, unconditionally: nothing below may run before this,
// and nothing below may prevent it from having run.
root.setAttribute('data-js', '');

try {
    var appearance = window.localStorage.getItem('theme-appearance');
    // …
} catch (e) {
    // Storage threw. Same outcome: the server-rendered default
    // stands, and "data-js" above is already set.
}
```

`localStorage` throws rather than returning `null` in Safari's private mode
and when cookies are blocked. An uncaught throw here would abort the script —
which is exactly why `data-js` is set before any `try` block runs, not after:
if the order were reversed, a throwing read could leave the page without the
marker entirely.

The outline is read here like the other two, not by the module script: applied
after first paint, every page of a visitor who switched the outlines off would
draw them for a moment. Only `on` and `off` are accepted; anything else leaves
the server-rendered value standing.

Losing the marker is not a cosmetic gap. `data-js` is what
`components/_nav-main.scss` gates the navigation collapse behind and what
`components/_settings.scss` gates the settings control's visibility behind
(see the next section). Without it the main navigation stays in its no-script
layout — always expanded, no working toggle — and the cog stays hidden, on
every page, for the rest of that visit. The navigation is still usable in that
state (it is the same layout a visitor with JavaScript disabled sees), but the
enhancement and the settings are both gone, silently, until a page load that
does not hit the storage exception.

## The settings control is hidden until `data-js`

```scss
.theme-settings {
    display: none;

    [data-js] & {
        display: inline-flex;
    }
}
```

The whole control — the cog and its panel — is hidden until the root carries
`data-js`, the same gate `_nav-main.scss` uses for the navigation toggle and
for the same reason: the cog discloses nothing and no choice applies without
the module script, and there is no native fallback the way a plain link or a
`<details>` element would give one. A control that cannot work is worse than
no control — a visible promise the page cannot keep — so the undecorated state
is "not there at all", not "there but inert".

## The settings control

One 44px cog button at the end of the site header replaces the two button
groups that used to sit there. Those took so much of the row that the site
title and the main navigation were left with almost no room; the title wrapped
onto five lines at 1280px. The panel it opens holds:

- **Appearance** — Auto, Light, Dark, as a segmented control (`.theme-segmented`);
- **Palette** — the five palettes as a list, each with its primary and
  secondary colour (`.theme-swatch-list`, `.theme-swatch`);
- **Element outlines** — a switch (`.theme-switch`, see
  [Component library § Forms](component-library.md#forms)) for the
  content-element outline, which could only be set with the constant before;
- **Reset** — back to the server defaults.

It is a **disclosure, not a menu**. The trigger is a plain button with
`aria-expanded` and `aria-controls`, the panel holds two radio groups and a
checkbox, each group a `fieldset` named by its `legend`. There is no
`role="menu"`: radios and a checkbox bring their own keyboard behaviour — Tab
between the groups, the arrow keys within one — and their own announced
state, which the ARIA menu pattern would have to rebuild by hand. The radios
stay in the document, stretched over their labels at zero opacity, so the
browser still owns focus, the checked state and the pointer target; the label
only draws that state, read off the input with `:has()`. The cog's name is a
visually hidden text rather than an `aria-label`, so it is translated with the
rest of the page.

The panel is positioned under the cog and right aligned, never wider than the
viewport less a gutter on either side, stacked with `--theme-z-overlay` and
flat like everything else: `--theme-color-surface-raised` inside a
`--theme-color-border-strong` border, no shadow — `DESIGN.md` has no elevation
token.

The panel's id and the radio `name`s derive from the partial's optional
`idPrefix` argument, `theme-settings` by default: `theme-settings-panel`,
`theme-settings-appearance`, `theme-settings-palette`,
`theme-settings-content-outline`. A second instance on a page passes its own
prefix; the script binds every `.theme-settings` and keeps them all in step.

### Forced colours

In a forced colours mode — Windows' contrast themes — the browser replaces
author colours with system colours and drops gradients. Measured in Chromium
with forced colours emulated, before the rules below: the checked segment
computed to the same `Canvas` as the track it sits on, the switch lost its
thumb (`background-image: none`) and had a `Canvas` track both on and off, and
every swatch was empty. `appearance: none` leaves the switch no native widget
to fall back on.

So under `@media (forced-colors: active)` the segment labels and the switch
input opt out with `forced-color-adjust: none` and paint themselves with
system colours — `Highlight`/`HighlightText` for the checked segment and the
switch when on, `Canvas`/`CanvasText` otherwise — which follow the reader's
own contrast theme rather than this theme's palette. Their focus outline is
re-pointed at `Highlight`, since opted out it would keep the palette's primary
colour. The swatch opts out and keeps its literal colours: it is a sample of a
palette, the one thing in the panel whose colour is the information. The
palette list's check mark and its focus outline need nothing; borders and
outlines survive with a system colour, only the focus ring's shadow is
dropped. The acceptance suite asserts the result with forced colours
emulated.

### The header around it

Brand, main navigation and the cog share one row at every width.
`layout/_site-header.scss` keeps the navigation from shrinking above
`bp.$md`, so when the row gets tight it is the brand that wraps, never the
menu. Below `bp.$md` the row holds the brand (one size smaller, `md` instead
of `lg`: at 375px the large size needs four lines), the menu toggle and the
cog. The expanded menu then drops down under the header as a full-width band
instead of unfolding inside the narrow slot of its toggle — gated behind
`data-js` like the collapse itself, because without a script the list is
always open and would otherwise lie on top of the page for good.

## The module script

Loaded as:

```typoscript
page.includeJSFooter.theme = EXT:theme_extension_development/Resources/Public/JavaScript/theme.js
page.includeJSFooter.theme.type = module
```

`type="module"` is what makes the `defer` attribute unnecessary — a module is
deferred by specification — and it is why the script sits in the footer
rather than the head: unlike the inline script above, it only wires up
listeners on elements that already exist by the time it can matter, so it
never has to run before paint.

`Resources/Public/JavaScript/theme.js` carries no `?.` (optional chaining)
anywhere, and the file's own header comment explains why in more detail than
repeated here: a browser that does not recognise `type="module"` skips the
element without parsing it, so it can never fail on unsupported syntax inside
one — but "recognises modules" and "supports optional chaining" are not the
same floor (module support landed 2017–2018, optional chaining in 2020), and
a syntax error anywhere in a module aborts the **whole file** — unlike a
classic script, there is no per-statement fallback. Plain `if` checks cost
nothing here and remove that failure mode entirely.

The script owns the display settings and the main menu toggle. For the
settings:

- **Disclosure.** The cog toggles `aria-expanded` and the panel's `hidden`.
  Opening it does not move focus: the controls are reached with Tab like any
  other form. Escape closes the panel and returns focus to the cog — unless
  focus is on an element elsewhere on the page, which Escape must not pull
  away; focus on no element (the body, after a click on the panel's padding,
  or in Safari, which does not focus a clicked button) goes back to the cog.
  A click outside the control closes it and leaves focus alone.
- **Leaving with Tab.** A `focusout` whose `relatedTarget` lies outside the
  control closes the panel, so it cannot stay open over the content that now
  has focus (WCAG 2.2, 2.4.11 Focus Not Obscured). A `focusout` without a
  `relatedTarget` — focus going nowhere, see above — is not leaving and keeps
  the panel open; the click handler covers a click outside.
- **Choosing.** One `change` listener on the panel — it fires for a click, for
  the arrow keys within a radio group and for Space alike. A choice applies to
  the root at once and is stored; there is no "apply" step to forget, and
  focus stays where the reader put it. The controls are identified by
  `data-theme-setting`, not by their `name`, which only groups the radios.
- **Initial state.** The controls are checked from the root's *current*
  attributes rather than from `localStorage` again — the inline head script
  already resolved server default vs. stored choice into those attributes, and
  re-deriving the same answer here would be a second, independent path to it
  that could in principle disagree.
- **Reset.** Removes the three stored keys and applies the
  `data-theme-default-*` values. It forgets the choice rather than storing the
  defaults, so a visitor who reset follows the site from then on — including a
  constant changed after the reset.

| Setting         | Root attribute               | `localStorage` key      | Note                                                   |
|-----------------|------------------------------|-------------------------|--------------------------------------------------------|
| Appearance      | `data-theme`                 | `theme-appearance`      | `auto` removes the attribute, and is stored as `auto`. |
| Palette         | `data-palette`               | `theme-palette`         | Always explicit, `neutral` included.                   |
| Content outline | `data-theme-content-outline` | `theme-content-outline` | `on` or `off`; only `off` has a rule of its own.       |

`auto` is stored rather than expressed by removing the key. Without a key the
next page load falls back to `theme.appearance.default`, which is only `auto`
as long as nobody changed that constant: on a site defaulting to `dark`, a
visitor who chose "Auto" would have been sent back to dark on the next page.

**Every** menu toggle is bound, not the first one. The collapse rule in
`_nav-main.scss` matches any `.theme-nav-main` whose own toggle is not
expanded, so a page carrying a second navigation — the
[styleguide's](styleguide.md) navigation specimen, or a site package repeating
the menu in its footer — would have been folded shut below the breakpoint by a
control nothing had wired up, with no way left to open it. Binding one element
while styling all of them is a mismatch that only shows on a narrow viewport of
a page nobody tested, which is where it was found. Each toggle is bound scoped
to the nav it sits in, so two menus on one page cannot close each other.

The same module also carries the behaviour of the three components of the
library that need a script — tabs, the dialog opener and tooltip dismissal —
bound by the same rule: every instance, each scoped to itself. They are not
part of the appearance switching and are documented with the components, in
[Component library § Components that need the script](component-library.md#components-that-need-the-script).

## Palette swatches carry literal colours

```scss
.theme-swatch--ocean {
    --theme-swatch-primary: light-dark(#00629a, #7cc4ee);
    --theme-swatch-secondary: light-dark(#0d6d76, #63cfd8);
}
```

This is the one place in the component library that does not read a colour
through a shared token, and it is unavoidable rather than an oversight: a
palette option's swatch has to show *that palette's* colours, not the ones
currently active, but every palette lives entirely inside its own
`[data-palette='…']` selector in `abstracts/_palettes.scss`. A custom property
only ever holds the value of whichever selector currently matches the root —
CSS has no mechanism to ask what `--theme-color-primary` *would* resolve to
under a different attribute value. So each swatch modifier carries literal
copies of that palette's `--theme-color-primary` and `--theme-color-secondary`
pairs — a palette is a pair of accents, and the swatch shows both.

`Tests/Unit/ComponentLibraryTest::everyPaletteHasASwatchWithItsOwnColours` is
what keeps the copy in step: it reads every `:root[data-palette='…']` block out
of `_palettes.scss`, adds `neutral` (which lives in `_tokens.scss` instead,
behind no selector), and asserts that the same set of names has a
`.theme-swatch--*` modifier in `_settings.scss` **and** that each modifier's
two values equal its palette's. A palette added to one file without the other,
or a colour changed in one of them, fails this test rather than rendering a
swatch that shows nothing or the wrong thing.

## No cookie, therefore no consent question

Everything above stores exactly three keys, `theme-appearance`,
`theme-palette` and `theme-content-outline`, in `localStorage`. There is no
cookie anywhere in this mechanism and nothing is persisted server side — no
session, no user preference record. `localStorage` is the whole story, which is
also why there is no consent banner or legal-basis question to design around
here: the choice never leaves the browser it was made in, and a new browser or
a cleared site data setting simply sees the server-rendered default again.

## What the tests guard

`Tests/Functional/AppearanceRenderingTest` renders the frontend and asserts
what a functional test can — the state *delivered*, before any script runs:
the default appearance is never stamped onto the tag, the configured palette
and content-outline value are, the no-flash script is inline in the head,
sets `data-js` before it reads anything and applies a stored outline,
`data-js` is absent from the delivered markup, the cog is a button that
controls an existing panel which starts `hidden` and has a translated name,
every appearance and every palette is a radio in its own group inside a
fieldset with a legend, the outline is a checkbox with `role="switch"`, the
server defaults are checked and exposed as `data-theme-default-*`, and the
theme script loads with `type="module"`.
`Tests/Functional/ConfiguredAppearanceRenderingTest` renders the same with
every constant changed, so none of those defaults can be hard coded.

`Tests/Unit/ComponentLibraryTest` covers the compiled stylesheet:
`collapsingTheMainNavigationRequiresTheScriptMarker` (the script only *adds*
the `data-js` marker, the navigation's CSS contract is gated behind it) and
`everyPaletteHasASwatchWithItsOwnColours` (above).

What happens once the script runs is the acceptance suite's part —
`Tests/Acceptance/frontend.spec.ts`, see
[Acceptance tests](../testing/acceptance-tests.md): a choice applies at once
and survives a reload, and is applied by the head script with the module
blocked; "Auto" is stored as `auto`; Escape closes the panel with focus back
on the cog; Tab past the last control and a click outside close it; at 1280px
brand, menu and cog are one row with the title on one line, at 375px the
header is one row and the open panel lies inside the viewport; the checked
segment and the switch stay distinguishable in forced colours; Reset restores
the server defaults rendered next to the control — moved away from the
script's own fallbacks first, so the spec cannot pass on those — and forgets
the stored keys.

## See also

- [`DESIGN.md`](../../DESIGN.md)
- [Component library](component-library.md)
- [Frontend assets](frontend-assets.md)
- [TypoScript delivery](../architecture/typoscript-delivery.md)
- [Navigation](../architecture/navigation.md)
