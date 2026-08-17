..  include:: /Includes.rst.txt

..  _feature-appearance-switcher:

=============================
Feature: Appearance switcher
=============================

Description
===========

The theme now ships the script and the control that let a visitor change
appearance, palette, and the main menu - the piece
:ref:`Feature: Navigation <feature-navigation>` shipped without. Three new
TypoScript constants under `theme.appearance`, in
:file:`Configuration/TypoScript/Appearance.typoscript`, set the server-rendered
default; an inline script and :file:`Resources/Public/JavaScript/theme.js`
apply a visitor's stored choice and operate the controls from there.

..  list-table::
    :header-rows: 1

    *   -   Constant
        -   Values
        -   Default
    *   -   :typoscript:`theme.appearance.default`
        -   :typoscript:`auto`, :typoscript:`light`, :typoscript:`dark`
        -   :typoscript:`auto`
    *   -   :typoscript:`theme.appearance.palette`
        -   :typoscript:`neutral`, :typoscript:`ember`, :typoscript:`ocean`,
            :typoscript:`moss`, :typoscript:`violet`
        -   :typoscript:`neutral`
    *   -   :typoscript:`theme.appearance.contentOutline`
        -   :typoscript:`on`, :typoscript:`off`
        -   :typoscript:`on`

These are constants, not Site Settings. A constant is read identically by the
site set and by the classic :php:`sys_template` static include, which is the
property this theme is built around; a :file:`settings.definitions.yaml` would
only serve the set and introduce a second source of truth for the same value.
A site package that wants these editable in the Site Settings UI can still
declare them in its own set.

Server-rendered attributes
===========================

:typoscript:`config.htmlTag.attributes.*` writes every value onto the
:html:`<html>` tag **raw** - :php:`RequestHandler::generateHtmlTag()` only
applies :typoscript:`stdWrap` when a matching `.` sub-array is configured, and
none is here - so a constant is the only thing that can go on this path, never
a cObject:

..  code-block:: typoscript

    config.htmlTag.attributes.data-palette = {$theme.appearance.palette}
    config.htmlTag.attributes.data-theme-content-outline = {$theme.appearance.contentOutline}

    ["{$theme.appearance.default}" != "auto"]
        config.htmlTag.attributes.data-theme = {$theme.appearance.default}
    [END]

:typoscript:`data-theme` is behind that condition rather than assigned
directly because :typoscript:`auto` means the **absence** of the attribute,
not a value for it - no selector matches :html:`data-theme="auto"`, and
rendering it would look like a working default while doing nothing.

The no-flash script
====================

An inline script in the document head applies a stored appearance and palette
before first paint. It is emitted through :typoscript:`page.headerData`
rather than :typoscript:`f:asset.script`, because the asset collector may move
a script to the end of the body, and a stored dark appearance would then paint
light first - the exact flash this script exists to prevent.

It sets :html:`data-js` on the root **first and unconditionally**, then reads
:js:`localStorage` for the stored appearance and palette, each inside its own
:js:`try`/:js:`catch`. :js:`localStorage` throws rather than returning
:js:`null` in Safari's private mode and when cookies are blocked, and an
uncaught throw would abort the script before the marker was set - which is
why the marker comes first, not after. Losing it costs more than a missed
colour choice: :html:`data-js` is also what
:ref:`the navigation collapse <feature-navigation>` and the switcher's
visibility are gated behind, so a page that lost it keeps the always-expanded
navigation and a permanently hidden switcher for the rest of that visit.

The switcher itself is hidden until :html:`data-js` is set, for the same
reason: a control that cannot work yet is a visible promise the page cannot
keep, not a degraded one.

The module script
==================

:file:`Resources/Public/JavaScript/theme.js`, loaded with
:typoscript:`page.includeJSFooter` and :typoscript:`.type = module`. A module
is deferred by specification, so it needs no :html:`defer` attribute, and it
runs in the footer rather than the head because it only wires up listeners on
elements that already exist by then. It owns the appearance control, the
palette control, and - now that its script has arrived - the main menu
toggle that :ref:`Feature: Navigation <feature-navigation>` shipped inert.

The file uses no optional chaining (:js:`?.`) anywhere. A browser that does
not recognise :html:`type="module"` skips the element unparsed and can never
fail on syntax inside it, but "recognises modules" and "supports optional
chaining" are not the same floor - module support landed 2017-2018, optional
chaining in 2020 - and a syntax error anywhere in a module aborts the whole
file, with no per-statement fallback the way a classic script has. Plain
:js:`if` checks cost nothing and remove that failure mode entirely.

Palette swatches
=================

Each palette button in the switcher carries a small swatch in that palette's
primary colour. The swatch colour is the one duplicated colour in the
stylesheet: every palette lives entirely inside its own
:css:`[data-palette='…']` selector, and CSS has no mechanism to ask what a
custom property *would* resolve to under a different attribute value, so the
swatch cannot reference the token live. :php:`ComponentLibraryTest::everyPaletteHasASwatchInTheSwitcher`
is what keeps the copy from drifting out of step with
:file:`abstracts/_palettes.scss`.

Impact
======

No cookie is set anywhere in this mechanism, and nothing is persisted server
side. :js:`localStorage` is the whole story - two keys,
:js:`theme-appearance` and :js:`theme-palette` - which is also why there is no
consent question to design around: the choice never leaves the browser it was
made in.

The main menu toggle, shipped inert in
:ref:`Feature: Navigation <feature-navigation>`, is now fully wired: clicking
it flips :html:`aria-expanded`, :kbd:`Escape` and an outside click close the
menu again. Nothing about the navigation's no-JavaScript layout changes -
the collapse remains gated behind :html:`data-js`, exactly as before.

..  note::

    Changing appearance or palette never moves focus, and no live region
    announces the change - a visible palette change does not need one, and one
    would be noise on every click.
