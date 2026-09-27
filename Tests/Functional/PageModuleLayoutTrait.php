<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Tests\Functional;

use TYPO3\CMS\Backend\View\BackendLayout\BackendLayout;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\Restriction\DeletedRestriction;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Resolves the backend layout of every page in the database the way the page
 * module does, and reports each page an editor would find broken there.
 *
 * The frontend cannot see this. `Page.typoscript` strips everything up to
 * `__` from the resolved value before it picks a template, so `content` and
 * `pagets__content` render the same page. The page module does not strip
 * anything: `DataProviderCollection::getBackendLayout()` hands a value
 * without `__` to the `default` provider, which looks it up as the uid of a
 * `backend_layout` record, finds none, and the page falls back to the
 * built-in one column layout - with every element outside colPos 0 listed as
 * unused. Only a value in the stored form `<provider>__<identifier>` reaches
 * the Page TSconfig provider `pagets`.
 *
 * The layout comes from `BackendLayoutView::getBackendLayoutForPage()`, the
 * call `PageLayoutController` makes on TYPO3 v13 and v14 alike. What the page
 * module lists as unused is every element no column of that layout drew
 * (`ContentFetcher::getUnusedRecords()`), so an element is reported here when
 * its colPos is not one the layout declares.
 *
 * @phpstan-require-extends AbstractFunctionalTestCase
 */
trait PageModuleLayoutTrait
{
    /**
     * @return list<string> One line per page that resolves to another layout
     *         than it declares, and per element in a column the resolved
     *         layout does not have. Empty when the page module shows every
     *         page as seeded.
     */
    private function pageModuleLayoutFailures(): array
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);

        $pageQuery = $connectionPool->getQueryBuilderForTable('pages');
        $pageQuery->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        /** @var list<array<string, mixed>> $pages */
        $pages = $pageQuery
            ->select('uid', 'backend_layout')
            ->from('pages')
            ->where($pageQuery->expr()->eq('sys_language_uid', 0))
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
        $this->assertNotSame([], $pages, 'No page was found at all - the seed was not imported.');

        // Hidden elements included: the page module shows them, and lists them
        // as unused like any other when their column is missing.
        $contentQuery = $connectionPool->getQueryBuilderForTable('tt_content');
        $contentQuery->getRestrictions()->removeAll()->add(GeneralUtility::makeInstance(DeletedRestriction::class));
        /** @var list<array<string, mixed>> $elements */
        $elements = $contentQuery
            ->select('uid', 'pid', 'colPos')
            ->from('tt_content')
            ->orderBy('uid')
            ->executeQuery()
            ->fetchAllAssociative();
        $elementsByPage = [];
        foreach ($elements as $element) {
            $elementsByPage[(int)$element['pid']][(int)$element['uid']] = (int)$element['colPos'];
        }

        $backendLayoutView = GeneralUtility::makeInstance(BackendLayoutView::class);
        $failures = [];

        foreach ($pages as $page) {
            $uid = (int)$page['uid'];
            // Declared "?BackendLayout" on v13, "BackendLayout" on v14, which
            // throws rather than return nothing.
            $layout = $backendLayoutView->getBackendLayoutForPage($uid);
            $this->assertInstanceOf(BackendLayout::class, $layout, sprintf('Page %d resolves to no backend layout at all.', $uid));

            $declared = trim((string)$page['backend_layout']);
            if ($declared !== '') {
                // The identifier a layout of the declared provider carries:
                // "pagets__content" is the Page TSconfig layout "content".
                $expected = str_contains($declared, '__') ? explode('__', $declared, 2)[1] : $declared;
                if ($layout->getIdentifier() !== $expected) {
                    $failures[] = sprintf(
                        'page %d declares "%s" and the page module shows layout "%s"',
                        $uid,
                        $declared,
                        $layout->getIdentifier(),
                    );
                }
            }

            $columns = array_map(intval(...), $layout->getColumnPositionNumbers());
            foreach ($elementsByPage[$uid] ?? [] as $element => $colPos) {
                if (!in_array($colPos, $columns, true)) {
                    $failures[] = sprintf(
                        'page %d: element %d is in colPos %d, which layout "%s" does not have (%s) - listed as unused',
                        $uid,
                        $element,
                        $colPos,
                        $layout->getIdentifier(),
                        implode(', ', $columns),
                    );
                }
            }
        }

        return $failures;
    }
}
