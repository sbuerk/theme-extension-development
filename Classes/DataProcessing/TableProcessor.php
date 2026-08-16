<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\DataProcessing;

use Symfony\Component\DependencyInjection\Attribute\Autoconfigure;
use TYPO3\CMS\Core\Utility\CsvUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;
use TYPO3\CMS\Frontend\ContentObject\DataProcessorInterface;

/**
 * Shapes the "table" content element's "bodytext" into the header/body/footer
 * structure "Templates/ContentElements/Table.html" renders, honouring the five
 * "table_*" fields EXT:frontend's TCA attaches to the CType
 * ("Configuration/TCA/Overrides/240-tt_content-content_type-table.php").
 *
 * Why this exists rather than reusing a core processor:
 *
 * - "TYPO3\CMS\Frontend\DataProcessing\SplitProcessor" only splits a field on
 *   ONE delimiter into a flat list - exactly what "bullets" needs, and what
 *   this extension's "ContentElements.typoscript" configures for it directly,
 *   with no PHP of its own. "table" needs two nested levels (rows, then
 *   cells), delimiter-aware quoting so an enclosure character or the
 *   delimiter itself can appear inside a cell, and multi-line cells - none of
 *   which "SplitProcessor" attempts.
 * - "TYPO3\CMS\Frontend\DataProcessing\CommaSeparatedValueProcessor" is
 *   actually built for this exact field - its own docblock names "table"'s
 *   "bodytext" as the example - and does the row/column split with proper
 *   quoting via "TYPO3\CMS\Core\Utility\CsvUtility::csvToArray()". It falls
 *   short on two points specific to this CType: "table_delimiter" and
 *   "table_enclosure" are stored as character CODES (verified against the TCA
 *   above: 124/59/44/58/9 and 0/39/34), and there is no "chr" stdWrap
 *   property to turn a TCA value into a character in TypoScript alone - only
 *   PHP can do that decode. And once the rows exist, "table_header_position"
 *   (top row vs. left column) and "table_tfoot" (last row as a footer) still
 *   need to be applied before the template can render "thead"/"tbody"/
 *   "tfoot" without doing index arithmetic in Fluid, which the "shortcut"
 *   half of this contract already rules out as unreasonable for a two-level,
 *   configurable-delimiter split - the same reasoning applies one step
 *   further down, to slicing a header or footer row off the result.
 *
 * The character-code decode mirrors the TYPO3 backend's own pattern in
 * "TYPO3\CMS\Backend\Form\Element\TextTableElement::getTableWizard()", which
 * builds the same table wizard preview from the same stored codes: it applies
 * "chr()" to a non-zero code and falls back to a fixed default otherwise. The
 * enclosure default differs on purpose: the backend falls back to an empty
 * string for "0 = None", but PHP's "fgetcsv()" rejects an empty enclosure
 * argument with a "ValueError" ("must be a single character") on every PHP
 * version this extension supports - verified locally. "chr(0)" (NUL) is used
 * instead: a single byte "fgetcsv()" accepts, and one that will not occur in
 * text edited through the backend's textarea, so it behaves as "no enclosure"
 * in practice while never throwing.
 */
#[Autoconfigure(tags: [['name' => 'data.processor', 'identifier' => 'table']])]
final readonly class TableProcessor implements DataProcessorInterface
{
    /**
     * @param ContentObjectRenderer $cObj The data of the content element or page
     * @param array<string, mixed> $contentObjectConfiguration The configuration of Content Object
     * @param array<string, mixed> $processorConfiguration The configuration of this processor
     * @param array<string, mixed> $processedData Key/value store of processed data (e.g. to be passed to a Fluid View)
     * @return array<string, mixed> the processed data as key/value store
     */
    public function process(
        ContentObjectRenderer $cObj,
        array $contentObjectConfiguration,
        array $processorConfiguration,
        array $processedData
    ): array {
        if (isset($processorConfiguration['if.']) && !$cObj->checkIf($processorConfiguration['if.'])) {
            return $processedData;
        }

        $fieldName = (string)$cObj->stdWrapValue('fieldName', $processorConfiguration, 'bodytext');
        $targetVariableName = (string)$cObj->stdWrapValue('as', $processorConfiguration, 'table');

        $bodytext = (string)($cObj->data[$fieldName] ?? '');
        $delimiterCode = (int)($cObj->data['table_delimiter'] ?? 124);
        $enclosureCode = (int)($cObj->data['table_enclosure'] ?? 0);
        $headerPosition = (int)($cObj->data['table_header_position'] ?? 0);
        $hasFooter = (bool)($cObj->data['table_tfoot'] ?? false);
        $caption = trim((string)($cObj->data['table_caption'] ?? ''));

        // "table_delimiter" has no "0 = None" option, but a fallback is kept
        // for defensive reasons, matching the TCA default of 124 ("|").
        $delimiter = $delimiterCode > 0 ? chr($delimiterCode) : '|';
        // "table_enclosure" DOES have "0 = None" - see the class docblock for
        // why "chr(0)" rather than an empty string is used here.
        $enclosure = $enclosureCode > 0 ? chr($enclosureCode) : chr(0);

        // Rows of differing cell counts are padded to the widest row with
        // empty cells by "csvToArray()" itself, so the template can always
        // iterate every row with the same column count.
        $rows = CsvUtility::csvToArray($bodytext, $delimiter, $enclosure);

        $headerRow = null;
        if ($headerPosition === 1 && $rows !== []) {
            $headerRow = array_shift($rows);
        }

        // A footer is only taken from what is left after the header row (if
        // any) was removed. On a single-row table with both "top" header and
        // "tfoot" set, the one row becomes the header and nothing remains for
        // a footer - there is nothing left to duplicate as one.
        $footerRow = null;
        if ($hasFooter && $rows !== []) {
            $footerRow = array_pop($rows);
        }

        $processedData[$targetVariableName] = [
            'caption' => $caption,
            'headerRow' => $headerRow,
            // "table_header_position" 2 = "Left": every row's first cell
            // renders as a row header ("th scope=row") instead of a "td".
            'headerColumn' => $headerPosition === 2,
            'bodyRows' => $rows,
            'footerRow' => $footerRow,
        ];

        return $processedData;
    }
}
