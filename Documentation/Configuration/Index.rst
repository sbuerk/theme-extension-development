..  include:: /Includes.rst.txt

..  _configuration:

=============
Configuration
=============

Enabling the theme for a site
=============================

There are two ways, and which are available depends on the TYPO3 version:

..  list-table::
    :header-rows: 1

    *   -   TYPO3
        -   Site set
        -   Classic static include
    *   -   v12.4
        -   not available
        -   the only way
    *   -   v13.4
        -   recommended
        -   supported

Site sets are a TYPO3 v13.1 feature. On TYPO3 v12 a ``dependencies`` key in a
site configuration is read by nothing, so the classic static include described
below is not a fallback there — it is *the* way to enable the theme.

Both paths read the same TypoScript files, so what they deliver is identical.

Site set — TYPO3 v13
--------------------

A site enables the theme by depending on the set in its
:file:`config/sites/<identifier>/config.yaml`:

..  code-block:: yaml

    dependencies:
      - sbuerk/theme-extension-development

Nothing else is required. The set brings the TypoScript, the page rendering and
the stylesheet with it, and no :guilabel:`sys_template` record is needed. The
set declares no dependencies of its own.

What an integrator changes is **either a site setting or a TypoScript
constant, and for most of it both**: every site setting the theme declares is
also a constant of the same name and the same default, so a site on the site
set edits it in :guilabel:`Site Management > Sites` while a site on the
classic static include below sets the constant. Where a value exists as both,
the setting wins — see :ref:`configuration-site-settings`.

In the backend the same set can be selected under
:guilabel:`Site Management > Sites` in the :guilabel:`Sets` field of the site.

..  _configuration-static-include:

Classic static include — TYPO3 v12 and v13
------------------------------------------

The theme also registers a classic static template. Create a
:guilabel:`sys_template` record on the root page — :guilabel:`Web > List`,
:guilabel:`Create new record`, :guilabel:`System records`,
:guilabel:`TypoScript record` — check :guilabel:`Rootlevel`, check
:guilabel:`Clear` for :guilabel:`Constants` and :guilabel:`Setup`, and select
:guilabel:`Theme Extension Development` in
:guilabel:`Include static (from extensions)`.

..  note::

    Use one mechanism or the other. When a site depends on the set, the static
    include deactivates itself — its constants and setup are wrapped in a
    condition asking whether the set is active for the current site, so an
    installation that has both configured is not served the theme twice.

    That condition asks the site for the sets it declares **itself**. A site
    that pulls the theme in transitively, by depending on another set which in
    turn depends on this one, is not covered: there the static include has to
    stay out of the :guilabel:`sys_template` record.

    On TYPO3 v12 the condition is always true, because no site can declare a
    set there, and the include is never suppressed.

..  _configuration-site-settings:

Site settings
=============

On TYPO3 v13, a site that depends on the set can edit these in the backend,
under :guilabel:`Site Management > Sites`, in the :guilabel:`Settings` tab of
the site:

..  list-table::
    :header-rows: 1

    *   -   Setting
        -   Default
        -   Is

    *   -   ``theme.header.variant``
        -   ``simple``
        -   How the site header arranges the title, the navigation and the
            controls: ``simple``, ``centred``, ``actions`` or ``two-tier`` —
            see :ref:`feature-header-variants`.

    *   -   ``theme.header.actionPage``
        -   ``0``
        -   The page the call to action of the ``actions`` header leads to.
            ``0`` renders no call to action.

    *   -   ``theme.header.actionLabel``
        -   *(empty)*
        -   The text of that call to action. An empty label renders no call to
            action.

Each of them is a TypoScript constant of the same name as well, so a site that
takes the theme through the static include configures it there instead — on
TYPO3 v12, which has no site sets and no settings editor, that is the only
way:

..  code-block:: typoscript

    theme.header.variant = two-tier

