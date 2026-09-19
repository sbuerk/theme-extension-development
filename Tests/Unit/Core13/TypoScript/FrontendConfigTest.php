<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\Core13\TypoScript;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\Core13\TypoScript\FrontendConfig;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\TypoScript\AST\Node\RootNode;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * Where TYPO3 v13 keeps the merged "config." of a page rendering: in the
 * "frontend.typoscript" request attribute. Whether the listener that reads it
 * acts is "Tests/Functional/ThemeLinkRenderingTest".
 */
#[Group('not-core-12')]
final class FrontendConfigTest extends UnitTestCase
{
    #[Test]
    public function theConfigArrayOfTheFrontendTypoScriptIsReturned(): void
    {
        $typoScript = new FrontendTypoScript(new RootNode(), [], [], []);
        $typoScript->setConfigArray(['tx_theme.' => ['linkDecoration' => '1']]);
        $request = (new ServerRequest('https://theme.example.com/'))->withAttribute('frontend.typoscript', $typoScript);

        $this->assertSame(['tx_theme.' => ['linkDecoration' => '1']], (new FrontendConfig())->forRequest($request));
    }

    /**
     * A "FrontendTypoScript" whose "config." was never set up throws when it is
     * read. That is a request that is not a page rendering.
     */
    #[Test]
    public function aConfigArrayNeverSetUpReturnsNone(): void
    {
        $request = (new ServerRequest('https://theme.example.com/'))
            ->withAttribute('frontend.typoscript', new FrontendTypoScript(new RootNode(), [], [], []));

        $this->assertNull((new FrontendConfig())->forRequest($request));
    }

    #[Test]
    public function aRequestWithoutFrontendTypoScriptReturnsNone(): void
    {
        $this->assertNull((new FrontendConfig())->forRequest(new ServerRequest('https://theme.example.com/')));
    }
}
