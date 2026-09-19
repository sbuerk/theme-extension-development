..  include:: /Includes.rst.txt

..  _feature-icon-picker:

=====================================
Feature: Editors pick an icon by name
=====================================

Description
===========

A field that stores an icon offers the Font Awesome Free solid set the theme
ships as a select list grouped by the categories of Font Awesome, with a grid
of the icons under it to click on. The stored value is the name of the icon,
which a template renders with :html:`<theme:icon>`.

A column - of the theme or of another extension - declares it with one line:

..  code-block:: php

    'tx_myextension_icon' => [
        'label' => 'Icon',
        'config' => \SBUERK\ThemeExtensionDevelopment\Tca\IconItems::selectConfig(),
    ],

The configuration holds the :guilabel:`No icon` item, the groups and the icon
grid. The icons themselves are added when the form is built, by an
:php:`itemsProcFunc`, from a list the theme builds once and keeps in the
:php:`core` cache - so they are not part of the TCA every request loads.

Page TSconfig narrows a field to the icons it names, and removes the
narrowing again:

..  code-block:: typoscript

    # Three icons for one field. The leading comma keeps "No icon".
    TCEFORM.tx_myextension_domain_model_thing.tx_myextension_icon.keepItems = ,envelope,phone,globe

    # Every icon of the set.
    TCEFORM.tx_myextension_domain_model_thing.tx_myextension_icon.keepItems >

A list written without the empty entry drops the :guilabel:`No icon` item,
and the form then stores the first icon of the list with every record.

Impact
======

The categories come from :file:`metadata/categories.yml` of the pinned
Font Awesome package and ship as
:file:`Resources/Public/Icons/FontAwesome/categories.yml`, next to the icons.
An icon listed in several categories is placed in the first one. The names
Font Awesome keeps for renamed icons are not offered: each draws the same
glyph as an icon that is.

The grid shows each icon as an image of its file, which is drawn in black in
either backend colour scheme.

After the icon set is changed by hand, flush the caches: the list is kept
under a fingerprint of the set and its categories.

:php:`\SBUERK\ThemeExtensionDevelopment\Tca\IconItems::addItems()` is the
:php:`itemsProcFunc` of the field and is public in the service container.
