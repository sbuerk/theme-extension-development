<?php

declare(strict_types=1);

namespace SBUERK\ThemeExtensionDevelopment\Compatibility;

use Symfony\Component\DependencyInjection\Attribute\Exclude;
use TYPO3\CMS\Core\Schema\Struct\SelectItem;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Registers a content type in `tt_content`, on TYPO3 v12 as well as on v13.
 *
 * `ExtensionManagementUtility::addRecordType()` is the v13 way to register a
 * record type in one call. It does not exist on TYPO3 v12.4: that version's
 * `ExtensionManagementUtility` offers `addTcaSelectItem()`,
 * `addTcaSelectItemGroup()`, `addPlugin()` and `addStaticFile()` and nothing
 * that writes a `types` definition. The method carries the exception code
 * `1725997543` in v13, which dates it to September 2024, i.e. v13.4, and no
 * changelog entry ships for it at all.
 *
 * This class is the single place where that difference is resolved, so the ten
 * `Configuration/TCA/Overrides/tt_content_theme_*.php` files keep exactly one
 * registration call each.
 *
 * ## Why this is in `Classes/` and not split into `Core12/` / `Core13/`
 *
 * Version differences in this extension are resolved by splitting a class per
 * core version and letting the dependency injection container register only the
 * matching directory (see `docs/architecture/core-version-aware-code.md`). That
 * mechanism is not available here: TCA files are plain PHP includes evaluated
 * while the TCA is being built, long before a container exists, so they cannot
 * receive an injected implementation and can only call a static method. The
 * selector would have to be `Typo3Version` in shared code — which is exactly
 * what the split exists to avoid.
 *
 * Configuration is the documented exception to that rule, and this class is
 * part of the configuration: it exists only to be called from TCA files.
 *
 * ## Why there is no version switch in here either
 *
 * The obvious shape would be "delegate to `addRecordType()` on v13, do it by
 * hand on v12". That is not what this does, for two reasons.
 *
 * The first is mechanical and was measured, not assumed: PHPStan analyses the
 * shared `Classes/` directory against *both* installed dependency sets, and on
 * the v12 set `Build/Scripts/runTests.sh -t 12 -p 8.1 -s phpstan` reports
 * "Call to an undefined static method
 * TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addRecordType()" for the
 * delegating branch. The only ways to keep the delegation would be a baseline
 * entry — a growing baseline is a defect here — or a dynamic method name, which
 * is hiding a real finding from a static analyser rather than answering it.
 *
 * The second is the repository's own rule that a spelling valid on every
 * supported version beats a switch. Everything v13's `addRecordType()` does is
 * expressible with API that exists unchanged on both versions:
 * `Core\Schema\Struct\SelectItem` is present in v12.4 (it backs `addPlugin()`
 * there), and `addTcaSelectItem()` has the same signature apart from accepting
 * a `SelectItem` in addition to an array on v13 — hence the `toArray()` below,
 * which is precisely what v13's `addTcaSelectItem()` does with a `SelectItem`
 * it is handed.
 *
 * The body is therefore a line by line reproduction of v13.4's
 * `ExtensionManagementUtility::addRecordType()`, using the `SelectItem` class of
 * whichever core is running, so each version produces the array *that* version's
 * core would have produced. `Tests/Functional/ThemeContentTypeRegistrationTest`
 * is the gate that holds it to that on both versions.
 *
 * @todo Delete this class and call
 *       `ExtensionManagementUtility::addRecordType()` directly in the ten
 *       `Configuration/TCA/Overrides/tt_content_theme_*.php` files as soon as
 *       support for TYPO3 v12 is dropped.
 */
#[Exclude]
final class ContentTypeRegistration
{
    /**
     * Adds a record type to a TCA table that has a type field configured.
     *
     * Same argument list, same order and the same defaults as v13.4's
     * `ExtensionManagementUtility::addRecordType()`, so dropping v12 support is
     * a search and replace of the call target and nothing else.
     *
     * @param array<string, mixed> $item     the select item: `label`, `value`, and optionally
     *                                       `icon`, `group` and `description`
     * @param string               $showItemList the `showitem` string of the new type
     * @param array<string, mixed> $additionalTypeInformation further `types` keys, e.g. `columnsOverrides`
     * @param string               $position `before:<value>` / `after:<value>`, empty to append
     * @param string               $table    the TCA table, defaults to `tt_content`
     */
    public static function addRecordType(
        array $item,
        string $showItemList,
        array $additionalTypeInformation = [],
        string $position = '',
        string $table = 'tt_content',
    ): void {
        $selectItem = SelectItem::fromTcaItemArray($item);

        $typeField = $GLOBALS['TCA'][$table]['ctrl']['type'] ?? null;
        if (!is_string($typeField) || $typeField === '') {
            throw new \RuntimeException(
                'Cannot add record type "' . (string)$selectItem->getValue() . '" for TCA table "' . $table
                . '" without type field defined.',
                1755424460
            );
        }

        // A record type is keyed by its value in both "types" and
        // "typeicon_classes", so a missing or non string value would silently
        // produce a type nobody can address. The core takes the value as it
        // comes; this guard is the one deliberate deviation, and it exists
        // because the value is used as an array key twice below.
        $recordType = $selectItem->getValue();
        if (!is_string($recordType) || $recordType === '') {
            throw new \RuntimeException(
                'Cannot add record type for TCA table "' . $table . '" without a non empty string "value".',
                1755424461
            );
        }

        // Register the type icon. Guarded exactly like the core does it: an
        // item without an icon must not write an empty "typeicon_classes"
        // entry, because that would shadow the table's default icon.
        if ($selectItem->getIcon()) {
            $GLOBALS['TCA'][$table]['ctrl']['typeicon_classes'][$recordType] = $selectItem->getIcon();
        }

        // An item without a group would end up outside every group of the
        // select field, and - on v13 - in a wizard group named after nothing.
        if (!$selectItem->hasGroup()) {
            $selectItem = $selectItem->withGroup('default');
        }

        // "$position" is one string ("after:textpic"), while addTcaSelectItem()
        // takes the two halves in the opposite order.
        $relativeInformation = GeneralUtility::trimExplode(':', $position, true, 2);
        ExtensionManagementUtility::addTcaSelectItem(
            $table,
            $typeField,
            $selectItem->toArray(),
            $relativeInformation[1] ?? '',
            $relativeInformation[0] ?? ''
        );

        $showItemList = trim($showItemList, ', ');
        // The "extended" tab is appended so other extensions adding fields to
        // this type land in a tab of their own rather than at the end of the
        // last tab this extension defined.
        if ($showItemList !== ''
            && !str_contains($showItemList, '--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended')
        ) {
            $showItemList .= ',--div--;LLL:EXT:core/Resources/Private/Language/Form/locallang_tabs.xlf:extended';
        }
        if ($showItemList !== '') {
            $showItemList .= ',';
        }

        $additionalTypeInformation['showitem'] = $showItemList;
        $GLOBALS['TCA'][$table]['types'][$recordType] = $additionalTypeInformation;
    }
}
