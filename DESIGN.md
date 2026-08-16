# Design tokens

The design token specification of `sbuerk/theme-extension-development`.

Every token is a **CSS custom property**, declared in
[`Resources/Private/Scss/_tokens.scss`](Resources/Private/Scss/_tokens.scss) and
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
| Body            | sans   | 400    | 1rem / 16px                                         | 1.6         | 0        |
| Label / control | sans   | 500    | 0.875rem / 14px                                     | 1.1         | +0.05em  |
| Caption         | sans   | 500    | 0.75rem / 12px                                      | 1.5         | +0.05em  |
| Mono            | mono   | 400    | 0.9375rem / 15px                                    | 1.5         | 0        |

**Tracking is positive.** `+0.05em` is the reference's signature and perfectly
consistent across it — 0.8px at 16, 1.7px at 34, 0.6px at 12, 2.7px at 54, all
exactly 0.05em. Body prose is the exception at 0. This is
worth stating because the reflex for display type is to track *in*; this design
tracks *out*.

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

There is **no single modular ratio**. The steps run 1.17, 1.14, 1.5, 1.42, 1.59
— a real design, not a generated scale. That irregularity is preserved rather
than smoothed, because smoothing it would replace the measurement with
arithmetic.
`lg` (20px) is the one added step, needed for a third heading level and placed
on the 5px grid.

Weights: `400` regular, `500` medium, `800` bold. There is no 600 or 700 in the
reference. A system font may synthesise 800 or clamp it to its boldest weight.

Line heights: `tight` 1.1, `snug` 1.3, `heading` 1.5, `base` 1.6.
Tracking: `wide` 0.05em, `none` 0. Measure: 68ch (authored).

## Colour

**Authored, not extracted — deliberately.** This is a theme for *extension
development*. Its job is to make document structure legible without biasing the
design of the extension being built against it, so it does not wear another
product's brand. The palette is neutral, and the tokens are named by role so a
site package can drop its own values in.

Contrast was **computed**, not estimated, for every value, against both the
background and the surface of its own mode. Body text clears 4.5:1 (WCAG AA);
borders that delimit a control clear 3:1 (WCAG 1.4.11).

### Light

| Token                           | Hex       | vs background     | vs surface |
|---------------------------------|-----------|-------------------|------------|
| `--theme-color-background`      | `#ffffff` | —                 | —          |
| `--theme-color-surface`         | `#f4f6fa` | —                 | —          |
| `--theme-color-surface-raised`  | `#ffffff` | —                 | —          |
| `--theme-color-primary`         | `#0b57d0` | 6.39              | 5.90       |
| `--theme-color-primary-hover`   | `#0a4bb4` | 7.84              | 7.25       |
| `--theme-color-on-primary`      | `#ffffff` | 6.39 on primary   | —          |
| `--theme-color-secondary`       | `#0f766e` | 5.47              | 5.06       |
| `--theme-color-secondary-hover` | `#0c5f59` | 7.51              | 6.94       |
| `--theme-color-on-secondary`    | `#ffffff` | 5.47 on secondary | —          |
| `--theme-color-text-primary`    | `#14181f` | 17.79             | 16.45      |
| `--theme-color-text-secondary`  | `#4a5567` | 7.54              | 6.97       |
| `--theme-color-text-muted`      | `#616b80` | 5.35              | 4.95       |
| `--theme-color-border`          | `#d6dce6` | 1.38              | 1.27       |
| `--theme-color-border-strong`   | `#828d9f` | 3.35              | 3.10       |
| `--theme-color-success`         | `#146c43` | 6.45              | 5.96       |
| `--theme-color-warning`         | `#8a5a00` | 5.93              | 5.48       |
| `--theme-color-danger`          | `#b3261e` | 6.54              | 6.04       |

### Dark

Dark means dark. The background is near-black rather than a grey card on grey;
surfaces step **up** in lightness; and the accents invert, because a saturated
blue that carries on white is unreadable on near-black. `--theme-color-primary`
becomes a light tint and `--theme-color-on-primary` becomes the background.

| Token                           | Hex       | vs background     | vs surface |
|---------------------------------|-----------|-------------------|------------|
| `--theme-color-background`      | `#0f1319` | —                 | —          |
| `--theme-color-surface`         | `#161c25` | —                 | —          |
| `--theme-color-surface-raised`  | `#1d2531` | —                 | —          |
| `--theme-color-primary`         | `#82abff` | 8.18              | 7.51       |
| `--theme-color-primary-hover`   | `#9dbeff` | 9.97              | 9.16       |
| `--theme-color-on-primary`      | `#0f1319` | 8.18 on primary   | —          |
| `--theme-color-secondary`       | `#4fd1c5` | 9.99              | 9.18       |
| `--theme-color-secondary-hover` | `#6ee0d6` | 11.79             | 10.84      |
| `--theme-color-on-secondary`    | `#0f1319` | 9.99 on secondary | —          |
| `--theme-color-text-primary`    | `#e9edf4` | 15.86             | 14.58      |
| `--theme-color-text-secondary`  | `#a9b4c5` | 8.89              | 8.17       |
| `--theme-color-text-muted`      | `#808b9d` | 5.41              | 4.97       |
| `--theme-color-border`          | `#29313d` | 1.42              | 1.30       |
| `--theme-color-border-strong`   | `#5f6c7d` | 3.48              | 3.20       |
| `--theme-color-success`         | `#4ade80` | 10.69             | 9.82       |
| `--theme-color-warning`         | `#fbbf24` | 11.16             | 10.25      |
| `--theme-color-danger`          | `#ff8a80` | 8.16              | 7.50       |

`--theme-color-border` is not required to reach 3:1: it is decorative
separation, not the boundary of a control. `--theme-color-border-strong` is the
one to use where a border carries meaning.

### How light and dark are selected

```css
:root                       { /* light — the default */ }
@media (prefers-color-scheme: dark) {
    :root:not([data-theme='light']) { /* dark */ }
}
:root[data-theme='dark']    { /* dark */ }
```

The system preference wins by default, and a `data-theme` attribute on the root
element overrides it **in both directions**. No JavaScript ships with the
extension; the attribute is there for whoever wants to build a switch.
`color-scheme` is set per mode so form controls and scrollbars follow.

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

| Token                | Value                                         |
|----------------------|-----------------------------------------------|
| `--theme-focus-ring` | `0 0 0 3px` in `--theme-color-primary` at 35% |

Applied through `:focus-visible`.

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

Tokens are the vocabulary, not the design. Component and layout design — how a
page is composed, what a card looks like, how the content elements use this
vocabulary — has not started. The stylesheet applies the tokens to an element
baseline and to the image gallery, and nothing else yet.
