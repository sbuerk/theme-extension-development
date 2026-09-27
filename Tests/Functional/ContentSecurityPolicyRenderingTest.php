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
 * request and on the second, which the page cache answers. The script
 * element carries no `nonce` attribute - the pattern below matches a bare
 * `<script>` only - so on both cores it is the hash that allows it, whatever
 * the header says about a nonce.
 *
 * The testing framework gives the `pages` cache a `NullBackend`, so every
 * request would render from scratch; this class gives it the
 * `Typo3DatabaseBackend` of a real installation.
 * That the second response comes from the cache is asserted, not assumed:
 * with `FE.debug` on, TYPO3 adds `X-TYPO3-Debug-Cache` to a page served from
 * the page cache and to no other, on v12 and v13 alike.
 *
 * Whether the page stays cacheable by browsers and proxies differs between
 * the cores, and is asserted beside each: `Core13/ContentSecurityPolicyCachingTest`
 * holds the page to no nonce and a response the client may cache, which is
 * the reason the policy uses a hash. On v12 the core itself consumes the
 * nonce for the theme's stylesheet and module script, see
 * `Core12/ContentSecurityPolicyNonceTest`.
 */
final class ContentSecurityPolicyRenderingTest extends AbstractFunctionalTestCase
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
        $this->setUpThemeSite();
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
            $this->assertSame(1, $matched, sprintf('On %s, the head carries no inline script without attributes.', $request));
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
