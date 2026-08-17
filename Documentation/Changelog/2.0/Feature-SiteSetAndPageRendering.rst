..  include:: /Includes.rst.txt

..  _feature-site-set-page-rendering:

====================================
Feature: Site set and page rendering
====================================

Description
===========

The theme now renders pages, and ships a **site set** to enable it.

A site enables the theme by depending on the set in its site configuration:

..  code-block:: yaml

    dependencies:
      - sbuerk/theme-extension-development

No :guilabel:`sys_template` record is needed. The set brings the TypoScript, a
Fluid based page rendering and the compiled stylesheet with it.

For installations that do not use site sets, the theme additionally registers a
classic static template, selectable in the :guilabel:`Include static (from
extensions)` field of a :guilabel:`sys_template` record. The two mechanisms are
safe side by side: the static include detects an active set and skips its own
import, so a site configured with both is not served the theme twice.

What is rendered
================

*   A page object with a Fluid template, layout and partials below
    :file:`Resources/Private/`.
*   The compiled stylesheet from :file:`Resources/Public/Css/theme.css`.
*   The content of the normal column.

The Fluid paths and the stylesheet are TypoScript constants under ``theme.``, so
an integrator can point them at their own files without editing the extension —
see :ref:`configuration`.

..  note::

    Content element rendering is described in
    :ref:`feature-content-element-rendering`.

    Every page currently renders the same template. Backend layout support
    follows later.
