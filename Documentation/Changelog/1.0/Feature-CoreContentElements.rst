..  include:: /Includes.rst.txt

..  _feature-core-content-elements:

=================================
Feature: Core content elements
=================================

Description
===========

The theme now renders the rest of the classic content element set:

..  list-table::
    :header-rows: 1

    *   -   Content element
        -   Rendered
    *   -   :guilabel:`Text & Images`
        -   :guilabel:`Images`, positioned beside :guilabel:`Text`.
    *   -   :guilabel:`Text & Media`
        -   The same, from the :guilabel:`Media` field, which is not
            restricted to images.
    *   -   :guilabel:`Bullet List`
        -   :guilabel:`Text` as a list, unordered, ordered or a definition
            list, per :guilabel:`Type of list`.
    *   -   :guilabel:`Table`
        -   :guilabel:`Text` as a table, honouring caption, delimiter,
            enclosure, header position and footer.
    *   -   :guilabel:`File Links`
        -   The :guilabel:`Files` and :guilabel:`Collections` fields as a
            file list - name, optional size, optional thumbnail.
    *   -   :guilabel:`Insert Records`
        -   The referenced records, rendered exactly as they render on their
            own.
    *   -   :guilabel:`Divider`
        -   A horizontal rule.
    *   -   :guilabel:`HTML`
        -   :guilabel:`Text`, unescaped.

Together with :guilabel:`Header`, :guilabel:`Text` and :guilabel:`Images`
(:ref:`feature-content-element-rendering`,
:ref:`feature-image-content-element-rendering`), every classic content
element ``EXT:frontend`` registers now renders, except the eleven menu
elements - tracked separately, they need a :php:`MenuProcessor` configured
per menu type rather than only a template.

No TCA of this extension's own is added for any of them. Every one of these
elements was already creatable in the backend before this change - their TCA
comes from ``EXT:frontend`` on TYPO3 v13.4, and ``fluid_styled_content`` is
not a dependency of this theme - so what changes is only that they now render
instead of TYPO3's own "no rendering definition" notice.

Two elements needed a decision
===============================

:guilabel:`Table`
------------------

:guilabel:`Text` for :guilabel:`Table` is delimited text, and the delimiter
and enclosure fields are stored as TCA character **codes**, not characters.
Neither core data processor is enough on its own: :php:`SplitProcessor`
splits on one delimiter into a flat list, with no nesting and no quoting;
:php:`CommaSeparatedValueProcessor` is built for exactly this field but has
no stdWrap property that turns a numeric code into a character, and does not
shape a header row, a header column or a footer row.

:php:`SBUERK\ThemeExtensionDevelopment\DataProcessing\TableProcessor` does
the decode and the shaping. One PHP detail is worth knowing if this class is
ever touched: the enclosure field's own **default** is "None" (code ``0``),
and PHP's :php:`fgetcsv()` throws a :php:`ValueError` when handed an empty
enclosure string - which is what the TYPO3 backend's own table wizard falls
back to for that same "None" option. :php:`chr(0)` is used instead, so the
field's default configuration does not throw.

:guilabel:`Insert Records`
---------------------------

:guilabel:`Insert Records` renders other content elements, through the
core's :typoscript:`RECORDS` cObject configured with
:typoscript:`conf.tt_content =< tt_content` - a referenced record renders
through the very same object this theme builds for every content element,
including, if it is itself an :guilabel:`Insert Records` element, going
through this same branch again.

..  note::

    That recursion is broken by this theme rather than left to the core.
    TYPO3 v13.4 does bring a guard of its own -
    :php:`TypoScriptFrontendController->recordRegister` skips a record that
    is already being rendered, so a self-reference or an indirect cycle is
    caught and the offending reference alone is silently dropped, not the
    rest of the element - but the theme does not depend on it. Inside an
    :guilabel:`Insert Records` element, the ``shortcut`` branch of the
    content element :typoscript:`CASE` is overridden to render nothing at
    all, so no chain of references can return to where it started.

    That break is structural: it keeps no per-request state, it is a
    property of this theme rather than of the core version it runs on, and
    :file:`Tests/Functional/CoreContentElementRenderingTest.php` renders a
    self-referencing element and a mutually referencing pair to prove it.
    See :file:`docs/architecture/content-elements.md` for the verification
    against the installed core.

Escaping
========

Table cells, bullet items, the table caption and file names/descriptions are
plain Fluid interpolation, HTML-escaped. :guilabel:`Text & Images` and
:guilabel:`Text & Media` run their :guilabel:`Text` field through
``f:format.html`` (the RTE parse), the same as the existing :guilabel:`Text`
element. :guilabel:`HTML` is the one deliberate exception: ``f:format.raw``,
completely unescaped - not ``f:format.html``, which would parse it and
change it. Access to :guilabel:`HTML` is restricted the same way as every
``CType``: the field carries ``authMode = explicitAllow``, and it is a site
administration decision, not something this theme enforces, which backend
groups are actually granted it.

Impact
======

An installation using the theme no longer renders the TYPO3 "no rendering
definition" notice for any of the elements listed above.
:file:`Tests/Functional/CoreContentElementRenderingTest.php` sweeps every
covered ``CType`` and fails if any of them regresses to the notice.

The markup is generated from the templates below
:file:`Resources/Private/Templates/ContentElements/`, redirected together
with every other template through the Fluid path constants under
``theme.`` - see :ref:`configuration`. See
:file:`docs/architecture/content-elements.md` in the developer documentation
for the full coverage table, the
``bullets_type``/``table_*``/``uploads_type`` field-by-field reasoning, and
the cycle break described above.
