<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\HashValue;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Type\Map;

/**
 * Allows the theme's inline no-flash script under an enforced frontend
 * Content Security Policy.
 *
 * The script is `page.headerData.10` in
 * `Configuration/TypoScript/Appearance.typoscript`: it sets `data-js` and the
 * stored appearance before first paint, so it has to be inline in the head.
 * The core's frontend policy (`EXT:frontend/Configuration/ContentSecurityPolicies.php`)
 * allows scripts by the nonce proxy only, and blocked it.
 *
 * A hash rather than a nonce. `page.headerData` is written as it is, with no
 * nonce attribute, and on v13 a nonce consumed for the page keeps browsers
 * and proxies from caching it - `Cache-Control: private, no-store`; the
 * server's own page cache still works, substituting the nonce. A hash of a
 * script that is the same on every page costs nothing. (On v12 the core consumes the nonce
 * for the theme's own stylesheet and module script anyway, see
 * `Tests/Functional/Core12/ContentSecurityPolicyNonceTest`.)
 *
 * The value is the base64 of the sha256 of the text between `<script>` and
 * `</script>` exactly as the page renders it - the multi-line value of
 * `page.headerData.10`, trimmed, without the tags, whitespace inside kept. Any
 * edit of the script changes it, a blank line included:
 * `Tests/Unit/ContentSecurityPolicyTest` recomputes it from the TypoScript,
 * and `Tests/Functional/ContentSecurityPolicyRenderingTest` from a rendered
 * page, and both fail on a value this file does not carry.
 *
 * This extends the frontend scope, which every site inherits by default. A
 * site whose `csp.yaml` sets `inheritDefault: false` has to add the hash
 * itself, and a site that overrides `page.headerData.10` has to hash its own
 * script - this value is of the theme's.
 */
return Map::fromEntries([
    Scope::frontend(),
    new MutationCollection(
        new Mutation(
            MutationMode::Extend,
            Directive::ScriptSrc,
            new HashValue('0SXSO0+weSJ1GT7a3JfQI/jwkUwGzQ/EI5Bep4zg5C4='),
        ),
    ),
]);
