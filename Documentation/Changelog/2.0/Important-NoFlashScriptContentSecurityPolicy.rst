..  include:: /Includes.rst.txt

..  _important-no-flash-script-content-security-policy:

===================================================================
Important: The no-flash script runs under a Content Security Policy
===================================================================

Description
===========

The theme's inline head script - :typoscript:`page.headerData.10`, which sets
:html:`data-js` and applies a stored appearance, palette and outline before
first paint - was blocked by the browser on a site that enforces TYPO3's
frontend Content Security Policy
(:php:`$GLOBALS['TYPO3_CONF_VARS']['SYS']['features']['security.frontend.enforceContentSecurityPolicy']`):
the core's frontend policy allows scripts by a nonce only. The main navigation
then did not collapse, the display settings stayed hidden, and a stored dark
appearance was not applied before the first paint.

The extension now ships :file:`Configuration/ContentSecurityPolicies.php`,
which extends :html:`script-src` of the frontend scope with the sha256 hash of
the script. A hash and not a nonce: a nonce used on a page makes TYPO3 answer
that page :http:`Cache-Control: private, no-store`, so no page could be cached
by browsers or proxies any more. TYPO3's own page cache is not affected.

Impact
======

A site that enforces the frontend policy and inherits the default frontend
scope needs to do nothing. Two cases need the site's own configuration:

*   A site that **overrides** :typoscript:`page.headerData.10` allows its own
    script, by the sha256 of that script. The hash is of the text between
    :html:`<script>` and :html:`</script>` exactly as it is rendered.
*   A site whose :file:`csp.yaml` sets :yaml:`inheritDefault: false` does not
    inherit the frontend scope, and adds the hash of the theme's script to its
    own :html:`script-src`. The value is in
    :file:`Configuration/ContentSecurityPolicies.php` of the extension, and it
    changes whenever the script changes.
