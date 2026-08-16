# Core version aware code

The extension is built to serve more than one TYPO3 major version from a single
code base. Code that cannot be written for all of them at once is **core version
aware**: it exists once per supported core version, and only the variant matching
the running TYPO3 version is used.

That structure does not depend on how many versions there happen to be. One
`Core<major>/` directory exists per supported core version, and today there are
two of them.

## Where the code lives

| Directory  | Contains                                                                                                                |
|------------|-------------------------------------------------------------------------------------------------------------------------|
| `Classes/` | Everything working on **all** supported core versions: interfaces, abstract base classes, version independent services. |
| `Core12/`  | Implementations for TYPO3 v12 only.                                                                                     |
| `Core13/`  | Implementations for TYPO3 v13 only.                                                                                     |

`Core12/` and `Core13/` are separate PSR-4 roots in the repository root, not
subdirectories of `Classes/`, and are registered in `composer.json` with the
core version as the third namespace part — one entry per supported core version:

```json
"autoload": {
    "psr-4": {
        "SBUERK\\ThemeExtensionDevelopment\\": "Classes/",
        "SBUERK\\ThemeExtensionDevelopment\\Core12\\": "Core12/",
        "SBUERK\\ThemeExtensionDevelopment\\Core13\\": "Core13/"
    }
}
```

## How the right variant is selected

Composer autoloads **all** of those directories — that is unavoidable and
harmless, as long as a class is never *instantiated* on the wrong core version.
The selection therefore happens in the dependency injection container:
[`Configuration/Services.php`](../../Configuration/Services.php) loads
`Classes/` unconditionally and, on top of it, only the `Core<major>/` directory
matching the running TYPO3 version:

```php
$coreMajorVersion = (new Typo3Version())->getMajorVersion();
$coreAwareDirectory = sprintf('%s/../Core%d', __DIR__, $coreMajorVersion);
if (is_dir($coreAwareDirectory)) {
    $services->load(
        sprintf('SBUERK\\ThemeExtensionDevelopment\\Core%d\\', $coreMajorVersion),
        $coreAwareDirectory . '/*',
    );
}
```

Because of that, a class below `Core13/` may freely use API that only exists in
TYPO3 v13 — it is never registered, and therefore never instantiated, when the
running core is a different major. The same holds in the other direction: a
class below `Core12/` may use API that v13 removed or deprecated.

This is deliberately the *only* mechanism used for version differences. Do not
write conditional code (`if ($coreMajorVersion === 13) { … }`) in shared classes
below `Classes/`; split the class instead. Shared code stays readable, and each
version aware implementation can be deleted as a whole once its core version is
dropped.

## The pattern to follow

