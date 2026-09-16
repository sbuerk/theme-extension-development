<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * The pricing element renders its plans on `.theme-pricing`.
 *
 * The one thing here that no other element does is the feature list: the
 * child's `text` holds one feature per line, and the template splits it with
 * `f:split` on a literal line break. Fluid does not decode an XML character
 * reference in an attribute, so the obvious spelling - `separator="&#10;"` -
 * splits on nothing and renders every feature of a plan as one run-on line,
 * which looks like an editor mistake rather than a template one. That is what
 * `theFeaturesOfAPlanAreOneListItemPerLine` is for.
 *
 * Asserted through both delivery paths, as the other element suites are: the
 * templates are the same files either way, but the path decides whether the
 * theme renders the page at all.
 */
final class PricingRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithPricing.csv');
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
        // The page is the theme's, or none of the assertions below means anything.
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
     * The plans are the items of one list, in the order of the relation, and
     * each name is one level below the h2 of the element.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function thePlansAreTheItemsOfOneList(string $path): void
    {
        $pricing = $this->element($this->render($path), 10);

        $this->assertSame(1, substr_count($pricing, '<ul class="theme-pricing">'));
        $this->assertSame(3, preg_match_all('#<li class="theme-pricing__plan#', $pricing));
        $this->assertStringContainsString('<h3 class="theme-pricing__name">Starter</h3>', $pricing);
        $this->assertLessThan(
            strpos($pricing, 'Agency'),
            strpos($pricing, 'Starter'),
            'The plans are not in the order of the relation.',
        );
    }

    /**
     * The price and what it is per: the period is inside the price paragraph,
     * because it belongs to the price and is read with it.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aPlanRendersItsPriceAndThePeriodItIsFor(string $path): void
    {
        $pricing = $this->element($this->render($path), 10);

        $this->assertMatchesRegularExpression(
            '#<p class="theme-pricing__price">\s*0 €\s*<span class="theme-pricing__period">per month</span>\s*</p>#',
            $pricing,
        );
    }

    /**
     * The feature list is one item per line of the plain text column. Getting
     * the separator wrong renders one item holding every feature, which still
     * looks like a list with one entry.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function theFeaturesOfAPlanAreOneListItemPerLine(string $path): void
    {
        $pricing = $this->element($this->render($path), 10);

        $matched = preg_match_all('#<ul class="theme-list theme-list--check theme-pricing__features">(.*?)</ul>#s', $pricing, $lists);
        $this->assertSame(2, $matched, 'Exactly the two plans with features have a feature list.');

        $this->assertSame(['One site', 'Community support'], self::items($lists[1][0]));
        $this->assertSame(['Ten sites', 'Support within one working day', 'Audit log'], self::items($lists[1][1]));
    }

    /**
     * @return list<string> The text of every list item of a fragment.
     */
    private static function items(string $list): array
    {
        preg_match_all('#<li>(.*?)</li>#s', $list, $matches);

        return array_map('trim', $matches[1]);
    }

    /**
     * Exactly the plan an editor singled out carries the modifier, and it is
     * the only thing that tells it apart in the markup.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function onlyTheHighlightedPlanCarriesItsModifier(string $path): void
    {
        $pricing = $this->element($this->render($path), 10);

        $this->assertSame(1, substr_count($pricing, 'theme-pricing__plan--highlighted'));
        $this->assertMatchesRegularExpression(
            '#<li class="theme-pricing__plan theme-pricing__plan--highlighted">\s*<h3 class="theme-pricing__name">Team</h3>#',
            $pricing,
        );
    }

    /**
     * A plan is its name and whatever else the editor filled in: no empty
     * price paragraph, no empty feature list and no empty button row. The
     * third plan of the fixture has none of the three.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aPlanRendersNothingForTheFieldsItHasNoValueFor(string $path): void
    {
        $pricing = $this->element($this->render($path), 10);

        $matched = preg_match('#<li class="theme-pricing__plan">\s*<h3 class="theme-pricing__name">Agency</h3>(.*?)</li>#s', $pricing, $plan);
        $this->assertSame(1, $matched, 'The plan without a price was not rendered.');

        $this->assertStringNotContainsString('theme-pricing__price', $plan[1]);
        $this->assertStringNotContainsString('theme-pricing__features', $plan[1]);
        $this->assertStringNotContainsString('theme-pricing__actions', $plan[1]);
    }

    /**
     * The link of a plan is a button of the style the editor picked, and a
     * plan without a link has no button row at all.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function aPlanRendersItsLinkAsAButtonOfItsStyle(string $path): void
    {
        $pricing = $this->element($this->render($path), 10);

        $this->assertSame(1, substr_count($pricing, 'theme-pricing__actions'));
        $this->assertMatchesRegularExpression(
            '#<div class="theme-pricing__actions">\s*<a [^>]*class="theme-button theme-button--secondary"[^>]*>\s*Start for nothing\s*</a>#',
            $pricing,
        );
        $this->assertStringNotContainsString('t3://', $pricing);
    }

    /**
     * An element whose relation resolves to nothing renders a correct, empty
     * wrapper - never an empty list, which a screen reader announces as a list
     * of no items.
     */
    #[DataProvider('deliveryPaths')]
    #[Test]
    public function anElementWithoutPlansRendersNoList(string $path): void
    {
        $empty = $this->element($this->render($path), 20);

        $this->assertStringContainsString('data-ctype="theme_pricing"', $empty);
        $this->assertStringNotContainsString('theme-pricing', str_replace('theme_pricing', '', $empty));
    }
}
