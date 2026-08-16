..  include:: /Includes.rst.txt

..  _feature-design-tokens:

======================================================
Feature: Design tokens, with light and dark appearance
======================================================

Description
===========

The stylesheet is built from a documented set of **design tokens** covering
typography, colour, spacing, border radius, borders, focus, motion and layout
width. Every one of them is a CSS custom property.

The theme now follows the **appearance the visitor asked for**. A light palette
is the default, a dark palette is used when the operating system asks for one,
and an explicit request on the root element overrules both:

..  code-block:: html

    <html data-theme="dark">
    <html data-theme="light">

No JavaScript ships with the extension - the attribute is there for whoever
wants to build a switch. :css:`color-scheme` is declared per appearance, so
form controls, scrollbars and the canvas follow along.

Re-theming without a build
==========================

Custom properties survive compilation, so a site package can re-theme the
extension from its own CSS without rebuilding the SCSS:

..  code-block:: css

    :root {
        --theme-color-primary: #7b1fa2;
        --theme-color-primary-hover: #6a1b9a;
        --theme-font-family-sans: 'Mulish', system-ui, sans-serif;
    }

    :root[data-theme='dark'] {
        --theme-color-primary: #ce93d8;
    }

The full token list, what each value is for and where it came from is in
:file:`DESIGN.md` in the repository root.

Impact
======

The palette is deliberately **neutral**. This is a theme for extension
development, and its job is to make document structure legible without biasing
the design of the extension being built against it.

Every colour was checked for contrast against both the background and the
surface of its own appearance: body text clears 4.5:1 and any border that
delimits a control clears 3:1.

The design is **flat**. There are no elevation tokens, and the only shadow is
the focus ring, because a visible focus indicator is a requirement rather than
decoration.

..  note::

    The token names changed with this release, which is a rename and not a
    breaking change: version 1.0 has not been released yet.

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
