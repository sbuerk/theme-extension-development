..  include:: /Includes.rst.txt

..  _feature-fluid-styled-content-bridge:

================================================
Feature: A bridge to fluid_styled_content
================================================

Description
===========

The theme renders every classic content element itself and does not require
``fluid_styled_content``. An installation that has both can now say so, and it
says so the same two ways the theme itself is delivered.

On **TYPO3 v13**, with a site set of its own:

..  code-block:: yaml

    dependencies:
      - sbuerk/theme-extension-development-fsc

That set replaces ``sbuerk/theme-extension-development`` in the site
configuration - it depends on it, so it brings the whole theme with it - and
adds ``typo3/fluid-styled-content`` as an optional dependency, which activates
that extension's own set for the site when it is installed.

On **TYPO3 v12**, and for any installation that uses ``sys_template`` records
rather than site sets, with the matching static template
:guilabel:`Theme Extension Development (fluid_styled_content)`. It has to be
included **last**, after both :guilabel:`Fluid Content Elements` and
:guilabel:`Theme Extension Development`.

The static template is the only route on v12: site sets do not exist there, and
``fluid_styled_content`` 12.4 ships none of its own either. It delivers exactly
the same TypoScript as the set.

Why it is needed
================

Side by side without the bridge, the two extensions are order dependent, and
neither order is what an integrator chose:

*   Loaded **after** the theme, ``fluid_styled_content`` starts its own
    rendering with :typoscript:`lib.contentElement >` and rebuilds the object
    from nothing, taking the theme's Fluid paths with it.
*   Loaded **before** the theme, its per-element data processing stays
    underneath the theme's branches, because :typoscript:`=<` and a plain
    assignment keep every key the theme does not overwrite.

The second failure is the one to know about: both extensions wire
:guilabel:`Content elements for selected categories` through
``DatabaseQueryProcessor`` and configure it differently, so the surviving keys
compose one database query out of two and the page fails with a SQL syntax
error.

The bridge clears the classic ``tt_content`` branches and declares them again
from the theme's own definition, last. The rendered page is then identical to
the page the theme renders on its own, whichever extension was loaded first.

Impact
======

Nothing changes for an installation that does not have ``fluid_styled_content``,
and nothing changes for one that has it but does not enable the bridge.

One detail changes for everyone: the theme's Fluid root paths on
:typoscript:`lib.contentElement` moved from index ``10`` to ``5``. Fluid tries
root paths from the highest index down, and ``fluid_styled_content`` puts its
own templates at ``0`` and the integrator's :typoscript:`styles.templates.*`
override at ``10`` — identical in that extension's 12.4 and 13.4 releases, read
in both. At ``5`` the theme's templates beat that extension's while a documented
override still beats both.

An installation that overrides the paths through the theme's own constants -
:typoscript:`theme.templateRootPath` and its two siblings - is unaffected,
because those constants fill whichever index the theme uses. An installation
that assigned :typoscript:`lib.contentElement.templateRootPaths.10` directly
still wins, and now keeps the theme's own path as a fallback below it, so a
partial override no longer has to provide every template.

..  index:: Frontend, TypoScript, Fluid
