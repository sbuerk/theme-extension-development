..  include:: /Includes.rst.txt

..  _feature-notice-tabs-accordion-elements:

==============================================
Feature: Notice, tabs and accordion elements
==============================================

Description
===========

Three more content elements of the theme's own, in the :guilabel:`Theme` group
of the :guilabel:`Create new content element` wizard. Each puts a component of
the library into an editor's hands that had no content element so far:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Renders through
    *   -   :guilabel:`Notice`
        -   The alert component, in one of six kinds.
    *   -   :guilabel:`Tabs`
        -   The tabs component: one tab per item, one panel at a time.
    *   -   :guilabel:`Accordion`
        -   The accordion component: collapsible items, one open at a time.

Notice
------

The :guilabel:`Kind` field offers the six kinds of the alert component:
:guilabel:`Note`, :guilabel:`Information`, :guilabel:`Tip`,
:guilabel:`Success`, :guilabel:`Warning` and :guilabel:`Danger`. The kind also
decides how a screen reader treats the notice, and an editor does not choose
that separately:

..  list-table::
    :header-rows: 1

    *   -   Kind
        -   :html:`role`
    *   -   Information, Success
        -   :html:`status` - read when the reader pauses
    *   -   Warning, Danger
        -   :html:`alert` - read at once
    *   -   Note, Tip
        -   :html:`note` - an aside, never announced

A new notice is a :guilabel:`Note`: of the six, it is the kind that never
interrupts anybody. The title is shown inside the box, and the text is rich
text.

Tabs and accordion
------------------

Both take their items from the same inline list the other list based
elements of the theme use - a title and a text per item - and the text is
rich text for these two. :guilabel:`Tabs` shows every panel under its heading
until the theme's script has run, so a page without JavaScript loses nothing.
:guilabel:`Accordion` needs no script at all: its items share a name, and the
browser keeps one of them open.

Impact
======

Integrators get three new content types to grant to editor groups:
``theme_notice``, ``theme_tabs`` and ``theme_accordion``. One new column,
``tt_content.tx_theme_notice_kind``, is added by the database analyzer.

A site package overriding :file:`Templates/ContentElements/` finds the three
templates there as :file:`ThemeNotice.html`, :file:`ThemeTabs.html` and
:file:`ThemeAccordion.html`.
