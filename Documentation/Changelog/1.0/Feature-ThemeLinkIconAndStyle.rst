..  include:: /Includes.rst.txt

..  _feature-theme-link-icon-and-style:

===============================================
Feature: An icon and a style for a theme link
===============================================

Description
===========

The link of the hero and teaser content elements - the :guilabel:`Link`
palette - offers two choices besides the link and its label:

:guilabel:`Link style`
    :guilabel:`Primary`, :guilabel:`Secondary`, :guilabel:`Ghost` and, new,
    :guilabel:`Link`: the plain button or one of its modifiers
    :css:`theme-button--secondary`, :css:`theme-button--ghost` and
    :css:`theme-button--link`.

:guilabel:`Link icon`
    An icon of the Font Awesome Free solid set, picked by name - see
    :ref:`feature-icon-picker`. It is rendered before the label.

The link of an inline list item - in the link list, the social links, the
author's links and the cards of the teaser grid - has the :guilabel:`Link icon`
as well. It has no style: in a list of links a button would break the list.

The icon is decoration; the label still names the link for a screen reader.
It is spaced by the gap of the link, so it comes first in a right-to-left page
too.

Impact
======

Two columns are added, :sql:`tt_content.tx_theme_link_icon` and
:sql:`tx_theme_list_item.link_icon`, declared in :file:`ext_tables.sql`; run
the database analyser after the update.

The existing column :sql:`tt_content.tx_theme_link_variant` keeps its name and
its values, and gains the value :php:`link`.

:html:`<theme:icon>` has a new argument, :html:`optional`. With it, an empty
name or a name the icon set does not have renders nothing instead of throwing.
It is meant for a name read from a record: a later Font Awesome version may
no longer have the icon an editor picked, and that costs the icon, not the
page.

A site package that overrides :file:`Partials/ContentElement/LinkButton.html`,
:file:`Partials/ContentElement/LinkList.html` or
:file:`Templates/ContentElements/ThemeMediaTeaserGrid.html` renders neither the
new style nor the icon until it adds them.
