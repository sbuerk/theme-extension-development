<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Core13\TypoScript;

use Psr\Http\Message\ServerRequestInterface;
use SBUERK\ThemeExtensionDevelopment\TypoScript\FrontendConfigInterface;
use Symfony\Component\DependencyInjection\Attribute\AsAlias;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;

/**
 * TYPO3 v13 implementation of {@see FrontendConfigInterface}.
 *
 * v13.4 keeps the merged "config." in the "frontend.typoscript" request
 * attribute. "FrontendTypoScript::getConfigArray()" throws a
 * "RuntimeException" when it was never set up, which is a request that
 * renders no page.
 */
#[AsAlias(id: FrontendConfigInterface::class)]
final class FrontendConfig implements FrontendConfigInterface
{
    public function forRequest(ServerRequestInterface $request): ?array
    {
        $typoScript = $request->getAttribute('frontend.typoscript');
        if (!$typoScript instanceof FrontendTypoScript) {
            return null;
        }
        try {
            return $typoScript->getConfigArray();
        } catch (\RuntimeException) {
            return null;
        }
    }
}
