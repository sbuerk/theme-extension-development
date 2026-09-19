..  include:: /Includes.rst.txt

..  _feature-external-media:

=====================================================
Feature: External media, without loading it in advance
=====================================================

Description
===========

A new content element, :guilabel:`External media`, in the :guilabel:`Theme`
group of the :guilabel:`Create new content element` wizard. It shows a video of
another site - and loads nothing from that site until a visitor presses play.

The editor gives it an address, a name, a shape and a poster image:

..  list-table::
    :header-rows: 1

    *   -   Field
        -   Is
    *   -   :guilabel:`Address of the video`
        -   The page of the video, as you copied it out of the browser.
    *   -   :guilabel:`Name of the video`
        -   Announced to a screen reader in place of the frame the video is
            loaded into. The heading of the element is used where it is empty.
    *   -   :guilabel:`Shape`
        -   :guilabel:`Widescreen (16:9)` or :guilabel:`Classic (4:3)`.
    *   -   :guilabel:`Poster`
        -   The picture shown in place of the video until it is played. A file
            of this installation - a thumbnail fetched from the provider would
            be the very request this element avoids.

Until the button is pressed the page carries the poster, a note and a link to
the source, and **no** :html:`iframe`, no preconnect and no image of the other
site. Nothing of the video's host - not a cookie, not an entry in its logs -
reaches a visitor who does not watch it.

What is embedded, and what is not
---------------------------------

YouTube and Vimeo addresses are recognised in the forms a person actually
copies - the watch page, the short link, the share link, the embed link - and
are rewritten onto the host that sets no cookie in advance:
``youtube-nocookie.com``, and ``player.vimeo.com`` with Vimeo's own ``dnt=1``.
An editor pasting the ordinary watch address does not have to know that.

**Any other address is not embedded.** The element then shows the poster and
the link to the source, with no play button - the same page a visitor without
JavaScript gets. That is deliberate and not a gap to be filled by pasting the
address into an :html:`iframe`: the field is not a trust boundary, an
:html:`iframe` runs the other origin's script inside the page, and a watch page
of an unknown site is a page rather than a player - most sites refuse to be
framed at all, so the reader would get an empty box and no explanation.

Without JavaScript
------------------

The play button is not rendered at all until the theme's script has bound it.
The link to the source is always there, so the video is one press away whether
there is a script or not.

Impact
======

Integrators get one new content type to grant to editor groups,
``theme_external_media``. The database analyzer adds three columns to
``tt_content``: ``tx_theme_embed_url``, ``tx_theme_embed_title`` and
``tx_theme_embed_ratio``. The element reuses the existing ``image`` column for
its poster.

A site package overriding :file:`Templates/ContentElements/` finds the template
there as :file:`ThemeExternalMedia.html`, and the stylesheet gains the
:html:`.theme-embed` component - see :ref:`components`. Which addresses become
an embed is decided in
:php:`SBUERK\ThemeExtensionDevelopment\DataProcessing\ExternalMediaProcessor`;
a site package with a fourth provider overrides that class, where the whole of
that knowledge is.

The showcase page :guilabel:`/elements/theme/external-media` shows both shapes,
an element without a poster and one whose host is not embedded.
