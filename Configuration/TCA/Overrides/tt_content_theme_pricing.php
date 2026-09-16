<?php

declare(strict_types=1);

use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;

defined('TYPO3') or die();

// Pricing plans side by side, rendered through ".theme-pricing"
// (components/_pricing.scss). A plan is a row of the shared child table
// "tx_theme_list_item", so this element needs no table of its own - the same
// reasoning as the card group.
//
// The columns of a plan are the ones a plan actually has, relabelled for this
// relation rather than named after it:
//
//   header      the name of the plan, required - a plan without a name is a
//               column a reader cannot refer to
//   subheader   the price, required, rendered in tabular figures so the
//               prices of several plans line up digit for digit
//   meta        what the price is per - "per month", "per seat and month"
//   text        the features, one per line, rendered as ".theme-list--check"
//   highlighted the one plan singled out from the others
//
// "highlighted" is the one new column ("Overrides/tx_theme_list_item.php").
// The price is "subheader" and the period is "meta" rather than two columns of
// their own: both already mean "a short second line that goes with the header"
// and "what qualifies this row", and a "price" column would be a column only
// this one element could ever use.
//
// A plan's link is the palette "theme_link_style" of the card group - a link
// with a label, a style and an icon, rendered as a button - because a plan
// ends in an action ("Choose this plan") exactly as a card does.
//
// No "layout" and no "tx_theme_columns": the plans are laid out by how many
// there are. A pricing table is read by comparing the columns against each
// other, so the element takes the number it is given rather than offering an
// editor a column count that would leave a plan on a row of its own.
ExtensionManagementUtility::addRecordType(
    [
        'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_pricing.label',
        'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.CType.theme_pricing.description',
        'value' => 'theme_pricing',
        // An identifier the core registers itself - verified against
        // ".Build/vendor/typo3/cms-core/Resources/Public/Icons/T3Icons/icons.json".
        // An unregistered one throws (1437425804) rather than falling back.
        'icon' => 'content-store',
        'group' => 'theme',
    ],
    '
        --palette--;;headers,
        --div--;LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tab.plans,
        tx_theme_list_items,
    ',
    [
        'columnsOverrides' => [
            'tx_theme_list_items' => [
                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tt_content.tx_theme_list_items.types.theme_pricing.label',
                'config' => [
                    'overrideChildTca' => [
                        'types' => [
                            '0' => [
                                'showitem' => '
                                    header,
                                    --linebreak--,
                                    subheader,
                                    meta,
                                    --linebreak--,
                                    highlighted,
                                    --linebreak--,
                                    text,
                                    --linebreak--,
                                    --palette--;;theme_link_style,
                                ',
                            ],
                        ],
                        'columns' => [
                            // The name and the price are what a plan is; a row
                            // missing either renders a column that says
                            // nothing. "required" is a check of the form only -
                            // the template still renders a row without them.
                            'header' => [
                                'config' => ['required' => true],
                            ],
                            'subheader' => [
                                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.subheader.types.theme_pricing.label',
                                'config' => ['required' => true],
                            ],
                            'meta' => [
                                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.meta.types.theme_pricing.label',
                            ],
                            // One feature per line, so a line break is the
                            // structure of the value rather than a wrap of it -
                            // the same thing the core says about the "bodytext"
                            // of "bullets", "table" and "html" by setting
                            // "wrap" to "off" there, and the template splits
                            // this column on its line breaks in exactly that
                            // spirit. "PlainTextSeedTest" reads this flag and
                            // stops holding the lines of the value to being
                            // whole sentences.
                            'text' => [
                                'label' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.text.types.theme_pricing.label',
                                'description' => 'LLL:EXT:theme_extension_development/Resources/Private/Language/locallang_tca.xlf:tx_theme_list_item.text.types.theme_pricing.description',
                                'config' => ['wrap' => 'off'],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
);
