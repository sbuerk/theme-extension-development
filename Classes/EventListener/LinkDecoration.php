<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\EventListener;

use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\LinkHandling\LinkService;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Site\Entity\SiteLanguage;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\TypoScript\FrontendTypoScript;
use TYPO3\CMS\Frontend\ContentObject\Exception\ContentRenderingException;
use TYPO3\CMS\Frontend\Event\AfterLinkIsGeneratedEvent;
use TYPO3\CMS\Frontend\Typolink\LinkResultInterface;

/**
 * Marks a link by what it leads to: another site, a file, an email address or
 * a phone number - and says so when it opens a new window.
 *
 * A link of one of the four kinds gets "theme-link" and
 * "theme-link--external", "--download", "--mail" or "--tel", and after its
 * text an empty marker the stylesheet paints the glyph of that kind into -
 * "components/_link.scss". A link that opens a new window - whatever it leads
 * to, a page of the site included - and a link to a file also get a hint in
 * words for a screen reader, which the stylesheet hides from sight: the
 * marker is decoration, and a link that opens a window or starts a download
 * has to say so before it is followed (WCAG 3.2.5, G201).
 *
 * The kind is the type TYPO3 resolved the link to, not a guess from the
 * "href": a "t3://file" link is a file whatever its URL looks like. A URL is
 * external when its host is no host of this installation: the host of the
 * request, or of a base, a base variant or a language base of any site
 * ("SiteFinder::getAllSites()"). Another site of the same installation is
 * not "elsewhere". Both sides are compared in their ASCII form
 * ("idn_to_ascii()", UTS #46), so "müller.example" and
 * "xn--mller-kva.example" are one host, and the host of a URL is what
 * "parse_url()" finds - "https://someone@own.host/" is own,
 * "https://own.host@elsewhere.example/" is not. A variant is only a host of
 * the installation when a site configures it: "www." is not added by guess.
 *
 * This is the one place every link built from a link reference passes: an
 * "AfterLinkIsGeneratedEvent" is dispatched by "LinkFactory" for
 * "f:link.typolink" of the templates and for the "<a>" of rich text, which
 * "lib.parseFunc_RTE" hands to "typolink" as well. It is not dispatched for a
 * link a template writes itself with "href", which is why the menus, the file
 * list and the gallery are not marked.
 *
 * Only where the theme renders the page: an installation can run sites
 * without it, and the listener is registered for all of them. The TypoScript
 * of the theme switches it on, "config.tx_theme.linkDecoration", from the
 * constant "theme.linkDecoration" - see
 * "Configuration/TypoScript/LinkDecoration.typoscript".
 *
 * Stateless: everything is read from the event, its request and the sites,
 * which "SiteFinder" keeps cached itself.
 */
