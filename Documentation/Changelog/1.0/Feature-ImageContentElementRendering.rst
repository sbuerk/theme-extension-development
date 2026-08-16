..  include:: /Includes.rst.txt

..  _feature-image-content-element-rendering:

========================================
Feature: Rendering of the Images element
========================================

Description
===========

The content element :guilabel:`Images` (``CType`` ``image``) is rendered by the
theme.

No TCA is added for it. The element is registered by **EXT:frontend**, in
:file:`Configuration/TCA/Overrides/225-tt_content-content_type-image.php`, on
TYPO3 v13.4 and v14 alike - what ``fluid_styled_content`` contributes for it is
the rendering, and that is what this theme now brings itself.

The backend fields of the element decide the layout, and all of them are
honoured:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Effect
    *   -   :guilabel:`Images`
        -   The file references that are rendered, in their sorted order.
    *   -   :guilabel:`Number of columns`
        -   The number of images per row.
    *   -   :guilabel:`Position and alignment`
        -   Rendered as the modifier classes ``gallery--above``,
            ``gallery--below``, ``gallery--intext``, ``gallery--left``,
            ``gallery--center`` and ``gallery--right``.
    *   -   :guilabel:`Width` / :guilabel:`Height`
        -   A fixed dimension all images are scaled to, with the row scaled
            down when it would exceed the gallery width.
    *   -   :guilabel:`Enable click-enlarge`
        -   Wraps the image in a link to the original file. No lightbox: this
            extension ships no JavaScript.

The :guilabel:`Alternative text`, :guilabel:`Title` and :guilabel:`Description`
of a file reference become the ``alt`` attribute, the ``title`` attribute and a
``<figcaption>``.

Every image is rendered with a ``width`` and a ``height`` attribute, so the
browser can reserve its box before the file has loaded.

Impact
======

An installation using the theme no longer renders the TYPO3 "no rendering
definition" notice for :guilabel:`Images`.

The width the gallery is computed for is a constant and should match the width
the layout gives the content column:

..  code-block:: typoscript

    theme.media {
        maxGalleryWidth = 1200
        maxGalleryWidthInText = 420
    }

The markup is generated from
:file:`Resources/Private/Templates/ContentElements/Image.html` and
:file:`Resources/Private/Partials/ContentElement/Gallery.html`, which the Fluid
path constants redirect together with every other template - see
:ref:`configuration`.
