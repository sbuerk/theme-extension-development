..  include:: /Includes.rst.txt

..  _feature-element-cheatsheet:

=================================================
Feature: A cheatsheet of every content element
=================================================

Description
===========

The showcase set ``theme-demo`` gains :guilabel:`Cheatsheet`
(``/elements/cheatsheet``): every content element the theme renders, and every
variant of one, on a single page. The pages below :guilabel:`Elements` show the
same elements one family at a time, with the prose that explains them; the
cheatsheet is the one to scroll through, or to search in the browser.

It holds no element of its own. Each family on it is an :guilabel:`Insert
Records` element whose reference list names the elements of one page of the
showcase, under a :guilabel:`Header` element that says which family it is.
Copying the elements onto the page would be a second definition of every one of
them, and the two would drift the first time one was changed - silently, since
a cheatsheet that is one variant short still looks like a cheatsheet.

Three things follow from that, and all three are asserted:

..  list-table::
    :header-rows: 1

    *   -   Property
        -   Why
    *   -   :guilabel:`Insert Records` itself is not on the page
        -   A shortcut reached through a shortcut renders nothing, which is how
            the theme makes a reference cycle impossible on both core versions.
            Its own page, :guilabel:`Insert records`, shows what it does.
    *   -   Completeness is read from the TypoScript
        -   A content type the theme starts to render and nobody adds to the
            cheatsheet fails a test rather than quietly not being on the page.
            A reference that resolves to nothing fails it as well: an empty
            shortcut looks exactly like a family that is simply short.
    *   -   No element is gathered twice
        -   Two families naming one element would put the same ``id`` on the
            page twice, and the second anchor of that id is unreachable.

The page declares no sidebar. Beside one the main column is narrower, and a
features element of four columns, a pricing element of three plans and a hero
all render differently in it.

The **backend layouts are not on the page**, and cannot be: a layout is a
property of a page, so one page can only ever show one of them. The pages of
the showcase carry them between them instead.

Impact
======

The set ``theme-demo`` declares page ``153`` and its content elements ``15301``
to ``15365``. An installation importing it needs those uids free.
