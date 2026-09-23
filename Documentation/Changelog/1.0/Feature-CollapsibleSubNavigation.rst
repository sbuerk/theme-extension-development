..  include:: /Includes.rst.txt

..  _feature-collapsible-sub-navigation:

=========================================
Feature: Collapsible sub navigation
=========================================

Description
===========

An entry of the sub navigation that has pages below it is a **branch** and
folds. Its link keeps the row and a toggle sits at the end of it, over the
list the toggle discloses. The branch holding the page being read is open;
every other branch starts folded.

**There is no JavaScript in it.** A branch is a native :html:`<details>` with
a :html:`<summary>`, so the browser carries the state, the keyboard handling
and the expanded and collapsed semantics a screen reader announces. Nothing is
hidden behind the theme's :html:`data-js` marker, because nothing about
folding a branch waits for a script: the tree works on a page whose JavaScript
never arrived, which is the reason it is built this way rather than on a
button and a class.

Accessibility
-------------

The branch link is **not** inside the summary. It was in the first version,
where the branch title named the disclosure control by itself and no extra
text was needed - and axe reports that as a nested interactive control, on
every appearance and palette of the styleguide. So the link and the toggle are
siblings.

The toggle is named by a visually hidden span naming its branch - "Pages below
Theme elements" - rather than by an :html:`aria-label`: subtree text is the
naming method a :html:`<summary>` has. The chevron inside it stays decoration,
:html:`aria-hidden` like every other icon of the theme, and the toggle is a
square of the minimum target size.

Impact
======

The sub navigation is rendered on the ``content_sidebar`` backend layout, so
every page using that layout whose section has pages two levels deep now shows
a foldable tree rather than a fully expanded list.

The markup of a branch changed - see :ref:`components`. A site package that
styled :css:`.theme-nav-sub__list--level-2` by its position in the list has to
follow; that list is inside the :html:`<details>` now. An entry without
children is unchanged, and so is every class it carries.

One new label ships, ``theme.navSubBranchLabel``, whose source is
``Pages below %s``.
