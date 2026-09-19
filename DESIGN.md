# Design tokens

The design token specification of `sbuerk/theme-extension-development`.

Every token is a **CSS custom property**, declared in
[`Resources/Private/Scss/abstracts/_tokens.scss`](Resources/Private/Scss/abstracts/_tokens.scss) and
compiled into `Resources/Public/Css/theme.css`. Custom properties survive
compilation, so a site can re-theme the extension by overriding a handful of
properties in its own CSS — **without rebuilding the SCSS**.

This file is the specification. The SCSS is the implementation, and the two are
kept in step by hand; where they disagree, the SCSS is what ships.

## Provenance

Two different origins, and the difference matters when you change something.

| Group                                                   | Origin                                                    |
|---------------------------------------------------------|-----------------------------------------------------------|
| Typography metrics, spacing, radius, borders, elevation | **Measured** from an internal reference design            |
| Colour                                                  | **Authored.** Deliberately neutral, see [Colour](#colour) |
| Motion, layout width, the type role mapping             | **Authored**, marked below                                |
| Controls, stacking, semantic colour, palettes           | **Authored**, added for the component library             |

The reference design tokenises colour and nothing else — no spacing, radius or
type tokens exist in it. Colour is precisely the part this theme does not take,
so its own token layer contributed nothing that was needed here.

Values below are marked *measured* where they come from that reference and
*extended* where a step was added to make the scale usable. Nothing is marked
measured unless it was counted.

## Typography

### The typeface is not shipped

The reference design is set in **Mulish** (weights 400, 500, 800). This extension
ships **no webfonts** — no CDN, no font files, no external request of any kind —
so the token resolves to a system stack and Mulish is *not* bundled.

What is taken is the **metrics**: the size scale, the line heights,
the tracking and the weight steps. They are what carry the design's rhythm, and
they apply to whatever face renders them.

An integrator who does load Mulish gets the reference design back by overriding
one token:

```css
:root { --theme-font-family-sans: 'Mulish', system-ui, sans-serif; }
```

Two stray faces — Helvetica Neue on seven layers and Inter on one — were read
as leftovers and ignored.

### Roles

| Role            | Family | Weight | Size                                                | Line height | Tracking |
|-----------------|--------|--------|-----------------------------------------------------|-------------|----------|
| Display         | sans   | 800    | `clamp(2.125rem, 1.5rem + 3vw, 3.375rem)` — 34→54px | 1.5         | +0.05em  |
| Heading 1       | sans   | 800    | 2.125rem / 34px                                     | 1.5         | +0.05em  |
| Heading 2       | sans   | 800    | 1.5rem / 24px                                       | 1.3         | +0.05em  |
| Heading 3       | sans   | 500    | 1.25rem / 20px                                      | 1.3         | +0.05em  |
| Heading 4       | sans   | 800    | 1rem / 16px                                         | 1.3         | +0.05em  |
| Heading 5       | sans   | 800    | 0.875rem / 14px, capitals                           | 1.3         | +0.08em  |
| Heading 6       | sans   | 800    | 0.75rem / 12px, capitals, secondary ink             | 1.3         | +0.08em  |
| Lead            | sans   | 400    | 1.25rem / 20px, secondary ink, held to the measure  | 1.6         | 0        |
| Eyebrow         | sans   | 800    | 0.75rem / 12px, capitals, primary accent            | 1.3         | +0.08em  |
| Body            | sans   | 400    | 1rem / 16px                                         | 1.6         | 0        |
| Label / control | sans   | 500    | 0.875rem / 14px                                     | 1.1         | +0.05em  |
| Caption         | sans   | 500    | 0.75rem / 12px                                      | 1.5         | +0.05em  |
| Mono            | mono   | 400    | 0.9375rem / 15px                                    | 1.5         | 0        |

**Heading 4 to 6, Lead and Eyebrow are authored**, not measured, and none of
them adds a size to the scale. Heading 4 lands on the body size and Heading 5
and 6 on the two steps below it, which is exactly why size cannot carry them:
an H4 at 16px is the size of the paragraph under it, so it takes weight 800;
below the body size a mixed-case heading reads as small print, so H5 and H6 are
set in capitals and tracked wider, and H6 drops to secondary ink as the least
important level. `text-transform` does the capitals, so the casing an editor
typed is what stays in the content.

Display, Lead and Eyebrow are roles, not elements. A heading level says where
something sits in the outline, not how loud it is, so the three are classes —
`.theme-display`, `.theme-lead`, `.theme-eyebrow` in
`components/_text.scss` — applied to whatever element the outline asks for.

**Tracking is positive.** `+0.05em` is the reference's signature and perfectly
consistent across it — 0.8px at 16, 1.7px at 34, 0.6px at 12, 2.7px at 54, all
exactly 0.05em. Body prose is the exception at 0. This is
worth stating because the reflex for display type is to track *in*; this design
tracks *out*. Text set in capitals is the one step wider, `+0.08em`
(`--theme-letter-spacing-caps`, authored): H5, H6 and the eyebrow, and nothing
set in mixed case.

**Line heights are the explicitly set ones only.** The reference also carries
values of ~1.255, which its editor reports as an *automatic* line height — that
is, nothing was chosen. Only the explicitly set ones were taken: 160% at 16px,
150% at 34px and 16px, 130% at 24px, 110% at 14px.

### Scale

| Token                       | Value                                     | px    | Origin   |
|-----------------------------|-------------------------------------------|-------|----------|
| `--theme-font-size-xs`      | 0.75rem                                   | 12    | measured |
| `--theme-font-size-sm`      | 0.875rem                                  | 14    | measured |
| `--theme-font-size-md`      | 1rem                                      | 16    | measured |
| `--theme-font-size-lg`      | 1.25rem                                   | 20    | extended |
| `--theme-font-size-xl`      | 1.5rem                                    | 24    | measured |
| `--theme-font-size-2xl`     | 2.125rem                                  | 34    | measured |
| `--theme-font-size-display` | `clamp(2.125rem, 1.5rem + 3vw, 3.375rem)` | 34→54 | measured |
| `--theme-font-size-mono`    | 0.9375rem                                 | 15    | measured |

`--theme-font-size-mono` sits outside the progression on purpose: it is not a
step of the scale but a correction. A monospace face at the same nominal size
reads larger than the body face next to it, so it is set one notch down to
match the surrounding text optically rather than numerically.

There is **no single modular ratio**. The steps run 1.17, 1.14, 1.5, 1.42, 1.59
— a real design, not a generated scale. That irregularity is preserved rather
than smoothed, because smoothing it would replace the measurement with
arithmetic.
`lg` (20px) is the one added step, needed for a third heading level and placed
on the 5px grid.

Weights: `400` regular, `500` medium, `800` bold. There is no 600 or 700 in the
reference. A system font may synthesise 800 or clamp it to its boldest weight.

Line heights: `tight` 1.1, `snug` 1.3, `heading` 1.5, `base` 1.6, `mono` 1.5.
Tracking: `wide` 0.05em, `caps` 0.08em (authored), `none` 0. Measure: 68ch
(authored).

`--theme-line-height-mono` is 1.5, the same number as `heading` — and it is a
separate token precisely because that is a coincidence. Sharing one would mean
that changing a heading silently re-set every code block.

## Colour

**Authored, not extracted — deliberately.** This is a theme for *extension
development*. Its job is to make document structure legible without biasing the
design of the extension being built against it, so it does not wear another
product's brand. The palette is neutral, and the tokens are named by role so a
site package can drop its own values in.

Contrast was **computed**, not estimated, for every value, against the
background, the surface and the raised surface of its own mode. Body text clears
4.5:1 (WCAG AA); borders that delimit a control clear 3:1 (WCAG 1.4.11).

The figures use the definitions of WCAG 2.2: relative luminance
`0.2126 R + 0.7152 G + 0.0722 B` over the linearised sRGB channels (`c / 12.92`
up to 0.04045, `((c + 0.055) / 1.055) ^ 2.4` above), and the ratio
`(L1 + 0.05) / (L2 + 0.05)` with the lighter colour first, rounded to two
decimals. They were computed with a PHP implementation of exactly that — the
same one `Tests/Unit/StylesheetTest` runs on `--theme-color-border-strong`.

### One declaration, both appearances

Every colour is declared **once**, carrying its light and dark value together,
with the CSS `light-dark()` function:

```css
:root {
    color-scheme: light dark;
    --theme-color-background: light-dark(#ffffff, #0f1319);
}
```

`light-dark()` resolves against the **used value of `color-scheme`**, which is
what makes the appearance switch a one-property affair. `color-scheme: light
dark` on the root is load-bearing: without it the used scheme is light, the
second argument of every call is unreachable, and the entire dark appearance
disappears silently.

The alternative — a second palette block for the media query and a third for
the attribute override — costs three declarations per token instead of one, and
multiplies again per palette. With five palettes that is 255 colour
declarations against 85. It also lets the copies drift, which is a class of
defect that cannot occur here at all.

**`light-dark()` takes colours, not arbitrary values.** That is why the focus
ring is split into `--theme-focus-ring-color` and a shadow built around it,
rather than being declared whole.

Support: Firefox 120, Chrome and Edge 123, Safari 17.5 — Baseline since
May 2024.

### Light

| Token                           | Hex       | vs background     | vs surface | vs surface-raised |
|---------------------------------|-----------|-------------------|------------|-------------------|
| `--theme-color-background`      | `#ffffff` | —                 | —          | —                 |
| `--theme-color-surface`         | `#f4f6fa` | —                 | —          | —                 |
| `--theme-color-surface-raised`  | `#ffffff` | —                 | —          | —                 |
| `--theme-color-primary`         | `#0b57d0` | 6.39              | 5.90       | 6.39              |
| `--theme-color-primary-hover`   | `#0a4bb4` | 7.84              | 7.25       | 7.84              |
| `--theme-color-on-primary`      | `#ffffff` | 6.39 on primary   | —          | —                 |
| `--theme-color-secondary`       | `#0f766e` | 5.47              | 5.06       | 5.47              |
| `--theme-color-secondary-hover` | `#0c5f59` | 7.51              | 6.94       | 7.51              |
| `--theme-color-on-secondary`    | `#ffffff` | 5.47 on secondary | —          | —                 |
| `--theme-color-text-primary`    | `#14181f` | 17.79             | 16.45      | 17.79             |
| `--theme-color-text-secondary`  | `#4a5567` | 7.54              | 6.97       | 7.54              |
| `--theme-color-text-muted`      | `#616b80` | 5.35              | 4.95       | 5.35              |
| `--theme-color-border`          | `#d6dce6` | 1.38              | 1.27       | 1.38              |
| `--theme-color-border-strong`   | `#828d9f` | 3.35              | 3.10       | 3.35              |
| `--theme-color-success`         | `#146c43` | 6.45              | 5.96       | 6.45              |
| `--theme-color-warning`         | `#8a5a00` | 5.93              | 5.48       | 5.93              |
| `--theme-color-danger`          | `#b3261e` | 6.54              | 6.04       | 6.54              |

In light the raised surface is the background colour, so its column repeats
the first one.

### Dark

Dark means dark. The background is near-black rather than a grey card on grey;
surfaces step **up** in lightness; and the accents invert, because a saturated
blue that carries on white is unreadable on near-black. `--theme-color-primary`
becomes a light tint and `--theme-color-on-primary` becomes the background.

| Token                           | Hex       | vs background     | vs surface | vs surface-raised |
|---------------------------------|-----------|-------------------|------------|-------------------|
| `--theme-color-background`      | `#0f1319` | —                 | —          | —                 |
| `--theme-color-surface`         | `#161c25` | —                 | —          | —                 |
| `--theme-color-surface-raised`  | `#1d2531` | —                 | —          | —                 |
| `--theme-color-primary`         | `#82abff` | 8.18              | 7.51       | 6.77              |
| `--theme-color-primary-hover`   | `#9dbeff` | 9.97              | 9.16       | 8.26              |
| `--theme-color-on-primary`      | `#0f1319` | 8.18 on primary   | —          | —                 |
| `--theme-color-secondary`       | `#4fd1c5` | 9.99              | 9.18       | 8.27              |
| `--theme-color-secondary-hover` | `#6ee0d6` | 11.79             | 10.84      | 9.77              |
| `--theme-color-on-secondary`    | `#0f1319` | 9.99 on secondary | —          | —                 |
| `--theme-color-text-primary`    | `#e9edf4` | 15.86             | 14.58      | 13.14             |
| `--theme-color-text-secondary`  | `#a9b4c5` | 8.89              | 8.17       | 7.36              |
| `--theme-color-text-muted`      | `#848fa1` | 5.70              | 5.24       | 4.72              |
| `--theme-color-border`          | `#29313d` | 1.42              | 1.30       | 1.18              |
| `--theme-color-border-strong`   | `#637183` | 3.74              | 3.44       | 3.10              |
| `--theme-color-success`         | `#4ade80` | 10.69             | 9.82       | 8.85              |
| `--theme-color-warning`         | `#fbbf24` | 11.16             | 10.25      | 9.24              |
| `--theme-color-danger`          | `#ff8a80` | 8.16              | 7.50       | 6.76              |

In dark, `--theme-color-surface-raised` is the **lightest** of the three
backgrounds, not the darkest, so it is where a colour loses the most contrast.
For every text colour but one the margin makes that academic.
`--theme-color-text-muted` is the exception: it sits closest to 4.5:1 by
design, and form hints, dates and the current breadcrumb item regularly sit on
a raised surface. It is therefore held to 4.5:1 on
`--theme-color-surface-raised` as well: `#848fa1` reaches 4.72 there. The
previous `#808b9d` reached 5.41 and 4.97 against background and surface and
4.48:1 on the raised one (axe reports 4.47), which failed AA while the tables
listed only the first two backgrounds.

### Control boundaries

**A control boundary uses `--theme-color-border-strong`; `--theme-color-border`
is decorative only.** An empty text field is a box and nothing else, so its
edge is the visual information that identifies it, and WCAG 1.4.11 holds that
edge to 3:1 against the colours next to it: the control's own fill, which is
the raised surface, and whatever it sits on. `--theme-color-border` reaches
1.18 to 1.42 and separates content — cards, panels, the fieldset, the rule
under the tabs — where nothing has to be recognised as operable.

`--theme-color-border-strong` is held to 3:1 against all three backgrounds in
both appearances. In dark it used to fail exactly where controls sit: `#5f6c7d`
reached 3.48 and 3.20 against background and surface, but only 2.89:1 on the
raised surface. `#637183` keeps the hue (HSL 214°, 14 % saturation) and raises
the lightness from 43.1 % to 45.1 %, the smallest step that clears the raised
surface with the margin the light value has against the surface (3.10). Light
(3.35 / 3.10 / 3.35) was not affected and is unchanged.

The controls that draw their resting edge in it are the text input, textarea
and select (`--theme-input-border-color`), the addon of an input group, which
shares an edge with its control, and the track of the switch. Hover moves the
input's border to `--theme-color-text-secondary` (7.54 light, 7.36 dark on the
raised surface), a step further from every background in both appearances.
The validation states re-point the same tokens and win over both: invalid is
the danger colour at the strong border width, valid the success colour.

