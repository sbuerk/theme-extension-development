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
=< lib.contentElement` for every plugin registered as its own
:guilabel:`CType`. Of the TYPO3 system extensions, only
``fluid_styled_content`` defines :typoscript:`lib.contentElement`, on TYPO3
v12.4 and v13.4 alike, with a ``Generic`` template of its own. On a site that
does not include its TypoScript, which is what this theme is built for,
nothing rendered that object's :typoscript:`templateName = Generic` before
this change, so every such plugin fell through to TYPO3's own "no rendering
definition" notice, indistinguishable to an editor from a broken content
element.

:file:`Resources/Private/Templates/Generic.html` is the new template that
fixes that - shared by every plugin regardless of extension, because
:typoscript:`templateName = Generic` is a fixed string the core writes
itself, not something a plugin author controls. It reads back the
per-plugin :typoscript:`20` cObject the core places beside
:typoscript:`templateName` at a path built from the record being rendered
(:typoscript:`tt_content.{data.CType}.20`), rather than a fixed one, so the
one template serves every plugin without knowing which one it is. The plugin
is rendered with its content element, so the settings an editor chose in its
FlexForm reach it.

:guilabel:`General Plugin` / ``list``
======================================

TYPO3 v13.4 still offers the historical registration TCA
(:typoscript:`types.list` in ``EXT:frontend``'s own
:file:`Configuration/TCA/tt_content.php`), deprecated but present
(Deprecation :issue:`105076`). A plugin registered that way gets
:typoscript:`tt_content.list.20.<pluginSignature>`, a child of a node that
nothing in the core turns into a :typoscript:`CASE`.
``fluid_styled_content`` renders it, on a site that includes its
TypoScript, with a :typoscript:`tt_content.list` object of its own -
:typoscript:`=< lib.contentElement` with :typoscript:`templateName = List` -
whose :file:`List.html` addresses the plugin directly through the record's
:guilabel:`Type` (``list_type``) field. This theme now supplies
:typoscript:`tt_content.list` too, in its own house style, reusing the same
:file:`Generic.html` template - a ``list`` record's own :guilabel:`CType` is
``list``, so the same :typoscript:`tt_content.{data.CType}.20` path
resolves to :typoscript:`tt_content.list.20` itself, which the theme
declares a :typoscript:`CASE` keyed on ``list_type``, with the plugins as its
branches.

An installation is free to ignore the deprecated registration entirely, and
a plugin registered as its own :guilabel:`CType` never reaches this branch.
See :file:`docs/architecture/content-elements.md` in the developer
documentation for how the two registrations resolve to the same template.

Both delivery paths
===================

The rendering the core generates for a plugin is added to a site using site
sets unconditionally, but to a site configured through a :sql:`sys_template`
record only right after a static include that is registered as a content
rendering template - the role ``fluid_styled_content`` plays for the
installations that use it. On TYPO3 v12, which has no site sets, the theme's
static include has always been registered as one. It now is on TYPO3 v13 as
well, so a plugin renders on a v13 site using the static include too. Before,
such a site rendered every other content element and showed the "no rendering
definition" notice for plugins alone.

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
