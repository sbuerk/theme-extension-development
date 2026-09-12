<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use SBUERK\TYPO3\Testing\SiteHandling\SiteBasedTestTrait;
use TYPO3\TestingFramework\Core\Functional\Framework\Frontend\InternalRequest;

/**
 * Holds the form showcase to the form contract, and to sending nothing.
 *
 * The page is literal markup, rendered from the `forms` backend layout the way
 * the styleguide is rendered from its own: nothing on it depends on a record,
 * and nothing an editor places on the page may reach it. What can go wrong
 * silently is the same as on the styleguide - a component the page stops
 * using, an id used twice, a `for` or `aria-describedby` pointing at nothing -
 * plus one of its own: a form that looks like a demonstration and posts
 * somewhere.
 */
final class FormShowcaseRenderingTest extends AbstractFunctionalTestCase
{
    use SiteBasedTestTrait;

    protected const LANGUAGE_PRESETS = [
        'EN' => ['id' => 0, 'title' => 'English', 'locale' => 'en_US.UTF8'],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/FormShowcasePage.csv');
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
        $this->setUpFrontendRootPage(1, [], [], false);
    }

    private function render(): string
    {
        return (string)$this->executeFrontendSubRequest(
            new InternalRequest('https://theme.example.com/forms'),
        )->getBody();
    }

    /**
     * The main column of the page, where the showcase is - the page chrome
     * carries a form control of its own (the display settings), and a sweep of
     * the whole document would be satisfied by it.
     */
    private function main(string $body): string
    {
        $matched = preg_match('#<main\b[^>]*>(.*)</main>#s', $body, $matches);
        $this->assertSame(1, $matched, 'No main column was rendered.');

        return $matches[1];
    }

    #[Test]
    public function thePageRendersThroughTheFormsLayout(): void
    {
        $this->assertStringContainsString('data-theme-page-layout="forms"', $this->render());
    }

    /**
     * The layout offers only the inert `colPos 999`, and a page switched to it
     * from another layout still carries its `colPos 0` content. Neither may
     * reach the frontend.
     */
    #[Test]
    public function contentPlacedOnTheFormShowcasePageIsNotRendered(): void
    {
        $body = $this->render();

        $this->assertStringNotContainsString('Editor content in the unused column', $body);
        $this->assertStringNotContainsString('Editor content left over in the main column', $body);
    }

    /**
     * Every form on the page ends its submission in the browser: method
     * "dialog" outside a dialog, no "action". A form with a real method, or any
     * "action" at all, would send the values somewhere - which the copy of the
     * page promises it does not.
     */
    #[Test]
    public function noFormOnThePageSendsAnything(): void
    {
        preg_match_all('#<form\b[^>]*>#', $this->main($this->render()), $forms);
        $this->assertCount(2, $forms[0], 'The showcase renders two forms, the pristine and the returned one.');

        foreach ($forms[0] as $form) {
            $this->assertStringContainsString('method="dialog"', $form);
            $this->assertStringNotContainsString('action=', $form);
        }
    }

    /**
     * @return \Generator<string, array{class: string}>
     */
    public static function formContractClasses(): \Generator
    {
        foreach ([
            'theme-form', 'theme-fieldset', 'theme-fieldset__legend',
            'theme-field', 'theme-field--inline', 'theme-field__label', 'theme-field__required', 'theme-field__hint',
            'theme-input', 'theme-textarea', 'theme-select', 'theme-check', 'theme-switch',
            'theme-input-group', 'theme-input-group__addon',
            'theme-choice-group', 'theme-choice-group--inline',
            'theme-field--invalid', 'theme-field__error', 'theme-field--valid', 'theme-field__success',
            'theme-form-summary', 'theme-form-summary--error', 'theme-form-summary--success',
            'theme-button',
        ] as $class) {
            yield $class => ['class' => $class];
        }
    }