What is not a control boundary, and why it keeps the decorative border:

- **Text or glyph labelled controls** — the tabs, the pagination links, the
  navigation toggle, the segmented options of the display settings, the
  settings trigger. The label identifies the control, and the state is carried
  by a fill or a colour that clears 3:1 on its own (the selected segment and
  the current page are filled with the primary accent). Their borders frame.
- **Grouping** — the fieldset, the segmented track, the `details` frame.
- **Native checkboxes, radios and the range track** — themed with
  `accent-color` only. The unchecked widget is drawn by the browser, and the
  checked one in the primary accent.
- **Buttons** — the outlined `--secondary` variant draws its border in the
  primary accent, which clears 4.5:1.

axe checks text contrast only and reports none of this.
`Tests/Unit/StylesheetTest` computes `--theme-color-border-strong` against the
three backgrounds of both appearances from `_tokens.scss`, and
`Tests/Unit/ComponentLibraryTest` asserts that the controls above default to
it.

### Semantic colour

Three tokens per meaning, because one colour cannot do all three jobs. The
accent is a **text and border** colour, `on-*` is the **foreground on a solid
fill** of it, and `*-surface` is the **soft tint** an alert or a badge sits on.
Each was checked for the job it actually does.

| Meaning | Accent (light / dark) | On (light / dark)     | Surface (light / dark) |
|---------|-----------------------|-----------------------|------------------------|
| success | `#146c43` / `#4ade80` | `#ffffff` / `#0f1319` | `#e6f2ec` / `#12251b`  |
| warning | `#8a5a00` / `#fbbf24` | `#ffffff` / `#0f1319` | `#fbf0dd` / `#2a2113`  |
| danger  | `#b3261e` / `#ff8a80` | `#ffffff` / `#0f1319` | `#fbeae9` / `#2e1717`  |
| info    | aliases `primary`     | aliases `on-primary`  | `#e7effc` / `#152033`  |

