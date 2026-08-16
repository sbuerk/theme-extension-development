..  include:: /Includes.rst.txt

..  _feature-extbase-plugin-rendering:

=================================
Feature: Extbase plugin rendering
=================================

Description
===========

A third-party Extbase plugin now renders on an installation using this
theme, whether it is registered as a dedicated :guilabel:`CType` (the way
:php:`TYPO3\CMS\Extbase\Utility\ExtensionUtility::configurePlugin()`
recommends) or through the historical :guilabel:`General Plugin` /
:guilabel:`list` registration TYPO3 v13.4 still offers.

:php:`configurePlugin()` generates :typoscript:`tt_content.<pluginSignature>
=< lib.contentElement` for every plugin, unconditionally - and on an
installation without ``fluid_styled_content``, which is what this theme is
built for, nothing outside the theme defines :typoscript:`lib.contentElement`
at all. Before this change nothing rendered that object's
:typoscript:`templateName = Generic`, so every such plugin fell through to
TYPO3's own "no rendering definition" notice, indistinguishable to an editor
from a broken content element.

:file:`Resources/Private/Templates/Generic.html` is the new template that
fixes that - shared by every plugin regardless of extension, because
:typoscript:`templateName = Generic` is a fixed string the core writes
itself, not something a plugin author controls. It reads back the
per-plugin :typoscript:`20` cObject the core places beside
:typoscript:`templateName` at a path built from the record being rendered
(:typoscript:`tt_content.{data.CType}.20`), rather than a fixed one, so the
one template serves every plugin without knowing which one it is.

:guilabel:`General Plugin` / ``list``
======================================

TYPO3 v13.4 still offers the historical registration TCA
(:typoscript:`types.list` in ``EXT:frontend``'s own
:file:`Configuration/TCA/tt_content.php`), deprecated but present
(Deprecation :issue:`105076`). ``fluid_styled_content`` supplied the
:typoscript:`tt_content.list` object that rendered it, as a :typoscript:`CASE`
keyed on the plugin's :guilabel:`Type` (``list_type``) field. This theme now
supplies that object too, in its own house style, reusing the same
:file:`Generic.html` template - a ``list`` record's own :guilabel:`CType` is
``list``, so the same :typoscript:`{data.CType}.20` path resolves to the
:typoscript:`CASE` rather than to a single plugin.

An installation is free to ignore the deprecated registration entirely, and
a plugin registered as its own :guilabel:`CType` never reaches this branch.
See :file:`docs/architecture/content-elements.md` in the developer
documentation for how the two registrations resolve to the same template.

Impact
======

An Extbase plugin registered by a third-party extension - one this theme
does not control the TypoScript of - now renders instead of the core's "no
rendering definition" notice on TYPO3 v13.4, regardless of whether it is
registered as its own :guilabel:`CType` or through the historical
:guilabel:`General Plugin` type.

:file:`Tests/Functional/Fixtures/Extensions/plugin-fixture` is a fixture
extension that registers a plugin with **no** TypoScript rendering
definition of its own - unlike :file:`Tests/Functional/Fixtures/Extensions/
example-fixture`, which deliberately overrides what :php:`configurePlugin()`
generates - so the only thing that can make it render is this theme's own
:typoscript:`lib.contentElement` and :file:`Generic.html`.
