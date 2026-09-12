..  include:: /Includes.rst.txt

..  _feature-form-showcase:

=======================
Feature: Form showcase
=======================

Description
===========

A new backend layout, :guilabel:`Form showcase`, renders a complete request
form built from the form components of the theme - fieldsets, every common
control, input groups, choice groups, a switch - followed by the same form as
it comes back after a failed submission, with an error summary linking to each
invalid field, and by the summary of a successful one.

The seeded demo tree carries it as ``/forms``, next to ``/styleguide`` and,
like it, hidden from the navigation. The start page of the tree links to both.

Like the styleguide, the page ignores content an editor places on it: the
layout offers one column, and nothing renders it.

The forms send nothing
======================

Pressing a submit button runs the browser's own validation, so a field that is
required or wrongly formatted is marked the way a real form marks it. The
submission then ends in the browser: the forms use the :html:`dialog` method
outside of a dialog, which sends no request and leaves the page where it is.

Impact
======

A site package can select the layout for a page of its own to look at its
form styling in the appearance and palette it uses.