`info` deliberately **aliases the primary accent** rather than introducing a
fifth hue. An informational message is not a different kind of thing from the
theme's own accent, and a palette should not have to author one more colour to
say so.

Accent-on-tint clears 4.5:1 in every combination (lowest: warning light, 5.25),
and so does body text on a tint (lowest: dark warning, 13.49). Solid fills clear
4.5:1 with their `on-*` foreground (lowest: warning light, 5.93).

### Alert asides

Two alert kinds are not severities, and neither gets a semantic colour of its
own. `--note` sits on `--theme-color-surface` with `--theme-color-text-secondary`
as its accent: 6.97 light and 8.17 dark against that surface, and body text on
it at 16.45 and 14.58 — values of the neutral tables above. `--tip` follows the
palette. Its accent is `--theme-color-secondary`, and its tint is **derived, not
declared**:

```css
color-mix(in oklab, var(--theme-color-secondary) 12%, var(--theme-color-background))
```

A palette varies accents only. A declared `--theme-color-secondary-surface`
would be one more colour for every palette to author, and a second copy of the
accent that could drift away from it; mixed from the accent, the tint cannot.

Computed, not estimated — the mix in Oklab, converted to sRGB, against the
background of its own appearance:

| Palette | Appearance | Tint      | Accent on tint | Text on tint |
|---------|------------|-----------|----------------|--------------|
| neutral | light      | `#e4eeed` | 4.63           | 15.04        |
| neutral | dark       | `#18262a` | 8.35           | 13.26        |
| ember   | light      | `#efe9e2` | 5.62           | 14.78        |
| ember   | dark       | `#242524` | 9.01           | 13.13        |
| ocean   | light      | `#e4edee` | 5.08           | 14.91        |
| ocean   | dark       | `#19262c` | 8.47           | 13.24        |
| moss    | light      | `#eaebe3` | 5.44           | 14.83        |
| moss    | dark       | `#212624` | 9.33           | 13.07        |
| violet  | light      | `#f6e6ed` | 5.49           | 14.81        |
| violet  | dark       | `#26202a` | 7.14           | 13.50        |

