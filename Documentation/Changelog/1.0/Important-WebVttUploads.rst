..  include:: /Includes.rst.txt

..  _important-web-vtt-uploads:

==============================================
Important: WebVTT files become uploadable
==============================================

Description
===========

The extension appends ``vtt`` to
:php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext']` in its
:file:`ext_localconf.php`. This is a change to what the **whole installation**
accepts as an upload, made by a theme, so it is stated here rather than left in
a code comment.

What exactly changes
--------------------

..  list-table::
    :header-rows: 1

    *   -   Before
        -   After
    *   -   ``css,csv,htm,html,js,json,md,rst,rtf,sql,srt,tmpl,ts,txt,typoscript,xlf,xml,yaml,yml``
        -   the same list, with ``vtt`` appended

The value is read, split, and written back with one entry added. **Nothing the
core ships is removed, replaced or reordered**, and the entry is added only
when it is not already present - so an installation that already allows
``vtt``, through its own :file:`additional.php` or another extension, is
changed in no way at all. The order of the other extensions' additions is
preserved, because the list is only ever appended to.

Why this is necessary at all
----------------------------

:guilabel:`Text & Media` gained a :guilabel:`Captions` field for WebVTT caption
tracks - see :ref:`feature-audio-and-video`. A caption track is what WCAG 1.2.2
asks of a video, and it is the one part of a media element that is not
decoration.

A file reaches a storage only if its extension is in one of the three lists
:php:`ResourceConsistencyService::getAllowedFileExtensions()` reads -
``textfile_ext``, ``mediafile_ext`` and ``miscfile_ext`` - whenever the
security feature ``security.system.enforceAllowedFileExtensions`` is on. TYPO3
ships ``srt``, the other subtitle format, in ``textfile_ext``, and ``vtt`` in
none of the three. Without this change an editor uploading the file the field
asks for gets :guilabel:`Resource consistency check failed`, a message that
names neither the file nor the extension, and the field can never be filled.
The registration is therefore a prerequisite of the feature, not a convenience
for the demo tree.

``textfile_ext`` and not ``mediafile_ext``: a WebVTT file is text - the
transcript of a medium, not a medium - and ``mediafile_ext`` is what
``common-media-types`` resolves to, which is the list the :guilabel:`Media`
field accepts. A caption file has no business being offered there.

Why the extension and not only the development instance
-------------------------------------------------------

The narrower option was to make the change in the development instances alone,
in their :file:`config/system/additional.php`, so that only the showcase is
affected. That was rejected: it would make the shipped
:guilabel:`Captions` field work in this repository and fail in every
installation that uses the extension, with the misleading error above and
nothing to point at the cause. A field an extension ships has to work where the
extension is installed.

Impact
======

An installation using this extension accepts ``.vtt`` uploads. That is the
intent, and it is the minimum the :guilabel:`Captions` field needs.

If you do not want it, remove the entry again in your own
:file:`additional.php`, which is loaded after :file:`ext_localconf.php`:

..  code-block:: php

    $extensions = GeneralUtility::trimExplode(',', $GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'], true);
    $GLOBALS['TYPO3_CONF_VARS']['SYS']['textfile_ext'] = implode(
        ',',
        array_filter($extensions, static fn(string $extension): bool => $extension !== 'vtt'),
    );

The :guilabel:`Captions` field then stays in the form and refuses the upload,
which is the state the field was in before this change.
