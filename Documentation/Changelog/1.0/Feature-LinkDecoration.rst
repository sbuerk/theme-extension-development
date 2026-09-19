..  include:: /Includes.rst.txt

..  _feature-link-decoration:

===================================================
Feature: External, download, mail and tel links
===================================================

Description
===========

A link TYPO3 builds from a link reference is marked by what it leads to:

*   another site - :css:`theme-link--external`, the glyph
    :html:`arrow-up-right-from-square`,
*   a file - :css:`theme-link--download`, :html:`download`,
*   an email address - :css:`theme-link--mail`, :html:`envelope`,
*   a phone number - :css:`theme-link--tel`, :html:`phone`.

The glyph follows the text of the link. It is decoration and hidden from
screen readers. A link that opens a new window - whatever it leads to, a page
of the site included - and a link to a file also carry a hint in words,
"(opens in a new window)" and "(download)", which screen readers read out and
which is hidden from sight. The download hint is added to every file link, and
repeats a label that already says "download".

This covers links in rich text and the link fields of the theme's content
elements: both are built by TYPO3's typolink. Links a template writes with a
plain :html:`href` - the menus, the file list of :guilabel:`File Links`, the
image gallery - are not marked.

A URL counts as another site when its host is no host of the installation:
neither the host of the page nor a base, base variant or language base of any
site. A link to another site of the same installation is not marked. A
:html:`www.` host counts as the same site only where a site configures it.

The marking is switched by a constant, on by default:

..  code-block:: typoscript

    theme.linkDecoration = 0

Impact
======

A marked link carries the classes :css:`theme-link` and one of the four
modifiers, and after its text a :html:`<span class="theme-link__marker">`; a
link with a hint carries a :html:`<span class="theme-link__hint">`. A site
package that styles links by their :html:`href` may now show two markers;
switch one off.

The glyph is painted by the stylesheet as a mask of the icon file, so a site
package changes or removes it per kind in its own CSS without touching PHP.

The marking only happens on sites that render with the theme: the setup of the
theme sets :typoscript:`config.tx_theme.linkDecoration`, and a page without it
is left alone.
