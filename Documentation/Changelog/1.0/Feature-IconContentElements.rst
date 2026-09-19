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
    *   -   :guilabel:`Features`
        -   A group of features, each with an icon, a title, a text and a
            link.
    *   -   :guilabel:`Figures`
        -   A row of figures, each with what it counts, an optional sentence
            and an optional icon.
    *   -   :guilabel:`Steps`
        -   The numbered steps of a process, each with a title, a text and an
            optional icon.

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

Features, figures and steps
---------------------------

The three take their items from the inline list the other list based elements
of the theme use, and each item has an :guilabel:`Icon` field there - offered
only in these three elements, which render it.

:guilabel:`Features` has a :guilabel:`Layout` palette with two fields:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Values
    *   -   :guilabel:`Layout`
        -   :guilabel:`Columns, the icon above` (the default),
            :guilabel:`Hanging icons`, :guilabel:`Tiles`,
            :guilabel:`With an introduction beside` - the heading and the text
            of the element beside the features instead of above them
    *   -   :guilabel:`Columns, at most`
        -   :guilabel:`Two`, :guilabel:`Three` (the default), :guilabel:`Four`
            - fewer where a column would be too narrow, one on a phone

Four columns need the full width of the content: on a page whose backend
layout sets a sub navigation beside the content, the features show three
columns at most.

A feature has a title, a text, an icon and a link with its label and its icon;
the title is required.

A figure of :guilabel:`Figures` has the :guilabel:`Figure` itself - a number or
a short value such as ``4.5:1`` - :guilabel:`What it counts`, both required, a
text and an icon. Screen readers read it as "what it counts, the figure"; the
figure is shown above, larger.

A step of :guilabel:`Steps` has a title, which is required, a text and an
icon. The steps are numbered in their order; a step with an icon shows the icon
instead of its number.

The seeded demo tree shows every layout and column count on
:file:`/elements/theme/features`, and the other two on
:file:`/elements/theme/stats` and :file:`/elements/theme/steps`.

Impact
======

Integrators get four new content types to grant to editor groups:
``theme_text_icon``, ``theme_features``, ``theme_stats`` and ``theme_steps``.
Four columns are added to ``tt_content`` - ``tx_theme_icon_position``,
``tx_theme_icon_shape``, ``tx_theme_icon_size`` and ``tx_theme_columns`` - and
two to ``tx_theme_list_item``: ``icon``, the icon of an item, and
``subheader``, a short second line, which the figures use for what they count.
All six are declared in :file:`ext_tables.sql`; run the database analyser after
the update. The core field :guilabel:`Layout` is offered on the features.

A site package overriding :file:`Templates/ContentElements/` finds the
templates as :file:`ThemeTextIcon.html`, :file:`ThemeFeatures.html`,
:file:`ThemeStats.html` and :file:`ThemeSteps.html`. They render on the four
components the component library gains - :html:`.theme-media-object`,
:html:`.theme-feature`, :html:`.theme-stat` and :html:`.theme-steps` - see
:ref:`components`.