The accent is the leading border and the icon, which WCAG 1.4.11 holds to 3:1;
it clears 4.5:1 in every combination (lowest: neutral light, 4.63). Title and
body text are `--theme-color-text-primary` on the tint (lowest: moss dark,
13.07). `Tests/Acceptance/styleguide.spec.ts` paints the tip in every palette
and both appearances and compares the pixel with the tint recorded here, which
is what holds this table to the formula in `components/_alert.scss`.

`--theme-color-overlay` is the scrim behind a modal or an off-canvas panel:
`rgb(20 24 31 / 55%)` light, `rgb(0 0 0 / 65%)` dark.

### Palettes

The neutral palette above is the **default**. Four alternates ship, and each
varies **accents only** — `primary`, `secondary`, their hover states and the
focus ring. Neutrals, semantic colour, spacing, radius and typography are
shared, which is what keeps a palette to a single block.

```html
<html data-palette="ocean">
```

| Palette           | Primary (light / dark) | Secondary (light / dark) |
|-------------------|------------------------|--------------------------|
| neutral (default) | `#0b57d0` / `#82abff`  | `#0f766e` / `#4fd1c5`    |
| ember             | `#9a4212` / `#f0a882`  | `#7c5310` / `#e8c16a`    |
| ocean             | `#00629a` / `#7cc4ee`  | `#0d6d76` / `#63cfd8`    |
| moss              | `#2f6a26` / `#8fd782`  | `#5c6218` / `#cbd06a`    |
| violet            | `#6a3ba8` / `#c2a4f2`  | `#a03270` / `#f090c0`    |

