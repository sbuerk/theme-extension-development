<?php

declare(strict_types=1);

namespace TESTS\PluginFixture\Controller;

use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

/**
 * Controller of the "Plugin" plugin of this fixture extension.
 *
 * Extbase instantiates a controller through the DI container and hands it a
 * request-scoped view, so it cannot be "final readonly" the way the rest of
 * this codebase's classes are - "ActionController" itself carries mutable,
 * framework-set properties (view, request, response) that a readonly class
 * could not accept. It stays plain "final".
 *
 * "indexAction()" renders a fixed, easily grepped string
 * ("Resources/Private/Templates/Plugin/Index.html") rather than anything
 * derived from the request - that string is what a functional test asserts
 * is present in the response body, proof the plugin actually rendered rather
 * than falling through to the core's own "no rendering definition" notice.
 */
final class PluginController extends ActionController
{
    public function indexAction(): ResponseInterface
    {
        return $this->htmlResponse();
    }
}
