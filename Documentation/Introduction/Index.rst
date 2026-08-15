..  include:: /Includes.rst.txt

..  _introduction:

============
Introduction
============

What does it do?
================

The :guilabel:`Frontend Theme for Extension Development` extension provides a
TYPO3 frontend theme for development purposes. Its job is to give a TYPO3
installation a reasonable frontend to look at and to render against, without
building a site package for it first.

..  important::

    No theme assets ship yet. What this chapter describes is what the extension
    is for, not what it already does — see the note at the end of this section
    for what is in place today.

The situations it is built for, where an extension has to be seen or exercised
in a frontend rather than only in a test assertion:

*   **Extension development**, to click through what an extension actually
    outputs instead of reading the rendered HTML in a test failure.
*   **DDEV based test instances** of an extension repository, where a throwaway
    TYPO3 installation needs a frontend rendering pages, navigation and
    content elements.
*   **Acceptance tests**, which need a stable and predictable frontend to
    drive a browser against.
*   **Reproducing an issue** in a minimal installation before debugging it.

..  warning::

    This is a development tool, not a production theme. It is meant to be
    required as a development dependency of an extension repository or
    installed into a disposable test instance, and it makes no promise about
    design, markup stability or upgrade paths for a live site.

..  note::

    The theme itself is not implemented yet: the extension currently ships no
    templates, no TypoScript and no site set, so installing it does not change
    what a frontend renders. This chapter is extended along with the
    implemented features.

    What is in place is the foundation it is built on: TYPO3 v13 and v14
    support from one code base with
    :ref:`core version aware <introduction-core-version-aware>` classes, wired
    by the dependency injection container of the running TYPO3 version.

..  _introduction-core-version-aware:

Core version aware implementations
==================================

Code that has to differ between the supported TYPO3 versions lives below
:file:`Core13/` and :file:`Core14/` in the repository root. Shared code —
interfaces, abstract base classes and everything working on both core
versions — lives in :file:`Classes/`.

Only the directory matching the running TYPO3 version is registered in the
dependency injection container, so a service asking for an interface always
receives the implementation matching the current core version.

Compatibility
=============

..  list-table::
    :header-rows: 1

    *   -   Branch
        -   Extension
        -   TYPO3
        -   PHP
    *   -   main
        -   1.x
        -   v13 / v14
        -   8.2 - 8.5

Contributing
============

Contributions are welcome. The development setup, the quality gates and the
commit message rules are described in the :file:`CONTRIBUTING.md` file of the
`source repository <https://github.com/sbuerk/theme-extension-development>`__.
