# Development site package

Composer package `tests/dev-site`, extension key `tests_dev_site`.

A **development aid** for the two instances `instance-core-12/` and
`instance-core-13/`. It is never released, never installed anywhere else, and
excluded from every distribution by the `export-ignore` of `/packages-dev` in
`.gitattributes`.

It carries the seed set the instances are built from,
`Configuration/DataFactory/theme-instance/`: the showcase of the extension
(`theme-demo`, pulled in through `imports`) plus a second tree below
`/legacy/` that is delivered through a root `sys_template` record instead of
the site set.

TYPO3 v12 has no site sets, so the v12 instance is built from
`Configuration/DataFactory/theme-instance-core12/`: `theme-instance` plus a
root `sys_template` record on page 1, which delivers the `/` tree there.

```bash
cd instance-core-13
ddev exec vendor/bin/typo3 data-factory:import theme-instance
cd ../instance-core-12
ddev exec vendor/bin/typo3 data-factory:import theme-instance-core12
```

`ScenarioLegacy.yaml` and `ReferencesLegacy.yaml` are **generated** by
`Build/Scripts/generateLegacyScenario.php` from the showcase. Edit the showcase
and re-run the script; never edit the generated files.

The functional tests load this package by its composer name through
`sbuerk/fixture-packages`, which scans `packages-dev/*` as well as the fixture
extensions below `Tests/`.

See [Seeding](../../docs/development/seeding.md) and
[Development instances](../../docs/development/instances.md).
