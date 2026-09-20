..  include:: /Includes.rst.txt

..  _feature-social-link-logos:

===============================
Feature: Social link logos
===============================

Description
===========

:php:`theme_sociallinks` renders a row of platform logos. Each entry of the
element gained a field :guilabel:`Platform logo`, which offers the fifteen
brand logos the extension vendors - see :ref:`feature-brand-logos` - with the
logo next to every option and a grid of them under the list.

The element used to render the same vertical list of text links as
:php:`theme_linklist`, because no platform logo shipped. It now has a
component of its own, :css:`.theme-social-links`: the row is horizontal, and
an entry that has a logo collapses to a square of the minimum target size.

The name stays in the markup
----------------------------

An entry always carries the platform's name as text, whether a logo was
picked or not. Where one was, the stylesheet hides that text **visually** and
it goes on naming the link for assistive technology; the logo itself is
:html:`aria-hidden`, like every other icon of the theme. A link whose only
content is a logo would otherwise be announced by its URL.

The rule that hides the name is :css:`:has(.theme-icon)` on the link, not a
class the template writes. The logo comes out of a record and is rendered
:html:`optional`, so a platform name a later Font Awesome version dropped
renders nothing - and a class decided earlier would then hide the name of an
entry with nothing left in it.

Impact
======

A new column :sql:`tx_theme_list_item.brand_icon`, derived from the TCA by
:php:`DefaultTcaSchema` like every other column of that table, so the database
analyser adds it. Existing records are unaffected: the column is empty, and an
entry without a logo renders as the text link it rendered before.

The element's child form changed: the entries of :php:`theme_sociallinks` now
show :guilabel:`Platform logo`, :guilabel:`Link` and :guilabel:`Link label`
instead of the shared :guilabel:`Link` palette. The palette's own icon field
offers the solid set, which holds no platform logo, so it is no longer shown
on this relation.

A site package that overrode
:file:`Templates/ContentElements/ThemeSociallinks.html` or styled
:css:`.theme-content-menu` inside :php:`theme_sociallinks` has to follow the
new markup - see :ref:`components`.

The seeded showcase shows both states: two entries with a logo, two without.
