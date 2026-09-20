..  include:: /Includes.rst.txt

..  _feature-cover-article-and-band-layouts:

=====================================================
Feature: Cover, article and full-width band layouts
=====================================================

Description
===========

Three further backend layouts, for pages that are not a stack of content in a
column:

..  list-table::
    :header-rows: 1

    *   -   Layout
        -   Template
        -   Content areas
    *   -   :guilabel:`Article`
        -   :file:`Page/Article.html`
        -   stage (2), main (0) at the reading measure, sidebar (1) beside it
    *   -   :guilabel:`Cover page`
        -   :file:`Page/Cover.html`
        -   main (0), centred in the viewport
    *   -   :guilabel:`Full-width bands`
        -   :file:`Page/Bands.html`
        -   main (0), second column (3), third column (4), stacked and full
            width

No new content area is introduced. :guilabel:`Article` reads the same
:sql:`colPos` 1 as :guilabel:`Content page with sidebar`, and
:guilabel:`Full-width bands` reads the three content columns of
:guilabel:`Three columns` — main (0), second (3) and third (4) — so content
moved between two layouts stays in the column it was in. It is not a swap
without consequence: :guilabel:`Three columns` also declares :guilabel:`Stage`
(:sql:`colPos` 2) and :guilabel:`Full-width bands` does not, so a page moved
onto it stops rendering its stage content. The page module lists that content
as unused, which is how an editor gets it back.

Impact
======

**The article aside is not the page shell's aside.** Both layouts read
:sql:`colPos` 1 and they render it as two different things. The shell's
:html:`.theme-page__aside` is the *navigation* column: it precedes the content
in the source order, it is a fixed 15 rem, and it carries the sub navigation
and the table of contents above whatever an editor placed in the slot. An
article's aside is editorial — it follows the article in reading order, carries
no navigation and is sized against the article. It is therefore a column of the
article grid, an :html:`<aside class="theme-page__column">`, and
:css:`:has(.theme-page__aside)` on the page body does not apply to it.

The article column is capped at the reading measure rather than its grid track
being sized to it. Both look the same for prose, but the measure is a property
of the text: a column holding a table or a gallery still gets the whole track.

**A band page leaves the content container.**
:css:`.theme-page__body:has(.theme-page__bands)` drops the maximum width and
the inline padding, so every band spans the viewport; the header and the footer
keep their own containers. A band breaking out with :css:`width: 100vw` and a
negative margin was rejected because :css:`100vw` includes the scrollbar and
overflows by its width wherever a platform reserves one.

What holds the text off the edge is the padding a content element already has
inside its own box, so how wide the reading line is inside a band stays the
business of what an editor puts there. Bands are flush against each other,
which is what makes them read as bands; the space between two of them is the
:guilabel:`Space after` of the last element of the one above.

**A cover page fills the viewport.** No height is configured for it: the page
shell is already at least as tall as the viewport, and the cover layout only
says where its content sits inside the space that is left between the header
and the footer. It renders neither a breadcrumb nor a stage — a cover page is
the top of whatever it announces, and the banner is the page.

Demo pages
==========

The :guilabel:`Layouts` section of the seed set ``theme-demo`` gains a page for
each of the three, labelled column by column like the others — see
:ref:`feature-column-page-layouts`.
