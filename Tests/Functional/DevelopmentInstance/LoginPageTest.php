<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequestContext;

/**
 * The login page of a development instance, through the committed "demo"
 * site configuration.
 *
 * The form is an EXT:felogin plugin, and felogin registers its TypoScript for
 * sys_template sites only. The "demo" site delivers it by depending on the set
 * "typo3/felogin"; without that dependency the plugin runs with no
 * configuration at all - which still renders a form, and on TYPO3 v13 turns
 * the logout form of a logged in user into an error, because its controller
 * reads "settings.redirectPageLogout" without a fallback. The second test is
 * the one that tells the two apart: rendered without the set dependency, it
 * raises that PHP warning, and this suite fails on a warning.
 */
final class LoginPageTest extends AbstractInstanceSeedTestCase
{
    private const URL = 'https://theme.example.com/login';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSeedSet(self::SEED_SET);
        $this->adoptCommittedSiteConfigurations();
    }

    #[Test]
    public function aVisitorGetsTheLoginFormForTheSeededUsers(): void
    {
        $body = (string)$this->executeFrontendSubRequest(new InternalRequest(self::URL))->getBody();

        $this->assertStringContainsString('name="user"', $body);
        $this->assertStringContainsString('name="logintype" value="login"', $body);
        // The folder of the frontend users, from the FlexForm - felogin's own
        // default is 0. The form carries it in the payload of its signed
        // request token, which is where the login reads it back from.
        $this->assertSame(1, preg_match('#name="__RequestToken" value="[^".]+\.([^".]+)\.#', $body, $token));
        $payload = json_decode((string)base64_decode(strtr($token[1], '-_', '+/'), true), true);
        $this->assertSame('20', $payload['params']['pid'] ?? null);
    }

    #[Test]
    public function aLoggedInMemberGetsTheLogoutForm(): void
    {
        $body = (string)$this->executeFrontendSubRequest(
            new InternalRequest(self::URL),
            (new InternalRequestContext())->withFrontendUserId(1),
        )->getBody();

        $this->assertStringNotContainsString('Oops, an error occurred', $body);
        $this->assertStringContainsString('name="logintype" value="logout"', $body);
    }
}
