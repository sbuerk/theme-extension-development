..  include:: /Includes.rst.txt

..  _feature-font-awesome-icons:

========================================
Feature: Font Awesome Free solid icons
========================================

Description
===========

The theme ships the complete solid style of Font Awesome Free 7.3.1 - 2001
icons - and renders an icon inline as SVG with the new ViewHelper
:html:`<theme:icon>`:

..  code-block:: html

    <html xmlns:theme="http://typo3.org/ns/SBUERK/ThemeExtensionDevelopment/ViewHelpers"
          data-namespace-typo3-fluid="true">

    <theme:icon name="circle-info" />
    <theme:icon name="gear" class="theme-settings__icon" />
    <theme:icon name="circle-info" label="Information" />

The name is the file name below
:file:`Resources/Public/Icons/FontAwesome/Solid/` without :file:`.svg`. Without
:html:`label` the icon is decoration and carries :html:`aria-hidden="true"`;
with it, it is :html:`role="img"` named by the label. The new component
:css:`.theme-icon` makes it one em square and fills it with the text colour. An
unknown name throws an exception.

No webfont, no CDN and no request is involved. The icons are licensed under
CC BY 4.0; :file:`LICENSE.txt` and :file:`ATTRIBUTION.txt` ship next to them,
and every rendered icon keeps the attribution comment of its file.

The glyphs the theme drew by hand are now icons of the set:

*   the cog and the three appearance options of the display settings, and
    the check mark of the chosen palette,
*   the icon of each alert kind - in the notice content element and in the
    messages of the login form,
*   the cross of the close button,
*   the chevron of the accordion,
*   the error and success messages of form fields,
*   and the toggle of the main navigation, which shows the :html:`bars` icon
    before its label.

What the stylesheet still draws itself is component geometry, not icons: the
arrow of the tooltip, the spinner of a busy button and the thumb of the
switch.

The styleguide has a new section, :guilabel:`Icons`, listing every icon the
theme uses by name.

Impact
======

The markup of three components changes, which matters to a site package that
writes it itself rather than rendering the partials of the theme:

*   :css:`.theme-close` no longer draws a cross. The icon is part of the
    markup:

    ..  code-block:: html

        <button class="theme-close" type="button" aria-label="Close"><theme:icon name="xmark" /></button>

    A close button without it is empty.

*   :css:`.theme-field__error` and :css:`.theme-field__success` no longer put a
    character in front of the message. The message starts with the icon:

    ..  code-block:: html

        <p class="theme-field__error" id="f-mail-error"><theme:icon name="circle-exclamation" /> …</p>

*   :css:`.theme-accordion__summary` no longer draws the chevron. The icon is
    the last thing in the summary:

    ..  code-block:: html

        <summary class="theme-accordion__summary">… <theme:icon name="chevron-down" class="theme-accordion__marker" /></summary>

*   The glyphs in :file:`Partials/ContentElement/AlertIcon.html` and
    :file:`Partials/Page/Settings.html` are icons of the set, in the
    :css:`.theme-icon` component. The check mark of a palette option is the
    :html:`check` icon in every option, shown on the checked one.
