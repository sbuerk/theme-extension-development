..  include:: /Includes.rst.txt

..  _feature-file-list-display-types:

=========================================
Feature: File type icons in the file list
=========================================

Description
===========

The :guilabel:`File Links` content element renders its files as the file list
component of the theme, and each of its three display types looks different:

..  list-table::
    :header-rows: 1

    *   -   Display file/icon/thumbnail
        -   Renders
    *   -   :guilabel:`Only file name`
        -   The names of the files, one per row.
    *   -   :guilabel:`File name and file extension icon`
        -   The icon of the file type before each name: PDF, image, archive,
            plain text, CSV, text document, spreadsheet, presentation, audio,
            video, and a plain file for any other type. The icon goes by the
            extension of the file.
    *   -   :guilabel:`File name and thumbnail (if possible)`
        -   A square thumbnail of every image, and the icon of the file type in
            a square of the same size for every file that is not an image.

:guilabel:`Show file size` and :guilabel:`Show description` add the size and
the description of the file reference in every display type.

Before, the icon of the second display type was not drawn at all, and it
rendered like the first.

Impact
======

The element renders :html:`<ul class="theme-file-list">`, with the modifier
:css:`--icon` or :css:`--preview` of the display type. The classes
:css:`theme-content-element__file-list`, :css:`__file-item`, :css:`__file-link`,
:css:`__file-name`, :css:`__file-thumbnail`, :css:`__file-size` and
:css:`__file-description` are no longer written; a site package that styled
them styles the classes of the component instead.

The icon, and the thumbnail beside it, sit next to the link rather than inside
it: the link is the file name. A thumbnail has an empty alternative text,
because the name next to it already says which file it is.

The :guilabel:`Layout` field stays hidden for this element: the display type
already is its layout.
