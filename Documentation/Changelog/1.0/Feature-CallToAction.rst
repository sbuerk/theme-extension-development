..  include:: /Includes.rst.txt

..  _feature-call-to-action:

=======================================
Feature: Call to action content element
=======================================

Description
===========

A new content element in the :guilabel:`Theme` group, :guilabel:`Call to
action`, renders a heading, a short rich text, an optional large icon and up to
two links as a band or a box - the jumbotron of other themes.

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Values
    *   -   :guilabel:`Tone`
        -   :guilabel:`Surface`, the default; :guilabel:`Accent tint`, the tint
            of the accent band of the :guilabel:`Appearance` tab;
            :guilabel:`Inverse`, the other appearance - dark on a light page,
            light on a dark one; :guilabel:`Placeholder`, no fill and a dashed
            frame, for content that does not exist yet.
    *   -   :guilabel:`Width`
        -   :guilabel:`A box, centred in the column`, the default, or
            :guilabel:`A band across the whole column`.
    *   -   :guilabel:`Icon`
        -   An icon of the theme's icon picker above the heading.
    *   -   :guilabel:`Link` and :guilabel:`Second link`
        -   Two links, each with a label, a style and an icon, like the link of
            a hero. The second link defaults to the outlined style.

The element is available to editors once their backend group grants the
content type :guilabel:`Call to action`.

Impact
======

The element renders :html:`<section class="theme-cta">` with the modifiers
:css:`--boxed` or :css:`--band` and :css:`--accent`, :css:`--inverse` or
:css:`--placeholder`. It is rendered by :typoscript:`lib.themeContentElement`,
like every content element of the theme.
