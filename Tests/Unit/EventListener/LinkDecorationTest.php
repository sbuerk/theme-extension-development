<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Unit\EventListener;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\ThemeExtensionDevelopment\EventListener\LinkDecoration;
use SBUERK\ThemeExtensionDevelopment\TypoScript\FrontendConfigInterface;
use TYPO3\CMS\Core\Configuration\SiteConfiguration;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;
use TYPO3\CMS\Frontend\Typolink\LinkResult;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

/**
 * When the listener marks a link, and when it leaves it alone.
 *
 * The rendering side - rich text through "lib.parseFunc_RTE", the link field
 * of an element, a file, a second site of the installation - is
 * "Tests/Functional/ThemeLinkRenderingTest". What is cheaper to hold here is
 * everything that decides whether and how a link is touched: the switch, the
 * kinds, the hosts of the installation, a link built outside a page
 * rendering, and a link that was marked already.
 *
 * The request is made on "theme.example.com". The installation has three
 * sites: the theme's, with a base variant and a language of its own host;
 * another one configured without a scheme; and one on an internationalised
 * domain configured in its ASCII form.
 */
final class LinkDecorationTest extends UnitTestCase
{
    private const MARKER = '<span class="theme-link__marker" aria-hidden="true"></span>';

    /**
     * @param array<string, mixed> $configuration
     */
    private function site(array $configuration): Site
    {
        $site = $this->createStub(Site::class);
        $site->method('getConfiguration')->willReturn($configuration);

        return $site;
    }

    /**
     * @param array<string, mixed>|null $config "config." of the request, or null for none set up
     */
    private function listener(?array $config): LinkDecoration
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('sL')->willReturnCallback(
            static fn(string $key): string => str_ends_with($key, 'newWindow') ? '(opens in a new window)' : '(download)',
        );
        $languageServiceFactory = $this->createStub(LanguageServiceFactory::class);
        $languageServiceFactory->method('create')->willReturn($languageService);
        $languageServiceFactory->method('createFromSiteLanguage')->willReturn($languageService);

        $siteConfiguration = $this->createStub(SiteConfiguration::class);
        $siteConfiguration->method('getAllExistingSites')->willReturn([
            'theme' => $this->site([
                'base' => 'https://theme.example.com/',
                'baseVariants' => [
                    ['base' => 'https://theme.ddev.site/', 'condition' => 'applicationContext == "Development"'],
                ],
                'languages' => [
                    ['languageId' => 0, 'base' => '/'],
                    ['languageId' => 1, 'base' => 'https://theme.example.de/'],
                ],
            ]),
            'other' => $this->site(['base' => 'other.example.com/']),
            'idn' => $this->site(['base' => 'https://xn--mller-kva.example/']),
        ]);
        // A real finder over the doubled configuration: v13.4 declares
        // "SiteFinder" a readonly class, which PHPUnit 10.5 cannot double, and
        // its constructor differs per core - see "Core12/Site/SiteFinderFactory".
        $siteFinderFactory = sprintf(
            '%s\\Core%d\\Site\\SiteFinderFactory',
            substr(__NAMESPACE__, 0, (int)strrpos(__NAMESPACE__, '\\')),
            (new Typo3Version())->getMajorVersion(),
        );
        if (!class_exists($siteFinderFactory)) {
            // Not an exception: a missing factory is a broken test harness,
            // and a failed assertion names the file that has to be added.
            $this->fail(sprintf('No site finder factory for the running TYPO3 version: "%s" does not exist.', $siteFinderFactory));
        }
        $siteFinder = $siteFinderFactory::create($siteConfiguration);
        $this->assertInstanceOf(SiteFinder::class, $siteFinder);

        // Reading "config." differs per core and is held by the tests of the
        // two implementations, below "Tests/Unit/Core12/" and "Core13/".
        $frontendConfig = $this->createStub(FrontendConfigInterface::class);
        $frontendConfig->method('forRequest')->willReturn($config);

