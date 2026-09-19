..  include:: /Includes.rst.txt

..  _feature-showcase-sections:

=====================================================
Feature: A showcase page per element and per variant
=====================================================

Description
===========

The showcase set ``theme-demo`` - see :ref:`feature-seeded-showcase-tree` -
grows from one page per family of content elements to one page per element:

..  list-table::
    :header-rows: 1

    *   -   Page
        -   What it shows
    *   -   :guilabel:`Core elements` (``/elements/core``) and one page
            below it per classic content element, at
            ``/elements/core/<CType>``
        -   :guilabel:`Header` in every level and alignment,
            :guilabel:`Text & Images` in each of its ten image positions,
            :guilabel:`Text & Media`, :guilabel:`Images` in one to four
            columns, :guilabel:`Bullet List` in every layout and list type,
            :guilabel:`Table` in every table class, :guilabel:`File Links` in
            every display type, :guilabel:`Divider`, :guilabel:`Plain HTML`
            and :guilabel:`Insert Records`.
    *   -   :guilabel:`Frames` (``/elements/frames``)
        -   Every frame, every space before and after, and every header
            alignment and look of the :guilabel:`Appearance` tab, one
            element each.
    *   -   :guilabel:`Typography` (``/typography``) and its pages
            :guilabel:`Text`, :guilabel:`Lists`, :guilabel:`Tables`,
            :guilabel:`Quotes and code` and :guilabel:`Article`
        -   The typography of the theme as an editor produces it, from rich
            text and content elements: headings inside a text, the inline
            semantics, the alignments and styles of the rich text preset,
            lists and tables of the rich text next to the elements, quotations,
            code blocks, and a long article that uses all of them.

The rich text of the typography pages is written into the database the way a
backend save writes it, so the processing of the installation's rich text
preset applies to it. With the preset of the theme everything on the pages
survives; it was not typed into the editor, and several parts need its source
view.

Impact
======

The set declares more uids: pages 30 to 39 and 50 to 56, and their content
elements at the page uid times 100 plus the position, for example ``3502``.
An installation importing ``theme-demo`` needs those uids free as well.

The new pages skip decades on purpose. The development instances mirror the
showcase with every uid moved by 1000, which puts the mirror of the content of
a page on the uids of the content of the page ten uids further; see the rule at
the top of the scenario file.