All 60 pairs were computed. Every accent clears 4.5:1 against both the
background and the surface of its own appearance — the lowest is ocean
secondary on light surface, at 5.60 — and every `on-*` foreground clears 4.5:1
against its fill.

**Why a development theme ships more than one.** Not for variety. An extension
that renders correctly across every palette in both appearances is one that is
not hardcoding colour, and that is exactly the class of defect this theme exists
to surface. The palettes are a test surface.

### How the appearance is selected

```css
:root                     { color-scheme: light dark; }  /* follow the system */
:root[data-theme='light'] { color-scheme: light; }
:root[data-theme='dark']  { color-scheme: dark; }
```

The system preference decides while no attribute is set, and a `data-theme`
attribute on the root element overrides it **in both directions**.

This does **not** work by changing what `prefers-color-scheme` matches — that
stays tied to the operating system and cannot be influenced from CSS. It works
because `light-dark()` resolves against `color-scheme`, and `color-scheme` is
an ordinary property that any selector can set. Setting it per appearance also
means form controls, scrollbars and the canvas follow along.

## Spacing

**Base unit: 5px.** Measured — the reference's auto-layout gaps are 5, 10, 15, 25,
30, 40 and 45, with 10 the workhorse (12 distinct layers). Off-grid values (6, 16,
18, 21, 31) each occur on a single layer and were read as noise, not system.

