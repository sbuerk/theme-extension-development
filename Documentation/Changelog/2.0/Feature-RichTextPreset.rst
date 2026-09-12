..  include:: /Includes.rst.txt

..  _feature-rich-text-preset:

===============================
Feature: Rich text preset
===============================

Description
===========

The theme ships a preset for the rich text editor, :yaml:`theme`, and selects
it for every rich text field. Its styles and alignments write the classes the
theme styles, instead of the Bootstrap classes of the core preset, which the
theme does not style:

..  list-table::
    :header-rows: 1

    *   -   Offered as
        -   Writes
    *   -   :guilabel:`Lead`, :guilabel:`Eyebrow`
        -   A paragraph with :html:`class="theme-lead"` or
            :html:`class="theme-eyebrow"`
    *   -   :guilabel:`Small print`, :guilabel:`Marked`,
            :guilabel:`Keyboard input`, :guilabel:`Code`
        -   :html:`<small>`, :html:`<mark>`, :html:`<kbd>`, :html:`<code>`
    *   -   Alignment left, centre, right, justify
        -   :html:`theme-text--start`, :html:`theme-text--center`,
            :html:`theme-text--end`, :html:`theme-text--justify`

Right and left follow the direction of the text. Justified text is hyphenated
in the language of the page.

The preset needs the system extension ``rte_ckeditor``, which the theme
suggests and does not require. Without it the preset is not registered and
rich text fields behave as before.

Impact
======

The preset is selected by page TSconfig for the whole installation:

..  code-block:: typoscript

    RTE.default.preset = theme

The line comes from :file:`Configuration/page.tsconfig` of the extension,
which TYPO3 loads for **every page tree of the installation**, the same way
the theme's backend layouts are registered. So it also reaches a site that
does not use the theme. That site selects its own preset in its own page
TSconfig, which the core loads after that of the extensions. The TSconfig can
come from the site's set, from :file:`config/sites/<site>/page.tsconfig`, or
from the :guilabel:`Page TSconfig` field of its root page:

..  code-block:: typoscript

    RTE.default.preset = default

A preset selected for a single field or type still takes precedence. To add
styles, a site package imports
:file:`EXT:theme_extension_development/Configuration/RTE/Theme.yaml` into a
preset of its own and registers that one - see the developer documentation of
the extension.

Content saved with the core preset keeps its Bootstrap classes, which the theme
does not style.

..  index:: Backend, RTE, TSConfig, ext:rte_ckeditor
