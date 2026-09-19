<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\TypoScript;

use Psr\Http\Message\ServerRequestInterface;

/**
 * The TypoScript "config." of the page a frontend request renders.
 *
 * What a consumer gets is the same on every supported core: the global
 * "config." merged with the "config." of the PAGE object of the requested
 * type - the array TYPO3 itself configures the page rendering from. Where it
 * is read from is not. TYPO3 v13 keeps it in the "frontend.typoscript"
 * request attribute, "FrontendTypoScript::getConfigArray()". v12.4 has no
 * such method - its "FrontendTypoScript" carries the whole setup array only,
 * without the merge - and keeps the merged array on the controller, in
 * "config['config']" of the "frontend.controller" request attribute
 * ("TypoScriptFrontendController::getFromCache()" of 12.4.45 merges it). So
 * there is one implementation per core, below "Core12/" and "Core13/", and
 * the implementation of the running core is the alias of this interface.
 *
 * @todo Drop the interface and both implementations with TYPO3 v12, and read
 *       "FrontendTypoScript::getConfigArray()" where this is used.
 */
interface FrontendConfigInterface
{
    /**
     * The merged "config." of the page the request renders, or null when the
     * request renders no page or has not set it up.
     *
     * @return array<array-key, mixed>|null
     */
    public function forRequest(ServerRequestInterface $request): ?array;
}
