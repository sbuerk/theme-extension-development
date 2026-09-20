..  include:: /Includes.rst.txt

..  _feature-article-byline-and-footnotes:

==========================================================
Feature: A byline, footnotes and a table of contents
==========================================================

Description
===========

Two components of long form text join the library, and the article of the
showcase - :guilabel:`Typography` > :guilabel:`Article`, ``/typography/article``
- uses both:

..  list-table::
    :header-rows: 1

    *   -   Component
        -   Is
    *   -   :css:`.theme-byline`
        -   The credit line between the title of a text and its first
            paragraph: who wrote it, when, and whatever else fits in one short
            phrase. One line rather than a list; the date is a :html:`<time>`
            with a machine readable :html:`datetime`. The items are separated
            by the border of the item that follows, so the separator follows
            the reading direction and is not a character the stylesheet
            invented.
    *   -   :css:`.theme-footnotes` and :css:`.theme-footnote-ref`
        -   The notes at the end of a text, and the superscript reference in
            the running text that points at one. Both carry the number
            literally, so a reader copying a sentence out of the page copies
            the reference with it. Each note ends on a link back to its
            reference, drawn from the vendored ``arrow-turn-up``.

The article also demonstrates two core fields of the :guilabel:`Appearance`
tab the theme renders but the showcase did not seed:
:guilabel:`Show in Section Menus` (``sectionIndex``), which is switched off on
the title, the byline and the notes so the table of contents of the page lists
the sections of the article and nothing else, and
:guilabel:`Append with Link to Top of Page` (``linkToTop``), at the end of the
notes.

The page therefore renders through the :guilabel:`Content with sidebar`
backend layout, the only layout with a column for the table of contents. Its
sibling pages are short enough to read straight through and keep
:guilabel:`Content`.

Neither component has a content element of its own. A site that publishes
articles would add one; the showcase writes the markup in a
:guilabel:`Plain HTML` element, and the references are part of the rich text of
the paragraphs they sit in.

Impact
======

The set ``theme-demo`` declares one more content element on page 56 and
renumbers the elements of that page: the byline is the second element, so what
was ``5602`` to ``5609`` is now ``5603`` to ``5610``, and the notes are
``5611``. An installation that imported an earlier version of the set and
points at one of those uids has to be reseeded.
