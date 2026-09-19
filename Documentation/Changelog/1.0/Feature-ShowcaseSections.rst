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

Impact
======

The set declares more uids: pages 30 to 39, 50 and 51, and their content
elements at the page uid times 100 plus the position, for example ``3502``.
An installation importing ``theme-demo`` needs those uids free as well.

The new pages skip decades on purpose. The development instances mirror the
showcase with every uid moved by 1000, which puts the mirror of the content of
a page on the uids of the content of the page ten uids further; see the rule at
the top of the scenario file.
