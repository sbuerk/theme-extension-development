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
recommends, and the only registration TYPO3 v14 accepts) or, on TYPO3 v13.4
only, through the historical :guilabel:`General Plugin` / :guilabel:`list`
registration.

For a plugin registered as its own :guilabel:`CType`,
:php:`configurePlugin()` generates :typoscript:`tt_content.<pluginSignature>
=< lib.contentElement` with :typoscript:`templateName = Generic`. On TYPO3
v14.3 that is every plugin, because any plugin type other than
:guilabel:`CType` throws. On v13.4 it is only a plugin registered with
:php:`ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT`: the default there is
still ``list_type``, which gets
:typoscript:`tt_content.list.20.<pluginSignature>` instead - see below.

Of the TYPO3 system extensions, ``fluid_styled_content`` defines
:typoscript:`lib.contentElement` on TYPO3 v13.4 and v14.3, and on v14.3
``theme_camino`` does as well, since v14.1 (Feature :issue:`108539`), each
with a ``Generic`` template of its own. On a site that includes the
TypoScript of neither, nothing rendered that object's
:typoscript:`templateName = Generic` before this change, so every such plugin
fell through to TYPO3's own "no rendering definition" notice,
indistinguishable to an editor from a broken content element.

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
(Deprecation :issue:`105076`). ``fluid_styled_content`` supplied the
:typoscript:`tt_content.list` object that rendered it, as a :typoscript:`CASE`
keyed on the plugin's :guilabel:`Type` (``list_type``) field, and removed it
outright in v14.0 together with the ``list`` :guilabel:`CType` itself
(Breaking :issue:`105377`). This theme now supplies that object too, in its
own house style, reusing the same :file:`Generic.html` template - a
``list`` record's own :guilabel:`CType` is ``list``, so the same
:typoscript:`{data.CType}.20` path resolves to the :typoscript:`CASE` rather
than to a single plugin.

It is declared unconditionally rather than behind a version condition: on
v14 the ``list_type`` database column itself was dropped along with the
``CType``, so nothing can ever reach the branch - verified directly against
the installed v14.3.7 core, not only argued from the changelog. See
:file:`docs/architecture/content-elements.md` in the developer documentation
for the full verification and the reasoning for leaving it unconditional.

Both delivery paths
===================

The rendering the core generates for a plugin is added to a site using site
sets unconditionally, but to a site configured through a :sql:`sys_template`
record only right after a static include that is registered as a content
rendering template - the role ``fluid_styled_content`` plays for the
installations that use it. The theme's static include is registered as one,
so a plugin renders there as well. Before, a site using the static include
rendered every other content element and showed the "no rendering
definition" notice for plugins alone.

Impact
======

An Extbase plugin registered by a third-party extension - one this theme
does not control the TypoScript of - now renders instead of the core's "no
rendering definition" notice, on both TYPO3 v13.4 and v14.3, regardless of
whether it is registered as its own :guilabel:`CType` or, on v13.4, through
the historical :guilabel:`General Plugin` type.

:file:`Tests/Functional/Fixtures/Extensions/plugin-fixture` is a fixture
extension that registers a plugin with **no** TypoScript rendering
definition of its own - unlike :file:`Tests/Functional/Fixtures/Extensions/
example-fixture`, which deliberately overrides what :php:`configurePlugin()`
generates - so the only thing that can make it render is this theme's own
:typoscript:`lib.contentElement` and :file:`Generic.html`.
