<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The carousel renders slides that stay reachable without a script.
 *
 * Almost everything asserted here is about what the markup promises rather
 * than about how it looks: the pattern a screen reader is told about, the
 * position of each slide within the set, the fact that the indicators are
 * links to real ids rather than tabs, and that the track itself is the one
 * thing that scrolls and carries the tab stop. Those are the properties that
 * make the element usable with JavaScript off, and every one of them is
 * invisible in a browser with the script running - which is exactly why they
 * are asserted rather than reviewed.
 *
 * Rendered through both delivery paths, like every other element: the
 * templates are the same files either way, but the path decides whether the
 * theme renders the page at all.
 */
final class CarouselRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithSlides.csv');

        // A real file for the picture of the first slide and the first tile,
        // indexed as sys_file uid 1, which the references name.
        $storage = $this->get(StorageRepository::class)
            ->createLocalStorage('fileadmin', 'fileadmin/', 'relative', 'Default storage of the test instance', true);
        GeneralUtility::mkdir_deep($this->instancePath . '/fileadmin');
        GeneralUtility::writeFile(
            $this->instancePath . '/fileadmin/slide.svg',
            '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 160 90" width="160" height="90"><rect width="160" height="90" fill="#d6dce6"/></svg>',
        );
        $this->get(StorageRepository::class)->findByUid($storage)?->getFile('slide.svg');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/SlideMedia.csv');
    }

    /**
     * @return \Generator<string, array{path: string}>
     */
    public static function deliveryPaths(): \Generator
    {
        yield 'site set' => ['path' => 'set'];
        yield 'static include' => ['path' => 'static'];
    }

    private function render(string $path): string
    {
        $this->writeSiteConfiguration(
            'theme',
            $this->buildSiteConfiguration(
                rootPageId: 1,
                base: 'https://theme.example.com/',
                websiteTitle: 'Theme',
            ) + ($path === 'set' ? ['dependencies' => ['sbuerk/theme-extension-development']] : []),
            [
                $this->buildDefaultLanguageConfiguration(
                    identifier: 'EN',
                    base: 'https://theme.example.com/',
                ),
            ],
        );
        if ($path === 'set') {
            $this->setUpFrontendRootPage(1, [], [], false);
        } else {
            $this->setUpFrontendRootPage(1, [], [
                'include_static_file' => 'EXT:theme_extension_development/Configuration/TypoScript/Static',
            ]);
        }

        $body = (string)$this->executeFrontendSubRequest(new InternalRequest('https://theme.example.com/'))->getBody();
        $this->assertStringContainsString('data-theme-page-layout=', $body);
        $this->assertStringNotContainsString('has no rendering definition', $body);

        return $body;
    }

    /**
     * One element by its uid, from its wrapper to the wrapper of the next.
     */
    private function element(string $body, int $uid): string
    {
        $matched = preg_match(
            sprintf('#<div id="c%d"[^>]*>(.*?)(?=<div id="c\d+"|</main>)#s', $uid),
            $body,
            $matches,
        );
        $this->assertSame(1, $matched, sprintf('No element c%d was rendered.', $uid));

        return $matches[0];
    }

    /**
     * The carousel says what it is, and is named: `aria-roledescription` needs
     * a role to describe, and a `section` only has one once it carries an
     * accessible name. Without the name the whole pattern announcement is
     * dropped, silently.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aCarouselIsANamedRegionThatSaysItIsACarousel(string $path): void
    {
        $body = $this->render($path);

        $this->assertStringContainsString(
            '<section class="theme-carousel" aria-roledescription="carousel" aria-label="Every caption position">',
            $this->element($body, 10),
        );
        // Without a heading the element is still named, never nameless.
        $this->assertStringContainsString(
            '<section class="theme-carousel" aria-roledescription="carousel" aria-label="Slides">',
            $this->element($body, 20),
        );
    }

    /**
     * The track is the scroll container and the list in one, and it carries
     * the tab stop: that is what lets the arrow keys move through the slides
     * with no script, and it is the axe rule `scrollable-region-focusable`.
     * Each slide says where it sits in the set, so a reader who jumped into
     * the middle of the track knows where they are.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theTrackScrollsAndEverySlideSaysWhereItSits(string $path): void
    {
        $carousel = $this->element($this->render($path), 10);

        $this->assertStringContainsString('<ul class="theme-carousel__track" tabindex="0">', $carousel);
        $this->assertSame(3, substr_count($carousel, 'class="theme-carousel__slide"'));
        $this->assertStringContainsString('<li class="theme-carousel__slide" id="c10-slide-1">', $carousel);
        $this->assertStringContainsString('aria-label="3 of 3"', $carousel);
    }

    /**
     * The track is a list of three items, and each slide is a group *inside*
     * its item.
     *
     * A `role` on the `li` would replace its implicit `listitem` role, which
     * leaves the `ul` holding a child that is not a list item - invalid, and
     * the serious axe rule `list`. The cost is the item count, which is the
     * only reason the track is a list at all, so putting the role one level in
     * keeps both properties. It was on the `li` first and the styleguide's axe
     * run caught it; this asserts it for a rendered element, which axe never
     * sees.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aTrackIsAListOfItemsAndEachSlideIsAGroupInsideIt(string $path): void
    {
        $carousel = $this->element($this->render($path), 10);

        $this->assertDoesNotMatchRegularExpression(
            '#<li class="theme-carousel__slide"[^>]*\srole=#',
            $carousel,
            'A slide carries a role on its "li", which takes the list item role away from it.',
        );
        $this->assertMatchesRegularExpression(
            '#<figure class="theme-carousel__figure"\s+role="group"\s+aria-roledescription="slide"\s+aria-label="1 of 3">#',
            $carousel,
        );
        $this->assertSame(3, substr_count($carousel, 'aria-roledescription="slide"'));
    }

    /**
     * **The indicators are links to the slides, not tabs.**
     *
     * This is the assertion the whole "works without JavaScript" claim rests
     * on: a link to a slide's id moves the reader there with no script, while
     * a `role="tab"` would promise an activation nothing implements until the
     * script runs. Asserting the absence of the tab roles is as much the point
     * as asserting the links - the defect this guards against is someone
     * "improving" the markup into the ARIA tabs pattern, which looks more
     * correct and strands every slide but one.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theIndicatorsAreLinksToTheSlidesRatherThanTabs(string $path): void
    {
        $carousel = $this->element($this->render($path), 10);

        $this->assertStringContainsString('<ol class="theme-carousel__indicators" aria-label="Go to a slide">', $carousel);
        foreach ([1, 2, 3] as $slide) {
            $this->assertStringContainsString(
                sprintf('<a class="theme-carousel__indicator" href="#c10-slide-%d">', $slide),
                $carousel,
                sprintf('The indicator of slide %d does not link the slide.', $slide),
            );
        }
        $this->assertStringContainsString('<span class="theme-carousel__indicator-label">Slide 1</span>', $carousel);

        $this->assertStringNotContainsString('role="tab"', $carousel);
        $this->assertStringNotContainsString('role="tablist"', $carousel);
        $this->assertStringNotContainsString('role="tabpanel"', $carousel);
        // Which slide is in view is not something the server can know, so it
        // states nothing about it; the script adds this once it can observe.
        $this->assertStringNotContainsString('aria-current', $carousel);
        // No slide is hidden in the markup - the whole point of not using
        // tabs. Asserted as the attribute rather than as the bare word:
        // "aria-hidden" on the chevron of a button holds that word too, so a
        // substring search would fail here whatever the slides carry, and
        // would have gone on "passing" for the wrong reason if they ever
        // stopped carrying it.
        $this->assertDoesNotMatchRegularExpression('#<li class="theme-carousel__slide"[^>]*\shidden#', $carousel);
    }

    /**
     * The two buttons are rendered even though they do nothing without the
     * script: the stylesheet hides them until it has bound the carousel, so
     * the markup carries them and the gate is one rule rather than a second
     * rendering path. Their icons come from the set.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theSlideButtonsAreRenderedAndNamed(string $path): void
    {
        $carousel = $this->element($this->render($path), 10);

        $this->assertMatchesRegularExpression(
            '#<button class="theme-carousel__control theme-carousel__control--previous"\s+type="button"\s+aria-label="Previous slide">#',
            $carousel,
        );
        $this->assertMatchesRegularExpression(
            '#<button class="theme-carousel__control theme-carousel__control--next"\s+type="button"\s+aria-label="Next slide">#',
            $carousel,
        );
        // Nothing marks the carousel as bound: only "theme.js" does that.
        $this->assertStringNotContainsString('data-theme-carousel-bound', $carousel);
        // And nothing here starts on its own.
        $this->assertStringNotContainsString('autoplay', $carousel);
        $this->assertStringNotContainsString('data-theme-carousel-interval', $carousel);
    }

    /**
     * The caption of a slide takes the modifier of its position, and a value
     * the form does not offer renders the caption below the picture - the bare
     * class - rather than a modifier nothing styles.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aCaptionTakesTheModifierOfItsPosition(string $path): void
    {
        $carousel = $this->element($this->render($path), 10);

        $this->assertStringContainsString('<figcaption class="theme-carousel__caption">', $carousel);
        $this->assertStringContainsString('<figcaption class="theme-carousel__caption theme-carousel__caption--above">', $carousel);
        $this->assertStringContainsString('<figcaption class="theme-carousel__caption theme-carousel__caption--overlay">', $carousel);

        // One level below the h2 of the element. The heading partial indents
        // what it renders, so the tag is matched on its own rather than as a
        // line of markup.
        $this->assertStringContainsString('<h3 class="theme-carousel__title">The caption below</h3>', $carousel);
        $this->assertStringContainsString('<p class="theme-carousel__text">The words sit under the picture.</p>', $carousel);

        // The picture is rendered before the caption whatever the caption
        // position says, so the reading order never follows the paint order.
        //
        // Asserted per slide rather than with one expression over the whole
        // element: the slides sit next to each other in the markup, so a
        // pattern anchored on "__media" alone matches the media of the first
        // slide and the caption of the second, and would claim something about
        // an ordering that spans two slides instead of holding within one.
        preg_match_all('#<li class="theme-carousel__slide".*?</li>#s', $carousel, $slides);
        $this->assertCount(3, $slides[0], 'The slides could not be told apart.');

        foreach ($slides[0] as $slide) {
            if (!str_contains($slide, 'theme-carousel__media')) {
                continue;
            }
            $this->assertMatchesRegularExpression(
                '#<div class="theme-carousel__media">.*?</div>\s*<figcaption class="theme-carousel__caption#s',
                $slide,
                'A slide renders its caption before its picture.',
            );
        }

        // And the slide whose caption is painted above still has it after the
        // picture in the source - the case the flip would break if it were
        // ever done by reordering the markup instead of with "order". That
        // slide carries a picture in the fixture precisely so this holds
        // something: on a slide without one there is no order to check.
        $this->assertStringContainsString('theme-carousel__caption--above', $slides[0][1]);
        $this->assertStringContainsString('theme-carousel__media', $slides[0][1]);
        $this->assertMatchesRegularExpression(
            '#<div class="theme-carousel__media">.*?</div>\s*<figcaption class="theme-carousel__caption theme-carousel__caption--above">#s',
            $slides[0][1],
        );
    }

    /**
     * An overlay caption needs a picture to lie over.
     *
     * The modifier takes the caption out of the flow, and the figure is a flex
     * box that clips what overflows it - so on a slide with no picture there
     * is nothing left to give the figure height and the caption is clipped
     * away entirely. An editor choosing "over the foot of the image" on a
     * slide they gave no image would lose the text, and neither choice is
     * unusual on its own.
     *
     * The template therefore writes the modifier only where there is a
     * picture, and the caption falls back to the bare class - the same way
     * every value this theme does not render falls back to it. Asserted on
     * the markup rather than left to the stylesheet, because that is where
     * the decision is made and where it can be seen.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function anOverlayCaptionNeedsAPictureToLieOver(string $path): void
    {
        $carousel = $this->element($this->render($path), 20);

        $this->assertStringContainsString('An overlay caption with no picture', $carousel);
        $this->assertStringNotContainsString(
            'theme-carousel__caption--overlay',
            $carousel,
            'A slide without a picture took the overlay modifier, which clips its caption away.',
        );
        $this->assertSame(2, substr_count($carousel, '<figcaption class="theme-carousel__caption">'));
        $this->assertStringNotContainsString('theme-carousel__media', $carousel);
    }

    /**
     * A slide with a link renders it as a button of the style the editor
     * picked, and a slide without one ends with its text. The stored link
     * reference never reaches the page.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aSlideRendersItsLinkAsAButton(string $path): void
    {
        $carousel = $this->element($this->render($path), 10);

        $this->assertMatchesRegularExpression('#<a [^>]*class="theme-button"[^>]*>\s*Read on\s*</a>#', $carousel);
        $this->assertSame(1, substr_count($carousel, 'theme-button'), 'A slide without a link got a button.');
        $this->assertStringNotContainsString('t3://', $carousel);
    }
}
