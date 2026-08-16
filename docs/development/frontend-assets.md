# Frontend assets

The theme stylesheet is written in SCSS below `Resources/Private/Scss/` and
compiled to `Resources/Public/Css/theme.css`. Like every other tool in this
repository, the build runs in a container through
[`Build/Scripts/runTests.sh`](../../Build/Scripts/runTests.sh) — nothing has to
be installed on the host, node included.

```bash
# Compile the committed stylesheet.
Build/Scripts/runTests.sh -s buildCss

# Check the committed stylesheet still matches its sources, as CI does.
Build/Scripts/runTests.sh -s checkCssBuild

# Compile with source maps and re-compile on every change. Blocks until ctrl-c.
Build/Scripts/runTests.sh -s watchCss

# npm with all remaining arguments dispatched, for "audit", "update", "outdated".
Build/Scripts/runTests.sh -s npm -- outdated
```

| File                             | Is                                                                    |
|----------------------------------|-----------------------------------------------------------------------|
| `Resources/Private/Scss/`        | The sources. `theme.scss` is the entry point, partials are `_*.scss`. |
| `Resources/Public/Css/theme.css` | The compiled stylesheet. **Committed** — see below.                   |
| `package.json`                   | The single dependency `sass`, and the four build scripts.             |
| `package-lock.json`              | Committed, so `npm ci` and the gate are reproducible.                 |
| [`DESIGN.md`](../../DESIGN.md)   | The design token specification `abstracts/_tokens.scss` implements.   |

## The source tree

```
Resources/Private/Scss/
├── theme.scss          the bundle, and the cascade order
├── abstracts/          tokens, palettes, mixins — mixins emit nothing
├── base/               the reset, then bare element selectors
├── forms/              native controls, field wrapper, validation
├── components/         one file per component, alphabetical
└── layout/             page chrome; last, because positioning has to win
```

The order in `theme.scss` **is** the cascade. Every rule has the same
specificity by construction — single class selectors, no nesting deeper than a
modifier — so nothing but source order separates a component rule from a base
rule. Reordering the `@use` list changes the output.

### Components are self-contained

A component file reads tokens through `var()` with a literal fallback and
reaches into no other component. Two things follow:

- **A subset compiles.** A site package that wants three components writes its
  own entry point and compiles it against this directory with `--load-path`:

  ```scss
  @use 'abstracts/tokens';
  @use 'components/button';
  @use 'components/card';
  ```

- **A component is portable into a shadow root.** Custom properties inherit
  through a shadow boundary, so only the token file has to live in the outer
  document, and the fallback covers the case where it does not. Inherited *CSS*
  does not cross that boundary, which is what `standalone-reset` in
  `abstracts/_mixins.scss` exists for.

No per-component bundle is emitted today, because nothing consumes one. What is
built is the structure that makes emitting one a build-script change rather than
a refactor.

## Design tokens

Every value in the stylesheet comes from a CSS custom property declared in
`abstracts/_tokens.scss`. A literal anywhere except a `var()` fallback is a
token that has not been declared yet. [`DESIGN.md`](../../DESIGN.md) is the
specification — what each token is, where its value came from, and the contrast
ratios behind the palette.

### Component tokens

A component declares its own token layer at the top of its block, each entry
falling back to a global token and then to a literal:

```scss
.theme-card {
    --theme-card-background: var(--theme-color-surface, #f4f6fa);

    background-color: var(--theme-card-background);
}
```

The fallback literal therefore appears **once per component**, not once per
declaration, which is what keeps `abstracts/_tokens.scss` authoritative. A site
package can re-theme globally (`--theme-color-surface`) or surgically
(`--theme-card-background`) without touching the other, and a modifier
re-points a token rather than restating the property.

Colour is declared once per token and carries both appearances through
`light-dark()`; the appearance is then selected by `color-scheme` alone. The
alternates in `_palettes.scss` vary **accents only**, which is what keeps a
palette to one block.

Three things about it are easy to break by accident:

