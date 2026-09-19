..  include:: /Includes.rst.txt

..  _feature-gallery-lightbox:

==========================================
Feature: Enlarge on click opens a lightbox
==========================================

Description
===========

:guilabel:`Enlarge on click` - the field :sql:`image_zoom` of the
:guilabel:`Images`, :guilabel:`Text & Images` and :guilabel:`Text & Media`
elements - now opens the image in a dialog instead of only linking to the file.

Every image of the element that carries it gets a badge in its corner, and the
dialog shows the file at its own size with the caption the file reference
carries. Where the element has more than one image, the dialog moves between
them: two buttons, and the arrow keys. :kbd:`Escape` closes it, and focus goes
back to the image that was pressed.

Nothing has to be configured. An element that already had
:guilabel:`Enlarge on click` ticked shows the lightbox after the update.

Without JavaScript
------------------

**The link is still the link.** The image keeps its :html:`href` to the file,
so a visitor without JavaScript - or one whose browser never loaded the theme's
script - gets exactly what this element did before: the file, in the browser.
The dialog is an enhancement on top of a link that works on its own, which is
why the link is deliberately *not* the :html:`data-theme-dialog-open` opener
the dialog component documents: that attribute is hidden while there is no
script, and hiding this link would take the fallback away with the enhancement.

Impact
======

Integrators get one new component in the stylesheet, :html:`.theme-lightbox`,
and one new partial, :file:`Partials/ContentElement/Lightbox.html`, rendered by
:file:`Partials/ContentElement/Gallery.html` once per element. A site package
that overrides the gallery partial and wants the lightbox has to render it, and
one that does not want it renders neither the partial nor the two data
attributes on the zoom link.

The dialog carries one item per image, addressed by the id of its file
reference rather than by a position - a :guilabel:`Text & Media` element may
hold a video between two images, and a numbering would have to agree with a
list the template does not have.

The badge on an enlargeable image is the :html:`magnifying-glass-plus` icon of
the shipped set, and the two arrows :html:`chevron-left` and
:html:`chevron-right` - see :ref:`icons`.
