<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Directive;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\HashValue;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Mutation;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationCollection;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\MutationMode;
use TYPO3\CMS\Core\Security\ContentSecurityPolicy\Scope;
use TYPO3\CMS\Core\Type\Map;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * The hash the extension's frontend policy allows is the hash of the inline
 * no-flash script the TypoScript renders.
 *
 * The script is `page.headerData.10` in `Appearance.typoscript`, and
 * `Configuration/ContentSecurityPolicies.php` allows it by its sha256. The two
 * are one change apart: any edit of the script - a word, a blank line, the
 * indentation - changes the hash, and the browser then blocks the script under
 * an enforced policy without a word in any log the site owner reads. This
 * recomputes the hash from the TypoScript by the rule TYPO3 renders it with -
 * the multi-line value, trimmed, without the `<script>` tags, whitespace inside
 * kept - so the mismatch fails here, before anything is rendered.
 * `Tests/Functional/ContentSecurityPolicyRenderingTest` checks the same
 * against a rendered page.
 */
final class ContentSecurityPolicyTest extends UnitTestCase
{
    private const TYPOSCRIPT = 'Configuration/TypoScript/Appearance.typoscript';

    private const POLICY = 'Configuration/ContentSecurityPolicies.php';

    #[Test]
    public function theFrontendPolicyAllowsTheHashOfTheNoFlashScript(): void
    {
        $root = dirname(__DIR__, 2);

        $typoscript = (string)file_get_contents($root . '/' . self::TYPOSCRIPT);
        // Any line ending: a checkout with CRLF still finds the script, and
        // then hashes the bytes it holds, which is what TYPO3 would send.
        $matched = preg_match('/^page\.headerData\.10 \{\r?\n    value \(\r?\n(.*?)\r?\n    \)\r?\n\}/ms', $typoscript, $value);
        $this->assertSame(1, $matched, sprintf('"%s" no longer declares "page.headerData.10" as a multi-line value.', self::TYPOSCRIPT));
        $matched = preg_match('#^<script>(.*)</script>$#s', trim($value[1]), $script);
        $this->assertSame(1, $matched, sprintf('"page.headerData.10" of "%s" is no longer one script element.', self::TYPOSCRIPT));
        $expected = (string)HashValue::hash($script[1]);

        /** @var Map<Scope, MutationCollection> $policy */
        $policy = require $root . '/' . self::POLICY;
        $this->assertInstanceOf(Map::class, $policy);
        $allowed = [];
        foreach ($policy as $scope => $collection) {
            $this->assertInstanceOf(MutationCollection::class, $collection);
            foreach ($collection->mutations as $mutation) {
                $this->assertInstanceOf(Mutation::class, $mutation);
                if ((string)$scope === (string)Scope::frontend() && $mutation->mode === MutationMode::Extend && $mutation->directive === Directive::ScriptSrc) {
                    foreach ($mutation->sources as $source) {
                        if ($source instanceof HashValue) {
                            $allowed[] = (string)$source;
                        }
                    }
                }
            }
        }

        $this->assertSame(
            [$expected],
            $allowed,
            sprintf(
                'The script of "page.headerData.10" in "%s" hashes to %s, and "%s" allows %s in the frontend script-src. Put the hash of the script into the policy.',
                self::TYPOSCRIPT,
                $expected,
                self::POLICY,
                $allowed === [] ? 'no hash' : implode(', ', $allowed),
            ),
        );
    }
}