The constant belongs in the :guilabel:`Constants` of the
:guilabel:`sys_template` record, or in a site package included after the
theme. A value of the same key in the site's :file:`settings.yaml` does not
reach a site on the static include: the core adds those values as constants
*before* the static templates of the record, so the default the theme's
constants declare overrules it.

..  note::

    The labels of these settings are English in the backend. A site set can
    carry a :file:`labels.xlf`, and on TYPO3 v13.4 the label and the
    description of a setting are taken from it — but the **options of a
    selection** only from TYPO3 v14.2 on. On TYPO3 v13.4 a reference written
    there is printed to the integrator verbatim, and the settings here are
    selections whose options are what an integrator reads.

Templates and stylesheet
========================

The Fluid paths and the stylesheet are TypoScript constants, so an integrator
can render their own templates without editing the theme:

..  list-table::
    :header-rows: 1

    *   -   Constant
        -   Default
    *   -   ``theme.templateRootPath``
        -   :file:`EXT:theme_extension_development/Resources/Private/Templates/`
    *   -   ``theme.partialRootPath``
        -   :file:`EXT:theme_extension_development/Resources/Private/Partials/`
    *   -   ``theme.layoutRootPath``
        -   :file:`EXT:theme_extension_development/Resources/Private/Layouts/`
    *   -   ``theme.stylesheet``
        -   :file:`EXT:theme_extension_development/Resources/Public/Css/theme.css`

..  code-block:: typoscript

    theme {
        templateRootPath = EXT:my_site_package/Resources/Private/Templates/
        partialRootPath = EXT:my_site_package/Resources/Private/Partials/
        layoutRootPath = EXT:my_site_package/Resources/Private/Layouts/
        stylesheet = EXT:my_site_package/Resources/Public/Css/my-theme.css
    }

The three paths are those of both content element objects:
:typoscript:`lib.contentElement`, which renders the classic content elements,
and :typoscript:`lib.themeContentElement`, which renders the theme's own - see
:ref:`important-theme-content-element-object`. A root path added to one of the
two objects directly reaches only the elements of that object.

The stylesheet is compiled from SCSS sources that ship with the extension, so
it can also be rebuilt with different design tokens instead of being replaced —
see :ref:`feature-design-tokens`.

Image width
===========

The image based elements scale their images to the width the layout gives the
content column. That width is a constant, because nothing in TypoScript can
read it out of the stylesheet:

..  code-block:: typoscript

    theme.media {
        # The width in pixels the gallery is computed for.
        maxGalleryWidth = 1200

        # The same, for an element positioned beside the text.
        maxGalleryWidthInText = 420
    }

The default matches the ``75rem`` of ``--theme-content-max-width``. Set it too
low and images are processed smaller than they are displayed, which shows. Set
it far too high and every image is processed at a size no visitor ever sees.

Appearance and palette
======================

The theme renders in a light and a dark appearance and carries five colour
palettes. What is rendered server side — before the display settings in the
header can restore a visitor's choice from ``localStorage`` — is configured
with three constants:

..  list-table::
    :header-rows: 1

    *   -   Constant
        -   Default
        -   Values
    *   -   ``theme.appearance.default``
        -   ``auto``
        -   ``auto``, ``light``, ``dark``
    *   -   ``theme.appearance.palette``
        -   ``neutral``
        -   ``neutral``, ``ember``, ``ocean``, ``moss``, ``violet``
    *   -   ``theme.appearance.contentOutline``
        -   ``on``
        -   ``on``, ``off``

They are written onto the ``<html>`` tag as ``data-theme``, ``data-palette``
and ``data-theme-content-outline``. Two of those are worth knowing exactly:

*   ``auto`` renders **no** ``data-theme`` attribute at all. The appearance is
    then left to the operating system through ``color-scheme``, which is what
    ``light-dark()`` in the stylesheet resolves against. A palette has no such
    case, so ``neutral`` is still written out.
