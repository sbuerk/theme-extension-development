..  include:: /Includes.rst.txt

..  _feature-icon-content-elements:

=================================
Feature: Icon content elements
=================================

Description
===========

Content elements of the theme's own that show icons of the Font Awesome Free
solid set the theme ships, in the :guilabel:`Theme` group of the
:guilabel:`Create new content element` wizard:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Shows
    *   -   :guilabel:`Text and icon`
        -   A heading, rich text and a link beside one icon.

Every icon field offers the curated list of about a hundred icons the other
icon fields of the theme offer - see :ref:`feature-icon-picker`. The icon is
decoration: the text beside it says what it shows, so a screen reader skips
it.

Text and icon
-------------

The :guilabel:`Icon` palette holds the icon and three choices of how it is
drawn:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Values
    *   -   :guilabel:`Icon position`
        -   :guilabel:`At the start of the line` (the default),
            :guilabel:`At the end of the line`, :guilabel:`Above the text`
    *   -   :guilabel:`Icon shape`
        -   :guilabel:`Plain` (the default) - the icon in the accent colour;
            :guilabel:`On a square` and :guilabel:`On a circle` - the icon on a
            tile of the accent colour
    *   -   :guilabel:`Icon size`
        -   :guilabel:`Medium` (the default), :guilabel:`Large`,
            :guilabel:`Extra large`

The start and the end are those of the line, so in a right-to-left language
the icon of :guilabel:`At the start of the line` is on the right. The heading
takes the alignment and the style of the header palette, the text is rich
text, and the link has the style and the icon of every theme link. An element
without an icon shows its text alone.

The seeded demo tree shows every value on :file:`/elements/theme/text-icon`.

Impact
======

Integrators get a new content type to grant to editor groups:
``theme_text_icon``. Four columns are added:
``tt_content.tx_theme_icon_position``, ``tx_theme_icon_shape`` and
``tx_theme_icon_size``, and ``tx_theme_list_item.icon``, the icon of an item
of the inline list, which the elements with an icon per item use. All four are
declared in :file:`ext_tables.sql`; run the database analyser after the
update.

A site package overriding :file:`Templates/ContentElements/` finds the template
as :file:`ThemeTextIcon.html`. It renders on the new component
:html:`.theme-media-object`, one of four components the component library
gains - see :ref:`components`.
