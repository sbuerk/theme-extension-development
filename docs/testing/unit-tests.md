# Unit tests

Unit tests live below [`Tests/Unit/`](../../Tests/Unit) and extend
`TYPO3\TestingFramework\Core\Unit\UnitTestCase`. They run without a database and
without a TYPO3 instance.

## Running

```bash
# Unit tests.
Build/Scripts/runTests.sh -s unit

# Unit tests in random order (add "-o <seed>" to replay a specific order).
Build/Scripts/runTests.sh -s unitRandom

# A single class or method.
Build/Scripts/runTests.sh -s unit -- --filter DummyTest
```

`unitRandom` exists to catch tests that depend on execution order. When it fails,
the output contains the seed; replay it with `-o <seed>` to reproduce.

Remember to run every supported core version, each after its own
`composerUpdate` — see [Dual core setup](../development/dual-core-setup.md).

## Conventions

- Test classes are `final` and named `<SubjectUnderTest>Test`.
- Test methods carry the PHPUnit `#[Test]` attribute and must **not** be
  prefixed with `test` — enforced by the `checkTestMethodsPrefix` gate:

  ```php
  #[Test]
  public function getExtensionKeyReturnsExtensionKey(): void
  {
      // ...
  }
  ```

- Method names describe the expected behaviour, not the mechanics:
  `exampleReturnsCoreVersionAwareValue()`, not `testExample()`.
- Every test asserts something. A test without an assertion is risky and
  therefore a failure. When the behaviour under test is "this does not throw",
  say so with `self::expectNotToPerformAssertions()` instead of leaving the
  method bare — see
  [PHPUnit configuration](phpunit-configuration.md#strictness-policy).
- Nothing is written to the output. A leftover `var_dump()` or `echo` makes the
  test risky and fails the run.
- Data providers are `public static` and return a `\Generator` with named keys,
  so a failing case is identifiable in the output:

  ```php
  public static function expectedLoadedExtensionIdentifiers(): \Generator
  {
      yield 'composer package name: sbuerk/theme-extension-development' => ['identifier' => 'sbuerk/theme-extension-development'];
      yield 'extension key: theme_extension_development' => ['identifier' => 'theme_extension_development'];
  }
  ```

## Core version aware unit tests

Tests for classes below a `Core<major>/` directory mirror that layout in
`Tests/Unit/Core12/` and `Tests/Unit/Core13/`, and carry the group of every core
version they must **not** run on:

```php
#[Group('not-core-12')]
final class ExampleTest extends UnitTestCase
{
}
```

The two `Example` test classes are mirror images of each other for that reason:
`Tests/Unit/Core13/Example/ExampleTest` carries `#[Group('not-core-12')]` and
`Tests/Unit/Core12/Example/ExampleTest` carries `#[Group('not-core-13')]`, so
each runs on exactly the version whose implementation it instantiates.
See [Dual core setup](../development/dual-core-setup.md#test-grouping).

[`Tests/Unit/VersionCompatTest`](../../Tests/Unit/VersionCompatTest.php) is the
guard underneath all of it: it asserts that a run with `-t 12` really is v12 and
a run with `-t 13` really is v13, so a stale `.Build/` cannot produce a green
suite that proved nothing. That test is **never removed** —
see [the two tests that must never be dropped](Index.md#the-two-tests-that-must-never-be-dropped).

## Testing classes with injected dependencies

A class using `#[Required]` method injection is constructed and injected by hand
in a unit test — there is no container:

```php
$subject = new Example();
$subject->injectTypo3Version(new Typo3Version());

$this->assertSame('Example implementation for TYPO3 v13', $subject->example());
```

If wiring itself is what needs verification, that belongs in a
[functional test](functional-tests.md), where the real container is available.

## See also

- [PHPUnit configuration](phpunit-configuration.md)
- [Functional tests](functional-tests.md)
- [Class design](../architecture/class-design.md)