> One trap worth recording: counting *instances* rather than *layers* said 16px
> and 20px padding dominated everything. Both turned out to be the padding of a
> single small-button component, repeated some three hundred times. Weighting by
> distinct layer is what makes the 5px grid visible.

| Token             | Value     | px | Origin   |
|-------------------|-----------|----|----------|
| `--theme-space-0` | 0         | 0  | —        |
| `--theme-space-1` | 0.3125rem | 5  | measured |
| `--theme-space-2` | 0.625rem  | 10 | measured |
| `--theme-space-3` | 0.9375rem | 15 | measured |
| `--theme-space-4` | 1.25rem   | 20 | measured |
| `--theme-space-5` | 1.5625rem | 25 | measured |
| `--theme-space-6` | 1.875rem  | 30 | measured |
| `--theme-space-7` | 2.5rem    | 40 | measured |
| `--theme-space-8` | 3.75rem   | 60 | extended |

Expressed in rem so spacing grows when a reader enlarges the base font size.
Step 8 is added: the reference is a dense screen and carries no page-level
rhythm to measure.

## Border radius

The reference has **exactly two** radii: `5px` across 174 fills, and a pill. There is no 3-step scale to extract, and inventing one would be
fabrication.

| Token                 | Value  | Origin                         |
|-----------------------|--------|--------------------------------|
| `--theme-radius-none` | 0      | —                              |
| `--theme-radius-sm`   | 5px    | measured — *the* system radius |
| `--theme-radius-md`   | 10px   | extended, on the 5px grid      |
| `--theme-radius-full` | 9999px | measured (pills)               |

