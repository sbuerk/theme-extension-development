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
 * ("Resources/Private/Templates/Plugin/Index.html") - proof the plugin
 * actually rendered rather than falling through to the core's own "no
 * rendering definition" notice - and, beside it, the "greeting" FlexForm
 * setting and the uid of the content element it was rendered from, which a
 * plugin only has when the theme hands the record to it.
 */
final class PluginController extends ActionController
{
    public function indexAction(): ResponseInterface
    {
        // What a plugin only has when it is rendered with its content element:
        // a FlexForm setting, and the record itself on the Extbase request.
        $this->view->assign('greeting', (string)($this->settings['greeting'] ?? ''));
        $this->view->assign('contentElementUid', (int)($this->request->getAttribute('currentContentObject')?->data['uid'] ?? 0));

        return $this->htmlResponse();
    }
}