    /**
     * Every part of the form contract is used by the showcase, in its own
     * main column - the page exists to show them composed.
     */
    #[DataProvider('formContractClasses')]
    #[Test]
    public function theShowcaseUsesEveryPartOfTheFormContract(string $class): void
    {
        $this->assertMatchesRegularExpression(
            sprintf('#class="(?:[^"]* )?%s(?: [^"]*)?"#', preg_quote($class, '#')),
            $this->main($this->render()),
            sprintf('The form showcase does not use "%s".', $class),
        );
    }

    /**
     * @return \Generator<string, array{type: string}>
     */
    public static function controlTypes(): \Generator
    {
        foreach (['text', 'email', 'tel', 'number', 'date', 'color', 'range', 'file', 'password', 'radio', 'checkbox'] as $type) {
            yield $type => ['type' => $type];
        }
    }

    #[DataProvider('controlTypes')]
    #[Test]
    public function theShowcaseUsesEveryKindOfControl(string $type): void
    {
        $this->assertStringContainsString(sprintf('type="%s"', $type), $this->main($this->render()));
    }

    /**
     * The switch is announced as a switch only through its role.
     */
    #[Test]
    public function theSwitchCarriesTheSwitchRole(): void
    {
        $this->assertMatchesRegularExpression(
            '#<label class="theme-switch">\s*<input type="checkbox" role="switch"#',
            $this->main($this->render()),
        );
    }

    /**
     * Two forms of the same fields on one page, with the page chrome around
     * them: a repeated id breaks every label and description pointing at it,
     * and the page still looks correct.
     */
    #[Test]
    public function everyIdOnThePageIsUnique(): void
    {
        preg_match_all('#\bid="([^"]+)"#', $this->render(), $ids);

        $duplicates = array_values(array_unique(array_diff_assoc($ids[1], array_unique($ids[1]))));
        sort($duplicates);

        $this->assertSame([], $duplicates, 'These ids appear more than once: ' . implode(', ', $duplicates));
    }

    /**
     * Every `for`, `aria-describedby`, `aria-labelledby` and every link of the
     * error summary names an id that exists.
     */
    #[Test]
    public function everyIdReferenceOnThePageResolves(): void
    {
        $body = $this->render();

        preg_match_all('#\sid="([^"]+)"#', $body, $ids);
        preg_match_all('#\s(?:for|aria-controls|aria-labelledby|aria-describedby)="([^"]+)"#', $body, $references);
        preg_match_all('#\shref="\#([^"]+)"#', $this->main($body), $anchors);
        $this->assertNotEmpty($references[1], 'No id reference was rendered at all.');
        $this->assertNotEmpty($anchors[1], 'The error summary links to no field.');

        $referenced = $anchors[1];
        foreach ($references[1] as $value) {
            $referenced = [...$referenced, ...(preg_split('/\s+/', trim($value)) ?: [])];
        }

        $missing = array_values(array_unique(array_diff($referenced, $ids[1])));
        sort($missing);

        $this->assertSame([], $missing, 'These ids are referenced, but no element carries them: ' . implode(', ', $missing));
    }

    /**
     * An invalid field says so to a screen reader, not only in colour: every
     * control of a `--invalid` field carries `aria-invalid` and is described
     * by its error message.
     */
    #[Test]
    public function everyInvalidFieldIsInvalidToAssistiveTechnologyAsWell(): void
    {
        preg_match_all(
            '#<div class="theme-field theme-field--invalid">(.*?)<p class="theme-field__error" id="([^"]+)"#s',
            $this->main($this->render()),
            $fields,
            PREG_SET_ORDER,
        );
        $this->assertNotEmpty($fields, 'No invalid field was rendered.');

        foreach ($fields as [, $markup, $errorId]) {
            $this->assertStringContainsString('aria-invalid="true"', $markup);
            $this->assertMatchesRegularExpression(
                sprintf('#aria-describedby="[^"]*\b%s\b#', preg_quote($errorId, '#')),
                $markup,
                sprintf('The control is not described by its error "%s".', $errorId),
            );
        }
    }
}
