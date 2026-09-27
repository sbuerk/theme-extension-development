<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The inline no-flash script runs under an enforced frontend Content
 * Security Policy.
 *
 * With `security.frontend.enforceContentSecurityPolicy` on, the core's
 * frontend policy allows scripts by its nonce proxy only, and a browser
 * blocks the script of `page.headerData.10` - no `data-js`, no stored
 * appearance before first paint. `Configuration/ContentSecurityPolicies.php`
 * allows it by its sha256. What this asserts is the header a browser gets:
 * that it carries the hash of the script *as rendered*, computed from the
 * delivered markup rather than copied from the policy file, on the first
 * request and on the second, which the page cache answers.
 *
 * The testing framework gives the `pages` cache a `NullBackend`, so every
 * request would render from scratch; this class gives it the
 * `Typo3DatabaseBackend` of a real installation.
 * That the second response comes from the cache is asserted, not assumed:
 * with `FE.debug` on, TYPO3 adds `X-TYPO3-Debug-Cache` to a page served from
 * the page cache and to no other, on v13 and v14 alike.
 *
 * And that the page stays cacheable by browsers and proxies: a nonce
 * consumed for the page makes the response `private, no-store` on v13 and v14
 * - the server-side page cache still works through its substitution of the
 * nonce - which is the reason the policy uses a hash. So the header carries
 * no nonce, and the response is `max-age=…`. That last check applies to a
 * site that sends cache headers at all (`config.sendCacheHeaders`); without
 * it TYPO3 answers every page `private, no-store`, so this test site sets it.
 */
final class ContentSecurityPolicyRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected array $configurationToUseInTestInstance = [
        'SYS' => [
            'features' => [
                'security.frontend.enforceContentSecurityPolicy' => true,
            ],
            'caching' => [
                'cacheConfigurations' => [
                    'pages' => [
                        'backend' => Typo3DatabaseBackend::class,
                    ],
                ],
            ],
        ],
        'FE' => [
            // What makes TYPO3 say "X-TYPO3-Debug-Cache" on a cache hit.
            'debug' => true,
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SiteSetPageTree.csv');
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + [
                'dependencies' => [
                    'sbuerk/theme-extension-development',
                ],
            ],
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        // Cache headers the client may use, which TYPO3 sends only when asked
        // to: without "config.sendCacheHeaders" every page is "private,
        // no-store", and the assertion on "Cache-Control" below would hold
        // for a page a consumed nonce made uncacheable as well. A site's own
        // TypoScript, loaded after its sets.
        file_put_contents($this->instancePath . '/typo3conf/sites/theme/setup.typoscript', "config.sendCacheHeaders = 1\n");
        $this->setUpFrontendRootPage(1, [], [], false);
    }

    #[Test]
    public function theEnforcedPolicyAllowsTheRenderedNoFlashScriptOnEveryRequest(): void
    {
        $responses = ['the first request' => $this->render(), 'a request the page cache answers' => $this->render()];
        $this->assertSame(
            ['the first request' => false, 'a request the page cache answers' => true],
            array_map(static fn(ResponseInterface $response): bool => $response->hasHeader('X-TYPO3-Debug-Cache'), $responses),
            'The first request did not render the page, or the second did not come from the page cache.',
        );
        foreach ($responses as $request => $response) {
            $body = (string)$response->getBody();
            $matched = preg_match('#<head\b[^>]*>.*?<script>(.*?)</script>#s', $body, $script);
            $this->assertSame(1, $matched, sprintf('On %s, the head carries no inline script.', $request));
            $this->assertStringContainsString("root.setAttribute('data-js', '')", $script[1], sprintf('On %s, the first inline script of the head is not the no-flash script.', $request));
            $hash = "'sha256-" . base64_encode(hash('sha256', $script[1], true)) . "'";

            $policy = $response->getHeaderLine('Content-Security-Policy');
            $this->assertNotSame('', $policy, sprintf('On %s, the response carries no enforced policy - is the feature flag set?', $request));
            $scriptSrc = self::directive($policy, 'script-src');
            $this->assertStringContainsString(
                $hash,
                $scriptSrc,
                sprintf('On %s, "script-src" does not allow the no-flash script as rendered (%s): %s', $request, $hash, $scriptSrc),
            );
            $this->assertStringNotContainsString("'nonce-", $policy, sprintf('On %s, the policy carries a nonce, which makes the page uncacheable.', $request));
            $this->assertStringStartsWith('max-age=', $response->getHeaderLine('Cache-Control'), sprintf('On %s, the page is not cacheable: "%s".', $request, $response->getHeaderLine('Cache-Control')));
        }
    }

    private function render(): ResponseInterface
    {
        return $this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'));
    }

    private static function directive(string $policy, string $name): string
    {
        foreach (explode(';', $policy) as $directive) {
            $directive = trim($directive);
            if (str_starts_with($directive, $name . ' ')) {
                return $directive;
            }
        }

        return '';
    }
}
