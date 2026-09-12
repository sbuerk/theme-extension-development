..  include:: /Includes.rst.txt

..  _important-tables-no-longer-striped-by-default:

==================================================
Important: Tables are no longer striped by default
==================================================

Description
===========

A table content element used to stripe every other row, whatever its
:guilabel:`Table class` said. The core's :guilabel:`Striped` therefore changed
nothing, and there was no way to get a table without stripes.

The table component now draws its default rows separated by a hairline only.
Stripes are the modifier :css:`.theme-table--striped`, which the
:guilabel:`Striped` table class selects. The table class offers more looks
beside it - see :ref:`feature-typography-components`.

Impact
======

Every table content element with the default table class loses its stripes,
and so does any template of a site package that renders
:html:`<table class="theme-table">` without a modifier.

A table that should stay striped selects :guilabel:`Striped` as its table
class, or carries :html:`theme-table--striped` in a template of its own. A
site package that wants every table striped again, without touching the
records, restores the previous default in its own CSS:

..  code-block:: css

    .theme-table > tbody > tr:nth-child(even) > * {
        background-color: var(--theme-table-stripe-background);
    }

The theme uses no cascade layers, so the site package's stylesheet has to be
included after :file:`theme.css`.
