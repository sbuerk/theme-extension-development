..  include:: /Includes.rst.txt

..  _feature-initial-theme-development-package:

==========================================
Feature: Initial theme development package
==========================================

Description
===========

Initial release of ``sbuerk/theme-extension-development``. The package is a
TYPO3 frontend theme for development purposes: its job is to give a TYPO3
installation a reasonable frontend to look at and to render against, without
building a site package for it first — for extension development, for DDEV
based test instances and for acceptance tests.

..  important::

    The theme is being built up. This release renders a page - see
    :ref:`feature-site-set-page-rendering` - and the content elements that exist
    without ``fluid_styled_content`` - see
    :ref:`feature-content-element-rendering`.

..  warning::

    This is a development tool, not a production theme. It is meant to be
    required as a development dependency of an extension repository or
    installed into a disposable test instance, and it makes no promise about
    design, markup stability or upgrade paths for a live site.

What this release provides:

*   TYPO3 v12.4 and v13.4 support on PHP 8.1 up to 8.4 - 8.1 for TYPO3 v12
    only - with core version aware implementations below :file:`Core12/` and
    :file:`Core13/`. See :ref:`feature-typo3-v12-support`.
*   Dependency injection wiring through :file:`Configuration/Services.php`,
    with services configured by Symfony dependency injection attributes on the
    classes themselves.
*   Container based tooling through :file:`Build/Scripts/runTests.sh` covering
    linting, coding guidelines, static analysis, unit and functional tests and
    documentation rendering.
*   GitHub Actions workflows running these gates for both supported TYPO3
    versions on pull requests.
*   A functional test setup ready to build on: strict PHPUnit configuration,
    an example fixture extension loaded by its composer package name, site
    based tests issuing frontend sub-requests in several languages, and
    repository tests running in a built frontend environment.
*   Developer documentation below :file:`docs/`, covering the architecture,
    the quality gates, both test suites and the release workflow.

The extension ships a :php:`SBUERK\ThemeExtensionDevelopment\Dummy` placeholder
class and an :php:`SBUERK\ThemeExtensionDevelopment\Example\ExampleInterface`
example service, both meant to be removed once the first real implementation is
added.
