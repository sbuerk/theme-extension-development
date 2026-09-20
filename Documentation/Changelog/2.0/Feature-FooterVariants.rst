..  include:: /Includes.rst.txt

..  _feature-footer-variants:

============================
Feature: Footer variants
============================

Description
===========

The site footer comes in two arrangements, selected by the site setting
:typoscript:`theme.footer.variant`:

..  list-table::
    :header-rows: 1

    *   -   Value
        -   The footer is

    *   -   ``columns``
        -   The four content columns above the meta row. **The default, and
            unchanged from before.**

    *   -   ``newsletter``
        -   A newsletter band above those.

Like the header variants, each is a Fluid partial of its own - below
:file:`Resources/Private/Partials/Page/Footer/` - and a modifier class, and
both share :file:`Body.html`, which is the columns and the meta row.

The newsletter band
-------------------

Three more settings, and the band needs all three:
:typoscript:`theme.footer.newsletterHeading`,
:typoscript:`theme.footer.newsletterPage` - the page carrying the
subscription form - and :typoscript:`theme.footer.newsletterLabel`, the text
of the button leading to it. A fourth, :typoscript:`theme.footer.newsletterText`,
is one optional line under the heading.

**The band is a link, not a form.** A subscription form needs somewhere to
post to, and this theme has nowhere: it ships no controller, no storage and no
double opt-in, and every one of those is a requirement rather than a detail
for an address somebody types in. A field that looks like a subscription and
drops what is typed into it is worse than no field, so the band leads to the
page where the real form lives - whichever extension renders it there.

Social icons in the footer
--------------------------

A row of platform logos is the content element :php:`theme_sociallinks`
placed in one of the four footer columns - see
:ref:`feature-social-link-logos`. The theme adds no footer slot of its own
for it, because a column already is one.

Impact
======

**A site that does not touch the setting is unaffected.** The default is
``columns``, its partial produces the footer that shipped before, and it
carries no modifier class.

A site package that overrode :file:`Partials/Page/Footer.html` keeps working -
the file still exists and is still what the layout renders - but it now
contains the switch. The columns and the meta row moved to
:file:`Partials/Page/Footer/Body.html`.

The styleguide section :guilabel:`Page chrome` shows both footers, the first
of them with a row of social links in its last column.
