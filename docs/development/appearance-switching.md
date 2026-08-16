# Appearance switching

Light / dark, the five colour palettes and the content-element outline are all
CSS-only — [`DESIGN.md`](../../DESIGN.md) and
[Component library § The two switches](component-library.md#the-two-switches)
cover that contract. This page covers what sits on top of it: the TypoScript
that renders a server-side default for all three, the inline script that
applies a stored choice before first paint, and the module script and Fluid
partial that let a visitor change any of it. The implementation is
[`Configuration/TypoScript/Appearance.typoscript`](../../Configuration/TypoScript/Appearance.typoscript),
[`Resources/Public/JavaScript/theme.js`](../../Resources/Public/JavaScript/theme.js)
and
[`Resources/Private/Partials/Page/AppearanceSwitcher.html`](../../Resources/Private/Partials/Page/AppearanceSwitcher.html).

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
each value onto the tag **raw**: `stdWrap` is only applied when a matching
`attributes.<name>.` sub-array is configured, and none is here. Verified
identical on both core versions —
`.Build/vendor/typo3/cms-frontend/Classes/Http/RequestHandler.php` (v13.4,
the loop at line 833) and
`instance-core-14/vendor/typo3/cms-frontend/Classes/Http/RequestHandler.php`
(v14.3, line 829) — the same method, the same behaviour. A constant is
therefore the only thing that can be assigned here; a cObject would need the
`stdWrap` this code path never runs.

`data-theme` is behind a condition rather than a plain assignment, for a
different reason: `auto` is the **absence** of the attribute, not a value for
it. No selector in `abstracts/_tokens.scss` matches `data-theme="auto"`, so
rendering it would look like a deliberate, working choice while doing
nothing — the operating system already decides via `color-scheme` once no
attribute is present, and stamping `auto` onto the tag would silently do
nothing while looking like it does. `theme.appearance.palette` needs no such
condition: unlike `auto`, `neutral` has no "let something else decide" case —
it is simply what `_tokens.scss` already declares — so it is rendered
unconditionally even though it is also the default.

Constants are substituted into setup conditions before they are evaluated
(`IncludeTreeSetupConditionConstantSubstitutionVisitor`, byte-identical
between the two core versions), so the condition above reads
`theme.appearance.default` the same way the unconditional assignments do.

## The no-flash script

An inline script in the document head, emitted through `page.headerData`
rather than `f:asset.script`, because the asset collector is free to move a
script to the end of the body — and a stored dark appearance would then paint
light first, which is the exact flash this script exists to prevent.

It does two things, in this order, and the order is load-bearing:

1. sets `data-js` on the root, **unconditionally and first**;
2. reads the stored appearance and palette from `localStorage` and applies
   them, each wrapped in its own `try`/`catch`.

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
which is exactly why `data-js` is set before either `try` block runs, not
after: if the order were reversed, a throwing read could leave the page
without the marker entirely.

Losing the marker is not a cosmetic gap. `data-js` is what
`components/_nav-main.scss` gates the navigation collapse behind and what
`components/_appearance-switcher.scss` gates the switcher's visibility
behind (see the next section). Without it the main navigation stays in its
no-script layout — always expanded, no working toggle — and the switcher
stays hidden, on every page, for the rest of that visit. The navigation is
still usable in that state (it is the same layout a visitor with JavaScript
disabled sees), but the enhancement and the switcher are both gone, silently,
until a page load that does not hit the storage exception.

## The switcher is hidden until `data-js`

```scss
.theme-appearance-switcher {
    display: none;

    [data-js] & {
        display: flex;
    }
}
```

The whole control — both button groups — is hidden until the root carries
`data-js`, the same gate `_nav-main.scss` uses for the navigation toggle and
for the same reason: neither group does anything without the module script
that owns `data-theme-appearance`/`data-theme-palette`, and there is no
native fallback the way a plain link or a `<details>` element would give one.
A switcher that cannot work is worse than no switcher — a visible promise the
page cannot keep — so the undecorated state is "not there at all", not
"there but inert".

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

The script owns three things: the appearance control (`data-theme`,
`localStorage` key `theme-appearance`, `auto` removes the attribute), the
palette control (`data-palette`, key `theme-palette`, no value removes the
attribute — `neutral` is always rendered explicitly, matching the server
side), and the main menu toggle. It reads its initial `aria-pressed` state off
the root's *current* attribute rather than off `localStorage` again — the
inline head script already resolved server default vs. stored choice into
that attribute, and re-deriving the same answer here would be a second,
independent path to it that could in principle disagree.

**Every** menu toggle is bound, not the first one. The collapse rule in
`_nav-main.scss` matches any `.theme-nav-main` whose own toggle is not
expanded, so a page carrying a second navigation — the
[styleguide's](styleguide.md) navigation specimen, or a site package repeating
the menu in its footer — would have been folded shut below the breakpoint by a
control nothing had wired up, with no way left to open it. Binding one element
while styling all of them is a mismatch that only shows on a narrow viewport of
a page nobody tested, which is where it was found. Each toggle is bound scoped
to the nav it sits in, so two menus on one page cannot close each other.

## Palette swatches carry literal colours

```scss
.theme-appearance__swatch--ocean {
    --theme-appearance-swatch-color: light-dark(#00629a, #7cc4ee);
}
```

This is the one place in the component library that does not read a colour
through a shared token, and it is unavoidable rather than an oversight: a
palette button's swatch has to show *that palette's* primary colour, not the
one currently active, but every palette lives entirely inside its own
`[data-palette='…']` selector in `abstracts/_palettes.scss`. A custom property
only ever holds the value of whichever selector currently matches the root —
CSS has no mechanism to ask what `--theme-color-primary` *would* resolve to
under a different attribute value. So each swatch modifier carries a literal
copy of that palette's `--theme-color-primary` pair.

`Tests/Unit/ComponentLibraryTest::everyPaletteHasASwatchInTheSwitcher` is what
keeps the copy in step: it reads every `:root[data-palette='…']` selector out
of `_palettes.scss`, adds `neutral` (which lives in `_tokens.scss` instead,
behind no selector), and asserts the same set of names has a
`.theme-appearance__swatch--*` modifier in `_appearance-switcher.scss`. A
palette added to one file without the other fails this test rather than
rendering a colourless swatch.

## No cookie, therefore no consent question

Everything above stores exactly two keys, `theme-appearance` and
`theme-palette`, in `localStorage`. There is no cookie anywhere in this
mechanism and nothing is persisted server side — no session, no user
preference record. `localStorage` is the whole story, which is also why there
is no consent banner or legal-basis question to design around here: the
choice never leaves the browser it was made in, and a new browser or a
cleared site data setting simply sees the server-rendered default again.

## What the tests guard

`Tests/Functional/AppearanceRenderingTest` renders the frontend and asserts
what a functional test can — the state *delivered*, before any script runs:
the default appearance is never stamped onto the tag, the configured palette
and content-outline value are, the no-flash script is inline in the head,
`data-js` is absent from the delivered markup, every appearance and every
palette has a button, both groups are labelled, every option reports
`aria-pressed`, and the theme script loads with `type="module"`.

`Tests/Unit/ComponentLibraryTest` covers the compiled stylesheet:
`collapsingTheMainNavigationRequiresTheScriptMarker` (unchanged by this
feature — the script only *adds* the `data-js` marker, the navigation's CSS
contract was already gated behind it) and
`everyPaletteHasASwatchInTheSwitcher` (above).

## See also

- [`DESIGN.md`](../../DESIGN.md)
- [Component library](component-library.md)
- [Frontend assets](frontend-assets.md)
- [TypoScript delivery](../architecture/typoscript-delivery.md)
- [Navigation](../architecture/navigation.md)