- **`color-scheme: light dark` on `:root` is load-bearing.** Without it the used
  colour scheme is light, the second argument of every `light-dark()` becomes
  unreachable, and the whole dark appearance disappears — silently, with no
  build error and no visual clue in light mode.
- **Both appearances have to ship.** `checkCssBuild` proves only that the
  committed CSS matches the build; drop the dark half of a colour and it stays
  green, because the committed file still matches.
  `Tests/Unit/StylesheetTest` is what asserts the appearance contract — that
  every colour carries two values in one declaration, that a neutral is declared
  exactly once, and that no palette restates anything but an accent.
- **`--theme-content-max-width` and `theme.media.maxGalleryWidth` are the same
  number** — 1200px — because the second decides how wide images are processed
  for the first. They are in different languages and nothing enforces the
  coupling, so moving one means moving the other.

## The node image

The TYPO3 PHP testing images ship **no node**, so the asset build uses the
TYPO3 node image instead:

```bash
IMAGE_NODEJS="ghcr.io/typo3/core-testing-nodejs24:latest"
```

This is the same pattern the documentation rendering already uses with
`IMAGE_DOCS` — a second image dispatched by the same wrapper. `-u` picks it up
along with the others, because that globs `ghcr.io/typo3/core-testing-*`.

The suites pass `-e npm_config_cache=.cache/npm`, which puts the npm download
cache next to the composer and PHPStan caches in `.cache/` at the repository
root. The reason is the same one documented for those:
[`composerUpdate` starts with `rm -rf .Build`](quality-gates.md#the-composer-cache),
so a cache kept below `.Build/` would be deleted before it is ever read.

## No framework, and no `@import`

The extension is deliberately free of CSS dependencies. There is no Bootstrap,
no Tailwind and no reset library — everything essential is written in
`Resources/Private/Scss/`. Design tokens are declared as **CSS custom
properties** rather than Sass variables, so an integrator can re-theme the
stylesheet from their own CSS without rebuilding it, and the browser devtools
show the token rather than the value it compiled to.

Partials are pulled in with `@use` and `@forward`, **never** with `@import`:
`@import` is deprecated since dart-sass 1.80.0 and is removed in dart-sass 3.0.

## Why the compiled CSS is committed

Because nothing builds it downstream. Both distribution paths are exports of
committed content:

- the composer `dist` archive is the git archive of the tag, which runs no
  build and honours `export-ignore`,
- the TER artifact is produced by `tailor create-artefact` in
  [`publish.yml`](../../.github/workflows/publish.yml), on a bare checkout of
  the tag, with no build step.

So whatever is not committed does not exist for a consumer. Building at release
time is not an alternative — it would fix the TER artifact and still leave every
composer install without a stylesheet.

The **sources ship as well**. `Resources/Private/Scss/`, `package.json` and
`package-lock.json` are deliberately *not* `export-ignore`d, so an integrator
who wants different tokens can rebuild the stylesheet rather than override
compiled rules. Shipping the sources without the manifest that builds them
would be half an answer, which is why all three travel together. Only
`node_modules` is excluded.

Source maps are **not** committed. The development build writes them with
`--source-map --embed-sources`, and `Resources/Public/Css/*.map` is git-ignored.

## The `checkCssBuild` gate

`checkCssBuild` compiles the sources into `.Build/css-verify/` and `diff`s the
result against the committed stylesheet. It fails in both directions — an edited
`theme.css` and an edited `.scss` that was never rebuilt both produce a diff.

It deliberately does **not** use the `git add` plus `git status` approach the
TYPO3 core uses for the same purpose. `git` inside the node image aborts with
`fatal: detected dubious ownership` whenever the uid does not match, which is
exactly the `-b docker` path CI runs on, and that approach writes the git index
as a side effect. Comparing two files needs neither.

In CI it is a **single job**: the stylesheet depends on neither the PHP version
nor the TYPO3 version, so running it per matrix combination would check the same
file again. It needs no `composerUpdate` either, which makes it the cheapest job
in the workflow.

## See also

- [Development environment](environment.md)
- [Quality gates](quality-gates.md)
- [Core version setup](dual-core-setup.md)
