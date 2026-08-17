..  include:: /Includes.rst.txt

..  _feature-content-element-rendering:

==================================
Feature: Content element rendering
==================================

Description
===========

The theme renders content elements itself, without depending on
``fluid_styled_content``.

Two content elements are rendered:

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

    :guilabel:`Text & Media`, :guilabel:`Bullet List`, :guilabel:`Table`,
    :guilabel:`File Links` and the menu elements can be created in the backend -
    their TCA comes from EXT:frontend, not from ``fluid_styled_content`` - but
    they have no rendering definition here yet and therefore render the TYPO3
    notice saying so.

    :guilabel:`Images` is rendered, see
    :ref:`feature-image-content-element-rendering`.

Overriding the templates
========================

The element templates live below :file:`Resources/Private/Templates/ContentElements/`
and use the same Fluid paths as the page templates, so the constants under
``theme.`` redirect them together - see :ref:`configuration`.

The rendering definition itself is the TypoScript object ``lib.contentElement``,
which fills the role the object of the same name has in
``fluid_styled_content``.