#[AsEventListener('theme-extension-development/link-decoration')]
final readonly class LinkDecoration
{
    private const LABELS = 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang.xlf:';

    public function __construct(
        private LanguageServiceFactory $languageServiceFactory,
        private SiteFinder $siteFinder,
    ) {}

    public function __invoke(AfterLinkIsGeneratedEvent $event): void
    {
        $request = $this->request($event);
        if ($request === null || !$this->isSwitchedOn($request)) {
            return;
        }

        $link = $event->getLinkResult();
        $class = trim((string)$link->getAttribute('class'));
        $text = (string)$link->getLinkText();
        if (preg_match('/(?:^|\s)theme-link(?:\s|$)/', $class) === 1 || str_contains($text, 'theme-link__hint')) {
            return;
        }

        $kind = $this->kind($link, $request);
        $hints = [];
        if ($kind === 'download') {
            $hints[] = 'link.hint.download';
        }
        if ($link->getTarget() === '_blank') {
            $hints[] = 'link.hint.newWindow';
        }
        if ($kind === null && $hints === []) {
            return;
        }

        if ($kind !== null) {
            $link = $link->withAttribute('class', trim($class . ' theme-link theme-link--' . $kind));
            $text .= '<span class="theme-link__marker" aria-hidden="true"></span>';
        }
        if ($hints !== []) {
            $languageService = $this->languageService($request);
            $words = array_map(
                static fn(string $hint): string => $languageService->sL(self::LABELS . $hint),
                $hints,
            );
            $text .= '<span class="theme-link__hint"> ' . htmlspecialchars(implode(' ', $words), ENT_QUOTES | ENT_HTML5) . '</span>';
        }

        $event->setLinkResult($link->withLinkText($text));
    }

    /**
     * The request the link is built for, or none - a link built outside a
     * rendering, in the backend or on the command line, is not marked.
     */
    private function request(AfterLinkIsGeneratedEvent $event): ?ServerRequestInterface
    {
        try {
            return $event->getContentObjectRenderer()->getRequest();
        } catch (ContentRenderingException) {
            return null;
        }
    }

    private function isSwitchedOn(ServerRequestInterface $request): bool
    {
        $typoScript = $request->getAttribute('frontend.typoscript');
        if (!$typoScript instanceof FrontendTypoScript) {
            return false;
        }
        try {
            $config = $typoScript->getConfigArray();
        } catch (\RuntimeException) {
            // "config." is not set up for this request - not a page rendering.
            return false;
        }

        return (string)($config['tx_theme.']['linkDecoration'] ?? '') === '1';
    }

    /**
     * @return 'external'|'download'|'mail'|'tel'|null
     */
    private function kind(LinkResultInterface $link, ServerRequestInterface $request): ?string
    {
        return match ($link->getType()) {
            LinkService::TYPE_URL => $this->isElsewhere($link->getUrl(), $request) ? 'external' : null,
            LinkService::TYPE_FILE => 'download',
            LinkService::TYPE_EMAIL => 'mail',
            LinkService::TYPE_TELEPHONE => 'tel',
            default => null,
        };
    }

    private function isElsewhere(string $url, ServerRequestInterface $request): bool
    {
        $host = $this->host($url);

        return $host !== null && !in_array($host, $this->hostsOfTheInstallation($request), true);
    }

    /**
     * The host of the request, and the host of every base, base variant,
     * language base and language base variant any site configures. A base
     * that is a path - "/", "/de/" - names no host of its own and is left out:
     * it is served on the host of its site, which is in the list already.
     *
     * @return list<string>
     */
    private function hostsOfTheInstallation(ServerRequestInterface $request): array
    {
        $bases = [(string)$request->getUri()];
        foreach ($this->siteFinder->getAllSites() as $site) {
            $configuration = $site->getConfiguration();
            array_push($bases, ...$this->basesOf($configuration));
            foreach ($configuration['languages'] ?? [] as $language) {
                if (is_array($language)) {
                    array_push($bases, ...$this->basesOf($language));
                }
            }
        }

        $hosts = [];
        foreach ($bases as $base) {
            $host = $this->host($this->withAuthority($base));
            if ($host !== null) {
                $hosts[$host] = true;
            }
        }

        return array_keys($hosts);
    }

    /**
     * The base and the base variants of a site or a site language.
     *
     * @param array<array-key, mixed> $configuration
     * @return list<string>
     */
    private function basesOf(array $configuration): array
    {
        $bases = [(string)($configuration['base'] ?? '')];
        foreach ($configuration['baseVariants'] ?? [] as $variant) {
            if (is_array($variant)) {
                $bases[] = (string)($variant['base'] ?? '');
            }
        }

        return $bases;
    }

    /**
     * A site base may be written without a scheme - "example.com/" - which
     * TYPO3 reads as a host, while "parse_url()" reads it as a path. A base
     * that is not a path and names no authority is given one.
     */
    private function withAuthority(string $base): string
    {
        if ($base === '' || str_starts_with($base, '/') || str_contains($base, '//')) {
            return $base;
        }

        return '//' . $base;
    }

    /**
     * The host of a URL in the form two hosts are compared in: lower case,
     * without a trailing dot, and in ASCII.
     */
    private function host(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            return null;
        }
        $host = mb_strtolower(rtrim($host, '.'));
        $ascii = idn_to_ascii($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);

        return is_string($ascii) && $ascii !== '' ? $ascii : $host;
    }

    private function languageService(ServerRequestInterface $request): LanguageService
    {
        $language = $request->getAttribute('language');

        return $language instanceof SiteLanguage
            ? $this->languageServiceFactory->createFromSiteLanguage($language)
            : $this->languageServiceFactory->create('default');
    }
}
