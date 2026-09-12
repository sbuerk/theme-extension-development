<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Backend\Form\FormDataCompiler;
use TYPO3\CMS\Backend\Form\FormDataGroup\TcaDatabaseRecord;
use TYPO3\CMS\Core\Core\SystemEnvironmentBuilder;
use TYPO3\CMS\Core\Http\NormalizedParams;
use TYPO3\CMS\Core\Http\ServerRequest;
use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * The editor of an inline list item offers rich text for tabs and accordion,
 * and plain text for every other relation.
 *
 * `ThemeContentElementRenderingTest` holds the TCA values. That the values
 * reach the form is a second question: `overrideChildTca` is applied by the
 * `InlineOverrideChildTca` data provider, and `TcaText` resolves the rich text
 * configuration from whatever `processedTca` holds when it runs. Nothing
 * declares one to depend on the other - `TcaText` sees the override only
 * because it is registered after it in the `tcaDatabaseRecord` group. A
 * provider order that changes, or a relation whose override stops reaching
 * the child, still carries the right TCA and renders a plain textarea.
 *
 * So this compiles the form data the way the backend does when an editor
 * opens a content element - the `tcaDatabaseRecord` group on the parent,
 * whose `TcaInline` provider compiles each inline child with the parent's
 * configuration - and reads the child's `text` field from the result.
 */
final class ListItemRichTextFormEngineTest extends AbstractFunctionalTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/AdminBackendUser.csv');
        $this->importCSVDataSet(__DIR__ . '/Fixtures/Database/PageWithThemeContentElements.csv');
        $backendUser = $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = $this->get(LanguageServiceFactory::class)->createFromUserPreferences($backendUser);
    }

    /**
     * The compiled form data of the first inline child of a content element.
     *
     * @return array<string, mixed>
     */
    private function firstInlineChild(int $contentElementUid): array
    {
        $request = (new ServerRequest('https://theme.example.com/typo3/record/edit', 'GET'))
            ->withAttribute('applicationType', SystemEnvironmentBuilder::REQUESTTYPE_BE);
        $request = $request->withAttribute('normalizedParams', NormalizedParams::createFromRequest($request));

        $result = GeneralUtility::makeInstance(FormDataCompiler::class)->compile(
            [
                'request' => $request,
                'tableName' => 'tt_content',
                'vanillaUid' => $contentElementUid,
                'command' => 'edit',
            ],
            GeneralUtility::makeInstance(TcaDatabaseRecord::class),
        );

        $children = $result['processedTca']['columns']['tx_theme_list_items']['children'] ?? [];
        $this->assertNotEmpty($children, sprintf('Content element %d compiled no inline child.', $contentElementUid));
        $this->assertSame('tx_theme_list_item', $children[0]['tableName']);

        return $children[0];
    }

    /**
     * @return \Generator<string, array{uid: int, richText: bool}>
     */
    public static function parents(): \Generator
    {
        yield 'tabs' => ['uid' => 180, 'richText' => true];
        yield 'accordion' => ['uid' => 200, 'richText' => true];
        yield 'teaser grid' => ['uid' => 100, 'richText' => false];
        yield 'link list' => ['uid' => 80, 'richText' => false];
    }

    #[DataProvider('parents')]
    #[Test]
    public function theListItemTextIsARichTextEditorOnlyUnderTabsAndAccordion(int $uid, bool $richText): void
    {
        $config = $this->firstInlineChild($uid)['processedTca']['columns']['text']['config'] ?? null;
        $this->assertIsArray($config, 'The child form has no "text" field at all.');

        // "TcaText" sets "richtextConfiguration" on exactly the fields it hands
        // to the rich text editor.
        $this->assertSame(
            $richText,
            array_key_exists('richtextConfiguration', $config),
            sprintf('The "text" field of a child of content element %d is %s.', $uid, $richText ? 'not rich text' : 'rich text'),
        );
    }
}