        return new LinkDecoration($languageServiceFactory, $siteFinder, $frontendConfig);
    }

    /**
     * @param array<string, mixed>|null $config "config." of the request, or null for none set up
     */
    private function decorate(LinkResult $link, ?array $config = ['tx_theme.' => ['linkDecoration' => '1']], bool $withRequest = true): LinkResult
    {
        $request = new ServerRequest('https://theme.example.com/page');

        $contentObjectRenderer = $this->createStub(ContentObjectRenderer::class);
        if ($withRequest) {
            $contentObjectRenderer->method('getRequest')->willReturn($request);
        } else {
            $contentObjectRenderer->method('getRequest')->willThrowException(new ContentRenderingException('No request.', 1607172972));
        }

        $event = new AfterLinkIsGeneratedEvent($link, $contentObjectRenderer, []);
        ($this->listener($config))($event);

        $result = $event->getLinkResult();
        $this->assertInstanceOf(LinkResult::class, $result);

        return $result;
    }

    private function link(string $type, string $url, string $class = '', string $target = ''): LinkResult
    {
        $link = (new LinkResult($type, $url))->withLinkText('Label');
        if ($target !== '') {
            $link = $link->withTarget($target);
        }

        return $class === '' ? $link : $link->withAttribute('class', $class);
    }

    /**
     * @return \Generator<string, array{type: string, url: string, kind: string}>
     */
    public static function kinds(): \Generator
    {
        yield 'another host' => ['type' => 'url', 'url' => 'https://example.org/', 'kind' => 'external'];
        yield 'another host, in capitals' => ['type' => 'url', 'url' => 'HTTPS://EXAMPLE.ORG/', 'kind' => 'external'];
        yield 'an own host as userinfo in front of another' => ['type' => 'url', 'url' => 'https://theme.example.com@elsewhere.example/', 'kind' => 'external'];
        yield 'an internationalised host of no site' => ['type' => 'url', 'url' => 'https://müller.example.org/', 'kind' => 'external'];
        yield 'a www variant no site configures' => ['type' => 'url', 'url' => 'https://www.theme.example.com/', 'kind' => 'external'];
        yield 'an email address' => ['type' => 'email', 'url' => 'mailto:hello@example.org', 'kind' => 'mail'];
        yield 'a phone number' => ['type' => 'telephone', 'url' => 'tel:+4930123456', 'kind' => 'tel'];
    }

    #[DataProvider('kinds')]
    #[Test]
    public function aLinkIsMarkedByTheTypeItWasResolvedTo(string $type, string $url, string $kind): void
    {
        $result = $this->decorate($this->link($type, $url, 'theme-button'));

        $this->assertSame('theme-button theme-link theme-link--' . $kind, $result->getAttribute('class'));
        $this->assertSame('Label' . self::MARKER, $result->getLinkText());
    }

    /**
     * @return \Generator<string, array{type: string, url: string}>
     */
    public static function withinTheInstallation(): \Generator
    {
        yield 'a page' => ['type' => 'page', 'url' => '/page'];
        yield 'an anchor on the page' => ['type' => 'inpage', 'url' => '#c1'];
        yield 'a record' => ['type' => 'record', 'url' => '/news/one'];
        yield 'a folder' => ['type' => 'folder', 'url' => '/fileadmin/'];
        yield 'a relative URL' => ['type' => 'url', 'url' => '/about'];
        yield 'the host of the request' => ['type' => 'url', 'url' => 'https://theme.example.com/about'];
        yield 'the host of the request, with a trailing dot' => ['type' => 'url', 'url' => 'https://theme.example.com./about'];
        yield 'userinfo in front of the host of the request' => ['type' => 'url', 'url' => 'https://someone@theme.example.com/about'];
        yield 'a base variant of the site' => ['type' => 'url', 'url' => 'https://theme.ddev.site/about'];
        yield 'a language base of the site' => ['type' => 'url', 'url' => 'https://theme.example.de/ueber'];
        yield 'another site, configured without a scheme' => ['type' => 'url', 'url' => 'https://other.example.com/'];
        yield 'another site on an internationalised domain' => ['type' => 'url', 'url' => 'https://müller.example/kontakt'];
        yield 'the same site, written in ASCII' => ['type' => 'url', 'url' => 'https://xn--mller-kva.example/kontakt'];
    }

    #[DataProvider('withinTheInstallation')]
    #[Test]
    public function aLinkWithinTheInstallationIsLeftAlone(string $type, string $url): void
    {
        $link = $this->link($type, $url);

        $this->assertSame($link, $this->decorate($link));
    }

    /**
     * A new window is announced whatever the link leads to - a page of the
     * site too - while the class and the marker stay with the four kinds.
     */
    #[Test]
    public function aLinkOpeningANewWindowSaysSoWhateverItLeadsTo(): void
    {
        $result = $this->decorate($this->link('page', '/page', 'theme-button', '_blank'));

        $this->assertSame('theme-button', $result->getAttribute('class'));
        $this->assertSame('Label<span class="theme-link__hint"> (opens in a new window)</span>', $result->getLinkText());
    }

    #[Test]
    public function anExternalLinkOpeningANewWindowIsMarkedAndSaysSo(): void
    {
        $result = $this->decorate($this->link('url', 'https://example.org/', '', '_blank'));

        $this->assertSame('theme-link theme-link--external', $result->getAttribute('class'));
        $this->assertSame('Label' . self::MARKER . '<span class="theme-link__hint"> (opens in a new window)</span>', $result->getLinkText());
    }

    #[Test]
    public function aLinkToAFileIsMarkedAndSaysItIsADownload(): void
    {
        $result = $this->decorate($this->link('file', '/fileadmin/report.pdf'));

        $this->assertSame('theme-link theme-link--download', $result->getAttribute('class'));
        $this->assertSame('Label' . self::MARKER . '<span class="theme-link__hint"> (download)</span>', $result->getLinkText());
    }

    /**
     * @return \Generator<string, array{config: array<string, mixed>|null}>
     */
    public static function switchedOff(): \Generator
    {
        yield 'switched off' => ['config' => ['tx_theme.' => ['linkDecoration' => '0']]];
        yield 'a site without the theme' => ['config' => []];
        yield 'no "config." set up' => ['config' => null];
    }

    /**
     * Only where the theme's TypoScript switches it on: the listener is
     * registered for every site of an installation, and a site without the
     * theme gets its links as TYPO3 builds them.
     *
     * @param array<string, mixed>|null $config
     */
    #[DataProvider('switchedOff')]
    #[Test]
    public function aLinkIsLeftAloneWhereTheThemeDoesNotSwitchItOn(?array $config): void
    {
        $link = $this->link('url', 'https://example.org/', '', '_blank');

        $this->assertSame($link, $this->decorate($link, $config));
    }

    /**
     * A content object renderer without a request throws when it is asked for
     * one. That is a link built outside a page rendering, and it must not
     * break the link.
     */
    #[Test]
    public function aLinkBuiltWithoutARequestIsLeftAlone(): void
    {
        $link = $this->link('url', 'https://example.org/');

        $this->assertSame($link, $this->decorate($link, withRequest: false));
    }

    #[Test]
    public function aLinkIsMarkedOnceOnly(): void
    {
        $marked = $this->link('url', 'https://example.org/', 'theme-link theme-link--external');
        $announced = $this->link('page', '/page', '', '_blank')
            ->withLinkText('Label<span class="theme-link__hint"> (opens in a new window)</span>');

        $this->assertSame($marked, $this->decorate($marked));
        $this->assertSame($announced, $this->decorate($announced));
    }
}
