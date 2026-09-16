..  include:: /Includes.rst.txt

..  _feature-table-of-contents-and-link-to-top:

=================================================
Feature: Table of contents, and a link to the top
=================================================

Description
===========

The two fields of the :guilabel:`Appearance` tab that the theme left switched
off are rendered now, and both are offered to editors again:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Renders
    *   -   :guilabel:`Show in Section Menus` (``sectionIndex``)
        -   The element becomes an entry of the table of contents of its page.
    *   -   :guilabel:`Append with Link to Top of Page` (``linkToTop``)
        -   A :guilabel:`Back to top` link is appended to the element.

Table of contents
-----------------

Pages using the :guilabel:`Content page with sidebar` backend layout show a
table of contents in the sidebar: one entry per content element of the page,
linking to that element.

An element is an entry when :guilabel:`Show in Section Menus` is set **and** it
has a header that is actually shown. An element whose header is empty, or whose
header level is :guilabel:`Hidden`, is left out - an entry with no text is a
link a reader cannot read. Elements of every column are listed, not only the
main one, so the contents of the page are the whole page.

The order is the order of the elements in the backend. The field is on by
default, which it always was, so every element written before this existed is
already in the index; an editor switches one *out* rather than having to switch
the rest in.

Back to top
-----------

:guilabel:`Append with Link to Top of Page` appends a link to the end of the
element, with an upwards arrow. It points at the main content landmark of the
page rather than at the top of the document, so following it moves the keyboard
focus there as well and the next :kbd:`Tab` continues from the top of the
content.

Impact
======

Both fields appear in the :guilabel:`Appearance` tab of every content element
that has one. Neither needs a database change: both columns are the core's own
and have always been there.

Integrators who want them out of the form again disable them in page TSconfig,
the way the theme did until now:

..  code-block:: typoscript

    TCEFORM.tt_content {
        sectionIndex.disabled = 1
        linkToTop.disabled = 1
    }

The table of contents is an :typoscript:`HMENU` at
:typoscript:`page.10.variables.tableOfContents`, not a data processor -
:php:`MenuProcessor` rejects :typoscript:`sectionIndex` outright. A site package
replaces it there, and overriding :file:`Partials/Navigation/TableOfContents.html`
changes its markup. It renders on the sidebar layout only, from
:file:`Partials/Page/Sidebar.html`.

The list is the existing :css:`.theme-content-menu` component rather than one of
its own; the link back to the top is :css:`.theme-content-element__to-top` - see
:ref:`components`.

The anchor an entry points at is the :html:`id="c<uid>"` the content element
wrapper has always written. A site package overriding
:file:`Layouts/ContentElement.html` must keep that id, or every entry of the
table of contents stops working.
