..  include:: /Includes.rst.txt

..  _feature-brand-logos:

=========================
Feature: Brand logos
=========================

Description
===========

Fifteen platform logos of the **brands** style of Font Awesome Free ship next
to the solid set, below
:file:`Resources/Public/Icons/FontAwesome/Brands/`. They are rendered by the
same ViewHelper, with the new :html:`set` argument:

..  code-block:: html

    <theme:icon set="brands" name="mastodon" />
    <theme:icon set="brands" name="{item.brand_icon}" optional="1" />

:html:`set` takes ``solid`` - the default, and every icon the theme drew
before this - or ``brands``. Any other value raises
:php:`\InvalidArgumentException` code ``1789218004`` rather than falling back
to the default: :html:`set="brand"` would otherwise look for a platform logo
among the solid icons and report the *name* as the thing that is missing.

An allowlist, not a style
-------------------------

The brands style is 609 files. The fifteen that ship are named one by one in
the root :file:`package.json`:

..  code-block:: json

    "fontAwesomeBrands": [
        "bluesky", "discord", "facebook", "github", "gitlab", "instagram",
        "linkedin", "mastodon", "pinterest", "threads", "tiktok", "whatsapp",
        "x-twitter", "xing", "youtube"
    ]

The same :bash:`runTests.sh -s buildIcons` that copies the solid set copies
these, byte for byte, and the same :bash:`-s checkIconsBuild` compares them -
against the allowlist applied to the pinned package, so it fails on an edited
file, a missing file, an extra file **and** on a name added to or dropped from
the list without a rebuild.

Trademarks
----------

A brand logo is a trademark of its owner. Font Awesome's licence asks that it
is used *only to represent the company, product or service to which it
refers*, and that is the reason the set is an allowlist rather than a copy of
the style: a logo that is reachable from one field on one content element can
hardly be used as decoration. :file:`ATTRIBUTION.txt` beside the files repeats
the restriction, because that is the copy which travels into the composer dist
archive and the TER artifact.

Impact
======

Integrators get a second, deliberately small icon set. A site package that
needs another platform adds its name to :typoscript:`fontAwesomeBrands`, runs
:bash:`-s buildIcons` and commits the file - the same path the version pin
takes.

Nothing about the solid set changes: the default of :html:`set` is ``solid``,
every existing template keeps rendering exactly what it rendered, and the icon
picker in the backend is still the solid catalogue.

The styleguide section :guilabel:`Icons` gained a table of the brand logos
under the table of the icons the theme uses.
