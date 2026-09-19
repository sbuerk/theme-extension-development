<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Core12\TypoScript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Core12\TypoScript\FrontendConfig;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Frontend\Controller\TypoScriptFrontendController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Where TYPO3 v12 keeps the merged "config." of a page rendering: on the
 * controller of the "frontend.controller" request attribute. Whether the
 * listener that reads it acts is "Tests/Functional/ThemeLinkRenderingTest".
 */
#[Group('not-core-13')]
final class FrontendConfigTest extends UnitTestCase
{
    #[Test]
    public function theConfigOfTheControllerIsReturned(): void
    {
        $controller = $this->createStub(TypoScriptFrontendController::class);
        $controller->config = ['config' => ['tx_theme.' => ['linkDecoration' => '1']]];
        $request = (new ServerRequest('https://theme.example.com/'))->withAttribute('frontend.controller', $controller);

        $this->assertSame(['tx_theme.' => ['linkDecoration' => '1']], (new FrontendConfig())->forRequest($request));
    }

    #[Test]
    public function aControllerWithoutConfigReturnsNone(): void
    {
        $controller = $this->createStub(TypoScriptFrontendController::class);
        $controller->config = [];
        $request = (new ServerRequest('https://theme.example.com/'))->withAttribute('frontend.controller', $controller);

        $this->assertNull((new FrontendConfig())->forRequest($request));
    }

    #[Test]
    public function aRequestWithoutAControllerReturnsNone(): void
    {
        $this->assertNull((new FrontendConfig())->forRequest(new ServerRequest('https://theme.example.com/')));
    }
}
