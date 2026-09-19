..  include:: /Includes.rst.txt

..  _feature-text-columns:

========================
Feature: Text in columns
========================

Description
===========

The :guilabel:`Layout` field of the :guilabel:`Text` content element, in the
:guilabel:`Appearance` tab, is offered again and has two values:

..  list-table::
    :header-rows: 1

    *   -   Layout
        -   Renders
    *   -   :guilabel:`Running text`
        -   The text in one column, as before.
    *   -   :guilabel:`Columns`
        -   The text in two columns where each can be at least 30 characters
            wide, and in one column on a phone. A hairline separates the
            columns; a heading stays with the text it opens, and a quotation,
            a table, a code block or a list item moves to the next column whole.

The layouts :guilabel:`Layout 2` and :guilabel:`Layout 3` of the core are not
offered for this element. A text element that carries one of them renders as
running text.

Impact
======

A text element with :guilabel:`Columns` renders its rich text in
:html:`<div class="theme-content-element__body theme-text--columns">`. The
class can be used on any block of rich text in a template of a site package.

A site package can relabel or remove the layouts in page TSconfig, below
:typoscript:`TCEFORM.tt_content.layout.types.text`, and change the number and
the width of the columns through the custom properties
:css:`--theme-text-columns-count` and :css:`--theme-text-columns-min-width`.
