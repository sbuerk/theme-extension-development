<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core13;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\ThemeSiteTrait;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * On TYPO3 v13 a page of the theme stays cacheable under an enforced frontend
 * Content Security Policy.
 *
 * A nonce consumed for a page makes the response `private, no-store` on v13
 * (Important #107062) - browsers and proxies do not cache it, while the page
 * cache of the server still works, substituting the nonce - which is the
 * reason `Configuration/ContentSecurityPolicies.php` allows the no-flash
 * script by its hash. So on the first request and on the second, which the
 * page cache answers, the header carries no nonce, and the page is one the
 * client may cache.
 * `Tests/Functional/ContentSecurityPolicyRenderingTest` holds the hash itself,
 * on both cores.
 *
 * Only on v13: on v12 the core consumes the nonce for every stylesheet and
 * script file tag, the theme's own among them, and no page with the theme is
 * cacheable under an enforced policy - see
 * `Core12/ContentSecurityPolicyNonceTest`.
 */
#[Group('not-core-12')]
final class ContentSecurityPolicyCachingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;
    use ThemeSiteTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'features' => [
                'security.frontend.enforceContentSecurityPolicy' => true,
            ],
            // The page cache of a real installation, and "X-TYPO3-Debug-Cache"
            // on a cache hit - see "ContentSecurityPolicyRenderingTest".
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
        'FE' => [
            'debug' => true,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/../Fixtures/Database/SiteSetPageTree.csv');
        $this->setUpThemeSite();
        // Cache headers the client may use, which TYPO3 sends only when asked
        // to: without "config.sendCacheHeaders" every page is "private,
        // no-store", and the assertion on "Cache-Control" below would hold
        // for a page a consumed nonce made uncacheable as well. A site's own
        // TypoScript, loaded after its sets.
        file_put_contents($this->instancePath . '/typo3conf/sites/theme/setup.typoscript', "config.sendCacheHeaders = 1\n");
    }

    #[Test]
    public function theEnforcedPolicyLeavesThePageCacheable(): void
    {
        $responses = ['the first request' => $this->render(), 'a request the page cache answers' => $this->render()];
        $this->assertSame(
            ['the first request' => false, 'a request the page cache answers' => true],
            array_map(static fn(ResponseInterface $response): bool => $response->hasHeader('X-TYPO3-Debug-Cache'), $responses),
            'The first request did not render the page, or the second did not come from the page cache.',
        );
        foreach ($responses as $request => $response) {
            $policy = $response->getHeaderLine('Content-Security-Policy');
            $this->assertNotSame('', $policy, sprintf('On %s, the response carries no enforced policy - is the feature flag set?', $request));
            $this->assertStringNotContainsString("'nonce-", $policy, sprintf('On %s, the policy carries a nonce, which makes the page uncacheable.', $request));
            $this->assertStringStartsWith('max-age=', $response->getHeaderLine('Cache-Control'), sprintf('On %s, the page is not cacheable: "%s".', $request, $response->getHeaderLine('Cache-Control')));
        }
    }

    private function render(): ResponseInterface
    {
        return $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));
    }
}
