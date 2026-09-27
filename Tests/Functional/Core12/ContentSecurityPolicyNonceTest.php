<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\Core12;

use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\AbstractFunctionalTestCase;
use SBUERK\ThemeExtensionDevelopment\Tests\Functional\ThemeSiteTrait;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Cache\Backend\Typo3DatabaseBackend;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * On TYPO3 v12 the core draws a nonce for the theme's own file tags under an
 * enforced frontend Content Security Policy, and the page is not cacheable.
 *
 * `PageRenderer` of v12 gives every stylesheet link and every script file tag
 * a `nonce` attribute whenever a nonce is set, which the frontend does when a
 * policy is enforced (`renderCssFiles()` and `renderJavaScriptFiles()`). The
 * theme includes its stylesheet with `page.includeCSS` and `theme.js` with
 * `page.includeJSFooter`, so the nonce is consumed on every page, the request
 * handler registers it as an uncached substitution, and the page is answered
 * `private, no-store` whatever `config.sendCacheHeaders` says - on the first
 * request and on the second, which the page cache answers with a fresh
 * nonce substituted. v13 no longer
 * does that - see `Core13/ContentSecurityPolicyCachingTest`.
 *
 * So on v12 the hash buys no cacheability. It is still what runs the no-flash
 * script: `page.headerData` is written as it is, the script element carries no
 * nonce, and the nonce in the header cannot allow it.
 * `Tests/Functional/ContentSecurityPolicyRenderingTest` holds the hash, on
 * both cores; this holds the v12 half of the reasoning, so that a change of
 * the core's behaviour shows up here rather than in the documentation only.
 */
#[Group('not-core-13')]
final class ContentSecurityPolicyNonceTest extends AbstractFunctionalTestCase
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
        // Cache headers the client may use, sent only when asked to - without
        // them every page is "private, no-store", and the assertion below
        // would not tell the nonce from the missing option. The setup of the
        // root "sys_template" record, which the v12 delivery writes, after
        // its static include.
        $this->getConnectionPool()->getConnectionForTable('sys_template')->update(
            'sys_template',
            ['config' => "config.sendCacheHeaders = 1\n"],
            ['pid' => 1],
        );
    }

    #[Test]
    public function theCoreDrawsANonceForTheFileTagsAndThePageIsNotCacheable(): void
    {
        $responses = ['the first request' => $this->render(), 'a request the page cache answers' => $this->render()];
        $this->assertSame(
            ['the first request' => false, 'a request the page cache answers' => true],
            array_map(static fn(ResponseInterface $response): bool => $response->hasHeader('X-TYPO3-Debug-Cache'), $responses),
            'The first request did not render the page, or the second did not come from the page cache.',
        );
        foreach ($responses as $request => $response) {
            $body = (string)$response->getBody();
            $matched = preg_match("#'nonce-([^']+)'#", self::directive($response->getHeaderLine('Content-Security-Policy'), 'script-src'), $nonce);
            $this->assertSame(1, $matched, sprintf('On %s, "script-src" carries no nonce.', $request));

            foreach (['stylesheet' => '#<link\b[^>]*theme\.css[^>]*>#', 'module script' => '#<script\b[^>]*theme\.js[^>]*>#'] as $tag => $pattern) {
                $this->assertSame(1, preg_match($pattern, $body, $element), sprintf('On %s, the page has no %s tag of the theme.', $request, $tag));
                $this->assertStringContainsString(
                    'nonce="' . $nonce[1] . '"',
                    $element[0],
                    sprintf('On %s, the %s tag of the theme does not carry the nonce of the header: %s', $request, $tag, $element[0]),
                );
            }

            $this->assertSame(1, preg_match('#<head\b[^>]*>.*?<script>(.*?)</script>#s', $body, $script), sprintf('On %s, the head carries no inline script without attributes.', $request));
            $this->assertStringContainsString("root.setAttribute('data-js', '')", $script[1], sprintf('On %s, the first inline script of the head is not the no-flash script.', $request));
            $this->assertSame('private, no-store', $response->getHeaderLine('Cache-Control'), sprintf('On %s, the page is cacheable after all.', $request));
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
