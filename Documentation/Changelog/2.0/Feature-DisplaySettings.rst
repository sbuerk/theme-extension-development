..  include:: /Includes.rst.txt

..  _feature-display-settings:

==============================================
Feature: Display settings behind a cog button
==============================================

Description
===========

The appearance and palette button groups of the site header are replaced by a
single settings button with a cog at the end of the header. It opens a panel
holding:

*   **Appearance** - Auto, Light and Dark, as one segmented control.
*   **Palette** - the five palettes as a list, each shown with its primary and
    its secondary colour.
*   **Element outlines** - a switch for the outline and the ``CType`` label
    around every content element, which could only be set with the constant
    :typoscript:`theme.appearance.contentOutline` so far.
*   **Reset** - back to the defaults of the site.

The two button groups took so much of the header that the site title and the
main navigation had almost no room left. With one button, the brand, the main
navigation and the settings share a single row at every width; below the
breakpoint of the main navigation the row holds the brand, the menu toggle and
the cog, and the expanded menu drops down under the header.

A choice applies at once and is kept in :js:`localStorage` under three keys:
:js:`theme-appearance`, :js:`theme-palette` and, new,
:js:`theme-content-outline`. The inline script in the document head applies a
stored outline before first paint, like the other two. No cookie is set.

The panel is a disclosure holding native radio groups and a switch, not an
ARIA menu: the radios keep their own keyboard behaviour, :kbd:`Escape` closes
the panel and returns focus to the cog, and Tab past the last control or a
click outside closes it. Under forced colours the checked segment and the
switch paint with system colours, so they stay distinguishable. The panel id
and the radio names derive from the optional partial argument ``idPrefix``
(``theme-settings`` by default), so a page can carry a second instance.

The defaults of the site
========================

The three constants of :ref:`feature-appearance-switcher` stay what configures
the defaults:

..  code-block:: typoscript

    theme.appearance.default = auto
    theme.appearance.palette = neutral
    theme.appearance.contentOutline = on

They now also reach the page template, as the FLUIDTEMPLATE settings
:typoscript:`settings.appearance.default`,
:typoscript:`settings.appearance.palette` and
:typoscript:`settings.appearance.contentOutline`. The settings control checks
its initial options from them and renders them as
:html:`data-theme-default-appearance`, :html:`data-theme-default-palette` and
:html:`data-theme-default-content-outline`, which is where :guilabel:`Reset`
takes them from. :guilabel:`Reset` removes the three stored keys rather than
storing the defaults, so a visitor who reset follows the constants of the site
again, including a constant changed afterwards.

A stored "Auto" is now kept as :js:`auto` instead of removing the key, so on a
site whose default appearance is not :typoscript:`auto` the choice is no longer
lost on the next page.

Impact
======

:file:`Resources/Private/Partials/Page/Settings.html` replaces
:file:`Resources/Private/Partials/Page/AppearanceSwitcher.html`, and
:file:`Resources/Private/Scss/components/_settings.scss` replaces
:file:`components/_appearance-switcher.scss`. The classes
:css:`.theme-appearance-switcher` and :css:`.theme-appearance*` are gone; the
new ones are :css:`.theme-settings`, :css:`.theme-segmented`,
:css:`.theme-swatch-list`, :css:`.theme-swatch-option` and
:css:`.theme-swatch`. A site package overriding
:file:`Partials/Page/Header.html` renders the partial :file:`Page/Settings`
instead of :file:`Page/AppearanceSwitcher`.

The switch of the panel is a general form control, :css:`.theme-switch` in
:file:`forms/_controls.scss`: a checkbox with :html:`role="switch"`, for a
setting that takes effect the moment it is flipped.

..  code-block:: html

    <label class="theme-switch">
        <input type="checkbox" role="switch" checked> <span>Element outlines</span>
    </label>

The language labels ``theme.settingsLabel``,
``theme.settingsContentOutline`` and ``theme.settingsReset`` are
added; the appearance and palette labels are used as before.
