..  include:: /Includes.rst.txt

..  _important-control-border-contrast:

===============================================
Important: Form controls draw a stronger border
===============================================

Description
===========

The text input, the textarea, the select and the addon of an input group
now draw their resting border in :css:`--theme-color-border-strong` instead
of the decorative :css:`--theme-color-border`. An empty text field is
identified by its edge alone, and WCAG 1.4.11 requires 3:1 against the
colours next to it; the decorative border reached about 1.4:1.

On hover the border of an input now turns to
:css:`--theme-color-text-secondary`, since the colour it used to turn to is
now its resting colour. The invalid and valid states are unchanged.

The dark value of :css:`--theme-color-border-strong` moves from
:css:`#5f6c7d` to :css:`#637183`. The old value reached only 2.89:1 on
:css:`--theme-color-surface-raised`, the background of a control; the new one
reaches 3.10 there, and 3.74 and 3.44 on background and surface. The light
value is unchanged.

Impact
======

Form controls look more defined in both appearances, and every border drawn
in :css:`--theme-color-border-strong` - the switch track, the display
settings panel, the dialog, table headers, :html:`kbd` - is slightly lighter
in dark.

A site package that preferred the previous look re-points the component
tokens in its own CSS, without rebuilding the theme:

..  code-block:: css

    .theme-input,
    .theme-textarea,
    .theme-select {
        --theme-input-border-color: var(--theme-color-border);
        --theme-input-border-color-hover: var(--theme-color-border-strong);
    }

    .theme-input-group {
        --theme-input-group-addon-border-color: var(--theme-color-border);
    }

The selectors carry the same specificity as the theme's own rules, and the
theme uses no cascade layers, so the site package's stylesheet has to be
included after :file:`theme.css`. The invalid and valid states still win,
they are more specific.

Doing so gives up the 3:1 boundary of the controls again.
