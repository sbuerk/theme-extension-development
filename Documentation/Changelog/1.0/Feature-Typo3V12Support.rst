..  include:: /Includes.rst.txt

..  _feature-typo3-v12-support:

============================
Feature: TYPO3 v12.4 support
============================

Description
===========

The extension now supports **TYPO3 v12.4 alongside v13.4** from one code base,
on **PHP 8.1 up to 8.4**. Nothing about the v13 behaviour changes; what follows
describes what is new or different for an installation on TYPO3 v12.

..  list-table::
    :header-rows: 1

    *   -   TYPO3
        -   PHP
    *   -   v12.4.22 and newer
        -   8.1 - 8.4
    *   -   v13.4
        -   8.2 - 8.4

The PHP ranges differ because :composer:`typo3/cms-core` 13.4 requires PHP
``^8.2``. The v12 floor is 12.4.22 rather than 12.4.0, stated in the constraint
rather than hidden behind ``^12.4``.

Enabling the theme without site sets
====================================

Site sets are a TYPO3 v13.1 feature (:issue:`103437`). On TYPO3 v12 a
``dependencies`` key in a site configuration is read by nothing, so the classic
static include is not a fallback there - it is the only way to enable the
theme:

#.  Create a :guilabel:`sys_template` record on the site root page:
    :guilabel:`Web > List`, :guilabel:`Create new record`,
    :guilabel:`System records`, :guilabel:`TypoScript record`.
#.  Check :guilabel:`Rootlevel`, and check :guilabel:`Clear` for both
    :guilabel:`Constants` and :guilabel:`Setup`.
#.  Select :guilabel:`Theme Extension Development` in
    :guilabel:`Include static (from extensions)`.

Both delivery paths read the same TypoScript files, so what they deliver is
identical - see :ref:`configuration` and
:ref:`feature-site-set-page-rendering`.

..  important::

    The condition that suppresses the static include when the site set is
    active previously raised an uncaught :php:`TypeError` on TYPO3 v12, killing
    every frontend request that loaded the file: :typoscript:`site('sets')`
    resolves a method the v12 site entity does not have and returns
    :php:`NULL`, and the Symfony expression ``in`` compiles to
    :php:`in_array($left, $right, true)`. The condition now falls back to an
    empty array, which is the truthful answer on a version without sets.

A new database table
====================

TYPO3 v13.0 creates a database column for every TCA ``columns`` entry
(:issue:`101553`, extended by :issue:`104311` in 13.3). TYPO3 v12.4 does not:
its schema analyser derives the management columns, a handful of column types
and MM tables, and nothing else.

The extension therefore ships an :file:`ext_tables.sql` declaring what TYPO3
v13 would have generated:

*   the table :sql:`tx_theme_list_item`, holding the entries of the two link
    list elements,
*   the columns :sql:`tx_theme_link`, :sql:`tx_theme_link_label`,
    :sql:`tx_theme_link_variant` and :sql:`tx_theme_list_items` on
    :sql:`tt_content`.

The file is not version aware: :issue:`101553` states that an explicit
definition takes precedence over the derived one, so one file serves both
versions. On TYPO3 v13 it is redundant and changes nothing.

..  note::

    Run the database analyser (:guilabel:`Admin Tools > Maintenance >
    Analyze Database Structure`, or ``vendor/bin/typo3 extension:setup``) after
    updating on TYPO3 v12. Without the table and the four columns the theme's
    hero, teaser and link list elements cannot store their data.

Rich text and plugin rendering on TYPO3 v12
===========================================

Two TypoScript objects the theme relies on are provided by ``EXT:frontend``
from TYPO3 v13.2 only (:issue:`103485`); before that they came from
``fluid_styled_content``, which this theme deliberately does not require. On
TYPO3 v12 the extension therefore registers ``lib.parseFunc`` and
``lib.parseFunc_RTE`` itself, copied unchanged from what TYPO3 v13.4 registers,
so rich text is parsed identically on both versions.

On TYPO3 v12 the extension additionally declares itself as a **content
rendering definition** of the installation. Without that declaration the
TypoScript :php:`ExtensionUtility::configurePlugin()` generates is never
included, and no Extbase plugin renders at all.

..  note::

    An installation that also installs ``fluid_styled_content`` then has two
    content rendering definitions, and the static include selected later wins
    per object path. This is the same trade every site package makes; it is
    mentioned here because on TYPO3 v13 the theme does not make it.

Both are switched off entirely on TYPO3 v13.

The new content element wizard on TYPO3 v12
===========================================

TYPO3 v13.0 generates the :guilabel:`new content element` wizard from the TCA
(:issue:`102834`). TYPO3 v12 builds it from page TSconfig only, so the ten
content elements of the theme would be selectable in the :guilabel:`Type`
dropdown of an existing element and impossible to create.

The extension now ships the wizard entries as page TSconfig, behind a condition
that only TYPO3 v12 evaluates. On TYPO3 v13 nothing changes: the entries would
not duplicate anything there, but the page TSconfig label, description and icon
would shadow the ones the TCA carries - see
:ref:`feature-theme-content-elements`.

Impact
======

For an installation on **TYPO3 v13** nothing changes. The site set stays the
recommended way to enable the theme, the wizard is still generated from the
TCA, and the shipped :file:`ext_tables.sql` describes exactly what the schema
analyser derives anyway.

For an installation on **TYPO3 v12** the theme is available for the first time.
Two steps have no equivalent on v13 and are easy to miss:

*   the theme is enabled through a :guilabel:`sys_template` record rather than
    through the site configuration,
*   the database structure has to be updated so the new table and columns are
    created.

Installations that pin PHP 8.1 can now use the extension as well; that
combination is TYPO3 v12 only.
