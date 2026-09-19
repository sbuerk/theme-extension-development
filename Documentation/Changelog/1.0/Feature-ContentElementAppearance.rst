..  include:: /Includes.rst.txt

..  _feature-content-element-appearance:

==============================================
Feature: Appearance fields of content elements
==============================================

Description
===========

The fields of the :guilabel:`Appearance` tab and of the header palette now
change how a content element looks. Before, an editor could set them and
nothing happened.

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Values
        -   Effect
    *   -   :guilabel:`Frame`
        -   Default, No frame, Surface band, Raised band, Accent band,
            Inverse band
        -   A band fills the element and draws a hairline around it. "No
            frame" removes the inner padding, for an element that brings its
            own box.
    *   -   :guilabel:`Space before`, :guilabel:`Space after`
        -   Extra small to Extra large
        -   The space above and below the element, on the spacing scale of the
            theme.
    *   -   :guilabel:`Alignment` of the header
        -   Default, Center, Right, Left
        -   The header at the centre, the end or the start of the line. Right
            and left follow the direction of the text.
    *   -   :guilabel:`Header style`
        -   As its level, Display, Like heading 1 to 5
        -   How large the header looks. Its level in the outline of the page
            stays the one :guilabel:`Type` sets.

:guilabel:`Header style` is a new field, next to the header level.

The four bands
--------------

*   :guilabel:`Surface band` and :guilabel:`Raised band` fill the element with
    the two surface colours of the theme.
*   :guilabel:`Accent band` is a light tint of the primary colour of the active
    palette, so it changes with the palette.
*   :guilabel:`Inverse band` shows the element in the other appearance: dark on
    a light page and light on a dark one. Everything inside it changes with it,
    links and buttons included, so it stays as readable as the rest of the
    page.

The labelled outline of the development mode is drawn on top of a band and is
switched off as before. The band stays.

Impact
======

The theme sets page TSconfig for the whole installation. The frames the theme
does not draw — the rulers and indents of the core list — are removed from
:guilabel:`Frame`, and three fields are taken out of the form until the theme
renders them:

*   :guilabel:`Layout`
*   :guilabel:`Include in section index`
*   :guilabel:`Append with link to top of page`

:guilabel:`Alignment` and :guilabel:`Header style` are hidden on the hero
elements, the media teaser and the testimonial, which show their title in a
component of their own.

An element that still holds one of the removed frames is rendered without a
frame.

This page TSconfig comes from :file:`Configuration/page.tsconfig` of the
extension, which TYPO3 loads for **every page tree of the installation**, the
same way the theme's backend layouts are registered. So it also reaches a site
that does not use the theme. That site gets its own fields back with its own
page TSconfig, which the core loads after that of the extensions. The TSconfig
can come from the site's set, from :file:`config/sites/<site>/page.tsconfig`, or
from the :guilabel:`Page TSconfig` field of its root page:

..  code-block:: typoscript

    TCEFORM.tt_content {
        frame_class.removeItems >
        frame_class.addItems >
        layout.disabled = 0
        sectionIndex.disabled = 0
        linkToTop.disabled = 0
    }

A site that does use the theme can offer a removed frame again the same way.
It then has to style that class itself.

..  index:: Backend, Frontend, TCA, TSConfig
