..  include:: /Includes.rst.txt

..  _feature-login-form:

===================================
Feature: Themed frontend login form
===================================

Description
===========

The login and the logout form of :file:`EXT:felogin` are drawn with the form
components of the theme: labelled fields, a required marker, the theme's
button, and the result of a login attempt as an alert - a failed login as a
danger alert, a logout as a success alert.

The theme ships two templates, :file:`Login/Login.html` and
:file:`Login/Logout.html`, and adds their path above felogin's own
(``plugin.tx_felogin_login.view.templateRootPaths.20``). The password recovery
templates of felogin are not overridden. Every field felogin sends is kept, so
redirects, the permanent login and the request token work as before.

The form renders on both delivery paths, the site set and the static include -
see :ref:`feature-extbase-plugin-rendering` for what the latter needed.

Impact
======

The login form looks like the rest of the site's forms without configuration.
The theme's path sits at key ``20``, above felogin's own at key ``10`` - which
is also where felogin's ``templateRootPath`` constant and site setting put a
path. Templates configured that way are therefore shadowed by the theme's
:file:`Login/Login.html` and :file:`Login/Logout.html`; a site package with
templates of its own for felogin adds its path with a key higher than ``20``.
