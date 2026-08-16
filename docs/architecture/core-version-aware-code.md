# Core version aware code

The extension is built to serve more than one TYPO3 major version from a single
code base. Code that cannot be written for all of them at once is **core version
aware**: it exists once per supported core version, and only the variant matching
the running TYPO3 version is used.

That structure does not depend on how many versions there happen to be. One
`Core<major>/` directory exists per supported core version, and today there is
exactly one of them.

## Where the code lives

| Directory  | Contains                                                                                                                |
|------------|-------------------------------------------------------------------------------------------------------------------------|
| `Classes/` | Everything working on **all** supported core versions: interfaces, abstract base classes, version independent services. |
| `Core13/`  | Implementations for TYPO3 v13 only.                                                                                     |

`Core13/` is a separate PSR-4 root in the repository root, not a subdirectory of
`Classes/`, and is registered in `composer.json` with the core version as the
third namespace part — one entry per supported core version:

```json
"autoload": {
    "psr-4": {
        "SBUERK\\ThemeExtensionDevelopment\\": "Classes/",
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
running core is a different major.

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
   directory — `Core13/` today — each registering itself as the default
   implementation of the interface with `#[AsAlias]`:

   ```php
   #[AsAlias(id: ExampleInterface::class, public: true)]
   final readonly class Example extends AbstractExample
   {
   }
   ```

See [`Classes/Example/`](../../Classes/Example) and
[`Core13/Example/`](../../Core13/Example) for the complete example shipped with
this extension.

## Configuration is the exception

The `Core<major>/` split works for classes, because the container picks one of
them. Configuration files — TCA, TypoScript, `ext_localconf.php` — are loaded by
TYPO3 from a fixed path and cannot be split that way. A version difference there
is resolved **in the file**, by building the array and adjusting the finished
result before returning it.

Three things make that acceptable where a conditional in a class would not be:

- The difference sits **at the end of the file**, applied to the finished array,
  not scattered through it. The configuration stays readable as one thing.
- It carries a `@todo` naming the condition under which it goes away. A version
  switch without an exit condition becomes permanent.
- It names the **changelog issue**, so the reason can be looked up. The
  changelogs ship with `typo3/cms-core` in `Documentation/Changelog/` — verify
  against them rather than from memory.

Two further rules follow from experience rather than from the mechanism:

- **Guarding an option is not the same as dropping it.** If one version ignores
  a key and another evaluates it, removing the key changes behaviour on the
  second one, silently and without an error anywhere. Guard, do not delete.
- **Look for the spelling that is correct everywhere first.** Where one call is
  valid on every supported version, that beats any switch —
  [`ext_localconf.php`](../../Tests/Functional/Fixtures/Extensions/example-fixture/ext_localconf.php)
  of the [fixture extension](../testing/fixture-extensions.md) passes
  `PLUGIN_TYPE_CONTENT_ELEMENT` explicitly for exactly that reason, and so needs
  no version switch at all.

> [!NOTE]
> There is currently **no worked example of a version conditional in the
> repository**. With a single supported core version there is no difference to
> resolve, so the last one — a `searchFields` guard in the fixture extension's
> TCA — was made unconditional rather than left standing as decoration. The rule
> is documented here because it applies the moment a second version is
> supported, not because something in the tree demonstrates it today.

## Tooling and tests

- **PHPStan** is configured per core version, one directory below
  `Build/phpstan/` each. A configuration analyses only its own core version aware
  sources — `Build/phpstan/Core13/phpstan.neon` lists `Classes`, `Configuration`,
  `Core13` and `Tests` — because a directory written for a different core version
  uses API that does not exist here and would report nothing but false positives.
  A second supported version gets its own configuration beside this one rather
  than a widened path list in it. See
  [Quality gates](../development/quality-gates.md).
- **Tests** mirror the same layout: `Tests/Unit/Core13/` and
  `Tests/Functional/Core13/`, one such directory per supported core version. A
  core version aware test class carries the PHPUnit group of the core versions it
  must **not** run on:

  ```php
  #[Group('not-core-<version>')]
  final class ExampleTest extends UnitTestCase
  {
  }
  ```

  `Build/Scripts/runTests.sh` passes `--exclude-group not-core-<version>` for the
  selected core version, so those tests are skipped automatically elsewhere. With
  one supported version there is nothing to exclude and no test carries a group.
- Every supported core version must be verified before opening a pull request,
  each after its own `composerUpdate` — see
  [Core version setup](../development/dual-core-setup.md) and
  [Pull requests](../workflow/pull-requests.md).

## See also

- [Dependency injection](dependency-injection.md)
- [Class design](class-design.md)
- [Core version setup](../development/dual-core-setup.md)
- [Functional tests](../testing/functional-tests.md)