In px, not rem: a corner should not grow with the root font size.

Borders: `--theme-border-width` 1px (160 layers), `--theme-border-width-strong`
2px. Both measured.

## Shadows

**None. The design is flat.**

This is not a simplification — the reference contains **zero** visible effects
of any kind. There are no elevation tokens, and adding one is a design
decision that needs a source rather than a default.

The single exception is focus, which is a requirement and not decoration:

| Token                      | Value                                                       |
|----------------------------|-------------------------------------------------------------|
| `--theme-focus-ring-color` | `rgb(11 87 208 / 35%)` light, `rgb(130 171 255 / 40%)` dark |
| `--theme-focus-ring`       | `0 0 0 3px var(--theme-focus-ring-color)`                   |

Applied through `:focus-visible`. Split in two because `light-dark()` takes
colours and not shadows; the composite stays a token so a site package can
still replace the whole ring in one declaration. A palette overrides the colour
half, so the ring follows the accent.

## Controls

| Token                      | Value | Origin                                    |
|----------------------------|-------|-------------------------------------------|
| `--theme-tap-target-min`   | 44px  | WCAG 2.2 §2.5.8 Target Size (Minimum), AA |
| `--theme-opacity-disabled` | 0.5   | authored                                  |

44px is a **floor for anything clickable**, not a height for everything.

## Stacking

Named rather than numeric at the point of use, so two components cannot
disagree about what `10` means.

| Token               | Value |
|---------------------|-------|
| `--theme-z-sticky`  | 100   |
| `--theme-z-overlay` | 200   |
| `--theme-z-modal`   | 300   |
| `--theme-z-tooltip` | 400   |

## Motion

Authored — the reference is static and had nothing to measure.

| Token                     | Value                        |
|---------------------------|------------------------------|
| `--theme-duration-fast`   | 120ms                        |
| `--theme-duration-base`   | 200ms                        |
| `--theme-easing-standard` | `cubic-bezier(0.2, 0, 0, 1)` |

Both durations collapse to `1ms` under `prefers-reduced-motion: reduce`.

## Layout

`--theme-content-max-width: 75rem` (1200px). **Authored.** The reference's
containers are an artboard minus its margins, and a panel — neither is a
max-width declaration worth lifting.

1200px is deliberately the same number as the `theme.media.maxGalleryWidth`
TypoScript constant, which decides how wide images are processed. **If one
moves, the other has to.**

## What this file does not decide

Tokens are the vocabulary, not the design.

The **structural variant is chosen: Frame.** Components sit in a box — surface
fill, hairline border, the single 5px radius — and a content element carries a
visible outline labelled with its `CType`. That outline is a deliberate
development affordance rather than a style: it makes an element's boundary
something you can see while building against it, which is the whole reason this
theme exists. It is one token away from being switched off for a production
site package.

Two alternatives were rejected, and the reasons are worth keeping. *Rule* —
whitespace and hairline rules, no boxes — is the most faithful reading of the
flat finding above and would be the pick for a content site, but element
boundaries are invisible in it. *Band* — full-bleed alternating bands — is the
closest to how the TYPO3 v14 default theme feels, and is the only one with an
architectural cost: an element's appearance would depend on the band it sits in,
so the backend layout would have to pass a context down to every element.

What each component actually looks like in that variant, and which tokens it
consumes, is not settled here — that belongs with the components.