*   ``contentOutline`` draws the labelled outline around every content element.
    Only ``off`` has a rule of its own; ``on`` is simply the absence of it. It
    is a development and staging affordance — a site package rendering for real
    visitors sets it to ``off``.

All three are defaults. A visitor can change each of them behind the cog at
the end of the header - the outline with the :guilabel:`Element outlines`
switch - and the choice is kept in that browser. :guilabel:`Reset` in the same
panel returns to the values configured here: they are also handed to the page
template as ``settings.appearance.default``, ``settings.appearance.palette``
and ``settings.appearance.contentOutline``, and rendered next to the control
for exactly that purpose.

See :ref:`feature-display-settings` for the display settings,
:ref:`feature-appearance-switcher` for the mechanism behind them and
:ref:`feature-design-tokens` for what the palettes are built from.

Backend layouts and page templates
==================================

The theme ships its backend layouts through page TSconfig, and the layout an
editor selects picks the Fluid template the page is rendered with:

..  list-table::
    :header-rows: 1

    *   -   Identifier
        -   Backend label
        -   Columns (``colPos``)
        -   Page template
    *   -   ``default``
        -   :guilabel:`Default`
        -   main (0)
        -   :file:`Page/Default.html`
    *   -   ``content``
        -   :guilabel:`Content page`
        -   stage (2), main (0), footer 1-4 (11-14), footer meta (10)
        -   :file:`Page/Content.html`
    *   -   ``content_sidebar``
        -   :guilabel:`Content page with sidebar`
        -   stage (2), main (0), sidebar (1), footer 1-4 (11-14), footer meta
            (10)
        -   :file:`Page/ContentSidebar.html`
    *   -   ``start``
        -   :guilabel:`Start page`
        -   stage (2), main (0), footer 1-4 (11-14), footer meta (10)
        -   :file:`Page/Start.html`
    *   -   ``two_columns``
        -   :guilabel:`Two columns (50/50)`
        -   stage (2), main (0), second column (3)
        -   :file:`Page/TwoColumns.html`
    *   -   ``two_columns_wide``
        -   :guilabel:`Two columns (66/33)`
        -   stage (2), main (0), second column (3)
        -   :file:`Page/TwoColumnsWide.html`
    *   -   ``three_columns``
        -   :guilabel:`Three columns`
        -   stage (2), main (0), second column (3), third column (4)
        -   :file:`Page/ThreeColumns.html`
    *   -   ``article``
        -   :guilabel:`Article`
        -   stage (2), main (0), sidebar (1)
        -   :file:`Page/Article.html`
    *   -   ``cover``
        -   :guilabel:`Cover page`
        -   main (0)
        -   :file:`Page/Cover.html`
    *   -   ``bands``
        -   :guilabel:`Full-width bands`
        -   main (0), second column (3), third column (4)
        -   :file:`Page/Bands.html`
    *   -   ``styleguide``
        -   :guilabel:`Styleguide`
        -   unused (999)
        -   :file:`Page/Styleguide.html`
    *   -   ``forms``
        -   :guilabel:`Form showcase`
        -   unused (999)
        -   :file:`Page/Forms.html`

The ``colPos`` numbers are a contract, not an implementation detail: the same
number means the same slot in every layout, which is what lets an editor change
a page's layout without content disappearing. The footer columns and the footer
meta row slide down the rootline, so they are edited once on the start page and
appear on every page below it.

The mapping is by convention rather than by configuration: the identifier is
upper-camel-cased and prefixed with :file:`Page/`, so a new layout needs a
TSconfig file and a template of the matching name and nothing else. A page
without a layout, and a page whose layout is TYPO3's built-in
:guilabel:`[None]`, both render with :file:`Page/Default.html`.

See :ref:`feature-backend-layouts`, :ref:`feature-column-page-layouts` and
:ref:`feature-cover-article-and-band-layouts`.

Content elements
================

The theme brings its own content element rendering and does **not** depend on
:file:`fluid_styled_content` — that extension is not required here, and the
development instances do not install it. An installation that has it anyway
enables the bridge instead, see :ref:`feature-fluid-styled-content-bridge`.
What the theme's own rendering covers:

