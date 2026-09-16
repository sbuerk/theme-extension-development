..  include:: /Includes.rst.txt

..  _feature-audio-and-video:

===============================================
Feature: Audio and video in Text & Media
===============================================

Description
===========

The :guilabel:`Text & Media` element reads the :guilabel:`Media` field, which
takes a video or an audio file as well as an image. Until now the theme
rendered every file in it as an image, which a video is not. Both now render as
what they are: a native player with the browser's own controls, keyboard
handling and full screen button.

A new field, :guilabel:`Captions`, holds WebVTT files:

..  code-block:: text

    launch.mp4    the video
    launch.vtt    its captions

**A caption file belongs to the media file of the same name.** Upload both,
put the video in :guilabel:`Media` and the caption file in
:guilabel:`Captions`, and the player offers the track in its own menu. Pairing
them by the order of the two lists was the alternative and was rejected: an
image added in the middle of :guilabel:`Media` would silently re-point every
caption after it, and nothing on the page would look wrong.

Why the theme writes the tag
----------------------------

TYPO3 renders a media file through :php:`AudioTagRenderer` and
:php:`VideoTagRenderer`, which :html:`f:media` dispatches to. Both emit
:html:`<video controls><source …></video>` and **neither has any notion of a
text track** - there is no :html:`track` in either class, on TYPO3 v13.4 or
v14.3. A caption track is the one part of a media element that is not
decoration, so the theme writes the tag itself and leaves those renderers to
whoever wants their defaults.

Impact
======

The database analyzer adds one column to ``tt_content``,
``tx_theme_captions``, a file field restricted to ``vtt``. It is shown on
:guilabel:`Text & Media` only - the other two media elements read the
``image`` field, which takes images alone.

**The extension adds** ``vtt`` **to**
:php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext']` in
:file:`ext_localconf.php`, appended and only where it is not already there.
TYPO3 ships ``srt`` - the other subtitle format - in that list and ``vtt`` in
none of the three lists a file may be uploaded under, so without it an editor
uploading a caption file gets :guilabel:`Resource consistency check failed` and
the field can never be filled. ``vtt`` is deliberately **not** added to
``mediafile_ext``, which is what the :guilabel:`Media` field accepts: a caption
file is not a medium of the element, it belongs to one.

The stylesheet gains the :html:`.theme-media` component - see
:ref:`components`. A gallery item that is a player carries it beside
:html:`.theme-figure`, so the caption stays the gallery's own.

The player carries ``preload="metadata"``: a gallery may hold several of them,
and none is what the visitor came for. A video is letterboxed into a 16:9 box
until its own dimensions are known, because FAL records no width or height for
a video file and the gallery would otherwise reserve a box of no height for it.

An installation that has put a video into :guilabel:`Media` before this change
was showing a broken image in its place; it now shows a player, with no change
to the record.