1. Declare an **interface** in `Classes/` — consumers only ever type hint this.
2. Put shared behaviour into an **abstract base class** in `Classes/`. Abstract
   classes use method injection, see
   [Class design](class-design.md#abstract-classes-must-not-use-constructor-injection).
3. Implement it once per core version, in that version's `Core<major>/`
   directory, each registering itself as the default implementation of the
   interface with `#[AsAlias]`:

   ```php
   #[AsAlias(id: ExampleInterface::class, public: true)]
   final class Example extends AbstractExample
   {
   }
   ```

See [`Classes/Example/`](../../Classes/Example), [`Core12/Example/`](../../Core12/Example)
and [`Core13/Example/`](../../Core13/Example) for the demonstration pair shipped
with this extension.

> [!NOTE]
> The classes are `final`, not `final readonly`. This branch supports PHP 8.1
> for TYPO3 v12, where a `readonly` class does not parse, so the keyword sits on
> the properties instead. That is a property of the branch, not of the version
> aware layout.
> → [Class design](class-design.md#backports-from-main-readonly-moves-off-the-class)

### The worked example: `DuplicationBehavior`

The `Example` pair above demonstrates the mechanism. The
[seeder](../development/seeding.md) contains the real one, and it is the shape
every future split should copy.

`ResourceStorage::addFile()` takes a conflict mode, and its **type** changed
between the two supported versions. TYPO3 v13.0 introduced the native enum
`TYPO3\CMS\Core\Resource\Enum\DuplicationBehavior` (#101151, "Native
DuplicationBehavior enumeration"); on v12 the only spelling is the class
constant `TYPO3\CMS\Core\Resource\DuplicationBehavior::REPLACE`. Neither works on
both versions:

- The enum does not exist in `typo3/cms-core` 12.4 at all, so naming it there is
  a fatal error rather than a deprecation one could live with.
- The v12 constant is the string `'replace'`, and v13's `addFile()` answers
  anything that is not an instance of the enum with `E_USER_DEPRECATED`. The
  suites of this extension
  [fail on deprecations](../testing/phpunit-configuration.md#strictness-policy),
  so that turns the run red.

So this is code, and rule 1 applies in full:

| File                                                                                           | Is                                                       |
|------------------------------------------------------------------------------------------------|----------------------------------------------------------|
| [`Classes/Seeding/FileImporterInterface.php`](../../Classes/Seeding/FileImporterInterface.php) | The seam. One method, fully typed, no `mixed`.           |
| [`Core12/Seeding/FileImporter.php`](../../Core12/Seeding/FileImporter.php)                     | Uses the v12 class constant.                             |
| [`Core13/Seeding/FileImporter.php`](../../Core13/Seeding/FileImporter.php)                     | Uses the v13 enum.                                       |
| [`Classes/Seeding/FileSeeder.php`](../../Classes/Seeding/FileSeeder.php)                       | Type hints the interface and knows nothing about either. |

Two decisions in it are worth copying:

- **Only the operation is split, not the seeder.** The two implementations
  differ in one `use` statement and one argument. Everything else — resolving the
  storage, the target folder, the file name — stays in the shared seeder.
- **The interface models the operation, not the argument.** A method handing the
  conflict mode back to shared code would have to declare a `mixed` return: an
  enum case on one version, a string on the other. That is the version
  difference pushed back into `Classes/` in a shape neither the type system nor
  PHPStan can check. `addFileReplacingExisting()` puts the whole `addFile()`
  call inside the implementation instead, so each version's argument type is
  concrete.

## Configuration is the exception

The `Core<major>/` split works for classes, because the container picks one of
them. Configuration files — TCA, TypoScript, page TSconfig, `ext_localconf.php`,
`ext_tables.sql` — are loaded by TYPO3 from a fixed path and cannot be split
that way. A version difference there is resolved **in the file**.

Three things make that acceptable where a conditional in a class would not be:

- The difference sits **at the end of the file**, applied to the finished array
  or wrapped in a single condition block, not scattered through it. The
  configuration stays readable as one thing.
- It carries a `@todo` naming the condition under which it goes away. A version
  switch without an exit condition becomes permanent.
- It names the **changelog issue**, so the reason can be looked up. The
  changelogs ship with `typo3/cms-core` in `Documentation/Changelog/` — verify
  against them rather than from memory.

### The worked example: the new content element wizard

[`Configuration/PageTsConfig/NewContentElementWizard.tsconfig`](../../Configuration/PageTsConfig/NewContentElementWizard.tsconfig)
is the version conditional this repository ships, and it has all three
properties.

TYPO3 v13.0 builds the "new content element" wizard from the TCA (#102834,
"Auto-registration of New Content Element Wizard via TCA"): every `CType` item
becomes a wizard entry, so the ten registrations in
`Configuration/TCA/Overrides/tt_content_theme_*.php` produce the wizard as a
side effect. v12 has none of that — its
`NewContentElementController::getWizards()` reads
`mod.wizards.newContentElement.wizardItems` out of page TSconfig and nothing
else. Without the file the ten theme types are selectable in the `CType`
dropdown of an existing element but cannot be *created*, which is the only way
an editor ever reaches them.

The whole difference is one condition, in one file, imported unconditionally
from `Configuration/page.tsconfig`:

```typoscript
[typo3.branch == "12.4"]
    mod.wizards.newContentElement.wizardItems {
        …
    }
[END]
```

Two things about it that were established rather than assumed:

- **`typo3.branch` is available in a page TSconfig condition.** It comes from
  `Core\ExpressionLanguage\DefaultProvider`, which is registered for the
  `typoscript` context that page TSconfig conditions resolve in as well, and it
  returns `Typo3Version::BRANCH` — `"12.4"` and `"13.4"` for the two versions
  this extension supports. A branch literal is exact rather than clever here,
  because `^12.4.22 || ^13.4` cannot resolve to any other v12 branch.
- **Leaving the block switched on for v13 too would not be harmless.** v13 does
  merge a page TSconfig entry with the TCA generated one rather than duplicating
  it, but the page TSconfig title, description and icon *win*, and
  `types.<value>.creationOptions` is dropped along with the TCA entry. Two
  sources of truth for one wizard entry, of which only one is read — that is
  what the condition avoids, not a duplicate tile.

Two further rules follow from experience rather than from the mechanism:

- **Guarding an option is not the same as dropping it.** If one version ignores
  a key and another evaluates it, removing the key changes behaviour on the
  second one, silently and without an error anywhere. Guard, do not delete.
- **Look for the spelling that is correct everywhere first.** Where one call is
  valid on every supported version, that beats any switch. Two files in this
  repository exist because of that rule:
  - [`Classes/Compatibility/ContentTypeRegistration.php`](../../Classes/Compatibility/ContentTypeRegistration.php)
    replaces `ExtensionManagementUtility::addRecordType()`, which v12.4 does not
    have, at all ten theme content type registrations — and it contains **no
    version switch at all**. It reproduces what v13's method does using
    `SelectItem` and `addTcaSelectItem()`, both of which exist unchanged on both
    versions, so each core produces the array that core would have produced. A
    delegating branch would have needed a PHPStan baseline entry on the v12 leg,
    which is a defect here.
  - the [fixture extension](../testing/fixture-extensions.md)'s
    [`ext_localconf.php`](../../Tests/Functional/Fixtures/Extensions/example-fixture/ext_localconf.php)
    passes `PLUGIN_TYPE_CONTENT_ELEMENT` explicitly, which is correct on v12 and
    v13 alike, and so needs no version switch either.

### The third: `ext_localconf.php`

[`ext_localconf.php`](../../ext_localconf.php) is one
`if ((new Typo3Version())->getMajorVersion() < 13)` block that repairs two
things `EXT:frontend` does on v13 and not on v12 — `lib.parseFunc` /
`lib.parseFunc_RTE` (#103485, v13.2) and the `defaultContentRendering` gate that
makes an Extbase plugin renderable at all. It is the exception for the same
reason as the wizard TSconfig: TYPO3 loads the file from a fixed path, under
exactly one name, before a container exists.

Three details of it are worth carrying to the next file of this kind:

- **The version test is `< 13`, not `=== 12`.** The question is "is this older
  than the version that brought the feature", not "is this exactly v12".
- **It uses the same selector expression** as `Configuration/Services.php` and
  `Tests/Functional/ThemeSiteTrait`, so the three read as one mechanism rather
  than three ideas.
- **The TypoScript it registers is copied, not rewritten.**
  `Configuration/TypoScript/Compatibility/Core12/ParseFunc.typoscript` is v13.4's
  own block byte for byte, so the two versions parse rich text identically
  rather than similarly.

The full reasoning, including why v13 never reaches the `contentRenderingTemplates`
gate, is in
[TypoScript delivery](typoscript-delivery.md#what-v12-does-not-give-for-free-ext_localconfphp).

### The other configuration exception: `ext_tables.sql`

[`ext_tables.sql`](../../ext_tables.sql) is the third shape the exception takes,
and the odd one: the file is **not** version aware, it exists *because* of a
version difference.

TYPO3 v13.0 derives a database column from every TCA `columns` entry (#101553,
extended by #104311 in 13.3 for the `ctrl` derived columns). v12.4's
`DefaultTcaSchema` does not — it only derives the management columns, the
`category|datetime|slug|json|uuid` types and MM tables, with no branch for
`input`, `text`, `link`, `file` or `inline`. Without an explicit definition the
`tx_theme_list_item` table and the four `tx_theme_*` columns on `tt_content` are
simply never created on v12, and every theme element using them fails.

#101553 states that an explicit `ext_tables.sql` definition takes precedence
over the derived one, so **one file serves both versions** and nothing in it is
conditional. Its definitions were not written by hand either: they reproduce,
column for column, what v13's own schema analyzer derives from this extension's
TCA, so the analyzer stays quiet on both versions.
`Tests/Functional/DatabaseSchemaTest` is the gate that holds it to that.

The consequence for day to day work: **on this branch a TCA change can be a
schema change that only fails on v12.** Adding a column to a theme element means
adding it to `ext_tables.sql` too.

## Tooling and tests

- **PHPStan** is configured per core version, one directory below
  `Build/phpstan/` each. A configuration analyses only its own core version aware
  sources — `Build/phpstan/Core12/phpstan.neon` lists `Classes`, `Configuration`,
  `Core12` and `Tests` and excludes `Tests/*/Core13`, and `Core13/phpstan.neon`
  is the mirror image — because a directory written for a different core version
  uses API that does not exist here and would report nothing but false positives.
  The two configurations also run different PHPStan majors, see
  [Dual core setup](../development/dual-core-setup.md#the-dependency-sets-differ-by-more-than-the-core).
  See [Quality gates](../development/quality-gates.md).
- **Tests** mirror the same layout: `Tests/Unit/Core12/`, `Tests/Unit/Core13/`,
  `Tests/Functional/Core12/` and `Tests/Functional/Core13/`, one such directory
  per supported core version. A core version aware test class carries the PHPUnit
  group of the core versions it must **not** run on:

  ```php
  #[Group('not-core-12')]
  final class ExampleTest extends UnitTestCase
  {
  }
  ```

  `Build/Scripts/runTests.sh` passes `--exclude-group not-core-<version>` for the
  selected core version, so those tests are skipped automatically elsewhere.
- Both core versions must be verified before opening a pull request, each after
  its own `composerUpdate` — see
  [Dual core setup](../development/dual-core-setup.md) and
  [Pull requests](../workflow/pull-requests.md).

## See also

- [Dependency injection](dependency-injection.md)
- [Class design](class-design.md)
- [Dual core setup](../development/dual-core-setup.md)
- [Functional tests](../testing/functional-tests.md)
