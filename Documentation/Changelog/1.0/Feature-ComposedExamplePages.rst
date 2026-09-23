..  include:: /Includes.rst.txt

..  _feature-composed-example-pages:

===================================================
Feature: Composed example pages in the showcase
===================================================

Description
===========

The showcase set ``theme-demo`` gains a seventh section, :guilabel:`Examples`
(``/examples``), of **whole pages**. Every other section of the tree shows one
thing at a time — a page per content element, a page per backend layout, a page
per value of a field. That is what a showcase has to do first, and it is not
what an editor ever builds.

..  list-table::
    :header-rows: 1

    *   -   Page
        -   Backend layout
        -   Composed of
    *   -   :guilabel:`Album` (``/examples/album``)
        -   :guilabel:`Content page`
        -   A reduced hero in the stage, a text, the core :guilabel:`Images`
            element in three columns, a card group and a call to action as a
            band.
    *   -   :guilabel:`Pricing` (``/examples/pricing``)
        -   :guilabel:`Content page`
        -   A hero without media, the pricing element with three plans, a
            feature grid, an accordion and a call to action in a box.
    *   -   :guilabel:`Journal` (``/examples/journal``)
        -   :guilabel:`Content page with sidebar`
        -   A media teaser in the stage, a teaser list, a card wall, and a
            text, a link list and a row of social links in the sidebar.
    *   -   :guilabel:`Composing a page`
            (``/examples/journal/composing-a-page``)
        -   :guilabel:`Article`
        -   Texts, a :guilabel:`Text & Images` element with the image in the
            text, a pull quote and an author — with a notice and a link list
            in the aside.
    *   -   :guilabel:`Product` (``/examples/product``)
        -   :guilabel:`Full-width bands`
        -   A hero with a cropped image, split tiles, and the figures with a
            call to action, one band each.
    *   -   :guilabel:`Campaign` (``/examples/campaign``)
        -   :guilabel:`Cover page`
        -   A hero without media and one call to action, centred in the
            viewport.
    *   -   :guilabel:`Carousel landing`
            (``/examples/carousel-landing``)
        -   :guilabel:`Full-width bands`
        -   A carousel as the first band, a four column feature grid as the
            second, a call to action as the third.

**Nothing was added for them.** No content element, no component, no backend
layout and no template is new in this change: every page is built from what the
extension already ships, and the last element of each one is a notice naming
which elements that was. Two tests hold the section to it —
``ShowcaseTreeTest::theExamplesSectionIntroducesNoContentTypeOfItsOwn()`` fails
on an element type seeded only there, and
``anExamplePageIsMadeOfSeveralKindsOfElement()`` fails on a page that has
turned back into a specimen of one element repeated.

Impact
======

**A seventh top level entry.** :guilabel:`Examples` sits between
:guilabel:`Layouts` and :guilabel:`Styleguide` in the main navigation. The
header holds seven entries beside a one line site title at 1280 pixels, which
:file:`Tests/Acceptance/frontend.spec.ts` measures at three and at seven
entries — see :ref:`feature-header-variants`.

**The set declares more uids.** Pages 113 to 119 and 131, their content
elements at the page uid times 100 plus the position, and the inline list items
600 to 677. An installation importing ``theme-demo`` needs those free as well.
The pages were **appended** to the tree rather than inserted into an existing
one, so no uid of the existing showcase moved and every file reference of
:file:`config.yml` still hangs on the record it always did.

**Two galleries were deliberately not built.** The catalogue entry this change
answers also listed a gallery of header and footer variants and one of heroes
and features:

*   All six header and footer arrangements are already specimens of the
    ``chrome`` section of the styleguide, and ``ChromeVariantRenderingTest``
    drives each of them from its setting — a gallery of pages would restate
    what is shown and tested already. It would also cost more than it looks:
    ``theme.header.variant`` and ``theme.footer.variant`` are read once into a
    TypoScript variable in :file:`Page.typoscript`, so a page choosing its own
    needs a setup condition on the page uid — a per-page override of a
    site-wide setting, seeded into the showcase, that no site would write. See
    :ref:`feature-header-variants` and :ref:`feature-footer-variants`.
*   Every hero layout is on ``/elements/theme/hero`` and every feature layout
    on ``/elements/theme/features``, one element per value. A composed
    repetition of those would restate what those pages say without adding a
    page shape.