*   Every classic content element :file:`EXT:frontend` registers, including
    :guilabel:`Text & Media`, :guilabel:`Bullet List`, :guilabel:`Table`,
    :guilabel:`File Links`, :guilabel:`Insert Records`, :guilabel:`Divider` and
    :guilabel:`Plain HTML` — see :ref:`feature-core-content-elements`.
*   All eleven menu elements — see :ref:`feature-menu-content-elements` —
    and cards and thumbnails for two of them — see
    :ref:`feature-menu-card-layouts`.
*   The content elements of the theme's own, in a :guilabel:`Theme` group —
    see :ref:`feature-theme-content-elements`,
    :ref:`feature-notice-tabs-accordion-elements`,
    :ref:`feature-icon-content-elements`,
    :ref:`feature-card-group-timeline-teaser-list`,
    :ref:`feature-carousel-and-split-tiles`,
    :ref:`feature-external-media` and
    :ref:`feature-pricing-element`.
*   A wall layout for the card group — see :ref:`feature-card-wall`.
*   :guilabel:`Enlarge on click`, which opens a lightbox — see
    :ref:`feature-gallery-lightbox`.
*   Audio and video files in :guilabel:`Text & Media`, with a caption track —
    see :ref:`feature-audio-and-video`.
*   A language menu in the site header, which shows a language the current page
    is not translated into as unavailable rather than hiding it — see
    :ref:`feature-language-menu`.
*   A table of contents in the sidebar and a :guilabel:`Back to top` link, both
    from the fields of the :guilabel:`Appearance` tab — see
    :ref:`feature-table-of-contents-and-link-to-top`.
*   Third-party Extbase plugins, which render through a generic template
    without any per-plugin configuration — see
    :ref:`feature-extbase-plugin-rendering` — on both delivery paths, the
    site set and the static include.

The fields of the :guilabel:`Appearance` tab - frame, space before and after -
and the alignment and style of the header change how an element looks, and the
page TSconfig of the theme takes the ones it does not render out of the form -
see :ref:`feature-content-element-appearance`.

Nothing an editor can create is left without a rendering definition, and a test
asserts exactly that: it walks the content types registered in TCA and fails if
the core's "no rendering definition" notice appears for any of them.

Links
=====

The link of a theme content element has a style and an optional icon - see
:ref:`feature-theme-link-icon-and-style`. The icon fields offer about a
hundred icons by default; page TSconfig widens the list - see
:ref:`feature-icon-picker`.

A link to another site, a file, an email address or a phone number is marked
with a glyph after its text, and a new window or a download is announced to
screen readers - see :ref:`feature-link-decoration`. A constant switches that
off:

..  code-block:: typoscript

    theme.linkDecoration = 0

Demo content
============

A page tree to look at is imported rather than built by hand. The extension
ships it as a seed set of
`sbuerk/data-factory <https://packagist.org/packages/sbuerk/data-factory>`__,
which is suggested rather than required:

..  code-block:: bash

    composer require --dev sbuerk/data-factory
    vendor/bin/typo3 data-factory:import theme-demo

The set seeds a start page, pages for typography and media, one page
deliberately without a backend layout, a ``/elements`` branch carrying every
content type, a ``/layouts`` branch with one wireframe page per backend layout,
and a ``/styleguide`` page rendering the whole component library
— see :ref:`feature-seeded-showcase-tree` and :ref:`feature-styleguide`. Its
records declare their uids and point at each other by them, so it is imported
into an installation where those uids are free. The set declares no site
configuration: create one with root page ``1`` afterwards, which is also what
makes the tree answer in the frontend. On TYPO3 v12, which has no site sets,
the tree renders through the theme once a :guilabel:`sys_template` record on
page ``1`` selects the static include as well — see
:ref:`configuration-static-include`. ``data-factory:import --help`` lists every
option.
