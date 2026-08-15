..  include:: /Includes.rst.txt

..  _feature-content-element-rendering:

==================================
Feature: Content element rendering
==================================

Description
===========

The theme renders content elements itself, without depending on
``fluid_styled_content``.

Two content elements are rendered, and they are the only two that exist in an
installation without that extension:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Rendered
    *   -   :guilabel:`Header`
        -   The heading, at the level chosen in :guilabel:`Type`. The
            :guilabel:`Hidden` option of that field is honoured.
    *   -   :guilabel:`Text`
        -   The heading and the rich text of the element.

Both are wrapped in an element frame carrying the familiar ``c<uid>`` anchor, so
links to a content element keep working.

..  warning::

    :guilabel:`Text & Media`, :guilabel:`Images`, :guilabel:`Bullet List`,
    :guilabel:`Table`, :guilabel:`File Links` and the menu elements are
    registered by ``fluid_styled_content``, not by the TYPO3 core. In an
    installation without that extension they do not exist as content types at
    all, so they are not merely unstyled here - they cannot be created.

    Bringing them means registering the content types and their TCA in this
    extension, which is a separate step.

Overriding the templates
========================

The element templates live below :file:`Resources/Private/Templates/ContentElements/`
and use the same Fluid paths as the page templates, so the constants under
``theme.`` redirect them together - see :ref:`configuration`.

The rendering definition itself is the TypoScript object ``lib.contentElement``,
which fills the role the object of the same name has in
``fluid_styled_content``.
