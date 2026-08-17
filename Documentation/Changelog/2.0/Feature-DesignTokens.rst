..  include:: /Includes.rst.txt

..  _feature-design-tokens:

======================================================
Feature: Design tokens, with light and dark appearance
======================================================

Description
===========

The stylesheet is built from a documented set of **design tokens** covering
typography, colour, spacing, border radius, borders, focus, controls, stacking,
motion and layout width. Every one of them is a CSS custom property.

Every colour is declared **once** and carries both appearances, using the CSS
:css:`light-dark()` function:

..  code-block:: css

    :root {
        color-scheme: light dark;
        --theme-color-background: light-dark(#ffffff, #0f1319);
    }

Appearance
==========

The theme follows the **appearance the visitor asked for**. The operating
system decides by default, and an explicit request on the root element
overrules it in both directions:

..  code-block:: html

    <html data-theme="dark">
    <html data-theme="light">

..  code-block:: css

    :root                     { color-scheme: light dark; }
    :root[data-theme='light'] { color-scheme: light; }
    :root[data-theme='dark']  { color-scheme: dark; }

That is the whole mechanism. It works because :css:`light-dark()` resolves
against the *used value* of :css:`color-scheme`, so switching one property
switches every colour. It does **not** work by influencing what
:css:`prefers-color-scheme` matches, which is tied to the operating system and
cannot be changed from CSS.

Setting :css:`color-scheme` per appearance also makes form controls, scrollbars
and the canvas follow along.

No JavaScript ships with the extension - the attribute is there for whoever
wants to build a switch.

Palettes
========

Four alternate palettes ship alongside the neutral default. A palette varies
**accents only** - :css:`primary`, :css:`secondary`, their hover states and the
focus ring - while neutrals, semantic colour, spacing, radius and typography
stay shared:

..  code-block:: html

    <html data-palette="ocean">

Available: :html:`ember`, :html:`ocean`, :html:`moss`, :html:`violet`. Omitting
the attribute selects the neutral default.

For a theme whose purpose is extension development, the palettes are a **test
surface** rather than decoration: an extension that renders correctly across
every palette in both appearances is one that is not hardcoding colour.

Re-theming without a build
==========================

Custom properties survive compilation, so a site package can re-theme the
extension from its own CSS without rebuilding the SCSS:

..  code-block:: css

    :root {
        --theme-color-primary: light-dark(#7b1fa2, #ce93d8);
        --theme-font-family-sans: 'Mulish', system-ui, sans-serif;
    }

Impact
======

The palette is deliberately **neutral**. This is a theme for extension
development, and its job is to make document structure legible without biasing
the design of the extension being built against it.

Every colour was checked for contrast against both the background and the
surface of its own appearance: body text clears 4.5:1 and any border that
delimits a control clears 3:1. Semantic colour carries three tokens per meaning
- the accent for text and borders, :css:`--theme-color-on-*` for a foreground on
a solid fill, and :css:`--theme-color-*-surface` for the soft tint an alert sits
on - because one value cannot do all three jobs.

The design is **flat**. There are no elevation tokens, and the only shadow is
the focus ring, because a visible focus indicator is a requirement rather than
decoration. It is split into :css:`--theme-focus-ring-color` and a composite
built around it, because :css:`light-dark()` takes colours and not shadows.

..  note::

    :css:`light-dark()` is supported by Firefox 120, Chrome and Edge 123, and
    Safari 17.5 - Baseline since May 2024.

..  note::

    The token names changed while this was still unreleased, which makes it a
    rename rather than a breaking change: no version had shipped at that point.

    ..  list-table::
        :header-rows: 1

        *   -   Previously
            -   Now
        *   -   :css:`--theme-color-text`
            -   :css:`--theme-color-text-primary`
        *   -   :css:`--theme-color-accent`
            -   :css:`--theme-color-primary`
        *   -   :css:`--theme-font-family-base`
            -   :css:`--theme-font-family-sans`
        *   -   :css:`--theme-font-size-base`
            -   :css:`--theme-font-size-md`
        *   -   :css:`--theme-spacing-xs` … :css:`--theme-spacing-xl`
            -   :css:`--theme-space-1` … :css:`--theme-space-8`

    The spacing scale is now a **5px** grid - 5, 10, 15, 20, 25, 30, 40, 60 -
    rather than the doubling scale it replaced.
