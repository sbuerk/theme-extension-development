..  include:: /Includes.rst.txt

..  _feature-testimonial-portrait:

=======================================
Feature: A portrait for the testimonial
=======================================

Description
===========

The :guilabel:`Testimonial` content element has an :guilabel:`Images` tab
again, with one field, :guilabel:`Portrait`, for one image of the person
quoted. The portrait is shown as a round avatar at the start of the
attribution, before the name.

The portrait appears only when the element has a name: next to the name it is
decoration, and a screen reader reads the name once. An element without a
name shows no portrait.

Impact
======

The attribution of a testimonial with a portrait starts with
:html:`<span class="theme-avatar theme-avatar--large theme-quote__portrait">`,
holding the image with an empty alternative text. The alternative text of the
file reference is not used there.
