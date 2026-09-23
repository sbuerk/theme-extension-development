..  include:: /Includes.rst.txt

..  _feature-reading-direction:

===============================================
Feature: A right-to-left page and its section
===============================================

Description
===========

Every box of the component library is spaced, bordered and placed with logical
properties, and every alignment is :css:`start` or :css:`end`. A right-to-left
page therefore needs no stylesheet of its own: :html:`dir="rtl"` on the
:html:`html` element, on a wrapper or on a single block is enough, and the
boxes turn around by themselves.

What does not turn around by itself is a glyph. An icon that points somewhere
means something by where it points, and in a right-to-left text that direction
is the other way round. Five of them are now mirrored with
:css:`scale: -1 1` under :css:`:dir(rtl)`:

..  list-table::
    :header-rows: 1

    *   -   Icon
        -   Where
    *   -   ``arrow-up-right-from-square``
        -   The marker of a link that leaves the site, and the link to the
            source of an embedded video
    *   -   ``arrow-turn-up``
        -   The link from a footnote back to its reference
    *   -   ``chevron-left`` / ``chevron-right``
        -   The previous and next buttons of the carousel and of the lightbox

The shape stays the vendored one, drawn the other way round, so no second icon
file is shipped. ``download``, ``envelope``, ``phone`` and ``chevron-down``
point nowhere horizontal and are left alone, and an arrow an editor puts into a
button label is content rather than furniture of the theme.

Two places show it. The styleguide has a :guilabel:`Reading direction` section,
and the showcase set ``theme-demo`` has a page,
:guilabel:`Typography` > :guilabel:`Right to left`
(``/typography/right-to-left``). The page takes its direction from a
:html:`dir="rtl"` wrapper per block rather than from a site language: a
language is a property of the installation - a site configuration and a
translation of every record - and neither travels with a seed set, while
:html:`dir` is an allowed attribute of the theme's rich text preset and an
editor has it as well. Both directions on one page is also what makes a
difference between them visible at all.

The page chrome is not in the wrapper and stays left to right. A site that is
right to left sets the direction through its site language, and the header, the
navigation, the breadcrumb and the footer follow it there.

Impact
======

The set ``theme-demo`` declares page ``152`` and its content elements
``15201`` to ``15207``. An installation importing it needs those uids free.

Two new assertions of :file:`Tests/Unit/ComponentLibraryTest.php` read the
compiled stylesheet: no box is placed by a physical edge, and every
direction-aware icon is mirrored. A site package that overrides a component
file and writes :css:`margin-left` or drops a mirroring rule fails them.
