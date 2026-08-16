# Architecture

How the code base is organised and which design rules apply to it. These are the
rules this extension itself follows — the shipped `Example` classes exist to
demonstrate them and are meant to be deleted once real code arrives.

| Page                                                  | Contents                                                                                                                                                                            |
|-------------------------------------------------------|-------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------|
| [Core version aware code](core-version-aware-code.md) | `Classes/` vs `Core<major>/`, container based selection of the right variant, the interface + abstract + implementation pattern, the configuration exception.                       |
| [Dependency injection](dependency-injection.md)       | Symfony DI attributes instead of `Services.yaml`, stateless services, private by default, `#[AsAlias]`, non-shared services.                                                        |
| [Class design](class-design.md)                       | `final` with `readonly` properties and why the keyword is not on the class here, method injection in abstract classes, data objects vs services, the two accepted PHPStan ignores.  |
| [TypoScript delivery](typoscript-delivery.md)         | The site set and the classic static include — one delivery path per supported core version — why `addStaticFile()` sits in a TCA override, and page rendering with `FLUIDTEMPLATE`. |
| [Page rendering](page-rendering.md)                   | Why `FLUIDTEMPLATE` over `PAGEVIEW`, backend layout registration, template name resolution, content slots, the Fluid structure.                                                     |
| [Navigation](navigation.md)                           | Main menu, sub navigation and breadcrumb, the fixed-rootline `leveluid:1` resolution, placement by backend layout, accessibility.                                                   |
| [Content elements](content-elements.md)               | Classic `CType` coverage, why `table` needed a `DataProcessor`, `shortcut` recursion, escaping, a Fluid `&&` gotcha.                                                                |

## The short version

- One code base serves both supported TYPO3 versions — **v12.4 and v13.4**.
  Version differences are resolved by **splitting classes**, not by conditionals
  in shared code. Configuration is the one documented exception.
- Services are **stateless** and wired with **attributes on the class**, never
  with service definitions in `Services.php` or a `Services.yaml`.
- Services are **private** unless something really has to fetch them from the
  container.
- Classes are **`final`**, and every property they carry is **`readonly`**,
  unless a framework constraint prevents it. The keyword is on the properties
  rather than on the class because this branch supports PHP 8.1 —
  [why, and what that means for a backport](class-design.md#prefer-final-with-readonly-properties).
- Data is not a service: models, entities, value objects and DTOs are created,
  not injected — and they carry **`#[Exclude]`** so directory based service
  registration does not pick them up.

## See also

- [Documentation index](../Index.md)
- [Testing](../testing/Index.md)
- [Quality gates](../development/quality-gates.md)
