..  include:: /Includes.rst.txt

..  _important-header-navigation-never-wraps:

========================================================
Important: The main navigation in the header never wraps
========================================================

Description
===========

The main navigation in the site header keeps its top level entries in **one
row**, or it is behind its menu toggle. When the header row gets tight, the
**site title gives way**: it wraps onto as many lines as it needs, down to its
longest word. Neither the title nor the navigation is capped at a share of the
row any more.

Where one row no longer fits beside the title, the header collapses the menu
behind its toggle — at a width of its own **per header arrangement**, because
each arrangement puts something else beside the menu:

..  list-table::
    :header-rows: 1

    *   -   Arrangement
        -   The menu is a row from
        -   Sass variable

    *   -   ``simple``
        -   74rem (1184 pixels)
        -   ``bp.$header-simple``

    *   -   ``centred``
        -   55rem (880 pixels)
        -   ``bp.$header-centred``

    *   -   ``actions``
        -   55rem (880 pixels)
        -   ``bp.$header-actions``

    *   -   ``two-tier``
        -   65rem (1040 pixels)
        -   ``bp.$header-two-tier``

The widths are a budget of **seven top level entries** of the length of the
showcase's sections, beside its title and both header controls, with a margin
of 41 to 51 pixels for a wider system font. A main navigation outside the site
header still collapses at 48rem (768 pixels).

It used to be the other way round. From 1024 pixels up the title kept its
line, up to half the row, and the top level of the menu wrapped onto a second
row inside its frame; between 768 and 1024 pixels the menu was held to half
the row and wrapped inside that. The second level of the menu opens under its
own entry, so the second level of an entry in the first row opened over the
entries of the second. The half row cap also reached ``centred``, ``actions``
and ``two-tier``, whose menu has a row of its own, and made it wrap between
768 and 1024 pixels as well.

Impact
======

On a screen narrower than the breakpoint of its arrangement, a site's main
navigation is now behind its toggle where it used to be shown, wrapped: with
the default arrangement that is every width below 1184 pixels. On a wider
screen a long site title takes more lines than before, beside a menu of one
row. A single word longer than the row is not broken.

The collapse needs the theme's script, so two degraded cases now reach up to
the breakpoint of the arrangement instead of up to 768 pixels:

*   **Without JavaScript** nothing collapses: below the breakpoint the list is
    stacked in the flow with every second level shown, and the header grows to
    the height of the whole menu - about 1730 pixels for the showcase with the
    default arrangement, at any width below 1184 pixels. It still fits the
    screen.
*   **When the theme script fails to load** while the inline head script ran,
    the menu is hidden below the breakpoint and its toggle opens nothing.

A site whose menu is longer or shorter than the budget compiles the stylesheet
with breakpoints of its own. They are Sass variables, because a media query
cannot read a custom property, and they are ``!default``: a site package's own
entry point configures them before anything else loads the module, and is
compiled with :file:`Resources/Private/Scss/` on the load path:

..  code-block:: scss

    @use 'abstracts/breakpoints' with ($header-simple: 80rem);
    @use 'theme';

Editing :file:`Resources/Private/Scss/abstracts/_breakpoints.scss` in place is
lost with the next update of the extension.

A header carries **one** arrangement modifier. A site package that adds an
arrangement of its own with a modifier class of its own gets the breakpoint of
``simple``, the widest; a header with two of the shipped modifiers matches the
rules of both, and between their breakpoints the menu cannot be reached.

The Sass variable ``bp.$lg`` (64rem), which only the site header used, is
removed.
