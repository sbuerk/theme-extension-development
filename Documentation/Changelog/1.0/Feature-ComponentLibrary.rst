..  include:: /Includes.rst.txt

..  _feature-component-library:

===========================
Feature: Component library
===========================

Description
===========

The theme now ships a **component library** built entirely from the design
tokens: an element baseline covering everything a rich text editor can emit,
native form controls with their validation states, and the components a page is
composed from - navigation, breadcrumb, hero, teaser, card, alert, badge,
accordion, quote, table, pagination, gallery, and the page chrome.

There is **no CSS framework**. No Bootstrap, no Tailwind, no reset library, and
no external request of any kind.

Class names carry a :html:`theme-` prefix. That is not decoration: this theme
exists to host *other* extensions while they are being developed, those
extensions bring their own stylesheets, and an unprefixed :css:`.button` or
:css:`.card` would collide with them.

Component tokens
================

Each component declares its own token layer, every entry falling back to a
global token and then to a literal:

..  code-block:: scss

    .theme-card {
        --theme-card-background: var(--theme-color-surface, #f4f6fa);

        background-color: var(--theme-card-background);
    }

A site package can therefore re-theme **globally** by moving
:css:`--theme-color-surface`, or **surgically** by moving
:css:`--theme-card-background`, without touching the other. A modifier
re-points a token rather than restating a property.

The fallback literal appears once per component rather than once per
declaration, which keeps :file:`abstracts/_tokens.scss` the single source of
truth. It also makes a component portable into a shadow root: custom properties
inherit across a shadow boundary, so only the token file has to live in the
outer document.

The CType outline
=================

Every content element is wrapped in :css:`.theme-content-element`, which draws
a dashed outline and a small chip naming its :html:`CType`. It exists so that
the column and element boundaries a backend layout produces are visible without
opening the page module.

It is a development aid, and it is switched off wholesale with one attribute:

..  code-block:: html

    <html data-theme-content-outline="off">

A single element opts out with :css:`.theme-content-element--plain`.

..  note::

    The switch is an attribute rather than a custom property on purpose. Hiding
    the label means reacting to a property *value*, which :css:`:has()` cannot
    do; a container style query can, but Firefox only shipped those in 151 on
    19 May 2026. On anything older the query is dropped, the outline goes and
    the label stays - a stray chip on a production page. An attribute selector
    has no such floor.

Navigation works without JavaScript
===================================

The main navigation is **fully usable with no JavaScript at all**. It is laid
out stacked below the breakpoint and in a row above it, and needs no script.

Collapsing behind the toggle is an *enhancement*, gated behind a
:html:`data-js` attribute that the theme's script sets on the root element.
A plain :html:`<button>` carries no native disclosure behaviour the way
:html:`<details>` does, so collapsing by default would hide the entire menu
whenever that script had not run.

Impact
======

Nothing renders differently yet: the Fluid templates that emit this markup are
not part of this release. What ships is the stylesheet those templates will be
written against, and the markup contract each component documents in its own
file header.

The compiled stylesheet grows from roughly 6 kB to roughly 54 kB, uncompressed
and before transport compression.

..  note::

    There is exactly one breakpoint, 48rem, and it is a **Sass variable**
    rather than a custom property, because a media query condition is evaluated
    before the cascade runs. That makes it the one re-theming operation which
    requires recompiling the SCSS rather than overriding a property.

..  note::

    The image gallery's classes were renamed to carry the prefix, which is a
    rename and not a breaking change: version 1.0 has not been released yet.

    ..  list-table::
        :header-rows: 1

        *   -   Previously
            -   Now
        *   -   :css:`.gallery`, :css:`.gallery--left|center|right`
            -   :css:`.theme-gallery`, :css:`.theme-gallery--left|center|right`
        *   -   :css:`.gallery__row`, :css:`__item`, :css:`__image`, :css:`__zoom`, :css:`__caption`
            -   :css:`.theme-gallery__row` and the same element names
        *   -   :html:`data-gallery-columns`
            -   :html:`data-theme-gallery-columns`

    It shipped before the library existed and was the only unprefixed
    component. :css:`.gallery` is precisely the generic name the prefix exists
    to protect against.
