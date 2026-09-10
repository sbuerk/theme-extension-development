# Development site package

Composer package `tests/dev-site`, extension key `tests_dev_site`.

A **development aid** for the two instances `instance-core-12/` and
`instance-core-13/`. It is never released, never installed anywhere else, and
excluded from every distribution by the `export-ignore` of `/packages-dev` in
`.gitattributes`.

It carries the seed set the instances are built from,
`Configuration/DataFactory/theme-instance/`: the showcase of the extension
(`theme-demo`, pulled in through `imports`), a second tree below `/legacy/`
that is delivered through a root `sys_template` record instead of the site
set, and the backend and frontend accounts of an instance
(`Accounts.yaml`).

TYPO3 v12 has no site sets, so the v12 instance is built from
`Configuration/DataFactory/theme-instance-core12/`: `theme-instance` plus a
root `sys_template` record on page 1, which delivers the `/` tree there.

It also carries the command `dev-site:seed-state`, which records in
`sys_registry` that an instance was seeded and answers whether it was.

Neither is used by hand. `ddev start` runs `composer system:setup`, which
installs TYPO3 and imports the set of its instance when either is missing;
`composer system:reseed` rebuilds an instance from nothing.

`ScenarioLegacy.yaml` and `ReferencesLegacy.yaml` are **generated** by
`Build/Scripts/generateLegacyScenario.php` from the showcase. Edit the showcase
and re-run the script; never edit the generated files.

The functional tests load this package by its composer name through
`sbuerk/fixture-packages`, which scans `packages-dev/*` as well as the fixture
extensions below `Tests/`.

See [Seeding](../../docs/development/seeding.md) and
[Development instances](../../docs/development/instances.md).
