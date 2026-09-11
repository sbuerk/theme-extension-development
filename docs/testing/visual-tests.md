# Visual tests

The functional tests hold the styleguide to its markup — every component on the
page, every section linked, no id twice
([Styleguide page](../development/styleguide.md#what-the-tests-guard)). What
markup cannot show is what the stylesheet makes of it: a token that changed a
colour, a rule that moved a border, a palette whose accent no longer reads on
its background. The visual suite looks at exactly that, in a browser, without a
TYPO3 instance:

- **axe** — the WCAG 2.2 AA rules — on every styleguide section in every
  appearance and palette, with zero violations allowed;
- **screenshot comparison** against committed baselines on a reduced matrix of
  the same pages.

```bash
# Fluid comes from the root dependency set: install one first, either core.
Build/Scripts/runTests.sh -t 13 -s composerUpdate

Build/Scripts/runTests.sh -s visual

# Arguments after "--" go to "playwright test".
Build/Scripts/runTests.sh -s visual -- --grep buttons

# Rewrite baselines - only after looking at the diff, see below.
Build/Scripts/runTests.sh -s visual -- --update-snapshots
Build/Scripts/runTests.sh -s visual -- --update-snapshots --grep "screenshot buttons"
```

A run takes about 40 seconds of specs for 119 tests. It is **not** a
replacement for `/styleguide`, which remains the interactive reference with the
real display settings inside a real TYPO3.

## What a run does

1. **Renders the fixtures.**
   [`Build/Scripts/renderStyleguideFixtures.php`](../../Build/Scripts/renderStyleguideFixtures.php)
   renders every `Resources/Private/Partials/Styleguide/*.html` with the
   standalone `typo3fluid/fluid` of `.Build/vendor/` — no TYPO3 bootstrap, no
   database — and writes one page per section, appearance and palette below
   `.Build/visual/fixtures/`, plus the `manifest.json` the spec iterates and an
   `index.html` linking every page.
2. **Serves them** with the PHP built-in server, in a container of its own on
   the network of the run. The document root is the repository root, so a
   fixture can link the committed stylesheet where it is;
   [`Tests/Acceptance/Visual/router.php`](../../Tests/Acceptance/Visual/router.php)
   answers only below `/.Build/visual/` and `/Resources/Public/`, and 404 for
   everything else — the self referencing `theme` symlink included.
3. **Runs Playwright** with
   [`Tests/Acceptance/Visual/playwright.config.ts`](../../Tests/Acceptance/Visual/playwright.config.ts)
   in the same pinned image as the acceptance suite.

The suite lives in `Tests/Acceptance/Visual/` and shares `package.json`, the
lockfile and therefore the **one** Playwright pin of the acceptance suite; a
package of its own would make three places to keep in step with
`IMAGE_PLAYWRIGHT` instead of two. `Tests/Acceptance/playwright.config.ts`
ignores the directory. `@axe-core/playwright` is pinned exactly, like
`@playwright/test`.

Everything a run writes goes below `.Build/visual/`: the fixtures,
`test-results/` with the actual, expected and diff image of every failed
screenshot and a trace of every failure, the HTML report in `report/`, and the
log of the server. `composerUpdate` and `-s cleanTests` remove it.

The fixtures are **never committed**, so they cannot drift from the partials by
construction. They double as a static styleguide: open
`.Build/visual/index.html` in any browser.

## How the fixtures are made

**One document per combination.** Appearance and palette are both selected on
the root element — `:root[data-theme]` and `:root[data-palette]` — so a
combination is a document, not a region of one. The generator writes
`data-theme` and `data-palette` into the markup the way TYPO3 renders them
(`Appearance.typoscript`; `neutral` is rendered too, like the constant does), so
every page is right on its first paint and no script runs. `data-js` is
therefore absent: of what reacts to it, the collapsing main navigation is below
the viewport of the suite and the display settings of the header are page
chrome, not a specimen.

**The page frame is the real one.** The section is placed in the structure
`Layouts/Default.html` and `Templates/Page/Styleguide.html` produce —
`.theme-page`, `.theme-page__body`, `main.theme-page__main`,
`.theme-styleguide` and an `<h1>` — so it gets the width, padding and furniture
of `/styleguide`. That is not cosmetic: every specimen sits in a frame drawn in
`--theme-color-surface-raised`, and the evaluation spike, which rendered the
bare sections, never saw that the dark muted text colour failed AA on exactly
that surface. The first run of this suite did, and the token was fixed before
the suite was committed (see `DESIGN.md`).

**Nothing is listed.** The partials are found by glob, the palettes are read
from `abstracts/_palettes.scss`, and the section id is taken from the one
`<section class="theme-styleguide__section" id="…">` each partial renders.

**It fails loudly.** A notice or deprecation while rendering, a partial that
does not start with that section, two partials rendering the same id, and a
ViewHelper standalone Fluid cannot render — `f:translate`, `f:uri.resource` or
`f:image` need TYPO3 — stop the run rather than producing a broken fixture.

A **variable** cannot be caught there: Fluid renders one that is not set, and a
property path on one, as an empty string rather than as the literal `{name}`.
A partial that starts to expect a variable is caught only by the
[drift test](#standalone-fluid-against-typo3).

## The stylesheet is linked, never inlined or bundled

Every fixture links `Resources/Public/Css/theme.css` unchanged, with a relative
`href`. Both obvious alternatives test a different stylesheet, and both looked
plausible in the evaluation:

- **Inlined into `<style>`**, the text is no longer decoded as a stylesheet
  file. The build used to write a UTF-8 BOM, which a linked file loses in the
  decoder; inlined, it became part of the first selector and the first rule was
  dropped — the whole `:root` token block. The dark appearance then reported 22
  serious contrast violations that did not exist. The build no longer writes a
  BOM ([Frontend assets](../development/frontend-assets.md)), and linking is
  right either way: it tests the file as a browser receives it.
- **Imported through a bundler**, Vite rewrote all 51 `light-dark()` calls into
  110 `--lightningcss-*` fallbacks.

The spec asserts on every page that `--theme-color-surface` resolved, so a
stylesheet that is missing or reaches the page altered fails the test that
opened it rather than a screenshot much later.

## The matrix

| Check                  | Combinations                                                                                                                                        | Today |
|------------------------|-----------------------------------------------------------------------------------------------------------------------------------------------------|-------|
| axe, WCAG 2.2 AA       | every section × light, dark × every palette                                                                                                         | 70    |
| Screenshot, clipped    | every section × light, dark in `neutral`; the accent swatches and the focus ring of `tokens` and the whole of `buttons` × light, dark × four others | 38    |
| Info follows primary   | the `info` swatch of `tokens` shows the primary accent, every appearance and palette                                                                | 10    |
| Palette sections exist | `tokens` and `buttons` are sections of the manifest                                                                                                 | 1     |

axe runs everywhere because it is cheap and writes no files, and because colour
contrast is what a palette puts at risk in both appearances. It runs the tags
`wcag2a`, `wcag2aa`, `wcag21a`, `wcag21aa` and `wcag22aa`; the `best-practice`
rules are not run, since several judge a whole page (`page-has-heading-one`,
`region`) and a fixture is one section. Page context — duplicate landmark names,
duplicate ids — is asserted on the real page by `StyleguideRenderingTest`.

Screenshots are reduced because every one is a committed file. A palette
re-points five tokens and nothing else — the five every block of
`abstracts/_palettes.scss` overrides: `--theme-color-primary`, `-primary-hover`,
`-secondary`, `-secondary-hover` and `--theme-focus-ring-color`. In the four
alternate palettes `tokens` is therefore compared as the two specimens that
show them: the accent swatch group, as
`tokens-accents-<appearance>-<palette>.png`, and the focus ring, as
`tokens-focus-<appearance>-<palette>.png`. The rest of that section is neutral
and would repeat the same pixels in every palette. The buttons are the
component made of accents alone and stay whole. The other sections are compared
in the default palette, clipped to the section element.

`--theme-color-info` moves with a palette as well, without being overridden: it
aliases the primary accent, and neither clipped specimen shows its swatch.
Rather than widening a clip for one swatch, the spec asserts on every `tokens`
page that the `info` swatch computes to the same colour as the `primary` one.

Pixel relevant settings are fixed in the configuration rather than left to a
default: 1280 × 800 viewport, scale factor 1, reduced motion, animations
disabled, caret hidden. The comparison tolerates nothing — `threshold: 0` and
`maxDiffPixels: 0`. Playwright's default per pixel threshold of 0.2 let the dark
muted text move from `#848fa1` back to `#808b9d` without one differing pixel;
axe caught it, the screenshots did not. Renders in the pinned image are
identical to the pixel across runs and container runtimes, so the tolerance
bought nothing but blindness to small colour changes. Lazily loaded images are
switched to eager and decoded before the screenshot, so whether an image below
the viewport had arrived is not timing.

## Baselines

The baselines are the PNGs in
[`Tests/Acceptance/Visual/Baselines/`](../../Tests/Acceptance/Visual/Baselines),
named `<section>-<appearance>-<palette>.png`, the two clipped specimens of
`tokens` as `tokens-accents-…` and `tokens-focus-…` — 9.7 MB for the 38 today.
`Tests/` is `export-ignore`, so they never ship. Growth of the repository on
every intended visual change is the main ongoing cost of the suite.

They are valid for **one image on one architecture**. The theme sets
`system-ui`, so the fonts inside the pinned Playwright image decide the
rendering. Two consecutive runs are identical, and a run under docker matches
the baselines written under podman (amd64, both verified). An arm64 host is
expected to differ and has not been tried. Hence:

- Baselines are written by `-s visual` in its container, **never** by a browser
  on the host.
- **Bumping Playwright rebaselines in the same commit** — the image and
  `@playwright/test` move together anyway.
- `updateSnapshots: 'none'`: a missing or changed baseline fails the run and
  nothing is written. Only `-- --update-snapshots` writes.

## Rebaselining

**Never rebaseline without looking at the diff.** A failing screenshot is a
finding, exactly like a diagnostic of the PHPUnit suites, and
`--update-snapshots` is the visual form of silencing it.

1. Run `-s visual` and let it fail.
2. Open the diff: `.Build/visual/test-results/<test>/<name>-diff.png`, next to
   `-actual.png` and `-expected.png`, or the HTML report in
   `.Build/visual/report/`.
3. Decide whether that is the change you meant. If it is not, fix the cause.
4. If it is, rewrite only what it affects —
   `-- --update-snapshots --grep "<section>"` — look at the new baselines, and
   commit them **in the same commit as the change that caused them**, with the
   intended visual change named in the commit body.

## A new styleguide partial

Nothing has to be registered. A partial added below
`Resources/Private/Partials/Styleguide/` is rendered by the next run, axe
checks it in every combination, and its two `neutral` screenshots fail for want
of a baseline. Look at the `-actual.png` files, then write them with
`-- --update-snapshots --grep "<section id>"` and commit them with the partial.
The drift test below covers it as well.

The page itself still has to render it — `Templates/Page/Styleguide.html` and
the section list of `StyleguideRenderingTest` — see
[Styleguide page](../development/styleguide.md).

## Standalone Fluid against TYPO3

The fixtures are only worth anything while they are the markup `/styleguide`
carries. `StyleguideRenderingTest::everySectionRendersWithoutTypo3AsItDoesOnThePage`
runs the generator with `--sections`, which prints the rendered sections as
JSON and writes nothing, and requires each of them — whitespace collapsed — to
appear in the page TYPO3 renders. A partial that starts to depend on a
ViewHelper, a variable or a setting only TYPO3 provides fails there. The v13 set
(Fluid 4) and the v14 set (Fluid 5) render identical sections.

## In CI

The job `visual` runs once, after `quality`, on the v13 set and the lowest PHP
version — the set `quality` installs, whose composer cache it shares. It is not
a matrix: the stylesheet depends on neither PHP nor the core version, the
partials render identically with either Fluid, and the browser is the one
pinned image, so a second job would compare the same pixels again. On failure it
uploads the report, the test results with the diff images, the fixtures and the
server log — see [Quality gates](../development/quality-gates.md#continuous-integration).

## What it does not cover

- The `auto` appearance — no `data-theme`, the operating system decides. It
  resolves to one of the two tested ones through `color-scheme`.
- Other viewports, other browsers: one desktop viewport, Chromium only.
- Behaviour: the display settings, the navigation toggle and everything else
  that needs the theme's script are the acceptance suite's.
- The real `/styleguide` page in a browser. An axe pass over it per core
  version in the acceptance suite would add page context a fragment cannot
  have; it is not part of this suite.

## Why not Storybook

Storybook 10 was evaluated against this and deferred. It works — `html-vite`
builds, and `addon-vitest` with the a11y addon goes red on a real violation —
but for this repository it buys mainly an interactive viewer, which
`/styleguide` already is:

- **The markup has to come from somewhere.** Stories are JavaScript template
  strings; hand-written ones duplicate the Fluid partials, which is the drift
  `StyleguideRenderingTest` exists to prevent. The only non-duplicating route is
  generated fixtures — and once those exist, Storybook adds a browsing UI and a
  runner, not content.
- **The matrix is not its shape.** Both switches are root attributes, so each
  combination is a document; `addon-vitest` runs a story once with its initial
  globals, which means ten story variants per section. The default run was
  light only.
- **Cost:** 239 packages and 135 MB of `node_modules`, a third lockfile, a
  second Playwright pin to keep in step with the image, a minor release roughly
  monthly and a lagging `vitest` peer range — against two packages added to a
  set that exists.
- **It needed two workarounds on day one** — the stylesheet served statically
  rather than imported (the Vite rewrite above), and `server.fs.allow`.

If it is wanted later, it should consume the fixtures this suite generates, so
nothing here stands in its way.

## See also

- [Styleguide page](../development/styleguide.md)
- [Acceptance tests](acceptance-tests.md)
- [Frontend assets](../development/frontend-assets.md)
- [Quality gates](../development/quality-gates.md)
- [`DESIGN.md`](../../DESIGN.md)
