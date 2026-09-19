<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Core12\TypoScript;

use Psr\Http\Message\ServerRequestInterface;
use SBUERK\ThemeExtensionDevelopment\TypoScript\FrontendConfigInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;

/**
 * TYPO3 v12 implementation of {@see FrontendConfigInterface}.
 *
 * v12.4 merges the global "config." with the "config." of the PAGE object in
 * "TypoScriptFrontendController::getFromCache()" and keeps the result in the
 * public "config['config']" of the controller - also when the page comes from
 * the page cache, which stores the whole "config" property. The controller is
 * the "frontend.controller" attribute of every frontend request after the
 * "TypoScriptFrontendInitialization" middleware; a request without it renders
 * no page.
 */
#[AsAlias(id: FrontendConfigInterface::class)]
final class FrontendConfig implements FrontendConfigInterface
{
    public function forRequest(ServerRequestInterface $request): ?array
    {
        $controller = $request->getAttribute('frontend.controller');
        if (!$controller instanceof TypoScriptFrontendController) {
            return null;
        }
        $config = $controller->config['config'] ?? null;

        return is_array($config) ? $config : null;
    }
}
