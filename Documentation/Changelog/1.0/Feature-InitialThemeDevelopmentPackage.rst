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

    No theme assets ship in this release. The extension contains no templates,
    no TypoScript and no site set, so installing it does not change what a
    frontend renders. What this release delivers is the foundation the theme is
    built on, and the theme is added along with the implemented features.

..  warning::

    This is a development tool, not a production theme. It is meant to be
    required as a development dependency of an extension repository or
    installed into a disposable test instance, and it makes no promise about
    design, markup stability or upgrade paths for a live site.

What this release provides:

*   TYPO3 v13 and v14 support on PHP 8.2 up to 8.5, with core version aware
    implementations below :file:`Core13/` and :file:`Core14/`.
*   Dependency injection wiring through :file:`Configuration/Services.php`,
    with services configured by Symfony dependency injection attributes on the
    classes themselves.
*   Container based tooling through :file:`Build/Scripts/runTests.sh` covering
    linting, coding guidelines, static analysis, unit and functional tests and
    documentation rendering.
*   GitHub Actions workflows running these gates for TYPO3 v13 and v14 on pull
    requests.
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
