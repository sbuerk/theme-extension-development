..  include:: /Includes.rst.txt

..  _feature-pricing-element:

=========================
Feature: Pricing plans
=========================

Description
===========

A content element of the theme's own, :guilabel:`Pricing`, in the
:guilabel:`Theme` group of the :guilabel:`Create new content element` wizard.
It renders pricing plans side by side, and takes its plans from the inline
list the other list based elements of the theme use.

A plan has a name, a price, what the price is per, its features and a link:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Is
    *   -   :guilabel:`Title`
        -   The name of the plan. Required - it is what a reader refers to.
    *   -   :guilabel:`Price`
        -   Required, and written out as the site writes prices: the element
            formats nothing and adds no currency of its own.
    *   -   :guilabel:`Per`
        -   What the price is for - "per month", "per seat and month".
    *   -   :guilabel:`Features`
        -   One feature per line.
    *   -   :guilabel:`Highlighted`
        -   Singles this plan out from the others. Meant for one plan of a
            group.
    *   -   :guilabel:`Link`
        -   With a label, an icon and a :guilabel:`Link style` - the same four
            styles the link of a hero or a card offers - rendered as a button.

The plans share one row from 48rem up, in columns of equal width, and stack
below it. There is no column count to pick: a pricing table is read across, and
a fourth plan wrapping onto a row of its own is the one arrangement that stops
a reader comparing them. Every button sits on the same line whatever the
feature lists above them did.

The prices are set in tabular figures, so the digits line up between the
columns - "19 €" and "99 €" in two plans start and end at the same place.

A feature a plan does not include is left out
---------------------------------------------

The feature list says what a plan **includes**. A feature it does not include
is left out of its list rather than shown crossed out, and the plan beside it
is the comparison.

Showing exclusions would need a syntax inside the field saying which line is
which - a leading :code:`-`, say. That is a convention no editor is told about,
and a plan whose author did not know it would silently render its features as
exclusions. The theme made the same choice for the definition list of the
bullet element, and for the same reason.

Impact
======

Integrators get one new content type to grant to editor groups:
``theme_pricing``. The database analyzer adds one column to
``tx_theme_list_item``, ``highlighted``; the price is the existing
``subheader`` and the period the existing ``meta``, relabelled for this
relation rather than given columns only this element could use.

A site package overriding :file:`Templates/ContentElements/` finds the template
there as :file:`ThemePricing.html`. The stylesheet gains the
:css:`.theme-pricing` component - see :ref:`components` - whose feature list is
the existing :css:`.theme-list--check`, so the check mark is the one icon of
the shipped set that component already draws.

The showcase seeds the element on :file:`/elements/theme/pricing`, and the
:file:`/styleguide` page shows the component in its own :guilabel:`Pricing`
section.
