..  include:: /Includes.rst.txt

..  _start:

========================================
Frontend Theme for Extension Development
========================================

:Extension key:
    theme_extension_development

:Package name:
    sbuerk/theme-extension-development

:Version:
    |release|

:Language:
    en

:Author:
    sbuerk

:License:
    This document is published under the
    `Open Content License <https://www.openhub.net/licenses/opl>`__.

:Rendered:
    |today|

----

TYPO3 frontend theme for development purposes: extension development, DDEV
based test instances and acceptance tests.

..  note::

    This is a development tool, not a production theme. It renders pages,
    navigation and every content element the core registers without depending
    on :file:`fluid_styled_content`, and the public API is not stable and may
    change without a deprecation phase until the first stable release.

----

..  card-grid::
    :columns: 1
    :columns-md: 2
    :gap: 4
    :class: pb-4
    :card-height: 100

    ..  card:: :ref:`Introduction <introduction>`

        Learn what the extension is for and which TYPO3 and PHP versions are
        supported.

    ..  card:: :ref:`Installation <installation>`

        Install the extension in your development or test instance.

    ..  card:: :ref:`Configuration <configuration>`

        Enable the theme for a site and override its templates.

    ..  card:: :ref:`Changelog <changelog>`

        Overview of the changes per released version.

..  toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Changelog/Index
