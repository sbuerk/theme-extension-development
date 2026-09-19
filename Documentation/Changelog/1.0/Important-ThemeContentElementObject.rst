..  include:: /Includes.rst.txt

..  _important-theme-content-element-object:

==========================================================
Important: Theme content elements have their own frame
==========================================================

Description
===========

The content elements of the theme's own - the :guilabel:`Theme` group
of the :guilabel:`Create new content element` wizard - are rendered through the
TypoScript object :typoscript:`lib.themeContentElement` instead of
:typoscript:`lib.contentElement`. The classic content elements stay on
:typoscript:`lib.contentElement`.

:typoscript:`lib.contentElement` is a name the theme shares with
``fluid_styled_content``, and that extension clears the object before it
defines it. Loaded after the theme, it took the theme's template paths with
it, and every element of the theme's own failed to find its template. An
object of the theme's own name is not cleared by anything else, so these
elements render the same whether ``fluid_styled_content`` is installed or not.

Impact
======

Nothing changes for an installation that sets its template paths through the
constants: :typoscript:`theme.templateRootPath`,
:typoscript:`theme.partialRootPath` and :typoscript:`theme.layoutRootPath` set
both objects.

An installation that adds root paths to :typoscript:`lib.contentElement`
directly now reaches the classic content elements only. To override a template
of a theme element that way, add the same root path to
:typoscript:`lib.themeContentElement`:

..  code-block:: typoscript

    lib.themeContentElement.templateRootPaths.20 = EXT:my_site_package/Resources/Private/Templates/

..  index:: Frontend, TypoScript
