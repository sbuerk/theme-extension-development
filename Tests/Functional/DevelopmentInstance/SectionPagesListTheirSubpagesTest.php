<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional\DevelopmentInstance;

use PHPUnit\Framework\Attributes\Test;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Every section of the header menu lists its own pages.
 *
 * Without a script, the header hides the second level of its main navigation
 * at every width below the breakpoint of its arrangement - the stacked menu
 * with every second level open was about 1730 pixels of header from 768
 * pixels up, and 2523 at 375. What
 * keeps those pages reachable is the section page: a top level entry links
 * to it, and it links every page of its second level. That is a property of
 * the content, not of the stylesheet, and it did not hold until the seed gave
 * Typography and Elements a menu of their subpages - Typography linked none
 * of its six, Elements three of its five only through the header.
 *
 * So for both trees of the development instance, every top level entry of
 * the page header that has a second level is followed to its page, and every
 * link of its second level has to be on that page outside the header - in
 * the content, a sub navigation or the footer. An entry with a second level
 * but no link of its own - a spacer, which the menu renders as a `span` - is
 * a failure: without a script nothing leads to its pages at all.
 */
final class SectionPagesListTheirSubpagesTest extends AbstractInstanceSeedTestCase
{
    private const BASE = 'https://theme.example.com';

    protected function setUp(): void
    {
        parent::setUp();

        $this->importSeedSetOncePerClass(self::SEED_SET);
        $this->adoptCommittedSiteConfigurations();
    }

    #[Test]
    public function everySectionOfTheHeaderMenuLinksEveryPageOfItsSecondLevel(): void
    {
        $sections = 0;
        $missing = [];

        foreach (['/', '/legacy/'] as $root) {
            foreach ($this->sectionsOfTheHeaderMenu($this->render($root)) as $section => $subpages) {
                $sections++;
                $linked = $this->linksOutsideTheHeader($this->render($section));
                foreach ($subpages as $subpage => $title) {
                    if (!isset($linked[$subpage])) {
                        $missing[] = sprintf('%s does not link "%s" (%s)', $section, $title, $subpage);
                    }
                }
            }
        }

        // Layouts and Examples listed their pages before, Typography and
        // Elements do since the seed says so - four per tree. Fewer means the
        // header lost its second levels or this test lost the header.
        $this->assertGreaterThanOrEqual(8, $sections, 'Fewer sections with a second level than the showcase has.');
        $this->assertSame([], $missing, 'A section page does not link every page the header lists under it.');
    }

    private function render(string $path): string
    {
        $response = $this->executeFrontendSubRequest(new InternalRequest(self::BASE . $path));
        $this->assertSame(200, $response->getStatusCode(), sprintf('"%s" does not render.', $path));

        return (string)$response->getBody();
    }

    private function xpath(string $markup): \DOMXPath
    {
        $document = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML($markup);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($document);
    }

    /**
     * The top level entries of the page header's main navigation that have a
     * second level, as `section path => [subpage path => title]`.
     *
     * @return array<string, array<string, string>>
     */
    private function sectionsOfTheHeaderMenu(string $markup): array
    {
        $xpath = $this->xpath($markup);
        $header = '//div[contains(concat(" ", normalize-space(@class), " "), " theme-page ")]'
            . '/header[contains(concat(" ", normalize-space(@class), " "), " theme-site-header ")]';
        $items = $xpath->query($header . '//nav[contains(concat(" ", normalize-space(@class), " "), " theme-nav-main ")]/ul/li');
        $this->assertNotFalse($items);
        $this->assertGreaterThan(0, $items->length, 'The page header has no main navigation.');

        $sections = [];
        foreach ($items as $item) {
            $this->assertInstanceOf(\DOMElement::class, $item);
            $link = $xpath->query('./a', $item);
            $subpages = $xpath->query('./ul/li/a', $item);
            $this->assertNotFalse($link);
            $this->assertNotFalse($subpages);
            if ($subpages->length === 0) {
                continue;
            }
            $this->assertGreaterThan(
                0,
                $link->length,
                sprintf('The header entry "%s" has a second level but no link of its own - without a script its pages cannot be reached.', trim((string)$xpath->evaluate('string(./*[1])', $item))),
            );
            $section = $link->item(0);
            $this->assertInstanceOf(\DOMElement::class, $section);
            foreach ($subpages as $subpage) {
                $this->assertInstanceOf(\DOMElement::class, $subpage);
                $sections[$section->getAttribute('href')][$subpage->getAttribute('href')] = trim($subpage->textContent);
            }
        }

        return $sections;
    }

    /**
     * Every link target of a page outside the page header, keyed by itself.
     *
     * @return array<string, true>
     */
    private function linksOutsideTheHeader(string $markup): array
    {
        $xpath = $this->xpath($markup);
        $links = $xpath->query('//a[@href][not(ancestor::header[contains(concat(" ", normalize-space(@class), " "), " theme-site-header ")])]');
        $this->assertNotFalse($links);

        $targets = [];
        foreach ($links as $link) {
            $this->assertInstanceOf(\DOMElement::class, $link);
            $targets[$link->getAttribute('href')] = true;
        }

        return $targets;
    }
}
